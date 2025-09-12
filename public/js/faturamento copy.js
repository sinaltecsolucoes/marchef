$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $selectOrdem = $('#select-ordem-expedicao');
    const $container = $('#faturamento-resultado-container');
    const $btnGerarContainer = $('#container-btn-gerar');
    const $btnGerar = $('#btn-gerar-resumo');

    // Inicializa o Select2 (sem alterações)
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

    // Evento disparado quando o usuário seleciona uma Ordem
    $selectOrdem.on('change', function () {
        const ordemId = $(this).val();
        $btnGerarContainer.hide(); // Esconde o botão ao trocar de ordem

        if (!ordemId) {
            $container.html('<p class="text-muted text-center">Selecione uma Ordem de Expedição acima para começar.</p>');
            return;
        }

        $container.html('<p class="text-center">Buscando e agrupando dados, por favor aguarde...</p>');

        $.ajax({
            url: 'ajax_router.php?action=getFaturamentoDadosPorOrdem',
            type: 'POST',
            data: { ordem_id: ordemId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    construirTabelaFaturamento(response.data);
                    $btnGerarContainer.show(); // Mostra o botão após carregar os dados
                } else {
                    $container.html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            }
        });
    });

    // NOVO EVENTO DE CLIQUE PARA O BOTÃO DE GERAR RESUMO
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
                    // Aqui, no futuro, vamos recarregar a tabela com os botões de edição habilitados
                    $btnGerar.hide(); // Esconde o botão para não gerar novamente
                    $selectOrdem.prop('disabled', true); // Trava a seleção
                } else {
                    notificacaoErro('Erro ao Salvar', response.message);
                    $btnGerar.prop('disabled', false).html('<i class="fas fa-check me-2"></i> Confirmar e Gerar Resumo');
                }
            }
        });
    });


    // Função que desenha a tabela 
    /* function construirTabelaFaturamento(data) {
         if (!data || data.length === 0) {
             $container.html('<p class="text-muted text-center">Nenhum item encontrado nesta Ordem de Expedição.</p>');
             return;
         }
         let html = `
             <table class="table table-bordered table-sm">
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
                 <tbody>
         `;
         // Cole aqui o conteúdo da sua função construirTabelaFaturamento
         let fazendaAtual = '';
         html = `<table class="table table-bordered table-sm"> ... </table>`; // Resumido para brevidade
         data.forEach(item => {
             if (item.fazenda_nome !== fazendaAtual) {
                 fazendaAtual = item.fazenda_nome;
                 html += `<tr class="table-group-divider"><td colspan="8" class="bg-light-subtle fw-bold"><i class="fas fa-tractor me-2"></i> FAZENDA: ${fazendaAtual || 'NÃO ESPECIFICADA'}</td></tr>`;
             }
             html += `<tr><td></td><td>${item.cliente_nome}</td><td>${item.oep_numero_pedido || ''}</td><td>${item.produto_descricao}</td><td>${item.lote_completo_calculado}</td><td class="text-end">${parseFloat(item.total_caixas).toFixed(3)}</td><td class="text-end">${parseFloat(item.total_quilos).toFixed(3)}</td><td class="text-center"><button class="btn btn-warning btn-xs" title="Editar Preços/Obs." disabled><i class="fas fa-pencil-alt"></i></button></td></tr>`;
         });
         $container.html(html);
     }*/

    // Função que desenha a tabela 
    function construirTabelaFaturamento(data) {
        if (!data || data.length === 0) {
            $container.html('<p class="text-muted text-center">Nenhum item encontrado nesta Ordem de Expedição.</p>');
            return;
        }

        // 1. Inicia a string HTML com a estrutura da tabela (APENAS UMA VEZ)
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
            <tbody>
    `;

        let fazendaAtual = '';
        // 2. Loop para adicionar (+=) as linhas de dados
        data.forEach(item => {
            // Adiciona a linha de cabeçalho da Fazenda quando ela mudar
            if (item.fazenda_nome !== fazendaAtual) {
                fazendaAtual = item.fazenda_nome;
                html += `
                <tr class="table-group-divider">
                    <td colspan="8" class="bg-light-subtle fw-bold">
                        <i class="fas fa-tractor me-2"></i> FAZENDA: ${fazendaAtual || 'NÃO ESPECIFICADA'}
                    </td>
                </tr>
            `;
            }

            // Adiciona a linha do item
            html += `
            <tr>
                <td></td> <td>${item.cliente_nome}</td>
                <td>${item.oep_numero_pedido || ''}</td>
                <td>${item.produto_descricao}</td>
                <td>${item.lote_completo_calculado}</td>
                <td class="text-end">${parseFloat(item.total_caixas).toFixed(3)}</td>
                <td class="text-end">${parseFloat(item.total_quilos).toFixed(3)}</td>
                <td class="text-center">
                    <button class="btn btn-warning btn-xs" title="Editar Preços/Obs." disabled><i class="fas fa-pencil-alt"></i></button>
                </td>
            </tr>
        `;
        });

        // 3. Fecha as tags do corpo e da tabela
        html += `
            </tbody>
        </table>
    `;

        // 4. Insere o HTML completo na página
        $container.html(html);
    }

});