<?php
// process/listar_opcoes.php

require_once('../../conexao.php'); // Verifique o caminho aqui também!
require_once('../../includes/error_handler.php'); // Verifique o caminho aqui também!

header('Content-Type: application/json');

// Pega o filtro enviado pelo JavaScript
$tipo_embalagem = $_GET['tipo_embalagem'] ?? '';

try {
    $sql = "SELECT prod_codigo, prod_descricao, prod_tipo_embalagem, prod_validade_meses, prod_peso_embalagem 
            FROM tbl_produtos 
            WHERE prod_situacao = 'A'";

    // Adiciona a condição do filtro se ele for 'PRIMARIA' ou 'SECUNDARIA'
    if (!empty($tipo_embalagem) && $tipo_embalagem !== 'Todos') {
        $sql .= " AND prod_tipo_embalagem = :tipo_embalagem";
    }

    $sql .= " ORDER BY prod_descricao ASC";
    
    $stmt = $pdo->prepare($sql);

    // Faz o bind do parâmetro apenas se ele for necessário
    if (!empty($tipo_embalagem) && $tipo_embalagem !== 'Todos') {
        $stmt->bindParam(':tipo_embalagem', $tipo_embalagem, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $produtos]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor ao buscar produtos.']);
}

exit(); 