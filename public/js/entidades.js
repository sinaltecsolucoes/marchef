$(document).ready(function () {
    // Detecta se estamos na página de cliente ou fornecedor
    const pageType = $('body').data('page-type');
    if (!pageType) {
        return;
    }

    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalEntidade = $('#modal-adicionar-entidade');
    const $formEntidade = $('#form-entidade');
    const $formEndereco = $('#form-endereco-adicional'); // Assumindo um ID para o form de endereço
    const $tipoPessoaRadios = $('input[name="ent_tipo_pessoa"]');
    const $cpfCnpjInput = $('#cpf-cnpj');
    const $labelCpfCnpj = $('#label-cpf-cnpj');
    const $btnBuscarCnpj = $('#btn-buscar-cnpj');
    const $divInscricaoEstadual = $('#div-inscricao-estadual');
    const $cepFeedbackAdicional = $('#cep-feedback-adicional');
    const $btnBuscarCepAdicional = $('#btn-buscar-cep-adicional');
    const $btnSalvarEndereco = $('#btn-salvar-endereco');
    const $btnCancelarEdicaoEndereco = $('#btn-cancelar-edicao-endereco');
    let tableEntidades, tableEnderecos;

    // =================================================================
    // FUNÇÕES DE LÓGICA DE NEGÓCIO DO FORMULÁRIO
    // =================================================================

    /**
     * Função central para controlar a UI do campo CPF/CNPJ.
     * @param {string} tipoPessoa - 'F' para Física, 'J' para Jurídica.
     * @param {boolean} limparValor - Se true, limpa o valor do input.
     */
    function updatePessoaFields(tipoPessoa, limparValor = false) {
        // Garante que o input esteja sem máscara antes de qualquer ação
        $cpfCnpjInput.unmask();

        if (limparValor) {
            $cpfCnpjInput.val('');
        }

        if (tipoPessoa === 'J') {
            $labelCpfCnpj.text('CNPJ');
            $cpfCnpjInput.attr('placeholder', '00.000.000/0000-00');
            $cpfCnpjInput.mask('00.000.000/0000-00');
            $btnBuscarCnpj.show();
            $divInscricaoEstadual.show();
        } else { // Padrão é 'F' (Pessoa Física)
            $labelCpfCnpj.text('CPF');
            $cpfCnpjInput.attr('placeholder', '000.000.000-00');
            $cpfCnpjInput.mask('000.000.000-00');
            $btnBuscarCnpj.hide();
            $divInscricaoEstadual.hide();
        }
    }

    /**
     * Busca dados de um CNPJ na BrasilAPI e preenche o formulário.
     */
    function buscarDadosCNPJ() {
        const cnpj = $cpfCnpjInput.val().replace(/\D/g, ''); // Remove a formatação
        if (cnpj.length !== 14) {
            notificacaoErro('CNPJ Inválido', 'Por favor, digite um CNPJ válido com 14 dígitos.');
            return;
        }

        const feedback = $('#cnpj-feedback');
        feedback.text('Buscando...').removeClass('text-danger text-success');
        $btnBuscarCnpj.prop('disabled', true);

        fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('CNPJ não encontrado ou inválido.');
                }
                return response.json();
            })
            .then(data => {
                feedback.text('Dados carregados com sucesso!').addClass('text-success');
                // Preenche os campos do formulário
                $('#razao-social').val(data.razao_social);
                $('#nome-fantasia').val(data.nome_fantasia);
                $('#cep-endereco').val(data.cep.replace(/\D/g, ''));
                $('#logradouro-endereco').val(data.logradouro);
                $('#numero-endereco').val(data.numero);
                $('#complemento-endereco').val(data.complemento);
                $('#bairro-endereco').val(data.bairro);
                $('#cidade-endereco').val(data.municipio);
                $('#uf-endereco').val(data.uf);

            })
            .catch(error => {
                feedback.text(error.message).addClass('text-danger');
            })
            .finally(() => {
                $btnBuscarCnpj.prop('disabled', false);
            });
    }

    // =================================================================
    // LÓGICA DA ABA "ENDEREÇOS ADICIONAIS"
    // =================================================================

    function loadEnderecosTable(entidadeId) {
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-adicionais')) {
            tableEnderecos.destroy();
        }
        tableEnderecos = $('#tabela-enderecos-adicionais').DataTable({
            "ajax": {
                "url": "ajax_router.php?action=listarEnderecos",
                "type": "POST",
                "data": { ent_codigo: entidadeId, csrf_token: csrfToken },
                "error": function (xhr, error, thrown) {
                    showFeedbackMessage('Erro ao carregar endereços: ' + (xhr.responseJSON?.message || 'Erro desconhecido'), 'danger', '#mensagem-endereco');
                }
            },
            "columns": [
                { "data": "end_tipo_endereco" },
                { "data": "end_logradouro" },
                { "data": null, "render": (data, type, row) => `${row.end_cidade || ''}/${row.end_uf || ''}` },
                { "data": "end_codigo", "orderable": false, "render": data => `<a href="#" class="btn btn-warning btn-sm btn-editar-endereco me-1" data-id="${data}">Editar</a><a href="#" class="btn btn-danger btn-sm btn-excluir-endereco" data-id="${data}">Excluir</a>` }
            ],
            paging: false, searching: false, info: false,
            // language: { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" }
            language: { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
        });
    }

    function buscarCep(cep, feedbackElement, fields) {
        cep = cep.replace(/\D/g, '');
        if (cep.length !== 8) {
            $(feedbackElement).text('Por favor, digite um CEP válido com 8 dígitos.').addClass('text-danger');
            return;
        }
        $(feedbackElement).text('Buscando...').removeClass('text-danger text-success').addClass('text-muted');
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.ok ? response.json() : Promise.reject('CEP não encontrado.'))
            .then(data => {
                if (data.erro) throw new Error('CEP não encontrado.');
                $(feedbackElement).text('CEP encontrado!').addClass('text-success');
                $(fields.logradouro).val(data.logradouro);
                $(fields.bairro).val(data.bairro);
                $(fields.cidade).val(data.localidade);
                $(fields.uf).val(data.uf);
            })
            .catch(error => {
                $(feedbackElement).text(error.message).addClass('text-danger');
            });
    }

    function showFeedbackMessage(message, type = 'success', area = '#feedback-message-area-entidade') {
        const $area = $(area);
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        $area.empty().removeClass('alert-success alert-danger').addClass(`alert ${alertClass}`).text(message).fadeIn();
        setTimeout(() => $area.fadeOut('slow'), 5000);
    }

    function setPrincipalAddressFieldsReadonly(isReadonly) {
        const fields = '#cep-endereco, #logradouro-endereco, #numero-endereco, #complemento-endereco, #bairro-endereco, #cidade-endereco, #uf-endereco';
        $(fields).prop('readonly', isReadonly).toggleClass('bg-light', isReadonly);
    }

    function resetModal() {
        $formEntidade[0].reset();
        $formEndereco[0].reset();
        $('#ent-codigo, #end-codigo, #end-entidade-id').val('');
        $('#mensagem-entidade, #mensagem-endereco, #cnpj-feedback, #cep-feedback-adicional').empty().removeClass();
        $formEntidade.find(`input[name="ent_tipo_pessoa"][value="F"]`).prop('checked', true);
        $formEntidade.find(`input[name="ent_tipo_entidade"][value="${pageType === 'cliente' ? 'Cliente' : 'Fornecedor'}"]`).prop('checked', true);
        $('#situacao-entidade').prop('checked', true);
        $('#modal-adicionar-entidade-label').text(`Adicionar ${pageType === 'cliente' ? 'Cliente' : 'Fornecedor'}`);
        $('#enderecos-tab').addClass('disabled');
        if (tableEnderecos) tableEnderecos.clear().draw();
        setPrincipalAddressFieldsReadonly(false);
    }

    /**
     * Aplica a máscara de CPF a uma string de números, usando o plugin jQuery Mask.
     * @param {string} cpf O CPF sem formatação.
     * @returns {string} O CPF formatado.
     */
    function formatarCPF(cpf) {
        if (!cpf) return '';
        // Cria um elemento temporário em memória para aplicar a máscara e obter o valor formatado.
        return $('<span>').text(cpf).mask('000.000.000-00').text();
    }

    /**
     * Aplica a máscara de CNPJ a uma string de números, usando o plugin jQuery Mask.
     * @param {string} cnpj O CNPJ sem formatação.
     * @returns {string} O CNPJ formatado.
     */
    function formatarCNPJ(cnpj) {
        if (!cnpj) return '';
        // Cria um elemento temporário em memória para aplicar a máscara e obter o valor formatado.
        return $('<span>').text(cnpj).mask('00.000.000/0000-00').text();
    }

    // =================================================================
    // INICIALIZAÇÃO DA TABELA DATATABLES
    // =================================================================
    tableEntidades = $('#tabela-entidades').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": "ajax_router.php?action=listarEntidades",
            "type": "POST",
            "data": function (d) {
                // Envia os filtros atuais para o backend
                d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val();
                d.tipo_entidade = pageType; // Envia 'cliente' ou 'fornecedor'
                d.filtro_tipo_entidade = $('#filtro-tipo-entidade').val(); // Envia o valor do novo filtro
                d.csrf_token = csrfToken;
            }
        },
        "responsive": true,
        "columns": [
            {
                "data": "ent_situacao", "className": "text-center", "width": "5%",
                "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'
            },
            { "data": "ent_tipo_entidade", "className": "text-center", "width": "7%" },
            { "data": "ent_codigo_interno", "className": "text-center", "width": "5%" },
            { "data": "ent_razao_social", "width": "20%" },
            {
                "data": null,
                "className": "text-center",
                "width": "8%",
                "render": function (data, type, row) {
                    if (row.ent_tipo_pessoa === 'F') {
                        return formatarCPF(row.ent_cpf);
                    } else {
                        return formatarCNPJ(row.ent_cnpj);
                    }
                }
            },
            { "data": "end_logradouro", "width": "10%", "render": (data, type, row) => data ? `${row.end_logradouro || ''}, ${row.end_numero || ''}` : 'N/A' },
            {
                "data": "ent_codigo", "orderable": false, "className": "text-center", "width": "8%", "render": (data, type, row) =>
                    `<a href="#" class="btn btn-warning btn-sm btn-editar-entidade me-1" data-id="${data}">Editar</a>` +
                    `<a href="#" class="btn btn-danger btn-sm btn-inativar-entidade" data-id="${data}" data-nome="${row.ent_razao_social}">Inativar</a>`
            }
        ],
        //"language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" }
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // =================================================================
    // EVENTOS (AÇÕES DO USUÁRIO)
    // =================================================================

    // Abrir modal para Adicionar
    $('#btn-adicionar-entidade').on('click', function () {
        $formEntidade[0].reset();
        $modalEntidade.modal('show');
    });

    // Dispara a lógica de UI quando o tipo de pessoa muda
    $tipoPessoaRadios.on('change', function () {

        // FORMA ROBUSTA DE PEGAR O VALOR:
        // Em vez de confiar em 'this', buscamos no DOM pelo radio que está ":checked".
        const valorSelecionado = $('input[name="ent_tipo_pessoa"]:checked').val();

        // Agora usamos esse valor garantido para atualizar a interface.
        updatePessoaFields(valorSelecionado, true);

    });

    // Dispara a busca de CNPJ ao clicar no botão
    $btnBuscarCnpj.on('click', buscarDadosCNPJ);

    // Recarrega a tabela quando os filtros mudam
    $('input[name="filtro_situacao"], #filtro-tipo-entidade').on('change', () => {
        tableEntidades.ajax.reload();
    });

    $btnBuscarCepAdicional.on('click', function () {
        buscarCep($('#cep-endereco-adicional').val(), '#cep-feedback-adicional', {
            logradouro: '#logradouro-endereco-adicional',
            bairro: '#bairro-endereco-adicional',
            cidade: '#cidade-endereco-adicional',
            uf: '#uf-endereco-adicional',
            numero: '#numero-endereco-adicional'
        });
    });

    $btnCancelarEdicaoEndereco.on('click', function () {
        $formEndereco[0].reset();
        $('#end-codigo').val('');
        $btnSalvarEndereco.text('Salvar Endereço Adicional');
        $('#mensagem-endereco').empty().removeClass();
    });

    // Limpa o modal ao clicar em "Adicionar"
    $modalEntidade.on('show.bs.modal', function (event) {
        if ($(event.relatedTarget).is('#btn-adicionar-entidade')) {
            const singular = pageType === 'cliente' ? 'Cliente' : 'Fornecedor';
            $formEntidade[0].reset();
            $('#ent-codigo').val('');
            $('#modal-adicionar-entidade-label').text('Adicionar ' + singular);

            // Seleciona o radio button de tipo de entidade apropriado
            $formEntidade.find(`input[name="ent_tipo_entidade"][value="${singular}"]`).prop('checked', true);

            // Define Pessoa Física como padrão
            $formEntidade.find(`input[name="ent_tipo_pessoa"][value="F"]`).prop('checked', true);
            $('#enderecos-tab').addClass('disabled');

            updatePessoaFields();
        }
    });

    // Garante que a máscara seja aplicada após o modal ser exibido
    $modalEntidade.on('shown.bs.modal', function (event) {
        if ($(event.relatedTarget).is('#btn-adicionar-entidade')) {
            updatePessoaFields(true); // Aplica a máscara de CPF por padrão
        }
    });

    // Submissão do formulário (Salvar/Editar)
    $formEntidade.on('submit', function (e) {
        e.preventDefault();
        const id = $('#ent-codigo').val();
        const url = `ajax_router.php?action=salvarEntidade`;
        const formData = new FormData(this);
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                $modalEntidade.modal('hide');
                tableEntidades.ajax.reload(null, false);
                notificacaoSucesso('Sucesso!', response.message);
            } else {
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar a entidade.');
        });
    });

    $formEndereco.on('submit', function (e) {
        e.preventDefault();
        const idEndereco = $('#end-codigo').val();
        const entidadeId = $('#end-entidade-id').val() || $('#ent-codigo').val();
        if (!entidadeId) {
            notificacaoErro('Atenção', 'Salve a entidade principal antes de adicionar endereços.');
            return;
        }
        const formData = new FormData(this);
        formData.append('end_entidade_id', entidadeId);
        
        $.ajax({
            url: 'ajax_router.php?action=salvarEndereco',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                $formEndereco[0].reset();
                $('#end-codigo').val('');
                $btnSalvarEndereco.text('Salvar Endereço Adicional');
                tableEnderecos.ajax.reload();
                notificacaoSucesso('Sucesso!', response.message); // << REATORADO
            } else {
                notificacaoErro('Erro ao Salvar', response.message); // << REATORADO
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar o endereço.'); // << REATORADO
        });
    });

    $('#tabela-entidades').on('click', '.btn-editar-entidade', function () {
        const entidadeId = $(this).data('id');
        $.ajax({
            url: `ajax_router.php?action=getEntidade`,
            type: 'POST',
            data: { ent_codigo: entidadeId, csrf_token: csrfToken },
            dataType: 'json',
        })
            .done(function (response) {
                if (response.success) {
                    const data = response.data;
                    const entidadeId = data.ent_codigo;

                    // Limpa o formulário de estados anteriores
                    //  $formEntidade[0].reset();
                    $('#mensagem-entidade').empty().removeClass();

                    // --- Dados Principais ---
                    $('#ent-codigo').val(data.ent_codigo);
                    $('#razao-social').val(data.ent_razao_social);
                    $('#nome-fantasia').val(data.ent_nome_fantasia);
                    $('#codigo-interno').val(data.ent_codigo_interno);
                    $('#inscricao-estadual').val(data.ent_inscricao_estadual);
                    $('#situacao-entidade').prop('checked', data.ent_situacao === 'A');

                    // --- Tipo de Entidade ---
                    $formEntidade.find(`input[name="ent_tipo_entidade"][value="${data.ent_tipo_entidade}"]`).prop('checked', true);

                    // --- LÓGICA CORRETA PARA CPF/CNPJ ---
                    const tipoPessoaDoBanco = data.ent_tipo_pessoa;
                    // 1. Marca o radio correto ('F' ou 'J') SEM alterar seu valor
                    $formEntidade.find(`input[name="ent_tipo_pessoa"][value="${tipoPessoaDoBanco}"]`).prop('checked', true);
                    // 2. Chama a função para ajustar a UI (máscara, etc.)
                    updatePessoaFields(tipoPessoaDoBanco, false); // false para não limpar o valor
                    // 3. Preenche o valor no campo já com a máscara correta
                    const cpfCnpjValor = tipoPessoaDoBanco === 'F' ? data.ent_cpf : data.ent_cnpj;
                    $cpfCnpjInput.val(cpfCnpjValor);
                    // 4. "Avisamos" o plugin para aplicar a máscara no valor que acabamos de inserir
                    $cpfCnpjInput.trigger('input');


                    // --- Endereço Principal ---
                    $('#cep-endereco').val(data.end_cep);
                    $('#logradouro-endereco').val(data.end_logradouro);
                    $('#numero-endereco').val(data.end_numero);
                    $('#complemento-endereco').val(data.end_complemento);
                    $('#bairro-endereco').val(data.end_bairro);
                    $('#cidade-endereco').val(data.end_cidade);
                    $('#uf-endereco').val(data.end_uf);

                    // --- Ajustes Finais no Modal ---
                    $('#modal-adicionar-entidade-label').text('Editar: ' + (data.ent_nome_fantasia || data.ent_razao_social));
                    $('#enderecos-tab').removeClass('disabled');
                    loadEnderecosTable(entidadeId);
                    setPrincipalAddressFieldsReadonly(true);
                    // Garante que a aba de dados principais esteja sempre ativa ao abrir o modal de edição.
                    $('#dados-tab').tab('show');
                    $modalEntidade.modal('show');

                } else {
                    notificacaoErro('Erro ao Carregar', response.message); // << REATORADO
                }
            }).fail(function () {
                notificacaoErro('Erro de Comunicação', 'Não foi possível carregar os dados da entidade.'); // << REATORADO
            });
    });

    $('#tabela-entidades').on('click', '.btn-inativar-entidade', function () {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        const tituloConfirmacao = pageType === 'cliente' ? 'Inativar Cliente?' : 'Inativar Fornecedor?';


        confirmacaoAcao(
            tituloConfirmacao, // << USA O TÍTULO DINÂMICO AQUI
            `Tem a certeza de que deseja inativar "${nome}"?`
        ).then((result) => {
            if (result.isConfirmed) {
                // Se o usuário confirmar, executa a chamada AJAX
                $.ajax({
                    url: `ajax_router.php?action=inativarEntidade`,
                    type: 'POST',
                    data: { ent_codigo: id, csrf_token: csrfToken },
                    dataType: 'json',
                }).done(function (response) {
                    if (response.success) {
                        tableEntidades.ajax.reload();
                        notificacaoSucesso('Inativado!', response.message);
                    } else {
                        notificacaoErro('Erro ao Inativar', response.message);
                    }
                }).fail(function () {
                    notificacaoErro('Erro de Comunicação', 'Não foi possível inativar a entidade.');
                });
            }
        });
    });

    $('#tabela-enderecos-adicionais').on('click', '.btn-editar-endereco', function (e) {
        e.preventDefault();
        const idEndereco = $(this).data('id');
        $.ajax({
            url: `ajax_router.php?action=getEndereco`,
            type: 'POST',
            data: { end_codigo: idEndereco, csrf_token: csrfToken },
            dataType: 'json',
        }).done(function (response) {
            if (response.success) {
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
                $btnSalvarEndereco.text('Atualizar Endereço');
                $('#enderecos-tab').tab('show');
            } else {
                notificacaoErro('Erro ao Carregar', response.message);
            }
        }).fail(function (xhr, status, error) {
            notificacaoErro('Erro de Comunicação', 'Não foi possível carregar o endereço.');
        });
    });

    $('#tabela-enderecos-adicionais').on('click', '.btn-excluir-endereco', function (e) {
        e.preventDefault();
        const idEndereco = $(this).data('id');
        confirmacaoAcao(
            'Excluir Endereço?',
            'Tem a certeza de que deseja excluir este endereço?'
        ).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirEndereco',
                    type: 'POST',
                    data: { end_codigo: idEndereco, csrf_token: csrfToken },
                    dataType: 'json',
                }).done(function (response) {
                    if (response.success) {
                        tableEnderecos.ajax.reload();
                        notificacaoSucesso('Excluído!', response.message); // << REATORADO
                    } else {
                        notificacaoErro('Erro ao Excluir', response.message); // << REATORADO
                    }
                }).fail(function () {
                    notificacaoErro('Erro de Comunicação', 'Não foi possível excluir o endereço.'); // << REATORADO
                });
            }
        });
    });
});
