<?php
// Arquivo: painel-admin/process/listar_enderecos.php
// Este arquivo processa requisições AJAX do DataTables para listar endereços de uma entidade específica.

session_start(); // Para verificar o token CSRF

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

header('Content-Type: application/json');

$draw = $_POST['draw'] ?? 1; // Contador de requisições do DataTables (pode não ser usado se paging=false)
$entidadeId = $_POST['ent_codigo'] ?? null; // ID da entidade para filtrar os endereços
$submitted_token = $_POST['csrf_token'] ?? '';
//$submitted_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

// --- DEBUG CSRF: Loga os tokens recebidos e esperados ---
error_log("DEBUG CSRF (listar_enderecos.php):");
error_log("  Submitted Token: " . ($submitted_token ?: 'NULL/EMPTY'));
error_log("  Session Token: " . ($_SESSION['csrf_token'] ?? 'NULL/EMPTY'));
// --- FIM DEBUG CSRF ---

$data = []; // Array para armazenar os dados dos endereços

// ========================================================================
// Verificação do Token CSRF
// ========================================================================
if (!isset($_SESSION['csrf_token']) || $submitted_token !== $_SESSION['csrf_token']) {
    error_log("[CSRF ALERTA] Tentativa de CSRF detectada em listar_enderecos.php. IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode([
        "draw" => (int)$draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Erro de segurança: Requisição inválida (CSRF)."
    ]);
    exit();
}

if (!$entidadeId) {
    error_log("Erro: ent_codigo não fornecido para listar_enderecos.php");
    echo json_encode([
        "draw" => (int)$draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "ID da entidade não fornecido para listar endereços."
    ]);
    exit();
}

try {
    // Query para buscar todos os endereços de uma entidade específica
    $query = $pdo->prepare("
        SELECT
            end_codigo,
            end_tipo_endereco,
            end_cep,
            end_logradouro,
            end_numero,
            end_complemento,
            end_bairro,
            end_cidade,
            end_uf
        FROM
            tbl_enderecos
        WHERE
            end_entidade_id = :entidade_id
        ORDER BY end_tipo_endereco ASC, end_logradouro ASC
    ");
    $query->bindParam(':entidade_id', $entidadeId, PDO::PARAM_INT);
    $query->execute();
    $enderecos = $query->fetchAll(PDO::FETCH_ASSOC);

    $recordsTotal = count($enderecos); // Total de endereços encontrados
    $recordsFiltered = $recordsTotal; // Como não há busca global, é o mesmo que o total

    foreach ($enderecos as $endereco) {
        $data[] = [
            "end_tipo_endereco" => $endereco['end_tipo_endereco'],
            "end_cep"           => $endereco['end_cep'],
            "end_logradouro"    => $endereco['end_logradouro'],
            "end_numero"        => $endereco['end_numero'],
            "end_bairro"        => $endereco['end_bairro'],
            "end_cidade"        => $endereco['end_cidade'],
            "end_uf"            => $endereco['end_uf'],
            "end_codigo"        => $endereco['end_codigo']
        ];
    }

    $output = [
        "draw" => (int)$draw,
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => $data
    ];

    echo json_encode($output);

} catch (PDOException $e) {
    error_log("Erro no listar_enderecos.php (Server-Side): " . $e->getMessage());
    $output = [
        "draw" => (int)$draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Erro ao carregar dados dos endereços. Tente novamente mais tarde."
    ];
    echo json_encode($output);
}
?>
