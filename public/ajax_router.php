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
use App\Usuarios\UsuarioRepository;
use App\Lotes\LoteRepository;
use App\Permissions\PermissionRepository;
use App\Labels\LabelService;
use App\Etiquetas\TemplateRepository;
use App\Etiquetas\RegraRepository;
use App\Core\AuditLogRepository;
use App\Core\BackupService;
use App\Carregamentos\CarregamentoRepository;

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
    $entidadeRepo = new EntidadeRepository($pdo); // Cria a instância do repositório para Entidade
    $usuarioRepo = new UsuarioRepository($pdo); // Cria a instância do repositório para Usuário
    $loteRepo = new LoteRepository($pdo);
    $permissionRepo = new PermissionRepository($pdo);
    $templateRepo = new TemplateRepository($pdo);
    $regraRepo = new RegraRepository($pdo);
    $auditLogRepo = new AuditLogRepository($pdo);
    $carregamentoRepo = new CarregamentoRepository($pdo);
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
    case 'getProdutoOptions':
        getProdutoOptions($produtoRepo);
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
    case 'getFornecedorOptions':
        getFornecedorOptions($entidadeRepo);
        break;
    case 'getClienteOptions':
        getClienteOptions($entidadeRepo);
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

    // --- ROTAS DE USUÁRIOS ---
    case 'listarUsuarios':
        listarUsuarios($usuarioRepo);
        break;
    case 'getUsuario':
        getUsuario($usuarioRepo);
        break;
    case 'salvarUsuario':
        salvarUsuario($usuarioRepo);
        break;
    case 'excluirUsuario':
        excluirUsuario($usuarioRepo, $_SESSION['codUsuario']);
        break;
    case 'getUsuariosOptions': // Rota de apoio para o filtro de auditoria
        getUsuariosOptions($usuarioRepo);
        break;

    // --- ROTAS DE LOTES ---
    case 'listarLotes':
        listarLotes($loteRepo);
        break;
    case 'buscarLote':
        buscarLote($loteRepo);
        break;
    case 'getProximoNumeroLote':
        getProximoNumeroLote($loteRepo);
        break;
    case 'salvarLoteHeader':
        salvarLoteHeader($loteRepo, $_SESSION['codUsuario']);
        break;
    case 'salvarLoteItem':
        salvarLoteItem($loteRepo);
        break;
    case 'excluirLoteItem':
        excluirLoteItem($loteRepo);
        break;
    case 'excluirLote':
        excluirLote($loteRepo);
        break;
    case 'finalizarLote':
        finalizarLote($loteRepo);
        break;
    case 'finalizarLoteParcialmente':
        finalizarLoteParcialmente($loteRepo);
        break;
    case 'cancelarLote':
        cancelarLote($loteRepo);
        break;
    case 'getLoteItem':
        getLoteItem($loteRepo);
        break;
    case 'getItensDeEstoqueOptions':
        getItensDeEstoqueOptions($loteRepo); // Usa o LoteRepository
        break;
    case 'getLotesPorProduto':
        getLotesPorProduto($loteRepo);
        break;
    case 'listarEstoque':
        listarEstoque($loteRepo);
        break;

    // --- ROTA DE PERMISSÕES ---
    case 'salvarPermissoes':
        salvarPermissoes($permissionRepo);
        break;

    // --- ROTA DE ETIQUETAS ---  
    case 'imprimirEtiquetaItem':
        imprimirEtiquetaItem($pdo); // A função precisa da conexão PDO
        break;

    // --- ROTAS DE TEMPLATES DE ETIQUETA ---
    case 'listarTemplates':
        listarTemplates($templateRepo);
        break;
    case 'getTemplate':
        getTemplate($templateRepo);
        break;
    case 'salvarTemplate':
        salvarTemplate($templateRepo);
        break;
    case 'excluirTemplate':
        excluirTemplate($templateRepo);
        break;

    // --- ROTAS DE REGRAS DE ETIQUETA ---
    case 'listarRegras':
        listarRegras($regraRepo);
        break;
    case 'getRegra':
        getRegra($regraRepo);
        break;
    case 'salvarRegra':
        salvarRegra($regraRepo);
        break;
    case 'excluirRegra':
        excluirRegra($regraRepo);
        break;
    case 'getTemplateOptions': // Rota de apoio para o formulário
        getTemplateOptions($regraRepo);
        break;

    // --- ROTAS DE AUDITORIA ---
    case 'listarLogs':
        listarLogs($auditLogRepo);
        break;
    case 'getLogDetalhes':
        getLogDetalhes($auditLogRepo);
        break;

    // --- ROTA DE BACKUP ---
    case 'criarBackup':
        criarBackup(); // Não precisa de passar o repositório
        break;

    // --- ROTA DE CARREGAMENTOS ---
    case 'listarCarregamentos':
        listarCarregamentos($carregamentoRepo);
        break;
    case 'getProximoNumeroCarregamento':
        getProximoNumeroCarregamento($carregamentoRepo);
        break;
    case 'salvarCarregamentoHeader':
        salvarCarregamentoHeader($carregamentoRepo, $_SESSION['codUsuario']);
        break;
    case 'cancelarCarregamento':
        cancelarCarregamento($carregamentoRepo);
        break;
    case 'excluirCarregamento':
        excluirCarregamento($carregamentoRepo);
        break;
    case 'reativarCarregamento':
        reativarCarregamento($carregamentoRepo);
        break;
    case 'reabrirCarregamento':
        reabrirCarregamento($carregamentoRepo);
        break;
    case 'adicionarItemCarregamento':
        adicionarItemCarregamento($carregamentoRepo);
        break;
    case 'removerItemCarregamento':
        removerItemCarregamento($carregamentoRepo);
        break;
    case 'getCarregamentoDetalhes': // Para recarregar os dados
        getCarregamentoDetalhes($carregamentoRepo);
        break;
    case 'adicionarFila':
        adicionarFila($carregamentoRepo);
        break;
    case 'adicionarItemAFila':
        adicionarItemAFila($carregamentoRepo);
        break;
    case 'getDadosConferencia':
        getDadosConferencia($carregamentoRepo);
        break;
    case 'confirmarBaixaEstoque':
        confirmarBaixaEstoque($carregamentoRepo);
        break;
    case 'salvarFilaComposta':
        salvarFilaComposta($carregamentoRepo);
        break;
    case 'removerFilaCompleta':
        removerFilaCompleta($carregamentoRepo);
        break;
    case 'getFilaDetalhes':
        getFilaDetalhes($carregamentoRepo);
        break;
    case 'atualizarFilaComposta':
        atualizarFilaComposta($carregamentoRepo);
        break;

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

