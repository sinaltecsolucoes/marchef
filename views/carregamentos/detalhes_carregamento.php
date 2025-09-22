<?php // /views/carregamentos/detalhes_carregamento.php ?>

<h4 class="fw-bold mb-3" id="main-title">Carregamento</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">1. Cabeçalho</h6>
        <div>
            <button class="btn btn-success btn-sm" id="btn-finalizar-detalhe" style="display: none;">
                <i class="fas fa-check-circle me-1"></i> Finalizar Carregamento
            </button>
            <button class="btn btn-primary btn-sm" id="btn-editar-header"><i class="fas fa-pencil-alt me-1"></i>
                Editar</button>
            <button class="btn btn-success btn-sm" id="btn-salvar-header" style="display: none;"><i
                    class="fas fa-save me-1"></i> Salvar</button>
            <button class="btn btn-secondary btn-sm" id="btn-cancelar-header" style="display: none;"><i
                    class="fas fa-times me-1"></i> Cancelar</button>
            <a href="index.php?page=carregamentos" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="card-body">
        <form id="form-carregamento-header">
            <input type="hidden" id="carregamento_id" name="car_id">
            <input type="hidden" id="oe_id_hidden" name="oe_id_hidden"> <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Nº Carregamento</label>
                    <input type="text" id="car_numero" name="car_numero" class="form-control" readonly>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Data</label>
                    <input type="date" id="car_data" name="car_data" class="form-control" readonly>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ordem de Expedição (Base)</label>
                    <input type="text" id="oe_numero_base" class="form-control" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Cliente Responsável</label>
                    <select id="car_entidade_id_organizador" name="car_entidade_id_organizador" class="form-select"
                        style="width: 100%;" disabled></select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Transportadora</label>
                    <select id="car_transportadora_id" name="car_transportadora_id" class="form-select"
                        style="width: 100%;" disabled></select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Motorista</label>
                    <input type="text" id="car_motorista_nome" name="car_motorista_nome" class="form-control" readonly>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">CPF</label>
                    <input type="text" id="car_motorista_cpf" name="car_motorista_cpf" class="form-control" readonly>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Placa(s)</label>
                    <input type="text" id="car_placas" name="car_placas" class="form-control" readonly>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Lacre(s)</label>
                    <input type="text" id="car_lacres" name="car_lacres" class="form-control" readonly>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">2. Planejamento (Gabarito da OE)</h6>
    </div>
    <div class="card-body">
        <p class="text-muted">Resumo dos itens planejados na Ordem de Expedição. Use esta tabela como referência para o
            carregamento.</p>
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="tabela-planejamento">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th>Produto</th>
                        <th>Lote</th>
                        <th>Endereço</th>
                        <th class="text-end">Qtd. Planejada (Caixas)</th>
                        <th class="text-end">Qtd. Carregada (Caixas)</th>
                        <th class="text-end fw-bold">Saldo (Caixas)</th>
                    </tr>
                </thead>
                <tbody id="tabela-planejamento-body">
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">3. Execução (Filas de Carregamento)</h6>
        <button class="btn btn-success btn-sm" id="btn-adicionar-fila">
            <i class="fas fa-plus me-1"></i> Adicionar Nova Fila
        </button>
    </div>
    <div class="card-body">
        <div id="filas-container">
        </div>
    </div>
</div>


