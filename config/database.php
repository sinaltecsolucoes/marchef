<?php
// /config/database.php

const NOME_SITE = 'MARCHEF PESCADOS';
const URL_SITE = 'http://localhost/marchef/';
const ENDERECO_SITE = 'Rua Antônio Inácio, Número 418, Bairro Lagoa Seca - Itarema - CE CEP 62590-000';
const EMAIL_ADM = 'sinaltecsolucoes@gmail.com.br';
const TELEFONE_WHATSAPP = '(88) 99243-2756';
const TELEFONE_WHATSAPP_LINK = '5588992432756';

// Retorna um array com as configurações do banco de dados.
return [
//localhost
    'host' => 'localhost', 
    'dbname' => 'marchef',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'

    //online
    // 'host' => 'sql310.infinityfree.com',
    // 'dbname' => 'if0_39440979_marchef',
    // 'user' => 'if0_39440979',
    // 'password' => 'SiteMarchef2025'
];