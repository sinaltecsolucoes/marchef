<?php
// Arquivo: painel-adm/process/excluir_endereco.php
// Responsável por excluir um registro de endereço.

session_start();

// Requer os arquivos de conexão e o manipulador de erros
require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

// Verifica se o método da requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

// --- Validação do Token CSRF ---
// O token é enviado pelo AJAX no corpo da requisição
$submitted_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    http_response_code(403); // Proibido
    echo json_encode(['success' => false, 'message' => 'Erro de segurança: Requisição inválida (CSRF).']);
    exit();
}

// Obtém o ID do endereço a ser excluído
$enderecoId = $_POST['end_codigo'] ?? null;

// Valida se o ID foi fornecido e é um número inteiro
if (!$enderecoId || !filter_var($enderecoId, FILTER_VALIDATE_INT)) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['success' => false, 'message' => 'ID do endereço inválido ou não fornecido.']);
    exit();
}

try {
    // Prepara a query para deletar o endereço pelo seu ID
    $sql = "DELETE FROM tbl_enderecos WHERE end_codigo = :endereco_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':endereco_id', $enderecoId, PDO::PARAM_INT);
    $stmt->execute();

    // Verifica se a linha foi realmente deletada
    if ($stmt->rowCount() > 0) {
        // Retorna sucesso
        echo json_encode(['success' => true, 'message' => 'Endereço excluído com sucesso!']);
    } else {
        // Se nenhuma linha foi afetada, o endereço não existia
        http_response_code(404); // Não encontrado
        echo json_encode(['success' => false, 'message' => 'Endereço não encontrado para exclusão.']);
    }

} catch (PDOException $e) {
    // Em caso de erro no banco de dados, loga o erro e retorna uma mensagem genérica
    error_log("Erro ao excluir endereço: " . $e->getMessage());
    http_response_code(500); // Erro interno do servidor
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao tentar excluir o endereço.']);
}
?>
