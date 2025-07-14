<?php
// verificar.php

// Inicia a sessão se ainda não estiver iniciada.
// session_start() deve ser a primeira coisa no script.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// O index.php já possui uma verificação mais completa para saber se o usuário está logado
// e se seus dados são válidos. Este arquivo apenas garante que a sessão esteja ativa.
// Se você quiser uma verificação mínima aqui, seria algo como:
/*
if (!isset($_SESSION['codUsuario']) || empty($_SESSION['codUsuario'])) {
    header("Location: ../login.php");
    exit();
}
*/
// No entanto, como o index.php já faz essa checagem de forma mais robusta e com session_destroy(),
// podemos deixar este arquivo apenas para garantir o inicio da sessão.
?>