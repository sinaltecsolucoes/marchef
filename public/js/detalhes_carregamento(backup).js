// /public/js/detalhes_carregamento.js
$(document).ready(function () {

    if (!carregamentoData) {
        $('#dados-carregamento-header').html('<div class="alert alert-danger">Não foi possível carregar os dados deste carregamento.</div>');
        return;
    }

    const carregamentoId = carregamentoData.header.car_id;

    function preencherCabecalho() {
        const header = carregamentoData.header;
        $('#car-numero-detalhe').text(header.car_numero);

        const $statusBadge = $('#car-status-detalhe');
        $statusBadge.text(header.car_status);

        // Opcional: Mudar a cor do badge com base no status
        let badgeClass = 'bg-secondary';
        if (header.car_status === 'EM ANDAMENTO') badgeClass = 'bg-warning text-dark';
        if (header.car_status === 'AGUARDANDO CONFERENCIA') badgeClass = 'bg-primary';
        if (header.car_status === 'FINALIZADO') badgeClass = 'bg-success';
        if (header.car_status === 'CANCELADO') badgeClass = 'bg-danger';
        $statusBadge.removeClass('bg-secondary bg-warning bg-primary bg-success bg-danger text-dark').addClass(badgeClass);
    }

    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $filasContainer = $('#filas-container');
    const $modalAdicionarFila = $('#modal-adicionar-fila');

    // --- FUNÇÕES DE RENDERIZAÇÃO E LÓGICA ---

    // Função principal que redesenha todo o container de filas
    function renderizarFilasEItens(filas) {
        $filasContainer.empty();
        if (!filas || filas.length === 0) {
            $filasContainer.html('<p class="text-muted">Nenhuma fila de cliente foi adicionada a este carregamento ainda.</p>');
            return;
        }

        filas.forEach(fila => {
            let itensHtml = '';
            if (fila.itens && fila.itens.length > 0) {
                fila.itens.forEach(item => {
                    itensHtml += `
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>${item.prod_descricao} (Lote: ${item.lote_completo_calculado})</span>
                            <span class="badge bg-primary rounded-pill">${parseFloat(item.car_item_quantidade).toFixed(3)} Un.</span>
                        </li>
                    `;
                });
            } else {
                itensHtml = '<li class="list-group-item">Nenhum produto adicionado para este cliente na fila.</li>';
            }

            const cardFilaHtml = `
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0">Fila ${fila.fila_numero} - Cliente: <strong>${fila.cliente_razao_social}</strong></h6>
                        <button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}">Remover Fila</button>
                    </div>
                    <div class="card-body">
                        <form class="form-adicionar-item-fila" data-fila-id="${fila.fila_id}">
                            <div class="row align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label">Adicionar Produto do Estoque</label>
                                    <select class="form-select select-item-estoque" style="width: 100%;"></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Quantidade (Unidades)</label>
                                    <input type="number" class="form-control quantidade-item-fila" step="1" min="1">
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-success w-100">Adicionar</button>
                                </div>
                            </div>
                        </form>
                        <hr>
                        <ul class="list-group">
                            ${itensHtml}
                        </ul>
                    </div>
                </div>
            `;
            $filasContainer.append(cardFilaHtml);
        });

        // Inicializa todos os novos Select2 que foram adicionados
        inicializarSelect2ParaItens();
    }

    function inicializarSelect2ParaItens() {
        $('.select-item-estoque').select2({
            placeholder: 'Digite para buscar um produto em estoque...',
            theme: "bootstrap-5",
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

    function recarregarDadosCarregamento() {
        // Esta função irá buscar a versão mais recente dos dados do carregamento
        $.ajax({
            url: `ajax_router.php?action=getCarregamentoDetalhes&id=${carregamentoId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                carregamentoData = response.data;
                renderizarFilasEItens(carregamentoData.filas);
            }
        });
    }

    // --- INICIALIZAÇÃO DA PÁGINA ---

    preencherCabecalho();

    // Inicialização do Select2 para o modal de adicionar cliente/fila
    $('#select-cliente-para-fila').select2({
        placeholder: 'Selecione um cliente...',
        theme: "bootstrap-5",
        dropdownParent: $modalAdicionarFila,
        ajax: {
            url: 'ajax_router.php?action=getClienteOptions', // Usa a rota que já temos
            dataType: 'json',
            processResults: function (data) {
                const a = data.data.map(item => ({ id: item.ent_codigo, text: item.ent_razao_social }));
                return { results: a };
            }
        }
    });

    // Renderiza o estado inicial do carregamento
    renderizarFilasEItens(carregamentoData.filas);

    // --- EVENT HANDLERS ---

    // Abrir o modal para adicionar uma nova fila
    $('#btn-adicionar-fila').on('click', function () {
        $('#form-adicionar-fila')[0].reset();
        $('#select-cliente-para-fila').val(null).trigger('change');
        $modalAdicionarFila.modal('show');
    });

    // Submeter o formulário para criar a nova fila
    $('#form-adicionar-fila').on('submit', function (e) {
        e.preventDefault();
        const clienteId = $('#select-cliente-para-fila').val();
        if (!clienteId) {
            alert('Por favor, selecione um cliente.');
            return;
        }
        $.ajax({
            url: 'ajax_router.php?action=adicionarFila',
            type: 'POST',
            data: { carregamento_id: carregamentoId, cliente_id: clienteId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalAdicionarFila.modal('hide');
                recarregarDadosCarregamento(); // Recarrega tudo para mostrar a nova fila
            } else {
                $('#mensagem-adicionar-fila-modal').html(`<div class="alert alert-danger">${response.message}</div>`);
            }
        });
    });

    // Adicionar um item a uma fila específica (usando delegação de evento)
    $filasContainer.on('submit', '.form-adicionar-item-fila', function (e) {
        e.preventDefault();
        const $form = $(this);
        const filaId = $form.data('fila-id');
        const loteItemId = $form.find('.select-item-estoque').val();
        const quantidade = $form.find('.quantidade-item-fila').val();

        if (!loteItemId || !quantidade || quantidade <= 0) {
            alert('Por favor, selecione um produto e insira uma quantidade válida.');
            return;
        }

        $.ajax({
            url: 'ajax_router.php?action=adicionarItemAFila',
            type: 'POST',
            data: {
                fila_id: filaId,
                lote_item_id: loteItemId,
                quantidade: quantidade,
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                recarregarDadosCarregamento();
            } else {
                alert('Erro: ' + response.message);
            }
        });
    });

    /**
     * Evento para submeter o formulário de adicionar um produto a um cliente
     * dentro do modal. Usa delegação de eventos.
     */
    $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (e) {
        e.preventDefault(); // Impede o recarregamento da página

        const $form = $(this);
        const $cardCliente = $form.closest('.card-cliente-na-fila');
        const $listaProdutos = $cardCliente.find('.lista-produtos-cliente');

        const produtoSelecionado = $form.find('.select-produto-estoque').select2('data')[0];
        const quantidade = $form.find('input[type="number"]').val();

        // Validação
        if (!produtoSelecionado || !produtoSelecionado.id) {
            alert('Por favor, selecione um produto da lista.');
            return;
        }
        if (!quantidade || parseFloat(quantidade) <= 0) {
            alert('Por favor, insira uma quantidade válida.');
            return;
        }

        // Cria o HTML para o novo item na lista
        const produtoHtml = `
        <li class="list-group-item d-flex justify-content-between align-items-center" data-lote-item-id="${produtoSelecionado.id}">
            <span>${produtoSelecionado.text}</span>
            <div>
                <span class="badge bg-primary rounded-pill me-3">${parseFloat(quantidade).toFixed(3)} Un.</span>
                <button type="button" class="btn btn-danger btn-sm btn-remover-produto-da-lista">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </li>
    `;

        // Adiciona o novo item à lista de produtos do cliente correto
        $listaProdutos.append(produtoHtml);

        // Limpa o formulário para a próxima adição
        $form.find('.select-produto-estoque').val(null).trigger('change');
        $form.find('input[type="number"]').val('');
    });

});