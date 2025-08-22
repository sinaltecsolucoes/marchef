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

    case 'getCarregamentosAtivos':
        apiGetCarregamentos($carregamentoRepo, $usuarioRepo, 'ativos');
        break;

    case 'getCarregamentosFinalizados':
        apiGetCarregamentos($carregamentoRepo, $usuarioRepo, 'finalizados');
        break;

    case 'getResumoCarregamento':
        apiGetResumoCarregamento($carregamentoRepo, $usuarioRepo);
        break;

    case 'getFilasPorCarregamento':
        apiGetFilasPorCarregamento($carregamentoRepo, $usuarioRepo);
        break;

    case 'criarFila':
        apiCriarFila($carregamentoRepo, $usuarioRepo);
        break;

    case 'atualizarCarregamentoHeader':
        apiAtualizarCarregamentoHeader($carregamentoRepo, $usuarioRepo);
        break;

    case 'getCarregamentoHeader':
        apiGetCarregamentoHeader($carregamentoRepo, $usuarioRepo);
        break;

    case 'getDetalhesFila':
        apiGetDetalhesFila($carregamentoRepo, $usuarioRepo);
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
    $authHeader = null;

    // Tenta pegar o cabeçalho 'Authorization' de várias fontes possíveis
    if (isset($_SERVER['Authorization'])) {
        $authHeader = $_SERVER['Authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { // O mais comum em CGI/FastCGI
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        // A função getallheaders() pode retornar chaves em minúsculas
        $authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
    }

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
/* function apiSalvarFilaComLeituras(CarregamentoRepository $repo, UsuarioRepository $userRepo)
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
}*/


// Em public/api.php

/**
 * Lida com o salvamento de uma lista de leituras para um cliente em uma fila.
 */
function apiSalvarFilaComLeituras(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);

    $carregamentoId = $input['carregamentoId'] ?? null;
    $filaId = $input['filaId'] ?? null;
    $clienteId = $input['clienteId'] ?? null;
    $leituras = $input['leituras'] ?? null;

    if (!$carregamentoId || !$filaId || !$clienteId || !is_array($leituras) || empty($leituras)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados de entrada inválidos.']);
        return;
    }

    $pdo = $repo->getPdo(); // Precisamos do objeto PDO para controlar a transação
    $pdo->beginTransaction();
    try {
        // Itera sobre cada produto agrupado enviado pelo Flutter
        foreach ($leituras as $leitura) {
            // Adiciona o item no banco de dados
            $repo->adicionarItemAFila(
                (int) $filaId,
                (int) $leitura['produtoId'],
                (int) $leitura['loteId'],
                (float) $leitura['quantidade'],
                (int) $carregamentoId,
                (int) $clienteId
            );
        }
        $pdo->commit(); // Se tudo deu certo, confirma as alterações no banco
        echo json_encode(['success' => true, 'message' => 'Itens salvos com sucesso!']);
    } catch (Exception $e) {
        $pdo->rollBack(); // Se deu algum erro, desfaz todas as alterações
        http_response_code(500);
        error_log("API Error in salvarFilaComLeituras: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar os itens: ' . $e->getMessage()]);
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

/**
 * Fornece a lista de carregamentos (ativos ou finalizados).
 */
function apiGetCarregamentos(CarregamentoRepository $carregamentoRepo, UsuarioRepository $userRepo, string $tipo)
{
    getAuthenticatedUser($userRepo); // Protege o endpoint

    try {
        if ($tipo === 'ativos') {
            $carregamentos = $carregamentoRepo->findAtivos();
        } else {
            $carregamentos = $carregamentoRepo->findFinalizados();
        }
        echo json_encode(['success' => true, 'data' => $carregamentos]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar carregamentos: ' . $e->getMessage()]);
    }
}
function apiGetResumoCarregamento(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo); // Protege o endpoint
    $carregamentoId = (int) ($_GET['carregamentoId'] ?? 0);

    if ($carregamentoId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do carregamento inválido.']);
        return;
    }

    try {
        $data = $repo->findCarregamentoComFilasEItens($carregamentoId);

        if (!$data) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Carregamento não encontrado.']);
            return;
        }

        $header = $data['header'];
        $filas = $data['filas'];

        // Calcular totais
        $total_filas = count($filas);
        $total_quilos = 0;
        $produtos = [];

        foreach ($filas as $fila) {
            foreach ($fila['itens'] as $item) {
                $quantidade = (float) $item['car_item_quantidade'];
                $total_quilos += $quantidade; // Assumindo quantidade em quilos

                $produtos[] = [
                    'nome' => $item['prod_descricao'],
                    'lote' => $item['lote_completo_calculado'],
                    'quantidade' => $quantidade
                ];
            }
        }

        $resumo = [
            'numero' => $header['car_numero'],
            'responsavel' => $header['responsavel'] ?: 'Desconhecido',
            'total_filas' => $total_filas,
            'total_quilos' => $total_quilos,
            'produtos' => $produtos
        ];

        echo json_encode(['success' => true, 'data' => $resumo]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("API Error in getResumoCarregamento: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar resumo do carregamento: ' . $e->getMessage()]);
    }
}

/**
 * Fornece a lista de filas para um carregamento específico.
 */
function apiGetFilasPorCarregamento(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo);
    $carregamentoId = (int) ($_GET['carregamentoId'] ?? 0);

    if ($carregamentoId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do carregamento inválido.']);
        return;
    }

    $filas = $repo->findFilasByCarregamentoId($carregamentoId);
    echo json_encode(['success' => true, 'data' => $filas]);
}

/**
 * Cria uma nova fila para um carregamento.
 */
function apiCriarFila(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);
    $carregamentoId = $input['carregamentoId'] ?? null;

    if (!$carregamentoId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O ID do carregamento é obrigatório.']);
        return;
    }

    try {
        $newFilaId = $repo->adicionarFila((int) $carregamentoId);
        echo json_encode(['success' => true, 'message' => 'Fila criada com sucesso!', 'filaId' => $newFilaId]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao criar a fila: ' . $e->getMessage()]);
    }
}

/**
 * Lida com a atualização do cabeçalho de um carregamento.
 */
function apiAtualizarCarregamentoHeader(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);
    $carregamentoId = $input['carregamentoId'] ?? null;

    if (!$carregamentoId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do carregamento é obrigatório.']);
        return;
    }

    try {
        $repo->updateHeader((int) $carregamentoId, $input, $user['usu_codigo']);
        echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar dados: ' . $e->getMessage()]);
    }
}

/**
 * Fornece os detalhes do cabeçalho de um carregamento.
 */
function apiGetCarregamentoHeader(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo);
    $carregamentoId = (int) ($_GET['carregamentoId'] ?? 0);

    if ($carregamentoId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do carregamento inválido.']);
        return;
    }

    $header = $repo->findHeaderById($carregamentoId);

    if ($header) {
        echo json_encode(['success' => true, 'data' => $header]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Carregamento não encontrado.']);
    }
}

/**
 * Fornece os detalhes de uma fila, com seus clientes e itens.
 */
function apiGetDetalhesFila(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo);
    $filaId = (int) ($_GET['filaId'] ?? 0);

    if ($filaId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da fila inválido.']);
        return;
    }

    $fila = $repo->findFilaComClientesEItens($filaId);

    if ($fila) {
        echo json_encode(['success' => true, 'data' => $fila]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Fila não encontrada.']);
    }
}