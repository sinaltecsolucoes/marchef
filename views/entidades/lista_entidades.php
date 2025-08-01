<?php
// /views/entidades/lista_entidades.php
// View unificada para gestão de Clientes e Fornecedores

// O controlador (index.php) define as variáveis $pageType e $csrf_token.
$is_cliente = ($pageType === 'cliente');
$titulo = $is_cliente ? 'Clientes' : 'Fornecedores';
$singular = $is_cliente ? 'Cliente' : 'Fornecedor';
?>

<h4 class="fw-bold mb-3"><?php echo $titulo; ?></h4>

<a href="#" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-adicionar-entidade"
    id="btn-adicionar-entidade">Adicionar <?php echo $singular; ?></a>

<div class="row mb-3">
    <div class="col-md-6">
        <label for="filtro-tipo-entidade" class="form-label">Filtrar por Tipo Específico:</label>
        <select class="form-select" id="filtro-tipo-entidade">
            <option value="Todos">Todos (<?php echo $titulo; ?> e Ambos)</option>

            <?php if ($pageType === 'cliente'): // Se estiver na página de Clientes ?>
                <option value="Cliente">Apenas Clientes</option>
            <?php else: // Se estiver na página de Fornecedores ?>
                <option value="Fornecedor">Apenas Fornecedores</option>
            <?php endif; ?>

            <option value="Cliente e Fornecedor">Clientes e Fornecedores</option>
        </select>
    </div>
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


<div id="feedback-message-area-entidade" class="mt-3"></div>

