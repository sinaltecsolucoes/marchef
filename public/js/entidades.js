$(document).ready(function () {
    // Detecta se estamos na página de cliente ou fornecedor ou fazenda ou transportadora
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
    const $labelRazaoSocial = $('#label-razao-social');
    const $labelNomeFantasia = $('#label-nome-fantasia');
    const $btnBuscarCnpj = $('#btn-buscar-cnpj');
    const $divInscricaoEstadual = $('#div-inscricao-estadual');
    const $cepFeedbackAdicional = $('#cep-feedback-adicional');
    const $btnBuscarCepAdicional = $('#btn-buscar-cep-adicional');
    const $btnSalvarEndereco = $('#btn-salvar-endereco');
    const $btnCancelarEdicaoEndereco = $('#btn-cancelar-edicao-endereco');
    let tableEntidades, tableEnderecos;

    // Mapa para exibição amigável na Tabela (Baseado no valor do Banco)
    const MAPA_LABELS_TABELA = {
        'Cliente': 'Cliente',
        'Fornecedor': 'Fornecedor',
        'Transportadora': 'Transportadora',
        'Fazenda': 'Fazenda (Origem)',
        'Fornecedor e Fazenda': 'Fornecedor e Fazenda (Origem)'
    };

    // Mapa para nomes singulares (Baseado no pageType da URL)
    const MAPA_NOMES_SINGULAR = {
        'cliente': 'Cliente',
        'fornecedor': 'Fornecedor',
        'fazenda': 'Fazenda',
        'transportadora': 'Transportadora'
    };

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
            $labelRazaoSocial.text('Razão Social');
            $labelNomeFantasia.text('Nome Fantasia');
            $cpfCnpjInput.attr('placeholder', '00.000.000/0000-00');
            $cpfCnpjInput.mask('00.000.000/0000-00');
            $btnBuscarCnpj.show();
            $divInscricaoEstadual.show();
        } else { // Padrão é 'F' (Pessoa Física)
            $labelCpfCnpj.text('CPF');
            $labelRazaoSocial.text('Nome Completo');
            $labelNomeFantasia.text('Apelido (Nome Comum)');
            $cpfCnpjInput.attr('placeholder', '000.000.000-00');
            $cpfCnpjInput.mask('000.000.000-00');
            $btnBuscarCnpj.hide();
            $divInscricaoEstadual.hide();
        }
    }

    /**
     * Busca dados de um CNPJ usando fallback:
     * 1 - CNPJ.ws
     * 2 - CNPJá
     * 3 - BrasilAPI
     */
    function buscarDadosCNPJ() {
        const cnpj = $cpfCnpjInput.val().replace(/\D/g, '');
        if (cnpj.length !== 14) {
            notificacaoErro('CNPJ Inválido', 'Por favor, digite um CNPJ válido com 14 dígitos.');
            return;
        }

        const feedback = $('#cnpj-feedback');
        feedback.text('Buscando...').removeClass('text-danger text-success');
        $btnBuscarCnpj.prop('disabled', true);

        const toUpper = str => (str || '').toUpperCase();

        // 1 - Tenta CNPJ.ws
        fetch(`https://publica.cnpj.ws/cnpj/${cnpj}`)
            .then(response => {
                if (!response.ok) throw new Error('CNPJ não encontrado na CNPJ.ws.');
                return response.json();
            })
            .then(data => {
                const est = data.estabelecimento || {};
                const inscricaoEstadual = est.inscricoes_estaduais?.[0]?.inscricao_estadual || '';

                if (!inscricaoEstadual) throw new Error('CNPJ.ws não retornou inscrição estadual.');

                feedback.text('Dados carregados com sucesso (via CNPJ.ws)!').addClass('text-success');
                $('#razao-social').val(toUpper(data.razao_social || ''));
                $('#nome-fantasia').val(toUpper(est.nome_fantasia || ''));
                $('#cep-endereco').val(toUpper((est.cep || '').replace(/\D/g, '')));
                $('#logradouro-endereco').val(toUpper(est.logradouro || ''));
                $('#numero-endereco').val(toUpper(est.numero || ''));
                $('#complemento-endereco').val(toUpper(est.complemento || ''));
                $('#bairro-endereco').val(toUpper(est.bairro || ''));
                $('#cidade-endereco').val(toUpper(est.cidade?.nome || ''));
                $('#uf-endereco').val(toUpper(est.estado?.sigla || ''));
                $('#inscricao-estadual').val(toUpper(inscricaoEstadual));

                $('#cep-endereco').mask('00000-000').trigger('input');
                if ($('#cpf-cnpj').val() === cnpj) {
                    $('#cpf-cnpj').mask('00.000.000/0000-00').trigger('input');
                }
                $('#cep-endereco').trigger('input');
            })
            .catch(error => {
                // 2 - Fallback para CNPJá
                feedback.text('Tentando CNPJá...');
                fetch(`https://open.cnpja.com/office/${cnpj}`)
                    .then(response => {
                        if (!response.ok) throw new Error('CNPJ não encontrado na CNPJá.');
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'ERROR' || !data.taxId) {
                            throw new Error(data.message || 'CNPJ não encontrado.');
                        }

                        feedback.text('Dados carregados com sucesso (via CNPJá)!').addClass('text-success');
                        $('#razao-social').val(toUpper(data.company.name));
                        $('#nome-fantasia').val(toUpper(data.alias));
                        $('#cep-endereco').val(toUpper((data.address.zip || '').replace(/\D/g, '')));
                        $('#logradouro-endereco').val(toUpper(data.address.street));
                        $('#numero-endereco').val(toUpper(data.address.number));
                        $('#complemento-endereco').val(toUpper(data.address.details));
                        $('#bairro-endereco').val(toUpper(data.address.district));
                        $('#cidade-endereco').val(toUpper(data.address.city));
                        $('#uf-endereco').val(toUpper(data.address.state));
                        $('#inscricao-estadual').val(toUpper(data.registrations[0]?.number || ''));

                        $('#cep-endereco').mask('00000-000').trigger('input');
                        if ($('#cpf-cnpj').val() === cnpj) {
                            $('#cpf-cnpj').mask('00.000.000/0000-00').trigger('input');
                        }
                        $('#cep-endereco').trigger('input');
                    })
                    .catch(error => {
                        // 3 - Fallback para BrasilAPI
                        feedback.text('Tentando BrasilAPI...');
                        fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`)
                            .then(response => {
                                if (!response.ok) throw new Error('CNPJ não encontrado ou inválido.');
                                return response.json();
                            })
                            .then(data => {
                                feedback.text('Dados carregados (sem IE, via BrasilAPI).').addClass('text-success');
                                $('#razao-social').val(toUpper(data.razao_social || ''));
                                $('#nome-fantasia').val(toUpper(data.nome_fantasia || ''));
                                $('#cep-endereco').val(toUpper((data.cep || '').replace(/\D/g, '')));
                                $('#logradouro-endereco').val(toUpper(data.logradouro || ''));
                                $('#numero-endereco').val(toUpper(data.numero || ''));
                                $('#complemento-endereco').val(toUpper(data.complemento || ''));
                                $('#bairro-endereco').val(toUpper(data.bairro || ''));
                                $('#cidade-endereco').val(toUpper(data.municipio || ''));
                                $('#uf-endereco').val(toUpper(data.uf || ''));
                                $('#inscricao-estadual').val(''); // BrasilAPI não tem IE

                                $('#cep-endereco').mask('00000-000').trigger('input');
                                if ($('#cpf-cnpj').val() === cnpj) {
                                    $('#cpf-cnpj').mask('00.000.000/0000-00').trigger('input');
                                }
                                $('#cep-endereco').trigger('input');
                            })
                            .catch(error => {
                                feedback.text(error.message).addClass('text-danger');
                            });
                    });
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
            "responsive": true,
            "columns": [
                {
                    "data": "end_tipo_endereco",
                    "className": "text-center align-middle"
                },
                {
                    "data": "end_logradouro",
                    "className": "align-middle"
                },
                {
                    "data": null,
                    "className": "text-center align-middle",
                    "render": (data, type, row) => `${row.end_cidade || ''}/${row.end_uf || ''}`
                },
                {
                    "data": "end_codigo",
                    "orderable": false,
                    "className": "col-centralizavel align-middle",
                    "render": (data) => {
                        let btnEditar = `
                                <button class="btn btn-warning btn-sm btn-editar-endereco me-1 d-inline-flex align-items-center" 
                                data-id="${data}"><i class="fas fa-pencil-alt me-1"></i>Editar</button>`;

                        let btnExcluir = `
                                <button class="btn btn-danger btn-sm btn-excluir-endereco d-inline-flex align-items-center" 
                                data-id="${data}"><i class="fas fa-trash-alt me-1"></i>Excluir</button>`;

                        return `<div class="btn-group">${btnEditar}${btnExcluir}</div>`;
                    }
                }
            ],
            paging: false,
            searching: false,
            info: false,
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

    /* function resetModal() {
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
     }*/

    function resetModal() {
        $formEntidade[0].reset();
        $formEndereco[0].reset();
        $('#ent-codigo, #end-codigo, #end-entidade-id').val('');
        $('#mensagem-entidade, #mensagem-endereco, #cnpj-feedback, #cep-feedback-adicional').empty().removeClass();

        // Reseta tipos e rádio
        $formEntidade.find(`input[name="ent_tipo_pessoa"][value="F"]`).prop('checked', true);
        $formEntidade.find(`input[name="ent_tipo_entidade"][value="${pageType === 'cliente' ? 'Cliente' : 'Fornecedor'}"]`).prop('checked', true);
        $('#situacao-entidade').prop('checked', true);

        // Título e abas
        $('#modal-adicionar-entidade-label').text(`Adicionar ${pageType === 'cliente' ? 'Cliente' : 'Fornecedor'}`);
        $('#enderecos-tab').addClass('disabled');

        $('#dados-tab').tab('show'); // Garante que volta para a primeira aba

        if (tableEnderecos) tableEnderecos.clear().draw();
        setPrincipalAddressFieldsReadonly(false);

        // Reaplicar as máscaras para garantir que os campos resetados funcionem
        $('.cep').mask('00000-000');
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

    /**
 * Aplica a máscara de CEP a uma string de números, usando o plugin jQuery Mask.
 * @param {string} cep O CEP sem formatação.
 * @returns {string} O CEP formatado.
 */
    function formatarCep(cep) {
        if (!cep) return '';
        const numeros = cep.replace(/\D/g, '');
        if (numeros.length !== 8) return numeros;
        return numeros.replace(/(\d{5})(\d{3})/, '$1-$2');
    }

    $('#cep-endereco, #cep-endereco-adicional').on('input', function () {
        let valor = $(this).val().replace(/\D/g, '');
        if (valor.length > 8) valor = valor.slice(0, 8);
        if (valor.length > 5) {
            valor = valor.replace(/(\d{5})(\d{1,3})/, '$1-$2');
        }
        $(this).val(valor);
    });

    $modalEntidade.on('hidden.bs.modal', function () {
        resetModal();
    });

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
                d.tipo_entidade = pageType; // Envia 'cliente' ou 'fornecedor' ou 'fazenda' ou transportadora'
                d.filtro_tipo_entidade = $('#filtro-tipo-entidade').val(); // Envia o valor do novo filtro
                d.csrf_token = csrfToken;
            }
        },
        "responsive": true,
        "columns": [
            {
                "data": "ent_situacao",
                "className": "text-center align-middle font-small",
                "width": "3%",
                "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'
            },
            {
                "data": "ent_tipo_entidade",
                "className": "text-center align-middle font-small",
                "width": "7%",
                "render": function (data, type, row) {
                    // Só altera a visualização se for para exibição (display) ou filtro
                    if (type === 'display' || type === 'filter') {
                        return MAPA_LABELS_TABELA[data] || data;
                    }
                    return data;
                }
            },
            {
                "data": "ent_codigo_interno",
                "className": "text-center align-middle font-small",
                "width": "3%"
            },
            {
                "data": "ent_razao_social",
                "className": "align-middle font-small",
                "width": "20%"
            },
            {
                "data": "ent_nome_fantasia",
                "className": "align-middle font-small",
                "width": "15%"
            },
            {
                "data": null,
                "className": "col-centralizavel align-middle font-small",
                "width": "8%",
                "render": function (data, type, row) {
                    if (row.ent_tipo_pessoa === 'F') {
                        return formatarCPF(row.ent_cpf);
                        $tipoPessoaRadios;
                    } else {
                        return formatarCNPJ(row.ent_cnpj);
                    }
                }
            },
            {
                "data": "end_logradouro",
                "className": "align-middle font-small",
                "width": "10%",
                "render": (data, type, row) => data ? `${row.end_logradouro || ''}, ${row.end_numero || ''}` : 'N/A'
            },
            {
                "data": "ent_codigo",
                "orderable": false,
                "className": "col-centralizavel align-middle ",
                "width": "8%",
                "render": (data, type, row) => {
                    let btnEditar = `
                                <a href="#" 
                                class="btn btn-warning btn-sm btn-editar-entidade me-1 d-inline-flex align-items-center" 
                                data-id="${data}"><i class="fas fa-pencil-alt me-1"></i>Editar</a>`;

                    let btnInativar = `
                                <a href="#" 
                                class="btn btn-danger btn-sm btn-inativar-entidade me-1 d-inline-flex align-items-center" 
                                data-id="${data}" data-nome="${row.ent_razao_social}"><i class="fa fa-ban me-1"></i>Inativar</a>`;
                    let btnRelatorio = `
                                <a href="index.php?page=relatorio_entidade&id=${row.ent_codigo}" 
                                target="_blank" 
                                class="btn btn-sm btn-info d-inline-flex align-items-center"  
                                title="Imprimir Ficha"><i class="fas fa-print me-1"></i>Imprimir</a>`;

                    return `<div class="d-flex justify-content-center">${btnEditar}${btnInativar}${btnRelatorio}</div>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // =================================================================
    // EVENTOS (AÇÕES DO USUÁRIO)
    // =================================================================

    // Abrir modal para Adicionar
    /* $('#btn-adicionar-entidade').on('click', function () {
         // Reseta o formulário
         $formEntidade[0].reset();
       
         // Força o radio de Pessoa Física como selecionado
         $('#tipo-pessoa-fisica').prop('checked', true);
 
         // Atualiza os campos conforme tipo F (esconde IE, botão buscar CNPJ etc.)
         updatePessoaFields('F', true);
 
         $modalEntidade.modal('show');
     }); */

    // Abrir modal para Adicionar
    $('#btn-adicionar-entidade').on('click', function () {
        // 1. Reseta o formulário e o ID oculto (CRÍTICO para não editar o anterior)
        $formEntidade[0].reset();
        $('#ent-codigo').val('');
        $('#mensagem-entidade').empty().removeClass(); // Limpa mensagens de erro/sucesso anteriores

        // 2. Define o Título Corretamente baseado no pageType
        const singular = MAPA_NOMES_SINGULAR[pageType] || 'Entidade';
        $('#modal-adicionar-entidade-label').text('Adicionar ' + singular);

        // 3. Configurações Visuais (Abas e Tabelas)
        $('#enderecos-tab').addClass('disabled'); // Desabilita aba de endereços ao criar novo
        $('#dados-tab').tab('show'); // Força a volta para a aba principal
        if (tableEnderecos) tableEnderecos.clear().draw(); // Limpa tabela de endereços visualmente
        setPrincipalAddressFieldsReadonly(false); // Libera campos de endereço principal

        // 4. Seleciona os Radios Corretos
        // Seleciona o tipo de entidade (Cliente/Fornecedor/Fazenda/Transportadora)
        $formEntidade.find(`input[name="ent_tipo_entidade"][value="${singular}"]`).prop('checked', true);

        // Força Pessoa Física como padrão e atualiza UI
        $('#tipo-pessoa-fisica').prop('checked', true);
        $('#situacao-entidade').prop('checked', true); // Padrão Ativo
        updatePessoaFields('F', true);

        // 5. Finalmente, abre o modal
        $modalEntidade.modal('show');
    });

    // Dispara a lógica de UI quando o tipo de pessoa muda
    $tipoPessoaRadios.on('change', function () {
        const valorSelecionado = $('input[name="ent_tipo_pessoa"]:checked').val();
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
    /* $modalEntidade.on('show.bs.modal', function (event) {
         if ($(event.relatedTarget).is('#btn-adicionar-entidade')) {
             let singular = 'Entidade';
             switch (pageType) {
                 case 'cliente':
                     singular = 'Cliente';
                     break;
                 case 'fornecedor':
                     singular = 'Fornecedor';
                     break;
                 case 'transportadora':
                     singular = 'Transportadora';
                     break;
             }
 
             $formEntidade[0].reset();
             $('#ent-codigo').val('');
             $('#modal-adicionar-entidade-label').text('Adicionar ' + singular);
 
             // Seleciona o radio button correto (o HTML agora também tem o valor 'Transportadora')
             $formEntidade.find(`input[name="ent_tipo_entidade"][value="${singular}"]`).prop('checked', true);
 
             // Define Pessoa Física como padrão
             $formEntidade.find(`input[name="ent_tipo_pessoa"][value="F"]`).prop('checked', true);
             $('#enderecos-tab').addClass('disabled');
 
             updatePessoaFields('F', true); // Força a UI de Pessoa Física e limpa o campo
         }
     });*/

    // Garante que a máscara seja aplicada após o modal ser exibido
    $modalEntidade.on('shown.bs.modal', function (event) {
        if ($(event.relatedTarget).is('#btn-adicionar-entidade')) {
            updatePessoaFields(true); // Aplica a máscara de CPF por padrão
            $('#cep-endereco-adicional').mask('00000-000');
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
                notificacaoSucesso('Sucesso!', response.message);
            } else {
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar o endereço.');
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
                    $('#cep-endereco').val(formatarCep(data.end_cep));
                    //$('#cep-endereco').val(data.end_cep);
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
                    notificacaoErro('Erro ao Carregar', response.message);
                }
            }).fail(function () {
                notificacaoErro('Erro de Comunicação', 'Não foi possível carregar os dados da entidade.');
            });
    });

    $('#tabela-entidades').on('click', '.btn-inativar-entidade', function () {
        const id = $(this).data('id');
        const nome = $(this).data('nome');

        // 1. Definição do Título Dinâmico
        const titulos = {
            'cliente': 'Inativar Cliente?',
            'fornecedor': 'Inativar Fornecedor?',
            'transportadora': 'Inativar Transportadora?',
            'fazenda': 'Inativar Fazenda?'
        };

        const tituloConfirmacao = titulos[pageType] || 'Inativar Entidade?';

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
                $('#cep-endereco-adicional').val(data.end_cep).trigger('input');
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
                        notificacaoSucesso('Excluído!', response.message);
                    } else {
                        notificacaoErro('Erro ao Excluir', response.message);
                    }
                }).fail(function () {
                    notificacaoErro('Erro de Comunicação', 'Não foi possível excluir o endereço.');
                });
            }
        });
    });
});
