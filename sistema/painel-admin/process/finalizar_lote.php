<?php
// process/lotes/finalizar_lote.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

session_start();
header('Content-Type: application/json');

// Validações
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(); }
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { http_response_code(403); exit(); }

$response = ['success' => false, 'message' => 'ID do lote não fornecido.'];

if (isset($_POST['lote_id'])) {
    $lote_id = $_POST['lote_id'];

    $pdo->beginTransaction();
    try {
        // 1. Pega o status atual para garantir que o lote ainda está "EM ANDAMENTO"
        $stmt_check = $pdo->prepare("SELECT lote_status FROM tbl_lotes WHERE lote_id = :lote_id FOR UPDATE");
        $stmt_check->execute([':lote_id' => $lote_id]);
        $status_atual = $stmt_check->fetchColumn();

        if ($status_atual !== 'EM ANDAMENTO') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Este lote não pode ser finalizado, pois seu status não é "EM ANDAMENTO".']);
            exit();
        }

        // 2. Busca todos os itens do lote
        $stmt_itens = $pdo->prepare("SELECT item_id, item_produto_id, item_quantidade FROM tbl_lote_itens WHERE item_lote_id = :lote_id");
        $stmt_itens->execute([':lote_id' => $lote_id]);
        $itens_do_lote = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

        if (empty($itens_do_lote)) {
             $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Não é possível finalizar um lote sem produtos.']);
            exit();
        }

        // 3. Insere cada item na tabela de estoque
        $stmt_estoque = $pdo->prepare(
            "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento) 
             VALUES (:produto_id, :lote_item_id, :quantidade, 'ENTRADA')"
        );
        foreach ($itens_do_lote as $item) {
            $stmt_estoque->execute([
                ':produto_id' => $item['item_produto_id'],
                ':lote_item_id' => $item['item_id'],
                ':quantidade' => $item['item_quantidade']
            ]);
        }

        // 4. Altera o status do lote para "FINALIZADO"
        $stmt_lote = $pdo->prepare("UPDATE tbl_lotes SET lote_status = 'FINALIZADO' WHERE lote_id = :lote_id");
        $stmt_lote->execute([':lote_id' => $lote_id]);

        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Lote finalizado e estoque gerado com sucesso!';

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        $response['message'] = 'Erro no servidor ao finalizar o lote.';
        error_log("Erro ao finalizar lote: " . $e->getMessage());
    }
}

echo json_encode($response);