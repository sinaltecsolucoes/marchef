<?php

require_once("config.php");

// Define o fuso horário para garantir que todas as operações de data e hora sejam consistentes
date_default_timezone_set('America/Fortaleza');

try {
    // Adiciona opções de segurança e tratamento de erros na conexão PDO
    $opcoes_pdo = [
        // Lança exceções para erros de SQL, permitindo um tratamento mais limpo
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Desabilita emulação de prepared statements para maior segurança contra injeção SQL
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Define o modo de retorno padrão para arrays associativos (colunas com seus nomes)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    // Estabelece a conexão com o banco de dados.
    // Recomenda-se usar 'utf8mb4' para maior compatibilidade com emojis e outros caracteres especiais.
    $pdo = new PDO("mysql:host=$servidor;dbname=$banco;charset=utf8mb4", $usuario, $senha, $opcoes_pdo);

} catch (PDOException $e) {
    // Mensagem de erro para o usuário final
    echo 'Não foi possível conectar ao banco de dados. Por favor, tente novamente mais tarde.';

    // Opcional: Registre o erro detalhado em um arquivo de log, mas não o mostre para o usuário por questões de segurança.
    // error_log('Erro de conexão com o banco de dados: ' . $e->getMessage());

    // Interrompe a execução do script se a conexão falhar
    exit();
}

?>