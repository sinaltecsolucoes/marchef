<?php
// /src/Etiquetas/TemplateRepository.php
namespace App\Etiquetas;

use PDO;
use PDOException;
use Exception;
use App\Core\AuditLoggerService;

class TemplateRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Busca todos os templates para exibição no DataTables.
     */
    public function findAllForDataTable(array $params): array
    {
        try {
            $searchValue = $params['search']['value'] ?? '';
            $baseQuery = "FROM tbl_etiqueta_templates";

            $whereClause = "";
            if (!empty($searchValue)) {
                $whereClause = " WHERE template_nome LIKE :search_nome OR template_descricao LIKE :search_descricao";
            }

            // Conta total de registros
            $totalRecords = $this->pdo->query("SELECT COUNT(template_id) $baseQuery")->fetchColumn();

            // Conta registros filtrados
            $stmtFiltered = $this->pdo->prepare("SELECT COUNT(template_id) $baseQuery $whereClause");
            if (!empty($searchValue)) {
                $stmtFiltered->execute([
                    ':search_nome' => "%{$searchValue}%",
                    ':search_descricao' => "%{$searchValue}%"
                ]);
            } else {
                $stmtFiltered->execute();
            }
            $totalFiltered = $stmtFiltered->fetchColumn();

            // Busca os dados para a DataTable
            $sqlData = "SELECT template_id, template_nome, template_descricao, template_data_criacao $baseQuery $whereClause ORDER BY template_nome ASC LIMIT :start, :length";
            $stmt = $this->pdo->prepare($sqlData);
            $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
            if (!empty($searchValue)) {
                $stmt->bindValue(':search_nome', "%{$searchValue}%");
                $stmt->bindValue(':search_descricao', "%{$searchValue}%");
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                "draw" => intval($params['draw'] ?? 1),
                "recordsTotal" => (int) $totalRecords,
                "recordsFiltered" => (int) $totalFiltered,
                "data" => $data
            ];
        } catch (PDOException $e) {
            error_log('Erro em findAllForDataTable: ' . $e->getMessage());
            throw new Exception('Erro ao buscar templates: ' . $e->getMessage());
        }
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
        $zplContent = $data['template_conteudo_zpl']; // Padrão: usa o conteúdo da textarea

        if (isset($_FILES['zpl_file_upload']) && $_FILES['zpl_file_upload']['error'] === UPLOAD_ERR_OK) {
            $zplContent = file_get_contents($_FILES['zpl_file_upload']['tmp_name']);
            $zplContent = $this->processarPlaceholdersAutomaticos($zplContent);
            $zplContent = str_replace("\0", '', $zplContent);
        }

        $sql = "INSERT INTO tbl_etiqueta_templates (template_nome, template_descricao, template_conteudo_zpl) VALUES (:nome, :descricao, :zpl)";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':nome' => $data['template_nome'],
            ':descricao' => $data['template_descricao'] ?? null,
            ':zpl' => $zplContent // Usa o conteúdo do arquivo ou da textarea
        ]);

        if ($success) {
            $novoId = (int) $this->pdo->lastInsertId();
            $this->auditLogger->log('CREATE', $novoId, 'tbl_etiqueta_templates', null, $data);
        }

        return $success;
    }

    /**
     * Atualiza um template de etiqueta existente.
     */
    public function update(int $id, array $data): bool
    {
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos)
            return false;

        $zplContent = $data['template_conteudo_zpl']; // Padrão: usa o conteúdo da textarea

        if (isset($_FILES['zpl_file_upload']) && $_FILES['zpl_file_upload']['error'] === UPLOAD_ERR_OK) {
            $zplContent = file_get_contents($_FILES['zpl_file_upload']['tmp_name']);
            $zplContent = $this->processarPlaceholdersAutomaticos($zplContent);
            $zplContent = str_replace("\0", '', $zplContent); // <-- LINHA ADICIONADA
        }

        $sql = "UPDATE tbl_etiqueta_templates SET template_nome = :nome, template_descricao = :descricao, template_conteudo_zpl = :zpl WHERE template_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':id' => $id,
            ':nome' => $data['template_nome'],
            ':descricao' => $data['template_descricao'] ?? null,
            ':zpl' => $zplContent // Usa o conteúdo do novo arquivo ou da textarea
        ]);

        if ($success) {
            $this->auditLogger->log('UPDATE', $id, 'tbl_etiqueta_templates', $dadosAntigos, $data);
        }

        return $success;
    }
    /**
     * Exclui um template de etiqueta.
     */
    public function delete(int $id): bool
    {
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos)
            return false;

        $stmt = $this->pdo->prepare("DELETE FROM tbl_etiqueta_templates WHERE template_id = :id");
        $stmt->execute([':id' => $id]);
        $success = $stmt->rowCount() > 0;

        if ($success) {
            $this->auditLogger->log('DELETE', $id, 'tbl_etiqueta_templates', $dadosAntigos, null);
        }

        return $success;
    }

    /**
     * Processa o conteúdo ZPL para substituir dados de exemplo por placeholders do sistema.
     * @param string $zplContent O conteúdo ZPL original.
     * @return string O conteúdo ZPL com os placeholders corretos.
     */
    private function processarPlaceholdersAutomaticos(string $zplContent): string
    {
        // Define o mapa de substituições: "Dado de Exemplo" => "Placeholder do Sistema"
        $substituicoes = [
            '00000000000000' => '{dados_barras_1d}',
            '0112345678912343' => '{dados_qrcode_gs1}',
        ];

        foreach ($substituicoes as $exemplo => $placeholder) {
            // Esta forma é mais segura e substitui apenas o conteúdo dentro do comando ^FD...^FS
            $zplContent = str_replace('^FD' . $exemplo . '^FS', '^FD' . $placeholder . '^FS', $zplContent);
        }

        return $zplContent;
    }

}