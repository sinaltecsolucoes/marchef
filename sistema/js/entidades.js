/**
 * entidades.js
 * Script unificado para gerenciar as páginas de Clientes e Fornecedores.
 * Ele detecta a página atual e adapta os seletores e a lógica.
 */
$(document).ready(function () {

    // =================================================================
    // I. DETECÇÃO DE PÁGINA E CONFIGURAÇÃO
    // =================================================================
    const pageType = $('body').data('page-type'); // 'cliente' ou 'fornecedor'
    if (!pageType) return; // Não executa se não for uma página de entidade

    // Define todos os seletores e textos com base na página atual
    const config = {
        cliente: {
            entityName: 'Cliente',
            mainTable: '#example-clientes',
            mainButton: '#btn-adicionar-cliente-main',
            feedbackArea: '#feedback-message-area-cliente',
            radioFilter: 'input[name="filtro_situacao"]',
            modal: '#modal-adicionar-cliente',
            form: '#form-cliente',
            formEndereco: '#form-endereco',
            // Seletores do Modal
            modalLabel: '#modal-adicionar-cliente-label',
            entCodigo: '#ent-codigo',
            codigoInterno: '#codigo-interno',
            endEntidadeId: '#end-entidade-id',
            endCodigo: '#end-codigo',
            razaoSocial: '#razao-social',
            nomeFantasia: '#nome-fantasia',
            inscricaoEstadual: '#inscricao-estadual',
            divInscricaoEstadual: '#div-inscricao-estadual',
            tipoPessoaFisica: '#tipo-pessoa-fisica',
            tipoPessoaJuridica: '#tipo-pessoa-juridica',
            labelCpfCnpj: '#label-cpf-cnpj',
            cpfCnpj: '#cpf-cnpj',
            btnBuscarCnpj: '#btn-buscar-cnpj',
            cnpjFeedback: '#cnpj-feedback',
            tipoEntidade: 'input[name="ent_tipo_entidade"]',
            situacao: '#situacao-cliente',
            textoSituacao: '#texto-situacao-cliente',
            // Endereço Principal
            cep: '#cep-endereco',
            logradouro: '#logradouro-endereco',
            numero: '#numero-endereco',
            complemento: '#complemento-endereco',
            bairro: '#bairro-endereco',
            cidade: '#cidade-endereco',
            uf: '#uf-endereco',
            // Endereço Adicional
            tipoEnderecoAdicional: '#tipo-endereco',
            cepAdicional: '#cep-endereco-adicional',
            btnBuscarCepAdicional: '#btn-buscar-cep-adicional',
            cepFeedbackAdicional: '#cep-feedback-adicional',
            logradouroAdicional: '#logradouro-endereco-adicional',
            numeroAdicional: '#numero-endereco-adicional',
            complementoAdicional: '#complemento-endereco-adicional',
            bairroAdicional: '#bairro-endereco-adicional',
            cidadeAdicional: '#cidade-endereco-adicional',
            ufAdicional: '#uf-endereco-adicional',
            btnSalvarEndereco: '#btn-salvar-endereco',
            btnCancelarEndereco: '#btn-cancelar-edicao-endereco',
            tabelaEnderecos: '#tabela-enderecos-cliente',
            mensagem: '#mensagem-cliente',
            mensagemEndereco: '#mensagem-endereco',
            // Exclusão
            btnExcluir: '.btn-excluir-cliente',
            modalConfirmarExclusao: '#modal-confirmar-exclusao-cliente',
            nomeExcluir: '#nome-cliente-excluir',
            idExcluir: '#id-cliente-excluir',
            btnConfirmarExclusao: '#btn-confirmar-exclusao-cliente'
        },
        fornecedor: {
            entityName: 'Fornecedor',
            mainTable: '#example-fornecedores',
            mainButton: '#btn-adicionar-fornecedor-main',
            feedbackArea: '#feedback-message-area-fornecedor',
            radioFilter: 'input[name="filtro_situacao_forn"]',
            modal: '#modal-adicionar-fornecedor',
            form: '#form-fornecedor',
            formEndereco: '#form-endereco-forn',
            // Seletores do Modal
            modalLabel: '#modal-adicionar-fornecedor-label',
            entCodigo: '#ent-codigo-forn',
            codigoInterno: '#codigo-interno-forn',
            endEntidadeId: '#end-entidade-id-forn',
            endCodigo: '#end-codigo-forn',
            razaoSocial: '#razao-social-forn',
            nomeFantasia: '#nome-fantasia-forn',
            inscricaoEstadual: '#inscricao-estadual-forn',
            divInscricaoEstadual: '#div-inscricao-estadual-forn',
            tipoPessoaFisica: '#tipo-pessoa-fisica-forn',
            tipoPessoaJuridica: '#tipo-pessoa-juridica-forn',
            labelCpfCnpj: '#label-cpf-cnpj-forn',
            cpfCnpj: '#cpf-cnpj-forn',
            btnBuscarCnpj: '#btn-buscar-cnpj-forn',
            cnpjFeedback: '#cnpj-feedback-forn',
            tipoEntidade: 'input[name="ent_tipo_entidade"]',
            situacao: '#situacao-fornecedor',
            textoSituacao: '#texto-situacao-fornecedor',
            // Endereço Principal
            cep: '#cep-endereco-forn',
            logradouro: '#logradouro-endereco-forn',
            numero: '#numero-endereco-forn',
            complemento: '#complemento-endereco-forn',
            bairro: '#bairro-endereco-forn',
            cidade: '#cidade-endereco-forn',
            uf: '#uf-endereco-forn',
            // Endereço Adicional
            tipoEnderecoAdicional: '#tipo-endereco-forn',
            cepAdicional: '#cep-endereco-adicional-forn',
            btnBuscarCepAdicional: '#btn-buscar-cep-adicional-forn',
            cepFeedbackAdicional: '#cep-feedback-adicional-forn',
            logradouroAdicional: '#logradouro-endereco-adicional-forn',
            numeroAdicional: '#numero-endereco-adicional-forn',
            complementoAdicional: '#complemento-endereco-adicional-forn',
            bairroAdicional: '#bairro-endereco-adicional-forn',
            cidadeAdicional: '#cidade-endereco-adicional-forn',
            ufAdicional: '#uf-endereco-adicional-forn',
            btnSalvarEndereco: '#btn-salvar-endereco-forn',
            btnCancelarEndereco: '#btn-cancelar-edicao-endereco-forn',
            tabelaEnderecos: '#tabela-enderecos-fornecedor',
            mensagem: '#mensagem-fornecedor',
            mensagemEndereco: '#mensagem-endereco-forn',
            // Exclusão
            btnExcluir: '.btn-excluir-fornecedor',
            modalConfirmarExclusao: '#modal-confirmar-exclusao-fornecedor',
            nomeExcluir: '#nome-fornecedor-excluir',
            idExcluir: '#id-fornecedor-excluir',
            btnConfirmarExclusao: '#btn-confirmar-exclusao-fornecedor'
        }
    };
    const C = config[pageType]; // C de Configuração atual

    // =================================================================
    // II. VARIÁVEIS E CONFIGURAÇÕES GERAIS
    // =================================================================
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    let tableEntidades, tableEnderecos;
    let triggerButton = null;
    let lastCnpjRequest = '';

    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
        if (!options.crossDomain) {
            jqXHR.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }
    });

    // =================================================================
    // III. SELETORES GLOBAIS (CACHE DE ELEMENTOS)
    // =================================================================
    const $modal = $(C.modal);
    const $formEntidade = $(C.form);
    const $formEndereco = $(C.formEndereco);
    const $cpfCnpj = $(C.cpfCnpj);

    // =================================================================
    // IV. FUNÇÕES AUXILIARES (Genéricas)
    // =================================================================

    function showFeedbackMessage(message, type = 'success', area = C.feedbackArea) {
        const $area = $(area);
        const alertClass = (type === 'success') ? 'alert-success' : 'alert-danger';
        $area.empty().removeClass('alert-success alert-danger').addClass(`alert ${alertClass}`).text(message).fadeIn();
        setTimeout(() => $area.fadeOut('slow'), 5000);
    }

    function toggleInscricaoEstadualField() {
        const isJuridica = $(C.tipoPessoaJuridica).is(':checked');
        $(C.divInscricaoEstadual).toggle(isJuridica);
        if (!isJuridica) $(C.inscricaoEstadual).val('');
    }

    function applyCpfCnpjMask() {
        if ($(C.tipoPessoaFisica).is(':checked')) {
            $(C.labelCpfCnpj).text('CPF');
            $cpfCnpj.attr('placeholder', '000.000.000-00').mask('000.000.000-00', { reverse: true });
        } else {
            $(C.labelCpfCnpj).text('CNPJ');
            $cpfCnpj.attr('placeholder', '00.000.000/0000-00').mask('00.000.000/0000-00', { reverse: true });
        }
        toggleInscricaoEstadualField();
    }

    function setPrincipalAddressFieldsReadonly(isReadonly) {
        const fields = `${C.cep}, ${C.logradouro}, ${C.numero}, ${C.complemento}, ${C.bairro}, ${C.cidade}, ${C.uf}`;
        $(fields).prop('readonly', isReadonly).toggleClass('bg-light', isReadonly);
    }

    function resetModal() {
        $formEntidade[0].reset();
        $formEndereco[0].reset();
        $(`${C.entCodigo}, ${C.endCodigo}, ${C.endEntidadeId}, ${C.codigoInterno}`).val('');
        $(`${C.entCodigo}, ${C.endCodigo}, ${C.endEntidadeId}`).val('');
        $(`${C.mensagem}, ${C.mensagemEndereco}, ${C.cnpjFeedback}, #cep-feedback-principal-forn, #cep-feedback-adicional-forn`).empty().removeClass();
        $(C.situacao).prop('checked', true).trigger('change');
        $(C.tipoPessoaFisica).prop('checked', true).trigger('change');
        $(`${C.tipoEntidade}[value="${C.entityName}"]`).prop('checked', true);
        $modal.find('.nav-tabs .nav-link').first().trigger('click');
        $modal.find('.nav-tabs .nav-link').last().addClass('disabled');
        if (tableEnderecos) tableEnderecos.clear().draw();
        setPrincipalAddressFieldsReadonly(false);
        lastCnpjRequest = '';
    }

    function buscarCnpj(cnpj) {
        if (cnpj === lastCnpjRequest) return;
        const feedback = $(C.cnpjFeedback);
        feedback.text('Buscando dados...').addClass('text-muted');
        fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`)
            .then(response => response.ok ? response.json() : Promise.reject('CNPJ não encontrado.'))
            .then(data => {
                feedback.text('Dados encontrados!').addClass('text-success');
                lastCnpjRequest = cnpj;
                $(C.razaoSocial).val(data.razao_social);
                $(C.nomeFantasia).val(data.nome_fantasia);
                $(C.cep).val(data.cep);
                $(C.logradouro).val(data.logradouro);
                $(C.numero).val(data.numero);
                $(C.bairro).val(data.bairro);
                $(C.cidade).val(data.municipio);
                $(C.uf).val(data.uf);
            })
            .catch(error => {
                feedback.text(error.toString()).addClass('text-danger');
                lastCnpjRequest = '';
            });
    }

    function loadEnderecosTable(entidadeId) {
        if ($.fn.DataTable.isDataTable(C.tabelaEnderecos)) {
            tableEnderecos.destroy();
        }
        tableEnderecos = $(C.tabelaEnderecos).DataTable({
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
    // V. INICIALIZAÇÃO DA TABELA PRINCIPAL
    // =================================================================
    tableEntidades = $(C.mainTable).DataTable({
        ajax: {
            url: "process/listar_entidades.php",
            type: "POST",
            data: d => {
                d.filtro_situacao = $(C.radioFilter + ':checked').val();
                d.tipo_entidade = C.entityName;
            }
        },
        responsive: true,
        columns: [
            { "data": "ent_situacao", "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' },
            { "data": "ent_tipo_entidade" },
            { "data": "ent_codigo_interno" },
            { "data": "ent_razao_social" },
            { "data": null, "render": (data, type, row) => row.ent_tipo_pessoa === 'F' ? row.ent_cpf : row.ent_cnpj },
            { "data": "end_logradouro", "render": (data, type, row) => data ? `${row.end_logradouro || ''}, ${row.end_numero || ''}` : 'N/A' },
            { "data": "ent_codigo", "orderable": false, "render": (data, type, row) => `<a href="#" class="btn btn-warning btn-sm btn-editar-${pageType} me-1" data-id="${data}">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-${pageType}" data-id="${data}" data-nome="${row.ent_razao_social}">Excluir</a>` }
        ],
        language: { "url": "../vendor/DataTables/Portuguese-Brasil.json" }
    });

    // =================================================================
    // VI. EVENT HANDLERS
    // =================================================================

    $(C.radioFilter).on('change', () => tableEntidades.ajax.reload());

    $modal.on('show.bs.modal', function (event) {
        triggerButton = $(event.relatedTarget);
        if (triggerButton && triggerButton.is(C.mainButton)) {
            resetModal();
            $(C.modalLabel).text(`Adicionar ${C.entityName}`);
        }
    }).on('hidden.bs.modal', function () {
        if (triggerButton && triggerButton.length) {
            setTimeout(() => { triggerButton.focus(); triggerButton = null; }, 100);
        }
    });

    $modal.find('.nav-tabs .nav-link').on('click', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        $modal.find('.nav-tabs .nav-link, .tab-content .tab-pane').removeClass('active show');
        $(this).addClass('active');
        $($(this).data('tab-target')).addClass('show active');
    });

    $modal.find('input[name="ent_tipo_pessoa"]').on('change', function () {
        applyCpfCnpjMask();
        $(C.btnBuscarCnpj).toggle($(this).val() === 'J');
    }).trigger('change');

    $modal.on('click', C.btnBuscarCnpj, () => {
        const cnpjValue = $cpfCnpj.val().replace(/\D/g, '');
        if (cnpjValue.length === 14) buscarCnpj(cnpjValue);
    });

    $cpfCnpj.on('blur', function () {
        if ($(C.tipoPessoaJuridica).is(':checked')) {
            const cnpjValue = $(this).val().replace(/\D/g, '');
            if (cnpjValue.length === 14) buscarCnpj(cnpjValue);
        }
    });

    $(C.situacao).on('change', function () {
        $(C.textoSituacao).text(this.checked ? 'Ativo' : 'Inativo');
    });

    $(C.mainTable).on('click', `.btn-editar-${pageType}`, function (e) {
        e.preventDefault();
        const idEntidade = $(this).data('id');
        $.getJSON('process/get_entidade_data.php', { id: idEntidade })
            .done(response => {
                if (response.success && response.data) {
                    resetModal();
                    const data = response.data;
                    $(C.modalLabel).text(`Editar ${C.entityName}`);
                    $(C.entCodigo + ', ' + C.endEntidadeId).val(data.ent_codigo);
                    $(C.codigoInterno).val(data.ent_codigo_interno); 
                    $(C.razaoSocial).val(data.ent_razao_social);
                    $(C.nomeFantasia).val(data.ent_nome_fantasia);
                    $(C.inscricaoEstadual).val(data.ent_inscricao_estadual);
                    if (data.ent_tipo_pessoa === 'F') $(C.tipoPessoaFisica).prop('checked', true); else $(C.tipoPessoaJuridica).prop('checked', true);
                    $cpfCnpj.val(data.ent_tipo_pessoa === 'F' ? data.ent_cpf : data.ent_cnpj);
                    $modal.find('input[name="ent_tipo_pessoa"]:checked').trigger('change');
                    if (data.ent_tipo_entidade === C.entityName) $(`${C.tipoEntidade}[value="${C.entityName}"]`).prop('checked', true); else $(`${C.tipoEntidade}[value="Cliente e Fornecedor"]`).prop('checked', true);
                    $(C.situacao).prop('checked', data.ent_situacao === 'A').trigger('change');
                    $(C.cep).val(data.end_cep);
                    $(C.logradouro).val(data.end_logradouro);
                    $(C.numero).val(data.end_numero);
                    $(C.complemento).val(data.end_complemento);
                    $(C.bairro).val(data.end_bairro);
                    $(C.cidade).val(data.end_cidade);
                    $(C.uf).val(data.end_uf);
                    setPrincipalAddressFieldsReadonly(true);
                    $modal.find('.nav-tabs .nav-link').last().removeClass('disabled');
                    loadEnderecosTable(data.ent_codigo);
                    new bootstrap.Modal($modal[0]).show();
                } else {
                    showFeedbackMessage(response.message || 'Erro ao carregar dados.', 'danger');
                }
            })
            .fail(() => showFeedbackMessage('Falha na comunicação com o servidor.', 'danger'));
    });

    $formEntidade.on('submit', function (e) {
        e.preventDefault();
        const idEntidade = $(C.entCodigo).val();
        const url = idEntidade ? 'process/editar_entidade.php' : 'process/cadastrar_entidade.php';
        $.ajax({
            type: 'POST', url: url, data: new FormData(this), dataType: 'json', processData: false, contentType: false,
            success: response => {
                if (response.success) {
                    if (!idEntidade && response.ent_codigo) {
                        $(C.entCodigo + ', ' + C.endEntidadeId).val(response.ent_codigo);
                        $modal.find('.nav-tabs .nav-link').last().removeClass('disabled');
                        loadEnderecosTable(response.ent_codigo);
                    }
                    tableEntidades.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success', C.mensagem);
                } else {
                    showFeedbackMessage(response.message, 'danger', C.mensagem);
                }
            },
            error: () => showFeedbackMessage('Erro de comunicação ao salvar.', 'danger', C.mensagem)
        });
    });

    // --- Eventos da Aba de Endereços Adicionais ---
    $modal.on('click', C.btnBuscarCepAdicional, function () {
        searchCep($(C.cepAdicional).val(), C.cepFeedbackAdicional, {
            logradouro: C.logradouroAdicional,
            bairro: C.bairroAdicional,
            cidade: C.cidadeAdicional,
            uf: C.ufAdicional,
            numero: C.numeroAdicional
        });
    });

    $modal.on('click', C.btnCancelarEndereco, () => {
        $formEndereco[0].reset();
        $(C.endCodigo).val('');
        $(C.btnSalvarEndereco).text('Salvar Endereço Adicional');
    });

    $formEndereco.on('submit', function (e) {
        e.preventDefault();
        const idEndereco = $(C.endCodigo).val();
        const url = idEndereco ? 'process/editar_endereco.php' : 'process/cadastrar_endereco.php';
        $.ajax({
            type: 'POST', url: url, data: new FormData(this), dataType: 'json', processData: false, contentType: false,
            success: response => {
                if (response.success) {
                    $formEndereco[0].reset();
                    $(C.endCodigo).val('');
                    $(C.btnSalvarEndereco).text('Salvar Endereço Adicional');
                    tableEnderecos.ajax.reload();
                    tableEntidades.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success', C.mensagemEndereco);
                } else {
                    showFeedbackMessage(response.message, 'danger', C.mensagemEndereco);
                }
            },
            error: () => showFeedbackMessage('Erro de comunicação.', 'danger', C.mensagemEndereco)
        });
    });

    $(C.tabelaEnderecos).on('click', '.btn-editar-endereco', function (e) {
        e.preventDefault();
        const idEndereco = $(this).data('id');
        $.getJSON('process/get_endereco_data.php', { id: idEndereco })
            .done(response => {
                if (response.success && response.data) {
                    const data = response.data;
                    $(C.endCodigo).val(data.end_codigo);
                    $(C.tipoEnderecoAdicional).val(data.end_tipo_endereco);
                    $(C.cepAdicional).val(data.end_cep);
                    $(C.logradouroAdicional).val(data.end_logradouro);
                    $(C.numeroAdicional).val(data.end_numero);
                    $(C.complementoAdicional).val(data.end_complemento);
                    $(C.bairroAdicional).val(data.end_bairro);
                    $(C.cidadeAdicional).val(data.end_cidade);
                    $(C.ufAdicional).val(data.end_uf);
                    $(C.btnSalvarEndereco).text('Atualizar Endereço');
                }
            });
    });

    $(C.mainTable).on('click', C.btnExcluir, function (e) {
        e.preventDefault();
        $(C.nomeExcluir).text($(this).data('nome'));
        $(C.idExcluir).val($(this).data('id'));
        new bootstrap.Modal($(C.modalConfirmarExclusao)[0]).show();
    });

    $(C.btnConfirmarExclusao).on('click', function () {
        const idEntidade = $(C.idExcluir).val();
        $.post('process/excluir_entidade.php', { ent_codigo: idEntidade, csrf_token: csrfToken })
            .done(response => {
                if (response.success) {
                    tableEntidades.ajax.reload(null, false);
                    showFeedbackMessage(response.message, 'success');
                } else {
                    showFeedbackMessage(response.message, 'danger');
                }
            })
            .fail(() => showFeedbackMessage('Erro de comunicação.', 'danger'))
            .always(() => bootstrap.Modal.getInstance($(C.modalConfirmarExclusao)[0]).hide());
    });
});
