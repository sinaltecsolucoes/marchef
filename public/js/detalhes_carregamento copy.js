// /public/js/detalhes_carregamento.js
$(document).ready(function () {
    const csrfToken = $('input[name="csrf_token"]').val();
    const urlParams = new URLSearchParams(window.location.search);
    const carregamentoId = urlParams.get('id');

    // Modais (cache)
    const $modalAddItemOE = $('#modal-adicionar-item-oe');
    const $modalAddDivergencia = $('#modal-adicionar-divergencia');

    // Containers (cache)
    const $filasContainer = $('#filas-container');
    const $tabelaPlanejamentoBody = $('#tabela-planejamento tbody');
    const $tabelaItensOESelecaoBody = $('#tabela-itens-oe-para-selecao tbody');

    // --- FUNÇÃO PRINCIPAL DE CARREGAMENTO ---
    function loadDetalhesCarregamento() {
        if (!carregamentoId) {
            Swal.fire('Erro', 'ID do Carregamento não encontrado.', 'error');
            return;
        }

        $.ajax({
            url: 'ajax_router.php?action=getCarregamentoDetalhesCompletos', // ROTA NOVA
            type: 'POST',
            data: {
                carregamento_id: carregamentoId,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    renderCabecalho(data.header);
                    renderPlanejamento(data.planejamento);
                    renderExecucao(data.execucao);

                    // Passa os dados para os modais usarem
                    prepararModalOE(data.planejamento);
                    prepararModalDivergencia(carregamentoId);

                } else {
                    Swal.fire('Erro ao carregar', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro de Conexão', 'Não foi possível buscar os dados do carregamento.', 'error');
            }
        });
    }

    // --- FUNÇÕES DE RENDERIZAÇÃO ---

    function renderCabecalho(header) {
        if (!header) return;
        $('#carregamento_id').val(header.car_id);
        $('#main-title').text(`Carregamento Nº: ${header.car_numero}`);
        $('#car_numero').val(header.car_numero);
        $('#car_data').val(header.car_data);
        $('#oe_numero_base').val(header.oe_numero || 'N/A');
        $('#cliente_responsavel_nome').val(header.cliente_responsavel_nome || 'N/A');
        $('#transportadora_nome').val(header.transportadora_nome || 'N/A');
        $('#car_motorista_nome').val(header.car_motorista_nome || '');
        $('#car_motorista_cpf').val(header.car_motorista_cpf || '');
        $('#car_placas').val(header.car_placas || '');
        $('#car_lacres').val(header.car_lacres || '');
    }

    function renderPlanejamento(planejamento) {
        $tabelaPlanejamentoBody.empty();
        if (!planejamento || planejamento.length === 0) {
            $tabelaPlanejamentoBody.html('<tr><td colspan="7" class="text-center">Nenhum item encontrado na Ordem de Expedição.</td></tr>');
            return;
        }

        planejamento.forEach(item => {
            const saldo = parseFloat(item.qtd_planejada) - parseFloat(item.qtd_carregada);
            const saldoClass = saldo > 0 ? 'text-warning' : (saldo < 0 ? 'text-danger' : 'text-success');

            const html = `
                <tr data-oe-item-id="${item.oei_id}">
                    <td>${item.cliente_nome}</td>
                    <td>${item.prod_descricao}</td>
                    <td>${item.lote_completo}</td>
                    <td>${item.endereco_completo}</td>
                    <td class="text-end">${item.qtd_planejada}</td>
                    <td class="text-end">${item.qtd_carregada}</td>
                    <td class="text-end fw-bold ${saldoClass}">${saldo}</td>
                </tr>
            `;
            $tabelaPlanejamentoBody.append(html);
        });
    }

    function renderExecucao(filas) {
        $filasContainer.empty();
        if (!filas || filas.length === 0) {
            $filasContainer.html('<p class="text-center text-muted">Nenhuma fila adicionada a este carregamento.</p>');
            return;
        }

        filas.forEach(fila => {
            let itensHtml = '';
            if (fila.itens && fila.itens.length > 0) {
                fila.itens.forEach(item => {
                    const divergenciaBadge = item.motivo_divergencia
                        ? `<span class="badge bg-danger" title="Divergência: ${item.motivo_divergencia}">D</span>`
                        : '';

                    itensHtml += `
                        <tr>
                            <td>${item.cliente_nome} ${divergenciaBadge}</td>
                            <td>${item.prod_descricao}</td>
                            <td>${item.lote_completo}</td>
                            <td>${item.endereco_completo}</td>
                            <td class="text-end">${item.qtd_carregada}</td>
                            <td class="text-center">
                                <button class="btn btn-danger btn-xs btn-remover-item" data-item-id="${item.car_item_id}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                itensHtml = '<tr><td colspan="6" class="text-center text-muted">Nenhum item nesta fila.</td></tr>';
            }

            const filaHtml = `
                <div class="fila-group border rounded p-3 mb-3 shadow-sm" data-fila-id="${fila.fila_id}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Fila ${fila.fila_numero_sequencial}</h5>
                        <div>
                            <button class="btn btn-info btn-sm btn-adicionar-item-oe" data-fila-id="${fila.fila_id}">
                                <i class="fas fa-check me-1"></i> Add (da OE)
                            </button>
                            <button class="btn btn-warning btn-sm btn-adicionar-divergencia" data-fila-id="${fila.fila_id}">
                                <i class="fas fa-exclamation-triangle me-1"></i> Add (Divergência)
                            </button>
                            <button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th>Produto</th>
                                <th>Lote</th>
                                <th>Endereço</th>
                                <th class="text-end">Qtd. Carregada</th>
                                <th class="text-center">Ação</th>
                            </tr>
                        </thead>
                        <tbody>${itensHtml}</tbody>
                    </table>
                </div>
            `;
            $filasContainer.append(filaHtml);
        });
    }

    // --- PREPARAÇÃO DOS MODAIS ---

    function prepararModalOE(planejamento) {
        $tabelaItensOESelecaoBody.empty();
        let hasItens = false;

        planejamento.forEach(item => {
            const saldo = parseFloat(item.qtd_planejada) - parseFloat(item.qtd_carregada);
            if (saldo > 0) { // Só mostra itens que ainda têm saldo
                hasItens = true;
                const html = `
                    <tr data-oe-item-id="${item.oei_id}">
                        <td>${item.cliente_nome}</td>
                        <td>${item.prod_descricao}</td>
                        <td>${item.lote_completo}</td>
                        <td>${item.endereco_completo}</td>
                        <td class="text-end saldo-disponivel">${saldo}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm qtd-a-carregar" min="1" max="${saldo}" value="1">
                        </td>
                        <td class="text-center">
                            <button class="btn btn-success btn-sm btn-confirmar-add-oe-item" data-oei-id="${item.oei_id}">Add</button>
                        </td>
                    </tr>
                `;
                $tabelaItensOESelecaoBody.append(html);
            }
        });

        if (!hasItens) {
            $tabelaItensOESelecaoBody.html('<tr><td colspan="7" class="text-center">Nenhum item pendente no planejamento.</td></tr>');
        }
    }

    function prepararModalDivergencia(carregamentoId) {
        $('#modal_div_carregamento_id').val(carregamentoId);

        // Cliente (Select2 com todos os clientes)
        $('#div_cliente_id').select2({
            placeholder: "Selecione um cliente",
            dropdownParent: $modalAddDivergencia,
            theme: "bootstrap-5",
            ajax: {
                url: 'ajax_router.php?action=getClienteOptions', // Rota que já existe
                dataType: 'json',
                delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data.data }; }
            }
        });

        // Lógica dos 3 Selects (copiada de 'detalhes_ordem_expedicao.js')
        const $selectProduto = $('#div_produto_estoque');
        const $selectLote = $('#div_lote_estoque');
        const $selectEndereco = $('#div_endereco_estoque');
        const $displaySaldo = $('#div_saldo_display');
        const $inputQtd = $('#div_quantidade');
        const $btnAddItem = $('#btn-confirmar-add-divergencia');

        $selectProduto.select2({
            placeholder: "Selecione um produto...",
            dropdownParent: $modalAddDivergencia,
            theme: "bootstrap-5",
            ajax: {
                url: "ajax_router.php?action=getProdutosComEstoqueDisponivel", // Rota que já existe
                dataType: 'json',
                delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data.results }; },
            }
        });

        $selectProduto.on('change', function () {
            const produtoId = $(this).val();
            $selectLote.val(null).trigger('change');
            if (produtoId) {
                $selectLote.prop('disabled', false);
                $.getJSON(`ajax_router.php?action=getLotesDisponiveisPorProduto&produto_id=${produtoId}`, function (data) {
                    $selectLote.empty().append('<option value=""></option>');
                    if (data.results) {
                        data.results.forEach(lote => $selectLote.append(new Option(lote.text, lote.id)));
                    }
                });
            } else {
                $selectLote.prop('disabled', true).empty().append('<option value=""></option>');
            }
        });

        $selectLote.select2({ placeholder: "Selecione um lote...", dropdownParent: $modalAddDivergencia, theme: "bootstrap-5" });
        $selectEndereco.select2({ placeholder: "Selecione um endereço...", dropdownParent: $modalAddDivergencia, theme: "bootstrap-5" });

        $selectLote.on('change', function () {
            const loteItemId = $(this).val();
            $selectEndereco.val(null).trigger('change');
            if (loteItemId) {
                $selectEndereco.prop('disabled', false);
                $.getJSON(`ajax_router.php?action=getEnderecosDisponiveisPorLoteItem&lote_item_id=${loteItemId}`, function (data) {
                    $selectEndereco.empty().append('<option value=""></option>');
                    if (data.results) {
                        data.results.forEach(end => {
                            const option = new Option(end.text, end.id);
                            $(option).data('saldo', end.saldo_disponivel);
                            $selectEndereco.append(option);
                        });
                    }
                });
            } else {
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
                $inputQtd.prop('disabled', false).attr('max', saldo).val(1);
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
            }
        });
    }

    // --- EVENT HANDLERS (Ações da Página) ---

    // Adicionar Fila
    $('#btn-adicionar-fila').on('click', function () {
        $.post('ajax_router.php?action=addFilaCarregamento', { carregamento_id: carregamentoId, csrf_token: csrfToken }, function (response) {
            if (response.success) {
                loadDetalhesCarregamento(); // Recarrega tudo
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        }, 'json');
    });

    // Remover Fila
    $filasContainer.on('click', '.btn-remover-fila', function () {
        const filaId = $(this).data('fila-id');
        Swal.fire({
            title: 'Tem certeza?',
            text: "Deseja remover esta fila e TODOS os itens dentro dela?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=removeFilaCarregamento', { fila_id: filaId, csrf_token: csrfToken }, function (response) {
                    if (response.success) {
                        loadDetalhesCarregamento(); // Recarrega tudo
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // Remover Item (de uma fila)
    $filasContainer.on('click', '.btn-remover-item', function () {
        const itemId = $(this).data('item-id');
        // Adicionar lógica de confirmação e AJAX para 'action=removeItemCarregamento'
        // ... (Semelhante ao 'remover fila', mas chamando 'removeItemCarregamento')
        Swal.fire({
            title: 'Tem certeza?',
            text: "Deseja remover este item da fila?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=removeItemCarregamento', { car_item_id: itemId, csrf_token: csrfToken }, function (response) {
                    if (response.success) {
                        loadDetalhesCarregamento(); // Recarrega tudo
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });


    // --- EVENT HANDLERS (Ações dos Modais) ---

    // Abrir Modal "Adicionar (da OE)"
    $filasContainer.on('click', '.btn-adicionar-item-oe', function () {
        const filaId = $(this).data('fila-id');
        $('#modal_oe_fila_id').val(filaId);
        $modalAddItemOE.modal('show');
    });

    // Confirmar Adição (do Modal OE)
    $modalAddItemOE.on('click', '.btn-confirmar-add-oe-item', function () {
        const $row = $(this).closest('tr');
        const oeiId = $(this).data('oei-id');
        const filaId = $('#modal_oe_fila_id').val();
        const quantidade = $row.find('.qtd-a-carregar').val();
        const saldo = parseFloat($row.find('.saldo-disponivel').text());

        if (quantidade <= 0 || quantidade > saldo) {
            Swal.fire('Erro', 'Quantidade inválida ou excede o saldo.', 'error');
            return;
        }

        $.post('ajax_router.php?action=addItemCarregamentoFromOE', {
            fila_id: filaId,
            oei_id: oeiId,
            quantidade: quantidade,
            csrf_token: csrfToken
        }, function (response) {
            if (response.success) {
                $modalAddItemOE.modal('hide');
                loadDetalhesCarregamento(); // Recarrega tudo
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        }, 'json');
    });

    // Abrir Modal "Adicionar (Divergência)"
    $filasContainer.on('click', '.btn-adicionar-divergencia', function () {
        const filaId = $(this).data('fila-id');
        $('#modal_div_fila_id').val(filaId);
        // Resetar o form de divergência
        $('#form-add-divergencia')[0].reset();
        $('#div_cliente_id').val(null).trigger('change');
        $('#div_produto_estoque').val(null).trigger('change');
        $('#div_lote_estoque').val(null).trigger('change').prop('disabled', true);
        $('#div_endereco_estoque').val(null).trigger('change').prop('disabled', true);
        $('#btn-confirmar-add-divergencia').prop('disabled', true);

        $modalAddDivergencia.modal('show');
    });

    // Confirmar Adição (do Modal Divergência)
    $('#form-add-divergencia').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'ajax_router.php?action=addItemCarregamentoDivergencia', // ROTA NOVA
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalAddDivergencia.modal('hide');
                    loadDetalhesCarregamento(); // Recarrega tudo
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro', 'Não foi possível conectar ao servidor.', 'error');
            }
        });
    });

    // --- INICIALIZAÇÃO ---
    loadDetalhesCarregamento();
});