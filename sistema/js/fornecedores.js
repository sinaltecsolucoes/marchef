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
    // Seletores Globais para FORNECEDORES
    // =================================================================
    var $modalFornecedor = $('#modal-adicionar-fornecedor');
    var $formFornecedor = $('#form-fornecedor');
    var $mensagemFornecedor = $('#mensagem-fornecedor');
    var $feedbackMessageAreaFornecedor = $('#feedback-message-area-fornecedor');
    var $btnAdicionarFornecedorMain = $('#btn-adicionar-fornecedor-main');

    // Campos do formulário de Entidade (com sufixo '-fornecedor' para serem únicos)
    var $razaoSocial = $('#razao-social-fornecedor');
    var $tipoPessoaFisica = $('#tipo-pessoa-fisica-fornecedor');
    var $tipoPessoaJuridica = $('#tipo-pessoa-juridica-fornecedor');
    var $labelCpfCnpj = $('#label-cpf-cnpj-fornecedor');
    var $cpfCnpj = $('#cpf-cnpj-fornecedor');
    var $tipoEntidadeCliente = $('#tipo-entidade-cliente-fornecedor');
    var $tipoEntidadeFornecedor = $('#tipo-entidade-fornecedor-fornecedor');
    var $tipoEntidadeAmbos = $('#tipo-entidade-ambos-fornecedor');
    var $situacaoFornecedor = $('#situacao-fornecedor');
    var $textoSituacaoFornecedor = $('#texto-situacao-fornecedor');
    var $entCodigo = $('#ent-codigo-fornecedor');
    var $filtroSituacaoRadios = $('input[name="filtro_situacao_fornecedor"]');

    // Campos do formulário de Endereço (com sufixo '-fornecedor')
    var $formEndereco = $('#form-endereco-fornecedor');
    var $mensagemEndereco = $('#mensagem-endereco-fornecedor');
    var $endCodigo = $('#end-codigo-fornecedor');
    var $endEntidadeId = $('#end-entidade-id-fornecedor');
    var $tipoEndereco = $('#tipo-endereco-fornecedor');
    var $cepEndereco = $('#cep-endereco-fornecedor');
    var $btnBuscarCepEndereco = $('#btn-buscar-cep-fornecedor');
    var $cepFeedbackEndereco = $('#cep-feedback-fornecedor');
    var $logradouroEndereco = $('#logradouro-endereco-fornecedor');
    var $numeroEndereco = $('#numero-endereco-fornecedor');
    var $complementoEndereco = $('#complemento-endereco-fornecedor');
    var $bairroEndereco = $('#bairro-endereco-fornecedor');
    var $cidadeEndereco = $('#cidade-endereco-fornecedor');
    var $ufEndereco = $('#uf-endereco-fornecedor');
    var $btnSalvarEndereco = $('#btn-salvar-endereco-fornecedor');
    var $btnCancelarEdicaoEndereco = $('#btn-cancelar-edicao-endereco-fornecedor');

    var tableEnderecos;
    var tableFornecedores;
    var $triggerButton = null;

    // =================================================================
    // Controle Manual das Abas
    // =================================================================
    var $tabButtons = $modalFornecedor.find('.nav-tabs .nav-link');
    var $tabPanes = $modalFornecedor.find('.tab-content .tab-pane');

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
    function showFeedbackMessageFornecedor(message, type = 'success') {
        $feedbackMessageAreaFornecedor.empty().removeClass('alert alert-success alert-danger');
        var alertClass = (type === 'success') ? 'alert-success' : 'alert-danger';
        $feedbackMessageAreaFornecedor.addClass('alert ' + alertClass).text(message).fadeIn();
        setTimeout(function () {
            $feedbackMessageAreaFornecedor.fadeOut('slow');
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
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-fornecedor')) {
            tableEnderecos.destroy();
        }
        tableEnderecos = $('#tabela-enderecos-fornecedor').DataTable({
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
                { "data": "end_codigo", "orderable": false, "render": function (data, type, row) { return '<a href="#" class="btn btn-warning btn-sm btn-editar-endereco-fornecedor me-1" data-id="' + data + '">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-endereco-fornecedor" data-id="' + data + '">Excluir</a>'; } }
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

    tableFornecedores = $('#example-fornecedores').DataTable({
        "ajax": {
            "url": "process/listar_entidades.php",
            "type": "POST",
            "data": function (d) {
                d.filtro_situacao = $('input[name="filtro_situacao_fornecedor"]:checked').val();
                d.tipo_entidade = 'Fornecedor';
            }
        },
        "responsive": true,
        "columns": [
            { "data": "ent_situacao", "render": function (data) { return (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'; } },
            { "data": "ent_tipo_entidade" },
            { "data": "ent_razao_social" },
            { "data": null, "render": function (data, type, row) { return row.ent_tipo_pessoa === 'F' ? row.ent_cpf : row.ent_cnpj; } },
            { "data": "end_logradouro", "render": function (data, type, row) { return data ? (row.end_tipo_endereco ? row.end_tipo_endereco + ': ' : '') + data + ', ' + row.end_numero : 'N/A'; } },
            { "data": "ent_codigo", "orderable": false, "render": function (data, type, row) { return '<a href="#" class="btn btn-warning btn-sm btn-editar-fornecedor me-1" data-id="' + data + '">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-fornecedor" data-id="' + data + '" data-nome="' + row.ent_razao_social + '">Excluir</a>'; } }
        ],
        "ordering": true,
        "language": { "url": "../vendor/DataTables/Portuguese-Brasil.json" }
    });

    // Evento para mudança de Tipo de Pessoa (Física/Jurídica)
    $('input[name="ent_tipo_pessoa"]').on('change', applyCpfCnpjMask);
    applyCpfCnpjMask();

    // Evento para o switch de Situação (Ativo/Inativo)
    $situacaoFornecedor.on('change', function () {
        $textoSituacaoFornecedor.text(this.checked ? 'Ativo' : 'Inativo');
    });

    // Evento de abertura do modal
    $modalFornecedor.on('show.bs.modal', function (event) {
        $mensagemFornecedor.empty().removeClass();
        clearAddressFormFields();
        $triggerButton = $(event.relatedTarget);

        if ($triggerButton.is($btnAdicionarFornecedorMain)) {
            $('#modal-adicionar-fornecedor-label').text('Adicionar Fornecedor');
            $formFornecedor[0].reset();
            $entCodigo.val('');
            $situacaoFornecedor.prop('checked', true).trigger('change');
            $tipoPessoaFisica.prop('checked', true).trigger('change');
            $tipoEntidadeFornecedor.prop('checked', true);
            $('#enderecos-tab-fornecedor').addClass('disabled');
            if ($.fn.DataTable.isDataTable('#tabela-enderecos-fornecedor')) {
                tableEnderecos.clear().draw();
            }
        }
        $('#dados-fornecedor-tab').trigger('click');
    });

    $('#example-fornecedores tbody').on('click', '.btn-editar-fornecedor', function (e) {
        e.preventDefault();
        var idEntidade = $(this).data('id');
        $('#modal-adicionar-fornecedor-label').text('Editar Fornecedor');
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
                    $('input[name="ent_tipo_pessoa_fornecedor"]:checked').trigger('change');
                    var cpfCnpjValue = entidadeData.ent_tipo_pessoa === 'F' ? entidadeData.ent_cpf : entidadeData.ent_cnpj;
                    $cpfCnpj.val(cpfCnpjValue);
                    applyCpfCnpjMask();
                    if (entidadeData.ent_tipo_entidade === 'Cliente') { $tipoEntidadeCliente.prop('checked', true); } else if (entidadeData.ent_tipo_entidade === 'Fornecedor') { $tipoEntidadeFornecedor.prop('checked', true); } else if (entidadeData.ent_tipo_entidade === 'Cliente e Fornecedor') { $tipoEntidadeAmbos.prop('checked', true); }
                    $situacaoFornecedor.prop('checked', entidadeData.ent_situacao === 'A').trigger('change');

                    $('#enderecos-tab-fornecedor').removeClass('disabled');
                    loadEnderecosTable(idEntidade);
                    var fornecedorModal = new bootstrap.Modal(document.getElementById('modal-adicionar-fornecedor'));
                    fornecedorModal.show();
                    $('#dados-fornecedor-tab').trigger('click');
                }
            }
        });
    });

    $('#tabela-enderecos-fornecedor tbody').on('click', '.btn-editar-endereco-fornecedor', function (e) {
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
                    $('#enderecos-tab-fornecedor').trigger('click');
                }
            }
        });
    });

    // --- Lógica para o formulário de Endereço (Aba "Endereços") ---

    // Evento de clique no botão "Buscar CEP"
    $btnBuscarCepEndereco.on('click', searchCepEndereco);

    // Evento de "blur" (perda de foco) no campo CEP
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
            $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text('Primeiro, salve os dados do fornecedor.');
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
                    setTimeout(function () { tableFornecedores.ajax.reload(null, false); }, 200);
                    // Volta para a aba principal após salvar
                    $('#dados-fornecedor-tab').trigger('click');
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

    $btnCancelarEdicaoEndereco.on('click', function () {
        clearAddressFormFields();
        $('#dados-fornecedor-tab').trigger('click');
    });

    $formFornecedor.off('submit').on('submit', function (e) {
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
                        $('#enderecos-tab-fornecedor').removeClass('disabled');
                    }
                    setTimeout(function () { tableFornecedores.ajax.reload(null, false); }, 200);
                    $mensagemFornecedor.empty().removeClass().addClass('alert alert-success').text(response.message);
                } else {
                    $mensagemFornecedor.empty().removeClass().addClass('alert alert-danger').text(response.message);
                }
            }
        });
    });

    $('#tabela-enderecos-fornecedor tbody').on('click', '.btn-excluir-endereco-fornecedor', function (e) {
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

                    $('#enderecos-tab-fornecedor').trigger('click');
                    $('#id-endereco-excluir-fornecedor').val(idEndereco);

                    var confirmModalEndereco = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao-endereco-fornecedor'));
                    confirmModalEndereco.show();
                } else {
                    showFeedbackMessageFornecedor('Erro ao carregar dados do endereço para exclusão.', 'danger');
                }
            },
            error: function () {
                showFeedbackMessageFornecedor('Falha na requisição para carregar dados do endereço.', 'danger');
            }
        });
    });

    $('#btn-confirmar-exclusao-endereco-fornecedor').off('click').on('click', function () {
        var $this = $(this);
        if ($this.is(':disabled')) { return; }
        $this.prop('disabled', true).text('Excluindo...');

        var idEndereco = $('#id-endereco-excluir-fornecedor').val();
        $.ajax({
            type: 'POST',
            url: 'process/excluir_endereco.php',
            data: { end_codigo: idEndereco, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-endereco-fornecedor'));
                if (confirmModal) { confirmModal.hide(); }

                if (response.success) {
                    clearAddressFormFields();
                    tableEnderecos.ajax.reload();
                    showFeedbackMessageFornecedor(response.message, 'success');
                    setTimeout(function () { tableFornecedores.ajax.reload(null, false); }, 200);
                    $('#dados-fornecedor-tab').trigger('click');
                } else {
                    showFeedbackMessageFornecedor('Erro ao excluir endereço: ' + response.message, 'danger');
                }
            },
            error: function () {
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-endereco-fornecedor'));
                if (confirmModal) { confirmModal.hide(); }
                showFeedbackMessageFornecedor('Falha na requisição de exclusão.', 'danger');
            },
            complete: function () {
                $this.prop('disabled', false).text('Sim, Excluir');
            }
        });
    });

    $('#example-fornecedores tbody').on('click', '.btn-excluir-fornecedor', function (e) {
        e.preventDefault();
        var idEntidade = $(this).data('id');
        var nomeEntidade = $(this).data('nome');
        $('#nome-fornecedor-excluir').text(nomeEntidade);
        $('#id-fornecedor-excluir').val(idEntidade);
        var confirmModal = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao-fornecedor'));
        confirmModal.show();
    });

    $('#btn-confirmar-exclusao-fornecedor').off('click').on('click', function () {
        var $this = $(this);
        if ($this.is(':disabled')) { return; }
        $this.prop('disabled', true).text('Excluindo...');

        var idEntidade = $('#id-fornecedor-excluir').val();
        $.ajax({
            type: 'POST',
            url: 'process/excluir_entidade.php',
            data: { ent_codigo: idEntidade, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-fornecedor'));
                if (confirmModal) { confirmModal.hide(); }

                if (response.success) {
                    setTimeout(function () { tableFornecedores.ajax.reload(null, false); }, 200);
                    showFeedbackMessageFornecedor(response.message, 'success');
                } else {
                    showFeedbackMessageFornecedor('Erro ao excluir fornecedor: ' + response.message, 'danger');
                }
            },
            complete: function () {
                $this.prop('disabled', false).text('Sim, Excluir');
            }
        });
    });

    $modalFornecedor.on('hidden.bs.modal', function () {
        $(this).find(':focus').blur();
        $formFornecedor[0].reset();
        $entCodigo.val('');
        $situacaoFornecedor.prop('checked', true).trigger('change');
        $tipoPessoaFisica.prop('checked', true).trigger('change');
        $tipoEntidadeFornecedor.prop('checked', true);
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-fornecedor')) {
            tableEnderecos.clear().draw();
        }
        clearAddressFormFields();
        $('#enderecos-tab-fornecedor').addClass('disabled');
        if ($triggerButton && $triggerButton.length) {
            setTimeout(function () { $triggerButton.focus(); $triggerButton = null; }, 100);
        } else {
            setTimeout(function () { $btnAdicionarFornecedorMain.focus(); }, 100);
        }
    });

    $('#modal-confirmar-exclusao-fornecedor, #modal-confirmar-exclusao-endereco-fornecedor').on('hidden.bs.modal', function () {
        $(this).find(':focus').blur();
    });

    $filtroSituacaoRadios.on('change', function () {
        tableFornecedores.ajax.reload(null, false);
    });
});
