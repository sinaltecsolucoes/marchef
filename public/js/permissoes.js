// /public/js/permissoes.js
$(document).ready(function () {
    const formPermissoes = $('#form-gerenciar-permissoes');
    const submitButton = formPermissoes.find('button[type="submit"]');

    formPermissoes.on('submit', function (e) {
        e.preventDefault();
        submitButton.prop('disabled', true).text('Salvando...');

        $.ajax({
            type: 'POST',
            url: 'ajax_router.php?action=salvarPermissoes',
            data: formPermissoes.serialize(),
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    notificacaoSucesso('Sucesso!', response.message);
                } else {
                    notificacaoErro('Erro ao Salvar', response.message || 'Ocorreu um erro.');
                }
            })
            .fail(function () {
                notificacaoErro('Erro de Comunicação', 'Não foi possível salvar as permissões.');
            })
            .always(function () {
                submitButton.prop('disabled', false).text('Salvar Permissões');
            });
    });
});


