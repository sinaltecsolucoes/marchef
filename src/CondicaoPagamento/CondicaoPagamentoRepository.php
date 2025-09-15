<?php
// /src/CondicaoPagamento/CondicaoPagamentoRepository.php
namespace App\CondicaoPagamento;

use PDO;
use App\Core\AuditLoggerService;

class CondicaoPagamentoRepository
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
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_condicoes_pagamento WHERE cond_id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAllForDataTable(array $params): array
    {
        $totalRecords = $this->pdo->query("SELECT COUNT(cond_id) FROM tbl_condicoes_pagamento")->fetchColumn();

        $sql = "SELECT * FROM tbl_condicoes_pagamento ORDER BY cond_descricao ASC LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sql);
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

    public function create(array $data): int
    {
        $sql = "INSERT INTO tbl_condicoes_pagamento (cond_codigo, cond_descricao, cond_dias_parcelas, cond_ativo) 
                VALUES (:codigo, :descricao, :dias, :ativo)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $data['cond_codigo'],
            ':descricao' => $data['cond_descricao'],
            ':dias' => $data['cond_dias_parcelas'] ?: null,
            ':ativo' => isset($data['cond_ativo']) ? 1 : 0
        ]);
        $newId = (int) $this->pdo->lastInsertId();
        $this->auditLogger->log('CREATE', $newId, 'tbl_condicoes_pagamento', null, $data);
        return $newId;
    }

    public function update(int $id, array $data): bool
    {
        $dadosAntigos = $this->find($id);
        $sql = "UPDATE tbl_condicoes_pagamento SET 
                    cond_codigo = :codigo, 
                    cond_descricao = :descricao, 
                    cond_dias_parcelas = :dias,
                    cond_ativo = :ativo
                WHERE cond_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':id' => $id,
            ':codigo' => $data['cond_codigo'],
            ':descricao' => $data['cond_descricao'],
            ':dias' => $data['cond_dias_parcelas'] ?: null,
            ':ativo' => isset($data['cond_ativo']) ? 1 : 0
        ]);
        $this->auditLogger->log('UPDATE', $id, 'tbl_condicoes_pagamento', $dadosAntigos, $data);
        return $success;
    }

    public function delete(int $id): bool
    {
        // Adicionar verificação de FK antes de excluir
        $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM tbl_faturamento_notas_grupo WHERE fatn_condicao_pag_id = ?");
        $stmtCheck->execute([$id]);
        if ($stmtCheck->fetchColumn() > 0) {
            throw new \Exception("Esta condição não pode ser excluída pois está em uso por um resumo de faturamento.");
        }

        $dadosAntigos = $this->find($id);
        $stmt = $this->pdo->prepare("DELETE FROM tbl_condicoes_pagamento WHERE cond_id = :id");
        $success = $stmt->execute([':id' => $id]);
        $this->auditLogger->log('DELETE', $id, 'tbl_condicoes_pagamento', $dadosAntigos, null);
        return $success;
    }
}