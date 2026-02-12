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
            <h6 class="m-0 fw-bold text-primary">
                Gestão de Lotes
            </h6>
        </div>

        <div class="card-body">
            <div class="row">

                <div class="col-md-3 border-end">
                    <h5 class="fw-bold text-secondary mb-3" style="font-size: 0.9rem; text-transform: uppercase;">
                        <i class="fas fa-sign-in-alt me-2"></i>Entrada
                    </h5>

                    <div id="form-acoes-lote" class="row g-2 pt-4 align-items-end">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-sm py-2 flex-grow-1" id="btn-adicionar-lote-novo">
                                <i class="fas fa-plus me-2"></i> ABRIR NOVO LOTE
                            </button>

                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-dark btn-sm py-2 flex-grow-1" data-bs-toggle="modal" data-bs-target="#modal-importar-legado">
                                    <i class="fas fa-history me-2"></i>IMPORTAR LOTE ANTIGO
                                </button>

                                <button class="btn btn-outline-danger btn-sm py-2" data-bs-toggle="modal" data-bs-target="#modal-historico-legado" title="Gerenciar Importações">
                                    <i class="fas fa-list"></i> GERENCIAR IMPORTAÇÕES
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-9 ps-3">
                    <h5 class="fw-bold text-secondary mb-2" style="font-size: 0.9rem; text-transform: uppercase;">
                        <i class="fas fa-filter me-2"></i>Filtros e Relatório
                    </h5>

                    <div id="form-relatorio-mensal" class="row g-2 align-items-end">

                        <!-- Lista de Tipo Produto -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Produto</label>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start text-truncate" type="button" id="btn-dropdown-tipo" data-bs-toggle="dropdown">
                                    Produto...
                                </button>
                                <ul class="dropdown-menu p-2 shadow" id="lista-tipo-produto" style="width:100%; min-width:unset;">
                                    <li class="p-1 border-bottom mb-2 bg-light rounded">
                                        <div class="form-check">
                                            <input class="form-check-input fw-bold" type="checkbox" id="check-tipo-todos" checked>
                                            <label class="form-check-label fw-bold text-primary font-small" for="check-tipo-todos">MARCAR TODOS</label>
                                        </div>
                                    </li>
                                    <li class="p-1">
                                        <div class="form-check">
                                            <input class="form-check-input check-tipo-item" type="checkbox" value="SEM_PRODUTO" id="tipo-vazio" checked>
                                            <label class="form-check-label font-small" for="tipo-vazio">SEM PRODUTO</label>
                                        </div>
                                    </li>
                                    <li class="p-1">
                                        <div class="form-check">
                                            <input class="form-check-input check-tipo-item" type="checkbox" value="CAMARAO" id="tipo-camarao" checked>
                                            <label class="form-check-label font-small" for="tipo-camarao">CAMARÃO</label>
                                        </div>
                                    </li>
                                    <li class="p-1">
                                        <div class="form-check">
                                            <input class="form-check-input check-tipo-item" type="checkbox" value="PEIXE" id="tipo-peixe" checked>
                                            <label class="form-check-label font-small" for="tipo-peixe">PEIXE</label>
                                        </div>
                                    </li>
                                    <li class="p-1">
                                        <div class="form-check">
                                            <input class="form-check-input check-tipo-item" type="checkbox" value="LAGOSTA" id="tipo-lagosta" checked>
                                            <label class="form-check-label font-small" for="tipo-lagosta">LAGOSTA</label>
                                        </div>
                                    </li>
                                    <li class="p-1">
                                        <div class="form-check">
                                            <input class="form-check-input check-tipo-item" type="checkbox" value="POLVO" id="tipo-polvo" checked>
                                            <label class="form-check-label font-small" for="tipo-polvo">POLVO</label>
                                        </div>
                                    <li class="p-1">
                                        <div class="form-check">
                                            <input class="form-check-input check-tipo-item" type="checkbox" value="OUTRO" id="tipo-outros" checked>
                                            <label class="form-check-label font-small" for="tipo-outros">OUTROS</label>
                                        </div>
                                    </li>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Lista de Meses -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Período (Meses)</label>

                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start text-truncate" type="button" id="btn-dropdown-meses" data-bs-toggle="dropdown" aria-expanded="false">
                                    Selecione os meses...
                                </button>

                                <ul class="dropdown-menu p-2 shadow w-100" aria-labelledby="btn-dropdown-meses" style="max-height: 250px; overflow-y: auto;">

                                    <li class="p-1 border-bottom mb-2 bg-light rounded">
                                        <div class="form-check">
                                            <input class="form-check-input fw-bold" type="checkbox" id="check-mes-todos" checked>
                                            <label class="form-check-label fw-bold text-primary cursor-pointer" for="check-mes-todos">
                                                DESMARCAR TODOS
                                            </label>
                                        </div>
                                    </li>

                                    <?php
                                    $meses = [
                                        1 => 'Janeiro',
                                        2 => 'Fevereiro',
                                        3 => 'Março',
                                        4 => 'Abril',
                                        5 => 'Maio',
                                        6 => 'Junho',
                                        7 => 'Julho',
                                        8 => 'Agosto',
                                        9 => 'Setembro',
                                        10 => 'Outubro',
                                        11 => 'Novembro',
                                        12 => 'Dezembro'
                                    ];

                                    foreach ($meses as $num => $nome) {
                                        // Agora todos iniciam com 'checked'
                                        $checked = 'checked';
                                        echo "
                                            <li class='p-1'>
                                                <div class='form-check'>
                                                    <input class='form-check-input check-mes-item' type='checkbox' value='{$num}' id='mes-{$num}' {$checked}>
                                                    <label class='form-check-label w-100 cursor-pointer' for='mes-{$num}'>
                                                        {$nome}
                                                    </label>
                                                </div>
                                            </li>";
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Lista de Fornecedores -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Fornecedor</label>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start text-truncate" type="button" id="btn-dropdown-fornecedores" data-bs-toggle="dropdown">
                                    Carregando fornecedores...
                                </button>
                                <ul class="dropdown-menu p-2 shadow w-100" id="lista-filtro-fornecedores" aria-labelledby="btn-dropdown-fornecedores" style="max-height: 250px; overflow-y: auto;">
                                    <li class="p-1 border-bottom mb-2 bg-light rounded">
                                        <div class="form-check">
                                            <input class="form-check-input fw-bold" type="checkbox" id="check-fornecedor-todos" checked>
                                            <label class="form-check-label fw-bold text-primary cursor-pointer" for="check-fornecedor-todos">DESMARCAR TODOS</label>
                                        </div>
                                    </li>
                                    <div id="container-check-fornecedores-items" class="small">
                                        <li class="text-center text-muted small py-2"><i class="fas fa-spinner fa-spin"></i> Carregando...</li>
                                    </div>
                                </ul>
                            </div>
                        </div>

                        <!-- Lista de Situação -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Situação</label>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start text-truncate" type="button" id="btn-dropdown-situacao" data-bs-toggle="dropdown">
                                    Situação...
                                </button>
                                <ul class="dropdown-menu p-2 shadow" id="lista-filtro-situacao" style="width:100%; min-width:unset;">
                                    <li class="p-1 border-bottom mb-2 bg-light rounded">
                                        <div class="form-check">
                                            <input class="form-check-input fw-bold" type="checkbox" id="check-situacao-todos">
                                            <label class="form-check-label fw-bold text-primary font-small" for="check-situacao-todos">MARCAR TODAS</label>
                                        </div>
                                    </li>
                                    <li class="p-1">
                                        <div class="form-check text-warning">
                                            <input class="form-check-input check-situacao-item" type="checkbox" value="EM ANDAMENTO" id="sit-aberto" checked>
                                            <label class="form-check-label font-small" for="sit-aberto">ABERTO</label>
                                        </div>
                                    </li>
                                     <li class="p-1">
                                        <div class="form-check text-info">
                                            <input class="form-check-input check-situacao-item" type="checkbox" value="PARCIALMENTE FINALIZADO" id="sit-parcial" checked>
                                            <label class="form-check-label font-small" for="sit-parcial">PARCIAL</label>
                                        </div>
                                    </li>
                                    <li class="p-1">
                                        <div class="form-check text-success">
                                            <input class="form-check-input check-situacao-item" type="checkbox" value="FINALIZADO" id="sit-finalizado" checked>
                                            <label class="form-check-label font-small" for="sit-finalizado">FINALIZADO</label>
                                        </div>
                                    </li>
                                    <li class="p-1">
                                        <div class="form-check text-danger">
                                            <input class="form-check-input check-situacao-item" type="checkbox" value="CANCELADO" id="sit-cancelado">
                                            <label class="form-check-label font-small" for="sit-cancelado">CANCELADO</label>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Campo para Ano -->
                        <div class="col-md-1">
                            <label class="form-label small fw-bold mb-1">Exercício (Ano)</label>
                            <input
                                type="number"
                                class="form-control form-control-sm"
                                id="rel_ano"
                                value="<?php echo date('Y'); ?>">
                        </div>

                        <!-- Botão Gerar -->
                        <div class="col-md-2">
                            <button type="button" class="btn btn-info btn-sm fw-bold text-white w-100" id="btn-gerar-relatorio-mensal">
                                <i class="fas fa-print me-2"></i> GERAR
                            </button>
                        </div>
                    </div>

                    <?php if ($page === 'lotes_recebimento'): ?>
                        <div class="row g-2 mt-2 border-top">

                            <!-- Card Total Lotes -->
                            <div class="col-md-3">
                                <div class="d-flex align-items-center p-2 bg-light rounded border">
                                    <i class="fas fa-boxes text-info me-2"></i>
                                    <div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">TOTAL LOTES</small>
                                        <span id="card-total-itens" class="fw-bold text-dark">0</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Peso Filtrado -->
                            <div class="col-md-3">
                                <div class="d-flex align-items-center p-2 bg-light rounded border">
                                    <i class="fas fa-weight-hanging text-primary me-2"></i>
                                    <div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">PESO FILTRADO</small>
                                        <span id="card-total-peso" class="fw-bold text-dark">0,000kg</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Gramatura Fazenda (Média) -->
                            <div class="col-md-3">
                                <div class="d-flex align-items-center p-2 bg-light rounded border">
                                    <i class="fas fa-solid fa-balance-scale text-primary me-2"></i>
                                    <div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">GRAM. FAZENDA (MÉDIA)</small>
                                        <span id="card-media-fazenda" class="fw-bold text-dark">0,0g</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Gramaura Laboratório (Média) -->
                            <div class="col-md-3">
                                <div class="d-flex align-items-center p-2 bg-light rounded border">
                                    <i class="fas fa-solid fa-balance-scale text-success me-2"></i>
                                    <div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">GRAM. LABORAT. (MÉDIA)</small>
                                        <span id="card-media-lab" class="fw-bold text-dark">0,0g</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
                        <th class="text-center align-middle">Fornecedor</th>
                        <th class="text-center align-middle">Gram. Faz.</th>
                        <th class="text-center align-middle">Gram. Benef.</th>
                        <th class="text-center align-middle">Peso</th>
                        <th class="text-center align-middle">Data Fabricação</th>
                        <th class="text-center align-middle">Status</th>
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
    <div class="modal-dialog modal-xxl">
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

                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Número *</label>
                                    <input type="text" class="form-control" id="lote_numero_novo" name="lote_numero" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data Fabricação *</label>
                                    <input type="date" class="form-control" id="lote_data_fabricacao_novo" name="lote_data_fabricacao" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fornecedor *</label>
                                    <select class="form-select" id="lote_cliente_id_novo" name="lote_cliente_id" style="width: 100%;"></select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Fazenda (Origem) *</label>
                                    <select class="form-select" id="lote_fornecedor_id_novo" name="lote_fornecedor_id" style="width: 100%;"></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Viveiro</label>
                                    <input type="text" class="form-control" id="lote_viveiro_novo" name="lote_viveiro">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">SO2 (mg/kg)</label>
                                    <input type="number" step="0.01" class="form-control" id="lote_so2_novo" name="lote_so2">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <label class="form-label">Lote Completo (Automático) *</label>
                                    <input type="text" class="form-control bg-light" id="lote_completo_calculado_novo" name="lote_completo_calculado" required>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Observações</label>
                                    <textarea class="form-control" id="lote_observacao_novo" name="lote_observacao" rows="3"></textarea>
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
                            <input type="hidden" id="lote_status" name="lote_status" value="">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Tipo de Entrada</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_entrada_mp"
                                            id="entrada_mp_materia" value="MATERIA_PRIMA" checked>
                                        <label class="form-check-label" for="entrada_mp_materia">
                                            Matéria-Prima
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_entrada_mp"
                                            id="entrada_mp_lote" value="LOTE_ORIGEM">
                                        <label class="form-check-label" for="entrada_mp_lote">
                                            Reprocesso (Lote Origem)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3" id="div-select-mp">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Produto (Matéria Prima) *</label>
                                    <select class="form-select" id="item_receb_produto_id" name="item_receb_produto_id" style="width: 100%;">
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">

                                <div class="col-md-6" id="div-lote-origem" style="display: none;">
                                    <label class="form-label fw-bold">Lote de Origem (Finalizado) *</label>
                                    <select class="form-select select2-lotes-finalizados"
                                        id="item_receb_lote_origem_id"
                                        name="item_receb_lote_origem_id" style="width: 100%;">
                                        <option value="">Buscar lote...</option>
                                    </select>
                                </div>

                                <div class="col-md-6" id="div-produto-origem" style="display: none;">
                                    <label class="form-label fw-bold text-primary">Produto a Reprocessar *</label>
                                    <select class="form-select"
                                        id="select-produto-origem"
                                        name="lote_origem_produto_id" style="width: 100%;">
                                        <option value="">Selecione um lote primeiro...</option>
                                    </select>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        Os pesos serão calculados com base neste item.
                                    </small>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Nota Fiscal / Rastreio</label>
                                    <input type="text" class="form-control"
                                        id="item_receb_nota_fiscal" name="item_receb_nota_fiscal">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Peso Entrada (kg) *</label>
                                    <input type="text" class="form-control text-end mask-peso-3"
                                        id="item_receb_peso_nota_fiscal"
                                        name="item_receb_peso_nota_fiscal" placeholder="0,000">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Total Caixas</label>
                                    <input type="number" class="form-control text-end"
                                        id="item_receb_total_caixas" name="item_receb_total_caixas">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">P.Médio Fazenda (Kg)</label>
                                    <input type="text" class="form-control bg-light text-end"
                                        id="calc_peso_medio_fazenda" readonly tabindex="-1">
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">P. Médio Indústria (kg)</label>
                                    <input type="text" class="form-control text-end mask-peso-2"
                                        id="item_receb_peso_medio_ind"
                                        name="item_receb_peso_medio_ind" placeholder="0,00">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Gramatura Fazenda</label>
                                    <input type="text" class="form-control text-end mask-peso-2"
                                        name="item_receb_gram_faz" placeholder="0,00">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Gramatura Lab</label>
                                    <input type="text" class="form-control text-end mask-peso-2"
                                        name="item_receb_gram_lab" placeholder="0,00">
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
                                        <th class="text-center align-middle">Produto</th>
                                        <th class="text-center align-middle">Origem</th>
                                        <th class="text-center align-middle">NF</th>
                                        <th class="text-center align-middle">Peso NF</th>
                                        <th class="text-center align-middle">Caixas</th>
                                        <th class="text-center align-middle">Peso Médio (Ind.)</th>
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
                            <input type="hidden" id="item_prod_categoria_novo" name="item_prod_categoria">

                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label">Produto (Apenas Embalagens Primárias) *</label>
                                    <select class="form-select" id="item_prod_produto_id_novo" name="item_prod_produto_id" style="width: 100%;" required></select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label font-small">Quant. Produzida (kg) *</label>
                                    <input type="number" class="form-control" id="item_prod_quilos" name="item_prod_quilos" step="0.001" min="0" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label font-small">Quant. Produzida (und) *</label>
                                    <input type="number" class="form-control" id="item_prod_quantidade_novo" name="item_prod_quantidade" step="0.001" min="0" required readonly>
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
                                        <th class="text-center">Quilos</th>
                                        <th class="text-center">Und. Produzida</th>
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

<div class="modal fade" id="modal-importar-legado" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-file-import me-2"></i>Carga Inicial (Lote Legado)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Atenção:</strong> Utilize esta função apenas para implantar estoques existentes.
                    O lote será criado como <strong>FINALIZADO</strong> e o saldo entrará imediatamente no estoque físico.
                </div>

                <form id="form-importar-legado">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Número Lote (Antigo) *</label>
                            <input type="text" class="form-control uppercase" name="lote_codigo" placeholder="Ex: 120/24" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Data Fabricação *</label>
                            <input type="date" class="form-control" id="lote_data_fabricacao_legado" name="data_fabricacao" required>

                            <input type="hidden" id="lote_data_validade_legado" name="data_validade">
                        </div>


                        <div class="col-md-4">
                            <label class="form-label fw-bold">Fornecedor *</label>
                            <select class="form-select select2-clientes" name="cliente_id" style="width:100%" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>

                        <hr class="my-3 text-muted">

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Produto (Estoque) *</label>
                            <select class="form-select select2-produtos" name="produto_id" style="width:100%" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>

                        <hr class="my-3 text-muted">

                        <input type="hidden" id="hidden_peso_embalagem" value="0">

                        <div class="col-12 mb-2">
                            <label class="form-label fw-bold d-block">Modo de Cálculo:</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_calculo" id="modo_calc_caixa" value="caixa" checked>
                                <label class="form-check-label" for="modo_calc_caixa">Informar Caixas (Calcula Peso)</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_calculo" id="modo_calc_peso" value="peso">
                                <label class="form-check-label" for="modo_calc_peso">Informar Peso Total (Calcula Caixas)</label>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Qtd. Volumes (Caixas) *</label>
                            <input type="number" class="form-control" id="input_qtd_caixas" name="qtd_caixas" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Peso Total (Kg) *</label>
                            <input type="text" class="form-control mask-peso-3" id="input_peso_total" name="peso_total" readonly required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Local (Endereço Câmara) *</label>
                            <select class="form-select select2-enderecos" name="endereco_id" style="width:100%" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Observações / Histórico</label>
                            <textarea class="form-control" name="observacao" rows="2" placeholder="Informações adicionais para auditoria..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-dark fw-bold" id="btn-salvar-legado">
                    <i class="fas fa-check-circle me-2"></i> Confirmar Importação
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-historico-legado" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Histórico de Importações (Legado)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-striped" id="tabela-historico-legado" style="width:100%">
                        <thead>
                            <tr>
                                <th class="text-center align-middle font-small" width="30px">Lote</th>
                                <th class="text-center align-middle font-small" width="200px">Produto</th>
                                <th class="text-center align-middle font-small" width="30px">Qtd</th>
                                <th class="text-center align-middle font-small" width="30px">Data Imp.</th>
                                <th class="text-center align-middle font-small" width="10px">Ação</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-picking-reprocesso" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-boxes"></i> Confirmar Retirada de Estoque</h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> O produto selecionado está disponível em múltiplos endereços.
                    Informe quantas caixas serão retiradas de cada local para totalizar <strong><span id="picking-total-alvo">0</span></strong> caixas.
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Endereço / Pallet</th>
                                <th class="text-center">Saldo Atual</th>
                                <th class="text-center" width="150px">Retirar (Qtd)</th>
                            </tr>
                        </thead>
                        <tbody id="lista-picking-enderecos">
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-end fw-bold">Total Selecionado:</td>
                                <td class="text-center fw-bold"><span id="picking-total-selecionado">0</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="picking-erro-msg" class="text-danger fw-bold text-center mt-2" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-picking">Confirmar e Salvar</button>
            </div>
        </div>
    </div>
</div>