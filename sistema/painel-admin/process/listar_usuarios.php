<?php
// Arquivo: painel-admin/process/listar_usuarios.php
// Retorna os dados dos usuários em formato JSON para o DataTables.

require_once('../../conexao.php'); // CORREÇÃO AQUI: Caminho atualizado
require_once('../../includes/error_handler.php'); // CORREÇÃO AQUI: Caminho atualizado

header('Content-Type: application/json');

$data = []; // Array para armazenar os dados dos usuários

try {
    $query = $pdo->query("SELECT usu_codigo, usu_nome, usu_login, usu_tipo, usu_situacao FROM tbl_usuarios ORDER BY usu_nome ASC");
    $users = $query->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $data[] = [
            "usu_situacao" => $user['usu_situacao'],
            "usu_codigo" => $user['usu_codigo'],
            "usu_login" => $user['usu_login'],
            "usu_nome" => $user['usu_nome'],
            "usu_tipo" => $user['usu_tipo']

        ];
    }

} catch (PDOException $e) {
    error_log("Erro ao listar usuários para DataTables: " . $e->getMessage());
    // Em caso de erro, retorna um array vazio ou uma mensagem de erro para o frontend
    // Dependendo de como o DataTables lida com erros de AJAX.
    // Para depuração, pode-se retornar um erro mais detalhado:
    // echo json_encode(['error' => 'Falha ao carregar dados dos usuários.']);
}

echo json_encode(['data' => $data]);
exit();
?>