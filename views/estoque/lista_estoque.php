<?php
// /views/estoque/lista_estoque.php
?>

<h4 class="fw-bold mb-3">Visão Geral do Estoque</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Estoque Atual por Lote</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="tabela-estoque" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Tipo Produto</th>
                        <th>Subtipo</th>
                        <th>Classificação</th>
                        <th>Cód. Interno</th>
                        <th>Descrição do Produto</th>
                        <th>Lote</th>
                        <th>Cliente Origem</th>
                        <th>Data de Fabricação</th>
                        <th class="text-end">Peso (Emb.)</th>
                        <th class="text-end">Total Caixas</th>
                        <th class="text-end">Peso Total (kg)</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>