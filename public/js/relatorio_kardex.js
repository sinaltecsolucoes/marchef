// public/js/relatorio_kardex.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Inicializa o DataTable
    const table = $('#tabela-kardex').DataTable({
        "processing": true,
        "serverSide": true, // Importante para grandes volumes de dados
        "ajax": {
            "url": "ajax_router.php?action=relatorioKardex",
            "type": "POST",
            "data": function (d) {
                // Envia os filtros junto com a paginação
                d.csrf_token = csrfToken;
                d.filtro_lote = $('#filtro_lote').val();
                d.filtro_produto = $('#filtro_produto').val();
                d.data_inicio = $('#data_inicio').val();
                d.data_fim = $('#data_fim').val();
                d.filtro_tipo = $('#filtro_tipo').val();
            }
        },
        "order": [[0, "desc"]], // Ordena por data (mais recente primeiro)
        "columns": [
            {
                "data": "movimento_data",
                "className": "text-center align-middle",
                "render": function (data) {
                    // Formata: 17/12/2025 14:30
                    if (!data) return '-';
                    const d = new Date(data);
                    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                }
            },
            {
                "data": "movimento_tipo",
                "className": "text-center align-middle",
                "render": function (data) {
                    // Badges coloridos para facilitar leitura
                    let cor = 'secondary';
                    let icone = 'circle';
                    if (data === 'ENTRADA') { cor = 'success'; icone = 'arrow-down'; }
                    if (data === 'SAIDA') { cor = 'danger'; icone = 'arrow-up'; }
                    if (data === 'TRANSFERENCIA') { cor = 'warning text-dark'; icone = 'exchange-alt'; }

                    return `<span class="badge bg-${cor}"><i class="fas fa-${icone} me-1"></i>${data}</span>`;
                }
            },
            {
                "data": "produto_descricao",
                "className": "align-middle",
                "render": function (data, type, row) {
                    return `<strong>${data}</strong><br><small class="text-muted">Lote: ${row.lote_numero}</small>`;
                }
            },
            { "data": "origem_nome", "className": "text-center align-middle small" },
            { "data": "destino_nome", "className": "text-center align-middle small" },
            {
                "data": "movimento_quantidade",
                "className": "text-center align-middle fw-bold",
                "render": function (data) {
                    return parseFloat(data).toLocaleString('pt-BR', { minimumFractionDigits: 3 });
                }
            },
            { "data": "usuario_nome", "className": "text-center align-middle small" },
            { "data": "movimento_observacao", "className": "align-middle small text-muted" }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // Evento de Submit do Formulário
    $('#form-filtro-kardex').on('submit', function (e) {
        e.preventDefault();
        table.ajax.reload(); // Recarrega a tabela aplicando os filtros
    });

    // Evento Limpar
    $('#btn-limpar').on('click', function () {
        $('#form-filtro-kardex')[0].reset();
        // Reseta datas para o padrão (mês atual) se quiser, ou deixa em branco
        $('#data_inicio').val(new Date().toISOString().slice(0, 8) + '01');
        $('#data_fim').val(new Date().toISOString().slice(0, 10));
        table.ajax.reload();
    });
});