<?php
// /src/Produtos/ProdutoRepository.php
namespace App\Produtos;

use PDO;
use PDOException;

class ProdutoRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca dados para o DataTables, com paginação, busca e ordenação.
     */
    // SUBSTITUA O MÉTODO INTEIRO POR ESTE:
    // SUBSTITUA O MÉTODO INTEIRO POR ESTE:
    // SUBSTITUA O MÉTODO INTEIRO PELA VERSÃO FINAL E DEFINITIVA:
    public function findAllForDataTable(array $params): array
    {
        // Parâmetros do DataTables
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';
        $orderColumnIndex = $params['order'][0]['column'] ?? 1;
        $orderDir = $params['order'][0]['dir'] ?? 'asc';
        $filtroSituacao = $params['filtro_situacao'] ?? 'Todos';

        // Colunas para ordenação e busca
        $columns = ['prod_situacao', 'prod_codigo_interno', 'prod_descricao', 'prod_tipo', 'prod_tipo_embalagem', 'prod_peso_embalagem'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'prod_codigo_interno';

        // Colunas em que a busca será aplicada
        $searchableColumns = ['prod_codigo_interno', 'prod_descricao', 'prod_tipo', 'prod_tipo_embalagem', 'prod_situacao', 'prod_peso_embalagem'];

        // --- Contagem Total de Registros ---
        $totalRecords = $this->pdo->query("SELECT COUNT(prod_codigo) FROM tbl_produtos")->fetchColumn();

        // --- Construção da Cláusula WHERE e Parâmetros ---
        $whereConditions = [];
        $queryParams = [];

        // Filtro por Situação
        if ($filtroSituacao !== 'Todos') {
            $whereConditions[] = "prod_situacao = :filtro_situacao";
            $queryParams[':filtro_situacao'] = $filtroSituacao;
        }

        // Filtro por Busca (USANDO A SUA LÓGICA DE MÚLTIPLOS PARÂMETROS)
        if (!empty($searchValue)) {
            $searchConditions = [];
            $searchTerm = '%' . $searchValue . '%';
            foreach ($searchableColumns as $index => $column) {
                $placeholder = ':search' . $index;
                $searchConditions[] = "$column LIKE $placeholder";
                $queryParams[$placeholder] = $searchTerm;
            }
            $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // --- Contagem de Registros Filtrados ---
        $sqlFiltered = "SELECT COUNT(prod_codigo) FROM tbl_produtos $whereClause";
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // --- Busca dos Dados da Página Atual ---
        $sql = "SELECT * FROM tbl_produtos $whereClause ORDER BY $orderColumn $orderDir LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sql);

        // Vincula os parâmetros da cláusula WHERE (agora com múltiplos :searchX)
        foreach ($queryParams as $key => &$value) {
            $stmt->bindParam($key, $value);
        }

        // Vincula os parâmetros do LIMIT com tipo explícito
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);

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
     * Busca um único produto pelo ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_produtos WHERE prod_codigo = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Cria um novo produto.
     */
    // /src/Produtos/ProdutoRepository.php

    public function create(array $data): bool
    {
        // A query correta, que OMITE a coluna prod_codigo do INSERT
        $sql = "INSERT INTO tbl_produtos (prod_codigo_interno, prod_descricao, prod_situacao, prod_tipo, prod_subtipo, prod_classificacao, prod_especie, prod_origem, prod_conservacao, prod_congelamento, prod_fator_producao, prod_tipo_embalagem, prod_peso_embalagem, prod_total_pecas, prod_validade_meses, prod_primario_id, prod_ean13, prod_dun14) VALUES (:prod_codigo_interno, :prod_descricao, 'A', :prod_tipo, :prod_subtipo, :prod_classificacao, :prod_especie, :prod_origem, :prod_conservacao, :prod_congelamento, :prod_fator_producao, :prod_tipo_embalagem, :prod_peso_embalagem, :prod_total_pecas, :prod_validade_meses, :prod_primario_id, :prod_ean13, :prod_dun14)";
        $stmt = $this->pdo->prepare($sql);

        $params = $this->prepareData($data);

        // Remove o parâmetro :prod_codigo que não é usado no INSERT
        unset($params[':prod_codigo']);

        return $stmt->execute($params);
    }

    /**
     * Atualiza um produto existente.
     * Lógica movida de editar_produto.php
     */
    public function update(int $id, array $data): bool
    {
        $data['prod_codigo'] = $id; // Adiciona o ID para o binding
        $sql = "UPDATE tbl_produtos SET prod_codigo_interno = :prod_codigo_interno, prod_descricao = :prod_descricao, prod_tipo = :prod_tipo, prod_subtipo = :prod_subtipo, prod_classificacao = :prod_classificacao, prod_especie = :prod_especie, prod_origem = :prod_origem, prod_conservacao = :prod_conservacao, prod_congelamento = :prod_congelamento, prod_fator_producao = :prod_fator_producao, prod_tipo_embalagem = :prod_tipo_embalagem, prod_peso_embalagem = :prod_peso_embalagem, prod_total_pecas = :prod_total_pecas, prod_validade_meses = :validade_meses, prod_primario_id = :prod_primario_id, prod_ean13 = :prod_ean13, prod_dun14 = :prod_dun14 WHERE prod_codigo = :prod_codigo";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($this->prepareData($data));
    }

    /**
     * Exclui um produto.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tbl_produtos WHERE prod_codigo = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // Método auxiliar privado para preparar os dados para INSERT/UPDATE
    private function prepareData(array $data): array
    {
        $tipo_embalagem = $data['prod_tipo_embalagem'];
        $peso_embalagem = ($tipo_embalagem === 'SECUNDARIA')
            ? (!empty($data['prod_peso_embalagem_secundaria']) ? str_replace(',', '.', $data['prod_peso_embalagem_secundaria']) : null)
            : (!empty($data['prod_peso_embalagem']) ? str_replace(',', '.', $data['prod_peso_embalagem']) : null);

        return [
            ':prod_codigo_interno' => !empty($data['prod_codigo_interno']) ? $data['prod_codigo_interno'] : null,
            ':prod_descricao' => $data['prod_descricao'],
            ':prod_tipo' => $data['prod_tipo'],
            ':prod_subtipo' => !empty($data['prod_subtipo']) ? $data['prod_subtipo'] : null,
            ':prod_classificacao' => !empty($data['prod_classificacao']) ? $data['prod_classificacao'] : null,
            ':prod_especie' => !empty($data['prod_especie']) ? $data['prod_especie'] : null,
            ':prod_origem' => $data['prod_origem'],
            ':prod_conservacao' => $data['prod_conservacao'],
            ':prod_congelamento' => $data['prod_congelamento'],
            ':prod_fator_producao' => !empty($data['prod_fator_producao']) ? str_replace(',', '.', $data['prod_fator_producao']) : null,
            ':prod_tipo_embalagem' => $tipo_embalagem,
            ':prod_peso_embalagem' => $peso_embalagem,
            ':prod_total_pecas' => !empty($data['prod_total_pecas']) ? $data['prod_total_pecas'] : null,
            ':prod_validade_meses' => !empty($data['prod_validade_meses']) ? (int) $data['prod_validade_meses'] : null, // Nome padronizado
            ':prod_primario_id' => !empty($data['prod_primario_id']) ? $data['prod_primario_id'] : null,
            ':prod_ean13' => !empty($data['prod_ean13']) ? $data['prod_ean13'] : null,
            ':prod_dun14' => !empty($data['prod_dun14']) ? $data['prod_dun14'] : null,
            ':prod_codigo' => $data['prod_codigo'] ?? null,
        ];
    }

    public function findPrimarios(): ?array
    {
        $stmt = $this->pdo->query("SELECT prod_codigo, prod_descricao, prod_peso_embalagem, prod_tipo, prod_subtipo, prod_classificacao, prod_especie, prod_origem, prod_conservacao, prod_congelamento, prod_fator_producao, prod_total_pecas, prod_codigo_interno FROM tbl_produtos WHERE prod_tipo_embalagem = 'PRIMARIA' AND prod_situacao = 'A' ORDER BY prod_descricao");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}