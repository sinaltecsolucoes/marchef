<?php
// produtos.php
// O token CSRF já deve estar disponível via $csrf_token do index.php
?>

<h4 class="fw-bold mb-3">Gestão de Produtos</h4>


<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Gerenciar Produtos</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <!-- Botão Adicionar -->
            <div class="col-12 col-md-3 mb-3 mb-md-0 d-flex align-items-center">
                <button class="btn btn-primary" id="btn-adicionar-produto-main">
                    <i class="fas fa-plus me-2"></i> Adicionar Produto
                </button>
            </div>

            <!-- Espaço vazio (coluna 2) -->
            <div class="col-12 col-md-3 mb-3 mb-md-0 d-none d-md-block"></div>

            <!-- Espaço vazio (coluna 3) -->
            <div class="col-12 col-md-3 mb-3 mb-md-0 d-none d-md-block"></div>

            <!-- Filtro por Situação -->
            <div class="col-12 col-md-3 mb-3 mb-md-0 d-flex align-items-center">
                <label class="form-label me-2" style="white-space: nowrap;">Filtrar por Situação:</label>
                <div class="d-flex flex-wrap">
                    <div class="form-check form-check-inline me-3">
                        <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-todos"
                            value="Todos" checked>
                        <label class="form-check-label" for="filtro-situacao-todos">Todos</label>
                    </div>
                    <div class="form-check form-check-inline me-3">
                        <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-ativo"
                            value="A">
                        <label class="form-check-label" for="filtro-situacao-ativo">Ativo</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-inativo"
                            value="I">
                        <label class="form-check-label" for="filtro-situacao-inativo">Inativo</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Produtos -->
<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Produtos</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-produtos" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center">Situação</th>
                        <th class="text-center">Cód. Interno</th>
                        <th class="text-center">Descrição</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Embalagem</th>
                        <th class="text-center">Peso</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dados carregados via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Adicionar/Editar Produto -->
