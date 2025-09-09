// /public/js/enderecos.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modal = $('#modal-endereco');
    const $form = $('#form-endereco');
    const $selectCamara = $('#select-camara-filtro');
    const $btnAdd = $('#btn-adicionar-endereco');

    // Inicializa Select2 para o filtro de Câmaras
    $selectCamara.select2({
        placeholder: "Selecione uma câmara...",
        theme: "bootstrap-5"
    });

    // Inicializa a DataTable (vazia no início)
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
        "columns": [
            { "data": "endereco_completo" },
            { "data": "lado" },
            { "data": "nivel" },
            { "data": "fila" },
            { "data": "vaga" },
            { "data": "descricao_simples" },
            {
                "data": "endereco_id",
                "orderable": false,
                "className": "text-center",
                "render": function (data) {
                    return `
                        <button class="btn btn-warning btn-sm btn-editar-endereco" data-id="${data}">Editar</button>
                        <button class="btn btn-danger btn-sm btn-excluir-endereco" data-id="${data}">Excluir</button>
                    `;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // Desabilita a tabela no início
    $('#tabela-enderecos_wrapper').hide();

    // Carrega as Câmaras no Select2
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

    // Evento de mudança no seletor de Câmaras
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

    // --- LÓGICA DE SALVAR, EDITAR E EXCLUIR ---

    function resetarModal() {
        $form[0].reset();
        $('#endereco_id').val('');
        $('#hierarquia-group input, #descricao_simples').prop('disabled', false);
    }

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

    $('#tabela-enderecos').on('click', '.btn-editar-endereco', function () {
        const id = $(this).data('id');
        abrirModalParaEdicao(id);
    });

    /*  $('#tabela-enderecos').on('click', '.btn-editar-endereco', function () {
          const id = $(this).data('id');
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
  
                  // Dispara a lógica de desabilitar campos
                  $('#descricao_simples').trigger('input');
                  $('#hierarquia-group input:first').trigger('input');
  
                  $modal.modal('show');
              }
          });
      });*/

    $('#tabela-enderecos').on('click', '.btn-excluir-endereco', function () {
        const id = $(this).data('id');
        confirmacaoAcao('Excluir Endereço?', 'Tem a certeza? Esta ação não pode ser desfeita.')
            .then((result) => {
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
                    $modal.modal('hide'); // Esconde o modal de cadastro
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
                    // Se for outro tipo de erro
                    notificacaoErro('Erro ao Salvar', response.message);
                }
            }
        });
    });

    // Lógica para desabilitar campos opostos no modal
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

    $('#tabela-enderecos').on('click', '.btn-desalocar-endereco', function () {
        const id = $(this).data('id');
        confirmacaoAcao('Desalocar Item?', 'Tem a certeza que deseja liberar este endereço? O item voltará a ficar pendente de alocação.')
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=desalocarItemEndereco',
                        type: 'POST',
                        data: { endereco_id: id, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            table.ajax.reload(null, false);
                            notificacaoSucesso('Sucesso!', response.message);
                        } else {
                            notificacaoErro('Erro!', response.message);
                        }
                    });
                }
            });
    });
});