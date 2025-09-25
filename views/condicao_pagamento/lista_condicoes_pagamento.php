<?php // /views/cadastros/lista_condicoes_pagamento.php ?>

<h4 class="fw-bold mb-3">Gestão de Condições de Pagamento</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <p>Gerencie todas as condições de pagamento</p>
                <button class="btn btn-primary" id="btn-adicionar-condicao">
                    <i class="fas fa-plus me-2"></i> Adicionar Nova Condição
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow card-custom">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-condicoes" class="table table-hover table-bordered my-4" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Status</th>
                        <th class="text-center align-middle">Código</th>
                        <th class="text-center align-middle">Descrição</th>
                        <th class="text-center align-middle">Dias/Parcelas</th>
                        <th class="text-center align-middle">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-condicao" tabindex="-1" aria-labelledby="modal-condicao-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-condicao-label">Adicionar Condição</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-condicao">
                <div class="modal-body">
                    <input type="hidden" id="cond_id" name="cond_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="mb-3">
                        <label for="cond_codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cond_codigo" name="cond_codigo"
                            placeholder="Ex: Ex: 001, 002 ou A VISTA" required>
                    </div>
                    <div class="mb-3">
                        <label for="cond_descricao" class="form-label">Descrição <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cond_descricao" name="cond_descricao"
                            placeholder="Ex: Ex: 30/60 DIAS, 15 DIAS LIQUIDO" required>
                    </div>
                    <div class="mb-3">
                        <label for="cond_dias_parcelas" class="form-label">Dias (Ex: 15, 30,60,90)</label>
                        <input type="text" class="form-control" id="cond_dias_parcelas" name="cond_dias_parcelas"
                            placeholder="Ex: 30,60,90">
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="cond_ativo" name="cond_ativo"
                            value="1" checked>
                        <label class="form-check-label" for="cond_ativo">Ativo</label>
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