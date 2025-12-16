<?php // /views/estoque/visao_estoque_enderecos.php 
?>

<h4 class="fw-bold mb-3">Visão de Estoque por Endereços</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-body">

        <!-- NOVO CAMPO DE PESQUISA -->
        <div class="mb-4">
            <label for="input-search-estoque" class="form-label fw-bold"><i class="fas fa-search me-1"></i> Pesquisar
                Estoque por Item</label>
            <div class="input-group">
                <input type="text" class="form-control" id="input-search-estoque"
                    placeholder="Digite a descrição do produto ou número do lote...">
                <button class="btn btn-primary" type="button" id="btn-search-estoque">Buscar</button>
                <button class="btn btn-secondary" type="button" id="btn-clear-search" style="display: none;"><i
                        class="fas fa-times me-1"></i> Limpar</button>
            </div>
            <small class="form-text text-muted">A pesquisa irá exibir apenas a câmara e endereço onde o item for
                encontrado.</small>
        </div>
        <!-- FIM DO CAMPO DE PESQUISA -->

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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Confirmar
                        Alocação</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                            class="fas fa-times me-2"></i>Cancelar</button>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                        class="fas fa-times me-2"></i>Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-transferir-item" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>Transferir Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-transferir-item">
                <div class="modal-body">
                    <input type="hidden" id="transf_alocacao_id" name="alocacao_origem_id">

                    <div class="alert alert-light border mb-3">
                        <strong>Produto:</strong> <span id="transf_produto_nome"></span><br>
                        <strong>Lote:</strong> <span id="transf_lote"></span><br>
                        <strong>Origem Atual:</strong> <span id="transf_origem_nome"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Para qual endereço?</label>
                        <select class="form-select" id="select-endereco-destino" name="endereco_destino_id" style="width: 100%;" required>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Quantidade a Mover</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="transf_quantidade" name="quantidade" step="0.001" min="0.001" required>
                            <span class="input-group-text">cx/unid</span>
                        </div>
                        <div class="form-text">Máximo disponível: <span id="transf_max_qtd"></span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold">Confirmar Transferência</button>
                </div>
            </form>
        </div>
    </div>
</div>