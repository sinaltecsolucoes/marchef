$(document).ready(function () {
    // --- Seletores e Variáveis Globais para a Tela de Clientes ---
    // ID do modal de adicionar/editar cliente (deve corresponder ao ID no clientes.php)
    var $modalCliente = $('#modal-adicionar-cliente');
    // ID do formulário de cliente (deve corresponder ao ID no clientes.php)
    var $formCliente = $('#form-cliente');
    var $mensagemCliente = $('#mensagem-cliente'); // Mensagens dentro do modal (ex: erros de validação)
    var $feedbackMessageAreaCliente = $('#feedback-message-area-cliente'); // Mensagens na página principal (sucesso/erro geral)
    var $btnAdicionarClienteMain = $('#btn-adicionar-cliente-main'); // Botão principal "Adicionar Cliente"

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

    // Campos do formulário de Endereço
    var $cep = $('#cep');
    var $btnBuscarCep = $('#btn-buscar-cep');
    var $cepFeedback = $('#cep-feedback');
    var $logradouro = $('#logradouro');
    var $numero = $('#numero');
    var $complemento = $('#complemento');
    var $bairro = $('#bairro');
    var $cidade = $('#cidade');
    var $uf = $('#uf');

    // --- Funções Auxiliares ---

    // Função para exibir mensagens de feedback na área principal da página
    function showFeedbackMessageCliente(message, type = 'success') {
        $feedbackMessageAreaCliente.empty().removeClass('alert alert-success alert-danger');
        var alertClass = (type === 'success') ? 'alert-success' : 'alert-danger';
        $feedbackMessageAreaCliente.addClass('alert ' + alertClass).text(message);
        setTimeout(function() {
            $feedbackMessageAreaCliente.fadeOut('slow', function() {
                $(this).empty().removeClass('alert alert-success alert-danger').show();
            });
        }, 5000); // Mensagem some após 5 segundos
    }

    // Função para aplicar a máscara correta ao campo CPF/CNPJ
    function applyCpfCnpjMask() {
        if ($tipoPessoaFisica.is(':checked')) {
            $labelCpfCnpj.text('CPF');
            $cpfCnpj.attr('placeholder', '000.000.000-00').mask('000.000.000-00', {reverse: true});
        } else {
            $labelCpfCnpj.text('CNPJ');
            $cpfCnpj.attr('placeholder', '00.000.000/0000-00').mask('00.000.000/0000-00', {reverse: true});
        }
    }

    // Função para limpar os campos de endereço
    function clearAddressFields() {
        $logradouro.val('');
        $numero.val('');
        $complemento.val('');
        $bairro.val('');
        $cidade.val('');
        $uf.val('');
        $cepFeedback.empty();
    }

    // Função para buscar CEP via ViaCEP
    function searchCep() {
        var cepValue = $cep.val().replace(/\D/g, ''); // Remove não-dígitos
        if (cepValue.length !== 8) {
            $cepFeedback.text('CEP inválido.').removeClass('text-success').addClass('text-danger');
            clearAddressFields();
            return;
        }

        $cepFeedback.text('Buscando CEP...').removeClass('text-danger').addClass('text-muted');
        clearAddressFields(); // Limpa antes de buscar para evitar dados antigos

        $.ajax({
            url: 'https://viacep.com.br/ws/' + cepValue + '/json/',
            method: 'GET',
            dataType: 'json',
            success: function (data) {
                if (data.erro) {
                    $cepFeedback.text('CEP não encontrado.').removeClass('text-success').addClass('text-danger');
                    clearAddressFields();
                } else {
                    $logradouro.val(data.logradouro);
                    $bairro.val(data.bairro);
                    $cidade.val(data.localidade); // ViaCEP retorna 'localidade' para cidade
                    $uf.val(data.uf);
                    $cepFeedback.text('CEP encontrado!').removeClass('text-danger').addClass('text-success');
                    $numero.focus(); // Foca no campo número para o usuário preencher
                }
            },
            error: function () {
                $cepFeedback.text('Erro ao buscar CEP. Tente novamente.').removeClass('text-success').addClass('text-danger');
                clearAddressFields();
            }
        });
    }

    // --- Lógica de Eventos ---

    // Inicializa o DataTables para clientes
    // O ID da tabela deve ser 'example-clientes' conforme definido em clientes.php
    var tableClientes = $('#example-clientes').DataTable({
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"t>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "ajax": "process/listar_entidades.php?tipo_entidade=Cliente", // Endpoint para listar apenas clientes
        "responsive": true,
        "columns": [
            {
                "data": "ent_situacao",
                "render": function (data, type, row) {
                    return (data === 'A') ? "Ativo" : "Inativo";
                }
            },
            {
                "data": "ent_tipo_entidade",
                "render": function(data, type, row) {
                    // Capitaliza a primeira letra para exibição
                    return data.charAt(0).toUpperCase() + data.slice(1);
                }
            },
            { "data": "ent_razao_social" },
            {
                "data": null, // Usa null porque os dados vêm de diferentes colunas (CPF ou CNPJ)
                "render": function (data, type, row) {
                    return row.ent_tipo_pessoa === 'F' ? row.ent_cpf : row.ent_cnpj;
                }
            },
            {
                "data": "end_logradouro",
                "render": function (data, type, row) {
                    // Exibe o endereço principal formatado
                    if (data) {
                        return data + ', ' + row.end_numero + ' - ' + row.end_bairro + ', ' + row.end_cidade + '/' + row.end_uf;
                    }
                    return 'N/A'; // Se não houver endereço principal
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
            "url": "https://cdn.datatables.net/plug-ins/1.10.22/i18n/Portuguese-Brasil.json"
        }
    });

    // Evento para mudança de Tipo de Pessoa (Física/Jurídica)
    $('input[name="ent_tipo_pessoa"]').on('change', applyCpfCnpjMask);

    // Aplica a máscara inicial ao carregar a página
    applyCpfCnpjMask();

    // Evento de clique no botão "Buscar CEP"
    $btnBuscarCep.on('click', searchCep);

    // Evento de "blur" (perda de foco) no campo CEP para buscar automaticamente
    $cep.on('blur', function() {
        if ($(this).val().replace(/\D/g, '').length === 8) {
            searchCep();
        }
    });

    // Evento para o switch de Situação (Ativo/Inativo)
    $situacaoCliente.on('change', function() {
        $textoSituacaoCliente.text(this.checked ? 'Ativo' : 'Inativo');
        $(this).val(this.checked ? 'A' : 'I'); // Atualiza o valor do input hidden para 'A' ou 'I'
    });

    // --- Lógica do Modal Adicionar/Editar Cliente ---

    // Evento de abertura do modal (resetar formulário e definir título)
    $modalCliente.on('show.bs.modal', function (event) {
        $mensagemCliente.empty().removeClass('alert alert-success alert-danger'); // Limpa mensagens internas do modal
        $formCliente[0].reset(); // Reseta o formulário para seus valores padrão
        clearAddressFields(); // Limpa campos de endereço
        $entCodigo.val(''); // Limpa o ID oculto (para garantir que é um novo cadastro por padrão)
        $situacaoCliente.prop('checked', true).val('A'); // Define como Ativo por padrão
        $textoSituacaoCliente.text('Ativo');
        $tipoPessoaFisica.prop('checked', true); // Define Pessoa Física como padrão
        applyCpfCnpjMask(); // Aplica a máscara de CPF

        var button = $(event.relatedTarget); // Botão que acionou o modal (para identificar se é "Adicionar" ou "Editar")

        // Se o modal foi acionado pelo botão "Adicionar Cliente" principal
        if (button.is($btnAdicionarClienteMain)) {
            $('#modal-adicionar-cliente-label').text('Adicionar Cliente'); // Define o título do modal
            // Garante que o tipo de entidade padrão seja "Cliente" para o botão "Adicionar Cliente"
            $tipoEntidadeCliente.prop('checked', true);
        }
        // A lógica para "Editar Cliente" está no evento de clique do botão ".btn-editar-cliente"
    });

    // Lógica para o botão "Editar" da tabela de clientes
    // O evento é delegado para '#example-clientes tbody' porque os botões são adicionados dinamicamente pelo DataTables
    $('#example-clientes tbody').on('click', '.btn-editar-cliente', function (e) {
        e.preventDefault();
        var idEntidade = $(this).data('id'); // Pega o ID da entidade do atributo data-id do botão

        $('#modal-adicionar-cliente-label').text('Editar Cliente'); // Muda o título do modal para "Editar Cliente"
        $entCodigo.val(idEntidade); // Preenche o campo oculto com o ID da entidade para envio no formulário

        // Carrega os dados da entidade específica para preencher o formulário de edição
        $.ajax({
            url: 'process/get_entidade_data.php', // Endpoint para buscar dados de uma entidade
            type: 'GET',
            data: { id: idEntidade },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    var entidadeData = response.data;
                    $razaoSocial.val(entidadeData.ent_razao_social);

                    // Define o tipo de pessoa (Física/Jurídica) e aplica a máscara correta
                    if (entidadeData.ent_tipo_pessoa === 'F') {
                        $tipoPessoaFisica.prop('checked', true);
                        $cpfCnpj.val(entidadeData.ent_cpf);
                    } else {
                        $tipoPessoaJuridica.prop('checked', true);
                        $cpfCnpj.val(entidadeData.ent_cnpj);
                    }
                    applyCpfCnpjMask(); // Aplica a máscara após preencher o valor

                    // Define o tipo de entidade (Cliente, Fornecedor, Ambos)
                    if (entidadeData.ent_tipo_entidade === 'Cliente') {
                        $tipoEntidadeCliente.prop('checked', true);
                    } else if (entidadeData.ent_tipo_entidade === 'Fornecedor') {
                        $tipoEntidadeFornecedor.prop('checked', true);
                    } else if (entidadeData.ent_tipo_entidade === 'Cliente e Fornecedor') {
                        $tipoEntidadeAmbos.prop('checked', true);
                    }

                    // Define a situação (Ativo/Inativo)
                    $situacaoCliente.prop('checked', entidadeData.ent_situacao === 'A');
                    $textoSituacaoCliente.text(entidadeData.ent_situacao === 'A' ? 'Ativo' : 'Inativo');
                    $situacaoCliente.val(entidadeData.ent_situacao); // Garante que o valor do input hidden esteja correto

                    // Preenche os campos de endereço se existirem dados de endereço
                    if (entidadeData.endereco) {
                        $cep.val(entidadeData.endereco.end_cep);
                        $logradouro.val(entidadeData.endereco.end_logradouro);
                        $numero.val(entidadeData.endereco.end_numero);
                        $complemento.val(entidadeData.endereco.end_complemento);
                        $bairro.val(entidadeData.endereco.end_bairro);
                        $cidade.val(entidadeData.endereco.end_cidade); // Assume que o backend já retorna 'end_cidade'
                        $uf.val(entidadeData.endereco.end_uf);
                    } else {
                        clearAddressFields(); // Limpa se não houver endereço associado
                    }

                    // Abre o modal de edição
                    var clienteModal = new bootstrap.Modal(document.getElementById('modal-adicionar-cliente'));
                    clienteModal.show();
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

    // Lógica de envio do formulário (Adicionar ou Editar Cliente)
    $formCliente.on('submit', function (e) {
        e.preventDefault(); // Impede o envio padrão do formulário HTML
        console.log("Evento de submit do formulário de cliente acionado!"); // LINHA DE DEBUG
        console.log("Coletando dados do formulário..."); // Nova linha de debug
        
        var idEntidade = $entCodigo.val();
        var url = idEntidade ? 'process/editar_entidade.php' : 'process/cadastrar_entidade.php';

        var formData = new FormData(this); // Coleta todos os dados do formulário, incluindo arquivos (se houver)

        // Adiciona o token CSRF ao formData
        var csrfToken = $('input[name="csrf_token"]').val();
        formData.append('csrf_token', csrfToken);

        console.log("Dados do formulário coletados. URL de destino:", url); // Nova linha de debug
        console.log("Iniciando requisição AJAX..."); // Nova linha de debug

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json', // Espera uma resposta JSON do servidor
            processData: false, // Não processa os dados (necessário para FormData)
            contentType: false, // Não define o tipo de conteúdo (necessário para FormData)
            success: function (response) {
                console.log("Requisição AJAX bem-sucedida. Resposta:", response); // Nova linha de debug
                if (response.success) {
                    $modalCliente.modal('hide'); // Esconde o modal
                    $formCliente[0].reset(); // Reseta o formulário
                    tableClientes.ajax.reload(); // Recarrega a tabela DataTables para mostrar as alterações
                    showFeedbackMessageCliente(response.message, 'success'); // Exibe mensagem de sucesso na página principal
                } else {
                    // Exibe erro dentro do modal
                    $mensagemCliente.empty().removeClass().addClass('alert alert-danger').text(response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("Erro na requisição AJAX:", status, error, xhr.responseText); // Nova linha de debug
                // Exibe erro dentro do modal e no console
                $mensagemCliente.empty().removeClass().addClass('alert alert-danger').text('Erro na requisição: ' + error);
            }
        });
    });

    // --- Lógica do Botão Excluir Cliente ---
    // O evento é delegado para '#example-clientes tbody' porque os botões são adicionados dinamicamente
    $('#example-clientes tbody').on('click', '.btn-excluir-cliente', function (e) {
        e.preventDefault();
        var idEntidade = $(this).data('id'); // Pega o ID da entidade do botão
        var nomeEntidade = $(this).data('nome'); // Pega o nome da entidade do botão

        $('#nome-cliente-excluir').text(nomeEntidade); // Preenche o nome no modal de confirmação
        $('#id-cliente-excluir').val(idEntidade); // Preenche o ID oculto no modal de confirmação

        // Abre o modal de confirmação de exclusão
        var confirmModal = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao-cliente'));
        confirmModal.show();
    });

    // Lógica para o botão "Sim, Excluir" dentro do modal de confirmação de exclusão
    $('#btn-confirmar-exclusao-cliente').on('click', function () {
        var idEntidade = $('#id-cliente-excluir').val(); // Pega o ID da entidade a ser excluída
        var csrfToken = $('input[name="csrf_token"]').val(); // Pega o token CSRF para segurança

        $.ajax({
            type: 'POST',
            url: 'process/excluir_entidade.php', // Endpoint para excluir entidade
            data: { ent_codigo: idEntidade, csrf_token: csrfToken }, // Envia ID e token
            dataType: 'json',
            success: function (response) {
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-cliente'));
                confirmModal.hide(); // Esconde o modal de confirmação

                if (response.success) {
                    tableClientes.ajax.reload(); // Recarrega a tabela DataTables
                    showFeedbackMessageCliente(response.message, 'success'); // Exibe mensagem de sucesso
                } else {
                    showFeedbackMessageCliente('Erro ao excluir cliente: ' + response.message, 'danger'); // Exibe erro
                }
            },
            error: function (xhr, status, error) {
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao-cliente'));
                confirmModal.hide();
                showFeedbackMessageCliente('Erro na requisição de exclusão: ' + error, 'danger'); // Exibe erro
            }
        });
    });

    // --- Gerenciamento de Foco para Acessibilidade ---
    // NOVO: Usa o evento 'hide.bs.modal' para mover o foco antes que o modal seja completamente ocultado
    $modalCliente.on('hide.bs.modal', function () {
        // Garante que o foco seja movido para o botão principal antes que o modal seja totalmente ocultado
        if ($btnAdicionarClienteMain.length) { // Verifica se o botão existe
            $btnAdicionarClienteMain.focus();
        }
    });

    // Mantém o 'hidden.bs.modal' para qualquer limpeza ou lógica que precise ocorrer APÓS a ocultação
    $modalCliente.on('hidden.bs.modal', function () {
        // Opcional: qualquer outra lógica que precise ser executada após o modal estar completamente oculto
        // Por exemplo, limpar dados sensíveis se não foi feito no reset do formulário.
    });

    // Repete a lógica para o modal de exclusão
    $('#modal-confirmar-exclusao-cliente').on('hide.bs.modal', function () {
        if ($btnAdicionarClienteMain.length) {
            $btnAdicionarClienteMain.focus();
        }
    });
});
