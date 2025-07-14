$(document).ready(function () {
    // Seletor para a área de mensagens de feedback na tela de usuários
    var $feedbackMessageArea = $('#feedback-message-area');

    // Função para exibir mensagens de feedback na tela de usuários
    function showFeedbackMessage(message, type = 'success') {
        $feedbackMessageArea.empty().removeClass('alert alert-success alert-danger');
        var alertClass = (type === 'success') ? 'alert-success' : 'alert-danger';
        $feedbackMessageArea.addClass('alert ' + alertClass).text(message);
        // Opcional: esconder a mensagem após alguns segundos
        setTimeout(function() {
            $feedbackMessageArea.fadeOut('slow', function() {
                $(this).empty().removeClass('alert alert-success alert-danger').show();
            });
        }, 5000); // Mensagem some após 5 segundos
    }

    // --- LÓGICA DO DATATABLES (Lista de Usuários) ---
    // Verifica se a tabela já foi inicializada e a destrói se for o caso
    if ($.fn.DataTable.isDataTable('#example')) {
        $('#example').DataTable().destroy();
    }

    // Inicializa o DataTables
    var table = $('#example').DataTable({ // Armazena a instância do DataTables em uma variável
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"t>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "ajax": "process/listar_usuarios.php", // Caminho atualizado para o script PHP
        "responsive": true,
        "columns": [
            {
                "data": "usu_situacao",
                "render": function (data, type, row) {
                    if (data === 'A') {
                        return "Ativo";
                    }
                    return "Inativo";
                }
            },
            { "data": "usu_login" },
            { "data": "usu_nome" },
            { "data": "usu_tipo" },
            { "data": "usu_codigo" },
            {
                "data": "usu_codigo",
                "render": function (data, type, row) {
                    // Adiciona data-id e data-nome aos botões para fácil acesso
                    return '<a href="#" class="btn btn-warning btn-sm btn-editar-usuario me-1" data-id="' + row.usu_codigo + '">Editar</a>' +
                           '<a href="#" class="btn btn-danger btn-sm btn-excluir-usuario" data-id="' + row.usu_codigo + '" data-nome="' + row.usu_nome + '">Excluir</a>';
                }
            }
        ],
        "ordering": true,
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.10.22/i18n/Portuguese-Brasil.json"
        }
    });

    // --- LÓGICA DO FORMULÁRIO (Adicionar Usuário) ---
    $('#form-adicionar-usuario').on('submit', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
            type: 'POST',
            url: 'process/cadastrar_usuarios.php', // Caminho atualizado para o script PHP
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#modal-adicionar-usuario').modal('hide');
                    $('#form-adicionar-usuario')[0].reset();
                    table.ajax.reload(); // Usa a instância 'table' para recarregar
                    showFeedbackMessage(response.message, 'success'); // Usa a função personalizada
                } else {
                    showFeedbackMessage('Erro ao cadastrar usuário: ' + response.message, 'danger'); // Usa a função personalizada
                }
            },
            error: function (xhr, status, error) {
                showFeedbackMessage('Erro na requisição: ' + error, 'danger'); // Usa a função personalizada
            }
        });
    });

    // --- LÓGICA DO BOTÃO EXCLUIR ---
    // Usa delegação de eventos para botões gerados dinamicamente pelo DataTables
    $('#example tbody').on('click', '.btn-excluir-usuario', function (e) {
        e.preventDefault();
        var userId = $(this).data('id');
        var userName = $(this).data('nome');

        // Preenche o modal de confirmação
        $('#nome-usuario-excluir').text(userName);
        $('#id-usuario-excluir').val(userId);

        // Exibe o modal de confirmação
        var confirmModal = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao'));
        confirmModal.show();
    });

    // Lógica para o botão "Sim, Excluir" dentro do modal de confirmação
    $('#btn-confirmar-exclusao').on('click', function () {
        var userId = $('#id-usuario-excluir').val();
        var csrfToken = $('input[name="csrf_token"]').val(); // Pega o token CSRF do campo oculto no index.php

        $.ajax({
            type: 'POST',
            url: 'process/excluir_usuario.php', // Caminho atualizado para o script PHP
            data: { usu_codigo: userId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao'));
                confirmModal.hide(); // Esconde o modal de confirmação

                if (response.success) {
                    table.ajax.reload(); // Recarrega a tabela do DataTables
                    showFeedbackMessage(response.message, 'success'); // Usa a função personalizada
                } else {
                    showFeedbackMessage('Erro ao excluir usuário: ' + response.message, 'danger'); // Usa a função personalizada
                }
            },
            error: function (xhr, status, error) {
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-exclusao'));
                confirmModal.hide();
                showFeedbackMessage('Erro na requisição de exclusão: ' + error, 'danger'); // Usa a função personalizada
            }
        });
    });

    // --- LÓGICA DO BOTÃO EDITAR ---
    $('#example tbody').on('click', '.btn-editar-usuario', function (e) {
        e.preventDefault();
        var userId = $(this).data('id');

        // Esconde o combobox de seleção de usuário e sua label no modal de perfil
        $('#selecionar-usuario-perfil').closest('.mb-3').hide();
        // Adiciona um campo oculto para o ID do usuário que está sendo editado
        // Isso é para que editar-perfil.php saiba qual usuário editar,
        // já que o combobox estará escondido.
        $('#form-perfil').append('<input type="hidden" id="hidden-edit-usu-codigo" name="usu_codigo_selecionado" value="' + userId + '">');


        // Carrega os dados do usuário específico no modal de perfil
        // A função loadUserData está em enviar-dados-perfil.js
        // A chamada a loadUserData() precisa ser globalmente acessível ou estar no mesmo escopo.
        // Assumindo que enviar-dados-perfil.js já foi carregado e suas funções são acessíveis.
        if (typeof loadUserData === 'function') {
            loadUserData(userId);
        } else {
            console.error("loadUserData não está definida. Verifique a ordem de carregamento dos scripts.");
        }

        // Abre o modal de perfil
        var perfilModal = new bootstrap.Modal(document.getElementById('perfil'));
        perfilModal.show();
    });

    // Evento de fechamento do modal de perfil (para reexibir o combobox)
    $('#perfil').on('hidden.bs.modal', function () {
        // Remove o campo oculto do ID do usuário editado
        $('#hidden-edit-usu-codigo').remove();
        // Reexibe o combobox de seleção de usuário e sua label
        $('#selecionar-usuario-perfil').closest('.mb-3').show();
        // Limpa a seleção do combobox e os campos do formulário
        $('#selecionar-usuario-perfil').val('');
        // A função loadUserData('') já faz a limpeza dos campos
        if (typeof loadUserData === 'function') {
            loadUserData('');
        }
    });

    // Lógica para gerenciar o foco ao fechar o modal de adicionar usuário
    $('#modal-adicionar-usuario').on('hidden.bs.modal', function () {
        // Remove o foco de qualquer elemento dentro do modal que possa ter retido-o
        $(this).find(':focus').blur(); 
        
        // Retorna o foco para o botão que abriu o modal (o botão "Adicionar Usuário")
        $('#btn-adicionar-usuario-main').focus(); // NOVO: Adiciona um ID ao botão principal
    });

    // NOVO: Lógica para gerenciar o foco ao fechar o modal de confirmação de exclusão
    $('#modal-confirmar-exclusao').on('hidden.bs.modal', function () {
        // Remove o foco de qualquer elemento dentro do modal que possa ter retido-o
        $(this).find(':focus').blur(); 
        
        // Retorna o foco para o botão "Adicionar Usuário" como um fallback seguro
        // Ou você pode tentar focar no botão "Excluir" da linha da tabela, se tiver o ID
        $('#btn-adicionar-usuario-main').focus(); // NOVO: Adiciona um ID ao botão principal
    });

});
