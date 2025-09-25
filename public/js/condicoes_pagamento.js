// /public/js/condicoes_pagamento.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modal = $('#modal-condicao');
    const $form = $('#form-condicao');

    const table = $('#tabela-condicoes').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarCondicoesPagamento",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            {
                "data": "cond_ativo",
                "className": "text-center align-middle",
                "render": data => (data == 1) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'
            },
            {
                "data": "cond_codigo",
                "className": "text-center align-middle",

            },
            {
                "data": "cond_descricao",
                "className": "text-center align-middle",

            },
            {
                "data": "cond_dias_parcelas",
                "className": "text-center align-middle",

            },
            {
                "data": "cond_id",
                "orderable": false,
                "className": "text-center align-middle",
                "render": function (data) {
                    return `
                        <button class="btn btn-warning btn-sm btn-editar" data-id="${data}">Editar</button>
                        <button class="btn btn-danger btn-sm btn-excluir" data-id="${data}">Excluir</button>
                    `;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    $('#btn-adicionar-condicao').on('click', function () {
        $form[0].reset();
        $('#cond_id').val('');
        $('#modal-condicao-label').text('Adicionar Nova Condição');
        $('#cond_ativo').prop('checked', true);
        $modal.modal('show');
    });

    $('#tabela-condicoes').on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        $.post('ajax_router.php?action=getCondicaoPagamento', { cond_id: id, csrf_token: csrfToken }, function (response) {
            if (response.success) {
                const data = response.data;
                $('#cond_id').val(data.cond_id);
                $('#cond_codigo').val(data.cond_codigo);
                $('#cond_descricao').val(data.cond_descricao);
                $('#cond_dias_parcelas').val(data.cond_dias_parcelas);
                $('#cond_ativo').prop('checked', data.cond_ativo == 1);
                $('#modal-condicao-label').text('Editar Condição');
                $modal.modal('show');
            }
        }, 'json');
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        $.post('ajax_router.php?action=salvarCondicaoPagamento', $(this).serialize(), function (response) {
            if (response.success) {
                $modal.modal('hide');
                table.ajax.reload(null, false);
                notificacaoSucesso('Sucesso!', response.message);
            } else {
                notificacaoErro('Erro', response.message);
            }
        }, 'json');
    });

    $('#tabela-condicoes').on('click', '.btn-excluir', function () {
        const id = $(this).data('id');
        confirmacaoAcao('Excluir Condição?', 'Tem certeza? Esta ação não pode ser desfeita.')
            .then((result) => {
                if (result.isConfirmed) {
                    $.post('ajax_router.php?action=excluirCondicaoPagamento', { cond_id: id, csrf_token: csrfToken }, function (response) {
                        if (response.success) {
                            notificacaoSucesso('Excluído!', response.message);
                            table.ajax.reload(null, false);
                        } else {
                            notificacaoErro('Erro ao excluir', response.message);
                        }
                    }, 'json');
                }
            });
    });
});