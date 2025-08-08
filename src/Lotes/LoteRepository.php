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

        $orderColumn = 'l.lote_data_cadastro'; // Simplicado, pode ser melhorado
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

        /* $items = $this->pdo->prepare("SELECT li.*, p.prod_descricao, p.prod_peso_embalagem FROM tbl_lote_itens li JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo WHERE li.item_lote_id = :id");
         $items->execute([':id' => $id]);*/
        $items = $this->pdo->prepare("SELECT li.*, p.prod_descricao, p.prod_peso_embalagem, (li.item_quantidade - li.item_quantidade_finalizada) as quantidade_pendente FROM tbl_lote_itens li JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo WHERE li.item_lote_id = :id ORDER BY li.item_status ASC, li.item_id ASC");
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
                $this->auditLogger->log('UPDATE', $id, 'tbl_lote_itens', $dadosAntigos, $data);
            } else {
                $novoId = (int) $this->pdo->lastInsertId();
                $this->auditLogger->log('CREATE', $novoId, 'tbl_lote_itens', null, $data);
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
            $this->auditLogger->log('DELETE', $itemId, 'tbl_lote_itens', $dadosAntigos, null);

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
            $this->auditLogger->log('DELETE', $id, 'tbl_lotes', $dadosAntigosHeader, null);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    public function finalize(int $id): bool
    {
        // AUDITORIA: Capturar dados antes de finalizar
        $dadosAntigos = $this->findById($id);

        $this->pdo->beginTransaction();
        try {
            // ... (código de verificação e inserção em estoque existente) ...
            $stmt_check = $this->pdo->prepare("SELECT lote_status FROM tbl_lotes WHERE lote_id = :id FOR UPDATE");
            $stmt_check->execute([':id' => $id]);
            if ($stmt_check->fetchColumn() !== 'EM ANDAMENTO') {
                throw new Exception('Este lote não pode ser finalizado.');
            }

            $stmt_itens = $this->pdo->prepare("SELECT item_id, item_produto_id, item_quantidade FROM tbl_lote_itens WHERE item_lote_id = :id");
            $stmt_itens->execute([':id' => $id]);
            $itens_do_lote = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
            if (empty($itens_do_lote)) {
                throw new Exception('Não é possível finalizar um lote sem produtos.');
            }

            $stmt_estoque = $this->pdo->prepare("INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento) VALUES (:produto_id, :lote_item_id, :quantidade, 'ENTRADA')");
            foreach ($itens_do_lote as $item) {
                $stmt_estoque->execute([':produto_id' => $item['item_produto_id'], ':lote_item_id' => $item['item_id'], ':quantidade' => $item['item_quantidade']]);
            }

            $this->pdo->prepare("UPDATE tbl_lotes SET lote_status = 'FINALIZADO' WHERE lote_id = :id")->execute([':id' => $id]);

            // AUDITORIA: Registar a finalização como um UPDATE antes de confirmar a transação
            if ($dadosAntigos) {
                $dadosNovos = $dadosAntigos;
                $dadosNovos['lote_status'] = 'FINALIZADO';
                $this->auditLogger->log('FINALIZE_LOTE', $id, 'tbl_lotes', $dadosAntigos, $dadosNovos);
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException | Exception $e) {
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
     * Finaliza uma quantidade específica de itens de um lote,
     * atualizando as quantidades, gerando estoque e atualizando o status do lote.
     * @param int $loteId O ID do lote a ser finalizado.
     * @param array $itensAFinalizar Array de itens, ex: [['item_id' => 1, 'quantidade' => 10.5], ...]
     * @return bool
     * @throws Exception
     */
    public function finalizeParcialmente(int $loteId, array $itensAFinalizar): bool
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($itensAFinalizar as $itemData) {
                $itemId = $itemData['item_id'];
                $quantidadeAFinalizar = (float) $itemData['quantidade'];

                if ($quantidadeAFinalizar <= 0)
                    continue;

                // 1. Busca e bloqueia o item
                $stmtItem = $this->pdo->prepare("SELECT * FROM tbl_lote_itens WHERE item_id = :id AND item_lote_id = :lote_id FOR UPDATE");
                $stmtItem->execute([':id' => $itemId, ':lote_id' => $loteId]);
                $itemAtual = $stmtItem->fetch(PDO::FETCH_ASSOC);

                if (!$itemAtual)
                    throw new Exception("Item com ID {$itemId} não encontrado ou não pertence ao lote.");

                // 2. Valida a quantidade
                $quantidadePendente = (float) $itemAtual['item_quantidade'] - (float) $itemAtual['item_quantidade_finalizada'];
                if ($quantidadeAFinalizar > $quantidadePendente + 0.001) {
                    throw new Exception("A quantidade a finalizar ({$quantidadeAFinalizar}kg) é maior que a pendente ({$quantidadePendente}kg) para o item ID {$itemId}.");
                }

                // 3. Adiciona ao estoque
                $stmtEstoque = $this->pdo->prepare("INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento) VALUES (:produto_id, :lote_item_id, :quantidade, 'ENTRADA')");
                $stmtEstoque->execute([':produto_id' => $itemAtual['item_produto_id'], ':lote_item_id' => $itemId, ':quantidade' => $quantidadeAFinalizar]);

                // 4. Atualiza o item do lote
                $novaQtdFinalizada = (float) $itemAtual['item_quantidade_finalizada'] + $quantidadeAFinalizar;
                $novoStatusItem = ((float) $itemAtual['item_quantidade'] <= $novaQtdFinalizada) ? 'FINALIZADO' : 'EM PRODUCAO';
                $stmtUpdateItem = $this->pdo->prepare("UPDATE tbl_lote_itens SET item_quantidade_finalizada = :qtd_finalizada, item_status = :status WHERE item_id = :id");
                $stmtUpdateItem->execute([':qtd_finalizada' => $novaQtdFinalizada, ':status' => $novoStatusItem, ':id' => $itemId]);
            }

            // 5. Após processar todos os itens, verifica o estado geral do lote
            /*  $stmtVerificaPendente = $this->pdo->prepare(
                  "SELECT SUM(item_quantidade - item_quantidade_finalizada) as total_pendente 
                   FROM tbl_lote_itens WHERE item_lote_id = :lote_id"
              );
              $stmtVerificaPendente->execute([':lote_id' => $loteId]);
              $totalPendente = (float) $stmtVerificaPendente->fetchColumn();

              $novoStatusLote = '';
              if ($totalPendente <= 0.001) { // Se não há mais nada pendente
                  $novoStatusLote = 'FINALIZADO';
              } else { // Se ainda há itens pendentes
                  $novoStatusLote = 'PARCIALMENTE FINALIZADO';
              }

              // 6. Atualiza o status do cabeçalho do lote
              $stmtUpdateLote = $this->pdo->prepare("UPDATE tbl_lotes SET lote_status = :status WHERE lote_id = :id");
              $stmtUpdateLote->execute([':status' => $novoStatusLote, ':id' => $loteId]);*/

            $novoStatusLote = $this->atualizarStatusGeralDoLote($loteId);

            // AUDITORIA
            $this->auditLogger->log(
                'FINALIZE_PARTIAL',
                $loteId,
                'tbl_lotes',
                null,
                [
                    'itens_finalizados' => $itensAFinalizar,
                    '
                                                  novo_status_lote' => $novoStatusLote
                ]
            );

            $this->pdo->commit();
            return true;

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
            $this->auditLogger->log('CANCEL_LOTE', $loteId, 'tbl_lotes', $dadosAntigos, $dadosNovos);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Busca itens de lote com estoque pendente para uso em selects (como no Select2).
     * (Versão Corrigida)
     * @param string $searchTerm O termo de busca para filtrar produtos.
     * @return array
     */
    /*  public function findItensEmEstoqueParaSelect(string $searchTerm = ''): array
      {
          $sql = "SELECT 
                      li.item_id as id,
                      CONCAT(p.prod_descricao, ' (Lote: ', lh.lote_completo_calculado, ' | Pendente: ', FORMAT((li.item_quantidade - li.item_quantidade_finalizada), 3, 'de_DE'), ' kg)') as text
                  FROM tbl_lote_itens li
                  JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo
                  JOIN tbl_lotes lh ON li.item_lote_id = lh.lote_id
                  WHERE 
                      (li.item_quantidade - li.item_quantidade_finalizada) > 0.001
                      AND p.prod_descricao LIKE :term
                  ORDER BY p.prod_descricao ASC, lh.lote_data_fabricacao ASC
                  LIMIT 20";

          $stmt = $this->pdo->prepare($sql);
          $stmt->execute([':term' => '%' . $searchTerm . '%']);
          return $stmt->fetchAll(PDO::FETCH_ASSOC);
      }*/

    /**
     * Busca itens de lote para uso em selects.
     * (Versão Corrigida - com texto 'Pendente' condicional)
     * @param string $searchTerm O termo de busca para filtrar produtos.
     * @return array
     */
    /* public function findItensEmEstoqueParaSelect(string $searchTerm = ''): array
     {
         $sql = "SELECT 
                     li.item_id as id,

                     CONCAT(
                         p.prod_descricao, 
                         ' (Lote: ', 
                         lh.lote_completo_calculado, 
                         IF(
                             lh.lote_status != 'FINALIZADO',
                             CONCAT(' | Pendente: ', FORMAT((li.item_quantidade - li.item_quantidade_finalizada), 3, 'de_DE'), ' kg'),
                             ''
                         ),
                         ')'
                     ) as text
                 FROM tbl_lote_itens li
                 JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo
                 JOIN tbl_lotes lh ON li.item_lote_id = lh.lote_id
                 WHERE 
                     (li.item_quantidade - li.item_quantidade_finalizada) > 0.001
                     AND p.prod_descricao LIKE :term
                 ORDER BY lh.lote_status ASC, p.prod_descricao ASC, lh.lote_data_fabricacao ASC
                 LIMIT 20";

         $stmt = $this->pdo->prepare($sql);
         $stmt->execute([':term' => '%' . $searchTerm . '%']);
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
     }*/


    /**
     * Busca itens em estoque para o Select2, pesquisando por descrição ou código interno.
     * (Versão Definitiva - Corrigido o erro HY093)
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

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
