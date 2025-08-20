<?php
// /public/api.php

require_once __DIR__ . '/../src/bootstrap.php';

// --- Autoloader ---
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;
use App\Usuarios\UsuarioRepository;
use App\Carregamentos\CarregamentoRepository;
use App\Entidades\EntidadeRepository;

// --- Configurações Iniciais da API ---
header('Content-Type: application/json');

// --- Roteamento ---
$action = $_GET['action'] ?? '';

try {
    $pdo = Database::getConnection();
    $usuarioRepo = new UsuarioRepository($pdo);
    $carregamentoRepo = new CarregamentoRepository($pdo);
    $entidadeRepo = new EntidadeRepository($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados.']);
    exit;
}

// =================================================================
// ROTEADOR PRINCIPAL DA API
// =================================================================
switch ($action) {
    case 'login':
        apiLogin($usuarioRepo);
        break;

    case 'getDadosNovoCarregamento':
        apiGetDadosNovoCarregamento($carregamentoRepo, $entidadeRepo);
        break;

    case 'salvarCarregamentoHeader':
        apiSalvarCarregamentoHeader($carregamentoRepo, $usuarioRepo);
        break;

    case 'validarLeitura':
        apiValidarLeitura($carregamentoRepo, $usuarioRepo);
        break;

    case 'salvarFilaComLeituras':
        apiSalvarFilaComLeituras($carregamentoRepo, $usuarioRepo);
        break;

    case 'uploadFotoFila':
        apiUploadFotoFila($carregamentoRepo, $usuarioRepo);
        break;

    case 'finalizarCarregamento':
        apiFinalizarCarregamento($carregamentoRepo, $usuarioRepo);
        break;

    default:
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado.']);
        break;
}

// =================================================================
// FUNÇÕES DE CONTROLE DA API
// =================================================================

/**
 * Pega o token do cabeçalho da requisição e retorna os dados do usuário.
 * Se o token for inválido, encerra a execução com erro 401.
 */
function getAuthenticatedUser(UsuarioRepository $repo): array
{
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? null;

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Token de autorização não fornecido ou em formato inválido.']);
        exit;
    }

    $token = $matches[1];
    $user = $repo->findUserByToken($token);

    if (!$user) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Token inválido ou expirado.']);
        exit;
    }
    return $user;
}

/**
 * Lida com a autenticação de usuários via API.
 */
function apiLogin(UsuarioRepository $repo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $login = $input['login'] ?? null;
    $password = $input['senha'] ?? null;

    if (!$login || !$password) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Login e senha são obrigatórios.']);
        return;
    }

    $user = $repo->validateCredentials($login, $password);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTime())->modify('+7 days')->format('Y-m-d H:i:s');

        $repo->saveApiToken($user['usu_codigo'], $tokenHash, $expiresAt);

        echo json_encode([
            'success' => true,
            'message' => 'Login bem-sucedido!',
            'token' => $token,
            'userName' => $user['usu_nome']
        ]);
    } else {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Credenciais inválidas.']);
    }
}

/**
 * Fornece os dados iniciais para a tela de novo carregamento do app.
 */
function apiGetDadosNovoCarregamento(CarregamentoRepository $carregamentoRepo, EntidadeRepository $entidadeRepo)
{
    // Futuramente, esta função também terá uma verificação de token de segurança

    $proximoNumero = $carregamentoRepo->getNextNumeroCarregamento();
    $clientes = $entidadeRepo->getClienteOptions();

    echo json_encode([
        'success' => true,
        'proximoNumero' => $proximoNumero,
        'clientes' => $clientes
    ]);
}

/**
 * Lida com o salvamento do cabeçalho de um novo carregamento.
 */
