<?php
// login.php

// Inicia a sessão para poder acessar e limpar a variável de erro
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("conexao.php");

// REMOVEMOS AQUI O BLOCO QUE VERIFICAVA E INSERIA O USUÁRIO ADM INICIAL
// Essa lógica deve ser executada apenas uma vez, durante a configuração inicial do sistema.
// Por exemplo, em um script de instalação ou manualmente em um ambiente novo.

// Lógica para obter a mensagem de erro da sessão e limpá-la
$mensagem_erro_login = '';
if (isset($_SESSION['erro_login'])) {
    $mensagem_erro_login = htmlspecialchars($_SESSION['erro_login']);
    // CRUCIAL: Limpa a variável da sessão para que ela não persista no refresh
    unset($_SESSION['erro_login']);
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <link rel="shortcut icon" href="img/icone_2.ico" type="image/x-icon">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Marchef Pescados - Login</title>

    <link href="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
    <link href="vendor/css/login.css" rel="stylesheet">
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>

<body>
    <div id="login">
        <div class="container">
            <div id="login-row" class="row justify-content-center align-items-center">
                <div id="login-column" class="col-md-5">
                    <div id="login-box" class="col-md-12">
                        <form id="login-form" class="form" action="autenticar.php" method="post">
                            <h3 class="text-center text-info"><img src="img/logo_marchef.png" width="180px"></h3>

                            <?php if (!empty($mensagem_erro_login)) : ?>
                                <div class="alert alert-danger text-center" role="alert">
                                    <?php echo $mensagem_erro_login; ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="login-usuario" class="">Login (Email ou Nome):</label><br>
                                <input type="text" name="login-usuario" id="login-usuario"
                                    class="form-control required">
                            </div>
                            <div class="form-group">
                                <label for="senha" class="">Senha:</label><br>
                                <input type="password" name="senha" id="senha" class="form-control required">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="exibir-senha-login">
                                    <label class="form-check-label" for="exibir-senha-login">
                                        Exibir Senha
                                    </label>
                                </div>
                            </div>
                            <div class="form-group text-center mt-4">
                                <input type="submit" name="conectar" class="btn btn-info btn-md" value="Conectar">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const senhaInput = document.getElementById('senha');
            const exibirSenhaCheckbox = document.getElementById('exibir-senha-login');
            if (senhaInput && exibirSenhaCheckbox) {
                exibirSenhaCheckbox.addEventListener('change', function() {
                    senhaInput.type = this.checked ? 'text' : 'password';
                });
            }
        });
    </script>
</body>

</html>
