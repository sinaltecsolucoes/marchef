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
    var $modalCliente = $('#modal-adicionar-cliente');
    var $formCliente = $('#form-cliente');
    var $mensagemCliente = $('#mensagem-cliente');
    var $feedbackMessageAreaCliente = $('#feedback-message-area-cliente');
    var $btnAdicionarClienteMain = $('#btn-adicionar-cliente-main');
    var $razaoSocial = $('#razao-social');
    var $tipoPessoaFisica = $('#tipo-pessoa-fisica');
    var $tipoPessoaJuridica = $('#tipo-pessoa-juridica');
    var $labelCpfCnpj = $('#label-cpf-cnpj');
    var $cpfCnpj = $('#cpf-cnpj');
    var $tipoEntidadeCliente = $('#tipo-entidade-cliente');
    var $tipoEntidadeFornecedor = $('#tipo-entidade-fornecedor');
    var $tipoEntidadeAmbos = $('#tipo-entidade-ambos');
    var $situacaoCliente = $('#situacao-cliente');
    var $textoSituacaoCliente = $('#texto-situacao-cliente');
    var $entCodigo = $('#ent-codigo');
    var $filtroSituacaoRadios = $('input[name="filtro_situacao"]');
    var $formEndereco = $('#form-endereco');
    var $mensagemEndereco = $('#mensagem-endereco');
    var $endCodigo = $('#end-codigo');
    var $endEntidadeId = $('#end-entidade-id');
    var $tipoEndereco = $('#tipo-endereco');
    var $cepEndereco = $('#cep-endereco');
    var $btnBuscarCepEndereco = $('#btn-buscar-cep-endereco');
    var $cepFeedbackEndereco = $('#cep-feedback-endereco');
    var $logradouroEndereco = $('#logradouro-endereco');
    var $numeroEndereco = $('#numero-endereco');
    var $complementoEndereco = $('#complemento-endereco');
    var $bairroEndereco = $('#bairro-endereco');
    var $cidadeEndereco = $('#cidade-endereco');
    var $ufEndereco = $('#uf-endereco');
    var $btnSalvarEndereco = $('#btn-salvar-endereco');
    var $btnCancelarEdicaoEndereco = $('#btn-cancelar-edicao-endereco');
    var tableEnderecos;
    var tableClientes;
    var $triggerButton = null;

    // =================================================================
    // Controle Manual das Abas (Versão Única e Correta)
    // =================================================================
    var $tabButtons = $modalCliente.find('.nav-tabs .nav-link');
    var $tabPanes = $modalCliente.find('.tab-content .tab-pane');

    $tabButtons.on('click', function (e) {
        e.preventDefault();
        var $this = $(this);
        if ($this.hasClass('disabled')) {
            return;
        }
        $tabButtons.removeClass('active');
        $this.addClass('active');
        $tabPanes.removeClass('show active');
        var targetPaneSelector = $this.data('tab-target');
        $(targetPaneSelector).addClass('show active');
    });

    // =================================================================
    // Funções Auxiliares
    // =================================================================
    function showFeedbackMessageCliente(message, type = 'success') {
        $feedbackMessageAreaCliente.empty().removeClass('alert alert-success alert-danger');
        var alertClass = (type === 'success') ? 'alert-success' : 'alert-danger';
        $feedbackMessageAreaCliente.addClass('alert ' + alertClass).text(message).fadeIn();
        setTimeout(function () {
            $feedbackMessageAreaCliente.fadeOut('slow');
        }, 5000);
    }

    function applyCpfCnpjMask() {
        if ($tipoPessoaFisica.is(':checked')) {
            $labelCpfCnpj.text('CPF');
            $cpfCnpj.attr('placeholder', '000.000.000-00').mask('000.000.000-00', { reverse: true });
        } else {
            $labelCpfCnpj.text('CNPJ');
            $cpfCnpj.attr('placeholder', '00.000.000/0000-00').mask('00.000.000/0000-00', { reverse: true });
        }
    }

    function clearAddressFormFields() {
        $formEndereco[0].reset();
        $endCodigo.val('');
        $cepFeedbackEndereco.empty();
        $mensagemEndereco.empty().removeClass('alert alert-success alert-danger');
        $btnSalvarEndereco.text('Salvar Endereço');
    }

    function searchCepEndereco() {
        var cepValue = $cepEndereco.val().replace(/\D/g, '');
        if (cepValue.length !== 8) {
            $cepFeedbackEndereco.text('CEP inválido.').removeClass('text-success').addClass('text-danger');
            return;
        }
        $cepFeedbackEndereco.text('Buscando...').removeClass('text-danger text-success').addClass('text-muted');
        $.ajax({
            url: 'https://viacep.com.br/ws/' + cepValue + '/json/',
            method: 'GET',
            dataType: 'json',
            success: function (data) {
                if (data.erro) {
                    $cepFeedbackEndereco.text('CEP não encontrado.').addClass('text-danger');
                } else {
                    $logradouroEndereco.val(data.logradouro);
                    $bairroEndereco.val(data.bairro);
                    $cidadeEndereco.val(data.localidade);
                    $ufEndereco.val(data.uf);
                    $cepFeedbackEndereco.text('CEP encontrado!').addClass('text-success');
                    $numeroEndereco.focus();
                }
            },
            error: function () {
                $cepFeedbackEndereco.text('Erro ao buscar CEP.').addClass('text-danger');
            }
        });
    }

    function loadEnderecosTable(entidadeId) {
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-cliente')) {
            tableEnderecos.destroy();
        }
        tableEnderecos = $('#tabela-enderecos-cliente').DataTable({
            "ajax": {
                "url": "process/listar_enderecos.php",
                "type": "POST",
                "data": function (d) {
                    d.ent_codigo = entidadeId;
                    d.csrf_token = csrfToken;
                }
            },
            "columns": [
                { "data": "end_tipo_endereco" },
                { "data": "end_cep" },
                { "data": "end_logradouro" },
                { "data": "end_numero" },
                { "data": "end_bairro" },
                { "data": null, "render": function (data, type, row) { return row.end_cidade + '/' + row.end_uf; } },
                { "data": "end_codigo", "orderable": false, "render": function (data, type, row) { return '<a href="#" class="btn btn-warning btn-sm btn-editar-endereco me-1" data-id="' + data + '">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-endereco" data-id="' + data + '">Excluir</a>'; } }
            ],
            "paging": false,
            "searching": false,
            "info": false,
            "ordering": false,
            "language": { "url": "../vendor/DataTables/Portuguese-Brasil.json" }
        });
    }

    // =================================================================
    // Lógica de Eventos
    // =================================================================

    // Inicializa o DataTables principal para clientes
    tableClientes = $('#example-clientes').DataTable({
        "ajax": {
            "url": "process/listar_entidades.php",
            "type": "POST",
            "data": function (d) {
                d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val();
                d.tipo_entidade = 'Cliente';
            }
        },
        "responsive": true,
        "columns": [
            { "data": "ent_situacao", "render": function (data) { return (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'; } },
            { "data": "ent_tipo_entidade" },
            { "data": "ent_razao_social" },
            { "data": null, "render": function (data, type, row) { return row.ent_tipo_pessoa === 'F' ? row.ent_cpf : row.ent_cnpj; } },
            { "data": "end_logradouro", "render": function (data, type, row) { return data ? (row.end_tipo_endereco ? row.end_tipo_endereco + ': ' : '') + data + ', ' + row.end_numero : 'N/A'; } },
            { "data": "ent_codigo", "orderable": false, "render": function (data, type, row) { return '<a href="#" class="btn btn-warning btn-sm btn-editar-cliente me-1" data-id="' + data + '">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-cliente" data-id="' + data + '" data-nome="' + row.ent_razao_social + '">Excluir</a>'; } }
        ],
        "ordering": true,
        "language": { "url": "../vendor/DataTables/Portuguese-Brasil.json" }
    });

    // Evento para mudança de Tipo de Pessoa (Física/Jurídica)
    $('input[name="ent_tipo_pessoa"]').on('change', applyCpfCnpjMask);
    applyCpfCnpjMask();

    // Evento para o switch de Situação (Ativo/Inativo)
    $situacaoCliente.on('change', function () {
        $textoSituacaoCliente.text(this.checked ? 'Ativo' : 'Inativo');
    });

    // Evento de abertura do modal
    $modalCliente.on('show.bs.modal', function (event) {
        $mensagemCliente.empty().removeClass();
        clearAddressFormFields();
        $triggerButton = $(event.relatedTarget);

        if ($triggerButton.is($btnAdicionarClienteMain)) {
            $('#modal-adicionar-cliente-label').text('Adicionar Cliente');
            $formCliente[0].reset();
            $entCodigo.val('');
            $situacaoCliente.prop('checked', true).trigger('change');
            $tipoPessoaFisica.prop('checked', true).trigger('change');
            $tipoEntidadeCliente.prop('checked', true);
            $('#enderecos-tab').addClass('disabled');
            if ($.fn.DataTable.isDataTable('#tabela-enderecos-cliente')) {
                tableEnderecos.clear().draw();
            }
        }
        // Ativa a aba principal sempre que o modal abrir
        $('#dados-cliente-tab').trigger('click');
    });

    // Lógica para o botão "Editar" da tabela de clientes (Abre o Modal)
    $('#example-clientes tbody').on('click', '.btn-editar-cliente', function (e) {
        e.preventDefault();
        var idEntidade = $(this).data('id');
        $('#modal-adicionar-cliente-label').text('Editar Cliente');
        $entCodigo.val(idEntidade);
        $endEntidadeId.val(idEntidade);

        $.ajax({
            url: 'process/get_entidade_data.php',
            type: 'GET',
            data: { id: idEntidade },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    var entidadeData = response.data;
                    $razaoSocial.val(entidadeData.ent_razao_social);
                    if (entidadeData.ent_tipo_pessoa === 'F') { $tipoPessoaFisica.prop('checked', true); } else { $tipoPessoaJuridica.prop('checked', true); }
                    $('input[name="ent_tipo_pessoa"]:checked').trigger('change');
                    var cpfCnpjValue = entidadeData.ent_tipo_pessoa === 'F' ? entidadeData.ent_cpf : entidadeData.ent_cnpj;
                    $cpfCnpj.val(cpfCnpjValue);
                    applyCpfCnpjMask();
                    if (entidadeData.ent_tipo_entidade === 'Cliente') { $tipoEntidadeCliente.prop('checked', true); } else if (entidadeData.ent_tipo_entidade === 'Fornecedor') { $tipoEntidadeFornecedor.prop('checked', true); } else if (entidadeData.ent_tipo_entidade === 'Cliente e Fornecedor') { $tipoEntidadeAmbos.prop('checked', true); }
                    $situacaoCliente.prop('checked', entidadeData.ent_situacao === 'A').trigger('change');

                    $('#enderecos-tab').removeClass('disabled');
                    loadEnderecosTable(idEntidade);
                    var clienteModal = new bootstrap.Modal(document.getElementById('modal-adicionar-cliente'));
                    clienteModal.show();
                    $('#dados-cliente-tab').trigger('click');
                }
            }
        });
    });

    // Lógica para o botão "Editar" da tabela de endereços (Troca para a aba de formulário)
    $('#tabela-enderecos-cliente tbody').on('click', '.btn-editar-endereco', function (e) {
        e.preventDefault();
        var idEndereco = $(this).data('id');
        $.ajax({
            url: 'process/get_endereco_data.php',
            type: 'GET',
            data: { id: idEndereco },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    var enderecoData = response.data;
                    $endCodigo.val(enderecoData.end_codigo);
                    $tipoEndereco.val(enderecoData.end_tipo_endereco);
                    $cepEndereco.val(enderecoData.end_cep);
                    $logradouroEndereco.val(enderecoData.end_logradouro);
                    $numeroEndereco.val(enderecoData.end_numero);
                    $complementoEndereco.val(enderecoData.end_complemento);
                    $bairroEndereco.val(enderecoData.end_bairro);
                    $cidadeEndereco.val(enderecoData.end_cidade);
                    $ufEndereco.val(enderecoData.end_uf);
                    $btnSalvarEndereco.text('Atualizar Endereço');
                    $mensagemEndereco.empty();
                    // Dispara o clique na aba de endereços para mostrar o formulário
                    $('#enderecos-tab').trigger('click');
                }
            }
        });
    });

    // Lógica de envio do formulário de endereço
    $formEndereco.off('submit').on('submit', function (e) {
        e.preventDefault();

        if ($btnSalvarEndereco.is(':disabled')) {
            return; 
        }
        $btnSalvarEndereco.prop('disabled', true).text('Salvando...');

        var entidadeId = $endEntidadeId.val();
        if (!entidadeId) {
            $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text('Primeiro, salve os dados do cliente.');
            $btnSalvarEndereco.prop('disabled', false).text('Salvar Endereço');
            return;
        }
        var idEndereco = $endCodigo.val();
        var url = idEndereco ? 'process/editar_endereco.php' : 'process/cadastrar_endereco.php';
        var formData = new FormData(this);
        formData.append('end_entidade_id', entidadeId);
        
        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    clearAddressFormFields();
                    tableEnderecos.ajax.reload();
                    $mensagemEndereco.empty().removeClass().addClass('alert alert-success').text(response.message);
                    setTimeout(function () { tableClientes.ajax.reload(null, false); }, 200);
                    $('#dados-cliente-tab').trigger('click');
                } else {
                    $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text(response.message);
                }
            },
            error: function (xhr) {
                $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text('Erro na requisição: ' + xhr.statusText);
            },
            complete: function() {
                var buttonText = $endCodigo.val() ? 'Atualizar Endereço' : 'Salvar Endereço';
                $btnSalvarEndereco.prop('disabled', false).text(buttonText);
            }
        });
    });

    // Lógica para o botão "Cancelar" no formulário de endereço
    $btnCancelarEdicaoEndereco.on('click', function () {
        clearAddressFormFields();
        // Volta para a aba principal
        $('#dados-cliente-tab').trigger('click');
    });

    // Lógica de envio do formulário principal do cliente
    $formCliente.on('submit', function (e) {
        e.preventDefault();
        var idEntidade = $entCodigo.val();
        var url = idEntidade ? 'process/editar_entidade.php' : 'process/cadastrar_entidade.php';
        var formData = new FormData(this);
        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    if (!idEntidade) {
                        $entCodigo.val(response.ent_codigo);
                        $endEntidadeId.val(response.ent_codigo);
                        loadEnderecosTable(response.ent_codigo);
                        $('#enderecos-tab').removeClass('disabled');
                    }
                    setTimeout(function () { tableClientes.ajax.reload(null, false); }, 200);
                    $mensagemCliente.empty().removeClass().addClass('alert alert-success').text(response.message);
                } else {
                    $mensagemCliente.empty().removeClass().addClass('alert alert-danger').text(response.message);
                }
            }
        });
    });

    // --- Lógica para o formulário de Endereço (Aba "Endereços") ---

    // Evento de clique no botão "Buscar CEP" do formulário de endereço
    $btnBuscarCepEndereco.on('click', searchCepEndereco);

    // Evento de "blur" (perda de foco) no campo CEP do formulário de endereço
    $cepEndereco.on('blur', function () {
        if ($(this).val().replace(/\D/g, '').length === 8) {
            searchCepEndereco();
        }
    });

    // Lógica de envio do formulário de endereço
    $formEndereco.on('submit', function (e) {
        e.preventDefault();

        // Trava o botão para evitar duplo clique
        if ($btnSalvarEndereco.is(':disabled')) {
            return;
        }
        $btnSalvarEndereco.prop('disabled', true).text('Salvando...');

        var entidadeId = $endEntidadeId.val();
        if (!entidadeId) {
            $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text('Primeiro, salve os dados do cliente.');
            $btnSalvarEndereco.prop('disabled', false).text('Salvar Endereço'); // Reabilita o botão em caso de erro
            return;
        }
        var idEndereco = $endCodigo.val();
        var url = idEndereco ? 'process/editar_endereco.php' : 'process/cadastrar_endereco.php';
        var formData = new FormData(this);
        formData.append('end_entidade_id', entidadeId);

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    clearAddressFormFields();
                    tableEnderecos.ajax.reload();
                    $mensagemEndereco.empty().removeClass().addClass('alert alert-success').text(response.message);
                    setTimeout(function () { tableClientes.ajax.reload(null, false); }, 200);
                    // Volta para a aba principal após salvar
                    $('#dados-cliente-tab').trigger('click');
                } else {
                    $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text(response.message);
                }
            },
            error: function (xhr) {
                $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text('Erro na requisição: ' + xhr.statusText);
            },
            complete: function () {
                // Reabilita o botão ao final da requisição (sucesso ou erro)
                var buttonText = $endCodigo.val() ? 'Atualizar Endereço' : 'Salvar Endereço';
                $btnSalvarEndereco.prop('disabled', false).text(buttonText);
            }
        });
    });

    // Lógica para o botão "Cancelar" no formulário de endereço
    $btnCancelarEdicaoEndereco.on('click', function () {
        clearAddressFormFields();
    });

    // Lógica para o botão "Editar" da tabela de endereços (dentro do modal)
    $('#tabela-enderecos-cliente tbody').on('click', '.btn-editar-endereco', function (e) {
        e.preventDefault();
        var idEndereco = $(this).data('id');
        console.log("DEBUG: Clicou em Editar Endereço. ID do Endereço:", idEndereco); // LOG AQUI

        $.ajax({
            url: 'process/get_endereco_data.php', // Novo endpoint para buscar dados de um endereço
            type: 'GET',
            data: { id: idEndereco },
            dataType: 'json',
            success: function (response) {
                console.log("DEBUG: Dados do endereço recebidos para edição:", response); // LOG AQUI
                if (response.success && response.data) {
                    var enderecoData = response.data;
                    $endCodigo.val(enderecoData.end_codigo);
                    $tipoEndereco.val(enderecoData.end_tipo_endereco);
                    $cepEndereco.val(enderecoData.end_cep);
                    $logradouroEndereco.val(enderecoData.end_logradouro);
                    $numeroEndereco.val(enderecoData.end_numero);
                    $complementoEndereco.val(enderecoData.end_complemento);
                    $bairroEndereco.val(enderecoData.end_bairro);
                    $cidadeEndereco.val(enderecoData.end_cidade); // CORRIGIDO: end_localidade para cidade
                    $ufEndereco.val(enderecoData.end_uf);
                    $btnSalvarEndereco.text('Atualizar Endereço');
                    $mensagemEndereco.empty(); // Limpa mensagens anteriores
                    // Ativa a aba de endereços e foca nela
                    var someTabTriggerEl = document.querySelector('#enderecos-tab')
                    var tab = new bootstrap.Tab(someTabTriggerEl)
                    tab.show();
                } else {
                    $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text(response.message || 'Erro ao carregar dados do endereço.');
                }
            },
            error: function (xhr, status, error) {
                $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text('Erro na requisição: ' + error);
                console.error("Erro AJAX ao carregar dados do endereço: ", status, error, xhr.responseText);
            }
        });
    });

    // Lógica para o botão "Excluir" da tabela de endereços (dentro do modal)
    $('#tabela-enderecos-cliente tbody').on('click', '.btn-excluir-endereco', function (e) {
        e.preventDefault();
        var idEndereco = $(this).data('id');

        // Busca os dados do endereço para exibi-los antes de confirmar
        $.ajax({
            url: 'process/get_endereco_data.php',
            type: 'GET',
            data: { id: idEndereco },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    var enderecoData = response.data;
                    // Preenche o formulário para o usuário ver o que está excluindo
                    $endCodigo.val(enderecoData.end_codigo);
                    $tipoEndereco.val(enderecoData.end_tipo_endereco);
                    $cepEndereco.val(enderecoData.end_cep);
                    $logradouroEndereco.val(enderecoData.end_logradouro);
                    $numeroEndereco.val(enderecoData.end_numero);
                    $complementoEndereco.val(enderecoData.end_complemento);
                    $bairroEndereco.val(enderecoData.end_bairro);
                    $cidadeEndereco.val(enderecoData.end_cidade);
                    $ufEndereco.val(enderecoData.end_uf);

                    // Muda para a aba de endereço
                    $('#enderecos-tab').trigger('click');

                    // Armazena o ID no modal de confirmação
                    $('#id-endereco-excluir').val(idEndereco);

                    // Mostra o modal de confirmação
                    var confirmModalEndereco = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao-endereco'));
                    confirmModalEndereco.show();
                } else {
                    showFeedbackMessageCliente('Erro ao carregar dados do endereço para exclusão.', 'danger');
                }
            },
            error: function () {
                showFeedbackMessageCliente('Falha na requisição para carregar dados do endereço.', 'danger');
            }
        });
    });

    // Lógica para o botão "Sim, Excluir" dentro do modal de confirmação de exclusão de endereço
    $('#btn-confirmar-exclusao-endereco').on('click', function () {
        var idEndereco = $('#id-endereco-excluir').val();
        $.ajax({
            type: 'POST',
            url: 'process/excluir_endereco.php',
            data: { end_codigo: idEndereco, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                // Esconde o modal de confirmação
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-endereco'));
                if (confirmModal) {
                    confirmModal.hide();
                }

                if (response.success) {
                    // Limpa o formulário de endereço
                    clearAddressFormFields();
                    // Recarrega a tabela de endereços
                    tableEnderecos.ajax.reload();
                    // Mostra a mensagem de sucesso
                    showFeedbackMessageCliente(response.message, 'success');
                    // Recarrega a tabela principal de clientes
                    setTimeout(function () { tableClientes.ajax.reload(null, false); }, 200);
                    // Volta para a aba principal
                    $('#dados-cliente-tab').trigger('click');
                } else {
                    showFeedbackMessageCliente('Erro ao excluir endereço: ' + response.message, 'danger');
                }
            },
            error: function () {
                bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-endereco')).hide();
                showFeedbackMessageCliente('Falha na requisição de exclusão.', 'danger');
            }
        });
    });

    // --- Lógica do Botão Excluir Cliente (principal) ---
    $('#example-clientes tbody').on('click', '.btn-excluir-cliente', function (e) {
        e.preventDefault();
        var idEntidade = $(this).data('id');
        var nomeEntidade = $(this).data('nome');

        $('#nome-cliente-excluir').text(nomeEntidade);
        $('#id-cliente-excluir').val(idEntidade);

        var confirmModal = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao-cliente'));
        confirmModal.show();
    });

    // Lógica para o botão "Sim, Excluir" dentro do modal de confirmação de exclusão de cliente
    $('#btn-confirmar-exclusao-cliente').on('click', function () {
        var idEntidade = $('#id-cliente-excluir').val();
        var csrfToken = $('input[name="csrf_token"]').val();
        //var csrfToken = CSRF_TOKEN;

        $.ajax({
            type: 'POST',
            url: 'process/excluir_entidade.php',
            data: { ent_codigo: idEntidade, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-cliente'));
                confirmModal.hide();

                if (response.success) {
                    // NOVO: Recarrega a tabela principal com um pequeno atraso para garantir que o BD esteja atualizado
                    setTimeout(function () {
                        tableClientes.ajax.reload(null, false); // false para manter a paginação atual
                    }, 200);
                    showFeedbackMessageCliente(response.message, 'success');
                } else {
                    showFeedbackMessageCliente('Erro ao excluir cliente: ' + response.message, 'danger');
                }
            },
            error: function (xhr, status, error) {
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-cliente'));
                confirmModal.hide();
                showFeedbackMessageCliente('Erro na requisição de exclusão: ' + error, 'danger');
            }
        });
    });

    // --- Gerenciamento de Foco para Acessibilidade ---
    // Evento que dispara quando o modal de cliente é COMPLETAMENTE FECHADO
    $modalCliente.on('hidden.bs.modal', function () {
        //console.log("Modal de Cliente escondido. Forçando reset completo.");
        // NOVO: Desfoca qualquer elemento dentro do modal para garantir que nenhum foco residual persista.
        $(this).find(':focus').blur();

        // Reseta o formulário principal do cliente
        $formCliente[0].reset();
        $entCodigo.val(''); // Garante que o ID oculto esteja vazio

        // Redefine os valores padrão para os elementos do formulário principal
        $situacaoCliente.prop('checked', true).val('A');
        $textoSituacaoCliente.text('Ativo');
        $tipoPessoaFisica.prop('checked', true); // Define Pessoa Física como padrão
        applyCpfCnpjMask(); // Aplica a máscara de CPF
        $tipoEntidadeCliente.prop('checked', true); // Define como 'Cliente' por padrão

        // Desabilita a aba de endereços e limpa a tabela de endereços
        $('#tabela-enderecos-cliente tbody').empty(); // Limpa a tabela de endereços
        clearAddressFormFields(); // Limpa também o formulário de endereço

        // Tenta remover a classe 'disabled' da aba de endereços
        $('#enderecos-tab').addClass('disabled');

        // Move o foco de volta para o botão que abriu o modal
        // ou para um elemento padrão se o botão não for rastreado.
        if ($triggerButton && $triggerButton.length) {
            // Pequeno atraso para garantir que o modal esteja completamente fechado
            setTimeout(function () {
                $triggerButton.focus();
                $triggerButton = null; // Limpa a referência
            }, 100);
        } else {
            // Fallback: foca no botão principal de adicionar cliente
            setTimeout(function () {
                $btnAdicionarClienteMain.focus();
            }, 100);
        }
    });

    $('#modal-confirmar-exclusao-cliente').on('hidden.bs.modal', function () {
        // NOVO: Desfoca qualquer elemento dentro do modal para garantir que nenhum foco residual persista.
        $(this).find(':focus').blur();

        // Move o foco para o botão que abriu o modal principal, se estiver aberto.
        // Ou para o botão principal de adicionar cliente.
        if ($modalCliente.hasClass('show')) {
            // Se o modal de cliente ainda estiver aberto, retorna o foco para a aba de endereços
            var someTabTriggerEl = document.querySelector('#enderecos-tab')
            var tab = new bootstrap.Tab(someTabTriggerEl)
            tab.show();
            $('#btn-salvar-endereco').focus(); // Foca no botão de salvar endereço
        } else if ($triggerButton && $triggerButton.length) {
            setTimeout(function () {
                $triggerButton.focus();
                $triggerButton = null;
            }, 100);
        } else {
            setTimeout(function () {
                $btnAdicionarClienteMain.focus();
            }, 100);
        }
    });

    $('#modal-confirmar-exclusao-endereco').on('hidden.bs.modal', function () {
        // NOVO: Desfoca qualquer elemento dentro do modal para garantir que nenhum foco residual persista.
        $(this).find(':focus').blur();

        // Após fechar o modal de confirmação de endereço, foca na aba de endereços do modal principal.
        // O modal de cliente DEVE estar aberto para que isso faça sentido.
        if ($modalCliente.hasClass('show')) {
            var someTabTriggerEl = document.querySelector('#enderecos-tab')
            var tab = new bootstrap.Tab(someTabTriggerEl)
            tab.show();
            // Tenta focar no botão Salvar Endereço dentro da aba
            $('#btn-salvar-endereco').focus();
        } else { // Se o modal principal não estiver aberto (cenário improvável, mas fallback)
            setTimeout(function () {
                $btnAdicionarClienteMain.focus();
            }, 100);
        }
    });

    // --- Eventos para os Filtros de Situação (Radio Buttons) ---
    $filtroSituacaoRadios.on('change', function () {
        tableClientes.ajax.reload(null, false); // Recarrega o DataTables quando o filtro de situação muda, mantendo a paginação
    });
});