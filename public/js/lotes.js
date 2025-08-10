// /public/js/lotes.js
$(document).ready(function () {

    // --- Seletores e Variáveis Globais ---
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalLote = $('#modal-lote');
    const $modalImpressao = $('#modal-imprimir-etiqueta');
    //const $selectCliente = $('#select-cliente-etiqueta');
    //const $btnConfirmarImpressao = $('#btn-confirmar-impressao');
    let tabelaLotes, loteIdAtual;

    // --- Inicialização da Tabela Principal de Lotes ---
    tabelaLotes = $('#tabela-lotes').DataTable({
        "serverSide": true,
        "ajax": { "url": "ajax_router.php?action=listarLotes", "type": "POST", "data": { csrf_token: csrfToken } },
        "responsive": true,
        "columns": [
            { "data": "lote_completo_calculado", "width": "10%" },
            { "data": "fornecedor_razao_social", "width": "25%" },
            {
                "data": "lote_data_fabricacao",
                "className": "text-center",
                "width": "15%",
                "render": function (data, type, row) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "lote_status",
                "className": "text-center",
                "width": "10%",

                "render": function (data, type, row) {
                    let badgeClass = 'bg-secondary';
                    if (data === 'EM ANDAMENTO') badgeClass = 'bg-warning text-dark';
                    if (data === 'PARCIALMENTE FINALIZADO') badgeClass = 'bg-info';
                    if (data === 'FINALIZADO') badgeClass = 'bg-success';
                    if (data === 'CANCELADO') badgeClass = 'bg-danger';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            {
                "data": "lote_data_cadastro",
                "className": "text-center",
                "width": "10%",
                "render": function (data, type, row) {
                    if (!data) return '';
                    const date = new Date(data);
                    return date.toLocaleString('pt-BR');
                }
            },
            {
                "data": "lote_id",
                //"data": "",
                "orderable": false,
                "className": "text-center",
                "width": "10%",

                "render": function (data, type, row) {
                    let acoesHtml = '';
                    const status = row.lote_status;


                    // O botão "Editar" só aparece se o lote estiver ativo (não finalizado e não cancelado)
                    if (status === 'EM ANDAMENTO' || status === 'PARCIALMENTE FINALIZADO') {
                        acoesHtml += `<button class="btn btn-warning btn-sm btn-editar-lote" data-id="${data}" title="Editar Lote">Editar</button> `;
                    }

                    // O botão "Finalizar" também só aparece para lotes ativos
                    if (status === 'EM ANDAMENTO' || status === 'PARCIALMENTE FINALIZADO') {
                        acoesHtml += `<button class="btn btn-success btn-sm btn-finalizar-lote" data-id="${data}" title="Finalizar e Gerar Estoque">Finalizar</button> `;
                    }

                    // O menu "Mais Ações" (com Cancelar e Excluir)
                    acoesHtml += `
                        <div class="btn-group d-inline-block">
                            <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Mais
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">`;

                    // A opção "Cancelar" só aparece para lotes ativos
                    if (status === 'EM ANDAMENTO' || status === 'PARCIALMENTE FINALIZADO') {
                        acoesHtml += `<li><a class="dropdown-item btn-cancelar-lote" href="#" data-id="${data}" data-nome="${row.lote_completo_calculado}">Cancelar Lote</a></li>`;
                    }

                    // A opção "Excluir" pode aparecer para todos, mas com um separador se houver outra opção
                    if (status === 'EM ANDAMENTO' || status === 'PARCIALMENTE FINALIZADO') {
                        acoesHtml += `<li><hr class="dropdown-divider"></li>`;
                    }
                    acoesHtml += `<li><a class="dropdown-item text-danger btn-excluir-lote" href="#" data-id="${data}" data-nome="${row.lote_completo_calculado}">Excluir Permanentemente</a></li>`;

                    acoesHtml += `
                    </ul>
                    </div>
                    `;
                    return acoesHtml;
                }
            }
        ],
        //"language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[4, 'desc']]
    });

    // =================================================================
    // FUNÇÕES AUXILIARES
    // =================================================================

    /**
     * Função para carregar os fornecedores no select
     */
    function carregarFornecedores() {
        return $.get('ajax_router.php?action=getFornecedorOptions').done(function (response) {
            if (response.success) {
                const $select = $('#lote_fornecedor_id');
                $select.empty().append('<option value="">Selecione...</option>');
                response.data.forEach(function (fornecedor) {
                    $select.append(
                        $('<option>', {
                            value: fornecedor.ent_codigo,
                            text: `${fornecedor.ent_razao_social} (Cód: ${fornecedor.ent_codigo_interno})`,
                            'data-codigo-interno': fornecedor.ent_codigo_interno
                        })
                    );
                });
                $select.trigger('change.select2'); // Atualiza o Select2 após preencher as opções
            } else {
                console.error('Erro ao carregar fornecedores:', response.message);
            }
        }).fail(function (xhr, status, error) {
            // Lógica de ERRO DE COMUNICAÇÃO
            console.error('Erro na requisição AJAX:', status, error);
        });
    }

    /**
     * Função para carregar os clientes no select
     */
    function carregarClientes() {
        return $.get('ajax_router.php?action=getClienteOptions').done(function (response) {
            if (response.success) {
                const $select = $('#lote_cliente_id');
                $select.empty().append('<option value="">Selecione...</option>');
                response.data.forEach(function (cliente) {
                    $select.append(
                        $('<option>', {
                            value: cliente.ent_codigo,
                            text: `${cliente.ent_razao_social} (Cód: ${cliente.ent_codigo_interno})`,
                            'data-codigo-interno': cliente.ent_codigo_interno
                        })
                    );
                });
                $select.trigger('change.select2');
            }
        });
    }

    /**
    * Função para carregar os produtos no select da Aba 2
    * @param {*} tipoEmbalagemFiltro 
    */
    function carregarProdutos(tipoEmbalagemFiltro = 'Todos') {
        // Usamos $.ajax() para ter mais controle
        return $.ajax({
            url: 'ajax_router.php?action=getProdutoOptions',
            type: 'GET',
            data: { tipo_embalagem: tipoEmbalagemFiltro },
            dataType: 'json'
        })
            .done(function (response) {
                // Bloco .done() -> Executa se a comunicação for bem-sucedida
                if (response.success) {
                    const $selectProduto = $('#item_produto_id');
                    $selectProduto.empty().append('<option value="">Selecione...</option>');
                    response.data.forEach(produto => {
                        const textoDaOpcao = `${produto.prod_descricao} (Cód: ${produto.prod_codigo_interno || 'N/A'})`;
                        const option = new Option(textoDaOpcao, produto.prod_codigo);
                        $(option).data('validade-meses', produto.prod_validade_meses);
                        $(option).data('peso-embalagem', produto.prod_peso_embalagem);
                        $(option).data('codigo-interno', produto.prod_codigo_interno);
                        $selectProduto.append(option);
                    });
                    $selectProduto.trigger('change.select2');
                } else {
                    // Trata o erro de negócio retornado pelo PHP
                    console.error('Erro ao carregar produtos:', response.message);
                }
            })
            .fail(function (xhr, status, error) {
                // Bloco .fail() -> Executa se houver erro de comunicação (servidor offline, erro 500, etc.)
                console.error('Falha na requisição AJAX para carregar produtos:', status, error);
            });
    }

    /**
     * Função para ATUALIZAR o valor do campo "Lote Completo" em tempo real
     * Esta função calcula e atualiza o campo Lote Completo, permitindo que o
     * usuário edite depois, se necessário.
     */
    function atualizarLoteCompleto() {
        const numero = $('#lote_numero').val() || '0000';
        const dataFabStr = $('#lote_data_fabricacao').val();
        const ciclo = $('#lote_ciclo').val() || 'C';
        const viveiro = $('#lote_viveiro').val() || 'V';

        const clienteOption = $('#lote_cliente_id').find(':selected');
        const codCliente = clienteOption.data('codigo-interno') || 'CC';

        let ano = 'YY';
        if (dataFabStr) {
            try {
                ano = new Date(dataFabStr + 'T00:00:00').getFullYear().toString().slice(-2);
            } catch (e) { /* ignora erro de data inválida durante a digitação */ }
        }

        // Junta todas as partes para formar o código final
        const loteCompletoCalculado = `${numero}/${ano}-${ciclo}/${viveiro} ${codCliente}`;


        // Atualiza o valor do campo no formulário
        $('#lote_completo_calculado').val(loteCompletoCalculado);
    }

    /**
     * Função para calcular a validade
     * @param {*} dataFabricacao 
     * @param {*} mesesValidade 
     * @returns 
     */
    function calcularValidadeArredondandoParaCima(dataFabricacao, mesesValidade) {
        const dataCalculada = new Date(dataFabricacao.getTime());
        const diaOriginal = dataCalculada.getDate();
        dataCalculada.setMonth(dataCalculada.getMonth() + mesesValidade);
        if (dataCalculada.getDate() !== diaOriginal) {
            dataCalculada.setDate(1);
            dataCalculada.setMonth(dataCalculada.getMonth() + 1);
        }
        // Formata para 'YYYY-MM-DD' para preencher o input type="date"
        return dataCalculada.toISOString().split('T')[0];
    }

    /**
     * Função para calcular o peso total do item de acordo com a quantidade e peso da embalagem)
     */
    function calcularPesoTotal() {
        const quantidade = parseFloat($('#item_quantidade').val()) || 0;
        const pesoEmbalagem = parseFloat($('#item_produto_id').find(':selected').data('peso-embalagem')) || 0;

        const pesoTotal = quantidade * pesoEmbalagem;

        // Exibe o resultado formatado com 3 casas decimais
        $('#item_peso_total').val(pesoTotal.toFixed(3));
    }

    /**
     * Função para recarregar a lista de itens de um lote
     * @param {*} loteId 
     * @returns 
     */
    function recarregarItensDoLote(loteId) {
        if (!loteId) return; // Não faz nada se não houver um ID

        $.ajax({
            url: 'ajax_router.php?action=buscarLote',
            type: 'POST',
            data: {
                lote_id: loteId,
                csrf_token: csrfToken // Adiciona o token de segurança
            },
            dataType: 'json'
        })
            .done(function (response) {
                // Bloco .done() -> Executa se a comunicação for bem-sucedida
                if (response.success) {
                    // Se a aplicação retornou sucesso, chama a função para redesenhar a tabela
                    renderizarTabelasDeItens(response.data.items);
                } else {
                    // Se a aplicação retornou um erro de negócio (ex: lote não encontrado)
                    console.error('Erro ao recarregar itens do lote:', response.message);
                }
            })
            .fail(function (xhr, status, error) {
                // Bloco .fail() -> Executa se houver erro de comunicação
                console.error('Falha na requisição AJAX para buscar lote:', status, error);
            });
    }

    /**
    * Renderiza os itens de um lote nas tabelas 'Em Produção' e 'Finalizados'.
    * @param {Array} itens - O array de itens vindo do backend.
    */
    function renderizarTabelasDeItens(itens) {
        const $tbodyProducao = $('#tabela-itens-em-producao').empty();
        const $tbodyFinalizados = $('#tabela-itens-finalizados').empty();

        if (!itens || itens.length === 0) {
            $tbodyProducao.html('<tr><td colspan="3" class="text-center">Nenhum item adicionado a este lote.</td></tr>');
            $tbodyFinalizados.html('<tr><td colspan="3" class="text-center">Nenhum item finalizado.</td></tr>');
            return;
        }

        let producaoVazia = true;
        let finalizadosVazio = true;

        itens.forEach(item => {
            // Itens que ainda têm quantidade pendente aparecem na primeira tabela
            const quantidadePendente = parseFloat(item.quantidade_pendente);
            if (quantidadePendente > 0) {
                producaoVazia = false;
                const row = `
                <tr 
                    data-item-id="${item.item_id}" 
                    data-descricao="${item.prod_descricao}" 
                    data-pendente="${quantidadePendente.toFixed(3)}"
                >
                    <td>${item.prod_descricao}</td>
                    <td class="text-end">${quantidadePendente.toFixed(3)}</td>
                    <td class="text-center">
                        <button class="btn btn-warning btn-sm btn-editar-item" data-id="${item.item_id}"><i class="fas fa-pencil-alt"></i> Editar</button>
                        <button class="btn btn-danger btn-sm btn-excluir-item" data-id="${item.item_id}"><i class="fas fa-trash-alt"></i> Excluir</button>
                    </td>
                </tr>
            `;
                $tbodyProducao.append(row);
            }

            // Itens que já tiveram alguma quantidade finalizada aparecem na segunda tabela
            const quantidadeFinalizada = parseFloat(item.item_quantidade_finalizada);
            if (quantidadeFinalizada > 0) {
                finalizadosVazio = false;
                const row = `
                <tr data-item-id="${item.item_id}">
                    <td>${item.prod_descricao}</td>
                    <td class="text-end">${quantidadeFinalizada.toFixed(3)}</td>
                </tr>
            `;
                $tbodyFinalizados.append(row);
            }
        });

        // Mensagens para quando as tabelas estiverem vazias
        if (producaoVazia) {
            $tbodyProducao.html('<tr><td colspan="3" class="text-center">Todos os itens foram finalizados.</td></tr>');
        }
        if (finalizadosVazio) {
            $tbodyFinalizados.html('<tr><td colspan="3" class="text-center">Nenhum item finalizado ainda.</td></tr>');
        }
    }

    /**
    * Valida os campos obrigatórios do cabeçalho do lote.
    * @returns {Array} Uma lista de mensagens de erro. Vazia se tudo estiver OK.
    */
    function validarCabecalhoLote() {
        const erros = [];
        if (!$('#lote_numero').val().trim()) {
            erros.push("O campo 'Número' é obrigatório.");
        }
        if (!$('#lote_data_fabricacao').val()) {
            erros.push("O campo 'Data de Fabricação' é obrigatório.");
        }
        if (!$('#lote_cliente_id').val()) {
            erros.push("O campo 'Cliente' é obrigatório.");
        }
        if (!$('#lote_fornecedor_id').val()) {
            erros.push("O campo 'Fornecedor' é obrigatório.");
        }
        if (!$('#lote_completo_calculado').val()) {
            erros.push("O campo 'Lote Completo' é obrigatório.");
        }
        return erros;
    }

    // Função auxiliar para buscar dados do lote, chamada pelo evento de editar
    function buscarDadosLoteParaEdicao(loteId) {
        $.ajax({
            url: 'ajax_router.php?action=buscarLote',
            type: 'POST',
            data: {
                lote_id: loteId,
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const lote = response.data;
                const header = lote.header;
                $('#lote_id').val(header.lote_id);
                $('#lote_numero').val(header.lote_numero);
                $('#lote_data_fabricacao').val(header.lote_data_fabricacao);
                $('#lote_ciclo').val(header.lote_ciclo);
                $('#lote_viveiro').val(header.lote_viveiro);
                $('#lote_completo_calculado').val(header.lote_completo_calculado);
                $('#lote_fornecedor_id').val(header.lote_fornecedor_id).trigger('change.select2');
                $('#lote_cliente_id').val(header.lote_cliente_id).trigger('change.select2');
                renderizarTabelasDeItens(lote.items);
                $('#btn-salvar-lote').text('Salvar Alterações');
                new bootstrap.Tab($('#aba-info-lote-tab')[0]).show();
                $('#modal-lote-label').text('Editar Lote: ' + header.lote_completo_calculado);
                $('#aba-add-produtos-tab').removeClass('disabled').attr('aria-disabled', 'false');
                $modalLote.modal('show');
            } else {
                notificacaoErro('Erro!', response.message || 'Não foi possível buscar os dados do lote.');
            }
        }).fail(() => notificacaoErro('Erro de Comunicação', 'Falha ao buscar os dados do lote.'));
    }

    // Gatilhos para o cálculo
    $('#item_produto_id').on('change', calcularPesoTotal);
    $('#item_quantidade').on('keyup change', calcularPesoTotal);

    $('#form-lote-header').on('change keyup', 'input, select', function (event) {
        if (event.target.id === 'lote_completo_calculado') {
            return;
        } atualizarLoteCompleto();
    });



    // =================================================================
    // EVENTOS HANDLERS
    // =================================================================

    /**
    * Evento que é acionado QUANDO O MODAL DE LOTE TERMINA DE SER EXIBIDO.
    * Usado para inicializar os plugins Select2 que estão dentro dele.
    */
    $('#modal-lote').on('shown.bs.modal', function () {
        // Inicializa o Select2 para os dropdowns de Fornecedor, Cliente e Produto
        $('#lote_fornecedor_id, #lote_cliente_id, #item_produto_id').select2({
            placeholder: 'Selecione uma opção',
            dropdownParent: $modalLote, // Essencial para funcionar no modal
            theme: "bootstrap-5"
        });
    });

    // Abrir modal para Adicionar Novo Lote
    $('#btn-adicionar-lote-main').on('click', function () {
        $(this).blur();
        // 1. Limpa o formulário e o modal
        $('#form-lote-header')[0].reset();
        $('#lote_id').val('');
        $('#modal-lote-label').text('Adicionar Novo Lote');
        $('#lista-produtos-deste-lote').html('<p class="text-muted">Salve o cabeçalho para poder incluir produtos.</p>');
        $('#mensagem-lote-header').html('');

        // 2. Desabilita a aba de produtos e ativa a primeira
        $('#aba-add-produtos-tab').addClass('disabled').attr('aria-disabled', 'true');
        new bootstrap.Tab($('#aba-info-lote-tab')[0]).show();

        // 3. Ajusta o texto do botão para "Novo Lote"
        $('#btn-salvar-lote').text('Salvar Cabeçalho');

        // 4. Busca o próximo número de lote
        $.get('ajax_router.php?action=getProximoNumeroLote', function (response) {
            if (response.success) {
                $('#lote_numero').val(response.proximo_numero);
                // Agora que temos o número, mandamos calcular o Lote Completo inicial
                atualizarLoteCompleto();
            } else {
                $('#lote_numero').val('Erro!');
            }
        });
        carregarFornecedores(); // Carrega fornecedores ao abrir
        carregarClientes();// Carrega clientes ao abrir
        carregarProdutos(); // Carrega produtos ao abrir
    });

    // Ação para o botão "Editar" na tabela principal de lotes 
    $('#tabela-lotes tbody').on('click', '.btn-editar-lote', function () {
        $(this).closest('.btn-group').find('.dropdown-toggle').blur();
        const loteId = $(this).data('id');
        loteIdAtual = loteId; // Guarda o ID do lote que estamos a editar
        carregarFornecedores().done(() => carregarClientes().done(() => buscarDadosLoteParaEdicao(loteId)));
    });

    // Ação para o botão "Excluir" na tabela PRINCIPAL de lotes
    $('#tabela-lotes tbody').on('click', '.btn-excluir-lote', function (e) {
        $(this).closest('.btn-group').find('.dropdown-toggle').blur();
        e.preventDefault();
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');
        confirmacaoAcao(`Excluir Lote "${loteNome}"?`, 'Esta ação é irreversível.').then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirLote', type: 'POST',
                    data: { lote_id: loteId, csrf_token: csrfToken }, dataType: 'json'
                }).done(response => {
                    if (response.success) {
                        tabelaLotes.ajax.reload(null, false);
                        notificacaoSucesso('Excluído!', response.message);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                }).fail(() => notificacaoErro('Erro de Comunicação', 'Não foi possível excluir o lote.'));
            }
        });
    });

    // Ação para o item de menu "Cancelar Lote"
    $('#tabela-lotes tbody').on('click', '.btn-cancelar-lote', function (e) {
        $(this).closest('.btn-group').find('.dropdown-toggle').blur();
        e.preventDefault();
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        confirmacaoAcao(`Cancelar Lote "${loteNome}"?`, 'O estoque gerado será revertido.').then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=cancelarLote', type: 'POST',
                    data: { lote_id: loteId, csrf_token: csrfToken }, dataType: 'json'
                }).done(response => {
                    if (response.success) {
                        tabelaLotes.ajax.reload(null, false);
                        notificacaoSucesso('Cancelado!', response.message);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                }).fail(() => notificacaoErro('Erro de Comunicação', 'Não foi possível cancelar o lote.'));
            }
        });
    });

    // Ação para o botão "Finalizar" na tabela principal
    $('#tabela-lotes tbody').on('click', '.btn-finalizar-lote', function () {
        $(this).closest('.btn-group').find('.dropdown-toggle').blur();
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        confirmacaoAcao('Finalizar Lote?', 'Mover todos os itens pendentes para o estoque?').then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=finalizarLote', type: 'POST',
                    data: { lote_id: loteId, csrf_token: csrfToken }, dataType: 'json'
                }).done(response => {
                    if (response.success) {
                        tabelaLotes.ajax.reload(null, false);
                        notificacaoSucesso('Sucesso!', response.message);
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                }).fail(() => notificacaoErro('Erro de Comunicação', 'Não foi possível finalizar o lote.'));
            }
        });
    });

    // Evento para ABRIR o modal de confirmação de exclusão do item
    $('#tabela-itens-em-producao').on('click', '.btn-excluir-item', function () {
        $(this).closest('.btn-group').find('.dropdown-toggle').blur();
        const itemId = $(this).data('id');
        const produtoDescricao = $(this).closest('tr').find('td:first').text();

        confirmacaoAcao(`Excluir o item "${produtoDescricao}"?`, 'Esta ação não pode ser desfeita.').then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirLoteItem', type: 'POST',
                    data: { item_id: itemId, csrf_token: csrfToken }, dataType: 'json'
                }).done(response => {
                    if (response.success) {
                        notificacaoSucesso('Item Excluído!');
                        recarregarItensDoLote(loteIdAtual);
                        tabelaLotes.ajax.reload(null, false);
                    } else {
                        notificacaoErro('Erro ao excluir', response.message);
                    }
                }).fail(() => notificacaoErro('Erro de Comunicação', 'Não foi possível excluir o item.'));
            }
        });
    });

    // Ação para o botão "Editar" de um item DENTRO do modal
    $('#tabela-itens-em-producao').on('click', '.btn-editar-item', function () {
        $(this).closest('.btn-group').find('.dropdown-toggle').blur();
        const itemId = $(this).data('id');

        // 1. Reseta o filtro de embalagem para "Todos"
        $('#filtro-todos').prop('checked', true);

        // 2. Recarrega a lista COMPLETA de produtos e ESPERA terminar
        carregarProdutos('Todos').done(function () {

            // 3. AGORA que a lista está completa, busca os dados do item específico
            $.ajax({
                url: 'ajax_router.php?action=getLoteItem',
                type: 'POST',
                data: {
                    item_id: itemId,
                    csrf_token: csrfToken
                },
                dataType: 'json'
            })
                .done(function (response) {
                    if (response.success) {
                        const item = response.data;
                        // Preenche o formulário na Aba 2 com os dados recebidos
                        // 1. Preenche o campo INVISÍVEL com o ID do item
                        $('#item_id').val(item.item_id);
                        // 2. Seleciona o produto correto no dropdown
                        $('#item_produto_id').val(item.item_produto_id).trigger('change');
                        // 3. Preenche a quantidade
                        $('#item_quantidade').val(item.item_quantidade);
                        // 4. Preenche a data de validade
                        $('#item_data_validade').val(item.item_data_validade);

                        // Calcula o peso total ao editar
                        calcularPesoTotal();

                        // Muda o texto do botão para indicar edição
                        $('#btn-incluir-produto').text('Salvar Alterações').removeClass('btn-success').addClass('btn-info');

                        // Leva o usuário para a aba de edição
                        new bootstrap.Tab($('#aba-add-produtos-tab')[0]).show();
                    } else {
                        notificacaoErro('Erro!', response.message || 'Erro ao buscar dados do item.');
                    }
                })
                .fail(function () {
                    notificacaoErro('Erro de Comunicação', 'Não foi possível buscar dados do item.');
                });
        });
    });

    // Evento de mudança no select de produto para calcular a validade
    $('#item_produto_id').on('change', function () {
        const optionSelecionada = $(this).find(':selected');
        const mesesValidade = optionSelecionada.data('validade-meses');
        const dataFabricacaoStr = $('#lote_data_fabricacao').val();

        if (mesesValidade && dataFabricacaoStr) {
            const dataFabricacao = new Date(dataFabricacaoStr + 'T00:00:00');
            const dataValidadeCalculada = calcularValidadeArredondandoParaCima(dataFabricacao, parseInt(mesesValidade));
            $('#item_data_validade').val(dataValidadeCalculada);
        } else {
            $('#item_data_validade').val(''); // Limpa se não tiver regra de validade
        }
    });

    // Evento delegado para os botões de rádio do filtro
    $('#modal-lote').on('change', 'input[name="filtro_tipo_embalagem"]', function () {
        // Pega o valor do rádio selecionado ('Todos', 'PRIMARIA' ou 'SECUNDARIA')
        const filtroSelecionado = $(this).val();
        // Chama a função para recarregar o dropdown com o filtro
        carregarProdutos(filtroSelecionado);
    });

    // Evento do checkbox para liberar a edição da validade
    $('#liberar_edicao_validade').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('#item_data_validade').prop('readonly', !isChecked);
    });

    $('#lista-produtos-deste-lote').on('click', '.btn-imprimir-item', function (e) {
        e.preventDefault();
        const $button = $(this);
        const itemId = $button.data('item-id');
        const clienteId = $('#lote_cliente_id').val(); // Pega o cliente do cabeçalho do lote

        // Salva o conteúdo original do botão e mostra um spinner
        const originalContent = $button.html();
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

        $.ajax({
            url: 'ajax_router.php?action=imprimirEtiquetaItem',
            type: 'POST',
            data: {
                loteItemId: itemId,
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.pdfUrl) {
                window.open(response.pdfUrl, '_blank');
            } else {
                notificacaoErro('Erro ao Gerar Etiqueta', response.message || 'Ocorreu um erro desconhecido.');
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível gerar a etiqueta.');
        }).always(function () {
            // Restaura o botão ao seu estado original
            $button.prop('disabled', false).html(originalContent);
        });
    });

    // Salvar o cabeçalho do lote
    $('#btn-salvar-lote').on('click', function () {
        // 1. Executa a validação primeiro
        const erros = validarCabecalhoLote();

        // 2. Verifica se a lista de erros não está vazia
        if (erros.length > 0) {
            // Monta a mensagem de erro com os itens da lista
            const mensagem = 'Por favor, corrija os seguintes erros:\n- ' + erros.join('\n- ');
            notificacaoErro('Campos Obrigatórios', mensagem);
            return; // Impede o envio do formulário via AJAX
        }

        // 3. Se não houver erros, limpa a mensagem e continua com o AJAX
        $('#mensagem-lote-header').html('');

        const formData = new FormData($('#form-lote-header')[0]);

        $.ajax({
            url: 'ajax_router.php?action=salvarLoteHeader',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    notificacaoSucesso('Sucesso!', response.message);
                    tabelaLotes.ajax.reload(null, false);
                    if (response.novo_lote_id) {
                        //$('#lote_id').val(response.novo_lote_id);
                        loteIdAtual = response.novo_lote_id;
                        $('#lote_id').val(loteIdAtual);
                        $('#aba-add-produtos-tab').removeClass('disabled').attr('aria-disabled', 'false');
                        new bootstrap.Tab($('#aba-add-produtos-tab')[0]).show();
                    }
                } else {
                    notificacaoErro('Erro!', response.message);
                }
            })

            .fail(function (xhr, status, error) {
                notificacaoErro('Erro de Comunicação', 'Não foi possível salvar o cabeçalho do lote.');

            });
    });

    // Ação do botão "Incluir Produto" / "Salvar Alterações"
    $('#btn-incluir-produto').on('click', function () {
        const $button = $(this); // Guarda a referência do botão
        //const loteId = $('#lote_id').val();
        const loteId = loteIdAtual

        if (!loteId) {
            notificacaoErro('Erro', 'ID do lote não encontrado. Salve o cabeçalho primeiro.');
            return;
        }

        const formData = new FormData($('#form-adicionar-produto')[0]);
        formData.append('lote_id', loteId); // Adiciona o lote_id diretamente ao FormData

        // Desabilita o botão para prevenir cliques duplos
        $button.prop('disabled', true).text('Salvando...');

        $.ajax({
            url: 'ajax_router.php?action=salvarLoteItem',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    notificacaoSucesso('Item Salvo!');
                    // 1. Limpa o formulário e reseta o botão para o modo "Incluir"
                    $('#btn-cancelar-inclusao').trigger('click');

                    // 2. Chama a função para buscar e redesenhar a lista completa de itens
                    recarregarItensDoLote(loteId);

                    // 3. Volta para a primeira aba para o usuário ver o resultado
                    new bootstrap.Tab($('#aba-info-lote-tab')[0]).show();

                } else {
                    notificacaoErro('Erro!', response.message);
                }
            })
            .fail(function () {
                notificacaoErro('Erro de Comunicação', 'Não foi possível salvar o item.');
            })
            .always(function () {
                $button.prop('disabled', false).text('Incluir Produto');
            });
    });

    // Ação do botão "Limpar / Cancelar" na Aba 2
    $('#btn-cancelar-inclusao').on('click', function () {
        // 1. Limpa o formulário e o ID do item
        $('#form-adicionar-produto')[0].reset();
        $('#item_id').val('');
        $('#item_produto_id').val(null).trigger('change');
        $('#item_peso_total').val('');

        // 2. Restaura o botão para o modo de inclusão
        $('#btn-incluir-produto').text('Incluir Produto').removeClass('btn-info').addClass('btn-success');

        // 3. Volta para a primeira aba (Opcional, mas melhora a experiência)
        new bootstrap.Tab($('#aba-info-lote-tab')[0]).show();
    });

    $('#modal-lote').on('hidden.bs.modal', function () {
        // Quando o modal principal é fechado, retorna o foco para o corpo do documento.
        $('body').focus();
    });

    // Este código é executado quando se clica no botão "Finalizar Lote..."
    $('#modal-lote').on('click', '#btn-finalizar-lote', function () {
        const $tbodyProducao = $('#tabela-itens-em-producao');
        const $tbodyFinalizacao = $('#itens-para-finalizar-tbody').empty();

        const itensParaFinalizar = [];
        // Itera sobre cada linha da tabela "Itens em Produção" para obter os dados
        $tbodyProducao.find('tr').each(function () {
            const $row = $(this);
            const itemId = $row.data('item-id');
            if (itemId) { // Garante que não estamos a pegar numa linha de "nenhum item"
                itensParaFinalizar.push({
                    id: itemId,
                    descricao: $row.data('descricao'),
                    pendente: $row.data('pendente')
                });
            }
        });

        if (itensParaFinalizar.length === 0) {
            notificacaoErro('Atenção', 'Não há itens pendentes para finalizar.');
            return;
        }

        // Constrói a tabela dentro do novo modal
        itensParaFinalizar.forEach(item => {
            const rowHtml = `
                <tr>
                    <td>${item.descricao}</td>
                    <td class="text-end">${item.pendente}</td>
                    <td>
                        <input 
                            type="number" 
                            class="form-control form-control-sm text-end" 
                            data-item-id="${item.id}"
                            name="quantidades[${item.id}]"
                            min="0"
                            max="${item.pendente}"
                            step="0.001"
                            placeholder="0.000"
                        >
                    </td>
                </tr>
            `;
            $tbodyFinalizacao.append(rowHtml);
        });

        // Abre o modal de finalização
        $('#modal-finalizacao-parcial').modal('show');
    });

    // Este código é executado quando se clica no botão final de confirmação
    $('#btn-confirmar-finalizacao-parcial').on('click', function () {
        const $button = $(this);
        const $statusDiv = $('#mensagem-finalizacao-parcial');
        const loteId = $('#lote_id').val(); // Pega o ID do lote que está aberto

        const itensAFinalizar = [];
        // Itera sobre cada input para obter as quantidades digitadas
        $('#itens-para-finalizar-tbody input').each(function () {
            const $input = $(this);
            const quantidade = parseFloat($input.val());

            // Só adiciona à lista se uma quantidade válida (> 0) foi inserida
            if (quantidade > 0) {
                itensAFinalizar.push({
                    item_id: $input.data('item-id'),
                    quantidade: quantidade
                });
            }
        });

        if (itensAFinalizar.length === 0) {
            notificacaoErro('Atenção', 'Nenhuma quantidade foi inserida para finalização.');
            return;
        }

        // Feedback visual
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> A processar...');
        $statusDiv.html('');

        // Chamada AJAX para o backend
        $.ajax({
            url: 'ajax_router.php?action=finalizarLoteParcialmente', // A NOSSA NOVA ROTA
            type: 'POST',
            dataType: 'json',
            data: {
                lote_id: loteId,
                itens: itensAFinalizar,
                csrf_token: $('meta[name="csrf-token"]').attr('content')
            }
        }).done(function (response) {
            if (response.success) {
                // Sucesso! Fecha o modal e recarrega os dados do lote principal
                $('#modal-finalizacao-parcial').modal('hide');

                // Simula um clique no botão de editar da tabela principal para recarregar o modal do lote
                // com os dados atualizados (uma forma simples de recarregar)
                $(`#tabela-lotes .btn-editar-lote[data-id='${loteId}']`).click();

                notificacaoSucesso('Sucesso!', response.message);
            } else {
                notificacaoErro('Erro!', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível processar a finalização.');
        }).always(function () {
            $button.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i> Confirmar Finalização e Gerar Estoque');
        });
    });
});
