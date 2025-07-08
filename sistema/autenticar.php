<?php
require_once("conexao.php");
@session_start();
$usuario = $_POST['login-usuario'];
$senha = $_POST['senha'];

$query = $pdo->prepare("SELECT * FROM tbl_usuarios WHERE (usu_nome = :usuario or usu_login = :usuario) and usu_senha = :senha");
$query->bindValue(":usuario", $usuario);
$query->bindValue(":senha", $senha);
$query->execute();

$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if ($total_reg > 0) {
    $_SESSION['codUsuario'] = $res[0]['usu_codigo'];
    $_SESSION['logUsuario'] = $res[0]['usu_login'];
    $_SESSION['nomeUsuario'] = $res[0]['usu_nome'];
    $_SESSION['sitUsuario'] = $res[0]['usu_situacao'];
    $_SESSION['tipoUsuario'] = $res[0]['usu_tipo'];

    //REDIRECIONAR O USUARIO DE ACORDO COM O TIPO
    if ($res[0]['usu_tipo'] == 'Admin') {
        echo "<script language='javascript'>
        window.location='painel-admin' </script>";
    }
}

?>