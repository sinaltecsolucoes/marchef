<?php
// /public/ajax_router.php
// Ponto de entrada para todas as requisições AJAX do sistema.

require_once __DIR__ . '/../src/bootstrap.php';

// Usaremos um Autoloader simples por enquanto. No futuro, o Composer fará isso.
spl_autoload_register(function ($class) {
    // Converte o namespace em caminho de arquivo (App\Core\Database -> /src/Core/Database.php)
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
use App\Produtos\ProdutoRepository;

// --- Configurações Iniciais ---
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Roteamento Simples ---
// Pegamos a ação da URL, ex: /ajax_router.php?action=cadastrarProduto
$action = $_GET['action'] ?? '';

// Validação de CSRF para todas as requisições POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Erro de validação de segurança (CSRF).']);
        exit;
    }
}

// Inicialização centralizada
try {
    $pdo = Database::getConnection();
    $produtoRepo = new ProdutoRepository($pdo); // Cria a instância do repositório
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados.']);
    exit;
}

switch ($action) {
    // --- ROTAS DE PRODUTOS ---
    case 'listarProdutos':
        listarProdutos($produtoRepo);
        break;
    case 'getProduto':
        getProduto($produtoRepo);
        break;
    case 'cadastrarProduto':
        salvarProduto($produtoRepo);
        break;
    case 'editarProduto':
        salvarProduto($produtoRepo);
        break;
    case 'excluirProduto':
        excluirProduto($produtoRepo);
        break;
    case 'listarProdutosPrimarios': // <-- NOVA ROTA
        listarProdutosPrimarios($produtoRepo);
        break;

    // ... suas outras rotas (salvarPermissoes, etc.)

    default:
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
        exit;
}

// --- FUNÇÕES DE CONTROLE PARA PRODUTOS ---
function listarProdutos(ProdutoRepository $repo)
{
    $output = $repo->findAllForDataTable($_POST);
    echo json_encode($output);
}

function getProduto(ProdutoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'prod_codigo', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }
    $produto = $repo->find($id);
    if ($produto) {
        echo json_encode(['success' => true, 'data' => $produto]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado.']);
    }
}
function salvarProduto(ProdutoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'prod_codigo', FILTER_VALIDATE_INT);
    try {
        if ($id) { // Editando
            $success = $repo->update($id, $_POST);
            $message = 'Produto atualizado com sucesso!';
        } else { // Cadastrando
            $success = $repo->create($_POST);
            $message = 'Produto cadastrado com sucesso!';
        }
        echo json_encode(['success' => $success, 'message' => $message]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
}

function excluirProduto(ProdutoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'prod_codigo', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }
    try {
        if ($repo->delete($id)) {
            echo json_encode(['success' => true, 'message' => 'Produto excluído com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produto não encontrado ou já excluído.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: Este produto não pode ser excluído pois está em uso.']);
    }
}

function listarProdutosPrimarios(ProdutoRepository $repo) {
    $produtos = $repo->findPrimarios();
    if ($produtos) {
        echo json_encode(['success' => true, 'data' => $produtos]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhum produto primário encontrado.']);
    }
}