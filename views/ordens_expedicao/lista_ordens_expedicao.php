<?php // /views/ordens_expedicao/lista_ordens_expedicao.php ?>

<h4 class="fw-bold mb-3">Gerenciamento de Ordens de Expedição</h4>

<a href="index.php?page=ordem_expedicao_detalhes" class="btn btn-primary mb-3">
    <i class="fas fa-plus me-2"></i> Criar Nova Ordem de Expedição
</a>

<div class="table-responsive">
    <table id="tabela-ordens-expedicao" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th>Nº da Ordem</th>
                <th>Data</th>
                <th>Status</th>
                <th>Criado por</th>
                <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>