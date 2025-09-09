<?php // /views/estoque/lista_enderecos.php ?>

<h4 class="fw-bold mb-3">Gerenciamento de Endereços de Estoque</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header">
        <div class="row">
            <div class="col-md-6">
                <label for="select-camara-filtro" class="form-label">Selecione uma Câmara para gerenciar os
                    endereços:</label>
                <select id="select-camara-filtro" class="form-select"></select>
            </div>
        </div>
    </div>
    <div class="card-body">
        <button class="btn btn-primary mb-3" id="btn-adicionar-endereco" disabled>
            <i class="fas fa-plus me-2"></i> Adicionar Novo Endereço
        </button>

        <div class="table-responsive">
            <table id="tabela-enderecos" class="table table-hover my-4" style="width:100%">
                <thead>
                    <tr>
                        <th>Endereço Completo</th>
                        <th>Lado</th>
                        <th>Nível</th>
                        <th>Fila</th>
                        <th>Vaga</th>
                        <th>Simples</th>
                        <th class="text-center">Ações</th>
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