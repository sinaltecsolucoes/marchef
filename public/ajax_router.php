<?php
// /public/ajax_router.php
// Ponto de entrada para todas as requisições AJAX do sistema.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$action = $_REQUEST['action'] ?? 'NENHUMA';
error_log(" ROUTER ACIONADO. Ação: " . $action);


require_once __DIR__ . '/../src/bootstrap.php';
//require_once __DIR__ . '/libs/dompdf/vendor/autoload.php'; 

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
use App\Lotes\LoteNovoRepository;
use App\Estoque\CamaraRepository;
use App\Estoque\EnderecoRepository;
use App\OrdensExpedicao\OrdemExpedicaoRepository;
use App\Faturamento\FaturamentoRepository;
use App\CondicaoPagamento\CondicaoPagamentoRepository;
use App\FichasTecnicas\FichaTecnicaRepository;
use App\Core\RelatorioService;
use App\Estoque\MovimentoRepository;

// --- Configurações Iniciais ---
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// 1. SANITIZAÇÃO GLOBAL
// ============================================================================
// Converte todo o POST para maiúsculo automaticamente antes de qualquer processamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    // A função sanitize_upper() já está disponível via helpers.php (carregado no bootstrap)
    $_POST = sanitize_upper($_POST);
}
// ============================================================================

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
    $loteRepo = new LoteRepository($pdo); // Cria a instância do repositório para Lotes
    $loteNovoRepo = new LoteNovoRepository($pdo); // Cria a instância do repositório para Lotes Novos (novo modelo de Lotes)
    $permissionRepo = new PermissionRepository($pdo); // Cria a instância do repositório para Permissoes
    $templateRepo = new TemplateRepository($pdo); // Cria a instância do repositório para Templates das Etiquetas
    $regraRepo = new RegraRepository($pdo); // Cria a instância do repositório para Regras das Etiquetas
    $auditLogRepo = new AuditLogRepository($pdo); // Cria a instância do repositório para Auditoria de Logs
    $carregamentoRepo = new CarregamentoRepository($pdo); // Cria a instância do repositório para Carregamentos
    $camaraRepo = new CamaraRepository($pdo); // Cria a instância do repositório para Câmaras
    $enderecoRepo = new EnderecoRepository($pdo); // Cria a instância do repositório para Endereçamento das Câmaras
    $movimentoRepo = new MovimentoRepository($pdo); // Cria a instância do repositório para Movimentos de Produtos
    $ordemExpedicaoRepo = new OrdemExpedicaoRepository($pdo); //Cria a instância do repositorio para Ordens de Expedição
    $faturamentoRepo = new FaturamentoRepository($pdo); //Cria a instância do repositorio para Faturamento
    $condPagRepo = new CondicaoPagamentoRepository($pdo); //Cria a instância do repositorio para Condições de Pagamento
    $fichaTecnicaRepo = new FichaTecnicaRepository($pdo); //Cria a instância do repositorio para Fichas Técnicas
    $labelService = new LabelService($pdo); // Cria a instância do Repositorio para Serviço de Etiquetas
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
    case 'getSecundariosPorPrimario':
        getSecundariosPorPrimario($produtoRepo);
        break;
    case 'getProdutosSecundariosOptions':
        getProdutosSecundariosOptions($produtoRepo);
        break;
    case 'getMarcasOptions':
        getMarcasOptions($produtoRepo);
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
    case 'getTransportadoraOptions':
        getTransportadoraOptions($entidadeRepo);
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
        getItensDeEstoqueOptions($loteRepo);
        break;
    case 'getLotesPorProduto':
        getLotesPorProduto($loteRepo);
        break;
    case 'listarEstoque':
        listarEstoque($loteNovoRepo);
        break;
    case 'getDadosDoLoteItem':
        getDadosDoLoteItem($loteRepo);
        break;
    case 'reabrirLote':
        reabrirLote($loteRepo);
        break;
    case 'listarLotesNovos':
        listarLotesNovos($loteNovoRepo);
        break;
    case 'getProximoNumeroLoteNovo':
        getProximoNumeroLoteNovo($loteNovoRepo);
        break;
    case 'salvarLoteNovoHeader':
        salvarLoteNovoHeader($loteNovoRepo, $_SESSION['codUsuario']);
        break;
    case 'buscarLoteNovo':
        buscarLoteNovo($loteNovoRepo);
        break;
    case 'adicionarItemProducaoNovo':
        adicionarItemProducaoNovo($loteNovoRepo);
        break;
    case 'adicionarItemEmbalagemNovo':
        adicionarItemEmbalagemNovo($loteNovoRepo);
        break;
    case 'getItensEmbalagemNovo':
        getItensEmbalagemNovo($loteNovoRepo);
        break;
    case 'excluirItemEmbalagemNovo':
        excluirItemEmbalagemNovo($loteNovoRepo);
        break;
    case 'excluirItemProducaoNovo':
        excluirItemProducaoNovo($loteNovoRepo);
        break;
    case 'atualizarItemProducaoNovo':
        atualizarItemProducaoNovo($loteNovoRepo);
        break;
    case 'atualizarItemEmbalagemNovo':
        atualizarItemEmbalagemNovo($loteNovoRepo);
        break;
    case 'getItensParaFinalizar':
        getItensParaFinalizar($loteNovoRepo);
        break;
    case 'finalizarLoteParcialmenteNovo':
        finalizarLoteParcialmenteNovo($loteNovoRepo);
        break;
    case 'cancelarLoteNovo':
        cancelarLoteNovo($loteNovoRepo);
        break;
    case 'reativarLoteNovo':
        reativarLoteNovo($loteNovoRepo);
        break;
    case 'reabrirLoteNovo':
        reabrirLoteNovo($loteNovoRepo);
        break;
    case 'excluirLoteNovo':
        excluirLoteNovo($loteNovoRepo);
        break;
    case 'getDadosDoLoteItemNovo':
        getDadosDoLoteItemNovo($loteNovoRepo);
        break;
    case 'getEstoqueDeSobras':
        getEstoqueDeSobras($loteNovoRepo);
        break;
    case 'getOpenLotsForSelect':
        getOpenLotsSelect($loteNovoRepo);
        break;
    case 'salvarCaixaMista':
        salvarCaixaMista($loteNovoRepo, $_SESSION['codUsuario']);
        break;
    case 'listarCaixasMistas':
        listarCaixasMistas($loteNovoRepo);
        break;
    case 'getDetalhesCaixaMista':
        getDetalhesCaixaMista($loteNovoRepo);
        break;
    case 'excluirCaixaMista':
        excluirCaixaMista($loteNovoRepo);
        break;
    case 'getResumoFinalizacao':
        getResumoFinalizacao($loteNovoRepo);
        break;
    case 'atualizarStatusLote':
        atualizarStatusLote($loteNovoRepo);
        break;
    case 'gerarRelatorioLote':
        gerarRelatorioLote();
        break;
    case 'gerarRelatorioMensalLotes':
        gerarRelatorioMensalLotes($loteNovoRepo);
        break;
    case 'listarLotesLegadosRecentes':
        listarLotesLegadosRecentes($loteNovoRepo);
        break;
    case 'estornarLoteLegado':
        estornarLoteLegado($loteNovoRepo);
        break;
    case 'salvarReprocessoGerandoOE':
        salvarReprocessoGerandoOE($loteNovoRepo);
        break;


    // --- ROTAS DE DETALHES DE RECEBIMENTO (NOVO) ---
    case 'adicionarItemRecebimento':
        adicionarItemRecebimento($loteNovoRepo);
        break;
    case 'getItensRecebimento':
        getItensRecebimento($loteNovoRepo);
        break;
    case 'excluirItemRecebimento':
        excluirItemRecebimento($loteNovoRepo);
        break;
    case 'getLotesFinalizadosOptions':
        getLotesFinalizadosOptions($loteNovoRepo);
        break;
    case 'getItemRecebimento':
        getItemRecebimento($loteNovoRepo);
        break;
    case 'atualizarItemRecebimento':
        atualizarItemRecebimento($loteNovoRepo);
        break;
    case 'getDadosLoteReprocesso':
        getDadosLoteReprocesso($loteNovoRepo);
        break;
    case 'getClientesComLotesOptions':
        getClientesComLotesOptions($loteNovoRepo);
        break;
    case 'importarLoteLegado':
        importarLoteLegado($loteNovoRepo);
        break;
    case 'listarProdutosDoLote':
        listarProdutosDoLote($loteNovoRepo);
        break;
    case 'getDadosReprocessoPorProduto':
        getDadosReprocessoPorProduto($loteNovoRepo);
        break;
    case 'checkEstoqueParaBaixa':
        checkEstoqueParaBaixa($loteNovoRepo);
        break;
    case 'salvarReprocesso':
        salvarReprocesso($loteNovoRepo);
        break;
    case 'editarItemRecebimento':
        editarItemRecebimento($loteNovoRepo);
        break;


    // --- ROTA DE PERMISSÕES ---
    case 'salvarPermissoes':
        salvarPermissoes($permissionRepo);
        break;

    // --- ROTA DE ETIQUETAS ---  
    case 'imprimirEtiquetaLoteItem':
        imprimirEtiquetaLoteItem($pdo, $labelService);
        break;

    // --- ROTAS DE TEMPLATES DE ETIQUETA ---
    case 'listarTemplates':
        listarTemplates($templateRepo);
        break;
    case 'getTemplate':
        // LOG 1: Verificar se a rota foi chamada
        error_log("DEBUG: Rota 'getTemplate' foi acionada.");

        // LOG 2: Verificar os dados recebidos via POST
        error_log("DEBUG: Dados recebidos (POST): " . print_r($_POST, true));
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
    case 'reabrirCarregamento':
        reabrirCarregamento($carregamentoRepo, $ordemExpedicaoRepo);
        break;
    case 'getOrdensParaCarregamentoSelect':
        getOrdensParaCarregamentoSelect($ordemExpedicaoRepo);
        break;
    case 'getFilaDetalhes':
        getFilaDetalhes($carregamentoRepo);
        break;
    case 'getFotosDaFila':
        getFotosDaFila($carregamentoRepo);
        break;
    case 'addFotoFila':
        addFotoFila($carregamentoRepo);
        break;
    case 'getCarregamentoDetalhesCompletos':
        getCarregamentoDetalhesCompletos($carregamentoRepo);
        break;
    case 'addFilaCarregamento':
        addFilaCarregamento($carregamentoRepo);
        break;
    case 'removeFilaCarregamento':
        removeFilaCarregamento($carregamentoRepo);
        break;
    case 'removeItemCarregamento':
        removeItemCarregamento($carregamentoRepo);
        break;
    case 'addItemCarregamentoFromOE':
        addItemCarregamentoFromOE($carregamentoRepo);
        break;
    case 'finalizarCarregamento':
        finalizarCarregamento($carregamentoRepo, $ordemExpedicaoRepo);
        break;
    case 'updateCarregamentoHeader':
        updateCarregamentoHeader($carregamentoRepo);
        break;
    case 'getClientesDaOE':
        getClientesDaOE($carregamentoRepo);
        break;
    case 'getProdutosDoClienteNaOE':
        getProdutosDoClienteNaOE($carregamentoRepo);
        break;
    case 'getLotesDoProdutoNaOE':
        getLotesDoProdutoNaOE($carregamentoRepo);
        break;
    case 'removeClienteFromFila':
        removeClienteFromFila($carregamentoRepo);
        break;
    case 'getCarregamentoItemDetalhes':
        getCarregamentoItemDetalhes($carregamentoRepo);
        break;
    case 'updateCarregamentoItemQuantidade':
        updateCarregamentoItemQuantidade($carregamentoRepo);
        break;
    case 'addItemCarregamento':
        addItemCarregamento($carregamentoRepo);
        break;
    case 'getEnderecosParaCarregamentoPorLoteItem':
        getEnderecosParaCarregamentoPorLoteItem($carregamentoRepo);
        break;
    case 'getLotesParaCarregamentoPorProduto':
        getLotesParaCarregamentoPorProduto($carregamentoRepo);
        break;
    case 'getResumoParaFinalizar':
        getResumoParaFinalizar($carregamentoRepo);
        break;

    // --- ROTAS PARA O DASHBOARD (KPIs) ---
    case 'getKpiLotesAtivos':
        getKpiLotesAtivos($loteNovoRepo); // Apenas chama a função
        break;
    case 'getKpiCarregamentosHoje':
        getKpiCarregamentosHoje($carregamentoRepo); // Apenas chama a função
        break;
    case 'getKpiTotalUsuarios':
        getKpiTotalUsuarios($usuarioRepo); // Apenas chama a função
        break;
    case 'getKpiTotalProdutos':
        getKpiTotalProdutos($produtoRepo); // Apenas chama a função
        break;
    case 'getGraficoLotesFinalizados':
        getGraficoLotesFinalizados($loteNovoRepo);
        break;
    case 'getKpiEstoquePorCamara':
        getKpiEstoquePorCamara($enderecoRepo);
        break;

    // --- ROTAS PARA OS PAINÉIS DO DASHBOARD GERENCIAL ---
    case 'getPainelLotesAtivos':
        getPainelLotesAtivos($loteNovoRepo);
        break;
    case 'getPainelCarregamentosAbertos':
        getPainelCarregamentosAbertos($carregamentoRepo);
        break;

    // --- ROTA PARA O PAINEL DE PRODUÇÃO ---
    case 'getPainelProducaoLotes':
        getPainelProducaoLotes($loteNovoRepo);
        break;

    // ROTA PARA BUSCAR LOTES FINALIZADOS NO PAINEL DE PRODUÇÃO
    case 'getPainelProducaoLotesFinalizados':
        getPainelProducaoLotesFinalizados($loteNovoRepo);
        break;

    // --- ROTAS DE ESTOQUE (CÂMARAS) ---
    case 'listarCamaras':
        listarCamaras($camaraRepo);
        break;
    case 'getCamara':
        getCamara($camaraRepo);
        break;
    case 'salvarCamara':
        salvarCamara($camaraRepo);
        break;
    case 'excluirCamara':
        excluirCamara($camaraRepo);
        break;

    // --- ROTAS DE ESTOQUE (ENDEREÇOS) ---
    case 'getCamaraOptions': // Usado pelo dropdown
        getCamaraOptions($enderecoRepo);
        break;
    case 'listarEnderecosCamaras':
        listarEnderecosCamaras($enderecoRepo);
        break;
    case 'getEnderecoCamaras':
        getEnderecoCamaras($enderecoRepo);
        break;
    case 'salvarEnderecoCamaras':
        salvarEnderecoCamaras($enderecoRepo);
        break;
    case 'excluirEnderecoCamaras':
        excluirEnderecoCamaras($enderecoRepo);
        break;
    case 'alocarItemEndereco':
        alocarItemEndereco($enderecoRepo);
        break;
    case 'desalocarItemEndereco':
        desalocarItemEndereco($enderecoRepo);
        break;
    case 'getItensNaoAlocados':
        getItensNaoAlocados($enderecoRepo);
        break;
    case 'getVisaoEstoqueHierarquico':
        getVisaoEstoqueHierarquico($enderecoRepo);
        break;
    case 'getVisaoEstoqueHierarquicoFiltrada':
        getVisaoEstoqueHierarquicoFiltrada($enderecoRepo);
        break;
    case 'transferirItemEndereco':
        transferirItemEndereco($enderecoRepo);
        break;
    case 'buscarEnderecosSelect':
        buscarEnderecosSelect($enderecoRepo);
        break;
    case 'relatorioKardex':
        relatorioKardex($movimentoRepo);
        break;

    // --- ROTAS DE ORDENS DE EXPEDIÇÃO ---
    case 'listarOrdensExpedicao':
        listarOrdensExpedicao($ordemExpedicaoRepo);
        break;
    case 'getNextOrderNumber':
        getNextOrderNumber($ordemExpedicaoRepo);
        break;
    case 'salvarOrdemExpedicaoHeader':
        salvarOrdemExpedicaoHeader($ordemExpedicaoRepo, $_SESSION['codUsuario']);
        break;
    case 'getOrdemExpedicaoCompleta':
        getOrdemExpedicaoCompleta($ordemExpedicaoRepo);
        break;
    case 'addPedidoClienteOrdem':
        addPedidoClienteOrdem($ordemExpedicaoRepo);
        break;
    case 'listarEstoqueParaSelecao':
        listarEstoqueParaSelecao($ordemExpedicaoRepo);
        break;
    case 'addItemPedidoOrdem':
        addItemPedidoOrdem($ordemExpedicaoRepo);
        break;
    case 'getProdutosComEstoqueDisponivel':
        getProdutosComEstoqueDisponivel($ordemExpedicaoRepo);
        break;
    case 'getLotesDisponiveisPorProduto':
        getLotesDisponiveisPorProduto($ordemExpedicaoRepo);
        break;
    case 'getEnderecosDisponiveisPorLoteItem':
        getEnderecosDisponiveisPorLoteItem($ordemExpedicaoRepo);
        break;
    case 'removePedidoOrdem':
        removePedidoOrdem($ordemExpedicaoRepo);
        break;
    case 'removeItemPedidoOrdem':
        removeItemPedidoOrdem($ordemExpedicaoRepo);
        break;
    case 'getItemDetalhesParaEdicao':
        getItemDetalhesParaEdicao($ordemExpedicaoRepo);
        break;
    case 'salvarOrdemClientes':
        salvarOrdemClientes($ordemExpedicaoRepo);
        break;
    case 'updateItemPedido':
        updateItemPedido($ordemExpedicaoRepo);
        break;
    case 'getOrdensParaFaturamentoSelect':
        getOrdensParaFaturamentoSelect($ordemExpedicaoRepo);
        break;
    case 'excluirOrdemExpedicao':
        excluirOrdemExpedicao($ordemExpedicaoRepo);
        break;


    // --- ROTA PARA DETALHES DA RESERVA DE ESTOQUE ---
    case 'getReservaDetalhes':
        getReservaDetalhes($ordemExpedicaoRepo); // Usaremos o OrdemExpedicaoRepository
        break;

    // --- ROTAS DE FATURAMENTO ---
    case 'getFaturamentoDadosPorOrdem':
        getFaturamentoDadosPorOrdem($faturamentoRepo);
        break;
    case 'salvarResumoFaturamento':
        salvarResumoFaturamento($faturamentoRepo, $_SESSION['codUsuario']);
        break;
    case 'getFaturamentoItemDetalhes':
        getFaturamentoItemDetalhes($faturamentoRepo);
        break;
    case 'salvarFaturamentoItem':
        salvarFaturamentoItem($faturamentoRepo);
        break;
    case 'getResumoSalvo':
        getResumoSalvo($faturamentoRepo);
        break;
    case 'listarFaturamentos':
        listarFaturamentos($faturamentoRepo);
        break;
    case 'getCondicoesPagamentoOptions':
        getCondicoesPagamentoOptions($faturamentoRepo);
        break;
    case 'getNotaGrupoDetalhes':
        getNotaGrupoDetalhes($faturamentoRepo);
        break;
    case 'salvarNotaGrupo':
        salvarNotaGrupo($faturamentoRepo);
        break;
    case 'salvarDadosTransporte':
        salvarDadosTransporte($faturamentoRepo);
        break;
    case 'excluirFaturamento':
        excluirFaturamento($faturamentoRepo);
        break;
    case 'marcarComoFaturado':
        marcarComoFaturado($faturamentoRepo);
        break;
    case 'getGruposDeNotaParaFaturamento':
        getGruposDeNotaParaFaturamento($faturamentoRepo);
        break;
    case 'cancelarFaturamento':
        cancelarFaturamento($faturamentoRepo);
        break;
    case 'reabrirFaturamento':
        reabrirFaturamento($faturamentoRepo);
        break;

    // --- ROTAS DE CADASTRO - CONDIÇÕES DE PAGAMENTO ---
    case 'listarCondicoesPagamento':
        listarCondicoesPagamento($condPagRepo);
        break;
    case 'getCondicaoPagamento':
        getCondicaoPagamento($condPagRepo);
        break;
    case 'salvarCondicaoPagamento':
        salvarCondicaoPagamento($condPagRepo);
        break;
    case 'excluirCondicaoPagamento':
        excluirCondicaoPagamento($condPagRepo);
        break;

    // --- ROTAS DE FICHAS TÉCNICAS ---
    case 'listarFichasTecnicas':
        listarFichasTecnicas($fichaTecnicaRepo);
        break;
    case 'getFichaTecnicaCompleta':
        getFichaTecnicaCompleta($fichaTecnicaRepo);
        break;
    case 'getProdutosSemFichaTecnica':
        getProdutosSemFichaTecnica($fichaTecnicaRepo);
        break;
    case 'getFabricanteOptionsFT': // FT para Ficha Técnica, evitando conflito
        getFabricanteOptionsFT($fichaTecnicaRepo);
        break;
    case 'getProdutoDetalhesParaFicha':
        getProdutoDetalhesParaFicha($fichaTecnicaRepo);
        break;
    case 'salvarFichaTecnicaGeral':
        salvarFichaTecnicaGeral($fichaTecnicaRepo, $_SESSION['codUsuario']);
        break;
    case 'excluirFichaTecnica':
        excluirFichaTecnica($fichaTecnicaRepo);
        break;
    case 'listarCriteriosFicha':
        listarCriteriosFicha($fichaTecnicaRepo);
        break;
    case 'salvarCriterioFicha':
        salvarCriterioFicha($fichaTecnicaRepo);
        break;
    case 'excluirCriterioFicha':
        excluirCriterioFicha($fichaTecnicaRepo);
        break;
    case 'listarFotosFicha':
        listarFotosFicha($fichaTecnicaRepo);
        break;
    case 'uploadFotoFicha':
        uploadFotoFicha($fichaTecnicaRepo);
        break;
    case 'excluirFotoFicha':
        excluirFotoFicha($fichaTecnicaRepo);
        break;
    case 'gerarPdfFichaTecnica':
        gerarPdfFichaTecnica($fichaTecnicaRepo);
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

