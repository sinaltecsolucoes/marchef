<?php
// produtos.php
// O token CSRF já deve estar disponível via $csrf_token do index.php
?>

<h4 class="fw-bold mb-3">Gestão de Produtos</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">
            Gestão de Produtos
        </h6>
    </div>

    <div class="card-body">
        <div class="row align-items-center">

            <div class="col-md-4 border-end px-4">
                <h5 class="fw-bold text-secondary mb-3" style="font-size: 1rem;">Gerenciar Produtos</h5>
                
                <label class="form-label small fw-bold d-block">&nbsp;</label>
                
                <div class="d-flex flex-column flex-md-row gap-2">
                    <button class="btn btn-primary py-2" id="btn-adicionar-produto-main">
                        <i class="fas fa-plus me-2"></i> Novo Produto
                    </button>
                    
                    <button class="btn btn-secondary py-2" id="btn-imprimir-relatorio">
                        <i class="fas fa-print me-2"></i> Relatório
                    </button>
                </div>
            </div>

            <div class="col-md-8 px-4">
                <div class="row g-2">
                    <h5 class="fw-bold text-secondary mb-3" style="font-size: 1rem;">
                        Filtros Avançados
                    </h5>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Tipo Embalagem</label>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start text-truncate" type="button" id="dropdownTipo" data-bs-toggle="dropdown" aria-expanded="false">
                                Tipo Embalagem
                            </button>
                            <ul class="dropdown-menu shadow" id="filter-tipo-container" aria-labelledby="dropdownTipo" style="min-width: 200px;">
                                <li class="px-2 pb-2 border-bottom">
                                    <div class="form-check">
                                        <input class="form-check-input check-all" type="checkbox" value="TODOS" id="tipo_todos" checked>
                                        <label class="form-check-label fw-bold" for="tipo_todos">Marcar Todos</label>
                                    </div>
                                </li>
                                <li class="px-2 pt-2">
                                    <div class="form-check"><input class="form-check-input filter-check" type="checkbox" value="PRIMARIA"><label class="form-check-label">Primária</label></div>
                                </li>
                                <li class="px-2">
                                    <div class="form-check"><input class="form-check-input filter-check" type="checkbox" value="SECUNDARIA"><label class="form-check-label">Secundária</label></div>
                                </li>
                                <li class="px-2">
                                    <div class="form-check"><input class="form-check-input filter-check" type="checkbox" value="MATERIA-PRIMA"><label class="form-check-label">Matéria-Prima</label></div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Situação</label>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start text-truncate" type="button" id="dropdownSituacao" data-bs-toggle="dropdown" aria-expanded="false">
                                Situação
                            </button>
                            <ul class="dropdown-menu shadow" id="filter-situacao-container" aria-labelledby="dropdownSituacao" style="min-width: 180px;">
                                <li class="px-2 pb-2 border-bottom">
                                    <div class="form-check">
                                        <input class="form-check-input check-all" type="checkbox" value="TODOS" id="sit_todos" checked>
                                        <label class="form-check-label fw-bold" for="sit_todos">Marcar Todos</label>
                                    </div>
                                </li>
                                <li class="px-2 pt-2">
                                    <div class="form-check"><input class="form-check-input filter-check" type="checkbox" value="A"><label class="form-check-label">Ativo</label></div>
                                </li>
                                <li class="px-2">
                                    <div class="form-check"><input class="form-check-input filter-check" type="checkbox" value="I"><label class="form-check-label">Inativo</label></div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Marcas</label>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start text-truncate" type="button" id="dropdownMarca" data-bs-toggle="dropdown" aria-expanded="false">
                                Marcas
                            </button>
                            <ul class="dropdown-menu shadow scrollable-menu" id="filter-marca-container" aria-labelledby="dropdownMarca" style="min-width: 250px; max-height: 300px; overflow-y: auto;">
                                <li class="px-2 pb-2 border-bottom">
                                    <div class="form-check">
                                        <input class="form-check-input check-all" type="checkbox" value="TODOS" id="marca_todos" checked>
                                        <label class="form-check-label fw-bold" for="marca_todos">Todas as Marcas</label>
                                    </div>
                                </li>
                                <div id="lista-marcas-dinamica">
                                    <li class="text-center text-muted small py-2"><i class="fas fa-spinner fa-spin"></i></li>
                                </div>
                            </ul>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <button type="button" class="btn btn-info fw-bold text-white" id="btn-aplicar-filtros" title="Aplicar Filtros">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-produtos" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center">Sit.</th>
                        <th class="text-center">Cód.</th>
                        <th>Descrição</th>
                        <th>Marca</th>
                        <th>Desc. Etiqueta</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Emb.</th>
                        <th class="text-center">Peso</th>
                        <th>Unid.</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
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

                    <!-- Linha 1: Categoria (IQF), Descrição para Etiquetas e Situação -->
                    <div class="row">
                        <!-- Campo Categoria (IQF) -->
                        <div class="col-md-3 mb-3">
                            <label for="prod_tipo_embalagem" class="form-label">Tipo de Embalagem</label>
                            <select class="form-select" id="prod_tipo_embalagem" name="prod_tipo_embalagem" required>
                                <option value="PRIMARIA">Primária</option>
                                <option value="SECUNDARIA">Secundária</option>
                                <option value="MATERIA-PRIMA">Matéria-Prima</option>
                            </select>
                        </div>

                        <!-- Campo Descrição para Etiqueta -->
                        <div class="col-md-7 mb-3">
                            <label for="prod_classe" class="form-label">Classe (Descrição para Etiqueta)</label>
                            <input type="text" class="form-control" id="prod_classe" name="prod_classe"
                                placeholder="Será calculado automaticamente...">
                        </div>

                        <!-- Switch Situação -->
                        <div class="col-md-2 mb-3">
                            <label for="prod_situacao" class="form-label">Situação</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="prod_situacao"
                                    name="prod_situacao" value="A" checked>
                                <label class="form-check-label" for="prod_situacao"><span
                                        id="label-prod-situacao">Ativo</span></label>
                            </div>
                        </div>
                    </div>

                    <div id="mensagem-produto" class="mb-3"></div>

                    <!-- Linha 2: Tipo de Embalagem e Descrição -->
                    <div class="row">

                        <!-- Tipo Embalagem-->
                        <div class="col-md-3 mb-3">

                            <label for="prod_categoria" class="form-label">Categoria (IQF)</label>
                            <select class="form-select" id="prod_categoria" name="prod_categoria">
                                <option value="">Nenhuma</option>
                                <option value="A1">A1</option>
                                <option value="A2">A2</option>
                                <option value="A3">A3</option>
                            </select>

                        </div>

                        <!-- Descrição do Produto -->
                        <div class="col-md-9 mb-3">
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

                    <!-- Linha 3: Código Interno, NCM e Marca -->
                    <div class="row">

                        <!-- Código Interno -->
                        <div class="col-md-3 mb-3">
                            <label for="prod_codigo_interno" class="form-label">Código Interno <span class="text-danger"
                                    id="asterisco-codigo-interno" style="display: none;">*</span></label>
                            <input type="text" class="form-control" id="prod_codigo_interno" name="prod_codigo_interno">
                        </div>

                        <!-- NCM -->
                        <div class="col-md-5 mb-3">
                            <label for="prod_ncm" class="form-label">NCM</label>
                            <input type="text" class="form-control" id="prod_ncm" name="prod_ncm"
                                placeholder="Nomenclatura Comum do Mercosul">
                        </div>

                        <!-- Marca -->
                        <div class="col-md-4 mb-3">
                            <label for="prod_marca" class="form-label">Marca</label>
                            <input type="text" class="form-control" id="prod_marca" name="prod_marca"
                                placeholder="Marca do produto">
                        </div>
                    </div>

                    <!-- Linha 4: Tipo de Produto e Subtipo-->
                    <div class="row">

                        <!-- Tipo Produto -->
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

                        <!-- Subtipo Produto -->
                        <div class="col-md-9 mb-3">
                            <label for="prod_subtipo" class="form-label">Subtipo</label>
                            <input type="text" class="form-control" id="prod_subtipo" name="prod_subtipo"
                                placeholder="Ex: Sem Cabeça, P&D, Posta...">
                        </div>
                    </div>

                    <!-- Linha 5: Classificação, Espécie e Origem -->
                    <div class="row">

                        <!-- Classificação -->
                        <div class="col-md-4 mb-3">
                            <label for="prod_classificacao" class="form-label">Classificação</label>
                            <input type="text" class="form-control" id="prod_classificacao" name="prod_classificacao">
                        </div>

                        <!-- Espécie -->
                        <div class="col-md-4 mb-3">
                            <label for="prod_especie" class="form-label">Espécie</label>
                            <input type="text" class="form-control" id="prod_especie" name="prod_especie">
                        </div>

                        <!-- Origem -->
                        <div class="col-md-4 mb-3">
                            <label for="prod_origem" class="form-label">Origem</label>
                            <select class="form-select" id="prod_origem" name="prod_origem">
                                <option value="CULTIVO">Cultivo</option>
                                <option value="PESCA EXTRATIVA">Pesca Extrativa</option>
                            </select>
                        </div>
                    </div>

                    <!-- Linha 6: Características de Produção (Conservação, Congelamento, Fator de Produção) -->
                    <div class="row">

                        <!-- Conservação -->
                        <div class="col-md-4 mb-3">
                            <label for="prod_conservacao" class="form-label">Conservação</label>
                            <select class="form-select" id="prod_conservacao" name="prod_conservacao">
                                <option value="CRU">Cru</option>
                                <option value="COZIDO">Cozido</option>
                                <option value="PARC. COZIDO">Parc. Cozido</option>
                                <option value="EMPANADO">Empanado</option>
                            </select>
                        </div>

                        <!-- Congelamento -->
                        <div class="col-md-4 mb-3">
                            <label for="prod_congelamento" class="form-label">Congelamento</label>
                            <select class="form-select" id="prod_congelamento" name="prod_congelamento">
                                <option value="IN NATURA">In Natura</option>
                                <option value="BLOCO">Bloco</option>
                                <option value="IQF">IQF</option>
                            </select>
                        </div>

                        <!-- Fator Produção -->
                        <div class="col-md-4 mb-3">
                            <label for="prod_fator_producao" class="form-label">Fator de Produção (%)</label>
                            <input type="number" step="0.01" class="form-control" id="prod_fator_producao"
                                name="prod_fator_producao" placeholder="Ex: 65.50">
                        </div>
                    </div>

                    <!-- Linha 7: Detalhes da Embalagem Primária (Peso, Total Peças, Código EAN-->
                    <div id="bloco-embalagem-primaria" class="row">

                        <!-- Peso Embalagem-->
                        <div class="col-md-4 mb-3">
                            <label for="prod_peso_embalagem" class="form-label">Peso Embalagem (kg)</label>
                            <input type="text" class="form-control" id="prod_peso_embalagem" name="prod_peso_embalagem"
                                placeholder="Ex: 2.000">
                        </div>

                        <!-- Total de Peças Embalagem-->
                        <div class="col-md-4 mb-3">
                            <label for="prod_total_pecas" class="form-label">Total de Peças</label>
                            <input type="text" class="form-control" id="prod_total_pecas" name="prod_total_pecas"
                                placeholder="Ex: 55 a 60">
                        </div>

                        <!-- Código EAN 13 Embalagem-->
                        <div class="col-md-4 mb-3">
                            <label for="prod_ean13" class="form-label">Cód. Barras (EAN-13)</label>
                            <input type="text" class="form-control" id="prod_ean13" name="prod_ean13" maxlength="13">
                        </div>
                    </div>

                    <!-- Linha 8: Validade em Meses, Unidade de Medida e Código de Barras DUN para Embalagem Secundária -->
                    <div class="row">

                        <!-- Validade Padrão -->
                        <div class="col-md-4 mb-3">
                            <div class="form-group">
                                <label for="prod_validade_meses" class="form-label">Validade Padrão (em
                                    meses)</label>
                                <input type="number" class="form-control" id="prod_validade_meses"
                                    name="prod_validade_meses" placeholder="Ex: 12">
                                <small class="form-text text-muted">Para 1 ano, digite 12. Para 1 ano e 6 meses,
                                    18.</small>
                            </div>
                        </div>

                        <!-- Unidade de medida -->
                        <div class="col-md-4 mb-3">
                            <label for="prod_unidade" class="form-label">Unidade</label>
                            <select class="form-select" id="prod_unidade" name="prod_unidade">
                                <option value="KG">KG (Quilo)</option>
                                <option value="CX">CX (Caixa)</option>
                                <option value="SC">SC (Saco)</option>
                                <option value="PCT">PCT (Pacote)</option>
                                <option value="UN">UN (Unidade)</option>
                                <option value="TON">TON (Tonelada)</option>
                            </select>
                        </div>

                        <!-- Código DUN para Embalagens Secundárias -->
                        <div id="bloco-dun14" class="col-md-4 mb-3" style="display: none;">
                            <label for="prod_dun14" class="form-label">Cód. Barras (DUN-14)</label>
                            <input type="text" class="form-control" id="prod_dun14" name="prod_dun14" maxlength="14">
                        </div>

                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="form-produto" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar
                    Produto</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                        class="fas fa-times me-2"></i>Fechar</button>
            </div>
        </div>
    </div>
</div>