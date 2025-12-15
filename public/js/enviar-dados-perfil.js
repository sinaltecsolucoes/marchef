$(document).ready(function () {
    const $modalPerfil = $('#perfil');
    const $formPerfil = $('#form-perfil');

    // Elementos do Form
    const $selectNome = $('#perfil_usu_nome_select');
    const $inputNomeHidden = $('#perfil_usu_nome');
    const $selectPerfil = $('#perfil_usu_tipo');
    const $inputLogin = $('#perfil_usu_login');

    // Novos Elementos do Switch
    const $switchSituacao = $('#perfil_usu_situacao'); // O Checkbox
    const $divSituacao = $('#div-situacao-perfil');    // O Container
    const $labelSituacao = $('#label-situacao');       // O Texto ao lado

    const $btnSalvar = $('#btn-salvar-perfil');
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Botão mostrar senha
    $('#btn-show-pass-perfil').on('click', function () {
        const input = $('#perfil_usu_senha');
        const icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Função para preencher os campos
    function preencherFormulario(user) {
        $('#perfil_usu_codigo').val(user.usu_codigo);
        $('#perfil_usu_login').val(user.usu_login);
        $('#perfil_usu_tipo').val(user.usu_tipo);

        // Lógica do Switch: Se "Ativo", marca o checkbox. Se não, desmarca.
        const isAtivo = (user.usu_situacao === 'A');
        $switchSituacao.prop('checked', isAtivo);

        // Atualiza o texto visualmente
        atualizarLabelSituacao(isAtivo);

        $selectNome.val(user.usu_codigo);
        $inputNomeHidden.val(user.usu_nome);
        $('#perfil_usu_senha').val('');
    }

    // Função auxiliar para mudar o texto (Ativo/Inativo) dinamicamente
    function atualizarLabelSituacao(isChecked) {
        if (isChecked) {
            $labelSituacao.text('Usuário Ativo').removeClass('text-danger').addClass('text-success fw-bold');
        } else {
            $labelSituacao.text('Usuário Inativo').removeClass('text-success fw-bold').addClass('text-danger');
        }
    }

    // Listener para quando o Admin clicar no switch (mudar o texto na hora)
    $switchSituacao.on('change', function () {
        atualizarLabelSituacao($(this).is(':checked'));
    });

    // Abrir Modal
    $modalPerfil.on('show.bs.modal', function () {
        // Redefine a seleção para garantir
        const $divSituacaoRef = $('#div-situacao-perfil');

        if (typeof LOGGED_IN_USER_ID === 'undefined' || !LOGGED_IN_USER_ID) {
            notificacaoErro('Erro', 'Sessão inválida. Recarregue a página.');
            return;
        }

        // Reset
        $formPerfil[0].reset();
        $selectNome.empty().prop('disabled', true);
        $selectPerfil.prop('disabled', true);

        // Estado Inicial: Oculto e Travado
        $divSituacaoRef.addClass('d-none');
        $switchSituacao.prop('disabled', true);

        // Busca dados
        $.ajax({
            url: 'ajax_router.php?action=getUsuario',
            type: 'POST',
            data: { usu_codigo: LOGGED_IN_USER_ID, csrf_token: csrfToken },
            dataType: 'json',
            success: function (res) {
                if (!res.success) {
                    notificacaoErro('Erro', res.message);
                    return;
                }

                const euMesmo = res.data;
                const tipoUsuario = (euMesmo.usu_tipo || '').toLowerCase();
                const souAdmin = (tipoUsuario === 'admin' || tipoUsuario === 'administrador');

                if (souAdmin) {
                    // MODO ADMIN
                    carregarListaUsuarios(euMesmo.usu_codigo);

                    $selectNome.prop('disabled', false);
                    $selectPerfil.prop('disabled', false);

                    // Exibe e Habilita o Switch
                    $divSituacaoRef.removeClass('d-none');
                    $switchSituacao.prop('disabled', false);

                } else {
                    // MODO COMUM
                    $selectNome.append(new Option(euMesmo.usu_nome, euMesmo.usu_codigo));
                    $selectNome.val(euMesmo.usu_codigo);
                    preencherFormulario(euMesmo);
                }
            },
            error: function (xhr) {
                console.error("Erro AJAX:", xhr);
                notificacaoErro('Erro', 'Falha ao comunicar com o servidor.');
            }
        });
    });

    function carregarListaUsuarios(idPreSelecionado) {
        $.ajax({
            url: 'ajax_router.php?action=getUsuariosOptions',
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $selectNome.empty();
                    res.data.forEach(function (u) {
                        let id = u.usu_codigo || u.id;
                        let text = u.usu_nome || u.text;
                        $selectNome.append(new Option(text, id));
                    });
                    $selectNome.val(idPreSelecionado).trigger('change');
                }
            }
        });
    }

    $selectNome.on('change', function () {
        const idSelecionado = $(this).val();
        const nomeSelecionado = $(this).find('option:selected').text();
        $inputNomeHidden.val(nomeSelecionado);

        if (!idSelecionado) return;

        $inputLogin.prop('disabled', true).val('Carregando...');

        $.ajax({
            url: 'ajax_router.php?action=getUsuario',
            type: 'POST',
            data: { usu_codigo: idSelecionado, csrf_token: csrfToken },
            dataType: 'json',
            success: function (res) {
                $inputLogin.prop('disabled', false);
                if (res.success) {
                    preencherFormulario(res.data);
                }
            }
        });
    });

    // SALVAR
    $btnSalvar.on('click', function () {
        if (!$inputLogin.val()) {
            notificacaoAlerta('Atenção', 'O login é obrigatório.');
            return;
        }

        let formData = $formPerfil.serializeArray();
        formData.push({ name: 'csrf_token', value: csrfToken });

        // Envia Perfil manualmente se estiver disabled
        if ($selectPerfil.is(':disabled')) {
            formData.push({ name: 'usu_tipo', value: $selectPerfil.val() });
        }

        // --- TRATAMENTO DO SWITCH ---
        // Se o switch estiver visível/habilitado (Admin), pegamos o estado dele.
        // Se estiver oculto/desabilitado (Comum), precisamos mandar o estado original?
        // Na verdade, o 'getUsuario' já preencheu o switch corretamente na carga (linha 47), 
        // mas campos disabled não são serializados.
        // Então, independentemente de estar habilitado ou não, nós lemos o estado visual do switch
        // e enviamos a string correspondente para o backend.

        const valorSituacao = $switchSituacao.is(':checked') ? 'A' : 'I';
        formData.push({ name: 'usu_situacao', value: valorSituacao });
        // ---------------------------

        const originalText = $btnSalvar.html();
        $btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        $.ajax({
            url: 'ajax_router.php?action=salvarUsuario',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    notificacaoSucesso('Sucesso', 'Dados atualizados com sucesso!');
                    $modalPerfil.modal('hide');
                    if ($('#perfil_usu_codigo').val() == LOGGED_IN_USER_ID) {
                        $('.navbar-nav .text-dark.fw-bold').text($inputNomeHidden.val());
                    }
                } else {
                    notificacaoErro('Erro', response.message);
                }
            },
            error: function () {
                notificacaoErro('Erro', 'Falha ao salvar dados.');
            },
            complete: function () {
                $btnSalvar.prop('disabled', false).html(originalText);
            }
        });
    });
});