<?php
// /src/Produtos/ProdutoRepository.php
namespace App\Produtos;

use PDO;
use Exception;
use App\Core\AuditLoggerService;

class ProdutoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Busca dados para o DataTables, com paginação, busca e ordenação.
     */
    /*   public function findAllForDataTable(array $params): array
    {
        // Parâmetros do DataTables
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';
        $orderColumnIndex = $params['order'][0]['column'] ?? 1;
        $orderDir = $params['order'][0]['dir'] ?? 'asc';

        // Filtros
        // $filtroSituacao = !empty($params['filtro_situacao']) ? $params['filtro_situacao'] : 'Todos';
        // $filtroTipo = !empty($params['filtro_tipo']) ? $params['filtro_tipo'] : 'Todos';
        $filtroSituacao = $params['filtro_situacao'] ?? [];
        $filtroTipo = $params['filtro_tipo'] ?? [];
        $filtroMarcas = $params['filtro_marcas'] ?? [];

        // Colunas para ordenação e busca
        $columns = ['prod_situacao', 'prod_codigo_interno', 'prod_classe', 'prod_descricao', 'prod_tipo', 'prod_tipo_embalagem', 'prod_peso_embalagem'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'prod_codigo_interno';

        // Colunas em que a busca será aplicada
        $searchableColumns = ['prod_codigo_interno', 'prod_classe', 'prod_descricao', 'prod_tipo', 'prod_tipo_embalagem', 'prod_situacao', 'prod_peso_embalagem'];

        // --- Contagem Total de Registros ---
        $totalRecords = $this->pdo->query("SELECT COUNT(prod_codigo) FROM tbl_produtos")->fetchColumn();

        // --- Construção da Query base, Cláusula WHERE e Parâmetros ---
        $sqlBase = "FROM tbl_produtos";
        $whereConditions = [];
        $queryParams = [];

        // Filtro por Situação
        if (!empty($filtroSituacao) && !in_array('TODOS', $filtroSituacao)) {
            // $situacao = ($filtroSituacao === 'Ativo' || $filtroSituacao === 'A') ? 'A' : (($filtroSituacao === 'Inativo' || $filtroSituacao === 'I') ? 'I' : '');

            // Cria placeholders: :sit0, :sit1, etc.
            $placeholders = [];
            foreach ($filtroSituacao as $i => $val) {
                $key = ":sit{$i}";
                $placeholders[] = $key;
                $queryParams[$key] = $val;
            }
            $whereConditions[] = "prod_situacao IN (" . implode(',', $placeholders) . ")";
          
        }

        // Filtro de Tipo de Embalagem 
        if (!empty($filtroTipo) && !in_array('TODOS', $filtroTipo)) {
            $placeholders = [];
            foreach ($filtroTipo as $i => $val) {
                $key = ":tipo{$i}";
                $placeholders[] = $key;
                $queryParams[$key] = $val;
            }
            $whereConditions[] = "prod_tipo_embalagem IN (" . implode(',', $placeholders) . ")";
            // $queryParams[':tipo'] = $filtroTipo;
        }

        // Filtro Marcas
        if (!empty($filtroMarcas) && !in_array('TODOS', $filtroMarcas)) {
            $placeholders = [];
            foreach ($filtroMarcas as $i => $val) {
                $key = ":marca{$i}";
                $placeholders[] = $key;
                $queryParams[$key] = $val;
            }
            $whereConditions[] = "prod_marca IN (" . implode(',', $filtroMarcas);
        }

        // Filtro por Busca
        if (!empty($searchValue)) {
            $searchableColumns = [
                'prod_codigo_interno',
                'prod_classe',
                'prod_descricao',
                'prod_tipo',
                'prod_tipo_embalagem',
                'prod_situacao',
                'prod_peso_embalagem',
                'prod_marca'
            ];
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
        $totalRecords = $this->pdo->query("SELECT COUNT(prod_codigo) FROM tbl_produtos")->fetchColumn();

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(prod_codigo) $sqlBase $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // --- Busca dos Dados da Página Atual ---
        $sql = "SELECT * $sqlBase $whereClause ORDER BY $orderColumn $orderDir LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);

        // Vincula os parâmetros da cláusula WHERE (agora com múltiplos :searchX)
        foreach ($queryParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        // Vincula os parâmetros do LIMIT com tipo explícito
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => (int) $draw,
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data ?? []
        ];
    } */

    public function findAllForDataTable(array $params): array
    {
        // Parâmetros do DataTables
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';
        $orderColumnIndex = $params['order'][0]['column'] ?? 1;
        $orderDir = $params['order'][0]['dir'] ?? 'asc';

        // Mapeamento de Colunas
        $columns = [
            0 => 'prod_situacao',
            1 => 'prod_codigo_interno',
            2 => 'prod_descricao',
            3 => 'prod_marca',
            4 => 'prod_tipo',
            5 => 'prod_tipo_embalagem',
            6 => 'prod_peso_embalagem',
            7 => 'prod_codigo' // Ações
        ];
        $orderColumn = $columns[$orderColumnIndex] ?? 'prod_descricao';

        // --- PREPARAÇÃO DOS FILTROS (Lógica Robusta) ---
        $whereConditions = [];
        $queryParams = [];

        // 1. FILTRO SITUAÇÃO
        $filtroSituacao = $params['filtro_situacao'] ?? [];
        // Se vier string "TODOS" ou array contendo "TODOS" ou vazio, ignora o filtro.
        if (!empty($filtroSituacao) && $filtroSituacao !== 'TODOS' && !in_array('TODOS', (array)$filtroSituacao)) {
            $placeholders = [];
            foreach ((array)$filtroSituacao as $i => $val) {
                $key = ":sit_{$i}"; // Nome único para o parâmetro
                $placeholders[] = $key;
                $queryParams[$key] = $val;
            }
            if (!empty($placeholders)) {
                $whereConditions[] = "prod_situacao IN (" . implode(',', $placeholders) . ")";
            }
        }

        // 2. FILTRO TIPO DE EMBALAGEM
        $filtroTipo = $params['filtro_tipo'] ?? [];
        if (!empty($filtroTipo) && $filtroTipo !== 'TODOS' && !in_array('TODOS', (array)$filtroTipo)) {
            $placeholders = [];
            foreach ((array)$filtroTipo as $i => $val) {
                $key = ":tipo_{$i}";
                $placeholders[] = $key;
                $queryParams[$key] = $val;
            }
            if (!empty($placeholders)) {
                $whereConditions[] = "prod_tipo_embalagem IN (" . implode(',', $placeholders) . ")";
            }
        }

        // 3. FILTRO MARCAS
        $filtroMarcas = $params['filtro_marcas'] ?? [];
        if (!empty($filtroMarcas) && $filtroMarcas !== 'TODOS' && !in_array('TODOS', (array)$filtroMarcas)) {
            $placeholders = [];
            foreach ((array)$filtroMarcas as $i => $val) {
                $key = ":marca_{$i}";
                $placeholders[] = $key;
                $queryParams[$key] = $val;
            }
            if (!empty($placeholders)) {
                $whereConditions[] = "prod_marca IN (" . implode(',', $placeholders) . ")";
            }
        }

        // 4. BUSCA GLOBAL (Search Box)
        if (!empty($searchValue)) {
            $term = '%' . $searchValue . '%';
            $whereConditions[] = "(
                prod_codigo_interno LIKE :search_cod OR 
                prod_descricao LIKE :search_desc OR 
                prod_marca LIKE :search_marca OR
                prod_tipo LIKE :search_tipo
            )";
            $queryParams[':search_cod'] = $term;
            $queryParams[':search_desc'] = $term;
            $queryParams[':search_marca'] = $term;
            $queryParams[':search_tipo'] = $term;
        }

        // Montagem da Query
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Count Total
        $totalRecords = $this->pdo->query("SELECT COUNT(prod_codigo) FROM tbl_produtos")->fetchColumn();

        // Count Filtered
        $stmtCount = $this->pdo->prepare("SELECT COUNT(prod_codigo) FROM tbl_produtos $whereClause");
        foreach ($queryParams as $k => $v) {
            $stmtCount->bindValue($k, $v);
        }
        $stmtCount->execute();
        $totalFiltered = $stmtCount->fetchColumn();

        // Data Query
        $sql = "SELECT * FROM tbl_produtos $whereClause ORDER BY $orderColumn $orderDir LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sql);

        // Bind dos parâmetros de filtro
        foreach ($queryParams as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        // Bind da paginação (INT obrigatório)
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($draw),
            "recordsTotal" => intval($totalRecords),
            "recordsFiltered" => intval($totalFiltered),
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
    public function create(array $data): bool
    {

        // ### BLOCO DE VALIDAÇÃO ###
        $descricao = $data['prod_descricao'] ?? '';
        $codInterno = $data['prod_codigo_interno'] ?? null;

        // Validação 1: Código Interno (só validamos se não for nulo ou vazio)
        if ($codInterno !== null && $codInterno !== '') {
            $stmtCheckCod = $this->pdo->prepare("SELECT COUNT(*) FROM tbl_produtos WHERE prod_codigo_interno = :cod");
            $stmtCheckCod->execute([':cod' => $codInterno]);
            if ($stmtCheckCod->fetchColumn() > 0) {
                // Lança uma Exceção que será capturada pelo ajax_router
                //  throw new ("Validação falhou: O Código Interno '{$codInterno}' já está em uso por outro produto.");
                throw new Exception("Validação falhou: O Código Interno '{$codInterno}' já está em uso por outro produto.");
            }
        }

        // Validação 2: Descrição Exata
        $stmtCheckDesc = $this->pdo->prepare("SELECT COUNT(*) FROM tbl_produtos WHERE prod_descricao = :desc");
        $stmtCheckDesc->execute([':desc' => $descricao]);
        if ($stmtCheckDesc->fetchColumn() > 0) {
            throw new Exception("Validação falhou: A Descrição '{$descricao}' já está em uso por outro produto.");
        }
        // ### FIM DO BLOCO DE VALIDAÇÃO ###

        $sql = "INSERT INTO tbl_produtos (
                    prod_codigo_interno, prod_descricao, prod_situacao, prod_tipo, prod_subtipo, prod_classificacao, 
                    prod_categoria, prod_classe, prod_especie, prod_origem, prod_conservacao, prod_congelamento, 
                    prod_fator_producao, prod_tipo_embalagem, prod_peso_embalagem, prod_total_pecas, 
                    prod_validade_meses, prod_unidade, prod_primario_id, prod_ean13, prod_dun14, prod_ncm, prod_marca) 
                VALUES (
                    :prod_codigo_interno, :prod_descricao, :prod_situacao, :prod_tipo, :prod_subtipo, :prod_classificacao, 
                    :prod_categoria, :prod_classe, :prod_especie, :prod_origem, :prod_conservacao, 
                    :prod_congelamento, :prod_fator_producao, :prod_tipo_embalagem, :prod_peso_embalagem, 
                    :prod_total_pecas, :prod_validade_meses, :prod_unidade, :prod_primario_id, :prod_ean13, :prod_dun14, :prod_ncm, :prod_marca
 
                )";
        $stmt = $this->pdo->prepare($sql);
        $params = $this->prepareData($data);
        unset($params[':prod_codigo']); // Remove o parâmetro não usado no INSERT

        $success = $stmt->execute($params);

        if ($success) {
            $novoId = (int) $this->pdo->lastInsertId();
            $this->auditLogger->log('CREATE', $novoId, 'tbl_produtos', null, $data, "");
        }

        return $success;
    }

    /**
     * Atualiza um produto existente.
     */
    public function update(int $id, array $data): bool
    {
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos)
            return false;

        $data['prod_codigo'] = $id;
        $sql = "UPDATE tbl_produtos SET 
                    prod_codigo_interno = :prod_codigo_interno, prod_descricao = :prod_descricao, prod_tipo = :prod_tipo, 
                    prod_subtipo = :prod_subtipo, prod_classificacao = :prod_classificacao, prod_categoria = :prod_categoria, 
                    prod_classe = :prod_classe, prod_especie = :prod_especie, prod_origem = :prod_origem, 
                    prod_conservacao = :prod_conservacao, prod_congelamento = :prod_congelamento, 
                    prod_fator_producao = :prod_fator_producao, prod_tipo_embalagem = :prod_tipo_embalagem, 
                    prod_peso_embalagem = :prod_peso_embalagem, prod_total_pecas = :prod_total_pecas, 
                    prod_validade_meses = :prod_validade_meses, prod_unidade = :prod_unidade, prod_primario_id = :prod_primario_id, 
                    prod_ean13 = :prod_ean13, prod_dun14 = :prod_dun14, prod_ncm = :prod_ncm, prod_marca = :prod_marca, prod_situacao = :prod_situacao

                WHERE prod_codigo = :prod_codigo";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute($this->prepareData($data));

        if ($success) {
            $this->auditLogger->log('UPDATE', $id, 'tbl_produtos', $dadosAntigos, $data, "");
        }

        return $success;
    }

    /**
     * Exclui um produto.
     */
    public function delete(int $id): bool
    {
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos)
            return false;

        $stmt = $this->pdo->prepare("DELETE FROM tbl_produtos WHERE prod_codigo = :id");
        $stmt->execute([':id' => $id]);
        $success = $stmt->rowCount() > 0;

        if ($success) {
            $this->auditLogger->log('DELETE', $id, 'tbl_produtos', $dadosAntigos, null, "");
        }

        return $success;
    }

    // Método auxiliar privado para preparar os dados para INSERT/UPDATE
    private function prepareData(array $data): array
    {
        $situacao = isset($data['prod_situacao']) && $data['prod_situacao'] === 'A' ? 'A' : 'I';
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
            ':prod_categoria' => $data['prod_categoria'] ?: null,
            ':prod_classe' => $data['prod_classe'] ?: null,
            ':prod_especie' => !empty($data['prod_especie']) ? $data['prod_especie'] : null,
            ':prod_origem' => $data['prod_origem'],
            ':prod_conservacao' => $data['prod_conservacao'],
            ':prod_congelamento' => $data['prod_congelamento'],
            ':prod_fator_producao' => !empty($data['prod_fator_producao']) ? str_replace(',', '.', $data['prod_fator_producao']) : null,
            ':prod_tipo_embalagem' => $tipo_embalagem,
            ':prod_peso_embalagem' => $peso_embalagem,
            ':prod_total_pecas' => !empty($data['prod_total_pecas']) ? $data['prod_total_pecas'] : null,
            ':prod_validade_meses' => !empty($data['prod_validade_meses']) ? (int) $data['prod_validade_meses'] : null,
            ':prod_unidade' => !empty($data['prod_unidade']) ? $data['prod_unidade'] : null,
            ':prod_primario_id' => !empty($data['prod_primario_id']) ? $data['prod_primario_id'] : null,
            ':prod_ean13' => !empty($data['prod_ean13']) ? $data['prod_ean13'] : null,
            ':prod_dun14' => !empty($data['prod_dun14']) ? $data['prod_dun14'] : null,
            ':prod_ncm' => !empty($data['prod_ncm']) ? $data['prod_ncm'] : null,
            ':prod_marca' => !empty($data['prod_marca']) ? $data['prod_marca'] : null,
            ':prod_situacao' => $situacao,
            ':prod_codigo' => $data['prod_codigo'] ?? null,
        ];
    }

    public function findPrimarios(): ?array
    {
        $stmt = $this->pdo->query("SELECT 
                                        prod_codigo, prod_descricao, prod_peso_embalagem, 
                                        prod_tipo, prod_subtipo, prod_classificacao, 
                                        prod_categoria, prod_classe, prod_especie, 
                                        prod_origem, prod_conservacao, prod_congelamento, 
                                        prod_fator_producao, prod_total_pecas, 
                                        prod_codigo_interno, prod_validade_meses,
                                        prod_ncm, prod_marca  
                                    FROM tbl_produtos 
                                    WHERE prod_tipo_embalagem = 'PRIMARIA' 
                                    AND prod_situacao = 'A' 
                                    ORDER BY prod_descricao");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getProdutoOptions(string $tipoEmbalagem, string $term = ''): array
    {
        $params = [];
        $sql = "SELECT 
                p_sec.prod_codigo, 
                p_sec.prod_codigo AS id, 
                p_sec.prod_descricao,
                p_sec.prod_descricao AS text,  
                p_sec.prod_validade_meses, 
                p_sec.prod_peso_embalagem, 
                p_sec.prod_codigo_interno,
                IF(p_prim.prod_peso_embalagem > 0, p_sec.prod_peso_embalagem / p_prim.prod_peso_embalagem, 0) AS prod_unidades_primarias_calculado
            FROM tbl_produtos AS p_sec
            LEFT JOIN tbl_produtos AS p_prim ON p_sec.prod_primario_id = p_prim.prod_codigo
            WHERE p_sec.prod_situacao = 'A'";

        if ($tipoEmbalagem !== 'Todos') {
            $sql .= " AND p_sec.prod_tipo_embalagem = :tipo";
            $params[':tipo'] = $tipoEmbalagem;
        }

        if (!empty($term)) {
            // Busca pela descrição OU pelo código interno
            $sql .= " AND (p_sec.prod_descricao LIKE :term_desc OR p_sec.prod_codigo_interno LIKE :term_cod)";

            $params[':term_desc'] = '%' . $term . '%';
            $params[':term_cod'] = '%' . $term . '%';
        }

        $sql .= " ORDER BY p_sec.prod_descricao ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca produtos de embalagem secundária que estão associados a um produto primário específico.
     *
     * @param int $primarioId O ID do produto primário.
     * @return array A lista de produtos secundários encontrados.
     */
    public function findSecundariosByPrimarioId(int $primarioId): array
    {
        $sql = "SELECT 
                p_sec.prod_codigo, 
                p_sec.prod_descricao, 
                p_sec.prod_codigo_interno,
                IF(p_prim.prod_peso_embalagem > 0, p_sec.prod_peso_embalagem / p_prim.prod_peso_embalagem, 0) AS prod_unidades_primarias_calculado
            FROM tbl_produtos AS p_sec
            LEFT JOIN tbl_produtos AS p_prim ON p_sec.prod_primario_id = p_prim.prod_codigo
            WHERE p_sec.prod_situacao = 'A'
              AND p_sec.prod_tipo_embalagem = 'SECUNDARIA'
              AND p_sec.prod_primario_id = :primario_id
            ORDER BY p_sec.prod_descricao ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':primario_id' => $primarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM tbl_produtos");
        return (int) $stmt->fetchColumn();
    }

   /* public function getDadosRelatorioGeral(string $filtroSituacao = 'Todos', string $search = '', string $filtroTipo = 'Todos'): array
    {
        $filtroSituacao = !empty($filtroSituacao) ? $filtroSituacao : 'Todos';
        $filtroTipo = !empty($filtroTipo) ? $filtroTipo : 'Todos';

        $searchableColumns = ['p.prod_codigo_interno', 'p.prod_descricao', 'p.prod_tipo', 'p.prod_tipo_embalagem'];
        $whereConditions = [];
        $queryParams = [];

        // Filtro Situação
        if ($filtroSituacao !== 'Todos') {
            $situacao = ($filtroSituacao === 'Ativo' || $filtroSituacao === 'A') ? 'A' : (($filtroSituacao === 'Inativo' || $filtroSituacao === 'I') ? 'I' : '');
            if ($situacao) {
                $whereConditions[] = "p.prod_situacao = :situacao";
                $queryParams[':situacao'] = $situacao;
            }
        }

        // Filtro Tipo (CORREÇÃO)
        if ($filtroTipo !== 'Todos') {
            $whereConditions[] = "p.prod_tipo_embalagem = :tipo_emb";
            $queryParams[':tipo_emb'] = $filtroTipo;
        }

        // Busca
        if (!empty($search)) {
            $searchConditions = [];
            $searchTerm = '%' . $search . '%';
            foreach ($searchableColumns as $index => $column) {
                $key = ":search_rel_" . $index;
                $searchConditions[] = "$column LIKE $key";
                $queryParams[$key] = $searchTerm;
            }
            $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Query Principal com JOIN
        $sql = "SELECT 
                    p.prod_codigo, 
                    p.prod_codigo_interno, 
                    p.prod_descricao, 
                    p.prod_tipo_embalagem, 
                    p.prod_ean13, 
                    p.prod_dun14, 
                    p.prod_ncm,
                    p.prod_situacao,
                    -- Dados do Primário (Pai)
                    prim.prod_descricao AS nome_primario,
                    prim.prod_codigo_interno AS codigo_primario,
                    prim.prod_ean13 AS ean_primario
                FROM tbl_produtos p
                LEFT JOIN tbl_produtos prim ON p.prod_primario_id = prim.prod_codigo
                $whereClause
                ORDER BY p.prod_tipo_embalagem ASC, p.prod_descricao ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($queryParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } */

    /**
     * Busca produtos secundários (Caixas/Acabados) para o Select2.
     * Traz também o peso da embalagem para ajudar nos cálculos do modal.
     */

    public function getDadosRelatorioGeral(array $filtroSituacao, string $search, array $filtroTipo, array $filtroMarcas): array
    {
        // A lógica é IDÊNTICA à do DataTable, só sem LIMIT e ORDER BY fixo
        $whereConditions = [];
        $queryParams = [];

        if (!empty($filtroSituacao) && !in_array('TODOS', $filtroSituacao)) {
            $placeholders = [];
            foreach ($filtroSituacao as $i => $val) {
                $key = ":sit{$i}";
                $placeholders[] = $key;
                $queryParams[$key] = $val;
            }
            $whereConditions[] = "p.prod_situacao IN (" . implode(',', $placeholders) . ")";
        }

        if (!empty($filtroTipo) && !in_array('TODOS', $filtroTipo)) {
            $placeholders = [];
            foreach ($filtroTipo as $i => $val) {
                $key = ":tipo{$i}";
                $placeholders[] = $key;
                $queryParams[$key] = $val;
            }
            $whereConditions[] = "p.prod_tipo_embalagem IN (" . implode(',', $placeholders) . ")";
        }

        if (!empty($filtroMarcas) && !in_array('TODOS', $filtroMarcas)) {
            $placeholders = [];
            foreach ($filtroMarcas as $i => $val) {
                $key = ":marca{$i}";
                $placeholders[] = $key;
                $queryParams[$key] = $val;
            }
            $whereConditions[] = "p.prod_marca IN (" . implode(',', $placeholders) . ")";
        }

        if (!empty($search)) {
            $searchableColumns = ['p.prod_codigo_interno', 'p.prod_descricao', 'p.prod_tipo', 'p.prod_marca'];
            $searchConditions = [];
            $searchTerm = '%' . $search . '%';
            foreach ($searchableColumns as $index => $column) {
                $key = ":search_rel_" . $index;
                $searchConditions[] = "$column LIKE $key";
                $queryParams[$key] = $searchTerm;
            }
            $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT 
                    p.prod_codigo, p.prod_codigo_interno, p.prod_descricao, p.prod_tipo_embalagem, 
                    p.prod_ean13, p.prod_dun14, p.prod_ncm, p.prod_situacao, p.prod_marca,
                    prim.prod_descricao AS nome_primario,
                    prim.prod_codigo_interno AS codigo_primario,
                    prim.prod_ean13 AS ean_primario
                FROM tbl_produtos p
                LEFT JOIN tbl_produtos prim ON p.prod_primario_id = prim.prod_codigo
                $whereClause
                ORDER BY p.prod_tipo_embalagem ASC, p.prod_descricao ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($queryParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProdutosSecundariosOptions(string $term = ''): array
    {
        $term = "%" . $term . "%";

        // Selecionamos 'prod_codigo as id' e 'prod_descricao as text' 
        // para o Select2 entender automaticamente.
        $sql = "SELECT 
                    prod_codigo as id, 
                    CONCAT(prod_descricao, ' (Cód: ', COALESCE(prod_codigo_interno, 'N/A'), ')') as text,
                    prod_peso_embalagem,
                    prod_unidade
                FROM tbl_produtos 
                WHERE prod_tipo_embalagem = 'SECUNDARIA' 
                AND prod_situacao = 'A'
                AND (prod_descricao LIKE :term1 OR 
                     prod_codigo LIKE :term2 OR
                     prod_codigo_interno LIKE :term3)
                ORDER BY prod_descricao ASC 
                LIMIT 30"; // Limita a 30 resultados para ser rápido

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':term1' => $term,
            ':term2' => $term,
            ':term3' => $term
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDistinctMarcas(): array
    {
        // Busca apenas marcas não vazias e ordenadas
        $stmt = $this->pdo->query("SELECT DISTINCT prod_marca 
                                   FROM tbl_produtos 
                                   WHERE prod_marca IS NOT NULL AND prod_marca <> '' 
                                   ORDER BY prod_marca ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
