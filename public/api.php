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
use App\Entrada\EntradaRepository;
use App\Estoque\CamaraRepository;
use App\Estoque\EnderecoRepository;
use App\Estoque\EstoqueRepository;
use App\OrdensExpedicao\OrdemExpedicaoRepository;

// --- Configurações Iniciais da API ---
header('Content-Type: application/json');

// --- Roteamento ---
$action = $_GET['action'] ?? '';

try {
    $pdo = Database::getConnection();
    $usuarioRepo = new UsuarioRepository($pdo);
    $carregamentoRepo = new CarregamentoRepository($pdo);
    $entidadeRepo = new EntidadeRepository($pdo);
    $entradaRepo = new EntradaRepository($pdo);
    $camaraRepo = new CamaraRepository($pdo);
    $enderecoRepo = new EnderecoRepository($pdo);
    $estoqueRepo = new EstoqueRepository($pdo);
    $ordemExpedicaoRepo = new OrdemExpedicaoRepository($pdo);

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
        apiGetDadosNovoCarregamento($entidadeRepo, $carregamentoRepo);
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

    case 'excluirCarregamento':
        apiExcluirCarregamento($carregamentoRepo, $usuarioRepo);
        break;

    case 'removerClienteDeFila':
        apiRemoverClienteDeFila($carregamentoRepo, $usuarioRepo);
        break;

    case 'atualizarItensCliente':
        apiAtualizarItensCliente($carregamentoRepo, $usuarioRepo);
        break;

    case 'removerFilaCompleta':
        apiRemoverFilaCompleta($carregamentoRepo, $usuarioRepo);
        break;

    case 'atualizarQuantidadeItem':
        apiAtualizarQuantidadeItem($carregamentoRepo, $usuarioRepo);
        break;

    case 'getFotosDaFila':
        apiGetFotosDaFila($carregamentoRepo, $usuarioRepo);
        break;

    case 'excluirFotoFila': // Este endpoint agora receberá `foto_id`
        apiExcluirFotoFila($carregamentoRepo, $usuarioRepo);
        break;

    case 'getDadosNovaEntrada':
        apiGetDadosNovaEntrada($entradaRepo, $usuarioRepo);
        break;

    case 'salvarLeituraEntrada':
        apiSalvarLeituraEntrada($entradaRepo, $usuarioRepo);
        break;

    case 'get_camaras':
        apiGetCamaras($camaraRepo);
        break;

    case 'get_enderecos_por_camara':
        apiGetEnderecosPorCamara($enderecoRepo);
        break;

    case 'registrar_entrada_estoque':
        apiRegistrarEntradaEstoque($carregamentoRepo, $estoqueRepo);
        break;

    case 'excluir_alocacao_entrada':
        apiExcluirAlocacao($estoqueRepo);
        break;

    case 'editar_quantidade_alocacao':
        apiEditarQuantidadeAlocacao($estoqueRepo);
        break;

    case 'get_entradas_do_dia':
        apiGetEntradasDoDia($estoqueRepo);
        break;

    case 'get_ordens_prontas':
        // A função que busca as ordens precisa do OrdemExpedicaoRepository
        apiGetOrdensProntas($ordemExpedicaoRepo);
        break;

    case 'criar_carregamento_de_oe':
        // A função que cria o carregamento precisa do CarregamentoRepository
        apiCriarCarregamentoDeOe($carregamentoRepo, $usuarioRepo);
        break;

    case 'get_detalhes_oe':
        apiGetDetalhesOe($ordemExpedicaoRepo);
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

function apiGetDadosNovoCarregamento($entidadeRepo, $carregamentoRepo)
{
    $usuarioAutenticado = getAuthenticatedUser($GLOBALS['usuarioRepo']);

    try {
        $clientes = $entidadeRepo->getClienteOptions();
        $transportadoras = $entidadeRepo->getTransportadoraOptions();
        $proximoNumero = $carregamentoRepo->getNextNumeroCarregamento();

        echo json_encode([
            'success' => true,
            'clientes' => $clientes,
            'transportadoras' => $transportadoras,
            'proximo_numero' => $proximoNumero
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
    }
}

/**
 * Lida com o salvamento do cabeçalho de um novo carregamento.
 */
/* function apiSalvarCarregamentoHeader(CarregamentoRepository $repo, UsuarioRepository $userRepo)
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
} */

function apiSalvarCarregamentoHeader(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);

    // Validação básica dos dados recebidos do app
    if (empty($input['numero']) || empty($input['data']) || empty($input['clienteOrganizadorId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Campos obrigatórios ausentes.']);
        return;
    }

    // --- CORREÇÃO APLICADA AQUI ---
    // 1. "Traduzimos" os nomes dos campos do app para os nomes da base de dados.
    // 2. Adicionamos o 'tipo' que definimos. Por agora, será 'AVULSA'.
    $dadosParaSalvar = [
        'car_numero' => $input['numero'],
        'car_data' => $input['data'],
        'car_entidade_id_organizador' => $input['clienteOrganizadorId'],
        'car_placas' => $input['placa'] ?? null,
        'car_lacres' => $input['lacre'] ?? null,
        'tipo' => $input['tipo'] // O app vai enviar este campo
        // Adicione outros campos aqui se necessário (ex: transportadoraId)
    ];

    try {
        // 3. Chamamos o método com o nome correto e passamos os dados formatados.
        $newId = $repo->salvarCarregamentoHeader($dadosParaSalvar, $user['usu_codigo']);
        echo json_encode(['success' => true, 'message' => 'Cabeçalho salvo com sucesso!', 'carregamentoId' => $newId]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar o cabeçalho: ' . $e->getMessage()]);
    }
}


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
    getAuthenticatedUser($userRepo);

    $filaId = filter_input(INPUT_POST, 'filaId', FILTER_VALIDATE_INT);

    if (!$filaId || !isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos. É necessário enviar uma foto e um filaId válido.']);
        return;
    }

    try {
        $info = $repo->getInfoParaNomeArquivo($filaId);
        if (!$info) {
            throw new Exception("Fila ou Carregamento não encontrado.");
        }

        $ordemExpedicao = $info['car_ordem_expedicao'];
        $numeroFila = $info['fila_numero_sequencial'];

        // Sanitiza a ordem de expedição para usar como nome da pasta
        $nomePasta = preg_replace('/[^a-zA-Z0-9]/', '', $ordemExpedicao);

        // Cria o diretório se não existir
        $uploadDir = __DIR__ . '/uploads/carregamentos/' . $nomePasta . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // --- INÍCIO DA CORREÇÃO DO NOME DO ARQUIVO ---

        // 1. Conta quantas fotos já existem para obter o próximo número sequencial
        $proximoNumeroFoto = $repo->countFotosByFilaId($filaId) + 1;

        // 2. Monta o novo nome do arquivo com o sequencial
        $fileExtension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $newFileName = 'oe' . $nomePasta . '_fila' . $numeroFila . '_foto' . $proximoNumeroFoto . '.' . $fileExtension;

        // --- FIM DA CORREÇÃO ---

        $uploadFilePath = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadFilePath)) {
            $publicPath = 'uploads/carregamentos/' . $nomePasta . '/' . $newFileName;

            $repo->adicionarFotoFila($filaId, $publicPath);
            echo json_encode(['success' => true, 'message' => 'Foto enviada com sucesso!', 'path' => $publicPath]);
        } else {
            throw new Exception("Erro ao salvar o arquivo da foto no servidor.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("API Error in apiUploadFotoFila: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
}

/**
 * Valida o conteúdo de um QR Code em tempo real.
 */
function apiFinalizarCarregamento(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);
    $carregamentoId = $input['carregamentoId'] ?? null;

    if (!$carregamentoId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O ID do carregamento é obrigatório.']);
        return;
    }

    try {
        $repo->marcarComoAguardandoConferencia((int) $carregamentoId, $user['usu_codigo']);
        echo json_encode(['success' => true, 'message' => 'Carregamento enviado para conferência com sucesso!']);
    } catch (Exception $e) {
        // Tenta decodificar a mensagem da exceção
        $errorData = json_decode($e->getMessage(), true);

        // Se for a nossa exceção estruturada, retorna o JSON para o app
        if (json_last_error() === JSON_ERROR_NONE && isset($errorData['error_code'])) {
            http_response_code(400); // Bad Request (erro do cliente)
            echo json_encode([
                'success' => false,
                'error_code' => $errorData['error_code'],
                'message' => $errorData['message'],
                'data' => $errorData['data']
            ]);
        } else {
            // Se for qualquer outro erro, retorna um erro genérico
            http_response_code(500); // Internal Server Error
            error_log("API Error in finalizarCarregamento: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro interno ao processar a solicitação.']);
        }
    }
}

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

/**
 * Fornece a lista de filas para um carregamento específico.
 */
function apiGetResumoCarregamento(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo); // Protege o endpoint
    $carregamentoId = (int) ($_GET['carregamentoId'] ?? 0);

    if ($carregamentoId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do carregamento inválido.']);
        return;
    }

    try {
        // Esta função no repositório já busca todos os dados que precisamos
        $data = $repo->findCarregamentoComFilasEItens($carregamentoId);

        if (!$data) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Carregamento não encontrado.']);
            return;
        }

        // Apenas retornamos os dados completos, sem sumarizar no PHP
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("API Error in getResumoCarregamento: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar resumo do carregamento: ' . $e->getMessage()]);
    }
}

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

/**
 * Lida com a exclusão de um carregamento.
 */
function apiExcluirCarregamento(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo); // Protege o endpoint
    $input = json_decode(file_get_contents('php://input'), true);
    $carregamentoId = $input['carregamentoId'] ?? null;

    if (!$carregamentoId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O ID do carregamento é obrigatório.']);
        return;
    }

    try {
        if ($repo->excluir((int) $carregamentoId)) {
            echo json_encode(['success' => true, 'message' => 'Carregamento excluído com sucesso!']);
        } else {
            // Isso pode acontecer se o repo->excluir retornar false por alguma razão interna
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Carregamento não encontrado ou não pôde ser excluído.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Lida com a remoção de um cliente e seus itens de uma fila.
 */
function apiRemoverClienteDeFila(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);
    $filaId = $input['filaId'] ?? null;
    $clienteId = $input['clienteId'] ?? null;

    if (!$filaId || !$clienteId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'IDs da fila e do cliente são obrigatórios.']);
        return;
    }

    try {
        $repo->removerClienteDeFila((int) $filaId, (int) $clienteId);
        echo json_encode(['success' => true, 'message' => 'Cliente e seus itens foram removidos da fila.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao remover cliente: ' . $e->getMessage()]);
    }
}

/**
 * Lida com a atualização dos itens de um cliente em uma fila.
 */
function apiAtualizarItensCliente(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);

    $filaId = $input['filaId'] ?? null;
    $clienteId = $input['clienteId'] ?? null;
    $carregamentoId = $input['carregamentoId'] ?? null;
    $leituras = $input['leituras'] ?? []; // Pode ser uma lista vazia

    if (!$filaId || !$clienteId || !$carregamentoId || !is_array($leituras)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados de entrada inválidos.']);
        return;
    }

    $pdo = $repo->getPdo();
    $pdo->beginTransaction();
    try {
        $repo->atualizarItensClienteEmFila((int) $filaId, (int) $clienteId, (int) $carregamentoId, $leituras);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Itens do cliente atualizados com sucesso!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar itens: ' . $e->getMessage()]);
    }
}

