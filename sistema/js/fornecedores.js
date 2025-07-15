// js/fornecedores.js

console.log("fornecedores.js carregado."); // DEBUG: Verifica se o arquivo é carregado

$(document).ready(function () {
    console.log("jQuery ready no fornecedores.js. Tentando inicializar DataTables."); // DEBUG: Verifica se o DOM está pronto

    // --- Seletores e Variáveis Globais para a Tela de Fornecedores ---
    var $modalFornecedor = $('#modal-adicionar-fornecedor'); // ID do modal principal de fornecedor
    var $formFornecedor = $('#form-fornecedor');
    var $mensagemFornecedor = $('#mensagem-fornecedor'); // Área de feedback dentro da aba "Dados do Fornecedor"
    var $feedbackMessageAreaFornecedor = $('#feedback-message-area-fornecedor'); // Área de feedback na página principal

    // Campos do formulário de Entidade (Fornecedor) na aba "Dados do Fornecedor"
    var $entCodigoForn = $('#ent-codigo-fornecedor'); // Campo oculto para o ID do fornecedor (usado na edição)
    var $razaoSocialForn = $('#razao-social-fornecedor');
    var $tipoPessoaFornFisica = $('#tipo-pessoa-fisica-fornecedor');
    var $tipoPessoaFornJuridica = $('#tipo-pessoa-juridica-fornecedor');
    var $labelCpfCnpjForn = $('#label-cpf-cnpj-fornecedor');
    var $cpfCnpjForn = $('#cpf-cnpj-fornecedor');
    var $tipoEntidadeClienteForn = $('#tipo-entidade-cliente-fornecedor');
    var $tipoEntidadeFornecedorForn = $('#tipo-entidade-fornecedor-fornecedor');
    var $tipoEntidadeAmbosForn = $('#tipo-entidade-ambos-fornecedor');
    var $situacaoFornecedor = $('#situacao-fornecedor'); // Switch Ativo/Inativo
    var $textoSituacaoFornecedor = $('#texto-situacao-fornecedor'); // Texto ao lado do switch

    // Seletores para o filtro de situação (radio buttons) na tela principal
    var $filtroSituacaoRadiosForn = $('input[name="filtro_situacao"]');

    // --- Seletores e Variáveis Globais para a Aba de Endereços do Fornecedor ---
    var $formEnderecoForn = $('#form-endereco-fornecedor');
    var $mensagemEnderecoForn = $('#mensagem-endereco-fornecedor'); // Área de feedback dentro da aba "Endereços"
    var $endCodigoForn = $('#end-codigo-fornecedor'); // Campo oculto para o ID do endereço (edição)
    var $endEntidadeIdForn = $('#end-entidade-id-fornecedor'); // Campo oculto para o ID da entidade associada ao endereço
    var $tipoEnderecoForn = $('#tipo-endereco-fornecedor');
    var $cepEnderecoForn = $('#cep-endereco-fornecedor');
    var $btnBuscarCepEnderecoForn = $('#btn-buscar-cep-fornecedor');
    var $cepFeedbackEnderecoForn = $('#cep-feedback-fornecedor');
    var $logradouroEnderecoForn = $('#logradouro-endereco-fornecedor');
    var $numeroEnderecoForn = $('#numero-endereco-fornecedor');
    var $complementoEnderecoForn = $('#complemento-endereco-fornecedor');
    var $bairroEnderecoForn = $('#bairro-endereco-fornecedor');
    var $cidadeEnderecoForn = $('#cidade-endereco-fornecedor');
    var $ufEnderecoForn = $('#uf-endereco-fornecedor');
    var $btnSalvarEnderecoForn = $('#btn-salvar-endereco-fornecedor');
    var $btnCancelarEdicaoEnderecoForn = $('#btn-cancelar-edicao-endereco-fornecedor');

    // Instâncias do DataTables
    var tableFornecedores; // Tabela principal de fornecedores
    var tableEnderecosForn; // Tabela de endereços dentro do modal

    // --- Funções Auxiliares ---

    // Função para exibir mensagens de feedback na área principal da página ou dentro do modal
    function showFeedbackMessage(divElement, message, type = 'success') {
        divElement.empty().removeClass('alert alert-success alert-danger alert-info alert-warning').show(); // Garante que esteja visível
        var alertClass = '';
        if (type === 'success') alertClass = 'alert-success';
        else if (type === 'danger') alertClass = 'alert-danger';
        else if (type === 'info') alertClass = 'alert-info';
        else if (type === 'warning') alertClass = 'alert-warning';

        divElement.addClass('alert ' + alertClass).text(message);
        setTimeout(function () {
            divElement.fadeOut('slow', function () {
                $(this).empty().removeClass('alert alert-success alert-danger alert-info alert-warning');
            });
        }, 5000); // Mensagem some após 5 segundos
    }

    // Função para aplicar a máscara correta ao campo CPF/CNPJ para fornecedores
    function applyCpfCnpjMaskForn() {
        if ($tipoPessoaFornFisica.is(':checked')) {
            $labelCpfCnpjForn.text('CPF');
            $cpfCnpjForn.attr('placeholder', '000.000.000-00').mask('000.000.000-00', { reverse: true });
        } else {
            $labelCpfCnpjForn.text('CNPJ');
            $cpfCnpjForn.attr('placeholder', '00.000.000/0000-00').mask('00.000.000/0000-00', { reverse: true });
        }
    }

    // Função para limpar os campos de endereço do formulário de endereço do fornecedor
    function clearAddressFormFieldsForn() {
        $formEnderecoForn[0].reset(); // Reseta o formulário DOM
        $endCodigoForn.val(''); // Limpa o ID oculto
        $cepFeedbackEnderecoForn.empty().removeClass('text-success text-danger text-muted');
        $mensagemEnderecoForn.empty().removeClass('alert alert-success alert-danger alert-warning');
        $btnSalvarEnderecoForn.text('Salvar Endereço').removeClass('btn-warning').addClass('btn-primary');
        $btnCancelarEdicaoEnderecoForn.hide();
    }

    // Função para buscar CEP via ViaCEP (para formulário de endereço do fornecedor)
    function searchCepEnderecoForn() {
        var cepValue = $cepEnderecoForn.val().replace(/\D/g, ''); // Remove não-dígitos
        if (cepValue.length !== 8) {
            showFeedbackMessage($cepFeedbackEnderecoForn, 'CEP inválido. Digite 8 dígitos.', 'danger');
            return;
        }

        showFeedbackMessage($cepFeedbackEnderecoForn, 'Buscando CEP...', 'info');
        // Limpa os campos antes de buscar para evitar dados antigos
        $logradouroEnderecoForn.val('');
        $numeroEnderecoForn.val('');
        $complementoEnderecoForn.val('');
        $bairroEnderecoForn.val('');
        $cidadeEnderecoForn.val('');
        $ufEnderecoForn.val('');

        $.ajax({
            url: 'https://viacep.com.br/ws/' + cepValue + '/json/',
            method: 'GET',
            dataType: 'json',
            success: function (data) {
                if (data.erro) {
                    showFeedbackMessage($cepFeedbackEnderecoForn, 'CEP não encontrado.', 'danger');
                } else {
                    $logradouroEnderecoForn.val(data.logradouro);
                    $bairroEnderecoForn.val(data.bairro);
                    $cidadeEnderecoForn.val(data.localidade);
                    $ufEnderecoForn.val(data.uf);
                    showFeedbackMessage($cepFeedbackEnderecoForn, 'CEP encontrado!', 'success');
                    $numeroEnderecoForn.focus(); // Foca no campo número
                }
            },
            error: function () {
                showFeedbackMessage($cepFeedbackEnderecoForn, 'Erro ao buscar CEP. Tente novamente.', 'danger');
            }
        });
    }

    // Função para carregar a tabela de endereços de uma entidade específica (Fornecedor) no modal
    function loadEnderecosTableForn(entidadeId) {
        console.log("DEBUG: loadEnderecosTableForn chamado para entidadeId:", entidadeId);
        // Destrói a instância existente do DataTables se houver, para recriar com novos dados
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-fornecedor')) {
            tableEnderecosForn.destroy();
            console.log("DEBUG: Instância anterior de tabelaEnderecosForn destruída.");
        }

        tableEnderecosForn = $('#tabela-enderecos-fornecedor').DataTable({
            "ajax": {
                "url": "process/listar_enderecos.php", // Endpoint para listar endereços
                "type": "POST",
                "data": function (d) {
                    d.ent_codigo = entidadeId; // Envia o ID da entidade
                    d.csrf_token = $('input[name="csrf_token"]').val(); // Envia o token CSRF
                    console.log("DEBUG: Enviando ent_codigo para listar_enderecos.php (fornecedor):", entidadeId);
                },
                "dataSrc": function (json) {
                    console.log("DEBUG: DataTables received data for addresses (listar_enderecos.php - fornecedor):", json.data);
                    return json.data;
                },
                "error": function (xhr, error, thrown) {
                    console.error("DEBUG: Erro AJAX ao carregar endereços para DataTables (fornecedor):", error, thrown, xhr.responseText);
                    showFeedbackMessage($mensagemEnderecoForn, 'Erro ao carregar endereços.', 'danger');
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
                        return '<button type="button" class="btn btn-warning btn-sm btn-editar-endereco me-1" data-id="' + row.end_codigo + '">Editar</button>' +
                            '<button type="button" class="btn btn-danger btn-sm btn-excluir-endereco" data-id="' + row.end_codigo + '">Excluir</button>';
                    }
                }
            ],
            "paging": false,    // Não paginar
            "searching": false, // Não mostrar barra de busca
            "info": false,      // Não mostrar informações de "Mostrando X de Y"
            "ordering": false,  // Não ordenar
            // "language": {
            //     "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json"
            // }
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.22/i18n/Portuguese-Brasil.json"
            }
        });
    }

    // --- Lógica de Inicialização Principal e Eventos ---

    //console.log("Tentando inicializar DataTables para #example-fornecedores."); // DEBUG: Antes da inicialização
    tableFornecedores = $('#example-fornecedores').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "process/listar_fornecedores.php",
            "type": "POST",
            "data": function (d) {
                d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val();
            },
            "error": function (xhr, error, thrown) { // DEBUG: Adicionado manipulador de erro AJAX
                console.error("DEBUG: Erro AJAX no DataTables principal (listar_fornecedores.php):", error, thrown, xhr.responseText);
                showFeedbackMessage($feedbackMessageAreaFornecedor, 'Erro ao carregar dados dos fornecedores. Verifique o console para detalhes.', 'danger');
            }
        },
        "responsive": true,
        "columns": [
            {
                "data": "ent_situacao",
                "render": function (data, type, row) {
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
                    return row.ent_tipo_pessoa === 'F' ? (row.ent_cpf ? row.ent_cpf : 'N/A') : (row.ent_cnpj ? row.ent_cnpj : 'N/A');
                }
            },
            {
                "data": null, // Usaremos render para construir a string de endereço
                "render": function (data, type, row) {
                    // O end_logradouro e outros campos vêm do JOIN em listar_fornecedores.php
                    if (row.end_logradouro) {
                        return (row.end_tipo_endereco ? row.end_tipo_endereco + ': ' : '') + row.end_logradouro + ', ' + row.end_numero + ' - ' + row.end_bairro + ', ' + row.end_cidade + '/' + row.end_uf;
                    }
                    return 'N/A';
                }
            },
            // {
            //     "data": "ent_codigo",
            //     "orderable": false, // Ações não são ordenáveis
            //     "render": function (data, type, row) {
            //         return '<button type="button" class="btn btn-warning btn-sm btn-editar-fornecedor me-1" data-id="' + row.ent_codigo + '" title="Editar Fornecedor">' +
            //             '<i class="fas fa-edit"></i></button>' +
            //             '<button type="button" class="btn btn-danger btn-sm btn-excluir-fornecedor" data-id="' + row.ent_codigo + '" data-nome="' + row.ent_razao_social + '" title="Excluir Fornecedor">' +
            //             '<i class="fas fa-trash-alt"></i></button>';
            //     }
            // }

            {
                "data": "ent_codigo",
                "render": function (data, type, row) {
                    return '<a href="#" class="btn btn-warning btn-sm btn-editar-fornecedor me-1" data-id="' + row.ent_codigo + '">Editar</a>' +
                        '<a href="#" class="btn btn-danger btn-sm btn-excluir-fornecedor" data-id="' + row.ent_codigo + '" data-nome="' + row.ent_razao_social + '">Excluir</a>';
                }
            }




        ],
        "ordering": true,
        // "language": {
        //     "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json"
        // }

        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.10.22/i18n/Portuguese-Brasil.json"
        }
    });
    console.log("DataTables para #example-fornecedores inicializado."); // DEBUG: Após a inicialização

    // Evento para mudança dos filtros de situação na tela principal
    $filtroSituacaoRadiosForn.on('change', function () {
        tableFornecedores.ajax.reload(null, false); // Recarrega o DataTables
    });

    // Evento para mudança de Tipo de Pessoa (Física/Jurídica) do fornecedor no modal
    $('input[name="ent_tipo_pessoa"]').on('change', applyCpfCnpjMaskForn);
    applyCpfCnpjMaskForn(); // Aplica a máscara inicial ao carregar a página

    // Evento para o switch de Situação (Ativo/Inativo) do fornecedor no modal
    $situacaoFornecedor.on('change', function () {
        $textoSituacaoFornecedor.text(this.checked ? 'Ativo' : 'Inativo');
        $(this).val(this.checked ? 'A' : 'I'); // Define o valor do input baseado no estado do switch
    }).trigger('change'); // Dispara para definir o estado inicial do texto

    // --- Lógica do Modal Adicionar/Editar Fornecedor (com Abas) ---

    // Variável para armazenar o botão que abriu o modal (para gerenciamento de foco)
    var $triggerButton = null;

    // Evento de abertura do modal (resetar formulário e definir título)
    $modalFornecedor.on('show.bs.modal', function (event) {
        showFeedbackMessage($mensagemFornecedor, '', ''); // Limpa mensagens anteriores no modal (aba Dados Fornecedor)
        clearAddressFormFieldsForn(); // Limpa o formulário de endereço dentro do modal (aba Endereços)
        showFeedbackMessage($mensagemEnderecoForn, '', ''); // Limpa mensagens anteriores no modal (aba Endereços)

        $triggerButton = $(event.relatedTarget); // Armazena o botão que acionou o modal

        // Se o modal foi acionado pelo botão "Adicionar Fornecedor" principal
        if ($triggerButton.is($('#btn-adicionar-fornecedor-main'))) { // Use o ID correto do botão
            console.log("Modal aberto por 'Adicionar Fornecedor'. Forçando reset completo.");
            $('#modal-adicionar-fornecedor-label').text('Adicionar Fornecedor');

            // Força a limpeza de todos os campos do formulário principal
            $formFornecedor[0].reset();
            $entCodigoForn.val(''); // Garante que o ID oculto esteja vazio

            // Redefine os valores padrão para os elementos do formulário principal
            $situacaoFornecedor.prop('checked', true).val('A'); // Ativo por padrão
            $textoSituacaoFornecedor.text('Ativo');
            $tipoPessoaFornFisica.prop('checked', true).trigger('change'); // Pessoa Física e aplica máscara
            $tipoEntidadeFornecedorForn.prop('checked', true); // "Fornecedor" por padrão

            // Desabilita a aba de endereços para novos cadastros até o fornecedor ser salvo
            $('#enderecos-fornecedor-tab').addClass('disabled');
            // Limpa a tabela de endereços
            if ($.fn.DataTable.isDataTable('#tabela-enderecos-fornecedor')) {
                tableEnderecosForn.clear().draw();
            }
        }
        // A lógica para "Editar Fornecedor" está no evento de clique do botão ".btn-editar-fornecedor" (abaixo)
        // e não deve resetar o formulário aqui, pois os dados serão preenchidos por AJAX.

        // Ativa a primeira aba ("Dados do Fornecedor") por padrão em ambos os casos
        $('#dados-fornecedor-tab').tab('show');
    });

    // Lógica para o botão "Editar" da tabela de fornecedores (principal)
    $('#example-fornecedores tbody').on('click', '.btn-editar-fornecedor', function (e) {
        e.preventDefault();
        var idEntidade = $(this).data('id');

        // Resetar o formulário e carregar o modal, mas não redefinir valores padrão ainda
        $formFornecedor[0].reset();
        $('#modal-adicionar-fornecedor-label').text('Editar Fornecedor');
        $entCodigoForn.val(idEntidade); // Define o ID da entidade no campo oculto
        $endEntidadeIdForn.val(idEntidade); // Define o ID da entidade para o formulário de endereço

        // Habilita a aba de endereços para edição, pois é um fornecedor existente
        $('#enderecos-fornecedor-tab').removeClass('disabled');

        // Carrega os dados da entidade específica para preencher o formulário de edição
        $.ajax({
            url: 'process/get_entidade_data.php', // Endpoint para buscar dados da entidade
            type: 'POST', // Usamos POST porque estamos enviando um ID
            data: { id: idEntidade, tipo: 'fornecedor', csrf_token: $('input[name="csrf_token"]').val() }, // Passa o tipo
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    var entidadeData = response.data;
                    console.log("Dados da entidade recebidos para edição (fornecedor):", entidadeData);

                    // Preencher dados do fornecedor
                    $razaoSocialForn.val(entidadeData.ent_razao_social);

                    // Tipo de Pessoa (Física/Jurídica)
                    if (entidadeData.ent_tipo_pessoa === 'F') {
                        $tipoPessoaFornFisica.prop('checked', true);
                    } else {
                        $tipoPessoaFornJuridica.prop('checked', true);
                    }
                    $('input[name="ent_tipo_pessoa"]:checked').trigger('change'); // Dispara o evento change para a máscara

                    // CPF/CNPJ
                    var cpfCnpjValue = entidadeData.ent_tipo_pessoa === 'F' ? entidadeData.ent_cpf : entidadeData.ent_cnpj;
                    $cpfCnpjForn.val(cpfCnpjValue);
                    applyCpfCnpjMaskForn(); // Re-aplica a máscara após definir o valor

                    // Tipo de Entidade (Cliente, Fornecedor, Ambos)
                    $(`input[name="ent_tipo_entidade"][value="${entidadeData.ent_tipo_entidade}"]`).prop('checked', true);

                    // Situação (Ativo/Inativo)
                    $situacaoFornecedor.prop('checked', entidadeData.ent_situacao === 'A');
                    $situacaoFornecedor.trigger('change'); // Dispara o evento change para atualizar o texto visual

                    // Carrega a tabela de endereços para esta entidade no modal
                    loadEnderecosTableForn(idEntidade);

                    // Abre o modal de edição
                    $modalFornecedor.modal('show');

                } else {
                    showFeedbackMessage($feedbackMessageAreaFornecedor, response.message || 'Erro ao carregar dados do fornecedor para edição.', 'danger');
                    $modalFornecedor.modal('hide'); // Fecha o modal em caso de erro
                }
            },
            error: function (xhr, status, error) {
                showFeedbackMessage($feedbackMessageAreaFornecedor, 'Erro na requisição: ' + error + ' - ' + xhr.responseText, 'danger');
                console.error("Erro AJAX ao carregar dados do fornecedor: ", status, error, xhr.responseText);
                $modalFornecedor.modal('hide'); // Fecha o modal em caso de erro
            }
        });
    });

    // Lógica de envio do formulário principal do fornecedor (Aba "Dados do Fornecedor")
    $formFornecedor.on('submit', function (e) {
        e.preventDefault();

        var idEntidade = $entCodigoForn.val();
        var url = idEntidade ? 'process/editar_entidade.php' : 'process/cadastrar_entidade.php';

        var formData = new FormData(this);
        formData.append('csrf_token', $('input[name="csrf_token"]').val());
        // Opcional: Adicionar ID do usuário logado se o backend esperar
        // formData.append('usu_cadastro_id', <?php //echo $_SESSION['codUsuario'] ?? 'null'; ?>);

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            processData: false, // Importante para FormData
            contentType: false, // Importante para FormData
            success: function (response) {
                console.log('Resposta do servidor (salvar fornecedor):', response);
                if (response.success) {
                    showFeedbackMessage($mensagemFornecedor, response.message, 'success');
                    // Se for um novo cadastro, define o ID da entidade para permitir adicionar endereços
                    if (!idEntidade) {
                        $entCodigoForn.val(response.ent_codigo); // Assume que o backend retorna o ID do novo fornecedor
                        $endEntidadeIdForn.val(response.ent_codigo); // Define o ID para o formulário de endereço
                        $('#enderecos-fornecedor-tab').removeClass('disabled'); // Habilita a aba de endereços
                        loadEnderecosTableForn(response.ent_codigo); // Carrega a tabela de endereços para o novo fornecedor
                    }
                    // Recarrega a tabela principal de fornecedores para refletir a mudança
                    setTimeout(function () {
                        tableFornecedores.ajax.reload(null, false);
                    }, 200);

                } else {
                    showFeedbackMessage($mensagemFornecedor, response.message, 'danger');
                }
            },
            error: function (xhr, status, error) {
                showFeedbackMessage($mensagemFornecedor, 'Erro ao salvar fornecedor: ' + xhr.responseText, 'danger');
                console.error("Erro AJAX ao salvar fornecedor: ", status, error, xhr.responseText);
            }
        });
    });

    // --- Lógica para o formulário de Endereço (Aba "Endereços") ---

    // Evento de clique no botão "Buscar CEP" do formulário de endereço
    $btnBuscarCepEnderecoForn.on('click', searchCepEnderecoForn);

    // Evento de "blur" (perda de foco) no campo CEP do formulário de endereço
    $cepEnderecoForn.on('blur', function () {
        if ($(this).val().replace(/\D/g, '').length === 8) {
            searchCepEnderecoForn();
        }
    });

    // Lógica de envio do formulário de endereço do fornecedor
    $formEnderecoForn.on('submit', function (e) {
        e.preventDefault();

        var entidadeId = $endEntidadeIdForn.val();
        if (!entidadeId) {
            showFeedbackMessage($mensagemEnderecoForn, 'Primeiro, salve os dados do fornecedor na aba "Dados do Fornecedor".', 'danger');
            return;
        }

        // Validação básica dos campos de endereço
        const tipoEndereco = $tipoEnderecoForn.val();
        const cep = $cepEnderecoForn.val().replace(/\D/g, '');
        const logradouro = $logradouroEnderecoForn.val().trim();
        const numero = $numeroEnderecoForn.val().trim();
        const bairro = $bairroEnderecoForn.val().trim();
        const cidade = $cidadeEnderecoForn.val().trim();
        const uf = $ufEnderecoForn.val();

        if (!tipoEndereco || !cep || !logradouro || !numero || !bairro || !cidade || !uf) {
            showFeedbackMessage($mensagemEnderecoForn, 'Preencha todos os campos obrigatórios do endereço (Tipo, CEP, Logradouro, Número, Bairro, Cidade, UF).', 'danger');
            return;
        }
        if (cep.length !== 8) {
            showFeedbackMessage($mensagemEnderecoForn, 'CEP inválido. Digite 8 dígitos.', 'warning');
            return;
        }

        var idEndereco = $endCodigoForn.val();
        var url = idEndereco ? 'process/editar_endereco.php' : 'process/cadastrar_endereco.php';

        var formData = new FormData(this);
        formData.append('end_entidade_id', entidadeId); // Garante que o ID da entidade está sendo enviado
        formData.append('csrf_token', $('input[name="csrf_token"]').val());
        // Opcional: Adicionar ID do usuário logado se o backend esperar
        // formData.append('end_usuario_cadastro_id', <?php //echo $_SESSION['codUsuario'] ?? 'null'; ?>);

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (response) {
                console.log("DEBUG: Resposta do servidor para salvar/editar endereço (fornecedor):", response);
                if (response.success) {
                    clearAddressFormFieldsForn(); // Limpa o formulário de endereço
                    tableEnderecosForn.ajax.reload(); // Recarrega a tabela de endereços no modal
                    showFeedbackMessage($mensagemEnderecoForn, response.message, 'success');
                    // Recarrega a tabela principal com um pequeno atraso para garantir que o BD esteja atualizado
                    setTimeout(function () {
                        tableFornecedores.ajax.reload(null, false);
                    }, 200);
                } else {
                    showFeedbackMessage($mensagemEnderecoForn, response.message, 'danger');
                }
            },
            error: function (xhr, status, error) {
                showFeedbackMessage($mensagemEnderecoForn, 'Erro na requisição: ' + error + ' - ' + xhr.responseText, 'danger');
                console.error("Erro AJAX ao salvar endereço (fornecedor): ", status, error, xhr.responseText);
            }
        });
    });

    // Lógica para o botão "Cancelar" no formulário de endereço do fornecedor
    $btnCancelarEdicaoEnderecoForn.on('click', function () {
        clearAddressFormFieldsForn();
        showFeedbackMessage($mensagemEnderecoForn, 'Edição de endereço cancelada.', 'info');
    });

    // Lógica para o botão "Editar" da tabela de endereços (dentro do modal do fornecedor)
    $('#tabela-enderecos-fornecedor tbody').on('click', '.btn-editar-endereco', function (e) {
        e.preventDefault();
        var idEndereco = $(this).data('id');
        console.log("DEBUG: Clicou em Editar Endereço (fornecedor). ID do Endereço:", idEndereco);

        $.ajax({
            url: 'process/get_endereco_data.php', // Endpoint para buscar dados de um endereço
            type: 'POST', // Usamos POST para consistência com outros endpoints, embora GET também funcionaria
            data: { id: idEndereco, csrf_token: $('input[name="csrf_token"]').val() },
            dataType: 'json',
            success: function (response) {
                console.log("DEBUG: Dados do endereço recebidos para edição (fornecedor):", response);
                if (response.success && response.data) {
                    var enderecoData = response.data;
                    $endCodigoForn.val(enderecoData.end_codigo);
                    $tipoEnderecoForn.val(enderecoData.end_tipo_endereco);
                    $cepEnderecoForn.val(enderecoData.end_cep).trigger('blur'); // Aciona blur para máscara e busca
                    $logradouroEnderecoForn.val(enderecoData.end_logradouro);
                    $numeroEnderecoForn.val(enderecoData.end_numero);
                    $complementoEnderecoForn.val(enderecoData.end_complemento);
                    $bairroEnderecoForn.val(enderecoData.end_bairro);
                    $cidadeEnderecoForn.val(enderecoData.end_cidade);
                    $ufEnderecoForn.val(enderecoData.end_uf);
                    $btnSalvarEnderecoForn.text('Atualizar Endereço').removeClass('btn-primary').addClass('btn-warning');
                    $btnCancelarEdicaoEnderecoForn.show();
                    showFeedbackMessage($mensagemEnderecoForn, '', ''); // Limpa mensagens anteriores
                    // Ativa a aba de endereços e foca nela
                    var someTabTriggerEl = document.querySelector('#enderecos-fornecedor-tab');
                    var tab = new bootstrap.Tab(someTabTriggerEl);
                    tab.show();
                } else {
                    showFeedbackMessage($mensagemEnderecoForn, response.message || 'Erro ao carregar dados do endereço.', 'danger');
                }
            },
            error: function (xhr, status, error) {
                showFeedbackMessage($mensagemEnderecoForn, 'Erro na requisição: ' + error + ' - ' + xhr.responseText, 'danger');
                console.error("Erro AJAX ao carregar dados do endereço (fornecedor): ", status, error, xhr.responseText);
            }
        });
    });

    // Lógica para o botão "Excluir" da tabela de endereços (dentro do modal do fornecedor)
    $('#tabela-enderecos-fornecedor tbody').on('click', '.btn-excluir-endereco', function (e) {
        e.preventDefault();
        var idEndereco = $(this).data('id');
        $('#id-endereco-excluir-fornecedor').val(idEndereco); // Define o ID do endereço a ser excluído

        var confirmModalEnderecoForn = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao-endereco-fornecedor'));
        confirmModalEnderecoForn.show();
    });

    // Lógica para o botão "Sim, Excluir" dentro do modal de confirmação de exclusão de endereço do fornecedor
    $('#btn-confirmar-exclusao-endereco-fornecedor').on('click', function () {
        var idEndereco = $('#id-endereco-excluir-fornecedor').val();
        var csrfToken = $('input[name="csrf_token"]').val();
        console.log("DEBUG: Confirmando exclusão de endereço (fornecedor). ID:", idEndereco);

        $.ajax({
            type: 'POST',
            url: 'process/excluir_endereco.php', // Endpoint para excluir endereço
            data: { end_codigo: idEndereco, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                console.log("DEBUG: Resposta do servidor para exclusão de endereço (fornecedor):", response);
                var confirmModalEnderecoForn = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-endereco-fornecedor'));
                confirmModalEnderecoForn.hide();

                if (response.success) {
                    tableEnderecosForn.ajax.reload(); // Recarrega a tabela de endereços no modal
                    showFeedbackMessage($feedbackMessageAreaFornecedor, response.message, 'success'); // Mensagem na página principal
                    // Recarrega a tabela principal com um pequeno atraso para garantir que o BD esteja atualizado
                    setTimeout(function () {
                        tableFornecedores.ajax.reload(null, false);
                    }, 200);
                } else {
                    showFeedbackMessage($feedbackMessageAreaFornecedor, 'Erro ao excluir endereço: ' + response.message, 'danger');
                }
            },
            error: function (xhr, status, error) {
                var confirmModalEnderecoForn = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-endereco-fornecedor'));
                confirmModalEnderecoForn.hide();
                showFeedbackMessage($feedbackMessageAreaFornecedor, 'Erro na requisição de exclusão de endereço: ' + error + ' - ' + xhr.responseText, 'danger');
            }
        });
    });


    // --- Lógica do Botão Excluir Fornecedor (principal) ---
    $('#example-fornecedores tbody').on('click', '.btn-excluir-fornecedor', function (e) {
        e.preventDefault();
        var idEntidade = $(this).data('id');
        var nomeEntidade = $(this).data('nome');

        $('#nome-fornecedor-excluir').text(nomeEntidade);
        $('#id-fornecedor-excluir').val(idEntidade);

        var confirmModalForn = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao-fornecedor'));
        confirmModalForn.show();
    });

    // Lógica para o botão "Sim, Excluir" dentro do modal de confirmação de exclusão de fornecedor
    $('#btn-confirmar-exclusao-fornecedor').on('click', function () {
        var idEntidade = $('#id-fornecedor-excluir').val();
        var csrfToken = $('input[name="csrf_token"]').val();

        $.ajax({
            type: 'POST',
            url: 'process/excluir_entidade.php', // Endpoint para excluir entidade
            data: { ent_codigo: idEntidade, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                var confirmModalForn = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-fornecedor'));
                confirmModalForn.hide();

                if (response.success) {
                    // Recarrega a tabela principal
                    setTimeout(function () {
                        tableFornecedores.ajax.reload(null, false);
                    }, 200);
                    showFeedbackMessage($feedbackMessageAreaFornecedor, response.message, 'success');
                } else {
                    showFeedbackMessage($feedbackMessageAreaFornecedor, 'Erro ao excluir fornecedor: ' + response.message, 'danger');
                }
            },
            error: function (xhr, status, error) {
                var confirmModalForn = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-fornecedor'));
                confirmModalForn.hide();
                showFeedbackMessage($feedbackMessageAreaFornecedor, 'Erro na requisição de exclusão: ' + error + ' - ' + xhr.responseText, 'danger');
            }
        });
    });

    // --- Gerenciamento de Foco para Acessibilidade (após fechar modais) ---
    // Evento que dispara quando o modal de fornecedor é COMPLETAMENTE FECHADO
    $modalFornecedor.on('hidden.bs.modal', function () {
        console.log("Modal de Fornecedor escondido. Forçando reset completo.");
        $(this).find(':focus').blur(); // Desfoca qualquer elemento dentro do modal

        // Reseta o formulário principal do fornecedor
        $formFornecedor[0].reset();
        $entCodigoForn.val('');

        // Redefine os valores padrão do formulário principal
        $situacaoFornecedor.prop('checked', true).val('A');
        $textoSituacaoFornecedor.text('Ativo');
        $tipoPessoaFornFisica.prop('checked', true).trigger('change');
        $tipoEntidadeFornecedorForn.prop('checked', true);

        // Desabilita a aba de endereços e limpa a tabela de endereços (garantir estado inicial)
        $('#enderecos-fornecedor-tab').addClass('disabled');
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-fornecedor')) {
            tableEnderecosForn.clear().draw();
        }
        clearAddressFormFieldsForn(); // Limpa também o formulário de endereço

        // Move o foco de volta para o botão que abriu o modal
        if ($triggerButton && $triggerButton.length) {
            setTimeout(function () {
                $triggerButton.focus();
                $triggerButton = null; // Limpa a referência
            }, 100);
        } else {
            setTimeout(function () {
                $('#btn-adicionar-fornecedor-main').focus(); // Fallback: foca no botão principal de adicionar fornecedor
            }, 100);
        }
    });

    $('#modal-confirmar-exclusao-fornecedor').on('hidden.bs.modal', function () {
        $(this).find(':focus').blur();
        setTimeout(function () {
            $('#btn-adicionar-fornecedor-main').focus();
        }, 100);
    });

    $('#modal-confirmar-exclusao-endereco-fornecedor').on('hidden.bs.modal', function () {
        $(this).find(':focus').blur();
        if ($modalFornecedor.hasClass('show')) { // Se o modal principal de fornecedor estiver aberto
            var someTabTriggerEl = document.querySelector('#enderecos-fornecedor-tab');
            var tab = new bootstrap.Tab(someTabTriggerEl);
            tab.show();
            // Tenta focar no botão Salvar Endereço dentro da aba
            $('#btn-salvar-endereco-fornecedor').focus();
        } else {
            setTimeout(function () {
                $('#btn-adicionar-fornecedor-main').focus();
            }, 100);
        }
    });
});
