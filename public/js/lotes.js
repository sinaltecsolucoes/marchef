// js/lotes.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Inicializa a tabela principal de lotes
    var tableLotes = $('#tabela-lotes').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "process/listar_lotes.php",
            "type": "POST",
            "data": function (d) {
                d.csrf_token = csrfToken;
            }
        },
        "responsive": true,
        "columns": [
            { "data": "lote_completo_calculado" },
            { "data": "fornecedor_razao_social" },
            {
                "data": "lote_data_fabricacao",
                "render": function (data, type, row) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "lote_status",
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
                "render": function (data, type, row) {
                    if (!data) return '';
                    const date = new Date(data);
                    return date.toLocaleString('pt-BR');
                }
            },
            {
                "data": "lote_id",
                "orderable": false,
                "render": function (data, type, row) {
                    // O 'row' nos dá acesso a todos os dados da linha, incluindo o status
                    let finalizarBtn = '';
                    if (row.lote_status === 'EM ANDAMENTO') {
                        finalizarBtn = `<button class="btn btn-success btn-sm btn-finalizar-lote" data-id="${data}" title="Finalizar e Gerar Estoque">Finalizar</button>`;
                    }

                    return `
                        <button class="btn btn-warning btn-sm btn-editar-lote" data-id="${data}">Editar</button>
                        <button class="btn btn-danger btn-sm btn-excluir-lote" data-id="${data}">Excluir</button>
                        ${finalizarBtn}
                    `;
                }
            }
        ],
        "language": { "url": "../vendor/DataTables/Portuguese-Brasil.json" },
        "order": [[4, 'desc']]
    });

    // Função para carregar os fornecedores no select
    function carregarFornecedores() {
        return $.ajax({
            url: 'process/listar_opcoes_fornecedores.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                console.log('Resposta de listar_opcoes_fornecedores:', response); // Log para depuração
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
            },
            error: function (xhr, status, error) {
                console.error('Erro na requisição AJAX de fornecedores:', status, error);
            }
        });
    }



    // Função para ATUALIZAR o valor do campo "Lote Completo" em tempo real
    function atualizarLoteCompleto() {
        // Esta função agora SEMPRE calcula e atualiza o campo,
        // permitindo que o usuário edite depois, se necessário.

        const numero = $('#lote_numero').val() || '0000';
        const dataFabStr = $('#lote_data_fabricacao').val();
        const ciclo = $('#lote_ciclo').val() || 'C';
        const viveiro = $('#lote_viveiro').val() || 'V';

        const fornecedorOption = $('#lote_fornecedor_id').find(':selected');
        const codFornecedor = fornecedorOption.data('codigo-interno') || 'CF';

        let ano = 'YY';
        if (dataFabStr) {
            try {
                ano = new Date(dataFabStr + 'T00:00:00').getFullYear().toString().slice(-2);
            } catch (e) { /* ignora erro de data inválida durante a digitação */ }
        }

        const loteCompletoCalculado = `${numero}/${ano}-${ciclo}/${viveiro} ${codFornecedor}`;
        $('#lote_completo_calculado').val(loteCompletoCalculado);
    }

    $('#form-lote-header').on('change keyup', 'input, select', function (event) {
        if (event.target.id === 'lote_completo_calculado') {
            return;
        } atualizarLoteCompleto();
    });

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

        // 3. Busca o próximo número de lote
        $.ajax({
            url: 'process/get_proximo_numero.php', // VERIFIQUE ESTE CAMINHO
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#lote_numero').val(response.proximo_numero);
                } else {
                    $('#lote_numero').val('Erro!');
                }
            }
        });
    });

    // Inicializa o Select2 nos dropdowns (quando o modal for aberto)
    $('#modal-lote').on('shown.bs.modal', function () {
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
        carregarProdutos();
    });

    $('#btn-salvar-lote').on('click', function () {
        const formData = new FormData($('#form-lote-header')[0]);

        $.ajax({
            url: 'process/salvar_cabecalho.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#mensagem-lote-header').html(`<div class="alert alert-success">${response.message}</div>`);
                    if (response.novo_lote_id) {
                        $('#lote_id').val(response.novo_lote_id);
                    }
                    $('#aba-add-produtos-tab').removeClass('disabled').attr('aria-disabled', 'false');
                    new bootstrap.Tab($('#aba-add-produtos-tab')[0]).show();
                    tableLotes.ajax.reload(null, false);
                } else {
                    $('#mensagem-lote-header').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            },
            error: function () {
                $('#mensagem-lote-header').html(`<div class="alert alert-danger">Erro de comunicação ao salvar.</div>`);
            }
        });
    });

    // Carrega os fornecedores quando a página é carregada pela primeira vez
    carregarFornecedores();

    // Função auxiliar para renderizar a tabela de itens dentro do modal
    function renderizarItensDoLote(items) {
        const $container = $('#lista-produtos-deste-lote');
        if (!items || items.length === 0) {
            $container.html('<p class="text-muted">Nenhum produto incluído ainda.</p>');
            return;
        }

        let tableHtml = `
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Peso Total (kg)</th>
                    <th>Validade</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
    `;
        items.forEach(function (item) {
            const dataValidade = new Date(item.item_data_validade + 'T00:00:00').toLocaleDateString('pt-BR');
            const validadeISO = item.item_data_validade;
            const pesoTotalItem = (parseFloat(item.item_quantidade) * parseFloat(item.prod_peso_embalagem)).toFixed(3);

            tableHtml += `
            <tr data-produto-id="${item.item_produto_id}" data-validade-iso="${validadeISO}">
                <td>${item.prod_descricao}</td>
                <td>${item.item_quantidade}</td>
                <td>${pesoTotalItem}</td>
                <td>${dataValidade}</td>
                <td>
                    <button class="btn btn-secondary btn-sm btn-editar-item" data-item-id="${item.item_id}">Editar</button>
                    <button class="btn btn-outline-danger btn-sm btn-excluir-item" data-item-id="${item.item_id}">Excluir</button>
                    <button class="btn btn-outline-dark btn-sm btn-imprimir-etiqueta" data-item-id="${item.item_id}" title="Imprimir Etiqueta"><i class="fa-solid fa-barcode"></i></button>
                </td>
            </tr>
        `;
        });
        tableHtml += '</tbody></table>';
        $container.html(tableHtml);
    }

    // Função para carregar os produtos no select da Aba 2, aceitando um filtro
    function carregarProdutos(tipoEmbalagemFiltro = 'Todos') {
        $.ajax({
            url: 'process/listar_opcoes_produtos.php',
            type: 'GET',
            data: { tipo_embalagem: tipoEmbalagemFiltro }, // Envia o filtro para o PHP
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const $selectProduto = $('#item_produto_id');
                    $selectProduto.empty().append('<option value="">Selecione um produto...</option>');

                    response.data.forEach(function (produto) {
                        $selectProduto.append(
                            $('<option>', {
                                value: produto.prod_codigo,
                                text: produto.prod_descricao,
                                // Guardamos a regra de validade no próprio option para uso futuro
                                'data-validade-meses': produto.prod_validade_meses,
                                'data-peso-embalagem': produto.prod_peso_embalagem

                            })
                        );
                    });
                    // Atualiza o select2 para refletir as novas opções
                    $selectProduto.trigger('change.select2');
                }
            }
        });
    }

    // Adicione esta função ao seu lotes.js
    function recarregarItensDoLote(loteId) {
        if (!loteId) return; // Não faz nada se não houver um lote_id

        $.ajax({
            url: 'process/buscar_lote.php', // Reutilizamos o script que busca o lote inteiro
            type: 'POST',
            data: { lote_id: loteId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Chama a função para redesenhar a tabela com os dados atualizados
                    renderizarItensDoLote(response.data.items);
                }
            }
        });
    }

    // Evento de clique para os botões de rádio do filtro
    $('input[name="filtro_tipo_embalagem"]').on('change', function () {
        // Pega o valor do rádio selecionado ('Todos', 'PRIMARIA' ou 'SECUNDARIA')
        const filtroSelecionado = $(this).val();
        // Chama a função para recarregar o dropdown com o filtro
        carregarProdutos(filtroSelecionado);
    });

    // Função para calcular a validade
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

    // Função para calcular o peso total do item de acordo com a quantidade e peso da embalagem)
    function calcularPesoTotal() {
        const quantidade = parseFloat($('#item_quantidade').val()) || 0;
        const pesoEmbalagem = parseFloat($('#item_produto_id').find(':selected').data('peso-embalagem')) || 0;

        const pesoTotal = quantidade * pesoEmbalagem;

        // Exibe o resultado formatado com 3 casas decimais
        $('#item_peso_total').val(pesoTotal.toFixed(3));
    }

    // Gatilhos para o cálculo
    $('#item_produto_id').on('change', calcularPesoTotal);
    $('#item_quantidade').on('keyup change', calcularPesoTotal);

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

    // Evento do checkbox para liberar a edição da validade
    $('#liberar_edicao_validade').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('#item_data_validade').prop('readonly', !isChecked);
    });

    // Ajuste no 'shown.bs.modal' para carregar os produtos na abertura
    $('#modal-lote').on('shown.bs.modal', function () {

        $('#lote_fornecedor_id').select2({
            placeholder: 'Selecione um fornecedor',
            dropdownParent: $('#modal-lote'),
            theme: "bootstrap-5"
        });

        $('#item_produto_id').select2({
            placeholder: 'Selecione um produto',
            dropdownParent: $('#modal-lote'),
            theme: "bootstrap-5"
        });

        // Carrega os fornecedores e produtos na carga inicial da página
        carregarProdutos(); // Carrega os produtos ao abrir o modal
    });

    // Ação para o botão "Editar" na tabela principal de lotes
    $('#tabela-lotes tbody').on('click', '.btn-editar-lote', function () {
        const loteId = $(this).data('id');

        $.ajax({
            url: 'process/buscar_lote.php',
            type: 'POST',
            data: { lote_id: loteId },
            dataType: 'json',
            success: function (response) {
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

                    // 2. Renderiza a lista de produtos do lote
                    renderizarItensDoLote(lote.items);

                    // 3. Prepara o modal
                    $('#modal-lote-label').text('Editar Lote: ' + header.lote_completo_calculado);
                    $('#aba-add-produtos-tab').removeClass('disabled').attr('aria-disabled', 'false');

                    // 4. Carrega os fornecedores e define o valor do fornecedor após o carregamento
                    carregarFornecedores().done(function () {
                        if (header.lote_fornecedor_id) {
                            $('#lote_fornecedor_id').val(header.lote_fornecedor_id).trigger('change');
                        } else {
                            $('#lote_fornecedor_id').val('').trigger('change'); // Caso seja null
                        }
                        $('#modal-lote').modal('show'); // Abre o modal após carregar fornecedores
                    });
                } else {
                    alert('Erro ao buscar dados do lote: ' + response.message);
                }
            },
            error: function () {
                alert('Erro de comunicação ao buscar dados do lote.');
            }
        });
    });

    // Ação para o botão "Excluir" na tabela PRINCIPAL de lotes
    $('#tabela-lotes tbody').on('click', '.btn-excluir-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).closest('tr').find('td:first').text(); // Pega o texto da primeira coluna da linha

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
            url: 'process/excluir_lote.php',
            type: 'POST',
            data: {
                lote_id: loteId,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function (response) {
                // Fecha o modal de confirmação
                $('#modal-confirmar-exclusao-lote').modal('hide');

                if (response.success) {
                    // Exibe uma mensagem de sucesso na área principal
                    $('#feedback-message-area-lote').html(`<div class="alert alert-success">${response.message}</div>`);
                    // Recarrega a tabela para remover a linha excluída
                    tableLotes.ajax.reload(null, false);
                } else {
                    $('#feedback-message-area-lote').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            },
            error: function () {
                $('#modal-confirmar-exclusao-lote').modal('hide');
                alert('Erro de comunicação ao tentar excluir o lote.');
            }
        });
    });



    // =======================================================================
    // >> INÍCIO DO CÓDIGO DE FINALIZAR LOTE <<
    // =======================================================================

    // Ação para o botão "Finalizar" na tabela principal
    $('#tabela-lotes tbody').on('click', '.btn-finalizar-lote', function () {
        const loteId = $(this).data('id');
        const loteNome = $(this).closest('tr').find('td:first').text();

        $('#id-lote-finalizar').val(loteId);
        $('#nome-lote-finalizar').text(loteNome);

        $('#modal-confirmar-finalizar-lote').modal('show');
    });

    // Ação do botão de confirmação final, DENTRO do modal de finalização
    $('#btn-confirmar-finalizar').on('click', function () {
        const loteId = $('#id-lote-finalizar').val();

        $.ajax({
            url: 'process/finalizar_lote.php',
            type: 'POST',
            data: {
                lote_id: loteId,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function (response) {
                $('#modal-confirmar-finalizar-lote').modal('hide');

                if (response.success) {
                    $('#feedback-message-area-lote').html(`<div class="alert alert-success">${response.message}</div>`);
                    tableLotes.ajax.reload(null, false);
                } else {
                    $('#feedback-message-area-lote').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            },
            error: function () {
                $('#modal-confirmar-finalizar-lote').modal('hide');
                alert('Erro de comunicação ao tentar finalizar o lote.');
            }
        });
    });

    // =======================================================================
    // >> FIM DO CÓDIGO DE FINALIZAR LOTE <<
    // =======================================================================


    // Ação do botão "Incluir Produto" / "Salvar Alterações"
    $('#btn-incluir-produto').on('click', function () {
        const loteId = $('#lote_id').val();
        if (!loteId) {
            alert('Erro: ID do lote não encontrado. Salve o cabeçalho primeiro.');
            return;
        }

        const form = $('#form-adicionar-produto');
        // Garante que o lote_id esteja no formulário a ser enviado
        form.find('input[name="lote_id"]').remove();
        form.append(`<input type="hidden" name="lote_id" value="${loteId}">`);

        const formData = new FormData(form[0]);

        $.ajax({
            url: 'process/salvar_item.php', // Usando nosso script unificado
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // 1. Limpa o formulário e reseta o botão para o modo "Incluir"
                    $('#btn-cancelar-inclusao').trigger('click');

                    // 2. === A CORREÇÃO ESTÁ AQUI ===
                    // Chama a função para buscar e redesenhar a lista completa de itens
                    recarregarItensDoLote(loteId);

                    // 3. Volta para a primeira aba para o usuário ver o resultado
                    new bootstrap.Tab($('#aba-info-lote-tab')[0]).show();

                } else {
                    $('#mensagem-add-produto').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            },
            error: function () {
                $('#mensagem-add-produto').html(`<div class="alert alert-danger">Erro de comunicação ao incluir produto.</div>`);
            }
        });
    });

    // Ação para o botão "Editar" de um item DENTRO do modal
    $('#lista-produtos-deste-lote').on('click', '.btn-editar-item', function () {
        const itemId = $(this).data('item-id');

        // Simplesmente pegamos os dados da linha da tabela para preencher o formulário,
        // o que evita uma nova chamada AJAX.
        const linha = $(this).closest('tr');

        // Pega os dados guardados na linha da tabela
        const produtoId = linha.data('produto-id');
        const quantidade = linha.find('td:eq(1)').text();
        const validadeISO = linha.data('validade-iso');

        // Preenche o formulário na Aba 2
        // 1. Preenche o campo INVISÍVEL com o ID do item
        $('#item_id').val(itemId);
        // 2. Seleciona o produto correto no dropdown
        $('#item_produto_id').val(produtoId).trigger('change');
        // 3. Preenche a quantidade
        $('#item_quantidade').val(quantidade);
        // 4. Preenche a data de validade
        $('#item_data_validade').val(validadeISO);

        // Calcula o peso total ao editar
        calcularPesoTotal();

        // Muda o texto do botão para indicar edição
        $('#btn-incluir-produto').text('Salvar Alterações').removeClass('btn-success').addClass('btn-info');

        // Leva o usuário para a aba de edição
        new bootstrap.Tab($('#aba-add-produtos-tab')[0]).show();
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


    // Ação para o botão "Excluir" de um item DENTRO do modal
    $('#lista-produtos-deste-lote').on('click', '.btn-excluir-item', function () {
        const itemId = $(this).data('item-id');
        const $linhaParaRemover = $(this).closest('tr'); // Pega a linha da tabela (<tr>) para removermos depois

        // Pede a confirmação do usuário
        if (confirm('Tem certeza que deseja excluir este item do lote?')) {
            $.ajax({
                url: 'process/excluir_item.php',
                type: 'POST',
                data: {
                    item_id: itemId,
                    csrf_token: csrfToken // Usando a variável global que já definimos
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Remove a linha da tabela da interface com um efeito suave
                        $linhaParaRemover.fadeOut(400, function () {
                            $(this).remove();
                        });

                        // Opcional: mostrar uma pequena mensagem de sucesso
                        // $('#mensagem-lote-header').html(`<div class="alert alert-success">${response.message}</div>`);

                        // Recarrega a tabela principal ao fundo para refletir qualquer mudança
                        tableLotes.ajax.reload(null, false);

                    } else {
                        alert('Erro ao excluir item: ' + response.message);
                    }
                },
                error: function () {
                    alert('Erro de comunicação ao tentar excluir o item.');
                }
            });
        }
    });

});

$('#modal-lote').on('hidden.bs.modal', function () {
    // Quando o modal principal é fechado, retorna o foco para o corpo do documento.
    $('body').focus();
});