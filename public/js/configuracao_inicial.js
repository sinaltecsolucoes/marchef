// /public/js/configuracao_inicial.js
$(document).ready(function() {

    // --- ETAPA 2: Botão para Criar as Tabelas ---
    $('#btn-create-tables').on('click', function() {
        const $button = $(this);
        const $feedbackArea = $('#feedback-step-2');

        // Feedback visual
        $button.prop('disabled', true).text('A criar tabelas...');
        $feedbackArea.html('');

        $.ajax({
            url: 'installer_ajax.php?action=criar_tabelas',
            type: 'POST',
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                // Esconde as etapas anteriores e mostra a etapa 3
                $('#step-1').hide();
                $('#step-2').hide();
                $('#step-3').fadeIn();
            } else {
                $feedbackArea.html(`<div class="alert alert-danger">${response.message}</div>`);
                $button.prop('disabled', false).text('Tentar Novamente');
            }
        }).fail(function() {
            $feedbackArea.html('<div class="alert alert-danger">Erro de comunicação com o servidor.</div>');
            $button.prop('disabled', false).text('Tentar Novamente');
        });
    });


    // --- ETAPA 3: Formulário de Configuração Final ---
    $('#form-final-setup').on('submit', function(e) {
        e.preventDefault(); // Impede o envio normal do formulário

        const $button = $('#btn-final-setup');
        const $feedbackArea = $('#feedback-step-3');
        const formData = new FormData(this);

        // Feedback visual
        $button.prop('disabled', true).text('A finalizar...');
        $feedbackArea.html('');

        $.ajax({
            url: 'installer_ajax.php?action=finalizar_instalacao',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                // Esconde todas as etapas e mostra a tela final de sucesso
                $('#step-1').hide();
                $('#step-2').hide();
                $('#step-3').hide();
                $('#step-final').fadeIn();
            } else {
                $feedbackArea.html(`<div class="alert alert-danger">${response.message}</div>`);
                $button.prop('disabled', false).text('Finalizar Instalação');
            }
        }).fail(function() {
            $feedbackArea.html('<div class="alert alert-danger">Erro de comunicação com o servidor.</div>');
            $button.prop('disabled', false).text('Finalizar Instalação');
        });
    });

});