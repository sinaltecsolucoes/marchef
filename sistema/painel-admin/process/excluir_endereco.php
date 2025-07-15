<?php
// Arquivo: process/excluir_endereco.php
// Realiza a exclusão de um endereço no banco de dados.

session_start(); // Inicia a sessão para verificar o token CSRF
require_once('../../conexao.php'); // Inclui a conexão com o banco de dados
require_once('../../includes/error_handler.php'); // Inclui o manipulador de erros

header('Content-Type: application/json'); // Define o cabeçalho para resposta JSON

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 1. Verificação do Token CSRF ---
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $submitted_token !== $_SESSION['csrf_token']) {
        $response['message'] = "Erro de segurança: Requisição inválida (CSRF).";
        error_log("[CSRF ALERTA] Tentativa de CSRF detectada em excluir_endereco.php. IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode($response);
        exit();
    }

    // --- 2. Validação e Sanitização de Entradas ---
    $enderecoId = filter_input(INPUT_POST, 'end_codigo', FILTER_VALIDATE_INT);

    if (!$enderecoId) {
        $response['message'] = "ID do endereço inválido ou ausente.";
        echo json_encode($response);
        exit();
    }

    // --- 3. Lógica de Negócio e Interação com o Banco de Dados ---
    try {
        $pdo->beginTransaction();

        // Exclui o endereço
        $query = $pdo->prepare("DELETE FROM tbl_enderecos WHERE end_codigo = :id");
        $query->bindParam(':id', $enderecoId, PDO::PARAM_INT);
        $query->execute();

        if ($query->rowCount() > 0) {
            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Endereço excluído com sucesso!';
        } else {
            $pdo->rollBack();
            $response['message'] = 'Endereço não encontrado ou já excluído.';
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao excluir endereço (excluir_endereco.php): " . $e->getMessage());
        $response['message'] = "Erro no servidor ao excluir endereço. Por favor, tente novamente mais tarde.";
    }
} else {
    $response['message'] = "Método de requisição inválido. Apenas requisições POST são permitidas.";
}

echo json_encode($response);
exit();
?>
