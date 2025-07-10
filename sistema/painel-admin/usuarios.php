<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Painel Admin</title>

    <link rel="stylesheet" type="text/css" href="../vendor/DataTables/datatables.min.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" />

</head>

<body>
    <div class="table-responsive">
        
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script type="text/javascript" src="../vendor/DataTables/datatables.min.js"></script>

    <script type="text/javascript"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript"
        src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#example').DataTable({
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
    </script>

</body>

</html>