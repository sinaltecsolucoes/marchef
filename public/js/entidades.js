$(document).ready(function () {
    // Detecta se estamos na página de cliente ou fornecedor
    const pageType = $('body').data('page-type');
    if (!pageType) {
        // Se não for nenhuma das duas, não executa o resto do script
        return;
    }

    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalEntidade = $('#modal-adicionar-entidade');
    const $formEntidade = $('#form-entidade');

    // =================================================================
    // INICIALIZAÇÃO DA TABELA DATATABLES
    // =================================================================
    const tableEntidades = $('#tabela-entidades').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarEntidades",
            "type": "POST",
            "data": function (d) {
                // Envia os filtros atuais para o backend
                d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val();
                d.tipo_entidade = pageType; // Envia 'cliente' ou 'fornecedor'
                d.filtro_tipo_entidade = $('#filtro-tipo-entidade').val(); // Envia o valor do novo filtro
                d.csrf_token = csrfToken;
            }
        },
        "responsive": true,
        "columns": [
            { "data": "ent_situacao", "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' },
            { "data": "ent_tipo_entidade" },
            { "data": "ent_codigo_interno" },
            { "data": "ent_razao_social" },
            { "data": null, "render": (data, type, row) => row.ent_tipo_pessoa === 'F' ? row.ent_cpf : row.ent_cnpj },
            { "data": "end_logradouro", "render": (data, type, row) => data ? `${row.end_logradouro || ''}, ${row.end_numero || ''}` : 'N/A' },
            { "data": "ent_codigo", "orderable": false, "render": (data, type, row) =>
                `<a href="#" class="btn btn-warning btn-sm btn-editar-entidade me-1" data-id="${data}">Editar</a>` +
                `<a href="#" class="btn btn-danger btn-sm btn-inativar-entidade" data-id="${data}" data-nome="${row.ent_razao_social}">Inativar</a>`
            }
        ],
        "language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // =================================================================
    // EVENTOS (AÇÕES DO USUÁRIO)
    // =================================================================

    // Recarrega a tabela quando os filtros mudam
    $('input[name="filtro_situacao"], #filtro-tipo-entidade').on('change', () => tableEntidades.ajax.reload());

    // Limpa o modal ao clicar em "Adicionar"
    $modalEntidade.on('show.bs.modal', function(event) {
        if ($(event.relatedTarget).is('#btn-adicionar-entidade')) {
            $formEntidade[0].reset();
            $('#ent-codigo').val('');
            $('#modal-adicionar-entidade-label').text('Adicionar ' + (pageType === 'cliente' ? 'Cliente' : 'Fornecedor'));
            // Habilita a aba de endereços adicionais apenas na edição
            $('#enderecos-tab').addClass('disabled');
        }
    });

    // Submissão do formulário (Salvar/Editar)
    $formEntidade.on('submit', function(e) {
        e.preventDefault();
        const id = $('#ent-codigo').val();
        const url = `ajax_router.php?action=salvarEntidade`;
        const formData = new FormData(this);

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $modalEntidade.modal('hide');
                    tableEntidades.ajax.reload(null, false);
                    // Exibir mensagem de sucesso
                } else {
                    $('#mensagem-entidade').removeClass().addClass('alert alert-danger').text(response.message);
                }
            }
        });
    });

    // Clique no botão Editar da tabela
    $('#tabela-entidades').on('click', '.btn-editar-entidade', function() {
        const id = $(this).data('id');
        $.ajax({
            url: `ajax_router.php?action=getEntidade`,
            type: 'POST',
            data: { ent_codigo: id, csrf_token: csrfToken },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    $formEntidade[0].reset();
                    // Preenche todos os campos do formulário
                    Object.keys(data).forEach(key => {
                        // Combina busca por ID e por name para preencher tudo
                        $formEntidade.find(`#${key}, [name="${key}"]`).val(data[key]);
                    });
                    // Lógica para marcar os radio buttons corretos
                    $formEntidade.find(`input[name="ent_tipo_pessoa"][value="${data.ent_tipo_pessoa}"]`).prop('checked', true);
                    $formEntidade.find(`input[name="ent_tipo_entidade"][value="${data.ent_tipo_entidade}"]`).prop('checked', true);

                    $('#modal-adicionar-entidade-label').text('Editar ' + data.ent_tipo_entidade);
                    $('#enderecos-tab').removeClass('disabled'); // Habilita a aba de endereços
                    $modalEntidade.modal('show');
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // Clique no botão Inativar da tabela
    $('#tabela-entidades').on('click', '.btn-inativar-entidade', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');

        if (confirm(`Tem certeza que deseja inativar o registro "${nome}"?`)) {
            $.ajax({
                url: `ajax_router.php?action=inativarEntidade`,
                type: 'POST',
                data: { ent_codigo: id, csrf_token: csrfToken },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        tableEntidades.ajax.reload();
                    }
                    alert(response.message);
                }
            });
        }
    });
    
    // (Aqui entrará o resto da sua lógica de negócio, como busca de CEP, máscaras de campo, etc.,
    // que pode ser copiada do seu JS antigo, pois não depende da comunicação com o backend)
});