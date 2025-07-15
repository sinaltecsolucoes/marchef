<?php
// index.php

// ========================================================================
// HTTP Security Headers
// Defina estes headers o mais cedo possível no script.
// ========================================================================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY"); // Ou 'SAMEORIGIN' se você precisar que o site possa ser enquadrado pelo próprio domínio
// ATENÇÃO: HSTS só deve ser usado se o seu site estiver configurado para HTTPS.
// O 'max-age' define por quanto tempo (em segundos) o navegador deve forçar HTTPS.
// 31536000 segundos = 1 ano. 'includeSubDomains' aplica a política a todos os subdomínios.
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Content-Security-Policy (CSP)
// Esta é uma política básica. Você precisará ajustá-la e testá-la cuidadosamente.
// 'default-src 'self'' - Permite que recursos sejam carregados apenas do mesmo domínio.
// 'script-src 'self' https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net' - Permite scripts do próprio domínio e dos CDNs.
// 'style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net' - Permite estilos do próprio domínio e do CDN. 'unsafe-inline' é necessário para estilos inline (como style="background-color: #e3f2fd;"), idealmente deve ser removido refatorando para classes CSS.
// 'img-src 'self' data:' - Permite imagens do próprio domínio e data URIs (para ícones/imagens pequenas embutidas).
// 'font-src 'self' https://fonts.gstatic.com; https://cdn.jsdelivr.net' - Permite fontes do próprio domínio, Google Fonts e CDN JS (para Font Awesome, por exemplo).
// 'connect-src 'self' https://viacep.com.br;' - Permite conexões (AJAX, WebSockets) ao próprio domínio e ao ViaCEP.
// 'form-action 'self'' - Restringe para onde os formulários podem ser enviados.
header("Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.datatables.net; " .
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net; " .
    "img-src 'self' data:; " .
    "font-src 'self' https://fonts.gstatic.com; " .
    "connect-src 'self' https://viacep.com.br https://cdn.datatables.net; " .
    "form-action 'self';");

// Referrer-Policy
// 'strict-origin-when-cross-origin' é uma política equilibrada que protege a privacidade
// sem quebrar funcionalidades que dependem do referrer.
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions-Policy
// Define quais APIs e recursos do navegador podem ser usados.
// 'self' permite apenas o próprio domínio. 'none' desabilita completamente.
// Exemplo: camera=(), geolocation=(), microphone=() desabilita essas APIs.
// Para um painel administrativo, muitas dessas permissões podem ser desabilitadas.
// CORREÇÃO AQUI: Removido 'vr=()'
header("Permissions-Policy: camera=(), geolocation=(), microphone=(), usb=(), payment=()");
// Você pode adicionar mais ou remover conforme as necessidades do seu site.
// Ex: fullscreen=(self) se você tiver vídeos ou elementos que usam tela cheia.
// ========================================================================


// Inclui o manipulador de erros global (primeiro, para capturar erros iniciais)
require_once("../includes/error_handler.php");

// Inicia a sessão.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ========================================================================
// Geração de Token CSRF
// ========================================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Gera um token aleatório de 64 caracteres hexadecimais
}
$csrf_token = $_SESSION['csrf_token'];

// NOVO: Variável JavaScript global para o token CSRF
//echo '<script>const CSRF_TOKEN = "' . htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') . '";</script>';

// ========================================================================


// Inclui arquivos essenciais
require_once("../conexao.php");
require_once("verificar.php"); // Este arquivo agora apenas inicia a sessão.
require_once("../includes/helpers.php"); // Inclui o arquivo de funções auxiliares

// Mapeamento de páginas permitidas e seus arquivos correspondentes
$paginasPermitidas = [
    'home' => 'home.php',
    'usuarios' => 'usuarios.php',
    'clientes' => 'clientes.php',
    'fornecedores' => 'fornecedores.php',
    'produtos' => 'produtos.php',
    'permissoes' => 'permissoes.php', // Certifique-se que 'permissoes' está aqui
];

// Determina a página a ser carregada, padrão 'home'
$pagina = $_GET['pag'] ?? 'home';

// ========================================================================================
// OTIMIZAÇÃO: Carregamento de Dados do Usuário da Sessão ou Banco de Dados
// ========================================================================================

