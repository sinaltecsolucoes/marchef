// /public/js/detalhes_carregamento.js
$(document).ready(function () {

    if (!carregamentoData || !carregamentoData.header) {
        notificacaoErro('Erro Crítico', 'Não foi possível carregar os dados deste carregamento.');
        return;
    }

    // --- VARIÁVEIS GLOBAIS E SELETORES ---
    const carregamentoId = carregamentoData.header.car_id;
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || (window.csrfToken || '');
    const $modalGerenciarFila = $('#modal-gerenciar-fila');
    const $selectClienteParaFila = $('#select-cliente-para-fila');
    const $containerClientesNoModal = $('#clientes-e-produtos-container-modal');
    const $tabelaComposicaoBody = $('#tbody-composicao-carregamento');

    // --- FUNÇÕES ---

    function preencherCabecalho() {
        const header = carregamentoData.header;
        $('#car-numero-detalhe').text(header.car_numero);
        const $statusBadge = $('#car-status-detalhe');
        $statusBadge.text(header.car_status);
        let badgeClass = 'bg-secondary';
        if (header.car_status === 'EM ANDAMENTO') badgeClass = 'bg-warning text-dark';
        if (header.car_status === 'AGUARDANDO CONFERENCIA') badgeClass = 'bg-primary';
        if (header.car_status === 'FINALIZADO') badgeClass = 'bg-success';
        if (header.car_status === 'CANCELADO') badgeClass = 'bg-danger';
        $statusBadge.removeClass('bg-secondary bg-warning bg-primary bg-success bg-danger text-dark').addClass(badgeClass);
    }

    function inicializarSelectClienteModal() {
        $selectClienteParaFila.select2({
            placeholder: 'Digite para buscar um cliente...',
            theme: "bootstrap-5",
            dropdownParent: $modalGerenciarFila,
            language: "pt-BR",
            ajax: {
                url: 'ajax_router.php?action=getClienteOptions',
                dataType: 'json',
                processResults: function (data) {
                    const mappedData = data.data.map(item => ({ id: item.ent_codigo, text: item.ent_razao_social }));
                    return { results: mappedData };
                }
            }
        });
    }

    function inicializarSelectProdutoNoCard(selectId) {
        $('#' + selectId).select2({
            placeholder: 'Buscar produto no estoque...',
            theme: "bootstrap-5",
            dropdownParent: $modalGerenciarFila,
            language: "pt-BR",
            ajax: {
                url: 'ajax_router.php?action=getItensDeEstoqueOptions',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data.results }; }
            }
        });
    }

    function recarregarETabelaPrincipal() {
        console.log("Buscando dados atualizados do carregamento...");
        $.ajax({
            url: `ajax_router.php?action=getCarregamentoDetalhes&id=${carregamentoId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data) {
                const filas = response.data.filas;
                $tabelaComposicaoBody.empty();

                if (!filas || filas.length === 0) {
                    $tabelaComposicaoBody.html('<tr><td colspan="5" class="text-center text-muted">Nenhuma fila adicionada.</td></tr>');
                    return;
                }

                filas.forEach(fila => {
                    const totalItensNaFila = fila.itens ? fila.itens.length : 0;
                    if (totalItensNaFila > 0) {
                        // Agrupa os itens por cliente dentro da fila
                        const clientesDaFila = fila.itens.reduce((acc, item) => {
                            (acc[item.cliente_razao_social] = acc[item.cliente_razao_social] || []).push(item);
                            return acc;
                        }, {});

                        let isFirstRowOfQueue = true;
                        for (const nomeCliente in clientesDaFila) {
                            const itensDoCliente = clientesDaFila[nomeCliente];
                            const totalItensDoCliente = itensDoCliente.length;

                            itensDoCliente.forEach((item, index) => {
                                const $linha = $(`<tr data-fila-id="${fila.fila_id}">`);

                                // Colunas Fila e Ações são criadas apenas uma vez para todo o grupo da fila
                                if (isFirstRowOfQueue && index === 0) {
                                    const numSequencial = String(fila.fila_numero_sequencial).padStart(2, '0');
                                    $linha.append(`<td rowspan="${totalItensNaFila}">${numSequencial}</td>`);

                                    // Botão de Ações completo
                                    $linha.append(`
                                    <td class="text-center align-middle" rowspan="${totalItensNaFila}">
                                        <button class="btn btn-sm btn-outline-danger btn-remover-fila-principal" data-fila-sequencial="${numSequencial}" title="Remover Fila Completa">
                                            <i class="fas fa-trash"></i> Remover
                                        </button>
                                    </td>
                                `);
                                }

                                // Coluna Cliente é criada apenas uma vez para cada grupo de cliente dentro da fila
                                if (index === 0) {
                                    $linha.append(`<td rowspan="${totalItensDoCliente}">${nomeCliente}</td>`);
                                }

                                // Colunas Produto e Quantidade são criadas para cada item
                                $linha.append(`<td>${item.prod_descricao} (Cód: ${item.prod_codigo_interno})</td>`);
                                $linha.append(`<td class="text-end">${parseFloat(item.car_item_quantidade).toFixed(3)}</td>`);

                                $tabelaComposicaoBody.append($linha);
                            });
                            isFirstRowOfQueue = false;
                        }
                    }
                });
            } else {
                $tabelaComposicaoBody.html(`<tr><td colspan="5" class="text-center text-danger">Erro ao carregar os dados: ${response.message || ''}</td></tr>`);
            }
        }).fail(function () {
            $tabelaComposicaoBody.html('<tr><td colspan="5" class="text-center text-danger">Erro de comunicação ao carregar os dados.</td></tr>');
        });
    }

    function executarRemocaoFila(filaId) {
        $.ajax({
            url: 'ajax_router.php?action=removerFilaCompleta',
            type: 'POST',
            data: {
                fila_id: filaId,
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                // Usa a nossa nova função de sucesso
                notificacaoSucesso('Removida!', response.message);
                recarregarETabelaPrincipal();
            } else {
                // Usa a nossa nova função de erro
                notificacaoErro('Erro!', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível comunicar com o servidor.');
        });
    }

    // --- EVENT HANDLERS ---
    $modalGerenciarFila.on('show.bs.modal', function () {
        console.log("Modal de fila aberto. Limpando estado anterior...");

        // 1. Limpa o dropdown de cliente (código existente)
        $selectClienteParaFila.val(null).trigger('change');

        // 2. Limpa a área de cards de clientes (código existente)
        $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');

        // 3. Calcula o próximo número de fila sequencial
        let proximoNumeroFila = 1;
        const filasNaTabela = [];
        $tabelaComposicaoBody.find('tr[data-fila-id]').each(function () {
            const filaId = $(this).data('fila-id');
            if (!filasNaTabela.includes(filaId)) {
                filasNaTabela.push(filaId);
            }
        });
        proximoNumeroFila = filasNaTabela.length + 1;

        // 4. Atualiza o título do modal
        $('#numero-fila-modal').text(String(proximoNumeroFila).padStart(2, '0'));
    });

    $('#btn-adicionar-cliente-a-fila').on('click', function () {
        const clienteSelecionado = $selectClienteParaFila.select2('data')[0];
        if (!clienteSelecionado || !clienteSelecionado.id) {
            notificacaoErro('Atenção', 'Por favor, selecione um cliente da lista.');
            return;
        }
        if ($containerClientesNoModal.find('.card-cliente-na-fila').length === 0) { $containerClientesNoModal.empty(); }
        const clienteId = clienteSelecionado.id;
        const clienteNome = clienteSelecionado.text;
        const numeroCliente = $containerClientesNoModal.find('.card-cliente-na-fila').length + 1;
        const novoTitulo = `CLIENTE ${String(numeroCliente).padStart(2, '0')} - ${clienteNome}`;
        const selectIdUnico = `select-produto-${clienteId}-${new Date().getTime()}`;
        const $novoCard = $($('#template-card-cliente-modal').html());
        $novoCard.attr('data-cliente-id', clienteId);
        $novoCard.find('.nome-cliente-card').text(novoTitulo);
        $novoCard.find('.select-produto-estoque').attr('id', selectIdUnico);
        $containerClientesNoModal.append($novoCard);
        inicializarSelectProdutoNoCard(selectIdUnico);
        $selectClienteParaFila.val(null).trigger('change');
    });

    $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $produtoSelect = $form.find('.select-produto-estoque');
        const $quantidadeInput = $form.find('input[type="number"]');
        const produtoSelecionado = $produtoSelect.select2('data')[0];
        const quantidade = $quantidadeInput.val();
        if (!produtoSelecionado || !produtoSelecionado.id || !quantidade || parseFloat(quantidade) <= 0) {
            notificacaoErro('Atenção', 'Por favor, selecione um produto e insira uma quantidade válida.');
            return;
        }
        const $linhaSendoEditada = $form.data('editing-row');
        if ($linhaSendoEditada) {
            $linhaSendoEditada.attr('data-lote-item-id', produtoSelecionado.id);
            $linhaSendoEditada.find('td:nth-child(1)').text(produtoSelecionado.text);
            $linhaSendoEditada.find('td:nth-child(2)').text(parseFloat(quantidade).toFixed(3));
            $form.removeData('editing-row');
            $form.find('button[type="submit"]').html('<i class="fas fa-plus"></i> Adicionar Produto').removeClass('btn-warning').addClass('btn-primary');
        } else {
            const $listaProdutos = $form.closest('.card-cliente-na-fila').find('.lista-produtos-cliente');
            const produtoHtml = `<tr data-lote-item-id="${produtoSelecionado.id}"><td>${produtoSelecionado.text}</td><td class="text-end">${parseFloat(quantidade).toFixed(3)}</td><td class="text-center"><button type="button" class="btn btn-outline-secondary btn-sm btn-editar-produto-da-lista"><i class="fas fa-pencil-alt"></i> Editar</button> <button type="button" class="btn btn-outline-danger btn-sm btn-remover-produto-da-lista"><i class="fas fa-trash"></i> Remover</button></td></tr>`;
            $listaProdutos.append(produtoHtml);
        }
        $produtoSelect.val(null).trigger('change');
        $quantidadeInput.val('');
    });

    $modalGerenciarFila.on('click', '.btn-editar-produto-da-lista', function () {
        const $linhaParaEditar = $(this).closest('tr');
        const $cardCliente = $linhaParaEditar.closest('.card-cliente-na-fila');
        const $form = $cardCliente.find('.form-adicionar-produto-ao-cliente');
        const $produtoSelect = $form.find('.select-produto-estoque');
        const $quantidadeInput = $form.find('input[type="number"]');
        const loteItemId = $linhaParaEditar.data('lote-item-id');
        const produtoTexto = $linhaParaEditar.find('td:nth-child(1)').text();
        const quantidade = parseFloat($linhaParaEditar.find('td:nth-child(2)').text());
        if ($produtoSelect.find(`option[value="${loteItemId}"]`).length === 0) {
            const option = new Option(produtoTexto, loteItemId, true, true);
            $produtoSelect.append(option).trigger('change');
        }
        $produtoSelect.val(loteItemId).trigger('change');
        $quantidadeInput.val(quantidade);
        $form.data('editing-row', $linhaParaEditar);
        $form.find('button[type="submit"]').html('<i class="fas fa-check"></i> Atualizar Produto').removeClass('btn-primary').addClass('btn-warning');
        $quantidadeInput.focus();
    });

    $modalGerenciarFila.on('click', '.btn-remover-produto-da-lista', function () { $(this).closest('tr').remove(); });
    $modalGerenciarFila.on('click', '.btn-remover-cliente-da-fila', function () { $(this).closest('.card-cliente-na-fila').remove(); if ($containerClientesNoModal.find('.card-cliente-na-fila').length === 0) { $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>'); } });

    $('#btn-salvar-e-fechar-fila').on('click', function () {
        const $botaoSalvar = $(this);
        const filaData = [];
        $containerClientesNoModal.find('.card-cliente-na-fila').each(function () {
            const $card = $(this);
            const clienteId = $card.data('cliente-id');
            const produtos = [];
            $card.find('.lista-produtos-cliente tr').each(function () {
                const $linhaProduto = $(this);
                produtos.push({ loteItemId: $linhaProduto.data('lote-item-id'), quantidade: parseFloat($linhaProduto.find('td:nth-child(2)').text()) });
            });
            if (produtos.length > 0) { filaData.push({ clienteId: clienteId, produtos: produtos }); }
        });
        if (filaData.length === 0) {
            notificacaoErro('Fila Vazia', 'Nenhum produto foi adicionado. Adicione produtos antes de concluir.');
            return;
        }
        $botaoSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');
        $.ajax({
            url: 'ajax_router.php?action=salvarFilaComposta',
            type: 'POST',
            data: { carregamento_id: carregamentoId, fila_data: JSON.stringify(filaData), csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalGerenciarFila.modal('hide');
                notificacaoSucesso('Sucesso!', 'Fila salva com sucesso!');
                recarregarETabelaPrincipal();
            } else {
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar a fila.');
        }).always(function () {
            $botaoSalvar.prop('disabled', false).html('<i class="fas fa-check me-2"></i> Concluir e Adicionar Fila ao Carregamento');
        });
    });

    /**
    * Evento para o botão REMOVER na tabela principal de composição.
    */
    $tabelaComposicaoBody.on('click', '.btn-remover-fila-principal', function () {
        const $botao = $(this);
        const filaId = $botao.closest('tr').data('fila-id');
        const filaSequencial = $botao.data('fila-sequencial');

        if (!filaId) {
            notificacaoErro('Erro Interno', 'Não foi possível identificar a fila a ser removida.');
            return;
        }

        // Usa a nossa nova função de confirmação
        confirmacaoAcao(
            `Remover Fila Nº ${filaSequencial}?`,
            "Todos os produtos desta fila serão removidos. Esta ação não pode ser desfeita!"
        ).then((result) => {
            if (result.isConfirmed) {
                executarRemocaoFila(filaId);
            }
        });
    });

    $('#btn-confirmar-exclusao-acao').on('click', function () {
        const $botaoConfirmar = $(this);
        const filaId = $botaoConfirmar.data('fila-id-para-excluir');

        if (!filaId) {
            alert('Erro: ID da fila não encontrado.');
            return;
        }

        // Desabilita o botão para evitar cliques duplos
        $botaoConfirmar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Excluindo...');

        $.ajax({
            url: 'ajax_router.php?action=removerFilaCompleta',
            type: 'POST',
            data: {
                fila_id: filaId,
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $('#modal-confirmacao-exclusao').modal('hide');
                alert(response.message); // Ou usar um Toastr no futuro
                recarregarETabelaPrincipal();
            } else {
                alert('Erro: ' + response.message);
            }
        }).fail(function () {
            alert('Erro de comunicação com o servidor.');
        }).always(function () {
            // Reabilita o botão
            $botaoConfirmar.prop('disabled', false).text('Confirmar Exclusão');
        });
    });

    // --- INICIALIZAÇÃO DA PÁGINA ---
    preencherCabecalho();
    inicializarSelectClienteModal();
    recarregarETabelaPrincipal();
});