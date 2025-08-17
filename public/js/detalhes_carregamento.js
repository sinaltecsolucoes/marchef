// /public/js/detalhes_carregamento.js
$(document).ready(function () {
    if (!carregamentoData || !carregamentoData.header) {
        notificacaoErro('Erro Crítico', 'Não foi possível carregar os dados deste carregamento.');
        return;
    }

    // --- VARIÁVEIS GLOBAIS E SELETORES ---
    const carregamentoId = carregamentoData.header.car_id;
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || (window.csrfToken || '');
    const $modalGerenciarFila = $('#modal-gerenciar-fila');
    const $selectClienteParaFila = $('#select-cliente-para-fila');
    const $containerClientesNoModal = $('#clientes-e-produtos-container-modal');
    const $tabelaComposicaoBody = $('#tbody-composicao-carregamento');
    let modoModal = 'inclusao'; // Controle explícito: 'inclusao' ou 'edicao'
    let filaIdParaEditar = null; // Armazena o ID da fila para edição

    // --- FUNÇÕES ---
    function preencherCabecalho() {
        const header = carregamentoData.header;
        $('#car-numero-detalhe').text(header.car_numero);
        const $statusBadge = $('#car-status-detalhe');
        $statusBadge.text(header.car_status);
        let badgeClass = 'bg-secondary';
        if (header.car_status === 'EM ANDAMENTO') badgeClass = 'bg-warning text-dark';
        if (header.car_status === 'AGUARDANDO CONFERENCIA') badgeClass = 'bg-primary';
        if (header.car_status === 'FINALIZADO') badgeClass = 'bg-success';
        if (header.car_status === 'CANCELADO') badgeClass = 'bg-danger';
        $statusBadge.removeClass('bg-secondary bg-warning bg-primary bg-success bg-danger text-dark').addClass(badgeClass);
    }

    function inicializarSelectClienteModal() {
        $selectClienteParaFila.select2({
            placeholder: 'Digite para buscar um cliente...',
            theme: "bootstrap-5",
            dropdownParent: $modalGerenciarFila,
            language: "pt-BR",
            ajax: {
                url: 'ajax_router.php?action=getClienteOptions',
                dataType: 'json',
                processResults: function (data) {
                    const mappedData = data.data.map(item => ({ id: item.ent_codigo, text: item.nome_display }));
                    return { results: mappedData };
                }
            }
        });
    }

    // Carrega todos os PRODUTOS disponíveis de uma vez (não itens/lotes)
    function inicializarSelectProdutoNoCard(selectId) {
        const $select = $('#' + selectId);

        // Inicializa o Select2 com um placeholder enquanto os dados carregam
        $select.select2({
            placeholder: 'A carregar produtos do estoque...',
            theme: "bootstrap-5",
            dropdownParent: $modalGerenciarFila,
            language: "pt-BR"
        });

        // Faz uma única chamada AJAX para buscar TODOS os produtos disponíveis
        $.ajax({
            url: 'ajax_router.php?action=getProdutosDisponiveisEmEstoque',
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            // Limpa o select e adiciona a opção de placeholder
            $select.empty().append('<option value="">Selecione um produto...</option>');

            // Preenche o select com os dados recebidos
            $select.select2({
                placeholder: 'Selecione um produto do estoque...',
                theme: "bootstrap-5",
                dropdownParent: $modalGerenciarFila,
                language: "pt-BR",
                data: response.results // Usa a propriedade 'results' para carregar os produtos
            });
        }).fail(function () {
            notificacaoErro('Falha Crítica', 'Não foi possível carregar a lista de produtos do estoque.');
        });
    }

    function recarregarETabelaPrincipal() {
        console.log("Buscando dados atualizados do carregamento...");
        $.ajax({
            url: `ajax_router.php?action=getCarregamentoDetalhes&id=${carregamentoId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data) {
                const filas = response.data.filas;
                $tabelaComposicaoBody.empty();

                if (!filas || filas.length === 0) {
                    $tabelaComposicaoBody.html('<tr><td colspan="7" class="text-center text-muted">Nenhuma fila adicionada.</td></tr>');
                    return;
                }

                filas.forEach(fila => {
                    // Define totalItensNaFila para a fila atual
                    const totalItensNaFila = fila.itens ? fila.itens.length : 0;

                    if (totalItensNaFila > 0) {
                        // Agrupa itens por cliente
                        const clientesDaFila = fila.itens.reduce((acc, item) => {
                            (acc[item.cliente_razao_social] = acc[item.cliente_razao_social] || []).push(item);
                            return acc;
                        }, {});

                        let isFirstRowOfQueue = true;
                        for (const nomeCliente in clientesDaFila) {
                            const itensDoCliente = clientesDaFila[nomeCliente];
                            const totalItensDoCliente = itensDoCliente.length;

                            itensDoCliente.forEach((item, index) => {
                                const $linha = $(`<tr data-fila-id="${fila.fila_id}">`);

                                // Células de Fila e Ações são criadas apenas na primeira linha do grupo da Fila
                                if (isFirstRowOfQueue && index === 0) {
                                    const numSequencial = String(fila.fila_numero_sequencial).padStart(2, '0');

                                    // Célula 1: Fila
                                    $linha.append(`<td class="text-center align-middle" rowspan="${totalItensNaFila}">${numSequencial}</td>`);

                                    // Célula 2: Ações (com a classe 'coluna-acoes')
                                    $linha.append(`
                                        <td class="text-center align-middle coluna-acoes" rowspan="${totalItensNaFila}">
                                            <button class="btn btn-sm btn-warning btn-editar-fila-principal me-1" data-fila-id="${fila.fila_id}" data-fila-sequencial="${numSequencial}" title="Editar Fila">
                                                <i class="fas fa-pencil-alt"></i> Editar
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-remover-fila-principal" data-fila-sequencial="${numSequencial}" title="Remover Fila Completa">
                                                <i class="fas fa-trash"></i> Remover
                                            </button>
                                        </td>
                                    `);
                                }

                                // Célula 3: Cliente (criada uma vez por grupo de cliente)
                                if (index === 0) {
                                    $linha.append(`<td class="align-middle" rowspan="${totalItensDoCliente}">${nomeCliente}</td>`);
                                }

                                // Células restantes
                                $linha.append(`<td class="align-middle">${item.prod_descricao} (Cód: ${item.prod_codigo_interno})</td>`);
                                $linha.append(`<td class="text-center align-middle">${item.lote_completo_calculado || 'N/A'}</td>`);
                                $linha.append(`<td class="text-center align-middle">${item.cliente_lote_nome || 'N/A'}</td>`);
                                $linha.append(`<td class="text-end align-middle">${parseFloat(item.car_item_quantidade).toFixed(3)}</td>`);

                                $tabelaComposicaoBody.append($linha);
                            });
                            isFirstRowOfQueue = false;
                        }
                    } else {
                        // Caso a fila não tenha itens, exibe apenas o número da fila e ações
                        const numSequencial = String(fila.fila_numero_sequencial).padStart(2, '0');
                        const $linha = $(`<tr data-fila-id="${fila.fila_id}">`);
                        $linha.append(`<td class="text-center align-middle">${numSequencial}</td>`);
                        $linha.append(`
                            <td class="text-center align-middle coluna-acoes">
                                <button class="btn btn-sm btn-outline-warning btn-editar-fila-principal me-1" data-fila-id="${fila.fila_id}" data-fila-sequencial="${numSequencial}" title="Editar Fila">
                                    <i class="fas fa-pencil-alt"></i> Editar
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-remover-fila-principal" data-fila-sequencial="${numSequencial}" title="Remover Fila Completa">
                                    <i class="fas fa-trash"></i> Remover
                                </button>
                            </td>
                        `);
                        $linha.append(`<td colspan="5" class="text-center text-muted">Nenhum item nesta fila.</td>`);
                        $tabelaComposicaoBody.append($linha);
                    }
                });
            } else {
                $tabelaComposicaoBody.html(`<tr><td colspan="7" class="text-center text-danger">Erro ao carregar os dados: ${response.message || ''}</td></tr>`);
            }
            controlarVisibilidadeAcoes();
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Erro ao recarregar tabela:', textStatus, errorThrown, 'Resposta:', jqXHR.responseText);
            $tabelaComposicaoBody.html('<tr><td colspan="7" class="text-center text-danger">Erro de comunicação ao carregar os dados.</td></tr>');
        });
    }

    function executarRemocaoFila(filaId) {
        $.ajax({
            url: 'ajax_router.php?action=removerFilaCompleta',
            type: 'POST',
            data: {
                fila_id: filaId,
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                notificacaoSucesso('Removida!', response.message);
                recarregarETabelaPrincipal();
            } else {
                notificacaoErro('Erro!', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível comunicar com o servidor.');
        });
    }

    /**
     * Habilita ou desabilita os botões de ação na página com base no status do carregamento.
     */
    function controlarVisibilidadeAcoes() {
        const status = carregamentoData.header.car_status;

        // Se o status for FINALIZADO ou CANCELADO, esconde todos os botões de modificação.
        if (status === 'FINALIZADO' || status === 'CANCELADO') {
            // Esconde o botão de Adicionar Nova Fila
            $('button[data-bs-target="#modal-gerenciar-fila"]').hide();

            // Esconde a coluna inteira de Ações da tabela principal
            $('.coluna-acoes').hide();

            // Esconde o card inteiro de Finalização
            $('#btn-abrir-conferencia').closest('.card').hide();

        } else {
            // Garante que os botões estejam visíveis para outros status como EM ANDAMENTO
            $('button[data-bs-target="#modal-gerenciar-fila"]').show();
            $('.coluna-acoes').show();
            $('#btn-abrir-conferencia').closest('.card').show();
        }
    }

    // --- EVENT HANDLERS ---

    //Evento para limpar o modal e configurar o estado inicial para o modo de inclusão
    $modalGerenciarFila.on('show.bs.modal', function () {
        // --- Ações de Limpeza (executadas sempre que o modal abre) ---

        // 1. Fecha qualquer dropdown Select2 que possa estar aberto
        if ($('.select2-hidden-accessible').length) {
            $('.select2-hidden-accessible').select2('close');
        }
        // 2. Garante que o select de clientes e o container de produtos comecem limpos
        $selectClienteParaFila.val(null).trigger('change');
        $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');

        // --- Lógica Específica para o Modo (Adicionar vs. Editar) ---

        if (modoModal === 'edicao') {
            // Se estiver a editar, apenas define o título correto.
            // A busca dos dados será feita no evento 'shown.bs.modal' (depois de o modal aparecer).
            const seq = $tabelaComposicaoBody.find(`.btn-editar-fila-principal[data-fila-id="${filaIdParaEditar}"]`).data('fila-sequencial');
            $(this).find('.modal-title').text(`Editar Fila ${String(seq).padStart(2, '0')}`);

        } else {
            // Se estiver a adicionar, calcula o próximo número de fila e define o título.
            const proximoNumeroFila = 1 + new Set($tabelaComposicaoBody.find('tr[data-fila-id]').map((i, el) => $(el).data('fila-id'))).size;
            $(this).find('.modal-title').text(`Adicionar Nova Fila (Nº ${String(proximoNumeroFila).padStart(2, '0')})`);
        }
    });

    //Evento para carregar os dados de uma fila existente no modo de edição.
    $modalGerenciarFila.on('shown.bs.modal', function (event) {
        if (modoModal === 'edicao' && filaIdParaEditar) {
            $.ajax({
                url: 'ajax_router.php?action=getFilaDetalhes',
                type: 'POST',
                data: { fila_id: filaIdParaEditar, csrf_token: csrfToken },
                dataType: 'json'
            }).done(function (response) {
                if (response.success && response.data) {
                    const fila = response.data;
                    $('#numero-fila-modal').text(String(fila.fila_numero_sequencial).padStart(2, '0'));
                    $containerClientesNoModal.empty();

                    fila.clientes.forEach(cliente => {
                        const $novoCard = $($('#template-card-cliente-modal').html());
                        const selectIdUnico = `select-produto-${cliente.clienteId}-${new Date().getTime()}`;
                        const numeroCliente = $containerClientesNoModal.find('.card-cliente-na-fila').length + 1;
                        const novoTitulo = `CLIENTE ${String(numeroCliente).padStart(2, '0')} - ${cliente.clienteNome}`;
                        $novoCard.attr('data-cliente-id', cliente.clienteId);
                        $novoCard.find('.nome-cliente-card').text(novoTitulo);
                        $novoCard.find('.select-produto-estoque').attr('id', selectIdUnico);

                        const $listaProdutos = $novoCard.find('.lista-produtos-cliente');

                        cliente.produtos.forEach(produto => {
                            const produtoHtml = `
                                 <tr data-lote-item-id="${produto.loteItemId}" data-produto-id="${produto.produtoId}">
                                     <td>${produto.produtoTexto}</td>
                                     <td class="text-end">${parseFloat(produto.quantidade).toFixed(3)}</td>
                                     <td class="text-center">
                                         <button type="button" class="btn btn-warning btn-sm btn-editar-produto-da-lista"><i class="fas fa-pencil-alt"></i> Editar</button>
                                         <button type="button" class="btn btn-danger btn-sm btn-remover-produto-da-lista"><i class="fas fa-trash"></i> Remover</button>
                                     </td>
                                 </tr>`;
                            $listaProdutos.append(produtoHtml);
                        });

                        $containerClientesNoModal.append($novoCard);
                        inicializarSelectProdutoNoCard(selectIdUnico);
                    });
                } else {
                    notificacaoErro('Erro!', response.message || 'Não foi possível carregar os dados desta fila.');
                }
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error('Erro AJAX getFilaDetalhes:', textStatus, errorThrown);
                notificacaoErro('Erro de Comunicação', 'A requisição para buscar os dados da fila falhou.');
            });
        }
    });

    // Evento acionado sempre que o modal de gerir fila é fechado, por qualquer motivo.
    $modalGerenciarFila.on('hide.bs.modal', function () {

        // 1. Reseta as variáveis de controle para o estado padrão de "inclusão"
        modoModal = 'inclusao';
        filaIdParaEditar = null;

        // 2. Limpa o conteúdo dinâmico que foi adicionado
        $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');
        $selectClienteParaFila.val(null).trigger('change');

        // 3. Garante que o título do modal volte ao título padrão de "Adicionar"
        // (O evento 'show.bs.modal' irá definir o título correto na próxima vez que abrir)
    });

    $modalGerenciarFila.on('click', '#btn-adicionar-cliente-a-fila', function () {
        const clienteId = $selectClienteParaFila.val();
        const clienteNome = $selectClienteParaFila.find('option:selected').text();
        if (!clienteId) {
            notificacaoErro('Seleção Inválida', 'Por favor, selecione um cliente.');
            return;
        }
        if ($containerClientesNoModal.find(`.card-cliente-na-fila[data-cliente-id="${clienteId}"]`).length > 0) {
            notificacaoErro('Cliente Já Adicionado', 'Este cliente já foi adicionado à fila.');
            return;
        }
        const $novoCard = $($('#template-card-cliente-modal').html());
        const selectIdUnico = `select-produto-${clienteId}-${new Date().getTime()}`;
        const numeroCliente = $containerClientesNoModal.find('.card-cliente-na-fila').length + 1;
        const novoTitulo = `CLIENTE ${String(numeroCliente).padStart(2, '0')} - ${clienteNome}`;
        $novoCard.attr('data-cliente-id', clienteId);
        $novoCard.find('.nome-cliente-card').text(novoTitulo);
        $novoCard.find('.select-produto-estoque').attr('id', selectIdUnico);
        $containerClientesNoModal.find('p.text-muted').remove();
        $containerClientesNoModal.append($novoCard);
        inicializarSelectProdutoNoCard(selectIdUnico);
        $selectClienteParaFila.val(null).trigger('change');
    });

    $modalGerenciarFila.on('click', '.btn-remover-cliente-da-fila', function () {
        $(this).closest('.card-cliente-na-fila').remove();
        if ($containerClientesNoModal.find('.card-cliente-na-fila').length === 0) {
            $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');
        }
    });

    $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (event) {
        event.preventDefault();
        const $form = $(this);
        const $card = $form.closest('.card-cliente-na-fila');
        const $produtoSelect = $form.find('.select-produto-estoque');
        const $loteSelect = $form.find('.select-lote-estoque');
        const $quantidadeInput = $form.find('input[type="number"]');
        const produtoId = $produtoSelect.val();
        const loteItemId = $loteSelect.val();
        const quantidade = parseFloat($quantidadeInput.val());
        const $listaProdutos = $card.find('.lista-produtos-cliente');
        const isEditing = $form.data('editing-row');
        const loteId = $loteSelect.val();

        if (!produtoId || !loteItemId || !quantidade || quantidade <= 0) {
            notificacaoErro('Dados Inválidos', 'Por favor, preencha todos os campos corretamente.');
            return;
        }

        // Monta o texto do produto apenas com nome e lote, sem saldo
        const produtoTexto = `${$produtoSelect.find('option:selected').text()} (${$loteSelect.find('option:selected').text().split(' - Saldo:')[0]})`;
        const quantidadeTexto = quantidade.toFixed(3);

        if (isEditing) {
            const $row = $form.data('editing-row');
            $row.find('td:first').text(produtoTexto);
            $row.find('td:nth-child(2)').text(quantidadeTexto);
            //$row.data('lote-item-id', loteItemId);
            $row.data('produto-id', produtoId);
            $row.data('lote-id', loteId);

            // Restaura o formulário
            $form.find('button[type="submit"]').html('<i class="fas fa-plus me-2"></i> Adicionar').attr('title', 'Adicionar Produto').removeClass('btn-warning').addClass('btn-primary');
            $form.find('.btn-cancelar-edicao').remove();
            $form.removeData('editing-row');
        } else {
           /* const produtoHtml = `
                 <tr data-lote-item-id="${loteItemId}" data-produto-id="${produtoId}">
                     <td>${produtoTexto}</td>
                     <td class="text-end">${quantidadeTexto}</td>
                     <td class="text-center">
                         <button type="button" class="btn btn-sm btn-warning btn-editar-produto-da-lista me-1" title="Editar Item">
                             <i class="fas fa-pencil-alt me-1"></i> Editar
                         </button>
                         <button type="button" class="btn btn-sm btn-danger btn-remover-produto-da-lista" title="Remover Item">
                             <i class="fas fa-trash me-1"></i> Remover
                         </button>
                     </td>
                 </tr>
             `;*/

              const produtoHtml = `
             <tr data-lote-id="${loteId}" data-produto-id="${produtoId}">
                 <td>${produtoTexto}</td>
                 <td class="text-end">${quantidadeTexto}</td>
                 <td class="text-center">
                     <button type="button" class="btn btn-sm btn-warning btn-editar-produto-da-lista me-1">Editar</button>
                     <button type="button" class="btn btn-sm btn-danger btn-remover-produto-da-lista">Remover</button>
                 </td>
             </tr>
         `;
            $listaProdutos.append(produtoHtml);
        }

        $produtoSelect.val(null).trigger('change');
        $loteSelect.val(null).trigger('change').prop('disabled', true);
        $quantidadeInput.val('');
    });

    $modalGerenciarFila.on('click', '.btn-editar-produto-da-lista', function () {
        const $row = $(this).closest('tr');
        const $form = $row.closest('.card-cliente-na-fila').find('.form-adicionar-produto-ao-cliente');

        const $produtoSelect = $form.find('.select-produto-estoque');
        const $loteSelect = $form.find('.select-lote-estoque');
        const $quantidadeInput = $form.find('input[type="number"]');

        // Pega os dados do item que está a ser editado
       // const loteItemId = $row.data('lote-item-id');
        const loteId = $row.data('lote-id'); 
       const produtoId = $row.data('produto-id');
        const quantidade = parseFloat($row.find('td:nth-child(2)').text());

        // --- LÓGICA DE PREENCHIMENTO ---

        $form.data('editing-row', $row);
        $quantidadeInput.val(quantidade);

        // Limpa e prepara os dropdowns
        $produtoSelect.empty().prop('disabled', true).select2({ placeholder: 'A carregar produtos...', theme: "bootstrap-5", dropdownParent: $modalGerenciarFila });
        $loteSelect.empty().prop('disabled', true).select2({ placeholder: 'Selecione um produto primeiro', theme: "bootstrap-5", dropdownParent: $modalGerenciarFila });

        // ETAPA 1: Recupera do banco todos os produtos disponíveis
        $.ajax({
            url: 'ajax_router.php?action=getProdutosDisponiveisEmEstoque',
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.results) {
                // Inicializa o Select2 com a lista de todos os produtos
                $produtoSelect.select2({
                    placeholder: 'Selecione um produto do estoque...',
                    theme: "bootstrap-5",
                    dropdownParent: $modalGerenciarFila,
                    language: "pt-BR",
                    data: response.results
                });

                // ETAPA 2: O sistema seleciona o produto que será editado e aciona o 'change'
                // para que o dropdown de lotes seja configurado corretamente.
                $produtoSelect.val(produtoId).trigger('change');
                $produtoSelect.prop('disabled', false);

                // --- INÍCIO DA CORREÇÃO ---
                // ETAPA 3: Pré-selecionar o Lote específico que está a ser editado.
                // Como o select de lote agora é carregado via AJAX, a opção para o lote
                // que queremos editar pode ainda não existir. Vamos criá-la manualmente.

                // 3.1. Obter o texto do lote a partir da linha da tabela.
                const textoCompletoProduto = $row.find('td:first').text();
                // Ex: "Produto X (Lote: 1234/25)" -> extrai "1234/25"
                const textoDoLote = textoCompletoProduto.split(' (')[1]?.replace(')', '') || 'Lote não encontrado';

                // 3.2. Criar uma nova <option> com os dados do lote a ser editado.
                // Os parâmetros são: (texto, valor, defaultSelected, selected)
                const optionDoLote = new Option(textoDoLote, loteId, true, true);

                // 3.3. Adicionar esta opção ao select e notificar o Select2 da mudança.
                $loteSelect.append(optionDoLote).trigger('change');

                // 3.4. Garantir que o select de lote está habilitado para o utilizador.
                $loteSelect.prop('disabled', false);
                // --- FIM DA CORREÇÃO ---

            }
        }).fail(function () {
            notificacaoErro('Erro', 'Não foi possível carregar a lista de produtos.');
        });

        // ETAPA 4: Altera os botões para o modo de edição
        const $submitButton = $form.find('button[type="submit"]');
        $submitButton.html('<i class="fas fa-check"></i> Atualizar').attr('title', 'Atualizar Produto').removeClass('btn-primary').addClass('btn-warning');

        if ($form.find('.btn-cancelar-edicao').length === 0) {
            $submitButton.after('<button type="button" class="btn btn-sm btn-secondary btn-cancelar-edicao ms-2" title="Cancelar Edição"><i class="fas fa-times"></i> Cancelar</button>');
        }
    });

    $modalGerenciarFila.on('click', '.btn-remover-produto-da-lista', function () {
        const $row = $(this).closest('tr');
        const produtoTexto = $row.find('td:first').text();
        const quantidade = $row.find('td:nth-child(2)').text();

        confirmacaoAcao(
            `Remover Item?`,
            `Você está prestes a remover o item "${produtoTexto}" com quantidade ${quantidade}. Esta ação não pode ser desfeita!`
        ).then((result) => {
            if (result.isConfirmed) {
                $row.remove();
                if ($row.closest('.lista-produtos-cliente').find('tr').length === 0) {
                    $row.closest('.card-cliente-na-fila').remove();
                    if ($containerClientesNoModal.find('.card-cliente-na-fila').length === 0) {
                        $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');
                    }
                }
            }
        });
    });

    // Evento para quando um produto é selecionado, para carregar os lotes correspondentes
    $modalGerenciarFila.on('change', '.select-produto-estoque', function () {
        const $produtoSelect = $(this);
        const $card = $produtoSelect.closest('.card-cliente-na-fila');
        const $loteSelect = $card.find('.select-lote-estoque');
        const produtoId = $produtoSelect.val();

        // Limpa e desativa o dropdown de lote para uma nova seleção
        $loteSelect.val(null).trigger('change');
        $loteSelect.prop('disabled', true);

        if (produtoId) {
            // Se um produto foi selecionado, ativa o dropdown de lote e o configura
            // para buscar os lotes via AJAX.
            $loteSelect.prop('disabled', false).select2({
                placeholder: 'Selecione um lote...',
                theme: "bootstrap-5",
                dropdownParent: $modalGerenciarFila,
                language: "pt-BR",
                ajax: {
                    url: 'ajax_router.php?action=getLotesComSaldoPorProduto',
                    dataType: 'json',
                    data: function (params) {
                        return {
                            produto_id: produtoId
                        };
                    },
                    processResults: function (data) {
                        return { results: data.results };
                    }
                }
            });
        }
    });

    $modalGerenciarFila.on('click', '.btn-cancelar-edicao', function () {
        const $form = $(this).closest('form');

        // Limpa o formulário e o estado de edição
        $form.find('.select-produto-estoque').val(null).trigger('change');
        $form.find('.select-lote-estoque').val(null).trigger('change').prop('disabled', true);
        $form.find('input[type="number"]').val('');
        $form.removeData('editing-row');

        // Restaura o botão de Adicionar e remove o de Cancelar
        $form.find('button[type="submit"]').html('<i class="fas fa-plus me-2"></i> +').attr('title', 'Adicionar Produto').removeClass('btn-warning').addClass('btn-primary');
        $(this).remove();
    });

    $('#btn-salvar-e-fechar-fila').on('click', function () {
        const $botaoSalvar = $(this);
        const filaData = [];

        $containerClientesNoModal.find('.card-cliente-na-fila').each(function () {
            const $card = $(this);
            const clienteId = $card.data('cliente-id');
            const produtos = [];
            $card.find('.lista-produtos-cliente tr').each(function () {
                const $linhaProduto = $(this);
                /* produtos.push({
                     loteItemId: $linhaProduto.data('lote-item-id'),
                     quantidade: parseFloat($linhaProduto.find('td:nth-child(2)').text())
                 });*/
                produtos.push({
                    produtoId: $linhaProduto.data('produto-id'), // Adiciona o ID do produto
                    loteId: $linhaProduto.data('lote-id'), // Usa o novo atributo data
                    quantidade: parseFloat($linhaProduto.find('td:nth-child(2)').text())
                });

            });
            if (produtos.length > 0) {
                filaData.push({ clienteId: clienteId, produtos: produtos });
            }
        });

        if (filaData.length === 0) {
            notificacaoErro('Fila Vazia', 'Nenhum produto foi adicionado. Adicione produtos antes de concluir.');
            return;
        }

        // Correção: URL limpa, sem caracteres inválidos
        let ajaxUrl = 'ajax_router.php?action=salvarFilaComposta';
        let ajaxData = {
            carregamento_id: carregamentoId,
            fila_data: JSON.stringify(filaData),
            csrf_token: csrfToken
        };

        if (modoModal === 'edicao' && filaIdParaEditar) {
            ajaxUrl = 'ajax_router.php?action=atualizarFilaComposta';
            ajaxData.fila_id = filaIdParaEditar;
        }

        $botaoSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalGerenciarFila.modal('hide');
                notificacaoSucesso('Sucesso!', 'Fila salva com sucesso!');
                recarregarETabelaPrincipal();
            } else {
                notificacaoErro('Erro ao Salvar', response.message || 'Erro desconhecido ao salvar a fila.');
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            // Tratamento adicional para erro de parsing JSON
            let errorMessage = 'Não foi possível salvar a fila.';
            if (textStatus === 'parsererror') {
                errorMessage = 'Resposta inválida do servidor. Verifique se o servidor retornou JSON válido.';
                console.error('Resposta bruta do servidor:', jqXHR.responseText);
            } else {
                errorMessage = `Erro de comunicação: ${textStatus} - ${errorThrown}`;
            }
            notificacaoErro('Erro de Comunicação', errorMessage);
        }).always(function () {
            $botaoSalvar.prop('disabled', false).html('<i class="fas fa-check me-2"></i> Concluir');
            modoModal = 'inclusao';
            filaIdParaEditar = null;
        });
    });

    /**
     * Evento para o botão "Conferir e Finalizar Carregamento".
     * Busca os dados consolidados e abre o modal de conferência.
     */
    $('#btn-abrir-conferencia').on('click', function () {
        const $tbody = $('#tbody-resumo-conferencia');
        $tbody.html('<tr><td colspan="5" class="text-center">A carregar dados para conferência...</td></tr>');

        // Abre o modal de conferência
        const modalConferencia = new bootstrap.Modal(document.getElementById('modal-conferencia-final'));
        modalConferencia.show();

        // Faz a chamada AJAX para buscar os dados
        $.ajax({
            url: 'ajax_router.php?action=getDadosConferencia',
            type: 'POST',
            data: {
                carregamento_id: carregamentoId,
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data) {
                $tbody.empty(); // Limpa a mensagem "A carregar..."

                if (response.data.length === 0) {
                    $tbody.html('<tr><td colspan="5" class="text-center text-muted">Este carregamento não possui itens para conferir.</td></tr>');
                    return;
                }

                let haDiscrepancia = false;

                // Itera sobre os itens e constrói a tabela de resumo
                response.data.forEach(item => {
                    const qtdCarregamento = parseFloat(item.car_item_quantidade);
                    const qtdEstoque = parseFloat(item.estoque_pendente);
                    let statusHtml = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> OK</span>';

                    if (qtdCarregamento > qtdEstoque) {
                        statusHtml = `<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i> Estoque Insuficiente</span>`;
                        haDiscrepancia = true;
                    }

                    const linhaHtml = `
                        <tr>
                            <td>${item.prod_descricao}</td>
                            <td>${item.lote_completo_calculado}</td>
                            <td class="text-end">${qtdCarregamento.toFixed(3)}</td>
                            <td class="text-end">${qtdEstoque.toFixed(3)}</td>
                            <td class="text-center">${statusHtml}</td>
                        </tr>
                    `;
                    $tbody.append(linhaHtml);
                });

                // Mostra o aviso e a opção de forçar baixa se houver discrepância
                if (haDiscrepancia) {
                    $('#aviso-discrepancia-estoque').html('<div class="alert alert-danger"><strong>Atenção!</strong> Alguns itens têm uma quantidade no carregamento maior que o estoque disponível. A baixa não será permitida a menos que a opção "forçar baixa" seja marcada.</div>');
                    $('#container-forcar-baixa').removeClass('d-none');
                } else {
                    $('#aviso-discrepancia-estoque').html('');
                    $('#container-forcar-baixa').addClass('d-none');
                    $('#forcar-baixa-estoque').prop('checked', false);
                }

            } else {
                notificacaoErro('Erro!', response.message || 'Não foi possível obter os dados para conferência.');
                modalConferencia.hide();
            }
        }).fail(function () {
            modalConferencia.hide();
        });
    });

    /**
     * Evento para o botão de confirmação final, DENTRO do modal de conferência.
     * Este evento executa a baixa de estoque no backend.
     */
    $('#btn-confirmar-baixa-estoque').on('click', function () {
        const $botaoConfirmar = $(this);
        const forcarBaixa = $('#forcar-baixa-estoque').is(':checked');
        const haDiscrepancia = !$('#container-forcar-baixa').hasClass('d-none');

        // Validação final: se há discrepância, a opção de forçar precisa estar marcada.
        if (haDiscrepancia && !forcarBaixa) {
            notificacaoErro('Ação Bloqueada', 'Não é possível finalizar com estoque insuficiente a menos que a opção "forçar baixa" seja marcada.');
            return;
        }

        // Feedback visual e desabilitar botão
        $botaoConfirmar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> A Finalizar...');

        // Chamada AJAX para a ação final
        $.ajax({
            url: 'ajax_router.php?action=confirmarBaixaEstoque',
            type: 'POST',
            data: {
                carregamento_id: carregamentoId,
                forcar_baixa: forcarBaixa,
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                // Esconde o modal de conferência
                const modalConferencia = bootstrap.Modal.getInstance(document.getElementById('modal-conferencia-final'));
                modalConferencia.hide();

                // Exibe a notificação de sucesso
                notificacaoSucesso('Sucesso!', response.message);

                // Recarrega a tabela principal e o cabeçalho para refletir o novo status "FINALIZADO"
                recarregarETabelaPrincipal();
                // A função preencherCabecalho precisa ser atualizada para receber os novos dados
                // Por enquanto, um reload resolve de forma mais simples
                location.reload();

            } else {
                notificacaoErro('Erro ao Finalizar', response.message);
            }
        }).fail(function () {
            // O nosso tratador de erros global já exibe a mensagem de erro de comunicação.
        }).always(function () {
            // Reabilita o botão, independentemente do resultado
            $botaoConfirmar.prop('disabled', false).html('<i class="fas fa-truck-loading me-2"></i> Confirmar e Dar Baixa no Estoque');
        });
    });

    $tabelaComposicaoBody.on('click', '.btn-remover-fila-principal', function () {
        const $botao = $(this);
        const filaId = $botao.closest('tr').data('fila-id');
        const filaSequencial = $botao.data('fila-sequencial');

        if (!filaId) {
            notificacaoErro('Erro Interno', 'Não foi possível identificar a fila a ser removida.');
            return;
        }

        confirmacaoAcao(
            `Remover Fila Nº ${filaSequencial}?`,
            "Todos os produtos desta fila serão removidos. Esta ação não pode ser desfeita!"
        ).then((result) => {
            if (result.isConfirmed) {
                executarRemocaoFila(filaId);
            }
        });
    });

    $tabelaComposicaoBody.on('click', '.btn-editar-fila-principal', function () {
        const filaId = $(this).data('fila-id');
        const filaSequencial = $(this).data('fila-sequencial');

        // Apenas define o estado e abre o modal
        modoModal = 'edicao';
        filaIdParaEditar = filaId;

        // Atualiza o título do modal
        $modalGerenciarFila.find('.modal-title').text(`Editar Fila ${String(filaSequencial).padStart(2, '0')}`);
        $modalGerenciarFila.modal('show');
    });

    // --- INICIALIZAÇÃO DA PÁGINA ---
    preencherCabecalho();
    inicializarSelectClienteModal();
    recarregarETabelaPrincipal();
});