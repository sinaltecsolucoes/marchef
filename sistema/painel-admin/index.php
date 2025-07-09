<?php
@session_start(); // Inicia a sessão se ainda não estiver iniciada (com @ para suprimir warnings se já estiver)
require_once("verificar.php"); // Inclui o script para verificar a sessão do usuário

// Garanta que $_SESSION['nomeUsuario'] está definida para evitar warnings.
if (!isset($_SESSION['nomeUsuario'])) {
    $_SESSION['nomeUsuario'] = "Visitante"; // Valor padrão para exibição, se não logado
}

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

    <link href="../vendor/css/login.css" rel="stylesheet">

    <link rel="shortcut icon" href="../img/icone_2.ico" type="image/x-icon">

</head>

<body>
    <nav class="navbar navbar-expand-lg" style="background-color: #e3f2fd;" data-bs-theme="light">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="../img/logo_marchef.png" width="120px" alt="Logo Marchef"> </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">Home</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            Cadastros
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Usuários</a></li>
                            <li><a class="dropdown-item" href="#">Clientes</a></li>
                            <li><a class="dropdown-item" href="#">Fornecedores</a></li>
                            <li><a class="dropdown-item" href="#">Produtos</a></li>
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
                            <span class="text-dark fw-bold"><?php echo $_SESSION['nomeUsuario']; ?></span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const switchSituacao = document.getElementById('situacao-perfil');
            const textoSituacao = document.getElementById('texto-situacao-perfil');

            // Função para atualizar o texto com base no estado do switch
            function atualizarTextoSwitch() {
                if (switchSituacao.checked) {
                    textoSituacao.textContent = 'Ativo';
                } else {
                    textoSituacao.textContent = 'Desativado';
                }
            }

            // Define o estado inicial do switch e do texto (ex: se o usuário já está ativo no banco)
            // ESTA É A PARTE IMPORTANTE PARA CARREGAR O ESTADO CORRETO
            // Por exemplo, se você recebe o estado do backend:
            const usuarioAtivo = true; // <--- Substitua 'true' ou 'false' pelo valor real do seu backend
            switchSituacao.checked = usuarioAtivo;
            atualizarTextoSwitch(); // Atualiza o texto imediatamente com base no estado inicial

            // Adiciona um 'listener' para quando o switch for clicado
            switchSituacao.addEventListener('change', atualizarTextoSwitch);

            // Opcional: Quando o modal é aberto, garantir que o estado é carregado
            const perfilModal = document.getElementById('perfil');
            perfilModal.addEventListener('show.bs.modal', function (event) {
                // Aqui você pode adicionar lógica para carregar os dados do perfil,
                // e então definir o switchSituacao.checked com base nesses dados
                // e chamar atualizarTextoSwitch() novamente.
                // Exemplo:
                // let isUserActiveFromDB = true; // Simule o valor vindo do banco de dados
                // switchSituacao.checked = isUserActiveFromDB;
                // atualizarTextoSwitch();
            });
        });
    </script>
</body>

</html>
<div class="modal fade" id="perfil" tabindex="-1" aria-labelledby="perfilModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="perfilModalLabel">Editar Perfil</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" id="form-perfil">
                <div class="modal-body">

                    <div class="mb-3">
                        <label for="nome-perfil" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome-perfil" name="nome_perfil"
                            placeholder="Nome Completo">
                    </div>

                    <div class="mb-3">
                        <label for="login-perfil" class="form-label">Login</label>
                        <input type="email" class="form-control" id="login-perfil" name="login_perfil"
                            placeholder="seu.email@exemplo.com" required>
                    </div>

                    <div class="mb-3">
                        <label for="senha-perfil" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha-perfil" name="senha_perfil"
                            placeholder="Deixe em branco para não alterar">
                        <small class="form-text text-muted">Preencha apenas se quiser mudar a senha.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="situacao-perfil">Situação</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="situacao-perfil"
                                name="situacao_perfil" value="1">
                            <label class="form-check-label" for="situacao-perfil"><span
                                    id="texto-situacao-perfil">Ativo</span></label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="nivel-perfil" class="form-label">Nível de Acesso</label>
                        <select class="form-select" id="nivel-perfil" aria-label="Selecione o nível de acesso"
                            name="nivel_perfil">
                            <option value="Admin">Administrador</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Producao" selected>Produção</option>
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