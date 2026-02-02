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

        // Verifica se estamos em modo de busca ativa
        const isSearching = !!currentSearchTerm;

        if (Object.keys(data).length === 0) {
            // Se houver termo de busca e nenhum resultado
            if (isSearching) {
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
                    <th>Descrição (Câmara / Endereço)</th> 
                    <th class="text-center align-middle" style="width: 10%;">Caixas Físicas</th>
                    <th class="text-center align-middle" style="width: 10%;">Caixas Reserv.</th>
                    <th class="text-center align-middle" style="width: 10%;">Caixas Disp.</th>
                    <th class="text-center align-middle" style="width: 10%;">Quilos (kg)</th>
                    <th class="text-center align-middle" style="width: 10%;">Ação</th>
                </tr>
            </thead>
            <tbody>
    `;

        $.each(data, function (camaraId, camara) {

            // 2. CÂMARAS
            const caixasDisponiveisCamara = camara.total_caixas - camara.total_caixas_reservadas;

            // Ícone da CÂMARA: Se estiver pesquisando, começa aberto (fa-minus-square)
            const camaraIconClass = isSearching ? 'fa-minus-square' : 'fa-plus-square';

            html += `
            <tr class="table-primary" style="border-top: 2px solid #a9c6e8; border-bottom: 1px solid #a9c6e8;">
                <td><i class="fas ${camaraIconClass} toggle-btn" data-target=".camara-${camaraId}"></i></td>
                <td><strong>${camara.nome} (${camara.codigo})</strong></td>
                <td class="text-center" style="width: 10%;"><strong>${formatarNumero(camara.total_caixas)}</strong></td>
                <td class="text-center text-danger" style="width: 10%;"><strong>${formatarNumero(camara.total_caixas_reservadas)}</strong></td>
                <td class="text-center text-success fw-bolder" style="width: 10%;"><strong>${formatarNumero(caixasDisponiveisCamara)}</strong></td>
                <td class="text-center" style="width: 10%;"><strong>${formatarNumero(camara.total_quilos, true)}</strong></td>
                <td style="width: 10%;"></td>
            </tr>
        `;

            if (Object.keys(camara.enderecos).length > 0) {
                $.each(camara.enderecos, function (enderecoId, endereco) {
                    const temItens = endereco.itens.length > 0;

                    // Ícone do ENDEREÇO: Se estiver pesquisando E tiver itens, começa aberto (fa-minus-square)
                    let enderecoIconClass = temItens ? 'fa-plus-square' : 'fa-square text-muted';
                    if (isSearching && temItens) {
                        enderecoIconClass = 'fa-minus-square';
                    }

                    const toggleClass = temItens ? 'toggle-btn' : '';
                    const caixasDisponiveisEndereco = endereco.total_caixas - endereco.total_caixas_reservadas;

                    // Estilo de exibição: Se estiver pesquisando, TODAS as linhas de endereço devem vir abertas.
                    const displayStyle = isSearching ? 'display: table-row;' : 'display: none;';

                    // 3. ENDEREÇOS
                    html += `
                    <tr class="camara-${camaraId}" style="${displayStyle}">
                        <td style="width: 40px;"></td>
                        <td><i class="fas ${enderecoIconClass} ${toggleClass}" data-target=".endereco-${enderecoId}"></i> ${endereco.nome}</td>
                        <td class="text-center" style="width: 10%;">${formatarNumero(endereco.total_caixas)}</td>
                        <td class="text-center text-danger" style="width: 10%;">${formatarNumero(endereco.total_caixas_reservadas)}</td>
                        <td class="text-center text-success fw-bolder" style="width: 10%;">${formatarNumero(caixasDisponiveisEndereco)}</td>
                        <td class="text-center" style="width: 10%;">${formatarNumero(endereco.total_quilos, true)}</td>
                        <td class="text-center" style="width: 10%;">
                            <button class="btn btn-success btn-sm btn-alocar-item" data-id="${endereco.endereco_id}" data-nome="${endereco.nome}"><i class="fas fa-download me-1"></i>Alocar Item</button>
                        </td>
                    </tr>
                `;

                    if (temItens) {

                        // Estilo de exibição da TABELA DE ITENS: Se estiver pesquisando, TODOS os itens devem vir abertos.
                        const itemTableDisplayStyle = isSearching ? 'display: table-row;' : 'display: none;';

                        html += `
                        <tr class="endereco-${enderecoId}" style="${itemTableDisplayStyle}">
                            <td colspan="7">
                                <div class="ps-5 p-3 border rounded shadow-sm bg-white">
                                    <table class="table table-sm table-bordered table-hover mb-0" style="width: 100%;">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40%; text-align: left; padding-left: 20px;">Produto</th>
                                                <th style="width: 10%; text-align: center; vertical-align: middle;">Lote</th>
                                                
                                                <th style="width: 10%; text-align: center; vertical-align: middle;">Qtd. Física</th>
                                                <th style="width: 10%; text-align: center; vertical-align: middle;">Qtd. Reservada</th>
                                                <th style="width: 10%; text-align: center; vertical-align: middle;">Qtd. Disponível</th>
                                                
                                                <th style="width: 10%; text-align: center; vertical-align: middle;"></th> 
                                                
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
                                    <td style="width: 40%; padding-left: 20px;">${item.produto}</td>
                                    <td style="width: 10%; text-align: center; vertical-align: middle;">${item.lote}</td>
                                    
                                    <td class="text-center align-middle" style="width: 10%;">${formatarNumero(item.quantidade_fisica)}</td>
                                    <td class="text-center align-middle" style="width: 10%;">${reservadoHtml}</td>
                                    <td class="text-center align-middle text-success fw-bolder" style="width: 10%;">${formatarNumero(qtdDisponivel)}</td>
                                    
                                    <td style="width: 10%;"></td> 
                                    <td class="text-center align-middle" style="width: 10%;">
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-warning btn-xs btn-transferir-item text-dark" 
                                                data-alocacao-id="${item.alocacao_id}"
                                                data-produto="${item.produto}"
                                                data-lote="${item.lote}"
                                                data-qtd="${qtdDisponivel}" 
                                                data-origem="${endereco.nome}"
                                                title="Transferir para outro endereço">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            
                                            <button class="btn btn-danger btn-xs btn-desalocar-item-especifico ms-1" 
                                                data-alocacao-id="${item.alocacao_id}" 
                                                title="Desalocar este item">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>`;
                        });
                        html += `</tbody></table>
                                </div>
                            </td>
                        </tr>`;
                    }

                });
            } else {
                html += `<tr class="camara-${camaraId}" style="display: none;"><td colspan="7" class="text-muted ps-5">Nenhum endereço cadastrado nesta câmara.</td></tr>`;
            }
        });

        html += '</tbody></table>';
        $container.html(html);
    }

    /**
     * Carrega os dados do estoque, aceitando um termo de busca opcional.
     * @param {string} termoBusca - Termo para filtrar por produto ou lote.
     */
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
            notificacaoAviso('Atenção', 'Por favor, digite pelo menos 3 caracteres para a busca.');
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

    // --- FIM DA LÓGICA DE PESQUISA ---

    // Inicialização
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
                carregarDados(currentSearchTerm); // Recarrega com ou sem filtro
            } else {
                notificacaoErro('Erro ao Alocar', response.message);
            }
        });
    });

    // --- EVENTO PARA DESALOCAR UM ITEM ESPECÍFICO ---
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
                            carregarDados(currentSearchTerm); // Recarrega com ou sem filtro
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
                    let numeroOE = reserva.oe_numero;
                    let exibicaoOE;

                    if (/^[0-9]/.test(numeroOE)) {
                        // Caso comece com número, pega os 4 primeiros dígitos
                        exibicaoOE = String(numeroOE).substring(0, 4).padStart(4, '0');
                    }
                    else{
                        // Caso tenha prefixo (ex.: REP, LOTE, etc)
                        // Extrai o prefixo + número inicial
                        let match = numeroOE.match(/([A-Z]+)\.?(\d+)/i);
                        if(match){
                            exibicaoOE = `LOTE ${match[1].toUpperCase()} ${match[2]}`;
                        }else{
                            // Fallback: mostra o valor original
                            exibicaoOE = numeroOE;
                        }
                    }
                    tabelaHtml += `<tr>
                                        <td class="text-center align-middle">${exibicaoOE}</td>
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

    // --- LÓGICA DE TRANSFERÊNCIA ---

    const $modalTransf = $('#modal-transferir-item');
    const $formTransf = $('#form-transferir-item');
    const $selectDestino = $('#select-endereco-destino');

    // 1. Inicializa Select2 para buscar endereços
    $selectDestino.select2({
        dropdownParent: $modalTransf,
        placeholder: "Busque o endereço de destino...",
        theme: "bootstrap-5",
        ajax: {
            url: "ajax_router.php?action=buscarEnderecosSelect",
            dataType: 'json',
            delay: 250,
            data: function (params) { return { term: params.term }; },
            processResults: function (data) { return { results: data.results }; },
            cache: true
        }
    });

    // 2. Clique no botão de Transferir (Abre Modal)
    $container.on('click', '.btn-transferir-item', function () {
        const uid = $(this).data('alocacao-id');
        const prod = $(this).data('produto');
        const lote = $(this).data('lote');
        const qtdMax = parseFloat($(this).data('qtd'));
        const origemNome = $(this).data('origem');

        // Preenche os campos visuais
        $('#transf_alocacao_id').val(uid);
        $('#transf_produto_nome').text(prod);
        $('#transf_lote').text(lote);
        $('#transf_origem_nome').text(origemNome);

        // Configura o input de quantidade
        $('#transf_quantidade').val(qtdMax).attr('max', qtdMax);
        $('#transf_max_qtd').text(formatarNumero(qtdMax));

        // Reseta o select
        $selectDestino.val(null).trigger('change');

        $modalTransf.modal('show');
    });

    // 3. Envio do Formulário
    $formTransf.on('submit', function (e) {
        e.preventDefault();

        // Validação básica de destino diferente de origem (opcional no front, mas bom ter)
        // O back já garante a lógica, mas aqui evita requisição inútil.

        const formData = new FormData(this);
        // Adiciona CSRF se não estiver vindo automático
        formData.append('csrf_token', csrfToken);

        $.ajax({
            url: 'ajax_router.php?action=transferirItemEndereco',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalTransf.modal('hide');
                notificacaoSucesso('Sucesso!', response.message);
                carregarDados(currentSearchTerm); // Atualiza a árvore
            } else {
                notificacaoErro('Erro na Transferência', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro', 'Falha na comunicação com o servidor.');
        });
    });

});