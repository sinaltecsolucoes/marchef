// /public/js/gestao_caixas_mistas.js
// VERSÃO ATUALIZADA (PASSO 5)

$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $tabelaCart = $('#tbody-cart-mista');
    const $placeholderCart = $('#cart-placeholder');
    const $btnSalvarCaixa = $('#btn-salvar-caixa-mista');

    // Formata os saldos (esta função é necessária)
    function formatarNumeroBR(num, casasDecimais = 3) {
        if (typeof num !== 'number') {
            num = parseFloat(num) || 0;
        }
        return num.toLocaleString('pt-BR', { minimumFractionDigits: casasDecimais, maximumFractionDigits: casasDecimais });
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
            { "data": null, "defaultContent": '', "orderable": false, "className": "dt-center checkbox-select-col" }, // Coluna 0: Checkbox (será renderizada abaixo)
            { "data": "lote_completo_calculado", "className": "align-middle" }, // Coluna 1
            { "data": "prod_descricao", "className": "align-middle" }, // Coluna 2
            { "data": "fornecedor_nome", "className": "align-middle", "defaultContent": "<i>N/A</i>" }, // Coluna 3
            {
                "data": "lote_data_fabricacao", // Coluna 4
                "className": "text-center align-middle",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "item_prod_saldo", // Coluna 5
                "className": "text-center align-middle fw-bold",
                "render": function (data) { return formatarNumeroBR(data, 3); }
            }
        ],
        "columnDefs": [{
            // Define a coluna 0 (a primeira) como um checkbox
            "targets": 0,
            "render": function (data, type, row, meta) {
                // Adiciona os dados da linha no próprio checkbox para fácil recuperação
                return `<input type="checkbox" class="form-check-input check-sobra-item" data-item-id="${row.item_prod_id}">`;
            }
        }],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[1, 'asc']] // Ordena pelo Lote de Origem
    });


    // 2. INICIALIZA OS DROPDOWNS (PAINEL B)

    // Dropdown 1: Produto Final (Caixa Mista)
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
                return { results: data.data };
            }
        }
    });

    // Dropdown 2: Lote de Destino (Lotes Abertos)
    $('#select-lote-destino').select2({
        placeholder: 'Selecione um lote em andamento...',
        theme: "bootstrap-5",
        language: "pt-BR",
        ajax: {
            url: 'ajax_router.php?action=getOpenLotsForSelect', // Rota que criamos no Passo 4
            dataType: 'json',
            processResults: function (data) {
                return data; // Já retorna {results: [...]}
            }
        }
    });

    // 3. LÓGICA DE INTERAÇÃO (O CARRINHO)

    // Função para atualizar o estado do carrinho e o botão Salvar
    function atualizarEstadoCarrinho() {
        const itemCount = $tabelaCart.find('tr:not(#cart-placeholder)').length;
        if (itemCount > 0) {
            $placeholderCart.hide();
            $btnSalvarCaixa.prop('disabled', false); // Habilita o botão salvar
        } else {
            $placeholderCart.show();
            $btnSalvarCaixa.prop('disabled', true); // Desabilita o botão salvar
        }
    }

    // Evento: Quando um checkbox da tabela de sobras é clicado
    $('#tabela-estoque-sobras tbody').on('change', '.check-sobra-item', async function () {
        const $check = $(this);
        const rowNode = $check.closest('tr');
        const rowData = tabelaSobras.row(rowNode).data(); // Pega todos os dados da linha
        const itemId = rowData.item_prod_id;
        const saldoDisponivel = parseFloat(rowData.item_prod_saldo);

        if ($check.is(':checked')) {
            // Se marcou o checkbox, pergunta a quantidade
            const { value: quantidade } = await Swal.fire({
                title: 'Adicionar Item',
                text: `Produto: ${rowData.prod_descricao} | Saldo: ${formatarNumeroBR(saldoDisponivel)}`,
                input: 'number',
                inputLabel: 'Quantidade a usar:',
                inputValue: saldoDisponivel, // Sugere usar o saldo total
                inputAttributes: {
                    max: saldoDisponivel,
                    min: 0.001,
                    step: 0.001
                },
                showCancelButton: true,
                confirmButtonText: 'Adicionar ao Carrinho',
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
                // Usuário confirmou e a quantidade é válida. Adiciona ao carrinho.
                const $template = $('#template-cart-row').contents().clone(true);

                // Armazena todos os dados que precisamos para salvar
                $template.data('item-prod-id', itemId);
                $template.data('quantidade-usada', parseFloat(quantidade));

                // Preenche os dados visuais
                $template.find('.cart-item-produto').text(rowData.prod_descricao);
                $template.find('.cart-item-lote').text(rowData.lote_completo_calculado);
                $template.find('.cart-item-qtd').text(formatarNumeroBR(quantidade));

                // Adiciona um ID único à linha do carrinho
                $template.attr('id', `cart-row-${itemId}`);

                $tabelaCart.append($template);
                rowNode.addClass('table-success'); // Marca a linha na tabela de cima
            } else {
                // Usuário cancelou o SweetAlert ou a quantidade era inválida
                $check.prop('checked', false);
            }

        } else {
            // Se desmarcou o checkbox, remove do carrinho
            $(`#cart-row-${itemId}`).remove();
            rowNode.removeClass('table-success');
        }

        atualizarEstadoCarrinho();
    });

    // Evento: Remover item clicando no botão de lixeira (dentro do carrinho)
    $tabelaCart.on('click', '.btn-remover-cart-item', function () {
        const $rowCart = $(this).closest('tr');
        const itemId = $rowCart.data('item-prod-id');

        // Remove a linha do carrinho
        $rowCart.remove();

        // Desmarca o checkbox correspondente na tabela de sobras
        const $checkboxNaTabela = $(`#tabela-estoque-sobras .check-sobra-item[data-item-id="${itemId}"]`);
        if ($checkboxNaTabela.length > 0) {
            $checkboxNaTabela.prop('checked', false);
            $checkboxNaTabela.closest('tr').removeClass('table-success');
        }

        atualizarEstadoCarrinho();
    });

    // Inicializa o estado do carrinho (garante que o placeholder apareça)
    atualizarEstadoCarrinho();

    // --- (PASSO 6 virá aqui: a lógica de submit do #form-caixa-mista) ---

});