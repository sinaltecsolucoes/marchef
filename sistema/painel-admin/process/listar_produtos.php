<?php
// Arquivo: painel-adm/process/listar_produtos.php
// Versão robusta para processar requisições do DataTables com lógica separada para busca.

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

    $columns = [
        0 => 'prod_situacao', 1 => 'prod_codigo_interno', 2 => 'prod_descricao',
        3 => 'prod_tipo', 4 => 'prod_tipo_embalagem', 5 => 'prod_peso_embalagem',
        6 => null
    ];
    $orderColumn = $columns[$orderColumnIndex] ?? 'prod_codigo_interno';

    // --- 1. Contagem Total de Registros (sempre necessária) ---
    $stmtTotal = $pdo->query("SELECT COUNT(prod_codigo) FROM tbl_produtos");
    $totalRecords = $stmtTotal->fetchColumn();

    $data = [];
    $totalFiltered = 0;

    // --- 2. Lógica separada baseada na existência de um termo de busca ---
    if (!empty($searchValue)) {
        // --- LÓGICA QUANDO HÁ BUSCA ---
        $searchTerm = '%' . $searchValue . '%';
        $whereClause = "WHERE (prod_codigo_interno LIKE :search1 OR prod_descricao LIKE :search2 OR prod_tipo LIKE :search3 
OR prod_tipo_embalagem LIKE :search4 OR prod_situacao LIKE :search5 OR prod_peso_embalagem LIKE :search6)";

        // Contagem de registros filtrados
        $stmtFiltered = $pdo->prepare("SELECT COUNT(prod_codigo) FROM tbl_produtos " . $whereClause);
        $stmtFiltered->bindParam(':search1', $searchTerm);
        $stmtFiltered->bindParam(':search2', $searchTerm);
        $stmtFiltered->bindParam(':search3', $searchTerm);
        $stmtFiltered->bindParam(':search4', $searchTerm);
        $stmtFiltered->bindParam(':search5', $searchTerm);
        $stmtFiltered->bindParam(':search6', $searchTerm);
        $stmtFiltered->execute();
        $totalFiltered = $stmtFiltered->fetchColumn();

        // Busca dos dados da página atual
        $sql = "SELECT * FROM tbl_produtos " . $whereClause . " ORDER BY " . $orderColumn . " " . $orderDir . " LIMIT :start, :length";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':search1', $searchTerm);
        $stmt->bindParam(':search2', $searchTerm);
        $stmt->bindParam(':search3', $searchTerm);
        $stmt->bindParam(':search4', $searchTerm);
        $stmt->bindParam(':search5', $searchTerm);
        $stmt->bindParam(':search6', $searchTerm);
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // --- LÓGICA QUANDO NÃO HÁ BUSCA ---
        $totalFiltered = $totalRecords; // Se não há busca, o total filtrado é o total de registros

        // Busca dos dados da página atual
        $sql = "SELECT * FROM tbl_produtos ORDER BY " . $orderColumn . " " . $orderDir . " LIMIT :start, :length";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. Formatar a Saída para o DataTables ---
    $output = [
        "draw" => (int)$draw,
        "recordsTotal" => (int)$totalRecords,
        "recordsFiltered" => (int)$totalFiltered,
        "data" => $data
    ];

    echo json_encode($output);

} catch (PDOException $e) {
    error_log("Erro no listar_produtos.php: " . $e->getMessage());
    echo json_encode([
        "draw" => (int)($_POST['draw'] ?? 1),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Erro de Base de Dados: " . $e->getMessage()
    ]);
}
?>