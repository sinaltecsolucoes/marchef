// /public/js/carregamentos.js

$(document).ready(function () {

    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalCarregamento = $('#modal-carregamento');
    let tabelaCarregamentos; // Definida aqui para ser acessível em todo o escopo

    // Inicialização da tabela DataTables
    tabelaCarregamentos = $('#tabela-carregamentos').DataTable({
        "serverSide": true, // Processamento de dados no lado do servidor
        "ajax": {
            "url": "ajax_router.php?action=listarCarregamentos",
            "type": "POST",
            "data": function (d) {
                // Adiciona o token CSRF e o valor do nosso filtro de status à requisição
                d.csrf_token = csrfToken;
                d.filtro_status = $('#filtro-status-carregamento').val();
            }
        },
        "columns": [
            { "data": "car_numero" },
            { "data": "ent_razao_social" },
            {
                "data": "car_data",
                "className": "text-center",
                "render": function (data) {
                    if (!data) return '';
                    // Adiciona T00:00:00 para evitar problemas de fuso horário
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "car_status",
                "className": "text-center",
                "render": function (data) {
                    let badgeClass = 'bg-secondary';
                    if (data === 'EM ANDAMENTO') badgeClass = 'bg-warning text-dark';
                    if (data === 'AGUARDANDO CONFERENCIA') badgeClass = 'bg-primary';
                    if (data === 'FINALIZADO') badgeClass = 'bg-success';
                    if (data === 'CANCELADO') badgeClass = 'bg-danger';
                    return `<span class="badge ${badgeClass}">${data || 'INDEFINIDO'}</span>`;
                }
            },
            {
                "data": "car_id",
                "orderable": false,
                "className": "text-center",
                "render": function (data, type, row) {
                    const status = row.car_status;
                    if (status === 'AGUARDANDO CONFERENCIA') {
                        return `<button class="btn btn-primary btn-sm btn-conferir-carregamento" data-id="${data}">Conferir</button>`;
                    }
                    if (status === 'EM ANDAMENTO') {
                        return `<button class="btn btn-info btn-sm btn-continuar-carregamento" data-id="${data}">Continuar</button>`;
                    }
                    // Para status FINALIZADO e CANCELADO, podemos ter um botão de "Ver Detalhes"
                    return `<button class="btn btn-secondary btn-sm btn-ver-detalhes" data-id="${data}">Ver Detalhes</button>`;
                }
            }
        ],
        "order": [[2, 'desc']], // Ordenar pela data (mais recente primeiro)
        "language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // --- EVENT HANDLERS (AÇÕES DO UTILIZADOR) ---

    // Ação para os botões 'Continuar', 'Conferir' ou 'Ver Detalhes'
    $('#tabela-carregamentos tbody').on('click', '.btn-continuar-carregamento, .btn-conferir-carregamento, .btn-ver-detalhes', function () {
        const carregamentoId = $(this).data('id');

        // Redireciona o navegador para a nova página de detalhes, passando o ID do carregamento na URL
        window.location.href = `index.php?page=carregamento_detalhes&id=${carregamentoId}`;
    });

    // Evento para o filtro de status
    // Sempre que o utilizador mudar a seleção, a tabela é recarregada
    $('#filtro-status-carregamento').on('change', function () {
        tabelaCarregamentos.ajax.reload();
    });

    // Inicialização do Select2 para o dropdown de clientes dentro do novo modal
    $('#car_entidade_id_organizador').select2({
        placeholder: 'Selecione um cliente',
        dropdownParent: $modalCarregamento, // Essencial para funcionar no modal
        theme: "bootstrap-5"
    });

    // Ação para o botão "Novo Carregamento"
    $('#btn-novo-carregamento').on('click', function () {
        // Limpa o formulário
        $('#form-carregamento')[0].reset();
        $('#car_id').val('');
        $('#car_entidade_id_organizador').val(null).trigger('change');
        $('#modal-carregamento-label').text('Novo Carregamento');
        $('#mensagem-carregamento-modal').html('');

        // Busca os dados necessários para preencher o formulário
        // 1. Próximo número de carregamento
        $.get('ajax_router.php?action=getProximoNumeroCarregamento', function (response) {
            if (response.success) {
                $('#car_numero').val(response.proximo_numero);
                atualizarOrdemExpedicao();
            }
        });

        // Limpa e carrega os clientes
        carregarClientesParaModal();

        // 2. Lista de clientes
        $.get('ajax_router.php?action=getClienteOptions', function (response) {
            if (response.success) {
                const $select = $('#car_entidade_id_organizador');
                $select.empty().append('<option value="">Selecione...</option>');
                response.data.forEach(function (cliente) {
                    $select.append(new Option(cliente.ent_razao_social, cliente.ent_codigo));
                });
            }
        });

        // Abre o modal
        $modalCarregamento.modal('show');
    });

    // Ação para submeter o formulário de SALVAR (novo carregamento)
    $('#form-carregamento').on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const $botaoSalvar = $(this).find('button[type="submit"]');

        // Desabilitar o botão para evitar cliques duplos
        $botaoSalvar.prop('disabled', true);

        $.ajax({
            url: 'ajax_router.php?action=salvarCarregamentoHeader',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalCarregamento.modal('hide');
                tabelaCarregamentos.ajax.reload(null, false);

                notificacaoSucesso('Sucesso!', 'Carregamento criado com sucesso.');

                // Sugestão para o futuro:
                // if (confirm('Carregamento criado. Deseja adicionar os itens agora?')) {
                //     window.location.href = `index.php?page=carregamento_detalhes&id=${response.carregamento_id}`;
                // }

            } else {
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar o carregamento.');
        }).always(function () {
            // Reabilitar o botão
            $botaoSalvar.prop('disabled', false);
        });
    });
    // --- FUNÇÕES AUXILIARES ---

    /**
     * Carrega a lista de clientes para o dropdown do modal de carregamento.
     */
    function carregarClientesParaModal() {
        return $.ajax({
            url: 'ajax_router.php?action=getClienteOptions',
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const $select = $('#car_entidade_id_organizador');
                $select.empty().append('<option value="">Selecione um cliente...</option>');
                response.data.forEach(function (cliente) {
                    $select.append(new Option(cliente.ent_razao_social, cliente.ent_codigo));
                });
            } else {
                // SUBSTITUÍDO: alert() por notificacaoErro()
                notificacaoErro('Erro ao Carregar Clientes', response.message);
            }
        }).fail(function () {
            // SUBSTITUÍDO: alert() por notificacaoErro()
            notificacaoErro('Falha de Comunicação', 'Não foi possível carregar a lista de clientes.');
        });
    }

    /**
     * Atualiza o campo "Ordem de Expedição" com base no número e data.
     * Segue a regra: NUMERO.MES.ANO
     */
    function atualizarOrdemExpedicao() {
        const numero = $('#car_numero').val();
        const dataStr = $('#car_data').val(); // Formato YYYY-MM-DD

        if (numero && dataStr) {
            const data = new Date(dataStr + 'T00:00:00');
            const mes = String(data.getMonth() + 1).padStart(2, '0'); // +1 porque getMonth() é base 0
            const ano = data.getFullYear();

            const ordemExpedicao = `${numero}.${mes}.${ano}`;
            $('#car_ordem_expedicao').val(ordemExpedicao);
        } else {
            $('#car_ordem_expedicao').val(''); // Limpa se os campos não estiverem preenchidos
        }
    }

    $('#form-carregamento').on('change keyup', '#car_numero, #car_data', function () {
        atualizarOrdemExpedicao();
    });

});