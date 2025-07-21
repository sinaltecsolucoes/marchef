<?php
// process/lotes/get_proximo_numero.php

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT MAX(lote_numero) as ultimo_numero FROM tbl_lotes");
    $ultimo_numero = $stmt->fetchColumn();

    // Se não houver nenhum lote, começa do 1. Senão, incrementa o último.
    $proximo_numero = ($ultimo_numero) ? (int)$ultimo_numero + 1 : 1;

    // Formata o número para ter sempre 4 dígitos, com zeros à esquerda
    $numero_formatado = str_pad($proximo_numero, 4, '0', STR_PAD_LEFT);

    echo json_encode(['success' => true, 'proximo_numero' => $numero_formatado]);

} catch (PDOException $e) {
    // Loga o erro e envia uma resposta de erro
    error_log("Erro ao buscar próximo número do lote: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}