/* function getProduto(ProdutoRepository $repo)
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
} */

function getProduto(ProdutoRepository $repo)
{
    // AJUSTE AQUI: mudamos de 'prod_codigo' para 'id' para casar com o JS
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }

    // Aqui ele chama o método find() que você alterou no Repository (com o LEFT JOIN)
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
    } catch (Exception $e) {
        // Pega tanto erro de banco (PDO) quanto validações manuais (Exception)
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
    $term = $_GET['term'] ?? '';
    echo json_encode(['success' => true, 'data' => $repo->getProdutoOptions($tipo, $term)]);
}

function getSecundariosPorPrimario(ProdutoRepository $repo)
{
    // Usamos filter_input para segurança
    $primarioId = filter_input(INPUT_GET, 'primario_id', FILTER_VALIDATE_INT);

    if (!$primarioId) {
        echo json_encode(['success' => false, 'message' => 'ID do produto primário inválido.']);
        return;
    }

    try {
        $produtos = $repo->findSecundariosByPrimarioId($primarioId);
        echo json_encode(['success' => true, 'data' => $produtos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar produtos.']);
    }
}

function getProdutosSecundariosOptions(ProdutoRepository $repo)
{
    $term = $_GET['term'] ?? '';
    $data = $repo->getProdutosSecundariosOptions($term);
    echo json_encode(['results' => $data]);
}

function getMarcasOptions(ProdutoRepository $repo)
{
    try {
        // Retorna apenas a lista simples de strings
        $marcas = $repo->getDistinctMarcas();
        echo json_encode(['success' => true, 'data' => $marcas]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
    $term = $_GET['term'] ?? '';
    $data = $repo->getClienteOptions($term);
    echo json_encode(['data' => $data]);
}

function getTransportadoraOptions(EntidadeRepository $repo)
{
    echo json_encode(['results' => $repo->getTransportadoraOptions()]);
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
        if ($totalUsuarios >= 30) {
            echo json_encode(['success' => false, 'message' => 'Limite máximo de 30 usuários atingido. Não é possível adicionar mais usuários.']);
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
    // Validação básica dos dados recebidos do JavaScript
    $loteId = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    $itens = $_POST['itens'] ?? [];

    if (!$loteId || empty($itens) || !is_array($itens)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos para finalização.']);
        return;
    }

    try {
        if (!isset($_SESSION['usu_codigo'])) {
            throw new Exception("Sessão expirada.");
        }
        $usuarioId = $_SESSION['usu_codigo'];

        $repo->finalizeParcialmente($loteId, $itens, $usuarioId);
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

function listarEstoque(LoteNovoRepository $repo)
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

function getDadosDoLoteItem(LoteRepository $repo)
{
    $loteItemId = filter_input(INPUT_POST, 'lote_item_id', FILTER_VALIDATE_INT);
    if (!$loteItemId) {
        echo json_encode(['success' => false, 'message' => 'ID do item inválido.']);
        return;
    }
    $data = $repo->findItemDetalhes($loteItemId);
    echo json_encode(['success' => !!$data, 'data' => $data]);
}

function reabrirLote(LoteRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
        $motivo = trim($_POST['motivo'] ?? '');
        if (!$id || empty($motivo)) {
            throw new Exception("Dados inválidos para reabertura.");
        }
        $repo->reabrir($id, $motivo);
        echo json_encode(['success' => true, 'message' => 'Lote reaberto com sucesso! O estoque foi revertido.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function listarLotesNovos(LoteNovoRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function getProximoNumeroLoteNovo(LoteNovoRepository $repo)
{
    echo json_encode(['success' => true, 'proximo_numero' => $repo->getNextNumero()]);
}

function salvarLoteNovoHeader(LoteNovoRepository $repo, int $userId)
{
    try {
        $loteId = $repo->saveHeader($_POST, $userId);
        echo json_encode(['success' => true, 'message' => 'Cabeçalho do lote salvo com sucesso!', 'novo_lote_id' => $loteId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar cabeçalho: ' . $e->getMessage()]);
    }
}

function buscarLoteNovo(LoteNovoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }

    $lote = $repo->findLoteNovoCompleto($id);
    echo json_encode(['success' => !!$lote, 'data' => $lote]);
}

function adicionarItemProducaoNovo(LoteNovoRepository $repo)
{
    try {
        $novoItemId = $repo->adicionarItemProducao($_POST);
        echo json_encode(['success' => true, 'message' => 'Item adicionado!', 'item_id' => $novoItemId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function adicionarItemEmbalagemNovo(LoteNovoRepository $repo)
{
    try {
        // A validação e a lógica principal já estão no método do repositório!
        $novoItemId = $repo->adicionarItemEmbalagem($_POST);
        echo json_encode(['success' => true, 'message' => 'Item de embalagem adicionado com sucesso!', 'item_id' => $novoItemId]);
    } catch (Exception $e) {
        // Erros de negócio (ex: saldo insuficiente) serão capturados aqui
        http_response_code(400); // Bad Request é apropriado para erros de validação/lógica
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getItensEmbalagemNovo(LoteNovoRepository $repo)
{
    $loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);
    if (!$loteId) {
        echo json_encode(['success' => false, 'data' => []]);
        return;
    }
    $itens = $repo->findEmbalagemByLoteId($loteId);
    echo json_encode(['success' => true, 'data' => $itens]);
}

function excluirLoteNovo(LoteNovoRepository $repo)
{
    $lote_id = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    if (!$lote_id) { /* tratamento de erro */
    }
    try {
        $repo->excluirLote($lote_id);
        echo json_encode(['success' => true, 'message' => 'Lote excluído permanentemente com sucesso!']);
    } catch (Exception $e) {
        http_response_code(400); // Bad Request para erros de regra de negócio
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirItemEmbalagemNovo(LoteNovoRepository $repo)
{
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    if (!$item_id) { /* tratamento de erro */
    }
    try {
        $repo->excluirItemEmbalagem($item_id);
        echo json_encode(['success' => true, 'message' => 'Item excluído e saldo revertido com sucesso!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirItemProducaoNovo(LoteNovoRepository $repo)
{
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    if (!$item_id) { /* tratamento de erro */
    }
    try {
        $repo->excluirItemProducao($item_id);
        echo json_encode(['success' => true, 'message' => 'Item de produção excluído com sucesso!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function atualizarItemEmbalagemNovo(LoteNovoRepository $repo)
{
    $item_id = filter_input(INPUT_POST, 'item_emb_id', FILTER_VALIDATE_INT);
    if (!$item_id) { /* tratamento de erro */
    }
    try {
        $repo->atualizarItemEmbalagem($item_id, $_POST);
        echo json_encode(['success' => true, 'message' => 'Item de embalagem atualizado com sucesso!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getItensParaFinalizar(LoteNovoRepository $repo)
{
    $loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);
    if (!$loteId) {
        echo json_encode(['success' => false, 'data' => []]);
        return;
    }
    $itens = $repo->getItensParaFinalizar($loteId);
    echo json_encode(['success' => true, 'data' => $itens]);
}

function reativarLoteNovo(LoteNovoRepository $repo)
{
    $lote_id = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    $motivo = trim($_POST['motivo'] ?? ''); // Captura o motivo

    if (!$lote_id) {
        echo json_encode(['success' => false, 'message' => 'ID de lote inválido.']);
        return;
    }

    // Validação básica do motivo também aqui no controller
    if (empty($motivo)) {
        echo json_encode(['success' => false, 'message' => 'O motivo é obrigatório.']);
        return;
    }

    try {
        // Passa o ID e o Motivo para o repositório
        if ($repo->reativarLote($lote_id, $motivo)) {
            echo json_encode(['success' => true, 'message' => 'Lote reativado com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Não foi possível reativar o lote.']);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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

function atualizarItemProducaoNovo(LoteNovoRepository $repo)
{
    $item_id = filter_input(INPUT_POST, 'item_prod_id', FILTER_VALIDATE_INT);
    if (!$item_id) { /* tratamento de erro */
    }
    try {
        $repo->atualizarItemProducao($item_id, $_POST);
        echo json_encode(['success' => true, 'message' => 'Item atualizado com sucesso!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function finalizarLoteParcialmenteNovo(LoteNovoRepository $repo)
{
    $loteId = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);

    // Decodifica o JSON vindo do JavaScript
    $itensJson = $_POST['itens'] ?? '[]';
    $itens = json_decode($itensJson, true);

    // Validação extra: Se o JSON for inválido, $itens será null
    if (!$loteId || !is_array($itens)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos. Verifique os itens selecionados.']);
        return;
    }

    try {
        if (!isset($_SESSION['usu_codigo'])) {
            throw new Exception("Sessão expirada.");
        }
        $usuarioId = $_SESSION['usu_codigo'];

        // $repo->finalizeParcialmente($loteId, $itens, $usuarioId);
        $repo->finalizarLoteParcialmente($loteId, $itens, $usuarioId);
        echo json_encode(['success' => true, 'message' => 'Lote finalizado com sucesso e estoque atualizado!']);
    } catch (Exception $e) {
        http_response_code(400); // 400 é melhor que 500 para erros de regra de negócio
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function cancelarLoteNovo(LoteNovoRepository $repo)
{
    $lote_id = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    if (!$lote_id) { /* tratamento de erro */
    }
    try {
        $repo->cancelarLote($lote_id);
        echo json_encode(['success' => true, 'message' => 'Lote cancelado com sucesso! O stock foi revertido, se necessário.']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function reabrirLoteNovo(LoteNovoRepository $repo)
{
    $lote_id = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    $motivo = trim($_POST['motivo'] ?? ''); // Captura o motivo

    if (!$lote_id || empty($motivo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do lote e motivo são obrigatórios.']);
        return;
    }
    try {
        $repo->reabrirLote($lote_id, $motivo);
        echo json_encode(['success' => true, 'message' => 'Lote reaberto com sucesso! O stock foi estornado.']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getDadosDoLoteItemNovo(LoteNovoRepository $repo)
{
    $loteItemId = filter_input(INPUT_POST, 'lote_item_id', FILTER_VALIDATE_INT);
    if (!$loteItemId) {
        echo json_encode(['success' => false, 'message' => 'ID do item inválido.']);
        return;
    }
    $data = $repo->findLoteNovoItemDetalhes($loteItemId);
    echo json_encode(['success' => !!$data, 'data' => $data]);
}

// --- FUNÇÕES DE CONTROLE PARA DETALHES DE RECEBIMENTO ---

/* function adicionarItemRecebimento(LoteNovoRepository $repo)
{
    try {

        $tipoEntrada = $_POST['tipo_entrada_mp'] ?? null;

        if (!$tipoEntrada) {
            throw new Exception('Tipo de entrada não informado.');
        }

        if ($tipoEntrada === 'MATERIA_PRIMA') {

            if (empty($_POST['item_receb_produto_id'])) {
                throw new Exception('Selecione a matéria-prima.');
            }

            // garante consistência
            $_POST['item_receb_lote_origem_id'] = null;
        }

        if ($tipoEntrada === 'LOTE_ORIGEM') {

            if (empty($_POST['item_receb_lote_origem_id'])) {
                throw new Exception('Selecione o lote de origem.');
            }

            // ✅ BUSCA REAL DOS DADOS DO LOTE
            $dadosDoLote = $repo->getDadosBasicosLoteReprocesso(
                (int) $_POST['item_receb_lote_origem_id']
            );

            if (!$dadosDoLote) {
                throw new Exception('Lote de origem não encontrado.');
            }

            // ✅ SOBRESCREVE CAMPOS (backend manda)
            $_POST['item_receb_produto_id']        = null;
            $_POST['item_receb_total_caixas']      = $dadosDoLote['lote_total_caixas'];
            $_POST['item_receb_nota_fiscal']        = $dadosDoLote['lote_nota_fiscal'];
            $_POST['item_receb_peso_nota_fiscal']   = $dadosDoLote['lote_peso_nota_fiscal'];
            $_POST['item_receb_peso_medio_ind']     = $dadosDoLote['lote_peso_medio_industria'];
            $_POST['item_receb_gram_faz']           = $dadosDoLote['lote_gramatura_fazenda'];
            $_POST['item_receb_gram_lab']           = $dadosDoLote['lote_gramatura_lab'];
        }

        // ✅ SEGUE FLUXO NORMAL
        $repo->adicionarItemRecebimento($_POST);

        echo json_encode([
            'success' => true,
            'message' => 'Detalhe de recebimento adicionado com sucesso!'
        ]);
    } catch (Exception $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} */

function adicionarItemRecebimento(LoteNovoRepository $repo)
{
    try {
        // Pega todos os dados do formulário
        $dados = $_POST;

        $tipoEntrada = $dados['tipo_entrada_mp'] ?? null;

        if (!$tipoEntrada) {
            throw new Exception('Tipo de entrada não informado.');
        }

        // --- CASO 1: MATÉRIA-PRIMA ---
        if ($tipoEntrada === 'MATERIA_PRIMA') {
            // No HTML, o select de MP deve ter o name="produto_mp_id" ou similar.
            // Vamos assumir que você usa um campo específico ou o genérico.
            // Se o seu select de MP já tem name="item_receb_produto_id", ok. 
            // Se tiver outro nome, mapeie aqui:
            if (empty($dados['item_receb_produto_id'])) {
                // Se você usa 'produto_mp_id' no HTML, mude a linha acima para checar ele
                throw new Exception('Selecione a matéria-prima.');
            }

            // Garante que não tem lote de origem vinculado
            $dados['item_receb_lote_origem_id'] = null;
        }

        // --- CASO 2: REPROCESSO (LOTE_ORIGEM) ---
        if ($tipoEntrada === 'LOTE_ORIGEM') {

            // 1. Valida Lote de Origem
            if (empty($dados['item_receb_lote_origem_id'])) {
                throw new Exception('Selecione o lote de origem.');
            }

            // 2. Valida Produto de Origem (A GRANDE MUDANÇA)
            // Precisamos pegar o ID do produto finalizado que o usuário escolheu no select novo
            $prodOrigemId = $dados['lote_origem_produto_id'] ?? null; // O name do select novo

            if (empty($prodOrigemId)) {
                throw new Exception('Selecione qual produto deste lote será reprocessado.');
            }

            // 3. Mapeia para a coluna correta do banco
            // A coluna 'item_receb_produto_id' vai guardar o ID do produto acabado (ex: Camarão Cozido)
            $dados['item_receb_produto_id'] = $prodOrigemId;

            // OBS: NÃO buscamos no banco para sobrescrever ($repo->getDados...).
            // Confiamos nos valores de Peso e Caixas que vieram no $_POST ($dados),
            // pois o JavaScript já calculou e o usuário já conferiu/editou.
        }

        // --- ENVIA PARA O REPOSITÓRIO ---
        // Agora $dados tem o 'item_receb_produto_id' correto em ambos os casos.
        $repo->adicionarItemRecebimento($dados);

        echo json_encode([
            'success' => true,
            'message' => 'Detalhe de recebimento adicionado com sucesso!'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function getItensRecebimento(LoteNovoRepository $repo)
{
    // No JS definimos que este parâmetro vai via GET na URL
    $loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);

    if (!$loteId) {
        echo json_encode(['success' => false, 'data' => [], 'message' => 'ID do lote não fornecido.']);
        return;
    }

    $itens = $repo->getItensRecebimento($loteId);
    echo json_encode(['success' => true, 'data' => $itens]);
}

function excluirItemRecebimento(LoteNovoRepository $repo)
{
    // 1. Pega o ID do item que veio do Javascript
    $itemId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    // 2. Pega o ID do Usuário da Sessão (O QUE FALTOU)
    // Se não tiver sessão (login), usa 1 ou trata o erro
    $usuarioId = $_SESSION['user_id'] ?? 1;

    if (!$itemId) {
        echo json_encode(['success' => false, 'message' => 'ID do item inválido.']);
        return;
    }

    try {
        // 3. Passamos os DOIS parâmetros obrigatórios
        if ($repo->excluirItemRecebimento($itemId, $usuarioId)) {
            echo json_encode(['success' => true, 'message' => 'Item removido com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao remover o item.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getLotesFinalizadosOptions(LoteNovoRepository $repo)
{
    $term = $_GET['term'] ?? '';
    $dados = $repo->getLotesFinalizadosParaSelect($term);
    echo json_encode(['results' => $dados]);
}

/**
 * Controller para Salvar a Caixa Mista (recebe os dados e chama o Repositório).
 */
function salvarCaixaMista(LoteNovoRepository $repo, int $usuarioId)
{
    // O JavaScript enviará os dados serializados do formulário, que o PHP
    // interpreta automaticamente como um array no $_POST.
    try {
        // A função do Repo fará todo o trabalho pesado e a transação
        $novoItemEmbId = $repo->criarCaixaMista($_POST, $usuarioId);

        echo json_encode([
            'success' => true,
            'message' => 'Caixa Mista criada com sucesso! Novo item de embalagem gerado.',
            'novo_item_emb_id' => $novoItemEmbId // Retorna o ID para a impressão da etiqueta
        ]);
    } catch (Exception $e) {
        // Se o Repo falhar (ex: saldo insuficiente), ele envia a exceção
        http_response_code(400); // 400 Bad Request
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Controller para buscar os lotes abertos para o Select2.
 */
function getOpenLotsSelect(LoteNovoRepository $repo)
{
    try {
        $lotes = $repo->findOpenLotsForSelect();
        // O Select2 espera os dados dentro de uma chave 'results'
        echo json_encode(['results' => $lotes]);
    } catch (Exception $e) {
        echo json_encode(['results' => [], 'error' => $e->getMessage()]);
    }
}

function listarCaixasMistas(LoteNovoRepository $repo)
{
    // A função do repositório já faz todo o trabalho, incluindo paginação e busca.
    $output = $repo->findAllCaixasMistasForDataTable($_POST);
    echo json_encode($output);
}

function getDetalhesCaixaMista(LoteNovoRepository $repo)
{
    $mistaId = filter_input(INPUT_POST, 'mista_id', FILTER_VALIDATE_INT);
    if (!$mistaId) {
        echo json_encode(['success' => false, 'message' => 'ID da caixa mista inválido.']);
        return;
    }

    $detalhes = $repo->getDetalhesCaixaMista($mistaId);
    echo json_encode($detalhes);
}

/**
 * Controller para excluir uma caixa mista e reverter os saldos.
 */
function excluirCaixaMista(LoteNovoRepository $repo)
{
    // 1. Validação do ID da Caixa Mista
    $mistaId = filter_input(INPUT_POST, 'mista_id', FILTER_VALIDATE_INT);
    if (!$mistaId) {
        echo json_encode(['success' => false, 'message' => 'ID da caixa mista inválido.']);
        return;
    }

    // 2. Validação da Sessão (Fundamental para o Kardex saber quem excluiu)
    if (!isset($_SESSION['usu_codigo'])) {
        echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
        return;
    }
    $usuarioId = (int)$_SESSION['usu_codigo'];

    try {
        // 3. Chama o repositório passando o ID da Mista E o Usuário
        $repo->excluirCaixaMista($mistaId, $usuarioId);

        echo json_encode(['success' => true, 'message' => 'Caixa mista excluída com sucesso e saldos revertidos.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getItemRecebimento(LoteNovoRepository $repo)
{
    $itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
    if (!$itemId) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    $data = $repo->getItemRecebimento($itemId);
    echo json_encode(['success' => true, 'data' => $data]);
}

function atualizarItemRecebimento(LoteNovoRepository $repo)
{
    try {
        $repo->atualizarItemRecebimento($_POST);
        echo json_encode(['success' => true, 'message' => 'Item atualizado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getDadosLoteReprocesso(LoteNovoRepository $repo)
{
    try {
        // Usa $_GET pois o JS usa $.getJSON
        $loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);

        if (!$loteId) {
            throw new Exception('ID do lote inválido ou não informado.');
        }

        $dados = $repo->getDadosBasicosLoteReprocesso($loteId);

        if (empty($dados)) {
            // Não lançamos Exception aqui para evitar o Erro 400 no console.
            // Retornamos success: false para o JS tratar amigavelmente.
            echo json_encode([
                'success' => false,
                'message' => 'Detalhes deste lote não encontrados (verifique se o lote de origem possui dados de recebimento).'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'dados' => $dados
        ]);
    } catch (Exception $e) {
        http_response_code(400); // Bad Request apenas para erros críticos de sistema
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function getResumoFinalizacao(LoteNovoRepository $repo)
{
    $loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);
    if (!$loteId) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    try {
        $dados = $repo->getResumoFinalizacao($loteId);
        echo json_encode(['success' => true, 'data' => $dados]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function atualizarStatusLote(LoteNovoRepository $repo)
{
    /* $loteId = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';*/

    $loteId = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS);

    // Verifica Sessão
    if (!isset($_SESSION['codUsuario'])) {
        throw new Exception("Sessão expirada.");
    }
    $usuarioId = $_SESSION['codUsuario'];

    if (!$loteId || !$status) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        return;
    }

    try {
        $repo->atualizarStatusLote($loteId, $status, $usuarioId);
        echo json_encode(['success' => true, 'message' => 'Status do lote atualizado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function gerarRelatorioLote()
{
    // Apenas inclui o arquivo que contém a lógica de geração e o HTML.
    // O arquivo relatorio_lote.php vai gerar o PDF e devolver o JSON.
    require_once __DIR__ . '/../views/lotes_novo/relatorio_lote.php';
}

function gerarRelatorioMensalLotes(LoteNovoRepository $repo)
{
    // 1. Recebe os parâmetros
    $mesesStr = $_GET['meses'] ?? '';
    // ATENÇÃO: Verifique se no seu JS você está enviando como 'clientes' ou 'fornecedores'. 
    // Vou deixar lendo os dois para garantir que funcione independente do nome no JS.
    $idsStr = $_GET['clientes'] ?? ($_GET['fornecedores'] ?? '');
    $ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);

    if (empty($mesesStr) || !$ano) {
        die("Parâmetros inválidos.");
    }

    $mesesArray = array_map('intval', explode(',', $mesesStr));

    // 2. Processa os IDs e BUSCA OS NOMES
    $idsArray = [];
    $nomesClientesStr = ''; // Variável que vai para o cabeçalho

    if (!empty($idsStr)) {
        $idsArray = array_map('intval', explode(',', $idsStr));

        //  Busca os nomes no banco 
        $nomesClientesStr = $repo->getNomesClientesPorIds($idsArray);
    }

    try {
        // Passa os IDs para o filtro de dados
        $dados = $repo->getRelatorioMensalData($mesesArray, $ano, $idsArray);
    } catch (Exception $e) {
        die($e->getMessage());
    }

    // --- LÓGICA DO TÍTULO DO PERÍODO ---
    $todosMeses = [1 => 'JAN', 2 => 'FEV', 3 => 'MAR', 4 => 'ABR', 5 => 'MAI', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SET', 10 => 'OUT', 11 => 'NOV', 12 => 'DEZ'];

    if (count($mesesArray) == 12) {
        $periodoTexto = "ANO DE " . $ano . " (COMPLETO)";
    } elseif (count($mesesArray) <= 4) {
        $nomes = [];
        foreach ($mesesArray as $m) {
            if (isset($todosMeses[$m])) $nomes[] = $todosMeses[$m];
        }
        $periodoTexto = implode(', ', $nomes) . ' / ' . $ano;
    } else {
        $periodoTexto = count($mesesArray) . " MESES SELECIONADOS / " . $ano;
    }

    // Chama a visualização (PDF)
    require __DIR__ . '/../views/lotes_novo/relatorio_mensal_lotes.php';
}
function listarLotesLegadosRecentes(LoteNovoRepository $repo)
{
    // Instancia o repositório (Assumindo que $loteNovoRepo já está instanciado no início do arquivo)
    // Se não estiver, faça: $repo = new \App\Lotes\LoteNovoRepository($pdo);
    try {
        $dados = $repo->listarLegadosRecentes();
        echo json_encode(['success' => true, 'data' => $dados]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'data' => [], 'error' => $e->getMessage()]);
    }
}

/**
 * Rota para estornar/cancelar um lote legado.
 */
function estornarLoteLegado(LoteNovoRepository $repo)
{
    // 1. FILTROS
    $loteId = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    $motivo = filter_input(INPUT_POST, 'motivo', FILTER_SANITIZE_SPECIAL_CHARS);
    $usuarioId = $_SESSION['codUsuario'] ?? 0;

    // 2. VALIDAÇÃO BÁSICA
    if (!$loteId || empty($motivo)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos. Verifique o ID do lote e o motivo.']);
        return;
    }

    // 3. EXECUÇÃO
    try {
        // Chama o método do repositório
        // O método já retorna um array ['success' => ..., 'message' => ...]
        $resultado = $repo->estornarLoteLegado($loteId, $usuarioId, $motivo);

        echo json_encode($resultado);
    } catch (Exception $e) {
        // Captura erros gerais que não foram tratados no repositório
        echo json_encode(['success' => false, 'message' => 'Erro ao processar estorno: ' . $e->getMessage()]);
    }
}

function getClientesComLotesOptions(LoteNovoRepository $repo)
{
    // Retorna no formato { data: [...] } que o JS espera
    echo json_encode(['data' => $repo->getClientesComLotesOptions()]);
}

/* function importarLoteLegado(LoteNovoRepository $repo)
{
    // 1. FILTROS BÁSICOS
    $loteCodigo = filter_input(INPUT_POST, 'lote_codigo', FILTER_SANITIZE_SPECIAL_CHARS);
    $dataFab    = filter_input(INPUT_POST, 'data_fabricacao', FILTER_SANITIZE_SPECIAL_CHARS);
    $clienteId  = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $produtoId  = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $enderecoId = filter_input(INPUT_POST, 'endereco_id', FILTER_VALIDATE_INT);

    // 2. TRATAMENTO DA QUANTIDADE (CAIXAS)
    // O JS garante que esse campo tenha o valor correto (calculado ou digitado)
    $qtdCaixas = filter_input(INPUT_POST, 'qtd_caixas', FILTER_VALIDATE_FLOAT);

    // 3. TRATAMENTO DO PESO (Apenas para validação, já que o banco salva caixas)
    $pesoRaw = $_POST['peso_total'] ?? '0';
    $pesoFormatado = str_replace(',', '.', str_replace('.', '', $pesoRaw)); // 1.200,50 -> 1200.50

    // O nome correto da função é filter_var, não filter_val
    $pesoTotal = filter_var($pesoFormatado, FILTER_VALIDATE_FLOAT);

    // 4. VALIDAÇÃO
    if (!$loteCodigo || !$dataFab || !$clienteId || !$produtoId || !$enderecoId) {
        echo json_encode(['success' => false, 'message' => 'Preencha os campos obrigatórios (Lote, Data, Cliente, Produto, Endereço).']);
        return;
    }

    if (empty($qtdCaixas) || $qtdCaixas <= 0) {
        echo json_encode(['success' => false, 'message' => 'A quantidade de Caixas deve ser maior que zero.']);
        return;
    }

    $obs = filter_input(INPUT_POST, 'observacao', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $usuarioId = $_SESSION['codUsuario'] ?? 0;

    try {
        $dados = [
            'lote_codigo'     => $loteCodigo,
            'data_fabricacao' => $dataFab,
            'cliente_id'      => $clienteId,
            'produto_id'      => $produtoId,
            'quantidade'      => $qtdCaixas,
            'endereco_id'     => $enderecoId,
            'observacao'      => $obs
        ];

        // Chama o repositório
        $repo->importarLoteLegado($dados, $usuarioId);

        echo json_encode(['success' => true, 'message' => 'Lote legado importado e estoque alocado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
}*/

function importarLoteLegado(LoteNovoRepository $repo)
{
    // 1. FILTROS BÁSICOS
    $loteCodigo = filter_input(INPUT_POST, 'lote_codigo', FILTER_SANITIZE_SPECIAL_CHARS);
    $dataFab    = filter_input(INPUT_POST, 'data_fabricacao', FILTER_SANITIZE_SPECIAL_CHARS);
    $dataValidade = filter_input(INPUT_POST, 'data_validade', FILTER_SANITIZE_SPECIAL_CHARS);
    $clienteId  = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $produtoId  = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $enderecoId = filter_input(INPUT_POST, 'endereco_id', FILTER_VALIDATE_INT);

    // 2. TRATAMENTO DA QUANTIDADE (CAIXAS)
    // O JS garante que esse campo tenha o valor correto (calculado ou digitado)
    $qtdCaixas = filter_input(INPUT_POST, 'qtd_caixas', FILTER_VALIDATE_FLOAT);

    // 3. TRATAMENTO DO PESO (Apenas para validação, já que o banco salva caixas)
    $pesoRaw = $_POST['peso_total'] ?? '0';
    $pesoFormatado = str_replace(',', '.', str_replace('.', '', $pesoRaw)); // 1.200,50 -> 1200.50

    // O nome correto da função é filter_var, não filter_val
    $pesoTotal = filter_var($pesoFormatado, FILTER_VALIDATE_FLOAT);

    // 4. VALIDAÇÃO
    if (!$loteCodigo || !$dataFab || !$clienteId || !$produtoId || !$enderecoId) {
        echo json_encode(['success' => false, 'message' => 'Preencha os campos obrigatórios (Lote, Data, Cliente, Produto, Endereço).']);
        return;
    }

    if (empty($qtdCaixas) || $qtdCaixas <= 0) {
        echo json_encode(['success' => false, 'message' => 'A quantidade de Caixas deve ser maior que zero.']);
        return;
    }

    if (empty($pesoTotal) || $pesoTotal <= 0) {
        echo json_encode(['success' => false, 'message' => 'O peso total deve ser maior que zero.']);
        return;
    }

    $obs = filter_input(INPUT_POST, 'observacao', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $usuarioId = $_SESSION['codUsuario'] ?? 0;

    try {
        $dados = [
            'lote_codigo'     => $loteCodigo,
            'data_fabricacao' => $dataFab,
            'data_validade'   => $dataValidade,
            'cliente_id'      => $clienteId,
            'produto_id'      => $produtoId,
            'quantidade'      => $qtdCaixas,  // Mantido para compatibilidade, mas repo usa peso_total para cálculos
            'peso_total'      => $pesoTotal,  // Adicionado: Necessário para cálculos no repo
            'endereco_id'     => $enderecoId,
            'observacao'      => $obs
        ];

        // Chama o repositório
        $repo->importarLoteLegado($dados, $usuarioId);

        echo json_encode(['success' => true, 'message' => 'Lote legado importado e estoque alocado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
}

function listarProdutosDoLote(LoteNovoRepository $repo)
{
    $loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);
    if ($loteId) {
        echo json_encode(['data' => $repo->listarProdutosDoLote($loteId)]);
    } else {
        echo json_encode(['data' => []]);
    }
}

function getDadosReprocessoPorProduto(LoteNovoRepository $repo)
{
    $loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);
    $prodId = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);

    if ($loteId && $prodId) {
        $dados = $repo->getDadosReprocessoPorProduto($loteId, $prodId);

        if ($dados) {
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            // Aqui evitamos aquele erro feio. Se não achou, retorna vazio mas com success=false controlado.
            echo json_encode(['success' => false, 'message' => 'Nenhum saldo encontrado para este produto neste lote.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    }
}

function checkEstoqueParaBaixa(LoteNovoRepository $repo)
{
    $loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);
    $prodId = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);

    $saldos = $repo->getSaldosPorEndereco($loteId, $prodId);
    echo json_encode(['success' => true, 'data' => $saldos]);
}

/* function salvarReprocesso(LoteNovoRepository $repo)
{
    try {
        // Recebe os dados do formulário normal
        $dadosForm = $_POST;

        // --- Quando for produto Reprocesso ---
        // Se o formulário enviou 'lote_origem_produto_id' (o dropdown do reprocesso),
        // nós copiamos esse valor para 'item_receb_produto_id' (que o repositório espera).
        if (!empty($dadosForm['lote_origem_produto_id'])) {
            $dadosForm['item_receb_produto_id'] = $dadosForm['lote_origem_produto_id'];
        }
        // ---------------------------------------

        // Recebe o JSON da distribuição (quais endereços baixar)
        $distribuicao = json_decode($_POST['distribuicao_estoque'], true);

        if (empty($distribuicao)) {
            throw new Exception("Erro: Nenhuma informação de baixa de estoque recebida.");
        }

        // Pega ID do usuário da sessão
        $usuarioId = $_SESSION['user_id'] ?? 1; // Ajuste conforme sua sessão

        // Salva tudo na transação
        $repo->salvarReprocessoComBaixa($dadosForm, $distribuicao, $usuarioId);

        echo json_encode(['success' => true, 'message' => 'Reprocesso salvo e estoque baixado com sucesso!']);
    } catch (Exception $e) {
        // Log para ajudar no debug se der erro de novo
        error_log("Erro no salvarReprocesso: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
} */

// --- FUNÇÃO DE CONTROLE PARA ETIQUETAS ---



/* function salvarReprocesso(LoteNovoRepository $repo)
{
    try {
        $dadosForm = $_POST;

        // Mapeamento de campos (Reprocesso -> Recebimento)
        if (!empty($dadosForm['lote_origem_produto_id'])) {
            $dadosForm['item_receb_produto_id'] = $dadosForm['lote_origem_produto_id'];
        }

        // Recebe a distribuição. Se vier vazio, é array vazio.
        $distribuicao = isset($_POST['distribuicao_estoque']) ? json_decode($_POST['distribuicao_estoque'], true) : [];

        // REMOVIDA A EXCEÇÃO QUE TRAVAVA QUANDO ERA VAZIO
        // if (empty($distribuicao)) throw new Exception... (Apague isso)

        $usuarioId = $_SESSION['user_id'] ?? 1;

        // Salva
        $repo->salvarReprocessoComBaixa($dadosForm, $distribuicao, $usuarioId);

        echo json_encode(['success' => true, 'message' => 'Reprocesso salvo com sucesso!']);
    } catch (Exception $e) {
        // Log do erro real para você ver no servidor
        error_log("Erro salvarReprocesso: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
} */

function salvarReprocesso(LoteNovoRepository $repo)
{
    try {
        $dadosForm = $_POST;

        // --- CORREÇÃO CRÍTICA DE MAPEAMENTO ---
        // Verifica qual campo de produto veio preenchido e garante que 'item_receb_produto_id' tenha valor.

        // 1. Se veio 'lote_origem_produto_id' (nome antigo/alternativo), usa ele.
        if (!empty($dadosForm['lote_origem_produto_id'])) {
            $dadosForm['item_receb_produto_id'] = $dadosForm['lote_origem_produto_id'];
        }

        // 2. Validação de Segurança no PHP:
        // Se após o mapeamento o ID do produto ainda for vazio/zero, paramos aqui antes de tentar gravar no banco.
        if (empty($dadosForm['item_receb_produto_id'])) {
            throw new Exception("Erro: O Produto não foi identificado. Verifique se selecionou o produto no dropdown.");
        }

        // Recebe a distribuição. 
        $distribuicao = isset($_POST['distribuicao_estoque']) ? json_decode($_POST['distribuicao_estoque'], true) : [];
        $usuarioId = $_SESSION['user_id'] ?? 1;

        // Salva
        $repo->salvarReprocessoComBaixa($dadosForm, $distribuicao, $usuarioId);

        echo json_encode(['success' => true, 'message' => 'Reprocesso salvo com sucesso!']);
    } catch (Exception $e) {
        // Log para debug
        error_log("Erro salvarReprocesso: " . $e->getMessage());

        // Retorna erro legível para o SweetAlert
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function editarItemRecebimento(LoteNovoRepository $repo)
{
    $dados = $_POST;
    $usuarioId = $_SESSION['user_id'] ?? 1;

    try {
        // Remove campos de formatação (máscaras) antes de enviar
        // Opcional: tratamentos de dados aqui

        $repo->editarItemRecebimento($dados, $usuarioId);
        echo json_encode(['success' => true, 'message' => 'Item atualizado com sucesso!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
}

function salvarReprocessoGerandoOE(LoteNovoRepository $repo)
{

    $dados = $_POST;
    $usuarioId = $_SESSION['codUsuario'] ?? 1;

    try {
        // Verifica sessão básica
        if (!isset($_SESSION['codUsuario'])) {
            throw new Exception("Sessão expirada. Faça login novamente.");
        }

        // CORREÇÃO: Passando $_POST (dados) e o ID do Usuário
        // O $loteRepo já está instanciado no início dessa seção do arquivo
        $resultado = $repo->salvarReprocessoGerandoOE($dados, $usuarioId);

        echo json_encode($resultado);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao solicitar reprocesso: ' . $e->getMessage()
        ]);
    }
}


/**
 * Função de controle universal para impressão de etiquetas de lote.
 * Ela recebe o tipo de item (producao ou embalagem) e o ID,
 * e passa para o LabelService para fazer o trabalho pesado.
 */

function imprimirEtiquetaLoteItem(PDO $pdo, $labelService)
{
    try {
        // 1. Validação
        $itemId = filter_input(INPUT_POST, 'itemId', FILTER_VALIDATE_INT);
        $itemType = $_POST['itemType'] ?? '';
        $clienteId = filter_input(INPUT_POST, 'clienteId', FILTER_VALIDATE_INT);

        // Tratamento para clienteId false/null
        if (!$clienteId) $clienteId = null;

        if (!$itemId || !in_array($itemType, ['producao', 'embalagem'])) {
            throw new Exception("Dados inválidos (ID ou Tipo) para gerar etiqueta.");
        }

        // 2. Chama o serviço 
        $labelData = $labelService->gerarZplParaItemLote($itemId, $itemType, $clienteId);

        if ($labelData === null || empty($labelData['zpl'])) {
            throw new Exception('O serviço retornou ZPL vazio. Verifique logs anteriores.');
        }

        $zpl = $labelData['zpl'];
        $filename = $labelData['filename'];

        // =================================================================
        // LÓGICA DINÂMICA: Detectar tamanho baseado no ZPL
        // =================================================================

        // 1. Densidade da impressora física (203dpi é o padrão)
        $dpi = 203;
        $dpmm = 8; // Equivalente a 8 dots per mm para a URL do Labelary (8 pontos por milimetro)

        // 2. Tenta encontrar a largura em pontos (^PW)
        // O padrão regex busca por ^PW seguido de números
        if (preg_match('/\^PW(\d+)/', $zpl, $matchLargura)) {
            $larguraDots = (int)$matchLargura[1];
        } else {
            $larguraDots = 718; // Fallback: largura padrão se não tiver ^PW (aprox 9cm)
        }

        // 3. Tenta encontrar a altura em pontos (^LL)
        if (preg_match('/\^LL(\d+)/', $zpl, $matchAltura)) {
            $alturaDots = (int)$matchAltura[1];
        } else {
            $alturaDots = 479; // Fallback: altura padrão se não tiver ^LL (aprox 6cm)
        }

        // 4. Converte Pontos para Polegadas (Dots / DPI)
        // O Labelary exige o formato em polegadas (ex: 4x6)
        $larguraInches = number_format($larguraDots / $dpi, 2, '.', '');
        $alturaInches  = number_format($alturaDots / $dpi, 2, '.', '');

        // Log para conferência (pode remover depois)
        error_log("📏 Tamanho Detectado: {$larguraDots}x{$alturaDots} dots -> {$larguraInches}x{$alturaInches} pol");

        // 5. Monta a URL Dinâmica
        $apiUrl = "http://api.labelary.com/v1/printers/{$dpmm}dpmm/labels/{$larguraInches}x{$alturaInches}/0/";
        // =================================================================

        // 3. Conversão na API Labelary (PONTO CRÍTICO DE REDE)
        $curl = curl_init($apiUrl);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $zpl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($curl, CURLOPT_TIMEOUT, 10); // Dica: Adicione timeout para não travar o servidor
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: application/pdf']);
        $pdfContent = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode != 200) {
            $erroCurl = curl_error($curl);
            throw new Exception('Erro ao converter na API Labelary. Código: ' . $httpCode);
        }
        // curl_close($curl);

        // 4. Salvar Arquivo (PONTO CRÍTICO DE PERMISSÃO)
        $tempDir = __DIR__ . '/temp_labels/';
        if (!is_dir($tempDir)) {
            if (!mkdir($tempDir, 0775, true)) {
                throw new Exception("Falha ao criar pasta de etiquetas temporárias. Verifique permissões.");
            }
        }

        $filePath = $tempDir . $filename;
        if (file_put_contents($filePath, $pdfContent) === false) {
            throw new Exception("Falha ao salvar o arquivo PDF no disco. Verifique permissões.");
        }

        $publicUrl = 'temp_labels/' . $filename;

        echo json_encode([
            'success' => true,
            'pdfUrl' => $publicUrl,
            'fileName' => $filename
        ]);
    } catch (Throwable $e) { // Throwable pega tanto Exception quanto Error (erros fatais)
        error_log($e->getTraceAsString());
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
    try {
        $id = filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Erro: ID de template inválido ou não fornecido.']);
            return;
        }

        // Tenta buscar o template. Se houver um erro de PDO aqui, o 'catch' irá pegá-lo.
        $template = $repo->find($id);

        if ($template) {
            echo json_encode(['success' => true, 'data' => $template]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Template com ID ' . htmlspecialchars($id) . ' não foi encontrado.']);
        }
    } catch (PDOException $e) {
        // Se QUALQUER erro de banco de dados ocorrer no bloco 'try', ele será capturado aqui.
        error_log("ERRO FATAL EM getTemplate: " . $e->getMessage()); // Grava o erro real e detalhado no log do servidor.

        // E envia uma mensagem de erro JSON clara e útil para o frontend.
        echo json_encode(['success' => false, 'message' => 'Erro Crítico de Banco de Dados: ' . $e->getMessage()]);
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
        // $backupService = new BackupService($dbConfig);
        $backupService = new BackupService();
        $filename = $backupService->gerarBackup();

        echo json_encode(['success' => true, 'filename' => $filename]);
    } catch (Exception $e) {
        error_log("Erro ao criar backup: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function listarCarregamentos(CarregamentoRepository $repo)
{
    // A função do repositório agora faz todo o trabalho
    $output = $repo->findAllForDataTable($_POST);
    echo json_encode($output);
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
    try {
        $newId = $repo->salvarCarregamentoHeader($_POST, $userId);
        echo json_encode(['success' => true, 'message' => 'Carregamento criado com sucesso!', 'carregamento_id' => $newId]);
    } catch (Exception $e) {
        error_log("Erro em salvarCarregamentoHeader: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getOrdensParaCarregamentoSelect(OrdemExpedicaoRepository $repo)
{
    // O Select2 envia o termo de busca via POST por padrão na sua configuração    
    $term = $_POST['term'] ?? ''; // Select2 envia por POST

    // Pegamos o tipo da URL (ex: ?action=...&tipo=REPROCESSO)
    $tipo = $_GET['tipo'] ?? 'NORMAL';

    $results = $repo->findOrdensParaCarregamentoSelect($term, $tipo);
    echo json_encode(['results' => $results]);
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
        $repo->excluir($carregamentoId); // Chama a função do Repositório
        echo json_encode(['success' => true, 'message' => 'Carregamento excluído permanentemente!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function reabrirCarregamento(CarregamentoRepository $carregamentoRepo, $ordemExpedicaoRepo)
{
    try {
        $id = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        $motivo = trim($_POST['motivo'] ?? '');
        if (!$id || empty($motivo)) {
            throw new Exception("Dados inválidos para reabertura.");
        }
        // Passa o repositório da OE para a função
        $carregamentoRepo->reabrir($id, $motivo, $ordemExpedicaoRepo);
        echo json_encode(['success' => true, 'message' => 'Carregamento reaberto com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getFotosDaFila(CarregamentoRepository $repo)
{
    // Usamos GET pois estamos apenas buscando dados
    $filaId = filter_input(INPUT_GET, 'fila_id', FILTER_VALIDATE_INT);

    if (!$filaId) {
        echo json_encode(['success' => false, 'message' => 'ID da fila inválido.']);
        return;
    }

    try {
        $fotos = $repo->findFotosByFilaId($filaId);
        // Adicionando a URL base para facilitar a vida do JavaScript
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/marchef/public/";

        foreach ($fotos as &$foto) {
            $foto['full_url'] = $baseUrl . $foto['foto_path'];
        }

        echo json_encode(['success' => true, 'data' => $fotos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function addFotoFila(CarregamentoRepository $repo)
{
    try {
        // Chama a nova função 'addFotos' que processa múltiplos arquivos
        $count = $repo->addFotos($_POST, $_FILES);

        // Retorna uma mensagem com a contagem de fotos salvas
        echo json_encode(['success' => true, 'message' => "{$count} foto(s) salva(s) com sucesso!"]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getCarregamentoItemDetalhes(CarregamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'car_item_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID do Item inválido.");

        $data = $repo->getCarregamentoItemDetalhes($id);
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateCarregamentoItemQuantidade(CarregamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'car_item_id', FILTER_VALIDATE_INT);
        $qtd = filter_input(INPUT_POST, 'edit_quantidade', FILTER_VALIDATE_FLOAT);
        if (!$id || $qtd === false) {
            throw new Exception("Dados inválidos.");
        }

        $repo->updateCarregamentoItemQuantidade($id, $qtd);
        echo json_encode(['success' => true, 'message' => 'Quantidade atualizada.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getEnderecosParaCarregamentoPorLoteItem(CarregamentoRepository $repo)
{
    // ### Buscar os dois IDs ###
    $loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);
    $produtoId = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);

    if (!$loteId || !$produtoId) { // Precisa dos dois
        echo json_encode(['results' => []]);
        return;
    }

    echo json_encode(['results' => $repo->getEnderecosParaCarregamentoPorLoteItem($loteId, $produtoId)]);
}

function getLotesParaCarregamentoPorProduto(CarregamentoRepository $repo)
{
    $produtoId = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);
    if (!$produtoId) {
        echo json_encode(['results' => []]);
        return;
    }
    // Chama a função do CarregamentoRepository
    echo json_encode(['results' => $repo->getLotesParaCarregamentoPorProduto($produtoId)]);
}

function finalizarCarregamento(CarregamentoRepository $carregamentoRepo, OrdemExpedicaoRepository $ordemExpedicaoRepo)
{
    try {
        // 1. Inputs
        $id = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception("ID inválido.");
        }

        // 2. Validação Sessão (Verifique se no seu sistema é 'usu_codigo' ou 'codUsuario')
        if (!isset($_SESSION['usu_codigo'])) {
            throw new Exception("Sessão expirada.");
        }
        $usuarioId = (int)$_SESSION['usu_codigo'];

        // 3. Execução
        $carregamentoRepo->finalizar($id, $usuarioId, $ordemExpedicaoRepo);

        echo json_encode(['success' => true, 'message' => 'Carregamento finalizado com sucesso.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- FUNÇÕES DE CONTROLE PARA DETALHES DO CARREGAMENTO ---

function getCarregamentoDetalhesCompletos(CarregamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID inválido.");

        $data = $repo->getDetalhesCompletos($id);
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateCarregamentoHeader(CarregamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID do Carregamento inválido.");

        $repo->updateHeader($id, $_POST);
        echo json_encode(['success' => true, 'message' => 'Cabeçalho atualizado.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function addFilaCarregamento(CarregamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID inválido.");

        $filaId = $repo->addFila($id);
        echo json_encode(['success' => true, 'fila_id' => $filaId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function removeFilaCarregamento(CarregamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'fila_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID inválido.");

        $repo->removeFila($id);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function removeItemCarregamento(CarregamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'car_item_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID do item inválido.");

        $repo->removeItem($id);
        echo json_encode(['success' => true, 'message' => 'Item removido']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function addItemCarregamentoFromOE(CarregamentoRepository $repo)
{
    try {
        $id = $repo->addItemFromOE($_POST);
        echo json_encode(['success' => true, 'item_id' => $id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function addItemCarregamento(CarregamentoRepository $repo)
{
    try {
        $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
        if (!$carregamentoId) {
            throw new Exception("ID do Carregamento não foi enviado.");
        }

        // Esta linha é modificada para passar o ID lido do POST
        $novoItemId = $repo->addItemCarregamento($_POST, $carregamentoId);
        echo json_encode(['success' => true, 'item_id' => $novoItemId, 'message' => 'Item adicionado com sucesso!']);
    } catch (Exception $e) {
        http_response_code(400); // Erro de regra de negócio
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- FUNÇÕES DE CONTROLE PARA O MODAL CASCATA ---

function getClientesDaOE(CarregamentoRepository $repo)
{
    $oeId = filter_input(INPUT_POST, 'oe_id', FILTER_VALIDATE_INT);
    if (!$oeId) {
        echo json_encode(['results' => []]);
        return;
    }
    echo json_encode(['results' => $repo->getClientesDaOE($oeId)]);
}

function getProdutosDoClienteNaOE(CarregamentoRepository $repo)
{
    try {
        $oeId = filter_input(INPUT_POST, 'oe_id', FILTER_VALIDATE_INT);
        $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
        if (!$oeId || !$clienteId) {
            echo json_encode(['results' => []]);
            return;
        }

        $results = $repo->getProdutosDoClienteNaOE($oeId, $clienteId);
        echo json_encode(['results' => $results]);
    } catch (Exception $e) {
        // Se houver um erro de SQL aqui, ele será capturado
        error_log("Erro em getProdutosDoClienteNaOE: " . $e->getMessage());
        echo json_encode(['results' => [], 'error' => $e->getMessage()]);
    }
}

function getLotesDoProdutoNaOE(CarregamentoRepository $repo)
{
    $oeId = filter_input(INPUT_POST, 'oe_id', FILTER_VALIDATE_INT);
    $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $produtoId = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $carregamentoId = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);

    if (!$oeId || !$clienteId || !$produtoId || !$carregamentoId) {
        echo json_encode(['results' => [], 'message' => 'Dados incompletos']);
        return;
    }
    echo json_encode(['results' => $repo->getLotesDoProdutoNaOE($oeId, $clienteId, $produtoId, $carregamentoId)]);
}

function removeClienteFromFila(CarregamentoRepository $repo)
{
    try {
        $filaId = filter_input(INPUT_POST, 'fila_id', FILTER_VALIDATE_INT);
        $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
        if (!$filaId || !$clienteId) {
            throw new Exception("IDs de Fila e Cliente são obrigatórios.");
        }

        $repo->removeClienteFromFila($filaId, $clienteId);
        echo json_encode(['success' => true, 'message' => 'Cliente removido da fila.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getResumoParaFinalizar(CarregamentoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'carregamento_id', FILTER_VALIDATE_INT);
    if (!$id) { /* tratamento de erro */
    }
    $resumo = $repo->getResumoParaFinalizacao($id);
    echo json_encode(['success' => true, 'data' => $resumo]);
}

// --- FUNÇÕES DE CONTROLE PARA O DASHBOARD (KPIs) ---

function getKpiLotesAtivos(LoteNovoRepository $repo)
{
    try {
        $count = $repo->countByStatus('Aberto');
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getKpiCarregamentosHoje(CarregamentoRepository $repo)
{
    try {
        $count = $repo->countForToday();
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getKpiTotalUsuarios(UsuarioRepository $repo)
{
    try {
        $count = $repo->countAll();
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getKpiTotalProdutos(ProdutoRepository $repo)
{
    try {
        $count = $repo->countAll();
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- FUNÇÃO DE CONTROLE PARA O GRÁFICO DO DASHBOARD ---
function getGraficoLotesFinalizados(LoteNovoRepository $repo)
{
    try {
        // Pega os dados dos últimos 7 dias
        $chartData = $repo->getDailyFinalizedCountForLastDays(7);
        echo json_encode(['success' => true, 'data' => $chartData]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- FUNÇÕES DE CONTROLE PARA OS PAINÉIS DO DASHBOARD ---

function getPainelLotesAtivos(LoteNovoRepository $repo)
{
    try {
        // Busca os 5 lotes ativos mais antigos
        $lotes = $repo->findActiveLots(5);
        echo json_encode(['success' => true, 'data' => $lotes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getPainelCarregamentosAbertos(CarregamentoRepository $repo)
{
    try {
        // Busca os 5 carregamentos em aberto mais antigos
        $carregamentos = $repo->findOpenShipments(5);
        echo json_encode(['success' => true, 'data' => $carregamentos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getKpiEstoquePorCamara(EnderecoRepository $repo)
{
    try {
        $resumo = $repo->getResumoEstoquePorCamara();
        echo json_encode(['success' => true, 'data' => $resumo]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- FUNÇÃO DE CONTROLE PARA O PAINEL DE PRODUÇÃO ---
function getPainelProducaoLotes(LoteNovoRepository $repo)
{
    try {
        // Reutilizamos a função do gerente, mas podemos pedir mais itens se quisermos (ex: 10)
        $lotes = $repo->findActiveLots(10);
        echo json_encode(['success' => true, 'data' => $lotes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getPainelProducaoLotesFinalizados(LoteNovoRepository $repo)
{
    try {
        // Busca os 5 lotes finalizados mais recentes
        $lotes = $repo->findRecentlyFinishedLots(5);
        echo json_encode(['success' => true, 'data' => $lotes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Controller para buscar o estoque de sobras e formatar para o frontend.
 */
function getEstoqueDeSobras(LoteNovoRepository $repo)
{
    try {
        $sobras = $repo->findSaldosDeProducaoFinalizados();

        // Retorna no formato {data: [...]} que o DataTables espera
        echo json_encode(['data' => $sobras]);
    } catch (Exception $e) {
        echo json_encode(['data' => [], 'error' => $e->getMessage()]);
    }
}

// --- FUNÇÃO DE CONTROLE PARA ESTOQUE (CÂMARAS) ---
function listarCamaras(CamaraRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function getCamara(CamaraRepository $repo)
{
    $id = filter_input(INPUT_POST, 'camara_id', FILTER_VALIDATE_INT);
    $data = $id ? $repo->find($id) : null;
    echo json_encode(['success' => !!$data, 'data' => $data]);
}

function salvarCamara(CamaraRepository $repo)
{
    $id = filter_input(INPUT_POST, 'camara_id', FILTER_VALIDATE_INT);
    try {
        if ($id) {
            $repo->update($id, $_POST);
            $message = 'Câmara atualizada com sucesso!';
        } else {
            $repo->create($_POST);
            $message = 'Câmara cadastrada com sucesso!';
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirCamara(CamaraRepository $repo)
{
    $id = filter_input(INPUT_POST, 'camara_id', FILTER_VALIDATE_INT);
    try {
        if ($id && $repo->delete($id)) {
            echo json_encode(['success' => true, 'message' => 'Câmara excluída com sucesso!']);
        } else {
            throw new Exception('Câmara não encontrada.');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
    }
}

// --- FUNÇÃO DE CONTROLE PARA ESTOQUE (ENDEREÇOS) ---
function getCamaraOptions(EnderecoRepository $repo)
{
    echo json_encode(['success' => true, 'data' => $repo->getCamaraOptions()]);
}

function listarEnderecosCamaras(EnderecoRepository $repo)
{
    $camaraId = filter_input(INPUT_POST, 'camara_id', FILTER_VALIDATE_INT);
    if (!$camaraId) {
        echo json_encode(["draw" => 1, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
        return;
    }
    echo json_encode($repo->findAllForDataTable($camaraId, $_POST));
}

function getEnderecoCamaras(EnderecoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'endereco_id', FILTER_VALIDATE_INT);
    $data = $id ? $repo->find($id) : null;
    echo json_encode(['success' => !!$data, 'data' => $data]);
}

function salvarEnderecoCamaras(EnderecoRepository $repo)
{
    try {
        $repo->save($_POST);
        echo json_encode(['success' => true, 'message' => 'Endereço salvo com sucesso!']);
    } catch (Exception $e) {
        // --- LÓGICA PARA TRATAR A DUPLICATA ---
        $message = $e->getMessage();
        if (strpos($message, 'DUPLICATE_ENTRY:') === 0) {
            $parts = explode(':', $message);
            $existingId = (int) $parts[1];
            echo json_encode([
                'success' => false,
                'error_type' => 'duplicate_entry',
                'message' => 'Este endereço já está cadastrado.',
                'existing_id' => $existingId
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $message]);
        }
        // --- FIM DA LÓGICA ---
    }
}

function excluirEnderecoCamaras(EnderecoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'endereco_id', FILTER_VALIDATE_INT);
    try {
        if ($id && $repo->delete($id)) {
            echo json_encode(['success' => true, 'message' => 'Endereço excluído com sucesso!']);
        } else {
            throw new Exception('Endereço não encontrado.');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
    }
}

function alocarItemEndereco(EnderecoRepository $repo)
{
    try {
        $enderecoId = filter_input(INPUT_POST, 'endereco_id', FILTER_VALIDATE_INT);
        $loteItemId = filter_input(INPUT_POST, 'lote_item_id', FILTER_VALIDATE_INT);
        $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_FLOAT);
        $usuarioId = $_SESSION['codUsuario'] ?? null;

        if (!$enderecoId || !$loteItemId || !$quantidade || !$usuarioId) {
            throw new Exception("Dados insuficientes (endereço, item, quantidade e usuário são obrigatórios).");
        }

        if ($repo->alocarItem($enderecoId, $loteItemId, $quantidade, $usuarioId)) {
            echo json_encode(['success' => true, 'message' => 'Item alocado com sucesso!']);
        } else {
            throw new Exception('Não foi possível alocar o item.');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function desalocarItemEndereco(EnderecoRepository $repo)
{
    try {
        // 1. Validação do ID da Alocação.
        $alocacaoId = filter_input(INPUT_POST, 'alocacao_id', FILTER_VALIDATE_INT);
        if (!$alocacaoId) {
            throw new Exception("ID da alocação é obrigatório.");
        }

        // 2. Validação do Usuário
        // Precisamos garantir que existe um usuário logado para assinar o histórico
        //if (!isset($_SESSION['codUsuario'])) {
        if (!isset($_SESSION['codUsuario'])) {
            throw new Exception("Sessão inválida ou expirada");
        }
        //$usuarioId = (int)$_SESSION['codUsuario'];
        $usuarioId = (int)$_SESSION['codUsuario'];

        // 3. Chama o Repositório, passando os DOIS parâmetros
        if ($repo->desalocarItem($alocacaoId, $usuarioId)) {
            echo json_encode(['success' => true, 'message' => 'Item desalocado e endereço liberado com sucesso!']);
        } else {
            throw new Exception('Não foi possível desalocar o item.');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getItensNaoAlocados(EnderecoRepository $repo)
{
    try {
        $term = $_GET['term'] ?? '';
        $itens = $repo->findItensNaoAlocadosParaSelect($term);
        // A resposta precisa estar no formato que o Select2 espera: { results: [...] }
        echo json_encode(['results' => $itens]);
    } catch (Exception $e) {
        echo json_encode(['results' => [], 'message' => $e->getMessage()]);
    }
}

function getVisaoEstoqueHierarquico(EnderecoRepository $repo)
{
    try {
        $data = $repo->getVisaoHierarquicaEstoque();
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Função para buscar a visão hierárquica do estoque filtrada.
 */
function getVisaoEstoqueHierarquicoFiltrada(EnderecoRepository $repo)
{
    try {
        // Captura o termo de busca enviado via GET
        $term = trim($_GET['term'] ?? '');

        if (empty($term)) {
            // Se o termo estiver vazio, chama a função não filtrada
            $data = $repo->getVisaoHierarquicaEstoque();
        } else {
            // Se houver termo, chama a função filtrada (que você precisará criar)
            // Assumo que você irá criar o método getVisaoHierarquicaEstoqueFiltrada no EnderecoRepository
            $data = $repo->getVisaoHierarquicaEstoqueFiltrada($term);
        }

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function transferirItemEndereco(EnderecoRepository $repo)
{
    try {
        // 1. Inputs
        $alocacaoOrigemId = filter_input(INPUT_POST, 'alocacao_origem_id', FILTER_VALIDATE_INT);
        $enderecoDestinoId = filter_input(INPUT_POST, 'endereco_destino_id', FILTER_VALIDATE_INT);
        $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_FLOAT);

        // 2. Validação Sessão
        if (!isset($_SESSION['codUsuario'])) throw new Exception("Sessão expirada.");
        $usuarioId = $_SESSION['codUsuario'];

        if (!$alocacaoOrigemId || !$enderecoDestinoId || !$quantidade) {
            throw new Exception("Dados incompletos para transferência.");
        }

        // 3. Execução
        // Nota: $enderecoRepo deve estar instanciado anteriormente no router
        $sucesso = $repo->transferirItem(
            $alocacaoOrigemId,
            $enderecoDestinoId,
            $quantidade,
            $usuarioId
        );

        if ($sucesso) {
            echo json_encode(['success' => true, 'message' => 'Transferência realizada com sucesso!']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function buscarEnderecosSelect(EnderecoRepository $repo)
{
    $term = $_GET['term'] ?? '';
    $results = $repo->buscarEnderecosParaSelect($term);
    echo json_encode(['results' => $results]);
}

function relatorioKardex(MovimentoRepository $repo)
{
    // Pega todos os parâmetros $_POST (filtros, paginação, draw)
    $dados = $repo->buscarKardexDataTable($_POST);
    echo json_encode($dados);
}

// --- FUNÇÕES DE CONTROLE PARA ORDENS DE EXPEDIÇÃO ---
function listarOrdensExpedicao(OrdemExpedicaoRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function getNextOrderNumber(OrdemExpedicaoRepository $repo)
{
    try {
        echo json_encode(['success' => true, 'numero' => $repo->getNextOrderNumber()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function salvarOrdemExpedicaoHeader(OrdemExpedicaoRepository $repo, int $usuarioId)
{
    try {
        $id = $repo->createHeader($_POST, $usuarioId);
        echo json_encode(['success' => true, 'message' => 'Cabeçalho salvo com sucesso!', 'oe_id' => $id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
    }
}

function getOrdemExpedicaoCompleta(OrdemExpedicaoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'oe_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        return;
    }
    $header = $repo->findOrdemCompleta($id);
    echo json_encode(['success' => !!$header, 'data' => $header]);
}

function addPedidoClienteOrdem(OrdemExpedicaoRepository $repo)
{
    try {
        $id = $repo->addPedidoCliente($_POST);
        echo json_encode(['success' => true, 'message' => 'Pedido/Cliente adicionado com sucesso!', 'oep_id' => $id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar: ' . $e->getMessage()]);
    }
}

function listarEstoqueParaSelecao(OrdemExpedicaoRepository $repo)
{
    echo json_encode($repo->findEstoqueAlocadoParaSelecao($_POST));
}

function addItemPedidoOrdem(OrdemExpedicaoRepository $repo)
{
    try {
        $repo->addItemPedido($_POST);
        echo json_encode(['success' => true, 'message' => 'Item adicionado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar item: ' . $e->getMessage()]);
    }
}

function getProdutosComEstoqueDisponivel(OrdemExpedicaoRepository $repo)
{
    $term = $_GET['term'] ?? '';
    echo json_encode(['results' => $repo->getProdutosComEstoqueDisponivel($term)]);
}

function getLotesDisponiveisPorProduto(OrdemExpedicaoRepository $repo)
{
    $produtoId = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);
    echo json_encode(['results' => $repo->getLotesDisponiveisPorProduto($produtoId)]);
}

function getEnderecosDisponiveisPorLoteItem(OrdemExpedicaoRepository $repo)
{
    $loteItemId = filter_input(INPUT_GET, 'lote_item_id', FILTER_VALIDATE_INT);
    echo json_encode(['results' => $repo->getEnderecosDisponiveisPorLoteItem($loteItemId)]);
}

function removePedidoOrdem(OrdemExpedicaoRepository $repo)
{
    try {
        $pedidoId = filter_input(INPUT_POST, 'oep_id', FILTER_VALIDATE_INT);
        if (!$pedidoId) {
            throw new Exception("ID do pedido inválido.");
        }
        $repo->removePedido($pedidoId);
        echo json_encode(['success' => true, 'message' => 'Pedido removido com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao remover pedido: ' . $e->getMessage()]);
    }
}

function removeItemPedidoOrdem(OrdemExpedicaoRepository $repo)
{
    try {
        $itemId = filter_input(INPUT_POST, 'oei_id', FILTER_VALIDATE_INT);
        if (!$itemId) {
            throw new Exception("ID do item inválido.");
        }
        $repo->removeItem($itemId);
        echo json_encode(['success' => true, 'message' => 'Item removido com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao remover item: ' . $e->getMessage()]);
    }
}

function getItemDetalhesParaEdicao(OrdemExpedicaoRepository $repo)
{
    $oeiId = filter_input(INPUT_POST, 'oei_id', FILTER_VALIDATE_INT);
    if (!$oeiId) {
        echo json_encode(['success' => false, 'message' => 'ID do item inválido.']);
        return;
    }

    $detalhes = $repo->findItemDetalhesParaEdicao($oeiId);

    if ($detalhes) {
        echo json_encode(['success' => true, 'data' => $detalhes]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item não encontrado.']);
    }
}

function getReservaDetalhes(OrdemExpedicaoRepository $repo)
{
    $alocacaoId = filter_input(INPUT_POST, 'alocacao_id', FILTER_VALIDATE_INT);
    if (!$alocacaoId) {
        echo json_encode(['success' => false, 'message' => 'ID de alocação inválido.']);
        return;
    }
    $detalhes = $repo->findReservaDetalhesPorAlocacao($alocacaoId);
    echo json_encode(['success' => true, 'data' => $detalhes]);
}

function updateItemPedido(OrdemExpedicaoRepository $repo)
{
    try {
        $oeiId = filter_input(INPUT_POST, 'oei_id', FILTER_VALIDATE_INT);
        if (!$oeiId) {
            throw new Exception("ID do item não fornecido.");
        }

        if ($repo->updateItem($oeiId, $_POST)) {
            echo json_encode(['success' => true, 'message' => 'Item atualizado com sucesso!']);
        } else {
            // Este caso é raro, pois uma exceção seria lançada antes
            echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar o item.']);
        }
    } catch (Exception $e) {
        // Captura exceções de validação do repositório
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getOrdensParaFaturamentoSelect(OrdemExpedicaoRepository $repo)
{
    // O Select2 espera os dados dentro de uma chave 'results'
    echo json_encode(['results' => $repo->findForSelect()]);
}

function salvarOrdemClientes(OrdemExpedicaoRepository $repo)
{
    // Espera um array de IDs na ordem correta
    $ordemIds = $_POST['ordem'] ?? [];
    if (empty($ordemIds) || !is_array($ordemIds)) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma ordem recebida.']);
        return;
    }

    try {
        $repo->salvarOrdemClientes($ordemIds);
        echo json_encode(['success' => true, 'message' => 'Ordem de carregamento salva com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar a ordem: ' . $e->getMessage()]);
    }
}

function excluirOrdemExpedicao(OrdemExpedicaoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'oe_id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception("ID da Ordem de Expedição inválido.");
        }
        $repo->delete($id);
        echo json_encode(['success' => true, 'message' => 'Ordem de Expedição excluída com sucesso!']);
    } catch (Exception $e) {
        // Captura erros, incluindo a validação de status
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- FUNÇÕES DE CONTROLE PARA FATURAMENTO ---
function getFaturamentoDadosPorOrdem(FaturamentoRepository $repo)
{
    $ordemId = filter_input(INPUT_POST, 'ordem_id', FILTER_VALIDATE_INT);
    if (!$ordemId) {
        echo json_encode(['success' => false, 'message' => 'ID da Ordem de Expedição inválido.']);
        return;
    }

    try {
        $dados = $repo->getDadosAgrupadosPorOrdemExpedicao($ordemId);
        echo json_encode(['success' => true, 'data' => $dados]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar dados: ' . $e->getMessage()]);
    }
}

function salvarResumoFaturamento(FaturamentoRepository $repo, int $usuarioId)
{
    $ordemId = filter_input(INPUT_POST, 'ordem_id', FILTER_VALIDATE_INT);
    if (!$ordemId) {
        echo json_encode(['success' => false, 'message' => 'ID da Ordem de Expedição inválido.']);
        return;
    }

    try {
        $novoResumoId = $repo->salvarResumo($ordemId, $usuarioId);
        echo json_encode([
            'success' => true,
            'message' => 'Resumo de faturamento gerado e salvo com sucesso!',
            'resumo_id' => $novoResumoId
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getFaturamentoItemDetalhes(FaturamentoRepository $repo)
{
    $fatiId = filter_input(INPUT_POST, 'fati_id', FILTER_VALIDATE_INT);
    if (!$fatiId) {
        echo json_encode(['success' => false, 'message' => 'ID do item de faturamento inválido.']);
        return;
    }
    $detalhes = $repo->findItemDetalhes($fatiId);
    echo json_encode(['success' => true, 'data' => $detalhes]);
}

function salvarFaturamentoItem(FaturamentoRepository $repo)
{
    try {
        $fatiId = filter_input(INPUT_POST, 'fati_id', FILTER_VALIDATE_INT);
        if (!$fatiId) {
            throw new Exception("ID do item de faturamento não fornecido.");
        }
        $repo->updateItem($fatiId, $_POST);
        echo json_encode(['success' => true, 'message' => 'Item atualizado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getResumoSalvo(FaturamentoRepository $repo)
{
    $resumoId = filter_input(INPUT_POST, 'resumo_id', FILTER_VALIDATE_INT);
    if (!$resumoId) {
        echo json_encode(['success' => false, 'message' => 'ID do Resumo inválido.']);
        return;
    }
    $dados = $repo->getResumoSalvo($resumoId);
    echo json_encode(['success' => true, 'data' => $dados]);
}

function listarFaturamentos(FaturamentoRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function getCondicoesPagamentoOptions(FaturamentoRepository $repo)
{
    // O Select2 espera os dados dentro de uma chave 'results'
    echo json_encode(['results' => $repo->getCondicoesPagamentoOptions()]);
}

function getNotaGrupoDetalhes(FaturamentoRepository $repo)
{
    $fatnId = filter_input(INPUT_POST, 'fatn_id', FILTER_VALIDATE_INT);
    if (!$fatnId) {
        echo json_encode(['success' => false, 'message' => 'ID do grupo de nota inválido.']);
        return;
    }
    $detalhes = $repo->getNotaGrupoDetalhes($fatnId);
    echo json_encode(['success' => true, 'data' => $detalhes]);
}

function salvarNotaGrupo(FaturamentoRepository $repo)
{
    try {
        $fatnId = filter_input(INPUT_POST, 'fatn_id', FILTER_VALIDATE_INT);
        if (!$fatnId) {
            throw new Exception("ID do grupo de nota não fornecido.");
        }
        $repo->updateNotaGrupo($fatnId, $_POST);
        echo json_encode(['success' => true, 'message' => 'Grupo de Pedido atualizado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function salvarDadosTransporte(FaturamentoRepository $repo)
{
    try {
        $resumoId = filter_input(INPUT_POST, 'fat_resumo_id', FILTER_VALIDATE_INT);
        if (!$resumoId) {
            throw new Exception("ID do Resumo não fornecido.");
        }
        $repo->salvarDadosTransporte($resumoId, $_POST);
        echo json_encode(['success' => true, 'message' => 'Dados de transporte salvos!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirFaturamento(FaturamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'resumo_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID inválido.");
        $repo->delete($id);
        echo json_encode(['success' => true, 'message' => 'Faturamento excluído com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function marcarComoFaturado(FaturamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'resumo_id', FILTER_VALIDATE_INT);
        $notasJson = $_POST['notas'] ?? '[]';
        $notas = json_decode($notasJson, true);
        if (!$id || !is_array($notas))
            throw new Exception("Dados inválidos.");
        $repo->marcarComoFaturado($id, $notas);
        echo json_encode(['success' => true, 'message' => 'Faturamento marcado como "Faturado" com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getGruposDeNotaParaFaturamento(FaturamentoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'resumo_id', FILTER_VALIDATE_INT);
    if (!$id) { /* tratamento de erro */
    }
    $grupos = $repo->getGruposDeNotaParaFaturamento($id);
    echo json_encode(['success' => true, 'data' => $grupos]);
}

function cancelarFaturamento(FaturamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'resumo_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID inválido.");
        $repo->cancelar($id);
        echo json_encode(['success' => true, 'message' => 'Faturamento cancelado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function reabrirFaturamento(FaturamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'resumo_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID inválido.");
        $repo->reabrir($id);
        echo json_encode(['success' => true, 'message' => 'Faturamento reaberto com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}


// --- FUNÇÕES DE CONTROLE PARA CONDIÇÕES DE PAGAMENTO ---

function listarCondicoesPagamento(CondicaoPagamentoRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function getCondicaoPagamento(CondicaoPagamentoRepository $repo)
{
    $id = filter_input(INPUT_POST, 'cond_id', FILTER_VALIDATE_INT);
    $data = $id ? $repo->find($id) : null;
    echo json_encode(['success' => !!$data, 'data' => $data]);
}

function salvarCondicaoPagamento(CondicaoPagamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'cond_id', FILTER_VALIDATE_INT);
        if ($id) {
            $repo->update($id, $_POST);
            $message = 'Condição atualizada com sucesso!';
        } else {
            $repo->create($_POST);
            $message = 'Condição cadastrada com sucesso!';
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirCondicaoPagamento(CondicaoPagamentoRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'cond_id', FILTER_VALIDATE_INT);
        if (!$id)
            throw new Exception("ID inválido.");
        $repo->delete($id);
        echo json_encode(['success' => true, 'message' => 'Condição excluída com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- FUNÇÕES DE CONTROLE PARA FICHAS TÉCNICAS ---
function listarFichasTecnicas(FichaTecnicaRepository $repo)
{
    echo json_encode($repo->findAllForDataTable($_POST));
}

function getFichaTecnicaCompleta(FichaTecnicaRepository $repo)
{
    $id = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID da Ficha inválido.']);
        return;
    }
    $ficha = $repo->findCompletaById($id);
    if ($ficha) {
        echo json_encode(['success' => true, 'data' => $ficha]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ficha Técnica não encontrada.']);
    }
}

function getProdutosSemFichaTecnica(FichaTecnicaRepository $repo)
{
    try {
        $term = $_GET['term'] ?? '';
        $data = $repo->findProdutosSemFichaTecnica($term);
        echo json_encode(['results' => $data]);
    } catch (\PDOException $e) {
        // Captura especificamente erros de SQL e retorna a mensagem
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro de Banco de Dados: ' . $e->getMessage()]);
    }
}

function getFabricanteOptionsFT(FichaTecnicaRepository $repo)
{
    try {
        $term = $_GET['term'] ?? '';
        $data = $repo->getFabricanteOptions($term);
        echo json_encode(['results' => $data]);
    } catch (\PDOException $e) {
        // Captura especificamente erros de SQL e retorna a mensagem
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro de Banco de Dados: ' . $e->getMessage()]);
    }
}

function getProdutoDetalhesParaFicha(FichaTecnicaRepository $repo)
{
    $produtoId = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    if (!$produtoId) { /* tratamento de erro */
    }

    $produto = $repo->getProdutoDetalhes($produtoId);
    if ($produto) {
        echo json_encode(['success' => true, 'data' => $produto]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado.']);
    }
}

function salvarFichaTecnicaGeral(FichaTecnicaRepository $repo, int $usuarioId)
{
    try {
        // Adicionamos o ID do usuário como segundo argumento
        $fichaId = $repo->saveHeader($_POST, $usuarioId);

        echo json_encode([
            'success' => true,
            'message' => 'Dados gerais salvos com sucesso!',
            'ficha_id' => $fichaId // Retornamos o ID para o front-end
        ]);
    } catch (Exception $e) {
        // Em caso de erro, retorna uma mensagem clara
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirFichaTecnica(FichaTecnicaRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception("ID da ficha técnica inválido.");
        }

        $repo->delete($id);

        echo json_encode(['success' => true, 'message' => 'Ficha técnica excluída com sucesso!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function listarCriteriosFicha(FichaTecnicaRepository $repo)
{
    $fichaId = filter_input(INPUT_GET, 'ficha_id', FILTER_VALIDATE_INT);
    if (!$fichaId) {
        echo json_encode(['success' => false, 'data' => []]);
        return;
    }
    $criterios = $repo->getCriteriosByFichaId($fichaId);
    echo json_encode(['success' => true, 'data' => $criterios]);
}

function salvarCriterioFicha(FichaTecnicaRepository $repo)
{
    try {
        // A função do repositório cuidará da lógica de criar ou atualizar
        $repo->saveCriterio($_POST);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirCriterioFicha(FichaTecnicaRepository $repo)
{
    try {
        $id = filter_input(INPUT_POST, 'criterio_id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception("ID do critério inválido.");
        }
        $repo->deleteCriterio($id);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- FUNÇÕES DE CONTROLE PARA FOTOS DA FICHA TÉCNICA ---

function listarFotosFicha(FichaTecnicaRepository $repo)
{
    $fichaId = filter_input(INPUT_GET, 'ficha_id', FILTER_VALIDATE_INT);
    if (!$fichaId) {
        echo json_encode(['success' => false, 'data' => []]);
        return;
    }
    $fotos = $repo->getFotosByFichaId($fichaId);
    echo json_encode(['success' => true, 'data' => $fotos]);
}

function uploadFotoFicha(FichaTecnicaRepository $repo)
{
    try {
        $fichaId = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
        $fotoTipo = $_POST['foto_tipo'] ?? '';

        if (!$fichaId || empty($fotoTipo) || empty($_FILES['foto_arquivo'])) {
            throw new Exception("Dados insuficientes para o upload.");
        }

        // --- LÓGICA DE ORGANIZAÇÃO DE PASTAS ---
        $codigoInternoProduto = $repo->getCodigoInternoProdutoByFichaId($fichaId);
        if (!$codigoInternoProduto) {
            throw new Exception("Não foi possível encontrar o produto associado a esta ficha.");
        }
        $nomePasta = preg_replace('/[^a-zA-Z0-9_-]/', '', $codigoInternoProduto);

        $arquivo = $_FILES['foto_arquivo'];

        // Validações do arquivo
        if ($arquivo['error'] !== UPLOAD_ERR_OK)
            throw new Exception("Erro no upload do arquivo.");
        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($arquivo['type'], $tiposPermitidos))
            throw new Exception("Formato de arquivo não permitido.");

        // Cria a pasta do produto se não existir
        $pastaProdutoDir = __DIR__ . '/uploads/fichas_tecnicas/' . $nomePasta . '/';
        if (!is_dir($pastaProdutoDir)) {
            mkdir($pastaProdutoDir, 0775, true);
        }

        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        if (empty($extensao) && $arquivo['type'] == 'image/jpeg')
            $extensao = 'jpg';

        // Cria o nome do arquivo com o código interno e o tipo da foto
        $nomeArquivo = $nomePasta . "_" . $fotoTipo . "." . $extensao;
        $caminhoCompleto = $pastaProdutoDir . $nomeArquivo;
        $caminhoPublico = 'uploads/fichas_tecnicas/' . $nomePasta . '/' . $nomeArquivo;
        // --- FIM DA LÓGICA DE ORGANIZAÇÃO ---

        // Deleta o registro antigo, mas o UNLINK só ocorrerá se o arquivo existir, 
        // e se o novo upload for BEM SUCEDIDO. A função deleteFoto já apaga o registro no BD.
        $repo->deleteFoto($fichaId, $fotoTipo);

        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            throw new Exception("Falha ao mover o arquivo para o destino.");
        }

        $repo->saveFotoPath($fichaId, $fotoTipo, $caminhoPublico);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function excluirFotoFicha(FichaTecnicaRepository $repo)
{
    try {
        $fichaId = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
        $fotoTipo = $_POST['foto_tipo'] ?? '';
        if (!$fichaId || empty($fotoTipo)) {
            throw new Exception("Dados insuficientes para exclusão.");
        }

        $caminhoArquivo = $repo->deleteFoto($fichaId, $fotoTipo);

        if ($caminhoArquivo && file_exists(__DIR__ . '/' . $caminhoArquivo)) {
            unlink(__DIR__ . '/' . $caminhoArquivo);
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Rota para gerar o relatório PDF, salvar na pasta e retornar o caminho.
 */
function gerarPdfFichaTecnica(FichaTecnicaRepository $repo)
{
    $fichaId = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
    if (!$fichaId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da ficha inválido.']);
        return;
    }

    try {
        // A lógica de geração e salvamento está no Repositório.
        $caminhoRelatorio = $repo->gerarRelatorioPdf($fichaId);

        // Retorna o caminho público para que o JS possa abrir.
        echo json_encode(['success' => true, 'pdfUrl' => $caminhoRelatorio]);
    } catch (Exception $e) {
        error_log("Erro ao gerar PDF da Ficha Técnica: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar o PDF: ' . $e->getMessage()]);
    }
}
