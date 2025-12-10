<?php
// /views/lotes_novo/lista_lotes_novo.php
?>

<h4 class="fw-bold mb-3">Gestão de Lotes (Produção)</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Lotes</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-lotes-novo" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center aling-middle">Lote Completo</th>
                        <th class="text-center aling-middle">Fornecedor</th>
                        <th class="text-center aling-middle">Data Fabricação</th>
                        <th class="text-center aling-middle">Status</th>
                        <th class="text-center aling-middle">Data Cadastro</th>
                        <th class="text-center aling-middle" width="16%">Ações</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-lote-novo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-lote-novo-label">Adicionar Novo Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="aba-info-lote-novo-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-info-lote-novo" type="button" role="tab">1. Informações do
                            Lote</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="aba-producao-novo-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-producao-novo" type="button" role="tab">2. Incluir Produtos
                            (Produção)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="aba-embalagem-novo-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-embalagem-novo" type="button" role="tab">3. Incluir Produtos
                            (Embalagem)</button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3" id="myTabContent">

                    <div class="tab-pane fade show active" id="aba-info-lote-novo" role="tabpanel">
                        <form id="form-lote-novo-header">
                            <input type="hidden" id="lote_id_novo" name="lote_id">
                            <div id="mensagem-lote-novo-header" class="mb-3"></div>
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label for="lote_numero_novo" class="form-label">Número <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lote_numero_novo" name="lote_numero"
                                        required>
                                </div>
                                <div class="col-md-3">
                                    <label for="lote_data_fabricacao_novo" class="form-label">Data de Fabricação
                                        <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="lote_data_fabricacao_novo"
                                        name="lote_data_fabricacao" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-7">
                                    <label for="lote_cliente_id_novo" class="form-label">Cliente <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select" id="lote_cliente_id_novo" name="lote_cliente_id"
                                        style="width: 100%;"></select>
                                </div>
                                <div class="col-md-7">
                                    <label for="lote_fornecedor_id_novo" class="form-label">Fornecedor <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select" id="lote_fornecedor_id_novo" name="lote_fornecedor_id"
                                        style="width: 100%;"></select>
                                </div>
                                <div class="col-md-3">
                                    <label for="lote_viveiro_novo" class="form-label">Viveiro</label>
                                    <input type="text" class="form-control" id="lote_viveiro_novo" name="lote_viveiro">
                                </div>
                                <div class="col-md-2">
                                    <label for="lote_ciclo_novo" class="form-label">Ciclo</label>
                                    <input type="text" class="form-control" id="lote_ciclo_novo" name="lote_ciclo">
                                </div>
                                <div class="col-12">
                                    <label for="lote_completo_calculado_novo" class="form-label">Lote Completo
                                        (Automático) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lote_completo_calculado_novo"
                                        name="lote_completo_calculado" required>
                                </div>
                            </div>
                        </form>
                        <hr>
                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-primary" id="btn-salvar-lote-novo-header"><i class="fas fa-save me-2"></i>Salvar
                                Cabeçalho</button>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="aba-producao-novo" role="tabpanel">
                        <h5 class="mb-3">Adicionar Item de Produção (Embalagem Primária)</h5>
                        <form id="form-lote-novo-producao">
                            <input type="hidden" id="item_prod_id_novo" name="item_prod_id">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6">
                                    <label for="item_prod_produto_id_novo" class="form-label">Produto (Apenas
                                        Embalagens Primárias) <span class="text-danger">*</span></label>
                                    <select class="form-select" id="item_prod_produto_id_novo"
                                        name="item_prod_produto_id" style="width: 100%;" required></select>
                                </div>
                                <div class="col-md-3">
                                    <label for="item_prod_quantidade_novo" class="form-label">Quantidade Produzida
                                        (und) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="item_prod_quantidade_novo"
                                        name="item_prod_quantidade" step="0.001" min="0" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="item_prod_data_validade_novo" class="form-label">Data de
                                        Validade</label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="item_prod_data_validade_novo"
                                            name="item_prod_data_validade" readonly>
                                        <div class="input-group-text">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                    id="liberar_edicao_validade_novo"
                                                    title="Liberar edição manual da data">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-primary" id="btn-adicionar-item-producao"><i class="fas fa-plus me-1"></i>Adicionar
                                    Item</button>
                                <button type="button" class="btn btn-secondary"
                                    id="btn-cancelar-edicao-producao"><i class="fas fa-times me-2"></i>Limpar</button>
                            </div>
                        </form>
                        <hr>
                        <h6 class="mt-4">Itens de Produção Adicionados a Este Lote</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th class="align-middle">Produto</th>
                                        <th class="text-center align-middle">Und.</th>
                                        <th class="text-center align-middle">Qtd.<br>Produzida</th>
                                        <th class="text-center align-middle">Saldo<br>(p/ Embalar)</th>
                                        <th class="text-center align-middle">Validade</th>
                                        <th class="text-center align-middle coluna-acoes" style="width: 20%;">Ações
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-itens-producao-novo">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="aba-embalagem-novo" role="tabpanel">
                        <h5 class="mb-3">Adicionar Item de Embalagem (Embalagem Secundária)</h5>
                        <form id="form-lote-novo-embalagem">
                            <input type="hidden" id="item_emb_id_novo" name="item_emb_id">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label for="item_emb_prod_prim_id_novo" class="form-label">Consumir Saldo De
                                        (Produto Primário) <span class="text-danger">*</span></label>
                                    <select class="form-select" id="item_emb_prod_prim_id_novo"
                                        name="item_emb_prod_prim_id" style="width: 100%;" required></select>
                                </div>
                                <div class="col-md-5">
                                    <label for="item_emb_prod_sec_id_novo" class="form-label">Produto de Embalagem
                                        (Secundário) <span class="text-danger">*</span></label>
                                    <select class="form-select" id="item_emb_prod_sec_id_novo"
                                        name="item_emb_prod_sec_id" style="width: 100%;" required></select>
                                </div>
                                <div class="col-md-2">
                                    <label for="item_emb_qtd_sec_novo" class="form-label">Quantidade (und) <span
                                            class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="item_emb_qtd_sec_novo"
                                        name="item_emb_qtd_sec" step="1" min="1" required>
                                </div>
                            </div>

                            <div class.="mt-2 text-end">
                                <small id="feedback-consumo-embalagem" class="form-text text-muted">Preencha os
                                    campos para calcular o consumo.</small>
                            </div>

                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-success"
                                    id="btn-adicionar-item-embalagem"><i class="fas fa-plus me-1"></i>Adicionar Item</button>
                            </div>
                        </form>
                        <hr>
                        <h6 class="mt-4">Itens de Embalagem Adicionados a Este Lote</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped w-100">
                                <thead>
                                    <tr>
                                        <th>Produto (Embalagem)</th>
                                        <th class="text-center align-middle">Qtd. Embalagens</th>
                                        <th class="text-center align-middle">Consumido De (Produto Primário)</th>
                                        <th class="text-center align-middle">Qtd. Primária Consumida</th>
                                        <th class="text-center align-middle coluna-acoes" style="width: 20%;">Ações
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-itens-embalagem-novo">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-finalizar-lote" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Finalizar Itens do Lote: <span id="lote-nome-finalizacao"
                        class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Selecione as quantidades dos produtos de embalagem que deseja finalizar e enviar para o estoque.
                    Deixe em branco ou com valor 0 para não finalizar.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center align-middle">Produto</th>
                                <th class="text-center align-middle">Disponível</th>
                                <th style="width: 180px;" class="text-center align-middle">Qtd. a Finalizar</th>
                                <th class="text-center align-middle" style="width: 150px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="check-finalizar-todos">
                                        <label class="form-check-label" for="check-finalizar-todos">
                                            Finalizar Tudo
                                        </label>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tabela-itens-para-finalizar">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="btn-confirmar-finalizacao"><i class="fas fa-save me-2"></i>Confirmar
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancelar</button>
                    Finalização</button>
            </div>
        </div>
    </div>
</div>