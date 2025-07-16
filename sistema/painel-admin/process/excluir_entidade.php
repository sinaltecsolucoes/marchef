<?php
// Arquivo: painel-adm/process/excluir_entidade.php
// Responsável por excluir uma entidade e todos os seus dados relacionados.

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
$submitted_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    http_response_code(403); // Proibido
    echo json_encode(['success' => false, 'message' => 'Erro de segurança: Requisição inválida (CSRF).']);
    exit();
}

// Obtém o ID da entidade a ser excluída
$entidadeId = $_POST['ent_codigo'] ?? null;

// Valida se o ID foi fornecido e é um número inteiro
if (!$entidadeId || !filter_var($entidadeId, FILTER_VALIDATE_INT)) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['success' => false, 'message' => 'ID da entidade inválido ou não fornecido.']);
    exit();
}

// Inicia a transação para garantir a integridade dos dados
$pdo->beginTransaction();

try {
    // Passo 1: Excluir todos os endereços associados a esta entidade.
    $sqlDeleteEnderecos = "DELETE FROM tbl_enderecos WHERE end_entidade_id = :entidade_id";
    $stmtEnderecos = $pdo->prepare($sqlDeleteEnderecos);
    $stmtEnderecos->bindParam(':entidade_id', $entidadeId, PDO::PARAM_INT);
    $stmtEnderecos->execute();

    // Passo 2: Excluir o registro correspondente da tabela de clientes (se existir).
    $sqlDeleteCliente = "DELETE FROM tbl_clientes WHERE cli_entidade_id = :entidade_id";
    $stmtCliente = $pdo->prepare($sqlDeleteCliente);
    $stmtCliente->bindParam(':entidade_id', $entidadeId, PDO::PARAM_INT);
    $stmtCliente->execute();

    // Passo 3: Excluir o registro correspondente da tabela de fornecedores (se existir).
    $sqlDeleteFornecedor = "DELETE FROM tbl_fornecedores WHERE forn_entidade_id = :entidade_id";
    $stmtFornecedor = $pdo->prepare($sqlDeleteFornecedor);
    $stmtFornecedor->bindParam(':entidade_id', $entidadeId, PDO::PARAM_INT);
    $stmtFornecedor->execute();

    // Passo 4: Excluir a própria entidade da tabela principal (deve ser o último passo).
    $sqlDeleteEntidade = "DELETE FROM tbl_entidades WHERE ent_codigo = :entidade_id";
    $stmtEntidade = $pdo->prepare($sqlDeleteEntidade);
    $stmtEntidade->bindParam(':entidade_id', $entidadeId, PDO::PARAM_INT);
    $stmtEntidade->execute();

    // Verifica se a entidade foi realmente excluída (se a linha existia)
    if ($stmtEntidade->rowCount() > 0) {
        // Se tudo correu bem, confirma a transação
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Entidade excluída com sucesso!']);
    } else {
        // Se nenhuma linha foi afetada, a entidade não existia. Desfaz a transação.
        $pdo->rollBack();
        http_response_code(404); // Não encontrado
        echo json_encode(['success' => false, 'message' => 'Entidade não encontrada.']);
    }

} catch (PDOException $e) {
    // Em caso de qualquer erro no banco de dados, desfaz a transação
    $pdo->rollBack();
    
    // Loga o erro para depuração futura
    error_log("Erro ao excluir entidade: " . $e->getMessage());

    // Retorna uma mensagem de erro genérica para o usuário
    http_response_code(500); // Erro interno do servidor
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao tentar excluir a entidade.']);
}
?>
