$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $selectOrdem = $('#select-ordem-expedicao');
    const $container = $('#faturamento-resultado-container');
    const $btnGerarContainer = $('#container-btn-gerar');
    const $btnGerar = $('#btn-gerar-resumo');
    const $modalEditar = $('#modal-editar-faturamento');
    const $formEditar = $('#form-editar-faturamento');

    // ### LÓGICA DE INICIALIZAÇÃO  ###
    const urlParams = new URLSearchParams(window.location.search);
    const resumoIdParaCarregar = urlParams.get('resumo_id');

    if (resumoIdParaCarregar) {
        // Se a URL tem um ID, estamos em MODO DE EDIÇÃO
        // Esconde o card de seleção e carrega o resumo salvo
        $('.card-header:contains("1. Selecionar Ordem de Expedição")').closest('.card').hide();
        carregarResumoSalvo(resumoIdParaCarregar);
    } else {
        // Se não tem ID, estamos em MODO DE CRIAÇÃO
        // INICIALIZA O SELECT2 AQUI
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
    }

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

    /* function carregarResumoSalvo(resumoId) {
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
     } */

    // Função para carregar um resumo que já foi salvo (VERSÃO ATUALIZADA)
    function carregarResumoSalvo(resumoId) {
        // Mostra um spinner enquanto carrega
        $container.html('<p class="text-center">Carregando Resumo Salvo...</p>');

        $.ajax({
            url: 'ajax_router.php?action=getResumoSalvo',
            type: 'POST',
            data: { resumo_id: resumoId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // ### LÓGICA ATUALIZADA ###

                    // 1. Pega os dados do cabeçalho e dos itens
                    const headerData = response.data.header;
                    const itemsData = response.data.items;

                    // 2. Preenche o novo placeholder no título do Card 2
                    if (headerData && headerData.ordem_expedicao_numero) {
                        $('#ordem-origem-display')
                            .html(`Origem: <strong class="text-dark">Ordem Nº ${headerData.ordem_expedicao_numero}</strong>`)
                            .show();
                    }

                    // 3. Constrói a tabela usando apenas a lista de itens
                    construirTabelaFaturamento(itemsData, true); // true = modo edição
                } else {
                    $container.html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            }
        });
    }

    /* function construirTabelaFaturamento(data, modoEdicao) {
         if (!data || data.length === 0) {
             $container.html('<p class="text-muted text-center">Nenhum item encontrado.</p>');
             return;
         }
 
         // --- FASE 1: Processar os dados (não muda) ---
         const gruposFazenda = {};
         let granTotalCaixas = 0;
         let granTotalQuilos = 0;
         let granTotalValor = 0;
 
         data.forEach(item => {
             const fazendaNome = item.fazenda_nome || 'FAZENDA NÃO ESPECIFICADA';
             if (!gruposFazenda[fazendaNome]) {
                 gruposFazenda[fazendaNome] = {
                     itens: [],
                     subTotalCaixas: 0,
                     subTotalQuilos: 0,
                     subTotalValor: 0
                 };
             }
             let valorTotalItem = 0;
             const preco = parseFloat(item.fati_preco_unitario) || 0;
             const qtdCaixas = parseFloat(item.fati_qtd_caixas) || 0;
             const qtdQuilos = parseFloat(item.fati_qtd_quilos) || 0;
             if (item.fati_preco_unidade_medida === 'CX') {
                 valorTotalItem = preco * qtdCaixas;
             } else {
                 valorTotalItem = preco * qtdQuilos;
             }
             gruposFazenda[fazendaNome].itens.push({ ...item, valorTotalCalculado: valorTotalItem });
             gruposFazenda[fazendaNome].subTotalCaixas += qtdCaixas;
             gruposFazenda[fazendaNome].subTotalQuilos += qtdQuilos;
             gruposFazenda[fazendaNome].subTotalValor += valorTotalItem;
             granTotalCaixas += qtdCaixas;
             granTotalQuilos += qtdQuilos;
             granTotalValor += valorTotalItem;
         });
 
         // --- FASE 2: Construir o HTML da Tabela (COM A COLUNA LOTE SEPARADA) ---
         let html = `
         <table class="table table-bordered table-sm table-hover">
             <thead class="table-light">
                 <tr>
                     <th style="width: 15%;">Cliente Final / Pedido</th>
                     <th style="width: 20%;">Produto</th>
                     <th style="width: 12%;">Lote</th> <th class="text-end" style="width: 7%;">Qtd. Caixas</th>
                     <th class="text-end" style="width: 7%;">Qtd. Quilos</th>
                     <th class="text-end" style="width: 10%;">Preço Unit.</th>
                     <th class="text-end" style="width: 10%;">Valor Total</th>
                     <th style="width: 14%;">Observação</th>
                     <th class="text-center" style="width: 5%;">Ação</th>
                 </tr>
             </thead>
             <tbody>
     `;
 
         for (const nomeFazenda in gruposFazenda) {
             const grupo = gruposFazenda[nomeFazenda];
 
             // Cabeçalho do Grupo com colspan="9" (para 9 colunas)
             html += `
             <tr class="table-group-divider bg-light-subtle">
                 <td colspan="3" class="fw-bold"> <i class="fas fa-tractor me-2"></i> FAZENDA: ${nomeFazenda}
                 </td>
                 <td class="text-end fw-bold">${formatarNumeroBrasileiro(grupo.subTotalCaixas)}</td>
                 <td class="text-end fw-bold">${formatarNumeroBrasileiro(grupo.subTotalQuilos)}</td>
                 <td></td> 
                 <td class="text-end fw-bold">${grupo.subTotalValor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</td>
                 <td colspan="2"></td> 
             </tr>
         `;
 
             // Loop pelos itens
             grupo.itens.forEach(item => {
                 const btnEditar = modoEdicao
                     ? `<button class="btn btn-warning btn-xs btn-editar-faturamento" data-fati-id="${item.fati_id}" title="Editar Preços/Obs."><i class="fas fa-pencil-alt"></i></button>`
                     : `<button class="btn btn-warning btn-xs" title="Salve o resumo para editar" disabled><i class="fas fa-pencil-alt"></i></button>`;
 
                 const precoFormatado = item.fati_preco_unitario
                     ? parseFloat(item.fati_preco_unitario).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) + ` /${item.fati_preco_unidade_medida}`
                     : '-';
 
                 const valorTotalFormatado = item.valorTotalCalculado > 0
                     ? item.valorTotalCalculado.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
                     : '-';
 
                 // Células de Produto e Lote separadas
                 html += `
                 <tr>
                     <td>
                         ${item.cliente_nome}<br>
                         <small class="text-muted">Pedido: ${item.oep_numero_pedido || item.fati_numero_pedido || 'N/A'}</small>
                     </td>
                     <td>${item.produto_descricao}</td>
                     <td>${item.lote_completo_calculado}</td> 
                     <td class="text-end">${formatarNumeroBrasileiro(item.fati_qtd_caixas)}</td>
                     <td class="text-end">${formatarNumeroBrasileiro(item.fati_qtd_quilos)}</td>
                     <td class="text-end">${precoFormatado}</td>
                     <td class="text-end">${valorTotalFormatado}</td>
                     <td>${item.fati_observacao || ''}</td>
                     <td class="text-center">${btnEditar}</td>
                 </tr>
             `;
             });
         }
 
         // Linha de TOTAL GERAL (colspans ajustados)
         html += `
             <tr class="table-primary fw-bold">
                 <td colspan="3" class="text-end">TOTAL GERAL:</td> 
                 <td class="text-end">${formatarNumeroBrasileiro(granTotalCaixas)}</td>
                 <td class="text-end">${formatarNumeroBrasileiro(granTotalQuilos)}</td>
                 <td></td>
                 <td class="text-end">${granTotalValor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</td>
                 <td colspan="2"></td>
             </tr>
     `;
 
         html += `</tbody></table>`;
         $container.html(html);
     } */

    function construirTabelaFaturamento(data, modoEdicao) {
        if (!data || data.length === 0) {
            $container.html('<p class="text-muted text-center">Nenhum item encontrado.</p>');
            return;
        }

        // --- FASE 1: Processar os dados em uma hierarquia de 3 NÍVEIS ---
        const fazendas = {};
        let granTotalCaixas = 0;
        let granTotalQuilos = 0;
        let granTotalValor = 0;

        data.forEach(item => {
            const fazendaNome = item.fazenda_nome || 'FAZENDA NÃO ESPECIFICADA';
            // Criamos uma "chave" única para o grupo Cliente+Pedido
            const clientePedidoKey = `${item.cliente_nome}_${item.oep_numero_pedido || item.fati_numero_pedido}`;

            if (!fazendas[fazendaNome]) {
                fazendas[fazendaNome] = {
                    gruposClientePedido: {},
                    subTotalFazendaCaixas: 0,
                    subTotalFazendaQuilos: 0,
                    subTotalFazendaValor: 0
                };
            }

            if (!fazendas[fazendaNome].gruposClientePedido[clientePedidoKey]) {
                fazendas[fazendaNome].gruposClientePedido[clientePedidoKey] = {
                    clienteNome: item.cliente_nome,
                    pedidoNum: item.oep_numero_pedido || item.fati_numero_pedido || 'N/A',
                    itens: [],
                    subTotalClienteCaixas: 0,
                    subTotalClienteQuilos: 0,
                    subTotalClienteValor: 0
                };
            }

            // Calcular o valor total do item
            let valorTotalItem = 0;
            const preco = parseFloat(item.fati_preco_unitario) || 0;
            const qtdCaixas = parseFloat(item.fati_qtd_caixas || item.total_caixas) || 0;
            const qtdQuilos = parseFloat(item.fati_qtd_quilos || item.total_quilos) || 0;

            if (item.fati_preco_unidade_medida === 'CX') {
                valorTotalItem = preco * qtdCaixas;
            } else { // Padrão é KG
                valorTotalItem = preco * qtdQuilos;
            }

            const itemComTotal = { ...item, valorTotalCalculado: valorTotalItem, qtdCaixas, qtdQuilos };

            // Adiciona o item ao seu grupo específico de Cliente/Pedido
            fazendas[fazendaNome].gruposClientePedido[clientePedidoKey].itens.push(itemComTotal);

            // Soma subtotais do Cliente/Pedido
            fazendas[fazendaNome].gruposClientePedido[clientePedidoKey].subTotalClienteCaixas += qtdCaixas;
            fazendas[fazendaNome].gruposClientePedido[clientePedidoKey].subTotalClienteQuilos += qtdQuilos;
            fazendas[fazendaNome].gruposClientePedido[clientePedidoKey].subTotalClienteValor += valorTotalItem;

            // Soma subtotais da Fazenda
            fazendas[fazendaNome].subTotalFazendaCaixas += qtdCaixas;
            fazendas[fazendaNome].subTotalFazendaQuilos += qtdQuilos;
            fazendas[fazendaNome].subTotalFazendaValor += valorTotalItem;

            // Soma totais gerais
            granTotalCaixas += qtdCaixas;
            granTotalQuilos += qtdQuilos;
            granTotalValor += valorTotalItem;
        });

        // --- FASE 2: Construir o HTML da Tabela ---
        let html = `
        <table class="table table-bordered table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th style="width: 15%;">Cliente Final</th>
                    <th style="width: 20%;">Produto</th>
                    <th style="width: 12%;">Lote</th>
                    <th class="text-end" style="width: 7%;">Qtd. Caixas</th>
                    <th class="text-end" style="width: 7%;">Qtd. Quilos</th>
                    <th class="text-end" style="width: 10%;">Preço Unit.</th>
                    <th class="text-end" style="width: 10%;">Valor Total</th>
                    <th style="width: 14%;">Observação</th>
                    <th class="text-center" style="width: 5%;">Ação</th>
                </tr>
            </thead>
            <tbody>
    `;

        // Loop Nível 1: FAZENDAS
        for (const nomeFazenda in fazendas) {
            const grupoFazenda = fazendas[nomeFazenda];

            // Cabeçalho do Grupo (Fazenda) com seus totais
            html += `
            <tr class="table-group-divider bg-light-subtle">
                <td colspan="3" class="fw-bold">
                    <i class="fas fa-tractor me-2"></i> FAZENDA: ${nomeFazenda}
                </td>
                <td class="text-end fw-bold">${formatarNumeroBrasileiro(grupoFazenda.subTotalFazendaCaixas)}</td>
                <td class="text-end fw-bold">${formatarNumeroBrasileiro(grupoFazenda.subTotalFazendaQuilos)}</td>
                <td></td> 
                <td class="text-end fw-bold">${grupoFazenda.subTotalFazendaValor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</td>
                <td colspan="2"></td> 
            </tr>
        `;

            // Loop Nível 2: CLIENTE/PEDIDO
            for (const keyCliente in grupoFazenda.gruposClientePedido) {
                const grupoCliente = grupoFazenda.gruposClientePedido[keyCliente];

                // Loop Nível 3: ITENS (os produtos e lotes)
                grupoCliente.itens.forEach(item => {
                    const btnEditar = modoEdicao
                        ? `<button class="btn btn-warning btn-xs btn-editar-faturamento" data-fati-id="${item.fati_id}" title="Editar Preços/Obs."><i class="fas fa-pencil-alt"></i></button>`
                        : `<button class="btn btn-warning btn-xs" title="Salve o resumo para editar" disabled><i class="fas fa-pencil-alt"></i></button>`;

                    const precoFormatado = item.fati_preco_unitario
                        ? parseFloat(item.fati_preco_unitario).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) + ` /${item.fati_preco_unidade_medida}`
                        : '-';

                    const valorTotalFormatado = item.valorTotalCalculado > 0
                        ? item.valorTotalCalculado.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
                        : '-';

                    html += `
                    <tr>
                        <td>
                            ${item.cliente_nome}<br>
                            <small class="text-muted">Pedido: ${item.fati_numero_pedido || 'N/A'}</small>
                        </td>
                        <td>${item.produto_descricao}</td>
                        <td>${item.lote_completo_calculado}</td>
                        <td class="text-end">${formatarNumeroBrasileiro(item.qtdCaixas)}</td>
                        <td class="text-end">${formatarNumeroBrasileiro(item.qtdQuilos)}</td>
                        <td class="text-end">${precoFormatado}</td>
                        <td class="text-end">${valorTotalFormatado}</td>
                        <td>${item.fati_observacao || ''}</td>
                        <td class="text-center">${btnEditar}</td>
                    </tr>
                `;
                });

                // ### NOVO SUBTOTAL POR CLIENTE/PEDIDO ###
                // Adiciona a linha de subtotal para este grupo de cliente/pedido
                html += `
                <tr class="table-light" style="font-style: italic;">
                    <td colspan="3" class="text-end fw-bold">Subtotal (Cliente: ${grupoCliente.clienteNome} | Pedido: ${grupoCliente.pedidoNum}):</td>
                    <td class="text-end fw-bold">${formatarNumeroBrasileiro(grupoCliente.subTotalClienteCaixas)}</td>
                    <td class="text-end fw-bold">${formatarNumeroBrasileiro(grupoCliente.subTotalClienteQuilos)}</td>
                    <td></td>
                    <td class="text-end fw-bold">${grupoCliente.subTotalClienteValor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</td>
                    <td colspan="2"></td>
                </tr>
            `;
            }
        }

        // Linha de TOTAL GERAL (não muda)
        html += `
            <tr class="table-primary fw-bold">
                <td colspan="3" class="text-end">TOTAL GERAL:</td>
                <td class="text-end">${formatarNumeroBrasileiro(granTotalCaixas)}</td>
                <td class="text-end">${formatarNumeroBrasileiro(granTotalQuilos)}</td>
                <td></td>
                <td class="text-end">${granTotalValor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</td>
                <td colspan="2"></td>
            </tr>
    `;

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


    // EVENTO DE SUBMIT PARA SALVAR O ITEM EDITADO
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

                    // Recarrega a tabela para refletir as mudanças que acabamos de salvar.
                    // A variável 'resumoIdParaCarregar' está disponível pois foi definida no topo do arquivo.
                    if (resumoIdParaCarregar) {
                        carregarResumoSalvo(resumoIdParaCarregar);
                    }

                } else {
                    notificacaoErro('Erro', response.message);
                }
            }
        });
    });

    // EVENTO PARA ABRIR O RELATÓRIO EM NOVA ABA
    $('#btn-gerar-relatorio').on('click', function () {
        if (resumoIdParaCarregar) {
            // Abre o novo script de relatório, passando o ID do resumo
            /* window.open(`relatorio_faturamento.php?id=${resumoIdParaCarregar}`, '_blank');*/
            window.open(`index.php?page=relatorio_faturamento&id=${resumoIdParaCarregar}`, '_blank');
        } else {
            notificacaoErro('Erro', 'Não há resumo carregado para gerar relatório.');
        }
    });

});