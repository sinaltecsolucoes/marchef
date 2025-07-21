<?php
// Arquivo: painel-adm/process/editar_produto.php
// Responsável por receber dados do formulário e ATUALIZAR um produto existente.

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// --- Validação do Token CSRF ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Erro de validação de segurança (CSRF).']);
    exit;
}

// --- Validação de Campos Obrigatórios (ID e Descrição) ---

if ($_POST['prod_tipo_embalagem'] === 'SECUNDARIA' && empty(trim($_POST['prod_codigo_interno']))) {
    echo json_encode([
        'success' => false,
        'message' => 'Para produtos de embalagem secundária, o Código Interno é obrigatório.'
    ]);
    exit;
}

if (empty($_POST['prod_codigo']) || !filter_var($_POST['prod_codigo'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'ID do produto ausente ou inválido.']);
    exit;
}
if (empty($_POST['prod_descricao'])) {
    echo json_encode(['success' => false, 'message' => 'O campo Descrição é obrigatório.']);
    exit;
}

$prod_codigo = $_POST['prod_codigo'];

$validade_meses = !empty($_POST['prod_validade_meses']) ? (int) $_POST['prod_validade_meses'] : null;


// --- Início do Processamento da Edição ---
$pdo->beginTransaction();

try {
    // --- Preparação da Query SQL de UPDATE ---
    $sql = "UPDATE tbl_produtos SET
                prod_codigo_interno = :prod_codigo_interno,
                prod_descricao = :prod_descricao,
                prod_tipo = :prod_tipo,
                prod_subtipo = :prod_subtipo,
                prod_classificacao = :prod_classificacao,
                prod_especie = :prod_especie,
                prod_origem = :prod_origem,
                prod_conservacao = :prod_conservacao,
                prod_congelamento = :prod_congelamento,
                prod_fator_producao = :prod_fator_producao,
                prod_tipo_embalagem = :prod_tipo_embalagem,
                prod_peso_embalagem = :prod_peso_embalagem,
                prod_total_pecas = :prod_total_pecas,
                prod_validade_meses = :validade_meses,
                prod_primario_id = :prod_primario_id,
                prod_ean13 = :prod_ean13,
                prod_dun14 = :prod_dun14
            WHERE
                prod_codigo = :prod_codigo";

    $stmt = $pdo->prepare($sql);

    // --- Lógica para Tratar Campos (similar ao cadastro) ---
    $tipo_embalagem = $_POST['prod_tipo_embalagem'];

    $peso_embalagem = ($tipo_embalagem === 'SECUNDARIA')
        ? (!empty($_POST['prod_peso_embalagem_secundaria']) ? str_replace(',', '.', $_POST['prod_peso_embalagem_secundaria']) : null)
        : (!empty($_POST['prod_peso_embalagem']) ? str_replace(',', '.', $_POST['prod_peso_embalagem']) : null);

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
    $stmt->bindValue(':prod_fator_producao', $prod_fator_producao, PDO::PARAM_STR);
    $stmt->bindValue(':prod_tipo_embalagem', $tipo_embalagem, PDO::PARAM_STR);
    $stmt->bindValue(':prod_peso_embalagem', $peso_embalagem, PDO::PARAM_STR);
    $stmt->bindValue(':prod_total_pecas', !empty($_POST['prod_total_pecas']) ? $_POST['prod_total_pecas'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':validade_meses', $validade_meses, $validade_meses === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':prod_primario_id', $prod_primario_id, PDO::PARAM_INT);
    $stmt->bindValue(':prod_ean13', !empty($_POST['prod_ean13']) ? $_POST['prod_ean13'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':prod_dun14', !empty($_POST['prod_dun14']) ? $_POST['prod_dun14'] : null, PDO::PARAM_STR);

    // Vincula o ID do produto para a cláusula WHERE
    $stmt->bindValue(':prod_codigo', $prod_codigo, PDO::PARAM_INT);

    $stmt->execute();
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Produto atualizado com sucesso!'
    ]);

    // } catch (PDOException $e) {
//     $pdo->rollBack();

    //     if ($e->getCode() == '23000') {
//         if (strpos(strtolower($e->getMessage()), 'prod_codigo_interno') !== false) {
//             $message = 'Erro: O Código Interno informado já pertence a outro produto.';
//         } else {
//             $message = 'Erro de violação de dados. Verifique os campos.';
//         }
//         echo json_encode(['success' => false, 'message' => $message]);
//     } else {
//         error_log("Erro em editar_produto.php: " . $e->getMessage());
//         echo json_encode([
//             'success' => false,
//             'message' => 'Ocorreu um erro ao atualizar o produto. Tente novamente.'
//         ]);
//     }
//}

} catch (PDOException $e) {
    // Desfaz a transação em caso de erro
    $pdo->rollBack();

    // Verifica se o erro é de chave única duplicada (código 23000)
    if ($e->getCode() == '23000') {
        // Verifica se a mensagem de erro contém o nome do nosso novo índice
        if (strpos($e->getMessage(), 'idx_unique_codigo_secundario') !== false) {
            $message = 'Erro: O Código Interno informado já está em uso por outro produto de embalagem secundária.';
        } else {
            // Mensagem genérica para outras violações de chave
            $message = 'Erro: Violação de dados. Um campo único já existe no banco de dados.';
        }
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        // Para todos os outros erros de banco de dados
        error_log("Erro no script: " . $e->getMessage()); // Loga o erro real
        echo json_encode([
            'success' => false,
            'message' => 'Ocorreu um erro inesperado ao salvar o produto. Tente novamente.'
        ]);
    }
}

?>