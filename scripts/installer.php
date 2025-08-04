<?php
// public/installer.php

// A primeira coisa a fazer é definir o fuso horário e um título para a página
date_default_timezone_set('America/Sao_Paulo');
$pageTitle = 'Assistente de Instalação do Sistema "marchef"';

// Inclui o cabeçalho e o início do HTML
include_once __DIR__ . '/../views/layouts/header.php';
?>

<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 600px;">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0"><?php echo $pageTitle; ?></h4>
        </div>
        <div class="card-body">
            <p>Bem-vindo ao assistente de instalação! Para começar, preencha os dados abaixo.</p>

            <form id="install-form" method="POST" action="installer.php">
                <div class="mb-3">
                    <label for="db_host" class="form-label">Host do Banco de Dados</label>
                    <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                </div>
                <div class="mb-3">
                    <label for="db_user" class="form-label">Utilizador do Banco de Dados</label>
                    <input type="text" class="form-control" id="db_user" name="db_user" required>
                </div>
                <div class="mb-3">
                    <label for="db_pass" class="form-label">Senha do Banco de Dados</label>
                    <input type="password" class="form-control" id="db_pass" name="db_pass">
                </div>
                <div class="mb-3">
                    <label for="db_name" class="form-label">Nome do Banco de Dados</label>
                    <input type="text" class="form-control" id="db_name" name="db_name" required>
                </div>
                <hr>
                <div class="mb-3">
                    <label for="admin_name" class="form-label">Nome Completo do Administrador</label>
                    <input type="text" class="form-control" id="admin_name" name="admin_name" required>
                </div>
                <div class="mb-3">
                    <label for="admin_login" class="form-label">Login do Administrador</label>
                    <input type="text" class="form-control" id="admin_login" name="admin_login" required>
                </div>
                <div class="mb-3">
                    <label for="admin_pass" class="form-label">Senha do Administrador</label>
                    <input type="password" class="form-control" id="admin_pass" name="admin_pass" required>
                </div>
                <div class="mb-3">
                    <label for="admin_pass_confirm" class="form-label">Confirmação da Senha</label>
                    <input type="password" class="form-control" id="admin_pass_confirm" name="admin_pass_confirm" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Iniciar Instalação</button>
            </form>

            <div id="feedback-area" class="mt-3"></div>
        </div>
    </div>
</div>

<?php
// Inclui o rodapé da página
include_once __DIR__ . '/../views/layouts/footer.php';
?>