<?php // /views/estoque/lista_enderecos.php ?>

<h4 class="fw-bold mb-3">Gestão de Endereços de Estoque</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <div class="d-flex align-items-end gap-2">
                    <div>
                        <label for="select-camara-filtro" class="form-label">Selecione uma Câmara para gerenciar os
                            endereços:</label>
                        <select id="select-camara-filtro" class="form-select"></select>
                    </div>
                    <div>
                        <button class="btn btn-primary" id="btn-adicionar-endereco" disabled>
                            <i class="fas fa-plus me-2"></i> Adicionar Novo Endereço
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Endereços</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-enderecos" class="table table-bordered table-hover" style="width:100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Endereço Completo</th>
                        <th class="text-center align-middle">Lado</th>
                        <th class="text-center align-middle">Nível</th>
                        <th class="text-center align-middle">Fila</th>
                        <th class="text-center align-middle">Vaga</th>
                        <th class="text-center align-middle">Simples</th>
                        <th class="text-center align-middle">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>


<div class="modal fade" id="modal-endereco" tabindex="-1" aria-labelledby="modal-endereco-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-endereco-label">Adicionar Novo Endereço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-endereco">
                <div class="modal-body">
                    <input type="hidden" id="endereco_id" name="endereco_id">
                    <input type="hidden" id="endereco_camara_id" name="endereco_camara_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div id="hierarquia-group">
                        <p class="text-muted">Preencha a hierarquia do endereço (ex: estacionamento).</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="lado" class="form-label">Lado</label>
                                <input type="text" class="form-control" id="lado" name="lado" placeholder="Ex: LE, LD">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nivel" class="form-label">Nível</label>
                                <input type="text" class="form-control" id="nivel" name="nivel"
                                    placeholder="Ex: NV1, NV2">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fila" class="form-label">Fila</label>
                                <input type="text" class="form-control" id="fila" name="fila"
                                    placeholder="Ex: FL01, FL20">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vaga" class="form-label">Vaga</label>
                                <input type="text" class="form-control" id="vaga" name="vaga" placeholder="Ex: A, B">
                            </div>
                        </div>
                    </div>

                    <div class="text-center my-2"><strong>OU</strong></div>

                    <div id="simples-group">
                        <label for="descricao_simples" class="form-label">Descrição Simples</label>
                        <input type="text" class="form-control" id="descricao_simples" name="descricao_simples"
                            placeholder="Ex: CONT, CORREDOR">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>