<?php
// Arquivo: process/editar_endereco.php
// Responsável por editar um endereço existente.

session_start(); // Inicia a sessão para verificar o token CSRF e o ID do usuário logado

require_once('../../includes/error_handler.php'); // Inclui o manipulador de erros
require_once('../../conexao.php'); // Inclui a conexão com o banco de dados
require_once('../../includes/helpers.php'); // Inclui funções auxiliares (para validação)

header('Content-Type: application/json'); // Define o cabeçalho para resposta JSON

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- DEBUG: Loga todos os dados POST recebidos ---
    error_log("DEBUG: POST data for editar_endereco.php: " . print_r($_POST, true));

    // --- 1. Verificação do Token CSRF ---
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $submitted_token !== $_SESSION['csrf_token']) {
        $response['message'] = "Erro de segurança: Requisição inválida (CSRF).";
        error_log("[CSRF ALERTA] Tentativa de CSRF detectada em editar_endereco.php. IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode($response);
        exit();
    }

    // --- 2. Validação e Sanitização de Entradas ---
    $endereco_id = filter_input(INPUT_POST, 'end_codigo', FILTER_VALIDATE_INT);
    if (!$endereco_id) {
        $response['message'] = "ID do endereço inválido ou ausente.";
        error_log("DEBUG: ID do endereço inválido ou ausente: " . ($_POST['end_codigo'] ?? 'N/A'));
        echo json_encode($response);
        exit();
    }

    $entidade_id = filter_input(INPUT_POST, 'end_entidade_id', FILTER_VALIDATE_INT);
    if (!$entidade_id) {
        $response['message'] = "ID da entidade associada ao endereço inválido ou ausente.";
        error_log("DEBUG: ID da entidade associada ao endereço inválido ou ausente: " . ($_POST['end_entidade_id'] ?? 'N/A'));
        echo json_encode($response);
        exit();
    }

    $tipo_endereco_raw = filter_input(INPUT_POST, 'end_tipo_endereco', FILTER_SANITIZE_STRING);
    $allowed_tipos_endereco = ['Entrega', 'Cobranca', 'Residencial', 'Comercial', 'Outro'];
    $val_tipo_endereco = validate_selection($tipo_endereco_raw, $allowed_tipos_endereco);
    if (!$val_tipo_endereco['valid']) {
        $response['message'] = "Tipo de Endereço: " . $val_tipo_endereco['message'];
        error_log("DEBUG: Validação falhou para Tipo de Endereço: " . $val_tipo_endereco['message']);
        echo json_encode($response);
        exit();
    }
    $end_tipo_endereco = $val_tipo_endereco['value'];

    $cep_raw = filter_input(INPUT_POST, 'end_cep', FILTER_SANITIZE_STRING);
    $end_cep = preg_replace('/[^0-9]/', '', $cep_raw);
    if (empty($end_cep) || !preg_match('/^\d{8}$/', $end_cep)) {
        $response['message'] = "CEP: Formato inválido. Deve conter 8 dígitos.";
        error_log("DEBUG: Validação falhou para CEP: " . $end_cep);
        echo json_encode($response);
        exit();
    }

    $logradouro_raw = filter_input(INPUT_POST, 'end_logradouro', FILTER_SANITIZE_STRING);
    $val_logradouro = validate_string($logradouro_raw, 3, 255);
    if (!$val_logradouro['valid']) {
        $response['message'] = "Logradouro: " . $val_logradouro['message'];
        error_log("DEBUG: Validação falhou para Logradouro: " . $val_logradouro['message']);
        echo json_encode($response);
        exit();
    }
    $end_logradouro = $val_logradouro['value'];

    $numero_raw = filter_input(INPUT_POST, 'end_numero', FILTER_SANITIZE_STRING);
    $val_numero = validate_string($numero_raw, 1, 50, '/^[a-zA-Z0-9\s-]+$/');
    if (!$val_numero['valid']) {
        $response['message'] = "Número: " . $val_numero['message'];
        error_log("DEBUG: Validação falhou para Número: " . $val_numero['message']);
        echo json_encode($response);
        exit();
    }
    $end_numero = $val_numero['value'];

    $complemento_raw = filter_input(INPUT_POST, 'end_complemento', FILTER_SANITIZE_STRING);
    $end_complemento = !empty($complemento_raw) ? $complemento_raw : null; // Opcional

    $bairro_raw = filter_input(INPUT_POST, 'end_bairro', FILTER_SANITIZE_STRING);
    $val_bairro = validate_string($bairro_raw, 3, 100);
    if (!$val_bairro['valid']) {
        $response['message'] = "Bairro: " . $val_bairro['message'];
        error_log("DEBUG: Validação falhou para Bairro: " . $val_bairro['message']);
        echo json_encode($response);
        exit();
    }
    $end_bairro = $val_bairro['value'];

    $cidade_raw = filter_input(INPUT_POST, 'end_cidade', FILTER_SANITIZE_STRING);
    $val_cidade = validate_string($cidade_raw, 3, 100);
    if (!$val_cidade['valid']) {
        $response['message'] = "Cidade: " . $val_cidade['message'];
        error_log("DEBUG: Validação falhou para Cidade: " . $val_cidade['message']);
        echo json_encode($response);
        exit();
    }
    $end_cidade = $val_cidade['value'];

    $uf_raw = filter_input(INPUT_POST, 'end_uf', FILTER_SANITIZE_STRING);
    $allowed_ufs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
    $val_uf = validate_selection($uf_raw, $allowed_ufs);
    if (!$val_uf['valid']) {
        $response['message'] = "UF: " . $val_uf['message'];
        error_log("DEBUG: Validação falhou para UF: " . $val_uf['message']);
        echo json_encode($response);
        exit();
    }
    $end_uf = $val_uf['value'];

    // --- DEBUG: Loga os parâmetros que serão usados na query ---
    error_log("DEBUG: Parameters for UPDATE query:");
    error_log("  endereco_id: " . $endereco_id);
    error_log("  entidade_id: " . $entidade_id);
    error_log("  tipo_endereco: " . $end_tipo_endereco);
    error_log("  cep: " . $end_cep);
    error_log("  logradouro: " . $end_logradouro);
    error_log("  numero: " . $end_numero);
    error_log("  complemento: " . ($end_complemento ?? 'NULL'));
    error_log("  bairro: " . $end_bairro);
    error_log("  cidade: " . $end_cidade);
    error_log("  uf: " . $end_uf);


    // --- 3. Lógica de Negócio e Interação com o Banco de Dados ---
    try {
        $pdo->beginTransaction();

        $query = $pdo->prepare("
            UPDATE tbl_enderecos SET
                end_tipo_endereco = :tipo_endereco,
                end_cep = :cep,
                end_logradouro = :logradouro,
                end_numero = :numero,
                end_complemento = :complemento,
                end_bairro = :bairro,
                end_cidade = :cidade,
                end_uf = :uf
            WHERE
                end_codigo = :endereco_id AND end_entidade_id = :entidade_id
        ");
        $query->bindValue(':tipo_endereco', $end_tipo_endereco);
        $query->bindValue(':cep', $end_cep);
        $query->bindValue(':logradouro', $end_logradouro);
        $query->bindValue(':numero', $end_numero);
        $query->bindValue(':complemento', $end_complemento);
        $query->bindValue(':bairro', $end_bairro);
        $query->bindValue(':cidade', $end_cidade);
        $query->bindValue(':uf', $end_uf);
        $query->bindValue(':endereco_id', $endereco_id, PDO::PARAM_INT);
        $query->bindValue(':entidade_id', $entidade_id, PDO::PARAM_INT);
        
        $query->execute();

        $rowsAffected = $query->rowCount(); // Captura o número de linhas afetadas
        error_log("DEBUG: Rows affected by UPDATE: " . $rowsAffected); // LOG AQUI

        if ($rowsAffected > 0) {
            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Endereço atualizado com sucesso!';
        } else {
            $pdo->rollBack();
            $response['message'] = 'Endereço não encontrado ou nenhum dado foi alterado.';
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Erro ao editar endereço (editar_endereco.php): ' . $e->getMessage());
        $response['message'] = 'Erro no servidor ao atualizar endereço. Por favor, tente novamente mais tarde.';
    }

} else {
    $response['message'] = "Método de requisição inválido. Apenas requisições POST são permitidas.";
}

echo json_encode($response);
exit();
?>
