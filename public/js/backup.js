// /public/js/backup.js

$(document).ready(function () {

    $('#btn-criar-backup').on('click', function () {
        const $button = $(this);
        const $statusDiv = $('#backup-status');

        // 1. Dar feedback ao utilizador e desabilitar o botão
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> A gerar, por favor aguarde...');
        $statusDiv.html('<div class="alert alert-info">A iniciar o processo de backup...</div>');

        // 2. Chamar a nossa API via AJAX
        $.ajax({
            url: 'ajax_router.php?action=criarBackup',
            type: 'POST',
            dataType: 'json',
            data: {
                csrf_token: $('meta[name="csrf-token"]').attr('content')
            }
        }).done(function (response) {
            if (response.success) {
                // 3. Se for bem-sucedido, forçar o download do ficheiro
                $statusDiv.html(`<div class="alert alert-success">Backup <strong>${response.filename}</strong> gerado com sucesso! A iniciar o download...</div>`);

                // Cria um link temporário e clica nele para iniciar o download
                const link = document.createElement('a');
                link.href = 'backups/' + response.filename;
                link.download = response.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

            } else {
                // 4. Se ocorrer um erro controlado, exibe a mensagem de erro
                $statusDiv.html(`<div class="alert alert-danger"><strong>Erro:</strong> ${response.message}</div>`);
            }
        }).fail(function () {
            // 5. Se ocorrer um erro de comunicação, exibe uma mensagem genérica
            $statusDiv.html('<div class="alert alert-danger"><strong>Erro:</strong> Falha de comunicação com o servidor. Verifique a consola para mais detalhes.</div>');
        }).always(function () {
            // 6. Independentemente do resultado, reativa o botão
            $button.prop('disabled', false).html('<i class="fas fa-database me-2"></i> Criar Backup Agora');
        });
    });

});