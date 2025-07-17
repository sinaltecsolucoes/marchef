<?php
// Arquivo: painel-adm/process/listar_produtos_primarios.php
// Este script busca e retorna produtos de embalagem primária para serem usados em formulários.

// Inclui arquivos essenciais
require_once('../../conexao.php');
require_once('../../includes/error_handler.php');

// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

try {
    // Prepara a consulta SQL para selecionar produtos primários e ativos.
    // Selecionamos todos os campos, pois o JS pode usar vários deles para preencher
    // automaticamente o formulário do produto secundário.
    $query = $pdo->prepare("
        SELECT 
            * FROM 
            tbl_produtos 
        WHERE 
            prod_tipo_embalagem = :tipo_embalagem 
        AND 
            prod_situacao = 'A' 
        ORDER BY 
            prod_descricao ASC
    ");

    // Define o valor do parâmetro
    $tipo_emb = 'PRIMARIA';
    $query->bindParam(':tipo_embalagem', $tipo_emb, PDO::PARAM_STR);

    // Executa a consulta
    $query->execute();

    // Busca todos os resultados como um array associativo
    $produtos = $query->fetchAll(PDO::FETCH_ASSOC);

    // Retorna uma resposta de sucesso com os dados dos produtos
    echo json_encode(['success' => true, 'data' => $produtos]);

} catch (PDOException $e) {
    // Em caso de erro no banco de dados, loga o erro e retorna uma mensagem genérica.
    error_log("Erro no listar_produtos_primarios.php: " . $e->getMessage());

    // Envia uma resposta de erro em JSON
    echo json_encode([
        'success' => false,
        'message' => 'Ocorreu um erro ao buscar os produtos primários. Por favor, tente novamente.'
    ]);
}
?>