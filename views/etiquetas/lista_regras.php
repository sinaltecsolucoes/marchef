<?php // /views/etiquetas/lista_regras.php ?>

<h4 class="fw-bold mb-3">Gestão de Regras de Etiqueta</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <p>Gerencie todas as Regras de Etiquetas</p>
                <button class="btn btn-primary" id="btn-adicionar-regra">
                    <i class="fas fa-plus me-2"></i> Adicionar Nova Regra
                </button>
            </div>
        </div>
    </div>
</div>

<div id="feedback-message-area" class="mt-3"></div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Regras</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-regras" class="table table-hover my-4" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Cliente</th>
                        <th class="text-center align-middle">Produto</th>
                        <th class="text-center align-middle">Template Aplicado</th>
                        <th class="text-center align-middle">Prioridade</th>
                        <th class="text-center align-middle" width="20%">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-regra" tabindex="-1" aria-labelledby="modal-regra-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-regra-label">Adicionar Nova Regra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-regra">
                <div class="modal-body">
                    <input type="hidden" id="regra_id" name="regra_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <div id="mensagem-regra-modal" class="mb-3"></div>

                    <p class="text-muted">Deixe um campo como "Todos" para criar uma regra mais genérica. Uma
                        regra com Cliente e Produto definidos terá prioridade sobre uma regra mais geral.</p>

                    <div class="mb-3">
                        <label for="regra_cliente_id" class="form-label">Cliente</label>
                        <select class="form-select" id="regra_cliente_id" name="regra_cliente_id"
                            style="width:100%;"></select>
                    </div>
                    <div class="mb-3">
                        <label for="regra_produto_id" class="form-label">Produto</label>
                        <select class="form-select" id="regra_produto_id" name="regra_produto_id"
                            style="width:100%;"></select>
                    </div>
                    <div class="mb-3">
                        <label for="regra_template_id" class="form-label">Aplicar o Template <span
                                class="text-danger">*</span></label>
                        <select class="form-select" id="regra_template_id" name="regra_template_id" style="width:100%;"
                            required></select>
                    </div>
                    <div class="mb-3">
                        <label for="regra_prioridade" class="form-label">Prioridade</label>
                        <input type="number" class="form-control" id="regra_prioridade" name="regra_prioridade"
                            value="10">
                        <small class="form-text text-muted">Quanto menor o número, maior a prioridade.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar Regra</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Fechar</button>
                </div>
            </form>
        </div>
    </div>
</div>