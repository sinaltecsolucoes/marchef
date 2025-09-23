// /public/js/detalhes_carregamento.js
$(document).ready(function () {
    const csrfToken = $('input[name="csrf_token"]').val();
    const urlParams = new URLSearchParams(window.location.search);
    const carregamentoId = urlParams.get('id');
    let oeId = null; // ID da OE Base

    // Cache dos Elementos do Cabeçalho
    const $formHeader = $('#form-carregamento-header');
    const $inputsHeader = $formHeader.find('input, select');
    const $btnEditarHeader = $('#btn-editar-header');
    const $btnSalvarHeader = $('#btn-salvar-header');
    const $btnCancelarHeader = $('#btn-cancelar-header');

    // Novo Modal Unificado
    const $modalAddItem = $('#modal-adicionar-item-carregamento');
    const $formAddItem = $('#form-adicionar-item');

    // Containers (cache)
    const $filasContainer = $('#filas-container');
    const $tabelaPlanejamentoBody = $('#tabela-planejamento-body');

    // Nossos dados-mestres
    let dadosOriginaisHeader = {};
    let gabaritoPlanejamento = []; // A "OE Base"

    // Caches dos campos do novo modal
    const $itemCliente = $('#item_cliente_id');
    const $itemProduto = $('#item_produto_id');
    const $itemLote = $('#item_lote_id');
    const $itemAlocacao = $('#item_alocacao_id');
    const $itemSaldo = $('#item_saldo_display');
    const $itemQtd = $('#item_quantidade');
    const $itemHelper = $('#item_helper_text');
    const $itemMotivoContainer = $('#container-motivo-divergencia');
    const $itemMotivoInput = $('#item_motivo_divergencia');
    const $itemOeiId = $('#item_oei_id_origem');
    const $itemBtnAdd = $('#btn-confirmar-add-item');

    let dadosEnderecos = []; // Cache para guardar o saldo físico

    // --- FUNÇÕES DE NOTIFICAÇÃO (Helpers) ---
    function notificacaoSucesso(titulo, mensagem) {
        Swal.fire({
            icon: 'success', title: titulo, text: mensagem,
            showConfirmButton: false, timer: 1500
        });
    }

    // --- FUNÇÃO PRINCIPAL DE CARREGAMENTO ---
    function loadDetalhesCarregamento() {
        if (!carregamentoId) { return; }
        $.ajax({
            url: 'ajax_router.php?action=getCarregamentoDetalhesCompletos',
            type: 'POST',
            data: { carregamento_id: carregamentoId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    oeId = data.header.car_ordem_expedicao_id;
                    $('#oe_id_hidden').val(oeId);

                    // Salva o gabarito da OE na variável local
                    gabaritoPlanejamento = data.planejamento || [];

                    renderCabecalho(data.header);
                    renderPlanejamento(gabaritoPlanejamento); // Usa a variável local
                    renderExecucao(data.execucao);

                } else {
                    Swal.fire('Erro ao carregar', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro de Conexão', 'Não foi possível buscar os dados do carregamento.', 'error');
            }
        });
    }

    // --- RENDERIZAÇÃO DO CABEÇALHO ---
    function renderCabecalho(header) {
        dadosOriginaisHeader = header;
        $('#carregamento_id').val(header.car_id);
        $('#main-title').text(`Carregamento Nº: ${header.car_numero}`);
        $('#car_numero').val(header.car_numero);
        $('#car_data').val(header.car_data);
        $('#oe_numero_base').val(header.oe_numero || 'N/A');
        $('#oe_id_hidden').val(header.car_ordem_expedicao_id); // Importante
        setSelect2Value('#car_entidade_id_organizador', header.car_entidade_id_organizador, header.cliente_responsavel_nome);
        setSelect2Value('#car_transportadora_id', header.car_transportadora_id, header.transportadora_nome);
        $('#car_motorista_nome').val(header.car_motorista_nome || '');
        $('#car_motorista_cpf').val(header.car_motorista_cpf || '');
        $('#car_placas').val(header.car_placas || '');
        $('#car_lacres').val(header.car_lacres || '');

        // Aplica máscara CPF
        $('#car_motorista_cpf').mask('000.000.000-00');

        // Máscara de PLACA
        $('#car_placas').mask('SSS-0A00 / SSS-0A00', {
            translation: {
                'S': { pattern: /[A-Za-z]/ },
                'A': { pattern: /[A-Za-z0-9]/ }
            },
            onKeyPress: function (val, e, field, options) {
                field.val(val.toUpperCase());
                if (val.length === 8) {
                    if (val.charAt(7) !== ' ') {
                        let charExtra = val.charAt(7);
                        let newVal = val.substring(0, 7) + ' / ' + charExtra;
                        field.val(newVal);
                        field.mask('SSS-0A00 / SSS-0A00', options);
                    }
                }
            },
            clearIfNotMatch: true
        });

        if (header.car_status === 'EM ANDAMENTO') {
            $('#btn-finalizar-detalhe').show();
            $('#btn-editar-header').show();
        } else {
            $('#btn-finalizar-detalhe').hide();
            $('#btn-editar-header').hide();
        }
    }

    // --- RENDERIZAÇÃO DO PLANEJAMENTO (Gabarito OE) ---
    function renderPlanejamento(planejamento) {
        $tabelaPlanejamentoBody.empty();
        if (!planejamento || planejamento.length === 0) {
            $tabelaPlanejamentoBody.html('<tr><td colspan="7" class="text-center text-muted">Nenhum item encontrado na Ordem de Expedição base.</td></tr>');
            return;
        }
        planejamento.forEach(item => {
            const saldo = parseFloat(item.qtd_planejada) - parseFloat(item.qtd_carregada);

            // Lógica de Cor (Verde=0, Vermelho<0, Azul>0)
            let saldoClass = '';
            if (saldo === 0) {
                saldoClass = 'text-success'; // Verde (Carregado)
            }
            else if (saldo < 0) {
                saldoClass = 'text-danger'; // Vermelho (Carregado a mais)
            }
            else {
                saldoClass = 'text-primary'; // Azul (Ainda não carregado)
            }
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

    // --- RENDERIZAÇÃO DA EXECUÇÃO (Filas e Itens) ---
    function renderExecucao(filas) {
        $filasContainer.empty();
        if (!filas || filas.length === 0) {
            $filasContainer.html('<p class="text-center text-muted">Nenhuma fila adicionada a este carregamento.</p>');
        }

        filas.forEach((fila, index) => {
            const clientesNaFila = {};
            if (fila.itens && fila.itens.length > 0) {
                fila.itens.forEach(item => {
                    if (!clientesNaFila[item.car_item_cliente_id]) {
                        clientesNaFila[item.car_item_cliente_id] = { nome: item.cliente_nome, itens: [] };
                    }
                    clientesNaFila[item.car_item_cliente_id].itens.push(item);
                });
            }

            let clientesHtml = '';
            for (const clienteId in clientesNaFila) {
                const cliente = clientesNaFila[clienteId];
                let itensHtml = '';
                cliente.itens.forEach(item => {
                    // Badge de Divergência
                    const divergenciaBadge = item.motivo_divergencia
                        ? `<span class="badge bg-danger" title="Divergência: ${item.motivo_divergencia}">D</span>`
                        : '';
                    itensHtml += `
                        <tr>
                            <td>${item.prod_descricao} ${divergenciaBadge}</td>
                            <td>${item.lote_completo}</td>
                            <td>${item.endereco_completo}</td>
                            <td class="text-end">${item.qtd_carregada}</td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-xs me-1 btn-editar-item" data-item-id="${item.car_item_id}" title="Editar Quantidade"><i class="fas fa-pencil-alt"></i></button>
                                <button class="btn btn-danger btn-xs btn-remover-item" data-item-id="${item.car_item_id}" title="Remover Item"><i class="fas fa-times"></i></button>
                            </td>
                        </tr>
                    `;
                });

                clientesHtml += `
                    <div class="cliente-group border rounded p-3 mb-3" data-cliente-id="${clienteId}">
                         <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Cliente: ${cliente.nome}</h6>
                            
                            <div class="btn-group">
                                <button class="btn btn-info btn-xs btn-add-item-to-cliente" 
                                    data-fila-id="${fila.fila_id}" 
                                    data-cliente-id="${clienteId}" 
                                    data-cliente-nome="${cliente.nome}" 
                                    title="Adicionar mais itens para este cliente">
                                    <i class="fas fa-plus"></i> Add Item
                                </button>
                                <button class="btn btn-danger btn-sm btn-remove-cliente-from-fila" 
                                    data-fila-id="${fila.fila_id}" 
                                    data-cliente-id="${clienteId}" 
                                    title="Remover este cliente e todos os seus itens da fila">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                         </div>
                         `;
            }

            // Lógica do botão de remover fila
            let removerFilaBtnHtml = '';
            if (index === filas.length - 1) {
                removerFilaBtnHtml = `<button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}"><i class="fas fa-trash"></i></button>`;
            } else {
                removerFilaBtnHtml = `<button class="btn btn-danger btn-sm" data-fila-id="${fila.fila_id}" disabled title="Remova as filas posteriores para poder excluir esta."><i class="fas fa-trash"></i></button>`;
            }

            const filaHtml = `
                <div class="fila-group border rounded p-3 mb-3 shadow-sm" data-fila-id="${fila.fila_id}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Fila ${fila.fila_numero_sequencial}</h5>
                        <div>
                            <button class="btn btn-info btn-sm btn-adicionar-item-fila" data-fila-id="${fila.fila_id}">
                                <i class="fas fa-plus me-1"></i> Adicionar Item
                            </button>
                            ${removerFilaBtnHtml}
                        </div>
                    </div>
                    <div class="clientes-container-fila">
                        ${clientesHtml || '<p class="text-center text-muted small">Nenhum cliente/item nesta fila.</p>'}
                    </div>
                </div>
            `;
            $filasContainer.append(filaHtml);
        });

        // Regra de "só pode criar fila se a anterior não estiver vazia" 
        if (filas.length > 0) {
            const ultimaFila = filas[filas.length - 1];
            if (!ultimaFila.itens || ultimaFila.itens.length === 0) {
                $('#btn-adicionar-fila').prop('disabled', true).attr('title', 'Adicione itens à última fila para poder criar uma nova.');
            } else {
                $('#btn-adicionar-fila').prop('disabled', false).attr('title', '');
            }
        } else {
            $('#btn-adicionar-fila').prop('disabled', false).attr('title', '');
        }
    }

    // --- LÓGICA DO NOVO MODAL UNIFICADO ---

    function inicializarLogicaModalUnificado() {
        // 1. Inicializa os Select2 do modal
        $itemCliente.select2({
            placeholder: "Selecione um cliente...",
            dropdownParent: $modalAddItem, theme: "bootstrap-5",
            ajax: {
                url: 'ajax_router.php?action=getClienteOptions',
                dataType: 'json', delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data.data }; }
            }
        });

        $itemProduto.select2({
            placeholder: "Selecione um produto...",
            dropdownParent: $modalAddItem, theme: "bootstrap-5",
            ajax: {
                url: "ajax_router.php?action=getProdutosComEstoqueDisponivel",
                dataType: 'json', delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data.results }; },
            }
        });

        $itemLote.select2({ placeholder: "Selecione um lote...", dropdownParent: $modalAddItem, theme: "bootstrap-5" });
        $itemAlocacao.select2({ placeholder: "Selecione um endereço...", dropdownParent: $modalAddItem, theme: "bootstrap-5" });

        // 2. Evento de Abertura do Modal
        $filasContainer.on('click', '.btn-adicionar-item-fila', function () {
            const filaId = $(this).data('fila-id');
            $formAddItem[0].reset();
            $('#item_fila_id').val(filaId);

            // Reseta todos os selects e campos
            $itemCliente.val(null).trigger('change');
            $itemProduto.val(null).trigger('change').prop('disabled', true);
            $itemLote.empty().append('<option value=""></option>').prop('disabled', true);
            $itemAlocacao.empty().append('<option value=""></option>').prop('disabled', true);

            checkAgainstGabarito(); // Limpa o formulário
            $modalAddItem.modal('show');

            // 2b. Evento de Abertura do Modal (COM CLIENTE PRÉ-SELECIONADO)
            $filasContainer.on('click', '.btn-add-item-to-cliente', function () {
                const filaId = $(this).data('fila-id');
                const clienteId = $(this).data('cliente-id');
                const clienteNome = $(this).data('cliente-nome');

                // Reseta o formulário e define a fila
                $formAddItem[0].reset();
                $('#item_fila_id').val(filaId);

                // Limpa e desabilita os selects de estoque
                $itemProduto.val(null).trigger('change').prop('disabled', false); // HABILITA o produto
                $itemLote.empty().append('<option value=""></option>').prop('disabled', true);
                $itemAlocacao.empty().append('<option value=""></option>').prop('disabled', true);
                checkAgainstGabarito(); // Limpa o formulário (helper, motivo, etc.)

                // --- A Mágica de Pré-seleção ---
                // 1. Cria a <option> do cliente se ela não existir no dropdown
                if ($itemCliente.find("option[value='" + clienteId + "']").length === 0) {
                    $itemCliente.append(new Option(clienteNome, clienteId, true, true));
                }
                // 2. Seleciona o cliente e DESABILITA o select
                $itemCliente.val(clienteId).trigger('change').prop('disabled', true);
                // --- Fim da Mágica ---

                // 3. Abre o modal
                $modalAddItem.modal('show');
            });

            // Precisamos garantir que o select do cliente seja re-habilitado ao fechar
            $modalAddItem.on('hidden.bs.modal', function () {
                $itemCliente.prop('disabled', false);
            });
        });

        // 3. Lógica Cascata
        $itemCliente.on('change', function () {
            if ($(this).val()) { $itemProduto.prop('disabled', false); }
            else { $itemProduto.val(null).trigger('change').prop('disabled', true); }
            checkAgainstGabarito();
        });

        $itemProduto.on('change', function () {
            const produtoId = $(this).val();
            $itemLote.val(null).trigger('change');
            if (produtoId) {
                $itemLote.prop('disabled', false);
                $.getJSON(`ajax_router.php?action=getLotesDisponiveisPorProduto&produto_id=${produtoId}`, function (data) {
                    $itemLote.empty().append('<option value=""></option>');
                    if (data.results) {
                        data.results.forEach(lote => $itemLote.append(new Option(lote.text, lote.id)));
                    }
                });
            } else {
                $itemLote.prop('disabled', true).empty().append('<option value=""></option>');
            }
            checkAgainstGabarito();
        });

        $itemLote.on('change', function () {
            const loteItemId = $(this).val();
            $itemAlocacao.val(null).trigger('change');
            if (loteItemId) {
                $itemAlocacao.prop('disabled', false);
                $.getJSON(`ajax_router.php?action=getEnderecosDisponiveisPorLoteItem&lote_item_id=${loteItemId}`, function (data) {
                    $itemAlocacao.empty().append('<option value=""></option>');
                    dadosEnderecos = data.results || []; // Salva o cache de endereços
                    if (dadosEnderecos.length > 0) {
                        dadosEnderecos.forEach(end => {
                            const option = new Option(end.text, end.id);
                            $itemAlocacao.append(option);
                        });
                    }
                });
            } else {
                $itemAlocacao.prop('disabled', true).empty().append('<option value=""></option>');
                dadosEnderecos = [];
            }
            checkAgainstGabarito();
        });

        $itemAlocacao.on('change', function () {
            checkAgainstGabarito();
        });

        // 4. Função que verifica o Gabarito (OE)
        function checkAgainstGabarito() {
            const clienteId = $itemCliente.val();
            const produtoId = $itemProduto.val();
            const alocacaoId = $itemAlocacao.val();

            // Reseta o estado
            $itemQtd.prop('disabled', true).val('');
            $itemSaldo.val('');
            $itemHelper.text('');
            $itemMotivoContainer.hide();
            $itemMotivoInput.prop('required', false);
            $itemOeiId.val('');
            $itemBtnAdd.prop('disabled', true);

            if (!clienteId || !produtoId || !alocacaoId) {
                return; // Precisa de tudo selecionado
            }

            // Procura no gabarito
            const itemGabarito = gabaritoPlanejamento.find(item =>
                item.oep_cliente_id == clienteId &&
                item.oei_alocacao_id == alocacaoId
            );

            let saldoFisico = 0;
            const endSelecionado = dadosEnderecos.find(e => e.id == alocacaoId);
            if (endSelecionado) {
                saldoFisico = parseFloat(endSelecionado.saldo_disponivel) || 0;
            }

            if (itemGabarito) {
                // --- CENÁRIO 1: ITEM ESTÁ NA OE ---
                const saldoOE = parseFloat(itemGabarito.qtd_planejada) - parseFloat(itemGabarito.qtd_carregada);

                $itemHelper.text(`Item conforme a OE. Saldo no plano: ${saldoOE.toFixed(0)}`).css('color', 'green');
                $itemSaldo.val(saldoOE.toFixed(0));
                $itemQtd.attr('max', saldoOE).val(saldoOE > 0 ? saldoOE : 1).prop('disabled', false);
                $itemOeiId.val(itemGabarito.oei_id); // Guarda o ID da OE

                $itemMotivoContainer.hide();
                $itemMotivoInput.prop('required', false);

            } else {
                // --- CENÁRIO 2: ITEM NÃO ESTÁ NA OE (DIVERGÊNCIA) ---
                $itemHelper.text('DIVERGÊNCIA: Item não planejado na OE. Motivo é obrigatório.').css('color', 'red');
                $itemSaldo.val(saldoFisico.toFixed(0));
                $itemQtd.attr('max', saldoFisico).val(1).prop('disabled', false);
                $itemOeiId.val(''); // Limpa o ID da OE

                $itemMotivoContainer.show();
                $itemMotivoInput.prop('required', true);
            }

            $itemBtnAdd.prop('disabled', false);
        }
    } // Fim de inicializarLogicaModalUnificado()

    // 5. Submit do Novo Formulário
    $formAddItem.on('submit', function (e) {
        e.preventDefault();

        // Validação de Quantidade
        const $campoQtd = $('#item_quantidade');
        const qtd = parseFloat($campoQtd.val());
        const max = parseFloat($campoQtd.attr('max'));

        if (isNaN(qtd) || qtd <= 0) {
            Swal.fire('Erro', 'A quantidade deve ser maior que zero.', 'error');
            return;
        }
        if (qtd > max) {
            Swal.fire('Erro', `A quantidade (${qtd}) excede o saldo disponível (${max}).`, 'error');
            return;
        }

        // Validação de Motivo (se visível)
        if ($itemMotivoContainer.is(':visible') && $itemMotivoInput.val().trim() === '') {
            Swal.fire('Erro', 'O motivo da divergência é obrigatório.', 'error');
            $itemMotivoInput.focus();
            return;
        }

        // Envio via AJAX para a NOVA rota
        $.ajax({
            url: 'ajax_router.php?action=addItemCarregamento',
            type: 'POST',
            //data: $(this).serialize(),
            data: $(this).serialize() + '&carregamento_id=' + carregamentoId,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalAddItem.modal('hide');
                    notificacaoSucesso('Sucesso!', 'Item adicionado ao carregamento.');
                    loadDetalhesCarregamento(); // Recarrega TUDO
                } else {
                    Swal.fire('Erro ao adicionar', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro de Conexão', 'Não foi possível salvar o item.', 'error');
            }
        });
    });

    // Helper: setSelect2Value
    function setSelect2Value(selector, id, text) {
        const $select = $(selector);
        if (id && text) {
            $select.empty().append(new Option(text, id, true, true)).trigger('change');
        } else {
            $select.empty().trigger('change');
        }
    }

    // Lógica para Editar/Salvar Cabeçalho
    function toggleHeaderEdit(isEditing) {
        if (isEditing) {
            $inputsHeader.filter(':not(#oe_numero_base)').prop('readonly', false).prop('disabled', false);
            initSelect2Header('#car_entidade_id_organizador', 'getClienteOptions');
            initSelect2Header('#car_transportadora_id', 'getTransportadoraOptions');
            $btnEditarHeader.hide(); $btnSalvarHeader.show(); $btnCancelarHeader.show();
        } else {
            $inputsHeader.prop('readonly', true).prop('disabled', true);
            $btnEditarHeader.show(); $btnSalvarHeader.hide(); $btnCancelarHeader.hide();
            renderCabecalho(dadosOriginaisHeader);
        }
    }
    function initSelect2Header(selector, action) {
        $(selector).select2({
            placeholder: "Selecione...", theme: "bootstrap-5",
            ajax: {
                url: `ajax_router.php?action=${action}`,
                dataType: 'json', delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data.data || data.results }; }
            }
        });
    }
    $btnEditarHeader.on('click', () => toggleHeaderEdit(true));
    $btnCancelarHeader.on('click', () => toggleHeaderEdit(false));

    // Submit do Cabeçalho
    $formHeader.on('submit', function (e) {
        e.preventDefault();
        const $cpfField = $('#car_motorista_cpf');
        const cpfLimpo = $cpfField.cleanVal ? $cpfField.cleanVal() : $cpfField.val().replace(/\D/g, '');
        const formData = $(this).serialize().replace(/car_motorista_cpf=[^&]*/, '') + '&car_motorista_cpf=' + cpfLimpo;

        $.ajax({
            url: 'ajax_router.php?action=updateCarregamentoHeader',
            type: 'POST', data: formData, dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire('Sucesso!', 'Cabeçalho atualizado.', 'success');
                    toggleHeaderEdit(false);
                    loadDetalhesCarregamento();
                } else { Swal.fire('Erro', response.message, 'error'); }
            },
            error: function () { Swal.fire('Erro', 'Não foi possível conectar ao servidor.', 'error'); }
        });
    });

    // Remover Fila
    $filasContainer.on('click', '.btn-remover-fila', function () {
        const filaId = $(this).closest('.fila-group').data('fila-id');
        Swal.fire({
            title: 'Tem certeza?', text: "Deseja remover esta fila e TODOS os itens dentro dela?",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=removeFilaCarregamento', { fila_id: filaId, csrf_token: csrfToken }, function (response) {
                    if (response.success) { loadDetalhesCarregamento(); }
                    else { Swal.fire('Erro', response.message, 'error'); }
                }, 'json');
            }
        });
    });

    // Remover Cliente da Fila
    $filasContainer.on('click', '.btn-remove-cliente-from-fila', function () {
        const filaId = $(this).data('fila-id');
        const clienteId = $(this).data('cliente-id');
        Swal.fire({
            title: 'Remover Cliente da Fila?', text: "Todos os itens deste cliente serão removidos desta fila. Deseja continuar?",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=removeClienteFromFila', {
                    fila_id: filaId, cliente_id: clienteId, csrf_token: csrfToken
                }, function (response) {
                    if (response.success) {
                        notificacaoSucesso('Removido!', 'Cliente e seus itens removidos da fila.');
                        loadDetalhesCarregamento();
                    } else { Swal.fire('Erro', response.message, 'error'); }
                }, 'json');
            }
        });
    });

    // Remover Item (de uma fila)
    $filasContainer.on('click', '.btn-remover-item', function () {
        const itemId = $(this).data('item-id');
        Swal.fire({
            title: 'Tem certeza?', text: "Deseja remover este item da fila?",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=removeItemCarregamento', { car_item_id: itemId, csrf_token: csrfToken }, function (response) {
                    if (response.success) { loadDetalhesCarregamento(); }
                    else { Swal.fire('Erro', response.message, 'error'); }
                }, 'json');
            }
        });
    });

    // Editar Item (Abrir Modal)
    $filasContainer.on('click', '.btn-editar-item', function () {
        const itemId = $(this).data('item-id');
        const $modal = $('#modal-editar-item');

        // Limpa o modal
        $modal.find('form')[0].reset();
        $modal.find('#edit-produto-nome').text('Carregando...');
        $modal.find('#edit-lote-endereco').text('Carregando...');
        $modal.find('#edit_quantidade').val('').prop('disabled', true);
        $modal.find('#edit-saldo-info').text('');
        $modal.find('#edit_quantidade').removeClass('is-invalid');

        // Busca os dados do item
        $.post('ajax_router.php?action=getCarregamentoItemDetalhes', {
            car_item_id: itemId, csrf_token: csrfToken
        }, function (response) {
            if (response.success) {
                const item = response.data;
                $modal.find('#edit_car_item_id').val(item.car_item_id);
                $modal.find('#edit-produto-nome').text(item.prod_descricao);
                $modal.find('#edit-lote-endereco').text(item.lote_endereco);
                $modal.find('#edit_quantidade').val(item.qtd_carregada).prop('disabled', false);

                // Define o máximo permitido
                $modal.find('#edit_quantidade').attr('max', item.max_quantidade_disponivel);
                $modal.find('#edit-saldo-info').text(`Disponível (OE/Físico): ${item.max_quantidade_disponivel} caixas`);

                $modal.modal('show');
            } else { Swal.fire('Erro', response.message, 'error'); }
        }, 'json');
    });

    // Editar Item (Salvar)
    $('#form-editar-item').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $inputQtd = $form.find('#edit_quantidade');
        const quantidade = parseFloat($inputQtd.val());
        const maximo = parseFloat($inputQtd.attr('max'));

        if (isNaN(quantidade) || quantidade <= 0) {
            $inputQtd.addClass('is-invalid').siblings('.invalid-feedback').text('A quantidade deve ser maior que zero.');
            return;
        }
        if (quantidade > maximo) {
            $inputQtd.addClass('is-invalid').siblings('.invalid-feedback').text('A quantidade excede o saldo disponível.');
            return;
        }

        $.ajax({
            url: 'ajax_router.php?action=updateCarregamentoItemQuantidade',
            type: 'POST', data: $form.serialize(), dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#modal-editar-item').modal('hide');
                    notificacaoSucesso('Sucesso!', 'Quantidade do item atualizada.');
                    loadDetalhesCarregamento();
                } else { Swal.fire('Erro', response.message, 'error'); }
            },
            error: function () { Swal.fire('Erro', 'Não foi possível conectar ao servidor.', 'error'); }
        });
    });

    // Adicionar Fila
    $('#btn-adicionar-fila').on('click', function () {
        $.post('ajax_router.php?action=addFilaCarregamento', { carregamento_id: carregamentoId, csrf_token: csrfToken }, function (response) {
            if (response.success) { loadDetalhesCarregamento(); }
            else { Swal.fire('Erro', response.message, 'error'); }
        }, 'json');
    });

    // Finalizar Carregamento
    $('#btn-finalizar-detalhe').on('click', function () {
        Swal.fire({
            title: 'Finalizar Carregamento?',
            text: 'Esta ação irá finalizar o carregamento e dar baixa no estoque. Deseja continuar?',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d', confirmButtonText: 'Sim, finalizar!', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=finalizarCarregamento', {
                    carregamento_id: carregamentoId, csrf_token: csrfToken
                }, function (response) {
                    if (response.success) {
                        Swal.fire('Finalizado!', 'Carregamento finalizado com sucesso.', 'success').then(() => {
                            loadDetalhesCarregamento();
                        });
                    } else { Swal.fire('Erro', response.message, 'error'); }
                }, 'json');
            }
        });
    });

    // --- INICIALIZAÇÃO ---
    loadDetalhesCarregamento(); // Carrega os dados da página
    inicializarLogicaModalUnificado(); // Prepara o novo modal
});