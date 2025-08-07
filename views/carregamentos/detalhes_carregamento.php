<?php
// /views/carregamentos/detalhes_carregamento.php

// O controlador (index.php) irá buscar os dados e fornecê-los aqui.
// Por enquanto, vamos apenas montar a estrutura.
?>

<div id="dados-carregamento-header">
    <h4 class="fw-bold mb-1">Carregamento Nº <span id="car-numero-detalhe">...</span></h4>
    <p class="text-muted">Status: <span id="car-status-detalhe" class="badge bg-secondary">...</span></p>
</div>

<hr>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Adicionar Item ao Carregamento</h6>
    </div>
    <div class="card-body">
        <form id="form-adicionar-item-carregamento">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label for="select-item-estoque" class="form-label">Item do Estoque (Produto / Lote)</label>
                    <select id="select-item-estoque" class="form-select" style="width: 100%;"></select>
                </div>
                <div class="col-md-3">
                    <label for="quantidade-item-carregamento" class="form-label">Quantidade (kg)</label>
                    <input type="number" id="quantidade-item-carregamento" class="form-control" step="0.001" min="0">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-plus me-2"></i> Adicionar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Itens no Carregamento</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-itens-carregamento" class="table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Lote</th>
                        <th class="text-end">Quantidade (kg)</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>