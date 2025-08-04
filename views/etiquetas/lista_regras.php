<?php // /views/etiquetas/lista_regras.php ?>

<h4 class="fw-bold mb-3">Gerenciamento de Regras de Etiqueta</h4>

<button class="btn btn-primary mb-3" id="btn-adicionar-regra">
    Adicionar Nova Regra
</button>

<div id="feedback-message-area" class="mt-3"></div>

<div class="table-responsive">
    <table id="tabela-regras" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Produto</th>
                <th>Template Aplicado</th>
                <th class="text-center">Prioridade</th>
                <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
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
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <div id="mensagem-regra-modal" class="mb-3"></div>

                    <p class="text-muted">Deixe um campo como "Todos" para criar uma regra mais genérica. Uma regra com Cliente e Produto definidos terá prioridade sobre uma regra mais geral.</p>

                    <div class="mb-3">
                        <label for="regra_cliente_id" class="form-label">Cliente</label>
                        <select class="form-select" id="regra_cliente_id" name="regra_cliente_id" style="width:100%;"></select>
                    </div>
                    <div class="mb-3">
                        <label for="regra_produto_id" class="form-label">Produto</label>
                        <select class="form-select" id="regra_produto_id" name="regra_produto_id" style="width:100%;"></select>
                    </div>
                    <div class="mb-3">
                        <label for="regra_template_id" class="form-label">Aplicar o Template <span class="text-danger">*</span></label>
                        <select class="form-select" id="regra_template_id" name="regra_template_id" style="width:100%;" required></select>
                    </div>
                    <div class="mb-3">
                        <label for="regra_prioridade" class="form-label">Prioridade</label>
                        <input type="number" class="form-control" id="regra_prioridade" name="regra_prioridade" value="10">
                        <small class="form-text text-muted">Quanto menor o número, maior a prioridade.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary">Salvar Regra</button>
                </div>
            </form>
        </div>
    </div>
</div>