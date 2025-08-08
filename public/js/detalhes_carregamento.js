// /public/js/detalhes_carregamento.js
/*$(document).ready(function () {

    if (!carregamentoData || !carregamentoData.header) {
        $('#dados-carregamento-header').html('<div class="alert alert-danger">Não foi possível carregar os dados deste carregamento.</div>');
        return;
    }

    // --- VARIÁVEIS GLOBAIS E SELETORES ---
    const carregamentoId = carregamentoData.header.car_id;
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || csrfToken;
    const $modalGerenciarFila = $('#modal-gerenciar-fila');
    const $selectClienteParaFila = $('#select-cliente-para-fila');
    const $containerClientesNoModal = $('#clientes-e-produtos-container-modal');

    // --- FUNÇÕES DE LÓGICA E RENDERIZAÇÃO ---
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

    function inicializarSelectProdutoNoCard($card) {
        $card.find('.select-produto-estoque').select2({
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
    }

    // --- EVENT HANDLERS (Ações do Usuário) ---

    $modalGerenciarFila.on('show.bs.modal', function () {
        $selectClienteParaFila.val(null).trigger('change');
        $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');
    });

    $('#btn-adicionar-cliente-a-fila').on('click', function () {
        const clienteSelecionado = $selectClienteParaFila.select2('data')[0];
        if (!clienteSelecionado || !clienteSelecionado.id) { alert('Por favor, selecione um cliente da lista.'); return; }
        if ($containerClientesNoModal.find('.card-cliente-na-fila').length === 0) {
            $containerClientesNoModal.empty();
        }
        const $novoCard = $($('#template-card-cliente-modal').html());
        $novoCard.attr('data-cliente-id', clienteSelecionado.id);
        $novoCard.find('.nome-cliente-card').text(clienteSelecionado.text);
        $containerClientesNoModal.append($novoCard);
        inicializarSelectProdutoNoCard($novoCard);
        $selectClienteParaFila.val(null).trigger('change');
    });

    // ===================================================================
    // == INÍCIO DA CORREÇÃO: Lógica de Adicionar/Editar Produto ==
    // ===================================================================
    $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $produtoSelect = $form.find('.select-produto-estoque');
        const $quantidadeInput = $form.find('input[type="number"]');

        const produtoSelecionado = $produtoSelect.select2('data')[0];
        const quantidade = $quantidadeInput.val();

        if (!produtoSelecionado || !produtoSelecionado.id || !quantidade || parseFloat(quantidade) <= 0) {
            alert('Por favor, selecione um produto e insira uma quantidade válida.');
            return;
        }

        // Verifica se o formulário está em "modo de edição"
        const $linhaSendoEditada = $form.data('editing-row');

        if ($linhaSendoEditada) {
            // SE ESTIVER EDITANDO: Atualiza a linha existente
            $linhaSendoEditada.attr('data-lote-item-id', produtoSelecionado.id);
            $linhaSendoEditada.find('td:nth-child(1)').text(produtoSelecionado.text); // Atualiza o texto do produto
            $linhaSendoEditada.find('td:nth-child(2)').text(parseFloat(quantidade).toFixed(3)); // Atualiza a quantidade

            // Limpa o estado de edição do formulário
            $form.removeData('editing-row');
            $form.find('button[type="submit"]').html('<i class="fas fa-plus"></i> Adicionar Produto').removeClass('btn-warning').addClass('btn-primary');

        } else {
            // SE ESTIVER ADICIONANDO: Cria uma nova linha (lógica que você já tinha)
            const $listaProdutos = $form.closest('.card-cliente-na-fila').find('.lista-produtos-cliente');
            const produtoHtml = `
                <tr data-lote-item-id="${produtoSelecionado.id}">
                    <td>${produtoSelecionado.text}</td>
                    <td class="text-end">${parseFloat(quantidade).toFixed(3)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-editar-produto-da-lista"><i class="fas fa-pencil-alt"></i> Editar</button>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remover-produto-da-lista"><i class="fas fa-trash"></i> Remover</button>
                    </td>
                </tr>`;
            $listaProdutos.append(produtoHtml);
        }

        // Limpa o formulário para a próxima ação
        $produtoSelect.val(null).trigger('change');
        $quantidadeInput.val('');
    });
    // ===================================================================
    // == FIM DA CORREÇÃO ==
    // ===================================================================

    /**
     * Evento para o botão EDITAR de um produto na tabela.
     */
/* $modalGerenciarFila.on('click', '.btn-editar-produto-da-lista', function () {
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

 /**
   * Evento para remover um PRODUTO da tabela de um cliente, dentro do modal.
   */
/*  $modalGerenciarFila.on('click', '.btn-remover-produto-da-lista', function () {
      $(this).closest('tr').remove();
  });

  /**
   * Evento para remover um CARD DE CLIENTE inteiro de dentro do modal.
   */
