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
                // --- COLUNA 1: PESO (EMB.) ---
                "data": "peso_embalagem",
                "className": "text-center align-middle font-small",
                "render": function (data) {
                    let val = parseFloat(data);
                    
                    // Lógica: Se for inteiro (ex: 10), mostra sem decimais. 
                    // Se tiver decimal (ex: 18.144), mostra com 3 casas e vírgula.
                    if (Number.isInteger(val)) {
                         return val + 'kg';
                    }
                    
                    return val.toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + 'kg';
                }
            },
            {
                // --- COLUNA 2: TOTAL CAIXAS ---
                "data": "total_caixas",
                "className": "text-center align-middle fw-bold font-small",
                "render": function (data) {
                    let val = parseFloat(data);
                    
                    // Lógica: Transforma em Inteiro (sem casas decimais)
                    let formatado = parseInt(val).toLocaleString('pt-BR'); 

                    if (val < 0) {
                        return `<span class="text-danger">${formatado}</span>`;
                    }
                    return formatado;
                }
            },
            {
                // --- COLUNA 3: PESO TOTAL (KG) ---
                "data": "peso_total",
                "className": "text-center align-middle fw-bold font-small",
                "render": function (data) {
                    let val = parseFloat(data);
                    
                    // Lógica: Padrão PT-BR completo (milhar com ponto, decimal com vírgula, 3 casas)
                    // Ex: 1.680,000
                    let formatado = val.toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

                    if (val < 0) {
                        return `<span class="text-danger">${formatado}</span>`;
                    }
                    return formatado;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[4, 'asc'], [5, 'asc']]
    });

});