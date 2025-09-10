$(document).ready(function () {
    // --- Suas variáveis iniciais (sem alterações) ---
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $formHeader = $('#form-ordem-header');
    const urlParams = new URLSearchParams(window.location.search);
    const ordemId = urlParams.get('id');
    const $pedidosContainer = $('#pedidos-container');

    // --- MODAIS ---
    const $modalPedido = $('#modal-pedido-cliente');
    const $formPedido = $('#form-pedido-cliente');
    const $selectCliente = $('#oep_cliente_id');
    const $modalEstoque = $('#modal-selecao-estoque');

    // --- CAMPOS DO MODAL DE ESTOQUE ---
    const $selectProduto = $('#select-produto-estoque');
    const $selectLote = $('#select-lote-estoque');
    const $selectEndereco = $('#select-endereco-estoque');
    const $displaySaldo = $('#saldo-disponivel-display');
    const $inputQtd = $('#oei_quantidade');
    const $inputObs = $('#oei_observacao');
    const $btnAddItem = $('#btn-confirmar-add-item');

    // --- Suas funções de notificação e renderização ---
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

    function renderizarDetalhes(ordem) {
        if (!ordem || !ordem.header) return;

        const header = ordem.header;
        $('#ordem_id').val(header.oe_id);
        $('#oe_numero').val(header.oe_numero);
        $('#oe_data').val(header.oe_data);
        $('#main-title').text(`Editar Ordem de Expedição: ${header.oe_numero} `);
        $formHeader.find('input, button').prop('disabled', true);
        $('#section-details').show();

        const $pedidosContainer = $('#pedidos-container');
        $pedidosContainer.empty();

        if (ordem.pedidos && ordem.pedidos.length > 0) {
            ordem.pedidos.forEach(pedido => {
                let pedidoHtml = `
                <div class="pedido-group border rounded p-3 mb-3" data-oep-id="${pedido.oep_id || ''}">
                 <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5>Cliente: ${pedido.ent_razao_social || 'Cliente não informado'} (Pedido: ${pedido.oep_numero_pedido || 'N/A'})</h5>
                    <div>
                        <button class="btn btn-info btn-sm btn-adicionar-produto" data-oep-id="${pedido.oep_id || ''}">Adicionar Produto</button>
                        <button class="btn btn-danger btn-sm btn-remover-pedido" data-oep-id="${pedido.oep_id || ''}">Remover Pedido</button>
                    </div>
                </div>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Produto</th>
                            <th>Lote</th>
                            <th>Endereço</th>
                            <th class="text-end">Qtd. Caixas</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>`;

                if (pedido.itens && pedido.itens.length > 0) {
                    pedido.itens.forEach(item => {
                        pedidoHtml += `<tr>
                    <td>${item.prod_descricao || 'Produto não informado'}</td>
                    <td>${item.lote_completo_calculado || 'Sem lote'}</td>
                    <td>${item.endereco_completo || 'Sem endereço'}</td>
                    <td class="text-end">${parseFloat(item.oei_quantidade || 0).toFixed(3)}</td>
                    <td class="text-center">
                        <button class="btn btn-danger btn-xs btn-remover-item" data-oei-id="${item.oei_id || ''}"><i class="fas fa-times"></i></button>
                    </td>
                </tr>`;
                    });
                } else {
                    pedidoHtml += '<tr><td colspan="5" class="text-center text-muted">Nenhum produto adicionado a este pedido.</td></tr>';
                }

                pedidoHtml += `</tbody></table></div>`;
                $pedidosContainer.append(pedidoHtml);
            });
        }
    }

    function carregarOrdemCompleta(id) {
        $.ajax({
            url: 'ajax_router.php?action=getOrdemExpedicaoCompleta',
            type: 'POST',
            data: { oe_id: id, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                renderizarDetalhes(response.data);
            } else {
                notificacaoErro('Erro', response.message);
            }
        });
    }

    // --- Lógica inicial de carregamento e submit do header ---
    if (ordemId) {
        carregarOrdemCompleta(ordemId);
    } else {
        $.ajax({
            url: 'ajax_router.php?action=getNextOrderNumber',
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $('#oe_numero').val(response.numero);
            } else {
                notificacaoErro('Erro', response.message);
            }
        });
    }

    // --- Lógica do modal de pedido ---
    $selectCliente.select2({
        placeholder: "Selecione um cliente",
        dropdownParent: $modalPedido,
        theme: "bootstrap-5"
    });

    $('#btn-adicionar-pedido-cliente').on('click', function () {
        $formPedido[0].reset();
        $('#oep_ordem_id').val(ordemId);
        $selectCliente.val(null).trigger('change');

        $.get('ajax_router.php?action=getClienteOptions').done(function (response) {
            $selectCliente.empty().append('<option value=""></option>');
            if (response.success) {
                response.data.forEach(function (cliente) {
                    const textoOpcao = `${cliente.nome_display} (Cód: ${cliente.ent_codigo_interno})`;
                    $selectCliente.append(new Option(textoOpcao, cliente.ent_codigo));
                });
            }
        });

        $modalPedido.modal('show');
    });

    $formPedido.on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        $.ajax({
            url: 'ajax_router.php?action=addPedidoClienteOrdem',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalPedido.modal('hide');
                notificacaoSucesso('Sucesso!', response.message);
                carregarOrdemCompleta(ordemId);
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    });
    // ===================================================================
    //  INÍCIO DAS CORREÇÕES E OTIMIZAÇÕES
    // ===================================================================

    // FUNÇÃO DE RESET CORRIGIDA
    function resetModalEstoque() {
        // Reseta o select de produto
        $selectProduto.val(null).trigger('change');

        // Limpa e desabilita os selects seguintes
        $selectLote.empty().append('<option value=""></option>').prop('disabled', true).trigger('change');
        $selectEndereco.empty().append('<option value=""></option>').prop('disabled', true).trigger('change');

        // Limpa os campos de saldo, quantidade e observação
        $displaySaldo.val('');
        $inputQtd.val('').prop('disabled', true);
        $inputObs.val('');

        // Desabilita o botão de confirmação
        $btnAddItem.prop('disabled', true);
    }

    // --- Lógica do modal de estoque (sem alterações na inicialização dos selects) ---
    $selectProduto.select2({ /* ... */ });
    $selectProduto.on('change', function () { /* ... */ });
    $selectLote.on('change', function () { /* ... */ });
    $selectEndereco.on('change', function () { /* ... */ });

    // --- Lógica do botão de adicionar item (sem alterações) ---
    $btnAddItem.on('click', function () { /* ... */ });

    // EVENTOS DELEGADOS OTIMIZADOS

    // Evento: Abrir modal para adicionar produto
    $pedidosContainer.on('click', '.btn-adicionar-produto', function () {
        const pedidoId = $(this).data('oep-id');
        $('#hidden_oep_id').val(pedidoId);
        resetModalEstoque(); // <-- Usando a função corrigida
        $modalEstoque.modal('show');
    });

    // Evento: Remover Pedido
    $pedidosContainer.on('click', '.btn-remover-pedido', function () {
        const pedidoId = $(this).data('oep-id');
        if (!pedidoId) {
            notificacaoErro('Erro', 'Não foi possível encontrar o ID do pedido.');
            return;
        }
        Swal.fire({
            title: 'Tem certeza?',
            text: 'Deseja remover este pedido e todos os seus itens?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=removePedidoOrdem',
                    type: 'POST',
                    data: { oep_id: pedidoId, csrf_token: csrfToken },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        notificacaoSucesso('Sucesso!', response.message);
                        carregarOrdemCompleta(ordemId);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                });
            }
        });
    });

    // Evento: Remover Item
    $pedidosContainer.on('click', '.btn-remover-item', function () {
        const itemId = $(this).data('oei-id');
        if (!itemId) {
            notificacaoErro('Erro', 'Não foi possível encontrar o ID do item.');
            return;
        }
        Swal.fire({
            title: 'Tem certeza?',
            text: 'Deseja remover este item?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=removeItemPedidoOrdem',
                    type: 'POST',
                    data: { oei_id: itemId, csrf_token: csrfToken },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        notificacaoSucesso('Sucesso!', response.message);
                        carregarOrdemCompleta(ordemId);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                });
            }
        });
    });
});