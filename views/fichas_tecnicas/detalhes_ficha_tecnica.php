<?php // /views/fichas_tecnicas/detalhes_ficha_tecnica.php ?>

<h4 class="fw-bold mb-3" id="main-title">Nova Ficha Técnica</h4>

<div class="card shadow card-custom">
    <div class="card-body">

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
                <button class="nav-link" id="midia-tab" data-bs-toggle="tab" data-bs-target="#midia-pane" type="button"
                    role="tab" disabled>3. Mídia</button>
            </li>
        </ul>

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
                <p class="text-muted">Adicione os critérios laboratoriais para este produto.</p>
            </div>

            <div class="tab-pane fade" id="midia-pane" role="tabpanel">
                <p class="text-muted">Faça o upload das fotos do produto e do selo SIF.</p>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <a href="index.php?page=fichas_tecnicas" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para a Lista
        </a>
        <button type="submit" form="form-ficha-geral" class="btn btn-primary" id="btn-salvar-ficha-geral">
            <i class="fas fa-save me-2"></i> Salvar e ir para Critérios
        </button>
    </div>
</div>