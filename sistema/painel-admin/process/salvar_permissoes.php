<?php
// salvar_permissoes.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../../conexao.php');

// Apenas permitir acesso via POST e para administradores
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.']);
    exit();
}

if (!isset($_SESSION['tipoUsuario']) || $_SESSION['tipoUsuario'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem gerenciar permissões.']);
    exit();
}

$permissoes_enviadas = $_POST['permissoes'] ?? []; // Array associativo: ['pagina' => ['perfil1', 'perfil2'], ...]

// REPETIR ESTAS LISTAS AQUI E NO permissoes.php É CRUCIAL PARA A INTEGRIDADE
// Define os tipos de usuários (perfis) disponíveis
$perfis_disponiveis = ['Admin', 'Gerente', 'Producao']; 

// Define as páginas que podem ter permissões.
$paginas_disponiveis_chaves = [
    'home', 'usuarios', 'clientes', 'fornecedores', 'produtos',
    // A página 'permissoes' (a própria tela) não precisa ser gerenciada aqui.
];

try {
    $pdo->beginTransaction();

    // 1. Limpar TODAS as permissões existentes na tabela
    // Isso é mais simples do que gerenciar inserções e deleções específicas.
    // As permissões do Admin serão REINSERIDAS logo em seguida.
    $stmt_delete = $pdo->prepare("DELETE FROM tbl_permissoes");
    $stmt_delete->execute();

    // 2. Inserir as novas permissões marcadas que foram enviadas pelo formulário
    // Estas são as permissões que o Admin selecionou (ou não) para Gerente e Produção.
    $stmt_insert = $pdo->prepare("INSERT INTO tbl_permissoes (permissao_pagina, permissao_perfil) VALUES (:pagina, :perfil)");

    foreach ($permissoes_enviadas as $pagina_chave => $perfis_selecionados) {
        if (!is_array($perfis_selecionados)) {
            $perfis_selecionados = [$perfis_selecionados];
        }
        foreach ($perfis_selecionados as $perfil) {
            // Insere apenas as permissões selecionadas. O Admin será tratado abaixo.
            $stmt_insert->bindValue(':pagina', $pagina_chave);
            $stmt_insert->bindValue(':perfil', $perfil);
            $stmt_insert->execute();
        }
    }

    // 3. Garantir que o perfil 'Admin' SEMPRE tenha acesso a TODAS as páginas listadas.
    // Isso é feito explicitamente para evitar que as permissões do Admin sejam perdidas
    // devido aos checkboxes desabilitados no frontend.
    // 'INSERT IGNORE' é uma funcionalidade do MySQL que ignora erros de chave duplicada.
    // Se você usa outro banco de dados, pode precisar de uma lógica diferente aqui.
    $stmt_insert_admin = $pdo->prepare("INSERT IGNORE INTO tbl_permissoes (permissao_pagina, permissao_perfil) VALUES (:pagina, 'Admin')");
    foreach ($paginas_disponiveis_chaves as $pagina_chave_admin) {
        $stmt_insert_admin->bindValue(':pagina', $pagina_chave_admin);
        $stmt_insert_admin->execute();
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Permissões salvas com sucesso!']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Erro ao salvar permissões: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar permissões: ' . $e->getMessage()]);
}

?>