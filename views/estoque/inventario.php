<?php
// /views/estoque/inventario.php
?>
<h4 class="fw-bold mb-3">Inventário de Estoque</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Importação de Lotes via CSV</h6>
    </div>

    <div class="card-body">
        <div class="alert alert-info">
            <strong>Instruções:</strong> O arquivo CSV deve conter as colunas na ordem:
            <small>Lote Completo, Data Fab., Nome Fantasia, Cód. Interno, Descrição, Qtd Cx, Endereço.</small>
        </div>

        <form id="form-importar-inventario" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <input type="file" name="arquivo_csv" class="form-control" accept=".csv" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Processar Inventário
                    </button>
                </div>
            </div>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"> 
        </form>
    </div>
</div>

<div id="resultado-importacao" style="display: none;">
    <div class="card border-danger mb-4">
        <div class="card-header bg-danger text-white">Itens NÃO Importados (Falhas)</div>
        <div class="card-body">
            <table class="table table-sm table-hover" id="tabela-erros">
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Motivo da Falha</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>