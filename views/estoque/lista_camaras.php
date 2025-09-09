<?php // /views/estoque/lista_camaras.php ?>

<h4 class="fw-bold mb-3">Gerenciamento de Câmaras e Armazéns</h4>

<button class="btn btn-primary mb-3" id="btn-adicionar-camara">
    <i class="fas fa-plus me-2"></i> Adicionar Nova Câmara
</button>

<div class="table-responsive">
    <table id="tabela-camaras" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nome da Câmara</th>
                <th>Descrição</th>
                <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>