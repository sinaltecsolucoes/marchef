<?php require_once("conexao.php");

//INSERIR UM USUARIO ADM ADMINISTRADOR, CASO NÃO EXISTA NENHUM
$query = $pdo->query("SELECT * FROM tbl_usuarios WHERE usu_tipo = 'Admin'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if ($total_reg == 0) {
    $pdo->query("INSERT INTO tbl_usuarios set usu_nome = 'Administrador', usu_login = 'adm', usu_senha = 'adm@adm', usu_tipo = 'Admin', usu_situacao = 'A'");
} ?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <link rel="shortcut icon" href="img/icone_2.ico" type="image/x-icon">

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-with,initial-scale=1">
    <title>Marchef Pescados</title>

    <link href="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
    <link href="vendor/css/login.css" rel="stylesheet">
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <!------ Include the above in your HEAD tag ---------->
</head>

<body>
    <div id="login">
        <div class="container">
            <div id="login-row" class="row justify-content-center align-items-center">
                <div id="login-column" class="col-md-5">
                    <div id="login-box" class="col-md-12">
                        <form id="login-form" class="form" action="autenticar.php" method="post">
                            <h3 class="text-center text-info"><img src="img/logo_marchef.png" width="180px"></h3>
                            <div class="form-group">
                                <label for="login" class="">Login:</label><br>
                                <input type="text" name="login-usuario" id="login-usuario"
                                    class="form-control required">
                            </div>
                            <div class="form-group">
                                <label for="senha" class="">Senha:</label><br>
                                <input type="text" name="senha" id="senha" class="form-control required">
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
</body>

</html>