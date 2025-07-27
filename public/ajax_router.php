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
use App\Entidades\EntidadeRepository;

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
    $produtoRepo = new ProdutoRepository($pdo); // Cria a instância do repositório para Produto
    $entidadeRepo = new EntidadeRepository($pdo);// Cria a instância do repositório para Entidade
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
    case 'listarProdutosPrimarios':
        listarProdutosPrimarios($produtoRepo);
        break;

    // --- ROTAS DE ENTIDADES ---
    case 'listarEntidades':
        listarEntidades($entidadeRepo);
        break;
    case 'getEntidade':
        getEntidade($entidadeRepo);
        break;
    case 'salvarEntidade':
        salvarEntidade($entidadeRepo, $_SESSION['codUsuario']);
        break;
    case 'inativarEntidade':
        inativarEntidade($entidadeRepo);
        break;
    case 'listarEnderecos':
        listarEnderecos($entidadeRepo);
        break;
    case 'getEndereco':
        getEndereco($entidadeRepo);
        break;
    case 'salvarEndereco':
        salvarEndereco($entidadeRepo, $_SESSION['codUsuario']);
        break;
    case 'excluirEndereco':
        excluirEndereco($entidadeRepo);
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

function listarProdutosPrimarios(ProdutoRepository $repo)
{
    $produtos = $repo->findPrimarios();
    if ($produtos) {
        echo json_encode(['success' => true, 'data' => $produtos]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhum produto primário encontrado.']);
    }
}

// --- FUNÇÕES DE CONTROLE PARA ENTIDADES ---
function listarEntidades(EntidadeRepository $repo)
{
    try {
        $output = $repo->findAllForDataTable($_POST);
        echo json_encode($output);
    } catch (Exception $e) {
        // Tratar erro e retornar JSON formatado para DataTables
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function getEntidade(EntidadeRepository $repo)
{
    $id = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido fornecido.']);
        return;
    }
    $entidade = $repo->find($id);
    if ($entidade) {
        echo json_encode(['success' => true, 'data' => $entidade]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Entidade não encontrada.']);
    }
}

function salvarEntidade(EntidadeRepository $repo, int $userId)
{
    $id = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);
    try {
        if ($id) {
            $repo->update($id, $_POST, $userId);
            $message = 'Atualizado com sucesso!';
        } else {
            $newId = $repo->create($_POST, $userId);
            $message = 'Cadastrado com sucesso!';
        }
        echo json_encode(['success' => true, 'message' => $message, 'ent_codigo' => $newId ?? $id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function inativarEntidade(EntidadeRepository $repo)
{
    $id = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido fornecido.']);
        return;
    }
    try {
        if ($repo->inactivate($id)) {
            echo json_encode(['success' => true, 'message' => 'Entidade inativada com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Entidade não encontrada ou já inativa.']);
        }
    } catch (Exception $e) {
        error_log("Erro em inativarEntidade: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor.']);
    }
}

function listarEnderecos(EntidadeRepository $repo)
{
    $id = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID de entidade inválido.']);
        return;
    }
    try {
        $enderecos = $repo->findEnderecosByEntidadeId($id);
        echo json_encode(['data' => $enderecos]);
    } catch (PDOException $e) {
        error_log("Erro em listarEnderecos: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar endereços: ' . $e->getMessage()]);
    }
}

function getEndereco(EntidadeRepository $repo)
{
    // CORREÇÃO: Ler do POST e usar o nome de campo correto 'end_codigo'.
    $id = filter_input(INPUT_POST, 'end_codigo', FILTER_VALIDATE_INT);

    if (!$id) {
        // Adicionando tratamento de erro que estava faltando
        echo json_encode(['success' => false, 'message' => 'ID de endereço inválido.']);
        return;
    }

    try {
        $endereco = $repo->findEndereco($id);
        if ($endereco) {
            echo json_encode(['success' => true, 'data' => $endereco]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Endereço não encontrado.']);
        }
    } catch (PDOException $e) {
        // Adicionando um bloco try/catch por segurança
        error_log("Erro em getEndereco: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro de banco de dados ao buscar endereço.']);
    }
}

function salvarEndereco(EntidadeRepository $repo, int $userId)
{
    try {
        $repo->saveEndereco($_POST, $userId);
        echo json_encode(['success' => true, 'message' => 'Endereço salvo com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirEndereco(EntidadeRepository $repo)
{
    $id = filter_input(INPUT_POST, 'end_codigo', FILTER_VALIDATE_INT);
    if (!$id) { /* tratamento de erro */
    }
    if ($repo->deleteEndereco($id)) {
        echo json_encode(['success' => true, 'message' => 'Endereço excluído com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir endereço.']);
    }
}
