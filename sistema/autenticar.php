<?php
// autenticar.php
require_once("includes/error_handler.php");
// Inicia o buffer de saída. Isso captura qualquer saída (como espaços em branco ou erros)
// antes que os headers HTTP sejam enviados, prevenindo problemas de redirecionamento.
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("conexao.php");

// Limpa qualquer mensagem de erro de login anterior na sessão
unset($_SESSION['erro_login']);

$usuario_input = trim($_POST['login-usuario'] ?? '');
$senha_input = $_POST['senha'] ?? '';

$mensagem_feedback = ''; // Inicializa vazio, será preenchido apenas em caso de falha

if (empty($usuario_input) || empty(trim($senha_input))) { // Adicionado trim para senha também
    $mensagem_feedback = "Por favor, preencha todos os campos.";
} else {
    try {
        // OTIMIZAÇÃO AQUI: Selecionando apenas as colunas necessárias
        $query = $pdo->prepare("SELECT usu_codigo, usu_nome, usu_login, usu_senha, usu_situacao, usu_tipo FROM tbl_usuarios WHERE usu_nome = :nome_usuario OR usu_login = :login_usuario");
        $query->bindParam(":nome_usuario", $usuario_input);
        $query->bindParam(":login_usuario", $usuario_input);
        $query->execute();
        $res = $query->fetchAll(PDO::FETCH_ASSOC);

        error_log("Tentativa de login para: " . $usuario_input);
        error_log("Número de registros encontrados: " . count($res));

        if (count($res) > 0) {
            $dados_usuario = $res[0];
            $hash_armazenado = $dados_usuario['usu_senha'];

            error_log("Usuário encontrado. Tipo: " . $dados_usuario['usu_tipo'] . ", Situação: " . $dados_usuario['usu_situacao']);

            if (password_verify($senha_input, $hash_armazenado)) {
                // Login BEM-SUCEDIDO
                $_SESSION['codUsuario'] = $dados_usuario['usu_codigo'];
                $_SESSION['logUsuario'] = $dados_usuario['usu_login'];
                $_SESSION['nomeUsuario'] = $dados_usuario['usu_nome'];
                $_SESSION['sitUsuario'] = $dados_usuario['usu_situacao'];
                $_SESSION['tipoUsuario'] = $dados_usuario['usu_tipo'];

                error_log("Login bem-sucedido para: " . $_SESSION['nomeUsuario'] . " (" . $_SESSION['tipoUsuario'] . ")");

                // Limpa o buffer de saída
                ob_end_clean();

                // Tenta o redirecionamento HTTP (preferencial)
                header("Location: painel-admin/index.php");
                exit(); // MUITO IMPORTANTE: Garante que o script pare aqui

            } else {
                // Senha incorreta
                $mensagem_feedback = "Login ou senha inválidos.";
                error_log("Falha na verificação de senha para: " . $usuario_input);
            }
        } else {
            // Usuário não encontrado
            $mensagem_feedback = "Login ou senha inválidos.";
        }
    } catch (PDOException $e) {
        // Captura e loga erros de banco de dados
        error_log("Erro no autenticar.php (BD): " . $e->getMessage());
        $mensagem_feedback = "Ocorreu um erro inesperado. Tente novamente mais tarde.";
    }
}

// Se o script chegou até aqui, significa que o login FALHOU (ou o header() falhou anteriormente).
// Armazena a mensagem de feedback na sessão.
$_SESSION['erro_login'] = $mensagem_feedback;

// Limpa o buffer de saída (se ainda houver algo)
ob_end_clean();

// Redireciona de volta para a página de login.
// Adicionado um fallback JavaScript para garantir o redirecionamento.
echo "<script>window.location.href = 'login.php';</script>";
exit(); // Garante que o script pare aqui
?>