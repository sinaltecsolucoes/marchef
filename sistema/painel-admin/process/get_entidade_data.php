<?php
// Arquivo: painel-admin/process/get_entidade_data.php
// Retorna os dados de uma única entidade (cliente/fornecedor) com base no seu ID.

require_once('../../conexao.php'); // Inclui a conexão com o banco de dados
require_once('../../includes/error_handler.php'); // Inclui o manipulador de erros

header('Content-Type: application/json'); // Define o cabeçalho para resposta JSON

$response = ['success' => false, 'message' => 'Erro desconhecido.', 'data' => null];

// Verifica se a requisição é GET e se o ID foi fornecido
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $entidadeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$entidadeId) {
        $response['message'] = "ID da entidade inválido ou ausente.";
        echo json_encode($response);
        exit();
    }

    try {
        // Busca os dados da entidade, seu endereço principal (tipo 'Principal'),
        // e verifica a existência em tbl_clientes e tbl_fornecedores.
        $query = $pdo->prepare("
            SELECT
                ent.ent_codigo,
                ent.ent_razao_social,
                ent.ent_nome_fantasia,         
                ent.ent_tipo_pessoa,
                ent.ent_cpf,
                ent.ent_cnpj,
                ent.ent_inscricao_estadual,    
                ent.ent_codigo_interno,
                ent.ent_tipo_entidade,
                ent.ent_situacao,
                end.end_codigo,
                end.end_cep,
                end.end_logradouro,
                end.end_numero,
                end.end_complemento,
                end.end_bairro,
                end.end_cidade,
                end.end_uf
            FROM tbl_entidades ent
            LEFT JOIN (
                SELECT 
                    *,
                    ROW_NUMBER() OVER(PARTITION BY end_entidade_id ORDER BY 
                        CASE end_tipo_endereco
                            WHEN 'Comercial' THEN 1
                            WHEN 'Entrega' THEN 2
                            ELSE 3
                        END, end_codigo ASC) as rn
                FROM tbl_enderecos
            ) end ON ent.ent_codigo = end.end_entidade_id AND end.rn = 1
            WHERE ent.ent_codigo = :id
        "
        );
        $query->bindParam(':id', $entidadeId, PDO::PARAM_INT);
        $query->execute();
        $entidade = $query->fetch(PDO::FETCH_ASSOC);

        if ($entidade) {
            $response['success'] = true;
            $response['message'] = 'Dados da entidade carregados com sucesso.';

            // Organiza os dados para que o endereço seja um sub-array
            $response['data'] = $entidade;

        } else {
            $response['message'] = "Entidade não encontrada.";
        }

    } catch (PDOException $e) {
        error_log("Erro ao buscar dados da entidade (get_entidade_data.php): " . $e->getMessage());
        $response['message'] = "Erro no servidor ao carregar dados da entidade. Por favor, tente novamente mais tarde.";
    }
} else {
    $response['message'] = "Requisição inválida. O ID da entidade deve ser fornecido via GET.";
}

echo json_encode($response);
exit();
?>