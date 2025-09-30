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
                "className": "dt-center checkbox-select-col"
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
            url: 'ajax_router.php?action=getOpenLotsForSelect', // Rota do Passo 4
            dataType: 'json',
            processResults: function (data) {
                return data;
            }
        }
    });

    // 3. LÓGICA DE INTERAÇÃO (O CARRINHO)
    function atualizarEstadoCarrinho() {
        const itemCount = $tabelaCart.find('tr:not(#cart-placeholder)').length;
        if (itemCount > 0) {
            $placeholderCart.hide();
            $btnSalvarCaixa.prop('disabled', false);
        } else {
            $placeholderCart.show();
            $btnSalvarCaixa.prop('disabled', true);
        }
    }

    // Função para formatar o item na LISTA DE OPÇÕES (dropdown)
    function formatarOpcaoProduto(produto) {
        if (produto.loading) {
            return produto.text;
        }
        // 'produto' é o objeto completo do nosso repositório: {id, text, prod_codigo_interno, ...}
        const codigoInterno = produto.prod_codigo_interno || 'N/A';
        // Retorna um objeto jQuery formatado (assim como no módulo de Lotes)
        return $(`<span>${produto.text} (Cód: ${codigoInterno})</span>`);
    }

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

    $('#tabela-estoque-sobras tbody').on('change', '.check-sobra-item', async function () {
        const $check = $(this);
        const rowNode = $check.closest('tr');
        const rowData = tabelaSobras.row(rowNode).data();
        const itemId = rowData.item_prod_id;
        const saldoDisponivel = parseFloat(rowData.item_prod_saldo);

        if ($check.is(':checked')) {
            const { value: quantidade } = await Swal.fire({
                title: 'Adicionar Item',
                text: `Produto: ${rowData.prod_descricao} | Saldo: ${formatarNumeroBR(saldoDisponivel)}`,
                input: 'number',
                inputLabel: 'Quantidade a usar:',
                inputValue: saldoDisponivel,
                inputAttributes: {
                    max: saldoDisponivel,
                    min: 0.001,
                    step: 0.001
                },
                showCancelButton: true,
                confirmButtonText: 'Adicionar',
                cancelButtonText: 'Cancelar',
                validationMessage: (value) => {
                    const num = parseFloat(value);
                    if (!num || num <= 0) {
                        return 'A quantidade deve ser maior que zero.';
                    }
                    if (num > saldoDisponivel) {
                        return `Valor excede o saldo disponível de ${formatarNumeroBR(saldoDisponivel)}`;
                    }
                }
            });

            if (quantidade) {
                const $template = $('#template-cart-row').contents().clone(true);

                $template.data('item-prod-id', itemId);
                $template.data('quantidade-usada', parseFloat(quantidade));

                $template.find('.cart-item-produto').text(rowData.prod_descricao);
                $template.find('.cart-item-lote').text(rowData.lote_completo_calculado);
                $template.find('.cart-item-qtd').text(formatarNumeroBR(quantidade));

                $template.attr('id', `cart-row-${itemId}`);

                $tabelaCart.append($template);
                rowNode.addClass('table-success');
            } else {
                $check.prop('checked', false);
            }

        } else {
            $(`#cart-row-${itemId}`).remove();
            rowNode.removeClass('table-success');
        }
        atualizarEstadoCarrinho();
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

    // 4. ### PASSO 6: LÓGICA DE SUBMIT (NOVO) ###
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

        // Precisamos enviar os dados do carrinho de uma forma que o PHP entenda como um array
        // O jQuery.param() não serializa arrays complexos por padrão, então vamos construir os dados.

        let postData = {};
        // Adiciona os campos do formulário (Selects e CSRF)
        formData.forEach(field => {
            postData[field.name] = field.value;
        });

        // Adiciona os itens do carrinho (o PHP receberá isso como $_POST['itens'])
        postData.itens = cartItens;

        // Desabilita o botão para evitar clique duplo
        $btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        $.ajax({
            url: 'ajax_router.php?action=salvarCaixaMista',
            type: 'POST',
            data: postData, // Envia o objeto de dados completo
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
                        // Chama a função de impressão que criamos
                        imprimirEtiquetaEmbalagem(novoItemId);
                    }
                });

                // Limpa tudo e recarrega a tabela de sobras
                tabelaSobras.ajax.reload(); // Recarrega as sobras (os saldos diminuíram)
                $tabelaCart.empty().append($placeholderCart); // Limpa o carrinho
                $('#select-produto-final').val(null).trigger('change');
                $('#select-lote-destino').val(null).trigger('change');
                atualizarEstadoCarrinho(); // Reseta o formulário

            } else {
                // Erro de validação do backend (ex: Saldo insuficiente)
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function (jqXHR) {
            const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : 'Falha na comunicação com o servidor.';
            notificacaoErro('Erro Crítico', errorMsg);
        }).always(function () {
            // Reabilita o botão
            $btnSubmit.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Salvar Caixa Mista e Gerar Etiqueta');
        });
    });

    // Inicializa o estado do carrinho (garante que o placeholder apareça)
    atualizarEstadoCarrinho();
});