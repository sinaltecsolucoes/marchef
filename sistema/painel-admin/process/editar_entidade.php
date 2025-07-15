<?php
// Arquivo: painel-admin/process/editar_entidade.php
// Responsável por editar os dados de uma entidade (cliente/fornecedor) e seu endereço principal,
// além de gerenciar seus papéis em tbl_clientes e tbl_fornecedores.

session_start(); // Inicia a sessão para acessar o ID do usuário logado

require_once('../../includes/error_handler.php'); // Inclui o manipulador de erros
require_once('../../conexao.php'); // Inclui a conexão com o banco de dados
require_once('../../includes/helpers.php'); // Inclui as funções auxiliares (para validação)

header('Content-Type: application/json'); // Define o cabeçalho para resposta JSON

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ========================================================================
    // Verificação do Token CSRF
    // ========================================================================
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $submitted_token !== $_SESSION['csrf_token']) {
        $response['message'] = "Erro de segurança: Requisição inválida (CSRF).";
        error_log("[CSRF ALERTA] Tentativa de CSRF detectada em editar_entidade.php. IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode($response);
        exit();
    }

    // Verifica se o usuário está logado
    if (!isset($_SESSION['codUsuario']) || empty($_SESSION['codUsuario'])) {
        $response['message'] = "Erro: Usuário não autenticado.";
        echo json_encode($response);
        exit();
    }
    $usuario_logado_id = $_SESSION['codUsuario'];

    // --- 1. Validação e Sanitização de Entradas ---
    $ent_codigo = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);
    if (!$ent_codigo) {
        $response['message'] = "ID da entidade inválido ou ausente.";
        echo json_encode($response);
        exit();
    }

    $razao_social_raw = filter_input(INPUT_POST, 'ent_razao_social', FILTER_SANITIZE_STRING);
    $val_razao_social = validate_string($razao_social_raw, 3, 255, '/^[a-zA-Z0-9\s.,-]+$/u');
    if (!$val_razao_social['valid']) {
        $response['message'] = "Razão Social: " . $val_razao_social['message'];
        echo json_encode($response);
        exit();
    }
    $ent_razao_social = $val_razao_social['value'];

    $tipo_pessoa_raw = filter_input(INPUT_POST, 'ent_tipo_pessoa', FILTER_SANITIZE_STRING);
    $val_tipo_pessoa = validate_selection($tipo_pessoa_raw, ['F', 'J']);
    if (!$val_tipo_pessoa['valid']) {
        $response['message'] = "Tipo de Pessoa: " . $val_tipo_pessoa['message'];
        echo json_encode($response);
        exit();
    }
    $ent_tipo_pessoa = $val_tipo_pessoa['value'];

    $cpf_cnpj_raw = filter_input(INPUT_POST, 'ent_cpf_cnpj', FILTER_SANITIZE_STRING);
    $cpf_cnpj_limpo = preg_replace('/\D/', '', $cpf_cnpj_raw); // Remove não-dígitos

    $ent_cpf = null;
    $ent_cnpj = null;

    if ($ent_tipo_pessoa === 'F') {
        if (mb_strlen($cpf_cnpj_limpo) !== 11) {
            $response['message'] = "CPF inválido. Deve conter 11 dígitos.";
            echo json_encode($response);
            exit();
        }
        $ent_cpf = $cpf_cnpj_limpo;
    } else { // 'J'
        if (mb_strlen($cpf_cnpj_limpo) !== 14) {
            $response['message'] = "CNPJ inválido. Deve conter 14 dígitos.";
            echo json_encode($response);
            exit();
        }
        $ent_cnpj = $cpf_cnpj_limpo;
    }

    $tipo_entidade_raw = filter_input(INPUT_POST, 'ent_tipo_entidade', FILTER_SANITIZE_STRING);
    $val_tipo_entidade = validate_selection($tipo_entidade_raw, ['Cliente', 'Fornecedor', 'Cliente e Fornecedor']);
    if (!$val_tipo_entidade['valid']) {
        $response['message'] = "Tipo de Entidade: " . $val_tipo_entidade['message'];
        echo json_encode($response);
        exit();
    }
    $ent_tipo_entidade = $val_tipo_entidade['value'];

    $situacao_form = filter_input(INPUT_POST, 'ent_situacao', FILTER_SANITIZE_STRING);
    $ent_situacao = ($situacao_form === 'A') ? 'A' : 'I';


    // Dados do Endereço (podem ser opcionais para atualização)
    $end_cep_raw = filter_input(INPUT_POST, 'end_cep', FILTER_SANITIZE_STRING);
    $end_cep = preg_replace('/\D/', '', $end_cep_raw);

    $end_logradouro = filter_input(INPUT_POST, 'end_logradouro', FILTER_SANITIZE_STRING);
    $end_numero = filter_input(INPUT_POST, 'end_numero', FILTER_SANITIZE_STRING);
    $end_complemento = filter_input(INPUT_POST, 'end_complemento', FILTER_SANITIZE_STRING);
    $end_bairro = filter_input(INPUT_POST, 'end_bairro', FILTER_SANITIZE_STRING);
    $end_cidade = filter_input(INPUT_POST, 'end_cidade', FILTER_SANITIZE_STRING);
    $end_uf = filter_input(INPUT_POST, 'end_uf', FILTER_SANITIZE_STRING);

    // --- 2. Lógica de Negócio e Interação com o Banco de Dados ---
    try {
        $pdo->beginTransaction();

        // Verifica se o CPF/CNPJ já existe para outra entidade (excluindo a entidade atual)
        $query_verifica_doc = $pdo->prepare("SELECT ent_codigo FROM tbl_entidades WHERE (ent_cpf = :cpf OR ent_cnpj = :cnpj) AND ent_codigo != :ent_codigo");
        $query_verifica_doc->bindParam(':cpf', $ent_cpf);
        $query_verifica_doc->bindParam(':cnpj', $ent_cnpj);
        $query_verifica_doc->bindParam(':ent_codigo', $ent_codigo, PDO::PARAM_INT);
        $query_verifica_doc->execute();

        if ($query_verifica_doc->rowCount() > 0) {
            $pdo->rollBack();
            $response['message'] = 'Erro: CPF/CNPJ já cadastrado para outra entidade.';
            echo json_encode($response);
            exit();
        }

        // Atualiza a entidade principal
        $query_entidade = $pdo->prepare("
            UPDATE tbl_entidades SET
                ent_razao_social = :razao_social,
                ent_tipo_pessoa = :tipo_pessoa,
                ent_cpf = :cpf,
                ent_cnpj = :cnpj,
                ent_tipo_entidade = :tipo_entidade,
                ent_situacao = :situacao
            WHERE ent_codigo = :ent_codigo
        ");
        $query_entidade->bindParam(':razao_social', $ent_razao_social);
        $query_entidade->bindParam(':tipo_pessoa', $ent_tipo_pessoa);
        $query_entidade->bindParam(':cpf', $ent_cpf);
        $query_entidade->bindParam(':cnpj', $ent_cnpj);
        $query_entidade->bindParam(':tipo_entidade', $ent_tipo_entidade);
        $query_entidade->bindParam(':situacao', $ent_situacao);
        $query_entidade->bindParam(':ent_codigo', $ent_codigo, PDO::PARAM_INT);
        $query_entidade->execute();

        // ====================================================================
        // NOVO: Lógica para gerenciar tbl_clientes e tbl_fornecedores
        // ====================================================================

        // Verifica se a entidade deve ser um Cliente
        $is_cliente = ($ent_tipo_entidade === 'Cliente' || $ent_tipo_entidade === 'Cliente e Fornecedor');
        // Verifica se a entidade deve ser um Fornecedor
        $is_fornecedor = ($ent_tipo_entidade === 'Fornecedor' || $ent_tipo_entidade === 'Cliente e Fornecedor');

        // Gerenciamento de tbl_clientes
        $query_check_cliente = $pdo->prepare("SELECT cli_codigo FROM tbl_clientes WHERE cli_entidade_id = :ent_id");
        $query_check_cliente->bindParam(':ent_id', $ent_codigo, PDO::PARAM_INT);
        $query_check_cliente->execute();
        $cliente_existe = $query_check_cliente->fetchColumn();

        if ($is_cliente && !$cliente_existe) {
            // Se deve ser cliente e não existe, insere
            $query_insert_cliente = $pdo->prepare("
                INSERT INTO tbl_clientes (cli_entidade_id, cli_status_cliente, cli_limite_credito, cli_data_cadastro, cli_usuario_cadastro_id)
                VALUES (:ent_id, 'Ativo', 0.00, NOW(), :usuario_id)
            ");
            $query_insert_cliente->bindParam(':ent_id', $ent_codigo, PDO::PARAM_INT);
            $query_insert_cliente->bindParam(':usuario_id', $usuario_logado_id, PDO::PARAM_INT);
            $query_insert_cliente->execute();
        } else if (!$is_cliente && $cliente_existe) {
            // Se não deve ser cliente e existe, deleta
            $query_delete_cliente = $pdo->prepare("DELETE FROM tbl_clientes WHERE cli_entidade_id = :ent_id");
            $query_delete_cliente->bindParam(':ent_id', $ent_codigo, PDO::PARAM_INT);
            $query_delete_cliente->execute();
        }
        // Se $is_cliente e $cliente_existe, não faz nada (assumimos que os campos específicos do cliente serão editados em uma tela de cliente dedicada)

        // Gerenciamento de tbl_fornecedores
        $query_check_fornecedor = $pdo->prepare("SELECT forn_codigo FROM tbl_fornecedores WHERE forn_entidade_id = :ent_id");
        $query_check_fornecedor->bindParam(':ent_id', $ent_codigo, PDO::PARAM_INT);
        $query_check_fornecedor->execute();
        $fornecedor_existe = $query_check_fornecedor->fetchColumn();

        if ($is_fornecedor && !$fornecedor_existe) {
            // Se deve ser fornecedor e não existe, insere
            $query_insert_fornecedor = $pdo->prepare("
                INSERT INTO tbl_fornecedores (forn_entidade_id, forn_data_cadastro, forn_usuario_cadastro_id)
                VALUES (:ent_id, NOW(), :usuario_id)
            ");
            $query_insert_fornecedor->bindParam(':ent_id', $ent_codigo, PDO::PARAM_INT);
            $query_insert_fornecedor->bindParam(':usuario_id', $usuario_logado_id, PDO::PARAM_INT);
            $query_insert_fornecedor->execute();
        } else if (!$is_fornecedor && $fornecedor_existe) {
            // Se não deve ser fornecedor e existe, deleta
            $query_delete_fornecedor = $pdo->prepare("DELETE FROM tbl_fornecedores WHERE forn_entidade_id = :ent_id");
            $query_delete_fornecedor->bindParam(':ent_id', $ent_codigo, PDO::PARAM_INT);
            $query_delete_fornecedor->execute();
        }
        // Se $is_fornecedor e $fornecedor_existe, não faz nada (assumimos que os campos específicos do fornecedor serão editados em uma tela de fornecedor dedicada)


        // Lógica para atualizar/inserir endereço principal (tipo 'Entrega')
        // Primeiro, verifica se já existe um endereço principal para esta entidade
        $query_check_endereco = $pdo->prepare("SELECT end_codigo FROM tbl_enderecos WHERE end_entidade_id = :ent_codigo AND end_tipo_endereco = 'Entrega'");
        $query_check_endereco->bindParam(':ent_codigo', $ent_codigo, PDO::PARAM_INT);
        $query_check_endereco->execute();
        $endereco_existente = $query_check_endereco->fetchColumn();

        // Verifica se há dados de endereço válidos para processar
        $has_valid_address_data = !empty($end_logradouro) && !empty($end_numero) && !empty($end_bairro) && !empty($end_cidade) && !empty($end_uf);

        if ($has_valid_address_data) {
            if ($endereco_existente) {
                // Atualiza o endereço existente
                $query_update_endereco = $pdo->prepare("
                    UPDATE tbl_enderecos SET
                        end_cep = :cep,
                        end_logradouro = :logradouro,
                        end_numero = :numero,
                        end_complemento = :complemento,
                        end_bairro = :bairro,
                        end_cidade = :cidade,
                        end_uf = :uf
                    WHERE end_codigo = :end_codigo
                ");
                $query_update_endereco->bindParam(':cep', $end_cep);
                $query_update_endereco->bindParam(':logradouro', $end_logradouro);
                $query_update_endereco->bindParam(':numero', $end_numero);
                $query_update_endereco->bindParam(':complemento', $end_complemento);
                $query_update_endereco->bindParam(':bairro', $end_bairro);
                $query_update_endereco->bindParam(':cidade', $end_cidade);
                $query_update_endereco->bindParam(':uf', $end_uf);
                $query_update_endereco->bindParam(':end_codigo', $endereco_existente, PDO::PARAM_INT);
                $query_update_endereco->execute();
            } else {
                // Insere um novo endereço principal
                $query_insert_endereco = $pdo->prepare("
                    INSERT INTO tbl_enderecos (
                        end_entidade_id, end_tipo_endereco, end_cep, end_logradouro,
                        end_numero, end_complemento, end_bairro, end_cidade, end_uf,
                        end_data_cadastro, end_usuario_cadastro_id
                    ) VALUES (
                        :ent_id, 'Entrega', :cep, :logradouro,
                        :numero, :complemento, :bairro, :cidade, :uf,
                        CURRENT_TIMESTAMP, :usuario_cadastro_id
                    )
                ");
                $query_insert_endereco->bindParam(':ent_id', $ent_codigo, PDO::PARAM_INT);
                $query_insert_endereco->bindParam(':cep', $end_cep);
                $query_insert_endereco->bindParam(':logradouro', $end_logradouro);
                $query_insert_endereco->bindParam(':numero', $end_numero);
                $query_insert_endereco->bindParam(':complemento', $end_complemento);
                $query_insert_endereco->bindParam(':bairro', $end_bairro);
                $query_insert_endereco->bindParam(':cidade', $end_cidade);
                $query_insert_endereco->bindParam(':uf', $end_uf);
                $query_insert_endereco->bindParam(':usuario_cadastro_id', $usuario_logado_id, PDO::PARAM_INT);
                $query_insert_endereco->execute();
            }
        } else if ($endereco_existente) {
            // Se os campos de endereço não foram preenchidos (ou foram esvaziados), mas um endereço principal existe,
            // deleta o endereço principal.
            $query_delete_endereco = $pdo->prepare("DELETE FROM tbl_enderecos WHERE end_codigo = :end_codigo");
            $query_delete_endereco->bindParam(':end_codigo', $endereco_existente, PDO::PARAM_INT);
            $query_delete_endereco->execute();
        }

        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Entidade atualizada com sucesso!';

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Erro ao editar entidade (editar_entidade.php): ' . $e->getMessage());
        $response['message'] = 'Erro no servidor ao atualizar entidade. Por favor, tente novamente mais tarde.';
    }

} else {
    $response['message'] = "Método de requisição inválido. Apenas requisições POST são permitidas.";
}

echo json_encode($response);
exit();
?>