/*   $modalGerenciarFila.on('click', '.btn-remover-cliente-da-fila', function () {
       $(this).closest('.card-cliente-na-fila').remove();
       if ($containerClientesNoModal.find('.card-cliente-na-fila').length === 0) {
           $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');
       }
   });

   $('#btn-salvar-e-fechar-fila').on('click', function () {
       const $botaoSalvar = $(this);
       const filaData = [];

       // 1. Coleta os dados de cada card de cliente dentro do modal
       $containerClientesNoModal.find('.card-cliente-na-fila').each(function () {
           const $card = $(this);
           const clienteId = $card.data('cliente-id');
           const produtos = [];

           $card.find('.lista-produtos-cliente tr').each(function () {
               const $linhaProduto = $(this);
               produtos.push({
                   loteItemId: $linhaProduto.data('lote-item-id'),
                   quantidade: parseFloat($linhaProduto.find('td:nth-child(2)').text())
               });
           });

           // Só adiciona o cliente se ele tiver pelo menos um produto
           if (produtos.length > 0) {
               filaData.push({
                   clienteId: clienteId,
                   produtos: produtos
               });
           }
       });

       // 2. Validação: verifica se há algo para salvar
       if (filaData.length === 0) {
           alert('Nenhum produto foi adicionado aos clientes. Adicione produtos antes de concluir a fila.');
           return;
       }

       // Desabilita o botão para evitar cliques duplos
       $botaoSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

       // 3. Envia os dados para o backend via AJAX
       $.ajax({
           url: 'ajax_router.php?action=salvarFilaComposta',
           type: 'POST',
           data: {
               carregamento_id: carregamentoId,
               fila_data: JSON.stringify(filaData), // Enviamos os dados como uma string JSON
               csrf_token: csrfToken
           },
           dataType: 'json'
       }).done(function (response) {
           if (response.success) {
               $modalGerenciarFila.modal('hide');
               alert('Fila salva com sucesso!');
               // Futuramente, chamaremos a função para recarregar a tabela principal
               // recarregarETabelaPrincipal();
               location.reload(); // Por enquanto, um simples reload resolve
           } else {
               alert('Erro: ' + response.message);
           }
       }).fail(function () {
           alert('Erro de comunicação com o servidor. Tente novamente.');
       }).always(function () {
           // Reabilita o botão ao final da operação
           $botaoSalvar.prop('disabled', false).html('<i class="fas fa-check me-2"></i> Concluir e Adicionar Fila ao Carregamento');
       });
   });

   // --- INICIALIZAÇÃO DA PÁGINA ---
   preencherCabecalho();
   inicializarSelectClienteModal();
});*/