function apiSalvarCarregamentoHeader(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo); // Protege o endpoint
    $input = json_decode(file_get_contents('php://input'), true);

    // Validação básica dos dados recebidos
    if (empty($input['numero']) || empty($input['data']) || empty($input['clienteOrganizadorId'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Campos obrigatórios ausentes: numero, data, clienteOrganizadorId.']);
        return;
    }

    try {
        $newId = $repo->createHeader($input, $user['usu_codigo']);
        echo json_encode(['success' => true, 'message' => 'Cabeçalho salvo com sucesso!', 'carregamentoId' => $newId]);
    } catch (Exception $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar o cabeçalho: ' . $e->getMessage()]);
    }
}

/**
 * Lida com o salvamento de uma fila e suas leituras de QR Code.
 */
function apiSalvarFilaComLeituras(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo); // Protege o endpoint
    $input = json_decode(file_get_contents('php://input'), true);

    $carregamentoId = $input['carregamentoId'] ?? null;
    $clienteId = $input['clienteId'] ?? null;
    $leituras = $input['leituras'] ?? null;

    // Validação dos dados recebidos do aplicativo
    if (!$carregamentoId || !$clienteId || !is_array($leituras) || empty($leituras)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Dados inválidos. É necessário fornecer carregamentoId, clienteId e uma lista de leituras.']);
        return;
    }

    try {
        $newFilaId = $repo->createFilaWithLeituras($carregamentoId, $clienteId, $leituras);
        echo json_encode(['success' => true, 'message' => 'Fila e leituras salvas com sucesso!', 'filaId' => $newFilaId]);
    } catch (Exception $e) {
        http_response_code(500); // Internal Server Error
        error_log("API Error in salvarFilaComLeituras: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar a fila.']);
    }
}

/**
 * Lida com o upload de uma foto para uma fila de carregamento.
 */
function apiUploadFotoFila(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo); // Protege o endpoint

    // Validação dos dados recebidos
    $filaId = filter_input(INPUT_POST, 'filaId', FILTER_VALIDATE_INT);

    if (!$filaId || !isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Dados inválidos. É necessário enviar uma foto e um filaId válido.']);
        return;
    }

    $uploadDir = __DIR__ . '/uploads/carregamentos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true); // Cria o diretório se não existir
    }

    // Gera um nome de arquivo único para evitar sobreposições
    $fileExtension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('fila_' . $filaId . '_', true) . '.' . $fileExtension;
    $uploadFilePath = $uploadDir . $fileName;

    // Move o arquivo temporário para o destino final
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadFilePath)) {
        // O caminho a ser salvo no banco deve ser relativo à pasta public
        $publicPath = 'uploads/carregamentos/' . $fileName;

        try {
            $repo->updateFilaPhotoPath($filaId, $publicPath);
            echo json_encode(['success' => true, 'message' => 'Foto enviada com sucesso!']);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("API Error in apiUploadFotoFila DB: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Foto salva, mas houve um erro ao registrar no banco de dados.']);
        }
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar o arquivo da foto no servidor.']);
    }
}

/**
 * Lida com a finalização de um carregamento.
 */
/* function apiFinalizarCarregamento(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo); // Protege o endpoint
    $input = json_decode(file_get_contents('php://input'), true);

    $carregamentoId = $input['carregamentoId'] ?? null;

    if (!$carregamentoId) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'O ID do carregamento é obrigatório.']);
        return;
    }

    try {
        if ($repo->finalize((int) $carregamentoId)) {
            echo json_encode(['success' => true, 'message' => 'Carregamento finalizado com sucesso!']);
        } else {
            // Isso pode acontecer se o ID não existir ou o lote já estiver finalizado
            echo json_encode(['success' => false, 'message' => 'Não foi possível finalizar o carregamento. Verifique se o ID está correto e se o carregamento ainda está "EM ANDAMENTO".']);
        }
    } catch (Exception $e) {
        http_response_code(500); // Internal Server Error
        error_log("API Error in finalizarCarregamento: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao finalizar o carregamento.']);
    }
}*/

function apiFinalizarCarregamento(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo); // Protege o endpoint
    $input = json_decode(file_get_contents('php://input'), true);

    $carregamentoId = $input['carregamentoId'] ?? null;

    if (!$carregamentoId) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'O ID do carregamento é obrigatório.']);
        return;
    }

    try {
        // A lógica foi alterada para chamar o novo método
        if ($repo->marcarComoAguardandoConferencia((int) $carregamentoId, $user['usu_codigo'])) {
            echo json_encode(['success' => true, 'message' => 'Carregamento enviado para conferência com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Não foi possível enviar para conferência. Verifique se o carregamento ainda está "EM ANDAMENTO".']);
        }
    } catch (Exception $e) {
        http_response_code(500); // Internal Server Error
        error_log("API Error in finalizarCarregamento: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao processar a solicitação.']);
    }
}

/**
 * Valida o conteúdo de um QR Code em tempo real.
 */
function apiValidarLeitura(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo); // Protege o endpoint
    $input = json_decode(file_get_contents('php://input'), true);
    $qrCodeContent = $input['qrCode'] ?? null;

    if (!$qrCodeContent) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Conteúdo do QR Code não fornecido.']);
        return;
    }

    try {
        $validationResult = $repo->validarQrCode($qrCodeContent);
        echo json_encode($validationResult);

    } catch (Exception $e) {
        http_response_code(500); // Internal Server Error
        error_log("API Error in validarLeitura: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao validar a leitura.']);
    }
}