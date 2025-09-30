<?php
// /views/lotes_novo/gestao_caixas_mistas.php
?>

<h4 class="fw-bold mb-3">Gestão de Caixas Mistas (Estoque de Sobras)</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Estoque de Sobras de Lotes Finalizados</h6>
    </div>
    <div class="card-body">
        <p>Abaixo está a lista de todos os itens de produção primária que tiveram sobras (saldo > 0) após a finalização
            de seus lotes. Estes itens estão disponíveis para a montagem de Caixas Mistas.</p>

        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-estoque-sobras" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center align-middle" style="width: 5%;"><i class="fas fa-check"></i></th>
                        <th class="text-center align-middle">Lote de Origem</th>
                        <th class="text-center align-middle">Produto (Primário)</th>
                        <th class="text-center align-middle">Fornecedor</th>
                        <th class="text-center align-middle">Data Fabricação</th>
                        <th class="text-center align-middle">Saldo Restante (Unid.)</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Montagem da Nova Caixa Mista</h6>
    </div>
    <div class="card-body">
        <form id="form-caixa-mista">
            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="row">
                <div class="col-lg-7">
                    <h5 class="mb-3">Itens Selecionados (Sobras)</h5>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-striped" id="tabela-cart-mista">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Produto/Lote (Origem)</th>
                                    <th class="text-center">Qtd. a Usar</th>
                                    <th class="text-center">Ação</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-cart-mista">
                                <tr id="cart-placeholder">
                                    <td colspan="3" class="text-center text-muted">Selecione itens da tabela de sobras
                                        acima.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-lg-5">
                    <h5 class="mb-3">Destino da Nova Caixa</h5>
                    <div class="mb-3">
                        <label for="select-produto-final" class="form-label">Produto Final (Caixa Mista) <span
                                class="text-danger">*</span></label>
                        <select id="select-produto-final" name="mista_produto_final_id" class="form-select"
                            style="width: 100%;" required></select>
                        <div class="form-text">Cadastre produtos (Embalagem Secundária) como "Caixa Mista 5kg" no
                            Cadastro de Produtos.</div>
                    </div>
                    <div class="mb-3">
                        <label for="select-lote-destino" class="form-label">Lote de Destino <span
                                class="text-danger">*</span></label>
                        <select id="select-lote-destino" name="mista_lote_destino_id" class="form-select"
                            style="width: 100%;" required></select>
                        <div class="form-text">Selecione um lote "EM ANDAMENTO" ao qual esta nova caixa pertencerá.
                        </div>
                    </div>
                    <hr>
                    <button type="submit" id="btn-salvar-caixa-mista" class="btn btn-primary w-100 btn-lg" disabled>
                        <i class="fas fa-save me-2"></i> Salvar Caixa Mista e Gerar Etiqueta
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<template id="template-cart-row">
    <tr>
        <td class="align-middle small">
            <strong class="cart-item-produto">Nome Produto</strong><br>
            <span class="text-muted cart-item-lote">Lote Origem</span>
        </td>
        <td class="text-center align-middle cart-item-qtd">0.000</td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-danger btn-sm btn-remover-cart-item" title="Remover item do carrinho">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>