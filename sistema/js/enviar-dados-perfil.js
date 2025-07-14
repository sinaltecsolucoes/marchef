$(function () {
    var $formPerfil = $("#form-perfil");
    var $mensagemPerfil = $('#mensagem-perfil');
    var $btnFecharPerfil = $('#btn-fechar-perfil');
    var $selecionarUsuarioPerfil = $('#selecionar-usuario-perfil'); // Seletor para o combobox
    var $comboboxContainer = $selecionarUsuarioPerfil.closest('.mb-3'); // Container do combobox e label
    var $nomePerfil = $('#nome-perfil');
    var $loginPerfil = $('#login-perfil');
    var $senhaPerfil = $('#senha-perfil');
    var $situacaoPerfil = $('#situacao-perfil');
    var $textoSituacaoPerfil = $('#texto-situacao-perfil');
    var $nivelPerfil = $('#nivel-perfil');

    // Função para carregar os usuários no combobox
    function loadUsersIntoCombobox() {
        $.ajax({
            url: "process/listar_todos_usuarios.php", // Caminho atualizado
            type: 'GET', // Usamos GET para buscar dados
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    $selecionarUsuarioPerfil.empty(); // Limpa as opções existentes
                    $selecionarUsuarioPerfil.append('<option value="">Selecione um usuário...</option>');
                    $.each(response.data, function (index, user) {
                        $selecionarUsuarioPerfil.append('<option value="' + user.usu_codigo + '">' + user.usu_nome + ' (' + user.usu_login + ')</option>');
                    });
                } else {
                    $selecionarUsuarioPerfil.empty().append('<option value="">Nenhum usuário encontrado.</option>');
                    console.error("Erro ao carregar usuários: " + response.message);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Erro AJAX ao carregar usuários: ", textStatus, errorThrown, jqXHR.responseText);
                $selecionarUsuarioPerfil.empty().append('<option value="">Erro ao carregar usuários.</option>');
            }
        });
    }

    // Função para carregar os dados do usuário selecionado nos campos do formulário
    // Tornada global para ser acessível de usuarios.js
    window.loadUserData = function (userId) {
        if (!userId) {
            // Limpa os campos se nenhum usuário for selecionado ou ID inválido
            $nomePerfil.val('');
            $loginPerfil.val('');
            $senhaPerfil.val('');
            $situacaoPerfil.prop('checked', false);
            $textoSituacaoPerfil.text('Inativo');
            $nivelPerfil.val('');
            return;
        }

        $.ajax({
            url: "process/get_user_data.php", // CORREÇÃO AQUI: Caminho atualizado
            type: 'GET',
            data: { id: userId },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    var userData = response.data;
                    $nomePerfil.val(userData.usu_nome);
                    $loginPerfil.val(userData.usu_login);
                    $senhaPerfil.val(''); // Nunca preenche a senha por segurança
                    $situacaoPerfil.prop('checked', userData.usu_situacao === 'A');
                    $textoSituacaoPerfil.text(userData.usu_situacao === 'A' ? 'Ativo' : 'Inativo');
                    $nivelPerfil.val(userData.usu_tipo);
                } else {
                    $mensagemPerfil.removeClass().addClass('text-danger').text(response.message || 'Erro ao carregar dados do usuário.');
                    console.error("Erro ao carregar dados do usuário: " + response.message);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $mensagemPerfil.removeClass().addClass('text-danger').text('Erro ao comunicar com o servidor para carregar dados do usuário.');
                console.error("Erro AJAX ao carregar dados do usuário: ", textStatus, errorThrown, jqXHR.responseText);
            }
        });
    }

    // Evento de abertura do modal
    $('#perfil').on('show.bs.modal', function () {
        $mensagemPerfil.empty().removeClass('text-success text-danger'); // Sempre limpa mensagens anteriores

        // Verifica se o modal foi aberto pelo botão 'Editar' da tabela
        // (que adiciona um campo oculto com o ID do usuário)
        if ($('#hidden-edit-usu-codigo').length > 0) {
            // Se sim, esconde o combobox (o preenchimento já foi feito por usuarios.js)
            $comboboxContainer.hide();
            // loadUserData($('#hidden-edit-usu-codigo').val()); // Já é chamado por usuarios.js
        } else {
            // Se não, foi aberto pelo menu superior.
            // Garante que o combobox esteja visível e o preenche.
            $comboboxContainer.show();
            loadUsersIntoCombobox(); // Carrega os usuários no combobox
            loadUserData(''); // Limpa os campos para nova seleção
        }
    });

    // Evento de fechamento do modal de perfil (para reexibir o combobox)
    $('#perfil').on('hidden.bs.modal', function () {
        // Remove o campo oculto do ID do usuário editado (se existir)
        $('#hidden-edit-usu-codigo').remove();
        // Reexibe o combobox de seleção de usuário e sua label
        $comboboxContainer.show();
        // Limpa a seleção do combobox e os campos do formulário
        $selecionarUsuarioPerfil.val('');
        loadUserData(''); // Garante que o formulário esteja limpo para a próxima abertura
    });

    // Evento de mudança no combobox de seleção de usuário
    $selecionarUsuarioPerfil.on('change', function () {
        var selectedUserId = $(this).val();
        loadUserData(selectedUserId); // Carrega os dados do usuário selecionado
    });

    // Lida com o envio do formulário de perfil
    $formPerfil.submit(function (e) {
        e.preventDefault();

        // Pega o ID do usuário a ser editado.
        // Se o modal foi aberto pelo botão 'Editar' da tabela, o ID estará no campo oculto.
        // Caso contrário (aberto pelo menu superior), o ID virá do combobox.
        var selectedUserId = $('#hidden-edit-usu-codigo').val() || $selecionarUsuarioPerfil.val();

        // --- DEBUG LOGS ---
        console.log("Submit clicked. selectedUserId:", selectedUserId);
        console.log("Type of selectedUserId:", typeof selectedUserId);
        // --- FIM DEBUG LOGS ---

        if (!selectedUserId) {
            $mensagemPerfil.removeClass().addClass('text-danger').text('Por favor, selecione um usuário para editar.');
            return;
        }

        $mensagemPerfil.removeClass().text('Enviando dados...');
        $('button[type="submit"]', $formPerfil).prop('disabled', true).text('Salvando...');

        var formData = new FormData(this);
        // Garante que o ID do usuário a ser editado seja enviado corretamente
        formData.set('usu_codigo_selecionado', selectedUserId);

        $.ajax({
            url: "process/editar-perfil.php", // CORREÇÃO AQUI: Caminho atualizado
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,

            success: function (response) {
                $('button[type="submit"]', $formPerfil).prop('disabled', false).text('Salvar Alterações');
                $mensagemPerfil.removeClass('text-success text-danger');

                // --- DEBUG LOG ---
                console.log("Server response on success/failure:", response);
                // --- FIM DEBUG LOG ---

                if (response.success) {
                    $mensagemPerfil.addClass('text-success').text(response.message);
                    setTimeout(function() {
                        $btnFecharPerfil.click();
                        // Recarrega a tabela de usuários se ela estiver visível
                        if ($.fn.DataTable.isDataTable('#example')) {
                            $('#example').DataTable().ajax.reload();
                        }
                    }, 1500);
                } else {
                    $mensagemPerfil.addClass('text-danger').text(response.message);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $('button[type="submit"]', $formPerfil).prop('disabled', false).text('Salvar Alterações');
                $mensagemPerfil.removeClass().addClass('text-danger').text('Erro ao comunicar com o servidor: ' + textStatus);
                console.error("Erro AJAX: ", textStatus, errorThrown, jqXHR.responseText);
            }
        });
    });
});
