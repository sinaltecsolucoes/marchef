<?php
// /views/auditoria/visualizar_logs.php
?>

<h4 class="fw-bold mb-3">Logs de Auditoria do Sistema</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filtrar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-12 col-md-3 mb-3 mb-md-0">
                <label for="filtro_data_inicio">Data Início:</label>
                <input type="date" id="filtro_data_inicio" class="form-control">
            </div>
            <div class="col-12 col-md-3 mb-3 mb-md-0">
                <label for="filtro_data_fim">Data Fim:</label>
                <input type="date" id="filtro_data_fim" class="form-control">
            </div>
            <div class="col-12 col-md-3 mb-3 mb-md-0">
                <label for="filtro_usuario_id">Utilizador:</label>
                <select id="filtro_usuario_id" class="form-select">
                    <option value="">Todos os Utilizadores</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-grid d-md-flex align-items-end gap-2 mt-3 mt-md-0">
                <button id="btn_filtrar" class="btn btn-primary w-100 w-md-auto">Filtrar</button>
                <button id="btn_limpar" class="btn btn-secondary w-100 w-md-auto">Limpar</button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Histórico de Ações</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="tabelaLogs" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Utilizador</th>
                        <th>Ação</th>
                        <th>Tabela</th>
                        <th>ID do Registo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalhesLog" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Detalhes da Alteração</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Dados Antigos</h6>
                        <pre id="dados_antigos_content" class="bg-light p-2 rounded"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>Dados Novos</h6>
                        <pre id="dados_novos_content" class="bg-light p-2 rounded"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>