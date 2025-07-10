<?php
// config.php

// AVISO DE SEGURANÇA:
// As credenciais de banco de dados abaixo são para um ambiente de DESENVOLVIMENTO LOCAL.
// NUNCA use o usuário 'root' com senha vazia em um servidor de produção.
// Em produção, crie um usuário específico para a aplicação e defina uma senha forte.


// Configurações do Projeto (Podem ser constantes ou variáveis)
// Usar `const` é uma boa prática para valores fixos
const NOME_SITE = 'MARCHEF PESCADOS';
const URL_SITE = 'http://localhost/marchef/';
const ENDERECO_SITE = 'Rua Antônio Inácio, Número 418, Bairro Lagoa Seca - Itarema - CE CEP 62590-000';
const EMAIL_ADM = 'sinaltecsolucoes@gmail.com.br';
const TELEFONE_FIXO = '(33) 3333-3333';
const TELEFONE_WHATSAPP = '(88) 99243-2756';
const TELEFONE_WHATSAPP_LINK = '5588992432756';


// Configurações do Banco de Dados
$db_config = [
    //localhost
    'servidor' => 'localhost',
    'usuario' => 'root',
    'senha' => '',
    'banco' => 'marchef',

    //online
    // 'servidor' => 'sql310.infinityfree.com',
    // 'usuario' => '	if0_39440979',
    // 'senha' => 'SiteMarchef2025',
    // 'banco' => 'if0_39440979_marchef',
];

// Configurações de E-mail (Exemplo, caso precise no futuro)
$email_config = [
    'servidor_smtp' => 'smtp.gmail.com',
    'porta_smtp' => 587,
    'usuario_email' => 'seuemail@gmail.com',
    'senha_email' => 'suasenha',
];

// Opcional: Para manter as variáveis globais, você pode extraí-las do array
// Isso torna o acesso mais fácil em arquivos antigos que esperam essas variáveis
extract($db_config);
// Depois de usar extract, você pode acessar $servidor, $usuario, etc.
// No entanto, a prática recomendada é usar $db_config['servidor'] diretamente.