<div class="modal fade" id="modal-add-item-cascata" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Item (Baseado na OE)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-add-item-cascata">
                <div class="modal-body">
                    <input type="hidden" id="cascata_fila_id" name="cascata_fila_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="mb-3">
                        <label for="cascata_cliente" class="form-label">1. Cliente (da OE)</label>
                        <select id="cascata_cliente" name="cascata_cliente_id" class="form-select" style="width: 100%;"
                            required></select>
                    </div>

                    <div class="mb-3">
                        <label for="cascata_produto" class="form-label">2. Produto (da OE)</label>
                        <select id="cascata_produto" name="cascata_produto_id" class="form-select" style="width: 100%;"
                            disabled required></select>
                    </div>

                    <div class="mb-3">
                        <label for="cascata_lote_endereco" class="form-label">3. Lote / Endereço (da OE)</label>
                        <select id="cascata_lote_endereco" name="cascata_alocacao_id" class="form-select"
                            style="width: 100%;" disabled required></select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Saldo Disponível (neste Lote/Endereço)</label>
                            <input type="text" id="cascata_saldo_display" class="form-control" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cascata_quantidade" class="form-label">4. Quantidade a Carregar</label>
                            <input type="number" id="cascata_quantidade" name="cascata_quantidade" class="form-control"
                                step="1" min="1" disabled required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btn-confirmar-add-item-cascata" class="btn btn-primary" disabled>Adicionar
                        Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-adicionar-divergencia" tabindex="-1" aria-hidden="true">
</div>

<div class="modal fade" id="modal-editar-item" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Quantidade do Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-editar-item">
                <div class="modal-body">
                    <input type="hidden" id="edit_car_item_id" name="car_item_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="mb-3">
                        <label class="form-label">Produto</label>
                        <p id="edit-produto-nome" class="form-control-plaintext bg-light p-2 rounded"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lote / Endereço</label>
                        <p id="edit-lote-endereco" class="form-control-plaintext bg-light p-2 rounded"></p>
                    </div>
                    <div class="mb-3">
                        <label for="edit_quantidade" class="form-label">Nova Quantidade</label>
                        <input type="number" class="form-control" id="edit_quantidade" name="edit_quantidade" step="1"
                            min="1" required>
                        <div id="edit-saldo-info" class="form-text"></div>
                        <div class="invalid-feedback">A quantidade excede o saldo disponível!</div>
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

<div class="modal fade" id="modal-adicionar-divergencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Item por Divergência (Fora da OE)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="form-add-divergencia">
                <div class="modal-body">
                    <input type="hidden" id="div_fila_id" name="div_fila_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Você está adicionando um item que não foi planejado na Ordem de
                        Expedição.
                        O motivo é obrigatório.
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="div_cliente_id" class="form-label">1. Cliente <span
                                    class="text-danger">*</span></label>
                            <select id="div_cliente_id" name="div_cliente_id" class="form-select" style="width: 100%;"
                                required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="div_motivo" class="form-label">2. Motivo da Divergência <span
                                    class="text-danger">*</span></label>
                            <input type="text" id="div_motivo" name="div_motivo" class="form-control" required>
                        </div>
                    </div>

                    <hr class="my-3">

                    <p class="fw-bold">3. Seleção de Estoque Disponível</p>

                    <div class="mb-3">
                        <label for="div_produto_estoque" class="form-label">Produto</label>
                        <select id="div_produto_estoque" class="form-select" style="width: 100%;" required></select>
                    </div>
                    <div class="mb-3">
                        <label for="div_lote_estoque" class="form-label">Lote</label>
                        <select id="div_lote_estoque" class="form-select" style="width: 100%;" disabled
                            required></select>
                    </div>
                    <div class="mb-3">
                        <label for="div_endereco_estoque" class="form-label">Endereço</label>
                        <select id="div_endereco_estoque" name="div_alocacao_id" class="form-select"
                            style="width: 100%;" disabled required></select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="div_saldo_display" class="form-label">Saldo Físico Disponível</label>
                            <input type="text" id="div_saldo_display" class="form-control" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="div_quantidade" class="form-label">Quantidade a Adicionar</label>
                            <input type="number" id="div_quantidade" name="div_quantidade" class="form-control" step="1"
                                min="1" disabled required>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btn-confirmar-add-divergencia" class="btn btn-primary" disabled>Adicionar
                        Item</button>
                </div>
            </form>
        </div>
    </div>
</div>