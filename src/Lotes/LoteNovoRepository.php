<?php
// /src/Lotes/LoteNovoRepository.php
namespace App\Lotes;

use PDO;
use Exception;
use App\Core\AuditLoggerService;

class LoteNovoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Salva (cria ou atualiza) o cabeçalho de um novo lote na tabela tbl_lotes_novo_header.
     *
     * @param array $data Os dados do formulário (ex: $_POST).
     * @param int $userId O ID do utilizador que está a realizar a ação.
     * @return int O ID do lote que foi salvo.
     * @throws Exception
     */
    public function saveHeader(array $data, int $userId): int
    {
        $id = filter_var($data['lote_id'] ?? null, FILTER_VALIDATE_INT);
        $dadosAntigos = null;

        if ($id) {
            // Se estivermos a editar, busca os dados antigos para a auditoria
            $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_lotes_novo_header WHERE lote_id = :id");
            $stmtAntigo->execute([':id' => $id]);
            $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);
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
            // Se já existe um ID, fazemos um UPDATE
            $sql = "UPDATE tbl_lotes_novo_header 
                    SET lote_numero = :numero, lote_data_fabricacao = :data_fab, 
                        lote_fornecedor_id = :fornecedor, lote_cliente_id = :cliente,  
                        lote_ciclo = :ciclo, lote_viveiro = :viveiro, 
                        lote_completo_calculado = :completo 
                    WHERE lote_id = :id";
            $params[':id'] = $id;
        } else {
            // Se não existe ID, fazemos um INSERT
            $sql = "INSERT INTO tbl_lotes_novo_header (
                        lote_numero, lote_data_fabricacao, lote_fornecedor_id, lote_cliente_id,
                        lote_ciclo, lote_viveiro, lote_completo_calculado, lote_usuario_id
                    ) VALUES (
                        :numero, :data_fab, :fornecedor, :cliente, 
                        :ciclo, :viveiro, :completo, :user_id
                    )";
            $params[':user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultId = $id ?: (int) $this->pdo->lastInsertId();

        // Lógica de Auditoria
        if ($id) { // Log de UPDATE
            $this->auditLogger->log('UPDATE', $resultId, 'tbl_lotes_novo_header', $dadosAntigos, $data);
        } else { // Log de CREATE
            $this->auditLogger->log('CREATE', $resultId, 'tbl_lotes_novo_header', null, $data);
        }

        return $resultId;
    }

    /**
     * Adiciona um novo item de produção (embalagem primária) a um lote.
     *
     * @param array $data Os dados do formulário do item (lote_id, produto_id, quantidade, etc.).
     * @return int O ID do novo item de produção criado.
     * @throws Exception
     */
    public function adicionarItemProducao(array $data): int
    {
        // Validação básica dos dados recebidos
        if (empty($data['item_prod_lote_id']) || empty($data['item_prod_produto_id']) || empty($data['item_prod_quantidade'])) {
            throw new Exception("Dados insuficientes para adicionar o item de produção.");
        }

        $quantidade = (float) $data['item_prod_quantidade'];

        // A lógica de negócio principal: o saldo inicial é igual à quantidade produzida.
        $params = [
            ':lote_id' => $data['item_prod_lote_id'],
            ':produto_id' => $data['item_prod_produto_id'],
            ':quantidade' => $quantidade,
            ':saldo' => $quantidade, // O saldo inicial é a própria quantidade
            ':data_validade' => $data['item_prod_data_validade'] ?: null,
        ];

        $sql = "INSERT INTO tbl_lotes_novo_producao (
                    item_prod_lote_id, item_prod_produto_id, 
                    item_prod_quantidade, item_prod_saldo, item_prod_data_validade
                ) VALUES (
                    :lote_id, :produto_id, 
                    :quantidade, :saldo, :data_validade
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $novoId = (int) $this->pdo->lastInsertId();

        // Lógica de Auditoria
        if ($novoId > 0) {
            $this->auditLogger->log('CREATE', $novoId, 'tbl_lotes_novo_producao', null, $params);
        }

        return $novoId;
    }

    /**
     * Adiciona um item de embalagem (secundária), consumindo o saldo de um item de produção (primário).
     *
     * @param array $data Contém os IDs e quantidades necessários.
     * @return int O ID do novo item de embalagem criado.
     * @throws Exception
     */
    public function adicionarItemEmbalagem(array $data): int
    {
        // Validação dos dados de entrada
        $loteId = filter_var($data['item_emb_lote_id'], FILTER_VALIDATE_INT);
        $prodSecId = filter_var($data['item_emb_prod_sec_id'], FILTER_VALIDATE_INT);
        $prodPrimId = filter_var($data['item_emb_prod_prim_id'], FILTER_VALIDATE_INT);
        $qtdSec = filter_var($data['item_emb_qtd_sec'], FILTER_VALIDATE_FLOAT);

        if (!$loteId || !$prodSecId || !$prodPrimId || !$qtdSec || $qtdSec <= 0) {
            throw new Exception("Dados insuficientes ou inválidos para adicionar o item de embalagem.");
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Busca o produto secundário para saber quantas unidades primárias ele consome
            $stmtProdSec = $this->pdo->prepare("SELECT prod_unidades_primarias FROM tbl_produtos WHERE prod_codigo = :id");
            $stmtProdSec->execute([':id' => $prodSecId]);
            $unidadesPorEmbalagem = (float) $stmtProdSec->fetchColumn();

            if ($unidadesPorEmbalagem <= 0) {
                throw new Exception("O produto de embalagem secundária não tem uma quantidade de unidades primárias válida configurada.");
            }

            // 2. Calcula a quantidade total de embalagens primárias que serão consumidas
            $qtdPrimConsumida = $qtdSec * $unidadesPorEmbalagem;

            // 3. Busca o item de produção e bloqueia a linha para evitar concorrência (FOR UPDATE)
            $stmtProdPrim = $this->pdo->prepare("SELECT item_prod_saldo FROM tbl_lotes_novo_producao WHERE item_prod_id = :id FOR UPDATE");
            $stmtProdPrim->execute([':id' => $prodPrimId]);
            $saldoAtualPrimario = (float) $stmtProdPrim->fetchColumn();

            // 4. Verifica se há saldo suficiente
            if ($saldoAtualPrimario < $qtdPrimConsumida) {
                throw new Exception("Saldo insuficiente. Saldo disponível: {$saldoAtualPrimario}, Quantidade necessária: {$qtdPrimConsumida}.");
            }

            // 5. Atualiza o saldo do item de produção (subtrai o que foi consumido)
            $novoSaldo = $saldoAtualPrimario - $qtdPrimConsumida;
            $stmtUpdateSaldo = $this->pdo->prepare("UPDATE tbl_lotes_novo_producao SET item_prod_saldo = :saldo WHERE item_prod_id = :id");
            $stmtUpdateSaldo->execute([':saldo' => $novoSaldo, ':id' => $prodPrimId]);

            // 6. Insere o novo registo na tabela de embalagens
            $params = [
                ':lote_id' => $loteId,
                ':prod_sec_id' => $prodSecId,
                ':prod_prim_id' => $prodPrimId,
                ':qtd_sec' => $qtdSec,
                ':qtd_prim_cons' => $qtdPrimConsumida,
            ];
            $sql = "INSERT INTO tbl_lotes_novo_embalagem (
                        item_emb_lote_id, item_emb_prod_sec_id, item_emb_prod_prim_id, 
                        item_emb_qtd_sec, item_emb_qtd_prim_cons
                    ) VALUES (
                        :lote_id, :prod_sec_id, :prod_prim_id, 
                        :qtd_sec, :qtd_prim_cons
                    )";
            $stmtInsert = $this->pdo->prepare($sql);
            $stmtInsert->execute($params);

            $novoId = (int) $this->pdo->lastInsertId();

            // 7. Lógica de Auditoria
            $this->auditLogger->log('CREATE', $novoId, 'tbl_lotes_novo_embalagem', null, $params);
            $this->auditLogger->log('UPDATE', $prodPrimId, 'tbl_lotes_novo_producao', ['item_prod_saldo' => $saldoAtualPrimario], ['item_prod_saldo' => $novoSaldo]);

            // 8. Se tudo correu bem, confirma as operações
            $this->pdo->commit();

            return $novoId;

        } catch (Exception $e) {
            // 9. Se algo deu errado, desfaz tudo
            $this->pdo->rollBack();
            throw $e; // Lança a exceção para que a camada superior (API) possa tratá-la
        }
    }

    /**
     * Busca os dados para a tabela de listagem de novos lotes (server-side).
     *
     * @param array $params Parâmetros do DataTables (busca, paginação, etc.).
     * @return array
     */
    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        $baseQuery = "FROM tbl_lotes_novo_header l 
                      LEFT JOIN tbl_entidades f ON l.lote_fornecedor_id = f.ent_codigo";

        $totalRecords = $this->pdo->query("SELECT COUNT(l.lote_id) FROM tbl_lotes_novo_header l")->fetchColumn();

        $whereClause = "";
        $queryParams = [];
        if (!empty($searchValue)) {
            $whereClause = " WHERE (l.lote_completo_calculado LIKE :search OR f.ent_razao_social LIKE :search)";
            $queryParams[':search'] = '%' . $searchValue . '%';
        }

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(l.lote_id) $baseQuery $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT l.*, f.ent_razao_social AS fornecedor_razao_social 
                    $baseQuery $whereClause 
                    ORDER BY l.lote_data_cadastro DESC 
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
        if (!empty($searchValue)) {
            $stmt->bindValue(':search', $queryParams[':search']);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw']),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data
        ];
    }

    /**
     * Calcula o próximo número sequencial para um novo lote.
     * @return string O próximo número formatado com 4 dígitos.
     */
    public function getNextNumero(): string
    {
        $stmt = $this->pdo->query("SELECT MAX(lote_numero) FROM tbl_lotes_novo_header");
        $proximo_numero = ($stmt->fetchColumn() ?: 0) + 1;
        return str_pad($proximo_numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Busca um lote novo completo (cabeçalho, produção e embalagem) pelo seu ID.
     * @param int $id O ID do lote (da tbl_lotes_novo_header).
     * @return array|null
     */
    public function findLoteNovoCompleto(int $id): ?array
    {
        // 1. Busca o cabeçalho
        $headerStmt = $this->pdo->prepare("SELECT * FROM tbl_lotes_novo_header WHERE lote_id = :id");
        $headerStmt->execute([':id' => $id]);
        $header = $headerStmt->fetch(PDO::FETCH_ASSOC);

        if (!$header)
            return null;

        // 2. Busca os itens de produção
        $producaoStmt = $this->pdo->prepare(
            "SELECT p.*, prod.prod_descricao 
             FROM tbl_lotes_novo_producao p 
             JOIN tbl_produtos prod ON p.item_prod_produto_id = prod.prod_codigo 
             WHERE p.item_prod_lote_id = :id ORDER BY p.item_prod_id"
        );
        $producaoStmt->execute([':id' => $id]);
        $producao = $producaoStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Busca os itens de embalagem (faremos no futuro, por agora fica vazio)
        $embalagem = [];

        return [
            'header' => $header,
            'producao' => $producao,
            'embalagem' => $embalagem
        ];
    }

}
