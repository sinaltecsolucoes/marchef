<?php
// process/lotes/salvar_item.php

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

session_start();
header('Content-Type: application/json');

// Validações de segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(); }
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { http_response_code(403); exit(); }

// Pega os dados do POST
$item_id = $_POST['item_id'] ?? null; // A CHAVE para saber se é INSERT ou UPDATE
$lote_id = $_POST['lote_id'] ?? null;
$produto_id = $_POST['item_produto_id'] ?? null;
$quantidade = $_POST['item_quantidade'] ?? null;
$data_validade = $_POST['item_data_validade'] ?? null;

if (empty($lote_id) || empty($produto_id) || empty($quantidade) || empty($data_validade)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
    exit();
}

try {
    if (empty($item_id)) {
        // --- INSERIR NOVO ITEM ---
        $sql = "INSERT INTO tbl_lote_itens (item_lote_id, item_produto_id, item_quantidade, item_data_validade) 
                VALUES (:lote_id, :produto_id, :quantidade, :data_validade)";
        $stmt = $pdo->prepare($sql);
    } else {
        // --- ATUALIZAR ITEM EXISTENTE ---
        $sql = "UPDATE tbl_lote_itens SET 
                    item_produto_id = :produto_id, 
                    item_quantidade = :quantidade, 
                    item_data_validade = :data_validade 
                WHERE item_id = :item_id AND item_lote_id = :lote_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    }
    
    $stmt->bindParam(':lote_id', $lote_id, PDO::PARAM_INT);
    $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt->bindParam(':quantidade', $quantidade);
    $stmt->bindParam(':data_validade', $data_validade);
    $stmt->execute();

    $id_do_item_afetado = empty($item_id) ? $pdo->lastInsertId() : $item_id;

    // Busca os dados atualizados para retornar ao front-end (incluindo o peso)
    $stmt_item = $pdo->prepare("SELECT li.*, p.prod_descricao, p.prod_peso_embalagem FROM tbl_lote_itens li JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo WHERE li.item_id = :item_id");
    $stmt_item->execute([':item_id' => $id_do_item_afetado]);
    $item_atualizado = $stmt_item->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'message' => 'Item salvo com sucesso!', 'item_atualizado' => $item_atualizado]);

} catch (PDOException $e) {
    error_log("Erro ao salvar item no lote: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor ao salvar o item.']);
}