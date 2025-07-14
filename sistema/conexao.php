<?php
// conexao.php

// Inclui o arquivo de configuração que contém as credenciais do banco de dados.
// É crucial que 'config.php' NÃO seja acessível diretamente via navegador.
require_once("config.php");

// Define o fuso horário para garantir que todas as operações de data e hora sejam consistentes.
date_default_timezone_set('America/Fortaleza');

try {
    // Adiciona opções de segurança e tratamento de erros na conexão PDO.
    $opcoes_pdo = [
        // Lança exceções para erros de SQL, permitindo um tratamento mais limpo.
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Desabilita emulação de prepared statements para maior segurança contra injeção SQL.
        PDO::ATTR_EMULATE_PREPARES => false,
        // Define o modo de retorno padrão para arrays associativos (colunas com seus nomes).
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    // Estabelece a conexão com o banco de dados.
    // Recomenda-se usar 'utf8mb4' para maior compatibilidade com emojis e outros caracteres especiais.
    $pdo = new PDO("mysql:host=$servidor;dbname=$banco;charset=utf8mb4", $usuario, $senha, $opcoes_pdo);

} catch (PDOException $e) {
    // Em vez de 'echo' e 'exit' diretamente aqui, relançamos a exceção.
    // Isso permite que o manipulador de exceções global (definido em 'error_handler.php')
    // capture, logue e exiba a mensagem de erro de forma consistente para o usuário.
    throw new PDOException("Erro de conexão com o banco de dados: " . $e->getMessage(), (int) $e->getCode());
}

?>