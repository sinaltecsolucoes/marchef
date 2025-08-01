// /public/js/lotes.js
$(document).ready(function () {

    // --- Seletores e Variáveis Globais ---
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalLote = $('#modal-lote');
    const $modalImpressao = $('#modal-imprimir-etiqueta');
    const $selectCliente = $('#select-cliente-etiqueta');
    const $btnConfirmarImpressao = $('#btn-confirmar-impressao');

    let tableLotes;

    // =================================================================
    // INICIALIZAÇÃO DOS PLUGINS (Executa 1 vez quando a página carrega)
    // =================================================================
    $('#lote_fornecedor_id, #lote_cliente_id, #item_produto_id').select2({
        placeholder: 'Selecione uma opção',
        dropdownParent: $modalLote, // Essencial para funcionar no modal
        theme: "bootstrap-5"
    });

    // Inicialização para o select do modal de Impressão
    $('#select-cliente-etiqueta').select2({
        placeholder: 'Digite para buscar um cliente...',
        language: "pt-BR",
        theme: "bootstrap-5",
        dropdownParent: $modalImpressao // Aponta para o modal de Impressão
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

        //const fornecedorOption = $('#lote_fornecedor_id').find(':selected');
        //const codFornecedor = fornecedorOption.data('codigo-interno') || 'CF';

        const clienteOption = $('#lote_cliente_id').find(':selected');
        const codCliente = clienteOption.data('codigo-interno') || 'CC';


        let ano = 'YY';
        if (dataFabStr) {
            try {
                ano = new Date(dataFabStr + 'T00:00:00').getFullYear().toString().slice(-2);
            } catch (e) { /* ignora erro de data inválida durante a digitação */ }
        }

        // Junta todas as partes para formar o código final
        //const loteCompletoCalculado = `${numero}/${ano}-${ciclo}/${viveiro} ${codFornecedor}`;
        const loteCompletoCalculado = `${numero}/${ano}-${ciclo}/${viveiro} ${codCliente}`;


        // Atualiza o valor do campo no formulário
        $('#lote_completo_calculado').val(loteCompletoCalculado);
    }

    $('#form-lote-header').on('change keyup', 'input, select', function (event) {
        if (event.target.id === 'lote_completo_calculado') {
            return;
        } atualizarLoteCompleto();
    });


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
    * Exibe uma mensagem de feedback (alerta) para o usuário.
    * @param {string} msg - A mensagem a ser exibida.
    * @param {string} type - 'success' ou 'danger'.
    * @param {string} area - O seletor da div onde a mensagem aparecerá.
    */
    function showFeedbackMessage(msg, type = 'success', area = '#feedback-message-area-lote') {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const $feedbackArea = $(area);

        $feedbackArea.empty()
            .removeClass('alert-success alert-danger')
            .addClass(`alert ${alertClass}`)
            .text(msg)
            .fadeIn();

        // A mensagem desaparece após 5 segundos
        setTimeout(() => {
            $feedbackArea.fadeOut('slow');
        }, 5000);
    }

    // Gatilhos para o cálculo
    $('#item_produto_id').on('change', calcularPesoTotal);
    $('#item_quantidade').on('keyup change', calcularPesoTotal);

    /**
     * Função para renderizar a tabela de itens dentro do modal
     * @param {*} items 
     * @returns 
     */
    function renderizarItensDoLote(items) {
        const $container = $('#lista-produtos-deste-lote');
        const isLoteFinalizado = $('#lote_status_hidden').val() === 'FINALIZADO'; // Verificamos o status do lote

        if (!items || items.length === 0) {
            $container.html('<p class="text-muted">Nenhum produto incluído ainda.</p>');
            return;
        }

        let tableHtml = `<table class="table table-sm table-striped table-sm-custom" id="tabela-itens-lote-modal">
    <thead>
        <tr>
           <th style="width: 50%" class="text-center";">Produto</th>
           <th style="width: 10%" class="text-center";">Quantidade</th>
           <th style="width: 10%" class="text-center";">Peso Total (kg)</th>
           <th style="width: 10%" class="text-center";">Validade</th>
           <th style="width: 20%" class="text-center";">Ações</th>
       </tr>
    </thead>
    <tbody>`;

        items.forEach(function (item) {
            const dataValidade = new Date(item.item_data_validade + 'T00:00:00').toLocaleDateString('pt-BR');
            const validadeISO = item.item_data_validade;
            const pesoTotalItem = (parseFloat(item.item_quantidade) * parseFloat(item.prod_peso_embalagem)).toFixed(3);
            const disabled = isLoteFinalizado ? 'disabled' : ''; // Desabilita botões se o lote estiver finalizado

            tableHtml += `
           <tr data-produto-id="${item.item_produto_id}" data-validade-iso="${validadeISO}">
               <td>${item.prod_descricao}</td>
               <td class="text-center">${item.item_quantidade}</td>
               <td class="text-center">${pesoTotalItem}</td>
               <td class="text-center">${dataValidade}</td>
               <td>
                    <button class="btn btn-info btn-sm btn-imprimir-item" data-item-id="${item.item_id}" title="Imprimir Etiqueta"> Imprimir</button>
                    <button class="btn btn-warning btn-sm btn-editar-item" data-item-id="${item.item_id}" ${disabled}>Editar</button>
                    <button class="btn btn-danger btn-sm btn-excluir-item" data-item-id="${item.item_id}" ${disabled}>Excluir</button>
               </td>
           </tr>
       `;
        });

        tableHtml += '</tbody></table>';
        $container.html(tableHtml);
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
                    renderizarItensDoLote(response.data.items);
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
     * Função de busca customizada para o Select2.
     * Procura o termo na descrição, no código interno e no ID do produto.
     */
    function customMatcher(params, data) {
        // Se não houver termo de busca, retorna todos os resultados
        if ($.trim(params.term) === '') {
            return data;
        }

        // Não mostra os resultados para o placeholder (ex: "Selecione...")
        if (typeof data.text === 'undefined') {
            return null;
        }

        const term = params.term.toLowerCase();
        const text = data.text.toLowerCase();
        const id = data.id.toString(); // O ID do produto (value da option)

        // Pega o código interno que anexamos ao elemento
        const codigoInterno = $(data.element).data('codigo-interno') ? $(data.element).data('codigo-interno').toLowerCase() : '';

        // Verifica se o termo de busca está no texto, no código interno ou no ID
        if (text.indexOf(term) > -1 || codigoInterno.indexOf(term) > -1 || id.indexOf(term) > -1) {
            return data; // Encontrou, retorna o resultado
        }

        // Se não encontrou, retorna nulo
        return null;
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

    // --- Inicialização da Tabela Principal de Lotes ---
    tableLotes = $('#tabela-lotes').DataTable({
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
                    // O 'row' nos dá acesso a todos os dados da linha, incluindo o status
                    let finalizarBtn = '';
                    if (row.lote_status === 'EM ANDAMENTO') {
                        // finalizarBtn = `<button class="btn btn-success btn-sm btn-finalizar-lote" data-id="${data}" title="Finalizar e Gerar Estoque">Finalizar</button>`;
                        finalizarBtn = `<button class="btn btn-success btn-sm btn-finalizar-lote ms-1" data-id="${data}" data-nome="${row.lote_completo_calculado}" title="Finalizar e Gerar Estoque">Finalizar</button>`;

                    }

                    return `
                         <button class="btn btn-warning btn-sm btn-editar-lote" data-id="${data}">Editar</button>
                         <button class="btn btn-danger btn-sm btn-excluir-lote" data-id="${data}">Excluir</button>
                         ${finalizarBtn}
                     `;
                }
            }
        ],
        "language": { "url": "libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[4, 'desc']]
    });

    // =================================================================
    // EVENTOS
    // =================================================================
    // Inicializa o Select2 nos dropdowns (quando o modal for aberto)
    $('#modal-lote').on('shown.bs.modal', function () {
        if (!$('#lote_id').val()) {
            // Inicializa o Select2 para o dropdown de FORNECEDORES
            $('#lote_fornecedor_id').select2({
                placeholder: 'Selecione um fornecedor',
                dropdownParent: $('#modal-lote'),
                theme: "bootstrap-5"
            });

            // Inicializa o Select2 para o dropdown de PRODUTOS
            $('#item_produto_id').select2({
                placeholder: 'Selecione um produto',
                dropdownParent: $('#modal-lote'),
                theme: "bootstrap-5"
            });

            // Carrega a lista inicial de produtos (com o filtro 'Todos' padrão) ao abrir o modal
            carregarFornecedores();
            carregarProdutos();
        }
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

    // Abrir modal para Adicionar Novo Lote
    $('#btn-adicionar-lote-main').on('click', function () {
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

    // Ação para o botão "Editar" na tabela principal de lotes
    $('#tabela-lotes tbody').on('click', '.btn-editar-lote', function () {
        const loteId = $(this).data('id');

        // Primeiro, carrega a lista de fornecedores.
        carregarFornecedores().done(function () {
            // Quando os fornecedores estiverem carregados, carrega os clientes.
            carregarClientes().done(function () {
                // Quando ambos estiverem carregados, busca os dados do lote.
                $.ajax({
                    url: 'ajax_router.php?action=buscarLote',
                    type: 'POST',
                    data: {
                        lote_id: loteId,
                        csrf_token: csrfToken
                    },
                    dataType: 'json'
                })
                    .done(function (response) {
                        if (response.success) {
                            const lote = response.data;
                            const header = lote.header;
                            console.log('Dados do lote:', header); // Log para depuração

                            // 1. Preenche os campos do formulário, exceto o fornecedor
                            $('#lote_id').val(header.lote_id);
                            $('#lote_numero').val(header.lote_numero);
                            $('#lote_data_fabricacao').val(header.lote_data_fabricacao);
                            $('#lote_ciclo').val(header.lote_ciclo);
                            $('#lote_viveiro').val(header.lote_viveiro);
                            $('#lote_completo_calculado').val(header.lote_completo_calculado);

                            // 2. Seleciona o fornecedor e cliente corretos no dropdown (que já foi carregado)
                            $('#lote_fornecedor_id').val(header.lote_fornecedor_id).trigger('change.select2');
                            $('#lote_cliente_id').val(header.lote_cliente_id).trigger('change.select2');

                            // 3. Renderiza a lista de produtos do lote
                            renderizarItensDoLote(lote.items);

                            // 4. Ajusta o texto do botão para "Edição"
                            $('#btn-salvar-lote').text('Salvar Alterações');

                            // 5. Garante que a primeira aba sempre será a ativa ao abrir o modal
                            new bootstrap.Tab($('#aba-info-lote-tab')[0]).show();

                            // 6. Prepara e exibe o modal
                            $('#modal-lote-label').text('Editar Lote: ' + header.lote_completo_calculado);
                            $('#aba-add-produtos-tab').removeClass('disabled').attr('aria-disabled', 'false');
                            $('#modal-lote').modal('show');

                        } else {
                            alert('Erro ao buscar dados do lote: ' + response.message);
                        }
                    })
                    .fail(function () {
                        alert('Erro de comunicação ao buscar dados do lote.');

                    });
            });
        });
    });


    // Ação para o botão "Excluir" na tabela PRINCIPAL de lotes
    $('#tabela-lotes tbody').on('click', '.btn-excluir-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome'); // Pega o texto da primeira coluna da linha

        // Preenche o modal de confirmação
        $('#id-lote-excluir').val(loteId);
        $('#nome-lote-excluir').text(loteNome);

        // Abre o modal
        $('#modal-confirmar-exclusao-lote').modal('show');
    });

    // Ação do botão de confirmação final, DENTRO do modal de exclusão
    $('#btn-confirmar-exclusao-lote').on('click', function () {
        const loteId = $('#id-lote-excluir').val();

        $.ajax({
            url: 'ajax_router.php?action=excluirLote',
            type: 'POST',
            data: {
                lote_id: loteId,
                csrf_token: csrfToken
            },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    // Exibe uma mensagem de sucesso na área principal
                    $('#feedback-message-area-lote').html(`<div class="alert alert-success">${response.message}</div>`);
                    // Recarrega a tabela para remover a linha excluída
                    tableLotes.ajax.reload(null, false);
                } else {
                    $('#feedback-message-area-lote').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            })
            .fail(function () {
                alert('Erro de comunicação ao tentar excluir o lote.');
            })
            .always(function () {
                $('#modal-confirmar-exclusao-lote').modal('hide');
            });
    });

    // Ação para o botão "Excluir" de um item DENTRO do modal
    $('#lista-produtos-deste-lote').on('click', '.btn-excluir-item', function () {
        const itemId = $(this).data('item-id');
        const $linhaParaRemover = $(this).closest('tr'); // Pega a linha da tabela (<tr>) para removermos depois

        // Pede a confirmação do usuário
        if (confirm('Tem certeza que deseja excluir este item do lote?')) {
            $.ajax({
                url: 'ajax_router.php?action=excluirLoteItem',
                type: 'POST',
                data: {
                    item_id: itemId,
                    csrf_token: csrfToken
                },
                dataType: 'json'
            })
                .done(function (response) {
                    if (response.success) {
                        // Remove a linha da tabela da interface com um efeito suave
                        $linhaParaRemover.fadeOut(400, function () {
                            $(this).remove();
                        });

                        // Recarrega a tabela principal ao fundo para refletir qualquer mudança
                        tableLotes.ajax.reload(null, false);

                    } else {
                        alert('Erro ao excluir item: ' + response.message);
                    }
                })
                .fail(function () {
                    alert('Erro de comunicação ao tentar excluir o item.');
                });

        }
    });

    $('#lista-produtos-deste-lote').on('click', '.btn-imprimir-item', function (e) {
        e.preventDefault();
        const $button = $(this);
        const itemId = $button.data('item-id');

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
                alert(response.message || 'Ocorreu um erro desconhecido ao gerar a etiqueta.');
            }
        }).fail(function () {
            alert('Erro de comunicação com o servidor. Tente novamente.');
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
            const mensagem = 'Por favor, corrija os seguintes erros:<br>' + erros.join('<br>');

            // Exibe a mensagem na área de feedback do modal
            $('#mensagem-lote-header').html(`<div class="alert alert-danger">${mensagem}</div>`);
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
                    $('#mensagem-lote-header').html(`<div class="alert alert-success">${response.message}</div>`);
                    tableLotes.ajax.reload(null, false);
                    if (response.novo_lote_id) {
                        $('#lote_id').val(response.novo_lote_id);
                        $('#aba-add-produtos-tab').removeClass('disabled').attr('aria-disabled', 'false');
                        new bootstrap.Tab($('#aba-add-produtos-tab')[0]).show();
                    }
                } else {
                    $('#mensagem-lote-header').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            })

            .fail(function (xhr, status, error) {
                $('#mensagem-lote-header').html(`<div class="alert alert-danger">Erro de comunicação ao salvar.</div>`);

            });
    });

    // Ação do botão "Incluir Produto" / "Salvar Alterações"
    $('#btn-incluir-produto').on('click', function () {
        const $button = $(this); // Guarda a referência do botão
        const loteId = $('#lote_id').val();

        if (!loteId) {
            alert('Erro: ID do lote não encontrado. Salve o cabeçalho primeiro.');
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
                    // 1. Limpa o formulário e reseta o botão para o modo "Incluir"
                    $('#btn-cancelar-inclusao').trigger('click');

                    // 2. Chama a função para buscar e redesenhar a lista completa de itens
                    recarregarItensDoLote(loteId);

                    // 3. Volta para a primeira aba para o usuário ver o resultado
                    new bootstrap.Tab($('#aba-info-lote-tab')[0]).show();

                } else {
                    $('#mensagem-add-produto').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            })
            .fail(function () {
                $('#mensagem-add-produto').html(`<div class="alert alert-danger">Erro de comunicação ao incluir produto.</div>`);
            })
            .always(function () {
                $button.prop('disabled', false).text('Incluir Produto');
            });
    });

    // Ação para o botão "Editar" de um item DENTRO do modal
    $('#lista-produtos-deste-lote').on('click', '.btn-editar-item', function () {
        const itemId = $(this).data('item-id');

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
                        alert(response.message || 'Erro ao buscar dados do item.');
                    }
                })
                .fail(function () {
                    alert('Erro de comunicação ao buscar dados do item.');
                });
        });
    });

    // Ação para o botão "Finalizar" na tabela principal
    $('#tabela-lotes tbody').on('click', '.btn-finalizar-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).data('nome');

        $('#id-lote-finalizar').val(loteId);
        $('#nome-lote-finalizar').text(loteNome);

        $('#modal-confirmar-finalizar-lote').modal('show');
    });

    // Ação do botão de confirmação final, DENTRO do modal de finalização
    $('#btn-confirmar-finalizar').on('click', function () {
        const loteId = $('#id-lote-finalizar').val();

        $.ajax({
            url: 'ajax_router.php?action=finalizarLote',
            type: 'POST',
            data: {
                lote_id: loteId,
                csrf_token: csrfToken
            },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    $('#feedback-message-area-lote').html(`<div class="alert alert-success">${response.message}</div>`);
                    tableLotes.ajax.reload(null, false);
                } else {
                    $('#feedback-message-area-lote').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            })
            .fail(function () {
                alert('Erro de comunicação ao tentar finalizar o lote.');
            })
            .always(function () {
                $('#modal-confirmar-finalizar-lote').modal('hide');
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
});


