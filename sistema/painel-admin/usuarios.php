<a href="#" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-adicionar-usuario">Adicionar
    Usuário</a>

<div class="table-responsive">
    <table id="example" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th>Situação</th>
                <th>Login</th>
                <th>Nome</th>
                <th>Nível</th>
                <th>Código</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modal-adicionar-usuario" tabindex="-1" role="dialog"
    aria-labelledby="modal-adicionar-usuario-label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-adicionar-usuario-label">Adicionar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="form-adicionar-usuario">
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" class="form-control" id="nome" name="usu_nome" required>
                    </div>
                    <div class="form-group">
                        <label for="login">Login</label>
                        <input type="text" class="form-control" id="login" name="usu_login" required>
                    </div>
                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <input type="password" class="form-control" id="senha" name="usu_senha" required>
                    </div>
                    <div class="form-group">
                        <label for="nivel">Nível de Acesso</label>
                        <select class="form-control" id="nivel" name="usu_tipo" required>
                            <option value="Admin">Admin</option>
                            <option value="Comum">Gerente</option>
                            <option value="Comum">Produção</option>
                        </select>
                    </div>
                    <div class="form-group mt-3">
                        <label class="form-label" for="situacao">Situação</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="situacao"
                                name="usu_situacao" value="1" checked>
                            <label class="form-check-label" for="situacao">
                                Ativo
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="submit" form="form-adicionar-usuario" class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Inicializa o DataTables
        $('#example').DataTable({
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"t>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "ajax": "../vendor/datatables/listar_usuarios.php",
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
    });

    // Lida com o envio do formulário do modal
    $('#form-adicionar-usuario').on('submit', function (e) {
        e.preventDefault();

        // Pega os dados do formulário
        var formData = $(this).serialize();
        
        // Se o switch não estiver marcado, adiciona o valor '0' manualmente
        if (!$('#situacao').is(':checked')) {
            formData += '&usu_situacao=0';
        }

        $.ajax({
            type: 'POST',
            url: '../vendor/datatables/cadastrar_usuario.php',
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
</script>