// /public/js/lotes_novo.js
$(document).ready(function () {

    // --- Seletores e Variáveis Globais ---
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalLoteNovo = $('#modal-lote-novo');
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
            { "data": "lote_completo_calculado" },
            { "data": "fornecedor_razao_social" },
            {
                "data": "lote_data_fabricacao",
                "className": "text-center",
                "render": function (data) {
                    if (!data) return '';
                    //return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "lote_status",
                "className": "text-center",
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
                "className": "text-center",
                "render": function (data) {
                    //return new Date(data).toLocaleString('pt-BR');
                    if (!data) return '';
                    const date = new Date(data);
                    return date.toLocaleString('pt-BR');
                }
            },
            {
                "data": "lote_id",
                "orderable": false,
                "className": "text-center",
                "render": function (data, type, row) {
                    let acoesHtml = '';
                    const status = row.lote_status;
                    const loteId = row.lote_id;
                    const loteNome = row.lote_completo_calculado;

                    // Ações para lotes ATIVOS (EM ANDAMENTO ou PARCIALMENTE FINALIZADO)
                    if (status === 'EM ANDAMENTO' || status === 'PARCIALMENTE FINALIZADO') {
                        acoesHtml += `<button class="btn btn-warning btn-sm btn-editar-lote-novo me-1" data-id="${loteId}" title="Editar Lote">Editar</button>`;
                        acoesHtml += `<button class="btn btn-success btn-sm btn-finalizar-lote me-1" data-id="${loteId}" title="Finalizar e Gerar Estoque">Finalizar</button>`;
                        acoesHtml += `
                                    <div class="btn-group d-inline-block">
                                        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            Mais
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item btn-cancelar-lote" href="#" data-id="${loteId}" data-nome="${loteNome}">Cancelar Lote</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger btn-excluir-lote" href="#" data-id="${loteId}" data-nome="${loteNome}">Excluir Permanentemente</a></li>
                                        </ul>
                                    </div>`;
                        return acoesHtml;
                    }

                    if (status === 'FINALIZADO') {
                        acoesHtml += `
                                <div class="btn-group d-inline-block"> 
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item btn-reabrir-lote" href="#" ...>Reabrir Lote</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger btn-excluir-lote" ...>Excluir</a></li>
                                    </ul>
                                </div>`;
                    }

                    // Ações para lotes FINALIZADOS
                    if (status === 'FINALIZADO') {
                        acoesHtml += `<button class="btn btn-secondary btn-sm btn-editar-lote-novo me-1" data-id="${loteId}" title="Visualizar Lote">Visualizar</button>`;
                        acoesHtml += `
                                    <div class="btn-group d-inline-block">
                                        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            Mais
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item btn-reabrir-lote" href="#" data-id="${loteId}" data-nome="${loteNome}">Reabrir Lote</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger btn-excluir-lote" href="#" data-id="${loteId}" data-nome="${loteNome}">Excluir Permanentemente</a></li>
                                        </ul>
                                    </div>`;
                        return acoesHtml;
                    }

                    // Ações para lotes CANCELADOS (apenas Visualizar e Excluir)
                    if (status === 'CANCELADO') {
                        acoesHtml += `<button class="btn btn-secondary btn-sm btn-editar-lote-novo me-1" data-id="${loteId}" title="Visualizar Lote">Visualizar</button>`;
                        acoesHtml += `
                                    <div class="btn-group d-inline-block">
                                        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            Mais
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item text-danger btn-excluir-lote" href="#" data-id="${loteId}" data-nome="${loteNome}">Excluir Permanentemente</a></li>
                                        </ul>
                                    </div>`;
                        return acoesHtml;
                    }

                    // Fallback para qualquer outro status (não deve acontecer)
                    return '';
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

    // Função para carregar Clientes 
    function carregarClientes() {
        return $.get('ajax_router.php?action=getClienteOptions').done(function (response) {
            if (response.success) {
                const $select = $('#lote_cliente_id_novo');
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
    function carregarProdutosPrimarios() {
        // Usamos $.ajax() para ter mais controle
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

                $('#btn-salvar-lote-novo-header').text('Salvar Alterações');
                $('#modal-lote-novo-label').text('Editar Lote: ' + header.lote_completo_calculado);

                // Habilita as outras abas para navegação
                $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').removeClass('disabled');
                new bootstrap.Tab($('#aba-info-lote-novo-tab')[0]).show();

                const status = response.data.header.lote_status;
                if (status === 'FINALIZADO' || status === 'CANCELADO') {
                    configurarModalModoLeitura(true); // Ativa o modo de leitura
                } else {
                    configurarModalModoLeitura(false); // Garante o modo de edição
                }
                // Carrega a tabela de itens de produção ao editar um lote
                recarregarItensProducao(loteId);
                
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
        const $formHeader = $('#form-lote-header');

        // Aplica 'readonly' apenas aos inputs de texto, data, etc.
        $formHeader.find('input').prop('readonly', isReadOnly);

        // Aplica 'disabled' aos dropdowns (selects)
        $formHeader.find('select').prop('disabled', isReadOnly);
        // Esconde/mostra a aba de adicionar produtos
        $('#aba-add-produtos-tab').toggle(!isReadOnly);

        // Esconde ou mostra os botões de salvar, finalizar, etc.
        $('#btn-salvar-lote, #btn-finalizar-lote').toggle(!isReadOnly);

        // Esconde a coluna de ações da tabela de itens em produção
        $('#tabela-itens-em-producao').find('th:last-child, td:last-child').toggle(!isReadOnly);
    }

    /**
     * Busca os itens de produção de um lote e redesenha a tabela na Aba 2.
     * @param {number} loteId O ID do lote a ser consultado.
     */
    function recarregarItensProducao(loteId) {
        if (!loteId) return;

        const $tbody = $('#tabela-itens-producao-novo');
        $tbody.html('<tr><td colspan="5" class="text-center">A carregar itens...</td></tr>');

        $.ajax({
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
                            <td>${item.prod_descricao}</td>
                            <td class="text-end">${parseFloat(item.item_prod_quantidade).toFixed(3)}</td>
                            <td class="text-end">${parseFloat(item.item_prod_saldo).toFixed(3)}</td>
                            <td class="text-center">${new Date(item.item_prod_data_validade + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm">Editar</button>
                                <button class="btn btn-danger btn-sm">Excluir</button>
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

    // --- Event Handlers ---

    // Evento para o botão "Adicionar Novo Lote"
    $('#btn-adicionar-lote-novo').on('click', function () {
        $(this).blur();

        // 1. Limpa o formulário e o modal
        $formHeader[0].reset();
        $('#lote_id_novo').val('');
        $('#modal-lote-novo-label').text('Adicionar Novo Lote');

        // Inicializa os dropdowns do modal
        $('#lote_fornecedor_id_novo, #lote_cliente_id_novo, #item_prod_produto_id_novo').select2({
            placeholder: 'Selecione uma opção',
            dropdownParent: $modalLoteNovo,
            theme: "bootstrap-5"
        });

        // Carrega os dados para os dropdowns
        carregarFornecedores();
        carregarClientes();
        carregarProdutosPrimarios();

        // Busca o próximo número de lote
        $.get('ajax_router.php?action=getProximoNumeroLoteNovo', function (response) {
            if (response.success) {
                $('#lote_numero_novo').val(response.proximo_numero);
                atualizarLoteCompletoNovo(); // Calcula o lote inicial
            } else {
                $('#lote_numero').val('Erro!');
            }
        });
        // 2. Desabilita as abas de produção e embalagens e ativa a primeira
        new bootstrap.Tab($('#aba-info-lote-novo-tab')[0]).show();
        $('#aba-producao-novo-tab, #aba-embalagem-novo-tab').addClass('disabled');

        $modalLoteNovo.modal('show');
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

    // Evento de clique para o botão "Adicionar Item"
    $('#btn-adicionar-item-producao').on('click', function () {
        const loteId = loteIdAtual; // A variável global que guarda o ID do lote
        const formData = new FormData($('#form-lote-novo-producao')[0]);
        formData.append('item_prod_lote_id', loteId);
        formData.append('csrf_token', csrfToken);

        // Adicionar validação aqui no futuro

        $.ajax({
            url: 'ajax_router.php?action=adicionarItemProducaoNovo',
            type: 'POST',
            data: formData,
            processData: false, contentType: false, dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                notificacaoSucesso('Sucesso!', 'Item de produção adicionado ao lote.');
                // Limpa o formulário para a próxima adição
                $('#form-lote-novo-producao')[0].reset();
                $('#item_prod_produto_id_novo').val(null).trigger('change');
                // Recarrega a tabela de itens de produção
                recarregarItensProducao(loteId);
            } else {
                notificacaoErro('Erro!', response.message);
            }
        });
    });

    // Ação para o botão "Editar" na tabela principal de lotes 
    $('#tabela-lotes-novo').on('click', '.btn-editar-lote-novo', function () {
        loteIdAtual = $(this).data('id');
        // Garante que os dropdowns estejam prontos antes de buscar os dados
        $.when(carregarFornecedores(), carregarClientes()).done(function () {
            buscarDadosLoteParaEdicao(loteIdAtual);
        });
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

    // Evento do checkbox para liberar a edição da validade
    $('#liberar_edicao_validade_novo').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('#item_prod_data_validade_novo').prop('readonly', !isChecked);
    });

    $formHeader.on('change keyup', 'input, select', function (event) {
        // Impede que o cálculo seja refeito quando o próprio campo de lote completo é alterado (se um dia for editável)
        if (event.target.id === 'lote_completo_calculado_novo') {
            return;
        }
        atualizarLoteCompletoNovo();
    });

});