<div class="table-responsive">
    <table id="tabela-entidades" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th class="text-center">Situação</th>
                <th class="text-center">Tipo</th>
                <th class="text-center">Código Interno</th>
                <th class="text-center">Razão Social</th>
                <th class="text-center">CPF/CNPJ</th>
                <th class="text-center">Endereço Principal</th>
                <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<div class="modal fade" id="modal-adicionar-entidade" tabindex="-1" aria-labelledby="modal-adicionar-entidade-label"
    aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-adicionar-entidade-label">Adicionar <?php echo $singular; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="entidadeTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados-pane"
                            type="button" role="tab">Dados Principais</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="enderecos-tab" data-bs-toggle="tab"
                            data-bs-target="#enderecos-pane" type="button" role="tab">Endereços Adicionais</button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="entidadeTabContent">
                    <div class="tab-pane fade show active" id="dados-pane" role="tabpanel">
                        <form id="form-entidade" class="mt-3">
                            <input type="hidden" id="ent-codigo" name="ent_codigo">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                            <div id="mensagem-entidade" class="mb-3"></div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tipo de Entidade</label><br>
                                    <?php if ($pageType === 'cliente'): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                                id="tipo-entidade-cliente" value="Cliente" checked>
                                            <label class="form-check-label" for="tipo-entidade-cliente">Cliente</label>
                                        </div>
                                    <?php else: ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                                id="tipo-entidade-fornecedor" value="Fornecedor" checked>
                                            <label class="form-check-label"
                                                for="tipo-entidade-fornecedor">Fornecedor</label>
                                        </div>
                                    <?php endif; ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade"
                                            id="tipo-entidade-ambos" value="Cliente e Fornecedor">
                                        <label class="form-check-label" for="tipo-entidade-ambos">Ambos</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3" id="div-situacao-entidade">
                                    <label class="form-label" for="situacao-entidade">Situação</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                            id="situacao-entidade" name="ent_situacao" value="A" checked>
                                        <label class="form-check-label" for="situacao-entidade"><span
                                                id="texto-situacao-entidade">Ativo</span></label>
                                    </div>
                                </div>
                            </div>

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
                                <div class="col-md-5 mb-3">
                                    <label for="cpf-cnpj" class="form-label" id="label-cpf-cnpj">CPF</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="cpf-cnpj" name="ent_cpf_cnpj"
                                            placeholder="000.000.000-00" required>
                                        <button class="btn btn-outline-secondary" type="button" id="btn-buscar-cnpj"
                                            style="display: none;">Buscar Dados</button>
                                    </div>
                                    <small id="cnpj-feedback" class="form-text text-muted"></small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="codigo-interno" class="form-label">Código Interno</label>
                                    <input type="text" class="form-control" id="codigo-interno"
                                        name="ent_codigo_interno" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="razao-social" class="form-label">Razão Social / Nome Completo</label>
                                    <input type="text" class="form-control" id="razao-social" name="ent_razao_social"
                                        required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nome-fantasia" class="form-label">Nome Fantasia / Apelido</label>
                                    <input type="text" class="form-control" id="nome-fantasia" name="ent_nome_fantasia">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3" id="div-inscricao-estadual">
                                    <label for="inscricao-estadual" class="form-label">Inscrição Estadual</label>
                                    <input type="text" class="form-control" id="inscricao-estadual"
                                        name="ent_inscricao_estadual">
                                </div>
                            </div>

                            <hr>
                            <h5 class="mb-3">Endereço Principal</h5>

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
                        </form>
                    </div>
                    <div class="tab-pane fade" id="enderecos-pane" role="tabpanel">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <form id="form-endereco-adicional" class="mt-3 bg-light p-3 rounded border mb-4">
                                    <input type="hidden" id="end-codigo" name="end_codigo">
                                    <input type="hidden" id="end-entidade-id" name="end_entidade_id">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                                    <div id="mensagem-endereco" class="mb-3"></div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="tipo-endereco" class="form-label">Tipo de Endereço</label>
                                            <select class="form-select" id="tipo-endereco" name="end_tipo_endereco"
                                                required>
                                                <option value="Principal">Principal</option>
                                                <option value="Comercial">Comercial</option>
                                                <option value="Entrega">Entrega</option>
                                                <option value="Cobrança">Cobrança</option>
                                                <option value="Outros">Outros</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="cep-endereco-adicional" class="form-label">CEP</label>
                                            <input type="text" class="form-control" id="cep-endereco-adicional"
                                                name="end_cep" placeholder="00000-000">
                                        </div>
                                        <div class="col-md-4 mb-3 d-flex align-items-end">
                                            <button type="button" class="btn btn-outline-secondary"
                                                id="btn-buscar-cep-adicional">Buscar CEP</button>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label for="logradouro-endereco-adicional"
                                                class="form-label">Logradouro</label>
                                            <input type="text" class="form-control" id="logradouro-endereco-adicional"
                                                name="end_logradouro" placeholder="Rua, Avenida, etc.">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="numero-endereco-adicional" class="form-label">Número</label>
                                            <input type="text" class="form-control" id="numero-endereco-adicional"
                                                name="end_numero" placeholder="Número">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="complemento-endereco-adicional"
                                                class="form-label">Complemento</label>
                                            <input type="text" class="form-control" id="complemento-endereco-adicional"
                                                name="end_complemento" placeholder="Apto, Bloco, etc. (Opcional)">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="bairro-endereco-adicional" class="form-label">Bairro</label>
                                            <input type="text" class="form-control" id="bairro-endereco-adicional"
                                                name="end_bairro" placeholder="Bairro">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="cidade-endereco-adicional" class="form-label">Cidade</label>
                                            <input type="text" class="form-control" id="cidade-endereco-adicional"
                                                name="end_cidade" placeholder="Cidade">
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label for="uf-endereco-adicional" class="form-label">UF</label>
                                            <input type="text" class="form-control" id="uf-endereco-adicional"
                                                name="end_uf" maxlength="2">
                                        </div>
                                        <div class="col-md-4 mb-3 d-flex align-items-end">
                                            <label class="form-label"> </label>
                                            <button type="submit" class="btn btn-primary mt-2 me-2"
                                                id="btn-    alvar-endereco">Salvar Endereço Adicional</button>
                                            <button type="button" class="btn btn-secondary mt-2"
                                                id="btn-cancelar-edicao-endereco">Cancelar</button>
                                        </div>
                                    </div>
                                    <small id="cep-feedback-adicional" class="form-text text-muted"></small>
                                </form>
                            </div>
                            <div class="col-12">
                                <table id="tabela-enderecos-adicionais" class="table table-hover" style="width:100%">
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
            </div>

            <div class="modal-footer">
                <button type="submit" form="form-entidade" class="btn btn-primary" id="btn-salvar-entidade">Salvar
                    <?php echo $singular; ?></button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>

    </div>

    <div class="modal fade" id="modal-confirmar-inativacao" tabindex="-1" role="document">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Inativação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja inativar o registro <strong id="nome-inativar"></strong>?</p>
                    <p class="text-danger">O registro não será mais exibido nas listagens principais.</p>
                    <input type="hidden" id="id-inativar">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                    <button type="button" class="btn btn-warning" id="btn-confirmar-inativacao">Sim, Inativar</button>
                </div>
            </div>
        </div>
    </div>
</div>