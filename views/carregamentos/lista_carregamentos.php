<?php // /views/carregamentos/lista_carregamentos.php ?>

<h4 class="fw-bold mb-3">Gestão de Carregamentos</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <p>Gerencie todos os carregamentos</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-novo-carregamento">
                    <i class="fas fa-plus me-2"></i> Criar Novo Carregamento
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Carregamentos</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-carregamentos" class="table table-bordered table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Nº Carregamento</th>
                        <th class="text-center align-middle">Data</th>
                        <th class="text-center align-middle">Ordem de Expedição</th>
                        <th class="text-center align-middle">Motorista</th>
                        <th class="text-center align-middle">Placas</th>
                        <th class="text-center align-middle">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-novo-carregamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Carregamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-novo-carregamento">
                <input type="hidden" id="car_tipo" name="car_tipo" value="AVULSA">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="car_numero" class="form-label">Nº Carregamento</label>
                            <input type="text" class="form-control" id="car_numero" name="car_numero" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="car_data" class="form-label">Data</label>
                            <input type="date" class="form-control" id="car_data" name="car_data"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="car_entidade_id_organizador" class="form-label">Cliente Responsável</label>
                            <select class="form-select" id="car_entidade_id_organizador"
                                name="car_entidade_id_organizador" style="width: 100%;" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="car_transportadora_id" class="form-label">Transportadora</label>
                            <select class="form-select" id="car_transportadora_id" name="car_transportadora_id"
                                style="width: 100%;"></select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="car_ordem_expedicao_id" class="form-label">Ordem de Expedição (Base)</label>
                        <select class="form-select" id="car_ordem_expedicao_id" name="car_ordem_expedicao_id"
                            style="width: 100%;" required></select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="car_motorista_nome" class="form-label">Nome Motorista</label>
                            <input type="text" class="form-control" id="car_motorista_nome" name="car_motorista_nome">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="car_motorista_cpf" class="form-label">CPF Motorista</label>
                            <input type="text" class="form-control" id="car_motorista_cpf" name="car_motorista_cpf">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="car_placas" class="form-label">Placa(s)</label>
                            <input type="text" class="form-control" id="car_placas" name="car_placas">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="car_lacres" class="form-label">Lacre(s)</label>
                            <input type="text" class="form-control" id="car_lacres" name="car_lacres">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="btn-salvar-carregamento"><i
                            class="fas fa-save me-2"></i>Salvar e Iniciar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                            class="fas fa-times me-2"></i>Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-reabrir-motivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reabrir Carregamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Você está prestes a reabrir o carregamento <strong id="reabrir-carregamento-numero"></strong>.</p>
                <p>O estoque baixado (se houver) será estornado.</p>
                <input type="hidden" id="reabrir-carregamento-id">
                <div class="mb-3">
                    <label for="reabrir-motivo" class="form-label">Motivo da Reabertura (Obrigatório)</label>
                    <textarea class="form-control" id="reabrir-motivo" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btn-confirmar-reabertura">Confirmar
                    Reabertura</button>
            </div>
        </div>
    </div>
</div>