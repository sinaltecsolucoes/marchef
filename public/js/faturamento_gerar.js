$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $selectOrdem = $('#select-ordem-expedicao');
    const $container = $('#faturamento-resultado-container');
    const $btnGerarContainer = $('#container-btn-gerar');
    const $btnGerar = $('#btn-gerar-resumo');
    const $modalEditarItem = $('#modal-editar-faturamento');
    const $formEditarItem = $('#form-editar-faturamento');
    const $modalEditarNota = $('#modal-editar-nota-grupo');
    const $formEditarNota = $('#form-editar-nota-grupo');
    const $formTransporte = $('#form-transporte');


    // --- LÓGICA DE INICIALIZAÇÃO DA PÁGINA (corrigida) ---
    const urlParams = new URLSearchParams(window.location.search);
    const resumoIdParaCarregar = urlParams.get('resumo_id');

    if (resumoIdParaCarregar) {
        // MODO DE EDIÇÃO: Esconde a seleção e carrega o resumo salvo
        $('.card-header:contains("1. Selecionar Ordem de Expedição")').closest('.card').hide();
        carregarResumoSalvo(resumoIdParaCarregar);
    } else {
        // MODO DE CRIAÇÃO: Inicializa o Select2
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

    // --- EVENTOS DE CRIAÇÃO ---

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
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Salvando Resumo...');

        $.ajax({
            url: 'ajax_router.php?action=salvarResumoFaturamento',
            type: 'POST',
            data: { ordem_id: ordemId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    notificacaoSucesso('Sucesso!', response.message);
                    // Redireciona para a mesma página, agora em modo de edição
                    window.location.href = `index.php?page=faturamento_gerar&resumo_id=${response.resumo_id}`;
                } else {
                    notificacaoErro('Erro ao Salvar', response.message);
                    $btnGerar.prop('disabled', false).html('<i class="fas fa-check me-2"></i> Confirmar e Gerar Resumo');
                }
            }
        });
    });

    // --- FUNÇÕES DE CARREGAMENTO DE DADOS ---

    function carregarPreview(ordemId) {
        $container.html('<p class="text-center">Buscando e agrupando dados, por favor aguarde...</p>');
        $.ajax({
            url: 'ajax_router.php?action=getFaturamentoDadosPorOrdem',
            type: 'POST',
            data: { ordem_id: ordemId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // O preview usa a função de construção (visualização simples)
                    construirTabelaPreview(response.data);
                    if (response.data.length > 0) {
                        $btnGerarContainer.show();
                    }
                } else {
                    $container.html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            }
        });
    }

    function carregarResumoSalvo(resumoId) {
        $container.html('<p class="text-center">Carregando Resumo Salvo...</p>');

        $.ajax({
            url: 'ajax_router.php?action=getResumoSalvo',
            type: 'POST',
            data: { resumo_id: resumoId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const header = response.data.header;
                    const gruposFazenda = response.data.grupos_fazenda;

                    // 1. Preenche o subtítulo (como antes)
                    if (header && header.ordem_expedicao_numero) {
                        $('#ordem-origem-display')
                            .html(`Origem: <strong class="text-dark">Ordem Nº ${header.ordem_expedicao_numero}</strong>`)
                            .show();
                    }

                    // 2. MOSTRA E PREENCHE O CARD DE TRANSPORTE
                    $('#card-transporte').show();
                    $('#fat_resumo_id_transporte').val(header.fat_id);
                    $('#fat_motorista_nome').val(header.fat_motorista_nome);

                    // REFINAMENTO 2 (Máscara do CPF): Adiciona .trigger('input')
                    $('#fat_motorista_cpf').val(header.fat_motorista_cpf).trigger('input');

                    $('#fat_veiculo_placa').val(header.fat_veiculo_placa).trigger('input'); // Adicionamos trigger aqui tbm

                    // REFINAMENTO 4 (Texto do Botão): Verifica se já há dados salvos
                    if (header.fat_transportadora_id || header.fat_motorista_nome) {
                        $('#form-transporte button[type="submit"]').text('Atualizar Dados de Transporte');
                    } else {
                        $('#form-transporte button[type="submit"]').text('Salvar Dados de Transporte');
                    }

                    // Preenche o Select2 da transportadora (como antes)
                    if (header.fat_transportadora_id) {
                        const transpNome = header.transportadora_nome || header.transportadora_razao || 'Carregando...';
                        const option = new Option(transpNome, header.fat_transportadora_id, true, true);
                        $('#select-transportadora').append(option).trigger('change');
                    } else {
                        $('#select-transportadora').val(null).trigger('change');
                    }

                    // 3. Constrói a tabela principal (como antes)
                    construirTabelaFaturamentoEdicao(gruposFazenda);
                } else {
                    $container.html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            }
        });
    }

    // --- FUNÇÕES DE CONSTRUÇÃO DE TABELA (HTML) ---

    // Função 1: Apenas para o PREVIEW (simples, sem botões)
    function construirTabelaPreview(data) {
        if (!data || data.length === 0) {
            $container.html('<p class="text-muted text-center">Nenhum item encontrado.</p>');
            return;
        }
        let html = `
        <table class="table table-bordered table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th>Fazenda (Cliente do Lote)</th>
                    <th>Cliente Final / Pedido</th>
                    <th>Produto</th>
                    <th>Lote</th>
                    <th class="text-end">Qtd. Caixas</th>
                    <th class="text-end">Qtd. Quilos</th>
                </tr>
            </thead>
            <tbody>`;
        let fazendaAtual = '';
        data.forEach(item => {
            if (item.fazenda_nome !== fazendaAtual) {
                fazendaAtual = item.fazenda_nome;
                html += `<tr class="table-group-divider"><td colspan="6" class="bg-light-subtle fw-bold"><i class="fas fa-tractor me-2"></i> FAZENDA: ${fazendaAtual || 'N/A'}</td></tr>`;
            }
            html += `<tr>
                        <td></td>
                        <td>${item.cliente_nome} <small class="text-muted">(Pedido: ${item.oep_numero_pedido || 'N/A'})</small></td>
                        <td>${item.produto_descricao}</td>
                        <td>${item.lote_completo_calculado}</td>
                        <td class="text-end">${formatarNumeroBrasileiro(item.total_caixas)}</td>
                        <td class="text-end">${formatarNumeroBrasileiro(item.total_quilos)}</td>
                     </tr>`;
        });
        html += `</tbody></table>`;
        $container.html(html);
    }

    function construirTabelaFaturamentoEdicao(gruposFazenda) {
        if (!gruposFazenda || Object.keys(gruposFazenda).length === 0) {
            $container.html('<p class="text-muted text-center">Nenhum item encontrado neste resumo.</p>');
            return;
        }

        let html = '';
        let granTotalCaixas = 0, granTotalQuilos = 0, granTotalValor = 0;

        // Loop Nível 1: FAZENDAS
        for (const nomeFazenda in gruposFazenda) {
            const notasDaFazenda = gruposFazenda[nomeFazenda];
            let subTotalFazendaCaixas = 0, subTotalFazendaQuilos = 0, subTotalFazendaValor = 0;

            // Cabeçalho da Fazenda
            html += `<div class="fazenda-group border rounded shadow-sm mb-4">
                        <div class="fazenda-header bg-light-subtle p-2 fw-bold border-bottom">
                            <i class="fas fa-tractor me-2"></i> FAZENDA: ${nomeFazenda}
                        </div>
                     <div class="fazenda-body p-2">`;

            // Loop Nível 2: NOTAS (Cliente/Pedido)
            notasDaFazenda.forEach(nota => {
                let subTotalNotaCaixas = 0, subTotalNotaQuilos = 0, subTotalNotaValor = 0;

                // CABEÇALHO DA TABELA DE ITENS (ESTAS LARGURAS SÃO A NOSSA REFERÊNCIA)
                const condPagHtml = nota.condicao_pagamento ? `<span class="badge bg-info">${nota.condicao_pagamento}</span>` : '<span class="badge bg-warning text-dark">Cond. Pagto. a definir</span>';
                const obsHtml = nota.observacao ? `<small class="d-block text-muted fst-italic">Obs: ${nota.observacao}</small>` : '';

                html += `<table class="table table-sm table-bordered table-hover mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th colspan="5" class="align-middle">
                                        Cliente: <strong class="text-primary">${nota.cliente_nome}</strong> 
                                        (Pedido: ${nota.numero_pedido})
                                        <div class="mt-1">${condPagHtml}</div>
                                        ${obsHtml}
                                    </th>
                                    <th colspan="3" class="text-end align-middle">
                                        <button class="btn btn-secondary btn-xs btn-editar-nota" data-fatn-id="${nota.fatn_id}">
                                            <i class="fas fa-cog me-1"></i> Editar Pedido (Cond/Obs)
                                        </button>
                                    </th>
                                </tr>
                                <tr>
                                    <th style="width: 25%;">Produto</th>
                                    <th style="width: 15%;">Lote</th>
                                    <th class="text-end" style="width: 10%;">Qtd. Caixas</th>
                                    <th class="text-end" style="width: 10%;">Qtd. Quilos</th>
                                    <th class="text-end" style="width: 15%;">Preço Unit.</th>
                                    <th class="text-end" style="width: 15%;">Valor Total</th>
                                    <th class="text-center" style="width: 10%;">Ação (Preço)</th>
                                </tr>
                            </thead>
                            <tbody>`;

                // Loop Nível 3: ITENS
                nota.itens.forEach(item => {
                    // ... (lógica de cálculo de totais dos itens - sem alteração) ...
                    const qtdCaixas = parseFloat(item.fati_qtd_caixas) || 0;
                    const qtdQuilos = parseFloat(item.fati_qtd_quilos) || 0;
                    const preco = parseFloat(item.fati_preco_unitario) || 0;
                    let valorTotalItem = 0;
                    if (item.fati_preco_unidade_medida === 'CX') {
                        valorTotalItem = preco * qtdCaixas;
                    } else {
                        valorTotalItem = preco * qtdQuilos;
                    }
                    subTotalNotaCaixas += qtdCaixas;
                    subTotalNotaQuilos += qtdQuilos;
                    subTotalNotaValor += valorTotalItem;
                    const precoFormatado = item.fati_preco_unitario ? formatCurrency(preco) + ` /${item.fati_preco_unidade_medida}` : '-';
                    const valorTotalFormatado = valorTotalItem > 0 ? formatCurrency(valorTotalItem) : '-';

                    html += `<tr>
                                <td>${item.produto_descricao}</td>
                                <td>${item.lote_completo_calculado}</td>
                                <td class="text-end">${formatarNumeroBrasileiro(qtdCaixas)}</td>
                                <td class="text-end">${formatarNumeroBrasileiro(qtdQuilos)}</td>
                                <td class="text-end">${precoFormatado}</td>
                                <td class="text-end">${valorTotalFormatado}</td>
                                <td class="text-center">
                                    <button class="btn btn-warning btn-xs btn-editar-item-faturamento" data-fati-id="${item.fati_id}">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                </td>
                            </tr>`;
                });

                // RODAPÉ DA NOTA (COLSPANS AJUSTADOS PARA ALINHAR)
                html += `<tr class="table-light fw-bold" style="font-style: italic;">
                            <td colspan="2" class="text-end">Subtotal (Nota):</td>
                            <td class="text-end">${formatarNumeroBrasileiro(subTotalNotaCaixas)}</td>
                            <td class="text-end">${formatarNumeroBrasileiro(subTotalNotaQuilos)}</td>
                            <td></td> <td class="text-end">${formatCurrency(subTotalNotaValor)}</td>
                            <td></td> </tr></tbody></table>`;

                // Acumula totais da FAZENDA
                subTotalFazendaCaixas += subTotalNotaCaixas;
                subTotalFazendaQuilos += subTotalNotaQuilos;
                subTotalFazendaValor += subTotalNotaValor;
            });

            // RODAPÉ DA FAZENDA (CORRIGIDO PARA ALINHAR AS COLUNAS)
            html += `<table class="table table-sm table-bordered mt-2">
                        <tr class="table-dark fw-bold">
                            <td class="text-end" style="width: 40%;">TOTAL FAZENDA (${nomeFazenda}):</td> <td class="text-end" style="width: 10%;">${formatarNumeroBrasileiro(subTotalFazendaCaixas)}</td>
                            <td class="text-end" style="width: 10%;">${formatarNumeroBrasileiro(subTotalFazendaQuilos)}</td>
                            <td style="width: 15%;"></td> <td class="text-end" style="width: 15%;">${formatCurrency(subTotalFazendaValor)}</td>
                            <td style="width: 10%;"></td> </tr>
                     </table></div></div>`;

            // Acumula TOTAIS GERAIS
            granTotalCaixas += subTotalFazendaCaixas;
            granTotalQuilos += subTotalFazendaQuilos;
            granTotalValor += subTotalFazendaValor;
        }

        // TOTAL GERAL (CORRIGIDO PARA ALINHAR AS COLUNAS)
        html += `<table class="table table-bordered mt-4">
                    <tr class="table-primary fw-bolder">
                        <td class="text-end" style="width: 40%;">TOTAL GERAL DO RESUMO:</td>
                        <td class="text-end" style="width: 10%;">${formatarNumeroBrasileiro(granTotalCaixas)}</td>
                        <td class="text-end" style="width: 10%;">${formatarNumeroBrasileiro(granTotalQuilos)}</td>
                        <td style="width: 15%;"></td> <td class="text-end" style="width: 15%;">${formatCurrency(granTotalValor)}</td>
                        <td style="width: 10%;"></td> </tr>
                 </table>`;

        $container.html(html);
    }

    // Função helper para formatar moeda
    function formatCurrency(val) {
        return (parseFloat(val) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    // --- EVENTOS DOS MODAIS DE EDIÇÃO ---

    // Evento para ABRIR o modal de EDIÇÃO DE ITEM (Preço)
    $container.on('click', '.btn-editar-item-faturamento', function () {
        const fatiId = $(this).data('fati-id');
        $.ajax({
            url: 'ajax_router.php?action=getFaturamentoItemDetalhes',
            type: 'POST',
            data: { fati_id: fatiId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    $formEditarItem[0].reset();
                    $('#edit_fati_id').val(data.fati_id);
                    $('#display-produto').text(data.prod_descricao);
                    $('#display-lote').text(data.lote_completo_calculado);
                    $('#edit_fati_preco_unitario').val(data.fati_preco_unitario);
                    $('#edit_fati_preco_unidade_medida').val(data.fati_preco_unidade_medida);
                    //$('#edit_fati_observacao').val(data.fati_observacao);
                    $modalEditarItem.modal('show');
                }
            }
        });
    });

    // Evento para SALVAR o modal de EDIÇÃO DE ITEM
    $formEditarItem.on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax_router.php?action=salvarFaturamentoItem',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalEditarItem.modal('hide');
                    notificacaoSucesso('Sucesso!', response.message);
                    // Recarrega todo o resumo para ver a mudança
                    if (resumoIdParaCarregar) {
                        carregarResumoSalvo(resumoIdParaCarregar);
                    }
                } else {
                    notificacaoErro('Erro', response.message);
                }
            }
        });
    });

    // 1. Inicializa o Select2 para Condições de Pagamento (usando AJAX)
    const $selectCondPag = $('#edit_fatn_condicao_pag_id');

    $selectCondPag.select2({
        placeholder: "Selecione uma condição...",
        theme: "bootstrap-5",
        dropdownParent: $modalEditarNota, // Vincula ao novo modal
        ajax: {
            url: "ajax_router.php?action=getCondicoesPagamentoOptions",
            dataType: 'json',
            processResults: function (data) {
                return data; // Já vem como { results: [...] }
            }
        }
    });

    // 2. Evento de CLIQUE para abrir o modal de Edição da Nota
    $container.on('click', '.btn-editar-nota', function () {
        const fatnId = $(this).data('fatn-id');
        if (!fatnId) return;

        $.ajax({
            url: 'ajax_router.php?action=getNotaGrupoDetalhes',
            type: 'POST',
            data: { fatn_id: fatnId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const data = response.data;

                    $formEditarNota[0].reset();
                    $('#edit_fatn_id').val(data.fatn_id);
                    $('#display-nota-cliente').text(data.cliente_nome);
                    $('#display-nota-pedido').text(data.fatn_numero_pedido);
                    $('#edit_fatn_observacao').val(data.fatn_observacao);

                    // Limpa o select2 de condições
                    $selectCondPag.empty();
                    if (data.fatn_condicao_pag_id && data.condicao_pag_descricao) {
                        // Se já tiver um valor salvo, cria a <option> para ele e a define como selecionada
                        const option = new Option(data.condicao_pag_descricao, data.fatn_condicao_pag_id, true, true);
                        $selectCondPag.append(option).trigger('change');
                    } else {
                        // Se não, apenas limpa
                        $selectCondPag.val(null).trigger('change');
                    }

                    $modalEditarNota.modal('show');
                } else {
                    notificacaoErro('Erro', response.message);
                }
            }
        });
    });

    // 3. Evento de SUBMIT para SALVAR a Nota
    $formEditarNota.on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax_router.php?action=salvarNotaGrupo',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalEditarNota.modal('hide');
                    notificacaoSucesso('Sucesso!', response.message);
                    // Recarrega o resumo inteiro para mostrar a obs/condição atualizada
                    if (resumoIdParaCarregar) {
                        carregarResumoSalvo(resumoIdParaCarregar);
                    }
                } else {
                    notificacaoErro('Erro', response.message);
                }
            }
        });
    });

    // --- LÓGICA DO NOVO FORMULÁRIO DE TRANSPORTE ---

    // 1. Inicializa o Select2 para Transportadoras
    $('#select-transportadora').select2({
        placeholder: "Selecione uma transportadora...",
        theme: "bootstrap-5",
        allowClear: true,
        ajax: {
            url: "ajax_router.php?action=getTransportadoraOptions",
            dataType: 'json',
            processResults: function (data) {
                return data; // Já vem como { results: [...] }
            }
        }
    });

    // 2. Evento de SUBMIT para SALVAR os dados de transporte
    $formTransporte.on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax_router.php?action=salvarDadosTransporte',
            type: 'POST',
            data: $(this).serialize(), // Envia os dados do formulário de transporte
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    notificacaoSucesso('Sucesso!', response.message);
                    // Recarrega tudo para garantir que os dados do relatório estão 100% atualizados
                    carregarResumoSalvo(resumoIdParaCarregar);
                } else {
                    notificacaoErro('Erro', response.message);
                }
            }
        });
    });

    // --- EVENTOS DOS BOTÕES DE RELATÓRIO (Passos 2 e 3) ---

    /**
     * Passo 2: Ativa o botão de Gerar Relatório (PDF)
     */
    $('#btn-gerar-relatorio').on('click', function () {
        if (resumoIdParaCarregar) {

            // !! JÁ APLIQUE A CORREÇÃO DA URL QUE DISCUTIMOS !!
            const urlRelatorio = `index.php?page=relatorio_faturamento&id=${resumoIdParaCarregar}`;

            window.open(urlRelatorio, '_blank');
        } else {
            notificacaoErro('Erro', 'Não foi possível identificar o ID do resumo.');
        }
    });

    /**
     * Passo 3: Ativa o botão de Exportar Excel
     */
    $('#btn-exportar-excel').on('click', function () {
        // $container é a variável que definimos no topo do arquivo: $('#faturamento-resultado-container')
        const $tabelaHtml = $container.html();

        if (!$tabelaHtml.includes('<table')) {
            notificacaoErro('Erro', 'Não há dados na tabela para exportar.');
            return;
        }

        // Tenta pegar o número da ordem para montar um nome de arquivo amigável
        const ordemNumText = $('#ordem-origem-display strong').text(); // "Origem: Ordem Nº XXXXX"
        const ordemNum = ordemNumText.replace('Ordem Nº ', '').trim();
        const nomeArquivo = `Faturamento_OE_${ordemNum}_Resumo_${resumoIdParaCarregar}.xls`;

        // Monta o template HTML com o encoding correto para Excel
        const template = `<html xmlns:o="urn:schemas-microsoft-com:office:office" 
                                xmlns:x="urn:schemas-microsoft-com:office:excel" 
                                xmlns="http://www.w3.org/TR/REC-html40">
                          <head><meta charset="UTF-8"></head>
                          <body>${$tabelaHtml}</body>
                          </html>`;

        // Cria um 'Blob' (Binary Large Object) com o HTML
        // Isso é mais robusto que usar data:uri para encoding e acentuação
        const data = new Blob([template], {
            type: 'application/vnd.ms-excel'
        });

        // Cria uma URL temporária para o Blob
        const url = window.URL.createObjectURL(data);

        // Cria um link <a> invisível para forçar o download
        const link = document.createElement("a");
        link.href = url;
        link.download = nomeArquivo; // Define o nome do arquivo

        document.body.appendChild(link);
        link.click(); // Simula o clique no link

        // Limpa a URL e remove o link
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    });

    // Aplica máscara no campo CPF do motorista (requer a biblioteca jquery.mask)
    $('#fat_motorista_cpf').mask('000.000.000-00');

    // MÁSCARA INTELIGENTE PARA UMA OU DUAS PLACAS
    $('#fat_veiculo_placa').mask('SSS-0A00 / SSS-0A00', {
        translation: {
            'S': { pattern: /[A-Za-z]/ }, // Aceita Letras
            'A': { pattern: /[A-Za-z0-9]/ } // Aceita Letra ou Número (para Mercosul)
        },
        onKeyPress: function (val, e, field, options) {
            // 1. Força tudo para MAIÚSCULAS
            field.val(val.toUpperCase());

            // 2. Lógica para pular a barra '/'
            // Se o usuário terminar a primeira placa (7 chars) e digitar uma letra/número 
            // em vez de espaço, nós adicionamos o " / " automaticamente.
            if (val.length === 8) {
                // Se o 8º char não for um espaço (indicando que ele não digitou a barra)
                if (val.charAt(7) !== ' ') {
                    // Pega o 8º char (que seria o 1º da segunda placa)
                    let charExtra = val.charAt(7);
                    // Monta a string correta
                    let newVal = val.substring(0, 7) + ' / ' + charExtra;
                    field.val(newVal); // Define o novo valor
                    // Precisamos reaplicar a máscara para que ele entenda a nova posição
                    field.mask('SSS-0A00 / SSS-0A00', options);
                }
            }
        },
        // 3. Esta é a opção mais IMPORTANTE:
        // Se o usuário digitar só 7 chars (uma placa), não apague o campo.
        clearIfNotMatch: true
    });

});