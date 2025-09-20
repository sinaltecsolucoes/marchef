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
            {
                "data": "car_numero", "className": "text-center align-middle",
            },
            {
                "data": "ent_razao_social", "className": "align-middle",
            },
            {
                "data": "car_data",
                "className": "text-center align-middle",
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
                    const carregamentoId = row.car_id;
                    const carregamentoNumero = row.car_numero;
                    let acoesHtml = '';

                    // Ações para carregamentos ATIVOS
                    if (status === 'EM ANDAMENTO' || status === 'AGUARDANDO CONFERENCIA') {
                        acoesHtml = `<a href="index.php?page=carregamento_detalhes&id=${carregamentoId}" class="btn btn-info btn-sm btn-continuar-carregamento me-1">Continuar</a>`;
                        acoesHtml += `
                                    <div class="btn-group d-inline-block">
                                        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Mais</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item btn-cancelar-carregamento" href="#" data-id="${carregamentoId}" data-numero="${carregamentoNumero}">Cancelar</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger btn-excluir-carregamento" href="#" data-id="${carregamentoId}" data-numero="${carregamentoNumero}">Excluir</a></li>
                                        </ul>
                                    </div>`;
                        return acoesHtml;
                    }

                    if (status === 'FINALIZADO') {
                        let acoesHtml = `<a href="index.php?page=carregamento_detalhes&id=${carregamentoId}" class="btn btn-secondary btn-sm btn-ver-detalhes me-1">Ver Detalhes</a>`;
                        acoesHtml += `
                                <div class="btn-group d-inline-block">
                                    <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Mais</button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item btn-reabrir-carregamento" href="#" data-id="${carregamentoId}" data-numero="${carregamentoNumero}">Reabrir Carregamento</a></li>
                                    </ul>
                                </div>`;
                        return acoesHtml;
                    }

                    // Ação padrão para FINALIZADO (ou qualquer outro status)
                    return `<a href="index.php?page=carregamento_detalhes&id=${carregamentoId}" class="btn btn-secondary btn-sm btn-ver-detalhes">Ver Detalhes</a>`;
                }
            }
        ],
        "order": [[2, 'desc']], // Ordenar pela data (mais recente primeiro)
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
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
                // atualizarOrdemExpedicao();
            }
        });

        // Limpa e carrega os clientes
        carregarClientesParaModal();

        // 2. Lista de clientes
        /*    $.get('ajax_router.php?action=getClienteOptions', function (response) {
                if (response.success) {
                    const $select = $('#car_entidade_id_organizador');
                    $select.empty().append('<option value="">Selecione...</option>');
                    response.data.forEach(function (cliente) {
                        $select.append(new Option(cliente.nome_display, cliente.ent_codigo));
                    });
                }
            });*/

        // Para inicializar o novo select da OE
        $('#car_ordem_expedicao_id').val(null).trigger('change'); // Limpa o select
        $('#car_ordem_expedicao_id').select2({
            placeholder: 'Selecione uma OE aberta...',
            dropdownParent: $modalCarregamento,
            theme: "bootstrap-5",
            language: "pt-BR",
            ajax: {
                url: 'ajax_router.php?action=getOrdensParaFaturamentoSelect', // Rota que já existe e busca OEs!
                dataType: 'json',
                processResults: function (data) {
                    return data; // A rota já retorna {results: [...]}
                }
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

    /**
    * Evento para o botão CANCELAR um carregamento na tabela principal.
    */
    $('#tabela-carregamentos').on('click', '.btn-cancelar-carregamento', function (e) {
        e.preventDefault();
        const carregamentoId = $(this).data('id');
        const carregamentoNumero = $(this).data('numero');

        confirmacaoAcao(
            `Cancelar Carregamento Nº ${carregamentoNumero}?`,
            'O status do carregamento será alterado para "CANCELADO", mas o registo será mantido para fins de histórico. Deseja continuar?'
        ).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=cancelarCarregamento',
                    type: 'POST',
                    data: { carregamento_id: carregamentoId, csrf_token: csrfToken },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        tabelaCarregamentos.ajax.reload(null, false);
                        notificacaoSucesso('Cancelado!', response.message);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                });
            }
        });
    });

    /**
     * Evento para o botão EXCLUIR um carregamento na tabela principal.
     */
    $('#tabela-carregamentos').on('click', '.btn-excluir-carregamento', function (e) {
        e.preventDefault();
        const carregamentoId = $(this).data('id');
        const carregamentoNumero = $(this).data('numero');

        confirmacaoAcao(
            `Excluir Carregamento Nº ${carregamentoNumero}?`,
            'Esta ação é IRREVERSÍVEL e irá apagar permanentemente o carregamento, todas as suas filas e todos os seus produtos. Tem a certeza absoluta?'
        ).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirCarregamento',
                    type: 'POST',
                    data: { carregamento_id: carregamentoId, csrf_token: csrfToken },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        tabelaCarregamentos.ajax.reload(null, false);
                        notificacaoSucesso('Excluído!', response.message);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                });
            }
        });
    });

    /**
    * Evento para o botão REATIVAR um carregamento na tabela principal.
    */
    $('#tabela-carregamentos').on('click', '.btn-reativar-carregamento', function (e) {
        e.preventDefault();
        const carregamentoId = $(this).data('id');
        const carregamentoNumero = $(this).data('numero');

        confirmacaoAcao(
            `Reativar Carregamento Nº ${carregamentoNumero}?`,
            'O status do carregamento voltará para "EM ANDAMENTO".'
        ).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=reativarCarregamento',
                    type: 'POST',
                    data: { carregamento_id: carregamentoId, csrf_token: csrfToken },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        tabelaCarregamentos.ajax.reload(null, false);
                        notificacaoSucesso('Reativado!', response.message);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                });
            }
        });
    });

    // Evento para ABRIR o modal de reabertura
    $('#tabela-carregamentos').on('click', '.btn-reabrir-carregamento', function () {
        const id = $(this).data('id');
        const numero = $(this).data('numero');

        $('#carregamento-id-reabrir').val(id);
        $('#carregamento-numero-reabrir').text(numero);
        $('#motivo-reabertura').val(''); // Limpa o campo de motivo

        $('#modal-reabrir-carregamento').modal('show');
    });

    // Evento para CONFIRMAR a reabertura
    $('#btn-confirmar-reabertura').on('click', function () {
        const id = $('#carregamento-id-reabrir').val();
        const motivo = $('#motivo-reabertura').val().trim();

        if (motivo === '') {
            notificacaoErro('Campo Obrigatório', 'Por favor, preencha o motivo da reabertura.');
            return;
        }

        $.ajax({
            url: 'ajax_router.php?action=reabrirCarregamento',
            type: 'POST',
            data: { carregamento_id: id, motivo: motivo, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $('#modal-reabrir-carregamento').modal('hide');
                tabelaCarregamentos.ajax.reload(null, false);
                notificacaoSucesso('Reaberto!', response.message);
            } else {
                notificacaoErro('Erro!', response.message);
            }
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
            if (response.data) {
                const $select = $('#car_entidade_id_organizador');
                $select.empty().append('<option value="">Selecione um cliente...</option>');
                response.data.forEach(function (cliente) {
                    //$select.append(new Option(cliente.nome_display, cliente.ent_codigo));
                    $select.append(new Option(cliente.text, cliente.id));

                });
            } else {
                // SUBSTITUÍDO: alert() por notificacaoErro()
                //notificacaoErro('Erro ao Carregar Clientes', response.message);
                notificacaoErro('Erro ao Carregar Clientes', response.message || 'Resposta inesperada do servidor.');
            }
        }).fail(function () {
            // SUBSTITUÍDO: alert() por notificacaoErro()
            notificacaoErro('Falha de Comunicação', 'Não foi possível carregar a lista de clientes.');
        });
    }

    /**
     * Atualiza o campo "Ordem de Expedição" com base no número e data.
    */
    /* function atualizarOrdemExpedicao() {
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
     } */

    /* $('#form-carregamento').on('change keyup', '#car_numero, #car_data', function () {
         atualizarOrdemExpedicao();
     }); */

});