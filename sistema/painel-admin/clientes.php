<?php
// clientes.php
// Esta página será incluída por index.php, então já terá acesso a $pdo, $_SESSION, $csrf_token, etc.

// Certifique-se de que o usuário tem permissão para acessar esta página
// Esta verificação já é feita em index.php, mas é uma boa prática ter uma camada extra aqui se necessário.
// if (!in_array('clientes', $paginasPermitidasUsuario)) {
//     echo "<h1 class='text-danger'>Acesso Negado! Você não tem permissão para acessar a página de Clientes.</h1>";
//     exit();
// }

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

<!-- Modal Adicionar/Editar Cliente -->
<div class="modal fade" id="modal-adicionar-cliente" tabindex="-1" role="dialog"
    aria-labelledby="modal-adicionar-cliente-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
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
                            role="tab">Endereços</button>
                    </li>
                </ul>


                <!-- Conteúdo das Abas -->
                <div class="tab-content" id="myTabContent">
                    <!-- Aba de Dados do Cliente -->
                    <div class="tab-pane fade show active" id="dados-cliente" role="tabpanel"
                        aria-labelledby="dados-cliente-tab">
                        <form id="form-cliente">
                            <!-- Campo oculto para o ID do cliente (para edição) -->
                            <input type="hidden" id="ent-codigo" name="ent_codigo">
                            <!-- Campo oculto para o token CSRF -->
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                            <div id="mensagem-cliente" class="mb-3"></div>

                            <div class="mb-3 mt-3">
                                <label for="razao-social" class="form-label">Razão Social / Nome Completo</label>
                                <input type="text" class="form-control" id="razao-social" name="ent_razao_social"
                                    placeholder="Razão Social ou Nome Completo" required>
                            </div>

                            <div class="mb-3">
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

                            <div class="mb-3">
                                <label for="cpf-cnpj" class="form-label" id="label-cpf-cnpj">CPF</label>
                                <input type="text" class="form-control" id="cpf-cnpj" name="ent_cpf_cnpj"
                                    placeholder="000.000.000-00" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Entidade</< /label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                            id="tipo-entidade-cliente" value="Cliente" checked>
                                        <label class="form-check-label" for="tipo-entidade-cliente">Cliente</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                            id="tipo-entidade-fornecedor" value="Fornecedor">
                                        <label class="form-check-label"
                                            for="tipo-entidade-fornecedor">Fornecedor</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                            id="tipo-entidade-ambos" value="Cliente e Fornecedor">
                                        <label class="form-check-label" for="tipo-entidade-ambos">Ambos</label>
                                    </div>
                            </div>

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

                            <hr class="my-4">
                            <h5 class="mb-3">Endereços Cadastrados</h5>
                            <div class="table-responsive">
                                <table id="tabela-enderecos-cliente" class="table table-hover my-4" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>CEP</th>
                                            <th>Logradouro</th>
                                            <th>Número</th>
                                            <th>Bairro</th>
                                            <th>Cidade/UF</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Endereços serão carregados via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>

                    <!-- Aba de Endereços -->
                    <div class="tab-pane fade" id="enderecos" role="tabpanel" aria-labelledby="enderecos-tab">
                        <form id="form-endereco" class="mt-3">
                            <input type="hidden" id="end-codigo" name="end_codigo"> <!-- Para edição de endereço -->
                            <input type="hidden" id="end-entidade-id" name="end_entidade_id">
                            <!-- ID da entidade associada -->
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                            <div id="mensagem-endereco" class="mb-3"></div>

                            <div class="mb-3">
                                <label for="tipo-endereco" class="form-label">Tipo de Endereço</label>
                                <select class="form-select" id="tipo-endereco" name="end_tipo_endereco" required>
                                    <option value="">Selecione o Tipo</option>
                                    <option value="Entrega">Entrega</option>
                                    <option value="Cobranca">Cobrança</option>
                                    <option value="Residencial">Residencial</option>
                                    <option value="Comercial">Comercial</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="cep" class="form-label">CEP</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="cep-endereco" name="end_cep"
                                            placeholder="00000-000">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="btn-buscar-cep-endereco">Buscar CEP</button>
                                    </div>
                                    <small id="cep-feedback-endereco" class="form-text text-muted"></small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="uf" class="form-label">UF</label>
                                    <select class="form-select" id="uf-endereco" name="end_uf">
                                        <option value="">Selecione um Estado</option>
                                        <option value="AC">Acre</option>
                                        <option value="AL">Alagoas</option>
                                        <option value="AP">Amapá</option>
                                        <option value="AM">Amazonas</option>
                                        <option value="BA">Bahia</option>
                                        <option value="CE">Ceará</option>
                                        <option value="DF">Distrito Federal</option>
                                        <option value="ES">Espírito Santo</option>
                                        <option value="GO">Goiás</option>
                                        <option value="MA">Maranhão</option>
                                        <option value="MT">Mato Grosso</option>
                                        <option value="MS">Mato Grosso do Sul</option>
                                        <option value="MG">Minas Gerais</option>
                                        <option value="PA">Pará</option>
                                        <option value="PB">Paraíba</option>
                                        <option value="PR">Paraná</option>
                                        <option value="PE">Pernambuco</option>
                                        <option value="PI">Piauí</option>
                                        <option value="RJ">Rio de Janeiro</option>
                                        <option value="RN">Rio Grande do Norte</option>
                                        <option value="RS">Rio Grande do Sul</option>
                                        <option value="RO">Rondônia</option>
                                        <option value="RR">Roraima</option>
                                        <option value="SC">Santa Catarina</option>
                                        <option value="SP">São Paulo</option>
                                        <option value="SE">Sergipe</option>
                                        <option value="TO">Tocantins</option>
                                    </select>
                                </div>
                            </div>


                            <div class="mb-3">
                                <label for="logradouro" class="form-label">Logradouro</label>
                                <input type="text" class="form-control" id="logradouro-endereco" name="end_logradouro"
                                    placeholder="Rua, Avenida, etc.">
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="numero" class="form-label">Número</label>
                                    <input type="text" class="form-control" id="numero-endereco" name="end_numero"
                                        placeholder="Número">
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label for="complemento" class="form-label">Complemento</label>
                                    <input type="text" class="form-control" id="complemento-endereco"
                                        name="end_complemento" placeholder="Apto, Bloco, etc. (Opcional)">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="bairro" class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="bairro-endereco" name="end_bairro"
                                        placeholder="Bairro">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="cidade" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="cidade-endereco" name="end_cidade"
                                        placeholder="Cidade">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" id="btn-salvar-endereco">Salvar
                                Endereço</button>
                            <button type="button" class="btn btn-secondary"
                                id="btn-cancelar-edicao-endereco">Cancelar</button>
                        </form>
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