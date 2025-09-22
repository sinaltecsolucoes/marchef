// /public/js/detalhes_carregamento.js
$(document).ready(function () {
    const csrfToken = $('input[name="csrf_token"]').val();
    const urlParams = new URLSearchParams(window.location.search);
    const carregamentoId = urlParams.get('id');
    let oeId = null; // Vamos popular isso ao carregar

    // Cache dos Elementos do Cabeçalho
    const $formHeader = $('#form-carregamento-header');
    const $inputsHeader = $formHeader.find('input, select');
    const $btnEditarHeader = $('#btn-editar-header');
    const $btnSalvarHeader = $('#btn-salvar-header');
    const $btnCancelarHeader = $('#btn-cancelar-header');

    // Modais (cache)
    const $modalAddItemCascata = $('#modal-add-item-cascata');
    const $modalAddDivergencia = $('#modal-adicionar-divergencia');

    // Containers (cache)
    const $filasContainer = $('#filas-container');

    // Variável para guardar os dados originais
    let dadosOriginaisHeader = {};
    let gabaritoPlanejamento = [];

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

                    gabaritoPlanejamento = data.planejamento || [];

                    renderCabecalho(data.header);
                    renderPlanejamento(data.planejamento);
                    renderExecucao(data.execucao);

                    prepararModalDivergencia(carregamentoId);
                    prepararModalCascata();

                } else {
                    Swal.fire('Erro ao carregar', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro de Conexão', 'Não foi possível buscar os dados do carregamento.', 'error');
            }
        });
    }

    // --- RENDERIZAÇÃO DO CABEÇALHO (Com lógica de Edição) ---
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

        // Aplica máscaras
        $('#car_motorista_cpf').mask('000.000.000-00');

        // Máscara de PLACA (código completo)
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
            $('#btn-editar-header').hide(); // Não pode editar se não estiver Em Andamento
        }
    }

    function renderPlanejamento(planejamento) {
        const $tabelaPlanejamentoBody = $('#tabela-planejamento-body'); // Cache local
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
            } else if (saldo < 0) {
                saldoClass = 'text-danger';  // Vermelho (Carregado a mais)
            } else {
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

    function setSelect2Value(selector, id, text) {
        const $select = $(selector);
        if (id && text) {
            $select.empty().append(new Option(text, id, true, true)).trigger('change');
        } else {
            $select.empty().trigger('change');
        }
    }

    // --- RENDERIZAÇÃO DA EXECUÇÃO (FILAS E ITENS) ---
    function renderExecucao(filas) {
        $filasContainer.empty();
        if (!filas || filas.length === 0) {
            $filasContainer.html('<p class="text-center text-muted">Nenhuma fila adicionada a este carregamento.</p>');
        }

        // *** AQUI COMEÇA A MUDANÇA: USANDO O ÍNDICE ***
        filas.forEach((fila, index) => {
            const clientesNaFila = {};

            if (fila.itens && fila.itens.length > 0) {
                fila.itens.forEach(item => {
                    if (!clientesNaFila[item.car_item_cliente_id]) {
                        clientesNaFila[item.car_item_cliente_id] = {
                            nome: item.cliente_nome,
                            itens: []
                        };
                    }
                    clientesNaFila[item.car_item_cliente_id].itens.push(item);
                });
            }

            let clientesHtml = '';
            for (const clienteId in clientesNaFila) {
                // ... (a lógica interna de renderizar os clientes e itens continua a mesma)
                const cliente = clientesNaFila[clienteId];
                let itensHtml = '';

                cliente.itens.forEach(item => {
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
                                <button class="btn btn-info btn-sm btn-add-item-to-cliente" data-fila-id="${fila.fila_id}" data-cliente-id="${clienteId}" data-cliente-nome="${cliente.nome}" title="Adicionar mais itens para este cliente"><i class="fas fa-plus"></i> Add Item</button>
                                <button class="btn btn-danger btn-sm btn-remove-cliente-from-fila" data-fila-id="${fila.fila_id}" data-cliente-id="${clienteId}" title="Remover este cliente e todos os seus itens da fila"><i class="fas fa-trash-alt"></i> Excluir Cliente</button>
                            </div>
                         </div>
                         <table class="table table-sm table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Produto</th>
                                    <th>Lote</th>
                                    <th>Endereço</th>
                                    <th class="text-end">Qtd. Carregada</th>
                                    <th class="text-center" style="width: 80px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>${itensHtml}</tbody>
                        </table>
                    </div>
                `;
            }

            // *** LÓGICA DO BOTÃO DE REMOVER FILA ***
            let removerFilaBtnHtml = '';
            // Se o índice desta fila for o último da lista (ex: index 2 de 3 filas, length = 3)
            if (index === filas.length - 1) {
                removerFilaBtnHtml = `<button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}"><i class="fas fa-trash"></i></button>`;
            } else {
                removerFilaBtnHtml = `<button class="btn btn-danger btn-sm" data-fila-id="${fila.fila_id}" disabled title="Remova as filas posteriores para poder excluir esta."><i class="fas fa-trash"></i></button>`;
            }

            // Renderiza a Fila com o botão correto
            const filaHtml = `
                <div class="fila-group border rounded p-3 mb-3 shadow-sm" data-fila-id="${fila.fila_id}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Fila ${fila.fila_numero_sequencial}</h5>
                        <div>
                            <button class="btn btn-info btn-sm btn-abrir-modal-item" data-fila-id="${fila.fila_id}"><i class="fas fa-check me-1"></i> Add (da OE)</button>
                            <button class="btn btn-warning btn-sm btn-adicionar-divergencia" data-fila-id="${fila.fila_id}"><i class="fas fa-exclamation-triangle me-1"></i> Add (Divergência)</button>
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
        // *** FIM DAS MUDANÇAS ***

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


    // --- PREPARAÇÃO DOS MODAIS ---
    function prepararModalCascata() {
        const $selectCliente = $('#cascata_cliente');
        const $selectProduto = $('#cascata_produto');
        const $selectLote = $('#cascata_lote_endereco');
        const $displaySaldo = $('#cascata_saldo_display');
        const $inputQtd = $('#cascata_quantidade');
        const $btnAdd = $('#btn-confirmar-add-item-cascata');

        // --- Removemos todas as chamadas AJAX daqui ---

        // 1. Select Cliente
        $selectCliente.select2({
            placeholder: "Selecione o cliente...",
            dropdownParent: $modalAddItemCascata,
            theme: "bootstrap-5",
        });

        // 2. Select Produto
        $selectProduto.select2({ placeholder: "Selecione o produto...", dropdownParent: $modalAddItemCascata, theme: "bootstrap-5" });

        // 3. Select Lote/Endereço
        $selectLote.select2({ placeholder: "Selecione o lote/endereço...", dropdownParent: $modalAddItemCascata, theme: "bootstrap-5" });

        // --- EVENTOS EM CASCATA (LENDO DA VARIÁVEL 'gabaritoPlanejamento') ---

        // Quando o modal abre, populamos o primeiro dropdown
        $modalAddItemCascata.off('show.bs.modal').on('show.bs.modal', function () {
            $selectCliente.empty().append(new Option('', '')); // Limpa
            $selectProduto.empty().append(new Option('', '')).prop('disabled', true);
            $selectLote.empty().append(new Option('', '')).prop('disabled', true);

            // Filtra clientes únicos do gabarito
            const clientes = [...new Map(gabaritoPlanejamento.map(item =>
                [item.oep_cliente_id, { id: item.oep_cliente_id, text: item.cliente_nome }])).values()];

            clientes.forEach(cli => {
                $selectCliente.append(new Option(cli.text, cli.id));
            });
        });

        // Quando o Cliente muda
        $selectCliente.off('change').on('change', function () {
            const clienteId = $(this).val();
            $selectProduto.empty().append(new Option('', '')).prop('disabled', true);
            $selectLote.empty().append(new Option('', '')).prop('disabled', true);

            if (clienteId) {
                // Filtra produtos únicos para este cliente
                const produtos = [...new Map(gabaritoPlanejamento
                    .filter(item => item.oep_cliente_id == clienteId && (item.qtd_planejada - item.qtd_carregada) > 0) // Só os com saldo
                    .map(item => [item.produto_id, { id: item.produto_id, text: item.prod_descricao }]))
                    .values()];

                produtos.forEach(prod => {
                    $selectProduto.append(new Option(prod.text, prod.id));
                });
                $selectProduto.prop('disabled', false);
            }
        });

        // Quando o Produto muda
        $selectProduto.off('change').on('change', function () {
            const clienteId = $selectCliente.val();
            const produtoId = $(this).val();
            $selectLote.empty().append(new Option('', '')).prop('disabled', true);

            if (clienteId && produtoId) {
                // Filtra lotes/endereços para este cliente/produto
                const lotes = gabaritoPlanejamento.filter(item =>
                    item.oep_cliente_id == clienteId &&
                    item.produto_id == produtoId &&
                    (item.qtd_planejada - item.qtd_carregada) > 0 // Só os com saldo
                );

                lotes.forEach(lote => {
                    const saldo = parseFloat(lote.qtd_planejada) - parseFloat(lote.qtd_carregada);
                    const texto = `${lote.lote_completo} / ${lote.endereco_completo} [Saldo: ${saldo.toFixed(0)}]`;

                    // Criamos a opção e JÁ GUARDAMOS OS DADOS NELA
                    const $option = new Option(texto, lote.oei_alocacao_id);
                    $option.dataset.saldo = saldo;
                    $option.dataset.oeiId = lote.oei_id;

                    $selectLote.append($option);
                });
                $selectLote.prop('disabled', false);
            }
        });

        // Quando o Lote/Endereço muda
        $selectLote.off('change').on('change', function () {
            const $selectedOption = $(this).find('option:selected');
            const saldo = $selectedOption.data('saldo');

            if (saldo !== undefined && saldo > 0) {
                $displaySaldo.val(parseFloat(saldo).toFixed(3));
                $inputQtd.prop('disabled', false).attr('max', saldo).val(saldo); // Sugere o saldo máximo
                $btnAdd.prop('disabled', false);
            } else {
                $displaySaldo.val('');
                $inputQtd.prop('disabled', true).val('');
                $btnAdd.prop('disabled', true);
            }
        });

        // Validação da Quantidade (sem alteração)
        $inputQtd.off('input').on('input', function () {
            const $campoQuantidade = $(this);
            const quantidadeDigitada = parseFloat($campoQuantidade.val());
            const saldoDisponivel = parseFloat($campoQuantidade.attr('max'));

            if (isNaN(quantidadeDigitada) || quantidadeDigitada <= 0 || quantidadeDigitada > saldoDisponivel) {
                $campoQuantidade.addClass('is-invalid');
                $btnAdd.prop('disabled', true);
            } else {
                $campoQuantidade.removeClass('is-invalid');
                $btnAdd.prop('disabled', false);
            }
        });
    }

    function prepararModalDivergencia(carregamentoId) {
        $('#modal_div_carregamento_id').val(carregamentoId);

        // Cliente (Select2 com todos os clientes)
        $('#div_cliente_id').select2({
            placeholder: "Selecione um cliente",
            dropdownParent: $modalAddDivergencia,
            theme: "bootstrap-5",
            ajax: {
                url: 'ajax_router.php?action=getClienteOptions',
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
                url: "ajax_router.php?action=getProdutosComEstoqueDisponivel",
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
            // Restaura os dados originais
            renderCabecalho(dadosOriginaisHeader);
        }
    }

    // Função initSelect2Header (estava faltando)
    function initSelect2Header(selector, action) {
        const $select = $(selector);
        $select.select2({
            placeholder: "Selecione...",
            theme: "bootstrap-5",
            ajax: {
                url: `ajax_router.php?action=${action}`,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) {
                    return { results: data.data || data.results };
                }
            }
        });
    }

    $btnEditarHeader.on('click', () => toggleHeaderEdit(true));

    $btnCancelarHeader.on('click', () => toggleHeaderEdit(false));

    // Submit do Cabeçalho (estava faltando)
    $formHeader.on('submit', function (e) {
        e.preventDefault();

        // Pega o CPF limpo
        const $cpfField = $('#car_motorista_cpf');
        const cpfLimpo = $cpfField.cleanVal();
        // Remove o campo 'sujo' do serialize e adiciona o limpo
        const formData = $(this).serialize().replace(/car_motorista_cpf=[^&]*/, '') + '&car_motorista_cpf=' + cpfLimpo;

        $.ajax({
            url: 'ajax_router.php?action=updateCarregamentoHeader',
            type: 'POST',
            data: formData, // Usa o formData corrigido
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire('Sucesso!', 'Cabeçalho atualizado.', 'success');
                    toggleHeaderEdit(false);
                    loadDetalhesCarregamento(); // Recarrega
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro', 'Não foi possível conectar ao servidor.', 'error');
            }
        });
    });

    // Remover Fila
    $filasContainer.on('click', '.btn-remover-fila', function () {
        const filaId = $(this).closest('.fila-group').data('fila-id');
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

    // Abrir Modal "Adicionar (Cascata OE)"
    $filasContainer.on('click', '.btn-abrir-modal-item', function () {
        const filaId = $(this).closest('.fila-group').data('fila-id');
        $('#cascata_fila_id').val(filaId);

        // Resetar o modal cascata
        $('#form-add-item-cascata')[0].reset();
        $('#cascata_cliente').val(null).trigger('change');
        $('#cascata_produto').val(null).trigger('change').prop('disabled', true);
        $('#cascata_lote_endereco').val(null).trigger('change').prop('disabled', true);
        $('#btn-confirmar-add-item-cascata').prop('disabled', true);

        $modalAddItemCascata.modal('show');
    });

    // *** Handler para Adicionar Item (pré-selecionando cliente) ***
    $filasContainer.on('click', '.btn-add-item-to-cliente', function () {
        const filaId = $(this).data('fila-id');
        const clienteId = $(this).data('cliente-id');
        const clienteNome = $(this).data('cliente-nome');

        // Abre o modal cascata
        const $modal = $modalAddItemCascata;
        $modal.modal('show');

        // Reseta o formulário
        $('#form-add-item-cascata')[0].reset();
        $('#cascata_fila_id').val(filaId);

        // *** Pré-seleciona e desabilita o Cliente ***
        const $selectCliente = $('#cascata_cliente');
        // Cria a opção se não existir e seleciona
        if ($selectCliente.find("option[value='" + clienteId + "']").length === 0) {
            $selectCliente.append(new Option(clienteNome, clienteId, true, true));
        }
        $selectCliente.val(clienteId).trigger('change').prop('disabled', true); // Desabilita

        // Reseta os outros dropdowns
        $('#cascata_produto').val(null).trigger('change').prop('disabled', false); // Habilita o próximo
        $('#cascata_lote_endereco').val(null).trigger('change').prop('disabled', true);
        $('#btn-confirmar-add-item-cascata').prop('disabled', true);

        // Precisamos garantir que, ao fechar, o select de cliente volte ao normal
        $modal.off('hidden.bs.modal').on('hidden.bs.modal', function () {
            $('#cascata_cliente').prop('disabled', false); // Re-habilita ao fechar
        });
    });

    // *** Handler para Remover Cliente da Fila ***
    $filasContainer.on('click', '.btn-remove-cliente-from-fila', function () {
        const filaId = $(this).data('fila-id');
        const clienteId = $(this).data('cliente-id');

        Swal.fire({
            title: 'Remover Cliente da Fila?',
            text: "Todos os itens deste cliente serão removidos desta fila. Deseja continuar?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=removeClienteFromFila', {
                    fila_id: filaId,
                    cliente_id: clienteId,
                    csrf_token: csrfToken
                }, function (response) {
                    if (response.success) {
                        notificacaoSucesso('Removido!', 'Cliente e seus itens removidos da fila.');
                        loadDetalhesCarregamento(); // Recarrega
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // Abrir Modal "Adicionar (Divergência)"
    $filasContainer.on('click', '.btn-adicionar-divergencia', function () {
        const filaId = $(this).closest('.fila-group').data('fila-id');
        //$('#modal_div_fila_id').val(filaId);
        $('#div_fila_id').val(filaId);
        // Resetar o form de divergência
        $('#form-add-divergencia')[0].reset();
        $('#div_cliente_id').val(null).trigger('change');
        $('#div_produto_estoque').val(null).trigger('change');
        $('#div_lote_estoque').val(null).trigger('change').prop('disabled', true);
        $('#div_endereco_estoque').val(null).trigger('change').prop('disabled', true);
        $('#btn-confirmar-add-divergencia').prop('disabled', true);

        $modalAddDivergencia.modal('show');
    });

    // *** Handler para ABRIR modal de Edição de Item ***
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
            car_item_id: itemId,
            csrf_token: csrfToken
        }, function (response) {
            if (response.success) {
                const item = response.data;
                $modal.find('#edit_car_item_id').val(item.car_item_id);
                $modal.find('#edit-produto-nome').text(item.prod_descricao);
                $modal.find('#edit-lote-endereco').text(item.lote_endereco);
                $modal.find('#edit_quantidade').val(item.qtd_carregada).prop('disabled', false);

                // Define o máximo permitido
                $modal.find('#edit_quantidade').attr('max', item.max_quantidade_disponivel);
                $modal.find('#edit-saldo-info').text(`Disponível (OE): ${item.max_quantidade_disponivel} caixas`);

                $modal.modal('show');
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        }, 'json');
    });

    // *** Handler para SALVAR Edição de Item ***
    $('#form-editar-item').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $inputQtd = $form.find('#edit_quantidade');
        const quantidade = parseFloat($inputQtd.val());
        const maximo = parseFloat($inputQtd.attr('max'));

        // Validação
        if (isNaN(quantidade) || quantidade <= 0) {
            $inputQtd.addClass('is-invalid').siblings('.invalid-feedback').text('A quantidade deve ser maior que zero.');
            return;
        }
        if (quantidade > maximo) {
            $inputQtd.addClass('is-invalid').siblings('.invalid-feedback').text('A quantidade excede o saldo disponível na Ordem de Expedição.');
            return;
        }

        $.ajax({
            url: 'ajax_router.php?action=updateCarregamentoItemQuantidade',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#modal-editar-item').modal('hide');
                    notificacaoSucesso('Sucesso!', 'Quantidade do item atualizada.');
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

    $('#form-add-item-cascata').on('submit', function (e) {
        e.preventDefault();

        const $selectLote = $('#cascata_lote_endereco');
        const $selectedOption = $selectLote.find('option:selected');

        const oeiIdOrigem = $selectedOption.data('oei-id'); // Pega o ID do dataset

        if (!oeiIdOrigem) {
            Swal.fire('Erro', 'Item da OE de origem não encontrado. Selecione o lote/endereço novamente.', 'error');
            return;
        }

        const formData = $(this).serialize() + '&cascata_oei_id_origem=' + oeiIdOrigem;

        $.ajax({
            url: 'ajax_router.php?action=addItemCascata',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalAddItemCascata.modal('hide');
                    loadDetalhesCarregamento(); // Recarrega tudo (inclusive o gabarito!)
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function () { Swal.fire('Erro', 'Não foi possível conectar ao servidor.', 'error'); }
        });
    });

    // Confirmar Adição (do Modal Divergência)
    $('#form-add-divergencia').on('submit', function (e) {
        e.preventDefault();

        const motivo = $('#div_motivo').val().trim();
        if (motivo === '') {
            Swal.fire('Campo Obrigatório', 'Por favor, informe o motivo da divergência.', 'warning');
            $('#div_motivo').focus();
            return;
        }

        $.ajax({
            url: 'ajax_router.php?action=addItemCarregamentoDivergencia',
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

    // Adicionar Fila
    $('#btn-adicionar-fila').on('click', function () {
        $.post('ajax_router.php?action=addFilaCarregamento', { carregamento_id: carregamentoId, csrf_token: csrfToken }, function (response) {
            if (response.success) {
                loadDetalhesCarregamento(); // Recarrega tudo
            } else { Swal.fire('Erro', response.message, 'error'); }
        }, 'json');
    });

    $('#btn-finalizar-detalhe').on('click', function () {
        Swal.fire({
            title: 'Finalizar Carregamento?',
            text: 'Esta ação irá finalizar o carregamento e dar baixa no estoque. Deseja continuar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, finalizar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=finalizarCarregamento', {
                    carregamento_id: carregamentoId,
                    csrf_token: csrfToken
                }, function (response) {
                    if (response.success) {
                        Swal.fire('Finalizado!', 'Carregamento finalizado com sucesso.', 'success').then(() => {
                            loadDetalhesCarregamento(); // Recarrega os dados
                        });
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // --- INICIALIZAÇÃO ---
    loadDetalhesCarregamento();
});