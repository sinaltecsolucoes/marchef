<?php
// Arquivo: painel-admin/process/get_endereco_data.php
// Responsável por retornar os dados de um endereço específico para edição.

session_start(); // Inicia a sessão para verificar o token CSRF

require_once('../../includes/error_handler.php'); // Inclui o manipulador de erros
require_once('../../conexao.php'); // Inclui a conexão com o banco de dados
require_once('../../includes/helpers.php'); // Inclui as funções auxiliares (para validação)

header('Content-Type: application/json'); // Define o cabeçalho para resposta JSON

$response = ['success' => false, 'message' => 'Erro desconhecido.', 'data' => null];

// Verifica se a requisição é POST e se o ID foi fornecido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) { // ALTERADO DE GET PARA POST
    // ========================================================================
    // Verificação do Token CSRF
    // ========================================================================
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $submitted_token !== $_SESSION['csrf_token']) {
        $response['message'] = "Erro de segurança: Requisição inválida (CSRF).";
        error_log("[CSRF ALERTA] Tentativa de CSRF detectada em get_endereco_data.php. IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode($response);
        exit();
    }

    // --- 1. Validação e Sanitização de Entradas ---
    $endereco_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT); // ALTERADO DE INPUT_GET PARA INPUT_POST
    if (!$endereco_id) {
        $response['message'] = "ID do endereço inválido ou ausente.";
        echo json_encode($response);
        exit();
    }

    // --- 2. Lógica de Negócio e Interação com o Banco de Dados ---
    try {
        $query_get_endereco = $pdo->prepare("
            SELECT
                end_codigo,
                end_entidade_id,
                end_tipo_endereco,
                end_cep,
                end_logradouro,
                end_numero,
                end_complemento,
                end_bairro,
                end_cidade,
                end_uf
            FROM
                tbl_enderecos
            WHERE
                end_codigo = :endereco_id
        ");
        $query_get_endereco->bindParam(':endereco_id', $endereco_id, PDO::PARAM_INT);
        $query_get_endereco->execute();
        $endereco_data = $query_get_endereco->fetch(PDO::FETCH_ASSOC);

        if ($endereco_data) {
            $response['success'] = true;
            $response['message'] = 'Dados do endereço carregados com sucesso!';
            $response['data'] = $endereco_data;
        } else {
            $response['message'] = "Endereço não encontrado.";
        }

    } catch (PDOException $e) {
        error_log('Erro ao obter dados do endereço (get_endereco_data.php): ' . $e->getMessage());
        $response['message'] = 'Erro no servidor ao carregar dados do endereço. Por favor, tente novamente mais tarde.';
    }

} else {
    $response['message'] = "Requisição inválida. Apenas requisições POST com ID são permitidas."; // ALTERADO
}

echo json_encode($response);
exit();
?>