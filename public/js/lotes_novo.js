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
    let loteBloqueado = false;

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

    // --- INICIALIZAÇÃO DO MODAL DE IMPORTAÇÃO ---
    // Carrega os selects apenas quando o modal for aberto para não pesar a página inicial
    $('#modal-importar-legado').on('shown.bs.modal', function () {

        // 1. Select de Clientes (Busca dinâmica)
        $('.select2-clientes').select2({
            dropdownParent: $('#modal-importar-legado'), // Importante para o search funcionar dentro do modal
            ajax: {
                url: 'ajax_router.php?action=getClienteOptions',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { term: params.term }; // O que o usuário digita
                },
                processResults: function (data) {
                    // O retorno deve ser { results: [ {id:1, text:'Nome'}, ... ] } ou { data: ... }
                    // Ajuste conforme seu retorno padrão. Se for 'data', mapeie para 'results'.
                    return {
                        results: data.data || data
                    };
                }
            },
            placeholder: 'Busque o cliente...',
            minimumInputLength: 0
        });

        // 2. Select de Produtos (Busca dinâmica)
        $('.select2-produtos').select2({
            dropdownParent: $('#modal-importar-legado'),
            ajax: {
                url: 'ajax_router.php?action=getProdutosSecundariosOptions',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { term: params.term };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                }
            },
            placeholder: 'Busque o produto acabado...',
            minimumInputLength: 0
        });

        // 3. Select de Endereços (Busca dinâmica)
        $('.select2-enderecos').select2({
            dropdownParent: $('#modal-importar-legado'),
            ajax: {
                url: 'ajax_router.php?action=buscarEnderecosSelect',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { term: params.term };
                },
                processResults: function (data) {
                    return {
                        results: data.results || []
                    };
                }
            },
            placeholder: 'Busque o endereço (Câmara/Rua)...',
            minimumInputLength: 0,
            language: {
                noResults: function () {
                    return "Nenhum endereço encontrado";
                },
                searching: function () {
                    return "Buscando...";
                }
            }
        });
    });

    $('.select2-lotes-finalizados').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#modal-lote-novo'), // Confirme se o ID do modal é este
        placeholder: 'Busque pelo número do lote...',
        allowClear: true,
        ajax: {
            url: 'ajax_router.php?action=getLotesFinalizadosOptions', // Rota Nova
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    // MUDANÇA 1: O PHP espera $_GET['term'], então enviamos 'term'
                    term: params.term
                };
            },
            processResults: function (data) {
                // MUDANÇA 2: Simplificação Total!
                // Como o PHP já retorna { "results": [ {id: 1, text: "Lote A"}, ... ] }
                // nós passamos o objeto 'data' direto. O Select2 entende nativamente.
                return data;
            },
            cache: true
        }
    });

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
            // 0. Lote (Ordenável)
            {
                "data": "lote_completo_calculado",
                "className": "text-center align-middle",
            },
            // 1. Fornecedor (Ordenável)
            {
                "data": "cliente_razao_social",
                "className": "align-middle",
            },
            /*{
                "data": "fornecedor_razao_social",
                "className": "align-middle",
            },*/
            // 4. Gram Fazenda (Ordenável como texto)
            {
                "data": "gramaturas_fazenda",
                "className": "text-center align-middle",
                "render": function (data) {
                    if (!data) return '-';
                    // Separa os valores (caso tenha múltiplos), formata cada um e junta de novo
                    return data.split(' / ').map(v => {
                        let num = parseFloat(v);
                        if (isNaN(num)) return v;

                        // Lógica: Se for inteiro (ex: 19), mostra normal. Se tiver decimal, fixa em 2 casas.
                        let formatado = Number.isInteger(num)
                            ? num.toString()
                            : num.toFixed(2).replace('.', ',');

                        return formatado + 'g';
                    }).join(' / ');
                }
            },
            // 5. Gram Lab (Ordenável como texto)
            {
                "data": "gramaturas_laboratorio",
                "className": "text-center align-middle",
                "render": function (data) {
                    if (!data) return '-';
                    return data.split(' / ').map(v => {
                        let num = parseFloat(v);
                        if (isNaN(num)) return v;

                        let formatado = Number.isInteger(num)
                            ? num.toString()
                            : num.toFixed(2).replace('.', ',');

                        return formatado + 'g';
                    }).join(' / ');
                }
            },
            // 3. Peso (Ordenável)    
            {
                "data": "peso_total_nota",
                "className": "text-center align-middle",
                "title": "Peso Total (NF)",
                "render": function (data) {
                    // Usa a função formatarBR que já existe no seu JS
                    return data ? formatarBR(data) + ' kg' : '-';
                }
            },
            // 2. Data (Ordenável)
            {
                "data": "lote_data_fabricacao",
                "className": "col-centralizavel align-middle",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            // 6. Status (Ordenável)
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
            /*{
                "data": "lote_data_cadastro",
                "className": "col-centralizavel align-middle",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data);
                    return date.toLocaleString('pt-BR');
                }
            },*/
            // 7. Ações (NÃO Ordenável) 
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

                    // --- AÇÕES GERAIS (Botão PDF) ---

                    // Botão de Relatório APENAS em Embalagem (para qualquer status que não seja Cancelado)
                    if (pageType === 'lotes_embalagem' && status !== 'CANCELADO') {
                        if (menuItens !== '') { menuItens += `<li><hr class="dropdown-divider"></li>`; }

                        // Adicionamos a classe 'btn-imprimir-relatorio-lista' para capturar o clique depois
                        menuItens += `<li><a class="dropdown-item text-dark btn-imprimir-relatorio-lista d-inline-flex align-items-center" href="#" data-id="${loteId}"><i class="fas fa-file-pdf me-2"></i> Relatório Final</a></li>`;
                    }

                    // Botão Excluir (Apenas Produção)
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
        //const ciclo = $('#lote_ciclo_novo').val() || 'C';
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
        //const loteCompletoCalculado = `${numero}/${ano}-${ciclo}/${viveiro} ${codCliente}`;
        const loteCompletoCalculado = `${numero}/${ano}-${viveiro} ${codCliente}`;
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
    function buscarDadosLoteParaEdicao(loteId) {
        desbloquearFormularioRecebimento();
        loteBloqueado = false;
        $('#lote_status').val('');

        const $btnSalvar = $('#btn-salvar-lote-novo-header');
        const textoOriginal = $btnSalvar.html();
        $btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Carregando...');

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
                $('#lote_so2_novo').val(header.lote_so2);
                $('#lote_observacao_novo').val(header.lote_observacao);
                $('#lote_completo_calculado_novo').val(header.lote_completo_calculado);

                const $btnSalvar = $('#btn-salvar-lote-novo-header'); // Garante a seleção
                $btnSalvar.html('<i class="fas fa-save me-1"></i> Salvar Alterações');
                $btnSalvar.prop('disabled', false); // Reativa o botão para salvar alterações

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
                $('#lote_status').val(status);

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
        // Seleciona todos os formulários e botões principais de ação
        const $forms = $modalLoteNovo.find('form');
        const $botoesPrincipais = $modalLoteNovo.find('#btn-salvar-lote-novo-header, #btn-adicionar-item-producao, #btn-adicionar-item-recebimento, #btn-adicionar-item-embalagem, #btn-cancelar-edicao-producao, #btn-cancelar-edicao-embalagem, #btn-cancelar-edicao');

        // 1. Bloqueia/Desbloqueia inputs e selects (O dropdown ficará cinza aqui)
        $forms.find('input, select, textarea').prop('disabled', isReadOnly);

        // 2. Controla a visibilidade dos botões
        if (isReadOnly) {
            // Esconde botões de criar/salvar principais
            $botoesPrincipais.hide();

            // --- AJUSTE FINO NAS TABELAS ---
            // Esconde apenas os botões de edição/exclusão, mantendo a coluna e a etiqueta visíveis

            // Aba Detalhes
            $('.btn-editar-item-recebimento, .btn-excluir-item-recebimento').hide();

            // Aba Produção (Mantém .btn-imprimir-etiqueta-producao visível)
            $('.btn-editar-item-producao, .btn-excluir-item-producao').hide();
            $('.btn-imprimir-etiqueta-producao').show();

            // Aba Embalagem (Mantém .btn-imprimir-etiqueta-embalagem visível)
            $('.btn-editar-item-embalagem, .btn-excluir-item-embalagem').hide();
            $('.btn-imprimir-etiqueta-embalagem').show();

            // Ajusta botão do cabeçalho
            $('.btn-editar-lote-novo[data-id="' + loteIdAtual + '"]').html('<i class="fas fa-search"></i> Visualizar');
            $('#modal-lote-novo-label').text('Visualizar Lote: ' + $('#lote_completo_calculado_novo').val());

        } else {
            // Modo Edição: Mostra tudo
            $botoesPrincipais.show();

            // Reexibe os botões das tabelas
            $('.btn-editar-item-recebimento, .btn-excluir-item-recebimento').show();
            $('.btn-editar-item-producao, .btn-excluir-item-producao').show();
            $('.btn-editar-item-embalagem, .btn-excluir-item-embalagem').show();
            $('.btn-imprimir-etiqueta-producao, .btn-imprimir-etiqueta-embalagem').show();
        }
    }

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
                    let origemHtml = '-';

                    if (item.origem_formatada && item.origem_formatada !== '-') {
                        origemHtml = `<span class="badge bg-info text-dark">${item.origem_formatada}</span>`;
                    }

                    $tbody.append(`
                        <tr>
                            <td class="align-middle">${item.prod_descricao}</td>
                            <td class="text-center align-middle">${origemHtml}</td>
                            <td class="text-center align-middle">${item.item_receb_nota_fiscal || ''}</td>
                            
                            <td class="text-center align-middle">${floatToBr(item.item_receb_peso_nota_fiscal, 3)}</td>
                            <td class="text-center align-middle">${item.item_receb_total_caixas || ''}</td>
                            <td class="text-center align-middle">${floatToBr(item.item_receb_peso_medio_ind, 2)}</td>
                            
                            <td class="text-center align-middle">
                                <button class="btn btn-warning btn-sm btn-editar-item-recebimento me-1" data-id="${item.item_receb_id}" title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-excluir-item-recebimento" data-id="${item.item_receb_id}" title="Excluir">
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

        // pegamos o valor RAW (bruto) que salvamos no data-attribute 'saldo-total'
        const saldoAtualDropdown = parseFloat($selectPrimario.find('option:selected').data('saldo-total')) || 0;

        // Se estivermos a editar, o saldo disponível para o cálculo é o saldo atual MAIS o que o item já tinha consumido.
        // Precisamos tratar erros de ponto flutuante com toFixed e parseFloat novamente
        let saldoParaCalculo = isEditing ? (saldoAtualDropdown + consumoOriginal) : saldoAtualDropdown;

        // Pequena segurança para arredondamento de float (ex: 1502.000000001)
        saldoParaCalculo = parseFloat(saldoParaCalculo.toFixed(3));

        const unidadesPorEmbalagem = parseFloat($selectSecundario.find('option:selected').data('unidades-primarias')) || 0;
        const quantidade = parseInt($qtdInput.val()) || 0;

        if (saldoParaCalculo === 0 || unidadesPorEmbalagem === 0 || quantidade === 0) {
            $feedback.text('Preencha os campos para calcular o consumo.').removeClass('text-danger fw-bold').addClass('text-muted');
            $btnAdicionar.prop('disabled', true);
            return;
        }

        const consumoCalculado = quantidade * unidadesPorEmbalagem;
        const saldoRestante = saldoParaCalculo - consumoCalculado;

        // Comparação com margem de segurança para float
        if (consumoCalculado > (saldoParaCalculo + 0.001)) { // Aceita diferença ínfima de milésimos
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

    function atualizarTipoEntradaMP() {

        const tipo = $('input[name="tipo_entrada_mp"]:checked').val();
        const $selectMateria = $('#item_receb_produto_id');
        const $selectLote = $('#item_receb_lote_origem_id');

        // Usamos setTimeout para garantir que o DOM terminou de renderizar a aba/modal
        setTimeout(function () {
            if (tipo === 'MATERIA_PRIMA') {
                // ==========================================
                // MODO: MATÉRIA PRIMA
                // ==========================================

                // 1. Habilita o Produto
                $selectMateria.prop('disabled', false);

                // 2. Bloqueia o Lote de Origem
                $selectLote.val(null).trigger('change.select2'); // Limpa valor
                $selectLote.prop('disabled', true); // Bloqueia propriedade (Lógica)

                // Força visualmente o bloqueio (caso o Select2 esteja teimoso)
                $selectLote.parent().find('.select2-container').addClass('select2-container--disabled');

                aplicarModoMateriaPrima();

            } else {
                // ==========================================
                // MODO: REPROCESSO (LOTE ORIGEM)
                // ==========================================

                // 1. Habilita o Lote
                $selectLote.prop('disabled', false);
                $selectLote.parent().find('.select2-container').removeClass('select2-container--disabled');

                // 2. Bloqueia o Produto
                $selectMateria.val(null).trigger('change.select2');
                $selectMateria.prop('disabled', true);

                aplicarModoReprocesso();
            }
        }, 50); // Pequeno atraso de 50ms para garantir a renderização
    }

    function aplicarModoReprocesso() {

        if (loteBloqueado) {
            return;
        }

        // 1. MUDA O TEXTO DA LABEL (Busca pelo parente/vizinho)
        let $inputPeso = $('#item_receb_peso_nota_fiscal');
        let $label = $inputPeso.parent().find('label'); // Procura a label dentro da col-md-3

        $label.text('Peso Reprocesso (kg)');

        // 2. READONLY (Campos numéricos)
        $('#item_receb_total_caixas').prop('readonly', true).addClass('bg-light');
        $('#item_receb_peso_medio_ind').prop('readonly', true).addClass('bg-light');

        // 3. PRODUTO (Matéria Prima)
        let $prodSelect = $('#item_receb_produto_id');
        $prodSelect.val(null).trigger('change.select2'); // Limpa
        $prodSelect.prop('disabled', true); // Bloqueia
    }

    function aplicarModoMateriaPrima() {

        if (loteBloqueado) {
            return;
        }

        // 1. MUDA O TEXTO DA LABEL
        let $inputPeso = $('#item_receb_peso_nota_fiscal');
        let $label = $inputPeso.parent().find('label');

        $label.text('Peso NF (kg)');

        // 2. READONLY (Libera campos)
        $('#item_receb_total_caixas').prop('readonly', false).removeClass('bg-light');
        $('#item_receb_peso_medio_ind').prop('readonly', false).removeClass('bg-light');

        // 3. PRODUTO
        $('#item_receb_produto_id').prop('disabled', false);
    }

    function aplicarModoEntrada() {
        atualizarTipoEntradaMP();  // Chama o sub-método com logs

        const tipo = $('input[name="tipo_entrada_mp"]:checked').val();
        if (tipo === 'LOTE_ORIGEM') {
            aplicarModoReprocesso();
        } else {
            aplicarModoMateriaPrima();
        }
    }

    function bloquearFormularioRecebimento() {
        console.log('Bloqueando formulário de recebimento (Modo Leitura)');

        loteBloqueado = true; // Trava global

        // 1. Bloqueia Radios
        $('input[name="tipo_entrada_mp"]').prop('disabled', true);

        // 2. Bloqueia Selects (CORREÇÃO: Use uma string única com vírgula)
        $('#item_receb_produto_id, #item_receb_lote_origem_id')
            .prop('disabled', true)
            .trigger('change.select2'); // Atualiza visual do Select2

        // 3. Força visual cinza no Select2
        $('.select2-container').addClass('select2-container--disabled');

        // 4. Bloqueia Inputs (CORREÇÃO: Vírgulas obrigatórias entre seletores)
        $('#item_receb_nota_fiscal, #item_receb_peso_nota_fiscal, #item_receb_total_caixas, #item_receb_peso_medio_ind, [name="item_receb_gram_faz"], [name="item_receb_gram_lab"]')
            .prop('readonly', true)
            .addClass('bg-light'); // Reforço visual

        // 5. Bloqueia Botão de Ação
        $('#btn-adicionar-item-recebimento')
            .prop('disabled', true)
            .removeClass('btn-success btn-warning')
            .addClass('btn-secondary')
            .html('<i class="fas fa-lock me-2"></i> Visualizando');

        // Adiciona classe de controle ao form
        $('#form-recebimento-detalhe').addClass('modo-leitura');
    }

    function desbloquearFormularioRecebimento() {
        // console.log('Desbloqueando formulário de recebimento (Modo Edição)');

        loteBloqueado = false; // Destrava flag global

        // 1. Libera Radios
        $('input[name="tipo_entrada_mp"]').prop('disabled', false);

        // 2. Libera Selects (Visual e Lógico)
        $('#item_receb_produto_id, #item_receb_lote_origem_id')
            .prop('disabled', false);

        $('.select2-container').removeClass('select2-container--disabled');

        // 3. Libera Inputs e remove fundo cinza
        $('#item_receb_nota_fiscal, #item_receb_peso_nota_fiscal, #item_receb_total_caixas, #item_receb_peso_medio_ind, [name="item_receb_gram_faz"], [name="item_receb_gram_lab"]')
            .prop('readonly', false)
            .removeClass('bg-light');

        // 4. Restaura Botão de Ação
        $('#btn-adicionar-item-recebimento')
            .prop('disabled', false)
            .removeClass('btn-secondary')
            .addClass('btn-success') // ou a cor original que você usa
            .html('<i class="fas fa-plus me-2"></i> Adicionar');

        // Remove classe de controle
        $('#form-recebimento-detalhe').removeClass('modo-leitura');
    }

    function atualizarTipoEntradaMP() {

        if (loteBloqueado) {
            return;
        }

        const tipo = $('input[name="tipo_entrada_mp"]:checked').val();

        const $selectMateria = $('#item_receb_produto_id');
        const $selectLote = $('#item_receb_lote_origem_id');

        if (tipo === 'MATERIA_PRIMA') {

            $selectMateria.prop('disabled', false).trigger('change');
            $selectLote.val(null).prop('disabled', true).trigger('change');

            aplicarModoMateriaPrima();

        } else {

            $selectLote.prop('disabled', false).trigger('change');
            $selectMateria.val(null).prop('disabled', true).trigger('change');

            aplicarModoReprocesso();
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

    function onAbaDetalhesExibida() {
        // 1. Pega status
        const statusLote = $('#lote_status').val();
        const isBloqueado = (statusLote === 'FINALIZADO' || statusLote === 'CANCELADO');

        // 2. DECISÃO CRÍTICA: Bloqueia ou Desbloqueia?
        if (isBloqueado) {
            bloquearFormularioRecebimento();
        } else {
            desbloquearFormularioRecebimento(); // <--- AQUI ESTA A CORREÇÃO! Limpa as travas antigas.
        }

        // 3. Carrega Produtos
        $.get('ajax_router.php?action=getProdutoOptions', { tipo_embalagem: 'MATERIA-PRIMA' }, function (res) {
            if (!res.success) return;

            const $sel = $('#item_receb_produto_id');
            if (!$sel.val()) {
                $sel.empty().append('<option value=""></option>');
                res.data.forEach(p => {
                    $sel.append(new Option(p.prod_descricao, p.prod_codigo));
                });
            }
            initSelect2($sel, 'Selecione produto (recebimento)');

            // 4. Reafirma o estado após o AJAX (pois o initSelect2 pode resetar coisas)
            if (isBloqueado) {
                bloquearFormularioRecebimento();
            } else {
                atualizarTipoEntradaMP(); // Aplica a lógica de Radio Button (Matéria Prima vs Reprocesso)
            }
        }, 'json');

        // 5. Select2 Lote Origem
        if (!$('#item_receb_lote_origem_id').hasClass('select2-hidden-accessible')) {
            $('#item_receb_lote_origem_id').select2({
                placeholder: 'Lote origem',
                allowClear: true,
                width: '100%',
                dropdownParent: $modalLoteNovo,
                theme: 'bootstrap-5',
                ajax: {
                    url: 'ajax_router.php?action=getLotesFinalizadosOptions',
                    dataType: 'json',
                    data: params => ({ term: params.term || '' }),
                    processResults: data => ({ results: data.results || [] })
                }
            });
        }

        // Reforço final do bloqueio para o segundo select
        if (isBloqueado) $('#item_receb_lote_origem_id').prop('disabled', true);
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

    // Função reutilizável para atualizar a tabela principal
    function recarregarTabelaLotes() {
        let idTabelaPrincipal = '#tabela-lotes-novo';

        if ($.fn.DataTable && $.fn.DataTable.isDataTable(idTabelaPrincipal)) {
            // reload(null, false) recarrega os dados e mantém a paginação atual
            $(idTabelaPrincipal).DataTable().ajax.reload(null, false);
            // console.log('Tabela atualizada via Ajax.');
        } else {
            console.log('Tabela não encontrada ou não é DataTables.');
        }
    }

    // Listener
    $('input[name="tipo_entrada_mp"]').on('change', function () {
        if (loteBloqueado) {
            return;
        }

        const novoTipo = $(this).val();
        aplicarModoEntrada();
    });

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
    $('#btn-adicionar-lote-novo').on('click', function () {

        if (loteBloqueado) {
            return;
        }

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

        aplicarModoEntrada();

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

    // Evento disparado SEMPRE que o modal fecha
    $('#modalLoteNovo').on('hidden.bs.modal', function () {

        // 1. Reutiliza as funções de limpeza do Recebimento (que você já tem)
        limparFormularioDetalhes();
        sairModoEdicao();

        // 2. Limpa os OUTROS formulários (Header, Produção e Embalagem)
        $('#form-lote-novo-header')[0].reset();
        $('#form-lote-novo-producao')[0].reset();
        $('#form-lote-novo-embalagem')[0].reset();

        // 3. Limpa todos os Inputs Hidden de IDs Globais
        $('#lote_id_novo').val('');
        $('#item_prod_id_novo').val('');
        $('#item_emb_id_novo').val('');

        // 4. Reseta os Selects Globais (Cliente/Fornecedor/Produtos)
        // O trigger('change') é importante para o Select2 limpar o visual
        $('#lote_fornecedor_id_novo, #lote_cliente_id_novo').val(null).trigger('change');
        $('#item_prod_produto_id_novo').val(null).trigger('change');
        $('#item_emb_prod_prim_id_novo').val(null).trigger('change'); // Isso já limpa o secundário em cascata

        // 5. Limpa as tabelas visuais
        $('#tabela-itens-recebimento').empty();
        $('#tabela-itens-producao-novo').empty();
        $('#tabela-itens-embalagem-novo').empty();

        // 6. Restaura botões e textos para o padrão "Novo"
        $('#btn-salvar-lote-novo-header').html('<i class="fas fa-save me-2"></i>Salvar e Avançar').show();

        $('#btn-adicionar-item-producao').html('<i class="fas fa-plus me-1"></i> Adicionar Item');
        $('#btn-cancelar-edicao-producao').trigger('click'); // Usa o click para resetar validade e outros estados

        $('#btn-adicionar-item-embalagem').html('<i class="fas fa-plus me-1"></i> Adicionar');
        $('#btn-cancelar-edicao-embalagem').remove(); // Remove botão cancelar dinâmico da embalagem

        // 7. Limpa mensagens de erro/feedback
        $('#mensagem-lote-header').html('');
        $('#feedback-consumo-embalagem').text('Preencha os campos para calcular o consumo.').removeClass('text-danger fw-bold').addClass('text-muted');

        // 8. Reseta controles globais
        configurarModalModoLeitura(false);
        loteIdAtual = null;

        // 9. Trava as abas novamente para o próximo "Novo Lote"
        $('#aba-detalhes-recebimento-tab, #aba-producao-novo-tab, #aba-embalagem-novo-tab').addClass('disabled');

        // 10. Recarrega a tabela principal (mantendo a página atual) para exibir as alterações (peso, status, etc)
        if (tabelaLotesNovo) {
            tabelaLotesNovo.ajax.reload(null, false);
        }
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

    // =================================================================
    // LÓGICA DE FINALIZAÇÃO (RESUMO + DECISÃO)
    // =================================================================

    $tabelaLotes.on('click', '.btn-finalizar-lote-novo', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');
        const $modal = $('#modal-finalizar-lote');
        const $tbody = $('#tabela-resumo-finalizacao');

        $('#lote-nome-finalizacao').text(loteNome);
        $tbody.html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Calculando resumo...</td></tr>');

        $('#total-geral-producao, #total-geral-caixas, #total-geral-sobras').text('-');
        //$('#btn-acao-finalizar').data('lote-id', loteId);
        $modal.data('lote-id', loteId);

        const modalFinalizar = new bootstrap.Modal($modal[0]);
        modalFinalizar.show();

        $.ajax({
            url: 'ajax_router.php?action=getResumoFinalizacao',
            type: 'GET',
            data: { lote_id: loteId },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $tbody.empty();
                const embalagens = response.data.embalagens;
                const sobras = response.data.sobras;
                const totais = response.data.totais;

                let temDados = false;

                // 1. Renderiza Linhas de EMBALAGEM (O que virou caixa)
                if (embalagens.length > 0) {
                    temDados = true;
                    embalagens.forEach(item => {
                        $tbody.append(`
                            <tr>
                                <td class="text-center align-middle">${item.prod_codigo_interno || '-'}</td>
                                <td class="align-middle">${item.prod_descricao}</td>
                                <td class="text-center align-middle">${item.prod_unidade || 'UN'}</td>
                                <td class="text-center align-middle">${floatToBr(item.total_prim_consumido, 3)}</td> 
                                <td class="text-center align-middle fw-bold">${parseInt(item.total_cxs_sec)}</td>
                                <td class="text-center align-middle text-muted">-</td> </tr>
                        `);
                    });
                }

                // 2. Renderiza Linhas de SOBRAS (O que não foi usado)
                if (sobras.length > 0) {
                    temDados = true;
                    sobras.forEach(item => {
                        $tbody.append(`
                            <tr class="table-warning">
                                <td class="text-center align-middle">${item.prod_codigo_interno || '-'}</td>
                                <td class="align-middle fw-bold text-dark">${item.prod_descricao} (SOBRA)</td>
                                <td class="text-center align-middle">${item.prod_unidade || 'KG'}</td>
                                <td class="text-center align-middle text-muted">-</td> 
                                <td class="text-center align-middle text-muted">-</td> 
                                <td class="text-center align-middle fw-bold text-danger">${floatToBr(item.total_sobra_liquida, 3)}</td>
                            </tr>
                        `);
                    });
                }

                if (!temDados) {
                    $tbody.html('<tr><td colspan="6" class="text-center text-muted">Nenhuma produção ou embalagem encontrada neste lote.</td></tr>');
                }

                // 3. Rodapés (Totais Gerais)
                $('#total-geral-producao').text(floatToBr(totais.total_produzido || 0, 3));

                // Soma total das caixas (apenas visual)
                const totalCaixas = embalagens.reduce((acc, item) => acc + parseInt(item.total_cxs_sec), 0);
                $('#total-geral-caixas').text(totalCaixas);

                const sobrasVal = parseFloat(totais.total_sobras || 0);
                $('#total-geral-sobras').text(floatToBr(sobrasVal, 3));
                if (sobrasVal > 0) {
                    $('#total-geral-sobras').addClass('text-danger fw-bold').removeClass('text-warning');
                }

            } else {
                $tbody.html(`<tr><td colspan="6" class="text-center text-danger">Erro: ${response.message}</td></tr>`);
            }
        });
    });

    // =================================================================
    // AÇÕES DE FINALIZAÇÃO (Botões do Rodapé)
    // =================================================================

    // Função centralizada para enviar o status
    function enviarStatusFinalizacao(loteId, novoStatus) {
        const textoAcao = novoStatus === 'FINALIZADO' ? 'Encerrar o Lote' : 'Manter Parcial';
        const corConfirmacao = novoStatus === 'FINALIZADO' ? '#198754' : '#ffc107';

        // Pergunta simples de segurança antes de enviar
        Swal.fire({
            title: 'Confirmar ação?',
            text: `Você optou por: ${textoAcao}. Confirma?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: corConfirmacao,
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, confirmar!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=atualizarStatusLote',
                    type: 'POST',
                    data: {
                        lote_id: loteId,
                        status: novoStatus,
                        csrf_token: csrfToken
                    },
                    dataType: 'json'
                }).done(function (res) {
                    if (res.success) {
                        $('#modal-finalizar-lote').modal('hide');
                        notificacaoSucesso('Lote Atualizado!', `O lote foi marcado como ${novoStatus}.`);
                        tabelaLotesNovo.ajax.reload();
                    } else {
                        notificacaoErro('Erro', res.message);
                    }
                });
            }
        });
    }

    // Botão: FINALIZAR E ENCERRAR (Verde)
    $('#btn-decisao-total').on('click', function () {
        //const loteId = $('#btn-acao-finalizar').data('lote-id'); // Pegamos o ID que guardamos ao abrir
        const loteId = $('#modal-finalizar-lote').data('lote-id');
        enviarStatusFinalizacao(loteId, 'FINALIZADO');
    });

    // Botão: MANTER PARCIAL (Amarelo)
    $('#btn-decisao-parcial').on('click', function () {
        const loteId = $('#btn-acao-finalizar').data('lote-id');
        enviarStatusFinalizacao(loteId, 'PARCIALMENTE FINALIZADO');
    });

    // Evento para gerar Relatório Mensal

    $('#btn-gerar-relatorio-mensal')
        .off('click')
        .on('click', function () {
            const ano = $('#rel_ano').val();

            // Coleta Meses
            const mesesSelecionados = [];

            $('.check-mes-item:checked').each(function () {
                mesesSelecionados.push($(this).val());
            });

            // Coleta Fornecedores
            const fornecedoresSelecionados = [];

            // Se "Todos" estiver marcado, podemos enviar vazio (indicando todos) ou enviar todos os IDs. 
            // Para filtros URL, se todos estiverem marcados, enviar vazio deixa a URL mais limpa.
            if (!$('#check-fornecedor-todos').is(':checked')) {
                $('.check-fornecedor-item:checked').each(function () {
                    fornecedoresSelecionados.push($(this).val());
                });
                // Se o usuário desmarcou tudo, precisamos avisar
                if (fornecedoresSelecionados.length === 0) {
                    notificacaoErro('Erro', 'Selecione pelo menos um fornecedor.');
                    return;
                }
            }
            // Nota: Se 'Todos' estiver checkado, o array fica vazio, e o PHP entende como "trazer tudo".

            if (mesesSelecionados.length === 0 || !ano) {
                notificacaoErro('Erro', 'Selecione pelo menos um mês e informe o ano.');
                return;
            }

            const mesesStr = mesesSelecionados.join(',');
            const fornStr = fornecedoresSelecionados.join(',');

            // Monta a url
            let url = `ajax_router.php?action=gerarRelatorioMensalLotes&meses=${mesesStr}&ano=${ano}`;

            if (fornStr) {
                url += `&fornecedores=${fornStr}`;
            }
            window.open(url, '_blank');
        });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            // Página voltou do cache do navegador
            $('.check-mes-item').prop('checked', false);
            $('#check-mes-todos').prop('checked', false);
            $('#rel_ano').val('');
            atualizarTextoBotaoMeses();
        }
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

        // Pega o valor do campo Cliente
        const clienteIdInput = $('#lote_cliente_id_novo').val();

        if (!clienteIdInput) {
            notificacaoErro('Atenção', 'Cliente não identificado no cabeçalho.');
            return; // Para tudo aqui
        }

        const $btn = $(this);
        const originalIcon = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: 'ajax_router.php?action=imprimirEtiquetaLoteItem',
            type: 'POST',
            data: {
                itemId: loteItemId,
                itemType: 'producao',
                clienteId: clienteIdInput, // Enviando o valor que acabamos de validar
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                window.open(BASE_URL + '/' + response.pdfUrl, '_blank'); // Abre o PDF direto
            } else {
                notificacaoErro('Erro ao gerar etiqueta', response.message);
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            // Se falhar aqui, mostra o erro técnico
            console.error("Erro Impressão:", jqXHR.responseText);
            notificacaoErro('Erro Técnico', 'Falha ao comunicar com o servidor.');
        }).always(function () {
            // Destrava o botão
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

    $(document).on('click', '.btn-excluir-item-recebimento', function (e) {
        e.preventDefault();

        let idItem = $(this).data('id');
        let $linha = $(this).closest('tr');

        // 1. Pega o token CSRF da meta tag (padrão do seu sistema)
        let csrfToken = $('meta[name="csrf-token"]').attr('content');

        if (!idItem) {
            notificacaoErro('Erro', 'ID do item não encontrado.');
            return;
        }

        Swal.fire({
            title: 'Tem certeza?',
            text: "Deseja realmente excluir este item do recebimento?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {

                // 2. Mudamos de $.post para $.ajax para poder enviar o Header
                $.ajax({
                    url: 'ajax_router.php?action=excluirItemRecebimento',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: idItem,
                        // Alguns sistemas aceitam no corpo também, por garantia:
                        csrf_token: csrfToken
                    },
                    // 3. Adicionamos o cabeçalho de segurança
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function (response) {
                        if (response.success) {
                            $linha.fadeOut(500, function () {
                                $(this).remove();
                            });

                            // 2. ATUALIZA A TABELA PRINCIPAL (Para corrigir o "12g / 14g")
                            recarregarTabelaLotes();

                            Swal.fire('Excluído!', response.message || 'Item removido.', 'success');
                        } else {
                            notificacaoErro('Erro', response.message || 'Não foi possível excluir.');
                        }
                    },
                    error: function (xhr, status, error) {
                        // Se der erro 419 ou 403, é o CSRF
                        if (xhr.status === 419 || xhr.status === 403) {
                            notificacaoErro('Erro de Sessão', 'Sua sessão expirou ou o token é inválido. Recarregue a página.');
                        } else {
                            notificacaoErro('Erro', 'Falha na comunicação com o servidor: ' + error);
                        }
                    }
                });
            }
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
        onAbaDetalhesExibida();
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

    // --- BOTÃO EDITAR ITEM (DETALHES) ---
    /* $(document).on('click', '.btn-editar-item-recebimento', function () {
 
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
 
             // Aplica as regras de UI, com tudo já preenchido
             aplicarModoEntrada();
 
         }, 'json');
     }); */


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

            // 1. Preenche IDs Ocultos e Status
            $('#item_receb_id').val(data.item_receb_id);
            $('#item_receb_lote_id').val(data.item_receb_lote_id);
            $('#lote_status').val(data.lote_status);

            // 2. LÓGICA DE DECISÃO: REPROCESSO vs MATÉRIA PRIMA
            if (data.item_receb_lote_origem_id) {
                // === MODO REPROCESSO ===

                // A. Muda o Radio Button
                $('input[name="tipo_entrada_mp"][value="LOTE_ORIGEM"]').prop('checked', true).trigger('change');

                // B. Preenche o Lote de Origem
                // CORREÇÃO AQUI: Monta o texto bonito usando os dados que vieram do SQL acima
                let textoLote = 'Lote ' + data.item_receb_lote_origem_id; // Fallback

                if (data.lote_origem_numero) {
                    textoLote = data.lote_origem_numero;
                    // Se tiver o nome do produto do lote de origem, adiciona também
                    if (data.lote_origem_produto_nome) {
                        textoLote += ' (' + data.lote_origem_produto_nome + ')';
                    }
                } else if (data.lote_origem_label) {
                    // Caso seu PHP já mande o label pronto
                    textoLote = data.lote_origem_label;
                }

                // Verifica se já existe a opção, senão cria
                if ($('.select2-lotes-finalizados').find("option[value='" + data.item_receb_lote_origem_id + "']").length === 0) {
                    const optionLote = new Option(textoLote, data.item_receb_lote_origem_id, true, true);
                    $('.select2-lotes-finalizados').append(optionLote);
                }

                // Define o valor e dispara o change.select2 (apenas update visual, sem ajax)
                $('.select2-lotes-finalizados').val(data.item_receb_lote_origem_id).trigger('change.select2');

                // C. Preenche o Produto de Origem (Específico do Reprocesso)
                const $selectProdOrigem = $('#select-produto-origem');
                $selectProdOrigem.empty(); // Limpa carregamento anterior
                const optionProd = new Option(data.prod_descricao, data.item_receb_produto_id, true, true);
                $selectProdOrigem.append(optionProd).trigger('change');

                // Garante que a div do produto esteja visível
                $('#div-produto-origem').show();

            } else {
                // === MODO MATÉRIA PRIMA ===

                // A. Muda o Radio Button
                $('input[name="tipo_entrada_mp"][value="MATERIA_PRIMA"]').prop('checked', true).trigger('change');

                // B. Preenche o Produto Principal
                $('#item_receb_produto_id')
                    .val(data.item_receb_produto_id)
                    .trigger('change');
            }

            // 3. Inputs Comuns (Texto e Números)
            $('#item_receb_nota_fiscal').val(data.item_receb_nota_fiscal);
            $('#item_receb_peso_nota_fiscal').val(formatarBR(data.item_receb_peso_nota_fiscal, 3)); // Use sua função formatarBR
            $('#item_receb_total_caixas').val(data.item_receb_total_caixas);
            $('#item_receb_peso_medio_ind').val(formatarBR(data.item_receb_peso_medio_ind, 2));

            // Gramaturas (geralmente só MP, mas preenchemos se tiver)
            $('input[name="item_receb_gram_faz"]').val(formatarBR(data.item_receb_gram_faz, 2));
            $('input[name="item_receb_gram_lab"]').val(formatarBR(data.item_receb_gram_lab, 2));

            // Força recálculo visual se houver listeners
            $('#item_receb_peso_nota_fiscal').trigger('input');

            // 4. Ajusta Botões (Salvar vs Adicionar)
            $('#btn-adicionar-item-recebimento')
                .html('<i class="fas fa-save me-2"></i> Salvar Alteração')
                .removeClass('btn-success')
                .addClass('btn-warning');

            $('#btn-cancelar-edicao').removeClass('d-none');

            // Foca no primeiro campo editável
            setTimeout(() => { $('#item_receb_peso_nota_fiscal').focus(); }, 100);

        }, 'json');
    });


    // --- BOTÃO SALVAR (ADICIONAR / ATUALIZAR) ---
    /* $('#btn-adicionar-item-recebimento').on('click', function () {
 
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
 
             recarregarTabelaLotes();
 
             // Sai do modo edição (se estiver)
             sairModoEdicao();
 
             // Reaplica regras do rádio selecionado
 
             aplicarModoEntrada();
 
             // Mantém o lote atual
             $('#item_receb_lote_id').val(loteIdAtual);
 
             // Recarrega tabela
             recarregarItensRecebimento(loteIdAtual);
         });
     }); */

    $('#btn-adicionar-item-recebimento').on('click', function () {
        let tipo = $('input[name="tipo_entrada_mp"]:checked').val();

        // FLUXO MATÉRIA PRIMA
        if (tipo === 'MATERIA_PRIMA') {
            salvarRecebimentoNormal();
            return;
        }

        // FLUXO REPROCESSO
        let loteId = $('.select2-lotes-finalizados').val(); // Pega do Select2 direto
        let prodId = $('#select-produto-origem').val();
        let qtdCaixasTotal = parseFloat($('#item_receb_total_caixas').val()) || 0;

        if (!loteId || !prodId) {
            notificacaoErro('Atenção', 'Selecione o lote de origem e o produto.');
            return;
        }
        if (qtdCaixasTotal <= 0) {
            notificacaoErro('Erro', 'Quantidade de caixas inválida.');
            return;
        }

        // Consulta endereços
        $.ajax({
            url: 'ajax_router.php?action=checkEstoqueParaBaixa',
            data: { lote_id: loteId, produto_id: prodId },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    let saldos = res.data;

                    // CASO 1: SEM ALOCAÇÃO NENHUMA
                    if (saldos.length === 0) {
                        Swal.fire({
                            title: 'Item sem Alocação Definida',
                            text: "Este item consta no estoque mas não tem endereço de alocação (Kardex antigo ou Legado). Deseja registrar o reprocesso sem realizar baixa de endereço específico?",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Sim, continuar',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                distribuicaoBaixa = []; // Lista vazia
                                enviarReprocessoParaBackend();
                            }
                        });
                        return;
                    }

                    // CASO 2: TEM ALOCAÇÃO -> Abre modal ou baixa direto
                    if (saldos.length === 1 && parseFloat(saldos[0].saldo_caixas) >= qtdCaixasTotal) {
                        distribuicaoBaixa = [{
                            id: saldos[0].item_emb_id,
                            qtd: qtdCaixasTotal
                        }];
                        enviarReprocessoParaBackend();
                    } else {
                        abrirModalPicking(saldos, qtdCaixasTotal);
                    }
                }
            },
            error: function () {
                // Se der erro ao checar estoque, permite tentar salvar sem baixa? 
                // Melhor avisar o erro.
                notificacaoErro('Erro', 'Falha ao consultar alocações do estoque.');
            }
        });
    });

    $('#btn-cancelar-edicao').on('click', function () {
        limparFormularioDetalhes();
        sairModoEdicao();
    });

    // Evento para Gerar Relatório A PARTIR DA LISTA (DataTables)
    $tabelaLotes.on('click', '.btn-imprimir-relatorio-lista', function (e) {
        e.preventDefault(); // Evita que a página role para o topo
        const loteId = $(this).data('id');

        Swal.fire({
            icon: 'info',
            title: 'Gerando PDF...',
            text: 'Aguarde, o download iniciará em breve.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        $.ajax({
            url: 'ajax_router.php?action=gerarRelatorioLote',
            type: 'GET',
            data: { lote_id: loteId },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                // Abre o PDF em nova aba
                window.open(response.url, '_blank');
            } else {
                notificacaoErro('Erro', response.message || 'Não foi possível gerar o relatório.');
            }
        }).fail(function () {
            notificacaoErro('Erro', 'Erro de comunicação ao gerar relatório.');
        });
    });

    // --- LÓGICA DO MULTI-SELECT DE FORNECEDORES ---
    // 1. Carrega os fornecedores e monta a lista de checkboxes
    function carregarFornecedoresParaFiltro() {
        $.get('ajax_router.php?action=getClientesComLotesOptions', function (response) {
            const $container = $('#container-check-fornecedores-items');
            $container.empty();

            if (response.data) {
                response.data.forEach(item => {
                    const html = `
                        <li class='p-1'>
                            <div class='form-check'>
                                <input class='form-check-input check-fornecedor-item' type='checkbox' value='${item.id}' id='filtro-forn-${item.id}' checked>
                                <label class='form-check-label w-100 cursor-pointer text-truncate' for='filtro-forn-${item.id}' title="${item.text}">
                                    ${item.text}
                                </label>
                            </div>
                        </li>`;
                    $container.append(html);
                });
                atualizarTextoBotaoFornecedores();
            }
        });
    }

    // 2. Atualiza o texto do botão
    function atualizarTextoBotaoFornecedores() {
        const total = $('.check-fornecedor-item').length;
        const marcados = $('.check-fornecedor-item:checked').length;
        const $btn = $('#btn-dropdown-fornecedores');
        const $checkTodos = $('#check-fornecedor-todos');

        if (marcados === 0) {
            $btn.text('Nenhum selecionado');
            $checkTodos.prop('checked', false);
        } else if (marcados === total && total > 0) {
            $btn.text('Todos os Fornecedores');
            $checkTodos.prop('checked', true);
        } else {
            $btn.text(marcados + ' fornecedor(es)');
            $checkTodos.prop('checked', false);
        }
    }

    // Eventos de Checkbox Fornecedores
    $('#lista-filtro-fornecedores').on('change', '#check-fornecedor-todos', function () {
        const isChecked = $(this).is(':checked');
        $('.check-fornecedor-item').prop('checked', isChecked);
        atualizarTextoBotaoFornecedores();
    });

    $('#lista-filtro-fornecedores').on('change', '.check-fornecedor-item', function () {
        atualizarTextoBotaoFornecedores();
    });

    // Chama a função ao iniciar se estiver na página correta
    if ($('#btn-dropdown-fornecedores').length > 0) {
        carregarFornecedoresParaFiltro();
    }

    // --- LÓGICA DO MULTI-SELECT DE MESES ---
    // 1. Função para atualizar o texto do botão conforme a seleção
    function atualizarTextoBotaoMeses() {
        const selecionados = [];
        $('.check-mes-item:checked').each(function () {
            selecionados.push($(this).next('label').text().trim());
        });

        const $btn = $('#btn-dropdown-meses');
        const $checkTodos = $('#check-mes-todos');

        if (selecionados.length === 0) {
            $btn.text('Nenhum mês selecionado');
            $checkTodos.prop('checked', false);
        } else if (selecionados.length === 12) {
            $btn.text('ANO COMPLETO (12 Meses)');
            $checkTodos.prop('checked', true);
        } else if (selecionados.length <= 2) {
            $btn.text(selecionados.join(', ')); // Ex: "Janeiro, Março"
        } else {
            $btn.text(selecionados.length + ' meses selecionados');
            // Se não são 12, desmarca o todos
            $checkTodos.prop('checked', false);
        }
    }

    // Evento: Clique no "Marcar Todos"
    $('#check-mes-todos').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.check-mes-item').prop('checked', isChecked);
        atualizarTextoBotaoMeses();
    });

    // Evento: Clique em um mês individual
    $('.check-mes-item').on('change', function () {
        // Se desmarcar um item, desmarca o "Todos"
        if (!$(this).is(':checked')) {
            $('#check-mes-todos').prop('checked', false);
        }
        // Se marcar todos manualmente, marca o "Todos"
        if ($('.check-mes-item:checked').length === 12) {
            $('#check-mes-todos').prop('checked', true);
        }
        atualizarTextoBotaoMeses();
    });

    // Inicializa o texto ao carregar a página
    atualizarTextoBotaoMeses();

    // Impede que o dropdown feche ao clicar num checkbox (melhora a usabilidade)
    $('.dropdown-menu').on('click', function (e) {
        e.stopPropagation();
    });

    // --- IMPORTAÇÃO DE LOTE LEGADO ---
    $('#btn-salvar-legado').on('click', function () {
        const $btn = $(this);
        const $form = $('#form-importar-legado');

        // Validação HTML5 simples
        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }

        // Token CSRF
        const csrfToken = $('meta[name="csrf-token"]').attr('content');

        Swal.fire({
            title: 'Confirma Importação?',
            text: "Este lote será registrado como FINALIZADO e o saldo inserido no estoque. A operação será auditada.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#343a40',
            confirmButtonText: 'Sim, Importar'
        }).then((result) => {
            if (result.isConfirmed) {

                // Prepara dados
                let formData = new FormData($form[0]);

                // Se o seu ajax_router espera a action na URL, ou no FormData, mande nos dois para garantir
                // Mas baseado no nosso histórico, mande na URL:
                const urlAction = 'ajax_router.php?action=importarLoteLegado';

                formData.append('csrf_token', csrfToken);

                $.ajax({
                    url: urlAction,
                    type: 'POST',
                    data: formData,
                    processData: false, // Necessário para FormData
                    contentType: false, // Necessário para FormData
                    dataType: 'json',
                    beforeSend: function () {
                        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processando...');
                    },
                    success: function (response) {
                        if (response.success) {
                            $('#modal-importar-legado').modal('hide');
                            $form[0].reset();

                            // Limpa os Select2 se estiver usando
                            $('.select2-clientes, .select2-produtos, .select2-enderecos').val(null).trigger('change');

                            Swal.fire('Sucesso!', response.message, 'success');

                            // Se estiver na tela de estoque, recarrega. Se estiver em lotes, não precisa fazer nada visualmente, 
                            // pois o lote legado não aparece na lista de produção ativa.
                        } else {
                            notificacaoErro('Erro', response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        notificacaoErro('Erro', 'Falha na comunicação: ' + error);
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i> Confirmar Importação');
                    }
                });
            }
        });
    });

    // --- LÓGICA DE CÁLCULO AUTOMÁTICO ---

    // 1. Ao selecionar produto, guarda o peso da embalagem
    $('.select2-produtos').on('select2:select', function (e) {
        var data = e.params.data;

        // Debug: Veja no console (F12) se o peso está aparecendo
        console.log("Produto Selecionado:", data.text, "Peso Embalagem:", data.prod_peso_embalagem);

        // Garante que seja float, trocando vírgula por ponto se necessário
        let pesoString = String(data.prod_peso_embalagem || '0').replace(',', '.');
        let pesoEmb = parseFloat(pesoString) || 0;

        $('#hidden_peso_embalagem').val(pesoEmb);

        if (pesoEmb === 0) {
            notificacaoErro('Aviso', 'Produto sem peso padrão cadastrado. Digite manualmente.');
        }

        // Atualiza a interface
        configurarCamposCalculo();
        recalcularValores();
    });

    // 2. Monitora a troca do Radio Button (Modo de Cálculo)
    $('input[name="modo_calculo"]').on('change', function () {
        configurarCamposCalculo();
    });

    // 3. Monitora a digitação nos campos
    $('#input_qtd_caixas, #input_peso_total').on('keyup change', function () {
        recalcularValores();
    });

    function configurarCamposCalculo() {
        let modo = $('input[name="modo_calculo"]:checked').val();
        let pesoPadrao = parseFloat($('#hidden_peso_embalagem').val()) || 0;

        // Se não tiver peso padrão (produto livre), libera tudo
        if (pesoPadrao === 0) {
            $('#input_qtd_caixas, #input_peso_total').prop('readonly', false).removeClass('bg-secondary text-white bg-opacity-25');
            return;
        }

        // Classes visuais para "bloqueado"
        const classeBloqueado = 'bg-secondary bg-opacity-10'; // Cinza claro

        if (modo === 'caixa') {
            // Usuário digita CAIXA -> Bloqueia PESO
            $('#input_qtd_caixas').prop('readonly', false).removeClass(classeBloqueado).focus();
            $('#input_peso_total').prop('readonly', true).addClass(classeBloqueado);
        } else {
            // Usuário digita PESO -> Bloqueia CAIXA
            $('#input_peso_total').prop('readonly', false).removeClass(classeBloqueado).focus();
            $('#input_qtd_caixas').prop('readonly', true).addClass(classeBloqueado);
        }

        // Força um recalculo imediato ao trocar o switch
        recalcularValores();
    }

    function recalcularValores() {
        let pesoPadrao = parseFloat($('#hidden_peso_embalagem').val()) || 0;

        // Se peso for zero, não calcula nada (evita divisão por zero ou erros)
        if (pesoPadrao === 0) return;

        let modo = $('input[name="modo_calculo"]:checked').val();

        if (modo === 'caixa') {
            // MODO 1: Digitou CAIXAS -> Calcula PESO (Multiplicação)
            let caixas = parseFloat($('#input_qtd_caixas').val()) || 0;
            let pesoTotal = caixas * pesoPadrao;

            $('#input_peso_total').val(pesoTotal.toLocaleString('pt-BR', { minimumFractionDigits: 3 }));
        }
        else {
            // MODO 2: Digitou PESO -> Calcula CAIXAS (Divisão)
            let pesoTotalStr = $('#input_peso_total').val();
            // Limpa pontos de milhar e troca vírgula decimal
            let pesoTotal = parseFloat(pesoTotalStr.replace(/\./g, '').replace(',', '.')) || 0;

            if (pesoTotal > 0) {
                let caixas = pesoTotal / pesoPadrao;

                // Debug: Veja se a conta está certa
                console.log(pesoTotal + " / " + pesoPadrao + " = " + caixas);

                // Arredonda para o inteiro mais próximo
                $('#input_qtd_caixas').val(Math.round(caixas));
            } else {
                $('#input_qtd_caixas').val(0);
            }
        }
    }

    $('#modal-historico-legado').on('shown.bs.modal', function () {
        carregarHistoricoLegado();
    });

    function carregarHistoricoLegado() {
        $.ajax({
            url: 'ajax_router.php?action=listarLotesLegadosRecentes',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                let html = '';
                if (response.data.length === 0) {
                    html = '<tr><td colspan="5" class="text-center">Nenhum registro recente.</td></tr>';
                } else {
                    response.data.forEach(function (lote) {
                        html += `
                            <tr>
                                <td>${lote.lote_completo_calculado}</td>
                                <td>${lote.prod_descricao}</td>
                                <td>${lote.item_emb_qtd_sec}</td>
                                <td>${lote.data_formatada}</td>
                                <td>
                                    <button class="btn btn-sm btn-danger btn-estornar-legado" 
                                            data-id="${lote.lote_id}" title="Estornar/Cancelar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                }
                $('#tabela-historico-legado tbody').html(html);
            }
        });
    }

    // Clique no botão de lixeira (Estornar)
    $(document).on('click', '.btn-estornar-legado', function () {
        let id = $(this).data('id');

        Swal.fire({
            title: 'Cancelar Importação?',
            text: "Isso removerá o saldo do estoque. Esta ação é irreversível.",
            icon: 'warning',
            input: 'text',
            inputLabel: 'Motivo do Estorno',
            inputPlaceholder: 'Ex: Erro de digitação',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, Estornar',
            preConfirm: (motivo) => {
                if (!motivo) {
                    Swal.showValidationMessage('Informe um motivo para auditoria');
                }
                return motivo;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=estornarLoteLegado',
                    type: 'POST',
                    data: {
                        lote_id: id,
                        motivo: result.value,
                        csrf_token: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Sucesso', res.message, 'success');

                            // 1. Atualiza a lista do modal (Histórico)
                            carregarHistoricoLegado();

                            // 2. ATUALIZA A TABELA PRINCIPAL
                            recarregarTabelaLotes();
                        } else {
                            Swal.fire('Erro', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // --- RESETAR MODAL AO FECHAR ---
    $('#modal-importar-legado').on('hidden.bs.modal', function () {
        // 1. Limpa o formulário HTML (inputs de texto, number, etc)
        $(this).find('form')[0].reset();

        // 2. Limpa os Select2
        $('.select2-clientes, .select2-produtos, .select2-enderecos').val(null).trigger('change');

        // 3. Reseta variáveis ocultas e estilo
        $('#hidden_peso_embalagem').val(0);

        // 4. Volta o switch para o padrão (Caixas) e reseta bloqueios visuais
        $('#modo_calc_caixa').prop('checked', true);
        configurarCamposCalculo(); // Reaplica a lógica visual (bloqueia peso, libera caixa)

        // 5. Remove classes de validação do Bootstrap (se houver)
        $(this).find('form').removeClass('was-validated');
    });

    // ======================================================
    // LÓGICA DE REPROCESSO (Lote -> Produtos -> Dados)
    // ======================================================

    // 1. ALTERNAR ENTRE MATÉRIA-PRIMA E REPROCESSO (Com Inicialização Segura)
    $(document).on('change', 'input[name="tipo_entrada_mp"]', function () {
        let tipo = $('input[name="tipo_entrada_mp"]:checked').val();

        if (tipo === 'LOTE_ORIGEM') {
            // MODO REPROCESSO
            $('#div-select-mp').hide();
            $('#div-lote-origem').show();

            // --- CORREÇÃO DO ERRO 'isConnected' ---
            // Só inicializa o Select2 agora, garantindo que o modal existe
            // Verificamos se já não foi inicializado antes para não duplicar
            if (!$('.select2-lotes-finalizados').hasClass("select2-hidden-accessible")) {

                $('.select2-lotes-finalizados').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#modal-gerenciar-lotes'), // Agora ele com certeza existe!
                    placeholder: 'Busque pelo número do lote...',
                    allowClear: true,
                    ajax: {
                        url: 'ajax_router.php?action=searchLotes',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                q: params.term,
                                status: 'FINALIZADO'
                            };
                        },
                        processResults: function (data) {
                            return {
                                results: data.map(function (item) {
                                    return {
                                        id: item.lote_id,
                                        text: item.lote_completo_calculado + ' (' + item.prod_descricao + ')',
                                        lote_dados: item
                                    };
                                })
                            };
                        },
                        cache: true
                    }
                });
            }

            // Exibe/Oculta o produto de origem dependendo se já tem lote selecionado
            if ($('.select2-lotes-finalizados').val()) {
                $('#div-produto-origem').show();
            } else {
                $('#div-produto-origem').hide();
            }

        } else {
            // MODO MATÉRIA PRIMA
            $('#div-select-mp').show();
            $('#div-lote-origem').hide();
            $('#div-produto-origem').hide();

            $('.select2-lotes-finalizados').val(null).trigger('change');
            $('#select-produto-origem').empty();
        }
    });

    // 2. AO SELECIONAR O LOTE -> BUSCAR PRODUTOS
    $('.select2-lotes-finalizados').on('select2:select', function (e) {
        let loteId = e.params.data.id;

        // Limpa campos anteriores
        $('#select-produto-origem').empty().append('<option value="">Carregando...</option>');
        $('#div-produto-origem').show();

        // ROTA A: Listar Produtos (Não busca pesos ainda)
        $.ajax({
            url: 'ajax_router.php?action=listarProdutosDoLote',
            data: { lote_id: loteId },
            dataType: 'json',
            success: function (res) {
                let html = '<option value="">Selecione o produto a reprocessar...</option>';
                if (res.data && res.data.length > 0) {
                    res.data.forEach(function (p) {
                        html += `<option value="${p.prod_codigo}">${p.prod_descricao}</option>`;
                    });
                } else {
                    html = '<option value="">Nenhum produto neste lote.</option>';
                }
                $('#select-produto-origem').html(html);
            }
        });
    });

    // 3. AO SELECIONAR O PRODUTO -> BUSCAR PESOS E CAIXAS
    $('#select-produto-origem').on('change', function () {
        let produtoId = $(this).val();
        let loteId = $('.select2-lotes-finalizados').val();

        if (!produtoId || !loteId) return;

        // ROTA B: Busca dados na tabela de Embalagem (Estoque)
        $.ajax({
            url: 'ajax_router.php?action=getDadosReprocessoPorProduto',
            data: { lote_id: loteId, produto_id: produtoId },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    let d = res.data;

                    // Preenche os campos
                    $('input[name="item_receb_peso_nota_fiscal"]').val(formatarBR(d.peso_total_calculado));
                    $('input[name="item_receb_total_caixas"]').val(d.total_caixas);
                    $('input[name="item_receb_peso_medio_ind"]').val(formatarBR(d.prod_peso_embalagem));
                    $('input[name="item_receb_nota_fiscal"]').val(d.referencia_nota);

                    // Limpa campos que não fazem sentido no reprocesso
                    $('input[name="item_receb_gram_faz"]').val('');
                    $('input[name="item_receb_gram_lab"]').val('');

                } else {
                    // Se não tiver saldo, avisa mas não trava
                    notificacaoErro('Aviso', res.message || 'Dados não encontrados.');
                }
            }
        });
    });

    // LOGICA PARA BAIXA DE LOTES PARA REPROCESSO

    // VARIÁVEL GLOBAL PARA GUARDAR A DISTRIBUIÇÃO
    var distribuicaoBaixa = [];

    /*    $('#btn-adicionar-item-recebimento').on('click', function () {
            let tipo = $('input[name="tipo_entrada_mp"]:checked').val();
    
            // Se for Matéria Prima, salva normal (código antigo)
            if (tipo === 'MATERIA_PRIMA') {
                salvarRecebimentoNormal(); // *Supondo que vc tenha isolado a função antiga
                return;
            }
    
            // SE FOR REPROCESSO:
            let loteId = $('#item_receb_lote_origem_id').val();
            let prodId = $('#select-produto-origem').val();
            let qtdCaixasTotal = parseInt($('#item_receb_total_caixas').val()) || 0;
    
            if (qtdCaixasTotal <= 0) {
                notificacaoErro('Erro', 'Quantidade inválida.');
                return;
            }
    
            // 1. Consulta endereços
            $.ajax({
                url: 'ajax_router.php?action=checkEstoqueParaBaixa',
                data: { lote_id: loteId, produto_id: prodId },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        let saldos = res.data;
    
                        // Se tiver só 1 endereço e o saldo cobrir tudo, baixa direto
                        if (saldos.length === 1 && saldos[0].saldo_caixas >= qtdCaixasTotal) {
                            distribuicaoBaixa = [{
                                id: saldos[0].item_emb_id,
                                qtd: qtdCaixasTotal
                            }];
                            enviarReprocessoParaBackend(); // Salva direto
                        } else {
                            // Vários endereços ou saldo complexo: ABRE MODAL PICKING
                            abrirModalPicking(saldos, qtdCaixasTotal);
                        }
                    }
                }
            });
        }); */

    function abrirModalPicking(saldos, totalAlvo) {
        let html = '';
        saldos.forEach(item => {
            let nomeEnd = item.endereco_nome ? item.endereco_nome : 'Endereço Indefinido';
            html += `
                <tr>
                    <td>${nomeEnd} (ID: ${item.item_emb_id})</td>
                    <td class="text-center">${item.saldo_caixas}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm input-picking-qtd" 
                            data-id="${item.item_emb_id}" 
                            data-max="${item.saldo_caixas}" 
                            min="0" value="0">
                    </td>
                </tr>
            `;
        });

        $('#lista-picking-enderecos').html(html);
        $('#picking-total-alvo').text(totalAlvo);
        $('#picking-total-selecionado').text('0');
        $('#picking-erro-msg').hide();

        $('#modal-picking-reprocesso').modal('show');

        // Evento para somar em tempo real
        $('.input-picking-qtd').on('input', function () {
            let total = 0;
            $('.input-picking-qtd').each(function () {
                total += parseInt($(this).val()) || 0;
            });
            $('#picking-total-selecionado').text(total);

            // Validações visuais
            if (total === totalAlvo) {
                $('#picking-total-selecionado').removeClass('text-danger').addClass('text-success');
                $('#btn-confirmar-picking').prop('disabled', false);
            } else {
                $('#picking-total-selecionado').removeClass('text-success').addClass('text-danger');
                $('#btn-confirmar-picking').prop('disabled', true);
            }
        });

        // Trigger inicial para travar botão
        $('.input-picking-qtd').first().trigger('input');
    }

    $('#btn-confirmar-picking').on('click', function () {
        distribuicaoBaixa = [];
        let total = 0;
        let totalAlvo = parseInt($('#picking-total-alvo').text());

        $('.input-picking-qtd').each(function () {
            let qtd = parseInt($(this).val()) || 0;
            let id = $(this).data('id');
            if (qtd > 0) {
                distribuicaoBaixa.push({ id: id, qtd: qtd });
            }
            total += qtd;
        });

        if (total !== totalAlvo) {
            $('#picking-erro-msg').text('A soma das quantidades deve ser igual ao total do reprocesso.').show();
            return;
        }

        $('#modal-picking-reprocesso').modal('hide');
        enviarReprocessoParaBackend();
    });

    /* function enviarReprocessoParaBackend() {
         // 1. Serializa os dados do formulário
         let dadosForm = $('#form-recebimento-detalhe').serializeArray();
 
         // --- INJEÇÃO MANUAL DOS IDs ---
         // O serializeArray pode falhar se o campo estiver 'disabled' ou oculto de forma complexa.
         // Aqui nós garantimos que o valor selecionado no Select2 seja enviado.
 
         let loteId = $('.select2-lotes-finalizados').val();
         let prodId = $('#select-produto-origem').val();
 
         // Removemos entradas duplicadas ou vazias antigas para evitar confusão no PHP
         dadosForm = dadosForm.filter(function (item) {
             return item.name !== 'lote_origem_produto_id' && item.name !== 'item_receb_lote_origem_id';
         });
 
         // Adicionamos os valores corretos manualmente
         if (loteId) {
             dadosForm.push({ name: 'item_receb_lote_origem_id', value: loteId });
         }
         if (prodId) {
             dadosForm.push({ name: 'lote_origem_produto_id', value: prodId });
         }
         // -----------------------------------------------
 
         // 2. Adiciona o JSON da distribuição de estoque
         dadosForm.push({ name: 'distribuicao_estoque', value: JSON.stringify(distribuicaoBaixa) });
 
         // 3. Adiciona o token manualmente para garantir
         let csrfToken = $('meta[name="csrf-token"]').attr('content');
         dadosForm.push({ name: 'csrf_token', value: csrfToken });
 
         $.ajax({
             url: 'ajax_router.php?action=salvarReprocesso',
             type: 'POST',
             data: dadosForm,
             dataType: 'json',
             success: function (res) {
                 if (res.success) {
                     Swal.fire('Sucesso', res.message, 'success');
 
                     // A. Limpa os inputs do formulário
                     $('#form-recebimento-detalhe')[0].reset();
 
                     // B. Limpa os Select2 (Lote e Produto)
                     $('.select2-lotes-finalizados').val(null).trigger('change');
                     $('#select-produto-origem').empty();
 
                     // C. Limpa campos hidden e variáveis globais
                     $('#item_receb_id').val('');
                     distribuicaoBaixa = [];
 
                     // D. FORÇA A VOLTA VISUAL PARA MATÉRIA-PRIMA
                     // Isso dispara o evento 'change' que esconde as divs de reprocesso e mostra a de MP
                     $('#entrada_mp_materia').prop('checked', true).trigger('change');
 
                     // E. Atualiza as tabelas da tela
                     if (typeof carregarItensRecebimento === 'function') {
                         carregarItensRecebimento(loteIdAtual);
                     }
                     if (typeof recarregarTabelaLotes === 'function') {
                         recarregarTabelaLotes();
                     }
                 } else {
                     Swal.fire('Erro', res.message, 'error');
                 }
             },
             error: function (xhr) {
                 // Tratamento de erro caso o servidor devolva 403/500
                 let msg = 'Erro desconhecido';
                 if (xhr.responseJSON && xhr.responseJSON.message) {
                     msg = xhr.responseJSON.message;
                 }
                 Swal.fire('Erro', 'Falha ao salvar: ' + msg, 'error');
             }
         });
     } */

    // Função corrigida para salvar Reprocesso
    /* function enviarReprocessoParaBackend() {
         // 1. Pega os dados brutos
         let dadosForm = $('#form-recebimento-detalhe').serializeArray();
 
         // 2. Pega os valores REAIS dos campos de Reprocesso
         let loteId = $('.select2-lotes-finalizados').val();
         let prodId = $('#select-produto-origem').val();
 
         // 3. LIMPEZA CRÍTICA: Remove campos duplicados ou vazios que causam confusão
         // Removemos o 'item_receb_produto_id' original (que vem da aba Matéria Prima e está vazio ou oculto)
         dadosForm = dadosForm.filter(function (item) {
             return item.name !== 'item_receb_produto_id' &&
                 item.name !== 'item_receb_lote_origem_id' &&
                 item.name !== 'lote_origem_produto_id';
         });
 
         // 4. INJEÇÃO DOS DADOS CORRETOS
         // Aqui está o pulo do gato: Enviamos com o nome que o banco espera ('item_receb_produto_id')
         if (loteId) {
             dadosForm.push({ name: 'item_receb_lote_origem_id', value: loteId });
         }
 
         if (prodId) {
             // Mandamos o ID do produto de reprocesso como se fosse o produto principal
             dadosForm.push({ name: 'item_receb_produto_id', value: prodId });
         }
 
         // 5. Adiciona distribuição de estoque e token
         dadosForm.push({ name: 'distribuicao_estoque', value: JSON.stringify(distribuicaoBaixa) });
         let csrfToken = $('meta[name="csrf-token"]').attr('content');
         dadosForm.push({ name: 'csrf_token', value: csrfToken });
 
         // 6. Envia
         $.ajax({
             url: 'ajax_router.php?action=salvarReprocesso',
             type: 'POST',
             data: dadosForm,
             dataType: 'json',
             success: function (res) {
                 if (res.success) {
                     Swal.fire('Sucesso', res.message, 'success');
 
                     // Reseta tudo
                     $('#form-recebimento-detalhe')[0].reset();
                     $('.select2-lotes-finalizados').val(null).trigger('change');
                     $('#select-produto-origem').empty();
                     $('#item_receb_id').val('');
                     distribuicaoBaixa = [];
 
                     // Força volta para Matéria Prima visualmente
                     $('#entrada_mp_materia').prop('checked', true).trigger('change');
 
                     // Atualiza tabelas
                     if (typeof carregarItensRecebimento === 'function') carregarItensRecebimento(loteIdAtual);
                     if (typeof recarregarTabelaLotes === 'function') recarregarTabelaLotes();
 
                 } else {
                     Swal.fire('Erro', res.message, 'error');
                 }
             },
             error: function (xhr) {
                 let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Erro desconhecido';
                 Swal.fire('Erro', 'Falha ao salvar: ' + msg, 'error');
             }
         });
     } */


    /* function enviarReprocessoParaBackend() {
         let dadosForm = $('#form-recebimento-detalhe').serializeArray();
 
         let loteId = $('.select2-lotes-finalizados').val();
         let prodId = $('#select-produto-origem').val();
 
         // Limpeza de campos duplicados ou inúteis
         dadosForm = dadosForm.filter(function (item) {
             return item.name !== 'item_receb_produto_id' &&
                 item.name !== 'item_receb_lote_origem_id' &&
                 item.name !== 'lote_origem_produto_id';
         });
 
         // INJEÇÃO MANUAL (Garante que o PHP receba os IDs)
         if (loteId) dadosForm.push({ name: 'item_receb_lote_origem_id', value: loteId });
         if (prodId) dadosForm.push({ name: 'item_receb_produto_id', value: prodId });
 
         // Estoque e Token
         dadosForm.push({ name: 'distribuicao_estoque', value: JSON.stringify(distribuicaoBaixa) });
         let csrfToken = $('meta[name="csrf-token"]').attr('content');
         dadosForm.push({ name: 'csrf_token', value: csrfToken });
 
         // Debug: Veja no console o que está sendo enviado
         console.log("Enviando Reprocesso:", dadosForm);
 
         $.ajax({
             url: 'ajax_router.php?action=salvarReprocesso',
             type: 'POST',
             data: dadosForm,
             dataType: 'json',
             success: function (res) {
                 if (res.success) {
                     Swal.fire('Sucesso', res.message, 'success');
 
                     // Reset Total (Função que criamos antes)
                     if (typeof resetarFormularioRecebimento === 'function') {
                         resetarFormularioRecebimento();
                     } else {
                         // Fallback se a função não existir
                         $('#form-recebimento-detalhe')[0].reset();
                         window.location.reload();
                     }
 
                     if (loteIdAtual) carregarItensRecebimento(loteIdAtual);
                     recarregarTabelaLotes();
 
                 } else {
                     Swal.fire('Erro', res.message, 'error');
                 }
             },
             error: function (xhr) {
                 let msg = 'Erro desconhecido';
                 if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                 // Mostra erro detalhado na tela para facilitar
                 Swal.fire('Erro 500', 'Falha ao salvar. Verifique se o produto está selecionado. <br>Detalhe: ' + msg, 'error');
             }
         });
     } */

    function enviarReprocessoParaBackend() {
        let dadosForm = $('#form-recebimento-detalhe').serializeArray();

        // Valores dos campos Select2
        let loteId = $('.select2-lotes-finalizados').val();
        let prodId = $('#select-produto-origem').val();

        // 1. VALIDAÇÃO PRÉVIA (Evita enviar requisição se estiver vazio)
        if (!loteId) {
            Swal.fire('Atenção', 'Selecione o Lote de Origem.', 'warning');
            return;
        }
        if (!prodId) {
            Swal.fire('Atenção', 'Selecione o Produto para reprocessar.', 'warning');
            return;
        }

        // 2. LIMPEZA (Remove campos vazios ou duplicados que vêm do HTML oculto)
        dadosForm = dadosForm.filter(function (item) {
            return item.name !== 'item_receb_produto_id' &&
                item.name !== 'item_receb_lote_origem_id' &&
                item.name !== 'lote_origem_produto_id';
        });

        // 3. INJEÇÃO DOS DADOS (Envia com os dois nomes para garantir que o PHP pegue)
        // Lote Origem
        dadosForm.push({ name: 'item_receb_lote_origem_id', value: loteId });

        // Produto (Envia duplicado intencionalmente para casar com qualquer lógica do PHP)
        dadosForm.push({ name: 'item_receb_produto_id', value: prodId });
        dadosForm.push({ name: 'lote_origem_produto_id', value: prodId });

        // Estoque e Token
        dadosForm.push({ name: 'distribuicao_estoque', value: JSON.stringify(distribuicaoBaixa) });
        let csrfToken = $('meta[name="csrf-token"]').attr('content');
        dadosForm.push({ name: 'csrf_token', value: csrfToken });

        console.log("Enviando Reprocesso (Payload):", dadosForm);

        $.ajax({
            url: 'ajax_router.php?action=salvarReprocesso',
            type: 'POST',
            data: dadosForm,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire('Sucesso', res.message, 'success');

                    // Reset da tela
                    if (typeof resetarFormularioRecebimento === 'function') {
                        resetarFormularioRecebimento();
                    } else {
                        $('#form-recebimento-detalhe')[0].reset();
                        $('.select2-lotes-finalizados').val(null).trigger('change');
                        $('#select-produto-origem').empty();
                        $('#entrada_mp_materia').prop('checked', true).trigger('change');
                    }

                    if (loteIdAtual) recarregarItensRecebimento(loteIdAtual);
                    recarregarTabelaLotes();

                } else {
                    Swal.fire('Erro', res.message, 'error');
                }
            },
            error: function (xhr) {
                let msg = 'Erro desconhecido';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire('Erro', 'Falha ao salvar.<br>' + msg, 'error');
            }
        });
    }

    // Função para salvar Matéria Prima (Fluxo Padrão)
    function salvarRecebimentoNormal() {
        var formData = $('#form-recebimento-detalhe').serialize();
        // Adiciona token CSRF
        formData += '&csrf_token=' + $('meta[name="csrf-token"]').attr('content');

        // Verifica se é edição ou inserção
        var action = modoEdicao ? 'editarItemRecebimento' : 'adicionarItemRecebimento';

        $.ajax({
            url: 'ajax_router.php?action=' + action,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire('Sucesso', response.message, 'success');

                    // Reset total
                    resetarFormularioRecebimento();

                    // Recarrega tabelas
                    if (loteIdAtual) recarregarItensRecebimento(loteIdAtual);
                    recarregarTabelaLotes();
                } else {
                    notificacaoErro('Erro', response.message);
                }
            },
            error: function () {
                notificacaoErro('Erro', 'Erro ao processar requisição.');
            }
        });
    }

    // Função auxiliar para limpar tudo e voltar ao estado inicial
    function resetarFormularioRecebimento() {
        $('#form-recebimento-detalhe')[0].reset();
        $('#item_receb_id').val('');
        $('#item_receb_lote_origem_id').val('');

        // Limpa Select2
        $('.select2-lotes-finalizados').val(null).trigger('change');
        $('#select-produto-origem').empty();
        $('#item_receb_produto_id').val(null).trigger('change');

        // Reseta variáveis globais
        modoEdicao = false;
        dadosOriginaisEdicao = null;
        distribuicaoBaixa = [];

        // Volta visualmente para Matéria Prima
        $('#entrada_mp_materia').prop('checked', true).trigger('change');

        // Restaura botões
        $('#btn-adicionar-item-recebimento')
            .html('<i class="fas fa-plus"></i> Salvar')
            .removeClass('btn-warning').addClass('btn-success');
        $('#btn-cancelar-edicao').addClass('d-none');
    }

    // Botão Cancelar Edição
    $('#btn-cancelar-edicao').on('click', function () {
        resetarFormularioRecebimento();
    });

    // Ao fechar o modal, reseta tudo
    $('#modal-lote-novo').on('hidden.bs.modal', function () {
        resetarFormularioRecebimento();
    });

    // Função para carregar e desenhar a tabela de itens do recebimento
    /* function carregarItensRecebimento(loteId) {
         if (!loteId) return;
 
         // Limpa a tabela e mostra um "Carregando..."
         const $tbody = $('#tabela-itens-recebimento');
         $tbody.html('<tr><td colspan="7" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Carregando itens...</td></tr>');
 
         $.ajax({
             url: 'ajax_router.php?action=getItensRecebimento',
             type: 'GET',
             data: { lote_id: loteId },
             dataType: 'json',
             success: function (res) {
                 $tbody.empty(); // Limpa o loading
 
                 if (res.success && res.data && res.data.length > 0) {
                     let html = '';
 
                     res.data.forEach(function (item) {
                         // Formata valores
                         let peso = formatarBR(item.item_receb_peso_nota_fiscal, 3);
                         let caixas = item.item_receb_total_caixas || 0;
                         let medio = formatarBR(item.item_receb_peso_medio_ind, 2);
 
                         // Lógica visual para diferenciar MP de Reprocesso
                         let origemBadge = '<span class="badge bg-success">Matéria Prima</span>';
                         let prodDisplay = item.prod_descricao;
 
                         if (item.item_receb_lote_origem_id) {
                             // Se for reprocesso, mostra de qual lote veio
                             let loteRef = item.lote_origem_numero ? item.lote_origem_numero : item.item_receb_lote_origem_id;
                             origemBadge = `<span class="badge bg-warning text-dark" title="Reprocesso do Lote ${loteRef}">Reprocesso</span>`;
                             prodDisplay += `<br><small class="text-muted"><i class="fas fa-history"></i> Lote Origem: ${loteRef}</small>`;
                         }
 
                         html += `
                             <tr>
                                 <td>${prodDisplay}</td>
                                 <td class="text-center align-middle">${origemBadge}</td>
                                 <td class="text-center align-middle">${item.item_receb_nota_fiscal || '-'}</td>
                                 <td class="text-end align-middle">${peso} kg</td>
                                 <td class="text-center align-middle">${caixas}</td>
                                 <td class="text-end align-middle">${medio} kg</td>
                                 <td class="text-center align-middle">
                                     <div class="btn-group btn-group-sm">
                                         <button type="button" class="btn btn-outline-primary btn-editar-item-recebimento" 
                                                 data-id="${item.item_receb_id}" title="Editar">
                                             <i class="fas fa-edit"></i>
                                         </button>
                                         <button type="button" class="btn btn-outline-danger btn-excluir-item-recebimento" 
                                                 data-id="${item.item_receb_id}" title="Excluir">
                                             <i class="fas fa-trash-alt"></i>
                                         </button>
                                     </div>
                                 </td>
                             </tr>
                         `;
                     });
 
                     $tbody.html(html);
                 } else {
                     $tbody.html('<tr><td colspan="7" class="text-center text-muted py-3">Nenhum item lançado neste lote.</td></tr>');
                 }
             },
             error: function () {
                 $tbody.html('<tr><td colspan="7" class="text-center text-danger">Erro ao carregar itens.</td></tr>');
             }
         });
     } */

    // --- Torna a função global (opcional, mas recomendável para debug) ---
    /* window.carregarItensRecebimento = carregarItensRecebimento;*/

    // ======================================================
    // CÁLCULO AUTOMÁTICO: Peso x Peso Médio = Caixas
    // ======================================================
    $('#item_receb_peso_nota_fiscal, #item_receb_peso_medio_ind').on('input', function () {

        // 1. Pega os valores e converte de BR (1.200,50) para Float (1200.50)
        let pesoTotal = parseFloat($('#item_receb_peso_nota_fiscal').val().replace(/\./g, '').replace(',', '.')) || 0;
        let pesoMedio = parseFloat($('#item_receb_peso_medio_ind').val().replace(/\./g, '').replace(',', '.')) || 0;

        // 2. Só calcula se tiver peso médio válido (evita divisão por zero)
        if (pesoMedio > 0) {
            let caixas = pesoTotal / pesoMedio;

            // Arredonda para inteiro (opcional, depende da sua regra de negócio)
            // Se aceitar meia caixa, use .toFixed(2)
            let caixasArredondado = Math.round(caixas);

            // 3. Joga o valor no input de Caixas
            $('#item_receb_total_caixas').val(caixasArredondado);
        }
    });

    // =========================================================================
    // CORREÇÃO FINAL: CÁLCULO IMEDIATO (Sem Delay e Compatível com Máscaras)
    // =========================================================================

    // 1. Ao selecionar o produto, preenchemos o peso médio visualmente
    $('#select-produto-origem').on('select2:select', function (e) {
        const data = e.params.data;
        let pesoPadrao = parseFloat(data.prod_peso_embalagem) || 0;

        console.log("Produto Selecionado:", data.text, "| Peso Padrão:", pesoPadrao);

        if (pesoPadrao === 0) {
            notificacaoErro('Atenção', 'Este produto não tem peso de embalagem definido no cadastro. O cálculo automático não funcionará.');
            $('#item_receb_peso_medio_ind').val('');
        } else {
            // Preenche o campo visual. O cálculo vai ler deste campo depois.
            $('#item_receb_peso_medio_ind').val(formatarBR(pesoPadrao, 3));
        }

        // Tenta calcular imediatamente
        calcularCaixasReprocesso();
    });

    // 2. Monitora TODOS os eventos de digitação para garantir resposta imediata
    // O setTimeout(..., 50) é o segredo para funcionar com máscaras de dinheiro
    $('#item_receb_peso_nota_fiscal, #item_receb_peso_medio_ind').on('keyup input change paste', function () {
        setTimeout(function () {
            calcularCaixasReprocesso();
        }, 50);
    });

    // Função de Cálculo Robusta
    function calcularCaixasReprocesso() {
        // Pega o valor VISUAL do campo Peso Total
        let valorPesoTotal = $('#item_receb_peso_nota_fiscal').val() || '';
        // Remove pontos de milhar e troca vírgula por ponto
        let pesoTotal = parseFloat(valorPesoTotal.replace(/\./g, '').replace(',', '.')) || 0;

        // Pega o valor VISUAL do campo Peso Médio (Melhor que usar variável global)
        let valorPesoMedio = $('#item_receb_peso_medio_ind').val() || '';
        let pesoBase = parseFloat(valorPesoMedio.replace(/\./g, '').replace(',', '.')) || 0;

        // Só calcula se tivermos os dois valores positivos
        if (pesoTotal > 0 && pesoBase > 0) {
            let caixas = pesoTotal / pesoBase;
            let caixasArredondado = Math.round(caixas);

            // Só atualiza o campo se o valor for diferente (evita loop ou piscar tela)
            if ($('#item_receb_total_caixas').val() != caixasArredondado) {
                $('#item_receb_total_caixas').val(caixasArredondado);
            }
        }
    }

}); 
