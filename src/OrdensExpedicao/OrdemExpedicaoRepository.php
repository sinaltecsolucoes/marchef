<?php
// /src/OrdensExpedicao/OrdemExpedicaoRepository.php
namespace App\OrdensExpedicao;

use PDO;
use App\Core\AuditLoggerService;

class OrdemExpedicaoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Busca todas as Ordens de Expedição para a DataTable.
     */
    public function findAllForDataTable(array $params): array
    {
        $baseQuery = "FROM tbl_ordens_expedicao_header oe
                      JOIN tbl_usuarios u ON oe.oe_usuario_id = u.usu_codigo";

        $totalRecords = $this->pdo->query("SELECT COUNT(oe.oe_id) FROM tbl_ordens_expedicao_header oe")->fetchColumn();

        $sqlData = "SELECT 
                        oe.oe_id,
                        oe.oe_numero,
                        oe.oe_data,
                        oe.oe_status,
                        u.usu_nome AS usuario_nome
                    $baseQuery 
                    ORDER BY oe.oe_data DESC, oe.oe_id DESC
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalRecords, // Simplificado por agora
            "data" => $data
        ];
    }

    public function getNextOrderNumber(): string
    {
        // CORREÇÃO: A consulta agora busca o número máximo na própria tabela de ordens de expedição.
        $stmt = $this->pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(oe_numero, '.', 1) AS UNSIGNED)) FROM tbl_ordens_expedicao_header");
        $lastNum = ($stmt->fetchColumn() ?: 0) + 1;
        $sequence = str_pad($lastNum, 4, '0', STR_PAD_LEFT);

        $datePart = date('m.Y');
        return $sequence . '.' . $datePart;
    }

    public function createHeader(array $data, int $usuarioId): int
    {
        $sql = "INSERT INTO tbl_ordens_expedicao_header (oe_numero, oe_data, oe_usuario_id) 
                VALUES (:numero, :data, :usuario_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':numero' => $data['oe_numero'],
            ':data' => $data['oe_data'],
            ':usuario_id' => $usuarioId
        ]);
        $newId = (int) $this->pdo->lastInsertId();
        $this->auditLogger->log('CREATE', $newId, 'tbl_ordens_expedicao_header', null, $data);
        return $newId;
    }

    /*    public function findOrdemCompleta(int $id): ?array
     {
         $ordem = [];
         // 1. Busca o cabeçalho (sem alterações)
         $stmtHeader = $this->pdo->prepare("SELECT * FROM tbl_ordens_expedicao_header WHERE oe_id = :id");
         $stmtHeader->execute([':id' => $id]);
         $ordem['header'] = $stmtHeader->fetch(PDO::FETCH_ASSOC);

         if (!$ordem['header']) {
             return null;
         }

         // 2. Busca os pedidos/clientes (sem alterações)
         $stmtPedidos = $this->pdo->prepare(
             "SELECT p.*, e.ent_razao_social, end.end_uf
          FROM tbl_ordens_expedicao_pedidos p
          JOIN tbl_entidades e ON p.oep_cliente_id = e.ent_codigo
          LEFT JOIN tbl_enderecos end ON e.ent_codigo = end.end_entidade_id AND end.end_tipo_endereco = 'Principal'
          WHERE p.oep_ordem_id = :id 
          GROUP BY p.oep_id
          ORDER BY p.oep_id"
         );
         $stmtPedidos->execute([':id' => $id]);
         $ordem['pedidos'] = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

         // 3. Para cada pedido, busca os itens (CONSULTA TOTALMENTE REFEITA PARA BUSCAR TUDO)
         foreach ($ordem['pedidos'] as $key => $pedido) {
             $stmtItens = $this->pdo->prepare(
                 "SELECT 
                 i.oei_id,
                 i.oei_quantidade,
                 i.oei_observacao,
                 p_sec.prod_codigo_interno,
                 p_sec.prod_descricao,
                 p_prim.prod_peso_embalagem AS peso_primario,
                 p_sec.prod_peso_embalagem AS peso_secundario,
                 cam.camara_industria AS industria,
                 COALESCE(ent_lote.ent_nome_fantasia, ent_lote.ent_razao_social) AS cliente_lote_nome,
                 lnh.lote_completo_calculado
             FROM tbl_ordens_expedicao_itens i

             -- Joins para pegar os dados do item/produto
             JOIN tbl_estoque_alocacoes ea ON i.oei_alocacao_id = ea.alocacao_id
             JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
             JOIN tbl_produtos p_sec ON lne.item_emb_prod_sec_id = p_sec.prod_codigo

             -- Join para pegar o peso primário
             JOIN tbl_lotes_novo_producao lnp ON lne.item_emb_prod_prim_id = lnp.item_prod_id
             JOIN tbl_produtos p_prim ON lnp.item_prod_produto_id = p_prim.prod_codigo

             -- Joins para pegar os dados do lote
             JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
             LEFT JOIN tbl_entidades ent_lote ON lnh.lote_cliente_id = ent_lote.ent_codigo

             -- Joins para pegar a indústria da câmara
             JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
             JOIN tbl_estoque_camaras cam ON ee.endereco_camara_id = cam.camara_id

             WHERE i.oei_pedido_id = :pedido_id
             ORDER BY i.oei_id"
             );

             $stmtItens->execute([':pedido_id' => $pedido['oep_id']]);
             $ordem['pedidos'][$key]['itens'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
         }

         return $ordem;
     } */

    public function findOrdemCompleta(int $id): ?array
    {
        $ordem = [];
        // 1. Busca o cabeçalho
        $stmtHeader = $this->pdo->prepare("SELECT * FROM tbl_ordens_expedicao_header WHERE oe_id = :id");
        $stmtHeader->execute([':id' => $id]);
        $ordem['header'] = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$ordem['header']) {
            return null;
        }

        // 2. Busca os pedidos/clientes
        $stmtPedidos = $this->pdo->prepare(
            "SELECT p.*, COALESCE(e.ent_nome_fantasia, e.ent_razao_social) AS ent_razao_social, end.end_uf
          FROM tbl_ordens_expedicao_pedidos p
          JOIN tbl_entidades e ON p.oep_cliente_id = e.ent_codigo
          LEFT JOIN tbl_enderecos end ON e.ent_codigo = end.end_entidade_id AND end.end_tipo_endereco = 'Principal'
          WHERE p.oep_ordem_id = :id 
          GROUP BY p.oep_id
          ORDER BY p.oep_id"
        );
        $stmtPedidos->execute([':id' => $id]);
        $ordem['pedidos'] = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

        // 3. Para cada pedido, busca os itens
        foreach ($ordem['pedidos'] as $key => $pedido) {
            $stmtItens = $this->pdo->prepare(
                "SELECT 
                i.oei_id,
                i.oei_quantidade,
                i.oei_observacao,
                p_sec.prod_codigo_interno,
                p_sec.prod_descricao,
                p_prim.prod_peso_embalagem AS peso_primario,
                p_sec.prod_peso_embalagem AS peso_secundario,
                cam.camara_industria AS industria,
                COALESCE(ent_lote.ent_nome_fantasia, ent_lote.ent_razao_social) AS cliente_lote_nome,
                lnh.lote_completo_calculado,
                ee.endereco_completo
            FROM tbl_ordens_expedicao_itens i
            
            -- (O resto dos JOINs continua o mesmo)
            JOIN tbl_estoque_alocacoes ea ON i.oei_alocacao_id = ea.alocacao_id
            JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
            JOIN tbl_produtos p_sec ON lne.item_emb_prod_sec_id = p_sec.prod_codigo
            
            JOIN tbl_lotes_novo_producao lnp ON lne.item_emb_prod_prim_id = lnp.item_prod_id
            JOIN tbl_produtos p_prim ON lnp.item_prod_produto_id = p_prim.prod_codigo

            JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
            LEFT JOIN tbl_entidades ent_lote ON lnh.lote_cliente_id = ent_lote.ent_codigo

            JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
            JOIN tbl_estoque_camaras cam ON ee.endereco_camara_id = cam.camara_id

            WHERE i.oei_pedido_id = :pedido_id
            ORDER BY i.oei_id"
            );

            $stmtItens->execute([':pedido_id' => $pedido['oep_id']]);
            $ordem['pedidos'][$key]['itens'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
        }

        return $ordem;
    }

    public function addPedidoCliente(array $data): int
    {
        $sql = "INSERT INTO tbl_ordens_expedicao_pedidos (oep_ordem_id, oep_cliente_id, oep_numero_pedido) 
                VALUES (:ordem_id, :cliente_id, :numero_pedido)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ordem_id' => $data['oep_ordem_id'],
            ':cliente_id' => $data['oep_cliente_id'],
            ':numero_pedido' => $data['oep_numero_pedido'] ?: null
        ]);
        $newId = (int) $this->pdo->lastInsertId();
        $this->auditLogger->log('CREATE', $newId, 'tbl_ordens_expedicao_pedidos', null, $data);
        return $newId;
    }

    public function findEstoqueAlocadoParaSelecao(array $params): array
    {
        $baseQuery = "FROM tbl_estoque_alocacoes ea
                      JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                      JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                      JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                      JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id";

        // Subquery para calcular o total já reservado em outras ordens
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) FROM tbl_ordens_expedicao_itens WHERE oei_alocacao_id = ea.alocacao_id)";

        // Apenas itens com saldo disponível (alocado > reservado)
        $whereClause = " WHERE ea.alocacao_quantidade > {$subQueryReservado}";

        $totalRecords = $this->pdo->query("SELECT COUNT(ea.alocacao_id) $baseQuery $whereClause")->fetchColumn();

        $sqlData = "SELECT 
                        ea.alocacao_id,
                        p.prod_descricao,
                        lnh.lote_completo_calculado,
                        ee.endereco_completo,
                        (ea.alocacao_quantidade - {$subQueryReservado}) AS saldo_disponivel
                    $baseQuery $whereClause 
                    ORDER BY p.prod_descricao, lnh.lote_completo_calculado
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalRecords,
            "data" => $data
        ];
    }

    public function addItemPedido(array $data): int
    {
        // Validações
        $pedidoId = filter_var($data['oei_pedido_id'], FILTER_VALIDATE_INT);
        $alocacaoId = filter_var($data['oei_alocacao_id'], FILTER_VALIDATE_INT);
        $quantidade = filter_var($data['oei_quantidade'], FILTER_VALIDATE_FLOAT);

        if (!$pedidoId || !$alocacaoId || !$quantidade || $quantidade <= 0) {
            throw new Exception("Dados inválidos para adicionar o item.");
        }

        // Validação adicional: Verificar se a quantidade não excede o saldo disponível
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) FROM tbl_ordens_expedicao_itens WHERE oei_alocacao_id = ea.alocacao_id)";
        $query = "SELECT (ea.alocacao_quantidade - {$subQueryReservado}) AS saldo_disponivel
              FROM tbl_estoque_alocacoes ea
              WHERE ea.alocacao_id = :alocacao_id";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':alocacao_id' => $alocacaoId]);
        $saldo = $stmt->fetchColumn();

        if ($saldo === false || $saldo < $quantidade) {
            throw new Exception("Quantidade solicitada excede o saldo disponível no endereço.");
        }

        // Verificar se já existe um item com o mesmo pedido e alocação
        $checkQuery = "SELECT oei_id, oei_quantidade FROM tbl_ordens_expedicao_itens 
                   WHERE oei_pedido_id = :pedido_id AND oei_alocacao_id = :alocacao_id LIMIT 1";
        $checkStmt = $this->pdo->prepare($checkQuery);
        $checkStmt->execute([
            ':pedido_id' => $pedidoId,
            ':alocacao_id' => $alocacaoId
        ]);
        $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingItem) {
            // Se existir, soma a quantidade e atualiza o registro existente
            $novaQuantidade = $existingItem['oei_quantidade'] + $quantidade;
            $updateSql = "UPDATE tbl_ordens_expedicao_itens 
                      SET oei_quantidade = :nova_quantidade, oei_observacao = :observacao
                      WHERE oei_id = :oei_id";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([
                ':nova_quantidade' => $novaQuantidade,
                ':observacao' => $data['oei_observacao'] ?? null,  // Atualiza observação se fornecida
                ':oei_id' => $existingItem['oei_id']
            ]);
            $newId = $existingItem['oei_id'];  // Retorna o ID existente
            $this->auditLogger->log('UPDATE', $newId, 'tbl_ordens_expedicao_itens', $existingItem, $data);
        } else {
            // Se não existir, insere novo
            $sql = "INSERT INTO tbl_ordens_expedicao_itens (oei_pedido_id, oei_alocacao_id, oei_quantidade, oei_observacao)
                VALUES (:pedido_id, :alocacao_id, :quantidade, :observacao)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':pedido_id' => $pedidoId,
                ':alocacao_id' => $alocacaoId,
                ':quantidade' => $quantidade,
                ':observacao' => $data['oei_observacao'] ?? null
            ]);
            $newId = (int) $this->pdo->lastInsertId();
            $this->auditLogger->log('CREATE', $newId, 'tbl_ordens_expedicao_itens', null, $data);
        }

        return $newId;
    }

    public function getProdutosDisponiveisParaSelecao(): array
    {
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) FROM tbl_ordens_expedicao_itens WHERE oei_alocacao_id = ea.alocacao_id)";
        $sql = "SELECT DISTINCT
                    p.prod_codigo AS id,
                    CONCAT(p.prod_descricao, ' (Cód: ', p.prod_codigo_interno, ')') AS text
                FROM tbl_estoque_alocacoes ea
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                WHERE ea.alocacao_quantidade > {$subQueryReservado}
                ORDER BY p.prod_descricao";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLotesDisponiveisPorProduto(int $produtoId): array
    {
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) FROM tbl_ordens_expedicao_itens WHERE oei_alocacao_id = ea.alocacao_id)";
        $sql = "SELECT DISTINCT
                    lne.item_emb_id AS id,
                    CONCAT(lnh.lote_completo_calculado, ' [Saldo Total: ', FORMAT(SUM(ea.alocacao_quantidade - {$subQueryReservado}), 3, 'de_DE'), ']') AS text
                FROM tbl_estoque_alocacoes ea
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                WHERE lne.item_emb_prod_sec_id = :produto_id
                GROUP BY lne.item_emb_id, lnh.lote_completo_calculado
                HAVING SUM(ea.alocacao_quantidade - {$subQueryReservado}) > 0
                ORDER BY lnh.lote_completo_calculado";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':produto_id' => $produtoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEnderecosDisponiveisPorLoteItem(int $loteItemId): array
    {
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) FROM tbl_ordens_expedicao_itens WHERE oei_alocacao_id = ea.alocacao_id)";
        $sql = "SELECT 
                    ea.alocacao_id AS id,
                    CONCAT(ee.endereco_completo, ' [Saldo: ', FORMAT((ea.alocacao_quantidade - {$subQueryReservado}), 3, 'de_DE'), ']') as text,
                    (ea.alocacao_quantidade - {$subQueryReservado}) as saldo_disponivel
                FROM tbl_estoque_alocacoes ea
                JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                WHERE ea.alocacao_lote_item_id = :lote_item_id
                AND ea.alocacao_quantidade > {$subQueryReservado}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_item_id' => $loteItemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remove um pedido e todos os seus itens associados.
     * @param int $pedidoId ID do pedido (oep_id)
     * @throws Exception Se o pedido não for encontrado ou houver erro na exclusão
     */
    public function removePedido(int $pedidoId): void
    {
        // Verificar se o pedido existe
        $checkQuery = "SELECT COUNT(*) FROM tbl_ordens_expedicao_pedidos WHERE oep_id = :pedido_id";
        $checkStmt = $this->pdo->prepare($checkQuery);
        $checkStmt->execute([':pedido_id' => $pedidoId]);
        if ($checkStmt->fetchColumn() == 0) {
            throw new Exception("Pedido não encontrado.");
        }

        // Iniciar transação para garantir consistência
        $this->pdo->beginTransaction();
        try {
            // Excluir itens associados ao pedido
            $deleteItens = "DELETE FROM tbl_ordens_expedicao_itens WHERE oei_pedido_id = :pedido_id";
            $deleteItensStmt = $this->pdo->prepare($deleteItens);
            $deleteItensStmt->execute([':pedido_id' => $pedidoId]);

            // Excluir o pedido
            $deletePedido = "DELETE FROM tbl_ordens_expedicao_pedidos WHERE oep_id = :pedido_id";
            $deletePedidoStmt = $this->pdo->prepare($deletePedido);
            $deletePedidoStmt->execute([':pedido_id' => $pedidoId]);

            // Registrar no log de auditoria
            $this->auditLogger->log('DELETE', $pedidoId, 'tbl_ordens_expedicao_pedidos', null, null);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erro ao remover pedido: " . $e->getMessage());
        }
    }

    /**
     * Remove um item de um pedido.
     * @param int $itemId ID do item (oei_id)
     * @throws Exception Se o item não for encontrado ou houver erro na exclusão
     */
    public function removeItem(int $itemId): void
    {
        // Verificar se o item existe
        $checkQuery = "SELECT COUNT(*) FROM tbl_ordens_expedicao_itens WHERE oei_id = :item_id";
        $checkStmt = $this->pdo->prepare($checkQuery);
        $checkStmt->execute([':item_id' => $itemId]);
        if ($checkStmt->fetchColumn() == 0) {
            throw new Exception("Item não encontrado.");
        }

        // Excluir o item
        $sql = "DELETE FROM tbl_ordens_expedicao_itens WHERE oei_id = :item_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':item_id' => $itemId]);

        // Registrar no log de auditoria
        $this->auditLogger->log('DELETE', $itemId, 'tbl_ordens_expedicao_itens', null, null);
    }

    /**
     * Busca os detalhes de quais Ordens de Expedição estão reservando um item de estoque específico.
     * @param int $alocacaoId
     * @return array
     */
    public function findReservaDetalhesPorAlocacao(int $alocacaoId): array
    {
        $sql = "SELECT
                    oeh.oe_numero,
                    COALESCE(e.ent_nome_fantasia, e.ent_razao_social) AS cliente_nome,
                    oep.oep_numero_pedido,
                    oei.oei_quantidade
                FROM tbl_ordens_expedicao_itens oei
                JOIN tbl_ordens_expedicao_pedidos oep ON oei.oei_pedido_id = oep.oep_id
                JOIN tbl_ordens_expedicao_header oeh ON oep.oep_ordem_id = oeh.oe_id
                JOIN tbl_entidades e ON oep.oep_cliente_id = e.ent_codigo
                WHERE oei.oei_alocacao_id = :alocacao_id AND oeh.oe_status = 'EM ELABORAÇÃO'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':alocacao_id' => $alocacaoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca os detalhes de um item específico da ordem para preencher o modal de edição.
     * Inclui o cálculo do saldo máximo editável.
     * @param int $oeiId
     * @return array|null
     */
    public function findItemDetalhesParaEdicao(int $oeiId): ?array
    {
        $sql = "SELECT
                    oei.oei_quantidade,
                    oei.oei_observacao,
                    p.prod_descricao,
                    -- A mágica está aqui:
                    -- (Total Físico no Endereço - Total Reservado por TODOS) + Quantidade deste próprio item
                    (
                        ea.alocacao_quantidade - 
                        (SELECT COALESCE(SUM(outras_oei.oei_quantidade), 0) FROM tbl_ordens_expedicao_itens outras_oei WHERE outras_oei.oei_alocacao_id = oei.oei_alocacao_id)
                        + oei.oei_quantidade
                    ) as max_quantidade_disponivel
                FROM
                    tbl_ordens_expedicao_itens oei
                JOIN tbl_estoque_alocacoes ea ON oei.oei_alocacao_id = ea.alocacao_id
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                WHERE oei.oei_id = :oei_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':oei_id' => $oeiId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualiza a quantidade e/ou observação de um item existente na Ordem de Expedição.
     * @param int $oeiId
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function updateItem(int $oeiId, array $data): bool
    {
        $novaQuantidade = filter_var($data['oei_quantidade'], FILTER_VALIDATE_FLOAT);
        $novaObservacao = $data['oei_observacao'] ?? null;

        // 1. Validação básica
        if ($novaQuantidade === false || $novaQuantidade <= 0) {
            throw new Exception("A nova quantidade fornecida é inválida.");
        }

        // 2. Busca os dados atuais do item para validação e auditoria
        $dadosAtuais = $this->pdo->prepare("SELECT * FROM tbl_ordens_expedicao_itens WHERE oei_id = :oei_id");
        $dadosAtuais->execute([':oei_id' => $oeiId]);
        $itemAtual = $dadosAtuais->fetch(PDO::FETCH_ASSOC);

        if (!$itemAtual) {
            throw new Exception("Item da ordem não encontrado.");
        }
        $alocacaoId = $itemAtual['oei_alocacao_id'];

        // 3. Validação CRÍTICA de saldo
        $querySaldo = "SELECT (
                            ea.alocacao_quantidade - 
                            (SELECT COALESCE(SUM(oei.oei_quantidade), 0) FROM tbl_ordens_expedicao_itens oei WHERE oei.oei_alocacao_id = ea.alocacao_id AND oei.oei_id != :oei_id_param)
                        ) as max_quantidade_disponivel
                       FROM tbl_estoque_alocacoes ea
                       WHERE ea.alocacao_id = :alocacao_id_param";

        $stmtSaldo = $this->pdo->prepare($querySaldo);
        $stmtSaldo->execute([
            ':oei_id_param' => $oeiId,
            ':alocacao_id_param' => $alocacaoId
        ]);
        $maximoPermitido = $stmtSaldo->fetchColumn();

        if ($novaQuantidade > $maximoPermitido) {
            throw new Exception("Nova quantidade (" . $novaQuantidade . ") excede o saldo disponível no endereço (" . $maximoPermitido . ").");
        }

        // 4. Se todas as validações passaram, executa o UPDATE
        $sqlUpdate = "UPDATE tbl_ordens_expedicao_itens 
                      SET oei_quantidade = :nova_quantidade, oei_observacao = :nova_observacao 
                      WHERE oei_id = :oei_id";
        $stmtUpdate = $this->pdo->prepare($sqlUpdate);
        $success = $stmtUpdate->execute([
            ':nova_quantidade' => $novaQuantidade,
            ':nova_observacao' => $novaObservacao,
            ':oei_id' => $oeiId
        ]);

        // 5. Registra na auditoria
        $this->auditLogger->log('UPDATE', $oeiId, 'tbl_ordens_expedicao_itens', $itemAtual, $data);

        return $success;
    }

}