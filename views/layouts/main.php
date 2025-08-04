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
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" />

    <script>
        // Variáveis PHP passadas pelo Controlador para o JavaScript
        const PODE_EDITAR_OUTROS_USUARIOS = <?php echo $podeEditarOutrosUsuarios ? 'true' : 'false'; ?>;
        const LOGGED_IN_USER_ID = <?php echo json_encode($_SESSION['codUsuario'] ?? null); ?>;
    </script>
</head>

<body data-logged-in-user-id="<?php echo htmlspecialchars($_SESSION['codUsuario'] ?? ''); ?>"
    data-page-type="<?php echo $pageType; ?>">
    <nav class="navbar navbar-expand-lg" style="background-color: #e3f2fd;" data-bs-theme="light">
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
                            // Cria uma cópia do array de páginas para usar apenas no menu de cadastros
                            $paginasParaCadastro = $paginasPermitidas;

                            // Remove os itens que pertencem a outros menus (Configurações, etc.)
                            unset($paginasParaCadastro['home']);
                            unset($paginasParaCadastro['permissoes']);
                            unset($paginasParaCadastro['templates']);
                            unset($paginasParaCadastro['regras']);
                            unset($paginasParaCadastro['auditoria']);
                            unset($paginasParaCadastro['backup']);

                            // Chama a função de renderização do menu apenas com a lista filtrada
                            echo render_menu_items($paginasParaCadastro, $paginasPermitidasUsuario, BASE_URL);
                            ?>
                        </ul>
                    </li>

                    <?php
                    // Verifica se o usuário tem permissão para ver PELO MENOS UM item do menu Configurações
                    $paginasConfig = ['permissoes', 'templates', 'regras'];
                    if (count(array_intersect($paginasConfig, $paginasPermitidasUsuario)) > 0):
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown">Configurações</a>
                            <ul class="dropdown-menu">
                                <?php if (in_array('permissoes', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=permissoes">Gerenciar Permissões</a></li>
                                <?php endif; ?>
                                <?php if (in_array('templates', $paginasPermitidasUsuario) || in_array('regras', $paginasPermitidasUsuario)): ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                <?php endif; ?>
                                <?php if (in_array('templates', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=templates">Templates de Etiqueta</a></li>
                                <?php endif; ?>
                                <?php if (in_array('regras', $paginasPermitidasUsuario)): ?>
                                    <li><a class="dropdown-item" href="index.php?page=regras">Regras de Etiqueta</a></li>
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

    <div class="container-fluid mt-3 mx-4">
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>/libs/datatables.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <script type="text/javascript"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

    <script type="text/javascript"
        src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>

    <script src="<?php echo BASE_URL; ?>/js/exibe-senha.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/enviar-dados-perfil.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/usuarios.js"></script>

    <?php if ($paginaAtual === 'permissoes'): ?>
        <script src="<?php echo BASE_URL; ?>/js/permissoes.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'produtos'): ?>
        <script src="<?php echo BASE_URL; ?>/js/produtos.js"></script>
    <?php endif; ?>
    <?php if ($pageType === 'cliente' || $pageType === 'fornecedor'): ?>
        <script src="<?php echo BASE_URL; ?>/js/entidades.js"></script>
    <?php endif; ?>
    <?php if ($paginaAtual === 'lotes'): ?>
        <script src="<?php echo BASE_URL; ?>/js/lotes.js"></script>
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
</body>

</html>