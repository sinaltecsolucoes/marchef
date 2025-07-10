<?php
// Inicia a sessão se ainda não estiver iniciada.
// session_start() deve ser a primeira coisa no script.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado E se o tipo de usuário NÃO é 'Admin'.
// Usar isset() para evitar avisos se 'tipoUsuario' não estiver definido na sessão.
// Redirecionamento via header() é mais seguro e limpo.
if (!isset($_SESSION['tipoUsuario']) || $_SESSION['tipoUsuario'] !== 'Admin') {
    // Para depuração, você pode querer logar a tentativa de acesso não autorizado:
    // error_log("Tentativa de acesso não autorizado por usuário tipo: " . ($_SESSION['tipoUsuario'] ?? 'NÃO DEFINIDO'));

    // Redireciona para a página de login ou para a raiz, dependendo da sua estrutura.
    // Certifique-se de que o caminho '../login.php' ou '../' esteja correto.
    header("Location: ../login.php"); // Exemplo: redireciona para a página de login
    exit(); // É crucial parar a execução após o redirecionamento
}

// Se o código chegar aqui, significa que o usuário é 'Admin' e tem acesso.
?>