$(document).ready(function () {

    // 1. LÊ O TOKEN DA PÁGINA
    // Pega o token da meta tag que você adicionou no HTML
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Um log para você ver no console que ele foi lido corretamente
    //console.log('Token CSRF lido pelo JavaScript:', csrfToken);

    // 2. CONFIGURA O "CARTEIRO" (AJAX SETUP)
    // Configura TODAS as futuras requisições AJAX para incluir o token no cabeçalho
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    });




    // --- Seletores e Variáveis Globais para a Tela de Clientes ---
    var $modalCliente = $('#modal-adicionar-cliente'); // ID do modal
    var $formCliente = $('#form-cliente');
    var $mensagemCliente = $('#mensagem-cliente'); // Mensagens dentro do modal (aba Cliente)
    var $feedbackMessageAreaCliente = $('#feedback-message-area-cliente'); // Mensagens na página principal
    var $btnAdicionarClienteMain = $('#btn-adicionar-cliente-main');

    // Campos do formulário de Entidade
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
    var $entCodigo = $('#ent-codigo'); // Campo oculto para o ID do cliente (usado na edição)

    // Seletores para o filtro de situação (radio buttons)
    var $filtroSituacaoRadios = $('input[name="filtro_situacao"]');

    // --- Seletores e Variáveis Globais para a Aba de Endereços ---
    var $formEndereco = $('#form-endereco');
    var $mensagemEndereco = $('#mensagem-endereco'); // Mensagens dentro do modal (aba Endereço)
    var $endCodigo = $('#end-codigo'); // Campo oculto para o ID do endereço (edição)
    var $endEntidadeId = $('#end-entidade-id'); // Campo oculto para o ID da entidade associada ao endereço
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

    // Tabela de endereços dentro do modal (agora na aba "Dados do Cliente")
    var tableEnderecos; // Será inicializada dinamicamente

    // --- Funções Auxiliares ---

    // Função para exibir mensagens de feedback na área principal da página
    function showFeedbackMessageCliente(message, type = 'success') {
        $feedbackMessageAreaCliente.empty().removeClass('alert alert-success alert-danger');
        var alertClass = (type === 'success') ? 'alert-success' : 'alert-danger';
        $feedbackMessageAreaCliente.addClass('alert ' + alertClass).text(message);
        setTimeout(function () {
            $feedbackMessageAreaCliente.fadeOut('slow', function () {
                $(this).empty().removeClass('alert alert-success alert-danger').show();
            });
        }, 5000); // Mensagem some após 5 segundos
    }

    // Função para aplicar a máscara correta ao campo CPF/CNPJ
    function applyCpfCnpjMask() {
        if ($tipoPessoaFisica.is(':checked')) {
            $labelCpfCnpj.text('CPF');
            $cpfCnpj.attr('placeholder', '000.000.000-00').mask('000.000.000-00', { reverse: true });
        } else {
            $labelCpfCnpj.text('CNPJ');
            $cpfCnpj.attr('placeholder', '00.000.000/0000-00').mask('00.000.000/0000-00', { reverse: true });
        }
    }

    // Função para limpar os campos de endereço do formulário de endereço
    function clearAddressFormFields() {
        $endCodigo.val('');
        $tipoEndereco.val('');
        $cepEndereco.val('');
        $logradouroEndereco.val('');
        $numeroEndereco.val('');
        $complementoEndereco.val('');
        $bairroEndereco.val('');
        $cidadeEndereco.val('');
        $ufEndereco.val('');
        $cepFeedbackEndereco.empty();
        $mensagemEndereco.empty().removeClass('alert alert-success alert-danger');
        $btnSalvarEndereco.text('Salvar Endereço');
    }

    // Função para buscar CEP via ViaCEP (para formulário de endereço)
    function searchCepEndereco() {
        var cepValue = $cepEndereco.val().replace(/\D/g, ''); // Remove não-dígitos
        if (cepValue.length !== 8) {
            $cepFeedbackEndereco.text('CEP inválido.').removeClass('text-success').addClass('text-danger');
            return;
        }

        $cepFeedbackEndereco.text('Buscando CEP...').removeClass('text-danger').addClass('text-muted');
        $logradouroEndereco.val('');
        $numeroEndereco.val('');
        $complementoEndereco.val('');
        $bairroEndereco.val('');
        $cidadeEndereco.val('');
        $ufEndereco.val('');

        $.ajax({
            url: 'https://viacep.com.br/ws/' + cepValue + '/json/',
            method: 'GET',
            dataType: 'json',
            success: function (data) {
                if (data.erro) {
                    $cepFeedbackEndereco.text('CEP não encontrado.').removeClass('text-success').addClass('text-danger');
                } else {
                    $logradouroEndereco.val(data.logradouro);
                    $bairroEndereco.val(data.bairro);
                    $cidadeEndereco.val(data.localidade);
                    $ufEndereco.val(data.uf);
                    $cepFeedbackEndereco.text('CEP encontrado!').removeClass('text-danger').addClass('text-success');
                    $numeroEndereco.focus();
                }
            },
            error: function () {
                $cepFeedbackEndereco.text('Erro ao buscar CEP. Tente novamente.').removeClass('text-success').addClass('text-danger');
            }
        });
    }

    // Função para carregar a tabela de endereços de uma entidade específica
    function loadEnderecosTable(entidadeId) {
        //console.log("DEBUG: loadEnderecosTable chamado para entidadeId:", entidadeId);
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-cliente')) {
            tableEnderecos.destroy(); // Destrói a instância existente se houver
            //console.log("DEBUG: Instância anterior de tabelaEnderecos destruída.");
        }

        tableEnderecos = $('#tabela-enderecos-cliente').DataTable({
            "ajax": {
                "url": "process/listar_enderecos.php", // Novo endpoint para listar endereços
                "type": "POST",
                "data": function (d) {
                    d.ent_codigo = entidadeId; // Envia o ID da entidade

                    // LINHA FALTANTE QUE VOCÊ PRECISA ADICIONAR:
                    d.csrf_token = $('meta[name="csrf-token"]').attr('content');

                    //console.log("DEBUG: Enviando ent_codigo para listar_enderecos.php:", entidadeId);
                },
                "dataSrc": function (json) {
                    //console.log("DEBUG: DataTables received data for addresses (listar_enderecos.php):", json.data); // LOG AQUI
                    if (json.error) {
                        console.error("Erro retornado pelo servidor:", json.error);
                        // Opcional: mostrar o erro para o usuário em algum lugar
                    }

                    return json.data;
                },
                "error": function (xhr, error, thrown) {
                    console.error("DEBUG: Erro AJAX ao carregar endereços para DataTables:", error, thrown, xhr.responseText);
                    $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text('Erro ao carregar endereços.');
                }
            },
            "columns": [
                { "data": "end_tipo_endereco" },
                { "data": "end_cep" },
                { "data": "end_logradouro" },
                { "data": "end_numero" },
                { "data": "end_bairro" },
                {
                    "data": null,
                    "render": function (data, type, row) {
                        return row.end_cidade + '/' + row.end_uf;
                    }
                },
                {
                    "data": "end_codigo",
                    "render": function (data, type, row) {
                        return '<a href="#" class="btn btn-warning btn-sm btn-editar-endereco me-1" data-id="' + row.end_codigo + '">Editar</a>' +
                            '<a href="#" class="btn btn-danger btn-sm btn-excluir-endereco" data-id="' + row.end_codigo + '">Excluir</a>';
                    }
                }
                // Não precisamos adicionar colunas para end_data_cadastro e end_usuario_cadastro_id aqui
                // a menos que queiramos exibi-las. Apenas garantir que o PHP as retorne é o suficiente
                // para evitar problemas de dessincronização de colunas.
            ],
            "paging": false, // Não paginar, exibir todos os endereços
            "searching": false, // Não permitir busca na tabela de endereços
            "info": false, // Não exibir informações de "Mostrando X de Y"
            "ordering": false, // Não permitir ordenação
            "language": {
                "url": "../vendor/DataTables/Portuguese-Brasil.json"
                //"url": "https://cdn.datatables.net/plug-ins/1.10.22/i18n/Portuguese-Brasil.json"
            }
        });
    }


    // --- Lógica de Eventos ---

    // Inicializa o DataTables principal para clientes
    var tableClientes = $('#example-clientes').DataTable({ // ID da tabela principal
        //"dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"t>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "ajax": {
            "url": "process/listar_entidades.php",
            "type": "POST",
            "data": function (d) {
                d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val();
                d.tipo_entidade = 'Cliente'; // Adiciona o tipo de entidade para filtrar clientes
            }
        },
        "responsive": true,
        "columns": [
            {
                "data": "ent_situacao",
                "render": function (data, type, row) {
                    // return (data === 'A') ? "Ativo" : "Inativo";
                    return (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>';
                }
            },
            {
                "data": "ent_tipo_entidade",
                "render": function (data, type, row) {
                    return data.charAt(0).toUpperCase() + data.slice(1);
                }
            },
            { "data": "ent_razao_social" },
            {
                "data": null,
                "render": function (data, type, row) {
                    return row.ent_tipo_pessoa === 'F' ? row.ent_cpf : row.ent_cnpj;
                }
            },
            {
                "data": "end_logradouro",
                "render": function (data, type, row) {
                    if (data) {
                        // Exibe o tipo de endereço principal na tabela de clientes
                        return (row.end_tipo_endereco ? row.end_tipo_endereco + ': ' : '') + data + ', ' + row.end_numero + ' - ' + row.end_bairro + ', ' + row.end_cidade + '/' + row.end_uf;
                    }
                    return 'N/A';
                }
            },
            {
                "data": "ent_codigo",
                "render": function (data, type, row) {
                    return '<a href="#" class="btn btn-warning btn-sm btn-editar-cliente me-1" data-id="' + row.ent_codigo + '">Editar</a>' +
                        '<a href="#" class="btn btn-danger btn-sm btn-excluir-cliente" data-id="' + row.ent_codigo + '" data-nome="' + row.ent_razao_social + '">Excluir</a>';
                }
            }
        ],
        "ordering": true,
        "language": {
            //"url": "https://cdn.datatables.net/plug-ins/1.10.22/i18n/Portuguese-Brasil.json"
            "url": "../vendor/DataTables/Portuguese-Brasil.json"
        }
    });

    // Evento para mudança de Tipo de Pessoa (Física/Jurídica)
    $('input[name="ent_tipo_pessoa"]').on('change', applyCpfCnpjMask);
    applyCpfCnpjMask(); // Aplica a máscara inicial

    // Evento para o switch de Situação (Ativo/Inativo)
    $situacaoCliente.on('change', function () {
        $textoSituacaoCliente.text(this.checked ? 'Ativo' : 'Inativo');
        $(this).val(this.checked ? 'A' : 'I');
    });

    // --- Lógica do Modal Adicionar/Editar Cliente (com Abas) ---

    // Variável para armazenar o botão que abriu o modal
    var $triggerButton = null;

    // Evento de abertura do modal (resetar formulário e definir título)
    $modalCliente.on('show.bs.modal', function (event) {
        $mensagemCliente.empty().removeClass('alert alert-success alert-danger');
        clearAddressFormFields(); // Limpa o formulário de endereço

        $triggerButton = $(event.relatedTarget); // Armazena o botão que acionou o modal

        // Se o modal foi acionado pelo botão "Adicionar Cliente" principal
        if ($triggerButton.is($btnAdicionarClienteMain)) {
            //console.log("Modal aberto por 'Adicionar Cliente'. Forçando reset completo.");
            $('#modal-adicionar-cliente-label').text('Adicionar Cliente');

            // Força a limpeza de todos os campos do formulário principal
            $razaoSocial.val('').attr('value', '');
            $cpfCnpj.val('').attr('value', '');
            $entCodigo.val(''); // Garante que o ID oculto esteja vazio

            // Força o desmarcar de todos os radios antes de definir o padrão
            $('input[name="ent_tipo_pessoa"]').prop('checked', false);
            $('input[name="ent_tipo_entidade"]').prop('checked', false);
            $situacaoCliente.prop('checked', false).attr('checked', null); // Desmarca o switch

            // Redefine os valores padrão para os elementos do formulário principal
            $situacaoCliente.prop('checked', true).val('A');
            $textoSituacaoCliente.text('Ativo');
            $tipoPessoaFisica.prop('checked', true).trigger('change'); // Garante que Pessoa Física esteja marcado e dispara o change para a máscara
            $tipoEntidadeCliente.prop('checked', true); // Garante que Cliente esteja marcado

            // Desabilita a aba de endereços para novos cadastros até o cliente ser salvo
            $('#enderecos-tab').addClass('disabled');
            $('#tabela-enderecos-cliente tbody').empty(); // Limpa a tabela de endereços
        }
        // A lógica para "Editar Cliente" está no evento de clique do botão ".btn-editar-cliente"
        // e não deve resetar o formulário aqui, pois os dados serão preenchidos.

        // Ativa a primeira aba ("Dados do Cliente") por padrão em ambos os casos
        $('#dados-cliente-tab').tab('show');
    });

    // Lógica para o botão "Editar" da tabela de clientes
    $('#example-clientes tbody').on('click', '.btn-editar-cliente', function (e) {
        e.preventDefault();
        var idEntidade = $(this).data('id');

        $('#modal-adicionar-cliente-label').text('Editar Cliente');
        $entCodigo.val(idEntidade); // Define o ID da entidade no campo oculto
        $endEntidadeId.val(idEntidade); // Define o ID da entidade no campo oculto do formulário de endereço

        // Habilita a aba de endereços para edição
        $('#enderecos-tab').removeClass('disabled');

        // Carrega os dados da entidade específica para preencher o formulário de edição
        $.ajax({
            url: 'process/get_entidade_data.php',
            type: 'GET',
            data: { id: idEntidade },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    var entidadeData = response.data;
                    //console.log("Dados da entidade recebidos para edição:", entidadeData);

                    // Razão Social
                    $razaoSocial.val(entidadeData.ent_razao_social);
                    $razaoSocial.attr('value', entidadeData.ent_razao_social);

                    // Tipo de Pessoa (Física/Jurídica)
                    $('input[name="ent_tipo_pessoa"]').prop('checked', false); // Desmarca todos
                    if (entidadeData.ent_tipo_pessoa === 'F') {
                        $tipoPessoaFisica.prop('checked', true);
                        $tipoPessoaFisica.attr('checked', 'checked');
                    } else {
                        $tipoPessoaJuridica.prop('checked', true);
                        $tipoPessoaJuridica.attr('checked', 'checked');
                    }
                    $('input[name="ent_tipo_pessoa"]:checked').trigger('change'); // Dispara o evento change

                    // CPF/CNPJ
                    var cpfCnpjValue = entidadeData.ent_tipo_pessoa === 'F' ? entidadeData.ent_cpf : entidadeData.ent_cnpj;
                    $cpfCnpj.val(cpfCnpjValue);
                    $cpfCnpj.attr('value', cpfCnpjValue);
                    applyCpfCnpjMask(); // Re-aplica a máscara após definir o valor

                    // Tipo de Entidade (Cliente, Fornecedor, Ambos)
                    $('input[name="ent_tipo_entidade"]').prop('checked', false); // Desmarca todos
                    if (entidadeData.ent_tipo_entidade === 'Cliente') {
                        $tipoEntidadeCliente.prop('checked', true);
                        $tipoEntidadeCliente.attr('checked', 'checked');
                    } else if (entidadeData.ent_tipo_entidade === 'Fornecedor') {
                        $tipoEntidadeFornecedor.prop('checked', true);
                        $tipoEntidadeFornecedor.attr('checked', 'checked');
                    } else if (entidadeData.ent_tipo_entidade === 'Cliente e Fornecedor') {
                        $tipoEntidadeAmbos.prop('checked', true);
                        $tipoEntidadeAmbos.attr('checked', 'checked');
                    }
                    $('input[name="ent_tipo_entidade"]:checked').trigger('change'); // Dispara o evento change

                    // Situação (Ativo/Inativo)
                    $situacaoCliente.val(entidadeData.ent_situacao);
                    $situacaoCliente.prop('checked', entidadeData.ent_situacao === 'A');
                    $situacaoCliente.attr('checked', entidadeData.ent_situacao === 'A' ? 'checked' : null);
                    $textoSituacaoCliente.text(entidadeData.ent_situacao === 'A' ? 'Ativo' : 'Inativo');
                    $situacaoCliente.trigger('change'); // Dispara o evento change para atualizar o texto visual

                    // Preenche os campos de endereço se houver um endereço principal
                    if (entidadeData.endereco) {
                        $tipoEndereco.val(entidadeData.endereco.end_tipo_endereco);
                        $cepEndereco.val(entidadeData.endereco.end_cep);
                        $logradouroEndereco.val(entidadeData.endereco.end_logradouro);
                        $numeroEndereco.val(entidadeData.endereco.end_numero);
                        $complementoEndereco.val(entidadeData.endereco.end_complemento);
                        $bairroEndereco.val(entidadeData.endereco.end_bairro);
                        $cidadeEndereco.val(entidadeData.endereco.end_cidade);
                        $ufEndereco.val(entidadeData.endereco.end_uf);
                    } else {
                        clearAddressFormFields(); // Garante que os campos de endereço estejam limpos se não houver endereço principal
                    }

                    // Carrega a tabela de endereços para esta entidade
                    loadEnderecosTable(idEntidade);

                    // Abre o modal de edição com um pequeno atraso
                    setTimeout(function () {
                        var clienteModal = new bootstrap.Modal(document.getElementById('modal-adicionar-cliente'));
                        clienteModal.show();
                    }, 100); // Pequeno atraso de 100ms
                } else {
                    showFeedbackMessageCliente(response.message || 'Erro ao carregar dados do cliente.', 'danger');
                }
            },
            error: function (xhr, status, error) {
                showFeedbackMessageCliente('Erro na requisição: ' + error, 'danger');
                console.error("Erro AJAX ao carregar dados do cliente: ", status, error, xhr.responseText);
            }
        });
    });

    // Lógica de envio do formulário principal do cliente (Aba "Dados do Cliente")
    $formCliente.on('submit', function (e) {
        e.preventDefault();

        var idEntidade = $entCodigo.val();
        var url = idEntidade ? 'process/editar_entidade.php' : 'process/cadastrar_entidade.php';

        var formData = new FormData(this);
        formData.append('csrf_token', $('input[name="csrf_token"]').val());
        //formData.append('csrf_token', CSRF_TOKEN);

        // Adiciona os campos do endereço principal ao formData, mesmo que não sejam visíveis na aba "Dados do Cliente"
        // Isso é importante para que o editar_entidade.php possa gerenciar o endereço principal.
        formData.append('end_tipo_endereco', $tipoEndereco.val());
        formData.append('end_cep', $cepEndereco.val());
        formData.append('end_logradouro', $logradouroEndereco.val());
        formData.append('end_numero', $numeroEndereco.val());
        formData.append('end_complemento', $complementoEndereco.val());
        formData.append('end_bairro', $bairroEndereco.val());
        formData.append('end_cidade', $cidadeEndereco.val());
        formData.append('end_uf', $ufEndereco.val());


        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    // Se for um novo cadastro, define o ID da entidade para permitir adicionar endereços
                    if (!idEntidade) {
                        $entCodigo.val(response.ent_codigo); // Assume que o backend retorna o ID do novo cliente
                        $endEntidadeId.val(response.ent_codigo);
                        // A aba de endereços não é mais desabilitada, mas a tabela já está visível
                        loadEnderecosTable(response.ent_codigo); // Carrega a tabela de endereços para o novo cliente
                        // Habilita a aba de endereços após o primeiro salvamento do cliente
                        $('#enderecos-tab').removeClass('disabled');
                    }
                    // NOVO: Recarrega a tabela principal com um pequeno atraso para garantir que o BD esteja atualizado
                    setTimeout(function () {
                        tableClientes.ajax.reload(null, false); // false para manter a paginação atual
                    }, 200);

                    $mensagemCliente.empty().removeClass().addClass('alert alert-success').text(response.message);
                    // Não esconde o modal imediatamente para permitir ir para a aba de endereços
                } else {
                    $mensagemCliente.empty().removeClass().addClass('alert alert-danger').text(response.message);
                }
            },
            error: function (xhr, status, error) {
                $mensagemCliente.empty().removeClass().addClass('alert alert-danger').text('Erro na requisição: ' + error);
                console.error("Erro AJAX ao salvar cliente: ", status, error, xhr.responseText);
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

        var entidadeId = $endEntidadeId.val();
        if (!entidadeId) {
            $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text('Primeiro, salve os dados do cliente na aba "Dados do Cliente".');
            return;
        }

        var idEndereco = $endCodigo.val();
        // CORREÇÃO AQUI: Caminhos para os scripts de cadastro/edição de endereço
        var url = idEndereco ? 'process/editar_endereco.php' : 'process/cadastrar_endereco.php';

        console.log("DEBUG: Salvando/Editando Endereço:"); // LOG AQUI
        console.log("  URL:", url); // LOG AQUI
        console.log("  ID Endereço (end_codigo):", idEndereco); // LOG AQUI
        console.log("  ID Entidade (end_entidade_id):", entidadeId); // LOG AQUI

        var formData = new FormData(this);
        formData.append('end_entidade_id', entidadeId); // Garante que o ID da entidade está sendo enviado
        formData.append('csrf_token', $('input[name="csrf_token"]').val());
        //formData.append('csrf_token', CSRF_TOKEN);

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (response) {
                console.log("DEBUG: Resposta do servidor para salvar/editar endereço:", response); // LOG AQUI
                if (response.success) {
                    clearAddressFormFields(); // Limpa o formulário de endereço
                    tableEnderecos.ajax.reload(); // Recarrega a tabela de endereços
                    $mensagemEndereco.empty().removeClass().addClass('alert alert-success').text(response.message);
                    // NOVO: Recarrega a tabela principal com um pequeno atraso para garantir que o BD esteja atualizado
                    setTimeout(function () {
                        tableClientes.ajax.reload(null, false); // false para manter a paginação atual
                    }, 200);
                } else {
                    $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text(response.message);
                }
            },
            error: function (xhr, status, error) {
                $mensagemEndereco.empty().removeClass().addClass('alert alert-danger').text('Erro na requisição: ' + error);
                console.error("Erro AJAX ao salvar endereço: ", status, error, xhr.responseText); // LOG AQUI
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
        $('#id-endereco-excluir').val(idEndereco); // Define o ID do endereço a ser excluído

        var confirmModalEndereco = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao-endereco'));
        confirmModalEndereco.show();
    });

    // Lógica para o botão "Sim, Excluir" dentro do modal de confirmação de exclusão de endereço
    $('#btn-confirmar-exclusao-endereco').on('click', function () {
        var idEndereco = $('#id-endereco-excluir').val();
        var csrfToken = $('input[name="csrf_token"]').val();
        //var csrfToken = CSRF_TOKEN;
        console.log("DEBUG: Confirmando exclusão de endereço. ID:", idEndereco); // LOG AQUI

        $.ajax({
            type: 'POST',
            url: 'process/excluir_endereco.php', // Novo endpoint para excluir endereço
            data: { end_codigo: idEndereco, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                console.log("DEBUG: Resposta do servidor para exclusão de endereço:", response); // LOG AQUI
                var confirmModalEndereco = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-endereco'));
                confirmModalEndereco.hide();

                if (response.success) {
                    tableEnderecos.ajax.reload(); // Recarrega a tabela de endereços
                    showFeedbackMessageCliente(response.message, 'success'); // Mensagem na página principal
                    // NOVO: Recarrega a tabela principal com um pequeno atraso para garantir que o BD esteja atualizado
                    setTimeout(function () {
                        tableClientes.ajax.reload(null, false); // false para manter a paginação atual
                    }, 200);
                } else {
                    showFeedbackMessageCliente('Erro ao excluir endereço: ' + response.message, 'danger');
                }
            },
            error: function (xhr, status, error) {
                var confirmModalEndereco = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-endereco'));
                confirmModalEndereco.hide();
                showFeedbackMessageCliente('Erro na requisição de exclusão de endereço: ' + error, 'danger');
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
