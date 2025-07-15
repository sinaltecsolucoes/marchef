<?php
// Arquivo: painel-admin/process/cadastrar_entidade.php
// Responsável por cadastrar uma nova entidade (cliente/fornecedor) e seus dados específicos.

session_start(); // Inicia a sessão para acessar o token CSRF e o ID do usuário logado

require_once('../../includes/error_handler.php'); // Inclui o manipulador de erros
require_once('../../conexao.php'); // Inclui a conexão com o banco de dados
require_once('../../includes/helpers.php'); // Inclui funções auxiliares (para validação)

header('Content-Type: application/json'); // Define o cabeçalho para resposta JSON

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 1. Verificação do Token CSRF ---
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $submitted_token !== $_SESSION['csrf_token']) {
        $response['message'] = "Erro de segurança: Requisição inválida (CSRF).";
        error_log("[CSRF ALERTA] Tentativa de CSRF detectada em cadastrar_entidade.php. IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode($response);
        exit();
    }

    // --- 2. Validação e Sanitização de Entradas da Entidade (tbl_entidades) ---
    $razao_social_raw = filter_input(INPUT_POST, 'ent_razao_social', FILTER_SANITIZE_STRING);
    $val_razao_social = validate_string($razao_social_raw, 3, 255, '/^[a-zA-Z0-9\sÀ-ú.,-]+$/u');
    if (!$val_razao_social['valid']) {
        $response['message'] = "Razão Social/Nome: " . $val_razao_social['message'];
        echo json_encode($response);
        exit();
    }
    $ent_razao_social = $val_razao_social['value'];

    $tipo_pessoa_raw = filter_input(INPUT_POST, 'ent_tipo_pessoa', FILTER_SANITIZE_STRING);
    $allowed_tipos_pessoa = ['F', 'J'];
    $val_tipo_pessoa = validate_selection($tipo_pessoa_raw, $allowed_tipos_pessoa);
    if (!$val_tipo_pessoa['valid']) {
        $response['message'] = "Tipo de Pessoa: " . $val_tipo_pessoa['message'];
        echo json_encode($response);
        exit();
    }
    $ent_tipo_pessoa = $val_tipo_pessoa['value'];

    $cpf_cnpj_raw = filter_input(INPUT_POST, 'ent_cpf_cnpj', FILTER_SANITIZE_STRING);
    $ent_cpf = null;
    $ent_cnpj = null;

    if ($ent_tipo_pessoa === 'F') {
        // Validação de CPF
        $cpf_cnpj_limpo = preg_replace('/[^0-9]/', '', $cpf_cnpj_raw);
        if (!preg_match('/^\d{11}$/', $cpf_cnpj_limpo)) {
            $response['message'] = "CPF: Formato inválido. Deve conter 11 dígitos.";
            echo json_encode($response);
            exit();
        }
        $ent_cpf = $cpf_cnpj_limpo;
    } else { // Pessoa Jurídica
        // Validação de CNPJ
        $cpf_cnpj_limpo = preg_replace('/[^0-9]/', '', $cpf_cnpj_raw);
        if (!preg_match('/^\d{14}$/', $cpf_cnpj_limpo)) {
            $response['message'] = "CNPJ: Formato inválido. Deve conter 14 dígitos.";
            echo json_encode($response);
            exit();
        }
        $ent_cnpj = $cpf_cnpj_limpo;
    }

    $tipo_entidade_raw = filter_input(INPUT_POST, 'ent_tipo_entidade', FILTER_SANITIZE_STRING);
    $allowed_tipos_entidade = ['Cliente', 'Fornecedor', 'Cliente e Fornecedor'];
    $val_tipo_entidade = validate_selection($tipo_entidade_raw, $allowed_tipos_entidade);
    if (!$val_tipo_entidade['valid']) {
        $response['message'] = "Tipo de Entidade: " . $val_tipo_entidade['message'];
        echo json_encode($response);
        exit();
    }
    $ent_tipo_entidade = $val_tipo_entidade['value'];

    // Situação (checkbox)
    $situacao_form = filter_input(INPUT_POST, 'ent_situacao', FILTER_SANITIZE_STRING);
    $ent_situacao = ($situacao_form === 'A') ? 'A' : 'I'; // 'A' para Ativo, 'I' para Inativo

    $usuario_cadastro_id = $_SESSION['codUsuario'] ?? null; // Pega o ID do usuário logado
    if (!$usuario_cadastro_id) {
        $response['message'] = "Erro: Usuário não autenticado para realizar o cadastro.";
        echo json_encode($response);
        exit();
    }

    // --- 3. Validação e Sanitização de Entradas do Endereço (se fornecidas) ---
    // Todos os campos de endereço são opcionais para o cadastro inicial, mas se um for preenchido,
    // os campos essenciais para um endereço válido devem ser verificados.
    $end_cep_raw = filter_input(INPUT_POST, 'end_cep', FILTER_SANITIZE_STRING);
    $end_logradouro_raw = filter_input(INPUT_POST, 'end_logradouro', FILTER_SANITIZE_STRING);
    $end_numero_raw = filter_input(INPUT_POST, 'end_numero', FILTER_SANITIZE_STRING);
    $end_complemento_raw = filter_input(INPUT_POST, 'end_complemento', FILTER_SANITIZE_STRING);
    $end_bairro_raw = filter_input(INPUT_POST, 'end_bairro', FILTER_SANITIZE_STRING);
    $end_cidade_raw = filter_input(INPUT_POST, 'end_cidade', FILTER_SANITIZE_STRING);
    $end_uf_raw = filter_input(INPUT_POST, 'end_uf', FILTER_SANITIZE_STRING);

    $has_address_data = !empty($end_cep_raw) || !empty($end_logradouro_raw) || !empty($end_numero_raw) ||
        !empty($end_complemento_raw) || !empty($end_bairro_raw) || !empty($end_cidade_raw) || !empty($end_uf_raw);

    $end_cep = $end_logradouro = $end_numero = $end_complemento = $end_bairro = $end_cidade = $end_uf = null;

    if ($has_address_data) {
        // Se algum campo de endereço foi preenchido, valida os essenciais
        $end_cep = preg_replace('/[^0-9]/', '', $end_cep_raw);
        if (empty($end_cep) || !preg_match('/^\d{8}$/', $end_cep)) {
            $response['message'] = "CEP: Formato inválido ou vazio se outros campos de endereço foram preenchidos.";
            echo json_encode($response);
            exit();
        }

        $val_logradouro = validate_string($end_logradouro_raw, 3, 255);
        if (!$val_logradouro['valid']) {
            $response['message'] = "Logradouro: " . $val_logradouro['message'];
            echo json_encode($response);
            exit();
        }
        $end_logradouro = $val_logradouro['value'];

        $val_numero = validate_string($end_numero_raw, 1, 20, '/^[a-zA-Z0-9\s-]+$/'); // Permite números, letras e hífen
        if (!$val_numero['valid']) {
            $response['message'] = "Número: " . $val_numero['message'];
            echo json_encode($response);
            exit();
        }
        $end_numero = $val_numero['value'];

        $val_bairro = validate_string($end_bairro_raw, 3, 100);
        if (!$val_bairro['valid']) {
            $response['message'] = "Bairro: " . $val_bairro['message'];
            echo json_encode($response);
            exit();
        }
        $end_bairro = $val_bairro['value'];

        $val_cidade = validate_string($end_cidade_raw, 3, 100);
        if (!$val_cidade['valid']) {
            $response['message'] = "Cidade: " . $val_cidade['message'];
            echo json_encode($response);
            exit();
        }
        $end_cidade = $val_cidade['value'];

        $allowed_ufs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
        $val_uf = validate_selection($end_uf_raw, $allowed_ufs);
        if (!$val_uf['valid']) {
            $response['message'] = "UF: " . $val_uf['message'];
            echo json_encode($response);
            exit();
        }
        $end_uf = $val_uf['value'];

        // Complemento é opcional, apenas sanitiza
        $end_complemento = filter_var($end_complemento_raw, FILTER_SANITIZE_STRING) ?: null;
    }


    // --- 4. Lógica de Negócio e Interação com o Banco de Dados ---
    try {
        $pdo->beginTransaction();

        // Verifica se CPF/CNPJ já existe (para evitar duplicatas)
        $sql_check_cpf_cnpj = "SELECT COUNT(*) FROM tbl_entidades WHERE ";
        if ($ent_tipo_pessoa === 'F') {
            $sql_check_cpf_cnpj .= "ent_cpf = :cpf_cnpj_value";
        } else {
            $sql_check_cpf_cnpj .= "ent_cnpj = :cpf_cnpj_value";
        }
        $query_check = $pdo->prepare($sql_check_cpf_cnpj);
        $query_check->bindValue(':cpf_cnpj_value', $cpf_cnpj_limpo);
        $query_check->execute();
        if ($query_check->fetchColumn() > 0) {
            $pdo->rollBack();
            $response['message'] = "Erro: CPF/CNPJ já cadastrado para outra entidade.";
            echo json_encode($response);
            exit();
        }

        // Insere a nova entidade na tbl_entidades
        $query_entidade = $pdo->prepare("
            INSERT INTO tbl_entidades (
                ent_razao_social, ent_tipo_pessoa, ent_cpf, ent_cnpj, 
                ent_tipo_entidade, ent_situacao, ent_data_cadastro, ent_usuario_cadastro_id
            ) VALUES (
                :razao_social, :tipo_pessoa, :cpf, :cnpj, 
                :tipo_entidade, :situacao, NOW(), :usuario_cadastro_id
            )
        ");
        $query_entidade->bindValue(':razao_social', $ent_razao_social);
        $query_entidade->bindValue(':tipo_pessoa', $ent_tipo_pessoa);
        $query_entidade->bindValue(':cpf', $ent_cpf);
        $query_entidade->bindValue(':cnpj', $ent_cnpj);
        $query_entidade->bindValue(':tipo_entidade', $ent_tipo_entidade);
        $query_entidade->bindValue(':situacao', $ent_situacao);
        $query_entidade->bindValue(':usuario_cadastro_id', $usuario_cadastro_id, PDO::PARAM_INT);
        $query_entidade->execute();

        $entidade_id = $pdo->lastInsertId(); // Pega o ID da entidade recém-inserida

        // --- Insere na tbl_clientes se a entidade for um Cliente ou Cliente e Fornecedor ---
        if ($ent_tipo_entidade === 'Cliente' || $ent_tipo_entidade === 'Cliente e Fornecedor') {
            $query_cliente = $pdo->prepare("
                INSERT INTO tbl_clientes (
                    cli_entidade_id, cli_status_cliente, cli_limite_credito, 
                    cli_data_cadastro, cli_usuario_cadastro_id
                ) VALUES (
                    :entidade_id, 'Ativo', 0.00, 
                    NOW(), :usuario_cadastro_id
                )
            ");
            $query_cliente->bindValue(':entidade_id', $entidade_id, PDO::PARAM_INT);
            $query_cliente->bindValue(':usuario_cadastro_id', $usuario_cadastro_id, PDO::PARAM_INT);
            $query_cliente->execute();
        }

        // --- NOVO: Insere na tbl_fornecedores se a entidade for um Fornecedor ou Cliente e Fornecedor ---
        if ($ent_tipo_entidade === 'Fornecedor' || $ent_tipo_entidade === 'Cliente e Fornecedor') {
            $query_fornecedor = $pdo->prepare("
                INSERT INTO tbl_fornecedores (
                    forn_entidade_id, forn_data_cadastro, forn_usuario_cadastro_id
                ) VALUES (
                    :entidade_id, NOW(), :usuario_cadastro_id
                )
            ");
            $query_fornecedor->bindValue(':entidade_id', $entidade_id, PDO::PARAM_INT);
            $query_fornecedor->bindValue(':usuario_cadastro_id', $usuario_cadastro_id, PDO::PARAM_INT);
            $query_fornecedor->execute();
        }


        // Se houver dados de endereço, insere o endereço principal
        if ($has_address_data) {
            $query_endereco = $pdo->prepare("
                INSERT INTO tbl_enderecos (
                    end_entidade_id, end_tipo_endereco, end_cep, end_logradouro, 
                    end_numero, end_complemento, end_bairro, end_cidade, end_uf, 
                    end_data_cadastro, end_usuario_cadastro_id
                ) VALUES (
                    :entidade_id, 'Entrega', :cep, :logradouro, 
                    :numero, :complemento, :bairro, :cidade, :uf, 
                    NOW(), :usuario_cadastro_id
                )
            ");
            $query_endereco->bindValue(':entidade_id', $entidade_id, PDO::PARAM_INT);
            $query_endereco->bindValue(':tipo_endereco', 'Entrega'); // Endereço principal como 'Entrega'
            $query_endereco->bindValue(':cep', $end_cep);
            $query_endereco->bindValue(':logradouro', $end_logradouro);
            $query_endereco->bindValue(':numero', $end_numero);
            $query_endereco->bindValue(':complemento', $end_complemento);
            $query_endereco->bindValue(':bairro', $end_bairro);
            $query_endereco->bindValue(':cidade', $end_cidade);
            $query_endereco->bindValue(':uf', $end_uf);
            $query_endereco->bindValue(':usuario_cadastro_id', $usuario_cadastro_id, PDO::PARAM_INT);
            $query_endereco->execute();
        }

        $pdo->commit(); // Confirma a transação

        $response['success'] = true;
        $response['message'] = 'Entidade cadastrada com sucesso!';
        $response['ent_codigo'] = $entidade_id; // Retorna o ID da nova entidade

    } catch (PDOException $e) {
        $pdo->rollBack(); // Reverte a transação em caso de erro
        error_log('Erro ao cadastrar entidade (cadastrar_entidade.php): ' . $e->getMessage());
        $response['message'] = 'Erro no servidor ao cadastrar entidade. Por favor, tente novamente mais tarde.';
    }

} else {
    $response['message'] = "Método de requisição inválido. Apenas requisições POST são permitidas.";
}

echo json_encode($response);
exit();
?>