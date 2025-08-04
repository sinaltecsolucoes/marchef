// /public/js/templates.js
$(document).ready(function() {

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
        "columns": [
            { "data": "template_nome" },
            { "data": "template_descricao" },
            { 
                "data": "template_data_criacao",
                "render": function(data) {
                    return new Date(data).toLocaleString('pt-BR');
                }
            },
            {
                "data": "template_id",
                "orderable": false,
                "className": "text-center",
                "render": function(data) {
                    return `
                        <button class="btn btn-warning btn-sm btn-editar-template" data-id="${data}">Editar</button>
                        <button class="btn btn-danger btn-sm btn-excluir-template" data-id="${data}">Excluir</button>
                    `;
                }
            }
        ],
        "language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[2, 'desc']]
    });

    // Abrir modal para ADICIONAR
    $('#btn-adicionar-template').on('click', function() {
        $form[0].reset();
        $('#template_id').val('');
        $('#modal-template-label').text('Adicionar Novo Template');
        $('#mensagem-template-modal').html('');
        $modal.modal('show');
    });

    // Abrir modal para EDITAR
    $('#tabela-templates').on('click', '.btn-editar-template', function() {
        const id = $(this).data('id');
        $.ajax({
            url: 'ajax_router.php?action=getTemplate',
            type: 'POST',
            data: { template_id: id, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                const data = response.data;
                $('#template_id').val(data.template_id);
                $('#template_nome').val(data.template_nome);
                $('#template_descricao').val(data.template_descricao);
                $('#template_conteudo_zpl').val(data.template_conteudo_zpl);
                
                $('#modal-template-label').text('Editar Template');
                $('#mensagem-template-modal').html('');
                $modal.modal('show');
            } else {
                alert(response.message);
            }
        });
    });

    // EXCLUIR template
    $('#tabela-templates').on('click', '.btn-excluir-template', function() {
        const id = $(this).data('id');
        if (confirm('Tem certeza que deseja excluir este template? Esta ação não pode ser desfeita.')) {
            $.ajax({
                url: 'ajax_router.php?action=excluirTemplate',
                type: 'POST',
                data: { template_id: id, csrf_token: csrfToken },
                dataType: 'json'
            }).done(function(response) {
                if (response.success) {
                    table.ajax.reload(null, false);
                }
                alert(response.message);
            });
        }
    });

    // SALVAR (Criar ou Editar)
    $form.on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        $.ajax({
            url: 'ajax_router.php?action=salvarTemplate',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                $modal.modal('hide');
                table.ajax.reload(null, false);
                // Exibe a mensagem de sucesso na área principal
                $('#feedback-message-area').html(`<div class="alert alert-success">${response.message}</div>`);
            } else {
                // Exibe a mensagem de erro dentro do modal
                $('#mensagem-template-modal').html(`<div class="alert alert-danger">${response.message}</div>`);
            }
        }).fail(function() {
            $('#mensagem-template-modal').html(`<div class="alert alert-danger">Erro de comunicação com o servidor.</div>`);
        });
    });
});