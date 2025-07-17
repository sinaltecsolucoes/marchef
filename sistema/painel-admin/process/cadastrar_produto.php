<?php
// Arquivo: painel-adm/process/cadastrar_produto.php
// Responsável por receber dados do formulário e cadastrar um novo produto.

// Inclui arquivos essenciais
require_once('../../conexao.php'); 
require_once('../../includes/error_handler.php');

// Inicia a sessão para validar o token CSRF
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

// --- Validação do Token CSRF ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    // Se o token não for válido, encerra a execução e retorna um erro.
    echo json_encode([
        'success' => false,
        'message' => 'Erro de validação de segurança (CSRF). Por favor, recarregue a página e tente novamente.'
    ]);
    exit;
}

// --- Validação de Campos Obrigatórios ---
if (empty($_POST['prod_descricao']) || empty($_POST['prod_tipo_embalagem'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Descrição e Tipo de Embalagem são campos obrigatórios.'
    ]);
    exit;
}


// --- Início do Processamento do Cadastro ---
$pdo->beginTransaction();

try {
    // --- Preparação da Query SQL ---
    $sql = "INSERT INTO tbl_produtos (
                prod_codigo_interno, prod_descricao, prod_situacao, prod_tipo, prod_subtipo,
                prod_classificacao, prod_especie, prod_origem, prod_conservacao, prod_congelamento,
                prod_fator_producao, prod_tipo_embalagem, prod_peso_embalagem, prod_total_pecas,
                prod_primario_id, prod_ean13, prod_dun14
            ) VALUES (
                :prod_codigo_interno, :prod_descricao, 'A', :prod_tipo, :prod_subtipo,
                :prod_classificacao, :prod_especie, :prod_origem, :prod_conservacao, :prod_congelamento,
                :prod_fator_producao, :prod_tipo_embalagem, :prod_peso_embalagem, :prod_total_pecas,
                :prod_primario_id, :prod_ean13, :prod_dun14
            )";
    
    $stmt = $pdo->prepare($sql);

    // --- Lógica para Tratar Campos Vazios e Tipos de Embalagem ---
    $tipo_embalagem = $_POST['prod_tipo_embalagem'];
    
    // Define o peso da embalagem com base no tipo
    $peso_embalagem = ($tipo_embalagem === 'SECUNDARIA') 
        ? (!empty($_POST['peso_embalagem_secundaria']) ? str_replace(',', '.', $_POST['peso_embalagem_secundaria']) : null)
        : (!empty($_POST['prod_peso_embalagem']) ? str_replace(',', '.', $_POST['prod_peso_embalagem']) : null);

    // Converte strings vazias em NULL para campos que permitem nulos
    $prod_fator_producao = !empty($_POST['prod_fator_producao']) ? str_replace(',', '.', $_POST['prod_fator_producao']) : null;
    $prod_primario_id = !empty($_POST['prod_primario_id']) ? $_POST['prod_primario_id'] : null;

    // --- Vinculação dos Parâmetros (Binding) ---
    $stmt->bindValue(':prod_codigo_interno', !empty($_POST['prod_codigo_interno']) ? $_POST['prod_codigo_interno'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':prod_descricao', $_POST['prod_descricao'], PDO::PARAM_STR);
    $stmt->bindValue(':prod_tipo', $_POST['prod_tipo'], PDO::PARAM_STR);
    $stmt->bindValue(':prod_subtipo', !empty($_POST['prod_subtipo']) ? $_POST['prod_subtipo'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':prod_classificacao', !empty($_POST['prod_classificacao']) ? $_POST['prod_classificacao'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':prod_especie', !empty($_POST['prod_especie']) ? $_POST['prod_especie'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':prod_origem', $_POST['prod_origem'], PDO::PARAM_STR);
    $stmt->bindValue(':prod_conservacao', $_POST['prod_conservacao'], PDO::PARAM_STR);
    $stmt->bindValue(':prod_congelamento', $_POST['prod_congelamento'], PDO::PARAM_STR);
    $stmt->bindValue(':prod_fator_producao', $prod_fator_producao, PDO::PARAM_STR); // PDO::PARAM_STR funciona para decimais
    $stmt->bindValue(':prod_tipo_embalagem', $tipo_embalagem, PDO::PARAM_STR);
    $stmt->bindValue(':prod_peso_embalagem', $peso_embalagem, PDO::PARAM_STR);
    $stmt->bindValue(':prod_total_pecas', !empty($_POST['prod_total_pecas']) ? $_POST['prod_total_pecas'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':prod_primario_id', $prod_primario_id, PDO::PARAM_INT);
    $stmt->bindValue(':prod_ean13', !empty($_POST['prod_ean13']) ? $_POST['prod_ean13'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':prod_dun14', !empty($_POST['prod_dun14']) ? $_POST['prod_dun14'] : null, PDO::PARAM_STR);
    
    // Executa a query
    $stmt->execute();

    // Confirma a transação
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Produto cadastrado com sucesso!'
    ]);

} catch (PDOException $e) {
    // Desfaz a transação em caso de erro
    $pdo->rollBack();

    // Verifica se o erro é de chave única duplicada (código interno)
    if ($e->getCode() == '23000') { // Código de erro SQL para violação de integridade
        // A mensagem de erro pode variar, mas geralmente contém o nome da chave
        if (strpos(strtolower($e->getMessage()), 'prod_codigo_interno') !== false) {
            $message = 'Erro: O Código Interno informado já existe. Por favor, utilize outro.';
        } else {
            $message = 'Erro de violação de dados. Verifique os campos.';
        }
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        // Para outros erros de banco de dados
        error_log("Erro em cadastrar_produto.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Ocorreu um erro ao cadastrar o produto. Tente novamente.'
        ]);
    }
}
?>