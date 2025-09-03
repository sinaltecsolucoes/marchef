// /public/js/regras.js
$(document).ready(function () {

    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modal = $('#modal-regra');
    const $form = $('#form-regra');

    // Inicialização dos Select2 para os dropdowns do modal
    $('#regra_cliente_id, #regra_produto_id, #regra_template_id').select2({
        placeholder: 'Selecione uma opção ou deixe em branco',
        allowClear: true, // Permite limpar a seleção
        dropdownParent: $modal,
        theme: "bootstrap-5"
    });

    // Função para carregar Clientes no dropdown
    function carregarClientes() {
        $.get('ajax_router.php?action=getClienteOptions').done(function (response) {
            if (response.success) {
                const $select = $('#regra_cliente_id');
                $select.empty().append('<option value="">Todos os Clientes</option>');
                response.data.forEach(function (cliente) {
                    const textoOpcao = `${cliente.nome_display} (Cód: ${cliente.ent_codigo_interno})`;
                    $select.append(new Option(textoOpcao, cliente.ent_codigo));
                });
            }
        });
    }

    // Função para carregar Produtos no dropdown
    function carregarProdutos() {
        $.get('ajax_router.php?action=getProdutoOptions', { tipo_embalagem: 'Todos' }).done(function (response) {
            if (response.success) {
                const $select = $('#regra_produto_id');
                $select.empty().append('<option value="">Todos os Produtos</option>');
                response.data.forEach(function (produto) {
                    const textoOpcao = `${produto.prod_descricao} (Cód: ${produto.prod_codigo_interno || 'N/A'})`;
                    $select.append(new Option(textoOpcao, produto.prod_codigo));
                });
            }
        });
    }

    // Função para carregar Templates no dropdown
    function carregarTemplates() {
        return $.get('ajax_router.php?action=getTemplateOptions').done(function (response) {
            if (response.success) {
                const $select = $('#regra_template_id');
                $select.empty().append('<option value="">Selecione um template</option>');
                response.data.forEach(function (template) {
                    $select.append(new Option(template.template_nome, template.template_id));
                });
            }
        });
    }

    // Inicialização da DataTable
    const table = $('#tabela-regras').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarRegras",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            { "data": "cliente_nome" },
            { "data": "produto_nome" },
            { "data": "template_nome" },
            { "data": "regra_prioridade", "className": "text-center" },
            {
                "data": "regra_id",
                "orderable": false,
                "className": "text-center actions-column",
                "render": function (data) {
                    return `
                        <button class="btn btn-warning btn-sm btn-editar-regra" data-id="${data}">Editar</button>
                        <button class="btn btn-danger btn-sm btn-excluir-regra" data-id="${data}">Excluir</button>
                    `;
                }
            }
        ],
        // "language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[3, 'asc']]
    });

    // Abrir modal para ADICIONAR
    $('#btn-adicionar-regra').on('click', function () {
        $form[0].reset();
        $('#regra_id').val('');
        $('#regra_cliente_id').val(null).trigger('change');
        $('#regra_produto_id').val(null).trigger('change');
        $('#regra_template_id').val(null).trigger('change');
        $('#modal-regra-label').text('Adicionar Nova Regra');
        $('#mensagem-regra-modal').html('');
        carregarClientes();
        carregarProdutos();
        carregarTemplates();
        $modal.modal('show');
    });

    // Abrir modal para EDITAR
    $('#tabela-regras').on('click', '.btn-editar-regra', function () {
        const id = $(this).data('id');
        // Carrega todos os dropdowns primeiro
        $.when(carregarClientes(), carregarProdutos(), carregarTemplates()).done(function () {
            // Depois que todos carregaram, busca os dados da regra
            $.ajax({
                url: 'ajax_router.php?action=getRegra',
                type: 'POST',
                data: { regra_id: id, csrf_token: csrfToken },
                dataType: 'json'
            }).done(function (response) {
                if (response.success) {
                    const data = response.data;
                    $('#regra_id').val(data.regra_id);
                    $('#regra_prioridade').val(data.regra_prioridade);
                    // Define os valores e dispara o 'change' para o Select2 atualizar a exibição
                    $('#regra_cliente_id').val(data.regra_cliente_id).trigger('change');
                    $('#regra_produto_id').val(data.regra_produto_id).trigger('change');
                    $('#regra_template_id').val(data.regra_template_id).trigger('change');

                    $('#modal-regra-label').text('Editar Regra');
                    $('#mensagem-regra-modal').html('');
                    $modal.modal('show');
                } else {
                    notificacaoErro('Erro ao Carregar', response.message);
                }
            }).fail(function () {
                notificacaoErro('Erro de Comunicação', 'Não foi possível carregar os dados da regra.');
            });
        });
    });

    // EXCLUIR regra
    $('#tabela-regras').on('click', '.btn-excluir-regra', function () {
        const id = $(this).data('id');
        confirmacaoAcao(
            'Excluir Regra?',
            'Tem a certeza de que deseja excluir esta regra?'
        ).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirRegra',
                    type: 'POST',
                    data: { regra_id: id, csrf_token: csrfToken },
                    dataType: 'json'
                }).done(function (response) {
                    table.ajax.reload(null, false);
                    if (response.success) {
                        notificacaoSucesso('Excluída!', response.message); // << REATORADO
                    } else {
                        notificacaoErro('Erro!', response.message); // << REATORADO
                    }
                }).fail(function () {
                    notificacaoErro('Erro de Comunicação', 'Não foi possível excluir a regra.');
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
            url: 'ajax_router.php?action=salvarRegra',
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
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar a regra.');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });
});