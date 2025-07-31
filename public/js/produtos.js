$(document).ready(function () {
    // =================================================================
    // Bloco de Configuração e Seletores Globais
    // =================================================================
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalProduto = $('#modal-adicionar-produto');
    const $formProduto = $('#form-produto');
    const $tipoEmbalagemSelect = $('#prod_tipo_embalagem');
    const $blocoEmbalagemSecundaria = $('#bloco-embalagem-secundaria');
    const $blocoEmbalagemPrimaria = $('#bloco-embalagem-primaria');
    const $blocoEan13 = $('#bloco-ean13');
    const $blocoDun14 = $('#bloco-dun14');
    const $produtoPrimarioSelect = $('#prod_primario_id');
    const $pesoEmbalagemSecundariaInput = $('#peso_embalagem_secundaria');
    const $unidadesPrimariasInput = $('#unidades_primarias');
    let produtoPrimarioCache = {};

    // =================================================================
    // INICIALIZAÇÃO DO SELECT2 (FUNÇÃO DE BUSCA NO DROPDOWN)
    // =================================================================
    $produtoPrimarioSelect.select2({
        placeholder: 'Digite para buscar um produto...',
        language: "pt-BR",
        theme: "bootstrap-5",
        // Esta opção é CRUCIAL para que a busca funcione dentro de um modal do Bootstrap
        dropdownParent: $modalProduto
    });

    // Configura todas as chamadas AJAX para enviar o token CSRF
    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
        if (!options.crossDomain && originalOptions.type && originalOptions.type.toLowerCase() === 'post') {
            if (typeof options.data === 'string' && options.data.indexOf('csrf_token') === -1) {
                options.data += (options.data ? '&' : '') + 'csrf_token=' + encodeURIComponent(csrfToken);
            } else if (options.data instanceof FormData) {
                if (!options.data.has('csrf_token')) {
                    options.data.append('csrf_token', csrfToken);
                }
            }
        }
    });

    // =================================================================
    // Funções Auxiliares
    // =================================================================
    /**
    * Calcula e atualiza o campo 'Classe' com base em outros campos do formulário.
    */
    function atualizarClasseProduto() {
        const tipo = $('#prod_tipo').val();
        const subtipo = $('#prod_subtipo').val().toUpperCase(); // Normaliza para maiúsculas
        const conservacao = $('#prod_conservacao').val();
        const congelamento = $('#prod_congelamento').val();

        let partes = [];

        // 1. Tipo do Produto
        if (tipo) partes.push(tipo);

        // 2. Adiciona "CINZA" se for Camarão
        if (tipo === 'CAMARAO') partes.push('CINZA');

        // 3. Condicional para Subtipo
        let subtipoFormatado = '';
        switch (subtipo) {
            case 'PUD':
                subtipoFormatado = 'DESCASCADO';
                break;
            case 'P&D':
            case 'PPV':
                subtipoFormatado = 'DESCASCADO EVISCERADO';
                break;
            case 'P&D C/ CAUDA':
            case 'PPV C/ CAUDA':
                subtipoFormatado = 'DESCASCADO EVISCERADO COM CAUDA';
                break;
            case 'PUD C/ CAUDA':
                subtipoFormatado = 'DESCASCADO COM CAUDA';
                break;
            default:
                subtipoFormatado = subtipo; // Usa o valor original se não houver regra
        }
        if (subtipoFormatado) partes.push(subtipoFormatado);

        // 4. Condicional para Conservação
        let conservacaoFormatada = '';
        if (conservacao === 'COZIDO') {
            conservacaoFormatada = 'COZIDO';
        } else if (conservacao === 'PARC. COZIDO') {
            conservacaoFormatada = 'PARCIALMENTE COZIDO';
        }
        if (conservacaoFormatada) partes.push(conservacaoFormatada);

        // 5. Condicional para Congelamento
        if (congelamento === 'IQF' || congelamento === 'BLOCO') {
            partes.push('CONGELADO');
        }

        // Junta todas as partes com um espaço e atualiza o campo
        $('#prod_classe').val(partes.join(' ').toUpperCase());
    }

    function showFeedbackMessage(msg, type = 'success', area = '#feedback-message-area-produto') {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        $(area).empty().removeClass('alert-success alert-danger').addClass(`alert ${alertClass}`).text(msg).fadeIn();
        setTimeout(() => $(area).fadeOut('slow'), 5000);
    }

    function toggleEmbalagemFields() {
        const tipo = $tipoEmbalagemSelect.val();
        const isSecundaria = (tipo === 'SECUNDARIA');

        $blocoEmbalagemSecundaria.toggle(isSecundaria);
        $blocoEmbalagemPrimaria.toggle(!isSecundaria);
        $blocoEan13.toggle(!isSecundaria);
        $blocoDun14.toggle(isSecundaria);
        $('#asterisco-codigo-interno').toggle(isSecundaria);
        $('#prod_codigo_interno').prop('required', isSecundaria);

        if (isSecundaria) {
            loadProdutosPrimarios();
        } else {
            $('#prod_dun14').val('');
            $produtoPrimarioSelect.val('').trigger('change');
        }
    }

    function loadProdutosPrimarios() {
        $.ajax({
            url: 'ajax_router.php?action=listarProdutosPrimarios',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $produtoPrimarioSelect.empty().append('<option value="">Selecione...</option>');
                    response.data.forEach(function (produto) {
                        $produtoPrimarioSelect.append(new Option(produto.prod_descricao + ' (' + produto.prod_peso_embalagem + ' kg)', produto.prod_codigo));
                        produtoPrimarioCache[produto.prod_codigo] = produto;
                    });
                    $produtoPrimarioSelect.trigger('change.select2');
                }
            }
        });
    }

    function calcularUnidades() {
        const produtoPrimarioId = $produtoPrimarioSelect.val();
        const pesoSecundario = parseFloat($pesoEmbalagemSecundariaInput.val());
        if (produtoPrimarioId && pesoSecundario > 0 && produtoPrimarioCache[produtoPrimarioId]) {
            const pesoPrimario = parseFloat(produtoPrimarioCache[produtoPrimarioId].prod_peso_embalagem);
            if (pesoPrimario > 0) {
                $unidadesPrimariasInput.val((pesoSecundario / pesoPrimario).toFixed(2));
            }
        } else {
            $unidadesPrimariasInput.val('');
        }
    }

    const tableProdutos = $('#tabela-produtos').DataTable({
        "serverSide": true,
        "ajax": { "url": "ajax_router.php?action=listarProdutos", "type": "POST", "data": function (d) { d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val(); } },
        "responsive": true,
        "columns": [
            { "data": "prod_situacao", "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' },
            { "data": "prod_codigo_interno" }, { "data": "prod_descricao" }, { "data": "prod_tipo" },
            { "data": "prod_tipo_embalagem" }, { "data": "prod_peso_embalagem" },
            { "data": "prod_codigo", "orderable": false, "render": (data) => `<a href="#" class="btn btn-warning btn-sm btn-editar-produto" data-id="${data}">Editar</a> <a href="#" class="btn btn-danger btn-sm btn-excluir-produto" data-id="${data}">Excluir</a>` }
        ],
        "language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    $('input[name="filtro_situacao"]').on('change', () => tableProdutos.ajax.reload());
    $tipoEmbalagemSelect.on('change', toggleEmbalagemFields);
    $pesoEmbalagemSecundariaInput.on('keyup', calcularUnidades);

    $produtoPrimarioSelect.on('change', function () {
        const selectedId = $(this).val();
        if (selectedId && produtoPrimarioCache[selectedId]) {
            const produto = produtoPrimarioCache[selectedId];
            $('#prod_descricao').val(produto.prod_descricao + ' (Caixa)');
            $('#prod_tipo').val(produto.prod_tipo);
            $('#prod_subtipo').val(produto.prod_subtipo);
            $('#prod_classificacao').val(produto.prod_classificacao);
            $('#prod_especie').val(produto.prod_especie);
            $('#prod_origem').val(produto.prod_origem);
            $('#prod_conservacao').val(produto.prod_conservacao);
            $('#prod_congelamento').val(produto.prod_congelamento);
            $('#prod_fator_producao').val(produto.prod_fator_producao);
            $('#prod_total_pecas').val(produto.prod_total_pecas);
            $('#peso_embalagem_secundaria').val('');
            $('#prod_dun14').val('');
            $('#prod_ean13').val('');
            $('#prod_descricao').focus();
            calcularUnidades();
        }
    });

    $modalProduto.on('show.bs.modal', function (event) {
        if ($(event.relatedTarget).is('#btn-adicionar-produto-main')) {
            $('#modal-adicionar-produto-label').text('Adicionar Produto');
            $formProduto[0].reset();
            $('#prod_codigo').val('');
            $tipoEmbalagemSelect.val('PRIMARIA').trigger('change');
        }
    });

    $formProduto.on('submit', function (e) {
        e.preventDefault();
        const id = $('#prod_codigo').val();
        const action = id ? 'editarProduto' : 'cadastrarProduto';
        const url = `ajax_router.php?action=${action}`;

        // desabilitamos o campo de código antes de enviar.
        // Campos desabilitados não são incluídos no submit.
        if (!id) {
            $('#prod_codigo').prop('disabled', true);
        }

        const formData = new FormData(this);

        // Reabilitamos o campo logo após pegar os dados,
        // para que ele funcione normalmente depois.
        if (!id) {
            $('#prod_codigo').prop('disabled', false);
        }

        $.ajax({
            url: url, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalProduto.modal('hide');
                    tableProdutos.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success');
                } else {
                    $('#mensagem-produto').removeClass().addClass('alert alert-danger').text(response.message);
                }
            }
        });
    });

    // Gatilho para o cálculo automático da Classe do Produto
   /* $('#form-produto').on('change', '#prod_tipo, #prod_subtipo, #prod_conservacao, #prod_congelamento', function () {
        atualizarClasseProduto();
    });*/

    $('#form-produto').on('change keyup', '#prod_tipo, #prod_subtipo, #prod_conservacao, #prod_congelamento', function () {
        atualizarClasseProduto();
    });

    $('#tabela-produtos').on('click', '.btn-editar-produto', function (e) {
        e.preventDefault();
        const idProduto = $(this).data('id');
        $.ajax({
            url: 'ajax_router.php?action=getProduto',
            type: 'POST',
            data: {
                prod_codigo: idProduto,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $formProduto[0].reset();
                    const produto = response.data;
                    Object.keys(produto).forEach(key => $formProduto.find(`[name="${key}"],#${key}`).val(produto[key]));
                    atualizarClasseProduto();
                    $tipoEmbalagemSelect.val(produto.prod_tipo_embalagem).trigger('change');
                    if (produto.prod_tipo_embalagem === 'SECUNDARIA') {
                        $('#peso_embalagem_secundaria').val(produto.prod_peso_embalagem);
                        setTimeout(() => {
                            $('#prod_primario_id').val(produto.prod_primario_id).trigger('change.select2');
                            calcularUnidades();
                        }, 500);
                    } else {
                        $('#prod_peso_embalagem').val(produto.prod_peso_embalagem);
                    }
                    $('#modal-adicionar-produto-label').text('Editar Produto');
                    $modalProduto.modal('show');
                } else { showFeedbackMessage(response.message, 'danger'); }
            }
        });
    });

    $('#tabela-produtos').on('click', '.btn-excluir-produto', function (e) {
        e.preventDefault();
        const idProduto = $(this).data('id');
        if (confirm('Tem certeza de que deseja excluir este produto?')) {
            $.ajax({
                url: 'ajax_router.php?action=excluirProduto',
                type: 'POST', data: { prod_codigo: idProduto }, dataType: 'json',
                success: function (response) {
                    if (response.success) { tableProdutos.ajax.reload(); }
                    alert(response.message);
                }
            });
        }
    });
});