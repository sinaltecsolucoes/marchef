<?php // /views/faturamento/lista_faturamentos.php ?>

<h4 class="fw-bold mb-3">Gestão de Faturamento</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-1">
            <div class="col-md-6">
                <p>Gerencie todos os faturamentos</p>
                <a href="index.php?page=faturamento_gerar" class="btn btn-primary mb-3">
                    <i class="fas fa-plus me-2"></i> Criar Novo Faturamento
                </a>                
            </div>
        </div>
    </div>
</div>

<div class="card shadow card-custom">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-faturamentos" class="table table-bordered table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center align-middle">ID Resumo</th>
                        <th class="text-center align-middle">Nº Ordem Origem</th>
                        <th class="text-center align-middle">Data Geração</th>
                        <th class="text-center align-middle">Status</th>
                        <th class="text-center align-middle">Gerado por</th>
                        <th class="text-center align-middle">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-marcar-faturado" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Informar Notas Fiscais</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-marcar-faturado">
                <div class="modal-body">
                    <input type="hidden" id="faturado_resumo_id" name="resumo_id">
                    <p>Informe o número da Nota Fiscal para cada grupo de pedido:</p>
                    <div id="notas-fiscais-container">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar e Marcar como Faturado</button>
                </div>
            </form>
        </div>
    </div>
</div>