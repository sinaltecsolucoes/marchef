<?php
// /src/bootstrap.php
// Define o fuso horário padrão para toda a aplicação como o Horário de Brasília
date_default_timezone_set('America/Sao_Paulo'); //Mesmo fuso horario de Brasilia

// ========================================================================
// CONFIGURAÇÕES GLOBAIS E INICIALIZAÇÃO
// ========================================================================

// 1. Manipulador de Erros (deve ser o primeiro)
require_once __DIR__ . "/Core/error_handler.php";

// 2. Autoloader de Classes (essencial para carregar nossas classes de /src)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/'; // O diretório base para as classes é /src
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// 3. Definição de Constantes Globais
// ATENÇÃO: Ajuste a URL se seu projeto não estiver na raiz do localhost.
//define('BASE_URL', 'http://localhost/marchef/public');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$baseURL = rtrim($protocol . $host . $scriptPath, '/');

if (!defined('BASE_URL')) {
    define('BASE_URL', $baseURL);
}

// 4. Início da Sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 5. Cabeçalhos de Segurança (copiados do seu index.php original)
// Eles ficam aqui para serem aplicados a TODAS as respostas do servidor.
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
/* header("Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.datatables.net; " .
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com; " .
    "img-src 'self' data: https://cdn.jsdelivr.net; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
    "connect-src 'self' https://viacep.com.br https://cdn.datatables.net https://brasilapi.com.br https://cdn.jsdelivr.net; " .
    "form-action 'self';"); */
header("Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.datatables.net; " .
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com; " .
    "img-src 'self' data: https://cdn.jsdelivr.net; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
    "connect-src 'self' https://viacep.com.br https://brasilapi.com.br https://cdn.jsdelivr.net https://open.cnpja.com; " .
    "form-action 'self';");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), geolocation=(), microphone=(), usb=(), payment=()");