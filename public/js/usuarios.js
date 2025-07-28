$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalUsuario = $('#modal-usuario');
    const $formUsuario = $('#form-usuario');

    const tableUsuarios = $('#tabela-usuarios').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": "ajax_router.php?action=listarUsuarios",
            "type": "POST",
            "data": { "csrf_token": csrfToken }
        },
        "responsive": true,
        "columns": [
            { "data": "usu_situacao", "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' },
            { "data": "usu_nome" },
            { "data": "usu_login" },
            { "data": "usu_tipo" },
            {
                "data": "usu_codigo", "orderable": false, "render": (data, type, row) =>
                    `<a href="#" class="btn btn-warning btn-sm btn-editar-usuario me-1" data-id="${data}">Editar</a>` +
                    `<a href="#" class="btn btn-danger btn-sm btn-excluir-usuario" data-id="${data}" data-nome="${row.usu_nome}">Excluir</a>`
            }
        ],
        "language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // Abrir modal para Adicionar
    $('#btn-adicionar-usuario').on('click', function () {
        $formUsuario[0].reset();
        $('#usu-codigo').val('');
        $('#usu-senha').prop('required', true);
        $('#modal-usuario-label').text('Adicionar Usuário');
        $('#usu-situacao').prop('checked', true);
        $modalUsuario.modal('show');
    });

    // Abrir modal para Editar
    $('#tabela-usuarios').on('click', '.btn-editar-usuario', function () {
        const id = $(this).data('id');
        $.post('ajax_router.php?action=getUsuario', { usu_codigo: id, csrf_token: csrfToken }, function (response) {
            if (response.success) {
                const data = response.data;
                $formUsuario[0].reset();
                $('#usu-codigo').val(data.usu_codigo);
                $('#usu-nome').val(data.usu_nome);
                $('#usu-login').val(data.usu_login);
                $('#usu-tipo').val(data.usu_tipo);
                $('#usu-situacao').prop('checked', data.usu_situacao === 'A');
                $('#usu-senha').prop('required', false);
                $('#modal-usuario-label').text('Editar Usuário');
                $modalUsuario.modal('show');
            }
        }, 'json');
    });

    // Salvar (Criar ou Editar)
    $formUsuario.on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax_router.php?action=salvarUsuario',
            type: 'POST',
            data: new FormData(this),
            processData: false, contentType: false, dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalUsuario.modal('hide');

                    tableUsuarios.ajax.reload();
                } else {
                    $('#mensagem-usuario').addClass('alert alert-danger').text(response.message);
                }
            }
        });
    });

    // Excluir
    $('#tabela-usuarios').on('click', '.btn-excluir-usuario', function () {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        if (confirm(`Tem certeza que deseja excluir o usuário "${nome}"?`)) {
            $.post('ajax_router.php?action=excluirUsuario', { usu_codigo: id, csrf_token: csrfToken }, function (response) {
                if (response.success) {
                    tableUsuarios.ajax.reload();
                }
                alert(response.message);
            }, 'json');
        }
    });
});