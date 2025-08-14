// /public/js/lotes_novo.js
$(document).ready(function () {

    // --- Seletores e Variáveis Globais ---
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalLoteNovo = $('#modal-lote-novo');
    const $formHeader = $('#form-lote-novo-header');
    let tabelaLotesNovo, loteIdAtual;

    // --- Inicialização da Tabela DataTables ---
    tabelaLotesNovo = $('#tabela-lotes-novo').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": "ajax_router.php?action=listarLotesNovos",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            { "data": "lote_completo_calculado" },
            { "data": "fornecedor_razao_social" },
            {
                "data": "lote_data_fabricacao",
                "className": "text-center",
                "render": function (data) {
                    if (!data) return '';
                    return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "lote_status",
                "className": "text-center",
                "render": function (data) {
                    let badgeClass = 'bg-secondary';
                    if (data === 'EM ANDAMENTO') badgeClass = 'bg-warning text-dark';
                    if (data === 'FINALIZADO') badgeClass = 'bg-success';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            {
                "data": "lote_data_cadastro",
                "className": "text-center",
                "render": function (data) {
                    return new Date(data).toLocaleString('pt-BR');
                }
            },
            {
                "data": "lote_id",
                "orderable": false,
                "className": "text-center",
                "render": function (data) {
                    return `<button class="btn btn-warning btn-sm btn-editar-lote-novo" data-id="${data}">Editar</button>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[4, 'desc']]
    });

    // --- Funções Auxiliares ---

    // Função para carregar Fornecedores (reutilizada do ajax_router)
    function carregarFornecedores() {
        return $.get('ajax_router.php?action=getFornecedorOptions').done(function (response) {
            if (response.success) {
                const $select = $('#lote_fornecedor_id_novo');
                $select.empty().append('<option value="">Selecione...</option>');
                response.data.forEach(function (fornecedor) {
                    $select.append(new Option(fornecedor.ent_razao_social, fornecedor.ent_codigo));
                });
            }
        });
    }

    // Função para carregar Clientes (reutilizada do ajax_router)
    function carregarClientes() {
        return $.get('ajax_router.php?action=getClienteOptions').done(function (response) {
            if (response.success) {
                const $select = $('#lote_cliente_id_novo');
                $select.empty().append('<option value="">Selecione...</option>');
                response.data.forEach(function (cliente) {
                    //$select.append(new Option(cliente.ent_razao_social, cliente.ent_codigo));
                    const option = new Option(cliente.ent_razao_social, cliente.ent_codigo);
                    $(option).data('codigo-interno', cliente.ent_codigo_interno);
                    $select.append(option);
                });
            }
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
    function buscarDadosLoteParaEdicao(loteId) {
        $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteId, csrf_token: csrfToken }, // Envia 'lote_id'
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const lote = response.data;
                const header = lote.header;

                $('#lote_id_novo').val(header.lote_id);
                $('#lote_numero_novo').val(header.lote_numero);
                $('#lote_data_fabricacao_novo').val(header.lote_data_fabricacao);
                $('#lote_ciclo_novo').val(header.lote_ciclo);
                $('#lote_viveiro_novo').val(header.lote_viveiro);
                $('#lote_completo_calculado_novo').val(header.lote_completo_calculado);

                // Define os valores e dispara o 'change' para o Select2 atualizar
                $('#lote_fornecedor_id_novo').val(header.lote_fornecedor_id).trigger('change');
                $('#lote_cliente_id_novo').val(header.lote_cliente_id).trigger('change');

                $('#btn-salvar-lote-novo-header').text('Salvar Alterações');
                $('#modal-lote-novo-label').text('Editar Lote: ' + header.lote_completo_calculado);

                // Habilita as outras abas para navegação
                $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').removeClass('disabled');
                new bootstrap.Tab($('#aba-info-lote-novo-tab')[0]).show();

                // (No futuro, aqui chamaremos a função para preencher as tabelas das outras abas)
                $modalLoteNovo.modal('show');
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    }

    // --- Event Handlers ---

    // Evento para o botão "Adicionar Novo Lote"
    $('#btn-adicionar-lote-novo').on('click', function () {
        $(this).blur();

        // 1. Limpa o formulário e o modal
        $formHeader[0].reset();
        $('#lote_id_novo').val('');
        $('#modal-lote-novo-label').text('Adicionar Novo Lote');

        // Inicializa os dropdowns do modal
        $('#lote_fornecedor_id_novo, #lote_cliente_id_novo').select2({
            placeholder: 'Selecione uma opção',
            dropdownParent: $modalLoteNovo,
            theme: "bootstrap-5"
        });

        // Carrega os dados para os dropdowns
        carregarFornecedores();
        carregarClientes();
        carregarProdutos();

        // Busca o próximo número de lote
        $.get('ajax_router.php?action=getProximoNumeroLoteNovo', function (response) {
            if (response.success) {
                $('#lote_numero_novo').val(response.proximo_numero);
                atualizarLoteCompletoNovo(); // Calcula o lote inicial
            } else {
                $('#lote_numero').val('Erro!');
            }
        });
        // 2. Desabilita as abas de produção e embalagens e ativa a primeira
        new bootstrap.Tab($('#aba-info-lote-novo-tab')[0]).show();
        $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').addClass('disabled');

        $modalLoteNovo.modal('show');
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

                // Habilita as próximas abas
                $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').removeClass('disabled');
                new bootstrap.Tab($('#aba-producao-novo-tab')[0]).show(); // Move o utilizador para a próxima aba
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    });

    // Ação para o botão "Editar" na tabela principal de lotes 
    $('#tabela-lotes-novo').on('click', '.btn-editar-lote-novo', function () {
        loteIdAtual = $(this).data('id');
        // Garante que os dropdowns estejam prontos antes de buscar os dados
        $.when(carregarFornecedores(), carregarClientes()).done(function () {
            buscarDadosLoteParaEdicao(loteIdAtual);
        });
    });

    $formHeader.on('change keyup', 'input, select', function (event) {
        // Impede que o cálculo seja refeito quando o próprio campo de lote completo é alterado (se um dia for editável)
        if (event.target.id === 'lote_completo_calculado_novo') {
            return;
        }
        atualizarLoteCompletoNovo();
    });

});