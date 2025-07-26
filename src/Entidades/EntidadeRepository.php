<?php
// /src/Entidades/EntidadeRepository.php
namespace App\Entidades;

use PDO;
use PDOException;

class EntidadeRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lógica de listar_entidades.php
     */
    // SUBSTITUA O MÉTODO INTEIRO POR ESTE:
    // SUBSTITUA O MÉTODO INTEIRO PELA VERSÃO FINAL E DEFINITIVA:
    public function findAllForDataTable(array $params): array
    {
        // Parâmetros do DataTables
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';
        $orderColumnIndex = $params['order'][0]['column'] ?? 3;
        $orderDir = $params['order'][0]['dir'] ?? 'asc';
        $filtroSituacao = $params['filtro_situacao'] ?? 'Todos';
        $pageType = $params['tipo_entidade'] ?? 'cliente';
        $filtroTipoEntidade = $params['filtro_tipo_entidade'] ?? 'Todos';

        $columns = ['ent_situacao', 'ent_tipo_entidade', 'ent_codigo_interno', 'ent_razao_social', 'ent_cpf', 'end_logradouro'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'ent_razao_social';

        // AQUI ESTÁ A LÓGICA CORRETA: Definimos as colunas pesquisáveis
        $searchableColumns = ['ent_razao_social', 'ent_cpf', 'ent_cnpj', 'ent_codigo_interno'];

        $sqlBase = "FROM tbl_entidades ent LEFT JOIN (SELECT end_entidade_id, end_logradouro, end_numero, ROW_NUMBER() OVER(PARTITION BY end_entidade_id ORDER BY CASE end_tipo_endereco WHEN 'Principal' THEN 1 WHEN 'Comercial' THEN 2 ELSE 3 END, end_codigo ASC) as rn FROM tbl_enderecos) end ON ent.ent_codigo = end.end_entidade_id AND end.rn = 1";

        $conditions = [];
        $queryParams = [];

        if ($filtroTipoEntidade !== 'Todos') {
            $conditions[] = "ent.ent_tipo_entidade = :filtro_tipo_entidade";
            $queryParams[':filtro_tipo_entidade'] = $filtroTipoEntidade;
        } else {
            if (strtolower($pageType) === 'cliente') {
                $conditions[] = "(ent.ent_tipo_entidade = 'Cliente' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
            } elseif (strtolower($pageType) === 'fornecedor') {
                $conditions[] = "(ent.ent_tipo_entidade = 'Fornecedor' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
            }
        }

        if ($filtroSituacao !== 'Todos') {
            $conditions[] = "ent.ent_situacao = :filtro_situacao";
            $queryParams[':filtro_situacao'] = $filtroSituacao;
        }

        // LÓGICA DE BUSCA CORRIGIDA, IGUAL A DE PRODUTOS
        if (!empty($searchValue)) {
            $searchConditions = [];
            $searchTerm = '%' . $searchValue . '%';
            foreach ($searchableColumns as $index => $column) {
                $placeholder = ':search' . $index;
                $searchConditions[] = "$column LIKE $placeholder";
                $queryParams[$placeholder] = $searchTerm;
            }
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        $whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

        // O resto da função continua igual
        $totalRecords = $this->pdo->query("SELECT COUNT(ent_codigo) FROM tbl_entidades")->fetchColumn();
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(DISTINCT ent.ent_codigo) $sqlBase $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT ent.*, end.end_logradouro, end.end_numero $sqlBase $whereClause ORDER BY $orderColumn " . strtoupper($orderDir) . " LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
        foreach ($queryParams as $key => &$value) {
            $stmt->bindParam($key, $value);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ["draw" => (int) $draw, "recordsTotal" => (int) $totalRecords, "recordsFiltered" => (int) $totalFiltered, "data" => $data];
    }

    /**
     * Lógica de get_entidade_data.php
     */
    public function find(int $id): ?array
    {
        $query = $this->pdo->prepare("SELECT ent.*, end.end_cep, end.end_logradouro, end.end_numero, end.end_complemento, end.end_bairro, end.end_cidade, end.end_uf FROM tbl_entidades ent LEFT JOIN (SELECT *, ROW_NUMBER() OVER(PARTITION BY end_entidade_id ORDER BY CASE end_tipo_endereco WHEN 'Principal' THEN 1 ELSE 2 END) as rn FROM tbl_enderecos) end ON ent.ent_codigo = end.end_entidade_id AND end.rn = 1 WHERE ent.ent_codigo = :id");
        $query->execute([':id' => $id]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Lógica de cadastrar_entidade.php
     */
    public function create(array $data, int $userId): ?int
    {
        $this->pdo->beginTransaction();
        try {
            // Lógica de validação e inserção na tbl_entidades
            // ... (o código de validação e INSERT de cadastrar_entidade.php)
            $this->pdo->commit();
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lógica de editar_entidade.php
     */
    public function update(int $id, array $data, int $userId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // Lógica de validação, UPDATE em tbl_entidades, e INSERT/DELETE em tbl_clientes e tbl_fornecedores
            // ... (o código complexo de editar_entidade.php)
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lógica de inativar_entidade.php
     */
    public function inactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE tbl_entidades SET ent_situacao = 'I' WHERE ent_codigo = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}