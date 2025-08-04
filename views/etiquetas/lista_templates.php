<?php
// /views/etiquetas/lista_templates.php
?>

<h4 class="fw-bold mb-3">Gerenciamento de Templates de Etiqueta</h4>

<button class="btn btn-primary mb-3" id="btn-adicionar-template">
    Adicionar Novo Template
</button>

<div id="feedback-message-area" class="mt-3"></div>

<div class="table-responsive">
    <table id="tabela-templates" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th>Nome do Template</th>
                <th>Descrição</th>
                <th>Data de Criação</th>
                <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modal-template" tabindex="-1" aria-labelledby="modal-template-label" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-template-label">Adicionar Novo Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-template">
                <div class="modal-body">
                    <input type="hidden" id="template_id" name="template_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div id="mensagem-template-modal" class="mb-3"></div>

                    <div class="mb-3">
                        <label for="template_nome" class="form-label">Nome do Template <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="template_nome" name="template_nome" required>
                    </div>

                    <div class="mb-3">
                        <label for="template_descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="template_descricao" name="template_descricao" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="template_conteudo_zpl" class="form-label">Código ZPL <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="template_conteudo_zpl" name="template_conteudo_zpl" rows="15" placeholder="Cole aqui o código ZPL completo gerado pelo Zebra Designer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary">Salvar Template</button>
                </div>
            </form>
        </div>
    </div>
</div>