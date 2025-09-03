<?php
// /public/index.php - O ÚNICO PONTO DE ENTRADA DO PAINEL

// 1. Carrega o bootstrap do sistema
require_once __DIR__ . '/../src/bootstrap.php';

// 2. Importa as classes que vamos usar
use App\Core\Database;
use App\Auth\AuthService;

// 3. Define a rota principal
$page = $_GET['page'] ?? (isset($_SESSION['codUsuario']) ? 'home' : 'login');

// 4. Tratamento de rotas especiais (logout)
if ($page === 'logout') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/index.php?page=login");
    exit();
}

// 5. Lógica da página de LOGIN
if ($page === 'login') {
    if (isset($_SESSION['codUsuario'])) {
        header("Location: " . BASE_URL . "/index.php?page=home");
        exit();
    }
    $pdo = Database::getConnection();
    $authService = new AuthService($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login_ok = $authService->authenticate($_POST['login-usuario'] ?? '', $_POST['senha'] ?? '');
        if ($login_ok) {
            // Força a gravação de todos os dados da sessão ANTES de redirecionar
            session_write_close();

            header("Location: " . BASE_URL . "/index.php?page=home");
        } else {
            header("Location: " . BASE_URL . "/index.php?page=login");
        }
        exit();
    }
    require_once __DIR__ . '/../views/auth/login_view.php';
    exit();
}

// ========================================================================
// A PARTIR DAQUI, O CÓDIGO SÓ EXECUTA SE O USUÁRIO ESTIVER LOGADO
// ========================================================================

if (!isset($_SESSION['codUsuario'])) {
    header("Location: " . BASE_URL . "/index.php?page=login");
    exit();
}

// ### NOVO BLOCO DE VERIFICAÇÃO DE SESSÃO ÚNICA ###
try {
    $pdo = Database::getConnection();

    // Busca o token mais recente do banco de dados
    $stmt = $pdo->prepare("SELECT usu_session_token FROM tbl_usuarios WHERE usu_codigo = ?");
    $stmt->execute([$_SESSION['codUsuario']]);
    $dbToken = $stmt->fetchColumn();

    // Compara o token do banco com o da sessão atual
    if (!$dbToken || !isset($_SESSION['session_token']) || $dbToken !== $_SESSION['session_token']) {
        session_unset();
        session_destroy();
        // Redireciona com uma mensagem explicando o motivo
        header("Location: " . BASE_URL . "/index.php?page=login&motivo=nova_sessao");
        exit();
    }
} catch (PDOException $e) {
    // Tratar erro de banco de dados
    die("Erro ao verificar a sessão do usuário.");
}
// ### FIM DO NOVO BLOCO ###

