<?php
// Arquivo: painel-adm/process/listar_produtos.php
// Este arquivo processa requisições AJAX do DataTables para listar os produtos.

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

header('Content-Type: application/json');

// Parâmetros do DataTables
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';
$orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Ordenar por Cód. Interno por padrão
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';

// Mapeamento das colunas do DataTables para as colunas do banco de dados
$columns = [
    0 => 'prod_situacao',
    1 => 'prod_codigo_interno',
    2 => 'prod_descricao',
    3 => 'prod_tipo',
    4 => 'prod_tipo_embalagem',
    5 => 'prod_peso_embalagem',
    6 => null // Coluna de Ações, não ordenável
];

$orderColumn = $columns[$orderColumnIndex] ?? 'prod_codigo_interno';

try {
    // --- 1. Construção da Query Base ---
    $sqlBase = "FROM tbl_produtos";
    $conditions = [];
    $params = [];

    // Lógica de busca (filtro global do DataTables)
    if (!empty($searchValue)) {
        $conditions[] = "(prod_codigo_interno LIKE :search_value OR prod_descricao LIKE :search_value OR prod_tipo LIKE :search_value)";
        $params[':search_value'] = '%' . $searchValue . '%';
    }

    $whereClause = "";
    if (!empty($conditions)) {
        $whereClause = " WHERE " . implode(" AND ", $conditions);
    }

    // --- 2. Contagem Total de Registros (com filtros aplicados) ---
    $stmtFiltered = $pdo->prepare("SELECT COUNT(prod_codigo) " . $sqlBase . $whereClause);
    $stmtFiltered->execute($params);
    $totalFiltered = $stmtFiltered->fetchColumn();

    // --- 3. Contagem Total de Registros (sem nenhum filtro) ---
    $stmtTotal = $pdo->query("SELECT COUNT(prod_codigo) FROM tbl_produtos");
    $totalRecords = $stmtTotal->fetchColumn();

    // --- 4. Query Final para Obter os Dados ---
    $sqlData = "SELECT 
                    prod_codigo,
                    prod_situacao,
                    prod_codigo_interno,
                    prod_descricao,
                    prod_tipo,
                    prod_tipo_embalagem,
                    prod_peso_embalagem
                " . $sqlBase . $whereClause;

    // Adicionar Ordenação e Limite
    if ($orderColumn) {
        $sqlData .= " ORDER BY " . $orderColumn . " " . strtoupper($orderDir);
    }
    $sqlData .= " LIMIT :start, :length";

    // --- 5. Preparar e Executar a Query Final ---
    $stmt = $pdo->prepare($sqlData);
    $stmt->bindParam(':start', $start, PDO::PARAM_INT);
    $stmt->bindParam(':length', $length, PDO::PARAM_INT);
    foreach ($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 6. Formatar a Saída para o DataTables ---
    $output = [
        "draw" => (int)$draw,
        "recordsTotal" => (int)$totalRecords,
        "recordsFiltered" => (int)$totalFiltered,
        "data" => $data
    ];

    echo json_encode($output);

} catch (PDOException $e) {
    error_log("Erro no listar_produtos.php: " . $e->getMessage());
    $output = [
        "draw" => (int)$draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Erro ao carregar dados dos produtos. Tente novamente."
    ];
    echo json_encode($output);
}
?>
