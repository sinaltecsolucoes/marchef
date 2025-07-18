<?php
// clientes.php
// Esta página será incluída por index.php, então já terá acesso a $pdo, $_SESSION, $csrf_token, etc.
// O token CSRF já deve estar disponível via $csrf_token do index.php
?>

<h4 class="fw-bold mb-3">Clientes</h4>

<!-- Botão Adicionar Cliente -->
<a href="#" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-adicionar-cliente"
    id="btn-adicionar-cliente-main">Adicionar Cliente</a>

<!-- Filtros de Situação -->
<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">Filtrar por Situação:</label><br>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-todos" value="Todos"
                checked>
            <label class="form-check-label" for="filtro-situacao-todos">Todos</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-ativo" value="A">
            <label class="form-check-label" for="filtro-situacao-ativo">Ativo</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-inativo" value="I">
            <label class="form-check-label" for="filtro-situacao-inativo">Inativo</label>
        </div>
    </div>
</div>

<!-- Área para mensagens de feedback (sucesso/erro) -->
<div id="feedback-message-area-cliente" class="mt-3"></div>

<div class="table-responsive">
    <table id="example-clientes" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th>Situação</th>
                <th>Tipo</th>
                <th>Razão Social</th>
                <th>CPF/CNPJ</th>
                <th>Endereço Principal</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <!-- Dados serão carregados via DataTables e AJAX -->
        </tbody>
    </table>
</div>

