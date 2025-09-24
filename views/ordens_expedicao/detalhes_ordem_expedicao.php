<?php // /views/ordens_expedicao/detalhes_ordem_expedicao.php ?>

<h4 class="fw-bold mb-3" id="main-title">Nova Ordem de Expedição</h4>


<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">1. Cabeçalho</h6>

        <div id="botoes-cabecalho-oe" class="btn-group">
            <a href="index.php?page=ordens_expedicao" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Voltar para a Lista
            </a>
        </div>
    </div>
    <div class="card-body">
        <form id="form-ordem-header">
            <input type="hidden" id="ordem_id" name="oe_id">
            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="oe_numero" class="form-label">Nº da Ordem de Expedição</label>
                    <input type="text" class="form-control" id="oe_numero" name="oe_numero">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="oe_data" class="form-label">Data</label>
                    <input type="date" class="form-control" id="oe_data" name="oe_data"
                        value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="col-md-2 mb-3">
                    <label class="form-label">Total Caixas (Geral)</label>
                    <input type="text" id="total-caixas-geral" class="form-control text-end fw-bold" value="0" readonly>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Total Quilos (Geral)</label>
                    <input type="text" id="total-quilos-geral" class="form-control text-end fw-bold" value="0.000"
                        readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Destinos (UF)</label>
                    <input type="text" id="destinos-geral" class="form-control fw-bold" value="-" readonly>
                </div>
            </div>
            <button type="submit" id="btn-salvar-header" class="btn btn-primary" style="display: none;">Salvar
                Cabeçalho
            </button>
        </form>
    </div>
    </div>

    <div id="section-details" class="card shadow mb-4 card-custom" style="display: none;">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">2. Detalhes (Pedidos e Itens)</h6>
            <button class="btn btn-success btn-sm" id="btn-adicionar-pedido-cliente">
                <i class="fas fa-plus me-1"></i> Adicionar Pedido/Cliente
            </button>
        </div>
        <div class="card-body">
            <div id="pedidos-container">
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-pedido-cliente" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Pedido de Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="form-pedido-cliente">
                    <div class="modal-body">
                        <input type="hidden" name="oep_ordem_id" id="oep_ordem_id">
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                        <div class="mb-3">
                            <label for="oep_cliente_id" class="form-label">Cliente <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="oep_cliente_id" name="oep_cliente_id" style="width: 100%;"
                                required></select>
                        </div>
                        <div class="mb-3">
                            <label for="oep_numero_pedido" class="form-label">Nº do Pedido do Cliente *</label>
                            <input type="text" class="form-control" id="oep_numero_pedido" name="oep_numero_pedido"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-selecao-estoque" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Produto do Estoque</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="hidden_oep_id">

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="select-produto-estoque" class="form-label">1. Selecione o Produto</label>
                            <select id="select-produto-estoque" class="form-select" style="width: 100%;"></select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="select-lote-estoque" class="form-label">2. Selecione o Lote</label>
                            <select id="select-lote-estoque" class="form-select" style="width: 100%;" disabled></select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="select-endereco-estoque" class="form-label">3. Selecione o Endereço</label>
                            <select id="select-endereco-estoque" name="oei_alocacao_id" class="form-select"
                                style="width: 100%;" disabled></select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="saldo-disponivel-display" class="form-label">Saldo Disponível</label>
                                <input type="text" id="saldo-disponivel-display" class="form-control" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="oei_quantidade" class="form-label">4. Quantidade a Adicionar</label>
                                <input type="number" id="oei_quantidade" name="oei_quantidade" class="form-control"
                                    step="1" min="1" disabled>

                                <div class="invalid-feedback">A quantidade excede o saldo disponível neste endereço!
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label for="oei_observacao" class="form-label">Observação</label>
                            <input type="text" id="oei_observacao" name="oei_observacao" class="form-control">
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-confirmar-add-item" class="btn btn-primary" disabled>Adicionar Item ao
                        Pedido</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-editar-item" tabindex="-1" aria-labelledby="modal-editar-item-label"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-editar-item-label">Editar Quantidade do Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="form-editar-item">
                    <div class="modal-body">
                        <input type="hidden" id="edit_oei_id" name="oei_id">
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                        <div class="mb-3">
                            <label class="form-label">Produto</label>
                            <p id="edit-produto-nome" class="form-control-plaintext bg-light p-2 rounded"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit_oei_quantidade" class="form-label">Nova Quantidade</label>
                            <input type="number" class="form-control" id="edit_oei_quantidade" name="oei_quantidade"
                                step="1" min="1" required>
                            <div id="edit-saldo-info" class="form-text"></div>
                            <div class="invalid-feedback">A quantidade excede o saldo disponível!</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_oei_observacao" class="form-label">Observação</label>
                            <input type="text" class="form-control" id="edit_oei_observacao" name="oei_observacao">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>