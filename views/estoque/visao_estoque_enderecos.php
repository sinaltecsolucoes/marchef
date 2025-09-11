<?php // /views/estoque/visao_estoque_enderecos.php ?>

<h4 class="fw-bold mb-3">Visão de Estoque por Endereços</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-body">
        <p>Clique no ícone <i class="fas fa-plus-square text-primary"></i> para expandir uma câmara e visualizar seus
            endereços e itens alocados.</p>
        <div id="tree-container" class="mt-3">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p>Carregando dados do estoque...</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-alocar-item" tabindex="-1" aria-labelledby="modal-alocar-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-alocar-label">Alocar Item no Endereço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-alocar-item">
                <div class="modal-body">
                    <input type="hidden" id="alocar_endereco_id" name="endereco_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <p>Endereço de destino: <strong id="alocar-endereco-nome" class="text-primary"></strong></p>

                    <div class="mb-3">
                        <label for="select-item-para-alocar" class="form-label">Item Disponível (Lote
                            Finalizado)</label>
                        <select id="select-item-para-alocar" name="lote_item_id" class="form-select"
                            style="width: 100%;" required></select>
                    </div>

                    <div class="mb-3">
                        <label for="alocar_quantidade" class="form-label">Quantidade a Alocar</label>
                        <input type="number" class="form-control" id="alocar_quantidade" name="quantidade" step="0.001"
                            min="0.001" required>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar Alocação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-reserva-detalhes" tabindex="-1" aria-labelledby="modal-reserva-label"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-reserva-label">Detalhes da Reserva de Estoque</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="reserva-detalhes-container">
                    <p class="text-center">Carregando detalhes...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>