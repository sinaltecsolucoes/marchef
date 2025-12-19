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

// ### BLOCO DE VERIFICAÇÃO DE SESSÃO ÚNICA ###
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
// ### FIM DO BLOCO ###

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
        // Página Inicial
        'home' => 'home/home.php',

        // Páginas Módulo Cadastro 
        'usuarios'                  => 'usuarios/lista_usuarios.php',                      // Usuários 
        'clientes'                  => 'entidades/lista_entidades.php',                    // Entidades (Clientes)
        'fornecedores'              => 'entidades/lista_entidades.php',                    // Entidades (Fornecedores)
        'transportadoras'           => 'entidades/lista_entidades.php',                    // Entidades (Transportadoras)
        'relatorio_entidade'        => 'entidades/relatorio_ficha.php',                    // Relatório Entidades        
        'produtos'                  => 'produtos/lista_produtos.php',                      // Produtos
        'relatorio_produtos'        => 'produtos/relatorio_lista.php',                     // Relatório Produtos
        'fichas_tecnicas'           => 'fichas_tecnicas/lista_fichas_tecnicas.php',        // Fichas Técnicas
        'ficha_tecnica_detalhes'    => 'fichas_tecnicas/detalhes_ficha_tecnica.php',       // Detalhes Ficha Técnica
        'relatorio_ficha_tecnica'   => 'fichas_tecnicas/relatorio_ficha_tecnica.php',      // Relatório Ficha Técnica
        'condicoes_pagamento'       => 'condicao_pagamento/lista_condicoes_pagamento.php', // Condições de Pagamento
        'templates'                 => 'etiquetas/lista_templates.php',                    // Etiquetas
        'regras'                    => 'etiquetas/lista_regras.php',                       // Regras para Etiquetas
        'estoque_camaras'           => 'estoque/lista_camaras.php',                        // Câmaras de Armazenagem
        'estoque_enderecos'         => 'estoque/lista_enderecos.php',                      // Endereços Câmaras de Armazenagem

        // Páginas Módulo Lotes
        'lotes_recebimento'    => 'lotes_novo/gerenciar_lotes.php',      // Gestão de Lotes (Recebimento)
        'lotes_producao'       => 'lotes_novo/gerenciar_lotes.php',      // Gestão de Lotes (Produção)
        'lotes_embalagem'      => 'lotes_novo/gerenciar_lotes.php',      // Gestão de Lotes (Embalagem)
        'gestao_caixas_mistas' => 'lotes_novo/gestao_caixas_mistas.php', // Gestão de Caixas Mistas

        // Páginas Módulo Estoque
        'estoque'                 => 'estoque/lista_estoque.php',           // Lista Geral de Estoque
        'visao_estoque_enderecos' => 'estoque/visao_estoque_enderecos.php', // Visão Geral Estoque por Endereço

        // Páginas Módulo Carregamento / Expedição
        'ordens_expedicao'          => 'ordens_expedicao/lista_ordens_expedicao.php',    // Cadastro de Ordens de Expedição
        'ordem_expedicao_detalhes'  => 'ordens_expedicao/detalhes_ordem_expedicao.php',  // Detalhes de Ordem de Expedição
        'ordem_expedicao_relatorio' => 'ordens_expedicao/ordem_expedicao_relatorio.php', // Relatório de Ordens de Expedição        
        'carregamentos'             => 'carregamentos/lista_carregamentos.php',          // Cadastro de Carregamento
        'carregamento_detalhes'     => 'carregamentos/detalhes_carregamento.php',        // Detalhes Carregamento
        'carregamento_relatorio'    => 'carregamentos/carregamento_relatorio.php',       // Relatório de Carregamento

        // Páginas Módulo Faturamento
        'faturamentos_listar'         => 'faturamento/lista_faturamentos.php',          // Cadastro de Faturamentos
        'faturamento_gerar'           => 'faturamento/gerar_resumo.php',                // Gerar Resumo Faturamento
        'relatorio_faturamento'       => 'faturamento/relatorio_faturamento.php',       // Relatório Faturamento
        'relatorio_faturamento_excel' => 'faturamento/relatorio_faturamento_excel.php', // Relatório Faturamento (Excel)

        // Páginas Módulo Relatórios
        'relatorio_kardex' => 'estoque/relatorio_kardex.php', // Relatório Kardex

        // Páginas Módulo Configurações
        'permissoes' => 'permissoes/gerenciar.php', // Gerenciar Permissões

        // Páginas Módulo Utilitários
        'auditoria' => 'auditoria/visualizar_logs.php', // Logs de Auditoria
        'backup' => 'backup/pagina_backup.php',         // Backup Banco de Dados
    ];

    // Se for Admin, enchemos a lista de permissões com TODAS as páginas possíveis.
    // Assim, o main.php vai desenhar todos os menus sem precisar consultar o banco.
    if ($tipoUsuarioLogado === 'Admin') {
        $todasAsPaginas = array_keys($paginasPermitidas);
        $paginasPermitidasUsuario = array_unique(array_merge($paginasPermitidasUsuario, $todasAsPaginas));

        // Garante também a permissão especial de editar usuários
        if (!in_array('editar_outros_usuarios', $paginasPermitidasUsuario)) {
            $paginasPermitidasUsuario[] = 'editar_outros_usuarios';
        }
    }

    $pageType = '';
    if ($paginaAtual === 'clientes')
        $pageType = 'cliente';
    if ($paginaAtual === 'fornecedores')
        $pageType = 'fornecedor';
    if ($paginaAtual === 'transportadoras')
        $pageType = 'transportadora';

    if ($paginaAtual === 'lotes_recebimento')
        $pageType = 'lotes_recebimento';
    if ($paginaAtual === 'lotes_producao')
        $pageType = 'lotes_producao';
    if ($paginaAtual === 'lotes_embalagem')
        $pageType = 'lotes_embalagem';

    $homePadraoPorTipo = [
        'Admin' => 'home/home_admin.php',
        'Gerente' => 'home/home_gerente.php',
        'Logistica' => 'home/home_gerente.php',
        'Financeiro' => 'home/home_gerente.php',
        'Producao' => 'home/home_producao.php'
    ];
    $arquivoView = '';

    // Verifica se o utilizador tem permissão para a página solicitada
    $temPermissao = ($tipoUsuarioLogado === 'Admin' || in_array($paginaAtual, $paginasPermitidasUsuario));

    if ($temPermissao && isset($paginasPermitidas[$paginaAtual])) {
        // Se tem permissão e a página existe, processa
        switch ($paginaAtual) {
            case 'carregamento_detalhes':
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

    // ### LÓGICA DE RENDERIZAÇÃO ###
    // Define quais páginas devem ser carregadas SEM o layout principal (menu, navbar, etc.)
    $paginasSemLayout = [
        'relatorio_faturamento',
        'relatorio_faturamento_excel',
        'carregamento_relatorio',
        'ordem_expedicao_relatorio',
        'relatorio_ficha_tecnica',
        'relatorio_entidade',
        'relatorio_produtos',
    ];

    if (in_array($paginaAtual, $paginasSemLayout)) {
        // Se a página está na lista, inclui SÓ a view (sem layout)
        require_once($viewParaIncluir);
    } else {
        // Senão, carrega o layout principal (com menu, navbar, etc.)
        require_once __DIR__ . '/../views/layouts/main.php';
    }
} catch (PDOException $e) {
    error_log("Erro no Front Controller: " . $e->getMessage());
    die("Ocorreu um erro crítico na aplicação. Verifique os logs do servidor.");
}
