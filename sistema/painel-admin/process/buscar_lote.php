<?php
// process/lotes/buscar_lote.php

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'ID do lote não fornecido.'];

if (isset($_POST['lote_id'])) {
    $lote_id = $_POST['lote_id'];

    try {
        // 1. Busca os dados do cabeçalho do lote
        $stmt_header = $pdo->prepare("SELECT * FROM tbl_lotes WHERE lote_id = :lote_id");
        $stmt_header->execute([':lote_id' => $lote_id]);
        $header = $stmt_header->fetch(PDO::FETCH_ASSOC);

        if ($header) {
            // 2. Busca os itens (produtos) associados a este lote
            $stmt_items = $pdo->prepare(
                "SELECT li.*, p.prod_descricao, p.prod_peso_embalagem 
                 FROM tbl_lote_itens li 
                 JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo 
                 WHERE li.item_lote_id = :lote_id"
            );
            $stmt_items->execute([':lote_id' => $lote_id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            // 3. Monta a resposta final
            $response = [
                'success' => true,
                'data' => [
                    'header' => $header,
                    'items' => $items
                ]
            ];
        } else {
            $response['message'] = 'Lote não encontrado.';
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Erro no servidor ao buscar o lote.';
        error_log("Erro ao buscar lote: " . $e->getMessage());
    }
}

echo json_encode($response);