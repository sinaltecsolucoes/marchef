// /public/js/templates.js
$(document).ready(function () {

    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modal = $('#modal-template');
    const $form = $('#form-template');

    // Inicialização da DataTable
    const table = $('#tabela-templates').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarTemplates",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "responsive": true,
        "columns": [
            { "data": "template_nome" },
            { "data": "template_descricao" },
            {
                "data": "template_data_criacao",
                "render": function (data) {
                    return new Date(data).toLocaleString('pt-BR');
                }
            },
            {
                "data": "template_id",
                "orderable": false,
                "className": "col-centralizavel",
                "render": (data) => {
                    let btnEditar = `<button class="btn btn-warning btn-sm btn-editar-template me-1 d-inline-flex align-items-center" data-id="${data}"><i class="fas fa-pencil-alt me-1"></i>Editar</button>`;
                    let btnExcluir = `<button class="btn btn-danger btn-sm btn-excluir-template d-inline-flex align-items-center" data-id="${data}"><i class="fas fa-trash-alt me-1"></i>Excluir</button>`;
                    return `<div class="btn-group">${btnEditar}${btnExcluir}</div>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[2, 'desc']]
    });

    // Abrir modal para ADICIONAR
    $('#btn-adicionar-template').on('click', function () {
        $form[0].reset();
        $('#template_id').val('');
        $('#modal-template-label').text('Adicionar Novo Template');
        $('#mensagem-template-modal').html('');
        $modal.modal('show');
    });

    // Abrir modal para EDITAR
    $('#tabela-templates').on('click', '.btn-editar-template', function () {

        const id = $(this).data('id');
        $.ajax({
            url: 'ajax_router.php?action=getTemplate',
            type: 'POST',
            data: { template_id: id, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const data = response.data;
                $('#template_id').val(data.template_id);
                $('#template_nome').val(data.template_nome);
                $('#template_descricao').val(data.template_descricao);
                $('#template_conteudo_zpl').val(data.template_conteudo_zpl);

                $('#modal-template-label').text('Editar Template');
                $modal.modal('show');
            } else {
                notificacaoErro('Erro ao Carregar', response.message);
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log('AJAX Fail Details:', textStatus, errorThrown, jqXHR.responseText);
            notificacaoErro('Erro de Comunicação', 'Não foi possível carregar os dados do template. Detalhes: ' + (jqXHR.responseText || 'Resposta vazia'));
        });
    });

    // EXCLUIR template
    $('#tabela-templates').on('click', '.btn-excluir-template', function () {
        const id = $(this).data('id');
        confirmacaoAcao(
            'Excluir Template?',
            'Tem a certeza que deseja excluir este template? Esta ação não pode ser desfeita.'
        ).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirTemplate',
                    type: 'POST',
                    data: { template_id: id, csrf_token: csrfToken },
                    dataType: 'json'
                }).done(function (response) {
                    table.ajax.reload(null, false);
                    if (response.success) {
                        notificacaoSucesso('Excluído!', response.message);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                }).fail(function () {
                    notificacaoErro('Erro de Comunicação', 'Não foi possível excluir o template.');
                });
            }
        });
    });

    // SALVAR (Criar ou Editar)
    $form.on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const $button = $(this).find('button[type="submit"]');
        $button.prop('disabled', true);

        $.ajax({
            url: 'ajax_router.php?action=salvarTemplate',
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
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar o template.');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    // Evento para ler o arquivo ZPL e preencher a textarea
    $('#zpl_file_upload').on('change', function (event) {
        const file = event.target.files[0];
        if (!file) {
            return; // Nenhum arquivo selecionado
        }

        const reader = new FileReader();

        reader.onload = function (e) {
            const content = e.target.result;
            $('#template_conteudo_zpl').val(content); // Preenche a textarea com o conteúdo do arquivo
            notificacaoSucesso('Sucesso!', 'Conteúdo do arquivo carregado na caixa de texto.');
        };

        reader.onerror = function () {
            notificacaoErro('Erro!', 'Não foi possível ler o arquivo selecionado.');
        };

        reader.readAsText(file); // Lê o arquivo como texto
    });
});