$(document).ready(function () {
    // --- Variáveis e Modais (sem alterações) ---
    const csrfToken = $('input[name="csrf_token"]').val();
    const $formHeader = $('#form-ordem-header');
    const urlParams = new URLSearchParams(window.location.search);
    let ordemId = urlParams.get('id');
    const $pedidosContainer = $('#pedidos-container');
    const $modalPedido = $('#modal-pedido-cliente');
    const $formPedido = $('#form-pedido-cliente');
    const $selectCliente = $('#oep_cliente_id');
    const $modalEstoque = $('#modal-selecao-estoque');
    const $selectProduto = $('#select-produto-estoque');
    const $selectLote = $('#select-lote-estoque');
    const $selectEndereco = $('#select-endereco-estoque');
    const $displaySaldo = $('#saldo-disponivel-display');
    const $inputQtd = $('#oei_quantidade');
    const $inputObs = $('#oei_observacao');
    const $btnAddItem = $('#btn-confirmar-add-item');
    let sortableInstance = null;

    // --- Funções de notificação (sem alterações) ---
    function notificacaoSucesso(titulo, mensagem) {
        Swal.fire({
            icon: 'success',
            title: titulo,
            text: mensagem,
            showConfirmButton: false,
            timer: 1500
        });
    }

    function notificacaoErro(titulo, mensagem) {
        Swal.fire({
            icon: 'error',
            title: titulo,
            text: mensagem,
            confirmButtonText: 'OK'
        });
    }

    // ### FUNÇÃO PARA CALCULAR E ATUALIZAR TOTAIS ###
    function atualizarTotais(pedidos = []) {
        let grandTotalCaixas = 0;
        let grandTotalQuilos = 0;
        const destinos = new Set();

        pedidos.forEach(pedido => {
            let totalCaixasPedido = 0;
            let totalQuilosPedido = 0;

            if (pedido.itens && pedido.itens.length > 0) {
                pedido.itens.forEach(item => {
                    const quantidade = parseFloat(item.oei_quantidade) || 0;
                    const pesoEmbalagem = parseFloat(item.peso_secundario) || 0;
                    totalCaixasPedido += quantidade;
                    totalQuilosPedido += quantidade * pesoEmbalagem;
                });
            }

            // Atualiza os totais por pedido
            $(`#total-caixas-${pedido.oep_id}`).text(totalCaixasPedido);
            $(`#total-quilos-${pedido.oep_id}`).text(formatarNumeroBrasileiro(totalQuilosPedido) + 'kg');


            // Acumula os totais gerais
            grandTotalCaixas += totalCaixasPedido;
            grandTotalQuilos += totalQuilosPedido;

            // Adiciona a UF ao conjunto de destinos (Set já evita duplicatas)
            if (pedido.end_uf) {
                destinos.add(pedido.end_uf);
            }
        });

        // Atualiza os totais gerais no cabeçalho
        $('#total-caixas-geral').val(grandTotalCaixas);
        $('#total-quilos-geral').val(formatarNumeroBrasileiro(grandTotalQuilos));
        $('#destinos-geral').val(Array.from(destinos).sort().join(', '));
    }

    function renderizarDetalhes(ordem) {
        if (!ordem || !ordem.header) return;

        const estaBloqueada = ordem.header.oe_status === 'GEROU CARREGAMENTO';

        if (sortableInstance) {
            sortableInstance.option("disabled", estaBloqueada);
        }
        $('.drag-handle').toggle(!estaBloqueada);

        $('#ordem_id').val(ordem.header.oe_id);
        $('#oe_numero').val(ordem.header.oe_numero);
        $('#oe_data').val(ordem.header.oe_data);
        $('#main-title').text(`Ordem de Expedição: ${ordem.header.oe_numero}`);
        $formHeader.find('input, button').prop('disabled', true).css('pointer-events', 'none');
        $('#btn-salvar-header').hide();
        $('#section-details').show();

        $pedidosContainer.empty();

        if ($('#btn-relatorio-oe').length === 0) {
            const btnRelatorio = `
            <a id="btn-relatorio-oe" 
            href="index.php?page=ordem_expedicao_relatorio&id=${ordem.header.oe_id}" 
            target="_blank" 
            class="btn btn-success me-2 d-inline-flex align-items-center flex-shrink-0">
            <i class="fas fa-print me-1"></i>Imprimir Relatório</a>`;
            $('#botoes-cabecalho-oe').prepend(btnRelatorio);
        }

        if (estaBloqueada) {
            $('#btn-adicionar-pedido-cliente').hide();
            if ($('#aviso-bloqueio-oe').length === 0) {
                $('.card-header:contains("2. Detalhes")').append(
                    '<span id="aviso-bloqueio-oe" class="badge bg-warning text-dark ms-3">Esta OE está bloqueada pois já gerou um carregamento.</span>'
                );
            }
        } else {
            $('#btn-adicionar-pedido-cliente').show();
            $('#aviso-bloqueio-oe').remove();
        }

        if (ordem.pedidos && ordem.pedidos.length > 0) {
            ordem.pedidos.forEach(pedido => {
                let itensHtml = '';

                if (pedido.itens && pedido.itens.length > 0) {
                    pedido.itens.forEach(item => {
                        const qtdQuilos = (parseFloat(item.oei_quantidade) || 0) * (parseFloat(item.peso_secundario) || 0);
                        const acoesItemHtml = !estaBloqueada ?
                            `<td class="text-center align-middle">
                            <div class="d-inline-flex gap-1">
                                <button class="btn btn-warning btn-xs me-1 btn-editar-item" 
                                                data-oei-id="${item.oei_id}" 
                                                title="Editar este item"><i class="fas fa-pencil-alt"></i></button>
                                <button class="btn btn-danger btn-xs btn-remover-item" 
                                                data-oei-id="${item.oei_id}" 
                                                title="Remover este item"><i class="fas fa-times"></i></button>
                            </div>
                        </td>` : '';

                        itensHtml += `
                    <tr>
                        <td class="text-center align-middle small">${item.prod_codigo_interno || 'N/A'}</td>
                        <td class="align-middle small">${item.prod_descricao || 'N/A'}</td>
                        <td class="text-center align-middle small">${formatarNumeroBrasileiro(item.peso_primario)}</td>
                        <td class="text-center align-middle small">${formatarNumeroBrasileiro(item.peso_secundario)}</td>
                        <td class="text-center align-middle small">${item.industria || 'N/A'}</td>
                        <td class="text-center align-middle small">${item.cliente_lote_nome || 'N/A'}</td>
                        <td class="text-center align-middle small">${item.lote_completo_calculado || 'N/A'}</td>
                        <td class="text-center align-middle small">${item.endereco_completo || 'N/A'}</td> 
                        <td class="text-center align-middle small">${formatarNumeroBrasileiro(item.oei_quantidade)}</td>
                        <td class="text-center align-middle small">${formatarNumeroBrasileiro(qtdQuilos)}</td>
                        <td class="text-center align-middle small">${item.oei_observacao || ''}</td>
                        ${acoesItemHtml}
                    </tr>`;
                    });
                } else {
                    const colspan = estaBloqueada ? 11 : 12;
                    itensHtml = `<tr><td colspan="${colspan}" class="text-center text-muted">Nenhum produto adicionado a este pedido.</td></tr>`;
                }

                const botoesAcaoPedido = !estaBloqueada ?
                    `<button class="btn btn-info btn-adicionar-produto" data-oep-id="${pedido.oep_id}"><i class="fas fa-plus me-1"></i>Adicionar Produto</button>
                 <button class="btn btn-danger btn-remover-pedido" data-oep-id="${pedido.oep_id}"><i class="fas fa-trash-alt me-1"></i>Remover Pedido</button>` : '';

                const thAcoes = !estaBloqueada ? '<th class="text-center align-middle small" style="width: 8%;">Ações</th>' : '';

                const pedidoHtml = `
            <div class="pedido-group border border-primary-subtle rounded p-3 mb-3 shadow-sm" data-oep-id="${pedido.oep_id}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">
                        <i class="fas fa-grip-vertical me-3 text-muted drag-handle" style="cursor: grab;"></i>
                        Cliente: ${pedido.ent_razao_social || 'N/A'} (Pedido: ${pedido.oep_numero_pedido || 'N/A'})
                        <span class="text-muted fw-normal ps-1">
                            - Total Caixas: <span id="total-caixas-${pedido.oep_id}">0</span>
                            - Total Quilos: <span id="total-quilos-${pedido.oep_id}">0,000kg</span>
                        </span>
                    </h5>
                    <div>${botoesAcaoPedido}</div>
                </div>
                <div>
                    <table class="table table-sm table-bordered table-hover tabela-pedido" style="width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center align-middle small" style="width: 5%;">Código</th>
                                <th class="text-center align-middle small" style="width: 18%;">Produto</th>
                                <th class="text-center align-middle small" style="width: 5%;">Emb. Prim.</th>
                                <th class="text-center align-middle small" style="width: 5%;">Emb. Sec.</th>
                                <th class="text-center align-middle small" style="width: 7%;">Indústria</th>
                                <th class="text-center align-middle small" style="width: 10%;">Fazenda</th>
                                <th class="text-center align-middle small" style="width: 10%;">Lote</th>
                                <th class="text-center align-middle small" style="width: 10%;">Endereço</th>
                                <th class="text-center align-middle small" style="width: 5%;">Qtd Caixas</th>
                                <th class="text-center align-middle small" style="width: 5%;">Qtd Quilos</th>
                                <th class="text-center align-middle small" style="width: 12%;">Obs.</th>
                                ${thAcoes}
                            </tr>
                        </thead>
                        <tbody>${itensHtml}</tbody>
                    </table>
                </div>
            </div>`;
                $pedidosContainer.append(pedidoHtml);
            });
        }

        atualizarTotais(ordem.pedidos);

        $('.tabela-pedido').each(function () {
            const $tabela = $(this);
            if ($.fn.DataTable.isDataTable($tabela)) {
                $tabela.DataTable().destroy();
            }
            $tabela.DataTable({
                responsive: true,
                paging: false,
                searching: false,
                info: false,
                ordering: false,
                language: {
                    emptyTable: "Nenhum produto adicionado a este pedido.",
                    loadingRecords: "Carregando..."
                }
            });
        });

        $(window).trigger('resize');
    }


    function ajustarResponsividadeTabelas() {
        $('.tabela-pedido').each(function () {
            const $tabela = $(this);
            if ($.fn.DataTable.isDataTable($tabela)) {
                // Comando que faz a mágica: forçar o ajuste e recálculo
                $tabela.DataTable().columns.adjust().responsive.recalc();
            }
        });
    }

    // Debounce para evitar múltiplas execuções rápidas
    let resizeTimeout;
    $(window).on('resize', function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            ajustarResponsividadeTabelas();
        }, 100); // tempo ideal para evitar sobrecarga
    });

    function carregarOrdemCompleta(id) {
        $.ajax({
            url: 'ajax_router.php?action=getOrdemExpedicaoCompleta',
            type: 'POST',
            data: { oe_id: id, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // 1. Renderiza a tela primeiro
                    renderizarDetalhes(response.data);

                    // 2. Aplica o bloqueio passando o objeto correto
                    aplicarBloqueioReprocesso(response.data.header);
                } else {
                    notificacaoErro('Erro ao Carregar', response.message);
                }
            },
            error: function () {
                notificacaoErro('Erro de Conexão', 'Não foi possível carregar os detalhes da ordem.');
            }
        });
    }

    // Função para verificar e bloquear se for Reprocesso
    function aplicarBloqueioReprocesso(dadosOE) {
        if (dadosOE.oe_tipo_operacao === 'REPROCESSO') {

            // 1. Aviso Visual
            if ($('#aviso-reprocesso').length === 0) {
                $('.card-body').first().prepend(`
                    <div id="aviso-reprocesso" class="alert alert-warning border-left-warning shadow-sm">
                        <i class="fas fa-lock me-2"></i>
                        <strong>Modo Leitura:</strong> Esta é uma Ordem de Reprocesso Interno. 
                        As alterações devem ser feitas no módulo de <strong>Gestão de Lotes (Recebimento)</strong>.
                    </div>
                `);
            }

            // 2. Desabilitar Botões de Ação
            $('#btn-confirmar-add-item').prop('disabled', true).hide();
            $('#btn-salvar-header').prop('disabled', true).hide();

            // 3. Desabilitar Botões de Exclusão/Edição na Tabela (usando delegate se for dinâmico)
            $('#pedidos-container').addClass('disable-actions'); // CSS Class helper
            $('.btn-remover-item, .btn-editar-item').remove(); // Remove botões existentes

            // 4. Desabilitar Inputs
            $('input, select, textarea').prop('disabled', true);

            // Manter apenas o botão de voltar ativo
            $('.btn-secondary').prop('disabled', false);
        }
    }

    function resetModalEstoqueParaAbrir() {
        $selectProduto.val(null).trigger('change');
        $selectLote.empty().append('<option value=""></option>').prop('disabled', true).trigger('change');
        $selectEndereco.empty().append('<option value=""></option>').prop('disabled', true).trigger('change');
        $displaySaldo.val(''); $inputQtd.val('').prop('disabled', true);
        $inputObs.val(''); $btnAddItem.prop('disabled', true);
    }

    // ### INICIALIZAÇÃO DO DRAG AND DROP ###
    // Variável 'pedidosContainerEl' já está definida acima para o ResizeObserver.
    // Usaremos a variável local para evitar duplicação (que você já tinha feito)
    const containerParaSortable = document.getElementById('pedidos-container');

    if (containerParaSortable) {
        sortableInstance = new Sortable(containerParaSortable, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: function () {
                const cards = containerParaSortable.querySelectorAll('.pedido-group');
                const novaOrdemIds = Array.from(cards).map(card => card.dataset.oepId);

                $.ajax({
                    url: 'ajax_router.php?action=salvarOrdemClientes',
                    type: 'POST',
                    data: {
                        ordem: novaOrdemIds,
                        csrf_token: csrfToken
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            // Sucesso silencioso - não fazemos nada, como você sugeriu.
                            /* notificacaoSucesso('Sucesso!', response.message); */
                        } else {
                            // ERRO ALTO - Avisamos o usuário que algo deu errado.
                            notificacaoErro('Erro ao Salvar Ordem!', response.message);
                        }
                    },
                    error: function () {
                        // Erro de conexão também é importante avisar.
                        notificacaoErro('Erro de Conexão!', 'Não foi possível salvar a nova ordem.');
                    }
                });
            }
        });
    }

    // --- Lógica inicial da página ---
    if (ordemId) {
        carregarOrdemCompleta(ordemId);
    } else {
        $('#btn-salvar-header').show(); // Mostra o botão só na criação
        $.getJSON('ajax_router.php?action=getNextOrderNumber', function (response) {
            if (response.success) {
                $('#oe_numero').val(response.numero);
            }
        });
    }

    $formHeader.on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax_router.php?action=salvarOrdemExpedicaoHeader',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    notificacaoSucesso('Sucesso!', 'Cabeçalho da ordem salvo.');

                    // 1. Atualiza o ID da ordem na variável e no campo oculto
                    ordemId = response.oe_id;
                    $('#ordem_id').val(ordemId);

                    // 2. Atualiza a URL do navegador sem recarregar
                    const newUrl = `index.php?page=detalhes_ordem_expedicao&id=${ordemId}`;
                    window.history.pushState({ path: newUrl }, '', newUrl);

                    // 3. Transforma a página para o modo de edição
                    $('#main-title').text(`Editar Ordem de Expedição: ${$('#oe_numero').val()} `);
                    $formHeader.find('input').prop('disabled', true); // Desabilita os inputs
                    $('#btn-salvar-header').hide(); // Esconde o botão de salvar
                    $('#section-details').show(); // Mostra a seção de adicionar clientes/produtos

                } else {
                    notificacaoErro('Erro', response.message);
                }
            },
            error: function () {
                notificacaoErro('Erro de Conexão', 'Não foi possível salvar o cabeçalho.');
            }
        });
    });

    $('#btn-adicionar-pedido-cliente').on('click', function () {
        $formPedido[0].reset();
        $('#oep_ordem_id').val(ordemId);
        $selectCliente.val(null).trigger('change');
        $modalPedido.modal('show');
    });

    $selectCliente.select2({
        placeholder: "Selecione um cliente",
        dropdownParent: $modalPedido,
        theme: "bootstrap-5",
        ajax: {
            url: 'ajax_router.php?action=getClienteOptions',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term // Envia o termo de busca para o servidor
                };
            },

            processResults: function (data) {
                return {
                    results: data.data

                };
            }
        }
    });

    $formPedido.on('submit', function (e) {
        e.preventDefault();

        // --- VALIDAÇÃO PARA CAMPO DE PEDIDO OBRIGATÓRIO ---
        const numPedido = $('#oep_numero_pedido').val().trim();
        if (!numPedido) {
            notificacaoErro('Campo Obrigatório', 'Por favor, informe o Número do Pedido do Cliente.');
            return; // Impede o envio do formulário
        }
        // --- FIM DA VALIDAÇÃO ---

        $.ajax({
            url: 'ajax_router.php?action=addPedidoClienteOrdem',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalPedido.modal('hide');
                    notificacaoSucesso('Sucesso!', response.message);
                    carregarOrdemCompleta(ordemId);
                } else {
                    notificacaoErro('Erro!', response.message);
                }
            }
        });
    });

    $selectProduto.select2({
        placeholder: "Selecione um produto...",
        dropdownParent: $modalEstoque,
        theme: "bootstrap-5",
        ajax: {
            url: "ajax_router.php?action=getProdutosComEstoqueDisponivel",
            dataType: 'json',
            delay: 250,

            // ### A PARTE QUE ENVIA O TERMO DE BUSCA ###
            data: function (params) {
                return {
                    term: params.term
                };
            },

            processResults: function (data) {
                return {
                    results: data.results
                };
            },
            cache: true
        }
    });

    $selectProduto.on('change', function () {
        const produtoId = $(this).val();
        $selectLote.val(null).trigger('change');

        if (produtoId) {
            $selectLote.prop('disabled', false);
            $.getJSON(`ajax_router.php?action=getLotesDisponiveisPorProduto&produto_id=${produtoId}`,
                function (data) {
                    $selectLote.empty().append('<option value=""></option>');
                    if (data.results) {
                        data.results.forEach(lote => {
                            $selectLote.append(new Option(lote.text, lote.id));
                        });
                    }
                });
        }
        else {
            $selectLote.prop('disabled', true).empty().append('<option value=""></option>');
        }
    });

    $selectLote.select2({
        placeholder: "Selecione um lote...",
        dropdownParent: $modalEstoque, theme: "bootstrap-5"
    });

    $selectEndereco.select2({
        placeholder: "Selecione um endereço...",
        dropdownParent: $modalEstoque, theme: "bootstrap-5"
    });

    $selectLote.on('change', function () {
        const loteItemId = $(this).val();
        $selectEndereco.val(null).trigger('change');
        if (loteItemId) {
            $selectEndereco.prop('disabled', false);
            $.getJSON(`ajax_router.php?action=getEnderecosDisponiveisPorLoteItem&lote_item_id=${loteItemId}`,
                function (data) {
                    $selectEndereco.empty().append('<option value=""></option>');
                    if (data.results) {
                        data.results.forEach(end => {
                            const option = new Option(end.text, end.id);
                            $(option).data('saldo', end.saldo_disponivel);
                            $selectEndereco.append(option);
                        });
                    }
                });
        }
        else {
            $selectEndereco.prop('disabled', true).empty().append('<option value=""></option>');
        }
    });

    $selectEndereco.on('change', function () {
        const selectedOption = $(this).find('option:selected');
        const saldo = selectedOption.data('saldo');
        $displaySaldo.val('');
        $inputQtd.prop('disabled', true).val('');
        $btnAddItem.prop('disabled', true);

        if (saldo > 0) {
            $displaySaldo.val(parseFloat(saldo).toFixed(3));
            $inputQtd.prop('disabled',
                false).attr('max', saldo).val(1);
            $btnAddItem.prop('disabled', false);
        }
    });

    $inputQtd.on('input', function () {
        const $campoQuantidade = $(this);
        const quantidadeDigitada = parseFloat($campoQuantidade.val());
        const saldoDisponivel = parseFloat($campoQuantidade.attr('max'));

        if (isNaN(quantidadeDigitada) || quantidadeDigitada <= 0 || quantidadeDigitada > saldoDisponivel) {
            $campoQuantidade.addClass('is-invalid');
            $btnAddItem.prop('disabled', true);
        } else {
            $campoQuantidade.removeClass('is-invalid');
            $btnAddItem.prop('disabled', false);
            $btnAddItem.prop('disabled', false);
        }
    });

    $btnAddItem.on('click', function () {
        const data = {
            oei_pedido_id: $('#hidden_oep_id').val(),
            oei_alocacao_id: $selectEndereco.val(),
            oei_quantidade: $inputQtd.val(),
            oei_observacao: $inputObs.val(),
            csrf_token: csrfToken
        };
        if (!data.oei_alocacao_id || !data.oei_quantidade || data.oei_quantidade <= 0) {
            notificacaoErro('Atenção', 'Selecione um endereço e informe uma quantidade válida.');
            return;
        } $.ajax({
            url: 'ajax_router.php?action=addItemPedidoOrdem',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalEstoque.modal('hide');
                    notificacaoSucesso('Sucesso!', response.message);
                    carregarOrdemCompleta(ordemId);
                } else {
                    notificacaoErro('Erro', response.message);
                }
            }
        });
    });

    $pedidosContainer.on('click', '.btn-adicionar-produto', function () {
        $('#hidden_oep_id').val($(this).data('oep-id'));
        resetModalEstoqueParaAbrir();
        $modalEstoque.modal('show');
    });

    $pedidosContainer.on('click', '.btn-remover-pedido', function () {
        const pedidoId = $(this).data('oep-id');
        Swal.fire({
            title: 'Tem certeza?',
            text: "Deseja remover este pedido e todos os seus itens?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, remover!',
            cancelButtonText: 'Cancelar'
        })
            .then((result) => {
                if (result.isConfirmed) {
                    $.post('ajax_router.php?action=removePedidoOrdem',
                        {
                            oep_id: pedidoId,
                            csrf_token: csrfToken
                        },
                        function (response) {
                            if (response.success) {
                                notificacaoSucesso('Removido!',
                                    response.message);
                                carregarOrdemCompleta(ordemId);
                            } else {
                                notificacaoErro('Erro', response.message);
                            }
                        }, 'json');
                }
            });
    });

    // ### EVENTO PARA ABRIR E PREENCHER O MODAL DE EDIÇÃO ###
    $pedidosContainer.on('click', '.btn-editar-item', function () {
        const oeiId = $(this).data('oei-id');
        if (!oeiId) return;

        // Faz a chamada AJAX para buscar os detalhes do item
        $.ajax({
            url: 'ajax_router.php?action=getItemDetalhesParaEdicao',
            type: 'POST',
            data: { oei_id: oeiId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    const $modal = $('#modal-editar-item');

                    // Preenche os campos do modal com os dados recebidos
                    $modal.find('#edit_oei_id').val(oeiId);
                    $modal.find('#edit-produto-nome').text(data.prod_descricao);
                    $modal.find('#edit_oei_quantidade').val(parseFloat(data.oei_quantidade).toFixed(0));
                    $modal.find('#edit_oei_observacao').val(data.oei_observacao);

                    // Define o valor máximo permitido para a quantidade
                    const maxQty = parseFloat(data.max_quantidade_disponivel);
                    $modal.find('#edit_oei_quantidade').attr('max', maxQty);
                    $modal.find('#edit-saldo-info').text(`Quantidade máxima permitida: ${maxQty}`);

                    // Abre o modal
                    $modal.modal('show');
                } else {
                    notificacaoErro('Erro', response.message);
                }
            },
            error: function () {
                notificacaoErro('Erro de Conexão', 'Não foi possível buscar os dados do item.');
            }
        });
    });

    // Validação em tempo real para o campo de quantidade do modal de edição
    $('#edit_oei_quantidade').on('input', function () {
        const $campo = $(this);
        const quantidade = parseFloat($campo.val());
        const maximo = parseFloat($campo.attr('max'));

        if (isNaN(quantidade) || quantidade <= 0 || quantidade > maximo) {
            $campo.addClass('is-invalid');
            $('#form-editar-item button[type="submit"]').prop('disabled', true);
        } else {
            $campo.removeClass('is-invalid');
            $('#form-editar-item button[type="submit"]').prop('disabled', false);
        }
    });

    // ### EVENTO PARA SALVAR OS DADOS DO MODAL DE EDIÇÃO ###
    $('#form-editar-item').on('submit', function (e) {
        e.preventDefault(); // Impede o recarregamento da página

        $.ajax({
            url: 'ajax_router.php?action=updateItemPedido',
            type: 'POST',
            data: $(this).serialize(), // Envia os dados do formulário (id, qtd, obs)
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#modal-editar-item').modal('hide');
                    notificacaoSucesso('Sucesso!', response.message);
                    // Recarrega todos os detalhes da ordem para refletir a mudança
                    carregarOrdemCompleta(ordemId);
                } else {
                    notificacaoErro('Erro ao Atualizar', response.message);
                }
            },
            error: function () {
                notificacaoErro('Erro de Conexão', 'Não foi possível salvar as alterações.');
            }
        });
    });

    $pedidosContainer.on('click', '.btn-remover-item', function () {
        const itemId = $(this).data('oei-id');
        Swal.fire({
            title: 'Tem certeza?',
            text: "Deseja remover este item?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, remover!',
            cancelButtonText: 'Cancelar'
        })
            .then((result) => {
                if (result.isConfirmed) {
                    $.post('ajax_router.php?action=removeItemPedidoOrdem',
                        {
                            oei_id: itemId,
                            csrf_token: csrfToken
                        },
                        function (response) {
                            if (response.success) {
                                notificacaoSucesso('Removido!',
                                    response.message);
                                carregarOrdemCompleta(ordemId);
                            }
                            else {
                                notificacaoErro('Erro', response.message);
                            }
                        }, 'json');
                }
            });
    });
});