/**
 * Lida com a remoção de uma fila e todos os seus itens.
 */
function apiRemoverFilaCompleta(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo); // Protege o endpoint
    $input = json_decode(file_get_contents('php://input'), true);
    $filaId = $input['fila_id'] ?? null;

    if (!$filaId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O ID da fila é obrigatório.']);
        return;
    }

    try {
        // A função no repositório já existe e faz todo o trabalho pesado
        $repo->removerFilaCompleta((int) $filaId);
        echo json_encode(['success' => true, 'message' => 'Fila removida com sucesso!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao remover a fila: ' . $e->getMessage()]);
    }
}

/**
 * Lida com a atualização da quantidade de um único item.
 */
function apiAtualizarQuantidadeItem(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);

    $itemId = $input['itemId'] ?? null;
    $novaQuantidade = $input['novaQuantidade'] ?? null;

    if (!$itemId || !is_numeric($novaQuantidade)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados de entrada inválidos.']);
        return;
    }

    try {
        $repo->atualizarQuantidadeItem((int) $itemId, (float) $novaQuantidade);
        echo json_encode(['success' => true, 'message' => 'Quantidade atualizada com sucesso!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar a quantidade: ' . $e->getMessage()]);
    }
}

/**
 * Lida com a exclusão da foto de uma fila.
 */