function getProdutoOptions(ProdutoRepository $repo)
{
    $tipo = $_GET['tipo_embalagem'] ?? 'Todos';
    echo json_encode(['success' => true, 'data' => $repo->getProdutoOptions($tipo)]);
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

function getFornecedorOptions(EntidadeRepository $repo)
{
    echo json_encode(['success' => true, 'data' => $repo->getFornecedorOptions()]);
}

function getClienteOptions(EntidadeRepository $repo)
{
    echo json_encode(['success' => true, 'data' => $repo->getClienteOptions()]);
}

// --- FUNÇÕES DE CONTROLE PARA USUARIOS ---
function listarUsuarios(UsuarioRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function getUsuario(UsuarioRepository $repo)
{
    $id = filter_input(INPUT_POST, 'usu_codigo', FILTER_VALIDATE_INT);
    if (!$id) { /* tratamento de erro */
    }
    echo json_encode(['success' => true, 'data' => $repo->find($id)]);
}

function salvarUsuario(UsuarioRepository $repo)
{
    $id = filter_input(INPUT_POST, 'usu_codigo', FILTER_VALIDATE_INT);

    // Lógica para limitar a criação de novos usuários
    if (!$id) { // Só executa esta verificação se for um NOVO usuário (sem ID)
        $totalUsuarios = $repo->countAll();
        if ($totalUsuarios >= 4) {
            echo json_encode(['success' => false, 'message' => 'Limite máximo de 4 usuários atingido. Não é possível adicionar mais usuários.']);
            return; // Interrompe a execução
        }
    }

    try {
        if ($id) { // Editando
            $repo->update($id, $_POST);
            $message = 'Usuário atualizado com sucesso!';
        } else { // Cadastrando
            $repo->create($_POST);
            $message = 'Usuário cadastrado com sucesso!';
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            echo json_encode(['success' => false, 'message' => 'Erro: Este login já está em uso.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
        }
    }
}

function excluirUsuario(UsuarioRepository $repo, int $loggedInUserId)
{
    $id = filter_input(INPUT_POST, 'usu_codigo', FILTER_VALIDATE_INT);
    if (!$id) { /* tratamento de erro */
    }

    if ($id === $loggedInUserId) {
        echo json_encode(['success' => false, 'message' => 'Você não pode excluir seu próprio perfil.']);
        return;
    }

    if ($repo->delete($id)) {
        echo json_encode(['success' => true, 'message' => 'Usuário excluído com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir usuário.']);
    }
}

function getUsuariosOptions(UsuarioRepository $repo)
{
    // Usamos a nova função que criámos no UsuarioRepository
    $usuarios = $repo->findAllForOptions();
    echo json_encode(['success' => true, 'data' => $usuarios]);
}

// --- FUNÇÕES DE CONTROLE PARA LOTES ---
function listarLotes(LoteRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function buscarLote(LoteRepository $repo)
{
    $id = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    $lote = $repo->findLoteComItens($id);
    echo json_encode(['success' => !!$lote, 'data' => $lote]);
}

function getProximoNumeroLote(LoteRepository $repo)
{
    echo json_encode(['success' => true, 'proximo_numero' => $repo->getNextNumero()]);
}

function salvarLoteHeader(LoteRepository $repo, int $userId)
{
    try {
        $loteId = $repo->saveHeader($_POST, $userId);
        echo json_encode(['success' => true, 'message' => 'Cabeçalho do lote salvo com sucesso!', 'novo_lote_id' => $loteId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar cabeçalho: ' . $e->getMessage()]);
    }
}

function salvarLoteItem(LoteRepository $repo)
{
    try {
        $repo->saveItem($_POST);
        echo json_encode(['success' => true, 'message' => 'Item salvo com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar item: ' . $e->getMessage()]);
    }
}

function excluirLoteItem(LoteRepository $repo)
{
    $id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    if ($repo->deleteItem($id)) {
        echo json_encode(['success' => true, 'message' => 'Item excluído com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir item.']);
    }
}

function excluirLote(LoteRepository $repo)
{
    $id = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    try {
        $repo->delete($id);
        echo json_encode(['success' => true, 'message' => 'Lote excluído com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir lote: ' . $e->getMessage()]);
    }
}

function finalizarLote(LoteRepository $repo)
{
    $id = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    try {
        $repo->finalize($id);
        echo json_encode(['success' => true, 'message' => 'Lote finalizado e estoque gerado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function finalizarLoteParcialmente(LoteRepository $repo)
{
    
    // =======================================================
    // == DEPURACAO PASSO 2: VERIFICAR DADOS RECEBIDOS NO PHP ==
    // =======================================================
    error_log("--- DEBUG Roteador ---: Ação 'finalizarLoteParcialmente' recebida.");
    error_log("Dados recebidos via POST: " . print_r($_POST, true));
    // =======================================================

    // Validação básica dos dados recebidos do JavaScript
    $loteId = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    $itens = $_POST['itens'] ?? [];

    if (!$loteId || empty($itens) || !is_array($itens)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos para finalização.']);
        return;
    }

    try {
        $repo->finalizeParcialmente($loteId, $itens);
        echo json_encode(['success' => true, 'message' => 'Itens finalizados e adicionados ao estoque com sucesso!']);
    } catch (Exception $e) {
        // Captura qualquer erro vindo do repositório e envia como uma resposta amigável
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getLoteItem(LoteRepository $repo)
{
    $id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID do item inválido.']);
        return;
    }

    $item = $repo->findItem($id);
    if ($item) {
        echo json_encode(['success' => true, 'data' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item não encontrado.']);
    }
}

function cancelarLote(LoteRepository $repo)
{
    $loteId = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    if (!$loteId) {
        echo json_encode(['success' => false, 'message' => 'ID do lote inválido.']);
        return;
    }

    try {
        $repo->cancelar($loteId);
        echo json_encode(['success' => true, 'message' => 'Lote cancelado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getItensDeEstoqueOptions(LoteRepository $repo)
{
    $term = $_GET['term'] ?? '';
    $itens = $repo->findItensEmEstoqueParaSelect($term);
    // O Select2 espera um array com a chave 'results'
    echo json_encode(['results' => $itens]);
}

function getLotesPorProduto(LoteRepository $repo)
{
    $produtoId = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);
    if (!$produtoId) {
        echo json_encode(['results' => []]);
        return;
    }
    $lotes = $repo->findLotesDisponiveisPorProduto($produtoId);
    echo json_encode(['results' => $lotes]);
}

function listarEstoque(LoteRepository $repo)
{
    try {
        $output = $repo->getVisaoGeralEstoque($_POST);
        echo json_encode($output);
    } catch (Exception $e) {
        // Em caso de erro, retorna uma resposta formatada para o DataTables
        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 1),
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => [],
            "error" => $e->getMessage()
        ]);
    }
}


function salvarPermissoes(PermissionRepository $repo)
{
    // Apenas o Admin pode salvar permissões. Verificação dupla de segurança.
    if (!isset($_SESSION['tipoUsuario']) || $_SESSION['tipoUsuario'] !== 'Admin') {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        return;
    }

    try {
        // O formulário envia os dados no array 'permissoes'
        $permissoes = $_POST['permissoes'] ?? [];
        $repo->save($permissoes);
        echo json_encode(['success' => true, 'message' => 'Permissões salvas com sucesso!']);
    } catch (Exception $e) {
        error_log("Erro em salvarPermissoes: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao salvar as permissões.']);
    }
}

// --- FUNÇÃO DE CONTROLE PARA ETIQUETAS ---
function imprimirEtiquetaItem(PDO $pdo)
{
    // Validação dos dados de entrada
    $loteItemId = filter_input(INPUT_POST, 'loteItemId', FILTER_VALIDATE_INT);
    // O cliente pode não ser selecionado, então permitimos nulo
    $clienteId = filter_input(INPUT_POST, 'clienteId', FILTER_VALIDATE_INT);
    if ($clienteId === false)
        $clienteId = null; // Garante que seja nulo se não for um inteiro válido

    if (!$loteItemId) {
        echo json_encode(['success' => false, 'message' => 'ID do item do lote não fornecido.']);
        return;
    }


    // Validação básica dos dados de entrada
    if (!isset($_POST['loteItemId']) || empty($_POST['loteItemId'])) {
        echo json_encode(['success' => false, 'message' => 'ID do item do lote não fornecido.']);
        return; // Usamos return em vez de exit
    }

    // $loteItemId = (int)$_POST['loteItemId'];

    try {
        // Agora usamos o 'use' do topo, fica mais limpo
        $labelService = new LabelService($pdo);

        // Gera o código ZPL usando o serviço
        //$zpl = $labelService->gerarZplParaItem($loteItemId);

        //Recebe o array com 'zpl' e 'filename'
        //  $labelData = $labelService->gerarZplParaItem($loteItemId);

        // A chamada da função agora inclui o ID do cliente
        $labelData = $labelService->gerarZplParaItem($loteItemId, $clienteId);


        //if ($zpl === null) {
        if ($labelData === null) {
            echo json_encode(['success' => false, 'message' => 'Não foi possível gerar o ZPL. Verifique se o item existe e o template está configurado.']);
            return;
        }

        // Extrai os dados do array
        $zpl = $labelData['zpl'];
        $filename = $labelData['filename'];

        // --- Interação com a API da Labelary para converter ZPL em PDF ---
        // $curl = curl_init('http://api.labelary.com/v1/printers/8dpmm/labels/4x6/0/');
        // Esta é a linha corrigida
        $curl = curl_init('http://api.labelary.com/v1/printers/12dpmm/labels/4x7/0/');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $zpl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: application/pdf']);

        $pdfContent = curl_exec($curl);

        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {
            echo json_encode(['success' => false, 'message' => 'Erro da API Labelary: ' . $pdfContent]);
            curl_close($curl);
            return;
        }

        curl_close($curl);

        // --- Salvar o PDF temporariamente no servidor ---
        $tempDir = __DIR__ . '/temp_labels/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        //$filename = 'etiqueta-' . uniqid() . '.pdf';

        $filePath = $tempDir . $filename;

        file_put_contents($filePath, $pdfContent);

        $publicUrl = 'temp_labels/' . $filename;

        echo json_encode(['success' => true, 'pdfUrl' => $publicUrl]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
    }
}

// --- FUNÇÕES DE CONTROLE PARA TEMPLATES DE ETIQUETA ---

function listarTemplates(TemplateRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function getTemplate(TemplateRepository $repo)
{
    $id = filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }
    $template = $repo->find($id);
    if ($template) {
        echo json_encode(['success' => true, 'data' => $template]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Template não encontrado.']);
    }
}

function salvarTemplate(TemplateRepository $repo)
{
    $id = filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT);
    try {
        if ($id) {
            $repo->update($id, $_POST);
            $message = 'Template atualizado com sucesso!';
        } else {
            $repo->create($_POST);
            $message = 'Template cadastrado com sucesso!';
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar o template: ' . $e->getMessage()]);
    }
}

function excluirTemplate(TemplateRepository $repo)
{
    $id = filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }
    try {
        if ($repo->delete($id)) {
            echo json_encode(['success' => true, 'message' => 'Template excluído com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Template não encontrado.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir o template. Ele pode estar em uso por alguma regra.']);
    }
}

// --- FUNÇÕES DE CONTROLE PARA REGRAS DE ETIQUETA ---

function listarRegras(RegraRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function getRegra(RegraRepository $repo)
{
    $id = filter_input(INPUT_POST, 'regra_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }
    $regra = $repo->find($id);
    echo json_encode(['success' => !!$regra, 'data' => $regra]);
}

function salvarRegra(RegraRepository $repo)
{
    try {
        $repo->save($_POST);
        echo json_encode(['success' => true, 'message' => 'Regra salva com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar a regra: ' . $e->getMessage()]);
    }
}

function excluirRegra(RegraRepository $repo)
{
    $id = filter_input(INPUT_POST, 'regra_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }
    try {
        if ($repo->delete($id)) {
            echo json_encode(['success' => true, 'message' => 'Regra excluída com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Regra não encontrada.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir a regra.']);
    }
}

function getTemplateOptions(RegraRepository $repo)
{
    echo json_encode(['success' => true, 'data' => $repo->getTemplateOptions()]);
}

// --- FUNÇÕES DE CONTROLE PARA AUDITORIA ---

function listarLogs(AuditLogRepository $repo)
{
    try {
        // Tenta executar a busca normalmente
        $output = $repo->findAllForDataTable($_POST);
        echo json_encode($output);
    } catch (Exception $e) {
        // Em caso de qualquer erro (ex: erro de SQL no repositório),
        // regista o erro no log do servidor para podermos depurar
        error_log("Erro na API listarLogs: " . $e->getMessage());

        // E envia uma resposta vazia e bem formatada para o DataTables
        // para evitar o erro de JavaScript que você viu.
        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 1),
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => []
        ]);
    }
}

function getLogDetalhes(AuditLogRepository $repo)
{
    $logId = filter_input(INPUT_POST, 'log_id', FILTER_VALIDATE_INT);
    if (!$logId) {
        echo json_encode(['success' => false, 'message' => 'ID do log inválido.']);
        return;
    }
    $detalhes = $repo->getLogDetailsById($logId);
    if ($detalhes) {
        echo json_encode(['success' => true, 'data' => $detalhes]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Detalhes do log não encontrados.']);
    }
}

// --- FUNÇÃO DE CONTROLE PARA BACKUP ---

function criarBackup()
{
    try {
        // 1. Carrega a configuração da base de dados.
        $dbConfig = require __DIR__ . '/../config/database.php';

        // 2. Passa a configuração para o serviço ao criá-lo (Injeção de Dependência).
        $backupService = new \App\Core\BackupService($dbConfig);
        $filename = $backupService->gerarBackup();

        echo json_encode(['success' => true, 'filename' => $filename]);
    } catch (\Exception $e) {
        error_log("Erro ao criar backup: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- FUNÇÃO DE CONTROLE PARA CARREGAMENTOS ---

function listarCarregamentos(CarregamentoRepository $repo)
{
    try {
        $output = $repo->findAllForDataTable($_POST);
        echo json_encode($output);
    } catch (Exception $e) {
        error_log("Erro na API listarCarregamentos: " . $e->getMessage());
        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 1),
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => []
        ]);
    }
}

function getProximoNumeroCarregamento(CarregamentoRepository $repo)
{
    try {
        $proximoNumero = $repo->getNextNumeroCarregamento();
        echo json_encode(['success' => true, 'proximo_numero' => $proximoNumero]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function salvarCarregamentoHeader(CarregamentoRepository $repo, int $userId)
{
    // A lógica de salvar (criar ou editar) já está a ser preparada no repositório.
    // Por agora, vamos focar na criação.
    try {
        $newId = $repo->createHeader($_POST, $userId);
        echo json_encode(['success' => true, 'message' => 'Carregamento criado com sucesso!', 'carregamento_id' => $newId]);
    } catch (Exception $e) {
        error_log("Erro em salvarCarregamentoHeader: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar o carregamento.']);
    }
}

function adicionarItemCarregamento(CarregamentoRepository $repo)
{
    try {
        $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        $loteItemId = filter_input(INPUT_POST, 'lote_item_id', FILTER_VALIDATE_INT);
        $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_FLOAT);
        if (!$carregamentoId || !$loteItemId || !$quantidade)
            throw new Exception("Dados inválidos.");

        $repo->adicionarItem($carregamentoId, $loteItemId, $quantidade);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function removerItemCarregamento(CarregamentoRepository $repo)
{
    try {
        $carItemId = filter_input(INPUT_POST, 'car_item_id', FILTER_VALIDATE_INT);
        if (!$carItemId)
            throw new Exception("ID do item inválido.");

        $repo->removerItem($carItemId);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getDadosConferencia(CarregamentoRepository $repo)
{
    $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
    if (!$carregamentoId) {
        echo json_encode(['success' => false, 'message' => 'ID do carregamento inválido.']);
        return;
    }
    try {
        $itens = $repo->getItensParaConferencia($carregamentoId);
        echo json_encode(['success' => true, 'data' => $itens]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function confirmarBaixaEstoque(CarregamentoRepository $repo)
{
    $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
    $forcarBaixa = isset($_POST['forcar_baixa']) && $_POST['forcar_baixa'] === 'true';

    if (!$carregamentoId) {
        echo json_encode(['success' => false, 'message' => 'ID do carregamento inválido.']);
        return;
    }

    try {
        $repo->confirmarBaixaDeEstoque($carregamentoId, $forcarBaixa);
        echo json_encode(['success' => true, 'message' => 'Carregamento finalizado e baixa de estoque realizada com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getCarregamentoDetalhes(CarregamentoRepository $repo)
{
    $carregamentoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$carregamentoId) {
        echo json_encode(['success' => false, 'message' => 'ID de carregamento inválido.']);
        return;
    }
    $data = $repo->findCarregamentoComFilasEItens($carregamentoId);
    echo json_encode(['success' => !!$data, 'data' => $data]);
}

function adicionarFila(CarregamentoRepository $repo)
{
    try {
        $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
        if (!$carregamentoId || !$clienteId)
            throw new Exception("Dados inválidos.");

        $filaId = $repo->adicionarFila($carregamentoId, $clienteId);
        echo json_encode(['success' => true, 'message' => 'Cliente adicionado à fila com sucesso.', 'fila_id' => $filaId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function adicionarItemAFila(CarregamentoRepository $repo)
{
    try {
        $filaId = filter_input(INPUT_POST, 'fila_id', FILTER_VALIDATE_INT);
        $loteItemId = filter_input(INPUT_POST, 'lote_item_id', FILTER_VALIDATE_INT);
        $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_FLOAT);
        $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);

        if (!$filaId || !$loteItemId || !$quantidade || !$carregamentoId || !$clienteId) {
            throw new Exception("Dados inválidos fornecidos.");
        }
        $repo->adicionarItemAFila($filaId, $loteItemId, $quantidade, $carregamentoId, $clienteId);
        echo json_encode(['success' => true, 'message' => 'Item adicionado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function salvarFilaComposta(CarregamentoRepository $repo)
{
    try {
        $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        $filaDataJson = $_POST['fila_data'] ?? '[]';
        $filaData = json_decode($filaDataJson, true);

        if (!$carregamentoId || empty($filaData)) {
            throw new Exception("Dados inválidos para salvar a fila.");
        }

        $repo->salvarFilaComposta($carregamentoId, $filaData);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function removerFilaCompleta(CarregamentoRepository $repo)
{
    try {
        // Valida o ID recebido via POST
        $filaId = filter_input(INPUT_POST, 'fila_id', FILTER_VALIDATE_INT);
        if (!$filaId) {
            throw new Exception("ID de fila inválido.");
        }
        $repo->removerFilaCompleta($filaId);
        echo json_encode(['success' => true, 'message' => 'Fila removida com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getFilaDetalhes(CarregamentoRepository $repo)
{
    try {
        $filaId = filter_input(INPUT_POST, 'fila_id', FILTER_VALIDATE_INT);
        if (!$filaId) {
            throw new Exception("ID de fila inválido.");
        }
        $data = $repo->findFilaComClientesEItens($filaId);
        echo json_encode(['success' => !!$data, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function atualizarFilaComposta(CarregamentoRepository $repo)
{
    try {
        $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        $filaId = filter_input(INPUT_POST, 'fila_id', FILTER_VALIDATE_INT);
        $filaDataJson = $_POST['fila_data'] ?? '[]';
        $filaData = json_decode($filaDataJson, true);

        if (!$carregamentoId || !$filaId || !is_array($filaData)) {
            throw new Exception("Dados inválidos para atualizar a fila.");
        }

        $repo->atualizarFilaComposta($filaId, $carregamentoId, $filaData);
        echo json_encode(['success' => true, 'message' => 'Fila atualizada com sucesso!']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function cancelarCarregamento(CarregamentoRepository $repo)
{
    try {
        $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        if (!$carregamentoId) {
            throw new Exception("ID de carregamento inválido.");
        }
        $repo->cancelar($carregamentoId);
        echo json_encode(['success' => true, 'message' => 'Carregamento cancelado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirCarregamento(CarregamentoRepository $repo)
{
    try {
        $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        if (!$carregamentoId) {
            throw new Exception("ID de carregamento inválido.");
        }
        $repo->excluir($carregamentoId);
        echo json_encode(['success' => true, 'message' => 'Carregamento excluído permanentemente!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function reativarCarregamento(CarregamentoRepository $repo)
{
    try {
        $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        if (!$carregamentoId) {
            throw new Exception("ID de carregamento inválido.");
        }
        $repo->reativar($carregamentoId);
        echo json_encode(['success' => true, 'message' => 'Carregamento reativado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function reabrirCarregamento(CarregamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        $motivo = trim($_POST['motivo'] ?? '');
        if (!$id || empty($motivo)) {
            throw new Exception("Dados inválidos para reabertura.");
        }
        $repo->reabrir($id, $motivo);
        echo json_encode(['success' => true, 'message' => 'Carregamento reaberto com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}