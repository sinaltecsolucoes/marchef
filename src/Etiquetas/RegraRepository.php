<?php
// /src/Etiquetas/RegraRepository.php
namespace App\Etiquetas;

use PDO;
use App\Core\AuditLoggerService;

class RegraRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Busca todas as regras para exibição no DataTables.
     * Realiza JOINs para mostrar os nomes em vez dos IDs.
     */
    public function findAllForDataTable(array $params): array
    {
        $baseQuery = "FROM tbl_etiqueta_regras r
                      LEFT JOIN tbl_produtos p ON r.regra_produto_id = p.prod_codigo
                      LEFT JOIN tbl_entidades e ON r.regra_cliente_id = e.ent_codigo
                      JOIN tbl_etiqueta_templates t ON r.regra_template_id = t.template_id";

        // Lógica de busca e paginação (simplificada por enquanto)
        $totalRecords = $this->pdo->query("SELECT COUNT(r.regra_id) FROM tbl_etiqueta_regras r")->fetchColumn();
        $totalFiltered = $totalRecords; // Simplificado

        $sqlData = "SELECT 
                        r.regra_id,
                        COALESCE(p.prod_descricao, 'Todos os Produtos') AS produto_nome,
                        COALESCE(e.ent_razao_social, 'Todos os Clientes') AS cliente_nome,
                        t.template_nome,
                        r.regra_prioridade
                    $baseQuery 
                    ORDER BY r.regra_prioridade ASC, cliente_nome ASC, produto_nome ASC
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data
        ];
    }

    /**
     * Busca uma única regra pelo seu ID.
     */
    /*   public function find(int $id): ?array
       {
           $stmt = $this->pdo->prepare("SELECT * FROM tbl_etiqueta_regras WHERE regra_id = :id");
           $stmt->execute([':id' => $id]);
           return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
       } */

    public function find(int $id): ?array
    {
        // Agora faz JOINs para buscar os nomes/textos para os Selects
        $sql = "SELECT 
                r.*,
                -- Usamos o CONCAT/COALESCE que já criamos para a função getClienteOptions
                CONCAT(COALESCE(NULLIF(e.ent_nome_fantasia, ''), e.ent_razao_social), ' (Cód: ', COALESCE(e.ent_codigo_interno, 'N/A'), ')') AS cliente_text,
                p.prod_descricao AS produto_text,
                t.template_nome AS template_text
            FROM tbl_etiqueta_regras r
            LEFT JOIN tbl_entidades e ON r.regra_cliente_id = e.ent_codigo
            LEFT JOIN tbl_produtos p ON r.regra_produto_id = p.prod_codigo
            LEFT JOIN tbl_etiqueta_templates t ON r.regra_template_id = t.template_id
            WHERE r.regra_id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Cria ou atualiza uma regra.
     */
    public function save(array $data): bool
    {
        $id = filter_var($data['regra_id'] ?? null, FILTER_VALIDATE_INT);
        $dadosAntigos = null;

        // Se for uma atualização, busca os dados antigos primeiro
        if ($id) {
            $dadosAntigos = $this->find($id);
        }

        $params = [
            ':produto_id' => !empty($data['regra_produto_id']) ? $data['regra_produto_id'] : null,
            ':cliente_id' => !empty($data['regra_cliente_id']) ? $data['regra_cliente_id'] : null,
            ':template_id' => $data['regra_template_id'],
            ':prioridade' => $data['regra_prioridade'] ?? 10
        ];

        if ($id) {
            $sql = "UPDATE tbl_etiqueta_regras SET regra_produto_id = :produto_id, regra_cliente_id = :cliente_id, regra_template_id = :template_id, regra_prioridade = :prioridade WHERE regra_id = :id";
            $params[':id'] = $id;
        } else {
            $sql = "INSERT INTO tbl_etiqueta_regras (regra_produto_id, regra_cliente_id, regra_template_id, regra_prioridade) VALUES (:produto_id, :cliente_id, :template_id, :prioridade)";
        }

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute($params);

        if ($success) {
            if ($id) { // Se tinha um ID, é um log de UPDATE
                $this->auditLogger->log('UPDATE', $id, 'tbl_etiqueta_regras', $dadosAntigos, $data);
            } else { // Senão, é um log de CREATE
                $novoId = (int) $this->pdo->lastInsertId();
                $this->auditLogger->log('CREATE', $novoId, 'tbl_etiqueta_regras', null, $data);
            }
        }

        return $success;
    }

    /**
     * Exclui uma regra.
     */
    public function delete(int $id): bool
    {
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos)
            return false;

        $stmt = $this->pdo->prepare("DELETE FROM tbl_etiqueta_regras WHERE regra_id = :id");
        $stmt->execute([':id' => $id]);
        $success = $stmt->rowCount() > 0;

        if ($success) {
            $this->auditLogger->log('DELETE', $id, 'tbl_etiqueta_regras', $dadosAntigos, null);
        }

        return $success;
    }

    /**
     * Busca todos os templates para usar em um dropdown.
     */
    public function getTemplateOptions(): array
    {
        $stmt = $this->pdo->query("SELECT template_id, template_nome FROM tbl_etiqueta_templates ORDER BY template_nome ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Encontra o ID do template com base nas regras de negócio e prioridade.
     *
     * A lógica de prioridade funciona da seguinte forma:
     * 1. Procura uma regra que corresponda exatamente ao produto E ao cliente.
     * 2. Se não encontrar, procura uma regra que corresponda apenas ao cliente (para todos os produtos).
     * 3. Se não encontrar, procura uma regra que corresponda apenas ao produto (para todos os clientes).
     * 4. Se não encontrar, procura uma regra global (sem cliente e sem produto definidos).
     * O campo 'regra_prioridade' (quanto menor, maior a prioridade) é usado como o principal critério de ordenação.
     *
     * @param int|null $produtoId
     * @param int|null $clienteId
     * @return int|null O ID do template a ser usado, ou null se nenhuma regra for encontrada.
     */
    public function findTemplateIdByRule(?int $produtoId, ?int $clienteId): ?int
    {
        $sql = "SELECT regra_template_id 
                FROM tbl_etiqueta_regras
                WHERE 
                    (regra_produto_id = :produto_id OR regra_produto_id IS NULL)
                    AND 
                    (regra_cliente_id = :cliente_id OR regra_cliente_id IS NULL)
                ORDER BY
                    -- Regra mais específica tem prioridade máxima
                    (CASE WHEN regra_produto_id IS NOT NULL AND regra_cliente_id IS NOT NULL THEN 0 ELSE 1 END),
                    (CASE WHEN regra_cliente_id IS NOT NULL AND regra_produto_id IS NULL THEN 2 ELSE 3 END),
                    (CASE WHEN regra_produto_id IS NOT NULL AND regra_cliente_id IS NULL THEN 4 ELSE 5 END),
                    -- Dentro da mesma especificidade, a prioridade manual desempata
                    regra_prioridade ASC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':produto_id' => $produtoId,
            ':cliente_id' => $clienteId
        ]);

        $result = $stmt->fetchColumn();

        return $result ? (int) $result : null;
    }
}