function apiExcluirFotoFila(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo); // Protege o endpoint
    $input = json_decode(file_get_contents('php://input'), true);
    //$filaId = $input['filaId'] ?? null;
    $fotoId = $input['fotoId'] ?? null;

    //if (!$filaId) {
    if (!$fotoId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O ID da fila é obrigatório.']);
        return;
    }

    try {
        // if ($repo->deleteFilaPhoto((int) $filaId)) {
        $repo->removerFotoPorId((int) $fotoId);
        echo json_encode(['success' => true, 'message' => 'Foto removida com sucesso!']);
        /* } else {
            echo json_encode(['success' => false, 'message' => 'Não foi possível remover a foto.']);
        }*/
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao remover a foto: ' . $e->getMessage()]);
    }
}

function apiGetFotosDaFila(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo);
    $filaId = (int) ($_GET['filaId'] ?? 0);

    if ($filaId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da fila inválido.']);
        return;
    }
    $fotos = $repo->findFotosByFilaId($filaId);
    echo json_encode(['success' => true, 'data' => $fotos]);
}

function apiGetDadosNovaEntrada(EntradaRepository $repo, UsuarioRepository $userRepo)
{
    getAuthenticatedUser($userRepo);

    $cameras = $repo->getCamaraOptions();
    $enderecos = $repo->getEnderecoOptions();

    echo json_encode([
        'success' => true,
        'cameras' => $cameras,
        'enderecos' => $enderecos,
    ]);
}

