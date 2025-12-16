// /public/js/enderecos.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modal = $('#modal-endereco');
    const $form = $('#form-endereco');
    const $selectCamara = $('#select-camara-filtro');
    const $btnAdd = $('#btn-adicionar-endereco');

    $selectCamara.select2({
        placeholder: "Selecione uma câmara...",
        theme: "bootstrap-5"
    });

    const table = $('#tabela-enderecos').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarEnderecosCamaras",
            "type": "POST",
            "data": function (d) {
                d.camara_id = $selectCamara.val();
                d.csrf_token = csrfToken;
                return d;
            }
        },
        "responsive": true,
        "columns": [
            { "data": "endereco_completo" },
            {
                "data": "lado",
                "className": "text-center align-middle"
            },
            {
                "data": "nivel",
                "className": "text-center align-middle"
            },
            {
                "data": "fila",
                "className": "text-center align-middle"
            },
            {
                "data": "vaga",
                "className": "col-centralizavel align-middle"
            },
            {
                "data": "descricao_simples",
                "className": "col-centralizavel align-middle"
            },
            {
                "data": "endereco_id",
                "orderable": false,
                "className": "col-centralizavel align-middle",
                "render": (data) => {
                    let btnEditar = `<button class="btn btn-warning btn-sm btn-editar-endereco me-1 d-inline-flex align-items-center" 
                                        data-id="${data}"><i class="fas fa-pencil-alt me-1"></i>Editar</button>`;
                    let btnExcluir = `<button class="btn btn-danger btn-sm btn-excluir-endereco d-inline-flex align-items-center" 
                                        data-id="${data}"><i class="fas fa-trash-alt me-1"></i>Excluir</button>`;
                    return `<div class="btn-group">${btnEditar}${btnExcluir}</div>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    $('#tabela-enderecos_wrapper').hide();

    function carregarCamaras() {
        $.ajax({
            url: 'ajax_router.php?action=getCamaraOptions',
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            $selectCamara.empty().append(new Option('Selecione uma câmara...', ''));
            if (response.success) {
                response.data.forEach(function (camara) {
                    $selectCamara.append(new Option(camara.camara_nome, camara.camara_id));
                });
            }
        });
    }
    carregarCamaras();

    $selectCamara.on('change', function () {
        const camaraId = $(this).val();
        if (camaraId) {
            $('#tabela-enderecos_wrapper').show();
            table.ajax.reload();
            $btnAdd.prop('disabled', false);
        } else {
            $('#tabela-enderecos_wrapper').hide();
            table.clear().draw();
            $btnAdd.prop('disabled', true);
        }
    });

    function resetarModal() { $form[0].reset(); $('#endereco_id').val(''); $('#hierarquia-group input, #descricao_simples').prop('disabled', false); }

    function abrirModalParaEdicao(id) {
        $.ajax({
            url: 'ajax_router.php?action=getEnderecoCamaras',
            type: 'POST',
            data: { endereco_id: id, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const data = response.data;
                resetarModal();
                $('#endereco_id').val(data.endereco_id);
                $('#endereco_camara_id').val(data.endereco_camara_id);
                $('#lado').val(data.lado);
                $('#nivel').val(data.nivel);
                $('#fila').val(data.fila);
                $('#vaga').val(data.vaga);
                $('#descricao_simples').val(data.descricao_simples);
                $('#modal-endereco-label').text('Editar Endereço');
                $('#descricao_simples').trigger('input');
                $('#hierarquia-group input:first').trigger('input');
                $modal.modal('show');
            }
        });
    }

    $btnAdd.on('click', function () {
        resetarModal();
        $('#endereco_camara_id').val($selectCamara.val());
        $('#modal-endereco-label').text('Adicionar Novo Endereço');
        $modal.modal('show');
    });

    $('#tabela-enderecos').on('click', '.btn-editar-endereco', function () { abrirModalParaEdicao($(this).data('id')); });

    $('#tabela-enderecos').on('click', '.btn-excluir-endereco', function () {
        const id = $(this).data('id');
        confirmacaoAcao('Excluir Endereço?', 'Tem a certeza? Esta ação não pode ser desfeita.').then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirEnderecoCamaras',
                    type: 'POST',
                    data: { endereco_id: id, csrf_token: csrfToken },
                    dataType: 'json'
                }).done(function (response) {
                    table.ajax.reload(null, false);
                    if (response.success) {
                        notificacaoSucesso('Excluído!', response.message);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                });
            }
        });
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        $.ajax({
            url: 'ajax_router.php?action=salvarEnderecoCamaras',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modal.modal('hide');
                table.ajax.reload(null, false);
                notificacaoSucesso('Sucesso!', response.message);
            } else {
                if (response.error_type === 'duplicate_entry') {
                    $modal.modal('hide');
                    Swal.fire({
                        title: 'Endereço Duplicado',
                        text: 'Este endereço já está cadastrado. Gostaria de editar o registro existente?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sim, editar!',
                        cancelButtonText: 'Não'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            abrirModalParaEdicao(response.existing_id);
                        }
                    });
                } else {
                    notificacaoErro('Erro ao Salvar', response.message);
                }
            }
        });
    });

    $('#hierarquia-group input').on('input', function () {
        if ($(this).val() !== '') {
            $('#descricao_simples').prop('disabled', true);
        } else if ($('#hierarquia-group input').filter((_, el) => $(el).val() !== '').length === 0) {
            $('#descricao_simples').prop('disabled', false);
        }
    });

    $('#descricao_simples').on('input', function () {
        $('#hierarquia-group input').prop('disabled', $(this).val() !== '');
    });
});