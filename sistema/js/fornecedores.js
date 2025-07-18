/**
 * fornecedores.js
 * Gerencia toda a interatividade da página de Fornecedores, espelhando a funcionalidade de clientes.js.
 */
$(document).ready(function () {

    // =================================================================
    // I. CONFIGURAÇÃO E VARIÁVEIS DE ESTADO
    // =================================================================
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    let tableFornecedores, tableEnderecosForn;
    let triggerButton = null;
    let lastCnpjRequest = '';

    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
        if (!options.crossDomain) {
            jqXHR.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }
    });

    // =================================================================
    // II. SELETORES GLOBAIS
    // =================================================================
    const $modalFornecedor = $('#modal-adicionar-fornecedor');
    const $formFornecedor = $('#form-fornecedor');
    const $formEnderecoForn = $('#form-endereco-forn');
    const $cpfCnpjForn = $('#cpf-cnpj-forn');

    // =================================================================
    // III. FUNÇÕES AUXILIARES
    // =================================================================

    function showFeedbackMessage(message, type = 'success', area = '#feedback-message-area-fornecedor') {
        const $area = $(area);
        const alertClass = (type === 'success') ? 'alert-success' : 'alert-danger';
        $area.empty().removeClass('alert-success alert-danger').addClass(`alert ${alertClass}`).text(message).fadeIn();
        setTimeout(() => $area.fadeOut('slow'), 5000);
    }

    function toggleInscricaoEstadualField() {
        const isJuridica = $('#tipo-pessoa-juridica-forn').is(':checked');
        $('#div-inscricao-estadual-forn').toggle(isJuridica);
        if (!isJuridica) $('#inscricao-estadual-forn').val('');
    }

    function applyCpfCnpjMask() {
        if ($('#tipo-pessoa-fisica-forn').is(':checked')) {
            $('#label-cpf-cnpj-forn').text('CPF');
            $cpfCnpjForn.attr('placeholder', '000.000.000-00').mask('000.000.000-00', { reverse: true });
        } else {
            $('#label-cpf-cnpj-forn').text('CNPJ');
            $cpfCnpjForn.attr('placeholder', '00.000.000/0000-00').mask('00.000.000/0000-00', { reverse: true });
        }
        toggleInscricaoEstadualField();
    }
    
    function setPrincipalAddressFieldsReadonly(isReadonly) {
        const fields = '#cep-endereco-forn, #logradouro-endereco-forn, #numero-endereco-forn, #complemento-endereco-forn, #bairro-endereco-forn, #cidade-endereco-forn, #uf-endereco-forn';
        $(fields).prop('readonly', isReadonly).toggleClass('bg-light', isReadonly);
    }
    
    function resetFornecedorModal() {
        $formFornecedor[0].reset();
        $formEnderecoForn[0].reset();
        $('#ent-codigo-forn, #end-codigo-forn, #end-entidade-id-forn').val('');
        $('#mensagem-fornecedor, #mensagem-endereco-forn, #cnpj-feedback-forn, #cep-feedback-principal-forn, #cep-feedback-adicional-forn').empty().removeClass();
        $('#situacao-fornecedor').prop('checked', true).trigger('change');
        $('#tipo-pessoa-fisica-forn').prop('checked', true).trigger('change');
        $('#tipo-entidade-fornecedor-forn').prop('checked', true);
        $('#enderecos-fornecedor-tab').addClass('disabled');
        if (tableEnderecosForn) tableEnderecosForn.clear().draw();
        $('#dados-fornecedor-tab').trigger('click');
        setPrincipalAddressFieldsReadonly(false);
        lastCnpjRequest = '';
    }

    function buscarCnpj(cnpj) {
        if (cnpj === lastCnpjRequest) return;
        const feedback = $('#cnpj-feedback-forn');
        feedback.text('Buscando dados...').addClass('text-muted').removeClass('text-danger text-success');
        fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`)
            .then(response => response.ok ? response.json() : Promise.reject('CNPJ não encontrado.'))
            .then(data => {
                feedback.text('Dados encontrados!').addClass('text-success');
                lastCnpjRequest = cnpj;
                $('#razao-social-forn').val(data.razao_social);
                $('#nome-fantasia-forn').val(data.nome_fantasia);
                $('#cep-endereco-forn').val(data.cep);
                $('#logradouro-endereco-forn').val(data.logradouro);
                $('#numero-endereco-forn').val(data.numero);
                $('#bairro-endereco-forn').val(data.bairro);
                $('#cidade-endereco-forn').val(data.municipio);
                $('#uf-endereco-forn').val(data.uf);
            })
            .catch(error => {
                feedback.text(error.toString()).addClass('text-danger');
                lastCnpjRequest = '';
            });
    }

    function searchCep(cep, feedbackSelector, fields) {
        const $feedback = $(feedbackSelector);
        const cepValue = (cep || '').replace(/\D/g, '');
        if (cepValue.length !== 8) {
            if(cep) $feedback.text('CEP inválido.').addClass('text-danger');
            return;
        }
        $feedback.text('Buscando...').removeClass('text-danger text-success').addClass('text-muted');
        fetch(`https://viacep.com.br/ws/${cepValue}/json/`)
            .then(response => response.ok ? response.json() : Promise.reject('Erro na busca.'))
            .then(data => {
                if (data.erro) {
                    $feedback.text('CEP não encontrado.').addClass('text-danger');
                } else {
                    $(fields.logradouro).val(data.logradouro);
                    $(fields.bairro).val(data.bairro);
                    $(fields.cidade).val(data.localidade);
                    $(fields.uf).val(data.uf);
                    $feedback.text('CEP encontrado!').addClass('text-success');
                    $(fields.numero).focus();
                }
            })
            .catch(() => $feedback.text('Erro ao buscar CEP.').addClass('text-danger'));
    }

    function loadEnderecosTable(entidadeId) {
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-fornecedor')) {
            tableEnderecosForn.destroy();
        }
        tableEnderecosForn = $('#tabela-enderecos-fornecedor').DataTable({
            ajax: {
                url: "process/listar_enderecos.php",
                type: "POST",
                data: { ent_codigo: entidadeId, csrf_token: csrfToken }
            },
            columns: [
                { "data": "end_tipo_endereco" },
                { "data": "end_logradouro" },
                { "data": null, "render": (data, type, row) => `${row.end_cidade || ''}/${row.end_uf || ''}` },
                { "data": "end_codigo", "orderable": false, "render": data => `<a href="#" class="btn btn-warning btn-sm btn-editar-endereco-forn me-1" data-id="${data}">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-endereco-forn" data-id="${data}">Excluir</a>` }
            ],
            paging: false, searching: false, info: false,
            language: { "url": "../vendor/DataTables/Portuguese-Brasil.json" }
        });
    }

    // =================================================================
    // IV. INICIALIZAÇÃO DA TABELA PRINCIPAL
    // =================================================================
    tableFornecedores = $('#example-fornecedores').DataTable({
        ajax: {
            url: "process/listar_entidades.php",
            type: "POST",
            data: d => {
                d.filtro_situacao = $('input[name="filtro_situacao_forn"]:checked').val();
                d.tipo_entidade = 'Fornecedor';
            }
        },
        responsive: true,
        columns: [
            { "data": "ent_situacao", "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' },
            { "data": "ent_tipo_entidade" },
            { "data": "ent_razao_social" },
            { "data": null, "render": (data, type, row) => row.ent_tipo_pessoa === 'F' ? row.ent_cpf : row.ent_cnpj },
            { "data": "end_logradouro", "render": (data, type, row) => data ? `${row.end_logradouro || ''}, ${row.end_numero || ''}` : 'N/A' },
            { "data": "ent_codigo", "orderable": false, "render": (data, type, row) => `<a href="#" class="btn btn-warning btn-sm btn-editar-fornecedor me-1" data-id="${data}">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-fornecedor" data-id="${data}" data-nome="${row.ent_razao_social}">Excluir</a>` }
        ],
        language: { "url": "../vendor/DataTables/Portuguese-Brasil.json" }
    });

    // =================================================================
    // V. EVENT HANDLERS
    // =================================================================

    $('input[name="filtro_situacao_forn"]').on('change', () => tableFornecedores.ajax.reload());

    $modalFornecedor.on('show.bs.modal', function (event) {
        triggerButton = $(event.relatedTarget);
        if (triggerButton && triggerButton.is('#btn-adicionar-fornecedor-main')) {
            resetFornecedorModal();
            $('#modal-adicionar-fornecedor-label').text('Adicionar Fornecedor');
        }
    }).on('hidden.bs.modal', function () {
        if (triggerButton && triggerButton.length) {
            setTimeout(() => { triggerButton.focus(); triggerButton = null; }, 100);
        }
    });

    $('#fornecedorTab .nav-link').on('click', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        $('#fornecedorTab .nav-link, #fornecedorTabContent .tab-pane').removeClass('active show');
        $(this).addClass('active');
        $($(this).data('tab-target')).addClass('show active');
    });

    $('input[name="ent_tipo_pessoa"]').on('change', function () {
        applyCpfCnpjMask();
        $('#btn-buscar-cnpj-forn').toggle($(this).val() === 'J');
    }).trigger('change');

    $('#btn-buscar-cnpj-forn').on('click', () => {
        const cnpjValue = $cpfCnpjForn.val().replace(/\D/g, '');
        if (cnpjValue.length === 14) buscarCnpj(cnpjValue);
    });

    $cpfCnpjForn.on('blur', function () {
        if ($('#tipo-pessoa-juridica-forn').is(':checked')) {
            const cnpjValue = $(this).val().replace(/\D/g, '');
            if (cnpjValue.length === 14) buscarCnpj(cnpjValue);
        }
    });
    
    $('#situacao-fornecedor').on('change', function () {
        $('#texto-situacao-fornecedor').text(this.checked ? 'Ativo' : 'Inativo');
    });

    $('#example-fornecedores tbody').on('click', '.btn-editar-fornecedor', function (e) {
        e.preventDefault();
        const idEntidade = $(this).data('id');
        $.getJSON('process/get_entidade_data.php', { id: idEntidade })
            .done(response => {
                if (response.success && response.data) {
                    resetFornecedorModal();
                    const data = response.data;
                    $('#modal-adicionar-fornecedor-label').text('Editar Fornecedor');
                    $('#ent-codigo-forn, #end-entidade-id-forn').val(data.ent_codigo);
                    $('#razao-social-forn').val(data.ent_razao_social);
                    $('#nome-fantasia-forn').val(data.ent_nome_fantasia);
                    $('#inscricao-estadual-forn').val(data.ent_inscricao_estadual);
                    if (data.ent_tipo_pessoa === 'F') $('#tipo-pessoa-fisica-forn').prop('checked', true); else $('#tipo-pessoa-juridica-forn').prop('checked', true);
                    $cpfCnpjForn.val(data.ent_tipo_pessoa === 'F' ? data.ent_cpf : data.ent_cnpj);
                    $('input[name="ent_tipo_pessoa"]:checked').trigger('change');
                    if (data.ent_tipo_entidade === 'Fornecedor') $('#tipo-entidade-fornecedor-forn').prop('checked', true); else if (data.ent_tipo_entidade === 'Cliente e Fornecedor') $('#tipo-entidade-ambos-forn').prop('checked', true);
                    $('#situacao-fornecedor').prop('checked', data.ent_situacao === 'A').trigger('change');
                    $('#cep-endereco-forn').val(data.end_cep);
                    $('#logradouro-endereco-forn').val(data.end_logradouro);
                    $('#numero-endereco-forn').val(data.end_numero);
                    $('#complemento-endereco-forn').val(data.end_complemento);
                    $('#bairro-endereco-forn').val(data.end_bairro);
                    $('#cidade-endereco-forn').val(data.end_cidade);
                    $('#uf-endereco-forn').val(data.end_uf);
                    setPrincipalAddressFieldsReadonly(true);
                    $('#enderecos-fornecedor-tab').removeClass('disabled');
                    loadEnderecosTable(data.ent_codigo);
                    new bootstrap.Modal($modalFornecedor[0]).show();
                } else {
                    showFeedbackMessage(response.message || 'Erro ao carregar dados.', 'danger');
                }
            })
            .fail(() => showFeedbackMessage('Falha na comunicação com o servidor.', 'danger'));
    });

    $formFornecedor.on('submit', function (e) {
        e.preventDefault();
        const idEntidade = $('#ent-codigo-forn').val();
        const url = idEntidade ? 'process/editar_entidade.php' : 'process/cadastrar_entidade.php';
        $.ajax({
            type: 'POST', url: url, data: new FormData(this), dataType: 'json', processData: false, contentType: false,
            success: response => {
                if (response.success) {
                    if (!idEntidade && response.ent_codigo) {
                        $('#ent-codigo-forn, #end-entidade-id-forn').val(response.ent_codigo);
                        $('#enderecos-fornecedor-tab').removeClass('disabled');
                        loadEnderecosTable(response.ent_codigo);
                    }
                    tableFornecedores.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success', '#mensagem-fornecedor');
                } else {
                    showFeedbackMessage(response.message, 'danger', '#mensagem-fornecedor');
                }
            },
            error: () => showFeedbackMessage('Erro de comunicação ao salvar.', 'danger', '#mensagem-fornecedor')
        });
    });

    // --- Eventos da Aba de Endereços Adicionais ---
    $('#btn-buscar-cep-adicional-forn').on('click', function() {
        searchCep($('#cep-endereco-adicional-forn').val(), '#cep-feedback-adicional-forn', {
            logradouro: '#logradouro-endereco-adicional-forn',
            bairro: '#bairro-endereco-adicional-forn',
            cidade: '#cidade-endereco-adicional-forn',
            uf: '#uf-endereco-adicional-forn',
            numero: '#numero-endereco-adicional-forn'
        });
    });

    $('#btn-cancelar-edicao-endereco-forn').on('click', () => {
        $formEnderecoForn[0].reset();
        $('#end-codigo-forn').val('');
        $('#btn-salvar-endereco-forn').text('Salvar Endereço Adicional');
    });

    $formEnderecoForn.on('submit', function (e) {
        e.preventDefault();
        const idEndereco = $('#end-codigo-forn').val();
        const url = idEndereco ? 'process/editar_endereco.php' : 'process/cadastrar_endereco.php';
        $.ajax({
            type: 'POST', url: url, data: new FormData(this), dataType: 'json', processData: false, contentType: false,
            success: response => {
                if (response.success) {
                    $formEnderecoForn[0].reset();
                    $('#end-codigo-forn').val('');
                    $('#btn-salvar-endereco-forn').text('Salvar Endereço Adicional');
                    tableEnderecosForn.ajax.reload();
                    tableFornecedores.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success', '#mensagem-endereco-forn');
                } else {
                    showFeedbackMessage(response.message, 'danger', '#mensagem-endereco-forn');
                }
            },
            error: () => showFeedbackMessage('Erro de comunicação.', 'danger', '#mensagem-endereco-forn')
        });
    });

    $('#tabela-enderecos-fornecedor tbody').on('click', '.btn-editar-endereco-forn', function (e) {
        e.preventDefault();
        const idEndereco = $(this).data('id');
        $.getJSON('process/get_endereco_data.php', { id: idEndereco })
            .done(response => {
                if (response.success && response.data) {
                    const data = response.data;
                    $('#end-codigo-forn').val(data.end_codigo);
                    $('#tipo-endereco-forn').val(data.end_tipo_endereco);
                    $('#cep-endereco-adicional-forn').val(data.end_cep);
                    $('#logradouro-endereco-adicional-forn').val(data.end_logradouro);
                    $('#numero-endereco-adicional-forn').val(data.end_numero);
                    $('#complemento-endereco-adicional-forn').val(data.end_complemento);
                    $('#bairro-endereco-adicional-forn').val(data.end_bairro);
                    $('#cidade-endereco-adicional-forn').val(data.end_cidade);
                    $('#uf-endereco-adicional-forn').val(data.end_uf);
                    $('#btn-salvar-endereco-forn').text('Atualizar Endereço');
                }
            });
    });

    // Ação de Excluir Fornecedor (principal)
    $('#example-fornecedores tbody').on('click', '.btn-excluir-fornecedor', function (e) {
        e.preventDefault();
        $('#nome-fornecedor-excluir').text($(this).data('nome'));
        $('#id-fornecedor-excluir').val($(this).data('id'));
        new bootstrap.Modal($('#modal-confirmar-exclusao-fornecedor')[0]).show();
    });

    $('#btn-confirmar-exclusao-fornecedor').on('click', function () {
        const idEntidade = $('#id-fornecedor-excluir').val();
        $.post('process/excluir_entidade.php', { ent_codigo: idEntidade, csrf_token: csrfToken })
            .done(response => {
                if(response.success) {
                    tableFornecedores.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success');
                } else {
                    showFeedbackMessage(response.message, 'danger');
                }
            })
            .fail(() => showFeedbackMessage('Erro de comunicação.', 'danger'))
            .always(() => bootstrap.Modal.getInstance($('#modal-confirmar-exclusao-fornecedor')[0]).hide());
    });
    
    // Ação de Excluir Endereço (adicional)
    $('#tabela-enderecos-fornecedor tbody').on('click', '.btn-excluir-endereco-forn', function (e) {
        e.preventDefault();
        $('#id-endereco-excluir-forn').val($(this).data('id'));
        new bootstrap.Modal($('#modal-confirmar-exclusao-endereco-forn')[0]).show();
    });

    $('#btn-confirmar-exclusao-endereco-forn').on('click', function() {
        const idEndereco = $('#id-endereco-excluir-forn').val();
        $.post('process/excluir_endereco.php', { end_codigo: idEndereco, csrf_token: csrfToken })
            .done(response => {
                 if(response.success) {
                    tableEnderecosForn.ajax.reload();
                    tableFornecedores.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success');
                } else {
                    showFeedbackMessage(response.message, 'danger');
                }
            })
            .fail(() => showFeedbackMessage('Erro de comunicação.', 'danger'))
            .always(() => bootstrap.Modal.getInstance($('#modal-confirmar-exclusao-endereco-forn')[0]).hide());
    });
});
