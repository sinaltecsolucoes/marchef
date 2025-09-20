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
    const car_ordem_expedicao_id = carregamentoData.header.car_ordem_expedicao_id;
    let modoModal = 'inclusao'; // Controle explícito: 'inclusao' ou 'edicao'
    let filaIdParaEditar = null; // Armazena o ID da fila para edição
    let dadosPoolOE = null; // Armazena os dados da OE (Pool)
    let dadosFilasAtuais = []; // Armazena os dados das filas já carregadas

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
            placeholder: 'Selecione um cliente...',
            theme: "bootstrap-5",
            dropdownParent: $modalGerenciarFila,
            language: "pt-BR"
            // O AJAX removido.
        });
    }

    function inicializarSelectProdutoNoCard(selectId) {
        const $select = $('#' + selectId);
        $select.select2({
            placeholder: 'Selecione um produto...',
            theme: "bootstrap-5",
            dropdownParent: $modalGerenciarFila,
            language: "pt-BR"
        });
        // O AJAX foi removido.
    }

    function recarregarETabelaPrincipal() {
        console.log("Buscando dados atualizados do carregamento...");
        $.ajax({
            url: `ajax_router.php?action=getCarregamentoDetalhes&id=${carregamentoId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data) {
                dadosFilasAtuais = response.data.filas || [];
                const statusCarregamento = response.data.header.car_status;
                const isFinalizado = (statusCarregamento === 'FINALIZADO' || statusCarregamento === 'CANCELADO');

                const filas = response.data.filas;
                $tabelaComposicaoBody.empty();

                if (!filas || filas.length === 0) {
                    $tabelaComposicaoBody.html('<tr><td colspan="7" class="text-center text-muted">Nenhuma fila adicionada.</td></tr>');
                    return;
                }

                filas.forEach(fila => {
                    const totalItensNaFila = fila.itens ? fila.itens.length : 0;
                    const numSequencial = String(fila.fila_numero_sequencial).padStart(2, '0');

                    let fotoBtnHtml = '';
                    const temFotos = fila.total_fotos && fila.total_fotos > 0;

                    // 1. Cria o botão da galeria se existirem fotos
                    if (temFotos) {
                        // Lógica para singular/plural
                        const textoBotao = fila.total_fotos > 1 ? 'Ver Fotos' : 'Ver Foto';

                        const textoHtmlResponsivo = `<span class="d-none d-sm-inline ms-1">${textoBotao}</span>`;

                        fotoBtnHtml = `
                        <button class="btn btn-sm btn-info btn-ver-galeria ms-2" 
                                data-fila-id="${fila.fila_id}" 
                                title="Ver ${fila.total_fotos} Foto(s) da Fila">
                            <i class="fas fa-images"></i>  ${textoHtmlResponsivo} (${fila.total_fotos})
                        </button>
                    `;
                    }

                    // Monta o conteúdo da célula do número da fila de forma condicional.
                    let numSequencialHtml;
                    if (isFinalizado && temFotos) {
                        // Se finalizado, cria a estrutura flex para empilhar o número e o botão.
                        numSequencialHtml = `
                        <div class="d-flex flex-column align-items-center">
                            <span>${numSequencial}</span>
                            ${fotoBtnHtml.replace('ms-2', 'mt-1')}
                        </div>
                    `;
                    } else {
                        // Se não estiver finalizado, o conteúdo é apenas o número.
                        numSequencialHtml = numSequencial;
                    }

                    // Monta o HTML das ações (a lógica anterior continua perfeita).
                    let acoesHtml = `
                    <div class="d-flex flex-column align-items-center">
                        <div class="d-flex">
                            <button class="btn btn-sm btn-warning btn-editar-fila-principal me-1" data-fila-id="${fila.fila_id}" data-fila-sequencial="${numSequencial}" title="Editar Fila">
                                <i class="fas fa-pencil-alt"></i> Editar
                            </button>
                            <button class="btn btn-sm btn-danger btn-remover-fila-principal" data-fila-id="${fila.fila_id}" data-fila-sequencial="${numSequencial}" title="Remover Fila Completa">
                                <i class="fas fa-trash"></i> Remover
                            </button>
                        </div>
                `;
                    if (!isFinalizado && temFotos) {
                        acoesHtml += fotoBtnHtml.replace('ms-2', 'mt-1');
                    }
                    acoesHtml += `</div>`;


                    if (totalItensNaFila > 0) {
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

                                if (isFirstRowOfQueue && index === 0) {
                                    $linha.append(`<td class="text-center align-middle" rowspan="${totalItensNaFila}">${numSequencialHtml}</td>`);
                                    $linha.append(`<td class="text-center align-middle coluna-acoes" rowspan="${totalItensNaFila}">${acoesHtml}</td>`);
                                }

                                if (index === 0) {
                                    $linha.append(`<td class="align-middle font-small" rowspan="${totalItensDoCliente}">${nomeCliente}</td>`);
                                }

                                $linha.append(`<td class="align-middle font-small">${item.prod_descricao} (Cód: ${item.prod_codigo_interno})</td>`);
                                $linha.append(`<td class="text-center align-middle font-small">${item.lote_completo_calculado || 'N/A'}</td>`);
                                $linha.append(`<td class="text-center align-middle font-small">${item.cliente_lote_nome || 'N/A'}</td>`);
                                $linha.append(`<td class="text-end align-middle font-small">${parseFloat(item.car_item_quantidade).toFixed(3)}</td>`);
                                $tabelaComposicaoBody.append($linha);
                            });
                            isFirstRowOfQueue = false;
                        }
                    } else {
                        // Caso a fila não tenha itens
                        const $linha = $(`<tr data-fila-id="${fila.fila_id}">`);
                        $linha.append(`<td class="text-center align-middle">${numSequencialHtml}</td>`);
                        $linha.append(`<td class="text-center align-middle coluna-acoes">${acoesHtml}</td>`);
                        $linha.append(`<td colspan="5" class="text-center text-muted">Nenhum item nesta fila.</td>`);
                        $tabelaComposicaoBody.append($linha);
                    }
                });
            } else {
                $tabelaComposicaoBody.html(`<tr><td colspan="7" class="text-center text-danger">Erro ao carregar os dados: ${response.message || ''}</td></tr>`);
            }
            calcularESincronizarSaldosPool();
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

    /**
    * Busca os dados completos da Ordem de Expedição (o "Pool").
    * Esta função reutiliza a rota AJAX que o módulo de OE já usa.
    */
    function carregarPoolDaOrdem(oeId) {
        if (!oeId) {
            console.error("ID da Ordem de Expedição não encontrado no carregamento.");
            $('#tbody-pool-carregamento').html('<tr><td colspan="5" class="text-center text-danger">Erro: Este carregamento não está vinculado a nenhuma Ordem de Expedição.</td></tr>');
            return;
        }

        $.ajax({
            url: 'ajax_router.php?action=getOrdemExpedicaoCompleta',
            type: 'POST',
            data: {
                oe_id: oeId,
                csrf_token: csrfToken // reutiliza o token global
            },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    dadosPoolOE = response.data; // Salva os dados do pool globalmente

                    // Passa os dados para a função de renderização
                    recarregarETabelaPrincipal();
                    //renderizarPool(response.data);
                } else {
                    $('#tbody-pool-carregamento').html(`<tr><td colspan="5" class="text-center text-danger">Erro ao carregar o pool: ${response.message}</td></tr>`);
                }
            },
            error: function () {
                $('#tbody-pool-carregamento').html('<tr><td colspan="5" class="text-center text-danger">Erro de comunicação ao buscar dados da OE.</td></tr>');
            }
        });
    }

    /**
     * Renderiza os dados da OE na tabela "Pool de Itens Pendentes".
     * Por enquanto, apenas exibe o total. No Passo 3, faremos o cálculo de "pendente".
     */
    function renderizarPool(ordem) {
        const $tbody = $('#tbody-pool-carregamento');
        $tbody.empty(); // Limpa o "Carregando..."

        if (!ordem.pedidos || ordem.pedidos.length === 0) {
            $tbody.html('<tr><td colspan="5" class="text-center text-muted">A Ordem de Expedição vinculada não possui itens.</td></tr>');
            return;
        }

        // Itera sobre cada pedido/cliente na OE
        ordem.pedidos.forEach(pedido => {
            const nomeCliente = `${pedido.ent_razao_social || 'N/A'} (Pedido: ${pedido.oep_numero_pedido || 'N/A'})`;

            if (pedido.itens && pedido.itens.length > 0) {
                // Itera sobre cada item dentro do pedido
                pedido.itens.forEach((item, index) => {
                    const $linha = $('<tr>', {
                        'data-oei-id': item.oei_id, // ID do item da OE
                        'data-alocacao-id': item.oei_alocacao_id // ID da alocação (do estoque)
                    });

                    // Coluna Cliente/Pedido (só aparece na primeira linha do grupo)
                    if (index === 0) {
                        $linha.append(`<td class="align-middle" rowspan="${pedido.itens.length}">${nomeCliente}</td>`);
                    }

                    // Colunas do Item
                    $linha.append(`<td class="align-middle">${item.prod_descricao || 'N/A'}</td>`);
                    $linha.append(`<td class="align-middle">${item.lote_completo_calculado || 'N/A'}</td>`);
                    $linha.append(`<td class="align-middle">${item.endereco_completo || 'N/A'}</td>`);

                    // Coluna Quantidade (no futuro, será Qtd_Total - Qtd_Alocada_nas_Filas)
                    // $linha.append(`<td class="text-center align-middle fw-bold" data-qtd-total-oe="${item.oei_quantidade}">${formatarNumeroBrasileiro(item.oei_quantidade, 0)}</td>`);
                    const saldoPendente = parseFloat(item.saldo_pendente);
                    $linha.append(
                        `<td class="text-center align-middle fw-bold" 
                                data-qtd-total-oe="${item.oei_quantidade}"
                                data-qtd-pendente="${saldoPendente}">
                                ${formatarNumeroBrasileiro(saldoPendente, 0)}
                            </td>`
                    );
                    $tbody.append($linha);
                });
            }
        });
    }

    /**
 * Calcula o saldo pendente (Total OE - Total Alocado nas Filas)
 * e armazena em dadosPoolOE.
 * Depois, atualiza a UI (Tabela do Pool e Dropdowns do Modal).
 */
    function calcularESincronizarSaldosPool() {
        if (!dadosPoolOE || !dadosPoolOE.pedidos) return; // Pool não carregado
        if (!dadosFilasAtuais) return; // Filas não carregadas

        // 1. Criar um "mapa" de tudo que já foi alocado nas filas
        //    Chave: item_emb_id, Valor: quantidade total
        const mapaAlocado = new Map();
        dadosFilasAtuais.forEach(fila => {
            (fila.itens || []).forEach(item => {
                const itemEmbId = item.item_emb_id; // <-- A Chave Comum (do PHP)
                const quantidade = parseFloat(item.car_item_quantidade);
                if (itemEmbId) {
                    const totalAtual = mapaAlocado.get(itemEmbId) || 0;
                    mapaAlocado.set(itemEmbId, totalAtual + quantidade);
                }
            });
        });

        // 2. Iterar sobre o Pool e calcular o saldo pendente
        dadosPoolOE.pedidos.forEach(pedido => {
            (pedido.itens || []).forEach(item => {
                const itemEmbId = item.item_emb_id; // <-- A Chave Comum (do PHP)
                const totalOE = parseFloat(item.oei_quantidade);
                const totalAlocado = mapaAlocado.get(itemEmbId) || 0;

                // 3. Salva o novo saldoPendente dentro do objeto do Pool
                item.saldo_pendente = totalOE - totalAlocado;
            });
        });

        // 4. Agora que o dadosPoolOE está atualizado, renderiza a UI
        renderizarPool(dadosPoolOE);
        popularClientesDoPool(); // Repopula o modal com os saldos corretos
    }

    /**
     * Popula o dropdown de Clientes do modal (Passo 1)
     * lendo os dados da variável 'dadosPoolOE'
    */
    function popularClientesDoPool() {
        $selectClienteParaFila.empty().append('<option value="">Selecione um cliente...</option>');

        if (!dadosPoolOE || !dadosPoolOE.pedidos) {
            notificacaoErro("Erro", "Não foi possível carregar os dados da Ordem de Expedição (Pool).");
            return;
        }

        // Cria uma lista única de clientes a partir do Pool
        const clientesDoPool = new Map();
        dadosPoolOE.pedidos.forEach(pedido => {
            if (pedido.itens && pedido.itens.length > 0) { // Só mostra clientes que têm itens
                clientesDoPool.set(pedido.oep_cliente_id, {
                    id: pedido.oep_cliente_id,
                    text: pedido.ent_razao_social || 'Cliente N/A'
                });
            }
        });

        // Popula o select
        clientesDoPool.forEach(cliente => {
            $selectClienteParaFila.append(new Option(cliente.text, cliente.id));
        });
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
            popularClientesDoPool();
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

        // 1. Clona o template HTML
        const $novoCard = $($('#template-card-cliente-modal').html());
        const $selectItemPool = $novoCard.find('.select-item-do-pool');

        // Gera um ID único para o select (importante para o Select2)
        const selectIdUnico = `select-item-${clienteId}-${new Date().getTime()}`;
        $selectItemPool.attr('id', selectIdUnico);

        // 2. Define os dados do card
        $novoCard.attr('data-cliente-id', clienteId);
        $novoCard.find('.nome-cliente-card').text(`CLIENTE - ${clienteNome}`);
        $containerClientesNoModal.find('p.text-muted').remove();
        $containerClientesNoModal.append($novoCard);

        // 3. Inicializa o Select2 no novo elemento
        $selectItemPool.select2({
            placeholder: 'Selecione um item do pool...',
            theme: "bootstrap-5",
            dropdownParent: $modalGerenciarFila,
            language: "pt-BR",
            // Template para formatar o texto (remove espaços em branco)
            templateResult: function (data) {
                if (!data.id) { return data.text; }
                return $.parseHTML(data.text.replace(/(\r\n|\n|\r|\s\s+)/gm, ""));
            },
            templateSelection: function (data) {
                if (!data.id) { return data.text; }
                return $.parseHTML(data.text.replace(/(\r\n|\n|\r|\s\s+)/gm, ""));
            }
        });

        // 4. Popula o Select2 com os itens do Pool para este cliente
        $selectItemPool.empty().append('<option value="">Selecione um item...</option>');

        let itensEncontrados = 0;
        dadosPoolOE.pedidos.forEach(pedido => {
            if (pedido.oep_cliente_id == clienteId && pedido.itens) {
                pedido.itens.forEach(item => {
                    const saldoPendente = parseFloat(item.saldo_pendente);

                    // NOTA: Por enquanto, estamos mostrando a quantidade TOTAL da OE.
                    // O cálculo do saldo "pendente" (Total - Alocado nas filas) é um próximo passo.
                    if (saldoPendente > 0) {
                        const textoOpcao = `
                            ${item.prod_descricao} | 
                            Lote: ${item.lote_completo_calculado} | 
                            End: ${item.endereco_completo} 
                            (Pendente: ${formatarNumeroBrasileiro(saldoPendente, 0)}
                             `;

                        // O 'value' é o ID da alocação (a chave do item no estoque).
                        const $option = new Option(textoOpcao, item.oei_alocacao_id);

                        // Também guardamos o objeto 'item' inteiro dentro da opção
                        // para o próximo passo (submissão do formulário).
                        $($option).data('item-completo', item);

                        $selectItemPool.append($option);
                        itensEncontrados++;
                    }
                });
            }
        });

        if (itensEncontrados === 0) {
            $selectItemPool.empty().append('<option value="">Nenhum item pendente para este cliente no Pool.</option>');
            $selectItemPool.prop('disabled', true);
            $novoCard.find('button[type="submit"]').prop('disabled', true);
            $novoCard.find('input[type="number"]').prop('disabled', true);
        }

        // 5. Limpa o select de cliente
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
        const $selectItemPool = $form.find('.select-item-do-pool');
        const $quantidadeInput = $form.find('input[type="number"]');
        const $listaProdutos = $card.find('.lista-produtos-cliente');

        const $selectedOption = $selectItemPool.find('option:selected');
        const alocacaoId = $selectedOption.val(); // Este é o 'oei_alocacao_id'
        const item = $selectedOption.data('item-completo');
        const quantidade = parseFloat($quantidadeInput.val());

        // --- Validação ---
        if (!alocacaoId || !item) {
            notificacaoErro('Item Inválido', 'Por favor, selecione um item válido do pool.');
            return;
        }

        if (!quantidade || quantidade <= 0) {
            notificacaoErro('Quantidade Inválida', 'Por favor, insira uma quantidade maior que zero.');
            return;
        }

        // Validação de Saldo (usando o total da OE por enquanto)
        const qtdTotalOE = parseFloat(item.oei_quantidade);
        /* if (quantidade > qtdTotalOE) {
             notificacaoErro('Quantidade Inválida',
                 `A quantidade (${quantidade.toFixed(3)}) é maior que o total planejado na OE (${qtdTotalOE.toFixed(3)}).`);
             return;
         } */

        const saldoPendente = parseFloat(item.saldo_pendente);
        if (quantidade > saldoPendente) {
            notificacaoErro('Quantidade Inválida',
                `A quantidade (${quantidade.toFixed(3)}) é maior que o saldo pendente (${saldoPendente.toFixed(3)}).`);
            return;
        }

        // --- NOVA LÓGICA: PROCURAR ITEM EXISTENTE PARA SOMAR ---

        const $linhaExistente = $listaProdutos.find(`tr[data-alocacao-id="${alocacaoId}"]`);

        if ($linhaExistente.length > 0) {
            // 1. O ITEM JÁ EXISTE: Vamos somar

            const qtdAtual = parseFloat($linhaExistente.attr('data-quantidade'));
            const novaQtdTotal = qtdAtual + quantidade;

            // 1.1. Revalida o saldo com a nova soma
          /*  if (novaQtdTotal > qtdTotalOE) {
                notificacaoErro('Quantidade Excede o Total',
                    `Você já tinha ${qtdAtual.toFixed(3)} e adicionou ${quantidade.toFixed(3)}. O total ${novaQtdTotal.toFixed(3)} excede o planejado na OE (${qtdTotalOE.toFixed(3)}).`);
                return;
            }*/ if (novaQtdTotal > saldoPendente) { // <-- MUDANÇA (de qtdTotalOE para saldoPendente)
                notificacaoErro('Quantidade Excede o Saldo',
                    `Você já tinha ${qtdAtual.toFixed(3)} e adicionou ${quantidade.toFixed(3)}. O total ${novaQtdTotal.toFixed(3)} excede o saldo pendente (${saldoPendente.toFixed(3)}).`);
                return;
            }



            // 1.2. Atualiza os dados da linha
            $linhaExistente.attr('data-quantidade', novaQtdTotal); // Atualiza o data-attribute
            $linhaExistente.find('td:nth-child(2)').text(novaQtdTotal.toFixed(3)); // Atualiza o texto

            notificacaoSucesso('Item Somado', `+${quantidade.toFixed(3)} adicionado. Total agora: ${novaQtdTotal.toFixed(3)}`);

        } else {
            // 2. O ITEM É NOVO: Vamos adicionar

            // --- Monta a Linha da Tabela ---
            const produtoTexto = `
                ${item.prod_descricao} 
                (Lote: ${item.lote_completo_calculado} | End: ${item.endereco_completo})
            `;
            const quantidadeTexto = quantidade.toFixed(3);

            const produtoHtml = `
                <tr data-alocacao-id="${alocacaoId}" data-quantidade="${quantidade}">
                    <td>${produtoTexto.replace(/(\r\n|\n|\r|\s\s+)/gm, "")}</td>
                    <td class="text-end">${quantidadeTexto}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger btn-remover-produto-da-lista">Remover</button>
                    </td>
                </tr>
            `;
            $listaProdutos.append(produtoHtml);
        }

        // --- Limpa o formulário (em ambos os casos) ---
        $selectItemPool.val(null).trigger('change');
        $quantidadeInput.val('');
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
                /*produtos.push({
                    produtoId: $linhaProduto.data('produto-id'), // Adiciona o ID do produto
                    loteId: $linhaProduto.data('lote-id'), // Usa o novo atributo data
                    quantidade: parseFloat($linhaProduto.find('td:nth-child(2)').text())
                });*/
                produtos.push({
                    alocacaoId: $linhaProduto.data('alocacao-id'), // <-- MUDANÇA
                    quantidade: parseFloat($linhaProduto.data('quantidade')) // <-- MUDANÇA
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
        //let ajaxUrl = 'ajax_router.php?action=salvarFilaComposta';
        let ajaxUrl = 'ajax_router.php?action=salvarFilaDoPool';
        let ajaxData = {
            carregamento_id: carregamentoId,
            fila_data: JSON.stringify(filaData),
            csrf_token: csrfToken
        };

        if (modoModal === 'edicao' && filaIdParaEditar) {
            //ajaxUrl = 'ajax_router.php?action=atualizarFilaComposta';
            ajaxUrl = 'ajax_router.php?action=atualizarFilaDoPool';
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

    // Evento para o botão de ver a galeria de fotos na tabela principal
    $tabelaComposicaoBody.on('click', '.btn-ver-galeria', function () {
        const $botao = $(this);
        const filaId = $botao.data('fila-id');
        const filaNumero = String($botao.data('fila-sequencial') || $botao.closest('tr').find('td:first').text()).trim().padStart(2, '0');

        const $modal = $('#modal-galeria-fotos');
        const $modalBody = $('#galeria-modal-body');
        const $modalTitle = $('#modalGaleriaLabel');

        // 1. Prepara e abre o modal com a mensagem de "Carregando"
        $modalTitle.text(`Fotos da Fila Nº ${filaNumero}`);
        $modalBody.html('<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando fotos...</p>');
        const modalInstance = new bootstrap.Modal($modal[0]);
        modalInstance.show();

        // 2. Faz a chamada AJAX para buscar as fotos
        $.ajax({
            url: `ajax_router.php?action=getFotosDaFila&fila_id=${filaId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            $modalBody.empty(); // Limpa a mensagem "Carregando"

            if (response.success && response.data && response.data.length > 0) {
                const $containerFotos = $('<div class="row g-3"></div>');

                response.data.forEach(function (foto) {
                    const fotoHtml = `
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <a href="${foto.full_url}" data-lightbox="fila-${filaId}" data-title="Foto da Fila ${filaNumero}">
                            <img src="${foto.full_url}" class="img-fluid rounded shadow-sm" alt="Foto da Fila ${filaNumero}">
                        </a>
                    </div>
                `;
                    $containerFotos.append(fotoHtml);
                });

                $modalBody.append($containerFotos);
            } else {
                $modalBody.html('<p class="text-center text-muted">Nenhuma foto encontrada para esta fila.</p>');
            }
        }).fail(function () {
            $modalBody.html('<p class="text-center text-danger">Erro ao carregar as fotos. Tente novamente.</p>');
        });
    });

    // --- INICIALIZAÇÃO DA PÁGINA ---
    preencherCabecalho();
    inicializarSelectClienteModal();
    //recarregarETabelaPrincipal();
    carregarPoolDaOrdem(car_ordem_expedicao_id);
});