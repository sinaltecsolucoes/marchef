<?php
// fornecedores.php
// Esta página será incluída por index.php, então já terá acesso a $pdo, $_SESSION, $csrf_token, etc.

// O token CSRF já deve estar disponível via $csrf_token do index.php
?>

<h4 class="fw-bold mb-3">Fornecedores</h4>

<a href="#" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-adicionar-fornecedor" id="btn-adicionar-fornecedor-main">Adicionar Fornecedor</a>

<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">Filtrar por Situação:</label><br>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-todos-forn" value="Todos" checked>
            <label class="form-check-label" for="filtro-situacao-todos-forn">Todos</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-ativo-forn" value="A">
            <label class="form-check-label" for="filtro-situacao-ativo-forn">Ativo</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-inativo-forn" value="I">
            <label class="form-check-label" for="filtro-situacao-inativo-forn">Inativo</label>
        </div>
    </div>
</div>

<div id="feedback-message-area-fornecedor" class="mt-3"></div>

<div class="table-responsive">
    <table id="example-fornecedores" class="table table-hover my-4" style="width:100%">
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
            </tbody>
    </table>
</div>

<div class="modal fade" id="modal-adicionar-fornecedor" tabindex="-1" role="dialog" aria-labelledby="modal-adicionar-fornecedor-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-adicionar-fornecedor-label">Adicionar Fornecedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="myTabForn" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dados-fornecedor-tab" data-bs-toggle="tab" data-bs-target="#dados-fornecedor" type="button" role="tab" aria-controls="dados-fornecedor" aria-selected="true">Dados do Fornecedor</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="enderecos-fornecedor-tab" data-bs-toggle="tab" data-bs-target="#enderecos-fornecedor" type="button" role="tab" aria-controls="enderecos-fornecedor" aria-selected="false">Endereços</button>
                    </li>
                </ul>

                <div class="tab-content" id="myTabFornContent">
                    <div class="tab-pane fade show active" id="dados-fornecedor" role="tabpanel" aria-labelledby="dados-fornecedor-tab">
                        <form id="form-fornecedor" class="mt-3">
                            <input type="hidden" id="ent-codigo-fornecedor" name="ent_codigo">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                            <div id="mensagem-fornecedor" class="mb-3"></div>

                            <div class="mb-3">
                                <label for="razao-social-fornecedor" class="form-label">Razão Social / Nome Completo</label>
                                <input type="text" class="form-control" id="razao-social-fornecedor" name="ent_razao_social" placeholder="Razão Social ou Nome Completo" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Pessoa</label><br>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ent_tipo_pessoa" id="tipo-pessoa-fisica-fornecedor" value="F" checked>
                                    <label class="form-check-label" for="tipo-pessoa-fisica-fornecedor">Física</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ent_tipo_pessoa" id="tipo-pessoa-juridica-fornecedor" value="J">
                                    <label class="form-check-label" for="tipo-pessoa-juridica-fornecedor">Jurídica</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="cpf-cnpj-fornecedor" class="form-label" id="label-cpf-cnpj-fornecedor">CPF</label>
                                <input type="text" class="form-control" id="cpf-cnpj-fornecedor" name="ent_cpf_cnpj" placeholder="000.000.000-00" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Entidade</label><br>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ent_tipo_entidade" id="tipo-entidade-cliente-fornecedor" value="Cliente">
                                    <label class="form-check-label" for="tipo-entidade-cliente-fornecedor">Cliente</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ent_tipo_entidade" id="tipo-entidade-fornecedor-fornecedor" value="Fornecedor" checked>
                                    <label class="form-check-label" for="tipo-entidade-fornecedor-fornecedor">Fornecedor</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ent_tipo_entidade" id="tipo-entidade-ambos-fornecedor" value="Cliente e Fornecedor">
                                    <label class="form-check-label" for="tipo-entidade-ambos-fornecedor">Ambos</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="situacao-fornecedor">Situação</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="situacao-fornecedor" name="ent_situacao" value="A" checked>
                                    <label class="form-check-label" for="situacao-fornecedor">
                                        <span id="texto-situacao-fornecedor">Ativo</span>
                                    </label>
                                </div>
                            </div>
                            <hr class="my-4">
                            <h5 class="mb-3">Endereços Cadastrados</h5>
                            <div class="table-responsive">
                                <table id="tabela-enderecos-fornecedor" class="table table-hover my-4" style="width:100%">
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
                                        </tbody>
                                </table>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="enderecos-fornecedor" role="tabpanel" aria-labelledby="enderecos-fornecedor-tab">
                        <form id="form-endereco-fornecedor" class="mt-3">
                            <input type="hidden" id="end-codigo-fornecedor" name="end_codigo"> <input type="hidden" id="end-entidade-id-fornecedor" name="end_entidade_id"> <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                            <div id="mensagem-endereco-fornecedor" class="mb-3"></div>

                            <div class="mb-3">
                                <label for="tipo-endereco-fornecedor" class="form-label">Tipo de Endereço</label>
                                <select class="form-select" id="tipo-endereco-fornecedor" name="end_tipo_endereco" required>
                                    <option value="">Selecione o Tipo</option>
                                    <option value="Entrega">Entrega</option>
                                    <option value="Cobranca">Cobrança</option>
                                    <option value="Residencial">Residencial</option>
                                    <option value="Comercial">Comercial</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="cep-endereco-fornecedor" class="form-label">CEP</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="cep-endereco-fornecedor" name="end_cep" placeholder="00000-000">
                                    <button class="btn btn-outline-secondary" type="button" id="btn-buscar-cep-fornecedor">Buscar CEP</button>
                                </div>
                                <small id="cep-feedback-fornecedor" class="form-text text-muted"></small>
                            </div>

                            <div class="mb-3">
                                <label for="logradouro-endereco-fornecedor" class="form-label">Logradouro</label>
                                <input type="text" class="form-control" id="logradouro-endereco-fornecedor" name="end_logradouro" placeholder="Rua, Avenida, etc.">
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="numero-endereco-fornecedor" class="form-label">Número</label>
                                    <input type="text" class="form-control" id="numero-endereco-fornecedor" name="end_numero" placeholder="Número">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="complemento-endereco-fornecedor" class="form-label">Complemento</label>
                                    <input type="text" class="form-control" id="complemento-endereco-fornecedor" name="end_complemento" placeholder="Apto, Bloco, etc. (Opcional)">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="bairro-endereco-fornecedor" class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="bairro-endereco-fornecedor" name="end_bairro" placeholder="Bairro">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="cidade-endereco-fornecedor" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="cidade-endereco-fornecedor" name="end_cidade" placeholder="Cidade">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="uf-endereco-fornecedor" class="form-label">UF</label>
                                <select class="form-select" id="uf-endereco-fornecedor" name="end_uf">
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
                            <button type="submit" class="btn btn-primary" id="btn-salvar-endereco-fornecedor">Salvar Endereço</button>
                            <button type="button" class="btn btn-secondary" id="btn-cancelar-edicao-endereco-fornecedor" style="display:none;">Cancelar</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" form="form-fornecedor" class="btn btn-primary" id="btn-submit-fornecedor-modal">Salvar Fornecedor</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-confirmar-exclusao-fornecedor" tabindex="-1" role="dialog" aria-labelledby="modal-confirmar-exclusao-fornecedor-label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-confirmar-exclusao-fornecedor-label">Confirmar Exclusão de Fornecedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o fornecedor <strong id="nome-fornecedor-excluir"></strong>?</p>
                <p class="text-danger">Esta ação é irreversível e excluirá também todos os endereços associados!</p>
                <input type="hidden" id="id-fornecedor-excluir">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao-fornecedor">Sim, Excluir</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-confirmar-exclusao-endereco-fornecedor" tabindex="-1" role="dialog" aria-labelledby="modal-confirmar-exclusao-endereco-fornecedor-label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-confirmar-exclusao-endereco-fornecedor-label">Confirmar Exclusão de Endereço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir este endereço?</p>
                <p class="text-danger">Esta ação é irreversível!</p>
                <input type="hidden" id="id-endereco-excluir-fornecedor">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao-endereco-fornecedor">Sim, Excluir</button>
            </div>
        </div>
    </div>
</div>