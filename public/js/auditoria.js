// /public/js/auditoria.js

$(document).ready(function () {

    // Função para carregar a lista de utilizadores no filtro <select>
    function carregarUsuariosFiltro() {
        $.ajax({
            url: 'ajax_router.php?action=getUsuariosOptions',
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const select = $('#filtro_usuario_id');
                // Limpa opções existentes, exceto a primeira ("Todos")
                select.find('option:not(:first)').remove();
                response.data.forEach(function (user) {
                    select.append(new Option(user.usu_nome, user.usu_codigo));
                });
            }
        });
    }

    // Chama a função para popular o filtro assim que a página carrega
    carregarUsuariosFiltro();

    // Inicialização da tabela DataTables
    const tabelaLogs = $('#tabelaLogs').DataTable({
        "serverSide": true, // Habilita o processamento de dados no lado do servidor
        "ajax": {
            "url": "ajax_router.php?action=listarLogs",
            "type": "POST",
            "data": function (d) {
                // Envia os valores dos nossos filtros personalizados junto com a requisição
                d.filtro_data_inicio = $('#filtro_data_inicio').val();
                d.filtro_data_fim = $('#filtro_data_fim').val();
                d.filtro_usuario_id = $('#filtro_usuario_id').val();
                d.csrf_token = $('meta[name="csrf-token"]').attr('content');
            }
        },
        "columns": [
            { "data": "timestamp_formatado" },
            { "data": "log_usuario_nome" },
            { "data": "log_acao" },
            { "data": "log_tabela_afetada" },
            { "data": "log_registro_id" },
            {
                "data": "log_id",
                "orderable": false,
                "render": function (data, type, row) {
                    // Se não houver dados antigos ou novos, o botão de detalhes não é necessário
                    if (row.log_tabela_afetada) { // Simplificando, só mostra se houver tabela
                        return `<button class="btn btn-info btn-sm btn-detalhes" data-id="${data}">Detalhes</button>`;
                    }
                    return '';
                }
            }
        ],
        "order": [[0, 'desc']], // Ordenar pela data/hora (mais recente primeiro)
        //"language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" }
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // Evento de clique para o botão FILTRAR
    $('#btn_filtrar').on('click', function () {
        tabelaLogs.ajax.reload(); // Recarrega a tabela com os novos parâmetros de filtro
    });

    // Evento de clique para o botão LIMPAR
    $('#btn_limpar').on('click', function () {
        // Limpa os campos do formulário
        $('#filtro_data_inicio').val('');
        $('#filtro_data_fim').val('');
        $('#filtro_usuario_id').val('');

        // Recarrega a tabela para mostrar todos os resultados novamente
        tabelaLogs.ajax.reload();
    });

    // Evento de clique para o botão DETALHES (usando delegação de eventos)
    $('#tabelaLogs tbody').on('click', '.btn-detalhes', function () {
        const logId = $(this).data('id');

        $.ajax({
            url: 'ajax_router.php?action=getLogDetalhes',
            type: 'POST',
            data: {
                log_id: logId,
                csrf_token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const detalhes = response.data;

                // Formata o JSON para uma exibição legível e insere nos <pre>
                // O 'null, 2' no stringify formata o JSON com indentação de 2 espaços
                const dadosAntigos = detalhes.log_dados_antigos ? JSON.stringify(detalhes.log_dados_antigos, null, 2) : 'N/A';
                const dadosNovos = detalhes.log_dados_novos ? JSON.stringify(detalhes.log_dados_novos, null, 2) : 'N/A';

                $('#dados_antigos_content').text(dadosAntigos);
                $('#dados_novos_content').text(dadosNovos);

                // Abre o modal
                $('#modalDetalhesLog').modal('show');
            } else {
                notificacaoErro('Erro!', 'Não foi possível buscar os detalhes do log.');
            }
        }).fail(function () {
            // Adicionado tratamento de erro para falha de comunicação
            notificacaoErro('Erro de Comunicação', 'Não foi possível conectar ao servidor.');
        });
    });
});