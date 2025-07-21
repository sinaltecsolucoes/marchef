<?php
// Arquivo: painel-admin/process/listar_entidades.php
require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

header('Content-Type: application/json');

// Parâmetros do DataTables
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';
$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';
$filtroSituacao = $_POST['filtro_situacao'] ?? 'Todos';
$tipoEntidade = $_POST['tipo_entidade'] ?? 'Cliente';

// Log para depuração
error_log("Parâmetros recebidos: draw=$draw, start=$start, length=$length, searchValue=$searchValue, filtroSituacao=$filtroSituacao, tipoEntidade=$tipoEntidade");

// Mapeamento das colunas
$columns = [
    0 => 'ent_situacao',
    1 => 'ent_tipo_entidade',
    2 => 'ent_codigo_interno',
    3 => 'ent_razao_social',
    4 => 'ent_cpf',
    5 => 'end_logradouro',
    6 => 'ent_codigo'
];
$orderColumn = $columns[$orderColumnIndex] ?? 'ent_codigo';

try {
    // --- 1. Construção da Query Base ---
    $sqlBase = "
        FROM tbl_entidades ent
        LEFT JOIN (
            SELECT 
                end_entidade_id,
                end_logradouro,
                end_numero,
                end_tipo_endereco,
                ROW_NUMBER() OVER(PARTITION BY end_entidade_id ORDER BY 
                    CASE end_tipo_endereco
                        WHEN 'Entrega' THEN 1
                        WHEN 'Comercial' THEN 2
                        WHEN 'Cobranca' THEN 3
                        WHEN 'Residencial' THEN 4
                        ELSE 5
                    END, end_codigo ASC) as rn
            FROM tbl_enderecos
        ) end ON ent.ent_codigo = end.end_entidade_id AND end.rn = 1
    ";

    $conditions = [];
    $params = [];

    // --- 2. Filtro por Tipo de Entidade ---
    if ($tipoEntidade === 'Cliente') {
        $conditions[] = "(ent.ent_tipo_entidade = 'Cliente' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
    } elseif ($tipoEntidade === 'Fornecedor') {
        $conditions[] = "(ent.ent_tipo_entidade = 'Fornecedor' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
    } else {
        throw new Exception("Tipo de entidade inválido: $tipoEntidade");
    }

    // --- 3. Filtro por Situação ---
    if ($filtroSituacao !== 'Todos') {
        $conditions[] = "ent.ent_situacao = :filtro_situacao";
        $params[':filtro_situacao'] = $filtroSituacao;
    }

    // --- 4. Filtro por Busca ---
    if (!empty($searchValue)) {
        $conditions[] = "(ent.ent_razao_social LIKE :search OR ent.ent_cpf LIKE :search OR ent.ent_cnpj LIKE :search OR ent.ent_codigo_interno LIKE :search)";
        $params[':search'] = '%' . $searchValue . '%';
    }

    // --- 5. Construção da Cláusula WHERE ---
    $whereClause = empty($conditions) ? "" : " WHERE " . implode(" AND ", $conditions);

    // --- 6. Contagem Total de Registros (sem filtro de busca/situação) ---
    $totalConditions = [];
    if ($tipoEntidade === 'Cliente') {
        $totalConditions[] = "(ent.ent_tipo_entidade = 'Cliente' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
    } elseif ($tipoEntidade === 'Fornecedor') {
        $totalConditions[] = "(ent.ent_tipo_entidade = 'Fornecedor' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
    }
    $totalWhereClause = empty($totalConditions) ? "" : " WHERE " . implode(" AND ", $totalConditions);

    $stmtTotal = $pdo->prepare("SELECT COUNT(ent.ent_codigo) FROM tbl_entidades ent $totalWhereClause");
    $stmtTotal->execute();
    $totalRecords = $stmtTotal->fetchColumn();

    // --- 7. Contagem de Registros Filtrados ---
    $sqlFiltered = "SELECT COUNT(DISTINCT ent.ent_codigo) $sqlBase $whereClause";
    $stmtFiltered = $pdo->prepare($sqlFiltered);
    foreach ($params as $key => $value) {
        $stmtFiltered->bindValue($key, $value);
    }
    $stmtFiltered->execute();
    $totalFiltered = $stmtFiltered->fetchColumn();

    // --- 8. Query Final para Obter os Dados ---
    $sqlData = "
        SELECT 
            ent.ent_codigo, 
            ent.ent_razao_social, 
            ent.ent_tipo_pessoa, 
            ent.ent_cpf, 
            ent.ent_cnpj, 
            ent.ent_tipo_entidade, 
            ent.ent_situacao,
            ent.ent_codigo_interno,
            end.end_logradouro, 
            end.end_numero, 
            end.end_tipo_endereco
        $sqlBase $whereClause
        ORDER BY $orderColumn " . strtoupper($orderDir) . "
        LIMIT :start, :length
    ";

    $stmt = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 9. Formatar a Saída para o DataTables ---
    $output = [
        "draw" => (int) $draw,
        "recordsTotal" => (int) $totalRecords,
        "recordsFiltered" => (int) $totalFiltered,
        "data" => $data
    ];

    echo json_encode($output);

} catch (PDOException $e) {
    error_log("Erro no listar_entidades.php: " . $e->getMessage());
    $output = [
        "draw" => (int) $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Erro de banco de dados: " . $e->getMessage()
    ];
    echo json_encode($output);
} catch (Exception $e) {
    error_log("Erro no listar_entidades.php: " . $e->getMessage());
    $output = [
        "draw" => (int) $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Erro: " . $e->getMessage()
    ];
    echo json_encode($output);
}
?>