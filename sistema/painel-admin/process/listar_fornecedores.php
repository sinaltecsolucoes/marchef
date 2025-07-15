<?php
// Arquivo: painel-admin/process/listar_fornecedores.php
// Este arquivo processa requisições AJAX do DataTables para listar entidades (fornecedores)
// com server-side processing e filtros.

// Inclui a conexão com o banco de dados
require_once('../../conexao.php'); //

// Inclui o manipulador de erros global para tratamento consistente de exceções
require_once('../../includes/error_handler.php'); //

// Define o cabeçalho para indicar que a resposta será JSON.
header('Content-Type: application/json');

// Parâmetros do DataTables
$draw = $_POST['draw'] ?? 1; // Contador de requisições do DataTables
$start = $_POST['start'] ?? 0; // Ponto de início para os resultados (offset)
$length = $_POST['length'] ?? 10; // Número de registros a serem retornados (limit)
$searchValue = $_POST['search']['value'] ?? ''; // Termo de busca global
$orderColumnIndex = $_POST['order'][0]['column'] ?? 0; // Índice da coluna para ordenação
$orderDir = $_POST['order'][0]['dir'] ?? 'asc'; // Direção da ordenação (asc/desc)

// NOVO PARÂMETRO DE FILTRO PARA FORNECEDORES (se aplicável, similar aos clientes)
$filtroSituacao = $_POST['filtro_situacao'] ?? 'Todos'; // 'A', 'I' ou 'Todos'

// Mapeamento das colunas do DataTables para as colunas do banco de dados
// É crucial que a ordem aqui corresponda à ordem das colunas no seu JS do DataTables
$columns = [
    0 => 'ent_situacao',
    1 => 'ent_tipo_entidade',
    2 => 'ent_razao_social',
    3 => 'ent_cnpj', // Para fornecedores, geralmente CNPJ
    4 => 'end_logradouro', // Coluna do endereço
    5 => 'ent_codigo' // Coluna de ações (não mapeada diretamente para o DB para ordenação)
];

$orderColumn = $columns[$orderColumnIndex] ?? 'ent_codigo'; // Coluna real do DB para ordenação

try {
    // --- 1. Construção da Query Base ---
    // JOIN com tbl_enderecos para pegar o endereço principal (tipo 'Entrega')
    $sqlBase = "
        FROM tbl_entidades ent
        LEFT JOIN tbl_enderecos end ON ent.ent_codigo = end.end_entidade_id AND end.end_tipo_endereco = 'Entrega'
    ";

    $conditions = [];
    $params = [];

    // ====================================================================
    // FILTRO OBRIGATÓRIO: APENAS FORNECEDORES
    // Esta é a tela de Fornecedores, então filtramos por 'Fornecedor' ou 'Cliente e Fornecedor'
    $conditions[] = "(ent.ent_tipo_entidade = 'Fornecedor' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
    // ====================================================================

    // Lógica de filtro por Situação
    if ($filtroSituacao !== 'Todos') {
        $conditions[] = "ent.ent_situacao = :filtro_situacao";
        $params[':filtro_situacao'] = $filtroSituacao;
    }

    // Lógica de busca (filtro global do DataTables)
    if (!empty($searchValue)) {
        $conditions[] = "(ent.ent_razao_social LIKE :search_value OR ent.ent_cnpj LIKE :search_value OR end.end_logradouro LIKE :search_value OR end.end_cidade LIKE :search_value)";
        $params[':search_value'] = '%' . $searchValue . '%';
    }

    $whereClause = "";
    if (!empty($conditions)) {
        $whereClause = " WHERE " . implode(" AND ", $conditions);
    }

    // --- DEBUG LOG: Query de Contagem Filtrada ---
    error_log("DEBUG: SQL Filtered Count Query (Fornecedores): SELECT COUNT(ent.ent_codigo) " . $sqlBase . $whereClause);
    error_log("DEBUG: Params for Filtered Count (Fornecedores): " . print_r($params, true));

    // --- 2. Contagem Total de Registros (com filtros aplicados, mas sem limite/offset) ---
    $stmtFiltered = $pdo->prepare("SELECT COUNT(ent.ent_codigo) " . $sqlBase . $whereClause);
    $stmtFiltered->execute($params);
    $totalFiltered = $stmtFiltered->fetchColumn();

    // ====================================================================
    // Definir $sqlTotalBase para a contagem total de registros de fornecedores
    $sqlTotalBase = "
        FROM tbl_entidades ent
        WHERE (ent.ent_tipo_entidade = 'Fornecedor' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')
    ";
    // ====================================================================

    // --- DEBUG LOG: Query de Contagem Total ---
    error_log("DEBUG: SQL Total Count Query (Fornecedores): SELECT COUNT(ent.ent_codigo) " . $sqlTotalBase);

    // --- 3. Contagem Total de Registros (sem filtro de busca global, mas com filtro de tipo de entidade) ---
    $stmtTotal = $pdo->prepare("SELECT COUNT(ent.ent_codigo) " . $sqlTotalBase);
    $stmtTotal->execute();
    $totalRecords = $stmtTotal->fetchColumn();


    // --- 4. Query Final para Obter os Dados ---
    $sqlData = "
        SELECT
            ent.ent_codigo,
            ent.ent_razao_social,
            ent.ent_tipo_pessoa,
            ent.ent_cpf,
            ent.ent_cnpj,
            ent.ent_tipo_entidade,
            ent.ent_situacao,
            end.end_logradouro,
            end.end_numero,
            end.end_complemento,
            end.end_bairro,
            end.end_cidade,
            end.end_uf,
            end.end_tipo_endereco -- Incluído para uso no JS
        " . $sqlBase . $whereClause;

    // Adicionar Ordenação e Limite
    // Prevenção de injeção SQL na ordenação: A coluna já é mapeada de um array fixo.
    $sqlData .= " ORDER BY " . $orderColumn . " " . strtoupper($orderDir);
    $sqlData .= " LIMIT :start, :length";

    // --- DEBUG LOG: Query de Dados Final ---
    error_log("DEBUG: Final Data Query (Fornecedores): " . $sqlData);
    error_log("DEBUG: Final Data Query Params (Fornecedores) (start, length, others): " . print_r(array_merge([':start' => $start, ':length' => $length], $params), true));


    // --- 5. Preparar e Executar a Query Final ---
    $stmt = $pdo->prepare($sqlData);
    $stmt->bindParam(':start', $start, PDO::PARAM_INT);
    $stmt->bindParam(':length', $length, PDO::PARAM_INT);
    foreach ($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- DEBUG LOG: Dados Retornados ---
    error_log("DEBUG: Data Returned for DataTables (Fornecedores): " . print_r($data, true));

    // --- 6. Formatar a Saída para o DataTables ---
    $output = [
        "draw" => (int)$draw,
        "recordsTotal" => (int)$totalRecords, // Total de fornecedores (sem filtro de busca global)
        "recordsFiltered" => (int)$totalFiltered, // Total de fornecedores com filtro de busca global e situação
        "data" => $data
    ];

    echo json_encode($output);

} catch (PDOException $e) {
    error_log("Erro no listar_fornecedores.php (Server-Side): " . $e->getMessage());
    // Retorna um erro amigável para o DataTables
    $output = [
        "draw" => (int)$draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Erro ao carregar dados dos fornecedores. Tente novamente mais tarde."
    ];
    echo json_encode($output);
}
?>