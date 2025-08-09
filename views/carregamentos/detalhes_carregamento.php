<?php
// /views/carregamentos/detalhes_carregamento.php
?>

<h4 class="fw-bold mb-3">Detalhe Carregamento</h4>

<script>
    // Passa os dados do carregamento do PHP para uma variável JavaScript global
    const carregamentoData = <?php echo json_encode($carregamentoData ?? null); ?>;
    const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';
</script>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Dados Carregamento</h6>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div id="dados-carregamento-header">
                <h4 class="fw-bold mb-1">Carregamento Nº <span id="car-numero-detalhe">...</span></h4>
                <p class="text-muted mb-0">Status: <span id="car-status-detalhe" class="badge bg-secondary">...</span>
                </p>
            </div>
            <a href="index.php?page=carregamentos" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Voltar para a Listagem
            </a>
        </div>
    </div>
</div>


<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Composição do Carregamento</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-gerenciar-fila">
                <i class="fas fa-plus me-2"></i> Adicionar Nova Fila
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered" id="tabela-composicao-carregamento">
                <thead class="table-light">
                    <tr>
                        <th>Fila</th>
                        <th class="text-center" style="width: 120px;">Ações</th>
                        <th>Cliente</th>
                        <th>Produto</th>
                        <th class="text-end">Quantidade</th>
                    </tr>
                </thead>
                <tbody id="tbody-composicao-carregamento">
                    <tr>
                        <td colspan="5" class="text-center text-muted">Nenhuma fila adicionada.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
</div>


<div class="modal fade" id="modal-gerenciar-fila" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gerenciar Fila Nº <span id="numero-fila-modal">1</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>


            <div class="modal-body">

                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title">Passo 1: Adicionar Clientes à Fila</h6>
                        <div class="row align-items-end g-3">
                            <div class="col-md-8">
                                <label for="select-cliente-para-fila" class="form-label">Selecione o Cliente</label>
                                <select id="select-cliente-para-fila" class="form-select" style="width: 100%;"></select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-info w-100" id="btn-adicionar-cliente-a-fila">
                                    <i class="fas fa-user-plus me-2"></i> Adicionar Cliente
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <h6 class="mb-3">Passo 2: Adicionar Produtos para cada Cliente</h6>

                <div class="clientes-container-scroll">
                    <div id="clientes-e-produtos-container-modal">
                        <p class="text-muted">Nenhum cliente adicionado a esta fila.</p>
                    </div>
                </div>
            </div>

            <style>
                .clientes-container-scroll {
                    max-height: 45vh;
                    /* Define uma altura máxima (45% da altura da tela) */
                    overflow-y: auto;
                    /* Adiciona a barra de rolagem vertical apenas quando necessário */
                    padding: 5px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                }
            </style>


            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-salvar-e-fechar-fila">
                    <i class="fas fa-check me-2"></i> Concluir e Adicionar Fila ao Carregamento
                </button>
            </div>
        </div>
    </div>
</div>

<template id="template-card-cliente-modal">
    <div class="card mb-3 card-cliente-na-fila">
        <div class="card-header d-flex justify-content-between">
            <h6 class="m-0 nome-cliente-card"></h6>
            <button type="button" class="btn-close btn-remover-cliente-da-fila"></button>
        </div>
        <div class="card-body">
            <form class="form-adicionar-produto-ao-cliente">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6"><label class="form-label">Produto</label><select
                            class="form-select select-produto-estoque" style="width: 100%;"></select></div>
                    <div class="col-md-3"><label class="form-label">Quantidade</label><input type="number"
                            class="form-control" min="1" step="1"></div>
                    <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Adicionar Produto</button>
                    </div>
                </div>
            </form>
            <hr>

            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
                    <thead>
                        <tr>
                            <th>PRODUTO</th>
                            <th class="text-end">QUANT.</th>
                            <th class="text-center">AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody class="lista-produtos-cliente">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>