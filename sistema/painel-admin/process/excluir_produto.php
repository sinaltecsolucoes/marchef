<?php
// Arquivo: painel-adm/process/excluir_produto.php
// Responsável por excluir um produto do banco de dados.

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// --- Validação do Token CSRF ---
// O token será enviado pelo JavaScript junto com a requisição
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Erro de validação de segurança (CSRF).']);
    exit;
}

// --- Validação do ID do Produto ---
if (empty($_POST['prod_codigo']) || !filter_var($_POST['prod_codigo'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'ID do produto ausente ou inválido.']);
    exit;
}

$prod_codigo = $_POST['prod_codigo'];

$pdo->beginTransaction();

try {
    // Prepara o comando SQL para deletar o produto
    $stmt = $pdo->prepare("DELETE FROM tbl_produtos WHERE prod_codigo = :prod_codigo");

    // Vincula o ID do produto
    $stmt->bindValue(':prod_codigo', $prod_codigo, PDO::PARAM_INT);

    // Executa o comando
    $stmt->execute();

    // Verifica se alguma linha foi realmente afetada
    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Produto excluído com sucesso!'
        ]);
    } else {
        // Se nenhuma linha foi afetada, o produto com aquele ID não existia.
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum produto encontrado com o ID fornecido.'
        ]);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    
    // Tratamento de erro de chave estrangeira (se um produto primário em uso for excluído)
    // O seu banco está configurado com ON DELETE SET NULL, então este erro não deve ocorrer,
    // mas é uma boa prática manter o tratamento.
    if ($e->getCode() == '23000') {
        $message = 'Este produto não pode ser excluído pois está sendo referenciado por outros registros.';
    } else {
        $message = 'Ocorreu um erro ao excluir o produto. Tente novamente.';
    }

    error_log("Erro em excluir_produto.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
}
?>