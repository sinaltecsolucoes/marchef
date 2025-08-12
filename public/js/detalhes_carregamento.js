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
                    const mappedData = data.data.map(item => ({ id: item.ent_codigo, text: item.ent_razao_social }));
                    return { results: mappedData };
                }
            }
        });
    }

    /* function inicializarSelectProdutoNoCard(selectId) {
         $('#' + selectId).select2({
             placeholder: 'Buscar produto no estoque...',
             theme: "bootstrap-5",
             dropdownParent: $modalGerenciarFila,
             language: "pt-BR",
             ajax: {
                 url: 'ajax_router.php?action=getItensDeEstoqueOptions',
                 dataType: 'json',
                 delay: 250,
                 data: function (params) { return { term: params.term }; },
                 processResults: function (data) { return { results: data.results }; }
             }
         });
     }*/

    /* function inicializarSelectProdutoNoCard(selectId) {
         $('#' + selectId).select2({
             placeholder: 'Selecione um produto...',
             theme: "bootstrap-5",
             dropdownParent: $modalGerenciarFila,
             language: "pt-BR",
             ajax: {
                 url: 'ajax_router.php?action=getProdutoOptions', // Busca produtos únicos
                 dataType: 'json',
                 data: function (params) { return { term: params.term, tipo_embalagem: 'Todos' }; },
                 processResults: function (data) { return { results: data.data }; }
             }
         });
     }*/

    function inicializarSelectProdutoNoCard(selectId) {
        const $select = $('#' + selectId);

        $.ajax({
            url: 'ajax_router.php?action=getProdutoOptions',
            type: 'GET',
            data: { tipo_embalagem: 'Todos' },
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data) {
                // Mapeia os dados do produto para o formato {id, text} que o Select2 precisa
                const produtosMapeados = response.data.map(function (produto) {
                    return {
                        id: produto.prod_codigo,
                        text: `${produto.prod_descricao} (Cód: ${produto.prod_codigo_interno || 'N/A'})`
                    };
                });

                $select.select2({
                    placeholder: 'Selecione um produto...',
                    theme: "bootstrap-5",
                    dropdownParent: $modalGerenciarFila,
                    language: "pt-BR",
                    data: produtosMapeados // Passa os dados já mapeados e pré-carregados
                });
            }
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
                    const totalItensNaFila = fila.itens ? fila.itens.length : 0;
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
                                    const numSequencial = String(fila.fila_numero_sequencial).padStart(2, '0');
                                    $linha.append(`<td rowspan="${totalItensNaFila}">${numSequencial}</td>`);
                                    $linha.append(`
                                        <td class="text-center align-middle" rowspan="${totalItensNaFila}">
                                            <button class="btn btn-sm btn-outline-warning btn-editar-fila-principal me-1" data-fila-id="${fila.fila_id}" data-fila-sequencial="${numSequencial}" title="Editar Fila">
                                                <i class="fas fa-pencil-alt"></i> Editar
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-remover-fila-principal" data-fila-sequencial="${numSequencial}" title="Remover Fila Completa">
                                                <i class="fas fa-trash"></i> Remover
                                            </button>
                                        </td>
                                    `);
                                }

                                if (index === 0) {
                                    $linha.append(`<td rowspan="${totalItensDoCliente}">${nomeCliente}</td>`);
                                }

                                $linha.append(`<td>${item.prod_descricao} (Cód: ${item.prod_codigo_interno})</td>`);
                                $linha.append(`<td>${item.lote_completo_calculado || 'N/A'}</td>`);
                                $linha.append(`<td>${item.cliente_lote_nome || 'N/A'}</td>`);
                                $linha.append(`<td class="text-end">${parseFloat(item.car_item_quantidade).toFixed(3)}</td>`);

                                $tabelaComposicaoBody.append($linha);
                            });
                            isFirstRowOfQueue = false;
                        }
                    }
                });
            } else {
                $tabelaComposicaoBody.html(`<tr><td colspan="7" class="text-center text-danger">Erro ao carregar os dados: ${response.message || ''}</td></tr>`);
            }
        }).fail(function () {
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
            $('#tabela-composicao-carregamento').find('th:nth-child(2), td:nth-child(2)').hide();

            // Esconde o card inteiro de Finalização
            $('#btn-abrir-conferencia').closest('.card').hide();

        } else {
            // Garante que os botões estejam visíveis para outros status como EM ANDAMENTO
            $('button[data-bs-target="#modal-gerenciar-fila"]').show();
            $('#tabela-composicao-carregamento').find('th:nth-child(2), td:nth-child(2)').show();
            $('#btn-abrir-conferencia').closest('.card').show();
        }
    }

    // --- EVENT HANDLERS ---
    $modalGerenciarFila.on('show.bs.modal', function (event) {
        console.log("Modal de fila aberto. Limpando estado anterior...");

        if ($('.select2-hidden-accessible').length) {
            $('.select2-hidden-accessible').select2('close');
        }

        // Sempre limpa o modal para garantir estado inicial consistente
        $selectClienteParaFila.val(null).trigger('change');
        $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');

        // Calcula o próximo número de fila apenas para inclusão
        if (modoModal === 'inclusao') {
            let proximoNumeroFila = 1;
            const filasNaTabela = [];
            $tabelaComposicaoBody.find('tr[data-fila-id]').each(function () {
                const filaId = $(this).data('fila-id');
                if (!filasNaTabela.includes(filaId)) {
                    filasNaTabela.push(filaId);
                }
            });
            proximoNumeroFila = filasNaTabela.length + 1;
            $('#numero-fila-modal').text(String(proximoNumeroFila).padStart(2, '0'));
        }
    });

    $modalGerenciarFila.on('shown.bs.modal', function (event) {
        console.log("Modal visível (shown.bs.modal). Modo:", modoModal);

        if (modoModal === 'edicao' && filaIdParaEditar) {
            console.log("Preenchendo modal com dados da fila ID:", filaIdParaEditar);
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
                                <tr data-lote-item-id="${produto.loteItemId}">
                                    <td>${produto.produtoTexto}</td>
                                    <td class="text-end">${parseFloat(produto.quantidade).toFixed(3)}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-secondary btn-sm btn-editar-produto-da-lista"><i class="fas fa-pencil-alt"></i> Editar</button>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-remover-produto-da-lista"><i class="fas fa-trash"></i> Remover</button>
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

    $modalGerenciarFila.on('hidden.bs.modal', function () {
        console.log("Modal fechado. Limpando estado...");
        modoModal = 'inclusao';
        filaIdParaEditar = null;
        $selectClienteParaFila.val(null).trigger('change');
        $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');
        $('#numero-fila-modal').text('1');
    });

    $('#btn-adicionar-cliente-a-fila').on('click', function () {
        const clienteSelecionado = $selectClienteParaFila.select2('data')[0];
        if (!clienteSelecionado || !clienteSelecionado.id) {
            notificacaoErro('Atenção', 'Por favor, selecione um cliente da lista.');
            return;
        }
        if ($containerClientesNoModal.find('.card-cliente-na-fila').length === 0) {
            $containerClientesNoModal.empty();
        }
        const clienteId = clienteSelecionado.id;
        const clienteNome = clienteSelecionado.text;
        const numeroCliente = $containerClientesNoModal.find('.card-cliente-na-fila').length + 1;
        const novoTitulo = `CLIENTE ${String(numeroCliente).padStart(2, '0')} - ${clienteNome}`;
        const selectIdUnico = `select-produto-${clienteId}-${new Date().getTime()}`;
        const $novoCard = $($('#template-card-cliente-modal').html());
        $novoCard.attr('data-cliente-id', clienteId);
        $novoCard.find('.nome-cliente-card').text(novoTitulo);
        $novoCard.find('.select-produto-estoque').attr('id', selectIdUnico);
        $containerClientesNoModal.append($novoCard);
        inicializarSelectProdutoNoCard(selectIdUnico);
        $selectClienteParaFila.val(null).trigger('change');
    });

    /*  $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (e) {
          e.preventDefault();
          const $form = $(this);
          const $produtoSelect = $form.find('.select-produto-estoque');
          const $quantidadeInput = $form.find('input[type="number"]');
          const produtoSelecionado = $produtoSelect.select2('data')[0];
          const quantidade = $quantidadeInput.val();
          if (!produtoSelecionado || !produtoSelecionado.id || !quantidade || parseFloat(quantidade) <= 0) {
              notificacaoErro('Atenção', 'Por favor, selecione um produto e insira uma quantidade válida.');
              return;
          }
          const $linhaSendoEditada = $form.data('editing-row');
          if ($linhaSendoEditada) {
              $linhaSendoEditada.attr('data-lote-item-id', produtoSelecionado.id);
              $linhaSendoEditada.find('td:nth-child(1)').text(produtoSelecionado.text);
              $linhaSendoEditada.find('td:nth-child(2)').text(parseFloat(quantidade).toFixed(3));
              $form.removeData('editing-row');
              $form.find('button[type="submit"]').html('<i class="fas fa-plus"></i> Adicionar Produto').removeClass('btn-warning').addClass('btn-primary');
          } else {
              const $listaProdutos = $form.closest('.card-cliente-na-fila').find('.lista-produtos-cliente');
              const produtoHtml = `<tr data-lote-item-id="${produtoSelecionado.id}"><td>${produtoSelecionado.text}</td><td class="text-end">${parseFloat(quantidade).toFixed(3)}</td><td class="text-center"><button type="button" class="btn btn-outline-secondary btn-sm btn-editar-produto-da-lista"><i class="fas fa-pencil-alt"></i> Editar</button> <button type="button" class="btn btn-outline-danger btn-sm btn-remover-produto-da-lista"><i class="fas fa-trash"></i> Remover</button></td></tr>`;
              $listaProdutos.append(produtoHtml);
          }
          $produtoSelect.val(null).trigger('change');
          $quantidadeInput.val('');
      });*/


    /* $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (e) {
         e.preventDefault();
         const $form = $(this);
         const $produtoSelect = $form.find('.select-produto-estoque');
         const $loteSelect = $form.find('.select-lote-estoque');
         const $quantidadeInput = $form.find('input[type="number"]');
 
         // Pega os dados dos campos
         const produtoSelecionado = $produtoSelect.select2('data')[0];
         const loteSelecionado = $loteSelect.select2('data')[0];
         const quantidade = $quantidadeInput.val();
 
         // Validação
         if (!loteSelecionado || !loteSelecionado.id || !quantidade || parseFloat(quantidade) <= 0) {
             notificacaoErro('Atenção', 'Por favor, selecione um produto, um lote e uma quantidade válida.');
             return;
         }
 
         const loteItemId = loteSelecionado.id;
         // Monta o texto completo para exibição na tabela
         const textoCompleto = `${produtoSelecionado.text} | ${loteSelecionado.text}`;
 
         // Verifica se estamos em modo de edição
         const $linhaSendoEditada = $form.data('editing-row');
 
         if ($linhaSendoEditada) {
             $linhaSendoEditada.attr('data-lote-item-id', loteItemId);
             $linhaSendoEditada.find('td:nth-child(1)').text(textoCompleto); // Atualiza o texto do produto/lote
             $linhaSendoEditada.find('td:nth-child(2)').text(parseFloat(quantidade).toFixed(3));
 
             $form.removeData('editing-row');
             $form.find('button[type="submit"]').html('<i class="fas fa-plus"></i>').attr('title', 'Adicionar Produto').removeClass('btn-warning').addClass('btn-primary');
 
         } else {
             const $listaProdutos = $form.closest('.card-cliente-na-fila').find('.lista-produtos-cliente');
             const produtoHtml = `
             <tr data-lote-item-id="${loteItemId}">
                 <td>${textoCompleto}</td>
                 <td class="text-end">${parseFloat(quantidade).toFixed(3)}</td>
                 <td class="text-center">
                     <button type="button" class="btn btn-outline-secondary btn-sm btn-editar-produto-da-lista"><i class="fas fa-pencil-alt"></i> Editar</button>
                     <button type="button" class="btn btn-outline-danger btn-sm btn-remover-produto-da-lista"><i class="fas fa-trash"></i> Remover</button>
                 </td>
             </tr>`;
             $listaProdutos.append(produtoHtml);
         }
 
         // Limpa os campos para a próxima ação
         $produtoSelect.val(null).trigger('change');
         $loteSelect.val(null).trigger('change').prop('disabled', true);
         $quantidadeInput.val('');
     });*/


    // /public/js/detalhes_carregamento.js (Versão com Validação Final Corrigida)

    /* $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (e) {
         e.preventDefault();
         const $form = $(this);
         const $produtoSelect = $form.find('.select-produto-estoque');
         const $loteSelect = $form.find('.select-lote-estoque');
         const $quantidadeInput = $form.find('input[type="number"]');
 
         // Pega os dados dos campos
         const produtoSelecionado = $produtoSelect.select2('data')[0];
         const loteSelecionado = $loteSelect.select2('data')[0];
         const quantidade = $quantidadeInput.val();
 
         // Agora validamos se AMBOS, produto e lote, estão selecionados.
         if (!produtoSelecionado || !produtoSelecionado.id || !loteSelecionado || !loteSelecionado.id || !quantidade || parseFloat(quantidade) <= 0) {
             notificacaoErro('Atenção', 'Por favor, selecione um produto, um lote e uma quantidade válida.');
             return; // Interrompe a execução se algo estiver em falta.
         }
 
         const loteItemId = loteSelecionado.id;
         const textoCompleto = `${produtoSelecionado.text} | ${loteSelecionado.text}`;
 
         const $linhaSendoEditada = $form.data('editing-row');
 
         if ($linhaSendoEditada) {
             $linhaSendoEditada.attr('data-lote-item-id', loteItemId);
             $linhaSendoEditada.find('td:nth-child(1)').text(textoCompleto);
             $linhaSendoEditada.find('td:nth-child(2)').text(parseFloat(quantidade).toFixed(3));
 
             $form.removeData('editing-row');
             $form.find('button[type="submit"]').html('<i class="fas fa-plus"></i>').attr('title', 'Adicionar Produto').removeClass('btn-warning').addClass('btn-primary');
 
         } else {
             const $listaProdutos = $form.closest('.card-cliente-na-fila').find('.lista-produtos-cliente');
             const produtoHtml = `
             <tr data-lote-item-id="${loteItemId}">
                 <td>${textoCompleto}</td>
                 <td class="text-end">${parseFloat(quantidade).toFixed(3)}</td>
                 <td class="text-center">
                     <button type="button" class="btn btn-outline-secondary btn-sm btn-editar-produto-da-lista"><i class="fas fa-pencil-alt"></i> Editar</button>
                     <button type="button" class="btn btn-outline-danger btn-sm btn-remover-produto-da-lista"><i class="fas fa-trash"></i> Remover</button>
                 </td>
             </tr>`;
             $listaProdutos.append(produtoHtml);
         }
 
         // Limpa os campos para a próxima ação
         $produtoSelect.val(null).trigger('change');
         $loteSelect.val(null).trigger('change').prop('disabled', true);
         $quantidadeInput.val('');
     });*/


    /* $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (event) {
         event.preventDefault();
         const $form = $(this);
         const produtoId = $form.find('.select-produto-estoque').val();
         const loteId = $form.find('.select-lote-estoque').val();
         const quantidade = parseFloat($form.find('input[type="number"]').val());
         const $card = $form.closest('.card-cliente-na-fila');
         const clienteId = $card.data('cliente-id');
 
         if (!produtoId || !loteId || isNaN(quantidade) || quantidade <= 0) {
             notificacaoErro('Dados Inválidos', 'Preencha todos os campos corretamente.');
             return;
         }
 
         // Corrige para pegar texto com Select2 (melhor que option:selected)
         const produtoData = $form.find('.select-produto-estoque').select2('data')[0];
         const loteData = $form.find('.select-lote-estoque').select2('data')[0];
         const produtoNome = produtoData ? produtoData.text : 'Produto Desconhecido';
         const loteNome = loteData ? loteData.text : 'Lote Desconhecido';
 
         // Gera a linha
         const $linha = $(`
         <tr data-lote-item-id="${loteId}">
             <td>${produtoNome} - ${loteNome}</td>
             <td class="text-end">${quantidade.toFixed(3)}</td>
             <td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-remover-produto-cliente"><i class="fas fa-trash"></i></button></td>
         </tr>
     `);
 
         // Log para depuração: Verifica se a linha foi gerada
         console.log('Linha gerada:', $linha[0].outerHTML);
 
         // Seleciona o tbody explicitamente
         const $tabelaBody = $card.find('.lista-produtos-cliente tbody');
 
         // Log para depuração: Verifica se o tbody foi encontrado
         console.log('Tbody encontrado:', $tabelaBody.length > 0 ? 'Sim' : 'Não');
         if ($tabelaBody.length === 0) {
             console.error('Erro: Tbody não encontrado na tabela .lista-produtos-cliente');
             return; // Para a execução se não encontrar
         }
 
         // Adiciona a linha ao tbody
         $tabelaBody.append($linha);
 
         // Log para confirmar adição
         console.log('Linha adicionada ao tbody. Número de linhas agora:', $tabelaBody.find('tr').length);
 
         // Limpa o formulário
         $form[0].reset();
         $form.find('.select-lote-estoque').val(null).trigger('change').prop('disabled', true);
     });*/

    // /public/js/detalhes_carregamento.js (Versão com Depuração)

    /* $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (e) {
         e.preventDefault();
         console.log("==========================================");
         console.log("PASSO 1: Formulário submetido. O botão '+' foi clicado.");
 
         const $form = $(this);
         const $produtoSelect = $form.find('.select-produto-estoque');
         const $loteSelect = $form.find('.select-lote-estoque');
         const $quantidadeInput = $form.find('input[type="number"]');
 
         const produtoSelecionado = $produtoSelect.select2('data')[0];
         const loteSelecionado = $loteSelect.select2('data')[0];
         const quantidade = $quantidadeInput.val();
 
         console.log("PASSO 2: Dados recolhidos do formulário.");
         console.log(" - Produto Selecionado:", produtoSelecionado);
         console.log(" - Lote Selecionado:", loteSelecionado);
         console.log(" - Quantidade:", quantidade);
 
         console.log("PASSO 3: A executar a validação...");
         if (!produtoSelecionado || !produtoSelecionado.id || !loteSelecionado || !loteSelecionado.id || !quantidade || parseFloat(quantidade) <= 0) {
             notificacaoErro('Atenção', 'Por favor, selecione um produto, um lote e uma quantidade válida.');
             console.log("FALHA: A validação falhou. A função foi interrompida.");
             console.log("==========================================");
             return;
         }
         console.log("SUCESSO: A validação passou.");
 
         const loteItemId = loteSelecionado.id;
         const textoCompleto = `${produtoSelecionado.text} | ${loteSelecionado.text}`;
         const $linhaSendoEditada = $form.data('editing-row');
 
         if ($linhaSendoEditada) {
             console.log("PASSO 4: Entrou no modo de EDIÇÃO.");
             // (Lógica de edição...)
             $linhaSendoEditada.attr('data-lote-item-id', loteItemId);
             $linhaSendoEditada.find('td:nth-child(1)').text(textoCompleto);
             $linhaSendoEditada.find('td:nth-child(2)').text(parseFloat(quantidade).toFixed(3));
             console.log("SUCESSO: Linha da tabela foi ATUALIZADA.");
 
             $form.removeData('editing-row');
             $form.find('button[type="submit"]').html('<i class="fas fa-plus"></i>').attr('title', 'Adicionar Produto').removeClass('btn-warning').addClass('btn-primary');
 
         } else {
             console.log("PASSO 4: Entrou no modo de ADIÇÃO.");
 
            // const $listaProdutos = $form.closest('.card-cliente-na-fila').find('.lista-produtos-cliente');
            // const $listaProdutos = $form.siblings('.table-responsive').find('.lista-produtos-cliente');
             const $listaProdutos = $form.closest('.card-body').find('.lista-produtos-cliente');
             console.log("PASSO 5: A tentar encontrar a tabela do cliente. Objeto encontrado:", $listaProdutos);
 
             if ($listaProdutos.length === 0) {
                 console.error("FALHA CRÍTICA: Não foi possível encontrar o elemento <tbody> com a classe '.lista-produtos-cliente' para adicionar o produto.");
             }
 
             const produtoHtml = `
             <tr data-lote-item-id="${loteItemId}">
                 <td>${textoCompleto}</td>
                 <td class="text-end">${parseFloat(quantidade).toFixed(3)}</td>
                 <td class="text-center">
                     <button type="button" class="btn btn-outline-secondary btn-sm btn-editar-produto-da-lista"><i class="fas fa-pencil-alt"></i> Editar</button>
                     <button type="button" class="btn btn-outline-danger btn-sm btn-remover-produto-da-lista"><i class="fas fa-trash"></i> Remover</button>
                 </td>
             </tr>`;
 
             $listaProdutos.append(produtoHtml);
             console.log("SUCESSO: A linha do produto foi adicionada à tabela do cliente.");
         }
 
         console.log("PASSO 6: A limpar os campos do formulário.");
         $produtoSelect.val(null).trigger('change');
         $loteSelect.val(null).trigger('change').prop('disabled', true);
         $quantidadeInput.val('');
         console.log("==========================================");
     });*/

    /*    $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (event) {
        event.preventDefault();
        const $form = $(this);
        const produtoId = $form.find('.select-produto-estoque').val();
        const loteId = $form.find('.select-lote-estoque').val();
        const quantidade = parseFloat($form.find('input[type="number"]').val());
        const $card = $form.closest('.card-cliente-na-fila');
        const clienteId = $card.data('cliente-id');
    
        if (!produtoId || !loteId || isNaN(quantidade) || quantidade <= 0) {
            notificacaoErro('Dados Inválidos', 'Preencha todos os campos corretamente.');
            return;
        }
    
        // Obtém o texto do Select2
        const produtoData = $form.find('.select-produto-estoque').select2('data')[0];
        const loteData = $form.find('.select-lote-estoque').select2('data')[0];
        const produtoNome = produtoData ? produtoData.text : 'Produto Desconhecido';
        const loteNome = loteData ? loteData.text : 'Lote Desconhecido';
    
        // Gera a linha
        const $linha = $(`
            <tr data-lote-item-id="${loteId}">
                <td>${produtoNome} - ${loteNome}</td>
                <td class="text-end">${quantidade.toFixed(3)}</td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-remover-produto-cliente"><i class="fas fa-trash"></i></button></td>
            </tr>
        `);
    
        // Log para depuração: Verifica a linha gerada
        console.log('Linha gerada:', $linha[0].outerHTML);
    
        // Seleciona o div table-responsive
        const $tableResponsive = $card.find('.table-responsive');
        
        // Verifica se a tabela existe; se não, cria
        let $tabelaBody = $tableResponsive.find('.lista-produtos-cliente tbody');
        if ($tabelaBody.length === 0) {
            console.log('Tbody não encontrado, criando tabela...');
            $tableResponsive.html(`
                <table class="table table-bordered lista-produtos-cliente">
                    <thead>
                        <tr>
                            <th>Produto - Lote</th>
                            <th class="text-end">Quantidade</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            `);
            $tabelaBody = $tableResponsive.find('.lista-produtos-cliente tbody');
        }
    
        // Log para depuração: Verifica se o tbody foi encontrado ou criado
        console.log('Tbody encontrado ou criado:', $tabelaBody.length > 0 ? 'Sim' : 'Não');
        if ($tabelaBody.length === 0) {
            console.error('Erro: Não foi possível encontrar ou criar o tbody.');
            return;
        }
    
        // Adiciona a linha ao tbody
        $tabelaBody.append($linha);
    
        // Log para confirmar adição
        console.log('Linha adicionada ao tbody. Número de linhas agora:', $tabelaBody.find('tr').length);
    
        // Limpa o formulário
        $form[0].reset();
        $form.find('.select-lote-estoque').val(null).trigger('change').prop('disabled', true);
    });*/

    // /public/js/detalhes_carregamento.js (Versão Final com a sua lógica de depuração)

    $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (e) {
        e.preventDefault();
        const $form = $(this);

        // 1. Coleta e valida os dados (a sua lógica está perfeita)
        const produtoSelecionado = $form.find('.select-produto-estoque').select2('data')[0];
        const loteSelecionado = $form.find('.select-lote-estoque').select2('data')[0];
        const quantidade = $form.find('input[type="number"]').val();

        if (!produtoSelecionado || !loteSelecionado || !quantidade || parseFloat(quantidade) <= 0) {
            notificacaoErro('Dados Inválidos', 'Preencha todos os campos corretamente.');
            return;
        }

        const loteItemId = loteSelecionado.id;
        const textoCompleto = `${produtoSelecionado.text} | ${loteSelecionado.text}`;

        // 2. Encontra o <tbody> alvo. Este é o ponto crucial.
        const $listaProdutos = $form.closest('.card-cliente-na-fila').find('.lista-produtos-cliente');

        // 3. Verifica se o alvo foi encontrado (lógica de depuração que você sugeriu)
        if ($listaProdutos.length === 0) {
            notificacaoErro('Erro de Interface', 'Não foi possível encontrar a tabela de produtos deste cliente. Contacte o suporte.');
            console.error("FALHA CRÍTICA: O seletor não encontrou o elemento <tbody> com a classe '.lista-produtos-cliente'. Verifique o HTML do <template id='template-card-cliente-modal'>.");
            return;
        }

        // 4. Lógica de Edição vs. Adição (já estava correta)
        const $linhaSendoEditada = $form.data('editing-row');
        if ($linhaSendoEditada) {
            // Atualiza a linha existente
            $linhaSendoEditada.attr('data-lote-item-id', loteItemId);
            $linhaSendoEditada.find('td:nth-child(1)').text(textoCompleto);
            $linhaSendoEditada.find('td:nth-child(2)').text(parseFloat(quantidade).toFixed(3));
            $form.removeData('editing-row');
            $form.find('button[type="submit"]').html('<i class="fas fa-plus"></i>').attr('title', 'Adicionar Produto').removeClass('btn-warning').addClass('btn-primary');
        } else {
            // Adiciona uma nova linha
            const produtoHtml = `
            <tr data-lote-item-id="${loteItemId}">
                <td>${textoCompleto}</td>
                <td class="text-end">${parseFloat(quantidade).toFixed(3)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-editar-produto-da-lista"><i class="fas fa-pencil-alt"></i> Editar</button>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remover-produto-da-lista"><i class="fas fa-trash"></i> Remover</button>
                </td>
            </tr>`;
            $listaProdutos.append(produtoHtml);
        }

        // 5. Limpa o formulário (lógica existente)
        $form.find('.select-produto-estoque').val(null).trigger('change');
        $form.find('.select-lote-estoque').val(null).trigger('change').prop('disabled', true);
        $form.find('input[type="number"]').val('');
    });


























    $modalGerenciarFila.on('click', '.btn-editar-produto-da-lista', function () {
        const $linhaParaEditar = $(this).closest('tr');
        const $cardCliente = $linhaParaEditar.closest('.card-cliente-na-fila');
        const $form = $cardCliente.find('.form-adicionar-produto-ao-cliente');
        const $produtoSelect = $form.find('.select-produto-estoque');
        const $quantidadeInput = $form.find('input[type="number"]');
        const loteItemId = $linhaParaEditar.data('lote-item-id');
        const produtoTexto = $linhaParaEditar.find('td:nth-child(1)').text();
        const quantidade = parseFloat($linhaParaEditar.find('td:nth-child(2)').text());
        if ($produtoSelect.find(`option[value="${loteItemId}"]`).length === 0) {
            const option = new Option(produtoTexto, loteItemId, true, true);
            $produtoSelect.append(option).trigger('change');
        }
        $produtoSelect.val(loteItemId).trigger('change');
        $quantidadeInput.val(quantidade);
        $form.data('editing-row', $linhaParaEditar);
        $form.find('button[type="submit"]').html('<i class="fas fa-check"></i> Atualizar Produto').removeClass('btn-primary').addClass('btn-warning');
        $quantidadeInput.focus();
    });

    $modalGerenciarFila.on('click', '.btn-remover-produto-da-lista', function () {
        $(this).closest('tr').remove();
    });

    $modalGerenciarFila.on('click', '.btn-remover-cliente-da-fila', function () {
        $(this).closest('.card-cliente-na-fila').remove();
        if ($containerClientesNoModal.find('.card-cliente-na-fila').length === 0) {
            $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');
        }
    });

    $modalGerenciarFila.on('change', '.select-produto-estoque', function () {
        const $produtoSelect = $(this);
        const $card = $produtoSelect.closest('.card-cliente-na-fila');
        const $loteSelect = $card.find('.select-lote-estoque');
        const produtoId = $produtoSelect.val();

        // Limpa e desativa o dropdown de lote
        $loteSelect.val(null).trigger('change');
        $loteSelect.prop('disabled', true);

        if (produtoId) {
            // Se um produto foi selecionado, ativa e carrega os lotes
            $loteSelect.prop('disabled', false).select2({
                placeholder: 'Selecione um lote...',
                theme: "bootstrap-5",
                dropdownParent: $modalGerenciarFila,
                language: "pt-BR",
                ajax: {
                    url: 'ajax_router.php?action=getLotesPorProduto', // Nossa nova rota
                    dataType: 'json',
                    data: function () { return { produto_id: produtoId }; } // Envia o ID do produto selecionado
                }
            });
        }
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
                produtos.push({ loteItemId: $linhaProduto.data('lote-item-id'), quantidade: parseFloat($linhaProduto.find('td:nth-child(2)').text()) });
            });
            if (produtos.length > 0) {
                filaData.push({ clienteId: clienteId, produtos: produtos });
            }
        });

        if (filaData.length === 0) {
            notificacaoErro('Fila Vazia', 'Nenhum produto foi adicionado. Adicione produtos antes de concluir.');
            return;
        }

        let ajaxUrl = 'ajax_router.php?action=salvarFilaComposta';
        let ajaxData = {
            carregamento_id: carregamentoId,
            fila_data: JSON.stringify(filaData),
            csrf_token: csrfToken
        };

        if (modoModal === 'edicao' && filaIdParaEditar) {
            ajaxUrl = 'ajax_router.php?action=atualizarFilaComposta';
            ajaxData.fila_id = filaIdParaEditar;
            console.log("Modo de Edição: Atualizando Fila ID", filaIdParaEditar);
        } else {
            console.log("Modo de Criação: Salvando nova Fila");
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
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível salvar a fila.');
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
        const $botao = $(this);
        filaIdParaEditar = $botao.data('fila-id');
        modoModal = 'edicao';
        console.log("Preparando para editar fila ID:", filaIdParaEditar);
        $modalGerenciarFila.modal('show');
    });


    // --- INICIALIZAÇÃO DA PÁGINA ---
    preencherCabecalho();
    controlarVisibilidadeAcoes();
    inicializarSelectClienteModal();
    recarregarETabelaPrincipal();
});