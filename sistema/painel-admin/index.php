<?php
@session_start(); // Inicia a sessão se ainda não estiver iniciada

require_once("../conexao.php"); // Inclui o arquivo de conexão com o banco de dados
require_once("verificar.php"); // Inclui o script para verificar a sessão do usuário

// A chave do array é o que vai na URL, e o valor é o nome do arquivo a ser incluído.
$paginasPermitidas = [
    'home' => 'home.php',
    'usuarios' => 'usuarios.php',
    'clientes' => 'clientes.php',
    'fornecedores' => 'fornecedores.php',
    'produtos' => 'produtos.php',
    // Adicione mais páginas aqui facilmente
];

// 2. Obtém o valor do parâmetro 'pag' da URL, de forma segura.
// Se 'pag' não estiver na URL, define um valor padrão ('home').
$pagina = $_GET['pag'] ?? 'home';

// Verifica se a sessão 'codUsuario' existe
if (!isset($_SESSION['codUsuario']) || empty($_SESSION['codUsuario'])) {
    // Se não há codUsuario na sessão, destrói a sessão e redireciona para o login.
    // Isso é importante caso a sessão exista mas o ID do usuário seja inválido ou vazio.
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Recuperar os dados do usuário do banco de dados
$id_usuario = $_SESSION['codUsuario'];

try {
    $query = $pdo->prepare("SELECT * FROM tbl_usuarios WHERE usu_codigo = :id_usuario");
    $query->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT); // Usar PDO::PARAM_INT para IDs numéricos
    $query->execute();
    $res = $query->fetchAll(PDO::FETCH_ASSOC);
    $total_reg = count($res);

    if ($total_reg > 0) {
        // Se o usuário foi encontrado, armazena/atualiza seus dados na sessão
        // Isso garante que as sessões estejam atualizadas com os dados do banco
        $_SESSION['nomeUsuario'] = $res[0]['usu_nome'];
        $_SESSION['logUsuario'] = $res[0]['usu_login'];
        $_SESSION['senhaUsuario'] = $res[0]['usu_senha']; // Cuidado: Nunca use essa senha para nada além de comparação hash
        $_SESSION['sitUsuario'] = $res[0]['usu_situacao'];
        $_SESSION['tipoUsuario'] = $res[0]['usu_tipo'];

        // Se você precisar dos dados para o data-atributo do modal, use as variáveis de sessão
        // Elas já estão definidas acima.
    } else {
        // Se o usuário não foi encontrado no banco de dados (mas tinha codUsuario na sessão)
        session_destroy(); // Limpa a sessão atual
        header("Location: ../login.php"); // Redireciona para a página de login
        exit();
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
    die("Ocorreu um erro ao carregar seus dados. Por favor, tente novamente mais tarde.");
}

// Garante que $_SESSION['nomeUsuario'] está definida para o HTML, mesmo que ocorra algum erro inesperado.
// Embora menos provável com os tratamentos acima, é um fallback.
if (!isset($_SESSION['nomeUsuario'])) {
    $_SESSION['nomeUsuario'] = "Visitante"; // Valor padrão para exibição
}

// --- FIM DA LÓGICA PHP INICIAL ---
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <title>Painel Administrativo - MARCHEF</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">

    <link href="../vendor/css/main.css" rel="stylesheet">

    <link rel="shortcut icon" href="../img/icone_2.ico" type="image/x-icon">

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
                        <a class="nav-link <?php echo ($_GET['pag'] == 'home' || !isset($_GET['pag'])) ? 'active' : ''; ?>"
                            aria-current="page" href="index.php?pag=home">
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
                            // Usa um loop para criar os links do menu automaticamente
                            foreach ($paginasPermitidas as $nomeLink => $arquivo) {
                                // Se o link for 'home', ele já está na navegação principal, então podemos pular
                                if ($nomeLink == 'home') {
                                    continue;
                                }
                                ?>
                                <li>
                                    <a class="dropdown-item" href="index.php?pag=<?php echo $nomeLink ?>">
                                        <?php echo ucfirst($nomeLink) ?>
                                    </a>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#">Link</a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <span
                                class="text-dark fw-bold"><?php echo htmlspecialchars($_SESSION['nomeUsuario']); ?></span>
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
        data-nome-usuario="<?php echo htmlspecialchars($_SESSION['nomeUsuario'] ?? ''); ?>"
        data-login-usuario="<?php echo htmlspecialchars($_SESSION['logUsuario'] ?? ''); ?>"
        data-situacao-usuario="<?php echo htmlspecialchars($_SESSION['sitUsuario'] ?? 'A'); ?>"
        data-tipo-usuario="<?php echo htmlspecialchars($_SESSION['tipoUsuario'] ?? 'Producao'); ?>">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="perfilModalLabel">Editar Perfil</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post" id="form-perfil">
                    <div class="modal-body">

                        <div id="mensagem-perfil" class="mb-3"></div>

                        <div class="mb-3">
                            <label for="nome-perfil" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome-perfil" name="nome_perfil"
                                placeholder="Nome Completo">
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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"
        integrity="sha512-pHVGpX7F/27yZ0gYBHXAigFK9atxRBXMloApmFk3yocok6AQoFbyOpAhaLhqgGFPgHjpxzaVYzWsCMOoxHTMug=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>

    <script src="../js/exibe-senha.js"></script>

    <script src="../js/enviar-dados-perfil.js"></script>

    <link rel="stylesheet" type="text/css" href="../vendor/DataTables/datatables.min.css" />
    <script type="text/javascript" src="../vendor/DataTables/datatables.min.js"></script>

</body>

<div class="container-fluid mt-3 mx-4">
    <?php
    // 3. Verifica se o valor da variável $pagina existe na lista de páginas permitidas.
    if (array_key_exists($pagina, $paginasPermitidas)) {
        // Se a página for válida, inclui o arquivo correspondente.
        require_once($paginasPermitidas[$pagina]);
    } else {
        // Se a página não for válida, exibe uma mensagem de erro ou redireciona para uma página 404.
        echo "<h1>Página não encontrada!</h1>";
    }
    ?>
</div>


</html>