/**
 * Salva a leitura de uma entrada, criando um lote/item e alocando-o.
 */
function apiSalvarLeituraEntrada(EntradaRepository $repo, UsuarioRepository $userRepo)
{
    $user = getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);

    $enderecoId = $input['enderecoId'] ?? null;
    $leitura = $input['leitura'] ?? null;

    if (!$enderecoId || !$leitura || !is_array($leitura)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados de entrada inválidos.']);
        return;
    }

    try {
        $repo->salvarEntrada((int) $enderecoId, (int) $user['usu_codigo'], $leitura);
        echo json_encode(['success' => true, 'message' => 'Entrada salva com sucesso!']);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("API Error in salvarEntrada: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar os itens: ' . $e->getMessage()]);
    }
}

/**
 * @doc: Endpoint da API para buscar todas as câmaras de estoque.
 * Responde a requisições GET.
 * @param \App\Estoque\CamaraRepository $repo Repositório para acesso aos dados das câmaras.
 */
function apiGetCamaras(CamaraRepository $repo)
{
    // Apenas responde a requisições do tipo GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Content-Type: application/json', true, 405); // Method Not Allowed
        echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
        return;
    }

    try {
        $camaras = $repo->findAll();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'data' => $camaras]);
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar câmaras: ' . $e->getMessage()]);
    }
}

function apiGetEnderecosPorCamara(EnderecoRepository $repo)
{
    // Apenas responde a requisições do tipo GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Content-Type: application/json', true, 405); // Method Not Allowed
        echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
        return;
    }

    // Valida se o parâmetro camara_id foi enviado
    if (!isset($_GET['camara_id'])) {
        header('Content-Type: application/json', true, 400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'ID da câmara não fornecido.']);
        return;
    }

    $camaraId = (int) $_GET['camara_id'];

    try {
        // A chamada principal que vai buscar os dados
        $enderecos = $repo->findByCamaraId($camaraId);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'data' => $enderecos]);
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar endereços: ' . $e->getMessage()]);
    }
}

/**
 * @doc: Endpoint para registrar a entrada de um item (lido via QR Code) em um endereço de estoque.
 */
/* function apiRegistrarEntradaEstoque(EstoqueRepository $repo)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json', true, 405);
        echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $enderecoId = $input['endereco_id'] ?? null;
    $qrCode = $input['qrcode'] ?? null;
    $usuarioId = $input['usuario_id'] ?? 1; // Idealmente, o ID do usuário logado viria do token

    if (!$enderecoId || !$qrCode) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['status' => 'error', 'message' => 'Endereco ID e QR Code são obrigatórios.']);
        return;
    }

    try {
        $resultado = $repo->alocarItemPorQrCode($enderecoId, $qrCode, $usuarioId);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Item alocado com sucesso!', 'data' => $resultado]);
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} */

