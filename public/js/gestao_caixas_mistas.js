// /public/js/gestao_caixas_mistas.js

$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $tabelaCart = $('#tbody-cart-mista');
    const $placeholderCart = $('#cart-placeholder');
    const $btnSalvarCaixa = $('#btn-salvar-caixa-mista');
    const $formCaixaMista = $('#form-caixa-mista');

    // Formata os saldos (esta função é necessária)
    function formatarNumeroBR(num, casasDecimais = 3) {
        if (typeof num !== 'number') {
            num = parseFloat(num) || 0;
        }
        return num.toLocaleString('pt-BR', { minimumFractionDigits: casasDecimais, maximumFractionDigits: casasDecimais });
    }

    // Função para chamar a API de impressão de etiquetas
    function imprimirEtiquetaEmbalagem(itemId) {
        $.ajax({
            url: 'ajax_router.php?action=imprimirEtiquetaLoteItem',
            type: 'POST',
            data: {
                itemId: itemId,
                itemType: 'embalagem', // Nossa Caixa Mista gera um item de "embalagem"
                csrf_token: csrfToken
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.pdfUrl) {
                // Abre o PDF numa nova aba
                window.open(response.pdfUrl, '_blank');
            } else {
                notificacaoErro('Erro de Impressão', response.message || 'Não foi possível gerar a etiqueta PDF.');
            }
        }).fail(function () {
            notificacaoErro('Erro de Impressão', 'Falha ao contactar o servidor de etiquetas.');
        });
    }

    // 1. INICIALIZA A TABELA DE SOBRAS (PAINEL A)
    const tabelaSobras = $('#tabela-estoque-sobras').DataTable({
        "processing": true,
        "ajax": {
            "url": "ajax_router.php?action=getEstoqueDeSobras",
            "type": "GET",
            "dataSrc": "data"
        },
        "columns": [
            {
                "data": null,
                "defaultContent": '',
                "orderable": false,
                "className": "dt-center checkbox-select-col align-middle"
            },
            {
                "data": "lote_completo_calculado",
                "className": "text-center align-middle"
            },
            {
                "data": "prod_descricao",
                "className": "align-middle"
            },
            {
                "data": "fornecedor_nome",
                "className": "align-middle",
                "defaultContent": "<i>N/A</i>"
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
                "data": "item_prod_saldo",
                "className": "text-center align-middle fw-bold",
                "render": function (data) { return formatarNumeroBR(data, 3); }
            }
        ],
        "columnDefs": [{
            "targets": 0,
            "render": function (data, type, row, meta) {
                return `<input type="checkbox" class="form-check-input check-sobra-item" data-item-id="${row.item_prod_id}">`;
            }
        }],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[1, 'asc']]
    });

    // 2. INICIALIZA OS DROPDOWNS (PAINEL B)
    $('#select-produto-final').select2({
        placeholder: 'Selecione o produto final...',
        theme: "bootstrap-5",
        language: "pt-BR",
        ajax: {
            url: 'ajax_router.php?action=getProdutoOptions',
            dataType: 'json',
            data: function (params) {
                // Filtramos apenas por produtos de Embalagem Secundária
                return { tipo_embalagem: 'Secundaria', term: params.term };
            },
            processResults: function (data) {
                return { results: data.data }; // Isso passa o objeto completo para as funções de template
            }
        },
        templateResult: formatarOpcaoProduto,
        templateSelection: formatarSelecaoProduto
    });

    $('#select-lote-destino').select2({
        placeholder: 'Selecione um lote em andamento...',
        theme: "bootstrap-5",
        language: "pt-BR",
        ajax: {
            url: 'ajax_router.php?action=getOpenLotsForSelect',
            dataType: 'json',
            processResults: function (data) {
                return data;
            }
        },
        templateResult: formatarOpcaoLote,
        templateSelection: formatarSelecaoLote
    });

    // Funções de template para o Select2 (exemplo, ajuste conforme necessário)
    /* function formatarOpcaoProduto(produto) {
         if (!produto.id) return produto.text;
         return $(`<span>${produto.text}</span>`);
     } */

    function formatarOpcaoProduto(produto) {
        if (produto.loading) {
            return produto.text;
        }
        // 'produto' é o objeto completo do nosso repositório: {id, text, prod_codigo_interno, ...}
        const codigoInterno = produto.prod_codigo_interno || 'N/A';
        // Retorna um objeto jQuery formatado (assim como no módulo de Lotes)
        return $(`<span>${produto.text} (Cód: ${codigoInterno})</span>`);
    }

    /*  function formatarSelecaoProduto(produto) {
          return produto.text || produto.id;
      } */

    // Função para formatar o item DEPOIS DE SELECIONADO (na caixa principal)
    function formatarSelecaoProduto(produto) {
        // Se o objeto não tiver um ID (ex: é o placeholder "Selecione..."), apenas retorne o texto
        if (!produto.id) {
            return produto.text;
        }
        // 'produto.text' já vem do banco (ex: "CAMARAO COZIDO...")
        const codigoInterno = produto.prod_codigo_interno || 'N/A';
        return `${produto.text} (Cód: ${codigoInterno})`;
    }

    function formatarOpcaoLote(lote) {
        if (!lote.id) return lote.text;
        return $(`<span>${lote.text}</span>`);
    }

    function formatarSelecaoLote(lote) {
        return lote.text || lote.id;
    }

    // 3. ATUALIZA ESTADO DO CARRINHO
    function atualizarEstadoCarrinho() {
        const itensNoCarrinho = $tabelaCart.find('tr:not(#cart-placeholder)').length;
        if (itensNoCarrinho > 0) {
            $placeholderCart.hide();
            $btnSalvarCaixa.prop('disabled', false);
        } else {
            $placeholderCart.show();
            $btnSalvarCaixa.prop('disabled', true);
        }
    }

    // 4. EVENTO DE SELEÇÃO NA TABELA DE SOBRAS
    $('#tabela-estoque-sobras tbody').on('click', '.check-sobra-item', function () {
        const $check = $(this);
        const isChecked = $check.is(':checked');
        const itemId = $check.data('item-id');
        const rowData = tabelaSobras.row($check.closest('tr')).data();
        const rowNode = $check.closest('tr');

        if (isChecked) {
            Swal.fire({
                title: 'Quantidade a Usar',
                input: 'number',
                inputAttributes: {
                    step: 0.001,
                    min: 0.001,
                    max: rowData.item_prod_saldo
                },
                inputValue: rowData.item_prod_saldo,
                showCancelButton: true,
                confirmButtonText: 'Adicionar',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    if (!value || parseFloat(value) <= 0 || parseFloat(value) > rowData.item_prod_saldo) {
                        return 'Quantidade inválida! Deve ser entre 0.001 e ' + formatarNumeroBR(rowData.item_prod_saldo);
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const quantidade = parseFloat(result.value);
                    $check.prop('checked', true);
                    rowNode.addClass('table-success');

                    const $template = $('#template-cart-row').contents().clone();
                    $template.data('item-prod-id', itemId);
                    $template.data('quantidade-usada', quantidade);
                    $template.find('.cart-item-produto').text(rowData.prod_descricao);
                    $template.find('.cart-item-lote').text(rowData.lote_completo_calculado);
                    $template.find('.cart-item-qtd').text(formatarNumeroBR(quantidade));

                    $template.attr('id', `cart-row-${itemId}`);

                    $tabelaCart.append($template);
                    rowNode.addClass('table-success');
                } else {
                    $check.prop('checked', false);
                }

                atualizarEstadoCarrinho();
            });
        } else {
            $(`#cart-row-${itemId}`).remove();
            rowNode.removeClass('table-success');
            atualizarEstadoCarrinho();
        }
    });

    $tabelaCart.on('click', '.btn-remover-cart-item', function () {
        const $rowCart = $(this).closest('tr');
        const itemId = $rowCart.data('item-prod-id');
        $rowCart.remove();

        const $checkboxNaTabela = $(`#tabela-estoque-sobras .check-sobra-item[data-item-id="${itemId}"]`);
        if ($checkboxNaTabela.length > 0) {
            $checkboxNaTabela.prop('checked', false);
            $checkboxNaTabela.closest('tr').removeClass('table-success');
        }
        atualizarEstadoCarrinho();
    });

    // 5. LÓGICA DE SUBMIT
    $formCaixaMista.on('submit', function (e) {
        e.preventDefault();

        const $btnSubmit = $(this).find('button[type="submit"]');

        // Coletar dados do formulário (os 2 Selects)
        const formData = $(this).serializeArray();

        // Coletar dados do carrinho (Tabela)
        const cartItens = [];
        $tabelaCart.find('tr:not(#cart-placeholder)').each(function () {
            const $row = $(this);
            cartItens.push({
                item_id: $row.data('item-prod-id'),
                quantidade: $row.data('quantidade-usada')
            });
        });

        if (cartItens.length === 0) {
            notificacaoErro('Carrinho Vazio', 'Você deve selecionar pelo menos um item da sobra.');
            return;
        }

        let postData = {};
        formData.forEach(field => {
            postData[field.name] = field.value;
        });

        postData.itens = cartItens;

        $btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        $.ajax({
            url: 'ajax_router.php?action=salvarCaixaMista',
            type: 'POST',
            data: postData,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const novoItemId = response.novo_item_emb_id;

                Swal.fire({
                    icon: 'success',
                    title: 'Caixa Mista Criada!',
                    text: `A nova caixa (Item ID: ${novoItemId}) foi gerada e as sobras foram consumidas.`,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-print"></i> Imprimir Etiqueta da Nova Caixa',
                    cancelButtonText: 'Fechar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        imprimirEtiquetaEmbalagem(novoItemId);
                    }
                });

                // Limpa tudo e recarrega as tabelas
                tabelaSobras.ajax.reload();
                tabelaCaixasMistas.ajax.reload();
                $tabelaCart.empty().append($placeholderCart);
                $('#select-produto-final').val(null).trigger('change');
                $('#select-lote-destino').val(null).trigger('change');
                atualizarEstadoCarrinho();

            } else {
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function (jqXHR) {
            const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : 'Falha na comunicação com o servidor.';
            notificacaoErro('Erro Crítico', errorMsg);
        }).always(function () {
            $btnSubmit.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Salvar Caixa Mista e Gerar Etiqueta');
        });
    });

    // 6. INICIALIZA A TABELA DE CAIXAS MISTAS CRIADAS
    const tabelaCaixasMistas = $('#tabela-caixas-mistas').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarCaixasMistas",
            "type": "POST",
            "data": function (d) {
                d.csrf_token = csrfToken;
                console.log('Dados enviados para listarCaixasMistas:', d);  // Log para debug
                return d;
            },
            "dataSrc": function (json) {
                console.log('Resposta recebida de listarCaixasMistas:', json);  // Log para debug
                if (json.error) {
                    notificacaoErro('Erro no Servidor', json.error);
                    return [];  // Retorna array vazio para evitar crash
                }
                if (!Array.isArray(json.data)) {
                    console.error('data não é array:', json.data);
                    return [];  // Força array vazio se inválido
                }
                return json.data;
            },
            "error": function (xhr, error, thrown) {
                console.error('Erro AJAX em tabelaCaixasMistas:', xhr.status, xhr.responseText);  // Log para debug
                let errorMsg = 'Falha na comunicação com o servidor (Status: ' + xhr.status + ').';
                try {
                    const responseJson = JSON.parse(xhr.responseText);
                    if (responseJson.error) {
                        errorMsg = responseJson.error;
                    } else if (responseJson.message) {
                        errorMsg = responseJson.message;
                    }
                } catch (e) {
                    // Response não é JSON válido
                }
                notificacaoErro('Erro ao Carregar Tabela de Caixas Mistas', errorMsg);
            }
        },
        "columns": [
            { "data": "mista_id", "className": "text-center align-middle" },
            { "data": "produto_final", "className": "align-middle" },
            { "data": "lote_destino", "className": "text-center align-middle" },
            {
                "data": "data_criacao",
                "className": "text-center align-middle",
                "render": function (data) {
                    if (!data) return 'N/A';
                    const date = new Date(data);
                    return date.toLocaleString('pt-BR');
                }
            },
            {
                "data": "total_qtd_consumida",
                "className": "text-center align-middle fw-bold",
                "render": function (data) {
                    if (data === null || data === undefined) return '0.000';
                    return formatarNumeroBR(parseFloat(data), 3);
                }
            },
            {
                "data": null,
                "orderable": false,
                "className": "text-center align-middle",
                "render": function (data, type, row) {
                    const mistaId = row.mista_id || '';
                    const itemEmbId = row.mista_item_embalagem_id_gerado || '';
                    return `
                    <button class="btn btn-info btn-sm btn-detalhes-caixa me-1" data-id="${mistaId}" title="Ver Detalhes">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-primary btn-sm btn-imprimir-etiqueta" data-item-id="${itemEmbId}" title="Imprimir Etiqueta">
                        <i class="fas fa-print"></i>
                    </button>
                    <button class="btn btn-danger btn-sm btn-excluir-caixa" data-id="${mistaId}" title="Excluir Caixa Mista">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[3, 'desc']],
        "pageLength": 25,  // Mais registros por página para teste
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]]  // Opções de paginação
    });

    // 7. EVENTOS DA TABELA DE CAIXAS MISTAS (ajustado com logs)
    $('#tabela-caixas-mistas tbody').on('click', '.btn-imprimir-etiqueta', function () {
        const itemId = $(this).data('item-id');
        console.log('Imprimindo etiqueta para itemId:', itemId);  // Log para debug
        if (!itemId) {
            notificacaoErro('Erro', 'ID do item de embalagem não encontrado.');
            return;
        }
        imprimirEtiquetaEmbalagem(itemId);
    });

    $('#tabela-caixas-mistas tbody').on('click', '.btn-detalhes-caixa', function () {
        const mistaId = $(this).data('id');
        console.log('Carregando detalhes para mistaId:', mistaId);  // Log para debug
        if (!mistaId) {
            notificacaoErro('Erro', 'ID da caixa mista não encontrado.');
            return;
        }
        $.ajax({
            url: 'ajax_router.php?action=getDetalhesCaixaMista',
            type: 'POST',
            data: { mista_id: mistaId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            console.log('Resposta de getDetalhesCaixaMista:', response);  // Log para debug
            if (response.success && Array.isArray(response.itens)) {
                let detalhesHtml = '<div class="table-responsive"><table class="table table-sm table-striped"><thead class="table-light"><tr><th class="text-center">Produto Origem</th><th class="text-center">Lote Origem</th><th class="text-center">Qtd. Consumida</th></tr></thead><tbody>';
                if (response.itens.length === 0) {
                    detalhesHtml += '<tr><td colspan="3" class="text-center text-muted">Nenhum item encontrado para esta caixa mista.</td></tr>';
                } else {
                    response.itens.forEach(item => {
                        detalhesHtml += `<tr>
                        <td class="text-center">${item.prod_descricao || 'N/A'}</td>
                        <td class="text-center">${item.lote_completo_calculado || 'N/A'}</td>
                        <td class="text-center fw-bold">${formatarNumeroBR(parseFloat(item.qtd_consumida || 0), 3)}</td>
                    </tr>`;
                    });
                }
                detalhesHtml += '</tbody></table></div>';
                detalhesHtml += `<p class="text-muted small mt-2">Total de itens: ${response.total_itens || 0}</p>`;

                Swal.fire({
                    title: `Detalhes da Caixa Mista ID ${mistaId}`,
                    html: detalhesHtml,
                    width: '800px',
                    showCloseButton: true,
                    confirmButtonText: 'Fechar'
                });
            } else {
                notificacaoErro('Erro', response.message || 'Não foi possível carregar os detalhes.');
            }
        }).fail(function (jqXHR) {
            console.error('Erro AJAX em getDetalhesCaixaMista:', jqXHR.status, jqXHR.responseText);
            const errorMsg = jqXHR.responseJSON ? (jqXHR.responseJSON.message || 'Falha na comunicação.') : 'Falha na comunicação com o servidor.';
            notificacaoErro('Erro Crítico', errorMsg);
        });
    });

    $('#tabela-caixas-mistas tbody').on('click', '.btn-excluir-caixa', function () {
        const mistaId = $(this).data('id');

        Swal.fire({
            title: 'Tem certeza?',
            text: "Esta ação não pode ser desfeita e irá reverter os saldos consumidos para as sobras originais!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_router.php?action=excluirCaixaMista',
                    type: 'POST',
                    data: {
                        mista_id: mistaId,
                        csrf_token: csrfToken
                    },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        notificacaoSucesso('Excluído!', response.message);
                        tabelaCaixasMistas.ajax.reload();
                        tabelaSobras.ajax.reload(); // Recarrega também a tabela de sobras
                    } else {
                        notificacaoErro('Erro ao Excluir', response.message);
                    }
                }).fail(function (jqXHR) {
                    const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : 'Falha na comunicação com o servidor.';
                    notificacaoErro('Erro Crítico', errorMsg);
                });
            }
        });
    });

    // Inicializa o estado do carrinho
    atualizarEstadoCarrinho();
});