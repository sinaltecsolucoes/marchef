<?php require_once("config.php");

date_default_timezone_set('America/Fortaleza');
try {
    $pdo = new PDO("mysql:dbname=$banco;host=$servidor;charset=utf8", "$usuario", "$senha");
} catch (Exception $e) {
    echo 'Não conectado ao Banco de Dados! <br><br>' . $e;
}
?>