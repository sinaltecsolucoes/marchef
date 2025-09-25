<?php // /views/ordens_expedicao/lista_ordens_expedicao.php ?>

<h4 class="fw-bold mb-3">Gestão de Ordens de Expedição</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <p>Crie e gerencie todas as Ordens de Expedição do sistema.</p>
                <a href="index.php?page=ordem_expedicao_detalhes" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Criar Nova Ordem de Expedição
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Ordens de Expedição</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-ordens-expedicao" class="table table-bordered table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Nº da Ordem</th>
                        <th class="text-center align-middle">Data</th>
                        <th class="text-center align-middle">Status</th>
                        <th class="text-center align-middle">Nº Carregamento</th>
                        <th class="text-center align-middle">Criado por</th>
                        <th class="text-center align=middle">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>