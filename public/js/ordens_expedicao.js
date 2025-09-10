// /public/js/ordens_expedicao.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#tabela-ordens-expedicao').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarOrdensExpedicao",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            { "data": "oe_numero" },
            {
                "data": "oe_data",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            { "data": "oe_status" },
            { "data": "usuario_nome" },
            {
                "data": "oe_id",
                "orderable": false,
                "className": "text-center",
                "render": function (data, type, row) {
                    // Futuramente, os botões mudarão com base no status
                    return `<a href="index.php?page=ordem_expedicao_detalhes&id=${data}" class="btn btn-warning btn-sm">Editar</a>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[1, 'desc']]
    });
});