<?php
// /src/Core/AuditLogRepository.php

namespace App\Core;

use PDO;

class AuditLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca dados de auditoria para o DataTables com filtros avançados.
     */
    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        // Filtros personalizados
        $filtroUsuarioId = $params['filtro_usuario_id'] ?? null;
        $filtroDataInicio = $params['filtro_data_inicio'] ?? null;
        $filtroDataFim = $params['filtro_data_fim'] ?? null;

        $totalRecords = $this->pdo->query("SELECT COUNT(log_id) FROM tbl_auditoria_logs")->fetchColumn();

        // --- Construção da Cláusula WHERE e Parâmetros ---
        $whereConditions = [];
        $queryParams = [];

        if (!empty($filtroUsuarioId)) {
            $whereConditions[] = "log_usuario_id = :usuario_id";
            $queryParams[':usuario_id'] = $filtroUsuarioId;
        }

        if (!empty($filtroDataInicio)) {
            $whereConditions[] = "log_timestamp >= :data_inicio";
            $queryParams[':data_inicio'] = $filtroDataInicio . ' 00:00:00';
        }

        if (!empty($filtroDataFim)) {
            $whereConditions[] = "log_timestamp <= :data_fim";
            $queryParams[':data_fim'] = $filtroDataFim . ' 23:59:59';
        }

        if (!empty($searchValue)) {
            $whereConditions[] = "(log_usuario_nome LIKE :search OR log_acao LIKE :search OR log_tabela_afetada LIKE :search OR log_registro_id LIKE :search)";
            $queryParams[':search'] = '%' . $searchValue . '%';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // --- Contagem de Registros Filtrados ---
        $sqlFiltered = "SELECT COUNT(log_id) FROM tbl_auditoria_logs $whereClause";
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // --- Busca dos Dados da Página Atual ---
        $sqlData = "SELECT log_id, DATE_FORMAT(log_timestamp, '%d/%m/%Y %H:%i:%s') as timestamp_formatado, log_usuario_nome, log_acao, log_tabela_afetada, log_registro_id 
                    FROM tbl_auditoria_logs 
                    $whereClause 
                    ORDER BY log_timestamp DESC 
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
     * Busca os detalhes (dados antigos e novos) de um log específico.
     */
    public function getLogDetailsById(int $logId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT log_dados_antigos, log_dados_novos FROM tbl_auditoria_logs WHERE log_id = :id");
        $stmt->execute([':id' => $logId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result)
            return null;

        // Decodifica as strings JSON para arrays PHP para facilitar o uso no frontend
        $result['log_dados_antigos'] = $result['log_dados_antigos'] ? json_decode($result['log_dados_antigos'], true) : null;
        $result['log_dados_novos'] = $result['log_dados_novos'] ? json_decode($result['log_dados_novos'], true) : null;

        return $result;
    }
}