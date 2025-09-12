$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#tabela-faturamentos').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarFaturamentos",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            { "data": "fat_id" },
            { "data": "ordem_numero" },
            {
                "data": "fat_data_geracao",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data);
                    return date.toLocaleString('pt-BR');
                }
            },
            { "data": "fat_status" },
            { "data": "usuario_nome" },
            {
                "data": "fat_id",
                "orderable": false,
                "className": "text-center",
                "render": function (data) {
                    // Futuramente, o link de edição levará para a tela de detalhes
                    return `<a href="#" class="btn btn-warning btn-sm">Detalhes</a>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[0, 'desc']]
    });
});