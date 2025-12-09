<?php
// /views/lotes_novo/lista_lotes_recebimento.php
?>

<h4 class="fw-bold mb-3">Gestão de Lotes (Recebimento)</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Abertura de Lote</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <button class="btn btn-primary" id="btn-adicionar-lote-novo">
                    <i class="fas fa-plus me-2"></i> Abrir Novo Lote
                </button>
            </div>
        </div>
    </div>
</div>


<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Lotes Recebidos</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-lotes-novo" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Lote Completo</th>
                        <th class="text-center align-middle">Fornecedor</th>
                        <th class="text-center align-middle">Data Fabricação</th>
                        <th class="text-center align-middle">Status</th>
                        <th class="text-center align-middle">Data Cadastro</th>
                        <th class="text-center align-middle" width="16%">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>


<div class="modal fade" id="modal-lote-novo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-lote-novo-label">Gerenciar Recebimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="tabRecebimento" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="aba-info-lote-novo-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-info-lote-novo" type="button" role="tab">1. Informações Gerais</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="aba-detalhes-recebimento-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-detalhes-recebimento" type="button" role="tab">2. Detalhes (Itens/NF)</button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3" id="tabRecebimentoContent">

                    <div class="tab-pane fade show active" id="aba-info-lote-novo" role="tabpanel">
                        <form id="form-lote-novo-header">
                            <input type="hidden" id="lote_id_novo" name="lote_id">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label">Número *</label>
                                    <input type="text" class="form-control" id="lote_numero_novo" name="lote_numero" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data Fabricação *</label>
                                    <input type="date" class="form-control" id="lote_data_fabricacao_novo" name="lote_data_fabricacao" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Cliente *</label>
                                    <select class="form-select" id="lote_cliente_id_novo" name="lote_cliente_id" style="width: 100%;"></select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Fornecedor *</label>
                                    <select class="form-select" id="lote_fornecedor_id_novo" name="lote_fornecedor_id" style="width: 100%;"></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Viveiro</label>
                                    <input type="text" class="form-control" id="lote_viveiro_novo" name="lote_viveiro">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Ciclo</label>
                                    <input type="text" class="form-control" id="lote_ciclo_novo" name="lote_ciclo">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Lote Completo (Automático) *</label>
                                    <input type="text" class="form-control" id="lote_completo_calculado_novo" name="lote_completo_calculado" required>
                                </div>
                            </div>
                        </form>
                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-primary" id="btn-salvar-lote-novo-header"><i class="fas fa-save me-2"></i>Salvar e Avançar</button>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="aba-detalhes-recebimento" role="tabpanel">
                        <form id="form-recebimento-detalhe">
                            <input type="hidden" name="item_receb_lote_id" id="item_receb_lote_id">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Produto (Matéria Prima) *</label>
                                    <select class="form-select" id="item_receb_produto_id" name="item_receb_produto_id" style="width: 100%;" required></select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Origem Reprocesso (Opcional)</label>
                                    <select class="form-select" id="item_receb_lote_origem_id" name="item_receb_lote_origem_id" style="width: 100%;">
                                        <option value="">Nenhum (Matéria Prima Nova)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Nota Fiscal</label>
                                    <input type="text" class="form-control" name="item_receb_nota_fiscal">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Peso NF (kg)</label>
                                    <input type="number" step="0.001" class="form-control" name="item_receb_peso_nota_fiscal">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Total Caixas</label>
                                    <input type="number" class="form-control" id="item_receb_total_caixas" name="item_receb_total_caixas">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label text-muted" style="font-size: 0.85rem;">P.Médio Fazenda(Kg)</label>
                                    <input type="text" class="form-control bg-light" id="calc_peso_medio_fazenda" readonly tabindex="-1">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">P. Médio Indústria(kg)</label>
                                    <input type="number" step="0.01" class="form-control" id="item_receb_peso_medio_ind" name="item_receb_peso_medio_ind">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Gramatura Fazenda</label>
                                    <input type="number" step="0.01" class="form-control" name="item_receb_gram_faz">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gramatura Lab</label>
                                    <input type="number" step="0.01" class="form-control" name="item_receb_gram_lab">
                                </div>
                                <div class="col-md-6 d-flex align-items-end justify-content-end">
                                    <button type="button" class="btn btn-success w-100" id="btn-adicionar-item-recebimento">
                                        <i class="fas fa-plus me-2"></i> Adicionar Item
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr>
                        <h6>Itens Recebidos neste Lote</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produto</th>
                                        <th>Origem</th>
                                        <th>NF</th>
                                        <th>Peso NF</th>
                                        <th>Caixas</th>
                                        <th>Peso Médio</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-itens-recebimento"></tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>