<?php
// error_handler.php

// Define um manipulador de exceções global
function customExceptionHandler($exception) {
    // Loga a exceção
    error_log("[EXCEÇÃO NÃO CAPTURADA] " . $exception->getMessage() . " em " . $exception->getFile() . " na linha " . $exception->getLine());

    // Exibe uma mensagem amigável para o usuário (em ambiente de produção)
    // Em ambiente de desenvolvimento, você pode querer exibir mais detalhes.
    // Para simplificar, vamos exibir uma mensagem genérica aqui.
    http_response_code(500); // Define o código de status HTTP para Erro Interno do Servidor
    echo "<h1>Ocorreu um erro inesperado no servidor.</h1>";
    echo "<p>Por favor, tente novamente mais tarde ou entre em contato com o suporte.</p>";

    // Se estiver em ambiente de desenvolvimento, você pode querer mostrar o erro detalhado:
    // if (ini_get('display_errors') == '1') {
    //     echo "<pre>";
    //     print_r($exception);
    //     echo "</pre>";
    // }
    exit(); // Termina a execução do script
}

// Define um manipulador de erros global
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // Ignora erros que não são reportados pelo error_reporting (ex: se E_NOTICE for desabilitado)
    if (!(error_reporting() & $errno)) {
        return false;
    }

    // Loga o erro
    error_log("[ERRO PHP] Tipo: $errno, Mensagem: $errstr, Arquivo: $errfile, Linha: $errline");

    // Exibe uma mensagem amigável para o usuário (em ambiente de produção)
    // Em ambiente de desenvolvimento, você pode querer exibir mais detalhes.
    // Para simplificar, vamos exibir uma mensagem genérica aqui.
    http_response_code(500); // Define o código de status HTTP para Erro Interno do Servidor
    echo "<h1>Ocorreu um erro inesperado no servidor.</h1>";
    echo "<p>Por favor, tente novamente mais tarde ou entre em contato com o suporte.</p>";

    // Se estiver em ambiente de desenvolvimento, você pode querer mostrar o erro detalhado:
    // if (ini_get('display_errors') == '1') {
    //     echo "<h2>Detalhes do Erro (Apenas em Desenvolvimento):</h2>";
    //     echo "<p><strong>Mensagem:</strong> $errstr</p>";
    //     echo "<p><strong>Arquivo:</strong> $errfile</p>";
    //     echo "<p><strong>Linha:</strong> $errline</p>";
    // }
    exit(); // Termina a execução do script
}

// Registra os manipuladores
set_exception_handler("customExceptionHandler");
set_error_handler("customErrorHandler");

// Opcional: Desativar a exibição de erros no navegador para produção via código,
// se você não tiver controle total sobre o php.ini.
// No entanto, a preferência é configurar no php.ini.
// ini_set('display_errors', 'Off');
// error_reporting(E_ALL); // Garante que todos os erros sejam capturados para log.

?>
