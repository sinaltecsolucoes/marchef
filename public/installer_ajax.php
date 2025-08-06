<?php
// /public/installer_ajax.php
session_start();
header('Content-Type: application/json');

// Medida de segurança: só permite que este script seja executado se a instalação não estiver bloqueada
$lockFile = __DIR__ . '/../config/install.lock';
if (file_exists($lockFile)) {
    echo json_encode(['success' => false, 'message' => 'O sistema já está instalado.']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'criar_tabelas':
        criar_tabelas();
        break;
    case 'finalizar_instalacao':
        finalizar_instalacao();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
        exit;
}

function criar_tabelas()
{
    $configFile = __DIR__ . '/../config/database.php';
    $schemaFile = __DIR__ . '/../config/schema.sql';

    if (!file_exists($configFile) || !file_exists($schemaFile)) {
        echo json_encode(['success' => false, 'message' => 'Ficheiro de configuração ou schema.sql não encontrado.']);
        exit;
    }

    try {
        $dbConfig = require $configFile;
        // CORREÇÃO 1: Usar 'password' em vez de 'pass'
        $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4", $dbConfig['user'], $dbConfig['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);

        $_SESSION['install_step'] = 3;
        echo json_encode(['success' => true, 'message' => 'Tabelas criadas com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar tabelas: ' . $e->getMessage()]);
    }
}

function finalizar_instalacao()
{
    $configFile = __DIR__ . '/../config/database.php';
    if (!file_exists($configFile)) {
        echo json_encode(['success' => false, 'message' => 'Ficheiro de configuração não encontrado.']);
        exit;
    }

    try {
        $dbConfig = require $configFile;
        // CORREÇÃO 1: Usar 'password' em vez de 'pass'
        $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4", $dbConfig['user'], $dbConfig['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Processar o upload do logo
        $logoPath = null;
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/img/logo/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0775, true);
            $filename = 'logo_' . time() . '_' . basename($_FILES['company_logo']['name']);
            $logoPath = 'img/logo/' . $filename;
            move_uploaded_file($_FILES['company_logo']['tmp_name'], $uploadDir . $filename);
        }

        // 2. Inserir dados da empresa
        $stmt = $pdo->prepare("INSERT INTO tbl_configuracoes (config_nome_empresa, config_nome_fantasia, config_cnpj, config_logo_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['company_name'],
            $_POST['company_name_fantasia'],
            preg_replace('/\D/', '', $_POST['company_cnpj']),
            $logoPath
        ]);

        // 3. Criar o utilizador administrador
        $senha_hashed = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO tbl_usuarios (usu_nome, usu_login, usu_senha, usu_tipo, usu_situacao) VALUES (?, ?, ?, 'Admin', 'A')");
        $stmt->execute([$_POST['admin_name'], $_POST['admin_login'], $senha_hashed]);

        // 4. Inserir todas as permissões para o perfil 'Admin'
        $todasAsPaginas = [
            'usuarios',
            'clientes',
            'fornecedores',
            'produtos',
            'lotes',
            'permissoes',
            'templates',
            'regras',
            'auditoria',
            'backup'
        ];

        $stmtPermissao = $pdo->prepare("INSERT INTO tbl_permissoes (permissao_perfil, permissao_pagina) VALUES ('Admin', ?)");

        foreach ($todasAsPaginas as $pagina) {
            $stmtPermissao->execute([$pagina]);
        } // <-- CORREÇÃO 3: Chaveta '}' do foreach estava em falta aqui

        // CORREÇÃO 2: Estes passos devem acontecer DEPOIS do loop terminar
        // 5. Criar o ficheiro de bloqueio
        file_put_contents(__DIR__ . '/../config/install.lock', 'Instalado em: ' . date('Y-m-d H:i:s'));

        // 6. Enviar a resposta de sucesso
        echo json_encode(['success' => true, 'message' => 'Instalação finalizada com sucesso!']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro na finalização: ' . $e->getMessage()]);
    }
}