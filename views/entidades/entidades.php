<?php
// /views/entidades/lista_entidades.php

// O controlador (index.php) define as variáveis $pageType e $csrf_token.
$is_cliente = ($pageType === 'cliente');
$titulo = $is_cliente ? 'Clientes' : 'Fornecedores';
$singular = $is_cliente ? 'Cliente' : 'Fornecedor';
?>

<h4 class="fw-bold mb-3"><?php echo $titulo; ?></h4>

<a href="#" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-entidade">Adicionar <?php echo $singular; ?></a>

<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">Filtrar por Situação:</label><br>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filtro_situacao" id="filtro-situacao-todos" value="Todos" checked>
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

<div id="feedback-message-area" class="mt-3"></div>

<div class="table-responsive">
    <table id="tabela-entidades" class="table table-hover my-4" style="width:100%">
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

<div class="modal fade" id="modal-entidade" tabindex="-1" role="document" aria-labelledby="modal-entidade-label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-entidade-label">Adicionar <?php echo $singular; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="entidadeTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados-pane" type="button" role="tab">Dados Principais</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="enderecos-tab" data-bs-toggle="tab" data-bs-target="#enderecos-pane" type="button" role="tab">Endereços Adicionais</button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="entidadeTabContent">
                    <div class="tab-pane fade show active" id="dados-pane" role="tabpanel">
                        <form id="form-entidade">
                            <input type="hidden" id="ent-codigo" name="ent_codigo">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                            <div id="mensagem-entidade" class="mb-3"></div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tipo de Entidade</label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade" id="tipo-entidade-cliente" value="Cliente" <?php echo $is_cliente ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="tipo-entidade-cliente">Cliente</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade" id="tipo-entidade-fornecedor" value="Fornecedor" <?php echo !$is_cliente ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="tipo-entidade-fornecedor">Fornecedor</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ent_tipo_entidade" id="tipo-entidade-ambos" value="Cliente e Fornecedor">
                                        <label class="form-check-label" for="tipo-entidade-ambos">Ambos</label>
                                    </div>
                                </div>
                                </div>
                             </form>
                    </div>

                    <div class="tab-pane fade" id="enderecos-pane" role="tabpanel">
                        </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" form="form-entidade" class="btn btn-primary" id="btn-salvar-entidade">Salvar <?php echo $singular; ?></button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-confirmar-exclusao" tabindex="-1" role="document">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-exclusao-label">Confirmar Inativação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja inativar o registro <strong id="nome-excluir"></strong>?</p>
                <p class="text-danger">O registro não será mais exibido nas listagens principais.</p>
                <input type="hidden" id="id-excluir">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-warning" id="btn-confirmar-exclusao">Sim, Inativar</button>
            </div>
        </div>
    </div>
</div>