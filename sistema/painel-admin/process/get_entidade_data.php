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
        $query = $pdo->prepare(/*"
SELECT
ent.ent_codigo,
ent.ent_razao_social,
ent.ent_tipo_pessoa,
ent.ent_cpf,
ent.ent_cnpj,
ent.ent_tipo_entidade,
ent.ent_situacao,
end.end_codigo,
end.end_tipo_endereco,
end.end_cep,
end.end_logradouro,
end.end_numero,
end.end_complemento,
end.end_bairro,
end.end_cidade,
end.end_uf,
cli.cli_codigo AS is_cliente,    -- Retorna o cli_codigo se for cliente, NULL caso contrário
forn.forn_codigo AS is_fornecedor -- Retorna o forn_codigo se for fornecedor, NULL caso contrário
FROM tbl_entidades ent
LEFT JOIN tbl_enderecos end ON ent.ent_codigo = end.end_entidade_id AND end.end_tipo_endereco = 'Entrega'
LEFT JOIN tbl_clientes cli ON ent.ent_codigo = cli.cli_entidade_id
LEFT JOIN tbl_fornecedores forn ON ent.ent_codigo = forn.forn_entidade_id
WHERE ent.ent_codigo = :id
"*/

            "
            SELECT
                ent.ent_codigo,
                ent.ent_razao_social,
                ent.ent_nome_fantasia,         -- <-- CAMPO ADICIONADO
                ent.ent_tipo_pessoa,
                ent.ent_cpf,
                ent.ent_cnpj,
                ent.ent_inscricao_estadual,    -- <-- CAMPO ADICIONADO
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
            /*     $data = [
                     'ent_codigo' => $entidade['ent_codigo'],
                     'ent_razao_social' => $entidade['ent_razao_social'],
                     'ent_tipo_pessoa' => $entidade['ent_tipo_pessoa'],
                     'ent_cpf' => $entidade['ent_cpf'],
                     'ent_cnpj' => $entidade['ent_cnpj'],
                     'ent_tipo_entidade' => $entidade['ent_tipo_entidade'], // Mantém este campo para preencher o rádio
                     'ent_situacao' => $entidade['ent_situacao'],
                     'is_cliente' => !empty($entidade['is_cliente']), // True se cli_codigo não for NULL
                     'is_fornecedor' => !empty($entidade['is_fornecedor']), // True se forn_codigo não for NULL
                     'endereco' => null // Inicializa como null
                 ];*/
            $response['data'] = $entidade;

            // Se houver dados de endereço, preenche o sub-array 'endereco'
            /*    if ($entidade['end_codigo']) {
                    $data['endereco'] = [
                        'end_codigo' => $entidade['end_codigo'],
                        'end_tipo_endereco' => $entidade['end_tipo_endereco'],
                        'end_cep' => $entidade['end_cep'],
                        'end_logradouro' => $entidade['end_logradouro'],
                        'end_numero' => $entidade['end_numero'],
                        'end_complemento' => $entidade['end_complemento'],
                        'end_bairro' => $entidade['end_bairro'],
                        'end_cidade' => $entidade['end_cidade'],
                        'end_uf' => $entidade['end_uf']
                    ];
                }
                $response['data'] = $data;*/

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