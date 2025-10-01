<?php
// /views/etiquetas/lista_templates.php
?>

<h4 class="fw-bold mb-3">Gestão de Templates de Etiqueta</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <p>Gerencie todos os templates de etiquetas</p>
                <button class="btn btn-primary" id="btn-adicionar-template">
                    <i class="fas fa-plus me-2"></i> Adicionar Novo Template
                </button>
            </div>
        </div>
    </div>
</div>

<div id="feedback-message-area" class="mt-3"></div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Templates</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-templates" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Nome do Template</th>
                        <th class="text-center align-middle">Descrição</th>
                        <th class="text-center align-middle">Data de Criação</th>
                        <th class="text-center align-middle">Ações</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-template" tabindex="-1" aria-labelledby="modal-template-label" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-template-label">Adicionar Novo Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-template" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="template_id" name="template_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div id="mensagem-template-modal" class="mb-3"></div>

                    <div class="mb-3">
                        <label for="template_nome" class="form-label">Nome do Template <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="template_nome" name="template_nome" required>
                    </div>

                    <div class="mb-3">
                        <label for="template_descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="template_descricao" name="template_descricao"
                            rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="zpl_file_upload" class="form-label">Ou Carregar de um Arquivo (.zpl, .prn,
                            .txt)</label>
                        <input class="form-control" type="file" id="zpl_file_upload" name="zpl_file_upload"
                            accept=".zpl,.prn,.txt">
                    </div>

                    <div class="mb-3">
                        <label for="template_conteudo_zpl" class="form-label">Código ZPL <span
                                class="text-danger">*</span></label>
                        <textarea class="form-control" id="template_conteudo_zpl" name="template_conteudo_zpl" rows="15"
                            placeholder="Cole o código ZPL aqui ou use o botão acima para carregar de um arquivo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar
                        Template</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                            class="fas fa-times me-2"></i>Fechar</button>
                </div>
            </form>
        </div>
    </div>
</div>