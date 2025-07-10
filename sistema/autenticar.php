<?php
// autenticar.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("conexao.php");

$usuario_input = trim($_POST['login-usuario'] ?? '');
$senha_input = $_POST['senha'] ?? '';

$mensagem_feedback = '';

if (empty($usuario_input) || empty($senha_input)) {
    $mensagem_feedback = "Por favor, preencha todos os campos.";
} else {
    try {
        $query = $pdo->prepare("SELECT * FROM tbl_usuarios WHERE usu_nome = :nome_usuario OR usu_login = :login_usuario");
        $query->bindParam(":nome_usuario", $usuario_input);
        $query->bindParam(":login_usuario", $usuario_input);

        $query->execute();

        $res = $query->fetchAll(PDO::FETCH_ASSOC);

        if (count($res) > 0) {
            $dados_usuario = $res[0];
            $hash_armazenado = $dados_usuario['usu_senha'];

            if (password_verify($senha_input, $hash_armazenado)) {
                $_SESSION['codUsuario'] = $dados_usuario['usu_codigo'];
                $_SESSION['logUsuario'] = $dados_usuario['usu_login'];
                $_SESSION['nomeUsuario'] = $dados_usuario['usu_nome'];
                $_SESSION['sitUsuario'] = $dados_usuario['usu_situacao'];
                $_SESSION['tipoUsuario'] = $dados_usuario['usu_tipo'];

                if ($dados_usuario['usu_tipo'] == 'Admin') {
                    header("Location: painel-admin");
                    exit();
                } else {
                    header("Location: ../");
                    exit();
                }
            } else {
                $mensagem_feedback = "Login ou senha inválidos.";
            }
        } else {
            $mensagem_feedback = "Login ou senha inválidos.";
        }
    } catch (PDOException $e) {
        error_log("Erro no autenticar.php (BD): " . $e->getMessage());
        $mensagem_feedback = "Ocorreu um erro inesperado. Tente novamente mais tarde.";
    }
}

$_SESSION['erro_login'] = $mensagem_feedback;
header("Location: login.php");
exit();
?>