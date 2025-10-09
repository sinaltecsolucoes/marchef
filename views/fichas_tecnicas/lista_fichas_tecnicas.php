<?php // /views/fichas_tecnicas/lista_fichas_tecnicas.php ?>

<h4 class="fw-bold mb-3">Gestão de Fichas Técnicas</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <p>Crie e gerencie as Fichas Técnicas para cada produto do sistema.</p>
        <a href="index.php?page=ficha_tecnica_detalhes" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Criar Nova Ficha Técnica
        </a>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Fichas Técnicas</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-fichas-tecnicas" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Cód. Produto</th>
                        <th class="text-center align-middle">Produto</th>
                        <th class="text-center align-middle">Marca</th>
                        <th class="text-center align-middle">NCM</th>
                        <th class="text-center align-middle">Data Modificação</th>
                        <th class="text-center align-middle">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>