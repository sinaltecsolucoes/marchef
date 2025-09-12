<?php
// /src/Faturamento/FaturamentoRepository.php
namespace App\Faturamento;

use PDO;
use App\Core\AuditLoggerService;

class FaturamentoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Busca todos os itens de uma Ordem de Expedição e os agrupa para o faturamento.
     * @param int $ordemId
     * @return array
     */
    public function getDadosAgrupadosPorOrdemExpedicao(int $ordemId): array
    {
        $sql = "
            SELECT
                -- Nível 1: FAZENDA (Cliente do Lote)
                fazenda.ent_codigo AS fazenda_id, -- CORRIGIDO DE ent_id PARA ent_codigo
                COALESCE(fazenda.ent_nome_fantasia, fazenda.ent_razao_social) AS fazenda_nome,
                
                -- Nível 2: CLIENTE (Comprador)
                cliente.ent_codigo AS cliente_id, -- CORRIGIDO DE ent_id PARA ent_codigo
                COALESCE(cliente.ent_nome_fantasia, cliente.ent_razao_social) AS cliente_nome,
                
                -- Nível 3: PEDIDO
                oep.oep_numero_pedido,
                
                -- Nível 4: PRODUTO
                p_sec.prod_codigo AS produto_id,
                p_sec.prod_descricao AS produto_descricao,
                
                -- Nível 5: LOTE
                lnh.lote_id,
                lnh.lote_completo_calculado,
                
                -- Dados Agregados
                SUM(oei.oei_quantidade) AS total_caixas,
                SUM(oei.oei_quantidade * p_sec.prod_peso_embalagem) AS total_quilos
            
            FROM tbl_ordens_expedicao_itens oei
            
            JOIN tbl_ordens_expedicao_pedidos oep ON oei.oei_pedido_id = oep.oep_id
            JOIN tbl_entidades cliente ON oep.oep_cliente_id = cliente.ent_codigo
            
            JOIN tbl_estoque_alocacoes ea ON oei.oei_alocacao_id = ea.alocacao_id
            JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
            JOIN tbl_produtos p_sec ON lne.item_emb_prod_sec_id = p_sec.prod_codigo
            JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
            
            LEFT JOIN tbl_entidades fazenda ON lnh.lote_cliente_id = fazenda.ent_codigo
            
            WHERE oep.oep_ordem_id = :ordem_id
            
            GROUP BY
                fazenda.ent_codigo, -- CORRIGIDO
                cliente.ent_codigo, -- CORRIGIDO
                oep.oep_numero_pedido,
                p_sec.prod_codigo,
                lnh.lote_id
                
            ORDER BY
                fazenda_nome,
                cliente_nome,
                oep.oep_numero_pedido,
                produto_descricao
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ordem_id' => $ordemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gera e salva um novo resumo de faturamento a partir de uma Ordem de Expedição.
     * @param int $ordemId
     * @param int $usuarioId
     * @return int O ID do novo resumo de faturamento criado.
     * @throws \Exception
     */
    public function salvarResumo(int $ordemId, int $usuarioId): int
    {
        // 1. Prevenção de duplicatas: Verifica se já existe um resumo para esta Ordem
        $stmtCheck = $this->pdo->prepare("SELECT fat_id FROM tbl_faturamento_resumos WHERE fat_ordem_expedicao_id = :ordem_id");
        $stmtCheck->execute([':ordem_id' => $ordemId]);
        if ($stmtCheck->fetch()) {
            throw new \Exception("Já existe um resumo de faturamento gerado para esta Ordem de Expedição.");
        }

        // 2. Busca os dados agrupados que vamos salvar
        $itensAgrupados = $this->getDadosAgrupadosPorOrdemExpedicao($ordemId);
        if (empty($itensAgrupados)) {
            throw new \Exception("Não há itens nesta Ordem de Expedição para gerar um resumo.");
        }

        // 3. Inicia uma transação para garantir a integridade dos dados
        $this->pdo->beginTransaction();
        try {
            // 4. Insere o cabeçalho do resumo
            $sqlHeader = "INSERT INTO tbl_faturamento_resumos (fat_ordem_expedicao_id, fat_usuario_id, fat_status) 
                          VALUES (:ordem_id, :usuario_id, 'EM ELABORAÇÃO')";
            $stmtHeader = $this->pdo->prepare($sqlHeader);
            $stmtHeader->execute([
                ':ordem_id' => $ordemId,
                ':usuario_id' => $usuarioId
            ]);
            $novoResumoId = (int) $this->pdo->lastInsertId();

            // 5. Prepara a query para inserir os itens
            $sqlItens = "INSERT INTO tbl_faturamento_itens 
                            (fati_resumo_id, fati_fazenda_id, fati_cliente_id, fati_numero_pedido, fati_produto_id, fati_lote_id, fati_qtd_caixas, fati_qtd_quilos) 
                         VALUES 
                            (:resumo_id, :fazenda_id, :cliente_id, :numero_pedido, :produto_id, :lote_id, :qtd_caixas, :qtd_quilos)";
            $stmtItens = $this->pdo->prepare($sqlItens);

            // 6. Loop para inserir cada item agrupado
            foreach ($itensAgrupados as $item) {
                $stmtItens->execute([
                    ':resumo_id' => $novoResumoId,
                    ':fazenda_id' => $item['fazenda_id'],
                    ':cliente_id' => $item['cliente_id'],
                    ':numero_pedido' => $item['oep_numero_pedido'],
                    ':produto_id' => $item['produto_id'],
                    ':lote_id' => $item['lote_id'],
                    ':qtd_caixas' => $item['total_caixas'],
                    ':qtd_quilos' => $item['total_quilos']
                ]);
            }

            // 7. Se tudo correu bem, confirma a transação
            $this->pdo->commit();

            // Log de auditoria (opcional, mas bom ter)
            $this->auditLogger->log('CREATE', $novoResumoId, 'tbl_faturamento_resumos', null, ['ordem_id' => $ordemId]);

            return $novoResumoId;

        } catch (\Exception $e) {
            // 8. Se algo deu errado, desfaz tudo
            $this->pdo->rollBack();
            throw $e; // Re-lança a exceção para ser capturada pelo controller
        }
    }

    /**
     * Busca os detalhes de um item de faturamento específico.
     * @param int $fatiId
     * @return array|null
     */
    public function findItemDetalhes(int $fatiId): ?array
    {
        // Precisamos dos nomes (descrições) para exibir no modal
        $sql = "SELECT 
                    f.*,
                    p.prod_descricao,
                    lnh.lote_completo_calculado
                FROM tbl_faturamento_itens f
                JOIN tbl_produtos p ON f.fati_produto_id = p.prod_codigo
                JOIN tbl_lotes_novo_header lnh ON f.fati_lote_id = lnh.lote_id
                WHERE f.fati_id = :fati_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fati_id' => $fatiId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualiza os dados de preço e observação de um item de faturamento.
     * @param int $fatiId
     * @param array $data
     * @return bool
     */
    public function updateItem(int $fatiId, array $data): bool
    {
        $dadosAntigos = $this->findItemDetalhes($fatiId);

        $sql = "UPDATE tbl_faturamento_itens SET
                    fati_preco_unitario = :preco,
                    fati_preco_unidade_medida = :unidade,
                    fati_observacao = :observacao
                WHERE fati_id = :fati_id";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':preco' => $data['fati_preco_unitario'] ?: null,
            ':unidade' => $data['fati_preco_unidade_medida'],
            ':observacao' => $data['fati_observacao'] ?: null,
            ':fati_id' => $fatiId
        ]);

        $this->auditLogger->log('UPDATE', $fatiId, 'tbl_faturamento_itens', $dadosAntigos, $data);
        return $success;
    }

    /**
     * Busca os itens de um resumo de faturamento já salvo para exibição.
     * @param int $resumoId
     * @return array
     */
    public function getResumoSalvo(int $resumoId): array
    {
        $sql = "
            SELECT
                f.*, -- Todos os dados do item de faturamento, incluindo o fati_id
                fazenda.ent_nome_fantasia AS fazenda_nome,
                cliente.ent_nome_fantasia AS cliente_nome,
                oep.oep_numero_pedido,
                p.prod_descricao AS produto_descricao,
                lnh.lote_completo_calculado
            FROM tbl_faturamento_itens f
            JOIN tbl_entidades fazenda ON f.fati_fazenda_id = fazenda.ent_codigo
            JOIN tbl_entidades cliente ON f.fati_cliente_id = cliente.ent_codigo
            JOIN tbl_produtos p ON f.fati_produto_id = p.prod_codigo
            JOIN tbl_lotes_novo_header lnh ON f.fati_lote_id = lnh.lote_id
            -- O JOIN com oep é opcional se o número do pedido já estiver na tabela de itens
            LEFT JOIN tbl_ordens_expedicao_pedidos oep ON cliente.ent_codigo = oep.oep_cliente_id AND lnh.lote_id = (SELECT lne.item_emb_lote_id FROM tbl_lotes_novo_embalagem lne JOIN tbl_estoque_alocacoes ea ON lne.item_emb_id=ea.alocacao_lote_item_id JOIN tbl_ordens_expedicao_itens oei ON ea.alocacao_id=oei.oei_alocacao_id WHERE oei.oei_pedido_id=oep.oep_id LIMIT 1)

            WHERE f.fati_resumo_id = :resumo_id
            ORDER BY
                fazenda_nome,
                cliente_nome,
                oep.oep_numero_pedido,
                produto_descricao
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':resumo_id' => $resumoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todos os Resumos de Faturamento para a DataTable.
     */
    public function findAllForDataTable(array $params): array
    {
        $baseQuery = "FROM tbl_faturamento_resumos fr
                      JOIN tbl_usuarios u ON fr.fat_usuario_id = u.usu_codigo
                      JOIN tbl_ordens_expedicao_header oeh ON fr.fat_ordem_expedicao_id = oeh.oe_id";

        $totalRecords = $this->pdo->query("SELECT COUNT(fr.fat_id) FROM tbl_faturamento_resumos fr")->fetchColumn();

        $sqlData = "SELECT 
                        fr.fat_id,
                        oeh.oe_numero AS ordem_numero,
                        fr.fat_data_geracao,
                        fr.fat_status,
                        u.usu_nome AS usuario_nome
                    $baseQuery 
                    ORDER BY fr.fat_id DESC
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalRecords, // Simplificado
            "data" => $data
        ];
    }
}