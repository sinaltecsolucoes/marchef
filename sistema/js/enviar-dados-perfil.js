$(function () {
    var $formPerfil = $("#form-perfil");
    var $mensagemPerfil = $('#mensagem-perfil');
    var $btnFecharPerfil = $('#btn-fechar-perfil');
    var $selecionarUsuarioPerfil = $('#selecionar-usuario-perfil');
    var $comboboxContainer = $selecionarUsuarioPerfil.closest('.mb-3');
    var $nomePerfil = $('#nome-perfil');
    var $loginPerfil = $('#login-perfil');
    var $senhaPerfil = $('#senha-perfil');
    var $situacaoPerfil = $('#situacao-perfil');
    var $textoSituacaoPerfil = $('#texto-situacao-perfil');
    var $nivelPerfil = $('#nivel-perfil');

    // Função para carregar os usuários no combobox (para admins)
    function loadUsersIntoCombobox() {
        $.ajax({
            url: "process/listar_todos_usuarios.php",
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    $selecionarUsuarioPerfil.empty();
                    $selecionarUsuarioPerfil.append('<option value="">Selecione um usuário...</option>');
                    $.each(response.data, function (index, user) {
                        $selecionarUsuarioPerfil.append('<option value="' + user.usu_codigo + '">' + user.usu_nome + ' (' + user.usu_login + ')</option>');
                    });
                } else {
                    $selecionarUsuarioPerfil.empty().append('<option value="">Nenhum usuário encontrado.</option>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Erro AJAX ao carregar usuários: ", textStatus, errorThrown);
            }
        });
    }

    // Função para carregar os dados do usuário selecionado nos campos do formulário
    window.loadUserData = function (userId) {
        if (!userId) {
            $formPerfil[0].reset();
            $textoSituacaoPerfil.text('Inativo');
            return;
        }

        $.ajax({
            url: "process/get_user_data.php",
            type: 'GET',
            data: { id: userId },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    var userData = response.data;
                    $nomePerfil.val(userData.usu_nome);
                    $loginPerfil.val(userData.usu_login);
                    $senhaPerfil.val(''); // Nunca preenche a senha por segurança
                    
                    // Apenas preenche os campos de admin se eles existirem no DOM
                    if ($situacaoPerfil.length > 0) {
                        $situacaoPerfil.prop('checked', userData.usu_situacao === 'A');
                        $textoSituacaoPerfil.text(userData.usu_situacao === 'A' ? 'Ativo' : 'Inativo');
                    }
                    if ($nivelPerfil.length > 0) {
                        $nivelPerfil.val(userData.usu_tipo);
                    }
                } else {
                    $mensagemPerfil.removeClass().addClass('alert alert-danger').text(response.message || 'Erro ao carregar dados do usuário.');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $mensagemPerfil.removeClass().addClass('alert alert-danger').text('Erro ao comunicar com o servidor.');
            }
        });
    }

    // Evento que dispara QUANDO O MODAL DE PERFIL ESTÁ SENDO ABERTO
    $('#perfil').on('show.bs.modal', function (event) {
        $('#mensagem-perfil').empty().removeClass('alert alert-success alert-danger');
        var triggerElement = $(event.relatedTarget);

        // Se foi aberto pelo botão "Editar" da lista de usuários
        if (triggerElement.hasClass('btn-editar-usuario')) {
            var userId = triggerElement.data('id');
            // Apenas admins podem ver a lista de seleção
            if (typeof PODE_EDITAR_OUTROS_USUARIOS !== 'undefined' && PODE_EDITAR_OUTROS_USUARIOS) {
                loadUsersIntoCombobox();
                // Pequeno delay para garantir que o combobox foi populado antes de selecionarmos o valor
                setTimeout(function() { 
                    $selecionarUsuarioPerfil.val(userId).trigger('change');
                }, 200);
            }
        } 
        // Se foi aberto pelo menu superior "Editar Perfil"
        else {
            if (typeof PODE_EDITAR_OUTROS_USUARIOS !== 'undefined' && PODE_EDITAR_OUTROS_USUARIOS) {
                loadUsersIntoCombobox();
                loadUserData(''); // Limpa o formulário para seleção
            } else {
                // Se for um usuário comum, usa a variável global que criamos no index.php
                if (typeof LOGGED_IN_USER_ID !== 'undefined') {
                    loadUserData(LOGGED_IN_USER_ID);
                }
            }
        }
    });

    // Evento de fechamento do modal de perfil
    $('#perfil').on('hidden.bs.modal', function () {
        $('#hidden-edit-usu-codigo').remove(); // Limpa qualquer campo oculto antigo
        if ($comboboxContainer.length > 0) {
            $comboboxContainer.show();
        }
        $selecionarUsuarioPerfil.val('');
        loadUserData(''); // Garante que o formulário esteja limpo para a próxima abertura
    });

    // Evento de mudança no combobox de seleção de usuário
    $selecionarUsuarioPerfil.on('change', function () {
        var selectedUserId = $(this).val();
        loadUserData(selectedUserId);
    });

    // =================================================================
    // >> LÓGICA DE SUBMISSÃO CORRIGIDA <<
    // =================================================================
    $formPerfil.submit(function (e) {
        e.preventDefault();

        var selectedUserId;

        // Se o dropdown de seleção existe (admin), pega o valor dele.
        if ($('#selecionar-usuario-perfil').length > 0) {
            selectedUserId = $('#selecionar-usuario-perfil').val();
        } 
        // Se não (usuário comum), pega o ID da variável global.
        else {
            selectedUserId = (typeof LOGGED_IN_USER_ID !== 'undefined') ? LOGGED_IN_USER_ID : null;
        }
        
        if (!selectedUserId) {
            $mensagemPerfil.removeClass().addClass('alert alert-danger').text('Erro: ID do usuário não foi identificado. Não foi possível salvar.');
            return;
        }

        var $submitButton = $('button[type="submit"]', $formPerfil);
        $submitButton.prop('disabled', true).text('Salvando...');
        $mensagemPerfil.empty().removeClass();

        var formData = new FormData(this);
        // Garante que o ID do usuário seja enviado corretamente
        formData.set('usu_codigo_selecionado', selectedUserId);

        $.ajax({
            url: "process/editar-perfil.php",
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    $mensagemPerfil.addClass('alert alert-success').text(response.message);
                    setTimeout(function () {
                        $btnFecharPerfil.click();
                        // Recarrega a tabela de usuários se ela estiver visível na página
                        if ($.fn.DataTable.isDataTable('#example')) {
                            $('#example').DataTable().ajax.reload(null, false);
                        }
                    }, 1500);
                } else {
                    $mensagemPerfil.addClass('alert alert-danger').text(response.message);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $mensagemPerfil.addClass('alert alert-danger').text('Erro ao comunicar com o servidor: ' + textStatus);
            },
            complete: function() {
                $submitButton.prop('disabled', false).text('Salvar Alterações');
            }
        });
    });
});
