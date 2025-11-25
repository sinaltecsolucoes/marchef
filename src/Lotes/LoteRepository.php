<?php
// /src/Lotes/LoteRepository.php
namespace App\Lotes;

use PDO;
use PDOException;
use Exception;
use App\Core\AuditLoggerService;

class LoteRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    public function findAllForDataTable(array $params): array
    {
        $baseQuery = "FROM tbl_lotes l LEFT JOIN tbl_entidades f ON l.lote_fornecedor_id = f.ent_codigo";
        $searchValue = $params['search']['value'] ?? '';
        $params['length'] = $params['length'] ?? 10;

        $whereClause = "";
        $queryParams = [];
        if (!empty($searchValue)) {
            $whereClause = " WHERE (l.lote_completo_calculado LIKE :search OR f.ent_razao_social LIKE :search OR l.lote_status LIKE :search)";
            $queryParams[':search'] = '%' . $searchValue . '%';
        }

        $totalRecords = $this->pdo->query("SELECT COUNT(l.lote_id) FROM tbl_lotes l")->fetchColumn();

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(l.lote_id) $baseQuery $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $orderColumn = 'l.lote_data_cadastro';
        $orderDir = $params['order'][0]['dir'] ?? 'desc';

        $sqlData = "SELECT l.*, f.ent_razao_social AS fornecedor_razao_social $baseQuery $whereClause ORDER BY $orderColumn " . strtoupper($orderDir) . " LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) $params['start'], PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $params['length'], PDO::PARAM_INT);
        if (!empty($searchValue)) {
            $stmt->bindValue(':search', $queryParams[':search']);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ["draw" => intval($params['draw']), "recordsTotal" => (int) $totalRecords, "recordsFiltered" => (int) $totalFiltered, "data" => $data];
    }

    public function findLoteComItens(int $id): ?array
    {
        $header = $this->pdo->prepare("SELECT * FROM tbl_lotes WHERE lote_id = :id");
        $header->execute([':id' => $id]);
        $lote = $header->fetch(PDO::FETCH_ASSOC);

        if (!$lote)
            return null;

        $items = $this->pdo->prepare(
            "SELECT li.*, p.prod_descricao, p.prod_peso_embalagem, 
                           (li.item_quantidade - li.item_quantidade_finalizada) as quantidade_pendente 
                    FROM tbl_lote_itens li 
                    JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo 
                    WHERE li.item_lote_id = :id 
                    ORDER BY li.item_status ASC, li.item_id ASC"
        );
        $items->execute([':id' => $id]);

        return ['header' => $lote, 'items' => $items->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getNextNumero(): string
    {
        $stmt = $this->pdo->query("SELECT MAX(lote_numero) FROM tbl_lotes");
        $proximo_numero = ($stmt->fetchColumn() ?: 0) + 1;
        return str_pad($proximo_numero, 4, '0', STR_PAD_LEFT);
    }

    public function saveHeader(array $data, int $userId): int
    {
        $id = filter_var($data['lote_id'] ?? null, FILTER_VALIDATE_INT);
        $dadosAntigos = null;
        if ($id) {
            $dadosAntigos = $this->findById($id);
        }

        $params = [
            ':numero' => $data['lote_numero'],
            ':data_fab' => $data['lote_data_fabricacao'],
            ':fornecedor' => $data['lote_fornecedor_id'] ?: null,
            ':cliente' => $data['lote_cliente_id'] ?: null,
            ':ciclo' => $data['lote_ciclo'],
            ':viveiro' => $data['lote_viveiro'],
            ':completo' => $data['lote_completo_calculado'],
        ];

        if ($id) {
            $sql = "UPDATE tbl_lotes 
                    SET lote_numero = :numero, 
                        lote_data_fabricacao = :data_fab, 
                        lote_fornecedor_id = :fornecedor,
                        lote_cliente_id = :cliente,  
                        lote_ciclo = :ciclo, 
                        lote_viveiro = :viveiro, 
                        lote_completo_calculado = :completo 
                    WHERE lote_id = :id";
            $params[':id'] = $id;
        } else {
            $sql = "INSERT INTO tbl_lotes (lote_numero, lote_data_fabricacao, 
                                           lote_fornecedor_id, lote_cliente_id,
                                           lote_ciclo, lote_viveiro, lote_completo_calculado, 
                                           lote_usuario_id) 
                                        VALUES (:numero, :data_fab, 
                                                :fornecedor, :cliente, 
                                                :ciclo, :viveiro, 
                                                :completo, :user_id)";
            $params[':user_id'] = $userId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $id ?: $this->pdo->lastInsertId();

        // AUDITORIA
        if ($id) { // Log de UPDATE
            $this->auditLogger->log('UPDATE', $id, 'tbl_lotes', $dadosAntigos, $data);
        } else { // Log de CREATE
            $this->auditLogger->log('CREATE', $resultId, 'tbl_lotes', null, $data);
        }

        return $resultId;
    }

    public function saveItem(array $data): bool
    {
        $id = filter_var($data['item_id'] ?? null, FILTER_VALIDATE_INT);
        $dadosAntigos = null;
        if ($id) {
            $dadosAntigos = $this->findItemById($id);
        }

        $params = [
            ':lote_id' => $data['lote_id'],
            ':produto_id' => $data['item_produto_id'],
            ':quantidade' => $data['item_quantidade'],
            ':data_validade' => $data['item_data_validade'],
        ];

        if ($id) {
            $sql = "UPDATE tbl_lote_itens SET item_produto_id = :produto_id, item_quantidade = :quantidade, item_data_validade = :data_validade WHERE item_id = :id AND item_lote_id = :lote_id";
            $params[':id'] = $id;
        } else {
            $sql = "INSERT INTO tbl_lote_itens (item_lote_id, item_produto_id, item_quantidade, item_data_validade) VALUES (:lote_id, :produto_id, :quantidade, :data_validade)";
        }

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute($params);

        // AUDITORIA
        if ($success) {
            if ($id) {
                $this->auditLogger->log('UPDATE', $id, 'tbl_lote_itens', $dadosAntigos, $data,"");
            } else {
                $novoId = (int) $this->pdo->lastInsertId();
                $this->auditLogger->log('CREATE', $novoId, 'tbl_lote_itens', null, $data,"");
            }
            // ATUALIZA O STATUS GERAL DO LOTE
            $this->atualizarStatusGeralDoLote((int) $data['lote_id']);
        }

        return $success;
    }

    public function deleteItem(int $itemId): bool
    {
        $dadosAntigos = $this->findItemById($itemId);
        if (!$dadosAntigos)
            return false;

        $stmt = $this->pdo->prepare("DELETE FROM tbl_lote_itens WHERE item_id = :id");
        $success = $stmt->execute([':id' => $itemId]);

        if ($success && $stmt->rowCount() > 0) {
            $this->auditLogger->log('DELETE', $itemId, 'tbl_lote_itens', $dadosAntigos, null,"");

            // ATUALIZA O STATUS GERAL DO LOTE
            $this->atualizarStatusGeralDoLote((int) $dadosAntigos['item_lote_id']);

            return true;
        }

        return false;
    }

    public function delete(int $id): bool
    {
        // AUDITORIA: Capturar dados antes de apagar
        $dadosAntigosHeader = $this->findById($id);
        if (!$dadosAntigosHeader)
            return false;

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM tbl_lote_itens WHERE item_lote_id = :id")->execute([':id' => $id]);
            $this->pdo->prepare("DELETE FROM tbl_lotes WHERE lote_id = :id")->execute([':id' => $id]);

            // AUDITORIA: Registar a exclusão antes de confirmar a transação
            $this->auditLogger->log('DELETE', $id, 'tbl_lotes', $dadosAntigosHeader, null,"");

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Finaliza TODOS os itens pendentes de um lote e gera o estoque.
     *
     * @param int $loteId
     * @throws Exception
     */
    public function finalize(int $loteId): void
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Busca todos os itens que ainda têm quantidade pendente
            $stmtItens = $this->pdo->prepare(
                "SELECT item_id, item_produto_id, (item_quantidade - item_quantidade_finalizada) as qtd_pendente 
                 FROM tbl_lote_itens 
                 WHERE item_lote_id = :lote_id AND (item_quantidade - item_quantidade_finalizada) > 0"
            );
            $stmtItens->execute([':lote_id' => $loteId]);
            $itensParaFinalizar = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            if (empty($itensParaFinalizar)) {
                throw new Exception("Não há itens pendentes para finalizar neste lote.");
            }

            foreach ($itensParaFinalizar as $item) {
                $quantidadeAFinalizar = $item['qtd_pendente'];

                // 2. Cria o movimento de ENTRADA na tabela de estoque
                $stmtEstoque = $this->pdo->prepare(
                    "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
                     VALUES (:prod_id, :lote_item_id, :qtd, 'ENTRADA', :obs)"
                );
                $stmtEstoque->execute([
                    ':prod_id' => $item['item_produto_id'],
                    ':lote_item_id' => $item['item_id'],
                    ':qtd' => $quantidadeAFinalizar,
                    ':obs' => "Finalização total do Lote ID {$loteId}"
                ]);

                // 3. Atualiza a quantidade finalizada no item do lote (zera a pendência)
                $stmtUpdateItem = $this->pdo->prepare(
                    "UPDATE tbl_lote_itens SET item_quantidade_finalizada = item_quantidade_finalizada + :qtd 
                     WHERE item_id = :id"
                );
                $stmtUpdateItem->execute([':qtd' => $quantidadeAFinalizar, ':id' => $item['item_id']]);
            }

            // 4. Atualiza o status do lote para FINALIZADO
            $stmtUpdateLote = $this->pdo->prepare("UPDATE tbl_lotes SET lote_status = 'FINALIZADO' WHERE lote_id = :id");
            $stmtUpdateLote->execute([':id' => $loteId]);

            $this->auditLogger->log('FINALIZE', $loteId, 'tbl_lotes', null, ['status' => 'FINALIZADO'],"");
            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findItem(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_lote_itens WHERE item_id = :id");
        $stmt->execute([':id' => $itemId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca um único cabeçalho de lote pelo seu ID.
     * @param int $loteId O ID do lote a ser encontrado.
     * @return array|false Os dados do lote ou false se não for encontrado.
     */
    public function findById(int $loteId)
    {
        // Corrigido para usar a tabela 'tbl_lotes'
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_lotes WHERE lote_id = :lote_id");
        $stmt->execute([':lote_id' => $loteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um único item de lote pelo seu ID.
     * @param int $loteItemId O ID do item de lote a ser encontrado.
     * @return array|false Os dados do item ou false se não for encontrado.
     */
    public function findItemById(int $loteItemId)
    {
        // Corrigido para usar a tabela 'tbl_lote_itens'
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_lote_itens WHERE item_id = :item_id");
        $stmt->execute([':item_id' => $loteItemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Finaliza quantidades específicas de itens de um lote e gera o estoque.
     * (Versão Corrigida com lógica de estoque)
     *
     * @param int $loteId
     * @param array $itens Os itens a serem finalizados, no formato [['item_id' => x, 'quantidade' => y], ...].
     * @throws Exception
     */
    public function finalizeParcialmente(int $loteId, array $itens): void
    {
        $this->pdo->beginTransaction();

        try {
            foreach ($itens as $item) {
                $itemId = $item['item_id'];
                $quantidadeAFinalizar = (float) $item['quantidade'];

                // 1. Validação de segurança: busca o item no banco para garantir que a quantidade é válida
                $stmtItemAtual = $this->pdo->prepare(
                    "SELECT item_produto_id, (item_quantidade - item_quantidade_finalizada) as qtd_pendente 
                     FROM tbl_lote_itens WHERE item_id = :id"
                );
                $stmtItemAtual->execute([':id' => $itemId]);
                $itemAtual = $stmtItemAtual->fetch(PDO::FETCH_ASSOC);

                if (!$itemAtual || $quantidadeAFinalizar > (float) $itemAtual['qtd_pendente']) {
                    throw new Exception("Quantidade a finalizar para o item ID {$itemId} é maior que o estoque pendente.");
                }

                // 2. Cria o movimento de ENTRADA na tabela de estoque
                $stmtEstoque = $this->pdo->prepare(
                    "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
                     VALUES (:prod_id, :lote_item_id, :qtd, 'ENTRADA', :obs)"
                );
                $stmtEstoque->execute([
                    ':prod_id' => $itemAtual['item_produto_id'],
                    ':lote_item_id' => $itemId,
                    ':qtd' => $quantidadeAFinalizar,
                    ':obs' => "Finalização parcial do Lote ID {$loteId}"
                ]);

                // 3. Atualiza a quantidade finalizada no item do lote
                $stmtUpdateItem = $this->pdo->prepare(
                    "UPDATE tbl_lote_itens SET item_quantidade_finalizada = item_quantidade_finalizada + :qtd 
                     WHERE item_id = :id"
                );
                $stmtUpdateItem->execute([':qtd' => $quantidadeAFinalizar, ':id' => $itemId]);
            }

            // 4. Após finalizar os itens, recalcula e atualiza o status geral do lote
            $this->atualizarStatusLote($loteId);

            $this->auditLogger->log('FINALIZE_PARTIAL', $loteId, 'tbl_lotes', null, ['itens_finalizados' => $itens],"");
            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reavalia e atualiza o status geral de um lote com base no estado dos seus itens.
     * Esta função segue as regras de negócio definidas.
     *
     * @param int $loteId O ID do lote a ser verificado e atualizado.
     */
    private function atualizarStatusGeralDoLote(int $loteId): string
    {
        // Busca a soma das quantidades totais e finalizadas para todos os itens do lote
        $stmt = $this->pdo->prepare(
            "SELECT 
                SUM(item_quantidade) as total_geral, 
                SUM(item_quantidade_finalizada) as total_finalizado 
             FROM tbl_lote_itens 
             WHERE item_lote_id = :lote_id"
        );
        $stmt->execute([':lote_id' => $loteId]);
        $somas = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalGeral = (float) ($somas['total_geral'] ?? 0);
        $totalFinalizado = (float) ($somas['total_finalizado'] ?? 0);

        $novoStatus = 'EM ANDAMENTO'; // Status padrão

        if ($totalFinalizado > 0) {
            // Se algo já foi finalizado, verifica se AINDA há algo pendente
            if ($totalFinalizado >= $totalGeral - 0.001) { // Usamos tolerância para floats
                $novoStatus = 'FINALIZADO';
            } else {
                $novoStatus = 'PARCIALMENTE FINALIZADO';
            }
        } elseif ($totalGeral == 0) {
            // Se não há itens, o status é 'EM ANDAMENTO'
            $novoStatus = 'EM ANDAMENTO';
        }

        // Atualiza o status do cabeçalho do lote no banco de dados
        $stmtUpdate = $this->pdo->prepare("UPDATE tbl_lotes SET lote_status = :status WHERE lote_id = :id");
        $stmtUpdate->execute([':status' => $novoStatus, ':id' => $loteId]);

        return $novoStatus;
    }

    /**
     * Cancela um lote.
     * Se houver itens já no estoque, cria um movimento de saída para reverter.
     * Apenas lotes 'EM ANDAMENTO' ou 'PARCIALMENTE FINALIZADO' podem ser cancelados.
     *
     * @param int $loteId
     * @return bool
     * @throws Exception
     */
    public function cancelar(int $loteId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // PASSO 1: Buscar o lote e verificar o seu status
            $dadosAntigos = $this->findById($loteId);
            if (!$dadosAntigos) {
                throw new Exception("Lote não encontrado.");
            }
            if (!in_array($dadosAntigos['lote_status'], ['EM ANDAMENTO', 'PARCIALMENTE FINALIZADO'])) {
                throw new Exception("Apenas lotes 'Em Andamento' ou 'Parcialmente Finalizados' podem ser cancelados.");
            }

            // PASSO 2: Reverter o estoque (se houver itens finalizados)
            if ($dadosAntigos['lote_status'] === 'PARCIALMENTE FINALIZADO') {
                // Encontra todos os itens que geraram entrada no estoque para este lote
                $stmtItensEstoque = $this->pdo->prepare("SELECT estoque_produto_id, estoque_lote_item_id, estoque_quantidade FROM tbl_estoque WHERE estoque_lote_item_id IN (SELECT item_id FROM tbl_lote_itens WHERE item_lote_id = :lote_id) AND estoque_tipo_movimento = 'ENTRADA'");
                $stmtItensEstoque->execute([':lote_id' => $loteId]);
                $itensParaReverter = $stmtItensEstoque->fetchAll(PDO::FETCH_ASSOC);

                $stmtReversao = $this->pdo->prepare("INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) VALUES (:produto_id, :lote_item_id, :quantidade, 'SAIDA', :obs)");

                foreach ($itensParaReverter as $item) {
                    // Cria um movimento de SAÍDA para cada ENTRADA, zerando o saldo
                    $stmtReversao->execute([
                        ':produto_id' => $item['estoque_produto_id'],
                        ':lote_item_id' => $item['estoque_lote_item_id'],
                        ':quantidade' => $item['estoque_quantidade'],
                        ':obs' => 'SAIDA POR CANCELAMENTO DE LOTE'
                    ]);
                }
            }

            // PASSO 3: Atualizar o status do lote para 'CANCELADO'
            $stmtUpdate = $this->pdo->prepare("UPDATE tbl_lotes SET lote_status = 'CANCELADO' WHERE lote_id = :id");
            $stmtUpdate->execute([':id' => $loteId]);

            // PASSO 4: AUDITORIA
            $dadosNovos = $dadosAntigos;
            $dadosNovos['lote_status'] = 'CANCELADO';
            $this->auditLogger->log('CANCEL_LOTE', $loteId, 'tbl_lotes', $dadosAntigos, $dadosNovos,"");

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Busca itens em estoque para o Select2, pesquisando por descrição ou código interno.
     *
     * @param string $term O termo de busca do Select2.
     * @return array
     */
    public function findItensEmEstoqueParaSelect(string $term): array
    {
        // Consulta corrigida para usar placeholders distintos para cada LIKE,
        // evitando o erro "Invalid parameter number" (HY093).
        $sql = "SELECT 
                    li.item_id as id,
                    CONCAT(p.prod_descricao, ' (Cód: ', p.prod_codigo_interno, ')') as text
                FROM tbl_lote_itens li
                JOIN tbl_lotes lh ON li.item_lote_id = lh.lote_id
                JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo
                WHERE 
                    (li.item_quantidade - li.item_quantidade_finalizada) > 0 
                    AND lh.lote_status IN ('PARCIALMENTE FINALIZADO', 'FINALIZADO')
                    AND (
                        p.prod_descricao LIKE :term1 -- Usando placeholder :term1
                        OR p.prod_codigo_interno LIKE :term2 -- Usando placeholder :term2
                    )
                LIMIT 20";

        $stmt = $this->pdo->prepare($sql);

        // Passando valores para ambos os placeholders
        $searchTerm = '%' . $term . '%';
        $stmt->execute([
            ':term1' => $searchTerm,
            ':term2' => $searchTerm
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todos os lotes disponíveis para um produto específico.
     * @param int $produtoId
     * @return array
     */
    public function findLotesDisponiveisPorProduto(int $produtoId): array
    {
        // Esta consulta agora calcula o saldo real a partir da tabela de movimentos de estoque.
        $sql = "SELECT 
                    li.item_id as id,
                    CONCAT(
                        'Lote: ', lh.lote_completo_calculado, 
                        ' (Estoque: ', 
                        SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END),
                        ')'
                    ) as text
                FROM tbl_estoque es
                JOIN tbl_lote_itens li ON es.estoque_lote_item_id = li.item_id
                JOIN tbl_lotes lh ON li.item_lote_id = lh.lote_id
                WHERE li.item_produto_id = :produto_id
                GROUP BY li.item_id
                HAVING SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END) > 0";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':produto_id' => $produtoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca os dados para a visão geral do estoque para ser usado pelo DataTables.
     *
     * @param array $params Parâmetros do DataTables (para paginação, busca, etc.)
     * @return array
     */
    public function getVisaoGeralEstoque(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        // ===================================================================
        // == INÍCIO DA LÓGICA DE ORDENAÇÃO ==
        // ===================================================================

        // 1. Mapeamento das colunas do frontend para as colunas da subconsulta
        $columnMap = [
            0 => 'tipo_produto',
            1 => 'subtipo',
            2 => 'classificacao',
            3 => 'codigo_interno',
            4 => 'descricao_produto',
            5 => 'lote',
            6 => 'cliente_lote_nome',
            7 => 'data_fabricacao',
            8 => 'peso_embalagem',
            9 => 'total_caixas',
            10 => 'peso_total'
        ];

        // 2. Define uma ordenação padrão
        $orderColumn = 'descricao_produto'; // Padrão
        $orderDir = 'asc';

        // 3. Verifica se o DataTables enviou uma instrução de ordenação
        if (isset($params['order']) && isset($params['order'][0]['column'])) {
            $orderColumnIndex = $params['order'][0]['column'];
            $orderDir = strtolower($params['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';

            if (isset($columnMap[$orderColumnIndex])) {
                $orderColumn = $columnMap[$orderColumnIndex];
            }
        }
        // ===================================================================
        // == FIM DA LÓGICA DE ORDENAÇÃO ==
        // ===================================================================

        // A consulta interna (subquery) calcula o estoque corretamente
        $subQuery = "
        SELECT
            p.prod_tipo AS tipo_produto, p.prod_subtipo AS subtipo, p.prod_classificacao AS classificacao,
            p.prod_codigo_interno AS codigo_interno, p.prod_descricao AS descricao_produto,
            lh.lote_completo_calculado AS lote, 
            COALESCE(e_origem.ent_nome_fantasia, e_origem.ent_razao_social) as cliente_lote_nome,
            lh.lote_data_fabricacao AS data_fabricacao, p.prod_peso_embalagem AS peso_embalagem,
            SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END) AS total_caixas
        FROM tbl_estoque es
        JOIN tbl_lote_itens li ON es.estoque_lote_item_id = li.item_id
        JOIN tbl_lotes lh ON li.item_lote_id = lh.lote_id
        JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo
        LEFT JOIN tbl_entidades e_origem ON lh.lote_cliente_id = e_origem.ent_codigo
        GROUP BY li.item_id
    ";

        // Começamos a nossa cláusula WHERE com uma condição base que sempre será verdade.
        // Isso permite-nos adicionar as outras condições de filtro com AND sem nos preocuparmos se são as primeiras.
        $whereClause = "WHERE 1=1 ";
        $queryParams = [];

        // Filtro para mostrar apenas o que tem saldo diferente de zero
        $whereClause .= "AND total_caixas != 0 ";

        if (!empty($searchValue)) {
            $whereClause .= "AND (descricao_produto LIKE :search_desc 
                         OR codigo_interno LIKE :search_cod 
                         OR lote LIKE :search_lote
                         OR cliente_lote_nome LIKE :search_cli)";

            $searchTerm = '%' . $searchValue . '%';
            $queryParams = [
                ':search_desc' => $searchTerm,
                ':search_cod' => $searchTerm,
                ':search_lote' => $searchTerm,
                ':search_cli' => $searchTerm,
            ];
        }

        // Contagem total (apenas com o filtro de saldo diferente de zero)
        $totalRecordsQuery = $this->pdo->query("SELECT COUNT(*) FROM ({$subQuery}) as estoque_total WHERE total_caixas != 0");
        $totalRecords = $totalRecordsQuery->fetchColumn();

        // Contagem de registros filtrados (com a busca do utilizador)
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(*) FROM ({$subQuery}) as estoque_filtrado {$whereClause}");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // Busca dos dados da página atual
        $sqlData = "SELECT *, (total_caixas * peso_embalagem) AS peso_total 
                FROM ({$subQuery}) as estoque_final
                {$whereClause}
                ORDER BY {$orderColumn} {$orderDir}
                LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
        foreach ($queryParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => (int) $draw,
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data
        ];
    }

    /**
     * Recalcula e atualiza o status de um lote com base nas quantidades finalizadas de seus itens.
     *
     * @param int $loteId
     * @return void
     */
    private function atualizarStatusLote(int $loteId): void
    {
        // 1. Busca os dados antigos do lote para a auditoria
        $stmtAntigo = $this->pdo->prepare("SELECT lote_status FROM tbl_lotes WHERE lote_id = :id");
        $stmtAntigo->execute([':id' => $loteId]);
        $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

        // 2. Calcula o total planeado e o total já finalizado para o lote
        $stmtSomas = $this->pdo->prepare(
            "SELECT 
                SUM(item_quantidade) as total_quantidade, 
                SUM(item_quantidade_finalizada) as total_finalizado 
             FROM tbl_lote_itens 
             WHERE item_lote_id = :lote_id"
        );
        $stmtSomas->execute([':lote_id' => $loteId]);
        $somas = $stmtSomas->fetch(PDO::FETCH_ASSOC);

        $totalQuantidade = (float) ($somas['total_quantidade'] ?? 0);
        $totalFinalizado = (float) ($somas['total_finalizado'] ?? 0);

        // 3. Define o novo status com base nos cálculos
        $novoStatus = 'EM ANDAMENTO'; // Padrão
        if ($totalFinalizado >= $totalQuantidade) {
            $novoStatus = 'FINALIZADO';
        } elseif ($totalFinalizado > 0) {
            $novoStatus = 'PARCIALMENTE FINALIZADO';
        }

        // 4. Se o status mudou, atualiza no banco de dados
        if ($novoStatus !== $dadosAntigos['lote_status']) {
            $stmtUpdate = $this->pdo->prepare("UPDATE tbl_lotes SET lote_status = :status WHERE lote_id = :id");
            $stmtUpdate->execute([':status' => $novoStatus, ':id' => $loteId]);

            // 5. Regista a alteração de status na auditoria
            $dadosNovos = ['lote_status' => $novoStatus];
            $this->auditLogger->log('UPDATE_STATUS', $loteId, 'tbl_lotes', $dadosAntigos, $dadosNovos,"");
        }
    }

    /**
     * Busca os detalhes de um único item de lote para popular formulários.
     * @param int $loteItemId
     * @return array|false
     */
    public function findItemDetalhes(int $loteItemId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT 
            li.item_produto_id,
            p.prod_descricao,
            p.prod_codigo_interno,
            li.item_id as lote_item_id,
            CONCAT('Lote: ', lh.lote_completo_calculado, ' (Estoque: ', (li.item_quantidade - li.item_quantidade_finalizada), ')') as lote_texto
         FROM tbl_lote_itens li
         JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo
         JOIN tbl_lotes lh ON li.item_lote_id = lh.lote_id
         WHERE li.item_id = :id"
        );
        $stmt->execute([':id' => $loteItemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Reabre um lote finalizado, revertendo seus movimentos de estoque.
     * Ação é bloqueada se algum item do lote já foi usado em um carregamento.
     *
     * @param int $loteId
     * @param string $motivo
     * @return bool
     * @throws Exception
     */
    public function reabrir(int $loteId, string $motivo): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Validações Iniciais
            $dadosAntigos = $this->findById($loteId);
            if (!$dadosAntigos || $dadosAntigos['lote_status'] !== 'FINALIZADO') {
                throw new Exception("Apenas lotes finalizados podem ser reabertos.");
            }

            // 2. VERIFICAÇÃO CRÍTICA: Lote já foi usado em algum carregamento?
            $stmtUso = $this->pdo->prepare(
                "SELECT COUNT(ci.car_item_id) 
                 FROM tbl_carregamento_itens ci
                 JOIN tbl_lote_itens li ON ci.car_item_lote_item_id = li.item_id
                 WHERE li.item_lote_id = :lote_id"
            );
            $stmtUso->execute([':lote_id' => $loteId]);
            if ($stmtUso->fetchColumn() > 0) {
                throw new Exception("Este lote não pode ser reaberto, pois seus itens já foram expedidos em um carregamento.");
            }

            // 3. Busca todos os itens que geraram estoque para este lote
            $stmtItens = $this->pdo->prepare(
                "SELECT item_id, item_produto_id, item_quantidade_finalizada 
                 FROM tbl_lote_itens WHERE item_lote_id = :lote_id AND item_quantidade_finalizada > 0"
            );
            $stmtItens->execute([':lote_id' => $loteId]);
            $itensParaEstornar = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            // 4. Itera sobre cada item para reverter o estoque
            foreach ($itensParaEstornar as $item) {
                // 4a. Cria o movimento de SAÍDA (estorno) no estoque
                $stmtEstoque = $this->pdo->prepare(
                    "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
                     VALUES (:prod_id, :lote_item_id, :qtd, 'SAIDA POR ESTORNO', :obs)"
                );
                $stmtEstoque->execute([
                    ':prod_id' => $item['item_produto_id'],
                    ':lote_item_id' => $item['item_id'],
                    ':qtd' => $item['item_quantidade_finalizada'],
                    ':obs' => "Estorno por Reabertura do Lote ID {$loteId}"
                ]);

                // 4b. Zera a quantidade finalizada no item do lote
                $this->pdo->prepare("UPDATE tbl_lote_itens SET item_quantidade_finalizada = 0 WHERE item_id = :id")
                    ->execute([':id' => $item['item_id']]);
            }

            // 5. Altera o status do lote de volta para 'EM ANDAMENTO'
            $novoStatus = 'EM ANDAMENTO';
            $stmtUpdateLote = $this->pdo->prepare("UPDATE tbl_lotes SET lote_status = :status WHERE lote_id = :id");
            $stmtUpdateLote->execute([':status' => $novoStatus, ':id' => $loteId]);

            // 6. Regista a auditoria
            $dadosNovos = $dadosAntigos;
            $dadosNovos['lote_status'] = $novoStatus;
            $this->auditLogger->log('REOPEN', $loteId, 'tbl_lotes', $dadosAntigos, $dadosNovos, $motivo);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}