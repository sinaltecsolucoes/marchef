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

<div class="card shadow card-custom">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-fichas-tecnicas" class="table table-hover my-4" style="width:100%">
                <thead>
                    <tr>
                        <th>ID Ficha</th>
                        <th>Produto</th>
                        <th>Marca</th>
                        <th>NCM</th>
                        <th>Data Modificação</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>