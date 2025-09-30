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
            data: { carregamento_id: carregamentoId, csrf_token: csrfToken },
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
        // (Esta função está correta, cole a sua versão anterior aqui)
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
        //$('#car_motorista_cpf').mask('000.000.000-00');
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
        // (Esta função está correta, cole a sua versão anterior aqui)
        $tabelaPlanejamentoBody.empty();
        if (!planejamento || planejamento.length === 0) {
            $tabelaPlanejamentoBody.html('<tr><td colspan="7" class="text-center text-muted">Nenhum item encontrado na Ordem de Expedição base.</td></tr>');
            return;
        }
        planejamento.forEach(item => {
            const saldo = parseFloat(item.qtd_planejada) - parseFloat(item.qtd_carregada);
            let saldoClass = '';
            if (saldo === 0) { saldoClass = 'text-success'; }
            else if (saldo < 0) { saldoClass = 'text-danger'; }
            else { saldoClass = 'text-primary'; }
            const html = `
                <tr data-oe-item-id="${item.oei_id}">
                    <td>${item.cliente_nome}</td>
                    <td>${item.prod_descricao}</td>
                    <td>${item.lote_completo}</td>
                    <td>${item.endereco_completo}</td>
                    <td class="text-end">${item.qtd_planejada}</td>
                    <td class="text-end">${item.qtd_carregada}</td>
                    <td class="text-end fw-bold ${saldoClass}">${saldo}</td>
                </tr>
            `;
            $tabelaPlanejamentoBody.append(html);
        });
    }

    // --- RENDERIZAÇÃO DA EXECUÇÃO (Filas e Itens) ---
    /*   function renderExecucao(filas) {
           $filasContainer.empty();
           if (!filas || filas.length === 0) {
               $filasContainer.html('<p class="text-center text-muted">Nenhuma fila adicionada a este carregamento.</p>');
               return;
           }
   
           let html = '<div>';
           filas.forEach((fila, index) => {
               const clientesNaFila = {};
               if (fila.itens && fila.itens.length > 0) {
                   fila.itens.forEach(item => {
                       if (!clientesNaFila[item.car_item_cliente_id]) {
                           clientesNaFila[item.car_item_cliente_id] = { nome: item.cliente_nome, itens: [] };
                       }
                       clientesNaFila[item.car_item_cliente_id].itens.push(item);
                   });
               }
   
               let clientesHtml = '';
               for (const clienteId in clientesNaFila) {
                   const cliente = clientesNaFila[clienteId];
                   let itensHtml = '';
                   cliente.itens.forEach(item => {
                       const divergenciaBadge = item.motivo_divergencia
                           ? `<span class="badge bg-danger" title="Divergência: ${item.motivo_divergencia}">D</span>`
                           : '';
   
                       // ### INÍCIO - Adicionando botões de Ação do Item ###
                       itensHtml += `
                       <tr>
                           <td>${item.prod_codigo_interno || ''}</td>
                           <td>${item.prod_descricao} ${divergenciaBadge}</td>
                           <td>${item.lote_completo || ''}</td>
                           <td>${item.cliente_lote_nome || ''}</td>
                           <td>${item.endereco_completo || ''}</td>
                           <td class="text-end">${item.qtd_carregada}</td>
                           <td class="text-center">
                               <div class="btn-group btn-group-sm" role="group">
                                   <button class="btn btn-warning btn-xs me-1 btn-editar-item" data-item-id="${item.car_item_id}" title="Editar Quantidade"><i class="fas fa-pencil-alt"></i></button>
                                   <button class="btn btn-danger btn-xs btn-remover-item" data-item-id="${item.car_item_id}" title="Remover Item"><i class="fas fa-times"></i></button>
                               </div>
                           </td>
                       </tr>
                   `;
                       // ### FIM - CORREÇÃO 1 ###
                   });
   
                   const clienteBotoesHtml = `
                   <button class="btn btn-info btn-sm btn-add-item-to-cliente me-2" 
                           data-fila-id="${fila.fila_id}" data-cliente-id="${clienteId}" 
                           data-cliente-nome="${cliente.nome}" title="Adicionar mais itens para este cliente">
                       <i class="fas fa-plus me-1"></i> Adicionar Item
                   </button>
                   <button class="btn btn-danger btn-sm btn-remove-cliente-from-fila" 
                           data-fila-id="${fila.fila_id}" data-cliente-id="${clienteId}" 
                           title="Remover este cliente e todos os seus itens da fila">
                       <i class="fas fa-trash-alt me-1"></i> Excluir Cliente
                   </button>
                `;
   
                   // ### INÍCIO - CORREÇÃO 3: Mudança de Cor do Header do Cliente ###
                   clientesHtml += `
                   <div class="card mb-2 shadow-sm">
                       <div class="card-header bg-white d-flex justify-content-between align-items-center">
                   <span class="toggle-btn" data-target=".cliente-${fila.fila_id}-${clienteId}"><i class="fas fa-plus-square"></i> Cliente: ${cliente.nome}</span>
                           <div>${clienteBotoesHtml}</div>
                       </div>
                       <div class="card-body cliente-${fila.fila_id}-${clienteId}" style="display: none;">
                           ${itensHtml ? `
                               <table class="table table-sm table-bordered">
                                   <thead class="table-light">
                                       <tr>
                                           <th>Código Interno</th>
                                           <th>Produto</th>
                                           <th>Lote</th>
                                           <th>Cliente do Lote</th>
                                           <th>Endereço</th>
                                           <th class="text-end">Qtd. Carregada</th>
                                           <th class="text-center">Ações</th>
                                           </tr>
                                   </thead>
                                   <tbody>${itensHtml}</tbody>
                               </table>
                           ` : '<div class="text-center text-muted">Nenhum item para este cliente.</div>'}
                       </div>
                   </div>
                `;
               }
   
               let removerFilaBtnHtml = '';
               if (index === filas.length - 1) {
                   removerFilaBtnHtml = `<button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}"><i class="fas fa-trash me-1"></i> Excluir Fila</button>`;
               } else {
                   removerFilaBtnHtml = `<button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}" disabled title="Remova as filas posteriores para poder excluir esta."><i class="fas fa-trash me-1"></i> Excluir Fila</button>`;
   
               }
   
               const filaBotoesHtml = `
                   <div class="d-flex align-items-center" style="margin-right: 2.5rem;">
                       <span class="fw-bold small mx-3"></span>
                       <button class="btn btn-info btn-sm btn-adicionar-item-fila me-2" data-fila-id="${fila.fila_id}">
                           <i class="fas fa-plus me-1"></i>Adicionar Cliente/Item
                       </button>
                       <button class="btn btn-outline-primary btn-sm btn-adicionar-foto me-2" data-fila-id="${fila.fila_id}" title="Adicionar Foto (Upload Web - Futuro)">
                           <i class="fas fa-camera me-1"></i> Adicionar Foto
                       </button>
                       
                       <button class="btn btn-outline-secondary btn-sm btn-ver-fotos me-3" 
                               data-fila-id="${fila.fila_id}" 
                               data-fila-numero="${fila.fila_numero_sequencial}" title="Ver fotos desta fila">
                           <i class="fas fa-images me-1"></i> Ver Fotos
                       </button>
                       ${removerFilaBtnHtml}
                   </div>
               `;            
   
               html += `
               <div class="card mb-3 shadow-sm border-primary">
                   <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                       <span class="toggle-btn" data-target=".fila-${fila.fila_id}"><i class="fas fa-plus-square"></i> Fila ${fila.fila_numero_sequencial}</span>
                       
                       <div class="fila-buttons-container">${filaBotoesHtml}</div>
                       </div>
                   <div class="card-body fila-${fila.fila_id}" style="display: none;">
                       ${clientesHtml || '<div class="text-center text-muted">Nenhum cliente/item nesta fila.</div>'}
                   </div>
               </div>
           `;
   
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
           
           // Lógica centralizada para habilitar/desabilitar os botões de Ação Principal
           // Regra padrão: Se não há filas, pode criar a primeira, mas não pode finalizar.
           let podeCriarNovaFila = (filas.length === 0);
           let podeFinalizar = false;
   
           if (filas.length > 0) {
               const ultimaFila = filas[filas.length - 1];
               // Buscamos os dados que o backend já nos enviou
               const temItens = ultimaFila.itens && ultimaFila.itens.length > 0;
               const temFotos = ultimaFila.total_fotos > 0;
   
               // A última fila é considerada "completa" se tiver itens E fotos
               const ultimaFilaCompleta = temItens && temFotos;
   
               // Só pode criar uma nova fila SE a última estiver completa
               podeCriarNovaFila = ultimaFilaCompleta;
   
               // Só pode finalizar o carregamento SE a última fila estiver completa
               podeFinalizar = ultimaFilaCompleta;
           }
   
           // 1. Aplica as regras ao botão "Adicionar Nova Fila"
           $('#btn-adicionar-fila').prop('disabled', !podeCriarNovaFila)
               .attr('title', podeCriarNovaFila ?
                   'Adicionar uma nova fila de carregamento' :
                   'Adicione itens e pelo menos uma foto à última fila para poder criar uma nova.');
   
           // 2. Aplica as regras ao botão "Finalizar Carregamento"
           $('#btn-finalizar-detalhe').prop('disabled', !podeFinalizar)
               .attr('title', podeFinalizar ?
                   'Finalizar o carregamento e baixar o estoque' :
                   'O carregamento não pode ser finalizado pois a última fila está incompleta (faltam itens ou foto).');
           // ### FIM DA ALTERAÇÃO ###
   
       } */

    /*   function renderExecucao(filas, statusCarregamento) {
           $filasContainer.empty();
           if (!filas || filas.length === 0) {
               $filasContainer.html('<p class="text-center text-muted">Nenhuma fila adicionada a este carregamento.</p>');
               return;
           }
   
           let html = '<div>';
           filas.forEach((fila, index) => {
               const clientesNaFila = {};
               if (fila.itens && fila.itens.length > 0) {
                   fila.itens.forEach(item => {
                       if (!clientesNaFila[item.car_item_cliente_id]) {
                           clientesNaFila[item.car_item_cliente_id] = { nome: item.cliente_nome, itens: [] };
                       }
                       clientesNaFila[item.car_item_cliente_id].itens.push(item);
                   });
               }
   
               let clientesHtml = '';
               for (const clienteId in clientesNaFila) {
                   const cliente = clientesNaFila[clienteId];
                   let itensHtml = '';
                   cliente.itens.forEach(item => {
                       const divergenciaBadge = item.motivo_divergencia
                           ? `<span class="badge bg-danger" title="Divergência: ${item.motivo_divergencia}">D</span>`
                           : '';
   
                       // ### INÍCIO - Adicionando botões de Ação do Item ###
                       itensHtml += `
                       <tr>
                           <td>${item.prod_codigo_interno || ''}</td>
                           <td>${item.prod_descricao} ${divergenciaBadge}</td>
                           <td>${item.lote_completo || ''}</td>
                           <td>${item.cliente_lote_nome || ''}</td>
                           <td>${item.endereco_completo || ''}</td>
                           <td class="text-end">${item.qtd_carregada}</td>
                           <td class="text-center">
                               <div class="btn-group btn-group-sm" role="group">
                                   <button class="btn btn-warning btn-xs me-1 btn-editar-item" data-item-id="${item.car_item_id}" title="Editar Quantidade"><i class="fas fa-pencil-alt"></i></button>
                                   <button class="btn btn-danger btn-xs btn-remover-item" data-item-id="${item.car_item_id}" title="Remover Item"><i class="fas fa-times"></i></button>
                               </div>
                           </td>
                       </tr>
                   `;
                       // ### FIM - CORREÇÃO 1 ###
                   });
   
                   const clienteBotoesHtml = `
                   <button class="btn btn-info btn-sm btn-add-item-to-cliente me-2" 
                           data-fila-id="${fila.fila_id}" data-cliente-id="${clienteId}" 
                           data-cliente-nome="${cliente.nome}" title="Adicionar mais itens para este cliente">
                       <i class="fas fa-plus me-1"></i> Adicionar Item
                   </button>
                   <button class="btn btn-danger btn-sm btn-remove-cliente-from-fila" 
                           data-fila-id="${fila.fila_id}" data-cliente-id="${clienteId}" 
                           title="Remover este cliente e todos os seus itens da fila">
                       <i class="fas fa-trash-alt me-1"></i> Excluir Cliente
                   </button>
                `;
   
                   // ### INÍCIO - CORREÇÃO 3: Mudança de Cor do Header do Cliente ###
                   clientesHtml += `
                   <div class="card mb-2 shadow-sm">
                       <div class="card-header bg-white d-flex justify-content-between align-items-center">
                   <span class="toggle-btn" data-target=".cliente-${fila.fila_id}-${clienteId}"><i class="fas fa-plus-square"></i> Cliente: ${cliente.nome}</span>
                           <div>${clienteBotoesHtml}</div>
                       </div>
                       <div class="card-body cliente-${fila.fila_id}-${clienteId}" style="display: none;">
                           ${itensHtml ? `
                               <table class="table table-sm table-bordered">
                                   <thead class="table-light">
                                       <tr>
                                           <th>Código Interno</th>
                                           <th>Produto</th>
                                           <th>Lote</th>
                                           <th>Cliente do Lote</th>
                                           <th>Endereço</th>
                                           <th class="text-end">Qtd. Carregada</th>
                                           <th class="text-center">Ações</th>
                                           </tr>
                                   </thead>
                                   <tbody>${itensHtml}</tbody>
                               </table>
                           ` : '<div class="text-center text-muted">Nenhum item para este cliente.</div>'}
                       </div>
                   </div>
                `;
               }
   
               // ### INÍCIO DA NOVA LÓGICA DE BOTÕES ###
               let filaBotoesHtml = '';
   
               if (statusCarregamento === 'EM ANDAMENTO') {
                   // --- LÓGICA PARA CARREGAMENTO ATIVO ---
                   let removerFilaBtnHtml = '';
                   if (index === filas.length - 1) {
                       removerFilaBtnHtml = `<button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}"><i class="fas fa-trash me-1"></i> Excluir Fila</button>`;
                   } else {
                       removerFilaBtnHtml = `<button class="btn btn-danger btn-sm btn-remover-fila" data-fila-id="${fila.fila_id}" disabled title="..."><i class="fas fa-trash me-1"></i> Excluir Fila</button>`;
                   }
   
                   filaBotoesHtml = `
                       <span class="fw-bold small mx-3">- AÇÕES DA FILA:</span>
                       <button class="btn btn-info btn-sm btn-adicionar-item-fila me-2" data-fila-id="${fila.fila_id}">
                           <i class="fas fa-plus me-1"></i> Add Cliente/Item
                       </button>
                       <button class="btn btn-outline-primary btn-sm btn-adicionar-foto me-2" data-fila-id="${fila.fila_id}">
                           <i class="fas fa-camera me-1"></i> Add Foto
                       </button>
                       <button class="btn btn-outline-secondary btn-sm btn-ver-fotos me-2" data-fila-id="${fila.fila_id}">
                           <i class="fas fa-images me-1"></i> Ver Fotos
                       </button>
                       <div class="ms-auto">${removerFilaBtnHtml}</div>
                   `;
   
               } else {
                   // --- LÓGICA PARA CARREGAMENTO FINALIZADO/CANCELADO ---
                   // Mostra apenas o botão "Ver Fotos", já alinhado à direita
                   filaBotoesHtml = `
                       <div class="ms-auto"> <button class="btn btn-outline-secondary btn-sm btn-ver-fotos" data-fila-id="${fila.fila_id}">
                               <i class="fas fa-images me-1"></i> Ver Fotos
                           </button>
                       </div>
                   `;
               }
               // ### FIM DA NOVA LÓGICA DE BOTÕES ###
   
               html += `
               <div class="card mb-3 shadow-sm border-primary">
                   <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                       <span class="toggle-btn" data-target=".fila-${fila.fila_id}">
                           <i class="fas fa-plus-square"></i> Fila ${fila.fila_numero_sequencial}
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
   
           // O evento de toggle continua o mesmo
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
   
           // Lógica centralizada para habilitar/desabilitar os botões de Ação Principal
   
           // Regra padrão: Se não há filas, pode criar a primeira, mas não pode finalizar.
           let podeCriarNovaFila = (filas.length === 0);
           let podeFinalizar = false;
   
           if (filas.length > 0) {
               const ultimaFila = filas[filas.length - 1];
               // Buscamos os dados que o backend já nos enviou
               const temItens = ultimaFila.itens && ultimaFila.itens.length > 0;
               const temFotos = ultimaFila.total_fotos > 0;
   
               // A última fila é considerada "completa" se tiver itens E fotos
               const ultimaFilaCompleta = temItens && temFotos;
   
               // Só pode criar uma nova fila SE a última estiver completa
               podeCriarNovaFila = ultimaFilaCompleta;
   
               // Só pode finalizar o carregamento SE a última fila estiver completa
               podeFinalizar = ultimaFilaCompleta;
           }
   
           // 1. Aplica as regras ao botão "Adicionar Nova Fila"
           $('#btn-adicionar-fila').prop('disabled', !podeCriarNovaFila)
               .attr('title', podeCriarNovaFila ?
                   'Adicionar uma nova fila de carregamento' :
                   'Adicione itens e pelo menos uma foto à última fila para poder criar uma nova.');
   
           // 2. Aplica as regras ao botão "Finalizar Carregamento"
           $('#btn-finalizar-detalhe').prop('disabled', !podeFinalizar)
               .attr('title', podeFinalizar ?
                   'Finalizar o carregamento e baixar o estoque' :
                   'O carregamento não pode ser finalizado pois a última fila está incompleta (faltam itens ou foto).');
           // ### FIM DA ALTERAÇÃO ###
   
       } */


    function renderExecucao(filas, statusCarregamento) {
        $filasContainer.empty();
        if (!filas || filas.length === 0) {
            $filasContainer.html('<p class="text-center text-muted">Nenhuma fila adicionada a este carregamento.</p>');
            return;
        }

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

                    // Lógica para os botões de AÇÃO DO ITEM (Editar/Excluir)
                    let itemAcoesHtml = '';
                    if (statusCarregamento === 'EM ANDAMENTO') {
                        itemAcoesHtml = `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-warning btn-editar-item" data-item-id="${item.car_item_id}" title="Editar"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-remover-item" data-item-id="${item.car_item_id}" title="Excluir"><i class="fas fa-trash"></i></button>
                            </div>`;
                    }

                    itensHtml += `
                        <tr>
                            <td>${item.prod_codigo_interno || ''}</td>
                            <td>${item.prod_descricao} ${divergenciaBadge}</td>
                            <td>${item.lote_completo || ''}</td>
                            <td>${item.cliente_lote_nome || 'N/A'}</td>
                            <td>${item.endereco_completo || ''}</td>
                            <td class="text-end">${item.qtd_carregada}</td>
                            <td class="text-center">${itemAcoesHtml}</td>
                        </tr>`;
                });

                // Lógica para os botões de AÇÃO DO CLIENTE
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
                            <table class="table table-sm table-bordered table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Cód. Interno</th><th>Produto</th><th>Lote</th><th>Cliente do Lote</th>
                                        <th>Endereço</th><th class="text-end">Qtd. Carregada</th><th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>${itensHtml}</tbody>
                            </table>
                        </div>
                    </div>`;
            }

            // Lógica para os botões de AÇÃO DA FILA
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
                    <button class="btn btn-outline-secondary btn-sm btn-ver-fotos me-2" data-fila-id="${fila.fila_id}"><i class="fas fa-images me-1"></i> Ver Fotos</button>
                    ${removerFilaBtnHtml}`;
            } else {
                filaBotoesHtml = `<button class="btn btn-outline-secondary btn-sm btn-ver-fotos" data-fila-id="${fila.fila_id}"><i class="fas fa-images me-1"></i> Ver Fotos</button>`;
            }

            html += `
                <div class="card mb-3 shadow-sm border-primary">
                    <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                        <span class="toggle-btn" data-target=".fila-${fila.fila_id}"><i class="fas fa-plus-square"></i> Fila ${fila.fila_numero_sequencial}</span>
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

        // Lógica centralizada para habilitar/desabilitar os botões de Ação Principal

        // Regra padrão: Se não há filas, pode criar a primeira, mas não pode finalizar.
        let podeCriarNovaFila = (filas.length === 0);
        let podeFinalizar = false;

        if (filas.length > 0) {
            const ultimaFila = filas[filas.length - 1];
            // Buscamos os dados que o backend já nos enviou
            const temItens = ultimaFila.itens && ultimaFila.itens.length > 0;
            const temFotos = ultimaFila.total_fotos > 0;

            // A última fila é considerada "completa" se tiver itens E fotos
            const ultimaFilaCompleta = temItens && temFotos;

            // Só pode criar uma nova fila SE a última estiver completa
            podeCriarNovaFila = ultimaFilaCompleta;

            // Só pode finalizar o carregamento SE a última fila estiver completa
            podeFinalizar = ultimaFilaCompleta;
        }

        // 1. Aplica as regras ao botão "Adicionar Nova Fila"
        $('#btn-adicionar-fila').prop('disabled', !podeCriarNovaFila)
            .attr('title', podeCriarNovaFila ?
                'Adicionar uma nova fila de carregamento' :
                'Adicione itens e pelo menos uma foto à última fila para poder criar uma nova.');

        // 2. Aplica as regras ao botão "Finalizar Carregamento"
        $('#btn-finalizar-detalhe').prop('disabled', !podeFinalizar)
            .attr('title', podeFinalizar ?
                'Finalizar o carregamento e baixar o estoque' :
                'O carregamento não pode ser finalizado pois a última fila está incompleta (faltam itens ou foto).');
        // ### FIM DA ALTERAÇÃO ###    
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
        /*    function checkAgainstGabarito() {
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
    
                if (!clienteId || !produtoId || !alocacaoId) {
                    return;
                }
    
                // --- NOVA LÓGICA DE SALDO ---
                // Busca o saldo físico DIRETAMENTE do 'data' da <option> selecionada
                let saldoFisico = 0;
                const $opcaoEndereco = $itemAlocacao.find('option:selected');
                if ($opcaoEndereco.length > 0) {
                    // parseFloat para garantir que é um número
                    saldoFisico = parseFloat($opcaoEndereco.data('saldo_disponivel')) || 0;
                }
                // --- FIM DA NOVA LÓGICA DE SALDO ---
    
                const itemGabarito = gabaritoPlanejamento.find(item =>
                    item.oep_cliente_id == clienteId &&
                    item.oei_alocacao_id == alocacaoId
                );
    
                if (itemGabarito) {
                    // --- CENÁRIO 1: ITEM ESTÁ NA OE ---
                    const saldoOE = parseFloat(itemGabarito.qtd_planejada) - parseFloat(itemGabarito.qtd_carregada);
                    $itemHelper.text(`Item conforme a OE. Saldo no plano: ${saldoOE.toFixed(0)}`).css('color', 'green');
                    $itemSaldo.val(saldoOE.toFixed(0)); // Mostra o saldo da OE
                    //$itemQtd.attr('max', saldoOE).val(saldoOE > 0 ? saldoOE : 1).prop('disabled', false);
                    $itemQtd.attr('max', saldoOE).val(1).prop('disabled', false);
                    $itemOeiId.val(itemGabarito.oei_id);
                    $itemMotivoContainer.hide();
                    $itemMotivoInput.prop('required', false);
                } else {
                    // --- CENÁRIO 2: ITEM NÃO ESTÁ NA OE (DIVERGÊNCIA) ---
                    $itemHelper.text('DIVERGÊNCIA: Item não planejado na OE. Motivo é obrigatório.').css('color', 'red');
                    $itemSaldo.val(saldoFisico.toFixed(0)); // Mostra o saldo FÍSICO
                    $itemQtd.attr('max', saldoFisico).val(1).prop('disabled', false); // Usa o saldo FÍSICO
                    $itemOeiId.val('');
                    $itemMotivoContainer.show();
                    $itemMotivoInput.prop('required', true);
                }
    
                $itemBtnAdd.prop('disabled', false);
            } */

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

            if (!clienteId || !produtoId || !alocacaoId) {
                return;
            }

            const itemGabarito = gabaritoPlanejamento.find(item =>
                item.oep_cliente_id == clienteId &&
                item.oei_alocacao_id == alocacaoId
            );

            if (itemGabarito) {
                // --- CENÁRIO 1: ITEM ESTÁ NA OE (Lógica não muda) ---
                const saldoOE = parseFloat(itemGabarito.qtd_planejada) - parseFloat(itemGabarito.qtd_carregada);
                $itemHelper.text(`Item conforme a OE. Saldo no plano: ${saldoOE.toFixed(0)}`).css('color', 'green');
                $itemSaldo.val(saldoOE.toFixed(0));
                $itemQtd.attr('max', saldoOE).val(1).prop('disabled', false);
                $itemOeiId.val(itemGabarito.oei_id);
                $itemMotivoContainer.hide();
                $itemMotivoInput.prop('required', false);

            } else {
                // --- CENÁRIO 2: ITEM É DIVERGÊNCIA (Lógica corrigida) ---
                const $opcaoEndereco = $itemAlocacao.find('option:selected');
                // Busca o saldo FÍSICO que guardamos no passo anterior
                const saldoFisico = parseFloat($opcaoEndereco.data('saldo_fisico')) || 0;

                $itemHelper.text('DIVERGÊNCIA: Item não planejado na OE. Motivo é obrigatório.').css('color', 'red');

                // ### CORREÇÃO: Usa o saldo FÍSICO para o campo e para o 'max' ###
                $itemSaldo.val(saldoFisico.toFixed(0));
                $itemQtd.attr('max', saldoFisico).val(1).prop('disabled', false);

                $itemOeiId.val('');
                $itemMotivoContainer.show();
                $itemMotivoInput.prop('required', true);
            }

            $itemBtnAdd.prop('disabled', false);
        }


    } // Fim de inicializarLogicaModalUnificado()

    // 5. Submit do Novo Formulário
    $formAddItem.on('submit', function (e) {
        e.preventDefault();
        const $campoQtd = $('#item_quantidade');
        const qtd = parseFloat($campoQtd.val());
        const max = parseFloat($campoQtd.attr('max'));

        if (isNaN(qtd) || qtd <= 0) {
            Swal.fire('Erro', 'A quantidade deve ser maior que zero.', 'error');
            return;
        }
        if (qtd > max) {
            Swal.fire('Erro', `A quantidade (${qtd}) excede o saldo disponível (${max}).`, 'error');
            return;
        }
        if ($itemMotivoContainer.is(':visible') && $itemMotivoInput.val().trim() === '') {
            Swal.fire('Erro', 'O motivo da divergência é obrigatório.', 'error');
            $itemMotivoInput.focus();
            return;
        }

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
                    loadDetalhesCarregamento(); // Recarrega TUDO
                } else {
                    Swal.fire('Erro ao adicionar', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro de Conexão', 'Não foi possível salvar o item.', 'error');
            }
        });
    });

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
                // O resto da função continua igual...
                $.post('ajax_router.php?action=removeFilaCarregamento', { fila_id: filaId, csrf_token: csrfToken }, function (response) {
                    if (response.success) { loadDetalhesCarregamento(); }
                    else { Swal.fire('Erro', response.message, 'error'); }
                }, 'json');
            }
        });
    });

    // Remover Cliente da Fila
    $filasContainer.on('click', '.btn-remove-cliente-from-fila', function () {
        const filaId = $(this).data('fila-id');
        const clienteId = $(this).data('cliente-id');
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
                        loadDetalhesCarregamento();
                    } else { Swal.fire('Erro', response.message, 'error'); }
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
        $.post('ajax_router.php?action=addFilaCarregamento', { carregamento_id: carregamentoId, csrf_token: csrfToken }, function (response) {
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

    // Finalizar Carregamento
    /*  $('#btn-finalizar-detalhe').on('click', function () {
          // (Esta função está correta, cole a sua versão anterior aqui)
          Swal.fire({
              title: 'Finalizar Carregamento?',
              text: 'Esta ação irá finalizar o carregamento e dar baixa no estoque. Deseja continuar?',
              icon: 'warning', showCancelButton: true, confirmButtonColor: '#28a745',
              cancelButtonColor: '#6c757d', confirmButtonText: 'Sim, finalizar!', cancelButtonText: 'Cancelar'
          }).then((result) => {
              if (result.isConfirmed) {
                  $.post('ajax_router.php?action=finalizarCarregamento', {
                      carregamento_id: carregamentoId, csrf_token: csrfToken
                  }, function (response) {
                      if (response.success) {
                          Swal.fire('Finalizado!', 'Carregamento finalizado com sucesso.', 'success').then(() => {
                              loadDetalhesCarregamento();
                          });
                      } else { Swal.fire('Erro', response.message, 'error'); }
                  }, 'json');
              }
          });
      }); */

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
