// /public/js/app_config.js 

// ### FUNÇÃO PARA FORMATAR NÚMEROS ###
function formatarNumeroBrasileiro(numero) {
    const num = parseFloat(numero);
    if (isNaN(num)) {
        return "0,000";
    }
    // Se o número for inteiro, retorna sem casas decimais
    if (num % 1 === 0) {
        return num.toString();
    }
    // Se tiver decimais, formata para 3 casas e troca ponto por vírgula
    return num.toFixed(3).replace('.', ',');
}

$(document).ready(function () {
    /**
      * Tratador de Erros Global para chamadas AJAX.
      */
    $(document).ajaxError(function (event, jqXHR, ajaxSettings, thrownError) {

        // Condição para IGNORAR erros de tradução do DataTables, que não são críticos.
        if (ajaxSettings.url.includes('Portuguese-Brasil.json')) {
            console.warn("Falha ao carregar arquivo de tradução do DataTables. A tabela usará o idioma padrão (inglês).");
            return; // Interrompe a execução e não mostra o alerta para o usuário.
        }

        console.error("Erro AJAX Global Detectado:");
        console.error("URL:", ajaxSettings.url);
        console.error("Status:", jqXHR.status);
        console.error("Erro:", thrownError);

        let titulo = 'Erro de Comunicação';
        let mensagem = 'Não foi possível comunicar com o servidor. Verifique a sua conexão com a internet.';

        if (jqXHR.status === 404) {
            titulo = 'Recurso Não Encontrado';
            mensagem = 'A funcionalidade que você tentou aceder não foi encontrada no servidor.';
        } else if (jqXHR.status === 500) {
            titulo = 'Erro Interno no Servidor';
            mensagem = 'Ocorreu um problema inesperado no servidor. Contacte a equipe técnica';
        }

        notificacaoErro(titulo, mensagem);
    });
});