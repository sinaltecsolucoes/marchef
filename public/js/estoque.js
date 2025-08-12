// /public/js/estoque.js (Versão com Pesquisa Global Padrão)
$(document).ready(function () {

    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#tabela-estoque').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": "ajax_router.php?action=listarEstoque",
            "type": "POST",
            "data": {
                csrf_token: csrfToken
            }
        },
        "columns": [
            { "data": "tipo_produto" },
            { "data": "subtipo" },
            { "data": "classificacao" },
            { "data": "codigo_interno" },
            { "data": "descricao_produto" },
            { "data": "lote" },
            {
                "data": "cliente_lote_nome",
                "render": function (data) {
                    return data || '<span class="text-muted">N/A</span>';
                }
            },
            {
                "data": "data_fabricacao",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "peso_embalagem",
                "className": "text-end",
                "render": function (data) {
                    return parseFloat(data).toFixed(3);
                }
            },
            {
                "data": "total_caixas",
                "className": "text-end fw-bold",
                "render": function (data) {
                    return parseFloat(data).toFixed(3);
                }
            },
            {
                "data": "peso_total",
                "className": "text-end fw-bold",
                "render": function (data) {
                    return parseFloat(data).toFixed(3);
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[4, 'asc'], [5, 'asc']]
    });

});