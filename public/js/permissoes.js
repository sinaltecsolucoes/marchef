// /public/js/permissoes.js
$(document).ready(function () {
    const formPermissoes = $('#form-gerenciar-permissoes');
    const mensagemDiv = $('#mensagem-permissoes');
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
                    mensagemDiv.removeClass('alert-danger').addClass('alert alert-success').text(response.message);
                } else {
                    mensagemDiv.removeClass('alert-success').addClass('alert alert-danger').text(response.message || 'Ocorreu um erro.');
                }
            })
            .fail(function () {
                mensagemDiv.removeClass('alert-success').addClass('alert alert-danger').text('Erro de comunicação com o servidor.');
            })
            .always(function () {
                submitButton.prop('disabled', false).text('Salvar Permissões');
            });
    });
});