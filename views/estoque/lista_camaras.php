<?php // /views/estoque/lista_camaras.php ?>

<h4 class="fw-bold mb-3">Gestão de Câmaras</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <p>Gerencie todas as câmaras e armazéns</p>
                <button class="btn btn-primary" id="btn-adicionar-camara">
                    <i class="fas fa-plus me-2"></i> Adicionar Nova Câmara
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Câmaras</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-camaras" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Código</th>
                        <th class="text-center align-middle">Nome da Câmara</th>
                        <th class="text-center align-middle">Descrição</th>
                        <th class="text-center align-middle">Indústria (Unidade de Origem)</th>
                        <th class="text-center align-middle">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-camara" tabindex="-1" aria-labelledby="modal-camara-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-camara-label">Adicionar Nova Câmara</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-camara">
                <div class="modal-body">
                    <input type="hidden" id="camara_id" name="camara_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="mb-3">
                        <label for="camara_codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="camara_codigo" name="camara_codigo" required>
                    </div>
                    <div class="mb-3">
                        <label for="camara_nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="camara_nome" name="camara_nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="camara_descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="camara_descricao" name="camara_descricao"
                            rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="camara_industria" class="form-label">Indústria (Unidade de Origem)</label>
                        <input type="text" class="form-control" id="camara_industria" name="camara_industria"
                            placeholder="Ex: Matriz, Filial Natal, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Fechar</button>
                </div>
            </form>
        </div>
    </div>
</div>