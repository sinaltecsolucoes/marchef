<?php
// /src/bootstrap.php

// ========================================================================
// CONFIGURAÇÕES GLOBAIS E INICIALIZAÇÃO
// ========================================================================

// 1. Manipulador de Erros (deve ser o primeiro)
// ATENÇÃO: O caminho agora parte de /src, então usamos '__DIR__' para garantir o caminho correto.
require_once __DIR__ . "/Core/error_handler.php"; // Mantenha o seu por enquanto

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
define('BASE_URL', 'http://localhost/marchef/public');

// 4. Início da Sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 5. Cabeçalhos de Segurança (copiados do seu index.php original)
// Eles ficam aqui para serem aplicados a TODAS as respostas do servidor.
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.datatables.net; " .
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net; " .
    "img-src 'self' data:; " .
    "font-src 'self' https://fonts.gstatic.com; " .
    "connect-src 'self' https://viacep.com.br https://cdn.datatables.net https://brasilapi.com.br; " .
    "form-action 'self';");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), geolocation=(), microphone=(), usb=(), payment=()");