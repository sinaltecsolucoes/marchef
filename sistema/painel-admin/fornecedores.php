<?php
// fornecedores.php
// Estrutura da página de gestão de Fornecedores.
?>

<h4 class="fw-bold mb-3">Fornecedores</h4>

<!-- Botão Adicionar Fornecedor -->
<a href="#" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-adicionar-fornecedor"
    id="btn-adicionar-fornecedor-main">Adicionar Fornecedor</a>

<!-- Filtros de Situação -->
<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">Filtrar por Situação:</label><br>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao_forn" id="filtro-situacao-todos-forn"
                value="Todos" checked>
            <label class="form-check-label" for="filtro-situacao-todos-forn">Todos</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao_forn" id="filtro-situacao-ativo-forn"
                value="A">
            <label class="form-check-label" for="filtro-situacao-ativo-forn">Ativo</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao_forn" id="filtro-situacao-inativo-forn"
                value="I">
            <label class="form-check-label" for="filtro-situacao-inativo-forn">Inativo</label>
        </div>
    </div>
</div>

<!-- Área para mensagens de feedback -->
<div id="feedback-message-area-fornecedor" class="mt-3"></div>

<!-- Tabela Principal de Fornecedores -->
<div class="table-responsive">
    <table id="example-fornecedores" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th>Situação</th>
                <th>Tipo</th>
                <th>Código Interno</th>
                <th>Razão Social</th>
                <th>CPF/CNPJ</th>
                <th>Endereço Principal</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- ============================================================================== -->
