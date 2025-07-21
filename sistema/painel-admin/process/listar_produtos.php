<?php
// Arquivo: painel-adm/process/listar_produtos.php
require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

header('Content-Type: application/json');

try {
    // Parâmetros do DataTables
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $orderColumnIndex = $_POST['order'][0]['column'] ?? 1;
    $orderDir = $_POST['order'][0]['dir'] ?? 'asc';
    $filtroSituacao = $_POST['filtro_situacao'] ?? 'Todos';

    $columns = [
        0 => 'prod_situacao',
        1 => 'prod_codigo_interno',
        2 => 'prod_descricao',
        3 => 'prod_tipo',
        4 => 'prod_tipo_embalagem',
        5 => 'prod_peso_embalagem',
        6 => null
    ];
    $orderColumn = $columns[$orderColumnIndex] ?? 'prod_codigo_interno';

    // --- 1. Contagem Total de Registros ---
    $stmtTotal = $pdo->query("SELECT COUNT(prod_codigo) FROM tbl_produtos");
    $totalRecords = $stmtTotal->fetchColumn();

    $conditions = [];
    $params = [];

    // --- 2. Filtro por Situação ---
    if ($filtroSituacao !== 'Todos') {
        $conditions[] = "prod_situacao = :filtro_situacao";
        $params[':filtro_situacao'] = $filtroSituacao;
    }

    // --- 3. Filtro por Busca ---
    if (!empty($searchValue)) {
        $searchTerm = '%' . $searchValue . '%';
        $conditions[] = "(prod_codigo_interno LIKE :search1 OR prod_descricao LIKE :search2 OR prod_tipo LIKE :search3 
            OR prod_tipo_embalagem LIKE :search4 OR prod_situacao LIKE :search5 OR prod_peso_embalagem LIKE :search6)";
        $params[':search1'] = $searchTerm;
        $params[':search2'] = $searchTerm;
        $params[':search3'] = $searchTerm;
        $params[':search4'] = $searchTerm;
        $params[':search5'] = $searchTerm;
        $params[':search6'] = $searchTerm;
    }

    // --- 4. Construção da Cláusula WHERE ---
    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    // --- 5. Contagem de Registros Filtrados ---
    $sqlFiltered = "SELECT COUNT(prod_codigo) FROM tbl_produtos $whereClause";
    $stmtFiltered = $pdo->prepare($sqlFiltered);
    foreach ($params as $key => $value) {
        $stmtFiltered->bindValue($key, $value);
    }
    $stmtFiltered->execute();
    $totalFiltered = $stmtFiltered->fetchColumn();

    // --- 6. Busca dos Dados da Página Atual ---
    $sql = "SELECT * FROM tbl_produtos $whereClause ORDER BY $orderColumn $orderDir LIMIT :start, :length";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 7. Formatar a Saída para o DataTables ---
    $output = [
        "draw" => (int) $draw,
        "recordsTotal" => (int) $totalRecords,
        "recordsFiltered" => (int) $totalFiltered,
        "data" => $data
    ];

    echo json_encode($output);

} catch (PDOException $e) {
    error_log("Erro no listar_produtos.php: " . $e->getMessage());
    echo json_encode([
        "draw" => (int) ($_POST['draw'] ?? 1),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Erro de Base de Dados: " . $e->getMessage()
    ]);
}
?>