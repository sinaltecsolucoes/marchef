// /public/js/lotes_novo.js
$(document).ready(function () {

    // --- Seletores e Variáveis Globais ---
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalLoteNovo = $('#modal-lote-novo');
    const $modalFinalizarLote = $('#modal-finalizar-lote');
    const $tabelaLotes = $('#tabela-lotes-novo');
    const $tabelaItensProducao = $('#tabela-itens-producao-novo');
    const $tabelaItensEmbalagem = $('#tabela-itens-embalagem-novo');
    const $formHeader = $('#form-lote-novo-header');
    const $modalImpressao = $('#modal-imprimir-etiqueta');
    let tabelaLotesNovo, loteIdAtual;

    // --- Inicialização da Tabela DataTables ---
    tabelaLotesNovo = $('#tabela-lotes-novo').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": "ajax_router.php?action=listarLotesNovos",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            {
                "data": "lote_completo_calculado",
                "className": "text-center align-middle",
            },
            {
                "data": "fornecedor_razao_social",
                "className": "align-middle",
            },
            {
                "data": "lote_data_fabricacao",
                "className": "text-center align-middle",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "lote_status",
                "className": "text-center align-middle",
                "render": function (data) {
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
                "className": "text-center align-middle",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data);
                    return date.toLocaleString('pt-BR');
                }
            },
            {
                "data": "lote_id",
                "orderable": false,
                "className": "text-center align-middle",

                "render": function (data, type, row) {
                    const status = row.lote_status;
                    const loteId = row.lote_id;
                    const loteNome = row.lote_completo_calculado;
                    let acoesHtml = '';
                    let menuItens = '';

                    // Captura o pageType no início para ser usado em toda a função
                    const pageType = $('body').data('page-type');

                    // Define os botões principais com base no status
                    if (status === 'EM ANDAMENTO' || status === 'PARCIALMENTE FINALIZADO') {
                        acoesHtml += `<button class="btn btn-warning btn-sm btn-editar-lote-novo me-1" data-id="${loteId}" title="Editar Lote"><i class="fas fa-pencil-alt me-1"></i>Editar</button>`;

                        if (pageType === 'lotes_embalagem') {
                            acoesHtml += `<button class="btn btn-success btn-sm btn-finalizar-lote-novo me-1" data-id="${loteId}" data-nome="${loteNome}" title="Finalizar Lote"><i class="fas fa-check-circle me-1"></i>Finalizar</button>`;
                        }

                        // Apenas adiciona o item "Cancelar" na tela de Produção
                        if (pageType === 'lotes_producao') {
                            menuItens += `<li><a class="dropdown-item btn-cancelar-lote" href="#" data-id="${loteId}" data-nome="${loteNome}">Cancelar Lote</a></li>`;
                        }

                    } else if (status === 'FINALIZADO') {
                        acoesHtml += `<button class="btn btn-info btn-sm btn-editar-lote-novo me-1" data-id="${loteId}" title="Visualizar Lote"><i class="fas fa-search me-1"></i>Visualizar</button>`;

                        if (pageType === 'lotes_embalagem') {
                            menuItens += `<li><a class="dropdown-item btn-reabrir-lote" href="#" data-id="${loteId}" data-nome="${loteNome}">Reabrir Lote</a></li>`;
                        }


                    } else if (status === 'CANCELADO') {
                        acoesHtml += `<button class="btn btn-secondary btn-sm btn-editar-lote-novo me-1" data-id="${loteId}" title="Visualizar Lote"><i class="fas fa-search me-1"></i>Visualizar</button>`;
                        menuItens += `<li><a class="dropdown-item text-success btn-reativar-lote-novo" href="#" data-id="${loteId}" data-nome="${loteNome}">Reativar Lote</a></li>`;
                    }

                    // Apenas adiciona o item "Excluir" na tela de Produção
                    if (pageType === 'lotes_producao') {
                        if (menuItens !== '') {
                            menuItens += `<li><hr class="dropdown-divider"></li>`;
                        }
                        menuItens += `<li><a class="dropdown-item text-danger btn-excluir-lote" href="#" data-id="${loteId}" data-nome="${loteNome}">Excluir Permanentemente</a></li>`;
                    }

                    // Constrói o menu dropdown APENAS se houver itens nele
                    if (menuItens) {
                        acoesHtml += `
                    <div class="btn-group d-inline-block">
                        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Mais
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            ${menuItens}
                        </ul>
                    </div>`;
                    }
                    return acoesHtml;
                }

            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[4, 'desc']]
    });

    // --- Funções Auxiliares ---
    function carregarFornecedores() {
        return $.get('ajax_router.php?action=getFornecedorOptions').done(function (response) {
            if (response.success) {
                const $select = $('#lote_fornecedor_id_novo');
                $select.empty().append('<option value="">Selecione...</option>');
                response.data.forEach(function (fornecedor) {
                    $select.append(
                        $('<option>', {
                            value: fornecedor.ent_codigo,
                            text: `${fornecedor.nome_display} (Cód: ${fornecedor.ent_codigo_interno})`,
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

    // Função para carregar Clientes 
    function carregarClientes() {
        return $.get('ajax_router.php?action=getClienteOptions', { term: '' }).done(function (response) {
            if (response.data) {
                const $select = $('#lote_cliente_id_novo');
                $select.empty().append('<option value="">Selecione...</option>');

                response.data.forEach(function (cliente) {
                    $select.append(
                        $('<option>', {
                            value: cliente.id,
                            text: cliente.text,
                            'data-codigo-interno': cliente.ent_codigo_interno
                        })
                    );
                });
                $select.trigger('change.select2');
            } else {
                console.error('Erro ao carregar clientes:', response.message);
            }
        }).fail(function (xhr, status, error) {
            console.error('Erro na requisição AJAX (carregarClientes):', status, error);
        });
    }

    /**
    * Função para carregar os produtos no select da Aba 2
    * @param {*} tipoEmbalagemFiltro 
    */
    function carregarProdutosPrimarios() {
        return $.ajax({
            url: 'ajax_router.php?action=getProdutoOptions',
            type: 'GET',
            data: { tipo_embalagem: 'PRIMARIA' },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    const $selectProduto = $('#item_prod_produto_id_novo');
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
                console.error('Falha na requisição AJAX para carregar produtos:', status, error);
            });
    }

    /**
     * Função para ATUALIZAR o valor do campo "Lote Completo" em tempo real
     */
    function atualizarLoteCompletoNovo() {
        const numero = $('#lote_numero_novo').val() || '0000';
        const dataFabStr = $('#lote_data_fabricacao_novo').val();
        const ciclo = $('#lote_ciclo_novo').val() || 'C';
        const viveiro = $('#lote_viveiro_novo').val() || 'V';

        // Lógica para pegar o código interno do cliente selecionado (se houver)
        // Precisaremos adicionar 'data-codigo-interno' ao carregar os clientes
        const clienteOption = $('#lote_cliente_id_novo').find(':selected');
        const codCliente = clienteOption.data('codigo-interno') || 'CC';

        let ano = 'YY';
        if (dataFabStr) {
            try {
                ano = new Date(dataFabStr + 'T00:00:00').getFullYear().toString().slice(-2);
            } catch (e) { /* ignora erro de data inválida durante a digitação */ }
        }
        const loteCompletoCalculado = `${numero}/${ano}-${ciclo}/${viveiro} ${codCliente}`;
        $('#lote_completo_calculado_novo').val(loteCompletoCalculado);
    }

    /**
    * Valida os campos obrigatórios do cabeçalho do lote.
    * @returns {Array} Uma lista de mensagens de erro. Vazia se tudo estiver OK.
    */
    function validarCabecalhoLote() {
        const erros = [];
        if (!$('#lote_numero_novo').val().trim()) {
            erros.push("O campo 'Número' é obrigatório.");
        }
        if (!$('#lote_data_fabricacao_novo').val()) {
            erros.push("O campo 'Data de Fabricação' é obrigatório.");
        }
        if (!$('#lote_cliente_id_novo').val()) {
            erros.push("O campo 'Cliente' é obrigatório.");
        }
        if (!$('#lote_fornecedor_id_novo').val()) {
            erros.push("O campo 'Fornecedor' é obrigatório.");
        }
        if (!$('#lote_completo_calculado_novo').val()) {
            erros.push("O campo 'Lote Completo' é obrigatório.");
        }
        return erros;
    }

    // Função auxiliar para buscar dados do lote, chamada pelo evento de editar
    function buscarDadosLoteParaEdicao(loteId) {
        $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteId, csrf_token: csrfToken }, // Envia 'lote_id'
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const lote = response.data;
                const header = lote.header;

                $('#lote_fornecedor_id_novo, #lote_cliente_id_novo').select2({
                    placeholder: 'Selecione uma opção',
                    dropdownParent: $modalLoteNovo,
                    theme: "bootstrap-5"
                });

                $('#lote_id_novo').val(header.lote_id);
                $('#lote_numero_novo').val(header.lote_numero);
                $('#lote_data_fabricacao_novo').val(header.lote_data_fabricacao);
                $('#lote_ciclo_novo').val(header.lote_ciclo);
                $('#lote_viveiro_novo').val(header.lote_viveiro);
                $('#lote_completo_calculado_novo').val(header.lote_completo_calculado);

                // Define os valores e dispara o 'change' para o Select2 atualizar
                $('#lote_fornecedor_id_novo').val(header.lote_fornecedor_id).trigger('change.select2');
                $('#lote_cliente_id_novo').val(header.lote_cliente_id).trigger('change.select2');

                $('#btn-salvar-lote-novo-header').html('<i class="fas fa-save me-1"></i> Salvar Alterações');
                $('#modal-lote-novo-label').text('Editar Lote: ' + header.lote_completo_calculado);

                // Habilita as outras abas para navegação
                $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').removeClass('disabled');
                new bootstrap.Tab($('#aba-info-lote-novo-tab')[0]).show();

                // Determina o estado de "somente leitura" a partir da resposta AJAX
                const status = response.data.header.lote_status;
                const pageType = $('body').data('page-type');

                // Define se o modal INTEIRO será somente leitura (lotes finalizados/cancelados)
                const isReadOnlyGlobal = (status === 'FINALIZADO' || status === 'CANCELADO');

                $.when(recarregarItensProducao(loteId), recarregarItensEmbalagem(loteId)).done(function () {
                    // 1. Aplica o modo de leitura global se o lote estiver fechado
                    configurarModalModoLeitura(isReadOnlyGlobal);

                    // 2. Aplica a lógica específica da página
                    if (pageType === 'lotes_embalagem') {

                        // Se o lote estiver ABERTO, desabilita os formulários das abas 1 e 2
                        if (!isReadOnlyGlobal) {
                            $('#form-lote-novo-header').find('input, select').prop('disabled', true);
                            $('#btn-salvar-lote-novo-header').hide();
                            $('#form-lote-novo-producao').find('input, select').prop('disabled', true);
                            $('#form-lote-novo-producao').find('button').hide();
                        }

                        // 3. SEMPRE ativa a aba de embalagem nesta página
                        new bootstrap.Tab($('#aba-embalagem-novo-tab')[0]).show();
                    }
                });

                $modalLoteNovo.modal('show');
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
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
    * Configura o modal de lote para modo de edição ou apenas visualização.
    * @param {boolean} isReadOnly - True para modo de visualização, false para edição.
    */
    function configurarModalModoLeitura(isReadOnly) {
        // Seleciona todos os formulários e botões dentro do modal novo
        const $forms = $modalLoteNovo.find('form');
        const $botoesDeAcao = $modalLoteNovo.find('#btn-salvar-lote-novo-header, #btn-adicionar-item-producao, #btn-adicionar-item-embalagem, #btn-cancelar-edicao-producao, #btn-cancelar-edicao-embalagem');

        // Seleciona os CABEÇALHOS das colunas de ações
        const $colunasDeAcoes = $modalLoteNovo.find('.coluna-acoes');

        // Desabilita/habilita todos os campos de input, select, etc.
        $forms.find('input, select, textarea').prop('disabled', isReadOnly);

        // Mostra ou esconde os botões de ação (Salvar, Adicionar, etc.)
        $botoesDeAcao.toggle(!isReadOnly);

        // Mostra ou esconde a coluna de ações inteira (cabeçalho e corpo)
        $colunasDeAcoes.toggle(!isReadOnly);

        // Se estiver em modo de leitura, muda o texto do botão de editar principal
        if (isReadOnly) {
            $('.btn-editar-lote-novo[data-id="' + loteIdAtual + '"]').html('<i class="fas fa-search"></i> Visualizar');
            $('#modal-lote-novo-label').text('Visualizar Lote: ' + $('#lote_completo_calculado_novo').val());
        }
    }

    /**
     * Busca os itens de produção de um lote e redesenha a tabela na Aba 2.
     * @param {number} loteId O ID do lote a ser consultado.
     */
    function recarregarItensProducao(loteId) {
        if (!loteId) return;

        const $tbody = $('#tabela-itens-producao-novo');
        $tbody.html('<tr><td colspan="5" class="text-center">A carregar itens...</td></tr>');

        return $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            $tbody.empty();
            if (response.success && response.data.producao.length > 0) {
                response.data.producao.forEach(item => {
                    const rowHtml = `
                        <tr>
                            <td class="align-middle font-small">${item.prod_descricao}</td>
                            <td class="text-center align-middle font-small">${parseFloat(item.item_prod_quantidade).toFixed(3)}</td>
                            <td class="text-center align-middle font-small">${parseFloat(item.item_prod_saldo).toFixed(3)}</td>
                            <td class="text-center align-middle font-small">${new Date(item.item_prod_data_validade + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                            <td class="text-center align-middle coluna-acoes-lote">
                                <button class="btn btn-warning btn-sm btn-editar-item-producao" 
                                            data-id="${item.item_prod_id}"
                                            data-produto-id="${item.item_prod_produto_id}"
                                            data-quantidade="${item.item_prod_quantidade}"
                                            data-validade="${item.item_prod_data_validade}"
                                            title="Editar Item"><i class="fas fa-pencil-alt me-1"></i>Editar</button>
                                <button class="btn btn-danger btn-sm btn-excluir-item-producao" data-id="${item.item_prod_id}"title="Excluir Item"><i class="fas fa-trash-alt me-1"></i>Excluir</button>
                                <button class="btn btn-info btn-sm btn-imprimir-etiqueta-producao" data-id="${item.item_prod_id}" title="Imprimir Etiqueta"><i class="fas fa-print me-1"></i>Etiqueta</button>
                            </td>
                        </tr>
                    `;
                    $tbody.append(rowHtml);
                });
            } else {
                $tbody.html('<tr><td colspan="5" class="text-center text-muted">Nenhum item de produção adicionado a este lote.</td></tr>');
            }
        });
    }

    /**
     * Busca os itens de embalagem de um lote e redesenha a tabela na Aba 3.
     * @param {number} loteId O ID do lote a ser consultado.
     */
    function recarregarItensEmbalagem(loteId) {
        if (!loteId) return;

        const $tbody = $('#tabela-itens-embalagem-novo');
        $tbody.html('<tr><td colspan="5" class="text-center">A carregar itens...</td></tr>');

        return $.ajax({
            url: `ajax_router.php?action=getItensEmbalagemNovo&lote_id=${loteId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            $tbody.empty();
            if (response.success && response.data.length > 0) {
                response.data.forEach(item => {
                    const rowHtml = `
                    <tr>
                        <td class="align-middle font-small">${item.produto_secundario_nome}</td>
                        <td class="text-center align-middle font-small">${parseInt(item.item_emb_qtd_sec)}</td>
                        <td class="align-middle font-small">${item.produto_primario_nome}</td>
                        <td class="text-center align-middle font-small">${parseFloat(item.item_emb_qtd_prim_cons).toFixed(0)}</td>
                        <td class="text-center align-middle coluna-acoes-lote">
                            <button class="btn btn-warning btn-sm btn-editar-item-embalagem" 
                                        data-id="${item.item_emb_id}"
                                        data-primario-item-id="${item.item_emb_prod_prim_id}"
                                        data-secundario-prod-id="${item.item_emb_prod_sec_id}"
                                        data-quantidade="${item.item_emb_qtd_sec}"
                                        data-consumo="${item.item_emb_qtd_prim_cons}"
                                        title="Editar Item"><i class="fas fa-pencil-alt me-1"></i>Editar</button>
                            <button class="btn btn-danger btn-sm btn-excluir-item-embalagem" data-id="${item.item_emb_id}" title="Excluir Item"><i class="fas fa-trash-alt me-1"></i>Excluir</button>
                            <button class="btn btn-info btn-sm btn-imprimir-etiqueta-embalagem" data-id="${item.item_emb_id}" title="Imprimir Etiqueta"><i class="fas fa-print me-1"></i>Etiqueta</button>
                            </td>
                    </tr>
                `;
                    $tbody.append(rowHtml);
                });
            } else {
                $tbody.html('<tr><td colspan="5" class="text-center text-muted">Nenhum item de embalagem adicionado a este lote.</td></tr>');
            }
        });
    }

    /**
     * Função para carregar os itens de produção do lote atual (com saldo) para o select.
     * @param {number} loteId O ID do lote atual.
     */
    function carregarItensProducaoParaSelecao(loteId) {
        return $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo', // Reutilizamos a busca do lote
            type: 'POST',
            data: { lote_id: loteId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const $select = $('#item_emb_prod_prim_id_novo');
                $select.empty().append('<option value="">Selecione...</option>');
                response.data.producao.forEach(item => {
                    if (parseFloat(item.item_prod_saldo) > 0) { // Mostra apenas itens com saldo
                        const texto = `${item.prod_descricao} (Saldo: ${item.item_prod_saldo})`;
                        // O value aqui deve ser o ID do item de produção, não do produto!
                        const option = new Option(texto, item.item_prod_id);
                        $select.append(option);
                    }
                });
                $select.trigger('change.select2');
            }
        });
    }

    function calcularConsumoEmbalagem() {
        const $form = $('#form-lote-novo-embalagem');
        const $selectPrimario = $('#item_emb_prod_prim_id_novo');
        const $selectSecundario = $('#item_emb_prod_sec_id_novo');
        const $qtdInput = $('#item_emb_qtd_sec_novo');
        const $feedback = $('#feedback-consumo-embalagem');
        const $btnAdicionar = $('#btn-adicionar-item-embalagem');

        // Verifica se estamos em modo de edição
        const isEditing = !!$('#item_emb_id_novo').val();
        const consumoOriginal = parseFloat($form.data('consumo-original')) || 0;

        const saldoTexto = $selectPrimario.find('option:selected').text();
        const match = saldoTexto.match(/Saldo: ([\d\.]+)/);
        const saldoAtualDropdown = match ? parseFloat(match[1]) : 0;


        // Se estivermos a editar, o saldo disponível para o cálculo é o saldo atual MAIS o que o item já tinha consumido.
        const saldoParaCalculo = isEditing ? (saldoAtualDropdown + consumoOriginal) : saldoAtualDropdown;

        const unidadesPorEmbalagem = parseFloat($selectSecundario.find('option:selected').data('unidades-primarias')) || 0;
        const quantidade = parseInt($qtdInput.val()) || 0;

        if (saldoParaCalculo === 0 || unidadesPorEmbalagem === 0 || quantidade === 0) {
            $feedback.text('Preencha os campos para calcular o consumo.').removeClass('text-danger fw-bold').addClass('text-muted');
            $btnAdicionar.prop('disabled', true);
            return;
        }

        const consumoCalculado = quantidade * unidadesPorEmbalagem;
        const saldoRestante = saldoParaCalculo - consumoCalculado;

        if (consumoCalculado > saldoParaCalculo) {
            $feedback.html(`Consumo: <strong class="text-danger">${consumoCalculado.toFixed(3)}</strong> (Saldo insuficiente! Saldo disponível para a operação: ${saldoParaCalculo.toFixed(3)})`)
                .removeClass('text-muted').addClass('text-danger fw-bold');
            $btnAdicionar.prop('disabled', true);
        } else {
            $feedback.html(`Consumo: <strong>${consumoCalculado.toFixed(3)}</strong> (Saldo restante: ${saldoRestante.toFixed(3)})`)
                .removeClass('text-danger fw-bold').addClass('text-muted');
            $btnAdicionar.prop('disabled', false);
        }
    }

    /**
    * Reseta completamente o formulário de produção para o seu estado inicial.
    */
    function resetarFormularioProducao() {
        // 1. Limpa todos os campos do formulário
        $('#form-lote-novo-producao')[0].reset();

        // 2. Limpa campos específicos e reseta controles
        $('#item_prod_id_novo').val(''); // Limpa o ID oculto do item
        $('#item_prod_produto_id_novo').val(null).trigger('change'); // Limpa o Select2
        $('#btn-adicionar-item-producao').html('<i class="fas fa-plus me-1"></i>Adicionar Item'); // Restaura o texto do botão

        // 3. Garante que o campo de data de validade volte ao estado bloqueado
        $('#liberar_edicao_validade_novo').prop('checked', false);
        $('#item_prod_data_validade_novo').prop('readonly', true);
    }

    // --- Event Handlers ---

    // Evento para o botão "Adicionar Novo Lote"
    $('#btn-adicionar-lote-novo').on('click', function () {
        const pageType = $('body').data('page-type');

        // Garante que todas as abas estejam visíveis antes de aplicar a lógica
        $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').show();

        if (pageType === 'lotes_producao') {
            // Se a página for de PRODUÇÃO, esconde a aba de embalagem
            $('#aba-embalagem-novo-tab').hide();
        }

        configurarModalModoLeitura(false);

        // 1. Limpa o formulário principal (cabeçalho)
        $formHeader[0].reset();
        $('#lote_id_novo').val('');
        $('#modal-lote-novo-label').text('Adicionar Novo Lote');
        $modalLoteNovo.modal('show');

        // 2. Limpa os formulários das outras abas
        $('#form-lote-novo-producao')[0].reset();
        $('#form-lote-novo-embalagem')[0].reset();

        // 2.a - Garante que o campo de data de validade comece bloqueado e o switch desmarcado.
        $('#liberar_edicao_validade_novo').prop('checked', false);
        $('#item_prod_data_validade_novo').prop('readonly', true);

        // 3. Limpa as tabelas de itens e restaura a mensagem padrão
        $('#tabela-itens-producao-novo').empty().html('<tr><td colspan="5" class="text-center text-muted">Salve o cabeçalho do lote para adicionar itens.</td></tr>');
        $('#tabela-itens-embalagem-novo').empty().html('<tr><td colspan="5" class="text-center text-muted">Adicione itens de produção para poder embalar.</td></tr>');

        // 4. Garante que os botões de ação voltem ao estado inicial
        $('#btn-adicionar-item-producao').html('<i class="fas fa-plus me-1"></i>Adicionar Item');
        $('#btn-adicionar-item-embalagem').html('<i class="fas fa-plus me-1"></i>Adicionar Item');

        // 5. Remove botões de "cancelar edição" que podem ter sido adicionados dinamicamente
        $('#btn-cancelar-edicao-producao').show(); // Garante que o botão de limpar padrão esteja visível
        $('#btn-cancelar-edicao-embalagem').remove(); // Remove o botão de cancelar da aba de embalagem

        // 6. Inicializa os dropdowns do modal
        $('#lote_fornecedor_id_novo, #lote_cliente_id_novo, #item_prod_produto_id_novo').select2({
            placeholder: 'Selecione uma opção',
            dropdownParent: $modalLoteNovo,
            theme: "bootstrap-5"
        });

        // 7. Carrega os dados para os dropdowns
        carregarFornecedores();
        carregarClientes();
        carregarProdutosPrimarios();

        // 8. Busca o próximo número de lote
        $.get('ajax_router.php?action=getProximoNumeroLoteNovo', function (response) {
            if (response.success) {
                $('#lote_numero_novo').val(response.proximo_numero);
                atualizarLoteCompletoNovo(); // Calcula o lote inicial
            } else {
                $('#lote_numero').val('Erro!');
            }
        });

        // 9. Desabilita as abas de produção e embalagens e ativa a primeira
        new bootstrap.Tab($('#aba-info-lote-novo-tab')[0]).show();
        $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').addClass('disabled');

        $modalLoteNovo.modal('show');
    });

    // Evento de clique para o botão "Adicionar Item"
    $('#btn-adicionar-item-producao').on('click', function () {

        const dataValidade = $('#item_prod_data_validade_novo').val();
        if (!dataValidade) {
            notificacaoErro('Validação', 'Por favor, insira uma Data de Validade para este produto.');
            return; // Impede a execução do resto da função
        }

        const loteId = loteIdAtual; // A variável global que guarda o ID do lote
        const itemId = $('#item_prod_id_novo').val(); // Pega o ID do campo hidden
        const isEditing = !!itemId; // Converte para booleano: true se tiver ID, false se não
        const action = isEditing ? 'atualizarItemProducaoNovo' : 'adicionarItemProducaoNovo';
        const formData = new FormData($('#form-lote-novo-producao')[0]);

        if (!isEditing) {
            // Se estiver criando, adiciona o ID do lote
            formData.append('item_prod_lote_id', loteId);
        }
        formData.append('csrf_token', csrfToken);

        $.ajax({
            url: `ajax_router.php?action=${action}`,
            type: 'POST',
            data: formData,
            processData: false, contentType: false, dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                notificacaoSucesso('Sucesso!', 'Item de produção adicionado ao lote.');

                // Limpa o formulário para a próxima adição
                $('#btn-cancelar-edicao-producao').trigger('click'); // Dispara o evento de limpar/cancelar

                // Recarrega a tabela de itens de produção
                recarregarItensProducao(loteId);
                carregarItensProducaoParaSelecao(loteId); // Atualiza o dropdown da aba 3
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    });

    $('#btn-adicionar-item-embalagem').on('click', function () {
        const loteId = loteIdAtual; // Usamos a variável global
        const itemId = $('#item_emb_id_novo').val();
        const isEditing = !!itemId;

        const action = isEditing ? 'atualizarItemEmbalagemNovo' : 'adicionarItemEmbalagemNovo';
        const formData = new FormData($('#form-lote-novo-embalagem')[0]);

        if (!isEditing) {
            formData.append('item_emb_lote_id', loteId);
        }

        formData.append('csrf_token', csrfToken);

        $.ajax({
            url: `ajax_router.php?action=${action}`,
            type: 'POST',
            data: formData,
            processData: false, contentType: false, dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                notificacaoSucesso('Sucesso!', 'Item de embalagem adicionado.');

                // Limpa o formulário de embalagem para a próxima operação.
                // O .reset() limpa o campo de quantidade.
                $('#form-lote-novo-embalagem')[0].reset();

                // Limpa o dropdown de produto primário, o que automaticamente
                // irá limpar e desabilitar o dropdown de produto secundário por causa do evento 'change' que já criámos.
                $('#item_emb_prod_prim_id_novo').val(null).trigger('change');

                // Remove o botão de cancelar se ele existir (do modo de edição)
                $('#btn-cancelar-edicao-embalagem').remove();

                $('#form-lote-novo-embalagem').removeData('consumo-original');
                $('#btn-cancelar-edicao-embalagem').trigger('click'); // Dispara o cancelamento para limpar o form

                // ATUALIZAR TUDO:
                recarregarItensEmbalagem(loteId); // Atualiza a nova tabela de embalagens
                recarregarItensProducao(loteId);   // Atualiza a tabela da Aba 2, pois o saldo mudou!
                carregarItensProducaoParaSelecao(loteId); // Recarrega o dropdown com o saldo atualizado
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    });

    // Evento para o botão "Salvar Cabeçalho"
    $('#btn-salvar-lote-novo-header').on('click', function () {
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

        const formData = new FormData($formHeader[0]);
        formData.append('csrf_token', csrfToken);
        $.ajax({
            url: 'ajax_router.php?action=salvarLoteNovoHeader',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                notificacaoSucesso('Sucesso!', 'Cabeçalho do lote salvo com sucesso.');
                tabelaLotesNovo.ajax.reload(null, false);

                loteIdAtual = response.novo_lote_id; // Guarda o ID do lote
                $('#lote_id_novo').val(loteIdAtual);

                // Habilita as próximas abas
                $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').removeClass('disabled');
                new bootstrap.Tab($('#aba-producao-novo-tab')[0]).show(); // Move o utilizador para a próxima aba
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    });

    $('#btn-cancelar-edicao-producao').on('click', function () {
        resetarFormularioProducao();
    });

    $('#btn-confirmar-finalizacao').on('click', function () {
        const $modal = $('#modal-finalizar-lote');
        const loteId = $modal.data('lote-id');
        const itensParaFinalizar = [];

        // Percorre cada linha da tabela para coletar os dados
        $('#tabela-itens-para-finalizar tr').each(function () {
            const itemId = $(this).data('item-id');
            const quantidade = parseFloat($(this).find('.qtd-a-finalizar').val()) || 0;

            if (itemId && quantidade > 0) {
                itensParaFinalizar.push({
                    item_id: itemId,
                    quantidade: quantidade
                });
            }
        });

        if (itensParaFinalizar.length === 0) {
            notificacaoAlerta('Nenhum item selecionado', 'Por favor, informe a quantidade para pelo menos um item.');
            return;
        }

        // Mostra um spinner no botão para feedback
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> A Finalizar...');

        $.ajax({
            url: 'ajax_router.php?action=finalizarLoteParcialmenteNovo',
            type: 'POST',
            data: {
                lote_id: loteId,
                itens: JSON.stringify(itensParaFinalizar), // Enviamos o array como uma string JSON
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                bootstrap.Modal.getInstance($modal[0]).hide();
                notificacaoSucesso('Sucesso!', response.message);
                tabelaLotesNovo.ajax.reload(); // Recarrega a tabela principal
            } else {
                notificacaoErro('Erro!', response.message);
            }
        }).always(function () {
            // Restaura o botão ao estado original
            $('#btn-confirmar-finalizacao').prop('disabled', false).html('<i class="fas fa-check-circle me-1"></i>Confirmar Finalização');
        });
    });

    $formHeader.on('change keyup', 'input, select', function (event) {
        // Impede que o cálculo seja refeito quando o próprio campo de lote completo é alterado (se um dia for editável)
        if (event.target.id === 'lote_completo_calculado_novo') {
            return;
        }
        atualizarLoteCompletoNovo();
    });

    // Evento para o novo botão "Cancelar Edição"
    $modalLoteNovo.on('click', '#btn-cancelar-edicao-embalagem', function () {
        $('#form-lote-novo-embalagem')[0].reset();
        $('#item_emb_id_novo').val('');
        $('#item_emb_prod_prim_id_novo').val(null).trigger('change');
        $('#btn-adicionar-item-embalagem').html('<i class="fas fa-plus me-1"></i>Adicionar Item');
        $('#form-lote-novo-embalagem').removeData('consumo-original');
        $(this).remove(); // Remove o próprio botão de cancelar
    });

    // Evento para o checkbox "Finalizar Tudo" no cabeçalho
    $modalFinalizarLote.on('click', '#check-finalizar-todos', function () {
        const isChecked = $(this).is(':checked');
        $('#tabela-itens-para-finalizar .qtd-a-finalizar').each(function () {
            if (isChecked) {
                $(this).val($(this).attr('max'));
            } else {
                $(this).val('');
            }
        });
    });

    // Evento para o botão "Máx" em cada linha
    $modalFinalizarLote.on('click', '.btn-finalizar-tudo-item', function () {
        const $input = $(this).closest('tr').find('.qtd-a-finalizar');
        $input.val($input.attr('max'));
    });

    $modalFinalizarLote.on('input', '.qtd-a-finalizar', function () {
        const valor = parseFloat($(this).val()) || 0;
        const max = parseFloat($(this).attr('max')) || 0;
        if (valor > max) {
            $(this).val(max);
            notificacaoAlerta('Atenção', 'A quantidade a finalizar não pode ser maior que a disponível.');
        }
    });

    $tabelaLotes.on('click', '.btn-finalizar-lote-novo', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');
        const $modal = $('#modal-finalizar-lote');
        const $tbody = $('#tabela-itens-para-finalizar');

        // Guarda o ID do lote no modal para uso posterior
        $modal.data('lote-id', loteId);
        $('#lote-nome-finalizacao').text(loteNome);
        $tbody.html('<tr><td colspan="4" class="text-center">A carregar itens disponíveis...</td></tr>');

        // Mostra o modal
        const modalFinalizar = new bootstrap.Modal($modal[0]);
        modalFinalizar.show();

        // Busca os itens que podem ser finalizados
        $.ajax({
            url: `ajax_router.php?action=getItensParaFinalizar&lote_id=${loteId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            $tbody.empty();
            if (response.success && response.data.length > 0) {
                response.data.forEach(item => {
                    const rowHtml = `
                    <tr data-item-id="${item.item_emb_id}">
                        <td class="align-middle">${item.prod_descricao}</td>
                        <td class="text-center align-middle">
                            <span class="fw-bold text-success">${parseFloat(item.quantidade_disponivel).toFixed(3)}</span>
                        </td>
                        <td class="td-input-finalizar align-middle">
                            <input type="number" class="form-control qtd-a-finalizar" 
                                   min="0" 
                                   max="${parseFloat(item.quantidade_disponivel).toFixed(3)}" 
                                   step="1" 
                                   placeholder="0">
                        </td>
                        <td class="text-center align-middle">
                            <button class="btn btn-outline-primary btn-sm btn-finalizar-tudo-item" title="Preencher com o máximo">Máx</button>
                        </td>
                    </tr>
                `;
                    $tbody.append(rowHtml);
                });
            } else {
                $tbody.html('<tr><td colspan="4" class="text-center text-muted">Nenhum item com saldo disponível para finalizar neste lote.</td></tr>');
                $('#btn-confirmar-finalizacao').prop('disabled', true); // Desabilita o botão se não há itens
            }
        });
    });

    // Ação para o botão "Editar" na tabela principal de lotes 
    $tabelaLotes.on('click', '.btn-editar-lote-novo', function () {
        const pageType = $('body').data('page-type');

        // Garante que todas as abas estejam visíveis por padrão antes de aplicar a lógica
        $('#aba-info-lote-novo-tab, #aba-producao-novo-tab, #aba-embalagem-novo-tab').show();

        if (pageType === 'lotes_producao') {
            // Se estiver na página de PRODUÇÃO, esconde a aba de embalagem
            $('#aba-embalagem-novo-tab').hide();

        } else if (pageType === 'lotes_embalagem') {
            // Se estiver na página de EMBALAGEM, esconde as abas de info e produção
            $('#aba-info-lote-novo-tab').hide();
            $('#aba-producao-novo-tab').hide();
        }

        loteIdAtual = $(this).data('id');
        // Garante que os dropdowns estejam prontos antes de buscar os dados
        $.when(carregarFornecedores(), carregarClientes()).done(function () {
            buscarDadosLoteParaEdicao(loteIdAtual);
        });
    });

    // Evento para o botão "Cancelar Lote" no menu dropdown
    $tabelaLotes.on('click', '.btn-cancelar-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        confirmacaoAcao('Cancelar Lote?', `Tem a certeza de que deseja cancelar o lote "${loteNome}"? Se este lote já gerou estoque, ele será revertido. Esta ação não pode ser desfeita.`)
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=cancelarLoteNovo',
                        type: 'POST',
                        data: { lote_id: loteId, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            notificacaoSucesso('Sucesso!', response.message);
                            tabelaLotesNovo.ajax.reload();
                        } else {
                            notificacaoErro('Erro!', response.message);
                        }
                    });
                }
            });
    });

    // Evento para o botão "Reativar Lote" no menu dropdown
    $tabelaLotes.on('click', '.btn-reativar-lote-novo', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        confirmacaoAcao('Reativar Lote?', `Tem a certeza de que deseja reativar o lote "${loteNome}"? O seu status voltará para 'EM ANDAMENTO'.`)
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=reativarLoteNovo',
                        type: 'POST',
                        data: { lote_id: loteId, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            notificacaoSucesso('Sucesso!', response.message);
                            tabelaLotesNovo.ajax.reload(); // Recarrega a tabela para atualizar status e botões
                        } else {
                            notificacaoErro('Erro!', response.message);
                        }
                    });
                }
            });
    });

    // Evento para o botão "Reabrir Lote" no menu dropdown
    $tabelaLotes.on('click', '.btn-reabrir-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        // Usamos o SweetAlert2 diretamente para pedir o motivo
        Swal.fire({
            title: 'Reabrir Lote?',
            text: `Tem a certeza que deseja reabrir o lote "${loteNome}"? TODO o estoque gerado por ele será estornado.`,
            icon: 'warning',
            input: 'textarea', // Pede um campo de texto
            inputLabel: 'Motivo da Reabertura',
            inputPlaceholder: 'Digite o motivo aqui...',
            inputAttributes: {
                'aria-label': 'Digite o motivo aqui'
            },
            showCancelButton: true,
            confirmButtonText: 'Sim, Reabrir!',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            inputValidator: (value) => {
                if (!value || value.trim().length < 5) {
                    return 'Por favor, insira um motivo com pelo menos 5 caracteres.'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=reabrirLoteNovo',
                    type: 'POST',
                    data: {
                        lote_id: loteId,
                        motivo: result.value, // Envia o motivo digitado
                        csrf_token: csrfToken
                    },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        notificacaoSucesso('Sucesso!', response.message);
                        tabelaLotesNovo.ajax.reload();
                    } else {
                        notificacaoErro('Erro!', response.message);
                    }
                });
            }
        });
    });

    // Evento para o botão "Excluir Permanentemente" no menu dropdown
    $tabelaLotes.on('click', '.btn-excluir-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        Swal.fire({
            title: 'Excluir Permanentemente?',
            html: `Tem a certeza ABSOLUTA de que deseja excluir o lote "<b>${loteNome}</b>"?<br><strong class="text-danger">Esta ação é IRREVERSÍVEL e apagará todos os itens de produção e embalagem associados a ele.</strong>`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: 'Sim, Excluir!',
            confirmButtonColor: '#d33',
            cancelButtonText: 'Cancelar',
            cancelButtonColor: '#3085d6',
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirLoteNovo',
                    type: 'POST',
                    data: { lote_id: loteId, csrf_token: csrfToken },
                    dataType: 'json',
                    global: false
                }).done(function (response) {
                    if (response.success) {
                        notificacaoSucesso('Excluído!', response.message);
                        tabelaLotesNovo.ajax.reload();
                    } else {
                        notificacaoErro('Não permitido!', response.message);
                    }
                }).fail(function (jqXHR) {
                    // O .fail() será executado para erros 4xx e 5xx
                    let errorMessage = 'Ocorreu um erro de comunicação com o servidor.'; // Mensagem padrão

                    // Tenta ler a mensagem específica enviada pelo backend no corpo da resposta
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorMessage = jqXHR.responseJSON.message;
                    }

                    notificacaoErro('Não Permitido', errorMessage);
                });
            }
        });
    });

    $tabelaItensProducao.on('click', '.btn-editar-item-producao', function () {
        const $btn = $(this);

        // Primeiro, reseta o formulário para garantir um estado limpo.
        resetarFormularioProducao();

        // 1. Pega os dados dos atributos data-* do botão
        const itemId = $btn.data('id');
        const produtoId = $btn.data('produto-id');
        const quantidade = $btn.data('quantidade');
        const validade = $btn.data('validade');

        // 2. Preenche o formulário com esses dados
        $('#item_prod_id_novo').val(itemId); // O campo hidden mais importante!
        $('#item_prod_produto_id_novo').val(produtoId).trigger('change'); // .trigger('change') atualiza o Select2 e a data de validade
        $('#item_prod_quantidade_novo').val(parseFloat(quantidade).toFixed(3));
        $('#item_prod_data_validade_novo').val(validade);

        // 3. Muda o texto do botão de Ação e foca no formulário
        $('#btn-adicionar-item-producao').html('<i class="fas fa-save me-1"></i>Atualizar Item');

        // Leva o utilizador para o topo do modal para ver o formulário preenchido
        $modalLoteNovo.animate({ scrollTop: 0 }, "slow");
    });

    // Evento para imprimir etiqueta de PRODUÇÃO
    $tabelaItensProducao.on('click', '.btn-imprimir-etiqueta-producao', function () {
        const loteItemId = $(this).data('id');
        const $btn = $(this);
        const originalIcon = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: 'ajax_router.php?action=imprimirEtiquetaLoteItem',
            type: 'POST',
            data: {
                itemId: loteItemId,
                itemType: 'producao',
                clienteId: $('#lote_cliente_id_novo').val(),
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                window.open(BASE_URL + '/' + response.pdfUrl, '_blank');
            } else {
                notificacaoErro('Erro ao gerar etiqueta', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de comunicação', 'Não foi possível contactar o servidor.');
        }).always(function () {
            $btn.prop('disabled', false).html(originalIcon);
        });
    });

    $tabelaItensEmbalagem.on('click', '.btn-editar-item-embalagem', function () {
        const $btn = $(this);
        const itemId = $btn.data('id');
        const primarioItemId = $btn.data('primario-item-id');
        const secundarioProdId = $btn.data('secundario-prod-id');
        const quantidade = $btn.data('quantidade');
        const consumoOriginal = $btn.data('consumo');

        const $selectPrimario = $('#item_emb_prod_prim_id_novo');
        const $selectSecundario = $('#item_emb_prod_sec_id_novo');

        // Apresenta um estado de "a carregar" nos dropdowns
        $selectPrimario.empty().append('<option value="">A carregar itens...</option>').prop('disabled', true).trigger('change.select2');
        $selectSecundario.empty().append('<option value="">Aguardando item primário...</option>').prop('disabled', true).trigger('change.select2');

        // Fazemos uma única chamada AJAX para obter todos os dados de produção do lote.
        $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteIdAtual, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (!response.success) {
                notificacaoErro('Erro!', 'Não foi possível carregar os dados do lote.');
                return;
            }

            const producaoItens = response.data.producao;

            // --- PASSO A: POPULAR O DROPDOWN PRIMÁRIO ---
            $selectPrimario.empty().append('<option value="">Selecione...</option>');
            producaoItens.forEach(item => {
                // A lógica crítica: incluir item se saldo > 0 OU se for o item que estamos a editar.
                if (parseFloat(item.item_prod_saldo) > 0 || item.item_prod_id == primarioItemId) {
                    const texto = `${item.prod_descricao} (Saldo: ${parseFloat(item.item_prod_saldo).toFixed(3)})`;
                    const option = new Option(texto, item.item_prod_id);
                    $selectPrimario.append(option);
                }
            });
            $selectPrimario.prop('disabled', false);

            // --- PASSO B: SELECIONAR O ITEM PRIMÁRIO CORRETO ---
            $selectPrimario.val(primarioItemId).trigger('change.select2');

            // --- PASSO C: POPULAR E SELECIONAR O DROPDOWN SECUNDÁRIO ---
            const itemSelecionado = producaoItens.find(item => item.item_prod_id == primarioItemId);
            if (itemSelecionado) {
                const primarioProdutoId = itemSelecionado.item_prod_produto_id;
                $selectSecundario.empty().append('<option value="">A carregar...</option>').prop('disabled', true);

                $.ajax({
                    url: `ajax_router.php?action=getSecundariosPorPrimario&primario_id=${primarioProdutoId}`,
                    type: 'GET',
                    dataType: 'json'
                }).done(function (secResponse) {
                    if (secResponse.success) {
                        $selectSecundario.empty().append('<option value="">Selecione...</option>');
                        if (secResponse.data.length > 0) {
                            secResponse.data.forEach(produto => {
                                const texto = `${produto.prod_descricao} (Cód: ${produto.prod_codigo_interno || 'N/A'})`;
                                const option = new Option(texto, produto.prod_codigo);
                                $(option).data('unidades-primarias', produto.prod_unidades_primarias_calculado);
                                $selectSecundario.append(option);
                            });
                        }
                        $selectSecundario.val(secundarioProdId).trigger('change.select2');
                        $selectSecundario.prop('disabled', false);
                    }
                });
            }

            // --- PASSO D: PREENCHER O RESTO DO FORMULÁRIO ---
            $('#form-lote-novo-embalagem').data('consumo-original', consumoOriginal);
            $('#item_emb_id_novo').val(itemId);
            $('#item_emb_qtd_sec_novo').val(parseInt(quantidade));

            $('#btn-adicionar-item-embalagem').html('<i class="fas fa-save me-1"></i>Atualizar Item');
            if ($('#btn-cancelar-edicao-embalagem').length === 0) {
                $('#btn-adicionar-item-embalagem').after(' <button type="button" class="btn btn-secondary" id="btn-cancelar-edicao-embalagem"><i class="fas fa-times me-2"></i>Cancelar</button>');
            }

        }).fail(function () {
            notificacaoErro('Erro de Comunicação!', 'Falha ao buscar dados do lote.');
            $selectPrimario.empty().append('<option value="">Erro ao carregar</option>').prop('disabled', true).trigger('change.select2');
        });
    });

    $tabelaItensProducao.on('click', '.btn-excluir-item-producao', function () {
        const itemId = $(this).data('id');

        confirmacaoAcao('Excluir Item?', 'Só será possível excluir se o saldo não tiver sido consumido. Deseja continuar?')
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=excluirItemProducaoNovo',
                        type: 'POST',
                        data: { item_id: itemId, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            notificacaoSucesso('Sucesso!', response.message);
                            // Recarrega a tabela de produção e o dropdown da aba 3
                            recarregarItensProducao(loteIdAtual);
                            carregarItensProducaoParaSelecao(loteIdAtual);
                        } else {
                            notificacaoErro('Não permitido!', response.message);
                        }
                    });
                }
            });
    });

    $tabelaItensEmbalagem.on('click', '.btn-excluir-item-embalagem', function () {
        const itemId = $(this).data('id');

        confirmacaoAcao('Excluir Item?', 'Esta ação irá reverter o saldo consumido. Tem a certeza?')
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=excluirItemEmbalagemNovo',
                        type: 'POST',
                        data: { item_id: itemId, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            notificacaoSucesso('Sucesso!', response.message);
                            // Recarrega TUDO para garantir consistência
                            recarregarItensEmbalagem(loteIdAtual);
                            recarregarItensProducao(loteIdAtual);
                            carregarItensProducaoParaSelecao(loteIdAtual);
                        } else {
                            notificacaoErro('Erro!', response.message);
                        }
                    });
                }
            });
    });

    // Evento para imprimir etiqueta de EMBALAGEM
    $tabelaItensEmbalagem.on('click', '.btn-imprimir-etiqueta-embalagem', function () {
        const loteItemId = $(this).data('id');
        const $btn = $(this);
        const originalIcon = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: 'ajax_router.php?action=imprimirEtiquetaLoteItem',
            type: 'POST',
            data: {
                itemId: loteItemId,
                itemType: 'embalagem',
                clienteId: $('#lote_cliente_id_novo').val(),
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                window.open(BASE_URL + '/' + response.pdfUrl, '_blank');
            } else {
                notificacaoErro('Erro ao gerar etiqueta', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de comunicação', 'Não foi possível contactar o servidor.');
        }).always(function () {
            $btn.prop('disabled', false).html(originalIcon);
        });
    });

    $('#form-lote-novo-embalagem').on('change keyup', 'select, input', function () {
        calcularConsumoEmbalagem();
    });

    // Evento que é acionado QUANDO A ABA DE PRODUÇÃO É MOSTRADA
    $('#aba-producao-novo-tab').on('shown.bs.tab', function () {
        // Inicializa o Select2 para o dropdown de produtos
        $('#item_prod_produto_id_novo').select2({
            placeholder: 'Selecione um produto',
            dropdownParent: $modalLoteNovo,
            theme: "bootstrap-5"
        });
        // Carrega os produtos primários
        carregarProdutosPrimarios();
    });

    // Evento que é acionado QUANDO A ABA DE EMBALAGEM É MOSTRADA
    $('#aba-embalagem-novo-tab').on('shown.bs.tab', function () {
        // Inicializa os Select2 para os novos dropdowns
        $('#item_emb_prod_prim_id_novo, #item_emb_prod_sec_id_novo').select2({
            placeholder: 'Selecione uma opção',
            dropdownParent: $modalLoteNovo,
            theme: "bootstrap-5"
        });
        // Carrega os dados necessários
        carregarItensProducaoParaSelecao(loteIdAtual);

        // Define o estado inicial do dropdown de secundários
        $('#item_emb_prod_sec_id_novo').prop('disabled', true).empty().append('<option value="">Selecione um produto primário primeiro</option>').trigger('change.select2');

        calcularConsumoEmbalagem();
    });

    $('#item_prod_produto_id_novo').on('change', function () {
        const optionSelecionada = $(this).find(':selected');
        const mesesValidade = optionSelecionada.data('validade-meses');
        const dataFabricacaoStr = $('#lote_data_fabricacao_novo').val();

        if (mesesValidade && dataFabricacaoStr) {
            const dataFabricacao = new Date(dataFabricacaoStr + 'T00:00:00');
            const dataValidadeCalculada = calcularValidadeArredondandoParaCima(dataFabricacao, parseInt(mesesValidade));
            $('#item_prod_data_validade_novo').val(dataValidadeCalculada);
        } else {
            $('#item_prod_data_validade_novo').val('');
        }
    });

    // Evento de mudança no dropdown de produto primário (na Aba 3)
    $('#item_emb_prod_prim_id_novo').on('change', function () {
        const primarioItemId = $(this).val();
        const $selectSecundario = $('#item_emb_prod_sec_id_novo');

        // Limpa e desabilita o dropdown de secundários
        $selectSecundario.prop('disabled', true).empty().append('<option value="">Carregando...</option>').trigger('change.select2');

        if (!primarioItemId) {
            $selectSecundario.empty().append('<option value="">Selecione um produto primário primeiro</option>').trigger('change.select2');
            return;
        }

        $.ajax({
            url: 'ajax_router.php?action=buscarLoteNovo',
            type: 'POST',
            data: { lote_id: loteIdAtual, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (loteData) {
            const itemSelecionado = loteData.data.producao.find(item => item.item_prod_id == primarioItemId);
            if (!itemSelecionado) return;

            const primarioProdutoId = itemSelecionado.item_prod_produto_id;

            $.ajax({
                url: `ajax_router.php?action=getSecundariosPorPrimario&primario_id=${primarioProdutoId}`,
                type: 'GET',
                dataType: 'json'
            }).done(function (response) {
                if (response.success) {
                    $selectSecundario.prop('disabled', false).empty().append('<option value="">Selecione...</option>');

                    if (response.data.length === 0) {
                        $selectSecundario.empty().append('<option value="">Nenhum produto associado</option>').prop('disabled', true);
                    } else {
                        response.data.forEach(produto => {
                            const texto = `${produto.prod_descricao} (Cód: ${produto.prod_codigo_interno || 'N/A'})`;
                            const option = new Option(texto, produto.prod_codigo);
                            $(option).data('unidades-primarias', produto.prod_unidades_primarias_calculado);
                            $selectSecundario.append(option);
                        });
                    }
                    $selectSecundario.trigger('change.select2');
                }
            });
        });
    });

    // Evento do checkbox para liberar a edição da validade
    $('#liberar_edicao_validade_novo').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('#item_prod_data_validade_novo').prop('readonly', !isChecked);
    });

});
