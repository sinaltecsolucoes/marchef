<?php
// /views/carregamentos/lista_carregamentos.php
?>

<h4 class="fw-bold mb-3">Gestão de Carregamentos</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Novo Carregamento</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <button class="btn btn-primary" id="btn-novo-carregamento">
                    <i class="fas fa-plus me-2"></i> Novo Carregamento
                </button>
            </div>
            <div class="col-md-6 d-flex justify-content-md-end align-items-center">
                <label for="filtro-status-carregamento" class="form-label me-2 mb-0">Status:</label>
                <select class="form-select w-auto" id="filtro-status-carregamento">
                    <option value="Todos" selected>Todos</option>
                    <option value="EM ANDAMENTO">Em Andamento</option>
                    <option value="AGUARDANDO CONFERENCIA">Aguardando Conferência</option>
                    <option value="FINALIZADO">Finalizado</option>
                    <option value="CANCELADO">Cancelado</option>
                </select>
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
            <table class="table table-bordered" id="tabela-carregamentos" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Organizador/Cliente</th>
                        <th class="text-center">Data</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-carregamento" tabindex="-1" aria-labelledby="modal-carregamento-label"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-carregamento-label">Novo Carregamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-carregamento">
                <div class="modal-body">
                    <input type="hidden" id="car_id" name="car_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div id="mensagem-carregamento-modal" class="mb-3"></div>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="car_numero" class="form-label">Número <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="car_numero" name="car_numero" required>
                        </div>
                        <div class="col-md-3">
                            <label for="car_data" class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="car_data" name="car_data" required>
                        </div>
                        <div class="col-md-6">
                            <label for="car_entidade_id_organizador" class="form-label">Cliente Organizador <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="car_entidade_id_organizador"
                                name="car_entidade_id_organizador" required style="width: 100%;"></select>
                        </div>
                        <div class="col-md-4">
                            <label for="car_placa_veiculo" class="form-label">Placa do Veículo</label>
                            <input type="text" class="form-control" id="car_placa_veiculo" name="car_placa_veiculo">
                        </div>
                        <div class="col-md-4">
                            <label for="car_lacre" class="form-label">Nº do Lacre</label>
                            <input type="text" class="form-control" id="car_lacre" name="car_lacre">
                        </div>
                        <div class="col-md-4">
                            <label for="car_ordem_expedicao" class="form-label">Ordem de Expedição</label>
                            <input type="text" class="form-control" id="car_ordem_expedicao" name="car_ordem_expedicao">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary">Salvar e Continuar</button>
                </div>
            </form>
        </div>
    </div>
</div>
