<?php
// /config/database.php

// Constantes do site, agora protegidas contra redefinição
if (!defined('NOME_SITE')) {
    define('NOME_SITE', 'MARCHEF PESCADOS');
}
if (!defined('URL_SITE')) {
    define('URL_SITE', 'http://localhost/marchef/');
    //define('URL_SITE', 'http://marchef.infinityfreeapp.com/marchef/public/');
}
if (!defined('ENDERECO_SITE')) {
    define('ENDERECO_SITE', 'Rua Antônio Inácio, Número 418, Bairro Lagoa Seca - Itarema - CE CEP 62590-000');
}
if (!defined('EMAIL_ADM')) {
    define('EMAIL_ADM', 'sinaltecsolucoes@gmail.com.br');
}
if (!defined('TELEFONE_WHATSAPP')) {
    define('TELEFONE_WHATSAPP', '(88) 99243-2756');
}
if (!defined('TELEFONE_WHATSAPP_LINK')) {
    define('TELEFONE_WHATSAPP_LINK', '5588992432756');
}

// Retorna um array com as configurações do banco de dados.
// Esta parte continua igual.
return [
    //localhost
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'marchef',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'

    //online
    // 'host' => 'sql310.infinityfree.com',
    // 'dbname' => 'if0_39440979_marchef',
    // 'user' => 'if0_39440979',
    // 'password' => 'SiteMarchef2025',
    //'charset' => 'utf8mb4'
];