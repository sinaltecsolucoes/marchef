// /public/js/camaras.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modal = $('#modal-camara');
    const $form = $('#form-camara');

    const table = $('#tabela-camaras').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarCamaras",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            {
                "data": "camara_codigo",
                "className": "text-center align-middle"
            },
            {
                "data": "camara_nome",
                "className": "align-middle"
            },
            {
                "data": "camara_descricao",
                "className": "align-middle"
            },
            {
                "data": "camara_industria",
                "className": "text-center align-middle"
            },
            {
                "data": "camara_id",
                "orderable": false,
                "className": "text-center align-middle",
                "render": function (data) {
                    return `
                        <button class="btn btn-warning btn-sm btn-editar-camara" data-id="${data}"><i class="fas fa-pencil-alt me-1"></i>Editar</button>
                        <button class="btn btn-danger btn-sm btn-excluir-camara" data-id="${data}"><i class="fas fa-trash-alt me-1"></i>Excluir</button>
                    `;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    $('#btn-adicionar-camara').on('click', function () {
        $form[0].reset();
        $('#camara_id').val('');
        $('#modal-camara-label').text('Adicionar Nova Câmara');
        $modal.modal('show');
    });

    $('#tabela-camaras').on('click', '.btn-editar-camara', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'ajax_router.php?action=getCamara',
            type: 'POST',
            data: { camara_id: id, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    $('#camara_id').val(data.camara_id);
                    $('#camara_codigo').val(data.camara_codigo);
                    $('#camara_nome').val(data.camara_nome);
                    $('#camara_descricao').val(data.camara_descricao);
                    $('#camara_industria').val(data.camara_industria);
                    $('#modal-camara-label').text('Editar Câmara');
                    $modal.modal('show');
                }
            }
        });
    });

    $('#tabela-camaras').on('click', '.btn-excluir-camara', function () {
        const id = $(this).data('id');
        confirmacaoAcao('Excluir Câmara?', 'Tem a certeza? Todos os endereços dentro desta câmara também serão excluídos!')
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=excluirCamara',
                        type: 'POST',
                        data: { camara_id: id, csrf_token: csrfToken },
                        dataType: 'json',
                        success: function (response) {
                            table.ajax.reload(null, false);
                            if (response.success) {
                                notificacaoSucesso('Excluída!', response.message);
                            } else {
                                notificacaoErro('Erro!', response.message);
                            }
                        }
                    });
                }
            });
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        $.ajax({
            url: 'ajax_router.php?action=salvarCamara',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modal.modal('hide');
                    table.ajax.reload(null, false);
                    notificacaoSucesso('Sucesso!', response.message);
                } else {
                    notificacaoErro('Erro ao Salvar', response.message);
                }
            }
        });
    });
});