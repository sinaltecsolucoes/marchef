<?php
// editar-perfil.php

session_start();

require_once('../../includes/error_handler.php');
require_once('../../conexao.php');
require_once('../../includes/helpers.php');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Validação de Segurança ---
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        http_response_code(403);
        $response['message'] = "Erro de segurança: Requisição inválida (CSRF).";
        echo json_encode($response);
        exit();
    }

    $id_usuario_editar = filter_input(INPUT_POST, 'usu_codigo_selecionado', FILTER_VALIDATE_INT);
    if (!$id_usuario_editar) {
        $response['message'] = "ID do usuário a ser editado inválido ou ausente.";
        echo json_encode($response);
        exit();
    }

    // --- Validação dos Campos Básicos (sempre presentes) ---
    $nome_perfil_raw = filter_input(INPUT_POST, 'nome_perfil', FILTER_SANITIZE_STRING);
    $val_nome = validate_string($nome_perfil_raw, 3, 100);
    if (!$val_nome['valid']) {
        $response['message'] = "Nome: " . $val_nome['message'];
        echo json_encode($response);
        exit();
    }
    $nome_perfil = $val_nome['value'];

    $login_perfil_raw = filter_input(INPUT_POST, 'login_perfil', FILTER_SANITIZE_STRING);
    if (!filter_var($login_perfil_raw, FILTER_VALIDATE_EMAIL)) {
        $val_login = validate_string($login_perfil_raw, 3, 50, '/^[a-zA-Z0-9_.-]+$/');
        if (!$val_login['valid']) {
            $response['message'] = "Login: " . $val_login['message'];
            echo json_encode($response);
            exit();
        }
        $login_perfil = $val_login['value'];
    } else {
        $login_perfil = $login_perfil_raw;
    }

    $senha_perfil = $_POST['senha_perfil'] ?? '';

    // =================================================================
    // >> INÍCIO DA CORREÇÃO <<
    // =================================================================
    // Verifica se o usuário logado tem permissão para editar campos de admin
    $usuarioLogadoPodeEditarCamposAdmin = ($_SESSION['tipoUsuario'] === 'Admin'); // Adapte esta lógica se outros perfis também puderem

    try {
        // Verifica se o novo login já está em uso por OUTRO usuário
        $queryVerificaLogin = $pdo->prepare("SELECT usu_codigo FROM tbl_usuarios WHERE usu_login = :login AND usu_codigo != :id_usuario_editar");
        $queryVerificaLogin->bindParam(':login', $login_perfil);
        $queryVerificaLogin->bindParam(':id_usuario_editar', $id_usuario_editar, PDO::PARAM_INT);
        $queryVerificaLogin->execute();

        if ($queryVerificaLogin->rowCount() > 0) {
            $response['message'] = "Este login já está em uso por outro usuário.";
            echo json_encode($response);
            exit();
        }

        // Constrói a query SQL dinamicamente
        $sql_parts = ["usu_nome = :nome", "usu_login = :login"];
        $params = [
            ':nome' => $nome_perfil,
            ':login' => $login_perfil,
            ':id_usuario_editar' => $id_usuario_editar
        ];

        // Adiciona a senha à query se ela foi fornecida
        if (!empty($senha_perfil)) {
            if (mb_strlen($senha_perfil) < 6) {
                $response['message'] = "Senha: Mínimo de 6 caracteres.";
                echo json_encode($response);
                exit();
            }
            $senha_hashed = password_hash($senha_perfil, PASSWORD_DEFAULT);
            $sql_parts[] = "usu_senha = :senha";
            $params[':senha'] = $senha_hashed;
        }

        // Adiciona os campos de admin à query APENAS se o usuário tiver permissão
        if ($usuarioLogadoPodeEditarCamposAdmin) {
            // Valida os campos de admin que devem ter sido enviados
            $situacao_perfil_raw = isset($_POST['situacao_perfil']) ? '1' : '0';
            $situacao_perfil = ($situacao_perfil_raw === '1') ? 'A' : 'I';
            
            $nivel_perfil_raw = filter_input(INPUT_POST, 'nivel_perfil', FILTER_SANITIZE_STRING);
            $allowed_niveis = ['Admin', 'Gerente', 'Producao'];
            $val_nivel = validate_selection($nivel_perfil_raw, $allowed_niveis);
            if (!$val_nivel['valid']) {
                $response['message'] = "Nível de Acesso: " . $val_nivel['message'];
                echo json_encode($response);
                exit();
            }
            $nivel_perfil = $val_nivel['value'];

            // Adiciona à query e aos parâmetros
            $sql_parts[] = "usu_situacao = :situacao";
            $sql_parts[] = "usu_tipo = :nivel";
            $params[':situacao'] = $situacao_perfil;
            $params[':nivel'] = $nivel_perfil;
        }

        // Monta a query final
        $sql = "UPDATE tbl_usuarios SET " . implode(", ", $sql_parts) . " WHERE usu_codigo = :id_usuario_editar";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Atualiza a sessão se o usuário logado for o que foi editado
        if (isset($_SESSION['codUsuario']) && $_SESSION['codUsuario'] == $id_usuario_editar) {
            $_SESSION['nomeUsuario'] = $nome_perfil;
            $_SESSION['logUsuario'] = $login_perfil;
            if ($usuarioLogadoPodeEditarCamposAdmin) {
                $_SESSION['sitUsuario'] = $situacao_perfil;
                $_SESSION['tipoUsuario'] = $nivel_perfil;
            }
        }

        $response['success'] = true;
        $response['message'] = "Perfil atualizado com sucesso!";

    } catch (PDOException $e) {
        $response['message'] = "Erro ao atualizar perfil: " . $e->getMessage();
        error_log("Erro em editar-perfil.php: " . $e->getMessage());
    }
    // =================================================================
    // >> FIM DA CORREÇÃO <<
    // =================================================================

} else {
    $response['message'] = "Método de requisição inválido.";
}

echo json_encode($response);
exit();
?>
