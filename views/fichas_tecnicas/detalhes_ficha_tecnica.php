<?php // /views/fichas_tecnicas/detalhes_ficha_tecnica.php ?>

<h4 class="fw-bold mb-3" id="main-title">Nova Ficha Técnica</h4>

<div class="card shadow card-custom">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">

            <ul class="nav nav-tabs" id="fichaTecnicaTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="dados-gerais-tab" data-bs-toggle="tab"
                        data-bs-target="#dados-gerais-pane" type="button" role="tab">1. Dados Gerais</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="criterios-tab" data-bs-toggle="tab" data-bs-target="#criterios-pane"
                        type="button" role="tab" disabled>2. Critérios Laboratoriais</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="midia-tab" data-bs-toggle="tab" data-bs-target="#midia-pane"
                        type="button" role="tab" disabled>3. Mídia</button>
                </li>
            </ul>

            <div>
                <button href="index.php?page=relatorio_ficha_tecnica" class="btn btn-outline-primary" id="btn-imprimir-ficha" style="display: none;">
                    <i class="fas fa-print"></i> Imprimir Ficha
                </button>
                <a href="index.php?page=fichas_tecnicas" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar para a Lista
                </a>
                <button type="submit" form="form-ficha-geral" class="btn btn-primary" id="btn-salvar-ficha-geral">
                    <i class="fas fa-save me-2"></i> Salvar e ir para Critérios
                </button>
            </div>

        </div>

        <div class="tab-content pt-3" id="fichaTecnicaTabContent">

            <div class="tab-pane fade show active" id="dados-gerais-pane" role="tabpanel">
                <form id="form-ficha-geral">
                    <input type="hidden" id="ficha_id" name="ficha_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <h5>Informações do Produto</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ficha_produto_id" class="form-label">Produto <span
                                    class="text-danger">*</span></label>
                            <select id="ficha_produto_id" name="ficha_produto_id" class="form-select"
                                style="width: 100%;" required></select>
                            <small class="form-text text-muted">Apenas produtos que ainda não possuem ficha técnica são
                                listados.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ficha_fabricante_id" class="form-label">Fabricante (Entidade)</label>
                            <select id="ficha_fabricante_id" name="ficha_fabricante_id" class="form-select"
                                style="width: 100%;"></select>
                        </div>
                    </div>
                    <div id="produto-info-display" class="p-3 mb-3 bg-light rounded border" style="display: none;">
                    </div>


                    <hr>

                    <h5>Detalhes da Ficha Técnica</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ficha_conservantes" class="form-label">Conservantes</label>
                            <textarea class="form-control" id="ficha_conservantes" name="ficha_conservantes"
                                rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ficha_alergenicos" class="form-label">Alergênicos</label>
                            <textarea class="form-control" id="ficha_alergenicos" name="ficha_alergenicos"
                                rows="2"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="ficha_temp_estocagem_transporte" class="form-label">Temperatura de
                                Estocagem</label>
                            <input type="text" class="form-control" id="ficha_temp_estocagem_transporte"
                                name="ficha_temp_estocagem_transporte">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ficha_origem" class="form-label">Origem</label>
                            <input type="text" class="form-control" id="ficha_origem" name="ficha_origem"
                                value="INDÚSTRIA BRASILEIRA">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ficha_registro_embalagem" class="form-label">Registro de Embalagem</label>
                            <input type="text" class="form-control" id="ficha_registro_embalagem"
                                name="ficha_registro_embalagem" placeholder="Ex: 0522/3128">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ficha_desc_emb_primaria" class="form-label">Descrição Embalagem Primária</label>
                            <textarea class="form-control" id="ficha_desc_emb_primaria" name="ficha_desc_emb_primaria"
                                rows="3"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ficha_desc_emb_secundaria" class="form-label">Descrição Embalagem
                                Secundária</label>
                            <textarea class="form-control" id="ficha_desc_emb_secundaria"
                                name="ficha_desc_emb_secundaria" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ficha_medidas_emb_primaria" class="form-label">Medidas Embalagem
                                Primária</label>
                            <textarea class="form-control" id="ficha_medidas_emb_primaria"
                                name="ficha_medidas_emb_primaria" rows="3"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ficha_medidas_emb_secundaria" class="form-label">Medidas Embalagem
                                Secundária</label>
                            <textarea class="form-control" id="ficha_medidas_emb_secundaria"
                                name="ficha_medidas_emb_secundaria" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="ficha_paletizacao" class="form-label">Paletização</label>
                        <textarea class="form-control" id="ficha_paletizacao" name="ficha_paletizacao"
                            rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="ficha_gestao_qualidade" class="form-label">Gestão da Qualidade (Normas)</label>
                        <textarea class="form-control" id="ficha_gestao_qualidade" name="ficha_gestao_qualidade"
                            rows="4"></textarea>
                    </div>
                </form>

            </div>

            <div class="tab-pane fade" id="criterios-pane" role="tabpanel">
                <form id="form-ficha-criterios">
                    <input type="hidden" id="criterio_id" name="criterio_id">
                    <input type="hidden" name="criterio_ficha_id" id="criterio_ficha_id">

                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label for="criterio_grupo" class="form-label">Grupo <span
                                    class="text-danger">*</span></label>
                            <select id="criterio_grupo" name="criterio_grupo" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="FISICO-QUIMICO">Físico-químico</option>
                                <option value="MICROBIOLOGICO">Microbiológico</option>
                                <option value="SENSORIAL">Sensorial</option>
                                <option value="OUTRO">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="criterio_nome" class="form-label">Nome do Critério <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="criterio_nome" name="criterio_nome" required>
                        </div>
                        <div class="col-md-2">
                            <label for="criterio_unidade" class="form-label">Unidade</label>
                            <input type="text" class="form-control" id="criterio_unidade" name="criterio_unidade"
                                placeholder="ex: UFC/g">
                        </div>
                        <div class="col-md-3">
                            <label for="criterio_valor" class="form-label">Padrão / Valor <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="criterio_valor" name="criterio_valor"
                                placeholder="ex: Ausência" required>
                        </div>
                        <div class="col-md-2">
                            <div id="botoes-acao-criterio" class="d-flex">
                                <button type="submit" class="btn btn-success" id="btn-salvar-criterio">
                                    <i class="fas fa-plus me-2"></i>Adicionar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <hr>

                <h6 class="mt-4">Critérios Adicionados</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover" id="tabela-criterios">
                        <thead class="table-light">
                            <tr>
                                <th>Grupo</th>
                                <th>Critério</th>
                                <th>Unidade</th>
                                <th>Padrão</th>
                                <th class="text-center" style="width: 120px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-criterios">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Nenhum critério adicionado a esta ficha.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="midia-pane" role="tabpanel">
                <p class="text-muted">Faça o upload das imagens para esta ficha técnica. As imagens devem estar no
                    formato JPG, PNG ou GIF.</p>
                <hr>
                <div class="row">

                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 text-center">
                            <div class="card-header fw-bold">1. Tabela Nutricional</div>
                            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                                <div class="preview-container mb-3" id="preview-container-nutricional">
                                    <img src="assets/img/placeholder.png" class="img-fluid rounded"
                                        id="preview-nutricional">
                                </div>
                                <form class="form-upload" data-tipo="TABELA_NUTRICIONAL">
                                    <input type="file" class="form-control form-control-sm" accept="image/*">
                                </form>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-danger btn-sm btn-remover-foto" data-tipo="TABELA_NUTRICIONAL"
                                    style="display: none;">Remover Imagem</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 text-center">
                            <div class="card-header fw-bold">2. Embalagem Primária</div>
                            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                                <div class="preview-container mb-3" id="preview-container-primaria">
                                    <img src="assets/img/placeholder.png" class="img-fluid rounded"
                                        id="preview-primaria">
                                </div>
                                <form class="form-upload" data-tipo="EMBALAGEM_PRIMARIA">
                                    <input type="file" class="form-control form-control-sm" accept="image/*">
                                </form>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-danger btn-sm btn-remover-foto" data-tipo="EMBALAGEM_PRIMARIA"
                                    style="display: none;">Remover Imagem</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 text-center">
                            <div class="card-header fw-bold">3. Embalagem Secundária</div>
                            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                                <div class="preview-container mb-3" id="preview-container-secundaria">
                                    <img src="assets/img/placeholder.png" class="img-fluid rounded"
                                        id="preview-secundaria">
                                </div>
                                <form class="form-upload" data-tipo="EMBALAGEM_SECUNDARIA">
                                    <input type="file" class="form-control form-control-sm" accept="image/*">
                                </form>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-danger btn-sm btn-remover-foto" data-tipo="EMBALAGEM_SECUNDARIA"
                                    style="display: none;">Remover Imagem</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 text-center">
                            <div class="card-header fw-bold">4. Selo SIF</div>
                            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                                <div class="preview-container mb-3" id="preview-container-sif">
                                    <img src="assets/img/placeholder.png" class="img-fluid rounded" id="preview-sif">
                                </div>
                                <form class="form-upload" data-tipo="SIF">
                                    <input type="file" class="form-control form-control-sm" accept="image/*">
                                </form>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-danger btn-sm btn-remover-foto" data-tipo="SIF"
                                    style="display: none;">Remover Imagem</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between">

    </div>
</div>

<div class="modal fade" id="modal-crop-image" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajustar Imagem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="img-container" style="max-height: 500px;">
                    <img id="image-to-crop" src="">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-crop-upload">Cortar e Enviar</button>
            </div>
        </div>
    </div>
</div>