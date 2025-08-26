<?php
// O index.php já cuida da sessão e autenticação.
// A variável $_SESSION['nomeUsuario'] já está disponível.
?>

<header class="pb-3 mb-4 border-bottom">
    <h1 class="display-5">
        <i class="fa-solid fa-user-tie"></i>
        Painel Gerencial
    </h1>
    <h3 class="text-muted fw-light">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['nomeUsuario']); ?>!</h3>
</header>


<div class="row" id="dashboard-content">

    <div class="col-12">
        <div class="d-flex align-items-center justify-content-center" style="height: 200px;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <span class="ms-3">Carregando dados gerenciais...</span>
        </div>
    </div>

</div>