function apiRegistrarEntradaEstoque($carregamentoRepo, $estoqueRepo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $enderecoId = $input['endereco_id'] ?? null;
    $qrCode = $input['qrcode'] ?? null;
    $usuarioId = $input['usuario_id'] ?? 1; // TO DO: Obter o ID do usuário a partir do token de sessão

    if (!$enderecoId || !$qrCode) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success' => false, 'message' => 'Endereço, QR Code e Usuário são obrigatórios.']);
        return;
    }

    try {
        // Passo 1: Validar o QR Code usando a sua função existente
        $validacao = $carregamentoRepo->validarQrCode($qrCode);

        if (!$validacao['success']) {
            // Se a validação falhar, devolve a mensagem de erro da própria função
            header('Content-Type: application/json', true, 404); // Not Found
            echo json_encode($validacao);
            return;
        }

        // Extrai o ID do item que precisamos para alocar
        $loteItemId = $validacao['lote_item_id'];

        // Passo 2: Alocar o item no endereço
        $novaAlocacaoId = $estoqueRepo->alocarItem($enderecoId, $loteItemId, $usuarioId);

        // Se tudo correu bem, devolve uma resposta de sucesso
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Entrada registrada com sucesso!',
            'data' => [ // Devolvemos os dados para o app poder mostrar na lista
                'alocacao_id' => $novaAlocacaoId,
                'produto' => $validacao['produto'],
                'lote' => $validacao['lote']
            ]
        ]);

    } catch (Exception $e) {
        // Captura qualquer erro (ex: item já alocado) e envia uma resposta clara
        header('Content-Type: application/json', true, 409); // Conflict
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function apiExcluirAlocacao($estoqueRepo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $alocacaoId = $input['alocacao_id'] ?? null;

    if (!$alocacaoId) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success' => false, 'message' => 'ID da alocação não fornecido.']);
        return;
    }

    try {
        $estoqueRepo->excluirAlocacao((int) $alocacaoId);
        echo json_encode(['success' => true, 'message' => 'Alocação excluída com sucesso.']);
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function apiEditarQuantidadeAlocacao($estoqueRepo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $alocacaoId = $input['alocacao_id'] ?? null;
    $novaQuantidade = $input['nova_quantidade'] ?? null;

    if (!$alocacaoId || $novaQuantidade === null) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success' => false, 'message' => 'ID da alocação e nova quantidade são obrigatórios.']);
        return;
    }

    try {
        $estoqueRepo->editarQuantidade((int) $alocacaoId, (float) $novaQuantidade);
        echo json_encode(['success' => true, 'message' => 'Quantidade atualizada com sucesso.']);
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function apiGetEntradasDoDia($estoqueRepo)
{
    $enderecoId = $_GET['endereco_id'] ?? null;

    if (!$enderecoId) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success' => false, 'message' => 'ID do endereço não fornecido.']);
        return;
    }

    try {
        $entradas = $estoqueRepo->findEntradasDoDiaPorEndereco((int) $enderecoId);
        echo json_encode(['success' => true, 'data' => $entradas]);
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function apiGetOrdensProntas(OrdemExpedicaoRepository $repo)
{
    getAuthenticatedUser($GLOBALS['usuarioRepo']);
    try {
        // Chamamos o método que acabámos de criar no repositório correto
        $ordens = $repo->findProntasParaApi();
        echo json_encode(['success' => true, 'data' => $ordens]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function apiCriarCarregamentoDeOe(CarregamentoRepository $repo, UsuarioRepository $userRepo)
{
    $usuario = getAuthenticatedUser($userRepo);
    $input = json_decode(file_get_contents('php://input'), true);
    $oeId = $input['oe_id'] ?? null;

    if (!$oeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da Ordem de Expedição não fornecido.']);
        return;
    }

    try {
        // Reutilizamos o método que já existe no seu CarregamentoRepository
        $novoCarregamentoId = $repo->createCarregamentoFromOE((int) $oeId, $usuario['usu_codigo']);
        echo json_encode(['success' => true, 'message' => 'Carregamento criado com sucesso!', 'carregamentoId' => $novoCarregamentoId]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function apiGetDetalhesOe(OrdemExpedicaoRepository $repo)
{
    getAuthenticatedUser($GLOBALS['usuarioRepo']);
    $oeId = $_GET['oe_id'] ?? null;

    if (!$oeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da OE não fornecido.']);
        return;
    }

    try {
        $detalhes = $repo->findDetalhesParaCarregamento((int) $oeId);
        if ($detalhes) {
            echo json_encode(['success' => true, 'data' => $detalhes]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Ordem de Expedição não encontrada.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}