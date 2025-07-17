<?php
// Arquivo: painel-adm/process/buscar_produto.php
// Retorna os dados de um produto específico em formato JSON.

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

header('Content-Type: application/json');

// Verifica se o ID do produto foi enviado e se é um número inteiro válido
if (!isset($_POST['prod_codigo']) || !filter_var($_POST['prod_codigo'], FILTER_VALIDATE_INT)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de produto inválido ou não fornecido.'
    ]);
    exit;
}

$prod_codigo = $_POST['prod_codigo'];

try {
    // Prepara e executa a query para buscar o produto pelo seu código
    $stmt = $pdo->prepare("SELECT * FROM tbl_produtos WHERE prod_codigo = :prod_codigo");
    $stmt->bindParam(':prod_codigo', $prod_codigo, PDO::PARAM_INT);
    $stmt->execute();

    // Busca os dados do produto
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se o produto for encontrado, retorna sucesso e os dados
    if ($produto) {
        echo json_encode(['success' => true, 'data' => $produto]);
    } else {
        // Se nenhum produto for encontrado com o ID fornecido
        echo json_encode([
            'success' => false,
            'message' => 'Produto não encontrado.'
        ]);
    }

} catch (PDOException $e) {
    // Em caso de erro no banco de dados, loga o erro e retorna uma mensagem
    error_log("Erro em buscar_produto.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocorreu um erro ao buscar os dados do produto. Tente novamente.'
    ]);
}
?>