<!-- ======================= MODAL ADICIONAR/EDITAR FORNECEDOR ======================= -->
<!-- ============================================================================== -->
<div class="modal fade" id="modal-adicionar-fornecedor" tabindex="-1" aria-labelledby="modal-adicionar-fornecedor-label"
    aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-adicionar-fornecedor-label">Adicionar Fornecedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <!-- Abas de Navegação -->
                <ul class="nav nav-tabs" id="fornecedorTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dados-fornecedor-tab" data-tab-target="#dados-fornecedor"
                            type="button" role="tab">Dados Principais</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="enderecos-fornecedor-tab" data-tab-target="#enderecos-fornecedor"
                            type="button" role="tab">Endereços Adicionais</button>
                    </li>
                </ul>

                <!-- Conteúdo das Abas -->
                <div class="tab-content" id="fornecedorTabContent">
                    <!-- ABA 1: DADOS PRINCIPAIS -->
                    <div class="tab-pane fade show active" id="dados-fornecedor" role="tabpanel">
                        <form id="form-fornecedor" class="mt-3">
                            <input type="hidden" id="ent-codigo-forn" name="ent_codigo">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                            <div id="mensagem-fornecedor" class="mb-3"></div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tipo de Pessoa</label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_pessoa"
                                            id="tipo-pessoa-fisica-forn" value="F" checked>
                                        <label class="form-check-label" for="tipo-pessoa-fisica-forn">Física</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_pessoa"
                                            id="tipo-pessoa-juridica-forn" value="J">
                                        <label class="form-check-label" for="tipo-pessoa-juridica-forn">Jurídica</label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="cpf-cnpj-forn" class="form-label" id="label-cpf-cnpj-forn">CPF</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="cpf-cnpj-forn" name="ent_cpf_cnpj"
                                            required>
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="btn-buscar-cnpj-forn" style="display: none;">Buscar Dados</button>
                                    </div>
                                    <small id="cnpj-feedback-forn" class="form-text text-muted"></small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="codigo-interno" class="form-label">Código Interno</label>
                                    <input type="text" class="form-control" id="codigo-interno-forn"
                                        name="ent_codigo_interno" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="razao-social-forn" class="form-label">Razão Social / Nome
                                        Completo</label>
                                    <input type="text" class="form-control" id="razao-social-forn"
                                        name="ent_razao_social" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nome-fantasia-forn" class="form-label">Nome Fantasia / Apelido</label>
                                    <input type="text" class="form-control" id="nome-fantasia-forn"
                                        name="ent_nome_fantasia">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tipo de Entidade</label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                            id="tipo-entidade-fornecedor-forn" value="Fornecedor" checked>
                                        <label class="form-check-label"
                                            for="tipo-entidade-fornecedor-forn">Fornecedor</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                            id="tipo-entidade-ambos-forn" value="Cliente e Fornecedor">
                                        <label class="form-check-label" for="tipo-entidade-ambos-forn">Ambos</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3" id="div-inscricao-estadual-forn">
                                    <label for="inscricao-estadual-forn" class="form-label">Inscrição Estadual</label>
                                    <input type="text" class="form-control" id="inscricao-estadual-forn"
                                        name="ent_inscricao_estadual">
                                </div>
                            </div>

                            <hr>
                            <h5 class="mb-3">Endereço Principal</h5>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="cep-endereco-forn" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="cep-endereco-forn" name="end_cep">
                                    <small id="cep-feedback-principal-forn" class="form-text"></small>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="logradouro-endereco-forn" class="form-label">Logradouro</label>
                                    <input type="text" class="form-control" id="logradouro-endereco-forn"
                                        name="end_logradouro">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="numero-endereco-forn" class="form-label">Número</label>
                                    <input type="text" class="form-control" id="numero-endereco-forn" name="end_numero">
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label for="complemento-endereco-forn" class="form-label">Complemento</label>
                                    <input type="text" class="form-control" id="complemento-endereco-forn"
                                        name="end_complemento">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <label for="bairro-endereco-forn" class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="bairro-endereco-forn" name="end_bairro">
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label for="cidade-endereco-forn" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="cidade-endereco-forn" name="end_cidade">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="uf-endereco-forn" class="form-label">UF</label>
                                    <input type="text" class="form-control" id="uf-endereco-forn" name="end_uf"
                                        maxlength="2">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="situacao-fornecedor">Situação</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="situacao-fornecedor" name="ent_situacao" value="A" checked>
                                    <label class="form-check-label" for="situacao-fornecedor">
                                        <span id="texto-situacao-fornecedor">Ativo</span>
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- ABA 2: ENDEREÇOS ADICIONAIS -->
                    <div class="tab-pane fade" id="enderecos-fornecedor" role="tabpanel">
                        <h5 class="mt-3">Adicionar / Editar Endereço Adicional</h5>
                        <form id="form-endereco-forn" class="mt-3 border p-3 rounded bg-light">
                            <input type="hidden" id="end-codigo-forn" name="end_codigo">
                            <input type="hidden" id="end-entidade-id-forn" name="end_entidade_id">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                            <div id="mensagem-endereco-forn" class="mb-3"></div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tipo-endereco-forn" class="form-label">Tipo de Endereço</label>
                                    <select class="form-select" id="tipo-endereco-forn" name="end_tipo_endereco"
                                        required>
                                        <option value="">Selecione...</option>
                                        <option value="Principal">Principal</option>
                                        <option value="Entrega">Entrega</option>
                                        <option value="Cobranca">Cobrança</option>
                                        <option value="Comercial">Comercial</option>
                                        <option value="Outro">Outro</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="cep-endereco-adicional-forn" class="form-label">CEP</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="cep-endereco-adicional-forn"
                                            name="end_cep" placeholder="00000-000">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="btn-buscar-cep-adicional-forn">Buscar</button>
                                    </div>
                                    <small id="cep-feedback-adicional-forn" class="form-text"></small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="logradouro-endereco-adicional-forn" class="form-label">Logradouro</label>
                                <input type="text" class="form-control" id="logradouro-endereco-adicional-forn"
                                    name="end_logradouro">
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="numero-endereco-adicional-forn" class="form-label">Número</label>
                                    <input type="text" class="form-control" id="numero-endereco-adicional-forn"
                                        name="end_numero">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="complemento-endereco-adicional-forn"
                                        class="form-label">Complemento</label>
                                    <input type="text" class="form-control" id="complemento-endereco-adicional-forn"
                                        name="end_complemento">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <label for="bairro-endereco-adicional-forn" class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="bairro-endereco-adicional-forn"
                                        name="end_bairro">
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label for="cidade-endereco-adicional-forn" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="cidade-endereco-adicional-forn"
                                        name="end_cidade">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="uf-endereco-adicional-forn" class="form-label">UF</label>
                                    <input type="text" class="form-control" id="uf-endereco-adicional-forn"
                                        name="end_uf" maxlength="2">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success" id="btn-salvar-endereco-forn">Salvar Endereço
                                Adicional</button>
                            <button type="button" class="btn btn-secondary"
                                id="btn-cancelar-edicao-endereco-forn">Limpar / Cancelar</button>
                        </form>

                        <hr class="my-4">
                        <h5 class="mb-3">Endereços Cadastrados</h5>
                        <div class="table-responsive">
                            <table id="tabela-enderecos-fornecedor" class="table table-hover my-4" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Logradouro</th>
                                        <th>Cidade/UF</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" form="form-fornecedor" class="btn btn-primary">Salvar Fornecedor</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modais de Confirmação de Exclusão -->
<div class="modal fade" id="modal-confirmar-exclusao-fornecedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o fornecedor <strong id="nome-fornecedor-excluir"></strong>?</p>
                <p class="text-danger">Esta ação é irreversível e excluirá todos os endereços associados!</p>
                <input type="hidden" id="id-fornecedor-excluir">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao-fornecedor">Sim,
                    Excluir</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modal-confirmar-exclusao-endereco-forn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir este endereço?</p>
                <p class="text-danger">Esta ação é irreversível!</p>
                <input type="hidden" id="id-endereco-excluir-forn">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao-endereco-forn">Sim,
                    Excluir</button>
            </div>
        </div>
    </div>
</div>