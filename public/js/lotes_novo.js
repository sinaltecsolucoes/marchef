// /public/js/lotes_novo.js
$(document).ready(function () {

    // --- Seletores e Variáveis Globais ---
    const pageType = $('body').data('page-type') || $('#page-context').data('page-type');
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalLoteNovo = $('#modal-lote-novo');
    const $modalFinalizarLote = $('#modal-finalizar-lote');
    const $tabelaLotes = $('#tabela-lotes-novo');
    const $tabelaItensProducao = $('#tabela-itens-producao-novo');
    const $tabelaItensEmbalagem = $('#tabela-itens-embalagem-novo');
    const $formHeader = $('#form-lote-novo-header');
    const $modalImpressao = $('#modal-imprimir-etiqueta');
    const formatarBR = (valor, decimais = 3) => {
        if (!valor && valor !== 0) return '';
        return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: decimais, maximumFractionDigits: decimais }).format(valor);
    };

    let tabelaLotesNovo, loteIdAtual;
    let modoEdicao = false;
    let dadosOriginaisEdicao = null;


    // ==================================================
    // SELECT2: helper centralizado
    // ==================================================
    function initSelect2($el, placeholder = 'Selecione...', parent = $modalLoteNovo) {
        if (!$el || $el.length === 0) return;
        // se já inicializado, não reinicializa
        if ($el.hasClass('select2-hidden-accessible')) return;

        $el.select2({
            placeholder: placeholder,
            allowClear: true,
            width: '100%',
            dropdownParent: parent,
            theme: 'bootstrap-5',
            minimumResultsForSearch: 0 // força a exibição do campo de pesquisa
        });
    }

    // Inicializa selects do Header (Aba 1) já no load para garantir presença do search
    initSelect2($('#lote_fornecedor_id_novo'), 'Selecione o fornecedor');
    initSelect2($('#lote_cliente_id_novo'), 'Selecione o cliente');


    // --- Inicialização da Tabela DataTables ---
    tabelaLotesNovo = $('#tabela-lotes-novo').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": "ajax_router.php?action=listarLotesNovos",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "responsive": true,
        "columns": [
            {
                "data": "lote_completo_calculado",
                "className": "text-center align-middle",
            },
            {
                "data": "fornecedor_razao_social",
                "className": "align-middle",
            },
            {
                "data": "lote_data_fabricacao",
                "className": "col-centralizavel align-middle",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "lote_status",
                "className": "col-centralizavel align-middle",
                "render": function (data) {
                    let badgeClass = 'bg-secondary';
                    if (data === 'EM ANDAMENTO') badgeClass = 'bg-warning text-dark';
                    if (data === 'PARCIALMENTE FINALIZADO') badgeClass = 'bg-info';
                    if (data === 'FINALIZADO') badgeClass = 'bg-success';
                    if (data === 'CANCELADO') badgeClass = 'bg-danger';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            {
                "data": "lote_data_cadastro",
                "className": "col-centralizavel align-middle",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data);
                    return date.toLocaleString('pt-BR');
                }
            },
            {
                "data": "lote_id",
                "orderable": false,
                "className": "col-centralizavel align-middle",

                "render": function (data, type, row) {
                    const status = row.lote_status;
                    const loteId = row.lote_id;
                    const loteNome = row.lote_completo_calculado;
                    let btnHtml = ''; // ← Botões principais
                    let menuItens = '';

                    const pageType = $('body').data('page-type');

                    // 1. LOTES ATIVOS (Em Andamento ou Parcialmente Finalizados)
                    if (status === 'EM ANDAMENTO' || status === 'PARCIALMENTE FINALIZADO') {
                        btnHtml += `<button class="btn btn-warning btn-sm btn-editar-lote-novo me-1 d-inline-flex align-items-center" data-id="${loteId}" title="Editar Lote"><i class="fas fa-pencil-alt me-1"></i>Editar</button>`;

                        if (pageType === 'lotes_embalagem') {
                            btnHtml += `<button class="btn btn-success btn-sm btn-finalizar-lote-novo me-1 d-inline-flex align-items-center" data-id="${loteId}" data-nome="${loteNome}" title="Finalizar Lote"><i class="fas fa-check-circle me-1"></i>Finalizar</button>`;
                        }

                        if (pageType === 'lotes_producao') {
                            menuItens += `<li><a class="dropdown-item btn-cancelar-lote d-inline-flex align-items-center" href="#" data-id="${loteId}" data-nome="${loteNome}">Cancelar Lote</a></li>`;
                        }

                        // INATIVAR: Apenas no Recebimento
                        if (pageType === 'lotes_recebimento') {
                            menuItens += `<li><a class="dropdown-item text-danger btn-inativar-lote d-inline-flex align-items-center" href="#" data-id="${loteId}" data-nome="${loteNome}">Inativar Lote</a></li>`;
                        }
                        // 2. LOTES FINALIZADOS
                    } else if (status === 'FINALIZADO') {
                        btnHtml += `<button class="btn btn-info btn-sm btn-editar-lote-novo me-1 d-inline-flex align-items-center" data-id="${loteId}" title="Visualizar Lote"><i class="fas fa-search me-1"></i>Visualizar</button>`;

                        // REABRIR: Apenas na Embalagem (Conforme sua regra)
                        if (pageType === 'lotes_embalagem') {
                            menuItens += `<li><a class="dropdown-item btn-reabrir-lote d-inline-flex align-items-center" href="#" data-id="${loteId}" data-nome="${loteNome}">Reabrir Lote</a></li>`;
                        }
                        // 3. LOTES CANCELADOS (Inativos)
                    } else if (status === 'CANCELADO') {
                        btnHtml += `<button class="btn btn-secondary btn-sm btn-editar-lote-novo me-1 d-inline-flex align-items-center" data-id="${loteId}" title="Visualizar Lote"><i class="fas fa-search me-1"></i>Visualizar</button>`;

                        // REATIVAR: Apenas no Recebimento (Conforme sua regra)
                        if (pageType === 'lotes_recebimento') {
                            menuItens += `<li><a class="dropdown-item text-success btn-reativar-lote-novo d-inline-flex align-items-center" href="#" data-id="${loteId}" data-nome="${loteNome}">Reativar Lote</a></li>`;
                        }
                    }

                    // Botão Excluir (Aparece em qualquer estado, dependendo da regra, geralmente para Produção)
                    if (pageType === 'lotes_producao') {
                        if (menuItens !== '') {
                            menuItens += `<li><hr class="dropdown-divider"></li>`;
                        }
                        menuItens += `<li><a class="dropdown-item text-danger btn-excluir-lote d-inline-flex align-items-center" href="#" data-id="${loteId}" data-nome="${loteNome}">Excluir Permanentemente</a></li>`;
                    }

                    // Montagem do HTML final
                    let acoesHtml = `<div class="btn-group">${btnHtml}</div>`;

                    if (menuItens) {
                        acoesHtml += `
                                    <div class="btn-group d-inline-block">
                                        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        Mais
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                        ${menuItens}
                                        </ul>
                                    </div>`;
                    }
                    return acoesHtml;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[4, 'desc']]
    });

    // --- Funções Auxiliares ---
    function carregarFornecedores() {
        return $.get('ajax_router.php?action=getFornecedorOptions').done(function (response) {
            if (response.success) {
                const $select = $('#lote_fornecedor_id_novo');
                $select.empty().append('<option value=""></option>');
                response.data.forEach(function (fornecedor) {
                    const opt = new Option(`${fornecedor.nome_display} (Cód: ${fornecedor.ent_codigo_interno})`, fornecedor.ent_codigo);
                    $(opt).data('codigo-interno', fornecedor.ent_codigo_interno);
                    $select.append(opt);
                });
                // Se Select2 já foi inicializado, atualiza via change
                $select.trigger('change');
            } else {
                console.error('Erro ao carregar fornecedores:', response.message);
            }
        }).fail(function (xhr, status, error) {
            // Lógica de ERRO DE COMUNICAÇÃO
            console.error('Erro na requisição AJAX:', status, error);
        });
    }

    // Função para carregar Clientes 
    function carregarClientes() {
        return $.get('ajax_router.php?action=getClienteOptions', { term: '' }).done(function (response) {
            if (response.data) {
                const $select = $('#lote_cliente_id_novo');
                $select.empty().append('<option value=""></option>');

                response.data.forEach(function (cliente) {
                    const opt = new Option(cliente.text, cliente.id);
                    $(opt).data('codigo-interno', cliente.ent_codigo_interno);
                    $select.append(opt);
                });
                $select.trigger('change');
            } else {
                console.error('Erro ao carregar clientes:', response.message);
            }
        }).fail(function (xhr, status, error) {
            console.error('Erro na requisição AJAX (carregarClientes):', status, error);
        });
    }

    /**
    * Função para carregar os produtos no select da Aba 2
    * @param {*} tipoEmbalagemFiltro 
    */
    function carregarProdutosPrimarios() {
        return $.ajax({
            url: 'ajax_router.php?action=getProdutoOptions',
            type: 'GET',
            data: { tipo_embalagem: 'PRIMARIA' },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    const $selectProduto = $('#item_prod_produto_id_novo');
                    $selectProduto.empty().append('<option value=""></option>');
                    response.data.forEach(produto => {
                        const textoDaOpcao = `${produto.prod_descricao} (Cód: ${produto.prod_codigo_interno || 'N/A'})`;
                        const option = new Option(textoDaOpcao, produto.prod_codigo);
                        $(option).data('validade-meses', produto.prod_validade_meses);
                        $(option).data('peso-embalagem', produto.prod_peso_embalagem);
                        $(option).data('codigo-interno', produto.prod_codigo_interno);
                        $selectProduto.append(option);
                    });
                    $selectProduto.trigger('change');
                } else {
                    // Trata o erro de negócio retornado pelo PHP
                    console.error('Erro ao carregar produtos:', response.message);
                }
            })
            .fail(function (xhr, status, error) {
                console.error('Falha na requisição AJAX para carregar produtos:', status, error);
            });
    }

    /**
     * Função para ATUALIZAR o valor do campo "Lote Completo" em tempo real
     */
    function atualizarLoteCompletoNovo() {
        const numero = $('#lote_numero_novo').val() || '0000';
        const dataFabStr = $('#lote_data_fabricacao_novo').val();
        const ciclo = $('#lote_ciclo_novo').val() || 'C';
        const viveiro = $('#lote_viveiro_novo').val() || 'V';

        // Lógica para pegar o código interno do cliente selecionado (se houver)
        // Precisaremos adicionar 'data-codigo-interno' ao carregar os clientes
        const clienteOption = $('#lote_cliente_id_novo').find(':selected');
        const codCliente = clienteOption.data('codigo-interno') || 'CC';

        let ano = 'YY';
        if (dataFabStr) {
            try {
                ano = new Date(dataFabStr + 'T00:00:00').getFullYear().toString().slice(-2);
            } catch (e) { /* ignora erro de data inválida durante a digitação */ }
        }
        const loteCompletoCalculado = `${numero}/${ano}-${ciclo}/${viveiro} ${codCliente}`;
        $('#lote_completo_calculado_novo').val(loteCompletoCalculado);
    }

    /**
    * Valida os campos obrigatórios do cabeçalho do lote.
    * @returns {Array} Uma lista de mensagens de erro. Vazia se tudo estiver OK.
    */
    function validarCabecalhoLote() {
        const erros = [];
        if (!$('#lote_numero_novo').val().trim()) {
            erros.push("O campo 'Número' é obrigatório.");
        }
        if (!$('#lote_data_fabricacao_novo').val()) {
            erros.push("O campo 'Data de Fabricação' é obrigatório.");
        }
        if (!$('#lote_cliente_id_novo').val()) {
            erros.push("O campo 'Cliente' é obrigatório.");
        }
        if (!$('#lote_fornecedor_id_novo').val()) {
            erros.push("O campo 'Fornecedor' é obrigatório.");
        }
        if (!$('#lote_completo_calculado_novo').val()) {
            erros.push("O campo 'Lote Completo' é obrigatório.");
        }
        return erros;
    }

    // Função auxiliar para buscar dados do lote, chamada pelo evento de editar
    /* function buscarDadosLoteParaEdicao(loteId) {
        $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const lote = response.data;
                const header = lote.header;
                const pageType = $('body').data('page-type'); // Captura o tipo da página

                // Preenche os selects (necessário reinicializar o Select2 às vezes)
                // Garante que select2 esteja inicializado e usa change normal
                initSelect2($('#lote_fornecedor_id_novo'), 'Selecione o fornecedor');
                initSelect2($('#lote_cliente_id_novo'), 'Selecione o cliente');

                $('#lote_fornecedor_id_novo').val(header.lote_fornecedor_id).trigger('change');
                $('#lote_cliente_id_novo').val(header.lote_cliente_id).trigger('change');

                // Preenche os inputs de texto
                $('#lote_id_novo').val(header.lote_id);
                $('#lote_numero_novo').val(header.lote_numero);
                $('#lote_data_fabricacao_novo').val(header.lote_data_fabricacao);
                $('#lote_ciclo_novo').val(header.lote_ciclo);
                $('#lote_viveiro_novo').val(header.lote_viveiro);
                $('#lote_completo_calculado_novo').val(header.lote_completo_calculado);

                $('#btn-salvar-lote-novo-header').html('<i class="fas fa-save me-1"></i> Salvar Alterações');
                $('#modal-lote-novo-label').text('Editar Lote: ' + header.lote_completo_calculado);

                // --- LÓGICA DE HABILITAÇÃO DAS ABAS ---
                if (pageType === 'lotes_recebimento') {
                    // Se for Recebimento, habilita a Aba 2 (Detalhes) e define o ID oculto
                    $('#aba-detalhes-recebimento-tab').removeClass('disabled');
                    $('#item_receb_lote_id').val(header.lote_id);

                    // Opcional: Já carregar a tabela de itens para garantir que está atualizada
                    recarregarItensRecebimento(header.lote_id);
                } else {
                    // Se for Produção ou Embalagem, habilita as abas correspondentes
                    $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').removeClass('disabled');
                }

                // Volta sempre para a primeira aba ao abrir
                new bootstrap.Tab($('#aba-info-lote-novo-tab')[0]).show();

                // --- BLOQUEIOS DE SOMENTE LEITURA (PRODUÇÃO/EMBALAGEM) ---
                // Determina se o lote está fechado (Status Finalizado/Cancelado)
                const status = header.lote_status;
                const isReadOnlyGlobal = (status === 'FINALIZADO' || status === 'CANCELADO');

                // Carrega as tabelas das outras abas (mesmo que estejam ocultas, para garantir)
                $.when(recarregarItensProducao(loteId), recarregarItensEmbalagem(loteId)).done(function () {

                    configurarModalModoLeitura(isReadOnlyGlobal);

                    if (pageType === 'lotes_producao') {
                        $('#form-lote-novo-header').find('input, select').prop('disabled', true);
                        $('#btn-salvar-lote-novo-header').hide();
                    }

                    if (pageType === 'lotes_embalagem') {
                        $('#form-lote-novo-header').find('input, select').prop('disabled', true);
                        $('#btn-salvar-lote-novo-header').hide();

                        $('#form-lote-novo-producao').find('input, select').prop('disabled', true);
                        $('#form-lote-novo-producao').find('button').hide();

                        // Na embalagem, já abre direto na aba 3
                        new bootstrap.Tab($('#aba-embalagem-novo-tab')[0]).show();
                    }
                });

                $modalLoteNovo.modal('show');
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    } */

    // Função auxiliar para buscar dados do lote, chamada pelo evento de editar
    function buscarDadosLoteParaEdicao(loteId) {
        $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const lote = response.data;
                const header = lote.header;
                const pageType = $('body').data('page-type');

                // Preenche selects e inputs do Header (Aba 1)
                initSelect2($('#lote_fornecedor_id_novo'), 'Selecione o fornecedor');
                initSelect2($('#lote_cliente_id_novo'), 'Selecione o cliente');

                $('#lote_fornecedor_id_novo').val(header.lote_fornecedor_id).trigger('change');
                $('#lote_cliente_id_novo').val(header.lote_cliente_id).trigger('change');

                $('#lote_id_novo').val(header.lote_id);
                $('#lote_numero_novo').val(header.lote_numero);
                $('#lote_data_fabricacao_novo').val(header.lote_data_fabricacao);
                $('#lote_ciclo_novo').val(header.lote_ciclo);
                $('#lote_viveiro_novo').val(header.lote_viveiro);
                $('#lote_completo_calculado_novo').val(header.lote_completo_calculado);

                $('#btn-salvar-lote-novo-header').html('<i class="fas fa-save me-1"></i> Salvar Alterações');
                $('#modal-lote-novo-label').text('Editar Lote: ' + header.lote_completo_calculado);

                // --- LÓGICA DE HABILITAÇÃO DAS ABAS ---
                // Remove a classe 'disabled' das abas relevantes para permitir navegação
                if (pageType === 'lotes_recebimento') {
                    $('#aba-detalhes-recebimento-tab').removeClass('disabled');
                    $('#item_receb_lote_id').val(header.lote_id);
                    recarregarItensRecebimento(header.lote_id);
                } else {
                    $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').removeClass('disabled');
                }

                // --- BLOQUEIOS DE SOMENTE LEITURA ---
                const status = header.lote_status;
                const isReadOnlyGlobal = (status === 'FINALIZADO' || status === 'CANCELADO');

                // Carrega dados das outras abas (Eager Loading para performance)
                $.when(recarregarItensProducao(loteId), recarregarItensEmbalagem(loteId)).done(function () {

                    configurarModalModoLeitura(isReadOnlyGlobal);

                    // Regras específicas de bloqueio visual por módulo
                    if (pageType === 'lotes_producao' || pageType === 'lotes_embalagem') {
                        // Header é sempre somente leitura nestes módulos
                        $('#form-lote-novo-header').find('input, select').prop('disabled', true);
                        $('#btn-salvar-lote-novo-header').hide();
                    }

                    if (pageType === 'lotes_embalagem') {
                        // Produção também é somente leitura na Embalagem
                        $('#form-lote-novo-producao').find('input, select').prop('disabled', true);
                        $('#form-lote-novo-producao').find('button').hide();
                    }
                });

                $modalLoteNovo.modal('show');
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    }

    /**
     * Função para calcular a validade
     * @param {*} dataFabricacao 
     * @param {*} mesesValidade 
     * @returns 
     */
    function calcularValidadeArredondandoParaCima(dataFabricacao, mesesValidade) {
        const dataCalculada = new Date(dataFabricacao.getTime());
        const diaOriginal = dataCalculada.getDate();
        dataCalculada.setMonth(dataCalculada.getMonth() + mesesValidade);
        if (dataCalculada.getDate() !== diaOriginal) {
            dataCalculada.setDate(1);
            dataCalculada.setMonth(dataCalculada.getMonth() + 1);
        }
        // Formata para 'YYYY-MM-DD' para preencher o input type="date'
        return dataCalculada.toISOString().split('T')[0];
    }

    /**
     * Função para calcular o peso total do item de acordo com a quantidade e peso da embalagem)
     */
    function calcularPesoTotal() {
        const quantidade = parseFloat($('#item_quantidade').val()) || 0;
        const pesoEmbalagem = parseFloat($('#item_produto_id').find(':selected').data('peso-embalagem')) || 0;

        const pesoTotal = quantidade * pesoEmbalagem;

        // Exibe o resultado formatado com 3 casas decimais
        $('#item_peso_total').val(pesoTotal.toFixed(3));
    }

    // Converte "1.234,56" para float 1234.56
    function brToFloat(str) {
        if (!str) return 0;
        // Remove pontos de milhar e troca vírgula por ponto
        return parseFloat(str.toString().replace(/\./g, '').replace(',', '.')) || 0;
    }

    // Converte float 1234.56 para "1.234,56" (ou com 3 casas se precisar)
    function floatToBr(val, decimais = 2) {
        if (val === '' || val === null || val === undefined) return '';
        return parseFloat(val).toLocaleString('pt-BR', {
            minimumFractionDigits: decimais,
            maximumFractionDigits: decimais
        });
    }

    /**
    * Configura o modal de lote para modo de edição ou apenas visualização.
    * @param {boolean} isReadOnly - True para modo de visualização, false para edição.
    */
    function configurarModalModoLeitura(isReadOnly) {
        // Seleciona todos os formulários e botões dentro do modal novo
        const $forms = $modalLoteNovo.find('form');
        const $botoesDeAcao = $modalLoteNovo.find('#btn-salvar-lote-novo-header, #btn-adicionar-item-producao, #btn-adicionar-item-embalagem, #btn-cancelar-edicao-producao, #btn-cancelar-edicao-embalagem');

        // Seleciona os CABEÇALHOS das colunas de ações
        const $colunasDeAcoes = $modalLoteNovo.find('.coluna-acoes');

        // Desabilita/habilita todos os campos de input, select, etc.
        $forms.find('input, select, textarea').prop('disabled', isReadOnly);

        // Mostra ou esconde os botões de ação (Salvar, Adicionar, etc.)
        $botoesDeAcao.toggle(!isReadOnly);

        // Mostra ou esconde a coluna de ações inteira (cabeçalho e corpo)
        $colunasDeAcoes.toggle(!isReadOnly);

        // Se estiver em modo de leitura, muda o texto do botão de editar principal
        if (isReadOnly) {
            $('.btn-editar-lote-novo[data-id="' + loteIdAtual + '"]').html('<i class="fas fa-search"></i> Visualizar');
            $('#modal-lote-novo-label').text('Visualizar Lote: ' + $('#lote_completo_calculado_novo').val());
        }
    }

    /**
     * Busca os itens de produção de um lote e redesenha a tabela na Aba 2.
     * @param {number} loteId O ID do lote a ser consultado.
     */
    function recarregarItensProducao(loteId) {
        if (!loteId) return;

        const $tbody = $('#tabela-itens-producao-novo');
        $tbody.html('<tr><td colspan="6" class="text-center">A carregar itens...</td></tr>');

        return $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            $tbody.empty();
            if (response.success && response.data.producao.length > 0) {
                response.data.producao.forEach(item => {
                    const rowHtml = `
                        <tr>
                            <td class="align-middle font-small">${item.prod_descricao}</td>
                            <td class="text-center align-middle font-small">${item.prod_unidade}</td>
                            <td class="text-center align-middle font-small">${parseFloat(item.item_prod_quantidade).toFixed(0)}</td>
                            <td class="text-center align-middle font-small">${parseFloat(item.item_prod_saldo).toFixed(0)}</td>
                            <td class="text-center align-middle font-small">${new Date(item.item_prod_data_validade + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                            <td class="text-center align-middle coluna-acoes-lote">
                                <button class="btn btn-warning btn-sm btn-editar-item-producao" 
                                            data-id="${item.item_prod_id}"
                                            data-produto-id="${item.item_prod_produto_id}"
                                            data-quantidade="${item.item_prod_quantidade}"
                                            data-validade="${item.item_prod_data_validade}"
                                            title="Editar Item"><i class="fas fa-pencil-alt me-1"></i>Editar</button>
                                <button class="btn btn-danger btn-sm btn-excluir-item-producao" data-id="${item.item_prod_id}"title="Excluir Item"><i class="fas fa-trash-alt me-1"></i>Excluir</button>
                                <button class="btn btn-info btn-sm btn-imprimir-etiqueta-producao" data-id="${item.item_prod_id}" title="Imprimir Etiqueta"><i class="fas fa-print me-1"></i>Etiqueta</button>
                            </td>
                        </tr>
                    `;
                    $tbody.append(rowHtml);
                });
            } else {
                $tbody.html('<tr><td colspan="6" class="text-center text-muted">Nenhum item de produção adicionado a este lote.</td></tr>');
            }
        });
    }

    /**
     * Busca os itens de embalagem de um lote e redesenha a tabela na Aba 3.
     * @param {number} loteId O ID do lote a ser consultado.
     */
    function recarregarItensEmbalagem(loteId) {
        if (!loteId) return;

        const $tbody = $('#tabela-itens-embalagem-novo');
        $tbody.html('<tr><td colspan="5" class="text-center">A carregar itens...</td></tr>');

        return $.ajax({
            url: `ajax_router.php?action=getItensEmbalagemNovo&lote_id=${loteId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            $tbody.empty();
            if (response.success && response.data.length > 0) {
                response.data.forEach(item => {
                    const rowHtml = `
                    <tr>
                        <td class="align-middle font-small">${item.produto_secundario_nome}</td>
                        <td class="text-center align-middle font-small">${parseInt(item.item_emb_qtd_sec)}</td>
                        <td class="align-middle font-small">${item.produto_primario_nome}</td>
                        <td class="text-center align-middle font-small">${parseFloat(item.item_emb_qtd_prim_cons).toFixed(0)}</td>
                        <td class="text-center align-middle coluna-acoes-lote">
                            <button class="btn btn-warning btn-sm btn-editar-item-embalagem" 
                                        data-id="${item.item_emb_id}"
                                        data-primario-item-id="${item.item_emb_prod_prim_id}"
                                        data-secundario-prod-id="${item.item_emb_prod_sec_id}"
                                        data-quantidade="${item.item_emb_qtd_sec}"
                                        data-consumo="${item.item_emb_qtd_prim_cons}"
                                        title="Editar Item"><i class="fas fa-pencil-alt me-1"></i>Editar</button>
                            <button class="btn btn-danger btn-sm btn-excluir-item-embalagem" data-id="${item.item_emb_id}" title="Excluir Item"><i class="fas fa-trash-alt me-1"></i>Excluir</button>
                            <button class="btn btn-info btn-sm btn-imprimir-etiqueta-embalagem" data-id="${item.item_emb_id}" title="Imprimir Etiqueta"><i class="fas fa-print me-1"></i>Etiqueta</button>
                            </td>
                    </tr>
                `;
                    $tbody.append(rowHtml);
                });
            } else {
                $tbody.html('<tr><td colspan="5" class="text-center text-muted">Nenhum item de embalagem adicionado a este lote.</td></tr>');
            }
        });
    }

    /**
     * Função para carregar os itens de produção do lote atual AGRUPADOS por produto.
     * Exibe o saldo formatado condicionalmente (KG vs Outros).
     */
    function carregarItensProducaoParaSelecao(loteId) {
        return $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const $select = $('#item_emb_prod_prim_id_novo');
                initSelect2($select, 'Selecione item primário');
                $select.empty().append('<option value=""></option>');

                const produtosAgrupados = {};

                // 1. Agrupa os saldos
                response.data.producao.forEach(item => {
                    const prodId = item.item_prod_produto_id;
                    const saldo = parseFloat(item.item_prod_saldo);

                    if (saldo > 0.001) {
                        if (!produtosAgrupados[prodId]) {
                            produtosAgrupados[prodId] = {
                                nome: item.prod_descricao,
                                saldo: 0,
                                un: (item.prod_unidade || 'UN').toUpperCase() // Garante maiúsculo
                            };
                        }
                        produtosAgrupados[prodId].saldo += saldo;
                    }
                });

                // 2. Cria as opções com a formatação condicional
                Object.keys(produtosAgrupados).forEach(prodId => {
                    const dados = produtosAgrupados[prodId];
                    let saldoFormatado;

                    // Lógica de Formatação Visual
                    if (dados.un === 'KG') {
                        // Se for KG: Usa 3 casas decimais, vírgula e ponto de milhar
                        // Ex: 1.234,567
                        saldoFormatado = dados.saldo.toLocaleString('pt-BR', {
                            minimumFractionDigits: 3,
                            maximumFractionDigits: 3
                        });
                    } else {
                        // Se for CX, UN, SC, PCT: Usa número inteiro, mas COM ponto de milhar
                        // Ex: 1.234
                        saldoFormatado = parseInt(dados.saldo).toLocaleString('pt-BR');
                    }

                    const texto = `${dados.nome} (Saldo: ${saldoFormatado} ${dados.un})`;

                    const option = new Option(texto, prodId);
                    $(option).data('saldo-total', dados.saldo); // Guarda o valor bruto para validação

                    $select.append(option);
                });

                $select.trigger('change');
            }
        });
    }

    function calcularConsumoEmbalagem() {
        const $form = $('#form-lote-novo-embalagem');
        const $selectPrimario = $('#item_emb_prod_prim_id_novo');
        const $selectSecundario = $('#item_emb_prod_sec_id_novo');
        const $qtdInput = $('#item_emb_qtd_sec_novo');
        const $feedback = $('#feedback-consumo-embalagem');
        const $btnAdicionar = $('#btn-adicionar-item-embalagem');

        // Verifica se estamos em modo de edição
        const isEditing = !!$('#item_emb_id_novo').val();
        const consumoOriginal = parseFloat($form.data('consumo-original')) || 0;

        const saldoTexto = $selectPrimario.find('option:selected').text();
        const match = saldoTexto.match(/Saldo: ([\d\.]+)/);
        const saldoAtualDropdown = match ? parseFloat(match[1]) : 0;


        // Se estivermos a editar, o saldo disponível para o cálculo é o saldo atual MAIS o que o item já tinha consumido.
        const saldoParaCalculo = isEditing ? (saldoAtualDropdown + consumoOriginal) : saldoAtualDropdown;

        const unidadesPorEmbalagem = parseFloat($selectSecundario.find('option:selected').data('unidades-primarias')) || 0;
        const quantidade = parseInt($qtdInput.val()) || 0;

        if (saldoParaCalculo === 0 || unidadesPorEmbalagem === 0 || quantidade === 0) {
            $feedback.text('Preencha os campos para calcular o consumo.').removeClass('text-danger fw-bold').addClass('text-muted');
            $btnAdicionar.prop('disabled', true);
            return;
        }

        const consumoCalculado = quantidade * unidadesPorEmbalagem;
        const saldoRestante = saldoParaCalculo - consumoCalculado;

        if (consumoCalculado > saldoParaCalculo) {
            $feedback.html(`Consumo: <strong class="text-danger">${consumoCalculado.toFixed(3)}</strong> (Saldo insuficiente! Saldo disponível para a operação: ${saldoParaCalculo.toFixed(3)})`)
                .removeClass('text-muted').addClass('text-danger fw-bold');
            $btnAdicionar.prop('disabled', true);
        } else {
            $feedback.html(`Consumo: <strong>${consumoCalculado.toFixed(3)}</strong> (Saldo restante: ${saldoRestante.toFixed(3)})`)
                .removeClass('text-danger fw-bold').addClass('text-muted');
            $btnAdicionar.prop('disabled', false);
        }
    }

    /**
    * Reseta completamente o formulário de produção para o seu estado inicial.
    */
    function resetarFormularioProducao() {
        // 1. Limpa todos os campos do formulário
        $('#form-lote-novo-producao')[0].reset();

        // 2. Limpa campos específicos e reseta controles
        $('#item_prod_id_novo').val(''); // Limpa o ID oculto do item
        $('#item_prod_produto_id_novo').val(null).trigger('change'); // Limpa o Select2
        $('#btn-adicionar-item-producao').html('<i class="fas fa-plus me-1"></i>Adicionar Item'); // Restaura o texto do botão

        // 3. Garante que o campo de data de validade volte ao estado bloqueado
        $('#liberar_edicao_validade_novo').prop('checked', false);
        $('#item_prod_data_validade_novo').prop('readonly', true);
    }

    // 1. Carregar lista de itens
    function recarregarItensRecebimento(loteId) {
        const $tbody = $('#tabela-itens-recebimento');
        $tbody.html('<tr><td colspan="7" class="text-center">Carregando...</td></tr>');

        $.ajax({
            url: `ajax_router.php?action=getItensRecebimento&lote_id=${loteId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            $tbody.empty();
            if (response.success && response.data.length > 0) {
                response.data.forEach(item => {
                    let origemHtml = item.lote_origem_nome ? `<span class="badge bg-info text-dark">${item.lote_origem_nome}</span>` : '-';

                    $tbody.append(`
                        <tr>
                            <td>${item.prod_descricao}</td>
                            <td>${origemHtml}</td>
                            <td>${item.item_receb_nota_fiscal || ''}</td>
                            <td>${item.item_receb_peso_nota_fiscal || ''}</td>
                            <td>${item.item_receb_total_caixas || ''}</td>
                            <td>${item.item_receb_peso_medio_ind || ''}</td>
                            <td>
                                <button class="btn btn-danger btn-sm btn-excluir-item-recebimento" data-id="${item.item_receb_id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
            } else {
                $tbody.html('<tr><td colspan="7" class="text-center text-muted">Nenhum detalhe lançado.</td></tr>');
            }
        });
    }

    function atualizarTipoEntradaMP() {
        const tipo = $('input[name="tipo_entrada_mp"]:checked').val();
        const $selectMateria = $('#item_receb_produto_id');
        const $selectLote = $('#item_receb_lote_origem_id');

        if (tipo === 'MATERIA_PRIMA') {
            // Habilita Matéria Prima
            $selectMateria.prop('disabled', false);

            // Bloqueia e Limpa Lote Origem
            $selectLote.val(null).trigger('change');
            $selectLote.prop('disabled', true);

            // Ajuste visual do Select2 (Cinza)
            $selectLote.parent().find('.select2-container').addClass('select2-container--disabled');
            $selectMateria.parent().find('.select2-container').removeClass('select2-container--disabled');

            // APLICA AS REGRAS VISUAIS DE MATÉRIA PRIMA (Labels, Readonlys, etc)
            aplicarModoMateriaPrima();

        } else {
            // Habilita Lote Origem (Reprocesso)
            $selectLote.prop('disabled', false);
            $selectLote.parent().find('.select2-container').removeClass('select2-container--disabled');

            // Bloqueia e Limpa Matéria Prima
            $selectMateria.val(null).trigger('change');
            $selectMateria.prop('disabled', true);
            $selectMateria.parent().find('.select2-container').addClass('select2-container--disabled');

            //  APLICA AS REGRAS VISUAIS DE REPROCESSO (Labels, Readonlys, etc)
            aplicarModoReprocesso();
        }
    }

    function aplicarModoReprocesso() {

        // labels
        $('label[for="item_receb_peso_nota_fiscal"]').text('Peso Reprocesso (kg)');

        // readonly
        $('#item_receb_total_caixas').prop('readonly', true);
        $('#item_receb_peso_medio_ind').prop('readonly', true);

        // desabilita edição manual
        //$('#item_receb_produto_id').prop('disabled', true);
        // garante consistência
        $('#item_receb_produto_id')
            .val(null)
            .prop('disabled', true)
            .trigger('change');
    }

    function aplicarModoMateriaPrima() {

        $('label[for="item_receb_peso_nota_fiscal"]').text('Peso NF (kg)');

        $('#item_receb_total_caixas').prop('readonly', false);
        $('#item_receb_peso_medio_ind').prop('readonly', false);

        // limpa dados herdados
        // $('#form-recebimento-detalhe')[0].reset();

        //  $('#item_receb_produto_id').prop('disabled', false);
        $('#item_receb_produto_id')
            .prop('disabled', false)
            .trigger('change');
    }

    // atualizarTipoEntradaMP();
    function aplicarModoEntrada() {

        atualizarTipoEntradaMP();

        const tipo = $('input[name="tipo_entrada_mp"]:checked').val();

        if (tipo === 'LOTE_ORIGEM') {
            aplicarModoReprocesso();
        } else {
            aplicarModoMateriaPrima();
        }
    }

    function limparFormularioDetalhes() {
        $('#form-recebimento-detalhe')[0].reset();

        $('#item_receb_produto_id').val(null).trigger('change');
        $('#item_receb_lote_origem_id').val(null).trigger('change');

        $('#calc_peso_medio_fazenda').val('');
    }

    function sairModoEdicao() {
        modoEdicao = false;
        dadosOriginaisEdicao = null;

        $('#btn-adicionar-item-recebimento')
            .removeClass('btn-warning')
            .addClass('btn-success')
            .text('Adicionar Item');

        $('#btn-cancelar-edicao').addClass('d-none');
    }

    function calcularPesoMedioFazenda() {
        const peso = parseFloat($('#item_receb_peso_nota_fiscal').val());
        const caixas = parseInt($('#item_receb_total_caixas').val(), 10);

        if (peso > 0 && caixas > 0) {
            const resultado = (peso / caixas).toFixed(2);
            $('#calc_peso_medio_fazenda').val(resultado);
        } else {
            $('#calc_peso_medio_fazenda').val('');
        }
    }

    /**
     * Controla quais abas aparecem no modal dependendo do módulo.
     * @param {string} modulo - 'RECEBIMENTO', 'PRODUCAO' ou 'EMBALAGEM'
     */
    function configurarAbasPorModulo(modulo) {
        // 1. Mapeamento dos Botões das Abas (IDs)
        const $tabDados = $('#aba-info-lote-novo-tab');        // Aba 1
        const $tabDetalhes = $('#aba-detalhes-recebimento-tab');  // Aba 2
        const $tabProducao = $('#aba-producao-novo-tab');         // Aba 3
        const $tabEmbalagem = $('#aba-embalagem-novo-tab');        // Aba 4

        // 2. RESET: Esconde TODAS as abas (esconde o <li> pai do botão)
        $tabDados.parent().hide();
        $tabDetalhes.parent().hide();
        $tabProducao.parent().hide();
        $tabEmbalagem.parent().hide();

        // 3. Lógica de Exibição
        if (modulo === 'RECEBIMENTO') {
            // Exibe Aba 1 e 2
            $tabDados.parent().show();
            $tabDetalhes.parent().show();

            // Abre a Aba 1 por padrão
            new bootstrap.Tab($tabDados[0]).show();
        }
        else if (modulo === 'PRODUCAO') {
            // Exibe APENAS a Aba 3 (conforme solicitado)
            // Nota: Se quiser mostrar os Dados Gerais como leitura, descomente a linha abaixo:
            // $tabDados.parent().show(); 

            $tabProducao.parent().show();

            // Abre a Aba 3 diretamente
            new bootstrap.Tab($tabProducao[0]).show();
        }
        else if (modulo === 'EMBALAGEM') {
            // Exibe APENAS a Aba 4
            // $tabDados.parent().show(); // (Opcional: Dados gerais)

            $tabEmbalagem.parent().show();

            // Abre a Aba 4 diretamente
            new bootstrap.Tab($tabEmbalagem[0]).show();
        }
    }

    // Listener
    $('input[name="tipo_entrada_mp"]').on('change', aplicarModoEntrada);

    // Máscara para moedas/pesos: Formata enquanto digita
    $(document).on('input', '.mask-peso-3, .mask-peso-2', function () {
        let valor = $(this).val().replace(/\D/g, ''); // Remove tudo que não é dígito
        let decimais = $(this).hasClass('mask-peso-3') ? 3 : 2;
        let divisor = Math.pow(10, decimais);

        if (valor === '') {
            // Permite limpar o campo
            return;
        }

        // Converte para float e formata BR
        let formatado = (parseFloat(valor) / divisor).toLocaleString('pt-BR', {
            minimumFractionDigits: decimais,
            maximumFractionDigits: decimais
        });

        $(this).val(formatado);
    });

    // --- Event Handlers ---
    // Evento para o botão "Adicionar Novo Lote" (Apenas na tela de Recebimento)
    /*  $('#btn-adicionar-lote-novo').on('click', function () {
          const pageType = $('body').data('page-type');
  
          // 1. Bloqueio de Segurança
          if (pageType !== 'lotes_recebimento') {
              console.warn("Ação bloqueada: Página incorreta.");
              return;
          }
  
          configurarModalModoLeitura(false);
  
          // 2. Limpa o formulário principal
          const $headerForm = $('#form-lote-novo-header');
          if ($headerForm.length > 0) {
              $headerForm[0].reset();
          }
          $('#lote_id_novo').val('');
          $('#modal-lote-novo-label').text('Adicionar Novo Lote');
  
          // 3. Limpa formulário de detalhes (se existir)
          const $detalhesForm = $('#form-recebimento-detalhe');
          if ($detalhesForm.length > 0) {
              $detalhesForm[0].reset();
              $('#item_receb_lote_id').val(''); // Limpa o ID do lote nos detalhes
              $('#tabela-itens-recebimento').empty().html('<tr><td colspan="7" class="text-center text-muted">Salve o cabeçalho para adicionar itens.</td></tr>');
          }
  
          // 4. Limpa dropdowns e carrega dados
          $('#lote_fornecedor_id_novo, #lote_cliente_id_novo').val(null).trigger('change');
          carregarFornecedores();
          carregarClientes();
  
          // 5. Busca próximo número
          $.get('ajax_router.php?action=getProximoNumeroLoteNovo', function (response) {
              if (response.success) {
                  $('#lote_numero_novo').val(response.proximo_numero);
                  atualizarLoteCompletoNovo();
              }
          });
  
          // 6. GESTÃO DAS ABAS (REFATORADO)
          // Usa a função centralizada para exibir as abas corretas (1 e 2) e esconder as outras
          configurarAbasPorModulo('RECEBIMENTO');
  
          // Regra Específica de "Novo Lote": 
          // Embora o módulo RECEBIMENTO tenha a aba 2, ela começa DESABILITADA até salvar o cabeçalho.
          $('#aba-detalhes-recebimento-tab').addClass('disabled');
  
          // Abre o modal
          $modalLoteNovo.modal('show');
      }); */

    $('#btn-adicionar-lote-novo').on('click', function () {
        const pageType = $('body').data('page-type');

        if (pageType !== 'lotes_recebimento') return;

        configurarModalModoLeitura(false);

        // Limpeza dos Forms
        $('#form-lote-novo-header')[0].reset();
        $('#lote_id_novo').val('');
        $('#modal-lote-novo-label').text('Adicionar Novo Lote');

        $('#form-recebimento-detalhe')[0].reset();
        $('#item_receb_lote_id').val('');
        $('#tabela-itens-recebimento').empty().html('<tr><td colspan="7" class="text-center text-muted">Salve o cabeçalho para adicionar itens.</td></tr>');

        // Reset dos Selects
        $('#lote_fornecedor_id_novo').val(null).trigger('change');
        $('#lote_cliente_id_novo').val(null).trigger('change');

        // Garante carregamento das opções
        carregarFornecedores();
        carregarClientes();

        // Busca número
        $.get('ajax_router.php?action=getProximoNumeroLoteNovo', function (response) {
            if (response.success) {
                $('#lote_numero_novo').val(response.proximo_numero);
                atualizarLoteCompletoNovo();
            }
        });

        // Configuração Visual
        configurarAbasPorModulo('RECEBIMENTO'); // Mostra abas 1 e 2, esconde 3 e 4

        // Bloqueia a aba 2 até salvar
        $('#aba-detalhes-recebimento-tab').addClass('disabled');

        $modalLoteNovo.modal('show');
    });

    // Evento de clique para o botão "Adicionar Item"
    $('#btn-adicionar-item-producao').on('click', function () {

        const dataValidade = $('#item_prod_data_validade_novo').val();
        if (!dataValidade) {
            notificacaoErro('Validação', 'Por favor, insira uma Data de Validade para este produto.');
            return; // Impede a execução do resto da função
        }

        const loteId = loteIdAtual; // A variável global que guarda o ID do lote
        const itemId = $('#item_prod_id_novo').val(); // Pega o ID do campo hidden
        const isEditing = !!itemId; // Converte para booleano: true se tiver ID, false se não
        const action = isEditing ? 'atualizarItemProducaoNovo' : 'adicionarItemProducaoNovo';
        const formData = new FormData($('#form-lote-novo-producao')[0]);

        if (!isEditing) {
            // Se estiver criando, adiciona o ID do lote
            formData.append('item_prod_lote_id', loteId);
        }
        formData.append('csrf_token', csrfToken);

        $.ajax({
            url: `ajax_router.php?action=${action}`,
            type: 'POST',
            data: formData,
            processData: false, contentType: false, dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                // notificacaoSucesso('Sucesso!', 'Item de produção adicionado ao lote.');

                // Limpa o formulário para a próxima adição
                $('#btn-cancelar-edicao-producao').trigger('click'); // Dispara o evento de limpar/cancelar

                // Recarrega a tabela de itens de produção
                recarregarItensProducao(loteId);
                carregarItensProducaoParaSelecao(loteId); // Atualiza o dropdown da aba 3
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    });

    $('#btn-adicionar-item-embalagem').on('click', function () {
        const loteId = loteIdAtual; // Usamos a variável global
        const itemId = $('#item_emb_id_novo').val();
        const isEditing = !!itemId;

        const action = isEditing ? 'atualizarItemEmbalagemNovo' : 'adicionarItemEmbalagemNovo';
        const formData = new FormData($('#form-lote-novo-embalagem')[0]);

        if (!isEditing) {
            formData.append('item_emb_lote_id', loteId);
        }

        formData.append('csrf_token', csrfToken);

        $.ajax({
            url: `ajax_router.php?action=${action}`,
            type: 'POST',
            data: formData,
            processData: false, contentType: false, dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                notificacaoSucesso('Sucesso!', 'Item de embalagem adicionado.');

                // Limpa o formulário de embalagem para a próxima operação.
                // O .reset() limpa o campo de quantidade.
                $('#form-lote-novo-embalagem')[0].reset();

                // Limpa o dropdown de produto primário, o que automaticamente
                // irá limpar e desabilitar o dropdown de produto secundário por causa do evento 'change' que já criámos.
                $('#item_emb_prod_prim_id_novo').val(null).trigger('change');

                // Remove o botão de cancelar se ele existir (do modo de edição)
                $('#btn-cancelar-edicao-embalagem').remove();

                $('#form-lote-novo-embalagem').removeData('consumo-original');
                $('#btn-cancelar-edicao-embalagem').trigger('click'); // Dispara o cancelamento para limpar o form

                // ATUALIZAR TUDO:
                recarregarItensEmbalagem(loteId); // Atualiza a nova tabela de embalagens
                recarregarItensProducao(loteId);   // Atualiza a tabela da Aba 2, pois o saldo mudou!
                carregarItensProducaoParaSelecao(loteId); // Recarrega o dropdown com o saldo atualizado
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    });

    // Evento para o botão "Salvar Cabeçalho"
    $('#btn-salvar-lote-novo-header').on('click', function () {
        // 1. Executa a validação primeiro
        const erros = validarCabecalhoLote();

        // 2. Verifica se a lista de erros não está vazia
        if (erros.length > 0) {
            // Monta a mensagem de erro com os itens da lista
            const mensagem = 'Por favor, corrija os seguintes erros:\n- ' + erros.join('\n- ');
            notificacaoErro('Campos Obrigatórios', mensagem);
            return; // Impede o envio do formulário via AJAX
        }

        // 3. Se não houver erros, limpa a mensagem e continua com o AJAX
        $('#mensagem-lote-header').html('');

        const formData = new FormData($formHeader[0]);
        formData.append('csrf_token', csrfToken);
        $.ajax({
            url: 'ajax_router.php?action=salvarLoteNovoHeader',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                notificacaoSucesso('Sucesso!', 'Cabeçalho do lote salvo com sucesso.');
                tabelaLotesNovo.ajax.reload(null, false);

                loteIdAtual = response.novo_lote_id; // Guarda o ID do lote
                $('#lote_id_novo').val(loteIdAtual);

                // Garante que a aba de detalhes receba o ID recém-criado
                $('#item_receb_lote_id').val(loteIdAtual);

                const pageType = $('body').data('page-type');
                if (pageType === 'lotes_recebimento') {
                    $('#aba-detalhes-recebimento-tab').removeClass('disabled');
                    new bootstrap.Tab($('#aba-detalhes-recebimento-tab')[0]).show();
                } else {
                    $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').removeClass('disabled');
                    new bootstrap.Tab($('#aba-producao-novo-tab')[0]).show();
                }

            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    });

    $('#btn-cancelar-edicao-producao').on('click', function () {
        resetarFormularioProducao();
    });

    $('#btn-confirmar-finalizacao').on('click', function () {
        const $modal = $('#modal-finalizar-lote');
        const loteId = $modal.data('lote-id');
        const itensParaFinalizar = [];

        // Percorre cada linha da tabela para coletar os dados
        $('#tabela-itens-para-finalizar tr').each(function () {
            const itemId = $(this).data('item-id');
            const quantidade = parseFloat($(this).find('.qtd-a-finalizar').val()) || 0;

            if (itemId && quantidade > 0) {
                itensParaFinalizar.push({
                    item_id: itemId,
                    quantidade: quantidade
                });
            }
        });

        if (itensParaFinalizar.length === 0) {
            notificacaoAlerta('Nenhum item selecionado', 'Por favor, informe a quantidade para pelo menos um item.');
            return;
        }

        // Mostra um spinner no botão para feedback
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> A Finalizar...');

        $.ajax({
            url: 'ajax_router.php?action=finalizarLoteParcialmenteNovo',
            type: 'POST',
            data: {
                lote_id: loteId,
                itens: JSON.stringify(itensParaFinalizar), // Enviamos o array como uma string JSON
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                bootstrap.Modal.getInstance($modal[0]).hide();
                notificacaoSucesso('Sucesso!', response.message);
                tabelaLotesNovo.ajax.reload(); // Recarrega a tabela principal
            } else {
                notificacaoErro('Erro!', response.message);
            }
        }).always(function () {
            // Restaura o botão ao estado original
            $('#btn-confirmar-finalizacao').prop('disabled', false).html('<i class="fas fa-check-circle me-1"></i>Confirmar Finalização');
        });
    });

    $formHeader.on('change keyup', 'input, select', function (event) {
        // Impede que o cálculo seja refeito quando o próprio campo de lote completo é alterado (se um dia for editável)
        if (event.target.id === 'lote_completo_calculado_novo') {
            return;
        }
        atualizarLoteCompletoNovo();
    });

    // Evento para o novo botão "Cancelar Edição"
    $modalLoteNovo.on('click', '#btn-cancelar-edicao-embalagem', function () {
        $('#form-lote-novo-embalagem')[0].reset();
        $('#item_emb_id_novo').val('');
        $('#item_emb_prod_prim_id_novo').val(null).trigger('change');
        $('#btn-adicionar-item-embalagem').html('<i class="fas fa-plus me-1"></i>Adicionar Item');
        $('#form-lote-novo-embalagem').removeData('consumo-original');
        $(this).remove(); // Remove o próprio botão de cancelar
    });

    $('#modalLoteNovo').on('hidden.bs.modal', function () {
        limparFormularioDetalhes();
        sairModoEdicao();
    });

    // Evento para o checkbox "Finalizar Tudo" no cabeçalho
    $modalFinalizarLote.on('click', '#check-finalizar-todos', function () {
        const isChecked = $(this).is(':checked');
        $('#tabela-itens-para-finalizar .qtd-a-finalizar').each(function () {
            if (isChecked) {
                $(this).val($(this).attr('max'));
            } else {
                $(this).val('');
            }
        });
    });

    // Evento para o botão "Máx" em cada linha
    $modalFinalizarLote.on('click', '.btn-finalizar-tudo-item', function () {
        const $input = $(this).closest('tr').find('.qtd-a-finalizar');
        $input.val($input.attr('max'));
    });

    $modalFinalizarLote.on('input', '.qtd-a-finalizar', function () {
        const valor = parseFloat($(this).val()) || 0;
        const max = parseFloat($(this).attr('max')) || 0;
        if (valor > max) {
            $(this).val(max);
            notificacaoAlerta('Atenção', 'A quantidade a finalizar não pode ser maior que a disponível.');
        }
    });

    $tabelaLotes.on('click', '.btn-finalizar-lote-novo', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');
        const $modal = $('#modal-finalizar-lote');
        const $tbody = $('#tabela-itens-para-finalizar');

        // Guarda o ID do lote no modal para uso posterior
        $modal.data('lote-id', loteId);
        $('#lote-nome-finalizacao').text(loteNome);
        $tbody.html('<tr><td colspan="4" class="text-center">A carregar itens disponíveis...</td></tr>');

        // Mostra o modal
        const modalFinalizar = new bootstrap.Modal($modal[0]);
        modalFinalizar.show();

        // Busca os itens que podem ser finalizados
        $.ajax({
            url: `ajax_router.php?action=getItensParaFinalizar&lote_id=${loteId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            $tbody.empty();
            if (response.success && response.data.length > 0) {
                response.data.forEach(item => {
                    const rowHtml = `
                    <tr data-item-id="${item.item_emb_id}">
                        <td class="align-middle">${item.prod_descricao}</td>
                        <td class="text-center align-middle">
                            <span class="fw-bold text-success">${parseFloat(item.quantidade_disponivel).toFixed(3)}</span>
                        </td>
                        <td class="td-input-finalizar align-middle">
                            <input type="number" class="form-control qtd-a-finalizar" 
                                   min="0" 
                                   max="${parseFloat(item.quantidade_disponivel).toFixed(3)}" 
                                   step="1" 
                                   placeholder="0">
                        </td>
                        <td class="text-center align-middle">
                            <button class="btn btn-outline-primary btn-sm btn-finalizar-tudo-item" title="Preencher com o máximo">Máx</button>
                        </td>
                    </tr>
                `;
                    $tbody.append(rowHtml);
                });
            } else {
                $tbody.html('<tr><td colspan="4" class="text-center text-muted">Nenhum item com saldo disponível para finalizar neste lote.</td></tr>');
                $('#btn-confirmar-finalizacao').prop('disabled', true); // Desabilita o botão se não há itens
            }
        });
    });

    // Ação para o botão "Editar" na tabela principal de lotes 
    $tabelaLotes.on('click', '.btn-editar-lote-novo', function () {
        const pageType = $('body').data('page-type'); // Captura onde estamos
        loteIdAtual = $(this).data('id');

        // Lógica de Exibição
        if (pageType === 'lotes_recebimento') {
            configurarAbasPorModulo('RECEBIMENTO');
        } else if (pageType === 'lotes_producao') {
            configurarAbasPorModulo('PRODUCAO');
        } else if (pageType === 'lotes_embalagem') {
            configurarAbasPorModulo('EMBALAGEM');
        }

        // Garante que os dropdowns estejam prontos antes de buscar os dados
        $.when(carregarFornecedores(), carregarClientes()).done(function () {
            buscarDadosLoteParaEdicao(loteIdAtual);
        });
    });

    // Evento para o botão "Cancelar Lote" no menu dropdown
    $tabelaLotes.on('click', '.btn-cancelar-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        confirmacaoAcao('Cancelar Lote?', `Tem a certeza de que deseja cancelar o lote "${loteNome}"? Se este lote já gerou estoque, ele será revertido. Esta ação não pode ser desfeita.`)
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=cancelarLoteNovo',
                        type: 'POST',
                        data: { lote_id: loteId, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            notificacaoSucesso('Sucesso!', response.message);
                            tabelaLotesNovo.ajax.reload();
                        } else {
                            notificacaoErro('Erro!', response.message);
                        }
                    });
                }
            });
    });

    // Evento para o botão "Reativar Lote" no menu dropdown
    $tabelaLotes.on('click', '.btn-reativar-lote-novo', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        // Swal com Input de Texto (Motivo)
        Swal.fire({
            title: 'Reativar Lote?',
            html: `Deseja reativar o lote "<b>${loteNome}</b>"?<br>Ele voltará para o status 'EM ANDAMENTO'.`,
            icon: 'question',
            input: 'textarea',
            inputLabel: 'Motivo da Reativação',
            inputPlaceholder: 'Explique por que está reativando este lote...',
            inputAttributes: {
                'aria-label': 'Motivo da reativação'
            },
            showCancelButton: true,
            confirmButtonText: 'Sim, Reativar!',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754', // Verde success
            cancelButtonColor: '#6c757d',
            inputValidator: (value) => {
                if (!value || value.trim().length < 5) {
                    return 'É obrigatório informar um motivo (mínimo 5 caracteres).';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const motivo = result.value; // Pega o texto digitado

                $.ajax({
                    url: 'ajax_router.php?action=reativarLoteNovo',
                    type: 'POST',
                    data: {
                        lote_id: loteId,
                        motivo: motivo,  // Envia o motivo
                        csrf_token: csrfToken
                    },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        notificacaoSucesso('Sucesso!', response.message);
                        tabelaLotesNovo.ajax.reload();
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                });
            }
        });
    });

    // Evento para o botão "Reabrir Lote" no menu dropdown
    $tabelaLotes.on('click', '.btn-reabrir-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        // Usamos o SweetAlert2 diretamente para pedir o motivo
        Swal.fire({
            title: 'Reabrir Lote?',
            text: `Tem a certeza que deseja reabrir o lote "${loteNome}"? TODO o estoque gerado por ele será estornado.`,
            icon: 'warning',
            input: 'textarea', // Pede um campo de texto
            inputLabel: 'Motivo da Reabertura',
            inputPlaceholder: 'Digite o motivo aqui...',
            inputAttributes: {
                'aria-label': 'Digite o motivo aqui'
            },
            showCancelButton: true,
            confirmButtonText: 'Sim, Reabrir!',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            inputValidator: (value) => {
                if (!value || value.trim().length < 5) {
                    return 'Por favor, insira um motivo com pelo menos 5 caracteres.'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=reabrirLoteNovo',
                    type: 'POST',
                    data: {
                        lote_id: loteId,
                        motivo: result.value, // Envia o motivo digitado
                        csrf_token: csrfToken
                    },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        notificacaoSucesso('Sucesso!', response.message);
                        tabelaLotesNovo.ajax.reload();
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                });
            }
        });
    });

    // Evento para o botão "Excluir Permanentemente" no menu dropdown
    $tabelaLotes.on('click', '.btn-excluir-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        Swal.fire({
            title: 'Excluir Permanentemente?',
            html: `Tem a certeza ABSOLUTA de que deseja excluir o lote "<b>${loteNome}</b>"?<br><strong class="text-danger">Esta ação é IRREVERSÍVEL e apagará todos os itens de produção e embalagem associados a ele.</strong>`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: 'Sim, Excluir!',
            confirmButtonColor: '#d33',
            cancelButtonText: 'Cancelar',
            cancelButtonColor: '#3085d6',
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirLoteNovo',
                    type: 'POST',
                    data: { lote_id: loteId, csrf_token: csrfToken },
                    dataType: 'json',
                    global: false
                }).done(function (response) {
                    if (response.success) {
                        notificacaoSucesso('Excluído!', response.message);
                        tabelaLotesNovo.ajax.reload();
                    } else {
                        notificacaoErro('Não permitido!', response.message);
                    }
                }).fail(function (jqXHR) {
                    // O .fail() será executado para erros 4xx e 5xx
                    let errorMessage = 'Ocorreu um erro de comunicação com o servidor.'; // Mensagem padrão

                    // Tenta ler a mensagem específica enviada pelo backend no corpo da resposta
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorMessage = jqXHR.responseJSON.message;
                    }

                    notificacaoErro('Não Permitido', errorMessage);
                });
            }
        });
    });

    // Evento Inativar Lote
    $tabelaLotes.on('click', '.btn-inativar-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        confirmacaoAcao('Inativar Lote?', `Deseja inativar o lote "${loteNome}"? Isso bloqueará lançamentos de produção e embalagem vinculados.`)
            .then((result) => {
                if (result.isConfirmed) {
                    $.post('ajax_router.php?action=cancelarLoteNovo', { lote_id: loteId, csrf_token: csrfToken }, function (res) {
                        if (res.success) {
                            notificacaoSucesso('Sucesso', 'Lote inativado com sucesso.');
                            tabelaLotesNovo.ajax.reload();
                        } else {
                            notificacaoErro('Erro', res.message);
                        }
                    }, 'json');
                }
            });
    });

    $tabelaItensProducao.on('click', '.btn-editar-item-producao', function () {
        const $btn = $(this);

        // Primeiro, reseta o formulário para garantir um estado limpo.
        resetarFormularioProducao();

        // 1. Pega os dados dos atributos data-* do botão
        const itemId = $btn.data('id');
        const produtoId = $btn.data('produto-id');
        const quantidade = $btn.data('quantidade');
        const validade = $btn.data('validade');

        // 2. Preenche o formulário com esses dados
        $('#item_prod_id_novo').val(itemId); // O campo hidden mais importante!
        $('#item_prod_produto_id_novo').val(produtoId).trigger('change'); // .trigger('change') atualiza o Select2 e a data de validade
        $('#item_prod_quantidade_novo').val(parseFloat(quantidade).toFixed(3));
        $('#item_prod_data_validade_novo').val(validade);

        // 3. Muda o texto do botão de Ação e foca no formulário
        $('#btn-adicionar-item-producao').html('<i class="fas fa-save me-1"></i>Atualizar Item');

        // Leva o utilizador para o topo do modal para ver o formulário preenchido
        $modalLoteNovo.animate({ scrollTop: 0 }, "slow");
    });

    // Evento para imprimir etiqueta de PRODUÇÃO
    $tabelaItensProducao.on('click', '.btn-imprimir-etiqueta-producao', function () {
        const loteItemId = $(this).data('id');
        const $btn = $(this);
        const originalIcon = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: 'ajax_router.php?action=imprimirEtiquetaLoteItem',
            type: 'POST',
            data: {
                itemId: loteItemId,
                itemType: 'producao',
                clienteId: $('#lote_cliente_id_novo').val(),
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                window.open(BASE_URL + '/' + response.pdfUrl, '_blank');
            } else {
                notificacaoErro('Erro ao gerar etiqueta', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de comunicação', 'Não foi possível contactar o servidor.');
        }).always(function () {
            $btn.prop('disabled', false).html(originalIcon);
        });
    });

    $tabelaItensEmbalagem.on('click', '.btn-editar-item-embalagem', function () {
        const $btn = $(this);
        const itemId = $btn.data('id');
        const primarioItemId = $btn.data('primario-item-id');
        const secundarioProdId = $btn.data('secundario-prod-id');
        const quantidade = $btn.data('quantidade');
        const consumoOriginal = $btn.data('consumo');

        const $selectPrimario = $('#item_emb_prod_prim_id_novo');
        const $selectSecundario = $('#item_emb_prod_sec_id_novo');

        // Apresenta um estado de "a carregar" nos dropdowns
        $selectPrimario.empty().append('<option value=""></option>').prop('disabled', true).trigger('change');
        $selectSecundario.empty().append('<option value=""></option>').prop('disabled', true).trigger('change');

        // Fazemos uma única chamada AJAX para obter todos os dados de produção do lote.
        $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteIdAtual, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (!response.success) {
                notificacaoErro('Erro!', 'Não foi possível carregar os dados do lote.');
                return;
            }

            const producaoItens = response.data.producao;

            // garante que os select2 estão inicializados
            initSelect2($selectPrimario, 'Selecione item primário');
            initSelect2($selectSecundario, 'Selecione item secundário');

            // --- PASSO A: POPULAR O DROPDOWN PRIMÁRIO ---
            $selectPrimario.empty().append('<option value=""></option>');
            producaoItens.forEach(item => {
                // A lógica crítica: incluir item se saldo > 0 OU se for o item que estamos a editar.
                if (parseFloat(item.item_prod_saldo) > 0 || item.item_prod_id == primarioItemId) {
                    const texto = `${item.prod_descricao} (Saldo: ${parseFloat(item.item_prod_saldo).toFixed(3)})`;
                    const option = new Option(texto, item.item_prod_id);
                    $selectPrimario.append(option);
                }
            });
            $selectPrimario.prop('disabled', false);

            // --- PASSO B: SELECIONAR O ITEM PRIMÁRIO CORRETO ---
            $selectPrimario.val(primarioItemId).trigger('change');

            // --- PASSO C: POPULAR E SELECIONAR O DROPDOWN SECUNDÁRIO ---
            const itemSelecionado = producaoItens.find(item => item.item_prod_id == primarioItemId);
            if (itemSelecionado) {
                const primarioProdutoId = itemSelecionado.item_prod_produto_id;
                $selectSecundario.empty().append('<option value=""></option>').prop('disabled', true);

                $.ajax({
                    url: `ajax_router.php?action=getSecundariosPorPrimario&primario_id=${primarioProdutoId}`,
                    type: 'GET',
                    dataType: 'json'
                }).done(function (secResponse) {
                    if (secResponse.success) {
                        $selectSecundario.empty().append('<option value=""></option>');
                        if (secResponse.data.length > 0) {
                            secResponse.data.forEach(produto => {
                                const texto = `${produto.prod_descricao} (Cód: ${produto.prod_codigo_interno || 'N/A'})`;
                                const option = new Option(texto, produto.prod_codigo);
                                $(option).data('unidades-primarias', produto.prod_unidades_primarias_calculado);
                                $selectSecundario.append(option);
                            });
                        }
                        $selectSecundario.val(secundarioProdId).trigger('change');
                        $selectSecundario.prop('disabled', false);
                    } else {
                        $selectSecundario.prop('disabled', true);
                    }
                });
            }

            // --- PASSO D: PREENCHER O RESTO DO FORMULÁRIO ---
            $('#form-lote-novo-embalagem').data('consumo-original', consumoOriginal);
            $('#item_emb_id_novo').val(itemId);
            $('#item_emb_qtd_sec_novo').val(parseInt(quantidade));

            $('#btn-adicionar-item-embalagem').html('<i class="fas fa-save me-1"></i>Atualizar Item');
            if ($('#btn-cancelar-edicao-embalagem').length === 0) {
                $('#btn-adicionar-item-embalagem').after(' <button type="button" class="btn btn-secondary" id="btn-cancelar-edicao-embalagem"><i class="fas fa-times me-2"></i>Cancelar</button>');
            }

        }).fail(function () {
            notificacaoErro('Erro de Comunicação!', 'Falha ao buscar dados do lote.');
            $selectPrimario.empty().append('<option value=""></option>').prop('disabled', true).trigger('change');
        });
    });

    $tabelaItensProducao.on('click', '.btn-excluir-item-producao', function () {
        const itemId = $(this).data('id');

        confirmacaoAcao('Excluir Item?', 'Só será possível excluir se o saldo não tiver sido consumido. Deseja continuar?')
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=excluirItemProducaoNovo',
                        type: 'POST',
                        data: { item_id: itemId, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            notificacaoSucesso('Sucesso!', response.message);
                            // Recarrega a tabela de produção e o dropdown da aba 3
                            recarregarItensProducao(loteIdAtual);
                            carregarItensProducaoParaSelecao(loteIdAtual);
                        } else {
                            notificacaoErro('Não permitido!', response.message);
                        }
                    });
                }
            });
    });

    $tabelaItensEmbalagem.on('click', '.btn-excluir-item-embalagem', function () {
        const itemId = $(this).data('id');

        confirmacaoAcao('Excluir Item?', 'Esta ação irá reverter o saldo consumido. Tem a certeza?')
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=excluirItemEmbalagemNovo',
                        type: 'POST',
                        data: { item_id: itemId, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            notificacaoSucesso('Sucesso!', response.message);
                            // Recarrega TUDO para garantir consistência
                            recarregarItensEmbalagem(loteIdAtual);
                            recarregarItensProducao(loteIdAtual);
                            carregarItensProducaoParaSelecao(loteIdAtual);
                        } else {
                            notificacaoErro('Erro!', response.message);
                        }
                    });
                }
            });
    });

    // Evento para imprimir etiqueta de EMBALAGEM
    $tabelaItensEmbalagem.on('click', '.btn-imprimir-etiqueta-embalagem', function () {
        const loteItemId = $(this).data('id');
        const $btn = $(this);
        const originalIcon = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: 'ajax_router.php?action=imprimirEtiquetaLoteItem',
            type: 'POST',
            data: {
                itemId: loteItemId,
                itemType: 'embalagem',
                clienteId: $('#lote_cliente_id_novo').val(),
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                window.open(BASE_URL + '/' + response.pdfUrl, '_blank');
            } else {
                notificacaoErro('Erro ao gerar etiqueta', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de comunicação', 'Não foi possível contactar o servidor.');
        }).always(function () {
            $btn.prop('disabled', false).html(originalIcon);
        });
    });

    $('#form-lote-novo-embalagem').on('change keyup', 'select, input', function () {
        calcularConsumoEmbalagem();
    });

    // Evento que é acionado QUANDO A ABA DE PRODUÇÃO É MOSTRADA
    $('#aba-producao-novo-tab').on('shown.bs.tab', function () {
        // Inicializa o Select2 para o dropdown de produtos (se ainda não inicializado)
        initSelect2($('#item_prod_produto_id_novo'), 'Selecione um produto');
        // Carrega os produtos primários
        carregarProdutosPrimarios();
    });

    // Evento que é acionado QUANDO A ABA DE EMBALAGEM É MOSTRADA
    $('#aba-embalagem-novo-tab').on('shown.bs.tab', function () {
        // Inicializa os Select2 para os novos dropdowns
        initSelect2($('#item_emb_prod_prim_id_novo'), 'Selecione um produto primário');
        initSelect2($('#item_emb_prod_sec_id_novo'), 'Selecione um produto secundário');
        // Carrega os dados necessários
        carregarItensProducaoParaSelecao(loteIdAtual);

        // Define o estado inicial do dropdown de secundários
        $('#item_emb_prod_sec_id_novo').prop('disabled', true).empty().append('<option value=""></option>').trigger('change');

        calcularConsumoEmbalagem();
    });

    $('#item_prod_produto_id_novo').on('change', function () {
        const optionSelecionada = $(this).find(':selected');
        const mesesValidade = optionSelecionada.data('validade-meses');
        const dataFabricacaoStr = $('#lote_data_fabricacao_novo').val();

        if (mesesValidade && dataFabricacaoStr) {
            const dataFabricacao = new Date(dataFabricacaoStr + 'T00:00:00');
            const dataValidadeCalculada = calcularValidadeArredondandoParaCima(dataFabricacao, parseInt(mesesValidade));
            $('#item_prod_data_validade_novo').val(dataValidadeCalculada);
        } else {
            $('#item_prod_data_validade_novo').val('');
        }
    });

    // Evento de mudança no dropdown de produto primário (na Aba 3)
    $('#item_emb_prod_prim_id_novo').on('change', function () {
        const primarioProdutoId = $(this).val(); // Agora isso JÁ É o ID do produto
        const $selectSecundario = $('#item_emb_prod_sec_id_novo');

        $selectSecundario.prop('disabled', true).empty().append('<option value=""></option>').trigger('change');

        if (!primarioProdutoId) return;

        // Chama direto a busca de secundários usando o ID do produto que pegamos do value
        $.ajax({
            url: `ajax_router.php?action=getSecundariosPorPrimario&primario_id=${primarioProdutoId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $selectSecundario.prop('disabled', false).empty().append('<option value=""></option>');

                if (response.data.length === 0) {
                    $selectSecundario.empty().append('<option value="">Nenhum produto associado</option>').prop('disabled', true);
                } else {
                    response.data.forEach(produto => {
                        const texto = `${produto.prod_descricao} (Cód: ${produto.prod_codigo_interno || 'N/A'})`;
                        const option = new Option(texto, produto.prod_codigo);
                        $(option).data('unidades-primarias', produto.prod_unidades_primarias_calculado);
                        $selectSecundario.append(option);
                    });
                }
                $selectSecundario.trigger('change');
            }
        });
    });

    // Evento do checkbox para liberar a edição da validade
    $('#liberar_edicao_validade_novo').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('#item_prod_data_validade_novo').prop('readonly', !isChecked);
    });

    // 2. Inicializar Select2 na aba de detalhes
    $('#aba-detalhes-recebimento-tab').on('shown.bs.tab', function () {
        // Carrega produtos
        carregarProdutosPrimarios().then(() => {
            // A função carregarProdutosPrimarios popula #item_prod_produto_id_novo. 
            $.get('ajax_router.php?action=getProdutoOptions', { tipo_embalagem: 'PRIMARIA' }, function (res) {
                if (res.success) {
                    const $sel = $('#item_receb_produto_id');

                    // Verifica se já tem valor (caso seja edição) para não limpar à toa
                    const valorAtual = $sel.val();

                    if (!valorAtual) {
                        $sel.empty().append('<option value=""></option>');
                        res.data.forEach(p => {
                            $sel.append(new Option(p.prod_descricao, p.prod_codigo));
                        });
                    }
                    initSelect2($sel, 'Selecione produto (recebimento)');
                    $sel.trigger('change');
                }
            }, 'json');
        });

        // =====================================================
        // SELECT2 - LOTE ORIGEM (AJAX REAL)
        // =====================================================
        $('#item_receb_lote_origem_id').select2({
            placeholder: 'Lote origem',
            allowClear: true,
            width: '100%',
            dropdownParent: $modalLoteNovo,
            theme: 'bootstrap-5',

            // minimumInputLength: 1,
            //delay: 300,

            ajax: {
                url: 'ajax_router.php?action=getLotesFinalizadosOptions',
                dataType: 'json',

                data: function (params) {
                    return {
                        term: params.term || ''
                    };
                },

                processResults: function (data) {
                    return {
                        results: data.results || []
                    };
                }
            }
        });
        // Força a verificação das regras assim que a aba abre
        atualizarTipoEntradaMP();
    });

    // CÁLCULO AUTOMÁTICO: PESO MÉDIO FAZENDA
    $('#item_receb_peso_nota_fiscal, #item_receb_total_caixas').on('input', function () {
        // Usa brToFloat para entender o valor com ponto e vírgula
        const pesoNF = brToFloat($('#item_receb_peso_nota_fiscal').val());
        const totalCaixas = parseInt($('#item_receb_total_caixas').val()) || 0;

        if (totalCaixas > 0 && pesoNF > 0) {
            const media = pesoNF / totalCaixas;
            // Exibe com vírgula e 2 casas decimais
            $('#calc_peso_medio_fazenda').val(floatToBr(media, 2));
        } else {
            $('#calc_peso_medio_fazenda').val('');
        }
    });

    // --- RECARREGAR ITENS COM FORMATAÇÃO BR E BOTÃO EDITAR ---
    function recarregarItensRecebimento(loteId) {
        const $tbody = $('#tabela-itens-recebimento');
        $tbody.html('<tr><td colspan="7" class="text-center">Carregando.</td></tr>');

        $.ajax({
            url: `ajax_router.php?action=getItensRecebimento&lote_id=${loteId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            $tbody.empty();
            if (response.success && response.data.length > 0) {
                response.data.forEach(item => {
                    let origemHtml = item.origem_formatada !== '-' ? `<span class="badge bg-info text-dark">${item.origem_formatada}</span>` : '-';

                    $tbody.append(`
                        <tr>
                            <td>${item.prod_descricao}</td>
                            <td class="text-center">${origemHtml}</td>
                            <td>${item.item_receb_nota_fiscal || ''}</td>
                            <td class="text-end">${formatarBR(item.item_receb_peso_nota_fiscal, 3)}</td>
                            <td class="text-center">${item.item_receb_total_caixas || ''}</td>
                            <td class="text-end">${formatarBR(item.item_receb_peso_medio_ind, 3)}</td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm btn-editar-item-recebimento me-1" data-id="${item.item_receb_id}">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-excluir-item-recebimento" data-id="${item.item_receb_id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
            } else {
                $tbody.html('<tr><td colspan="7" class="text-center text-muted">Nenhum detalhe lançado.</td></tr>');
            }
        });
    }

    // --- BOTÃO EDITAR ITEM (DETALHES) ---
    $(document).on('click', '.btn-editar-item-recebimento', function () {

        const id = $(this).data('id');

        $.get('ajax_router.php?action=getItemRecebimento', { item_id: id }, function (res) {

            if (!res.success) {
                notificacaoErro('Erro', res.message);
                return;
            }

            const data = res.data;

            // Entra em modo edição
            modoEdicao = true;
            dadosOriginaisEdicao = { ...data };

            // IDs
            $('#item_receb_id').val(data.item_receb_id);
            $('#item_receb_lote_id').val(data.item_receb_lote_id);

            // Radio button
            if (data.item_receb_lote_origem_id) {
                $('input[name="tipo_entrada_mp"][value="LOTE_ORIGEM"]').prop('checked', true);
            } else {
                $('input[name="tipo_entrada_mp"][value="MATERIA_PRIMA"]').prop('checked', true);
            }

            // Aplica regras de UI
            aplicarModoEntrada();

            // Selects
            $('#item_receb_produto_id')
                .val(data.item_receb_produto_id)
                .trigger('change');

            if (data.item_receb_lote_origem_id) {
                const option = new Option(
                    data.lote_origem_label || 'Lote selecionado',
                    data.item_receb_lote_origem_id,
                    true,
                    true
                );
                $('#item_receb_lote_origem_id')
                    .append(option)
                    .trigger('change');
            } else {
                $('#item_receb_lote_origem_id').val(null).trigger('change');
            }

            // Inputs
            $('#item_receb_nota_fiscal').val(data.item_receb_nota_fiscal);
            $('#item_receb_peso_nota_fiscal').val(floatToBr(data.item_receb_peso_nota_fiscal, 3));
            $('#item_receb_total_caixas').val(data.item_receb_total_caixas);
            $('#item_receb_peso_medio_ind').val(floatToBr(data.item_receb_peso_medio_ind, 2));
            $('input[name="item_receb_gram_faz"]').val(floatToBr(data.item_receb_gram_faz, 2));
            $('input[name="item_receb_gram_lab"]').val(floatToBr(data.item_receb_gram_lab, 2));
            $('#item_receb_peso_nota_fiscal').trigger('input');

            // Botão principal
            $('#btn-adicionar-item-recebimento')
                .html('<i class="fas fa-save me-2"></i> Atualizar Item')
                .removeClass('btn-success')
                .addClass('btn-warning');

            // exibe botão cancelar
            $('#btn-cancelar-edicao').removeClass('d-none');

        }, 'json');
    });

    // --- BOTÃO SALVAR (ADICIONAR / ATUALIZAR) ---
    $('#btn-adicionar-item-recebimento').on('click', function () {

        const id = $('#item_receb_id').val();
        const action = id ? 'atualizarItemRecebimento' : 'adicionarItemRecebimento';

        // Cria o FormData baseado no form
        const formData = new FormData($('#form-recebimento-detalhe')[0]);
        formData.append('csrf_token', csrfToken);

        // CONVERTE OS VALORES FORMATADOS DE VOLTA PARA PADRÃO SQL (PONTO)
        // Precisamos pegar os valores visuais, converter e sobrescrever no FormData
        formData.set('item_receb_peso_nota_fiscal', brToFloat($('#item_receb_peso_nota_fiscal').val()));
        formData.set('item_receb_peso_medio_ind', brToFloat($('#item_receb_peso_medio_ind').val()));
        formData.set('item_receb_gram_faz', brToFloat($('[name="item_receb_gram_faz"]').val()));
        formData.set('item_receb_gram_lab', brToFloat($('[name="item_receb_gram_lab"]').val()));

        // AJAX com FormData (processData: false)
        $.ajax({
            url: `ajax_router.php?action=${action}`,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res.success) {
                notificacaoErro('Erro', res.message);
                return;
            }
            notificacaoSucesso('Sucesso', res.message);

            // Limpa o formulários
            limparFormularioDetalhes();

            // Sai do modo edição (se estiver)
            sairModoEdicao();

            // Reaplica regras do rádio selecionado

            aplicarModoEntrada();

            // Mantém o lote atual
            $('#item_receb_lote_id').val(loteIdAtual);

            // Recarrega tabela
            recarregarItensRecebimento(loteIdAtual);
        });
    });

    $('#btn-cancelar-edicao').on('click', function () {
        limparFormularioDetalhes();
        sairModoEdicao();
    });

    $('#item_receb_lote_origem_id').on('change', function () {
        const loteId = $(this).val();
        if (!loteId) return;

        // Feedback visual de carregamento
        const $btnSalvar = $('#btn-adicionar-item-recebimento');
        const textoOriginal = $btnSalvar.html();
        $btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Carregando...');

        $.getJSON('ajax_router.php?action=getDadosLoteReprocesso', { lote_id: loteId })
            .done(function (resp) {
                if (resp.success) {
                    const d = resp.dados;

                    // Preenchimento dos campos
                    // Nota: As chaves aqui (ex: d.lote_nota_fiscal) devem bater com o SQL do Repository
                    $('[name="item_receb_nota_fiscal"]').val(d.lote_nota_fiscal);
                    $('[name="item_receb_peso_nota_fiscal"]').val(d.lote_peso_nota_fiscal);
                    $('#item_receb_total_caixas').val(d.lote_total_caixas);

                    // Campos calculados/laboratório
                    $('#calc_peso_medio_fazenda').val(d.lote_peso_medio_fazenda);
                    $('#item_receb_peso_medio_ind').val(d.lote_peso_medio_industria);
                    $('[name="item_receb_gram_faz"]').val(d.lote_gramatura_fazenda);
                    $('[name="item_receb_gram_lab"]').val(d.lote_gramatura_lab);
                } else {
                    notificacaoErro('Erro', resp.message || 'Dados não encontrados');
                }
            })
            .fail(function (jqXHR) {
                console.error("Erro detalhado:", jqXHR.responseText);
                notificacaoErro('Erro', 'Falha ao buscar dados do lote. Verifique o console.');
            })
            .always(function () {
                $btnSalvar.prop('disabled', false).html(textoOriginal);
            });
    });

});