// Verifica se o usuário está logado. Se não, destrói a sessão e redireciona para o login.
if (!isset($_SESSION['codUsuario']) || empty($_SESSION['codUsuario'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$id_usuario = $_SESSION['codUsuario'];

// Verifica se as informações essenciais do usuário JÁ ESTÃO na sessão.
// Se estiverem, evitamos uma consulta desnecessária ao banco de dados.
if (!isset($_SESSION['nomeUsuario']) || !isset($_SESSION['tipoUsuario'])) {
    try {
        // Se as informações não estiverem na sessão, busca do banco de dados
        // Selecionamos apenas as colunas necessárias para a sessão.
        $query = $pdo->prepare("SELECT usu_nome, usu_login, usu_senha, usu_situacao, usu_tipo FROM tbl_usuarios WHERE usu_codigo = :id_usuario");
        $query->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $query->execute();
        $res = $query->fetchAll(PDO::FETCH_ASSOC);

        if (count($res) > 0) {
            // Popula as variáveis de sessão com os dados do banco de dados
            $_SESSION['nomeUsuario'] = $res[0]['usu_nome'];
            $_SESSION['logUsuario'] = $res[0]['usu_login'];
            $_SESSION['senhaUsuario'] = $res[0]['usu_senha']; // Cuidado: Senha em sessão não é ideal, mas mantido por consistência com seu código.
            $_SESSION['sitUsuario'] = $res[0]['usu_situacao'];
            $_SESSION['tipoUsuario'] = $res[0]['usu_tipo'];
        } else {
            // Se o usuário não for encontrado no DB (mesmo tendo codUsuario na sessão),
            // a sessão é inválida, então destrói e redireciona.
            session_destroy();
            header("Location: ../login.php");
            exit();
        }
    } catch (PDOException $e) {
        // Loga o erro e exibe uma mensagem genérica para o usuário.
        error_log("Erro ao buscar dados do usuário (index.php): " . $e->getMessage());
        // Lança a exceção para ser capturada pelo manipulador global.
        throw new PDOException("Erro ao carregar dados do usuário. Detalhe: " . $e->getMessage());
    }
}

// Define 'Visitante' apenas se nomeUsuario não estiver definido, embora com a lógica acima,
// isso só aconteceria em caso de erro grave ou sessão corrompida.
if (!isset($_SESSION['nomeUsuario'])) {
    $_SESSION['nomeUsuario'] = "Visitante";
}

// ========================================================================================
// FIM DA OTIMIZAÇÃO DE CARREGAMENTO DE DADOS DO USUÁRIO
// ========================================================================================


// ====================================================================================================
// INÍCIO DAS MODIFICAÇÕES PARA GERENCIAMENTO DE PERMISSÕES E PÁGINAS INICIAIS POR PERFIL
// ====================================================================================================

$tipoUsuarioLogado = $_SESSION['tipoUsuario'];

// 1. Buscar permissões do banco de dados para o tipo de usuário logado
$paginasPermitidasUsuario = [];
try {
    $queryPermissoes = $pdo->prepare("SELECT permissao_pagina FROM tbl_permissoes WHERE permissao_perfil = :tipo_usuario");
    $queryPermissoes->bindParam(':tipo_usuario', $tipoUsuarioLogado, PDO::PARAM_STR);
    $queryPermissoes->execute();
    $resPermissoes = $queryPermissoes->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resPermissoes as $item) {
        $paginasPermitidasUsuario[] = $item['permissao_pagina'];
    }

} catch (PDOException $e) {
    error_log("Erro ao buscar permissões do usuário " . $tipoUsuarioLogado . ": " . $e->getMessage());
    // Lança a exceção para ser capturada pelo manipulador global.
    throw new PDOException("Erro ao carregar suas permissões. Detalhe: " . $e->getMessage());
}

// NOVO: Garante que o Admin sempre tenha acesso à página de permissões
if ($tipoUsuarioLogado === 'Admin' && !in_array('permissoes', $paginasPermitidasUsuario)) {
    $paginasPermitidasUsuario[] = 'permissoes';
}


// 2. Definir a página padrão (home) para cada tipo de usuário
$homePadraoPorTipo = [
    'Admin' => 'home.php',        // Admin continua usando a home padrão
    'Gerente' => 'home_gerente.php',  // Gerentes terão sua própria home
    'Producao' => 'home_producao.php', // Produção terá sua própria home
    // Adicione outros tipos de usuário aqui, se houver, e crie os arquivos home_tipo.php correspondentes
];

// 3. Lógica para determinar qual página incluir no conteúdo principal
$paginaParaIncluir = ''; // Variável para armazenar o nome do arquivo a ser incluído

// PRIMEIRA VERIFICAÇÃO: Se a página solicitada é a 'home' padrão (ou se não foi especificada),
// e existe uma home específica para o tipo de usuário logado, use-a.
if ($pagina === 'home' && isset($homePadraoPorTipo[$tipoUsuarioLogado])) {
    $paginaParaIncluir = $homePadraoPorTipo[$tipoUsuarioLogado];
    // Mantém $pagina como 'home' para que o link 'Home' no menu fique ativo
    $pagina = 'home';
}
// SEGUNDA VERIFICAÇÃO: Se uma página específica foi solicitada (não a 'home' padrão)
// e o usuário tem permissão para ela, e ela está mapeada, use-a.
else if (in_array($pagina, $paginasPermitidasUsuario) && isset($paginasPermitidas[$pagina])) {
    $paginaParaIncluir = $paginasPermitidas[$pagina];
}
// TERCEIRA VERIFICAÇÃO (FALLBACK): Se a página solicitada NÃO é permitida ou não está mapeada,
// tenta carregar a home padrão para o tipo de usuário como fallback.
else {
    if (isset($homePadraoPorTipo[$tipoUsuarioLogado])) {
        $paginaParaIncluir = $homePadraoPorTipo[$tipoUsuarioLogado];
        // Mantém $pagina como 'home' para que o link 'Home' no menu fique ativo
        $pagina = 'home';
    } else {
        // ÚLTIMO FALLBACK: se não houver home padrão definida para o tipo de usuário,
        // ou se for um cenário inesperado, volta para home.php genérica.
        $paginaParaIncluir = 'home.php';
        echo "<h1 class='text-danger'>Acesso Negado ou Página Padrão Não Definida para seu Perfil!</h1>";
    }
}

// ====================================================================================================
// FIM DAS MODIFICAÇÕES NO BLOCO PHP INICIAL
// ====================================================================================================
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">

    <title>Painel Administrativo - MARCHEF</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        xintegrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <link href="../vendor/css/main.css" rel="stylesheet">
    <link rel="shortcut icon" href="../img/icone_2.ico" type="image/x-icon">

    <link rel="stylesheet" type="text/css" href="../vendor/DataTables/datatables.min.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" />
</head>

<body>
    <nav class="navbar navbar-expand-lg" style="background-color: #e3f2fd;" data-bs-theme="light">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="../img/logo_marchef.png" width="120px" alt="Logo Marchef">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina == 'home') ? 'active' : ''; ?>" aria-current="page"
                            href="index.php?pag=home">
                            Home
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            Cadastros
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <?php
                            // ====================================================================================================
                            // MODIFICAÇÃO: Usando a função auxiliar para renderizar itens do menu "Cadastros"
                            // ====================================================================================================
                            echo render_menu_items($paginasPermitidas, $paginasPermitidasUsuario);
                            ?>
                        </ul>
                    </li>
                    <?php if ($_SESSION['tipoUsuario'] === 'Admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                Configurações
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li>
                                    <!-- CORREÇÃO: Usando $pagina para verificar a página ativa -->
                                    <a class="dropdown-item <?php echo (get_session_data_attr('pagina') == 'permissoes') ? 'active' : ''; ?>"
                                        href="index.php?pag=permissoes">
                                        Gerenciar Permissões
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <span class="text-dark fw-bold"><?php echo get_session_data_attr('nomeUsuario'); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#perfil">Editar
                                    Perfil</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="../logout.php">Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="modal fade" id="perfil" tabindex="-1" aria-labelledby="perfilModalLabel" aria-hidden="true"
        data-nome-usuario="<?php echo get_session_data_attr('nomeUsuario'); ?>"
        data-login-usuario="<?php echo get_session_data_attr('logUsuario'); ?>"
        data-situacao-usuario="<?php echo get_session_data_attr('sitUsuario', 'A'); ?>"
        data-tipo-usuario="<?php echo get_session_data_attr('tipoUsuario', 'Producao'); ?>">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="perfilModalLabel">Editar Perfil</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post" id="form-perfil">
                    <!-- NOVO: Campo oculto para o token CSRF -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="modal-body">
                        <div id="mensagem-perfil" class="mb-3"></div>
                        <div class="mb-3">
                            <label for="selecionar-usuario-perfil" class="form-label">Selecionar Usuário</label>
                            <!-- NOVO: Combobox para selecionar o usuário -->
                            <select class="form-select" id="selecionar-usuario-perfil" name="usu_codigo_selecionado"
                                required>
                                <option value="">Selecione um usuário...</option>
                                <!-- As opções serão carregadas via JavaScript -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="nome-perfil" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome-perfil" name="nome_perfil"
                                placeholder="Nome Completo" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="login-perfil" class="form-label">Login</label>
                                <input type="text" class="form-control" id="login-perfil" name="login_perfil"
                                    placeholder="login" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="senha-perfil" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="senha-perfil" name="senha_perfil"
                                    placeholder="Deixe em branco para não alterar">
                                <small class="form-text text-muted">Preencha apenas se quiser mudar a senha.</small>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="exibir-senha-perfil">
                                    <label class="form-check-label" for="exibir-senha-perfil">
                                        Exibir Senha
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="situacao-perfil">Situação</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="situacao-perfil"
                                    name="situacao_perfil" value="1">
                                <label class="form-check-label" for="situacao-perfil">
                                    <span id="texto-situacao-perfil">Ativo</span>
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="nivel-perfil" class="form-label">Nível de Acesso</label>
                            <select class="form-select" id="nivel-perfil" aria-label="Selecione o nível de acesso"
                                name="nivel_perfil">
                                <option value="Admin">Administrador</option>
                                <option value="Gerente">Gerente</option>
                                <option value="Producao">Produção</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            id="btn-fechar-perfil">Fechar</button>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="container-fluid mt-3 mx-4">
        <?php
        // ====================================================================================================
        // MODIFICAÇÃO PRINCIPAL: Lógica de Inclusão da Página Baseada em Permissões
        // ====================================================================================================
        
        // Verifica se o arquivo da página a ser incluída existe fisicamente
        if (!empty($paginaParaIncluir) && file_exists($paginaParaIncluir)) {
            require_once($paginaParaIncluir);
        } else if (!empty($paginaParaIncluir)) {
            // Caso o arquivo da página calculada não exista (erro no caminho ou nome do arquivo)
            echo "<h1 class='text-danger'>Erro: Arquivo da página '{$paginaParaIncluir}' não encontrado no servidor.</h1>";
            error_log("Erro: Arquivo da página '{$paginaParaIncluir}' não encontrado para o tipo de usuário " . $tipoUsuarioLogado);
        } else {
            // Este else só deve ser alcançado se $paginaParaIncluir estiver vazio, o que não deveria acontecer com a lógica acima.
            echo "<h1 class='text-danger'>Erro inesperado na determinação da página.</h1>";
        }
        // ====================================================================================================
        // FIM DA MODIFICAÇÃO PRINCIPAL
        // ====================================================================================================
        ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"
        xintegrity="sha512-pHVGpX7F/27yZ0ISY+VVjyULApbDlD0/X0rgGbTqCE7WFW5MezNTWG/dnhtbBuICzsd0WQPgpE4REBLv+UqChw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script type="text/javascript" src="../vendor/DataTables/datatables.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        xintegrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>

    <script type="text/javascript"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript"
        src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>

    <script src="../js/exibe-senha.js"></script>
    <script src="../js/enviar-dados-perfil.js"></script>
    <script src="../js/usuarios.js"></script>


    <?php if (isset($pagina) && $pagina === 'permissoes'): ?>

        <script>
            $(document).ready(function () {
                const formPermissoes = $('#form-gerenciar-permissoes');
                const mensagemDiv = $('#mensagem-permissoes');

                formPermissoes.on('submit', function (e) {
                    e.preventDefault(); // Impede o envio padrão do formulário

                    // Limpa mensagens anteriores
                    mensagemDiv.empty().removeClass('alert alert-success alert-danger');

                    // Pega todos os dados do formulário serializados
                    // NOTA: .serialize() NÃO inclui campos desabilitados.
                    // Nossa correção no salvar_permissoes.php lida com isso para o 'Admin'.
                    let formData = formPermissoes.serialize();

                    // Envia os dados via AJAX
                    $.ajax({
                        type: 'POST',
                        url: 'salvar_permissoes.php', // Caminho para o seu script de backend
                        data: formData,
                        dataType: 'json', // Espera uma resposta JSON
                        success: function (response) {
                            if (response.success) {
                                mensagemDiv.addClass('alert alert-success').text(response.message);
                                // Opcional: recarregar a página para mostrar o estado salvo
                                // setTimeout(function() {
                                //      location.reload();
                                // }, 1500); // Recarrega após 1.5 segundos
                            } else {
                                mensagemDiv.addClass('alert alert-danger').text(response.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            mensagemDiv.addClass('alert alert-danger').text('Erro na requisição: ' + error + ' - ' + xhr.responseText);
                            console.error('Erro:', status, error, xhr.responseText);
                        }
                    });
                });
            });
        </script>


    <?php endif; ?>

    <?php if (isset($pagina) && $pagina === 'clientes'): ?>
        <script src="../js/clientes.js"></script>
    <?php endif; ?>

    <?php if (isset($pagina) && $pagina === 'fornecedores'): ?>
        <script src="../js/fornecedores.js"></script>
    <?php endif; ?>

</body>

</html>