<?php
// Arquivo: painel-admin/process/cadastrar_usuarios.php
// Responsável por cadastrar um novo usuário.

session_start(); // Inicia a sessão para acesso ao ID do usuário logado e CSRF
require_once('../../conexao.php'); // CORREÇÃO AQUI: Caminho atualizado
require_once('../../includes/error_handler.php'); // CORREÇÃO AQUI: Caminho atualizado
require_once('../../includes/helpers.php'); // CORREÇÃO AQUI: Caminho atualizado (para funções de validação)

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ========================================================================
    // Verificação do Token CSRF
    // ========================================================================
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $submitted_token !== $_SESSION['csrf_token']) {
        $response['message'] = "Erro de segurança: Requisição inválida (CSRF).";
        error_log("[CSRF ALERTA] Tentativa de CSRF detectada em cadastrar_usuarios.php. IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode($response);
        exit();
    }

    // --- 1. Validação e Sanitização de Entradas ---
    $nome_usuario_raw = filter_input(INPUT_POST, 'usu_nome', FILTER_SANITIZE_STRING);
    $val_nome = validate_string($nome_usuario_raw, 3, 100, '/^[a-zA-Z\sÀ-ú]+$/u');
    if (!$val_nome['valid']) {
        $response['message'] = "Nome: " . $val_nome['message'];
        echo json_encode($response);
        exit();
    }
    $nome_usuario = $val_nome['value'];

    $login_usuario_raw = filter_input(INPUT_POST, 'usu_login', FILTER_SANITIZE_STRING);
    if (!filter_var($login_usuario_raw, FILTER_VALIDATE_EMAIL)) {
        $val_login = validate_string($login_usuario_raw, 3, 50, '/^[a-zA-Z0-9_.-]+$/');
        if (!$val_login['valid']) {
            $response['message'] = "Login: " . $val_login['message'] . " (ou formato de e-mail inválido)";
            echo json_encode($response);
            exit();
        }
        $login_usuario = $val_login['value'];
    } else {
        $login_usuario = $login_usuario_raw;
    }

    $senha_usuario_raw = $_POST['usu_senha'] ?? '';
    if (mb_strlen($senha_usuario_raw) < 6) {
        $response['message'] = "Senha: Mínimo de 6 caracteres.";
        echo json_encode($response);
        exit();
    }
    $senha_hashed = password_hash($senha_usuario_raw, PASSWORD_DEFAULT);

    $tipo_usuario_raw = filter_input(INPUT_POST, 'usu_tipo', FILTER_SANITIZE_STRING);
    $allowed_tipos = ['Admin', 'Gerente', 'Producao'];
    $val_tipo = validate_selection($tipo_usuario_raw, $allowed_tipos);
    if (!$val_tipo['valid']) {
        $response['message'] = "Tipo de Usuário: " . $val_tipo['message'];
        echo json_encode($response);
        exit();
    }
    $tipo_usuario = $val_tipo['value'];

    $situacao_usuario_raw = filter_input(INPUT_POST, 'usu_situacao', FILTER_SANITIZE_STRING);
    $situacao_usuario = ($situacao_usuario_raw === '1') ? 'A' : 'I';


    // --- 2. Lógica de Negócio e Interação com o Banco de Dados ---
    try {
        // Verifica se o login já existe
        $queryVerificaLogin = $pdo->prepare("SELECT usu_codigo FROM tbl_usuarios WHERE usu_login = :login");
        $queryVerificaLogin->bindParam(':login', $login_usuario);
        $queryVerificaLogin->execute();

        if ($queryVerificaLogin->rowCount() > 0) {
            $response['message'] = "Este login já está em uso. Por favor, escolha outro.";
            echo json_encode($response);
            exit();
        }

        // Insere o novo usuário
        $stmt = $pdo->prepare("INSERT INTO tbl_usuarios (usu_nome, usu_login, usu_senha, usu_tipo, usu_situacao) VALUES (:nome, :login, :senha, :tipo, :situacao)");
        $stmt->bindParam(':nome', $nome_usuario);
        $stmt->bindParam(':login', $login_usuario);
        $stmt->bindParam(':senha', $senha_hashed);
        $stmt->bindParam(':tipo', $tipo_usuario);
        $stmt->bindParam(':situacao', $situacao_usuario);
        $stmt->execute();

        $response['success'] = true;
        $response['message'] = "Usuário cadastrado com sucesso!";

    } catch (PDOException $e) {
        $response['message'] = "Erro ao cadastrar usuário: " . $e->getMessage();
        error_log("Erro em cadastrar_usuarios.php: " . $e->getMessage());
    }

} else {
    $response['message'] = "Método de requisição inválido.";
}

echo json_encode($response);
exit();
?>
