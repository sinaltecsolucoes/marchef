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
            {
                "data": "usu_situacao", "className": "text-center align-middle",
                "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'
            },
            {
                "data": "usu_nome", "className": "text-center align-middle",
            },
            {
                "data": "usu_login", "className": "text-center align-middle",
            },
            {
                "data": "usu_tipo", "className": "text-center align-middle",
            },
            {
                "data": "usu_codigo", "orderable": false, 
                "className": "text-center align-middle",
                "render": (data, type, row) =>
                    `<a href="#" class="btn btn-warning btn-sm btn-editar-usuario me-1" data-id="${data}">Editar</a>` +
                    `<a href="#" class="btn btn-danger btn-sm btn-excluir-usuario" data-id="${data}" data-nome="${row.usu_nome}">Excluir</a>`
            }
        ],
        //"language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" }
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
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
            } else {
                notificacaoErro('Erro ao Carregar', response.message || 'Não foi possível carregar os dados do usuário.');
            }
        }, 'json')
            .fail(function () {
                notificacaoErro('Erro de Comunicação', 'Falha ao buscar dados do usuário.');
            });
    });

    // Salvar (Criar ou Editar)
    $formUsuario.on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax_router.php?action=salvarUsuario',
            type: 'POST',
            data: new FormData(this),
            processData: false, contentType: false, dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                $modalUsuario.modal('hide');
                tableUsuarios.ajax.reload();
                notificacaoSucesso('Sucesso!', 'Usuário salvo com sucesso.');
            } else {
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar os dados.');
        });
    });

    // Excluir
    $('#tabela-usuarios').on('click', '.btn-excluir-usuario', function () {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        confirmacaoAcao(
            'Excluir Usuário?',
            `Tem a certeza de que deseja excluir o usuário "${nome}"?`
        ).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=excluirUsuario', { usu_codigo: id, csrf_token: csrfToken }, function (response) {
                    tableUsuarios.ajax.reload();
                    if (response.success) {
                        notificacaoSucesso('Excluído!', response.message); // << REATORADO
                    } else {
                        notificacaoErro('Erro!', response.message); // << REATORADO
                    }
                }, 'json').fail(function () {
                    notificacaoErro('Erro de Comunicação', 'Não foi possível excluir o usuário.');
                });
            }
        });
    });
});