
// /public/js/detalhes_carregamento.js
$(document).ready(function () {
    const csrfToken = $('input[name="csrf_token"]').val();
    const urlParams = new URLSearchParams(window.location.search);
    const carregamentoId = urlParams.get('id');
    let oeId = null; // ID da OE Base

    // Cache dos Elementos do Cabeçalho
    const $formHeader = $('#form-carregamento-header');
    const $inputsHeader = $formHeader.find('input, select');
    const $btnEditarHeader = $('#btn-editar-header');
    const $btnSalvarHeader = $('#btn-salvar-header');
    const $btnCancelarHeader = $('#btn-cancelar-header');

    // Novo Modal Unificado
    const $modalAddItem = $('#modal-adicionar-item-carregamento');
    const $formAddItem = $('#form-adicionar-item');

    // Containers (cache)
    const $filasContainer = $('#filas-container');
    const $tabela = $('#tabela-planejamento');
    const $tabelaPlanejamentoBody = $('#tabela-planejamento-body');

    // Nossos dados-mestres
    let dadosOriginaisHeader = {};
    let gabaritoPlanejamento = []; // A "OE Base"

    // Caches dos campos do novo modal
    const $itemCliente = $('#item_cliente_id');
    const $itemProduto = $('#item_produto_id');
    const $itemLote = $('#item_lote_id');
    const $itemAlocacao = $('#item_alocacao_id');
    const $itemSaldo = $('#item_saldo_display');
    const $itemQtd = $('#item_quantidade');
    const $itemHelper = $('#item_helper_text');
    const $itemMotivoContainer = $('#container-motivo-divergencia');
    const $itemMotivoInput = $('#item_motivo_divergencia');
    const $itemOeiId = $('#item_oei_id_origem');
    const $itemBtnAdd = $('#btn-confirmar-add-item');

    let dadosEnderecos = []; // Cache para guardar o saldo físico

    // --- FUNÇÕES DE NOTIFICAÇÃO (Helpers) ---
    function notificacaoSucesso(titulo, mensagem) {
        Swal.fire({
            icon: 'success', title: titulo, text: mensagem,
            showConfirmButton: false, timer: 1500
        });
    }

    // --- FUNÇÃO PRINCIPAL DE CARREGAMENTO ---
    function loadDetalhesCarregamento() {
        if (!carregamentoId) { return; }
        $.ajax({
            url: 'ajax_router.php?action=getCarregamentoDetalhesCompletos',
            type: 'POST',
            data: {
                carregamento_id: carregamentoId,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    oeId = data.header.car_ordem_expedicao_id;
                    $('#oe_id_hidden').val(oeId);
                    gabaritoPlanejamento = data.planejamento || [];

                    // 1. Renderiza os componentes da tela
                    renderCabecalho(data.header);
                    renderPlanejamento(gabaritoPlanejamento);
                    renderExecucao(data.execucao, data.header.car_status); // Passa o status

                    // 2. Lógica para os botões GLOBAIS (que estão FORA da área de Execução)
                    if (data.header.car_status === 'FINALIZADO' || data.header.car_status === 'CANCELADO') {
                        $('#btn-adicionar-fila').hide();
                        $('#btn-editar-header').hide();
                        $('#btn-finalizar-detalhe').hide();

                        if ($('#btn-gerar-relatorio-link').length === 0) {
                            const btnRelatorio = `<a href="index.php?page=carregamento_relatorio&id=${carregamentoId}" id="btn-gerar-relatorio-link" class="btn btn-info btn-sm ms-2">
                            <i class="fas fa-print"></i> Imprimir Relatório</a>`;
                            $('#btn-editar-header').parent().prepend(btnRelatorio);
                        }
                    } else {
                        // Garante que os botões globais estejam visíveis para carregamentos em andamento
                        $('#btn-adicionar-fila').show();
                        $('#btn-editar-header').show();
                        $('#btn-finalizar-detalhe').show();
                        $('#btn-gerar-relatorio-link').remove();
                    }
                } else {
                    Swal.fire('Erro ao carregar', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro de Conexão', 'Não foi possível buscar os dados do carregamento.', 'error');
            }
        });
    }

    // --- RENDERIZAÇÃO DO CABEÇALHO ---
    function renderCabecalho(header) {
        dadosOriginaisHeader = header;
        $('#carregamento_id').val(header.car_id);
        $('#main-title').text(`Carregamento Nº: ${header.car_numero}`);
        $('#car_numero').val(header.car_numero);
        $('#car_data').val(header.car_data);
        $('#oe_numero_base').val(header.oe_numero || 'N/A');
        $('#oe_id_hidden').val(header.car_ordem_expedicao_id);
        setSelect2Value('#car_entidade_id_organizador', header.car_entidade_id_organizador, header.cliente_responsavel_nome);
        setSelect2Value('#car_transportadora_id', header.car_transportadora_id, header.transportadora_nome);
        $('#car_motorista_nome').val(header.car_motorista_nome || '');
        $('#car_motorista_cpf').mask('000.000.000-00');
        $('#car_motorista_cpf').val(header.car_motorista_cpf || '').trigger('input');
        $('#car_placas').val(header.car_placas || '');
        $('#car_lacres').val(header.car_lacres || '');
        $('#car_placas').mask('SSS-0A00 / SSS-0A00', {
            translation: { 'S': { pattern: /[A-Za-z]/ }, 'A': { pattern: /[A-Za-z0-9]/ } },
            onKeyPress: function (val, e, field, options) {
                field.val(val.toUpperCase());
                if (val.length === 8) {
                    if (val.charAt(7) !== ' ') {
                        let charExtra = val.charAt(7);
                        let newVal = val.substring(0, 7) + ' / ' + charExtra;
                        field.val(newVal);
                        field.mask('SSS-0A00 / SSS-0A00', options);
                    }
                }
            },
            clearIfNotMatch: true
        });
        if (header.car_status === 'EM ANDAMENTO') {
            $('#btn-finalizar-detalhe').show();
            $('#btn-editar-header').show();
        } else {
            $('#btn-finalizar-detalhe').hide();
            $('#btn-editar-header').hide();
        }
    }

    // --- RENDERIZAÇÃO DO PLANEJAMENTO (Gabarito OE) ---
    function renderPlanejamento(planejamento) {
        // 1. Limpa o corpo da tabela para evitar duplicatas
        $tabelaPlanejamentoBody.empty();

        // 2. Verifica se há itens para renderizar
        if (!planejamento || planejamento.length === 0) {
            $tabelaPlanejamentoBody.html('<tr><td colspan="7" class="text-center text-muted">Nenhum item encontrado na Ordem de Expedição base.</td></tr>');

            if ($.fn.DataTable.isDataTable($tabela)) {
                $tabela.DataTable().destroy();
            }

            // Inicializa a tabela vazia com responsividade
            $tabela.DataTable({
                responsive: true,
                paging: false,
                searching: false,
                info: false,
                ordering: false,
                language: {
                    emptyTable: "Nenhum item encontrado na Ordem de Expedição base.",
                    loadingRecords: "Carregando..."
                }
            });

            return;
        }

        // 3. Itera sobre cada item do planejamento e cria a linha da tabela
        planejamento.forEach(item => {
            // A lógica do back-end já nos envia a quantidade carregada,
            // então não precisamos recalcular. Apenas a usamos.
            const qtdCarregada = parseFloat(item.qtd_carregada);
            const qtdPlanejada = parseFloat(item.qtd_planejada);
            const saldo = qtdPlanejada - qtdCarregada;

            // Adiciona classes de cor para o saldo
            let saldoClass = 'text-danger';
            if (saldo === 0) {
                saldoClass = 'text-success'; // Saldo zero, 100% carregado
            } else if (saldo > 0) {
                saldoClass = 'text-primary'; // Saldo positivo, ainda falta carregar
            }

            const html = `
            <tr data-oe-item-id="${item.oei_id}">
                <td class="text-center align-middle font-small">${item.cliente_nome}</td>
                <td class="align-middle font-small">${item.prod_descricao}</td>
                <td class="text-center align-middle font-small">${item.lote_completo}</td>
                <td class="text-center align-middle font-small">${item.endereco_completo}</td>
                <td class="text-center align-middle font-small">${qtdPlanejada}</td>
                <td class="text-center align-middle font-small">${qtdCarregada}</td>
                <td class="text-center align-middle fw-bold ${saldoClass} font-small">${saldo}</td>
            </tr>
        `;
            $tabelaPlanejamentoBody.append(html);
        });

        // 4. Recria a DataTable com responsividade
        if ($.fn.DataTable.isDataTable($tabela)) {
            $tabela.DataTable().destroy();
        }

        $tabela.DataTable({
            responsive: true,
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            language: {
                emptyTable: "Nenhum item encontrado na Ordem de Expedição base.",
                loadingRecords: "Carregando..."
            }
        });
    }



    // --- RENDERIZAÇÃO DA EXECUÇÃO (Filas e Itens) ---
    /*   function renderExecucao(filas, statusCarregamento) {
           let html = '<div>';
           filas.forEach((fila, index) => {
               const clientesNaFila = {};
               if (fila.itens && fila.itens.length > 0) {
                   fila.itens.forEach(item => {
                       const clienteKey = item.car_item_cliente_id;
                       if (!clientesNaFila[clienteKey]) {
                           clientesNaFila[clienteKey] = { id: item.car_item_cliente_id, nome: item.cliente_nome, itens: [] };
                       }
                       clientesNaFila[clienteKey].itens.push(item);
                   });
               }
   
               let clientesHtml = '';
               for (const clienteId in clientesNaFila) {
                   const cliente = clientesNaFila[clienteId];
                   let itensHtml = '';
   
                   cliente.itens.forEach(item => {
                       const divergenciaBadge = item.motivo_divergencia ? `<span class="badge bg-danger" title="Divergência: ${item.motivo_divergencia}">D</span>` : '';
                       let itemAcoesHtml = '';
                       if (statusCarregamento === 'EM ANDAMENTO') {
                           itemAcoesHtml = `
                           <div class="btn-group btn-group-sm">
                               <button class="btn btn-warning btn-editar-item me-1" data-item-id="${item.car_item_id}" title="Editar"><i class="fas fa-edit"></i></button>
                               <button class="btn btn-danger btn-remover-item" data-item-id="${item.car_item_id}" title="Excluir"><i class="fas fa-trash"></i></button>
                           </div>`;
                       }
   
                       itensHtml += `
                       <tr>
                           <td style="width:10%" class="text-center align-middle">${item.prod_codigo_interno || ''}</td>
                           <td style="width:45%" class="align-middle">${item.prod_descricao} ${divergenciaBadge}</td>
                           <td style="width:10%" class="text-center align-middle">${item.lote_completo || ''}</td>
                           <td style="width:10%" class="text-center align-middle">${item.cliente_lote_nome || 'N/A'}</td>
                           <td style="width:10%" class="text-center align-middle">${item.endereco_completo || ''}</td>
                           <td style="width:10%" class="text-center align-middle"">${item.qtd_carregada}</td>
                           <td style="width:5%" class="text-center align-middle"">${itemAcoesHtml}</td>
                       </tr>`;
                   });
   
                   let clienteBotoesHtml = '';
                   if (statusCarregamento === 'EM ANDAMENTO') {
                       clienteBotoesHtml = `
                       <button class="btn btn-primary btn-sm btn-add-item-to-cliente" data-fila-id="${fila.fila_id}" data-cliente-id="${cliente.id}" data-cliente-nome="${cliente.nome}"><i class="fas fa-plus me-1"></i> Adicionar Item</button>
                       <button class="btn btn-danger btn-sm btn-remove-cliente-from-fila ms-2" data-fila-id="${fila.fila_id}" data-cliente-id="${cliente.id}"><i class="fas fa-trash me-1"></i> Excluir Cliente</button>`;
                   }
   
                   clientesHtml += `
                   <div class="card mb-2 shadow-sm">
                       <div class="card-header bg-white d-flex justify-content-between align-items-center">
                           <span class="toggle-btn fw-bold" data-target=".cliente-${fila.fila_id}-${cliente.id}"><i class="fas fa-plus-square"></i> Cliente: ${cliente.nome}</span>
                           <div>${clienteBotoesHtml}</div>
                       </div>
                       <div class="card-body cliente-${fila.fila_id}-${cliente.id}" style="display: none;">
                           <table class="table table-sm table-bordered table-striped mb-0 w-100 tabela-execucao" id="tabela-execucao-${fila.fila_id}-${cliente.id}">
                               <thead>
                                   <tr>
                                       <th style="width:10%" class="text-center align-middle">Cód. Interno</th>
                                       <th style="width:45%" class="text-center align-middle">Produto</th>
                                       <th style="width:10%" class="text-center align-middle">Lote</th>
                                       <th style="width:10%" class="text-center align-middle">Cliente do Lote</th>
                                       <th style="width:10%" class="text-center align-middle">Endereço</th>
                                       <th style="width:10%" class="text-center align-middle"">Qtd. Carregada</th>
                                       <th style="width:5%" class="text-center align-middle"">Ações</th>
                                   </tr>
                               </thead>
                               <tbody>${itensHtml}</tbody>
                           </table>
                       </div>
                   </div>`;
               }
   
               let filaBotoesHtml = '';
               if (statusCarregamento === 'EM ANDAMENTO') {
                   let removerFilaBtnHtml = '';
                   if (index === filas.length - 1) {
                       removerFilaBtnHtml = `<button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}"><i class="fas fa-trash me-1"></i> Excluir Fila</button>`;
                   } else {
                       removerFilaBtnHtml = `<button class="btn btn-danger btn-sm" disabled title="Remova as filas posteriores"><i class="fas fa-trash me-1"></i> Excluir Fila</button>`;
                   }
                   filaBotoesHtml = `
                               <button class="btn btn-info btn-sm btn-adicionar-item-fila me-2" data-fila-id="${fila.fila_id}"><i class="fas fa-plus me-1"></i> Adicionar Cliente</button>
                               <button class="btn btn-outline-primary btn-sm btn-adicionar-foto me-2" data-fila-id="${fila.fila_id}"><i class="fas fa-camera me-1"></i> Adicionar Foto</button>
                               <button class="btn btn-outline-secondary btn-sm btn-ver-fotos me-2" data-fila-id="${fila.fila_id}" data-fila-numero="${String(fila.fila_numero_sequencial || '1').padStart(2, '0')}"><i class="fas fa-images me-1"></i> Ver Fotos</button>
                   ${removerFilaBtnHtml}`;
               } else {
                   filaBotoesHtml = `<button class="btn btn-outline-secondary btn-sm btn-ver-fotos" data-fila-id="${fila.fila_id}" data-fila-numero="${String(fila.fila_numero_sequencial || '1').padStart(2, '0')}"><i class="fas fa-images me-1"></i> Ver Fotos</button>`;
               }
   
               html += `
               <div class="card mb-3 shadow-sm border-primary">
                   <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                       <span class="toggle-btn" data-target=".fila-${fila.fila_id}"><i class="fas fa-plus-square"></i> Fila ${String(fila.fila_numero_sequencial || '1').padStart(2, '0')}</span>
                       <div class="d-flex align-items-center">${filaBotoesHtml}</div>
                   </div>
                   <div class="card-body fila-${fila.fila_id}" style="display: none;">
                       ${clientesHtml || '<div class="text-center text-muted">Nenhum cliente/item nesta fila.</div>'}
                   </div>
               </div>`;
           });
           html += '</div>';
   
           $filasContainer.html(html);
   
           $('.toggle-btn').on('click', function () {
               const targetClass = $(this).data('target');
               const $content = $(targetClass);
               const $icon = $(this).find('i');
               if ($content.is(':visible')) {
                   $content.hide();
                   $icon.removeClass('fa-minus-square').addClass('fa-plus-square');
               } else {
                   $content.show();
                   $icon.removeClass('fa-plus-square').addClass('fa-minus-square');
               }
           });
   
           // Inicializa DataTables responsivo para cada tabela de cliente
           $('.tabela-execucao').each(function () {
               const $tabela = $(this);
               if ($.fn.DataTable.isDataTable($tabela)) {
                   $tabela.DataTable().destroy();
               }
               $tabela.DataTable({
                   responsive: true,
                   paging: false,
                   searching: false,
                   info: false,
                   ordering: false,
                   language: {
                       emptyTable: "Nenhum item nesta fila.",
                       loadingRecords: "Carregando..."
                   }
               });
           });
   
           // Lógica centralizada para habilitar/desabilitar os botões de Ação Principal
           let podeCriarNovaFila = (filas.length === 0);
           let podeFinalizar = false;
   
           if (filas.length > 0) {
               const ultimaFila = filas[filas.length - 1];
               const temItens = ultimaFila.itens && ultimaFila.itens.length > 0;
               const temFotos = ultimaFila.total_fotos > 0;
               const ultimaFilaCompleta = temItens && temFotos;
               podeCriarNovaFila = ultimaFilaCompleta;
               podeFinalizar = ultimaFilaCompleta;
           }
   
           $('#btn-adicionar-fila').prop('disabled', !podeCriarNovaFila)
               .attr('title', podeCriarNovaFila ?
                   'Adicionar uma nova fila de carregamento' :
                   'Adicione itens e pelo menos uma foto à última fila para poder criar uma nova.');
   
           $('#btn-finalizar-detalhe').prop('disabled', !podeFinalizar)
               .attr('title', podeFinalizar ?
                   'Finalizar o carregamento e baixar o estoque' :
                   'O carregamento não pode ser finalizado pois a última fila está incompleta (faltam itens ou foto).');
       } */


    function renderExecucao(filas, statusCarregamento) {
        let html = '<div>';
        filas.forEach((fila, index) => {
            const clientesNaFila = {};
            if (fila.itens && fila.itens.length > 0) {
                fila.itens.forEach(item => {
                    const clienteKey = item.car_item_cliente_id;
                    if (!clientesNaFila[clienteKey]) {
                        clientesNaFila[clienteKey] = {
                            id: item.car_item_cliente_id,
                            nome: item.cliente_nome,
                            itens: []
                        };
                    }
                    clientesNaFila[clienteKey].itens.push(item);
                });
            }

            let clientesHtml = '';
            for (const clienteId in clientesNaFila) {
                const cliente = clientesNaFila[clienteId];
                let itensHtml = '';

                cliente.itens.forEach(item => {
                    const divergenciaBadge = item.motivo_divergencia
                        ? `<span class="badge bg-danger" title="Divergência: ${item.motivo_divergencia}">D</span>` : '';
                    let itemAcoesHtml = '';
                    if (statusCarregamento === 'EM ANDAMENTO') {
                        itemAcoesHtml = `
                    <div class="d-inline-flex gap-1">
                        <button class="btn btn-warning btn-xs btn-editar-item" data-item-id="${item.car_item_id}" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-xs btn-remover-item" data-item-id="${item.car_item_id}" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>`;
                    }

                    itensHtml += `
                <tr>
                    <td class="text-center align-middle">${item.prod_codigo_interno || ''}</td>
                    <td class="align-middle">${item.prod_descricao} ${divergenciaBadge}</td>
                    <td class="text-center align-middle">${item.lote_completo || ''}</td>
                    <td class="text-center align-middle">${item.cliente_lote_nome || 'N/A'}</td>
                    <td class="text-center align-middle">${item.endereco_completo || ''}</td>
                    <td class="text-center align-middle">${item.qtd_carregada}</td>
                    <td class="text-center align-middle">${itemAcoesHtml}</td>
                </tr>`;
                });

                let clienteBotoesHtml = '';
                if (statusCarregamento === 'EM ANDAMENTO') {
                    clienteBotoesHtml = `
                <button class="btn btn-primary btn-sm btn-add-item-to-cliente" data-fila-id="${fila.fila_id}" data-cliente-id="${cliente.id}" data-cliente-nome="${cliente.nome}">
                    <i class="fas fa-plus me-1"></i> Adicionar Item
                </button>
                <button class="btn btn-danger btn-sm btn-remove-cliente-from-fila ms-2" data-fila-id="${fila.fila_id}" data-cliente-id="${cliente.id}">
                    <i class="fas fa-trash me-1"></i> Excluir Cliente
                </button>`;
                }

                clientesHtml += `
            <div class="card mb-2 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="toggle-btn fw-bold" data-target=".cliente-${fila.fila_id}-${cliente.id}">
                        <i class="fas fa-plus-square"></i> Cliente: ${cliente.nome}
                    </span>
                    <div>${clienteBotoesHtml}</div>
                </div>
                <div class="card-body cliente-${fila.fila_id}-${cliente.id}" style="display: none;">
                    <table class="table table-sm table-bordered table-striped w-100 tabela-execucao" id="tabela-execucao-${fila.fila_id}-${cliente.id}">
                        <thead>
                            <tr>
                                <th class="text-center align-middle">Cód. Interno</th>
                                <th class="text-center align-middle">Produto</th>
                                <th class="text-center align-middle">Lote</th>
                                <th class="text-center align-middle">Cliente do Lote</th>
                                <th class="text-center align-middle">Endereço</th>
                                <th class="text-center align-middle">Qtd. Carregada</th>
                                <th class="text-center align-middle">Ações</th>
                            </tr>
                        </thead>
                        <tbody>${itensHtml}</tbody>
                    </table>
                </div>
            </div>`;
            }

            let filaBotoesHtml = '';
            if (statusCarregamento === 'EM ANDAMENTO') {
                const ultimaFila = index === filas.length - 1;
                filaBotoesHtml = `
                <button class="btn btn-info btn-sm btn-adicionar-item-fila me-2" data-fila-id="${fila.fila_id}">
                    <i class="fas fa-plus me-1"></i> Adicionar Cliente
                </button>
                <button class="btn btn-outline-primary btn-sm btn-adicionar-foto me-2" data-fila-id="${fila.fila_id}">
                    <i class="fas fa-camera me-1"></i> Adicionar Foto
                </button>
                <button class="btn btn-outline-secondary btn-sm btn-ver-fotos me-2" data-fila-id="${fila.fila_id}" data-fila-numero="${String(fila.fila_numero_sequencial || '1').padStart(2, '0')}">
                    <i class="fas fa-images me-1"></i> Ver Fotos
                </button>
                ${ultimaFila
                        ? `<button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}">
                        <i class="fas fa-trash me-1"></i> Excluir Fila
                    </button>`
                        : `<button class="btn btn-danger btn-sm" disabled title="Remova as filas posteriores">
                        <i class="fas fa-trash me-1"></i> Excluir Fila
                    </button>`}`;
            } else {
                filaBotoesHtml = `
                <button class="btn btn-outline-secondary btn-sm btn-ver-fotos" data-fila-id="${fila.fila_id}" data-fila-numero="${String(fila.fila_numero_sequencial || '1').padStart(2, '0')}">
                    <i class="fas fa-images me-1"></i> Ver Fotos
                </button>`;
            }

            html += `
        <div class="card mb-3 shadow-sm border-primary">
            <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                <span class="toggle-btn" data-target=".fila-${fila.fila_id}">
                    <i class="fas fa-plus-square"></i> Fila ${String(fila.fila_numero_sequencial || '1').padStart(2, '0')}
                </span>
                <div class="d-flex align-items-center">${filaBotoesHtml}</div>
            </div>
            <div class="card-body fila-${fila.fila_id}" style="display: none;">
                ${clientesHtml || '<div class="text-center text-muted">Nenhum cliente/item nesta fila.</div>'}
            </div>
        </div>`;
        });
        html += '</div>';

        $filasContainer.html(html);

        // Toggle de colapso
        /*  $('.toggle-btn').on('click', function () {
              const targetClass = $(this).data('target');
              const $content = $(targetClass);
              const $icon = $(this).find('i');
              if ($content.is(':visible')) {
                  $content.hide();
                  $icon.removeClass('fa-minus-square').addClass('fa-plus-square');
              } else {
                  $content.show();
                  $icon.removeClass('fa-plus-square').addClass('fa-minus-square');
              }
          }); */


        $('.toggle-btn').on('click', function () {
            const targetClass = $(this).data('target');
            const $content = $(targetClass);
            const $icon = $(this).find('i');

            if ($content.is(':visible')) {
                $content.hide();
                $icon.removeClass('fa-minus-square').addClass('fa-plus-square');
            } else {
                $content.show();
                $icon.removeClass('fa-plus-square').addClass('fa-minus-square');

                // Aguarda a transição visual e ajusta o DataTables
                setTimeout(() => {
                    $content.find('.tabela-execucao').each(function () {
                        const table = $(this).DataTable();
                        table.columns.adjust().responsive.recalc();
                    });
                }, 150); // tempo suficiente para o DOM aplicar o display
            }
        });


        // Inicializa DataTables responsivo
        $('.tabela-execucao').each(function () {
            const $tabela = $(this);
            if ($.fn.DataTable.isDataTable($tabela)) {
                $tabela.DataTable().destroy();
            }
            $tabela.DataTable({
                responsive: true,
                paging: false,
                searching: false,
                info: false,
                ordering: false,
                language: {
                    emptyTable: "Nenhum item nesta fila.",
                    loadingRecords: "Carregando..."
                }
            });
        });

        // Botões principais
        let podeCriarNovaFila = (filas.length === 0);
        let podeFinalizar = false;

        if (filas.length > 0) {
            const ultimaFila = filas[filas.length - 1];
            const temItens = ultimaFila.itens && ultimaFila.itens.length > 0;
            const temFotos = ultimaFila.total_fotos > 0;
            const ultimaFilaCompleta = temItens && temFotos;
            podeCriarNovaFila = ultimaFilaCompleta;
            podeFinalizar = ultimaFilaCompleta;
        }

        $('#btn-adicionar-fila')
            .prop('disabled', !podeCriarNovaFila)
            .attr('title', podeCriarNovaFila
                ? 'Adicionar uma nova fila de carregamento'
                : 'Adicione itens e pelo menos uma foto à última fila para poder criar uma nova.');

        $('#btn-finalizar-detalhe')
            .prop('disabled', !podeFinalizar)
            .attr('title', podeFinalizar
                ? 'Finalizar o carregamento e baixar o estoque'
                : 'O carregamento não pode ser finalizado pois a última fila está incompleta (faltam itens ou foto).');
    }





    // --- LÓGICA DO NOVO MODAL UNIFICADO ---
    function inicializarLogicaModalUnificado() {

        // 1. Inicializa os Select2 do modal
        $itemCliente.select2({
            placeholder: "Selecione um cliente...",
            dropdownParent: $modalAddItem, theme: "bootstrap-5",
            ajax: {
                url: 'ajax_router.php?action=getClienteOptions',
                dataType: 'json', delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data.data }; }
            }
        });

        $itemProduto.select2({
            placeholder: "Selecione um produto...",
            dropdownParent: $modalAddItem, theme: "bootstrap-5",
            ajax: {
                url: "ajax_router.php?action=getProdutosComEstoqueDisponivel",
                dataType: 'json', delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data.results }; },
            }
        });

        $itemLote.select2({ placeholder: "Selecione um lote...", dropdownParent: $modalAddItem, theme: "bootstrap-5" });
        $itemAlocacao.select2({ placeholder: "Selecione um endereço...", dropdownParent: $modalAddItem, theme: "bootstrap-5" });

        // 2a. Evento de Abertura do Modal (GERAL - Pela Fila)
        $filasContainer.on('click', '.btn-adicionar-item-fila', function () {
            const filaId = $(this).data('fila-id');
            $formAddItem[0].reset();
            $('#item_fila_id').val(filaId);

            $itemCliente.val(null).trigger('change');
            $itemProduto.val(null).trigger('change').prop('disabled', true);
            $itemLote.empty().append('<option value=""></option>').prop('disabled', true);
            $itemAlocacao.empty().append('<option value=""></option>').prop('disabled', true);

            checkAgainstGabarito();
            $modalAddItem.modal('show');
        });

        // 2b. Evento de Abertura do Modal (ESPECÍFICO - Pelo Cliente)
        $filasContainer.on('click', '.btn-add-item-to-cliente', function () {
            const filaId = $(this).data('fila-id');
            const clienteId = $(this).data('cliente-id');
            const clienteNome = $(this).data('cliente-nome');

            $formAddItem[0].reset();
            $('#item_fila_id').val(filaId);

            $itemProduto.val(null).trigger('change').prop('disabled', false);
            $itemLote.empty().append('<option value=""></option>').prop('disabled', true);
            $itemAlocacao.empty().append('<option value=""></option>').prop('disabled', true);
            checkAgainstGabarito();

            if ($itemCliente.find("option[value='" + clienteId + "']").length === 0) {
                $itemCliente.append(new Option(clienteNome, clienteId, true, true));
            }
            $itemCliente.val(clienteId).trigger('change').prop('disabled', true);

            $modalAddItem.modal('show');
        });

        // 2c. Evento de Fechamento do Modal
        $modalAddItem.on('hidden.bs.modal', function () {
            $itemCliente.prop('disabled', false); // Sempre re-habilita o cliente ao fechar
        });

        // 3. Lógica Cascata
        $itemCliente.on('change', function () {
            if ($(this).val()) { $itemProduto.prop('disabled', false); }
            else { $itemProduto.val(null).trigger('change').prop('disabled', true); }
            checkAgainstGabarito();
        });

        $itemProduto.on('change', function () {
            const produtoId = $(this).val();
            $itemLote.val(null).trigger('change');
            if (produtoId) {
                $itemLote.prop('disabled', false);
                $.getJSON(`ajax_router.php?action=getLotesParaCarregamentoPorProduto&produto_id=${produtoId}`, function (data) {

                    $itemLote.empty().append('<option value=""></option>');
                    if (data.results) {
                        data.results.forEach(lote => $itemLote.append(new Option(lote.text, lote.id)));
                    }
                });
            } else {
                $itemLote.prop('disabled', true).empty().append('<option value=""></option>');
            }
            checkAgainstGabarito();
        });

        $itemLote.on('change', function () {
            const loteId = $(this).val();
            const produtoId = $itemProduto.val(); // <-- Pega o ID do produto já selecionado

            $itemAlocacao.val(null).trigger('change');

            if (loteId && produtoId) { // <-- Verifica se tem os dois
                $itemAlocacao.prop('disabled', false);

                // ### A URL agora envia os dois IDs ###
                $.getJSON(`ajax_router.php?action=getEnderecosParaCarregamentoPorLoteItem&lote_id=${loteId}&produto_id=${produtoId}`, function (data) {
                    $itemAlocacao.empty().append('<option value=""></option>');
                    dadosEnderecos = data.results || [];
                    if (dadosEnderecos.length > 0) {
                        dadosEnderecos.forEach(end => {
                            const option = new Option(end.text, end.id);
                            $(option).data('saldo_disponivel', end.saldo_disponivel);
                            $(option).data('saldo_fisico', end.saldo_fisico);
                            $itemAlocacao.append(option);
                        });
                    }
                });
            } else {
                $itemAlocacao.prop('disabled', true).empty().append('<option value=""></option>');
                dadosEnderecos = [];
            }
            checkAgainstGabarito();
        });

        $itemAlocacao.on('change', function () {
            // Agora que o 'saldo_disponivel' está no 'data' da option
            const saldoFisico = $(this).find('option:selected').data('saldo_disponivel') || 0;
            checkAgainstGabarito();
        });

        // 4. Função que verifica o Gabarito (OE)
        // 4. Função que verifica o Gabarito (OE)
        function checkAgainstGabarito() {
            const clienteId = $itemCliente.val();
            const produtoId = $itemProduto.val();
            const alocacaoId = $itemAlocacao.val();

            // Reseta o estado
            $itemQtd.prop('disabled', true).val('');
            $itemSaldo.val('');
            $itemHelper.text('');
            $itemMotivoContainer.hide();
            $itemMotivoInput.prop('required', false);
            $itemOeiId.val('');
            $itemBtnAdd.prop('disabled', true);
            $itemQtd.removeClass('is-invalid');

            if (!clienteId || !produtoId || !alocacaoId) {
                return;
            }

            const itemGabarito = gabaritoPlanejamento.find(item =>
                item.oep_cliente_id == clienteId &&
                item.oei_alocacao_id == alocacaoId
            );

            const $opcaoEndereco = $itemAlocacao.find('option:selected');
            const saldoFisico = parseFloat($opcaoEndereco.data('saldo_fisico')) || 0;

            let saldoPlano = 0;
            let oeiId = '';

            if (itemGabarito) {
                saldoPlano = parseFloat(itemGabarito.qtd_planejada) - parseFloat(itemGabarito.qtd_carregada);
                oeiId = itemGabarito.oei_id;
            }

            // A linha abaixo já está correta, mas vamos reforçar a sua importância.
            $itemSaldo.val(saldoPlano.toFixed(0));

            // Agora, definimos o valor de oei_id para o campo oculto,
            // que será enviado para o backend.
            $itemOeiId.val(oeiId);
            $itemQtd.val(1).prop('disabled', false);

            $itemQtd.off('input').on('input', function () {
                const qtdInserida = parseFloat($(this).val());
                let needsMotivo = false;
                let helperText = '';
                let helperColor = 'black';
                let isQtdInvalid = false;

                // A. Lógica para determinar o tipo de divergência
                if (!itemGabarito) {
                    // Divergência de item: não está na OE
                    needsMotivo = true;
                    helperText = 'DIVERGÊNCIA: Item não planejado na OE. Motivo é obrigatório.';
                    helperColor = 'orange';
                } else if (qtdInserida > saldoPlano) {
                    // Divergência de quantidade: excede o plano da OE
                    // Esta é a parte que foi corrigida na lógica
                    needsMotivo = true;
                    helperText = `ATENÇÃO: Quantidade (${qtdInserida}) excede o plano da OE (${saldoPlano.toFixed(0)}). Motivo da divergência obrigatório.`;
                    helperColor = 'orange';
                } else {
                    // Item conforme a OE
                    helperText = `Item conforme a OE. Saldo no plano: ${saldoPlano.toFixed(0)}`;
                    helperColor = 'green';
                }

                // B. Validação da quantidade em relação ao saldo FÍSICO
                if (qtdInserida > saldoFisico && saldoFisico > 0) {
                    // Se a quantidade excede o saldo FÍSICO, isso também é uma divergência
                    needsMotivo = true;
                    helperText = `ATENÇÃO: Quantidade (${qtdInserida}) excede o saldo físico (${saldoFisico.toFixed(0)}). Motivo da divergência obrigatório.`;
                    helperColor = 'red';
                }

                // C. Verificação de quantidade inválida (menor ou igual a zero)
                if (isNaN(qtdInserida) || qtdInserida <= 0) {
                    isQtdInvalid = true;
                    helperText = 'Erro: A quantidade deve ser maior que zero.';
                    helperColor = 'red';
                }

                // D. Atualização dos elementos da interface
                $itemHelper.text(helperText).css('color', helperColor);
                $itemMotivoContainer.toggle(needsMotivo);
                $itemMotivoInput.prop('required', needsMotivo);

                // E. Habilitação/Desabilitação do botão de salvar
                const isMotivoValid = !needsMotivo || $itemMotivoInput.val().trim() !== '';
                const isButtonEnabled = !isQtdInvalid && isMotivoValid;

                if (isButtonEnabled) {
                    $itemBtnAdd.prop('disabled', false);
                    $itemQtd.removeClass('is-invalid');
                } else {
                    $itemBtnAdd.prop('disabled', true);
                    $itemQtd.toggleClass('is-invalid', isQtdInvalid);
                }
            });

            // Aciona o evento de 'input' e também adiciona um evento para quando o motivo muda
            $itemQtd.trigger('input');
            $itemMotivoInput.on('input', function () {
                $itemQtd.trigger('input'); // Reavalia a lógica ao digitar o motivo
            });

            // NOVO CÓDIGO AQUI
            // Se o motivo de divergência está visível, o item é uma divergência.
            // Se o item é uma divergência TOTAL, o oei_id_origem deve ser NULL.
            // Se o item é uma divergência de QUANTIDADE, o oei_id_origem deve ser o ID do item da OE.
            $formAddItem.on('submit', function (e) {
                e.preventDefault();
                const $campoQtd = $('#item_quantidade');
                const qtd = parseFloat($campoQtd.val());

                if (isNaN(qtd) || qtd <= 0) {
                    Swal.fire('Erro', 'A quantidade deve ser maior que zero.', 'error');
                    return;
                }

                if ($itemMotivoContainer.is(':visible') && $itemMotivoInput.val().trim() === '') {
                    Swal.fire('Erro', 'O motivo da divergência é obrigatório.', 'error');
                    $itemMotivoInput.focus();
                    return;
                }

                const isDivergenciaTotal = !itemGabarito;
                if (isDivergenciaTotal) {
                    $itemOeiId.val(''); // Limpa o valor para que o back-end o interprete como null
                }

                // Se for divergência de quantidade, o ID da OE de origem já está no campo oculto
                // e será enviado para o back-end.

                // Re-habilita o cliente ANTES de serializar, caso ele esteja desabilitado
                $itemCliente.prop('disabled', false);

                $.ajax({
                    url: 'ajax_router.php?action=addItemCarregamento',
                    type: 'POST',
                    data: $(this).serialize() + '&carregamento_id=' + carregamentoId,
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $modalAddItem.modal('hide');
                            notificacaoSucesso('Sucesso!', 'Item adicionado ao carregamento.');
                            loadDetalhesCarregamento();
                        } else {
                            Swal.fire('Erro ao adicionar', response.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Erro de Conexão', 'Não foi possível salvar o item.', 'error');
                    }
                });
            });
        }
    }

    // Helper: setSelect2Value
    function setSelect2Value(selector, id, text) {
        const $select = $(selector);
        if (id && text) {
            $select.empty().append(new Option(text, id, true, true)).trigger('change');
        } else {
            $select.empty().trigger('change');
        }
    }

    // Lógica para Editar/Salvar Cabeçalho
    function toggleHeaderEdit(isEditing) {
        if (isEditing) {
            $inputsHeader.filter(':not(#oe_numero_base)').prop('readonly', false).prop('disabled', false);
            initSelect2Header('#car_entidade_id_organizador', 'getClienteOptions');
            initSelect2Header('#car_transportadora_id', 'getTransportadoraOptions');
            $btnEditarHeader.hide(); $btnSalvarHeader.show(); $btnCancelarHeader.show();
        } else {
            $inputsHeader.prop('readonly', true).prop('disabled', true);
            $btnEditarHeader.show(); $btnSalvarHeader.hide(); $btnCancelarHeader.hide();
            renderCabecalho(dadosOriginaisHeader);
        }
    }

    function initSelect2Header(selector, action) {
        $(selector).select2({
            placeholder: "Selecione...", theme: "bootstrap-5",
            ajax: {
                url: `ajax_router.php?action=${action}`,
                dataType: 'json', delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data.data || data.results }; }
            }
        });
    }

    $btnEditarHeader.on('click', () => toggleHeaderEdit(true));
    $btnCancelarHeader.on('click', () => toggleHeaderEdit(false));

    $btnSalvarHeader.on('click', function () {
        $formHeader.trigger('submit'); // Ou $formHeader.submit();
    });

    // Submit do Cabeçalho
    $formHeader.on('submit', function (e) {
        e.preventDefault();
        const $cpfField = $('#car_motorista_cpf');
        const cpfLimpo = $cpfField.cleanVal ? $cpfField.cleanVal() : $cpfField.val().replace(/\D/g, '');
        const formData = $(this).serialize().replace(/car_motorista_cpf=[^&]*/, '') + '&car_motorista_cpf=' + cpfLimpo;

        $.ajax({
            url: 'ajax_router.php?action=updateCarregamentoHeader',
            type: 'POST', data: formData, dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire('Sucesso!', 'Cabeçalho atualizado.', 'success');
                    toggleHeaderEdit(false);
                    loadDetalhesCarregamento();
                } else { Swal.fire('Erro', response.message, 'error'); }
            },
            error: function () { Swal.fire('Erro', 'Não foi possível conectar ao servidor.', 'error'); }
        });
    });

    // Remover Fila
    $filasContainer.on('click', '.btn-remover-fila', function () {
        const filaId = $(this).data('fila-id');
        Swal.fire({
            title: 'Tem certeza?', text: "Deseja remover esta fila e TODOS os itens dentro dela?",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=removeFilaCarregamento', {
                    fila_id: filaId,
                    csrf_token: csrfToken
                },
                    function (response) {
                        if (response.success) { loadDetalhesCarregamento(); }
                        else { Swal.fire('Erro', response.message, 'error'); }
                    }, 'json');
            }
        });
    });

    // Remover Cliente da Fila
    $filasContainer.on('click', '.btn-remove-cliente-from-fila', function () {
        const $button = $(this);
        const filaId = $button.data('fila-id');
        const clienteId = $button.data('cliente-id');
        Swal.fire({
            title: 'Remover Cliente da Fila?', text: "Todos os itens deste cliente serão removidos desta fila. Deseja continuar?",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=removeClienteFromFila', {
                    fila_id: filaId, cliente_id: clienteId, csrf_token: csrfToken
                }, function (response) {
                    if (response.success) {
                        notificacaoSucesso('Removido!', 'Cliente e seus itens removidos da fila.');

                        // Nova lógica: Verifica se era o último cliente da fila e se a fila é a última (para auto-remover a fila vazia)
                        const numClients = $button.closest('.card-body').find('.card.mb-2').length;
                        const $btnRemoverFila = $button.closest('.card.mb-3').find('.btn-remover-fila');

                        if (numClients === 1 && !$btnRemoverFila.prop('disabled')) {
                            // Auto-remover a fila pois ficou vazia e é a última
                            $.post('ajax_router.php?action=removeFilaCarregamento', {
                                fila_id: filaId,
                                csrf_token: csrfToken
                            }, function (remResponse) {
                                if (remResponse.success) {
                                    notificacaoSucesso('Fila removida', 'A fila foi removida automaticamente pois ficou vazia.');
                                } else {
                                    Swal.fire('Erro', remResponse.message, 'error');
                                }
                                loadDetalhesCarregamento();
                            }, 'json');
                        } else {
                            loadDetalhesCarregamento();
                        }
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // Remover Item (de uma fila)
    $filasContainer.on('click', '.btn-remover-item', function () {
        const itemId = $(this).data('item-id');
        Swal.fire({
            title: 'Tem certeza?', text: "Deseja remover este item da fila?",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=removeItemCarregamento', { car_item_id: itemId, csrf_token: csrfToken }, function (response) {
                    if (response.success) { loadDetalhesCarregamento(); }
                    else { Swal.fire('Erro', response.message, 'error'); }
                }, 'json');
            }
        });
    });

    // Editar Item (Abrir Modal)
    $filasContainer.on('click', '.btn-editar-item', function () {
        const itemId = $(this).data('item-id');
        const $modal = $('#modal-editar-item');
        $modal.find('form')[0].reset();
        $modal.find('#edit-produto-nome').text('Carregando...');
        $modal.find('#edit-lote-endereco').text('Carregando...');
        $modal.find('#edit_quantidade').val('').prop('disabled', true);
        $modal.find('#edit-saldo-info').text('');
        $modal.find('#edit_quantidade').removeClass('is-invalid');
        $.post('ajax_router.php?action=getCarregamentoItemDetalhes', {
            car_item_id: itemId, csrf_token: csrfToken
        }, function (response) {
            if (response.success) {
                const item = response.data;
                $modal.find('#edit_car_item_id').val(item.car_item_id);
                $modal.find('#edit-produto-nome').text(item.prod_descricao);
                $modal.find('#edit-lote-endereco').text(item.lote_endereco);
                $modal.find('#edit_quantidade').val(item.qtd_carregada).prop('disabled', false);
                $modal.find('#edit_quantidade').attr('max', item.max_quantidade_disponivel);
                $modal.find('#edit-saldo-info').text(`Disponível (OE/Físico): ${item.max_quantidade_disponivel} caixas`);
                $modal.modal('show');
            } else { Swal.fire('Erro', response.message, 'error'); }
        }, 'json');
    });

    // Editar Item (Salvar)
    $('#form-editar-item').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $inputQtd = $form.find('#edit_quantidade');
        const quantidade = parseFloat($inputQtd.val());
        const maximo = parseFloat($inputQtd.attr('max'));
        if (isNaN(quantidade) || quantidade <= 0) {
            $inputQtd.addClass('is-invalid').siblings('.invalid-feedback').text('A quantidade deve ser maior que zero.');
            return;
        }
        if (quantidade > maximo) {
            $inputQtd.addClass('is-invalid').siblings('.invalid-feedback').text('A quantidade excede o saldo disponível.');
            return;
        }
        $.ajax({
            url: 'ajax_router.php?action=updateCarregamentoItemQuantidade',
            type: 'POST', data: $form.serialize(), dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#modal-editar-item').modal('hide');
                    notificacaoSucesso('Sucesso!', 'Quantidade do item atualizada.');
                    loadDetalhesCarregamento();
                } else { Swal.fire('Erro', response.message, 'error'); }
            },
            error: function () { Swal.fire('Erro', 'Não foi possível conectar ao servidor.', 'error'); }
        });
    });

    // Adicionar Fila
    $('#btn-adicionar-fila').on('click', function () {
        $.post('ajax_router.php?action=addFilaCarregamento', {
            carregamento_id: carregamentoId,
            csrf_token: csrfToken
        },
            function (response) {
                if (response.success) { loadDetalhesCarregamento(); }
                else { Swal.fire('Erro', response.message, 'error'); }
            }, 'json');
    });

    // Cache do novo modal de visualização
    const $modalVerFotos = $('#modal-visualizar-fotos');
    const $previewContainer = $('#fotos-preview-container');

    $filasContainer.on('click', '.btn-ver-fotos', function () {
        const filaId = $(this).data('fila-id');
        const filaNumero = $(this).data('fila-numero');

        // 1. Limpa o conteúdo antigo e ajusta o título do modal
        $previewContainer.empty();
        $modalVerFotos.find('.modal-title').text(`Fotos da Fila ${filaNumero}`);

        // 2. Busca as fotos no backend (isso não muda)
        $.getJSON('ajax_router.php?action=getFotosDaFila', { fila_id: filaId }, function (response) {
            if (response.success && response.data && response.data.length > 0) {

                // 3. Loop para criar as miniaturas (previews)
                response.data.forEach((foto, index) => {
                    // Para cada foto, criamos um link <a> que envolve uma imagem <img>
                    // O <a> é o que o Lightbox usa para o "zoom"
                    // O <img> é a miniatura que o usuário vê
                    const thumbnailHtml = `
                        <div class="col-lg-3 col-md-4 col-6 mb-3">
                            <a href="${foto.full_url}" data-lightbox="fila-${filaId}" data-title="Foto ${index + 1} - Fila ${filaNumero}">
                                <img src="${foto.full_url}" class="img-fluid img-thumbnail" alt="Foto ${index + 1}">
                            </a>
                        </div>
                    `;
                    $previewContainer.append(thumbnailHtml);
                });

                // 4. Abre o modal com as miniaturas
                $modalVerFotos.modal('show');

            } else {
                Swal.fire('Sem Fotos', 'Nenhuma foto foi encontrada para esta fila.', 'info');
            }
        }).fail(function () {
            Swal.fire('Erro', 'Não foi possível buscar as fotos.', 'error');
        });
    });

    // Cache do novo modal
    const $modalAddFoto = $('#modal-adicionar-foto');
    const $formAddFoto = $('#form-adicionar-foto');
    const $fotoPreview = $('#foto-preview');
    const $fotoPreviewContainer = $('#foto-preview-container');

    // Handler para ABRIR o modal de Adicionar Foto
    $filasContainer.on('click', '.btn-adicionar-foto', function () {
        const filaId = $(this).data('fila-id');

        $formAddFoto[0].reset(); // Limpa o formulário
        $('#foto_fila_id').val(filaId); // Define o ID da fila no campo oculto
        $fotoPreview.attr('src', '#'); // Limpa o preview
        $fotoPreviewContainer.hide(); // Esconde o preview

        $modalAddFoto.modal('show');
    });

    // Handler para mostrar o PREVIEW da imagem selecionada
    $('#foto_upload').on('change', function () {
        // Limpa qualquer preview antigo
        $fotoPreviewContainer.empty().hide();

        if (this.files && this.files.length > 0) {
            // Loop através de todos os arquivos selecionados
            Array.from(this.files).forEach(file => {
                const reader = new FileReader();

                reader.onload = function (e) {
                    // Cria um elemento <img> para cada foto e o adiciona ao container
                    const img = $('<img>').attr('src', e.target.result)
                        .addClass('img-fluid rounded mb-2');
                    $fotoPreviewContainer.append(img);
                }

                reader.readAsDataURL(file); // Lê o arquivo para gerar o preview
            });

            $fotoPreviewContainer.show(); // Mostra o container com os previews
        }
    });

    // Handler para o SUBMIT do formulário de foto (Upload)
    $formAddFoto.on('submit', function (e) {
        e.preventDefault();

        // Usamos FormData para enviar arquivos via AJAX
        const formData = new FormData(this);

        $.ajax({
            url: 'ajax_router.php?action=addFotoFila', // <-- Nova Rota
            type: 'POST',
            data: formData,
            dataType: 'json',
            contentType: false, // Necessário para FormData
            processData: false, // Necessário para FormData
            success: function (response) {
                if (response.success) {
                    $modalAddFoto.modal('hide');
                    notificacaoSucesso('Sucesso!', response.message);
                    // Recarrega todos os dados do carregamento para reavaliar o botão "Adicionar Fila"
                    loadDetalhesCarregamento();
                    // Futuramente, podemos atualizar um contador de fotos no botão
                } else {
                    Swal.fire('Erro no Upload', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro de Conexão', 'Não foi possível enviar a foto.', 'error');
            }
        });
    });

    const $modalConferencia = $('#modal-conferencia-finalizacao');

    // Evento para ABRIR o modal de conferência
    $('#btn-finalizar-detalhe').on('click', function () {
        const container = $('#resumo-finalizacao-container');
        container.html('<p class="text-center">Gerando resumo de conferência...</p>');
        $modalConferencia.modal('show');

        $.post('ajax_router.php?action=getResumoParaFinalizar', { carregamento_id: carregamentoId, csrf_token: csrfToken }, function (response) {
            if (response.success && response.data) {
                if (response.data.length === 0) {
                    container.html('<div class="alert alert-success">Nenhuma divergência encontrada. O carregamento corresponde ao plano.</div>');
                } else {
                    let table = '<table class="table table-sm table-bordered"><thead><tr class="table-light"><th>Status</th><th>Produto</th><th>Lote</th><th>Endereço</th><th>Qtd. Planejada</th><th>Qtd. Carregada</th></tr></thead><tbody>';
                    response.data.forEach(item => {
                        let statusClass = '';
                        if (item.status_divergencia === 'NÃO CARREGADO') statusClass = 'table-danger';
                        if (item.status_divergencia === 'ITEM NÃO PLANEJADO') statusClass = 'table-warning';
                        if (item.status_divergencia === 'QUANTIDADE DIVERGENTE') statusClass = 'table-info';

                        table += `<tr class="${statusClass}">
                                    <td>${item.status_divergencia}</td>
                                    <td>${item.prod_descricao || item.produto_descricao}</td>
                                    <td>${item.lote_completo || item.lote_completo_calculado}</td>
                                    <td>${item.endereco_completo}</td>
                                    <td class="text-end">${item.qtd_planejada || 'N/A'}</td>
                                    <td class="text-end">${item.qtd_carregada || 'N/A'}</td>
                                  </tr>`;
                    });
                    table += '</tbody></table>';
                    container.html(table);
                }
            }
        }, 'json');
    });

    // Evento para o clique no botão de CONFIRMAÇÃO DENTRO DO MODAL
    $('#btn-confirmar-finalizacao-real').on('click', function () {
        $(this).prop('disabled', true).text('Finalizando...');
        $.post('ajax_router.php?action=finalizarCarregamento', { carregamento_id: carregamentoId, csrf_token: csrfToken }, function (response) {
            if (response.success) {
                $modalConferencia.modal('hide');
                Swal.fire('Finalizado!', 'Carregamento finalizado com sucesso.', 'success').then(() => {
                    loadDetalhesCarregamento();
                });
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
            $('#btn-confirmar-finalizacao-real').prop('disabled', false).text('Confirmar e Finalizar');
        }, 'json');
    });

    // --- INICIALIZAÇÃO ---
    loadDetalhesCarregamento(); // Carrega os dados da página
    inicializarLogicaModalUnificado(); // Prepara o novo modal
});