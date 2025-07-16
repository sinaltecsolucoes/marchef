<?php
// Arquivo: painel-adm/process/get_endereco_data.php
// Responsável por buscar os dados de um único endereço para preencher o formulário de edição.

// Requer os arquivos de conexão e o manipulador de erros
require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

// Verifica se o método da requisição é GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Método não permitido
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

// Obtém o ID do endereço da query string (?id=...)
$enderecoId = $_GET['id'] ?? null;

// Valida se o ID foi fornecido e é um número inteiro
if (!$enderecoId || !filter_var($enderecoId, FILTER_VALIDATE_INT)) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['success' => false, 'message' => 'ID do endereço inválido ou não fornecido.']);
    exit();
}

try {
    // Prepara a query para buscar o endereço pelo seu ID
    $sql = "SELECT * FROM tbl_enderecos WHERE end_codigo = :endereco_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':endereco_id', $enderecoId, PDO::PARAM_INT);
    $stmt->execute();

    // Busca o resultado como um array associativo
    $endereco = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifica se um endereço foi encontrado
    if ($endereco) {
        // Retorna sucesso e os dados do endereço
        echo json_encode(['success' => true, 'data' => $endereco]);
    } else {
        // Se nenhum endereço foi encontrado com o ID fornecido
        http_response_code(404); // Não encontrado
        echo json_encode(['success' => false, 'message' => 'Endereço não encontrado.']);
    }

} catch (PDOException $e) {
    // Em caso de erro no banco de dados, loga o erro e retorna uma mensagem genérica
    error_log("Erro ao buscar dados do endereço: " . $e->getMessage());
    http_response_code(500); // Erro interno do servidor
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao buscar os dados do endereço.']);
}
?>