// /public/js/detalhes_carregamento.js (Versão Final Corrigida e Limpa)
$(document).ready(function () {

    if (!carregamentoData || !carregamentoData.header) {
        $('#dados-carregamento-header').html('<div class="alert alert-danger">Não foi possível carregar os dados deste carregamento.</div>');
        return;
    }

    // --- VARIÁVEIS GLOBAIS E SELETORES ---
    const carregamentoId = carregamentoData.header.car_id;
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || csrfToken;
    const $modalGerenciarFila = $('#modal-gerenciar-fila');
    const $selectClienteParaFila = $('#select-cliente-para-fila');
    const $containerClientesNoModal = $('#clientes-e-produtos-container-modal');

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

    function inicializarSelectProdutoNoCard(selectId) {
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
    }

     /**
     * Busca os dados mais recentes do carregamento e redesenha a tabela principal.
     */
    function recarregarETabelaPrincipal() {
        console.log("Buscando dados atualizados do carregamento...");
        $.ajax({
            url: `ajax_router.php?action=getCarregamentoDetalhes&id=${carregamentoId}`,
            type: 'GET',
            dataType: 'json'
        }).done(function(response) {
            if (response.success && response.data) {
                const filas = response.data.filas;
                $tabelaComposicaoBody.empty(); // Limpa a tabela antes de redesenhar

                if (filas.length === 0) {
                    $tabelaComposicaoBody.html('<tr><td colspan="5" class="text-center text-muted">Nenhuma fila adicionada.</td></tr>');
                    return;
                }

                filas.forEach(fila => {
                    const totalItensNaFila = fila.itens.length;
                    if (totalItensNaFila > 0) {
                        fila.itens.forEach((item, index) => {
                            const $linha = $('<tr>');
                            
                            // A primeira linha de uma fila ocupa todas as células de Fila e Cliente
                            if (index === 0) {
                                $linha.append(`<td rowspan="${totalItensNaFila}">${fila.fila_id}</td>`);
                                $linha.append(`<td rowspan="${totalItensNaFila}">${fila.cliente_razao_social}</td>`);
                            }
                            
                            $linha.append(`<td>${item.prod_descricao} (Cód: ${item.prod_codigo_interno})</td>`);
                            $linha.append(`<td class="text-end">${parseFloat(item.car_item_quantidade).toFixed(3)}</td>`);
                            $linha.append(`<td class="text-center">
                                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                         </td>`);
                            
                            $tabelaComposicaoBody.append($linha);
                        });
                    }
                });
            } else {
                $tabelaComposicaoBody.html('<tr><td colspan="5" class="text-center text-danger">Erro ao carregar os dados.</td></tr>');
            }
        }).fail(function() {
            $tabelaComposicaoBody.html('<tr><td colspan="5" class="text-center text-danger">Erro de comunicação ao carregar os dados.</td></tr>');
        });
    }

    // --- EVENT HANDLERS (Ações do Usuário) ---

    $modalGerenciarFila.on('show.bs.modal', function () {
        $selectClienteParaFila.val(null).trigger('change');
        $containerClientesNoModal.html('<p class="text-muted">Nenhum cliente adicionado a esta fila.</p>');
    });

    // ===================================================================
    // VERSÃO ÚNICA E CORRETA DO BOTÃO "ADICIONAR CLIENTE"
    // ===================================================================
    $('#btn-adicionar-cliente-a-fila').on('click', function () {
        const clienteSelecionado = $selectClienteParaFila.select2('data')[0];
        if (!clienteSelecionado || !clienteSelecionado.id) {
            alert('Por favor, selecione um cliente da lista.');
            return;
        }

        if ($containerClientesNoModal.find('.card-cliente-na-fila').length === 0) {
            $containerClientesNoModal.empty();
        }

        const clienteId = clienteSelecionado.id;
        const clienteNome = clienteSelecionado.text;

        // Melhoria 1: Título numerado
        const numeroCliente = $containerClientesNoModal.find('.card-cliente-na-fila').length + 1;
        const novoTitulo = `CLIENTE ${String(numeroCliente).padStart(2, '0')} - ${clienteNome}`;

        // Correção do Bug: Gerar ID único para o select de produtos
        const selectIdUnico = `select-produto-${clienteId}-${new Date().getTime()}`;

        const $novoCard = $($('#template-card-cliente-modal').html());
        $novoCard.attr('data-cliente-id', clienteId);
        $novoCard.find('.nome-cliente-card').text(novoTitulo);
        $novoCard.find('.select-produto-estoque').attr('id', selectIdUnico); // Atribui o ID único

        $containerClientesNoModal.append($novoCard);

        // Chamada correta, passando o ID em formato de texto
        inicializarSelectProdutoNoCard(selectIdUnico);

        $selectClienteParaFila.val(null).trigger('change');
    });


    $modalGerenciarFila.on('submit', '.form-adicionar-produto-ao-cliente', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $produtoSelect = $form.find('.select-produto-estoque');
        const $quantidadeInput = $form.find('input[type="number"]');
        const produtoSelecionado = $produtoSelect.select2('data')[0];
        const quantidade = $quantidadeInput.val();
        if (!produtoSelecionado || !produtoSelecionado.id || !quantidade || parseFloat(quantidade) <= 0) {
            alert('Por favor, selecione um produto e insira uma quantidade válida.');
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

    $('#btn-salvar-e-fechar-fila').on('click', function () {
        const $botaoSalvar = $(this);
        const filaData = [];
        $containerClientesNoModal.find('.card-cliente-na-fila').each(function () {
            const $card = $(this);
            const clienteId = $card.data('cliente-id');
            const produtos = [];
            $card.find('.lista-produtos-cliente tr').each(function () {
                const $linhaProduto = $(this);
                produtos.push({
                    loteItemId: $linhaProduto.data('lote-item-id'),
                    quantidade: parseFloat($linhaProduto.find('td:nth-child(2)').text())
                });
            });
            if (produtos.length > 0) {
                filaData.push({ clienteId: clienteId, produtos: produtos });
            }
        });
        if (filaData.length === 0) {
            alert('Nenhum produto foi adicionado aos clientes. Adicione produtos antes de concluir a fila.');
            return;
        }
        $botaoSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');
        $.ajax({
            url: 'ajax_router.php?action=salvarFilaComposta',
            type: 'POST',
            data: {
                carregamento_id: carregamentoId,
                fila_data: JSON.stringify(filaData),
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalGerenciarFila.modal('hide');
                alert('Fila salva com sucesso!');
                recarregarETabelaPrincipal();
                
            } else {
                alert('Erro: ' + response.message);
            }
        }).fail(function () {
            alert('Erro de comunicação com o servidor. Tente novamente.');
        }).always(function () {
            $botaoSalvar.prop('disabled', false).html('<i class="fas fa-check me-2"></i> Concluir e Adicionar Fila ao Carregamento');
        });
    });

    // --- INICIALIZAÇÃO DA PÁGINA ---
    preencherCabecalho();
    inicializarSelectClienteModal();
    recarregarETabelaPrincipal();
});