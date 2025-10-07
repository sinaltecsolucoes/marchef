// /public/js/visao_estoque_enderecos.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $container = $('#tree-container');
    const $modalAlocar = $('#modal-alocar-item');
    const $formAlocar = $('#form-alocar-item');

    // NOVAS VARIÁVEIS DO CAMPO DE BUSCA
    const $inputSearch = $('#input-search-estoque');
    const $btnSearch = $('#btn-search-estoque');
    const $btnClearSearch = $('#btn-clear-search');

    // Variável de controle para o termo de busca
    let currentSearchTerm = '';

    // Função auxiliar para formatar números
    function formatarNumero(valor, decimal = false) {
        const numero = parseFloat(valor) || 0;
        return numero.toLocaleString('pt-BR', {
            minimumFractionDigits: decimal ? 3 : 0,
            maximumFractionDigits: decimal ? 3 : 0
        });
    }

    // Inicialização do Select2 para o modal de alocação
    $('#select-item-para-alocar').select2({
        placeholder: "Busque por produto ou lote...",
        theme: "bootstrap-5",
        dropdownParent: $modalAlocar,
        ajax: {
            url: "ajax_router.php?action=getItensNaoAlocados",
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term // Envia o termo de busca (o que o usuário digita)
                };
            },
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        }
    });

    /**
     * Constrói a estrutura de árvore do estoque (Câmara -> Endereço -> Itens).
     * @param {object} data - Dados hierárquicos do estoque.
     */
    function construirArvore(data) {
        $container.empty();
        /* if (Object.keys(data).length === 0) {
             $container.html('<p class="text-muted">Nenhuma câmara cadastrada.</p>');
             return;
         }*/

        if (Object.keys(data).length === 0) {
            // Se houver termo de busca e nenhum resultado
            if (currentSearchTerm) {
                $container.html(`<div class="alert alert-warning text-center">Nenhum item encontrado para a busca: <strong>${currentSearchTerm}</strong>.</div>`);
            } else {
                $container.html('<p class="text-muted text-center">Nenhuma câmara cadastrada ou estoque encontrado.</p>');
            }
            return;
        }

        // 1. CABEÇALHO PRINCIPAL
        let html = `
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th style="width: 40px;"></th>
                    <th class="align-middle">Descrição (Câmara / Endereço)</th>
                    <th class="text-center align-middle" style="width: 120px;">Caixas Físicas</th>
                    <th class="text-center align-middle" style="width: 120px;">Caixas Reserv.</th>
                    <th class="text-center align-middle" style="width: 120px;">Caixas Disp.</th>
                    <th class="text-center align-middle" style="width: 120px;">Quilos (kg)</th>
                    <th class="text-center align-middle" style="width: 150px;">Ação</th>
                </tr>
            </thead>
            <tbody>
    `;

        // Váriavel para rastrear se o primeiro item foi expandido (apenas para o modo de busca)
        let firstItemExpanded = false;

        $.each(data, function (camaraId, camara) {
            // 2. CÂMARAS
            const caixasDisponiveisCamara = camara.total_caixas - camara.total_caixas_reservadas;

            // Define o ícone inicial da CÂMARA
            const camaraIconClass = (currentSearchTerm && Object.keys(camara.enderecos).length > 0) ? 'fa-minus-square' : 'fa-plus-square';
                        
            html += `
            <tr class="table-primary" style="border-top: 2px solid #a9c6e8; border-bottom: 1px solid #a9c6e8;">
                <td><i class="fas fa-plus-square toggle-btn" data-target=".camara-${camaraId}"></i></td>
                <td><strong>${camara.nome} (${camara.codigo})</strong></td>
                <td class="text-center"><strong>${formatarNumero(camara.total_caixas)}</strong></td>
                <td class="text-center text-danger"><strong>${formatarNumero(camara.total_caixas_reservadas)}</strong></td>
                <td class="text-center text-success fw-bolder"><strong>${formatarNumero(caixasDisponiveisCamara)}</strong></td>
                <td class="text-center"><strong>${formatarNumero(camara.total_quilos, true)}</strong></td>
                <td></td>
            </tr>
        `;

            if (Object.keys(camara.enderecos).length > 0) {
                $.each(camara.enderecos, function (enderecoId, endereco) {
                    const temItens = endereco.itens.length > 0;
                    const iconClass = temItens ? 'fa-plus-square' : 'fa-square text-muted';
                    const toggleClass = temItens ? 'toggle-btn' : '';
                    const caixasDisponiveisEndereco = endereco.total_caixas - endereco.total_caixas_reservadas;

                    // Condição para expandir o primeiro endereço na busca
                    let displayStyle = 'display: none;';
                    let enderecoIconClass = iconClass; // Inicializa com o ícone normal (+)
                    
                    if (currentSearchTerm && !firstItemExpanded) {
                        displayStyle = 'display: table-row;';
                        if (temItens) {
                            enderecoIconClass = 'fa-minus-square'; // Troca para o ícone de recolher (-)
                        }
                    }
                    
                    // 3. ENDEREÇOS
                  /*  html += `
                    <tr class="camara-${camaraId}" style="display: none;">
                        <td></td>
                        <td class="ps-4"><i class="fas ${iconClass} ${toggleClass}" data-target=".endereco-${enderecoId}"></i> ${endereco.nome}</td>
                        <td class="text-center">${formatarNumero(endereco.total_caixas)}</td>
                        <td class="text-center text-danger">${formatarNumero(endereco.total_caixas_reservadas)}</td>
                        <td class="text-center text-success fw-bolder">${formatarNumero(caixasDisponiveisEndereco)}</td>
                        <td class="text-center">${formatarNumero(endereco.total_quilos, true)}</td>
                        <td class="text-center">
                            <button class="btn btn-success btn-sm btn-alocar-item" data-id="${endereco.endereco_id}" data-nome="${endereco.nome}"><i class="fas fa-download me-1"></i>Alocar Item</button>
                        </td>
                    </tr>
                `;*/
                html += `
                    <tr class="camara-${camaraId}" style="${displayStyle}">
                        <td></td>
                        <td class="ps-4"><i class="fas ${enderecoIconClass} ${toggleClass}" data-target=".endereco-${enderecoId}"></i> ${endereco.nome}</td>
                        <td class="text-center">${formatarNumero(endereco.total_caixas)}</td>
                        <td class="text-center text-danger">${formatarNumero(endereco.total_caixas_reservadas)}</td>
                        <td class="text-center text-success fw-bolder">${formatarNumero(caixasDisponiveisEndereco)}</td>
                        <td class="text-center">${formatarNumero(endereco.total_quilos, true)}</td>
                        <td class="text-center">
                            <button class="btn btn-success btn-sm btn-alocar-item" data-id="${endereco.endereco_id}" data-nome="${endereco.nome}"><i class="fas fa-download me-1"></i>Alocar Item</button>
                        </td>
                    </tr>
                `;

                    if (temItens) {

                        // Determinar se a tabela de itens deve ser exibida (apenas na busca)
                        const itemTableDisplayStyle = (currentSearchTerm && !firstItemExpanded) ? 'display: table-row;' : 'display: none;';

                      /*  html += `
                        <tr class="endereco-${enderecoId}" style="display: none;">
                            <td colspan="7">
                                <div class="ps-5 p-3 border rounded shadow-sm bg-white">
                                    <table class="table table-sm table-bordered table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40%; text-align: center; vertical-align: middle;">Produto</th>
                                                <th style="width: 10%; text-align: center; vertical-align: middle;">Lote</th>
                                                <th style="width: 10%; text-align: center; vertical-align: middle;">Qtd. Física</th>
                                                <th style="width: 10%; text-align: center; vertical-align: middle;">Qtd. Reservada</th>
                                                <th style="width: 10%; text-align: center; vertical-align: middle;">Qtd. Disponível</th>
                                                <th style="width: 10%; text-align: center; vertical-align: middle;">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;*/
                         html += `
                            <tr class="endereco-${enderecoId}" style="${itemTableDisplayStyle}">
                                <td colspan="7">
                                    <div class="ps-5 p-3 border rounded shadow-sm bg-white">
                                        <table class="table table-sm table-bordered table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 40%; text-align: center; vertical-align: middle;">Produto</th>
                                                    <th style="width: 10%; text-align: center; vertical-align: middle;">Lote</th>
                                                    <th style="width: 10%; text-align: center; vertical-align: middle;">Qtd. Física</th>
                                                    <th style="width: 10%; text-align: center; vertical-align: middle;">Qtd. Reservada</th>
                                                    <th style="width: 10%; text-align: center; vertical-align: middle;">Qtd. Disponível</th>
                                                    <th style="width: 10%; text-align: center; vertical-align: middle;">Ação</th>
                                                </tr>
                                            </thead>
                                            <tbody>`;
                                                                
                        $.each(endereco.itens, function (i, item) {
                            const qtdDisponivel = item.quantidade_fisica - item.quantidade_reservada;
                            let reservadoHtml = formatarNumero(item.quantidade_reservada);
                            if (item.quantidade_reservada > 0) {
                                reservadoHtml = `<a href="#" class="link-reserva text-danger fw-bold" data-alocacao-id="${item.alocacao_id}">${reservadoHtml}</a>`;
                            }

                            html += `<tr>
                                    <td style="width: 40%;">${item.produto}</td>
                                    <td style="width: 20%; text-align: center; vertical-align: middle;">${item.lote}</td>
                                    <td style="width: 10%; text-align: center; vertical-align: middle;">${formatarNumero(item.quantidade_fisica)}</td>
                                    <td style="width: 10%; text-align: center; vertical-align: middle;">${reservadoHtml}</td>
                                    <td style="width: 10%; text-align: center; vertical-align: middle; text-success fw-bolder">${formatarNumero(qtdDisponivel)}</td>
                                    <td style="width: 10%; text-align: center; vertical-align: middle;">
                                        <button class="btn btn-danger btn-xs btn-desalocar-item-especifico" data-alocacao-id="${item.alocacao_id}" title="Desalocar este item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>`;
                        });
                        html += `</tbody></table>
                                </div>
                            </td>
                        </tr>`;

                        // Marca que o primeiro item foi expandido após a primeira iteração em modo de busca
                        if (currentSearchTerm && !firstItemExpanded) {
                            firstItemExpanded = true;
                        }
                    }
                });
            } else {
                html += `<tr class="camara-${camaraId}" style="display: none;"><td colspan="7" class="text-muted ps-5">Nenhum endereço cadastrado nesta câmara.</td></tr>`;
            }
        });

        html += '</tbody></table>';
        $container.html(html);

        // Atualiza ícones de expansão se estiver em modo de busca
       /* if (currentSearchTerm) {
            // Expande o ícone da primeira câmara e endereço
            $container.find('.toggle-btn:first').removeClass('fa-plus-square').addClass('fa-minus-square');
            $container.find('.camara-' + Object.keys(data)[0] + ' .toggle-btn:first').removeClass('fa-plus-square').addClass('fa-minus-square');
        }*/
    }

    function carregarDados(termoBusca = '') {
        currentSearchTerm = termoBusca; // Atualiza a variável de controle

        const url = termoBusca
            ? 'ajax_router.php?action=getVisaoEstoqueHierarquicoFiltrada&term=' + encodeURIComponent(termoBusca)
            : 'ajax_router.php?action=getVisaoEstoqueHierarquico';

        $container.html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div><p>Carregando dados do estoque...</p></div>');

        // Desabilita os botões enquanto carrega
        $btnSearch.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $btnClearSearch.prop('disabled', true);

        $.ajax({
            // url: 'ajax_router.php?action=getVisaoEstoqueHierarquico',
            url: url,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            $btnSearch.prop('disabled', false).html('Buscar');
            $btnClearSearch.prop('disabled', false);

            if (response.success) {
                construirArvore(response.data);
            } else {
                $container.html(`<div class="alert alert-danger">${response.message}</div>`);
            }

            // Exibe ou oculta o botão Limpar
            if (termoBusca) {
                $btnClearSearch.show();
            } else {
                $btnClearSearch.hide();
            }

        }).fail(function () {
            $btnSearch.prop('disabled', false).html('Buscar');
            $btnClearSearch.prop('disabled', false);
            $container.html('<div class="alert alert-danger">Erro ao comunicar com o servidor.</div>');

            if (termoBusca) { $btnClearSearch.show(); } else { $btnClearSearch.hide(); }
        });
    }

    // --- LÓGICA DE PESQUISA ---
    $btnSearch.on('click', function () {
        const term = $inputSearch.val().trim();
        if (term.length >= 3) {
            carregarDados(term);
        } else if (term.length === 0) {
            // Se o usuário clicar em buscar com o campo vazio, carrega tudo
            carregarDados('');
        } else {
            // Usei uma notificação de erro ou um tooltip aqui seria ideal
            alert('Por favor, digite pelo menos 3 caracteres para a busca.');
        }
    });

    // Suporte para buscar ao pressionar Enter
    $inputSearch.on('keypress', function (e) {
        if (e.which == 13) { // Tecla Enter
            e.preventDefault();
            $btnSearch.click();
        }
    });

    // Botão Limpar
    $btnClearSearch.on('click', function () {
        $inputSearch.val('');
        carregarDados(''); // Recarrega o estoque completo
    });


    /* function formatarNumero(valor, decimal = false) {
         // Converte para número, se ainda não for
         const numero = parseFloat(valor) || 0;
         // Configuração para pt-BR
         return numero.toLocaleString('pt-BR', {
             minimumFractionDigits: decimal ? 3 : 0,
             maximumFractionDigits: decimal ? 3 : 0
         });
     }*/

    carregarDados();

    $container.on('click', '.toggle-btn', function () {
        const targetClass = $(this).data('target');
        $(targetClass).toggle();
        $(this).toggleClass('fa-plus-square fa-minus-square');
    });

    // Evento para abrir o modal de alocação (sem alterações)
    $container.on('click', '.btn-alocar-item', function () {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        $formAlocar[0].reset();
        $('#alocar_endereco_id').val(id);
        $('#alocar-endereco-nome').text(nome);
        $('#select-item-para-alocar').val(null).trigger('change');
        $modalAlocar.modal('show');
    });

    // Evento para submeter o formulário de alocação (sem alterações)
    $formAlocar.on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        $.ajax({
            url: 'ajax_router.php?action=alocarItemEndereco',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalAlocar.modal('hide');
                notificacaoSucesso('Sucesso!', response.message);
                carregarDados();
            } else {
                notificacaoErro('Erro ao Alocar', response.message);
            }
        });
    });

    // --- NOVO EVENTO PARA DESALOCAR UM ITEM ESPECÍFICO ---
    $container.on('click', '.btn-desalocar-item-especifico', function () {
        const alocacaoId = $(this).data('alocacao-id');
        confirmacaoAcao('Desalocar Item?', 'Tem a certeza que deseja remover este item do endereço?')
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=desalocarItemEndereco',
                        type: 'POST',
                        data: { alocacao_id: alocacaoId, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            notificacaoSucesso('Sucesso!', response.message);
                            carregarDados(); // Recarrega a árvore para refletir a mudança
                        } else {
                            notificacaoErro('Erro!', response.message);
                        }
                    });
                }
            });
    });

    // --- EVENTO PARA DETALHES DA RESERVA ---
    $container.on('click', '.link-reserva', function (e) {
        e.preventDefault();
        const alocacaoId = $(this).data('alocacao-id');
        const $modal = $('#modal-reserva-detalhes');
        const $containerDetalhes = $('#reserva-detalhes-container');

        $containerDetalhes.html('<p class="text-center">Carregando detalhes...</p>');
        $modal.modal('show');

        $.ajax({
            url: 'ajax_router.php?action=getReservaDetalhes',
            type: 'POST',
            data: { alocacao_id: alocacaoId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data.length > 0) {
                let tabelaHtml = `
                    <table class="table table-striped table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center align-middle">Nº da Ordem</th>
                                <th class="text-center align-middle">Cliente</th>
                                <th class="text-center align-middle">Pedido do Cliente</th>
                                <th class="text-center align-middle">Qtd. Reservada</th>
                            </tr>
                        </thead>
                        <tbody>`;
                response.data.forEach(reserva => {
                    tabelaHtml += `<tr>
                                        <td class="text-center align-middle">${formatarNumero(reserva.oe_numero).padStart(4, '0')}</td>
                                        <td class="align-middle">${reserva.cliente_nome}</td>
                                        <td class="text-center align-middle">${reserva.oep_numero_pedido || 'N/A'}</td>
                                        <td class="text-center align-middle">${formatarNumero(reserva.oei_quantidade, false)}</td>
                                    </tr>`;
                });
                tabelaHtml += `</tbody></table>`;
                $containerDetalhes.html(tabelaHtml);
            } else {
                $containerDetalhes.html('<p class="text-center text-muted">Nenhuma reserva encontrada para este item.</p>');
            }
        });
    });

});