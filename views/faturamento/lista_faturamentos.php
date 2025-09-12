<?php // /views/faturamento/lista_faturamentos.php ?>
<h4 class="fw-bold mb-3">Gerenciamento de Faturamento</h4>

<a href="index.php?page=faturamento_gerar" class="btn btn-primary mb-3">
    <i class="fas fa-plus me-2"></i> Gerar Novo Resumo de Faturamento
</a>

<div class="card shadow card-custom">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-faturamentos" class="table table-hover my-4" style="width:100%">
                <thead>
                    <tr>
                        <th>ID Resumo</th>
                        <th>Nº Ordem Origem</th>
                        <th>Data Geração</th>
                        <th>Status</th>
                        <th>Gerado por</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>