<?php
// views/estoque/relatorio_kardex.php
// Verificação de segurança (opcional, dependendo do seu router)
if (!isset($_SESSION['codUsuario'])) die("Acesso negado.");
?>

<h4 class="fw-bold mb-3">Kardex - Histórico de Movimentações</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3 bg-light">
        <h6 class="m-0 fw-bold text-primary">Filtros de Pesquisa</h6>
    </div>
    <div class="card-body">
        <form id="form-filtro-kardex">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Lote</label>
                    <input type="text" class="form-control" name="filtro_lote" id="filtro_lote" placeholder="Ex: 3586">
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Produto</label>
                    <input type="text" class="form-control" name="filtro_produto" id="filtro_produto" placeholder="Nome ou Código">
                </div>

                <div class="col-md-2 mb-3">
                    <label class="form-label fw-bold">Data Início</label>
                    <input type="date" class="form-control" name="data_inicio" id="data_inicio" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label fw-bold">Data Fim</label>
                    <input type="date" class="form-control" name="data_fim" id="data_fim" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="col-md-2 mb-3">
                    <label class="form-label fw-bold">Tipo Mov.</label>
                    <select class="form-select" name="filtro_tipo" id="filtro_tipo">
                        <option value="">Todos</option>
                        <option value="ENTRADA">Entrada</option>
                        <option value="SAIDA">Saída</option>
                        <option value="TRANSFERENCIA">Transferência</option>
                        <option value="AJUSTE_INVENTARIO">Ajuste Inventário</option>
                        <option value="PRODUCAO">Produção</option>
                        <option valeu="ALOCACAO">Alocação</option>
                    </select>
                </div>
            </div>

            <div class="text-end">
                <button type="button" id="btn-limpar" class="btn btn-secondary me-2"><i class="fas fa-eraser"></i> Limpar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Pesquisar</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" id="tabela-kardex" width="100%">
                <thead class="table-dark">
                    <tr>
                        <th class="text-center">Data/Hora</th>
                        <th class="text-center">Tipo</th>
                        <th>Produto / Lote</th>
                        <th class="text-center">Origem</th>
                        <th class="text-center">Destino</th>
                        <th class="text-center">Qtd.</th>
                        <th class="text-center">Usuário</th>
                        <th>Obs.</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>