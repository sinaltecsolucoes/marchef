$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $selectOrdem = $('#select-ordem-expedicao');
    const $container = $('#faturamento-resultado-container');
    const $btnGerarContainer = $('#container-btn-gerar');
    const $btnGerar = $('#btn-gerar-resumo');
    const $modalEditar = $('#modal-editar-faturamento');
    const $formEditar = $('#form-editar-faturamento');

    $selectOrdem.select2({
        placeholder: "Selecione uma Ordem...",
        theme: "bootstrap-5",
        ajax: {
            url: "ajax_router.php?action=getOrdensParaFaturamentoSelect",
            dataType: 'json',
            delay: 250,
            processResults: function (data) { return data; },
            cache: true
        }
    });

    $selectOrdem.on('change', function () {
        const ordemId = $(this).val();
        $btnGerarContainer.hide();
        if (!ordemId) {
            $container.html('<p class="text-muted text-center">Selecione uma Ordem de Expedição acima para começar.</p>');
            return;
        }
        carregarPreview(ordemId);
    });

    $btnGerar.on('click', function () {
        const ordemId = $selectOrdem.val();
        if (!ordemId) {
            notificacaoErro('Erro', 'Nenhuma Ordem de Expedição selecionada.');
            return;
        }
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Salvando...');

        $.ajax({
            url: 'ajax_router.php?action=salvarResumoFaturamento',
            type: 'POST',
            data: { ordem_id: ordemId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    notificacaoSucesso('Sucesso!', response.message);
                    $btnGerar.hide();
                    $selectOrdem.prop('disabled', true);
                    carregarResumoSalvo(response.resumo_id);
                } else {
                    notificacaoErro('Erro ao Salvar', response.message);
                    $btnGerar.prop('disabled', false).html('<i class="fas fa-check me-2"></i> Confirmar e Gerar Resumo');
                }
            }
        });
    });

    function carregarPreview(ordemId) {
        $container.html('<p class="text-center">Buscando e agrupando dados, por favor aguarde...</p>');
        $.ajax({
            url: 'ajax_router.php?action=getFaturamentoDadosPorOrdem',
            type: 'POST',
            data: { ordem_id: ordemId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    construirTabelaFaturamento(response.data, false); // false = modo preview
                    if (response.data.length > 0) {
                        $btnGerarContainer.show();
                    }
                } else {
                    $container.html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            }
        });
    }

    // CORREÇÃO 1: A função agora chama a nova rota 'getResumoSalvo'
    function carregarResumoSalvo(resumoId) {
        $.ajax({
            url: 'ajax_router.php?action=getResumoSalvo',
            type: 'POST',
            data: { resumo_id: resumoId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    construirTabelaFaturamento(response.data, true); // true = modo edição
                }
            }
        });
    }

    // CORREÇÃO 2: A função agora está limpa e sem código duplicado
    function construirTabelaFaturamento(data, modoEdicao) {
        if (!data || data.length === 0) {
            $container.html('<p class="text-muted text-center">Nenhum item encontrado.</p>');
            return;
        }

        let html = `
        <table class="table table-bordered table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th>Fazenda (Cliente do Lote)</th>
                    <th>Cliente Final</th>
                    <th>Pedido</th>
                    <th>Produto</th>
                    <th>Lote</th>
                    <th class="text-end">Qtd. Caixas</th>
                    <th class="text-end">Qtd. Quilos</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>`;

        let fazendaAtual = '';
        data.forEach(item => {
            if (item.fazenda_nome !== fazendaAtual) {
                fazendaAtual = item.fazenda_nome;
                html += `<tr class="table-group-divider"><td colspan="8" class="bg-light-subtle fw-bold"><i class="fas fa-tractor me-2"></i> FAZENDA: ${fazendaAtual || 'NÃO ESPECIFICADA'}</td></tr>`;
            }

            const btnEditar = modoEdicao
                ? `<button class="btn btn-warning btn-xs btn-editar-faturamento" data-fati-id="${item.fati_id}" title="Editar Preços/Obs."><i class="fas fa-pencil-alt"></i></button>`
                : `<button class="btn btn-warning btn-xs" title="Salve o resumo para editar" disabled><i class="fas fa-pencil-alt"></i></button>`;

            html += `<tr>
                        <td></td>
                        <td>${item.cliente_nome}</td>
                        <td>${item.oep_numero_pedido || item.fati_numero_pedido || ''}</td>
                        <td>${item.produto_descricao}</td>
                        <td>${item.lote_completo_calculado}</td>
                        <td class="text-end">${parseFloat(item.total_caixas || item.fati_qtd_caixas).toFixed(3)}</td>
                        <td class="text-end">${parseFloat(item.total_quilos || item.fati_qtd_quilos).toFixed(3)}</td>
                        <td class="text-center">${btnEditar}</td>
                     </tr>`;
        });
        html += `</tbody></table>`;
        $container.html(html);
    }

    $container.on('click', '.btn-editar-faturamento', function () {
        const fatiId = $(this).data('fati-id');
        $.ajax({
            url: 'ajax_router.php?action=getFaturamentoItemDetalhes',
            type: 'POST',
            data: { fati_id: fatiId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    $formEditar[0].reset();
                    $('#edit_fati_id').val(data.fati_id);
                    $('#display-produto').text(data.prod_descricao);
                    $('#display-lote').text(data.lote_completo_calculado);
                    $('#edit_fati_preco_unitario').val(data.fati_preco_unitario);
                    $('#edit_fati_preco_unidade_medida').val(data.fati_preco_unidade_medida);
                    $('#edit_fati_observacao').val(data.fati_observacao);
                    $modalEditar.modal('show');
                }
            }
        });
    });

    $formEditar.on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax_router.php?action=salvarFaturamentoItem',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalEditar.modal('hide');
                    notificacaoSucesso('Sucesso!', response.message);
                    // Futuramente, vamos recarregar a tabela aqui
                } else {
                    notificacaoErro('Erro', response.message);
                }
            }
        });
    });
});