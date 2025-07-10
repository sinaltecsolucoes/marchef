<?php

// Inicia a sessão para garantir que as variáveis de sessão possam ser acessadas e destruídas.
// Usar session_status() evita erros se a sessão já foi iniciada em outro lugar.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Limpa todas as variáveis de sessão.
session_unset();

// Destrói a sessão por completo.
// Isso remove o arquivo de sessão do servidor.
session_destroy();

// Redireciona o usuário para a página de login ou a página inicial (index.php).
// O header() é um redirecionamento do lado do servidor, que é mais seguro e rápido.
header("Location: login.php");

// É crucial parar a execução do script para evitar qualquer processamento de código indevido.
exit();
?>