<?php
// process/lotes/excluir_lote.php

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

session_start();
header('Content-Type: application/json');

// Validações de segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit();
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403); exit();
}

$response = ['success' => false, 'message' => 'ID do lote não fornecido.'];

if (isset($_POST['lote_id'])) {
    $lote_id = $_POST['lote_id'];
    
    $pdo->beginTransaction();
    try {
        // Passo 1: Exclui os itens associados ao lote.
        // (Este passo não é estritamente necessário se você usou ON DELETE CASCADE na tabela,
        // mas é uma boa prática ser explícito para evitar erros).
        $stmt_itens = $pdo->prepare("DELETE FROM tbl_lote_itens WHERE item_lote_id = :lote_id");
        $stmt_itens->execute([':lote_id' => $lote_id]);

        // Passo 2: Exclui o cabeçalho do lote.
        $stmt_lote = $pdo->prepare("DELETE FROM tbl_lotes WHERE lote_id = :lote_id");
        $stmt_lote->execute([':lote_id' => $lote_id]);

        // Se ambos os comandos foram bem-sucedidos, confirma a transação.
        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Lote e todos os seus itens foram excluídos com sucesso!';

    } catch (PDOException $e) {
        // Se qualquer erro ocorrer, desfaz todas as alterações.
        $pdo->rollBack();
        http_response_code(500);
        $response['message'] = 'Erro no servidor ao excluir o lote.';
        error_log("Erro ao excluir lote: " . $e->getMessage());
    }
}

echo json_encode($response);