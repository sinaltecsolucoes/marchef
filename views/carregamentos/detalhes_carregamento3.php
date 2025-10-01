<?php
// /views/carregamentos/detalhes_carregamento.php
?>
<script>
    // Passa os dados do carregamento do PHP para uma variável JavaScript global
    const carregamentoData = <?php echo json_encode($carregamentoData ?? null); ?>;
    const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';
</script>
<div class="card-body card-custom">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div id="dados-carregamento-header">
            <h4 class="fw-bold mb-1">Carregamento Nº <span id="car-numero-detalhe">...</span></h4>
            <p class="text-muted mb-0">Status: <span id="car-status-detalhe" class="badge bg-secondary">...</span></p>
        </div>
        <a href="index.php?page=carregamentos" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Voltar para a Listagem
        </a>
    </div>
    <hr>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Composição do Carregamento</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <button class="btn btn-primary" id="btn-adicionar-fila">
                <i class="fas fa-plus me-2"></i> Adicionar Fila
            </button>
        </div>

        <div id="filas-container">
        </div>
    </div>
</div>


<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Finalizar Carregamento</h6>
    </div>
    <div class="card-body">
        <p>Após adicionar todas as filas e itens, clique no botão abaixo para rever e confirmar a saída do estoque.</p>
        <button id="btn-abrir-conferencia" class="btn btn-success btn-lg">
            <i class="fas fa-check-double me-2"></i> Conferir e Finalizar Carregamento
        </button>
    </div>
</div>

<div class="modal fade" id="modal-adicionar-fila" tabindex="-1" aria-labelledby="modalFilaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFilaLabel">Adicionar Nova Fila de Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-adicionar-fila">
                <div class="modal-body">
                    <div id="mensagem-adicionar-fila-modal" class="mb-3"></div>
                    <p>Selecione o cliente para criar uma nova fila de entrega dentro deste carregamento.</p>
                    <div class="mb-3">
                        <label for="select-cliente-para-fila" class="form-label">Cliente Comprador <span
                                class="text-danger">*</span></label>
                        <select id="select-cliente-para-fila" class="form-select" style="width: 100%;"
                            required></select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Adicionar Cliente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-conferencia-final" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Conferir e Confirmar Saída de Estoque</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Por favor, confira os itens e quantidades abaixo antes de confirmar a baixa no estoque. Esta ação é
                    irreversível.</p>

                <div id="aviso-discrepancia-estoque" class="mb-3"></div>

                <div class="table-responsive">
                    <table class="table table-bordered" id="tabela-resumo-conferencia">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Lote</th>
                                <th class="text-end">Qtd. no Carregamento (Unidades)</th>
                                <th class="text-end">Qtd. em Estoque (Unidades)</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-resumo-conferencia">
                        </tbody>
                    </table>
                </div>

                <div class="form-check form-switch my-3 d-none" id="container-forcar-baixa">
                    <input class="form-check-input" type="checkbox" id="forcar-baixa-estoque">
                    <label class="form-check-label fw-bold text-danger" for="forcar-baixa-estoque">
                        Confirmar e forçar a baixa, mesmo com estoque insuficiente (irá gerar estoque negativo).
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="button" class="btn btn-success" id="btn-confirmar-baixa-estoque">
                    <i class="fas fa-truck-loading me-2"></i> Confirmar e Dar Baixa no Estoque
                </button>
            </div>
        </div>
    </div>
</div>