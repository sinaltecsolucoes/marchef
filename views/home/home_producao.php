<?php
// O index.php já cuida da sessão e autenticação.
?>

<header class="pb-3 mb-4 border-bottom">
    <h1 class="display-5">
        <i class="fa-solid fa-user-tie"></i>
        Painel de Produção
    </h1>
    <h3 class="text-muted fw-light">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['nomeUsuario']); ?>! Vamos produzir.</h3>
</header>

<div class="row" id="dashboard-content">

    <div class="col-12">
        <div class="d-flex align-items-center justify-content-center" style="height: 200px;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <span class="ms-3">Carregando lotes em andamento...</span>
        </div>
    </div>

</div>