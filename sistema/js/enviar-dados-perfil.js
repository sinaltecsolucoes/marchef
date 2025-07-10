$(function () { // Forma abreviada de $(document).ready(function() { ... });
    // Seleciona o formulário pelo ID
    var $formPerfil = $("#form-perfil");
    var $mensagemPerfil = $('#mensagem-perfil'); // Cache do seletor
    var $btnFecharPerfil = $('#btn-fechar-perfil'); // Cache do seletor

    $formPerfil.submit(function (e) { // Passa 'e' (evento) como argumento
        e.preventDefault(); // Previne o comportamento padrão do formulário

        // Opcional: Adicionar um feedback visual de carregamento
        $mensagemPerfil.removeClass().text('Enviando dados...'); // Limpa e mostra "Enviando..."
        // Ou adicionar um spinner, desabilitar o botão de submit, etc.
        $('button[type="submit"]', $formPerfil).prop('disabled', true).text('Salvando...');


        var formData = new FormData(this); // 'this' refere-se ao formulário

        $.ajax({
            url: "editar-perfil.php",
            type: 'POST',
            data: formData,
            dataType: 'json', // Adicionado: Espera uma resposta JSON do servidor
            processData: false, // Necessário para FormData
            contentType: false, // Necessário para FormData

            success: function (response) { // 'response' agora é um objeto JSON
                // Remove o feedback de carregamento
                $('button[type="submit"]', $formPerfil).prop('disabled', false).text('Salvar Alterações');

                // Limpa as classes de status anteriores (mantém outras classes se houver)
                $mensagemPerfil.removeClass('text-success text-danger');

                if (response.success) { // Se a propriedade 'success' do JSON for true
                    $mensagemPerfil.addClass('text-success').text(response.message);
                    
                    // Fechar o modal após um pequeno atraso para o usuário ver a mensagem de sucesso
                    setTimeout(function() {
                        $btnFecharPerfil.click();
                        // Opcional: Recarregar a página para atualizar os dados visíveis, se necessário
                        // window.location.reload(); 
                        // ou atualizar elementos específicos do DOM na página principal
                    }, 1500); // Fecha após 1.5 segundos
                } else {
                    $mensagemPerfil.addClass('text-danger').text(response.message);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                // Remove o feedback de carregamento
                $('button[type="submit"]', $formPerfil).prop('disabled', false).text('Salvar Alterações');

                // Lida com erros de comunicação AJAX
                $mensagemPerfil.removeClass().addClass('text-danger').text('Erro ao comunicar com o servidor: ' + textStatus);
                console.error("Erro AJAX: ", textStatus, errorThrown, jqXHR.responseText);
            }
        });
    });
});