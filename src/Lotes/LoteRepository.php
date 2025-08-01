<?php
// /src/Lotes/LoteRepository.php
namespace App\Lotes;

use PDO;
use PDOException;

class LoteRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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

        $items = $this->pdo->prepare("SELECT li.*, p.prod_descricao, p.prod_peso_embalagem FROM tbl_lote_itens li JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo WHERE li.item_lote_id = :id");
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
    }

    public function saveItem(array $data): bool
    {
        $id = filter_var($data['item_id'] ?? null, FILTER_VALIDATE_INT);
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
        return $stmt->execute($params);
    }

    public function deleteItem(int $itemId): bool
    {
        return $this->pdo->prepare("DELETE FROM tbl_lote_itens WHERE item_id = :id")->execute([':id' => $itemId]);
    }

    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM tbl_lote_itens WHERE item_lote_id = :id")->execute([':id' => $id]);
            $this->pdo->prepare("DELETE FROM tbl_lotes WHERE lote_id = :id")->execute([':id' => $id]);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function finalize(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt_check = $this->pdo->prepare("SELECT lote_status FROM tbl_lotes WHERE lote_id = :id FOR UPDATE");
            $stmt_check->execute([':id' => $id]);
            if ($stmt_check->fetchColumn() !== 'EM ANDAMENTO') {
                throw new \Exception('Este lote não pode ser finalizado.');
            }

            $stmt_itens = $this->pdo->prepare("SELECT item_id, item_produto_id, item_quantidade FROM tbl_lote_itens WHERE item_lote_id = :id");
            $stmt_itens->execute([':id' => $id]);
            $itens_do_lote = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
            if (empty($itens_do_lote)) {
                throw new \Exception('Não é possível finalizar um lote sem produtos.');
            }

            $stmt_estoque = $this->pdo->prepare("INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento) VALUES (:produto_id, :lote_item_id, :quantidade, 'ENTRADA')");
            foreach ($itens_do_lote as $item) {
                $stmt_estoque->execute([':produto_id' => $item['item_produto_id'], ':lote_item_id' => $item['item_id'], ':quantidade' => $item['item_quantidade']]);
            }

            $this->pdo->prepare("UPDATE tbl_lotes SET lote_status = 'FINALIZADO' WHERE lote_id = :id")->execute([':id' => $id]);

            $this->pdo->commit();
            return true;
        } catch (PDOException | \Exception $e) {
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
}
