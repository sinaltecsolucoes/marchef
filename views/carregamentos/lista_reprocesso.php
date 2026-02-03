<?php // /views/carregamentos/lista_reprocesso.php
?>

<h4 class="fw-bold mb-3">Saída por Reprocesso</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <p>Gestão de saídas exclusivas para reprocesso interno.</p>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modal-nova-saida-reprocesso">
                    <i class="fas fa-sync-alt me-2"></i> Criar Nova Saída Reprocesso
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Saídas para Reprocesso</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-reprocesso" class="table table-bordered table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Nº Saída</th>
                        <th class="text-center align-middle">Data</th>
                        <th class="text-center align-middle">Ordem de Expedição</th>
                        <th class="text-center align-middle">Status</th>
                        <th class="text-center align-middle">Ações</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-nova-saida-reprocesso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form-nova-saida-reprocesso">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Saída por Reprocesso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>"> 
                    <input type="hidden" name="car_tipo" value="REPROCESSO">
                    <div class="mb-3">
                        <label class="form-label">Número do Registro</label>
                        <input type="text" name="car_numero_repro" id="car_numero_repro" class="form-control" placeholder="Automático">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data</label>
                        <input type="date" name="car_data_repro" id="car_data_repro" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cliente Destino</label>
                        <input type="text" class="form-control" value="CLIENTE INTERNO" readonly>
                        <input type="hidden" name="car_entidade_id_organizador" value="3850">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ordem de Expedição (Reprocesso)</label>
                        <select name="car_ordem_expedicao_id" id="select-oe-reprocesso" class="form-select" required>
                            <option value="">Selecione uma OE...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Saída</button>
                </div>
            </form>
        </div>
    </div>
</div>