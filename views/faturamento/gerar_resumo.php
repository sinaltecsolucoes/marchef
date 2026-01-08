<?php
// /views/faturamento/gerar_resumo.php 

// Verificamos se um ID de resumo foi passado pela URL. Isso define o modo da página.
$modoEdicao = isset($_GET['resumo_id']) && !empty($_GET['resumo_id']);
?>

<h4 class="fw-bold mb-3">Gerar Resumo para Faturamento</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">1. Selecionar Ordem de Expedição</h6>

        <?php if (!$modoEdicao): // Só mostra este botão se NÃO estiver em modo de edição (modo criação) 
        ?>
            <a href="index.php?page=faturamentos_listar" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Voltar para a Lista
            </a>
        <?php endif; ?>

    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <label for="select-ordem-expedicao" class="form-label">Selecione uma Ordem de Expedição para
                    processar:</label>
                <select id="select-ordem-expedicao" class="form-select"></select>
            </div>
            <div class="col-md-6 align-self-end text-end" id="container-btn-gerar" style="display: none;">
                <button class="btn btn-success" id="btn-gerar-resumo">
                    <i class="fas fa-check me-2"></i> Confirmar e Gerar Resumo
                </button>
            </div>
        </div>
    </div>
</div>

<div id="card-transporte" class="card shadow mb-4 card-custom" style="display: none;">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Dados de Transporte</h6>
        <div>
            <?php if ($modoEdicao): ?>
                <a href="index.php?page=relatorio_faturamento_excel&id=<?php echo htmlspecialchars($_GET['resumo_id']); ?>"
                    id="btn-exportar-excel-link" class="btn btn-success" target="_blank">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </a>
                <button id="btn-gerar-relatorio" class="btn btn-info">
                    <i class="fas fa-print"></i> Imprimir Relatório
                </button>
                <a href="index.php?page=faturamentos_listar" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar para a Lista
                </a>
            <?php endif; ?>
        </div>
    </div>


    <div class="card-body">
        <form id="form-transporte">
            <input type="hidden" name="fat_resumo_id" id="fat_resumo_id_transporte">
            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="select-transportadora" class="form-label">Transportadora</label>
                    <select id="select-transportadora" name="fat_transportadora_id" class="form-select"
                        style="width: 100%;"></select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fat_motorista_nome" class="form-label">Nome do Motorista</label>
                    <input type="text" class="form-control" id="fat_motorista_nome" name="fat_motorista_nome">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="fat_motorista_cpf" class="form-label">CPF do Motorista</label>
                    <input type="text" class="form-control" id="fat_motorista_cpf" name="fat_motorista_cpf">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fat_veiculo_placa" class="form-label">Placa do Veículo</label>
                    <input type="text" class="form-control" id="fat_veiculo_placa" name="fat_veiculo_placa">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Dados de Transporte</button>
        </form>
    </div>
    <div class="card-body">
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="m-0 font-weight-bold text-primary">2. Resumo Agrupado</h6>
            <span id="ordem-origem-display" class="text-muted small" style="display: none;"></span>
        </div>

    </div>
    <div class="card-body">
        <div id="faturamento-resultado-container">
            <p class="text-muted text-center">Selecione uma Ordem de Expedição acima para começar.</p>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-editar-faturamento" tabindex="-1" aria-labelledby="modal-editar-faturamento-label"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-editar-faturamento-label">Adicionar Preço e Observação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-editar-faturamento">
                <div class="modal-body">
                    <input type="hidden" id="edit_fati_id" name="fati_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <p class="text-muted"><strong>Produto:</strong> <span id="display-produto" class="text-dark"></span>
                    </p>
                    <p class="text-muted"><strong>Lote:</strong> <span id="display-lote" class="text-dark"></span>
                    </p>
                    <hr>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_fati_preco_unitario" class="form-label">Preço Unitário</label>
                            <input type="number" class="form-control" id="edit_fati_preco_unitario"
                                name="fati_preco_unitario" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_fati_preco_unidade_medida" class="form-label">Unidade de Medida</label>
                            <select class="form-select" id="edit_fati_preco_unidade_medida"
                                name="fati_preco_unidade_medida">
                                <option value="KG">por KG</option>
                                <option value="CX">por Caixa</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-editar-nota-grupo" tabindex="-1" aria-labelledby="modal-editar-nota-label"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-editar-nota-label">Editar Dados do Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-editar-nota-grupo">
                <div class="modal-body">
                    <input type="hidden" id="edit_fatn_id" name="fatn_id">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Cliente:</label>
                            <p id="display-nota-cliente" class="form-control-plaintext bg-light p-2 rounded"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nº Pedido Cliente:</label>
                            <p id="display-nota-pedido" class="form-control-plaintext bg-light p-2 rounded"></p>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label for="edit_fatn_condicao_pag_id" class="form-label">Condição de Pagamento</label>
                        <select class="form-select" id="edit_fatn_condicao_pag_id" name="fatn_condicao_pag_id"
                            style="width: 100%;"></select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_fatn_endereco_id" class="form-label">Endereço de Entrega (Local de Entrega)</label>
                        <select class="form-select" id="edit_fatn_endereco_id" name="fatn_endereco_id" style="width: 100%;">
                            <option value="">Endereço Principal (Padrão)</option>
                        </select>
                        <div class="form-text">Selecione o endereço de entrega cadastrado para este cliente.</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_fatn_observacao" class="form-label">Observação (para esta Nota/Pedido)</label>
                        <textarea class="form-control" id="edit_fatn_observacao" name="fatn_observacao"
                            rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações da Nota</button>
                </div>
            </form>
        </div>
    </div>
</div>