<?php
// Arquivo: process/cadastrar_endereco.php
// Responsável por cadastrar um novo endereço para uma entidade existente.

session_start(); // Inicia a sessão para verificar o token CSRF e o ID do usuário logado

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
        error_log("[CSRF ALERTA] Tentativa de CSRF detectada em cadastrar_endereco.php. IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode($response);
        exit();
    }

    // --- 2. Validação e Sanitização de Entradas ---
    $entidade_id = filter_input(INPUT_POST, 'end_entidade_id', FILTER_VALIDATE_INT);
    if (!$entidade_id) {
        $response['message'] = "ID da entidade associada ao endereço inválido ou ausente.";
        echo json_encode($response);
        exit();
    }

    $tipo_endereco_raw = filter_input(INPUT_POST, 'end_tipo_endereco', FILTER_SANITIZE_STRING);
    $allowed_tipos_endereco = ['Entrega', 'Cobranca', 'Residencial', 'Comercial', 'Outro'];
    $val_tipo_endereco = validate_selection($tipo_endereco_raw, $allowed_tipos_endereco);
    if (!$val_tipo_endereco['valid']) {
        $response['message'] = "Tipo de Endereço: " . $val_tipo_endereco['message'];
        echo json_encode($response);
        exit();
    }
    $end_tipo_endereco = $val_tipo_endereco['value'];

    $cep_raw = filter_input(INPUT_POST, 'end_cep', FILTER_SANITIZE_STRING);
    $end_cep = preg_replace('/[^0-9]/', '', $cep_raw);
    if (empty($end_cep) || !preg_match('/^\d{8}$/', $end_cep)) {
        $response['message'] = "CEP: Formato inválido. Deve conter 8 dígitos.";
        echo json_encode($response);
        exit();
    }

    $logradouro_raw = filter_input(INPUT_POST, 'end_logradouro', FILTER_SANITIZE_STRING);
    $val_logradouro = validate_string($logradouro_raw, 3, 255);
    if (!$val_logradouro['valid']) {
        $response['message'] = "Logradouro: " . $val_logradouro['message'];
        echo json_encode($response);
        exit();
    }
    $end_logradouro = $val_logradouro['value'];

    $numero_raw = filter_input(INPUT_POST, 'end_numero', FILTER_SANITIZE_STRING);
    $val_numero = validate_string($numero_raw, 1, 50, '/^[a-zA-Z0-9\s-]+$/');
    if (!$val_numero['valid']) {
        $response['message'] = "Número: " . $val_numero['message'];
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
        echo json_encode($response);
        exit();
    }
    $end_bairro = $val_bairro['value'];

    $cidade_raw = filter_input(INPUT_POST, 'end_cidade', FILTER_SANITIZE_STRING);
    $val_cidade = validate_string($cidade_raw, 3, 100);
    if (!$val_cidade['valid']) {
        $response['message'] = "Cidade: " . $val_cidade['message'];
        echo json_encode($response);
        exit();
    }
    $end_cidade = $val_cidade['value'];

    $uf_raw = filter_input(INPUT_POST, 'end_uf', FILTER_SANITIZE_STRING);
    $allowed_ufs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
    $val_uf = validate_selection($uf_raw, $allowed_ufs);
    if (!$val_uf['valid']) {
        $response['message'] = "UF: " . $val_uf['message'];
        echo json_encode($response);
        exit();
    }
    $end_uf = $val_uf['value'];

    $usuario_cadastro_id = $_SESSION['codUsuario'] ?? null;
    if (!$usuario_cadastro_id) {
        $response['message'] = "Erro: Usuário não autenticado para realizar o cadastro.";
        echo json_encode($response);
        exit();
    }

    // --- 3. Lógica de Negócio e Interação com o Banco de Dados ---
    try {
        $pdo->beginTransaction();

        $query = $pdo->prepare("
            INSERT INTO tbl_enderecos (
                end_entidade_id, end_tipo_endereco, end_cep, end_logradouro, 
                end_numero, end_complemento, end_bairro, end_cidade, end_uf
            ) VALUES (
                :entidade_id, :tipo_endereco, :cep, :logradouro, 
                :numero, :complemento, :bairro, :cidade, :uf
            )
        ");
        $query->bindValue(':entidade_id', $entidade_id, PDO::PARAM_INT);
        $query->bindValue(':tipo_endereco', $end_tipo_endereco);
        $query->bindValue(':cep', $end_cep);
        $query->bindValue(':logradouro', $end_logradouro);
        $query->bindValue(':numero', $end_numero);
        $query->bindValue(':complemento', $end_complemento);
        $query->bindValue(':bairro', $end_bairro);
        $query->bindValue(':cidade', $end_cidade);
        $query->bindValue(':uf', $end_uf);
        
        $query->execute();

        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Endereço cadastrado com sucesso!';

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Erro ao cadastrar endereço (cadastrar_endereco.php): ' . $e->getMessage());
        $response['message'] = 'Erro no servidor ao cadastrar endereço. Por favor, tente novamente mais tarde.';
    }

} else {
    $response['message'] = "Método de requisição inválido. Apenas requisições POST são permitidas.";
}

echo json_encode($response);
exit();
?>
