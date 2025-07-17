<?php
// Arquivo: painel-admin/process/listar_entidades.php
// Este arquivo processa requisições AJAX do DataTables para listar entidades (clientes/fornecedores)
// com server-side processing e filtros.

// Inclui a conexão com o banco de dados
require_once('../../conexao.php');

// Inclui o manipulador de erros global para tratamento consistente de exceções
require_once('../../includes/error_handler.php');

// Define o cabeçalho para indicar que a resposta será JSON.
header('Content-Type: application/json');

// Parâmetros do DataTables
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';
$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';

// Parâmetros de Filtro Personalizados
$filtroSituacao = $_POST['filtro_situacao'] ?? 'Todos';
$tipoEntidade = $_POST['tipo_entidade'] ?? 'Cliente'; // 'Cliente' ou 'Fornecedor'

// Mapeamento das colunas
$columns = [
    0 => 'ent_situacao',
    1 => 'ent_tipo_entidade',
    2 => 'ent_razao_social',
    3 => 'ent_cpf',
    4 => 'end_logradouro',
    5 => 'ent_codigo'
];
$orderColumn = $columns[$orderColumnIndex] ?? 'ent_codigo';

try {
    // --- 1. Construção da Query Base ---
    $sqlBase = "
        FROM tbl_entidades ent
        LEFT JOIN (
            SELECT 
                *,
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

    // ====================================================================
    // >> INÍCIO DA CORREÇÃO <<
    // Filtro dinâmico para Clientes ou Fornecedores
    // ====================================================================
    if ($tipoEntidade === 'Cliente') {
        $conditions[] = "(ent.ent_tipo_entidade = 'Cliente' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
    } elseif ($tipoEntidade === 'Fornecedor') {
        $conditions[] = "(ent.ent_tipo_entidade = 'Fornecedor' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
    }
    // ====================================================================
    // >> FIM DA CORREÇÃO <<
    // ====================================================================

    // Lógica de filtro por Situação
    if ($filtroSituacao !== 'Todos') {
        $conditions[] = "ent.ent_situacao = :filtro_situacao";
        $params[':filtro_situacao'] = $filtroSituacao;
    }

    // Lógica de busca global
    if (!empty($searchValue)) {
        $conditions[] = "(ent.ent_razao_social LIKE :search_value OR ent.ent_cpf LIKE :search_value OR ent.ent_cnpj LIKE :search_value)";
        $params[':search_value'] = '%' . $searchValue . '%';
    }

    $whereClause = "";
    if (!empty($conditions)) {
        $whereClause = " WHERE " . implode(" AND ", $conditions);
    }

    // --- 2. Contagem Total de Registros (com filtros) ---
    $stmtFiltered = $pdo->prepare("SELECT COUNT(DISTINCT ent.ent_codigo) " . $sqlBase . $whereClause);
    $stmtFiltered->execute($params);
    $totalFiltered = $stmtFiltered->fetchColumn();

    // --- 3. Contagem Total de Registros (sem filtro de busca/situação) ---
    $totalConditions = [];
    if ($tipoEntidade === 'Cliente') {
        $totalConditions[] = "(ent.ent_tipo_entidade = 'Cliente' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
    } elseif ($tipoEntidade === 'Fornecedor') {
        $totalConditions[] = "(ent.ent_tipo_entidade = 'Fornecedor' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
    }
    $totalWhereClause = " WHERE " . implode(" AND ", $totalConditions);
    
    $stmtTotal = $pdo->prepare("SELECT COUNT(ent.ent_codigo) FROM tbl_entidades ent " . $totalWhereClause);
    $stmtTotal->execute();
    $totalRecords = $stmtTotal->fetchColumn();


    // --- 4. Query Final para Obter os Dados ---
    $sqlData = "
        SELECT 
            ent.ent_codigo, ent.ent_razao_social, ent.ent_tipo_pessoa, 
            ent.ent_cpf, ent.ent_cnpj, ent.ent_tipo_entidade, ent.ent_situacao,
            end.end_logradouro, end.end_numero, end.end_tipo_endereco
        " . $sqlBase . $whereClause;
    
    $sqlData .= " GROUP BY ent.ent_codigo"; // Agrupa para evitar duplicatas do JOIN
    $sqlData .= " ORDER BY " . $orderColumn . " " . strtoupper($orderDir);
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
    error_log("Erro no listar_entidades.php (Server-Side): " . $e->getMessage());
    $output = [
        "draw" => (int)$draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Erro ao carregar dados. Tente novamente mais tarde."
    ];
    echo json_encode($output);
}
?>