try {
    $pdo = Database::getConnection();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];
    require_once __DIR__ . "/../src/Core/helpers.php";

    // Lógica de carregar dados do usuário e permissões
    $id_usuario = $_SESSION['codUsuario'];
    if (!isset($_SESSION['nomeUsuario']) || !isset($_SESSION['tipoUsuario'])) {
        $query = $pdo->prepare("SELECT usu_nome, usu_tipo FROM tbl_usuarios WHERE usu_codigo = :id");
        $query->execute([':id' => $id_usuario]);
        $res = $query->fetch();
        if ($res) {
            $_SESSION['nomeUsuario'] = $res['usu_nome'];
            $_SESSION['tipoUsuario'] = $res['usu_tipo'];
        } else {
            session_destroy();
            header("Location: " . BASE_URL . "/index.php?page=login");
            exit();
        }
    }
    $tipoUsuarioLogado = $_SESSION['tipoUsuario'];
    $queryPermissoes = $pdo->prepare("SELECT permissao_pagina FROM tbl_permissoes WHERE permissao_perfil = :tipo_usuario");
    $queryPermissoes->execute([':tipo_usuario' => $tipoUsuarioLogado]);
    $paginasPermitidasUsuario = $queryPermissoes->fetchAll(PDO::FETCH_COLUMN, 0);

    // Garante que a permissão de 'permissoes' seja adicionada para o Admin
    if ($tipoUsuarioLogado === 'Admin' && !in_array('permissoes', $paginasPermitidasUsuario)) {
        $paginasPermitidasUsuario[] = 'permissoes';
    }

    // Calcula o valor real da variável $podeEditarOutrosUsuarios
    $podeEditarOutrosUsuarios = ($tipoUsuarioLogado === 'Admin' || in_array('editar_outros_usuarios', $paginasPermitidasUsuario));

    // Lógica de roteamento
    $paginaAtual = $page;
    $paginasPermitidas = [
        'home' => 'home/home.php',
        'usuarios' => 'usuarios/lista_usuarios.php',
        'clientes' => 'entidades/lista_entidades.php',
        'fornecedores' => 'entidades/lista_entidades.php',
        'produtos' => 'produtos/lista_produtos.php',
        //'lotes' => 'lotes/lista_lotes.php',
        //'lotes' => 'lotes_novo/lista_lotes_novo.php',
        'lotes_producao' => 'lotes_novo/lista_lotes_producao.php',
        'lotes_embalagem' => 'lotes_novo/lista_lotes_embalagem.php',
        'permissoes' => 'permissoes/gerenciar.php',
        'templates' => 'etiquetas/lista_templates.php',
        'regras' => 'etiquetas/lista_regras.php',
        'auditoria' => 'auditoria/visualizar_logs.php',
        'backup' => 'backup/pagina_backup.php',
        'carregamentos' => 'carregamentos/lista_carregamentos.php',
        'carregamento_detalhes' => 'carregamentos/detalhes_carregamento.php',
        'estoque' => 'estoque/lista_estoque.php'
    ];

    $pageType = '';
    if ($paginaAtual === 'clientes')
        $pageType = 'cliente';
    if ($paginaAtual === 'fornecedores')
        $pageType = 'fornecedor';

    if ($paginaAtual === 'lotes_producao')
        $pageType = 'lotes_producao';
    if ($paginaAtual === 'lotes_embalagem')
        $pageType = 'lotes_embalagem';

    $homePadraoPorTipo = [
        'Admin' => 'home/home_admin.php',
        'Gerente' => 'home/home_gerente.php',
        'Producao' => 'home/home_producao.php'
    ];
    $arquivoView = '';

    // Verifica se o utilizador tem permissão para a página solicitada
    $temPermissao = ($tipoUsuarioLogado === 'Admin' || in_array($paginaAtual, $paginasPermitidasUsuario));

    if ($temPermissao && isset($paginasPermitidas[$paginaAtual])) {
        // Se tem permissão e a página existe, processa
        switch ($paginaAtual) {
            case 'carregamento_detalhes':
                // Lógica especial para esta página: buscar dados antes de carregar a view
                $carregamentoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$carregamentoId) {
                    // Se não houver ID, redireciona para a lista
                    header("Location: index.php?page=carregamentos");
                    exit;
                }

                $carregamentoRepo = new \App\Carregamentos\CarregamentoRepository($pdo);
                $carregamentoData = $carregamentoRepo->findCarregamentoComItens($carregamentoId);

                if (!$carregamentoData) {
                    // Se não encontrar o carregamento, mostra um erro ou redireciona
                    die("Erro: Carregamento não encontrado.");
                }

                // Define a view a ser incluída
                $arquivoView = $paginasPermitidas[$paginaAtual];
                break;

            default:
                // Lógica padrão para todas as outras páginas
                if ($paginaAtual === 'home') {
                    $arquivoView = $homePadraoPorTipo[$tipoUsuarioLogado] ?? 'home/home.php';
                } else {
                    $arquivoView = $paginasPermitidas[$paginaAtual];
                }
                break;
        }
    } else {
        // Se não tem permissão ou a página não existe, volta para a home
        $arquivoView = $homePadraoPorTipo[$tipoUsuarioLogado] ?? 'home/home.php';
        $paginaAtual = 'home';
    }

    $viewParaIncluir = __DIR__ . '/../views/' . $arquivoView;

    // Renderiza o Layout Principal
    require_once __DIR__ . '/../views/layouts/main.php';
} catch (PDOException $e) {
    error_log("Erro no Front Controller: " . $e->getMessage());
    die("Ocorreu um erro crítico na aplicação. Verifique os logs do servidor.");
}
