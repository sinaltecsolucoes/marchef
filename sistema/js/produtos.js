$(document).ready(function () {
    // =================================================================
    // Bloco de Configuração Inicial
    // =================================================================
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
        if (!options.crossDomain) {
            jqXHR.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }
    });

    // =================================================================
    // Seletores Globais
    // =================================================================
    var $modalProduto = $('#modal-adicionar-produto');
    var $formProduto = $('#form-produto');
    var $tipoEmbalagemSelect = $('#prod_tipo_embalagem');
    var $blocoEmbalagemSecundaria = $('#bloco-embalagem-secundaria');
    var $blocoEmbalagemPrimaria = $('#bloco-embalagem-primaria');
    var $blocoDun14 = $('#bloco-dun14');
    var $produtoPrimarioSelect = $('#prod_primario_id');
    var $descricaoProduto = $('#prod_descricao');
    var $pesoEmbalagemSecundariaInput = $('#peso_embalagem_secundaria');
    var $unidadesPrimariasInput = $('#unidades_primarias');
    var produtoPrimarioCache = {}; // Cache para armazenar dados dos produtos primários

    // =================================================================
    // Inicialização do Select2 no Dropdown de Produto Primário
    // =================================================================
    $produtoPrimarioSelect.select2({
        placeholder: 'Digite para buscar um produto...',
        language: "pt-BR",
        theme: "bootstrap-5",
        // Esta opção é CRUCIAL para que o Select2 funcione dentro de um modal do Bootstrap
        dropdownParent: $modalProduto
    });

    // =================================================================
    // Função de Feedback ao Usuário
    // =================================================================
    function showFeedbackMessage(msg, type = 'success') {
        $('#feedback-message-area-produto').html('<div class="alert alert-' + type + '">' + msg + '</div>');
    }

    // =================================================================
    // Funções Auxiliares
    // =================================================================

    // Função para controlar a visibilidade dos campos com base no tipo de embalagem (VERSÃO ATUALIZADA)
    function toggleEmbalagemFields() {
        var tipo = $tipoEmbalagemSelect.val();
        var $inputCodigoInterno = $('#prod_codigo_interno');
        var $asteriscoCodigoInterno = $('#asterisco-codigo-interno');

        if (tipo === 'SECUNDARIA') {
            $blocoEmbalagemSecundaria.show();
            $blocoEmbalagemPrimaria.hide();
            $blocoDun14.show();

            // TORNA O CÓDIGO INTERNO OBRIGATÓRIO
            $asteriscoCodigoInterno.show();
            $inputCodigoInterno.prop('required', true);

            // Carrega os produtos primários no dropdown
            loadProdutosPrimarios();
        } else { // PRIMARIA
            $blocoEmbalagemSecundaria.hide();
            $blocoEmbalagemPrimaria.show();
            $blocoDun14.hide();

            // TORNA O CÓDIGO INTERNO OPCIONAL
            $asteriscoCodigoInterno.hide();
            $inputCodigoInterno.prop('required', false);
        }
    }

    // Função para carregar produtos primários no dropdown
    function loadProdutosPrimarios() {
        $.ajax({
            url: 'process/listar_produtos_primarios.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $produtoPrimarioSelect.empty().append('<option value="">Selecione...</option>');
                    response.data.forEach(function (produto) {
                        $produtoPrimarioSelect.append(
                            $('<option>', {
                                value: produto.prod_codigo,
                                text: produto.prod_descricao + ' (' + produto.prod_peso_embalagem + ' kg)',
                                'data-peso': produto.prod_peso_embalagem
                            })
                        );
                        produtoPrimarioCache[produto.prod_codigo] = produto;
                    });
                    // Informa ao Select2 para atualizar sua visualização após carregar novos dados.
                    // Isso é útil se os dados forem carregados depois da inicialização.
                    $produtoPrimarioSelect.trigger('change');
                }
            }
        });
    }

    // Função para calcular unidades
    function calcularUnidades() {
        var produtoPrimarioId = $produtoPrimarioSelect.val();
        var pesoSecundario = parseFloat($pesoEmbalagemSecundariaInput.val());

        if (produtoPrimarioId && pesoSecundario > 0 && produtoPrimarioCache[produtoPrimarioId]) {
            var pesoPrimario = parseFloat(produtoPrimarioCache[produtoPrimarioId].prod_peso_embalagem);
            if (pesoPrimario > 0) {
                var unidades = pesoSecundario / pesoPrimario;
                $unidadesPrimariasInput.val(unidades.toFixed(2));
            }
        } else {
            $unidadesPrimariasInput.val('');
        }
    }

    // =================================================================
    // Lógica de Eventos
    // =================================================================

    // Evento de mudança no tipo de embalagem
    $tipoEmbalagemSelect.on('change', toggleEmbalagemFields);

    // Evento de mudança no produto primário selecionado
    $produtoPrimarioSelect.on('change', function () {
        var selectedId = $(this).val();

        // Limpa o formulário se nenhum produto for selecionado
        if (!selectedId) {
            // Você pode adicionar aqui uma lógica para limpar os campos se desejar
            return;
        }

        if (produtoPrimarioCache[selectedId]) {
            var produto = produtoPrimarioCache[selectedId];

            // =================================================================
            // 1. PREENCHE OS CAMPOS HERDADOS DO PRODUTO PRIMÁRIO
            // =================================================================
            $('#prod_descricao').val(produto.prod_descricao + ' (Caixa)'); // Adiciona um sufixo
            $('#prod_tipo').val(produto.prod_tipo);
            $('#prod_subtipo').val(produto.prod_subtipo);
            $('#prod_classificacao').val(produto.prod_classificacao);
            $('#prod_especie').val(produto.prod_especie);
            $('#prod_origem').val(produto.prod_origem);
            $('#prod_conservacao').val(produto.prod_conservacao);
            $('#prod_congelamento').val(produto.prod_congelamento);
            $('#prod_fator_producao').val(produto.prod_fator_producao);
            $('#prod_total_pecas').val(produto.prod_total_pecas);
            $('#prod_codigo_interno').val(produto.prod_codigo_interno);

            // =================================================================
            // 2. LIMPA OS CAMPOS QUE DEVEM SER ÚNICOS OU ESPECÍFICOS
            // =================================================================

            // **ESTA É A CORREÇÃO PRINCIPAL PARA O ERRO DE VIOLAÇÃO DE DADOS**
            //$('#prod_codigo_interno').val('');

            // Limpa os campos de peso e códigos de barras da embalagem secundária
            $('#peso_embalagem_secundaria').val('');
            $('#prod_dun14').val('');

            // Também limpa campos que pertencem exclusivamente à embalagem primária
            $('#prod_ean13').val('');
            //$('#prod_total_pecas').val('');

            // Foca no campo de código interno para o usuário preencher
            //$('#prod_codigo_interno').focus();
            $('#prod_descricao').focus();

            // Calcula as unidades (se o peso já for preenchido)
            calcularUnidades();
        }
    });

    // Evento de digitação no peso da embalagem secundária
    $pesoEmbalagemSecundariaInput.on('keyup', calcularUnidades);

    // Inicializa a tabela de produtos
    var tableProdutos = $('#tabela-produtos').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "process/listar_produtos.php",
            "type": "POST",
            "data": function (d) {
                d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val();
            }
        },
        "responsive": true,
        "columns": [
            { "data": "prod_situacao", "render": function (data) { return (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'; } },
            { "data": "prod_codigo_interno" },
            { "data": "prod_descricao" },
            { "data": "prod_tipo" },
            { "data": "prod_tipo_embalagem" },
            { "data": "prod_peso_embalagem" },
            { "data": "prod_codigo", "orderable": false, "render": function (data, type, row) { return '<a href="#" class="btn btn-warning btn-sm btn-editar-produto" data-id="' + data + '">Editar</a> <a href="#" class="btn btn-danger btn-sm btn-excluir-produto" data-id="' + data + '">Excluir</a>'; } }
        ],
        "language": { "url": "../vendor/DataTables/Portuguese-Brasil.json" }
    });

    // =================================================================
    // FILTRO SITUAÇÃO: Evento para recarregar a tabela ao mudar o filtro
    // =================================================================
    $('input[name="filtro_situacao"]').on('change', function () {
        tableProdutos.ajax.reload();
    });
    // =================================================================
    // FIM
    // =================================================================


    // Resetar o modal ao abrir para um novo produto
    $modalProduto.on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var isNew = button && button.attr('id') === 'btn-adicionar-produto-main';

        if (isNew) {
            $('#modal-adicionar-produto-label').text('Adicionar Produto');
            $formProduto[0].reset();
            $('#prod_codigo').val('');
            $tipoEmbalagemSelect.val('PRIMARIA').trigger('change');
        }
    });

    // Envio do formulário (Cadastrar/Editar)
    $formProduto.on('submit', function (e) {
        console.log('Função de salvar acionada!'); // <--- ADICIONE ESTA LINHA
        e.preventDefault();
        var url = $('#prod_codigo').val() ? 'process/editar_produto.php' : 'process/cadastrar_produto.php';
        var formData = new FormData(this);

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
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

    // Evento para Editar Produto
    $('#tabela-produtos tbody').on('click', '.btn-editar-produto', function (e) {
        e.preventDefault();
        var idProduto = $(this).data('id');

        $.ajax({
            url: 'process/buscar_produto.php',
            type: 'POST',
            data: { prod_codigo: idProduto },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    var produto = response.data;
                    $formProduto[0].reset();

                    $('#prod_codigo').val(produto.prod_codigo);
                    $('#prod_descricao').val(produto.prod_descricao);
                    $('#prod_codigo_interno').val(produto.prod_codigo_interno);
                    $('#prod_tipo').val(produto.prod_tipo);
                    $('#prod_subtipo').val(produto.prod_subtipo);
                    $('#prod_classificacao').val(produto.prod_classificacao);
                    $('#prod_especie').val(produto.prod_especie);
                    $('#prod_origem').val(produto.prod_origem);
                    $('#prod_conservacao').val(produto.prod_conservacao);
                    $('#prod_congelamento').val(produto.prod_congelamento);
                    $('#prod_fator_producao').val(produto.prod_fator_producao);
                    $('#prod_ean13').val(produto.prod_ean13);
                    $('#prod_dun14').val(produto.prod_dun14);
                    $('#prod_total_pecas').val(produto.prod_total_pecas);
                    $('#prod_validade_meses').val(produto.prod_validade_meses);

                    $('#prod_tipo_embalagem').val(produto.prod_tipo_embalagem);
                    toggleEmbalagemFields();

                    if (produto.prod_tipo_embalagem === 'SECUNDARIA') {
                        $('#peso_embalagem_secundaria').val(produto.prod_peso_embalagem);
                        loadProdutosPrimarios();
                        setTimeout(function () {
                            $('#prod_primario_id').val(produto.prod_primario_id);
                            calcularUnidades();
                        }, 500);
                    } else {
                        $('#prod_peso_embalagem').val(produto.prod_peso_embalagem);
                    }

                    $('#modal-adicionar-produto-label').text('Editar Produto');
                    $modalProduto.modal('show');
                } else {
                    showFeedbackMessage(response.message, 'danger');
                }
            },
            error: function () {
                showFeedbackMessage('Erro de comunicação ao tentar buscar dados do produto.', 'danger');
            }
        });
    });

    // =================================================================
    // Evento para Excluir Produto
    // =================================================================
    $('#tabela-produtos tbody').on('click', '.btn-excluir-produto', function (e) {
        e.preventDefault();

        var idProduto = $(this).data('id');

        // Pede a confirmação do usuário antes de prosseguir
        if (confirm('Tem certeza de que deseja excluir este produto? Esta ação não pode ser desfeita.')) {

            $.ajax({
                url: 'process/excluir_produto.php',
                type: 'POST',
                data: {
                    prod_codigo: idProduto,
                    csrf_token: csrfToken // Usando a variável global definida no início do arquivo
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Mostra uma mensagem de sucesso (opcional, pode ser um alert ou um toast)
                        alert(response.message);
                        // Recarrega os dados da tabela para remover a linha excluída
                        tableProdutos.ajax.reload();
                    } else {
                        // Mostra a mensagem de erro retornada pelo PHP
                        alert(response.message);
                    }
                },
                error: function () {
                    alert('Erro de comunicação ao tentar excluir o produto.');
                }
            });
        }
    });
});