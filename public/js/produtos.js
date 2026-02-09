// public/produtos.js
$(document).ready(function () {
    // =================================================================
    // Bloco de Configuração e Seletores Globais
    // =================================================================
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $modalProduto = $('#modal-adicionar-produto');
    const $formProduto = $('#form-produto');
    const $tipoEmbalagemSelect = $('#prod_tipo_embalagem');
    const $blocoEmbalagemSecundaria = $('#bloco-embalagem-secundaria');
    const $blocoEmbalagemPrimaria = $('#bloco-embalagem-primaria');
    const $blocoEan13 = $('#bloco-ean13');
    const $blocoDun14 = $('#bloco-dun14');
    const $produtoPrimarioSelect = $('#prod_primario_id');
    const $pesoEmbalagemSecundariaInput = $('#peso_embalagem_secundaria');
    const $unidadesPrimariasInput = $('#unidades_primarias');
    let produtoPrimarioCache = {};

    // =================================================================
    // INICIALIZAÇÃO DO SELECT2 (FUNÇÃO DE BUSCA NO DROPDOWN)
    // =================================================================
    $produtoPrimarioSelect.select2({
        placeholder: 'Digite para buscar um produto...',
        language: "pt-BR",
        theme: "bootstrap-5",
        // Esta opção é CRUCIAL para que a busca funcione dentro de um modal do Bootstrap
        dropdownParent: $modalProduto
    });

    // Configura todas as chamadas AJAX para enviar o token CSRF
    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
        if (!options.crossDomain && originalOptions.type && originalOptions.type.toLowerCase() === 'post') {
            if (typeof options.data === 'string' && options.data.indexOf('csrf_token') === -1) {
                options.data += (options.data ? '&' : '') + 'csrf_token=' + encodeURIComponent(csrfToken);
            } else if (options.data instanceof FormData) {
                if (!options.data.has('csrf_token')) {
                    options.data.append('csrf_token', csrfToken);
                }
            }
        }
    });

    // =================================================================
    // Funções Auxiliares
    // =================================================================

    /**
    * Calcula e atualiza o campo 'Classe' com base em outros campos do formulário.
    */
    function atualizarClasseProduto() {
        const tipo = $('#prod_tipo').val();
        const subtipo = $('#prod_subtipo').val().toUpperCase(); // Normaliza para maiúsculas
        const conservacao = $('#prod_conservacao').val();
        const congelamento = $('#prod_congelamento').val();

        let partes = [];

        // 1. Tipo do Produto
        // if (tipo) partes.push(tipo);

        if (tipo) {
            if (tipo === 'CAMARAO') {
                partes.push('CAMARÃO');
            } else {
                partes.push(tipo);
            }
        }

        // 2. Adiciona "CINZA" se for Camarão
        if (tipo === 'CAMARAO') partes.push('CINZA');

        // 3. Condicional para Subtipo
        let subtipoFormatado = '';
        switch (subtipo) {
            case 'PUD':
                subtipoFormatado = 'DESCASCADO';
                break;
            case 'P&D':
            case 'PPV':
                subtipoFormatado = 'DESCASCADO EVISCERADO';
                break;
            case 'P&D C/ CAUDA':
            case 'P&D TAIL ON':
            case 'P&D TAIL-ON':
            case 'P&D TAILON':
            case 'PPV C/ CAUDA':
                subtipoFormatado = 'DESCASCADO EVISCERADO COM CAUDA';
                break;
            case 'PUD C/ CAUDA':
                subtipoFormatado = 'DESCASCADO COM CAUDA';
                break;
            default:
                subtipoFormatado = subtipo; // Usa o valor original se não houver regra
        }
        if (subtipoFormatado) partes.push(subtipoFormatado);

        // 4. Condicional para Conservação
        let conservacaoFormatada = '';
        if (conservacao === 'COZIDO') {
            conservacaoFormatada = 'COZIDO';
        } else if (conservacao === 'PARC. COZIDO') {
            conservacaoFormatada = 'PARCIALMENTE COZIDO';
        }
        if (conservacaoFormatada) partes.push(conservacaoFormatada);

        // 5. Condicional para Congelamento
        if (congelamento === 'IQF' || congelamento === 'BLOCO') {
            partes.push('CONGELADO');
        }

        // Junta todas as partes com um espaço e atualiza o campo
        $('#prod_classe').val(partes.join(' ').toUpperCase());
    }

    function showFeedbackMessage(msg, type = 'success', area = '#feedback-message-area-produto') {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        $(area).empty().removeClass('alert-success alert-danger').addClass(`alert ${alertClass}`).text(msg).fadeIn();
        setTimeout(() => $(area).fadeOut('slow'), 5000);
    }

    function toggleEmbalagemFields() {
        const tipo = $tipoEmbalagemSelect.val();
        const isSecundaria = (tipo === 'SECUNDARIA');
        const isMateriaPrima = (tipo === 'MATERIA-PRIMA');

        $blocoEmbalagemSecundaria.toggle(isSecundaria);
        $blocoEmbalagemPrimaria.toggle(!isSecundaria);
        $blocoEmbalagemPrimaria.toggle(!isSecundaria && !isMateriaPrima);
        $blocoEan13.toggle(!isSecundaria);
        $blocoDun14.toggle(isSecundaria);
        $('#asterisco-codigo-interno').toggle(isSecundaria);
        $('#prod_codigo_interno').prop('required', isSecundaria);

        // Campos específicos a serem escondidos para 'MATERIA-PRIMA'
        const showCamposComuns = !isMateriaPrima;
        $('#prod_categoria').closest('.col-md-3.mb-3').toggle(showCamposComuns);
        $('#prod_classe').closest('.col-md-7.mb-3').toggle(showCamposComuns);
        $('#prod_fator_producao').closest('.col-md-4.mb-3').toggle(showCamposComuns);

        // Limpar campos escondidos ao selecionar 'MATERIA-PRIMA'
        if (isMateriaPrima) {
            $('#prod_categoria').val('');
            $('#prod_classe').val('');
            $('#prod_fator_producao').val('');
            $('#prod_peso_embalagem').val('');
            $('#prod_total_pecas').val('');
            $('#prod_ean13').val('');
        }

        if (isSecundaria) {
            loadProdutosPrimarios();
        } else {
            $('#prod_dun14').val('');
            $produtoPrimarioSelect.val('').trigger('change');
        }
    }

    function loadProdutosPrimarios() {
        return $.ajax({
            url: 'ajax_router.php?action=listarProdutosPrimarios',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $produtoPrimarioSelect.empty().append('<option value="">Selecione...</option>');
                    response.data.forEach(function (produto) {
                        // Lógica para adicionar o Cód. Interno APENAS se ele existir (não for nulo ou vazio)
                        const codigoInternoTexto = produto.prod_codigo_interno ? ` (Cód: ${produto.prod_codigo_interno})` : '';

                        // O texto final é apenas a descrição (que já tem o peso) + o código interno (se existir)
                        const optionText = `${produto.prod_descricao}${codigoInternoTexto}`;

                        $produtoPrimarioSelect.append(new Option(optionText, produto.prod_codigo));
                        produtoPrimarioCache[produto.prod_codigo] = produto;
                    });
                    $produtoPrimarioSelect.trigger('change.select2');
                }
            }
        });
    }

    function calcularUnidades() {
        const produtoPrimarioId = $produtoPrimarioSelect.val();
        const pesoSecundario = parseFloat($pesoEmbalagemSecundariaInput.val());
        if (produtoPrimarioId && pesoSecundario > 0 && produtoPrimarioCache[produtoPrimarioId]) {
            const pesoPrimario = parseFloat(produtoPrimarioCache[produtoPrimarioId].prod_peso_embalagem);
            if (pesoPrimario > 0) {
                $unidadesPrimariasInput.val((pesoSecundario / pesoPrimario).toFixed(2));
            }
        } else {
            $unidadesPrimariasInput.val('');
        }
    }

    // =================================================================
    // 1. CARREGAMENTO DE MARCAS PARA O FILTRO
    // =================================================================
    /* function carregarFiltroMarcas() {
         $.ajax({
             url: 'ajax_router.php?action=getMarcasOptions',
             type: 'GET',
             dataType: 'json',
             success: function (response) {
                 if (response.success) {
                     let html = '';
                     if (response.data.length === 0) {
                         html = '<li class="text-muted small text-center">Nenhuma marca cad.</li>';
                     } else {
                         response.data.forEach(function (marca, index) {
                             // Sanitiza a marca para usar como ID
                             let idSafe = 'marca-' + index;
                             html += `
                                 <li>
                                     <div class="form-check dropdown-item-custom">
                                         <input class="form-check-input filter-check" type="checkbox" value="${marca}" id="${idSafe}">
                                         <label class="form-check-label" for="${idSafe}">${marca}</label>
                                     </div>
                                 </li>`;
                         });
                     }
                     $('#lista-marcas-dinamica').html(html);
                 }
             }
         });
     } */

    /*  function carregarFiltroMarcas() {
         $.ajax({
             url: 'ajax_router.php?action=getMarcasOptions',
             type: 'GET',
             dataType: 'json',
             success: function (response) {
                 if (response.success) {
                     let html = '';
                     if (response.data.length === 0) {
                         html = '<li class="text-muted small text-center py-2">Nenhuma marca cadastrada</li>';
                     } else {
                         response.data.forEach(function (marca, index) {
                             // Correção para marcas com espaços ou caracteres especiais no ID
                             let idSafe = 'marca_' + index;
                             html += `
                                 <li class="px-2">
                                     <div class="form-check">
                                         <input class="form-check-input filter-check" type="checkbox" value="${marca}" id="${idSafe}">
                                         <label class="form-check-label" for="${idSafe}">${marca}</label>
                                     </div>
                                 </li>`;
                         });
                     }
                     $('#lista-marcas-dinamica').html(html);
                 }
             }
         });
     } */

    function carregarFiltroMarcas() {
        $.ajax({
            url: 'ajax_router.php?action=getMarcasOptions',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    let html = '';
                    const isTodosMarcado = $('#marca_todos').is(':checked');
                    const checkedStr = isTodosMarcado ? 'checked' : '';

                    if (response.data.length === 0) {
                        html = '<li class="text-muted small text-center py-2">Nenhuma marca cadastrada</li>';
                    } else {
                        response.data.forEach(function (marca, index) {
                            let idSafe = 'marca_' + index;
                            html += `
                            <li class="px-2">
                                <div class="form-check">
                                    <input class="form-check-input filter-check" type="checkbox" value="${marca}" id="${idSafe}" ${checkedStr}>
                                    <label class="form-check-label" for="${idSafe}">${marca}</label>
                                </div>
                            </li>`;
                        });
                    }
                    $('#lista-marcas-dinamica').html(html);

                    // Inicializa a lógica do dropdown DEPOIS de inserir as marcas
                    setupDropdownFilterLogic('filter-marca-container', 'dropdownMarca', 'Todas as Marcas');
                }
            }
        });
    }

    carregarFiltroMarcas();

    // =================================================================
    // 2. LÓGICA GENÉRICA DOS DROPDOWNS COM CHECKBOX (Select All)
    // =================================================================
    function setupDropdownCheckboxes(containerId) {
        const $container = $('#' + containerId);
        const $checkAll = $container.find('.check-all');

        // Quando clicar em "Todos"
        $container.on('change', '.check-all', function () {
            const isChecked = $(this).is(':checked');
            // Marca/Desmarca todos os filhos
            $container.find('.filter-check').prop('checked', isChecked);

            // Recarrega a tabela automaticamente (opcional, pode deixar pro botão filtrar)
            // tableProdutos.ajax.reload(); 
        });

        // Quando clicar em um Item individual
        $container.on('change', '.filter-check', function () {
            const totalItems = $container.find('.filter-check').length;
            const totalChecked = $container.find('.filter-check:checked').length;

            // Se todos estiverem marcados, marca o "Todos", senão desmarca
            $checkAll.prop('checked', totalItems === totalChecked);

            // Se nenhum estiver marcado, poderiamos forçar "Todos" ou deixar vazio (que o backend trata como todos)
            if (totalChecked === 0) {
                $checkAll.prop('checked', true); // UX: Resetar para todos se o usuário desmarcar tudo
                // $container.find('.filter-check').prop('checked', true); // Opcional: remarcar visualmente
            }
        });
    }

    // Aplica a lógica nos 3 filtros
    setupDropdownCheckboxes('filter-tipo-container');
    setupDropdownCheckboxes('filter-situacao-container');
    setupDropdownCheckboxes('filter-marca-container');

    // Função auxiliar para coletar os valores marcados
    function getSelectedValues(containerId) {
        const $container = $('#' + containerId);
        // Verifica se "Todos" está marcado
        if ($container.find('.check-all').is(':checked')) {
            return ['TODOS'];
        }

        // Coleta os individuais
        let values = [];
        $container.find('.filter-check:checked').each(function () {
            values.push($(this).val());
        });

        // Fallback de segurança: se nada marcado, retorna TODOS
        return values.length > 0 ? values : ['TODOS'];
    }

    // --- LÓGICA VISUAL DOS FILTROS DROPDOWN ---

    /**
     * Função genérica para gerenciar o texto do botão dropdown
     * CORREÇÃO: Sincronização inicial para evitar delay e texto errado.
     */
    function setupDropdownFilterLogic(containerId, buttonId, defaultText) {

        const $container = $('#' + containerId);
        const $btn = $('#' + buttonId);
        const $checkAll = $container.find('.check-all');
        const $items = $container.find('.filter-check');

        // --- CORREÇÃO 1: SINCRONIZAÇÃO INICIAL (O PULO DO GATO) ---
        // Se o botão "Todos" vier marcado do HTML, forçamos todos os filhos a ficarem marcados também.
        if ($checkAll.is(':checked')) {
            $items.prop('checked', true);
        }

        // Chama a atualização de texto logo de cara para corrigir o título do botão
        atualizarTexto();

        // --- EVENTO 1: CLIQUE NO "MARCAR TODOS" ---
        $checkAll.on('click', function () {
            const isChecked = $(this).is(':checked');
            $items.prop('checked', isChecked);
            atualizarTexto();
        });

        // --- EVENTO 2: CLIQUE EM UM ITEM INDIVIDUAL ---
        $items.on('click', function (e) {
            const $this = $(this);
            const wasAllChecked = $checkAll.is(':checked'); // Estado ANTES do clique

            // LÓGICA DE ISOLAMENTO:
            // Se estava tudo marcado e o usuário clica em um item, ele quer FILTRAR só por aquele.
            if (wasAllChecked) {
                e.preventDefault(); // Impede o comportamento padrão para controlarmos manualmente

                // 1. Desmarca tudo
                $items.prop('checked', false);
                $checkAll.prop('checked', false);

                // 2. Marca SÓ o que foi clicado
                $this.prop('checked', true);
            }

            // SE NÃO ESTAVA TUDO MARCADO (Seleção parcial):
            // Deixa o navegador marcar/desmarcar nativamente e só conferimos se "encheu" a lista.

            // Verifica status final para atualizar o pai
            const total = $items.length;
            const checkedCount = $items.filter(':checked').length;

            $checkAll.prop('checked', total === checkedCount);

            atualizarTexto();
        });

        function atualizarTexto() {
            const $checked = $items.filter(':checked');
            const count = $checked.length;
            const total = $items.length;

            // 1. NENHUM SELECIONADO
            if (count === 0) {
                $btn.text("Nenhum Selecionado");
                $btn.attr('title', "Selecione pelo menos uma opção");
                $btn.removeClass('btn-secondary text-white').addClass('btn-outline-secondary');
            }
            // 2. TODOS SELECIONADOS
            else if (count === total) {
                $btn.text(defaultText);
                $btn.attr('title', defaultText);
                $btn.removeClass('btn-secondary text-white').addClass('btn-outline-secondary');
            }
            // 3. SELEÇÃO PARCIAL
            else {
                $btn.removeClass('btn-outline-secondary').addClass('btn-secondary text-white');

                if (count <= 3) {
                    let labels = [];
                    $checked.each(function () {
                        labels.push($(this).next('label').text().trim());
                    });
                    $btn.text(labels.join(', '));
                    $btn.attr('title', labels.join(', '));
                } else {
                    $btn.text(count + ' selecionados');
                }
            }
        }


        // Inicializa texto ao carregar
        atualizarTexto();
    }

    // --- INICIALIZAÇÃO DA LÓGICA ---
    $(document).ready(function () {
        // Aplica a lógica para cada filtro
        setupDropdownFilterLogic('filter-tipo-container', 'dropdownTipo', 'Todos Tipos');
        setupDropdownFilterLogic('filter-situacao-container', 'dropdownSituacao', 'Todas Situações');
        // setupDropdownFilterLogic('filter-marca-container', 'dropdownMarca', 'Todas as Marcas');
    });

    // Controla o texto do switch Ativo/Inativo
    $modalProduto.on('change', '#prod_situacao', function () {
        const isChecked = $(this).is(':checked');
        $('#label-prod-situacao').text(isChecked ? 'Ativo' : 'Inativo');
    });

    // =================================================================
    // INICIALIZAÇÃO DA TABELA DATATABLES
    // =================================================================
    const tableProdutos = $('#tabela-produtos').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarProdutos",
            "type": "POST",
            "data": function (d) {
                // d.filtro_situacao = $('input[name="filtro_situacao"]:checked').val() || 'Todos';
                // d.filtro_tipo = $('input[name="filtro_tipo"]:checked').val() || 'Todos';

                d.filtro_situacao = getSelectedValues('filter-situacao-container');
                d.filtro_tipo = getSelectedValues('filter-tipo-container');
                d.filtro_marcas = getSelectedValues('filter-marca-container');
            }
        },
        "responsive": true,
        "columns": [

            //  <th class="text-center">Sit.</th>
            {
                "data": "prod_situacao",
                "className": "text-center align-middle",
                "render": data => (data === 'A') ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'
            },
            //<th class="text-center">Cód.</th>
            {
                "data": "prod_codigo_interno",
                "className": "text-center align-middle font-small"
            },
            //<th>Descrição</th>
            {
                "data": "prod_descricao",
                "className": "align-middle font-small"
            },
            // <th>Marca</th>
            { "data": "prod_marca", "defaultContent": "-" },
            //<th>Desc. Etiqueta</th>
            {
                "data": "prod_classe",
                "className": "align-middle font-small"
            },
            //<th class="text-center">Tipo</th>
            {
                "data": "prod_tipo",
                "className": "col-centralizavel align-middle font-small"
            },
            // <th class="text-center">Emb.</th>
            {
                "data": "prod_tipo_embalagem",
                "className": "col-centralizavel align-middle font-small"
            },
            // <th class="text-center">Peso</th>
            {
                "data": "prod_peso_embalagem",
                "className": "col-centralizavel align-middle font-small"
            },
            // <th>Unid.</th>   
            {
                "data": "prod_unidade",
                "className": "text-center align-middle font-small"
            },
            //<th class="text-center">Ações</th>
            {
                "data": "prod_codigo",
                "orderable": false,
                "className": "col-centralizavel align-middle",
                "render": (data) => {
                    let btnEditar = `<a href="#" class="btn btn-warning btn-sm btn-editar-produto me-1 d-inline-flex align-items-center" data-id="${data}"><i class="fas fa-pencil-alt me-1"></i>Editar</a>`;
                    let btnCopiar = `<a href="#" class="btn btn-info btn-sm btn-copiar-produto me-1 d-inline-flex align-items-center" data-id="${data}" title="Copiar/Duplicar"><i class="fas fa-copy me-1"></i>Copiar</a>`;
                    let btnExcluir = `<a href="#" class="btn btn-danger btn-sm btn-excluir-produto me-1 d-inline-flex align-items-center" data-id="${data}"><i class="fas fa-trash-alt me-1"></i>Excluir</a>`;
                    return `<div class="btn-group">${btnEditar}${btnCopiar}${btnExcluir}</div>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // Botão "Aplicar Filtros" (o pequeno botão verde no menu)
    $('#btn-aplicar-filtros').on('click', function () {
        tableProdutos.ajax.reload();
    });

    // Recarregar tabela ao fechar o dropdown (Opcional, dá uma sensação de "Aplicar")
    $('.dropdown').on('hidden.bs.dropdown', function () {
        // tableProdutos.ajax.reload(); // Descomente se quiser reload automático ao sair do menu
    });

    // $('input[name="filtro_situacao"]').on('change', () => tableProdutos.ajax.reload());
    // $('input[name="filtro_tipo"]').on('change', () => tableProdutos.ajax.reload());

    $tipoEmbalagemSelect.on('change', toggleEmbalagemFields);
    $pesoEmbalagemSecundariaInput.on('keyup', calcularUnidades);

    // Abrir modal para Adicionar
    $('#btn-adicionar-produto-main').on('click', function () {
        // Este é o "Resetador" oficial para o modo ADICIONAR
        $formProduto[0].reset();
        $('#prod_codigo').val(''); // Garante que o ID esteja limpo
        $('#modal-adicionar-produto-label').text('Adicionar Produto');

        // Força o reset dos campos que o .reset() pode não pegar:
        $('#prod_situacao').prop('checked', true).trigger('change');

        // Força o dropdown para "PRIMARIA" e dispara o evento 'change'
        // O trigger('change') chama a função toggleEmbalagemFields(), 
        // que esconde os blocos "Secundária" e mostra os blocos "Primária".
        $tipoEmbalagemSelect.val('PRIMARIA').trigger('change');

        $modalProduto.modal('show');
    });

    $produtoPrimarioSelect.on('change', function () {
        const selectedId = $(this).val();
        if (selectedId && produtoPrimarioCache[selectedId]) {
            const produto = produtoPrimarioCache[selectedId];
            $('#prod_descricao').val(produto.prod_descricao + ' (Caixa)');
            $('#prod_tipo').val(produto.prod_tipo);
            $('#prod_subtipo').val(produto.prod_subtipo);
            $('#prod_classificacao').val(produto.prod_classificacao);
            $('#prod_especie').val(produto.prod_especie);
            $('#prod_ncm').val(produto.prod_ncm);
            $('#prod_marca').val(produto.prod_marca);
            $('#prod_origem').val(produto.prod_origem);
            $('#prod_conservacao').val(produto.prod_conservacao);
            $('#prod_congelamento').val(produto.prod_congelamento);
            $('#prod_fator_producao').val(produto.prod_fator_producao);
            $('#prod_total_pecas').val(produto.prod_total_pecas);
            $('#prod_categoria').val(produto.prod_categoria);
            $('#prod_validade_meses').val(produto.prod_validade_meses);
            $('#peso_embalagem_secundaria').val('');
            $('#prod_dun14').val('');
            $('#prod_ean13').val('');
            $('#prod_descricao').focus();
            calcularUnidades();
        }
    });

    $formProduto.on('submit', function (e) {
        e.preventDefault();

        const id = $('#prod_codigo').val();
        const action = id ? 'editarProduto' : 'cadastrarProduto';
        const url = `ajax_router.php?action=${action}`;

        // desabilitamos o campo de código antes de enviar.
        // Campos desabilitados não são incluídos no submit.
        if (!id) {
            $('#prod_codigo').prop('disabled', true);
        }

        const formData = new FormData(this);

        // Reabilitamos o campo logo após pegar os dados,
        // para que ele funcione normalmente depois.
        if (!id) {
            $('#prod_codigo').prop('disabled', false);
        }

        $.ajax({
            url: url, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                $modalProduto.modal('hide');
                tableProdutos.ajax.reload(null, false);
                notificacaoSucesso('Sucesso!', response.message);
            } else {
                notificacaoErro('Erro ao Salvar', response.message);
            }
        }).fail(function () {
            // notificacaoErro('Erro de Comunicação', 'Não foi possível salvar o produto.'); 
        });
    });

    // Gatilho para o cálculo automático da Classe do Produto
    $('#form-produto').on('change keyup', '#prod_tipo, #prod_subtipo, #prod_conservacao, #prod_congelamento', function () {
        atualizarClasseProduto();
    });

    $('#tabela-produtos').on('click', '.btn-editar-produto', function (e) {
        e.preventDefault();
        const idProduto = $(this).data('id');
        $.ajax({
            url: 'ajax_router.php?action=getProduto',
            type: 'POST',
            data: {
                prod_codigo: idProduto,
                csrf_token: csrfToken
            },
            dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                $formProduto[0].reset();
                const produto = response.data;
                Object.keys(produto).forEach(key => $formProduto.find(`[name="${key}"],#${key}`).val(produto[key]));
                atualizarClasseProduto();

                // Setar o switch de Ativo/Inativo
                const isAtivo = (produto.prod_situacao === 'A');
                $('#prod_situacao')
                    .val('A')
                    .prop('checked', isAtivo)
                    .trigger('change');

                // Dispara a mudança de embalagem, o que também chama o loadProdutosPrimarios se for SECUNDARIA
                $tipoEmbalagemSelect.val(produto.prod_tipo_embalagem).trigger('change');

                // Se for secundária, esperamos o carregamento terminar ANTES de definir o valor.
                if (produto.prod_tipo_embalagem === 'SECUNDARIA') {
                    $('#peso_embalagem_secundaria').val(produto.prod_peso_embalagem);

                    // A função 'loadProdutosPrimarios' agora retorna uma promessa.
                    // O código dentro do .done() só executa quando a lista estiver 100% carregada.
                    loadProdutosPrimarios().done(function () {
                        $('#prod_primario_id').val(produto.prod_primario_id).trigger('change.select2');
                        calcularUnidades();
                    });
                } else {
                    $('#prod_peso_embalagem').val(produto.prod_peso_embalagem);
                }

                $('#modal-adicionar-produto-label').text('Editar Produto');
                $modalProduto.modal('show');
            } else {
                notificacaoErro('Erro!', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível carregar os dados do produto.');
        });
    });

    $('#tabela-produtos').on('click', '.btn-excluir-produto', function (e) {
        e.preventDefault();
        const idProduto = $(this).data('id');
        confirmacaoAcao(
            'Excluir Produto?',
            'Tem a certeza de que deseja excluir este produto?'
        ).then((result) => {
            if (result.isConfirmed) {
                // Se o usuário confirmar, executa a exclusão
                $.ajax({
                    url: 'ajax_router.php?action=excluirProduto',
                    type: 'POST',
                    data: { prod_codigo: idProduto }, // CSRF é adicionado pelo prefilter
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        tableProdutos.ajax.reload();
                        notificacaoSucesso('Excluído!', response.message);
                    } else {
                        notificacaoErro('Erro ao Excluir', response.message);
                    }
                }).fail(function () {
                    notificacaoErro('Erro de Comunicação', 'Não foi possível excluir o produto.');
                });
            }
        });
    });

    // ### LISTENER PARA O BOTÃO COPIAR ###
    $('#tabela-produtos').on('click', '.btn-copiar-produto', function (e) {
        e.preventDefault();
        const idProduto = $(this).data('id');

        // 1. Usamos a mesma rota do "Editar" para buscar os dados completos do produto
        $.ajax({
            url: 'ajax_router.php?action=getProduto',
            type: 'POST',
            data: {
                prod_codigo: idProduto,
                csrf_token: csrfToken // Token global já definido no seu JS
            },
            dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                $formProduto[0].reset();
                const produto = response.data;
                Object.keys(produto).forEach(key => $formProduto.find(`[name="${key}"],#${key}`).val(produto[key]));

                // 2. Limpamos o ID do produto.
                // Isso garante que, ao salvar, o backend entenda como um INSERT (Criar), não um UPDATE (Editar).
                $('#prod_codigo').val('');

                // 3. UX: Forçamos o usuário a alterar os campos únicos
                const descricaoAtual = $('#prod_descricao').val();
                $('#prod_descricao').val(descricaoAtual + ' (CÓPIA)');
                $('#prod_codigo_interno').val(''); // Limpa o código interno

                // 4. Acionamos as funções de atualização do formulário (como o "Editar" faz)
                atualizarClasseProduto();
                $tipoEmbalagemSelect.val(produto.prod_tipo_embalagem).trigger('change');

                $('#prod_situacao')
                    .val('A') // Garante que o valor (value) seja "A" 
                    .prop('checked', true) // Marca como checado
                    .trigger('change'); // Atualiza o label (que dirá "Ativo")

                if (produto.prod_tipo_embalagem === 'SECUNDARIA') {
                    $('#peso_embalagem_secundaria').val(produto.prod_peso_embalagem);
                    loadProdutosPrimarios().done(function () {
                        $('#prod_primario_id').val(produto.prod_primario_id).trigger('change.select2');
                        calcularUnidades();
                    });
                } else {
                    $('#prod_peso_embalagem').val(produto.prod_peso_embalagem);
                }

                // 5. Mudamos o título do modal e o abrimos
                $('#modal-adicionar-produto-label').text('Copiar Produto (Criando Novo)');
                $modalProduto.modal('show');
            } else {
                notificacaoErro('Erro!', response.message);
            }
        }).fail(function () {
            notificacaoErro('Erro de Comunicação', 'Não foi possível carregar os dados do produto para cópia.');
        });
    });

    // Evento do Botão de Imprimir Relatório
    /*  $('#btn-imprimir-relatorio').on('click', function () {
          // 1. Filtro de Situação
          // let filtroSituacao = $('input[name="filtro_situacao"]:checked').val();
          let filtroSituacao = $('input[name="filtro_situacao"]:checked').val() || 'Todos';
     
          // 2. Filtro de Tipo
          // let filtroTipo = $('input[name="filtro_tipo"]:checked').val();
          let filtroTipo = $('input[name="filtro_tipo"]:checked').val() || 'Todos';
     
          // 3. Termo de Busca
          let termoBusca = $('.dataTables_filter input').val() || '';
     
          // 4. Monta a URL com todos os parâmetros
          let urlRelatorio = `index.php?page=relatorio_produtos&filtro=${filtroSituacao}&tipo=${filtroTipo}&search=${encodeURIComponent(termoBusca)}`;
     
          window.open(urlRelatorio, '_blank');
      });*/

    // =================================================================
    // 4. RELATÓRIO
    // =================================================================
    $('#btn-imprimir-relatorio').on('click', function () {
        // Pega os arrays
        let sit = getSelectedValues('filter-situacao-container');
        let tipo = getSelectedValues('filter-tipo-container');
        let marca = getSelectedValues('filter-marca-container');
        let search = $('.dataTables_filter input').val() || '';
        // let timestamp = new Date().getTime();

        // Transforma arrays em strings separadas por vírgula para passar na URL
        let urlRelatorio = `index.php?page=relatorio_produtos` +
            `&filtro=${sit.join(',')}` +
            `&tipo=${tipo.join(',')}` +
            `&marcas=${encodeURIComponent(marca.join(','))}` +
            `&search=${encodeURIComponent(search)}`;

        /* let url = `views/produtos/relatorio_lista.php?modo=pdf` +
             `&filtro=${sit.join(',')}` +
             `&tipo=${tipo.join(',')}` +
             `&marcas=${encodeURIComponent(marca.join(','))}` + // Encode para evitar erros com espaços na marca
             `&search=${encodeURIComponent(search)}`;*/

        window.open(urlRelatorio, '_blank');

    });


    // =================================================================
    // 5. VISUALIZAR DETALHES (CLIQUE NA LINHA)
    // =================================================================

    // Detecta clique na linha da tabela
    $('#tabela-produtos tbody').on('click', 'tr', function (e) {
        if ($(e.target).closest('button, a, .btn').length) {
            return;
        }

        const dataTable = $('#tabela-produtos').DataTable();
        const rowData = dataTable.row(this).data();

        // --- DEBUG: VAMOS DESCOBRIR O QUE TEM NA LINHA ---
        console.log("CLIQUE NA LINHA - DADOS:", rowData);
        // ------------------------------------------------

        // Se rowData for undefined (clique em cabeçalho ou linha vazia), para.
        if (!rowData) return;

        // TENTATIVA 1: O nome padrão do banco
        let idProduto = rowData.prod_codigo;

        // TENTATIVA 2: Às vezes o DataTables usa 'id' se foi aliasado no SQL
        if (!idProduto) idProduto = rowData.id;

        // TENTATIVA 3: Se for array numérico (índices 0, 1, 2...)
        if (!idProduto && Array.isArray(rowData)) idProduto = rowData[0]; // Supondo que ID é a primeira coluna

        console.log("ID CAPTURADO:", idProduto); // Confirme se apareceu o número aqui

        if (!idProduto) {
            notificacaoErro('Erro', 'Não foi possível identificar o ID do produto nesta linha.');
            return;
        }

        // Busca os dados completos no servidor
        $.post('ajax_router.php?action=getProduto', { id: idProduto }, function (response) {
            if (response.success) {
                abrirModalDetalhes(response.data);
            } else {
                notificacaoErro('Erro', response.message);
            }
        }, 'json');
    });

    function abrirModalDetalhes(produto) {
        // 1. Muda o Título
        $('#modal-adicionar-produto .modal-title').text('Detalhes do Produto (Modo Leitura)');

        // 2. Preenche os campos (Use os IDs do seu formulário)
        $('#prod_descricao').val(produto.prod_descricao);
        $('#prod_codigo_interno').val(produto.prod_codigo_interno);
        $('#prod_tipo').val(produto.prod_tipo);
        $('#prod_subtipo').val(produto.prod_subtipo);
        $('#prod_classificacao').val(produto.prod_classificacao);
        $('#prod_categoria').val(produto.prod_categoria);
        $('#prod_origem').val(produto.prod_origem);
        $('#prod_situacao').val(produto.prod_situacao);
        $('#prod_unidade').val(produto.prod_unidade);
        $('#prod_peso_embalagem').val(produto.prod_peso_embalagem);

        // Lógica dos Blocos (Embalagem Primária/Secundária)
        $('#prod_tipo_embalagem').val(produto.prod_tipo_embalagem).trigger('change');

        // Se for secundária, preenche o Select2 do Pai
        if (produto.prod_tipo_embalagem === 'SECUNDARIA' && produto.prod_primario_id) {
            // Cria a option manualmente para o Select2 exibir o texto correto
            const newOption = new Option(produto.nome_produto_pai, produto.prod_primario_id, true, true);
            $('#prod_primario_id').append(newOption).trigger('change');
        }

        // 3. BLOQUEIA TUDO (Inputs, Selects, Textareas)
        $formProduto.find('input, select, textarea').prop('disabled', true);

        // 4. Esconde o botão de Salvar e ajusta o botão Fechar
        $formProduto.closest('.modal-content').find('button[type="submit"]').hide();
        $formProduto.closest('.modal-content').find('.btn-secondary').text('Fechar');

        // 5. Abre o Modal
        $modalProduto.modal('show');
    }

    // =================================================================
    // 6. RESETAR MODAL AO FECHAR (CRUCIAL)
    // =================================================================
    $modalProduto.on('hidden.bs.modal', function () {
        // 1. Reseta o Título
        $(this).find('.modal-title').text('Adicionar Novo Produto');

        // 2. Limpa o form
        $formProduto[0].reset();

        // 3. Desbloqueia os campos
        $formProduto.find('input, select, textarea').prop('disabled', false);

        // 4. Mostra o botão Salvar novamente
        $(this).find('button[type="submit"]').show();
        $(this).find('.btn-secondary').text('Cancelar');

        // 5. Limpa Select2
        $('#prod_primario_id').val(null).trigger('change');
        $('#prod_tipo_embalagem').val('PRIMARIA').trigger('change'); // Volta ao padrão

        // Remove classes de validação se houver
        $formProduto.removeClass('was-validated');
    });


});