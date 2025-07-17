<?php
// salvar_permissoes.php

session_start();

// Requer os arquivos de conexão e o manipulador de erros
require_once('../conexao.php');
require_once('../includes/error_handler.php');

// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

// Inicializa a resposta padrão
$response = ['success' => false, 'message' => 'Ocorreu um erro desconhecido.'];

// 1. Validações de Segurança
// ========================================================================

// Apenas requisições POST são permitidas
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    $response['message'] = 'Método não permitido.';
    echo json_encode($response);
    exit();
}

// Apenas o Admin pode salvar permissões
if (!isset($_SESSION['tipoUsuario']) || $_SESSION['tipoUsuario'] !== 'Admin') {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Você não tem permissão para executar esta ação.';
    echo json_encode($response);
    exit();
}

// Validação do Token CSRF
$submitted_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    http_response_code(403);
    $response['message'] = 'Erro de segurança: Requisição inválida (CSRF).';
    echo json_encode($response);
    exit();
}

// 2. Lógica de Salvamento
// ========================================================================

// Perfis que podem ter suas permissões alteradas. O 'Admin' é fixo e não deve ser modificado.
$perfis_gerenciaveis = ['Gerente', 'Producao']; 

// Pega os dados de permissões enviados pelo formulário
$permissoes = $_POST['permissoes'] ?? [];

// Inicia uma transação para garantir que todas as operações sejam bem-sucedidas ou nenhuma o seja.
$pdo->beginTransaction();

try {
    // Passo 1: Limpa todas as permissões antigas dos perfis gerenciáveis.
    // Isso garante que as permissões desmarcadas sejam removidas.
    // Usamos placeholders (?) para segurança (prevenção de injeção de SQL).
    $in_placeholders = implode(',', array_fill(0, count($perfis_gerenciaveis), '?'));
    $sql_delete = "DELETE FROM tbl_permissoes WHERE permissao_perfil IN ($in_placeholders)";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute($perfis_gerenciaveis);

    // Passo 2: Insere as novas permissões que foram marcadas no formulário.
    $sql_insert = "INSERT INTO tbl_permissoes (permissao_perfil, permissao_pagina) VALUES (:perfil, :pagina)";
    $stmt_insert = $pdo->prepare($sql_insert);

    // Itera sobre os perfis enviados pelo formulário
    if (is_array($permissoes)) {
        foreach ($permissoes as $perfil => $paginasPermitidas) {
            // Garante que estamos modificando apenas perfis permitidos
            if (in_array($perfil, $perfis_gerenciaveis) && is_array($paginasPermitidas)) {
                // Itera sobre cada página/ação permitida para o perfil
                foreach ($paginasPermitidas as $pagina) {
                    $stmt_insert->execute(['perfil' => $perfil, 'pagina' => $pagina]);
                }
            }
        }
    }

    // Se tudo correu bem, confirma as alterações no banco de dados.
    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'Permissões salvas com sucesso!';

} catch (PDOException $e) {
    // Se qualquer erro ocorrer, desfaz todas as alterações.
    $pdo->rollBack();
    
    // Loga o erro detalhado para o administrador do sistema
    error_log("Erro ao salvar permissões: " . $e->getMessage());
    
    // Envia uma mensagem de erro genérica para o usuário
    http_response_code(500);
    $response['message'] = 'Ocorreu um erro no servidor ao salvar as permissões.';
}

// Envia a resposta final em formato JSON para o JavaScript.
echo json_encode($response);
exit();
?>
