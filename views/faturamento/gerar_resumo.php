<?php // /views/faturamento/gerar_resumo.php ?>

<h4 class="fw-bold mb-3">Gerar Resumo para Faturamento</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">1. Selecionar Ordem de Expedição</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <label for="select-ordem-expedicao" class="form-label">Selecione uma Ordem de Expedição para
                    processar:</label>
                <select id="select-ordem-expedicao" class="form-select"></select>
            </div>
            <div class="col-md-6 align-self-end text-end" id="container-btn-gerar" style="display: none;">
                <button class="btn btn-success" id="btn-gerar-resumo">
                    <i class="fas fa-check me-2"></i> Confirmar e Gerar Resumo
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">2. Resumo Agrupado</h6>
    </div>
    <div class="card-body">
        <div id="faturamento-resultado-container">
            <p class="text-muted text-center">Selecione uma Ordem de Expedição acima para começar.</p>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-editar-faturamento" tabindex="-1" aria-labelledby="modal-editar-faturamento-label"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-editar-faturamento-label">Adicionar Preço e Observação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-editar-faturamento">
                <div class="modal-body">
                    <input type="hidden" id="edit_fati_id" name="fati_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <p class="text-muted"><strong>Produto:</strong> <span id="display-produto" class="text-dark"></span>
                    </p>
                    <p class="text-muted"><strong>Lote:</strong> <span id="display-lote" class="text-dark"></span></p>
                    <hr>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_fati_preco_unitario" class="form-label">Preço Unitário</label>
                            <input type="number" class="form-control" id="edit_fati_preco_unitario"
                                name="fati_preco_unitario" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_fati_preco_unidade_medida" class="form-label">Unidade de Medida</label>
                            <select class="form-select" id="edit_fati_preco_unidade_medida"
                                name="fati_preco_unidade_medida">
                                <option value="KG">por KG</option>
                                <option value="CX">por Caixa</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_fati_observacao" class="form-label">Observação do Faturamento</label>
                        <textarea class="form-control" id="edit_fati_observacao" name="fati_observacao"
                            rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>