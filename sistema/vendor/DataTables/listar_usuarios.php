<?php
// Arquivo: listar_usuarios.php
// Este arquivo deve estar em uma pasta segura, como 'vendor'.

// Inclui a conexão com o banco de dados
require_once('../../conexao.php');

// Prepara e executa a consulta para buscar os usuários
$query = $pdo->prepare("SELECT * FROM tbl_usuarios");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

// Cria um array para armazenar os dados
$data = [];
foreach ($res as $row) {
    // Adiciona cada linha ao array, no formato que o DataTables espera
    $data[] = $row;
}

// Prepara o array final para o DataTables
$output = [
    'data' => $data
];

// Retorna o resultado em formato JSON
header('Content-Type: application/json');
echo json_encode($output);
?>