<!-- ============================================================================== -->
<!-- ======================= MODAL ADICIONAR/EDITAR CLIENTE ======================= -->
<!-- ============================================================================== -->
<div class="modal fade" id="modal-adicionar-cliente" tabindex="-1" role="document"
    aria-labelledby="modal-adicionar-cliente-label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-adicionar-cliente-label">Adicionar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <!-- Abas de Navegação -->
                <!-- <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dados-cliente-tab" data-bs-toggle="tab" data-bs-target="#dados-cliente" type="button" role="tab" aria-controls="dados-cliente" aria-selected="true">Dados do Cliente</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="enderecos-tab" data-bs-toggle="tab" data-bs-target="#enderecos" type="button" role="tab" aria-controls="enderecos" aria-selected="false" disabled>Endereços</button>
                    </li>
                </ul> -->

                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dados-cliente-tab" data-tab-target="#dados-cliente"
                            type="button" role="tab">Dados do Cliente</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="enderecos-tab" data-tab-target="#enderecos" type="button"
                            role="tab">Endereços Adicionais</button>
                    </li>
                </ul>

                <!-- Conteúdo das Abas -->
                <div class="tab-content" id="myTabContent">
                    <!-- ======================================================================= -->
                    <!-- ======================= ABA 1: DADOS PRINCIPAIS ======================= -->
                    <!-- ======================================================================= -->
                    <div class="tab-pane fade show active" id="dados-cliente" role="tabpanel"
                        aria-labelledby="dados-cliente-tab">
                        <form id="form-cliente" class="mt-3">
                            <!-- Campos ocultos -->
                            <input type="hidden" id="ent-codigo" name="ent_codigo">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                            <div id="mensagem-cliente" class="mb-3"></div>

                            <!-- Linha: Tipo Pessoa e CPF/CNPJ -->
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tipo de Pessoa</label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_pessoa"
                                            id="tipo-pessoa-fisica" value="F" checked>
                                        <label class="form-check-label" for="tipo-pessoa-fisica">Física</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_pessoa"
                                            id="tipo-pessoa-juridica" value="J">
                                        <label class="form-check-label" for="tipo-pessoa-juridica">Jurídica</label>
                                    </div>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="cpf-cnpj" class="form-label" id="label-cpf-cnpj">CPF</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="cpf-cnpj" name="ent_cpf_cnpj"
                                            placeholder="000.000.000-00" required>
                                        <button class="btn btn-outline-secondary" type="button" id="btn-buscar-cnpj"
                                            style="display: none;">Buscar Dados</button>
                                    </div>
                                    <small id="cnpj-feedback" class="form-text text-muted"></small>
                                </div>
                            </div>

                            <!-- Linha: Razão Social e Nome Fantasia -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="razao-social" class="form-label">Razão Social / Nome
                                        Completo</label>
                                    <input type="text" class="form-control" id="razao-social" name="ent_razao_social"
                                        required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nome-fantasia" class="form-label">Nome Fantasia / Apelido</label>
                                    <input type="text" class="form-control" id="nome-fantasia" name="ent_nome_fantasia">
                                </div>
                            </div>

                            <!-- Linha: Tipo Entidade e IE -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tipo de Entidade</label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                            id="tipo-entidade-cliente" value="Cliente" checked>
                                        <label class="form-check-label" for="tipo-entidade-cliente">Cliente</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                            id="tipo-entidade-ambos" value="Cliente e Fornecedor">
                                        <label class="form-check-label" for="tipo-entidade-ambos">Ambos</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3" id="div-inscricao-estadual">
                                    <label for="inscricao-estadual" class="form-label">Inscrição Estadual</label>
                                    <input type="text" class="form-control" id="inscricao-estadual"
                                        name="ent_inscricao_estadual">
                                </div>
                            </div>

                            <hr>
                            <h5 class="mb-3">Endereço Principal</h5>

                            <!-- Linha: CEP e Logradouro -->
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="cep-endereco" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="cep-endereco" name="end_cep"
                                        placeholder="00000-000">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="logradouro-endereco" class="form-label">Logradouro</label>
                                    <input type="text" class="form-control" id="logradouro-endereco"
                                        name="end_logradouro" placeholder="Rua, Avenida, etc.">
                                </div>
                            </div>

                            <!-- Linha: Número e Complemento -->
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="numero-endereco" class="form-label">Número</label>
                                    <input type="text" class="form-control" id="numero-endereco" name="end_numero"
                                        placeholder="Número">
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label for="complemento-endereco" class="form-label">Complemento</label>
                                    <input type="text" class="form-control" id="complemento-endereco"
                                        name="end_complemento" placeholder="Apto, Bloco, etc. (Opcional)">
                                </div>
                            </div>

                            <!-- Linha: Bairro, Cidade e UF -->
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <label for="bairro-endereco" class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="bairro-endereco" name="end_bairro"
                                        placeholder="Bairro">
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label for="cidade-endereco" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="cidade-endereco" name="end_cidade"
                                        placeholder="Cidade">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="uf-endereco" class="form-label">UF</label>
                                    <input type="text" class="form-control" id="uf-endereco" name="end_uf"
                                        maxlength="2">
                                </div>
                            </div>

                            <!-- Linha: Situação -->
                            <div class="mb-3">
                                <label class="form-label" for="situacao-cliente">Situação</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="situacao-cliente"
                                        name="ent_situacao" value="A" checked>
                                    <label class="form-check-label" for="situacao-cliente">
                                        <span id="texto-situacao-cliente">Ativo</span>
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- =========================================================================== -->
                    <!-- ======================= ABA 2: ENDEREÇOS ADICIONAIS ======================= -->
                    <!-- =========================================================================== -->
                    <div class="tab-pane fade" id="enderecos" role="tabpanel" aria-labelledby="enderecos-tab">
                        <h5 class="mt-3">Adicionar / Editar Endereço Adicional</h5>
                        <form id="form-endereco" class="mt-3 border p-3 rounded bg-light">
                            <!-- Campos Ocultos -->
                            <input type="hidden" id="end-codigo" name="end_codigo">
                            <input type="hidden" id="end-entidade-id" name="end_entidade_id">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                            <div id="mensagem-endereco" class="mb-3"></div>

                            <!-- Linha 1: Tipo de Endereço e CEP -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tipo-endereco" class="form-label">Tipo de Endereço</label>
                                    <select class="form-select" id="tipo-endereco" name="end_tipo_endereco" required>
                                        <option value="">Selecione...</option>
                                        <option value="Principal">Principal</option>
                                        <option value="Entrega">Entrega</option>
                                        <option value="Cobranca">Cobrança</option>
                                        <option value="Residencial">Residencial</option>
                                        <option value="Comercial">Comercial</option>
                                        <option value="Outro">Outro</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="cep-endereco-adicional" class="form-label">CEP</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="cep-endereco-adicional"
                                            name="end_cep" placeholder="00000-000">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="btn-buscar-cep-adicional">Buscar</button>
                                    </div>
                                    <small id="cep-feedback-adicional" class="form-text text-muted"></small>
                                </div>
                            </div>

                            <!-- Linha 2: Logradouro -->
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="logradouro-endereco-adicional" class="form-label">Logradouro</label>
                                    <input type="text" class="form-control" id="logradouro-endereco-adicional"
                                        name="end_logradouro">
                                </div>
                            </div>

                            <!-- Linha 3: Número e Complemento -->
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="numero-endereco-adicional" class="form-label">Número</label>
                                    <input type="text" class="form-control" id="numero-endereco-adicional"
                                        name="end_numero">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="complemento-endereco-adicional" class="form-label">Complemento</label>
                                    <input type="text" class="form-control" id="complemento-endereco-adicional"
                                        name="end_complemento">
                                </div>
                            </div>

                            <!-- Linha 4: Bairro, Cidade e UF -->
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <label for="bairro-endereco-adicional" class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="bairro-endereco-adicional"
                                        name="end_bairro">
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label for="cidade-endereco-adicional" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="cidade-endereco-adicional"
                                        name="end_cidade">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="uf-endereco-adicional" class="form-label">UF</label>
                                    <input type="text" class="form-control" id="uf-endereco-adicional"
                                        name="end_uf" maxlength="2">
                                </div>
                            </div>

                            <!-- Botões -->
                            <button type="submit" class="btn btn-success" id="btn-salvar-endereco">Salvar Endereço
                                Adicional</button>
                            <button type="button" class="btn btn-secondary" id="btn-cancelar-edicao-endereco">Limpar /
                                Cancelar</button>
                        </form>

                        <hr class="my-4">
                        <h5 class="mb-3">Endereços Cadastrados</h5>
                        <div class="table-responsive">
                            <table id="tabela-enderecos-cliente" class="table table-hover my-4" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Logradouro</th>
                                        <th>Cidade/UF</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <!-- Botão Salvar para o formulário principal do cliente -->
                <button type="submit" form="form-cliente" class="btn btn-primary" id="btn-submit-cliente-modal">Salvar
                    Cliente</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão de Cliente -->
