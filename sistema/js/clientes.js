/**
 * clientes.js
 * Gerencia toda a interatividade da página de Clientes.
 * - Inicialização de DataTables
 * - Manipulação de modais e abas
 * - Busca de dados via API (CNPJ e CEP)
 * - Submissão de formulários via AJAX para CRUD de entidades e endereços.
 */
$(document).ready(function () {

    // =================================================================
    // I. CONFIGURAÇÃO E VARIÁVEIS DE ESTADO
    // =================================================================
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    let tableClientes, tableEnderecos;
    let triggerButton = null; // Rastreia o botão que abriu o modal
    let lastCnpjRequest = ''; // Armazena o último CNPJ consultado para evitar chamadas repetidas

    // Configuração global do AJAX para enviar o token CSRF
    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
        if (!options.crossDomain) {
            jqXHR.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }
    });

    // =================================================================
    // II. SELETORES GLOBAIS (CACHE DE ELEMENTOS)
    // =================================================================
    const $modalCliente = $('#modal-adicionar-cliente');
    const $formCliente = $('#form-cliente');
    const $formEndereco = $('#form-endereco');
    const $cpfCnpj = $('#cpf-cnpj');

    // =================================================================
    // III. FUNÇÕES AUXILIARES E PRINCIPAIS
    // =================================================================

    function showFeedbackMessage(message, type = 'success', area = '#feedback-message-area-cliente') {
        const $area = $(area);
        const alertClass = (type === 'success') ? 'alert-success' : 'alert-danger';
        $area.empty().removeClass('alert-success alert-danger').addClass(`alert ${alertClass}`).text(message).fadeIn();
        setTimeout(() => $area.fadeOut('slow'), 5000);
    }

    /**
     * Mostra ou esconde o campo de Inscrição Estadual com base no tipo de pessoa.
     */
    function toggleInscricaoEstadualField() {
        const isJuridica = $('#tipo-pessoa-juridica').is(':checked');
        const $divInscricaoEstadual = $('#div-inscricao-estadual');

        if (isJuridica) {
            $divInscricaoEstadual.show();
        } else {
            $divInscricaoEstadual.hide();
            $('#inscricao-estadual').val(''); // Limpa o valor ao esconder
        }
    }

    function applyCpfCnpjMask() {
        if ($('#tipo-pessoa-fisica').is(':checked')) {
            $('#label-cpf-cnpj').text('CPF');
            $cpfCnpj.attr('placeholder', '000.000.000-00').mask('000.000.000-00', { reverse: true });
        } else {
            $('#label-cpf-cnpj').text('CNPJ');
            $cpfCnpj.attr('placeholder', '00.000.000/0000-00').mask('00.000.000/0000-00', { reverse: true });
        }
        toggleInscricaoEstadualField(); // Garante que o campo IE seja atualizado
    }
    
    /**
     * Bloqueia ou desbloqueia os campos de endereço principal.
     * @param {boolean} isReadonly - true para bloquear, false para desbloquear.
     */
    function setPrincipalAddressFieldsReadonly(isReadonly) {
        const fields = '#cep-endereco, #logradouro-endereco, #numero-endereco, #complemento-endereco, #bairro-endereco, #cidade-endereco, #uf-endereco';
        $(fields).prop('readonly', isReadonly);
        $(fields).toggleClass('bg-light', isReadonly);
    }
    
    function resetClientModal() {
        $formCliente[0].reset();
        $formEndereco[0].reset();
        $('#ent-codigo, #end-codigo, #end-entidade-id').val('');
        $('#mensagem-cliente, #mensagem-endereco, #cnpj-feedback, #cep-feedback-adicional, #cep-feedback-principal').empty().removeClass();
        $('#situacao-cliente').prop('checked', true).trigger('change');
        $('#tipo-pessoa-fisica').prop('checked', true).trigger('change');
        $('#tipo-entidade-cliente').prop('checked', true);
        $('#enderecos-tab').addClass('disabled');
        if (tableEnderecos) tableEnderecos.clear().draw();
        $('#dados-cliente-tab').trigger('click');
        setPrincipalAddressFieldsReadonly(false); // Garante que os campos estejam editáveis para um novo cliente
        lastCnpjRequest = ''; // Limpa o CNPJ cacheado ao resetar
    }

    function buscarCnpj(cnpj) {
        // Verifica se a requisição é para o mesmo CNPJ da última vez
        if (cnpj === lastCnpjRequest) {
            return; // Não faz nada se o CNPJ for o mesmo
        }

        const feedback = $('#cnpj-feedback');
        feedback.text('Buscando dados...').removeClass('text-danger text-success').addClass('text-muted');
        
        fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`)
            .then(response => response.ok ? response.json() : Promise.reject('CNPJ não encontrado ou inválido.'))
            .then(data => {
                feedback.text('Dados encontrados!').removeClass('text-muted').addClass('text-success');
                lastCnpjRequest = cnpj; // Armazena o CNPJ que foi consultado com sucesso
                
                $('#razao-social').val(data.razao_social);
                $('#nome-fantasia').val(data.nome_fantasia);
                $('#cep-endereco').val(data.cep).trigger('blur');
                $('#logradouro-endereco').val(data.logradouro);
                $('#numero-endereco').val(data.numero);
                $('#complemento-endereco').val(data.complemento);
                $('#bairro-endereco').val(data.bairro);
                $('#cidade-endereco').val(data.municipio);
                $('#uf-endereco').val(data.uf);
                $('#razao-social').focus();
            })
            .catch(error => {
                feedback.text(error.toString()).removeClass('text-muted').addClass('text-danger');
                lastCnpjRequest = ''; // Limpa o cache em caso de erro para permitir nova tentativa
            });
    }

    function searchCep(cep, feedbackSelector, fields) {
        const $feedback = $(feedbackSelector);
        const cepValue = (cep || '').replace(/\D/g, '');
        if (cepValue.length !== 8) {
            if (cep) $feedback.text('CEP inválido.').removeClass('text-success').addClass('text-danger');
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
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-cliente')) {
            tableEnderecos.destroy();
        }
        tableEnderecos = $('#tabela-enderecos-cliente').DataTable({
            ajax: {
                url: "process/listar_enderecos.php",
                type: "POST",
                data: { ent_codigo: entidadeId, csrf_token: csrfToken }
            },
            columns: [
                { "data": "end_tipo_endereco" },
                { "data": "end_logradouro" },
                { "data": null, "render": (data, type, row) => `${row.end_cidade || ''}/${row.end_uf || ''}` },
                { "data": "end_codigo", "orderable": false, "render": data => `<a href="#" class="btn btn-warning btn-sm btn-editar-endereco me-1" data-id="${data}">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-endereco" data-id="${data}">Excluir</a>` }
            ],
            paging: false, searching: false, info: false,
            language: { "url": "../vendor/DataTables/Portuguese-Brasil.json" }
        });
    }

    // =================================================================
    // IV. INICIALIZAÇÃO DA TABELA PRINCIPAL
    // =================================================================
    tableClientes = $('#example-clientes').DataTable({
        ajax: {
            url: "process/listar_entidades.php",
            type: "POST",
            data: d => {
                d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val();
                d.tipo_entidade = 'Cliente';
            }
        },
        responsive: true,
        columns: [
            { "data": "ent_situacao", "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' },
            { "data": "ent_tipo_entidade" },
            { "data": "ent_razao_social" },
            { "data": null, "render": (data, type, row) => row.ent_tipo_pessoa === 'F' ? row.ent_cpf : row.ent_cnpj },
            { "data": "end_logradouro", "render": (data, type, row) => data ? `${row.end_logradouro || ''}, ${row.end_numero || ''}` : 'N/A' },
            { "data": "ent_codigo", "orderable": false, "render": (data, type, row) => `<a href="#" class="btn btn-warning btn-sm btn-editar-cliente me-1" data-id="${data}">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-cliente" data-id="${data}" data-nome="${row.ent_razao_social}">Excluir</a>` }
        ],
        language: { "url": "../vendor/DataTables/Portuguese-Brasil.json" }
    });

    // =================================================================
    // V. EVENT HANDLERS (Manipuladores de Eventos)
    // =================================================================

    // Filtro de situação da tabela principal
    $('input[name="filtro_situacao"]').on('change', () => tableClientes.ajax.reload());

    // Abertura do modal
    $modalCliente.on('show.bs.modal', function (event) {
        triggerButton = $(event.relatedTarget);
        if (triggerButton && triggerButton.is('#btn-adicionar-cliente-main')) {
            resetClientModal();
            $('#modal-adicionar-cliente-label').text('Adicionar Cliente');
        }
    });

    $modalCliente.on('hidden.bs.modal', function () {
        if (triggerButton && triggerButton.length) {
            setTimeout(() => { triggerButton.focus(); triggerButton = null; }, 100);
        }
    });

    // Navegação por abas
    $('.nav-tabs .nav-link').on('click', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        $('.nav-tabs .nav-link, .tab-content .tab-pane').removeClass('active show');
        $(this).addClass('active');
        $($(this).data('tab-target')).addClass('show active');
    });

    // Eventos do formulário principal
    $('input[name="ent_tipo_pessoa"]').on('change', function () {
        applyCpfCnpjMask();
        $('#btn-buscar-cnpj').toggle($(this).val() === 'J');
    }).trigger('change');

    $('#btn-buscar-cnpj').on('click', () => {
        const cnpjValue = $cpfCnpj.val().replace(/\D/g, '');
        if (cnpjValue.length === 14) buscarCnpj(cnpjValue);
    });

    $cpfCnpj.on('blur', function () {
        if ($('#tipo-pessoa-juridica').is(':checked')) {
            const cnpjValue = $(this).val().replace(/\D/g, '');
            if (cnpjValue.length === 14) buscarCnpj(cnpjValue);
        }
    });
    
    $('#situacao-cliente').on('change', function () {
        $('#texto-situacao-cliente').text(this.checked ? 'Ativo' : 'Inativo');
    });

    // Ação de Editar Cliente
    $('#example-clientes tbody').on('click', '.btn-editar-cliente', function (e) {
        e.preventDefault();
        const idEntidade = $(this).data('id');
        $.getJSON('process/get_entidade_data.php', { id: idEntidade })
            .done(response => {
                if (response.success && response.data) {
                    resetClientModal(); // Reseta primeiro para garantir um estado limpo
                    const data = response.data;
                    $('#modal-adicionar-cliente-label').text('Editar Cliente');
                    $('#ent-codigo, #end-entidade-id').val(data.ent_codigo);
                    $('#razao-social').val(data.ent_razao_social);
                    $('#nome-fantasia').val(data.ent_nome_fantasia);
                    $('#inscricao-estadual').val(data.ent_inscricao_estadual);
                    if (data.ent_tipo_pessoa === 'F') $('#tipo-pessoa-fisica').prop('checked', true); else $('#tipo-pessoa-juridica').prop('checked', true);
                    $cpfCnpj.val(data.ent_tipo_pessoa === 'F' ? data.ent_cpf : data.ent_cnpj);
                    $('input[name="ent_tipo_pessoa"]:checked').trigger('change');
                    if (data.ent_tipo_entidade === 'Cliente') $('#tipo-entidade-cliente').prop('checked', true); else if (data.ent_tipo_entidade === 'Cliente e Fornecedor') $('#tipo-entidade-ambos').prop('checked', true);
                    $('#situacao-cliente').prop('checked', data.ent_situacao === 'A').trigger('change');
                    $('#cep-endereco').val(data.end_cep);
                    $('#logradouro-endereco').val(data.end_logradouro);
                    $('#numero-endereco').val(data.end_numero);
                    $('#complemento-endereco').val(data.end_complemento);
                    $('#bairro-endereco').val(data.end_bairro);
                    $('#cidade-endereco').val(data.end_cidade);
                    $('#uf-endereco').val(data.end_uf);
                    
                    setPrincipalAddressFieldsReadonly(true); // Bloqueia os campos de endereço principal
                    
                    $('#enderecos-tab').removeClass('disabled');
                    loadEnderecosTable(data.ent_codigo);
                    
                    new bootstrap.Modal($modalCliente[0]).show();
                } else {
                    showFeedbackMessage(response.message || 'Erro ao carregar dados.', 'danger');
                }
            })
            .fail(() => showFeedbackMessage('Falha na comunicação com o servidor.', 'danger'));
    });

    // Submissão do formulário principal (Cliente)
    $formCliente.on('submit', function (e) {
        e.preventDefault();
        const idEntidade = $('#ent-codigo').val();
        const url = idEntidade ? 'process/editar_entidade.php' : 'process/cadastrar_entidade.php';
        $.ajax({
            type: 'POST', url: url, data: new FormData(this), dataType: 'json', processData: false, contentType: false,
            success: response => {
                if (response.success) {
                    if (!idEntidade && response.ent_codigo) {
                        $('#ent-codigo, #end-entidade-id').val(response.ent_codigo);
                        $('#enderecos-tab').removeClass('disabled');
                        loadEnderecosTable(response.ent_codigo);
                    }
                    tableClientes.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success', '#mensagem-cliente');
                } else {
                    showFeedbackMessage(response.message, 'danger', '#mensagem-cliente');
                }
            },
            error: () => showFeedbackMessage('Erro de comunicação ao salvar.', 'danger', '#mensagem-cliente')
        });
    });

    // --- Eventos da Aba de Endereços Adicionais e Endereço Principal ---
    $('#cep-endereco').on('blur', function() {
        const cepValue = $(this).val();
        searchCep(cepValue, '#cep-feedback-principal', {
            logradouro: '#logradouro-endereco',
            bairro: '#bairro-endereco',
            cidade: '#cidade-endereco',
            uf: '#uf-endereco',
            numero: '#numero-endereco'
        });
    });

    $('#btn-buscar-cep-adicional').on('click', function() {
        searchCep($('#cep-endereco-adicional').val(), '#cep-feedback-adicional', {
            logradouro: '#logradouro-endereco-adicional',
            bairro: '#bairro-endereco-adicional',
            cidade: '#cidade-endereco-adicional',
            uf: '#uf-endereco-adicional',
            numero: '#numero-endereco-adicional'
        });
    });

    $('#btn-cancelar-edicao-endereco').on('click', () => {
        $formEndereco[0].reset();
        $('#end-codigo').val('');
        $('#btn-salvar-endereco').text('Salvar Endereço Adicional');
    });

    $formEndereco.on('submit', function (e) {
        e.preventDefault();
        const idEndereco = $('#end-codigo').val();
        const url = idEndereco ? 'process/editar_endereco.php' : 'process/cadastrar_endereco.php';
        $.ajax({
            type: 'POST', url: url, data: new FormData(this), dataType: 'json', processData: false, contentType: false,
            success: response => {
                if (response.success) {
                    $formEndereco[0].reset();
                    $('#end-codigo').val('');
                    $('#btn-salvar-endereco').text('Salvar Endereço Adicional');
                    tableEnderecos.ajax.reload();
                    tableClientes.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success', '#mensagem-endereco');
                } else {
                    showFeedbackMessage(response.message, 'danger', '#mensagem-endereco');
                }
            },
            error: () => showFeedbackMessage('Erro de comunicação ao salvar endereço.', 'danger', '#mensagem-endereco')
        });
    });

    $('#tabela-enderecos-cliente tbody').on('click', '.btn-editar-endereco', function (e) {
        e.preventDefault();
        const idEndereco = $(this).data('id');
        $.getJSON('process/get_endereco_data.php', { id: idEndereco })
            .done(response => {
                if (response.success && response.data) {
                    const data = response.data;
                    $('#end-codigo').val(data.end_codigo);
                    $('#tipo-endereco').val(data.end_tipo_endereco);
                    $('#cep-endereco-adicional').val(data.end_cep);
                    $('#logradouro-endereco-adicional').val(data.end_logradouro);
                    $('#numero-endereco-adicional').val(data.end_numero);
                    $('#complemento-endereco-adicional').val(data.end_complemento);
                    $('#bairro-endereco-adicional').val(data.end_bairro);
                    $('#cidade-endereco-adicional').val(data.end_cidade);
                    $('#uf-endereco-adicional').val(data.end_uf);
                    $('#btn-salvar-endereco').text('Atualizar Endereço');
                }
            });
    });

    // Ação de Excluir Cliente (principal)
    $('#example-clientes tbody').on('click', '.btn-excluir-cliente', function (e) {
        e.preventDefault();
        $('#nome-cliente-excluir').text($(this).data('nome'));
        $('#id-cliente-excluir').val($(this).data('id'));
        new bootstrap.Modal($('#modal-confirmar-exclusao-cliente')[0]).show();
    });

    $('#btn-confirmar-exclusao-cliente').on('click', function () {
        const idEntidade = $('#id-cliente-excluir').val();
        $.post('process/excluir_entidade.php', { ent_codigo: idEntidade, csrf_token: csrfToken })
            .done(response => {
                if(response.success) {
                    tableClientes.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success');
                } else {
                    showFeedbackMessage(response.message, 'danger');
                }
            })
            .fail(() => showFeedbackMessage('Erro de comunicação.', 'danger'))
            .always(() => bootstrap.Modal.getInstance($('#modal-confirmar-exclusao-cliente')[0]).hide());
    });
    
    // Ação de Excluir Endereço (adicional)
    $('#tabela-enderecos-cliente tbody').on('click', '.btn-excluir-endereco', function (e) {
        e.preventDefault();
        $('#id-endereco-excluir').val($(this).data('id'));
        new bootstrap.Modal($('#modal-confirmar-exclusao-endereco')[0]).show();
    });

    $('#btn-confirmar-exclusao-endereco').on('click', function() {
        const idEndereco = $('#id-endereco-excluir').val();
        $.post('process/excluir_endereco.php', { end_codigo: idEndereco, csrf_token: csrfToken })
            .done(response => {
                 if(response.success) {
                    tableEnderecos.ajax.reload();
                    tableClientes.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success');
                } else {
                    showFeedbackMessage(response.message, 'danger');
                }
            })
            .fail(() => showFeedbackMessage('Erro de comunicação.', 'danger'))
            .always(() => bootstrap.Modal.getInstance($('#modal-confirmar-exclusao-endereco')[0]).hide());
    });
});
