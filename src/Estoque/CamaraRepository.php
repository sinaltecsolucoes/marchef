<?php
// /src/Estoque/CamaraRepository.php
namespace App\Estoque;

use PDO;
use App\Core\AuditLoggerService;

class CamaraRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_estoque_camaras WHERE camara_id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAllForDataTable(array $params): array
    {
        // Lógica básica para DataTables (pode ser expandida com busca, etc.)
        $totalRecords = $this->pdo->query("SELECT COUNT(camara_id) FROM tbl_estoque_camaras")->fetchColumn();

        $sql = "SELECT * FROM tbl_estoque_camaras ORDER BY camara_nome ASC LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sql);
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

    public function create(array $data): int
    {
        $sql = "INSERT INTO tbl_estoque_camaras (camara_codigo, camara_nome, camara_descricao, camara_industria) VALUES (:codigo, :nome, :descricao, :industria)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $data['camara_codigo'],
            ':nome' => $data['camara_nome'],
            ':descricao' => $data['camara_descricao'] ?? null,
            ':industria' => $data['camara_industria'] ?? null
        ]);
        $newId = (int) $this->pdo->lastInsertId();
        $this->auditLogger->log('CREATE', $newId, 'tbl_estoque_camaras', null, $data);
        return $newId;
    }

    public function update(int $id, array $data): bool
    {
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos)
            return false;

        $sql = "UPDATE tbl_estoque_camaras SET camara_codigo = :codigo, camara_nome = :nome, camara_descricao = :descricao, camara_industria = :industria WHERE camara_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':id' => $id,
            ':codigo' => $data['camara_codigo'],
            ':nome' => $data['camara_nome'],
            ':descricao' => $data['camara_descricao'] ?? null,
            ':industria' => $data['camara_industria'] ?? null
        ]);
        $this->auditLogger->log('UPDATE', $id, 'tbl_estoque_camaras', $dadosAntigos, $data);
        return $success;
    }

    public function delete(int $id): bool
    {
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos)
            return false;

        $stmt = $this->pdo->prepare("DELETE FROM tbl_estoque_camaras WHERE camara_id = :id");
        $stmt->execute([':id' => $id]);
        $success = $stmt->rowCount() > 0;

        $this->auditLogger->log('DELETE', $id, 'tbl_estoque_camaras', $dadosAntigos, null);
        return $success;
    }
}