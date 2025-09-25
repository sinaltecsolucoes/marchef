// /public/js/ordens_expedicao.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    const dataTable = $('#tabela-ordens-expedicao').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarOrdensExpedicao",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            {
                "data": "oe_numero",
                "className": "text-center align-middle"

            },
            {
                "data": "oe_data",
                "className": "text-center align-middle",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            { "data": "oe_status" },
            {
                "data": "carregamento_numero",
                "className": "text-center align-middle",
                "render": function (data, type, row) {
                    if (!data) return 'N/A';
                    // Cria o link para o futuro relatório
                    return `<a href="index.php?page=carregamento_relatorio&id=${row.oe_carregamento_id}" 
                           class="fw-bold" title="Ver relatório do carregamento">${data}</a>`;
                }
            },
            {
                "data": "usuario_nome",
                "className": "text-center align-middle"
            },
            {
                "data": "oe_id",
                "orderable": false,
                "className": "text-center align-middle",
                "render": function (data, type, row) {
                    // ### INÍCIO DA NOVA LÓGICA DE BOTÕES ###
                    if (row.oe_status === 'GEROU CARREGAMENTO') {
                        // Se já gerou carregamento, mostra apenas o botão "Visualizar"
                        return `<a href="index.php?page=ordem_expedicao_detalhes&id=${data}" 
                               class="btn btn-info btn-sm" 
                               title="Visualizar Ordem de Expedição (bloqueada)">
                               <i class="fas fa-eye"></i> Visualizar
                            </a>`;
                    } else {
                        // Senão, mostra os botões "Editar" e "Excluir"
                        let btnEditar = `<a href="index.php?page=ordem_expedicao_detalhes&id=${data}" class="btn btn-warning btn-sm me-1">Editar</a>`;
                        let btnExcluir = `<button class="btn btn-danger btn-sm btn-excluir-oe" data-id="${data}">Excluir</button>`;
                        return `<div class="btn-group">${btnEditar}${btnExcluir}</div>`;
                    }
                    // ### FIM DA NOVA LÓGICA DE BOTÕES ###
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[1, 'desc']]
    });

    $('#tabela-ordens-expedicao').on('click', '.btn-excluir-oe', function () {
        const oeId = $(this).data('id');

        Swal.fire({
            title: 'Tem certeza?',
            text: "Esta ação excluirá permanentemente a Ordem de Expedição e todos os seus itens. Esta ação não pode ser desfeita!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=excluirOrdemExpedicao', {
                    oe_id: oeId,
                    csrf_token: csrfToken
                }, function (response) {
                    if (response.success) {
                        Swal.fire('Excluído!', response.message, 'success');
                        dataTable.ajax.reload(); // Recarrega a tabela
                    } else {
                        Swal.fire('Erro!', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });
});
