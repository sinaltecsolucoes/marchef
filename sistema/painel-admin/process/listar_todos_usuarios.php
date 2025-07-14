<?php
// Arquivo: painel-admin/listar_todos_usuarios.php
// Retorna uma lista de todos os usuários para preencher um combobox.

require_once('../../conexao.php'); // Ajuste o caminho conforme necessário
require_once('../../includes/error_handler.php'); // Inclui o manipulador de erros

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro desconhecido.', 'data' => []];

try {
    // Busca todos os usuários, ordenados por nome para facilitar a seleção.
    // Selecionamos agora o código, o nome E O LOGIN, que são necessários para o combobox.
    $query = $pdo->prepare("SELECT usu_codigo, usu_nome, usu_login FROM tbl_usuarios ORDER BY usu_nome ASC");
    $query->execute();
    $users = $query->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['message'] = 'Usuários carregados com sucesso.';
    $response['data'] = $users;

} catch (PDOException $e) {
    error_log("Erro ao listar todos os usuários: " . $e->getMessage());
    $response['message'] = "Erro ao carregar a lista de usuários. Por favor, tente novamente mais tarde.";
}

echo json_encode($response);
exit();
?>
