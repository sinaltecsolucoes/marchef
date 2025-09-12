<?php
// /views/auth/login_view.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$mensagem_erro_login = '';
if (isset($_SESSION['erro_login'])) {
    $mensagem_erro_login = htmlspecialchars($_SESSION['erro_login']);
    unset($_SESSION['erro_login']);
}

$mensagem_info_sessao = '';
if (isset($_GET['motivo']) && $_GET['motivo'] === 'nova_sessao') {
    $mensagem_info_sessao = "A sua conta foi conectada em um novo local. Para usar o sistema nesta janela, por favor, faça o login novamente.";
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Marchef Pescados - Login</title>

    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/img/icone_2.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/css/login.css" rel="stylesheet">
</head>

<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">

                <div id="login-box">
                    <img src="<?php echo BASE_URL; ?>/img/logo_marchef.png" alt="Logo Marchef">

                    <form id="login-form" class="form" action="<?php echo BASE_URL; ?>/index.php?page=login"
                        method="post">

                        <?php if (!empty($mensagem_erro_login)): ?>
                            <div class="alert alert-danger text-center" role="alert">
                                <?php echo $mensagem_erro_login; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($mensagem_info_sessao)): ?>
                            <div class="alert alert-warning text-center" role="alert">
                                <?php echo $mensagem_info_sessao; ?>
                            </div>
                        <?php endif; ?>

                        <div class="form-group text-start mb-3">
                            <label for="login-usuario">Login:</label>
                            <input type="text" name="login-usuario" id="login-usuario" class="form-control" required>
                        </div>
                        <div class="form-group text-start mb-3">
                            <label for="senha">Senha:</label>
                            <input type="password" name="senha" id="senha" class="form-control" required>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="exibir-senha-login">
                                <label class="form-check-label" for="exibir-senha-login">Exibir Senha</label>
                            </div>
                        </div>
                        <div class="form-group text-center">
                            <input type="submit" name="conectar" class="btn btn-info" value="Conectar">
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const elSenha = document.getElementById('senha');
            const elCheckbox = document.getElementById('exibir-senha-login');
            if (elSenha && elCheckbox) {
                elCheckbox.addEventListener('change', function () {
                    elSenha.type = this.checked ? 'text' : 'password';
                });
            }
        });
    </script>
</body>

</html>