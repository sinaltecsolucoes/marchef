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
        return $.ajax({
            url: 'ajax_router.php?action=listarProdutosPrimarios',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $produtoPrimarioSelect.empty().append('<option value="">Selecione...</option>');
                    response.data.forEach(function (produto) {
                        // Lógica para adicionar o Cód. Interno APENAS se ele existir (não for nulo ou vazio)
                        const codigoInternoTexto = produto.prod_codigo_interno ? ` (Cód: ${produto.prod_codigo_interno})` : '';

                        // O texto final é apenas a descrição (que já tem o peso) + o código interno (se existir)
                        const optionText = `${produto.prod_descricao}${codigoInternoTexto}`;

                        $produtoPrimarioSelect.append(new Option(optionText, produto.prod_codigo));
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

    // Controla o texto do switch Ativo/Inativo
    $modalProduto.on('change', '#prod_situacao', function () {
        const isChecked = $(this).is(':checked');
        $('#label-prod-situacao').text(isChecked ? 'Ativo' : 'Inativo');
    });

    // =================================================================
    // INICIALIZAÇÃO DA TABELA DATATABLES
    // =================================================================
    const tableProdutos = $('#tabela-produtos').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarProdutos",
            "type": "POST",
            "data": function (d) {
                d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val();
            }
        },
        "responsive": true,
        "columns": [
            {
                "data": "prod_situacao",
                "className": "text-center align-middle",
                "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'
            },
            {
                "data": "prod_codigo_interno",
                "className": "text-center align-middle"
            },
            {
                "data": "prod_descricao",
                "className": "align-middle"
            },
            {
                "data": "prod_tipo",
                "className": "text-center align-middle"
            },
            {
                "data": "prod_tipo_embalagem",
                "className": "text-center align-middle"
            },
            {
                "data": "prod_peso_embalagem",
                "className": "text-center align-middle"
            },
            {
                "data": "prod_codigo",
                "orderable": false,
                "className": "text-center align-middle",
                "render": (data) =>
                    `<div class="btn-group" role="group">
                        <a href="#" class="btn btn-warning btn-sm btn-editar-produto me-1" data-id="${data}"><i class="fas fa-pencil-alt me-1"></i>Editar</a>
                        <a href="#" class="btn btn-info btn-sm btn-copiar-produto me-1" data-id="${data}" title="Copiar/Duplicar"><i class="fas fa-copy me-1"></i>Copiar</a> 
                        <a href="#" class="btn btn-danger btn-sm btn-excluir-produto me-1" data-id="${data}"><i class="fas fa-trash-alt me-1"></i>Excluir</a>
                    </div>`
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    $('input[name="filtro_situacao"]').on('change', () => tableProdutos.ajax.reload());
    $tipoEmbalagemSelect.on('change', toggleEmbalagemFields);
    $pesoEmbalagemSecundariaInput.on('keyup', calcularUnidades);

    // Abrir modal para Adicionar
    $('#btn-adicionar-produto-main').on('click', function () {
        // Este é o "Resetador" oficial para o modo ADICIONAR
        $formProduto[0].reset();
        $('#prod_codigo').val(''); // Garante que o ID esteja limpo
        $('#modal-adicionar-produto-label').text('Adicionar Produto');

        // Força o reset dos campos que o .reset() pode não pegar:
        $('#prod_situacao').prop('checked', true).trigger('change');

        // Força o dropdown para "PRIMARIA" e dispara o evento 'change'
        // O trigger('change') chama a função toggleEmbalagemFields(), 
        // que esconde os blocos "Secundária" e mostra os blocos "Primária".
        $tipoEmbalagemSelect.val('PRIMARIA').trigger('change');

        $modalProduto.modal('show');
    });

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
            $('#prod_categoria').val(produto.prod_categoria);
            $('#prod_validade_meses').val(produto.prod_validade_meses);
            $('#peso_embalagem_secundaria').val('');
            $('#prod_dun14').val('');
            $('#prod_ean13').val('');
            $('#prod_descricao').focus();
            calcularUnidades();
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
        }).done(function (response) {
            if (response.success) {
                $modalProduto.modal('hide');
                tableProdutos.ajax.reload(null, false);
                notificacaoSucesso('Sucesso!', response.message); // << REATORADO
            } else {
                notificacaoErro('Erro ao Salvar', response.message); // << REATORADO
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar o produto.'); // << REATORADO
        });
    });

    // Gatilho para o cálculo automático da Classe do Produto
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
        }).done(function (response) {
            if (response.success) {
                $formProduto[0].reset();
                const produto = response.data;
                Object.keys(produto).forEach(key => $formProduto.find(`[name="${key}"],#${key}`).val(produto[key]));
                atualizarClasseProduto();

                // Setar o switch de Ativo/Inativo
                const isAtivo = (produto.prod_situacao === 'A');
                $('#prod_situacao')
                    .val('A')
                    .prop('checked', isAtivo)
                    .trigger('change');

                // Dispara a mudança de embalagem, o que também chama o loadProdutosPrimarios se for SECUNDARIA
                $tipoEmbalagemSelect.val(produto.prod_tipo_embalagem).trigger('change');

                // Se for secundária, esperamos o carregamento terminar ANTES de definir o valor.
                if (produto.prod_tipo_embalagem === 'SECUNDARIA') {
                    $('#peso_embalagem_secundaria').val(produto.prod_peso_embalagem);

                    // A função 'loadProdutosPrimarios' agora retorna uma promessa.
                    // O código dentro do .done() só executa quando a lista estiver 100% carregada.
                    loadProdutosPrimarios().done(function () {
                        $('#prod_primario_id').val(produto.prod_primario_id).trigger('change.select2');
                        calcularUnidades();
                    });
                } else {
                    $('#prod_peso_embalagem').val(produto.prod_peso_embalagem);
                }

                $('#modal-adicionar-produto-label').text('Editar Produto');
                $modalProduto.modal('show');
            } else {
                notificacaoErro('Erro!', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível carregar os dados do produto.');
        });
    });

    $('#tabela-produtos').on('click', '.btn-excluir-produto', function (e) {
        e.preventDefault();
        const idProduto = $(this).data('id');
        confirmacaoAcao(
            'Excluir Produto?',
            'Tem a certeza de que deseja excluir este produto?'
        ).then((result) => {
            if (result.isConfirmed) {
                // Se o usuário confirmar, executa a exclusão
                $.ajax({
                    url: 'ajax_router.php?action=excluirProduto',
                    type: 'POST',
                    data: { prod_codigo: idProduto }, // CSRF é adicionado pelo prefilter
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        tableProdutos.ajax.reload();
                        notificacaoSucesso('Excluído!', response.message);
                    } else {
                        notificacaoErro('Erro ao Excluir', response.message);
                    }
                }).fail(function () {
                    notificacaoErro('Erro de Comunicação', 'Não foi possível excluir o produto.');
                });
            }
        });
    });

    // ### LISTENER PARA O BOTÃO COPIAR ###
    $('#tabela-produtos').on('click', '.btn-copiar-produto', function (e) {
        e.preventDefault();
        const idProduto = $(this).data('id');

        // 1. Usamos a mesma rota do "Editar" para buscar os dados completos do produto
        $.ajax({
            url: 'ajax_router.php?action=getProduto',
            type: 'POST',
            data: {
                prod_codigo: idProduto,
                csrf_token: csrfToken // Token global já definido no seu JS
            },
            dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                $formProduto[0].reset();
                const produto = response.data;
                Object.keys(produto).forEach(key => $formProduto.find(`[name="${key}"],#${key}`).val(produto[key]));

                // 2. Limpamos o ID do produto.
                // Isso garante que, ao salvar, o backend entenda como um INSERT (Criar), não um UPDATE (Editar).
                $('#prod_codigo').val('');

                // 3. UX: Forçamos o usuário a alterar os campos únicos
                const descricaoAtual = $('#prod_descricao').val();
                $('#prod_descricao').val(descricaoAtual + ' (CÓPIA)');
                $('#prod_codigo_interno').val(''); // Limpa o código interno

                // 4. Acionamos as funções de atualização do formulário (como o "Editar" faz)
                atualizarClasseProduto();
                $tipoEmbalagemSelect.val(produto.prod_tipo_embalagem).trigger('change');

                $('#prod_situacao')
                    .val('A') // Garante que o valor (value) seja "A" 
                    .prop('checked', true) // Marca como checado
                    .trigger('change'); // Atualiza o label (que dirá "Ativo")

                if (produto.prod_tipo_embalagem === 'SECUNDARIA') {
                    $('#peso_embalagem_secundaria').val(produto.prod_peso_embalagem);
                    loadProdutosPrimarios().done(function () {
                        $('#prod_primario_id').val(produto.prod_primario_id).trigger('change.select2');
                        calcularUnidades();
                    });
                } else {
                    $('#prod_peso_embalagem').val(produto.prod_peso_embalagem);
                }

                // 5. Mudamos o título do modal e o abrimos
                $('#modal-adicionar-produto-label').text('Copiar Produto (Criando Novo)');
                $modalProduto.modal('show');
            } else {
                notificacaoErro('Erro!', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível carregar os dados do produto para cópia.');
        });
    });
});