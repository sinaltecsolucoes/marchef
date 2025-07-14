<?php
// Arquivo: painel-admin/process/listar_entidades.php
// Retorna os dados das entidades (clientes ou fornecedores) em formato JSON para o DataTables.

require_once('../../conexao.php'); // Caminho atualizado
require_once('../../includes/error_handler.php'); // Caminho atualizado

header('Content-Type: application/json');

$data = []; // Array para armazenar os dados das entidades

try {
    // Pega o tipo de entidade da requisição GET (ex: 'Cliente', 'Fornecedor')
    $tipo_entidade = filter_input(INPUT_GET, 'tipo_entidade', FILTER_SANITIZE_STRING);

    // Valida o tipo de entidade para evitar SQL Injection e garantir que é um tipo esperado
    $allowed_types = ['Cliente', 'Fornecedor', 'Cliente e Fornecedor'];
    if (!in_array($tipo_entidade, $allowed_types)) {
        // Se o tipo não for válido, retorna um erro ou um array vazio
        echo json_encode(['data' => [], 'error' => 'Tipo de entidade inválido.']);
        exit();
    }

    // Consulta para buscar entidades e seu endereço principal associado
    // Usamos LEFT JOIN para incluir entidades que podem não ter um endereço cadastrado ainda.
    // LIMITAMOS PARA APENAS UM ENDEREÇO POR ENTIDADE (o primeiro encontrado, se houver)
    $query = $pdo->prepare("
        SELECT
            e.ent_codigo,
            e.ent_razao_social,
            e.ent_tipo_pessoa,
            e.ent_cpf,
            e.ent_cnpj,
            e.ent_tipo_entidade,
            e.ent_situacao,
            e.ent_data_cadastro,
            e.ent_usuario_cadastro_id,
            a.end_logradouro,
            a.end_numero,
            a.end_bairro,
            a.end_cidade,
            a.end_uf,
            a.end_complemento
        FROM
            tbl_entidades AS e
        LEFT JOIN
            tbl_enderecos AS a ON e.ent_codigo = a.end_entidade_id AND a.end_tipo_endereco = 'Entrega'
        WHERE
            e.ent_tipo_entidade = :tipo_entidade
        ORDER BY
            e.ent_razao_social ASC
    ");
    $query->bindParam(':tipo_entidade', $tipo_entidade, PDO::PARAM_STR);
    $query->execute();
    $entidades = $query->fetchAll(PDO::FETCH_ASSOC);

    foreach ($entidades as $entidade) {
        $data[] = [
            "ent_situacao"        => $entidade['ent_situacao'],
            "ent_tipo_entidade"   => $entidade['ent_tipo_entidade'],
            "ent_razao_social"    => $entidade['ent_razao_social'],
            "ent_tipo_pessoa"     => $entidade['ent_tipo_pessoa'],
            "ent_cpf"             => $entidade['ent_cpf'],
            "ent_cnpj"            => $entidade['ent_cnpj'],
            "end_logradouro"      => $entidade['end_logradouro'], // Para exibir na tabela
            "end_numero"          => $entidade['end_numero'],
            "end_bairro"          => $entidade['end_bairro'],
            "end_cidade"          => $entidade['end_cidade'],
            "end_uf"              => $entidade['end_uf'],
            "ent_codigo"          => $entidade['ent_codigo']
        ];
    }

} catch (PDOException $e) {
    error_log("Erro ao listar entidades para DataTables: " . $e->getMessage());
    echo json_encode(['data' => [], 'error' => 'Falha ao carregar dados das entidades.']);
    exit();
}

echo json_encode(['data' => $data]);
exit();
?>
