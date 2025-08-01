<?php
// /views/lotes/lista_lotes.php
// O controlador (index.php) já fornece a variável $csrf_token.
?>

<h4 class="fw-bold mb-3">Gestão de Lotes de Produção</h4>

<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-lote" id="btn-adicionar-lote-main">
    Adicionar Novo Lote
</button>

<div id="feedback-message-area-lote" class="mt-3"></div>

<div class="table-responsive">
    <table id="tabela-lotes" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th class="text-center">Lote Completo</th>
                <th class="text-center">Fornecedor</th>
                <th class="text-center">Data Fabricação</th>
                <th class="text-center">Status</th>
                <th class="text-center">Data Cadastro</th>
                <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modal-lote" tabindex="-1" aria-labelledby="modal-lote-label" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-lote-label">Adicionar Novo Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="lote-tabs-modal" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="aba-info-lote-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-info-lote" type="button" role="tab">1. Informações do Lote</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="aba-add-produtos-tab" data-bs-toggle="tab"
                            data-bs-target="#aba-add-produtos" type="button" role="tab">2. Incluir Produtos</button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="lote-tabs-content-modal">
                    <div class="tab-pane fade show active" id="aba-info-lote" role="tabpanel">
                        <form id="form-lote-header">
                            <input type="hidden" id="lote_id" name="lote_id">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                            <div id="mensagem-lote-header" class="mb-3"></div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-2">
                                    <label for="lote_numero" class="form-label">Número <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lote_numero" name="lote_numero">
                                </div>
                                <div class="col-md-3">
                                    <label for="lote_data_fabricacao" class="form-label">Data de Fabricação <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="lote_data_fabricacao"
                                        name="lote_data_fabricacao">
                                </div>
                                <div class="col-md-7">
                                    <label for="lote_cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                    <select class="form-select" id="lote_cliente_id" name="lote_cliente_id"></select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-7">
                                    <label for="lote_fornecedor_id" class="form-label">Fornecedor <span class="text-danger">*</span></label>
                                    <select class="form-select" id="lote_fornecedor_id"
                                        name="lote_fornecedor_id"></select>
                                </div>
                                <div class="col-md-2">
                                    <label for="lote_viveiro" class="form-label">Viveiro</label>
                                    <input type="text" class="form-control" id="lote_viveiro" name="lote_viveiro">
                                </div>
                                <div class="col-md-3">
                                    <label for="lote_ciclo" class="form-label">Ciclo</label>
                                    <input type="text" class="form-control" id="lote_ciclo" name="lote_ciclo">
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label for="lote_completo_calculado" class="form-label">Lote Completo
                                        (Automático) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lote_completo_calculado"
                                        name="lote_completo_calculado">
                                </div>
                            </div>

                        </form>
                        <hr class="my-4">
                        <h6>Produtos Incluídos neste Lote</h6>
                        <div id="lista-produtos-deste-lote" class="table-responsive"></div>
                    </div>

                    <div class="tab-pane fade" id="aba-add-produtos" role="tabpanel">
                        <form id="form-adicionar-produto" class="p-3 mb-3 bg-light border rounded">
                            <input type="hidden" id="item_id" name="item_id">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                            <div id="mensagem-add-produto" class="mb-3"></div>

                            <div class="row g-3 align-items-end">
                                <div class="col-12 mb-2">
                                    <label class="form-label fw-bold">Filtrar produtos por embalagem:</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="filtro_tipo_embalagem"
                                                id="filtro-todos" value="Todos" checked>
                                            <label class="form-check-label" for="filtro-todos">Todos</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="filtro_tipo_embalagem"
                                                id="filtro-primaria" value="PRIMARIA">
                                            <label class="form-check-label" for="filtro-primaria">Primária</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="filtro_tipo_embalagem"
                                                id="filtro-secundaria" value="SECUNDARIA">
                                            <label class="form-check-label" for="filtro-secundaria">Secundária</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-7"><label for="item_produto_id"
                                        class="form-label">Produto</label><select class="form-select"
                                        id="item_produto_id" name="item_produto_id"></select>
                                </div>
                                <div class="col-md-2"><label for="item_quantidade"
                                        class="form-label">Quantidade</label><input type="number" step="0.001"
                                        class="form-control" id="item_quantidade" name="item_quantidade">
                                </div>
                                <div class="col-md-3">
                                    <label for="item_peso_total" class="form-label">Peso Total (kg)</label>
                                    <input type="text" class="form-control" id="item_peso_total" readonly
                                        style="font-weight: bold;">
                                </div>
                                <div class="col-md-4"><label for="item_data_validade"
                                        class="form-label">Validade</label><input type="date" class="form-control"
                                        id="item_data_validade" name="item_data_validade" readonly>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-check form-switch mt-4 pt-2"><input class="form-check-input"
                                            type="checkbox" id="liberar_edicao_validade"><label class="form-check-label"
                                            for="liberar_edicao_validade">Editar validade manualmente</label>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <button type="button" id="btn-incluir-produto" class="btn btn-success">Incluir Produto</button>
                        <button type="button" id="btn-cancelar-inclusao" class="btn btn-secondary">Limpar</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" id="btn-salvar-lote" class="btn btn-primary">Salvar Cabeçalho</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modal-confirmar-finalizar-lote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Confirmar Finalização de Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Você tem certeza que deseja finalizar o lote <strong id="nome-lote-finalizar"></strong>?</p>
                <p class="fw-bold">Esta ação irá:</p>
                <ul>
                    <li>Alterar o status do lote para "FINALIZADO".</li>
                    <li>Adicionar todos os itens deste lote ao estoque.</li>
                </ul>
                <p class="text-warning">Esta ação não poderá ser desfeita facilmente.</p>
                <input type="hidden" id="id-lote-finalizar">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não, cancelar</button>
                <button type="button" class="btn btn-success" id="btn-confirmar-finalizar">Sim, Finalizar e Gerar
                    Estoque</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-confirmar-exclusao-lote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Exclusão de Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o lote <strong id="nome-lote-excluir"></strong>?</p>
                <p class="text-danger fw-bold">Atenção: Esta ação é irreversível e excluirá também todos os produtos
                    associados a este lote!</p>
                <input type="hidden" id="id-lote-excluir">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não, cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao-lote">Sim, Excluir</button>
            </div>
        </div>
    </div>
</div>