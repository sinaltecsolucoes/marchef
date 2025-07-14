<a href="#" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-adicionar-cliente" id="btn-adicionar-cliente-main">
    Adicionar Cliente
</a>

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
        </tbody>
    </table>
</div>

<!-- Modal Adicionar Cliente -->
<div class="modal fade" id="modal-adicionar-cliente" tabindex="-1" role="dialog" aria-labelledby="modal-adicionar-cliente-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-adicionar-cliente-label">Adicionar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="form-cliente">
                    <input type="hidden" id="ent-codigo" name="ent_codigo">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                    <div class="form-group">
                        <label for="razao-social">Razão Social / Nome Completo</label>
                        <input type="text" class="form-control" id="razao-social" name="ent_razao_social" required>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Pessoa</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="ent_tipo_pessoa" id="tipo-pessoa-fisica" value="F" checked>
                            <label class="form-check-label" for="tipo-pessoa-fisica">Física</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="ent_tipo_pessoa" id="tipo-pessoa-juridica" value="J">
                            <label class="form-check-label" for="tipo-pessoa-juridica">Jurídica</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="cpf-cnpj" id="label-cpf-cnpj">CPF</label>
                        <input type="text" class="form-control" id="cpf-cnpj" name="ent_cpf_cnpj" required>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Entidade</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="ent_tipo_entidade" value="Cliente" checked>
                            <label class="form-check-label">Cliente</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="ent_tipo_entidade" value="Fornecedor">
                            <label class="form-check-label">Fornecedor</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="ent_tipo_entidade" value="Cliente e Fornecedor">
                            <label class="form-check-label">Ambos</label>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label for="situacao-cliente">Situação</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="situacao-cliente" name="ent_situacao" value="A" checked>
                            <label class="form-check-label" for="situacao-cliente">Ativo</label>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Endereço Principal</h5>

                    <div class="form-group">
                        <label for="cep">CEP</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="cep" name="end_cep">
                            <button class="btn btn-outline-secondary" type="button" id="btn-buscar-cep">Buscar CEP</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="logradouro">Logradouro</label>
                        <input type="text" class="form-control" id="logradouro" name="end_logradouro">
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <label for="numero">Número</label>
                            <input type="text" class="form-control" id="numero" name="end_numero">
                        </div>
                        <div class="col-md-8">
                            <label for="complemento">Complemento</label>
                            <input type="text" class="form-control" id="complemento" name="end_complemento">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="bairro">Bairro</label>
                            <input type="text" class="form-control" id="bairro" name="end_bairro">
                        </div>
                        <div class="col-md-6">
                            <label for="cidade">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="end_cidade">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="uf">UF</label>
                        <select class="form-select" id="uf" name="end_uf">
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
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="submit" form="form-cliente" class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="modal-confirmar-exclusao-cliente" tabindex="-1" role="dialog" aria-labelledby="modal-confirmar-exclusao-cliente-label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-confirmar-exclusao-cliente-label">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o cliente <strong id="nome-cliente-excluir"></strong>?</p>
                <p class="text-danger">Esta ação é irreversível e excluirá todos os dados associados!</p>
                <input type="hidden" id="id-cliente-excluir">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao-cliente">Sim, Excluir</button>
            </div>
        </div>
    </div>
</div>