<div class="modal fade" id="modal-adicionar-produto" tabindex="-1" role="dialog"
    aria-labelledby="modal-adicionar-produto-label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-adicionar-produto-label">Adicionar Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="form-produto">
                    <input type="hidden" id="prod_codigo" name="prod_codigo">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                    <div class="row">
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="prod_categoria" class="form-label">Categoria (IQF)</label>
                            <select class="form-select" id="prod_categoria" name="prod_categoria">
                                <option value="">Nenhuma</option>
                                <option value="A1">A1</option>
                                <option value="A2">A2</option>
                                <option value="A3">A3</option>
                            </select>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="prod_classe" class="form-label">Classe (Descrição para Etiqueta)</label>
                            <input type="text" class="form-control" id="prod_classe" name="prod_classe"
                                placeholder="Será calculado automaticamente...">
                        </div>
                    </div>
                    <div class="row">
                    </div>

                    <div id="mensagem-produto" class="mb-3"></div>

                    <!-- Linha 1: Tipo de Embalagem e Descrição -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="prod_tipo_embalagem" class="form-label">Tipo de Embalagem</label>
                            <select class="form-select" id="prod_tipo_embalagem" name="prod_tipo_embalagem" required>
                                <option value="PRIMARIA">Primária</option>
                                <option value="SECUNDARIA">Secundária</option>
                            </select>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="prod_descricao" class="form-label">Descrição do Produto</label>
                            <input type="text" class="form-control" id="prod_descricao" name="prod_descricao" required>
                        </div>
                    </div>

                    <!-- Bloco que só aparece para Embalagem Secundária -->
                    <div id="bloco-embalagem-secundaria" class="row bg-light p-3 rounded mb-3" style="display: none;">
                        <div class="col-md-6 mb-3">
                            <label for="prod_primario_id" class="form-label">Produto Primário Contido</label>
                            <select class="form-select" id="prod_primario_id" name="prod_primario_id">
                                <option value="">Selecione o produto primário...</option>
                                <!-- Carregado via JS -->
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="peso_embalagem_secundaria" class="form-label">Peso Emb. Secundária</label>
                            <input type="text" class="form-control" id="peso_embalagem_secundaria"
                                name="prod_peso_embalagem_secundaria" placeholder="Ex: 10.000">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Unidades Primárias</label>
                            <input type="text" class="form-control" id="unidades_primarias" readonly disabled>
                        </div>
                    </div>

                    <!-- Linha 2: Código Interno, Tipo e Subtipo -->
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <!--<label for="prod_codigo_interno" class="form-label">Código Interno</label>-->
                            <label for="prod_codigo_interno" class="form-label">Código Interno <span class="text-danger"
                                    id="asterisco-codigo-interno" style="display: none;">*</span></label>
                            <input type="text" class="form-control" id="prod_codigo_interno" name="prod_codigo_interno">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="prod_tipo" class="form-label">Tipo</label>
                            <select class="form-select" id="prod_tipo" name="prod_tipo" required>
                                <option value="CAMARAO">Camarão</option>
                                <option value="PEIXE">Peixe</option>
                                <option value="POLVO">Polvo</option>
                                <option value="LAGOSTA">Lagosta</option>
                                <option value="OUTRO">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prod_subtipo" class="form-label">Subtipo</label>
                            <input type="text" class="form-control" id="prod_subtipo" name="prod_subtipo"
                                placeholder="Ex: Sem Cabeça, P&D, Posta...">
                        </div>
                    </div>

                    <!-- Linha 3: Classificação, Espécie e Origem -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="prod_classificacao" class="form-label">Classificação</label>
                            <input type="text" class="form-control" id="prod_classificacao" name="prod_classificacao">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="prod_especie" class="form-label">Espécie</label>
                            <input type="text" class="form-control" id="prod_especie" name="prod_especie">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="prod_origem" class="form-label">Origem</label>
                            <select class="form-select" id="prod_origem" name="prod_origem">
                                <option value="CULTIVO">Cultivo</option>
                                <option value="PESCA EXTRATIVA">Pesca Extrativa</option>
                            </select>
                        </div>
                    </div>

                    <!-- Linha 4: Características de Produção -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="prod_conservacao" class="form-label">Conservação</label>
                            <select class="form-select" id="prod_conservacao" name="prod_conservacao">
                                <option value="CRU">Cru</option>
                                <option value="COZIDO">Cozido</option>
                                <option value="PARC. COZIDO">Parc. Cozido</option>
                                <option value="EMPANADO">Empanado</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="prod_congelamento" class="form-label">Congelamento</label>
                            <select class="form-select" id="prod_congelamento" name="prod_congelamento">
                                <option value="BLOCO">Bloco</option>
                                <option value="IQF">IQF</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="prod_fator_producao" class="form-label">Fator de Produção (%)</label>
                            <input type="number" step="0.01" class="form-control" id="prod_fator_producao"
                                name="prod_fator_producao" placeholder="Ex: 65.50">
                        </div>
                    </div>

                    <!-- Linha 5: Detalhes da Embalagem Primária -->
                    <div id="bloco-embalagem-primaria" class="row">
                        <div class="col-md-4 mb-3">
                            <label for="prod_peso_embalagem" class="form-label">Peso Embalagem (kg)</label>
                            <input type="text" class="form-control" id="prod_peso_embalagem" name="prod_peso_embalagem"
                                placeholder="Ex: 2.000">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="prod_total_pecas" class="form-label">Total de Peças</label>
                            <input type="text" class="form-control" id="prod_total_pecas" name="prod_total_pecas"
                                placeholder="Ex: 55 a 60">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="prod_ean13" class="form-label">Cód. Barras (EAN-13)</label>
                            <input type="text" class="form-control" id="prod_ean13" name="prod_ean13" maxlength="13">
                        </div>
                    </div>

                    <!-- Linha 6: Código de Barras DUN para Embalagem Secundária e Validade em Meses -->
                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <label for="prod_validade_meses" class="form-label">Validade Padrão (em
                                    meses)</label>
                                <input type="number" class="form-control" id="prod_validade_meses"
                                    name="prod_validade_meses" placeholder="Ex: 12">
                                <small class="form-text text-muted">Para 1 ano, digite 12. Para 1 ano e 6 meses,
                                    18.</small>
                            </div>
                        </div>

                        <div id="bloco-dun14" class="col-md-6 mb-3" style="display: none;">
                            <label for="prod_dun14" class="form-label">Cód. Barras (DUN-14)</label>
                            <input type="text" class="form-control" id="prod_dun14" name="prod_dun14" maxlength="14">
                        </div>

                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="submit" form="form-produto" class="btn btn-primary">Salvar Produto</button>
            </div>
        </div>
    </div>
</div>