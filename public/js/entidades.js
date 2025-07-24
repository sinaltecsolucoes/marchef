$(document).ready(function () {
    const pageType = $('body').data('page-type'); // 'cliente' ou 'fornecedor'
    if (!pageType) return;

    const entityName = pageType.charAt(0).toUpperCase() + pageType.slice(1);
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    let tableEntidades;

    // Função para exibir mensagens de feedback
    function showFeedbackMessage(message, type = 'success', area = '#feedback-message-area') {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        $(area).empty().removeClass('alert-success alert-danger').addClass(`alert ${alertClass}`).text(message).fadeIn();
        setTimeout(() => $(area).fadeOut('slow'), 5000);
    }

    // Inicialização da Tabela Principal
    tableEntidades = $('#tabela-entidades').DataTable({
        "ajax": {
            "url": "ajax_router.php?action=listarEntidades", // ROTA CORRIGIDA
            "type": "POST",
            "data": function(d) {
                d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val();
                d.tipo_entidade = entityName;
                d.csrf_token = csrfToken;
            }
        },
        "columns": [
            { "data": "ent_situacao", "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' },
            { "data": "ent_tipo_entidade" },
            { "data": "ent_codigo_interno" },
            { "data": "ent_razao_social" },
            { "data": null, "render": (data, type, row) => row.ent_tipo_pessoa === 'F' ? row.ent_cpf : row.ent_cnpj },
            { "data": "end_logradouro", "render": (data, type, row) => data ? `${row.end_logradouro || ''}, ${row.end_numero || ''}` : 'N/A' },
            { "data": "ent_codigo", "orderable": false, "render": (data, type, row) =>
                `<a href="#" class="btn btn-warning btn-sm btn-editar me-1" data-id="${data}">Editar</a>` +
                `<a href="#" class="btn btn-danger btn-sm btn-inativar" data-id="${data}" data-nome="${row.ent_razao_social}">Inativar</a>`
            }
        ],
        "language": { "url": "<?php echo BASE_URL; ?>/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });
    
    // Recarrega a tabela quando o filtro de situação muda
    $('input[name="filtro_situacao"]').on('change', () => tableEntidades.ajax.reload());

    // Abrir modal para editar
    $('#tabela-entidades').on('click', '.btn-editar', function() {
        const id = $(this).data('id');
        // AJAX para buscar dados da entidade (apontando para o router)
        // $.post('ajax_router.php?action=getEntidade', { id: id }, function(response) { ... });
        // Lógica para preencher o modal...
    });
    
    // Abrir modal de inativação
    $('#tabela-entidades').on('click', '.btn-inativar', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        $('#id-excluir').val(id);
        $('#nome-excluir').text(nome);
        new bootstrap.Modal($('#modal-confirmar-exclusao')[0]).show();
    });

    // Lógica do botão de confirmação da inativação
    $('#btn-confirmar-exclusao').on('click', function() {
        const id = $('#id-excluir').val();
        // AJAX para inativar (apontando para o router)
        // $.post('ajax_router.php?action=inativarEntidade', { ent_codigo: id }, function(response) { ... });
    });

    // Lógica para o submit do formulário principal da entidade
    $('#form-entidade').on('submit', function(e) {
        e.preventDefault();
        // AJAX para salvar/editar (apontando para o router)
        // A URL muda se for edição ou cadastro.
    });
});