<div class="modal fade" id="modal-confirmar-exclusao-cliente" tabindex="-1" role="dialog"
    aria-labelledby="modal-confirmar-exclusao-cliente-label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-confirmar-exclusao-cliente-label">Confirmar Exclusão de Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o cliente <strong id="nome-cliente-excluir"></strong>?</p>
                <p class="text-danger">Esta ação é irreversível e excluirá também todos os endereços associados!</p>
                <!-- Campo oculto para armazenar o ID do cliente a ser excluído -->
                <input type="hidden" id="id-cliente-excluir">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao-cliente">Sim, Excluir</button>
            </div>
        </div>
    </div>
</div>

<!-- NOVO: Modal de Confirmação de Exclusão de Endereço -->
<div class="modal fade" id="modal-confirmar-exclusao-endereco" tabindex="-1" role="dialog"
    aria-labelledby="modal-confirmar-exclusao-endereco-label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-confirmar-exclusao-endereco-label">Confirmar Exclusão de Endereço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir este endereço?</p>
                <p class="text-danger">Esta ação é irreversível!</p>
                <!-- Campo oculto para armazenar o ID do endereço a ser excluído -->
                <input type="hidden" id="id-endereco-excluir">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao-endereco">Sim, Excluir</button>
            </div>
        </div>
    </div>
</div>