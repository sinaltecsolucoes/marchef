<?php
// views/lotes_novo/gerenciar_lotes.php

// Define variáveis baseadas na página atual (passada pelo index.php via GET)
$page = $_GET['page'] ?? 'lotes_recebimento';

// Configurações dinâmicas
$titulo = '';
$mostrarBotaoNovo = false;

switch ($page) {
    case 'lotes_recebimento':
        $titulo = 'Gestão de Lotes (Recebimento)';
        $mostrarBotaoNovo = true; // Apenas recebimento abre lote do zero
        break;
    case 'lotes_producao':
        $titulo = 'Gestão de Lotes (Produção)';
        break;
    case 'lotes_embalagem':
        $titulo = 'Gestão de Lotes (Embalagem)';
        break;
    default:
        $titulo = 'Gestão de Lotes';
}
?>

<div id="page-context" data-page-type="<?php echo $page; ?>"></div>

<h4 class="fw-bold mb-3"><?php echo $titulo; ?></h4>

<?php if ($mostrarBotaoNovo): ?>
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
<?php endif; ?>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-lotes-novo" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Lote Completo</th>
                        <th class="text-center align-middle">Cliente</th>
                        <th class="text-center align-middle">Fornecedor</th>
                        <th class="text-center align-middle">Data Fabricação</th>
                        <th class="text-center align-middle">Status</th>
                        <th class="text-center align-middle">Data Cadastro</th>
                        <th class="text-center align-middle" width="16%">Ações</th>
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
                <h5 class="modal-title" id="modal-lote-novo-label">Gerenciar Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

                <ul class="nav nav-tabs" id="tabLoteUnificado" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="aba-info-lote-novo-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-info-lote-novo" type="button" role="tab">1. Informações Gerais</button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="aba-detalhes-recebimento-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-detalhes-recebimento" type="button" role="tab">2. Detalhes (Itens/NF)</button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="aba-producao-novo-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-producao-novo" type="button" role="tab">2. Produção (Primária)</button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="aba-embalagem-novo-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-embalagem-novo" type="button" role="tab">3. Embalagem (Secundária)</button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3" id="tabLoteUnificadoContent">

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
                            <input type="hidden" name="item_receb_id" id="item_receb_id">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Tipo de Entrada da Matéria-Prima</label>
                                <div class="form-check">
                                    <input class="form-check-input"
                                        type="radio"
                                        name="tipo_entrada_mp"
                                        id="entrada_mp_materia"
                                        value="MATERIA_PRIMA" checked>
                                    <label class="form-check-label"
                                        for="entrada_mp_materia">Entrada por Matéria-Prima</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input"
                                        type="radio"
                                        name="tipo_entrada_mp"
                                        id="entrada_mp_lote"
                                        value="LOTE_ORIGEM">
                                    <label class="form-check-label"
                                        for="entrada_mp_lote">Entrada por Lote de Origem (Reprocesso)</label>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Produto (Matéria Prima) *</label>
                                    <select class="form-select"
                                        id="item_receb_produto_id"
                                        name="item_receb_produto_id"
                                        style="width: 100%;"></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Origem Reprocesso</label>
                                    <select class="form-select"
                                        id="item_receb_lote_origem_id"
                                        name="item_receb_lote_origem_id"
                                        style="width: 100%;" disabled>
                                        <option value="">Nenhum</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Nota Fiscal</label>
                                    <input type="text"
                                        class="form-control"
                                        id="item_receb_nota_fiscal"
                                        name="item_receb_nota_fiscal">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Peso NF (kg)</label>
                                    <input type="text"
                                        class="form-control text-end mask-peso-3"
                                        id="item_receb_peso_nota_fiscal"
                                        name="item_receb_peso_nota_fiscal" placeholder="0,000">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Total Caixas</label>
                                    <input type="number"
                                        class="form-control text-end"
                                        id="item_receb_total_caixas"
                                        name="item_receb_total_caixas">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label text-muted small">P.Médio Fazenda (Kg)</label>
                                    <input type="text"
                                        class="form-control bg-light text-end"
                                        id="calc_peso_medio_fazenda" readonly tabindex="-1">
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">P. Médio Indústria (kg)</label>
                                    <input type="text" class="form-control text-end mask-peso-2" id="item_receb_peso_medio_ind" name="item_receb_peso_medio_ind" placeholder="0,00">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Gramatura Fazenda</label>
                                    <input type="text" class="form-control text-end mask-peso-2" name="item_receb_gram_faz" placeholder="0,00">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Gramatura Lab</label>
                                    <input type="text" class="form-control text-end mask-peso-2" name="item_receb_gram_lab" placeholder="0,00">
                                </div>

                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="w-100 d-flex gap-2">
                                        <button type="button" class="btn btn-secondary flex-fill d-none" id="btn-cancelar-edicao">
                                            Cancelar
                                        </button>
                                        <button type="button" class="btn btn-success flex-fill" id="btn-adicionar-item-recebimento">
                                            <i class="fas fa-plus"></i> Salvar
                                        </button>
                                    </div>
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
                                        <th class="text-center align-middle">Origem</th>
                                        <th class="text-center align-middle">NF</th>
                                        <th class="text-center align-middle">Peso NF</th>
                                        <th class="text-center align-middle">Caixas</th>
                                        <th class="text-center align-middle">Peso Médio</th>
                                        <th class="text-center align-middle">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-itens-recebimento"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="aba-producao-novo" role="tabpanel">
                        <h5 class="mb-3">Adicionar Item de Produção (Embalagem Primária)</h5>

                        <form id="form-lote-novo-producao">
                            <input type="hidden" id="item_prod_id_novo" name="item_prod_id">

                            <div class="row g-3 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label">Produto (Apenas Embalagens Primárias) *</label>
                                    <select class="form-select" id="item_prod_produto_id_novo" name="item_prod_produto_id" style="width: 100%;" required></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Quantidade Produzida (und) *</label>
                                    <input type="number" class="form-control" id="item_prod_quantidade_novo" name="item_prod_quantidade" step="0.001" min="0" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data de Validade</label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="item_prod_data_validade_novo" name="item_prod_data_validade" readonly>
                                        <div class="input-group-text">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" id="liberar_edicao_validade_novo" title="Liberar edição manual">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-primary" id="btn-adicionar-item-producao"><i class="fas fa-plus me-1"></i> Adicionar Item</button>
                                <button type="button" class="btn btn-secondary" id="btn-cancelar-edicao-producao"><i class="fas fa-times me-2"></i> Limpar</button>
                            </div>
                        </form>

                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th class="text-center">Unid.</th>
                                        <th class="text-center">Qtd. Produzida</th>
                                        <th class="text-center">Saldo (p/ Embalar)</th>
                                        <th class="text-center">Validade</th>
                                        <th class="text-center" style="width: 150px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-itens-producao-novo"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="aba-embalagem-novo" role="tabpanel">
                        <h5 class="mb-3">Adicionar Item de Embalagem (Embalagem Secundária)</h5>

                        <form id="form-lote-novo-embalagem">
                            <input type="hidden" id="item_emb_id_novo" name="item_emb_id">

                            <div class="row g-3 align-items-start">
                                <div class="col-md-5">
                                    <label class="form-label">Consumir Saldo De (Produto Primário) *</label>
                                    <select class="form-select" id="item_emb_prod_prim_id_novo" name="item_emb_prod_prim_id" style="width: 100%;" required></select>
                                    <small id="feedback-consumo-embalagem"
                                        class="form-text text-muted">Preencha os campos para calcular o consumo.</small>

                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Produto de Embalagem (Secundário) *</label>
                                    <select class="form-select" id="item_emb_prod_sec_id_novo" name="item_emb_prod_sec_id" style="width: 100%;" required></select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Quantidade (und) *</label>
                                    <input type="number" class="form-control" id="item_emb_qtd_sec_novo" name="item_emb_qtd_sec" step="1" min="1" required>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-primary" id="btn-adicionar-item-embalagem"><i class="fas fa-plus me-1"></i> Adicionar Item</button>
                            </div>
                        </form>

                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-striped w-100">
                                <thead>
                                    <tr>
                                        <th>Produto (Embalagem)</th>
                                        <th class="text-center">Qtd. Embalagens</th>
                                        <th class="text-center">Consumido De (Produto Primário)</th>
                                        <th class="text-center">Qtd. Primária Consumida</th>
                                        <th class="text-center" style="width: 150px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-itens-embalagem-novo"></tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-finalizar-lote" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Encerrar Lote: <span id="lote-nome-finalizacao" class="text-primary fw-bold"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body ">

                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">

                            <!-- Alerta -->
                            <div class="alert alert-info d-flex align-items-center mb-3">
                                <i class="fas fa-info-circle fa-2x me-3"></i>
                                <div>
                                    <strong>Conferência de Finalização</strong><br>
                                    Confira os totais produzidos abaixo. O estoque já foi movimentado durante a criação das caixas.
                                    Ao finalizar, você define o status do lote.
                                </div>
                            </div>

                            <!-- Tabela -->
                            <div class="table-responsive px-0 mx-0">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="10%" class="text-center align-middle font-small">CÓDIGO</th>
                                            <th width="55%" class="text-center align-middle font-small">DESCRIÇÃO</th>
                                            <th width="5%" class="text-center align-middle font-small">UND</th>
                                            <th width="10%" class="text-center align-middle font-small">T. PRODUÇÃO (Prim)</th>
                                            <th width="10%" class="text-center align-middle font-small">T. CXS SEC</th>
                                            <th width="10%" class="text-center aling=middle text-warning font-small">T. SOBRAS (Prim)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabela-resumo-finalizacao">
                                    </tbody>
                                    <tfoot class="table-light fw-bold">
                                        <tr>
                                            <td colspan="3" class="text-end">TOTAIS GERAIS:</td>
                                            <td class="text-center" id="total-geral-producao">-</td>
                                            <td class="text-center" id="total-geral-caixas">-</td>
                                            <td class="text-center text-warning" id="total-geral-sobras">-</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light d-flex justify-content-between align-items-center">

                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-arrow-left me-2"></i> Voltar / Sair
                    </button>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-warning text-dark px-3" id="btn-decisao-parcial">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Manter Parcial</strong>
                            <br><small style="font-size: 0.7em;">Continuar produzindo</small>
                        </button>

                        <button type="button" class="btn btn-success px-4" id="btn-decisao-total">
                            <i class="fas fa-check-double me-2"></i>
                            <strong>Finalizar e Encerrar</strong>
                            <br><small style="font-size: 0.7em;">Estoque completo</small>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>