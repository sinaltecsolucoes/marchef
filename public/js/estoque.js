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
            { "data": "tipo_produto", "className": "text-center align-middle font-small" },
            { "data": "subtipo", "className": "text-center align-middle font-small" },
            { "data": "classificacao", "className": "text-center align-middle font-small" },
            { "data": "codigo_interno", "className": "text-center align-middle font-small" },
            { "data": "descricao_produto", "className": "align-middle font-small" },
            { "data": "lote", "className": "align-middle font-small" },
            {
                "data": "cliente_lote_nome",
                "class": "text-center align-middle font-small",
                "render": function (data) {
                    return data || '<span class="text-muted">N/A</span>';
                }
            },
            {
                "data": "data_fabricacao",
                "className": "text-center align-middle font-small",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "peso_embalagem",
                "className": "text-center align-middle font-small",
                "render": function (data) {
                    return parseFloat(data).toFixed(3);
                }
            },
            {
                "data": "total_caixas",
                "className": "text-center align-middle fw-bold font-small",
                "render": function (data) {
                    const valor = parseFloat(data);
                    const numeroFormatado = valor.toFixed(3);
                    if (valor < 0) {
                        // Se for negativo, adiciona a classe de cor vermelha
                        return `<span class="text-danger">${numeroFormatado}</span>`;
                    }
                    return numeroFormatado;
                }
            },
            {
                "data": "peso_total",
                "className": "text-center align-middle fw-bold font-small",
                "render": function (data) {
                    const valor = parseFloat(data);
                    const numeroFormatado = valor.toFixed(3);
                    if (valor < 0) {
                        // Se for negativo, adiciona a classe de cor vermelha
                        return `<span class="text-danger">${numeroFormatado}</span>`;
                    }
                    return numeroFormatado;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[4, 'asc'], [5, 'asc']]
    });

});