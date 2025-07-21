<?php
// process/lotes/salvar_cabecalho.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Valida o token CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança (CSRF).']);
    exit();
}

// Pega os dados do POST
$lote_id = $_POST['lote_id'] ?? null;
$lote_numero = $_POST['lote_numero'] ?? '';
$lote_data_fabricacao = $_POST['lote_data_fabricacao'] ?? '';
$lote_fornecedor_id = $_POST['lote_fornecedor_id'] ? (int)$_POST['lote_fornecedor_id'] : null;
$lote_ciclo = $_POST['lote_ciclo'] ?? '';
$lote_viveiro = $_POST['lote_viveiro'] ?? '';
$lote_completo_calculado = $_POST['lote_completo_calculado'] ?? '';
$usuario_id = $_SESSION['codUsuario'] ?? null;

// Validação simples dos dados
if (empty($lote_numero) || empty($lote_data_fabricacao) || empty($usuario_id)) {
    echo json_encode(['success' => false, 'message' => 'Número do Lote e Data de Fabricação são obrigatórios.']);
    exit();
}

$pdo->beginTransaction();

try {
    if (empty($lote_id)) {
        // --- INSERIR NOVO LOTE ---
        $sql = "INSERT INTO tbl_lotes (lote_numero, lote_data_fabricacao, lote_fornecedor_id, lote_ciclo, lote_viveiro, lote_completo_calculado, lote_usuario_id) 
                VALUES (:numero, :data_fab, :fornecedor, :ciclo, :viveiro, :completo, :usuario)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':numero' => $lote_numero,
            ':data_fab' => $lote_data_fabricacao,
            ':fornecedor' => $lote_fornecedor_id,
            ':ciclo' => $lote_ciclo,
            ':viveiro' => $lote_viveiro,
            ':completo' => $lote_completo_calculado,
            ':usuario' => $usuario_id
        ]);
        
        $novo_lote_id = $pdo->lastInsertId();
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cabeçalho do lote salvo com sucesso!', 
            'novo_lote_id' => $novo_lote_id
        ]);

    } else {
        // --- ATUALIZAR LOTE EXISTENTE ---
        $sql = "UPDATE tbl_lotes SET 
                    lote_numero = :numero, 
                    lote_data_fabricacao = :data_fab, 
                    lote_fornecedor_id = :fornecedor, 
                    lote_ciclo = :ciclo, 
                    lote_viveiro = :viveiro, 
                    lote_completo_calculado = :completo
                WHERE lote_id = :lote_id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':numero' => $lote_numero,
            ':data_fab' => $lote_data_fabricacao,
            ':fornecedor' => $lote_fornecedor_id,
            ':ciclo' => $lote_ciclo,
            ':viveiro' => $lote_viveiro,
            ':completo' => $lote_completo_calculado,
            ':lote_id' => $lote_id
        ]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Cabeçalho do lote atualizado com sucesso!']);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Erro ao salvar lote: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor ao salvar o lote.']);
}