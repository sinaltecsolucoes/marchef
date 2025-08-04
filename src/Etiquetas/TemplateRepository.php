<?php
// /src/Etiquetas/TemplateRepository.php
namespace App\Etiquetas;

use PDO;
use PDOException;

class TemplateRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca todos os templates para exibição no DataTables.
     */
    public function findAllForDataTable(array $params): array
    {
        $searchValue = $params['search']['value'] ?? '';
        $baseQuery = "FROM tbl_etiqueta_templates";
        
        $whereClause = "";
        if (!empty($searchValue)) {
            $whereClause = " WHERE template_nome LIKE :search OR template_descricao LIKE :search";
        }

        $totalRecords = $this->pdo->query("SELECT COUNT(template_id) $baseQuery")->fetchColumn();
        
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(template_id) $baseQuery $whereClause");
        $stmtFiltered->execute(!empty($searchValue) ? [':search' => "%{$searchValue}%"] : []);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT template_id, template_nome, template_descricao, template_data_criacao $baseQuery $whereClause ORDER BY template_nome ASC LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int)($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)($params['length'] ?? 10), PDO::PARAM_INT);
        if (!empty($searchValue)) {
            $stmt->bindValue(':search', "%{$searchValue}%");
        }
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
     * Busca um único template pelo seu ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_etiqueta_templates WHERE template_id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Cria um novo template de etiqueta.
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO tbl_etiqueta_templates (template_nome, template_descricao, template_conteudo_zpl) VALUES (:nome, :descricao, :zpl)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':nome' => $data['template_nome'],
            ':descricao' => $data['template_descricao'] ?? null,
            ':zpl' => $data['template_conteudo_zpl']
        ]);
    }

    /**
     * Atualiza um template de etiqueta existente.
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE tbl_etiqueta_templates SET template_nome = :nome, template_descricao = :descricao, template_conteudo_zpl = :zpl WHERE template_id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':nome' => $data['template_nome'],
            ':descricao' => $data['template_descricao'] ?? null,
            ':zpl' => $data['template_conteudo_zpl']
        ]);
    }

    /**
     * Exclui um template de etiqueta.
     */
    public function delete(int $id): bool
    {
        // Futuramente, podemos adicionar uma verificação para não excluir um template que está em uso em alguma regra.
        $stmt = $this->pdo->prepare("DELETE FROM tbl_etiqueta_templates WHERE template_id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}