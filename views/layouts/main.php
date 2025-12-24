<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">

    <title>Painel Administrativo - MARCHEF</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/css/main.css" rel="stylesheet">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/img/icone_2.ico" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>/libs/datatables.min.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightbox2@2.11.4/dist/css/lightbox.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">


</head>

<body data-logged-in-user-id="<?php echo htmlspecialchars($_SESSION['codUsuario'] ?? ''); ?>"
    data-page-type="<?php echo $pageType; ?>">
    <nav class="navbar navbar-expand-lg shadow-sm" style="background-color: #e3f2fd;" data-bs-theme="light">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/index.php?page=home">
                <img src="<?php echo BASE_URL; ?>/img/logo_marchef.png" width="120px" alt="Logo Marchef">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($paginaAtual == 'home') ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>/index.php?page=home">Home</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                            data-bs-toggle="dropdown">Cadastros</a>
                        <ul class="dropdown-menu">
                            <?php
                            // Itens do menu de Cadastros
                            $paginasSimples = [
                                'usuarios' => 'Usuários',
                                'clientes' => 'Clientes',
                                'fornecedores' => 'Fornecedores',
                                'transportadoras' => 'Transportadoras',
                                'condicoes_pagamento' => 'Condições de Pagamento'
                            ];

                            foreach ($paginasSimples as $page => $label) {
                                if (in_array($page, $paginasPermitidasUsuario)) {
                                    echo "<li><a class='dropdown-item' href='index.php?page={$page}'>{$label}</a></li>";
                                }
                            }
                            ?>

                            <?php // --- SUBMENU DE PRODUTOS ---
                            $paginasProdutos = ['produtos', 'fichas_tecnicas', 'templates', 'regras'];
                            if (count(array_intersect($paginasProdutos, $paginasPermitidasUsuario)) > 0):
                            ?>
                                <li class="dropend">
                                    <a class="dropdown-item dropdown-toggle" href="#" data-bs-toggle="dropdown">Produtos</a>
                                    <ul class="dropdown-menu dropdown-submenu">
                                        <?php if (in_array('produtos', $paginasPermitidasUsuario)): ?>
                                            <li><a class="dropdown-item" href="index.php?page=produtos">Gerenciar Produtos</a>
                                            </li>
                                        <?php endif; ?>
                                        <?php if (in_array('fichas_tecnicas', $paginasPermitidasUsuario)): ?>
                                            <li><a class="dropdown-item" href="index.php?page=fichas_tecnicas">Fichas
                                                    Técnicas</a></li>
                                        <?php endif; ?>
                                        <?php // --- SUBSUBMENU DE ETIQUETAS ---
                                        $paginasEtiquetas = ['templates', 'regras'];
                                        if (count(array_intersect($paginasEtiquetas, $paginasPermitidasUsuario)) > 0):
                                        ?>
                                            <li class="dropend">
                                                <a class="dropdown-item dropdown-toggle" href="#"
                                                    data-bs-toggle="dropdown">Etiquetas</a>
                                                <ul class="dropdown-menu dropdown-submenu">
                                                    <?php if (in_array('templates', $paginasPermitidasUsuario)): ?>
                                                        <li><a class="dropdown-item" href="index.php?page=templates">Modelos de
                                                                Etiqueta</a></li>
                                                    <?php endif; ?>
                                                    <?php if (in_array('regras', $paginasPermitidasUsuario)): ?>
                                                        <li><a class="dropdown-item" href="index.php?page=regras">Regras de
                                                                Etiqueta</a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </li>
                            <?php endif; ?>

                            <?php // --- SUBMENU DE ESTOQUE ---
                            $paginasEstoque = ['estoque_camaras', 'estoque_enderecos'];
                            if (count(array_intersect($paginasEstoque, $paginasPermitidasUsuario)) > 0):
                            ?>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li class="dropend">
                                    <a class="dropdown-item dropdown-toggle" href="#" data-bs-toggle="dropdown">Estoque</a>
                                    <ul class="dropdown-menu dropdown-submenu">
                                        <?php if (in_array('estoque_camaras', $paginasPermitidasUsuario)): ?>
                                            <li><a class="dropdown-item" href="index.php?page=estoque_camaras">Gerenciar
                                                    Câmaras</a></li>
                                        <?php endif; ?>
                                        <?php if (in_array('estoque_enderecos', $paginasPermitidasUsuario)): ?>
                                            <li><a class="dropdown-item" href="index.php?page=estoque_enderecos">Gerenciar
                                                    Endereços</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <?php
                    // Define as páginas que pertencem a este menu
                    $paginasLotes = ['lotes_recebimento', 'lotes_producao', 'lotes_embalagem'];
                    // Verifica se o usuário tem permissão para ver PELO MENOS UMA página do menu
                    if (count(array_intersect($paginasLotes, $paginasPermitidasUsuario)) > 0):
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Lotes</a>
                            <ul class="dropdown-menu">
                                <?php if (in_array('lotes_recebimento', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=lotes_recebimento">Gestão de Lotes
                                            (Recebimento)</a></li>
                                <?php endif; ?>
                                <?php if (in_array('lotes_producao', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=lotes_producao">Gestão de Lotes
                                            (Produção)</a></li>
                                <?php endif; ?>
                                <?php if (in_array('lotes_embalagem', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=lotes_embalagem">Gestão de Lotes
                                            (Embalagem)</a></li>
                                <?php endif; ?>
                                <?php
                                if (in_array('gestao_caixas_mistas', $paginasPermitidasUsuario)): ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="index.php?page=gestao_caixas_mistas">Gestão de Caixas
                                            Mistas (Sobras)</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php
                    // Verifica se o usuário tem permissão para ver a página de estoque
                    $paginasConsulta = ['estoque'];
                    if (count(array_intersect($paginasConsulta, $paginasPermitidasUsuario)) > 0):
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown">Estoques</a>
                            <ul class="dropdown-menu">
                                <?php if (in_array('estoque', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=estoque">Visão Geral do Estoque</a></li>
                                <?php endif; ?>
                                <?php if (in_array('visao_estoque_enderecos', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=visao_estoque_enderecos">Visão por
                                            Endereços</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php
                    $paginasExpedicao = ['ordens_expedicao', 'carregamentos'];
                    if (count(array_intersect($paginasExpedicao, $paginasPermitidasUsuario)) > 0):
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown">Expedição</a>
                            <ul class="dropdown-menu">
                                <?php if (in_array('ordens_expedicao', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=ordens_expedicao">Ordens de Expedição</a>
                                    </li>
                                <?php endif; ?>
                                <?php if (in_array('carregamentos', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=carregamentos">Carregamentos</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php
                    $paginasFaturamento = ['faturamentos_listar'];
                    if (count(array_intersect($paginasFaturamento, $paginasPermitidasUsuario)) > 0):
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown">Faturamento</a>
                            <ul class="dropdown-menu">
                                <?php if (in_array('faturamentos_listar', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=faturamentos_listar">Gerenciar
                                            Faturamentos</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php
                    $paginasRelatorios = ['relatorio_kardex'];
                    if (count(array_intersect($paginasRelatorios, $paginasPermitidasUsuario)) > 0):
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown">Relatórios</a>
                            <ul class="dropdown-menu">
                                <?php if (in_array('relatorio_kardex', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=relatorio_kardex">Relatório KARDEX</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php
                    // Verifica se o usuário tem permissão para ver PELO MENOS UM item do menu Configurações
                    // --- SUBMENU DE CONFIGURAÇÕES ---
                    $paginasConfig = ['permissoes'];
                    if (count(array_intersect($paginasConfig, $paginasPermitidasUsuario)) > 0):
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown">Configurações</a>
                            <ul class="dropdown-menu">
                                <?php if (in_array('permissoes', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=permissoes">Gerenciar Permissões</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php
                    // Verifica se o usuário tem permissão para ver PELO MENOS UM item do menu Utilitários
                    $paginasUtils = ['auditoria', 'backup'];
                    if (count(array_intersect($paginasUtils, $paginasPermitidasUsuario)) > 0):
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown">Utilitários</a>
                            <ul class="dropdown-menu">
                                <?php if (in_array('auditoria', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=auditoria">Logs de Auditoria</a></li>
                                <?php endif; ?>
                                <?php if (in_array('backup', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=backup">Backup do Sistema</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php // --- MENU SOBRE --- 
                    ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#sobreModal">
                            <i class="fas fa-circle-info me-1"></i> Sobre
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <span
                                class="text-dark fw-bold"><?php echo htmlspecialchars($_SESSION['nomeUsuario']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#perfil">Editar
                                    Perfil</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=logout">Sair</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-3 px-4">
        <?php
        // ### PONTO DE INCLUSÃO DINÂMICO ###
        // Lógica original de inclusão da página
        if (isset($viewParaIncluir) && file_exists($viewParaIncluir)) {
            require_once($viewParaIncluir);
        } else {
            echo "<h1 class='text-danger'>Erro 404: Página não encontrada.</h1>";
            echo "<p>O arquivo da view não foi encontrado no servidor.</p>";
        }
        ?>
    </div>

    <!-- Modal Sobre -->
    <div class="modal fade" id="sobreModal" tabindex="-1" aria-labelledby="sobreModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content border-0 shadow-sm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="sobreModalLabel">
                        <i class="fas fa-circle-info me-2"></i> Sobre o Sistema
                    </h5>
                </div>
                <div class="modal-body">
                    <ul class="list-unstyled mb-3">
                        <li><strong>🧭 Nome:</strong> <span class="text-muted">SGI - Sistema de Gestão Industrial</span>
                        </li>
                        <li><strong>📦 Versão:</strong> <span class="text-muted">1.0.0</span></li>
                        <li><strong>👨‍💻 Desenvolvido por:</strong> <span class="text-muted">MD Soluções
                                Tecnológicas</span></li>
                        <li><strong>📅 Atualização:</strong> <span class="text-muted">01/10/2025</span></li>
                    </ul>

                    <hr class="my-3">

                    <h6 class="fw-bold mb-2">📞 Contato</h6>
                    <ul class="list-unstyled">
                        <li><strong>WhatsApp:</strong> <a href="https://wa.me/5588992432756" target="_blank">(88)
                                99243-2756</a></li>
                        <li><strong>E-mail:</strong> <a
                                href="mailto:contato@mdsolucoestecnologicas.com.br">contato@mdsolucoestecnologicas.com.br</a>
                        </li>
                    </ul>

                    <hr class="my-3">

                    <p class="text-muted small mb-0">
                        Sistema desenvolvido para otimizar o planejamento de carregamentos, rastreabilidade de
                        produtos e controle de expedições.
                    </p>
                    <p class="text-muted small mb-0">
                        Todos os dados são protegidos conforme as diretrizes da LGPD.
                    </p>
                    <p class="text-muted small">
                        © 2025 MD Soluções Ltda. Todos os direitos reservados.
                    </p>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                        data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Edição de Perfil -->
    <div class="modal fade" id="perfil" tabindex="-1" aria-labelledby="perfilLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="perfilLabel">
                        <i class="fas fa-user-edit me-2"></i>Editar Meu Perfil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form id="form-perfil">
                        <input type="hidden" id="perfil_usu_codigo" name="usu_codigo">

                        <div class="mb-3">
                            <label for="perfil_usu_nome_select" class="form-label">Nome (Usuário)</label>

                            <select class="form-select" id="perfil_usu_nome_select" style="width: 100%;">
                            </select>

                            <input type="hidden" id="perfil_usu_nome" name="usu_nome">
                        </div>

                        <div class="mb-3">
                            <label for="perfil_usu_login" class="form-label">Login (Usuário)</label>
                            <input type="text" class="form-control" id="perfil_usu_login" name="usu_login" required>
                        </div>

                        <div class="mb-3 d-none" id="div-situacao-perfil">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="perfil_usu_situacao">
                                <label class="form-check-label" for="perfil_usu_situacao" id="label-situacao">Usuário Ativo</label>
                            </div>
                            <div class="form-text text-muted" style="font-size: 0.8em;">
                                <i class="fas fa-info-circle"></i> Controle de acesso (Apenas Admin).
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="perfil_usu_senha" class="form-label">Alterar Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="perfil_usu_senha" name="usu_senha" placeholder="Deixe vazio para manter a atual">
                                <button class="btn btn-outline-secondary" type="button" id="btn-show-pass-perfil" title="Mostrar/Ocultar Senha">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Preencha apenas se desejar mudar sua senha.</div>
                        </div>

                        <div class="mb-3">
                            <label for="perfil_usu_tipo" class="form-label">Perfil de Acesso</label>
                            <select class="form-select bg-light" id="perfil_usu_tipo" name="usu_tipo" disabled>
                                <option value="Admin">Administrador</option>
                                <option value="Financeiro">Financeiro</option>
                                <option value="Gerente">Gerente</option>
                                <option value="Logistica">Logística</option>
                                <option value="Producao">Produção</option>
                            </select>
                            <div class="form-text text-muted">
                                <i class="fas fa-lock me-1"></i> O perfil não pode ser alterado aqui.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-salvar-perfil">
                        <i class="fas fa-save me-1"></i> Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>/libs/datatables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script type="text/javascript"
        src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript"
        src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lightbox2@2.11.4/dist/js/lightbox.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
        const PODE_EDITAR_OUTROS_USUARIOS = <?php echo $podeEditarOutrosUsuarios ? 'true' : 'false'; ?>;
        const LOGGED_IN_USER_ID = <?php echo json_encode($_SESSION['codUsuario'] ?? null); ?>;
    </script>
    <script src="<?php echo BASE_URL; ?>/js/exibe-senha.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/enviar-dados-perfil.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/usuarios.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/app_notifications.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/app_config.js"></script>

    <?php if ($paginaAtual === 'permissoes'): ?>
        <script src="<?php echo BASE_URL; ?>/js/permissoes.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'produtos'): ?>
        <script src="<?php echo BASE_URL; ?>/js/produtos.js"></script>
    <?php endif; ?>

    <?php if ($pageType === 'cliente' || $pageType === 'fornecedor' || $pageType === 'transportadora'): ?>
        <script src="<?php echo BASE_URL; ?>/js/entidades.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'lotes_recebimento' || $paginaAtual === 'lotes_producao' || $paginaAtual === 'lotes_embalagem'): ?>
        <script src="<?php echo BASE_URL; ?>/js/lotes_novo.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'templates'): ?>
        <script src="<?php echo BASE_URL; ?>/js/templates.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'regras'): ?>
        <script src="<?php echo BASE_URL; ?>/js/regras.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'auditoria'): ?>
        <script src="<?php echo BASE_URL; ?>/js/auditoria.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'backup'): ?>
        <script src="<?php echo BASE_URL; ?>/js/backup.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'carregamentos'): ?>
        <script src="<?php echo BASE_URL; ?>/js/carregamentos.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'carregamento_detalhes'): ?>
        <script src="<?php echo BASE_URL; ?>/js/detalhes_carregamento.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'estoque'): ?>
        <script src="<?php echo BASE_URL; ?>/js/estoque.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'estoque_camaras'): ?>
        <script src="<?php echo BASE_URL; ?>/js/camaras.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'estoque_enderecos'): ?>
        <script src="<?php echo BASE_URL; ?>/js/enderecos.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'relatorio_kardex'): ?>
        <script src="<?php echo BASE_URL; ?>/js/relatorio_kardex.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'visao_estoque_enderecos'): ?>
        <script src="<?php echo BASE_URL; ?>/js/visao_estoque_enderecos.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'condicoes_pagamento'): ?>
        <script src="<?php echo BASE_URL; ?>/js/condicoes_pagamento.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'ordens_expedicao'): ?>
        <script src="<?php echo BASE_URL; ?>/js/ordens_expedicao.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'ordem_expedicao_detalhes'): ?>
        <script src="<?php echo BASE_URL; ?>/js/detalhes_ordem_expedicao.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'faturamento_gerar'): ?>
        <script src="<?php echo BASE_URL; ?>/js/faturamento_gerar.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'faturamentos_listar'): ?>
        <script src="<?php echo BASE_URL; ?>/js/lista_faturamentos.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'gestao_caixas_mistas'): ?>
        <script src="<?php echo BASE_URL; ?>/js/gestao_caixas_mistas.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'fichas_tecnicas' || $paginaAtual === 'ficha_tecnica_detalhes'): ?>
        <script src="<?php echo BASE_URL; ?>/js/fichas_tecnicas.js"></script>
    <?php endif; ?>

    <?php if ($paginaAtual === 'home'): ?>
        <?php if ($_SESSION['tipoUsuario'] === 'Admin'): ?>
            <script src="<?php echo BASE_URL; ?>/js/dashboard_admin.js"></script>
        <?php elseif (in_array($_SESSION['tipoUsuario'], ['Gerente', 'Financeiro', 'Logistica'])): ?>
            <script src="<?php echo BASE_URL; ?>/js/dashboard_gerente.js"></script>
        <?php elseif ($_SESSION['tipoUsuario'] === 'Producao'): ?>
            <script src="<?php echo BASE_URL; ?>/js/dashboard_producao.js"></script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Scripts para recalcular Tamanhos de telas maiores para menores e vice-versa -->
    <script>
        function debounce(func, wait) {
            let timeout;
            return function() {
                clearTimeout(timeout);
                timeout = setTimeout(func, wait);
            };
        }

        const recalcDataTables = () => {
            $('.dataTable').each(function() {
                const dt = $(this).DataTable();
                if (dt.responsive) {
                    dt.columns.adjust().responsive.recalc();
                }
            });
        };

        $(window).on('resize', debounce(recalcDataTables, 100));
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('.dropdown-menu .dropdown-toggle').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();

                    let nextEl = this.nextElementSibling;
                    if (nextEl && nextEl.classList.contains('dropdown-menu')) {
                        // Fecha outros submenus abertos no mesmo nível
                        let parentMenu = this.closest('.dropdown-menu');
                        parentMenu.querySelectorAll(':scope .dropdown-menu.show').forEach(function(submenu) {
                            if (submenu !== nextEl) {
                                submenu.classList.remove('show');
                            }
                        });
                        nextEl.classList.toggle('show');
                    }
                });
            });
        });
    </script>
</body>

</html>