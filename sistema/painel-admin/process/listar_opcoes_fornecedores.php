<?php
// process/listar_opcoes.php
// (Este script pode ser reutilizado em outras telas)

require_once('../../conexao.php'); // Verifique se este caminho está correto
require_once('../../includes/error_handler.php'); // Verifique se este caminho está correto

header('Content-Type: application/json');

try {
    // Busca apenas fornecedores ativos
    $stmt = $pdo->query("SELECT ent_codigo, ent_razao_social, ent_codigo_interno 
                         FROM tbl_entidades 
                         WHERE (ent_tipo_entidade = 'Fornecedor' OR ent_tipo_entidade = 'Cliente e Fornecedor') 
                         AND ent_situacao = 'A' 
                         ORDER BY ent_razao_social ASC");
    
    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $fornecedores]);

} catch (PDOException $e) {
    error_log("Erro ao listar fornecedores: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}

exit(); 