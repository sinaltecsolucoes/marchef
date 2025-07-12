$(document).ready(function () {
    // --- LÓGICA DO DATATABLES (Lista de Usuários) ---
    // Verifica se a tabela já foi inicializada e a destrói se for o caso
    if ($.fn.DataTable.isDataTable('#example')) {
        $('#example').DataTable().destroy();
    }

    // Inicializa o DataTables
    $('#example').DataTable({
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"t>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "ajax": "../vendor/DataTables/listar_usuarios.php",
        "responsive": true,
        "columns": [
            {
                "data": "usu_situacao",
                "render": function (data, type, row) {
                    if (data === 'A') {
                        return "Ativo";
                    }
                    return "Inativo";
                }
            },
            { "data": "usu_login" },
            { "data": "usu_nome" },
            { "data": "usu_tipo" },
            { "data": "usu_codigo" },
            {
                "data": "usu_codigo",
                "render": function (data, type, row) {
                    return '<a href="#" class="btn btn-warning btn-sm">Editar</a> ' +
                        '<a href="#" class="btn btn-danger btn-sm">Excluir</a>';
                }
            }
        ],
        "ordering": true,
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.10.22/i18n/Portuguese-Brasil.json"
        }
    });

    // --- LÓGICA DO FORMULÁRIO (Adicionar Usuário) ---
    // Lida com o envio do formulário do modal
    $('#form-adicionar-usuario').on('submit', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
            type: 'POST',
            url: '../vendor/DataTables/cadastrar_usuarios.php', // CORRIGIDO: nome e capitalização
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#modal-adicionar-usuario').modal('hide');
                    $('#form-adicionar-usuario')[0].reset();
                    $('#example').DataTable().ajax.reload();
                    alert(response.message);
                } else {
                    alert('Erro ao cadastrar usuário: ' + response.message);
                }
            },
            error: function (xhr, status, error) {
                alert('Erro na requisição: ' + error);
            }
        });
    });
});