<?php
// process/lotes/listar_lotes.php

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

header('Content-Type: application/json');

// Parâmetros do DataTables
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';
$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';

// Mapeamento das colunas do DataTables para as colunas do banco
$columns = [
    0 => 'l.lote_completo_calculado',
    1 => 'f.ent_razao_social',
    2 => 'l.lote_data_fabricacao',
    3 => 'l.lote_status',
    4 => 'l.lote_data_cadastro'
];
$orderColumn = $columns[$orderColumnIndex] ?? 'l.lote_data_cadastro';

// Construção da consulta base com JOIN para buscar o nome do fornecedor
$baseQuery = "FROM tbl_lotes l LEFT JOIN tbl_entidades f ON l.lote_fornecedor_id = f.ent_codigo";

// --- Contagem Total de Registros ---
$stmtTotal = $pdo->query("SELECT COUNT(l.lote_id) " . $baseQuery);
$totalRecords = $stmtTotal->fetchColumn();

// --- Lógica de Filtro (Busca) ---
$whereClause = "";
$params = [];
if (!empty($searchValue)) {
    $searchTerm = '%' . $searchValue . '%';
    $whereClause = " WHERE (l.lote_completo_calculado LIKE :search OR f.ent_razao_social LIKE :search OR l.lote_status LIKE :search)";
    $params[':search'] = $searchTerm;
}

// --- Contagem de Registros Filtrados ---
$stmtFiltered = $pdo->prepare("SELECT COUNT(l.lote_id) " . $baseQuery . $whereClause);
$stmtFiltered->execute($params);
$totalFiltered = $stmtFiltered->fetchColumn();

// --- Busca dos Dados Paginados ---
$query = "SELECT l.*, f.ent_razao_social AS fornecedor_razao_social " . $baseQuery . $whereClause . " ORDER BY " . $orderColumn . " " . $orderDir . " LIMIT :start, :length";

$stmt = $pdo->prepare($query);
$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
if (!empty($searchValue)) {
    $stmt->bindParam(':search', $searchTerm);
}
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Formatação da Saída para o DataTables ---
$output = [
    "draw" => (int)$draw,
    "recordsTotal" => (int)$totalRecords,
    "recordsFiltered" => (int)$totalFiltered,
    "data" => $data
];

echo json_encode($output);