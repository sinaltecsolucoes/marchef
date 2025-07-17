$(document).ready(function () {
    const formPermissoes = $('#form-gerenciar-permissoes');
    const mensagemDiv = $('#mensagem-permissoes');
    const submitButton = formPermissoes.find('button[type="submit"]');

    formPermissoes.on('submit', function (e) {
        e.preventDefault(); // Impede o envio padrão do formulário

        // Desabilita o botão para prevenir múltiplos envios
        submitButton.prop('disabled', true).text('Salvando...');
        mensagemDiv.empty().removeClass('alert alert-success alert-danger');

        // Pega todos os dados do formulário
        let formData = formPermissoes.serialize();

        $.ajax({
            type: 'POST',
            url: 'salvar_permissoes.php', // Caminho para o seu script de backend
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    mensagemDiv.addClass('alert alert-success').text(response.message);
                } else {
                    mensagemDiv.addClass('alert alert-danger').text(response.message || 'Ocorreu um erro desconhecido.');
                }
            },
            error: function (xhr, status, error) {
                mensagemDiv.addClass('alert alert-danger').text('Erro na requisição: ' + error);
                console.error('Erro:', status, error, xhr.responseText);
            },
            complete: function() {
                // Habilita o botão novamente ao final da requisição
                submitButton.prop('disabled', false).text('Salvar Permissões');
            }
        });
    });
});
