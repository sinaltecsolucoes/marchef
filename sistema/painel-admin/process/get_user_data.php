<?php
// Arquivo: painel-admin/process/get_user_data.php
// Retorna os dados de um único usuário com base no seu ID.

require_once('../../conexao.php'); // CORREÇÃO AQUI: Caminho atualizado
require_once('../../includes/error_handler.php'); // CORREÇÃO AQUI: Caminho atualizado

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro desconhecido.', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$userId) {
        $response['message'] = "ID de usuário inválido.";
        echo json_encode($response);
        exit();
    }

    try {
        $query = $pdo->prepare("SELECT usu_codigo, usu_nome, usu_login, usu_situacao, usu_tipo FROM tbl_usuarios WHERE usu_codigo = :id");
        $query->bindParam(':id', $userId, PDO::PARAM_INT);
        $query->execute();
        $user = $query->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $response['success'] = true;
            $response['message'] = 'Dados do usuário carregados com sucesso.';
            $response['data'] = $user;
        } else {
            $response['message'] = "Usuário não encontrado.";
        }

    } catch (PDOException $e) {
        error_log("Erro ao buscar dados do usuário (get_user_data.php): " . $e->getMessage());
        $response['message'] = "Erro no servidor ao carregar dados do usuário. Por favor, tente novamente mais tarde.";
    }
} else {
    $response['message'] = "Requisição inválida.";
}

echo json_encode($response);
exit();
?>
