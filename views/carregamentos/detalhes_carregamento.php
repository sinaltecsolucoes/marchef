<?php // /views/carregamentos/detalhes_carregamento.php ?>

<h4 class="fw-bold mb-3" id="main-title">Carregamento</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">1. Cabeçalho</h6>
        <div>
            <button class="btn btn-success btn-sm" id="btn-finalizar-detalhe" style="display: none;">
                <i class="fas fa-check-circle me-1"></i> Finalizar Carregamento
            </button>
            <button class="btn btn-warning btn-sm" id="btn-editar-header"><i class="fas fa-pencil-alt me-1"></i>
                Editar</button>
            <button class="btn btn-success btn-sm" id="btn-salvar-header" style="display: none;"><i
                    class="fas fa-save me-1"></i> Salvar</button>
            <button class="btn btn-secondary btn-sm" id="btn-cancelar-header" style="display: none;"><i
                    class="fas fa-times me-1"></i> Cancelar</button>
            <a href="index.php?page=carregamentos" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Voltar para a Lista
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

<div class="modal fade" id="modal-adicionar-item-carregamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Item à Fila</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="form-adicionar-item">
                <div class="modal-body">
                    <input type="hidden" id="item_fila_id" name="item_fila_id">
                    <input type="hidden" id="item_oei_id_origem" name="item_oei_id_origem">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="mb-3">
                        <label for="item_cliente_id" class="form-label">1. Cliente <span
                                class="text-danger">*</span></label>
                        <select id="item_cliente_id" name="item_cliente_id" class="form-select" style="width: 100%;"
                            required></select>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="item_produto_id" class="form-label">2. Produto <span
                                    class="text-danger">*</span></label>
                            <select id="item_produto_id" name="item_produto_id" class="form-select" style="width: 100%;"
                                disabled required></select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="item_lote_id" class="form-label">3. Lote <span
                                    class="text-danger">*</span></label>
                            <select id="item_lote_id" name="item_lote_id" class="form-select" style="width: 100%;"
                                disabled required></select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="item_alocacao_id" class="form-label">4. Endereço <span
                                    class="text-danger">*</span></label>
                            <select id="item_alocacao_id" name="item_alocacao_id" class="form-select"
                                style="width: 100%;" disabled required></select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="item_saldo_display" class="form-label">Saldo Disponível</label>
                            <input type="text" id="item_saldo_display" class="form-control" readonly>
                            <div id="item_helper_text" class="form-text"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="item_quantidade" class="form-label">5. Quantidade a Carregar <span
                                    class="text-danger">*</span></label>
                            <input type="number" id="item_quantidade" name="item_quantidade" class="form-control"
                                step="1" min="1" disabled required>
                        </div>
                    </div>

                    <div class="mb-3" id="container-motivo-divergencia" style="display: none;">
                        <label for="item_motivo_divergencia" class="form-label">6. Motivo da Divergência <span
                                class="text-danger">*</span></label>
                        <input type="text" id="item_motivo_divergencia" name="item_motivo_divergencia"
                            class="form-control" placeholder="Item não planejado na OE. Descreva o motivo.">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btn-confirmar-add-item" class="btn btn-primary" disabled>Adicionar
                        Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-adicionar-foto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Foto à Fila</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-adicionar-foto" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="foto_fila_id" name="foto_fila_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="mb-3">
                        <label for="foto_upload" class="form-label">Selecionar Imagem(ns) (JPG, PNG)</label>
                        <input class="form-control" type="file" id="foto_upload" name="foto_upload[]"
                            accept="image/jpeg, image/png" required multiple>
                    </div>

                    <div class="mb-3" id="foto-preview-container" style="display: none;">
                        <label class="form-label">Pré-visualização:</label>
                        <img id="foto-preview" src="#" alt="Preview" class="img-fluid rounded" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Foto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-visualizar-fotos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visualizar-fotos-titulo">Fotos da Fila</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="fotos-preview-container" class="row">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-conferencia-finalizacao" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conferência para Finalização</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Por favor, revise o resumo abaixo. Ao confirmar, o estoque será baixado e o carregamento finalizado.
                </p>
                <div id="resumo-finalizacao-container">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-confirmar-finalizacao-real">
                    <i class="fas fa-check-circle me-1"></i> Confirmar e Finalizar
                </button>
            </div>
        </div>
    </div>
</div>