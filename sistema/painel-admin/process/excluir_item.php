<?php
// process/excluir_item.php

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

session_start();
header('Content-Type: application/json');

// Validações de segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

// Valida o token CSRF (se você estiver enviando um com esta requisição)
/* Descomente se for enviar o token CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança (CSRF).']);
    exit();
}
*/

$response = ['success' => false, 'message' => 'ID do item não fornecido.'];

if (isset($_POST['item_id'])) {
    $item_id = $_POST['item_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM tbl_lote_itens WHERE item_id = :item_id");
        $stmt->execute([':item_id' => $item_id]);

        // Verifica se alguma linha foi realmente afetada
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Item removido do lote com sucesso!';
        } else {
            $response['message'] = 'Nenhum item encontrado com o ID fornecido.';
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Erro no servidor ao excluir o item.';
        error_log("Erro ao excluir item do lote: " . $e->getMessage());
    }
}

echo json_encode($response);