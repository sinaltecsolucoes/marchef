// /public/js/visao_estoque_enderecos.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const $container = $('#tree-container');
    const $modalAlocar = $('#modal-alocar-item');
    const $formAlocar = $('#form-alocar-item');

    // Inicialização do Select2 para o modal de alocação
    $('#select-item-para-alocar').select2({
        placeholder: "Busque por produto ou lote...",
        theme: "bootstrap-5",
        dropdownParent: $modalAlocar,
        ajax: {
            url: "ajax_router.php?action=getItensNaoAlocados",
            dataType: 'json',
            delay: 250,
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        }
    });

    /* function construirArvore(data) {
         $container.empty();
         if (Object.keys(data).length === 0) {
             $container.html('<p class="text-muted">Nenhuma câmara cadastrada.</p>');
             return;
         }
 
         let html = `
             <table class="table table-hover">
                 <thead class="table-light">
                     <tr>
                         <th style="width: 40px;"></th>
                         <th>Descrição (Câmara / Endereço)</th>
                         <th class="text-end" style="width: 150px;">Total Caixas</th>
                         <th class="text-end" style="width: 150px;">Total Quilos (kg)</th>
                         <th class="text-end" style="width: 150px;">Ação</th>
                     </tr>
                 </thead>
                 <tbody>
         `;
 
         $.each(data, function (camaraId, camara) {
             html += `
                 <tr class="table-primary">
                     <td><i class="fas fa-plus-square toggle-btn" data-target=".camara-${camaraId}"></i></td>
                     <td><strong>${camara.nome} (${camara.codigo})</strong></td>
                     <td class="text-end"><strong>${parseFloat(camara.total_caixas).toFixed(3)}</strong></td>
                     <td class="text-end"><strong>${parseFloat(camara.total_quilos).toFixed(3)}</strong></td>
                     <td></td>
                 </tr>
             `;
 
             if (Object.keys(camara.enderecos).length > 0) {
                 $.each(camara.enderecos, function (enderecoId, endereco) {
                     const temItens = endereco.itens.length > 0;
                     const iconClass = temItens ? 'fa-plus-square' : 'fa-square text-muted';
                     const toggleClass = temItens ? 'toggle-btn' : '';
 
                     html += `
                         <tr class="camara-${camaraId}" style="display: none;">
                             <td></td>
                             <td class="ps-4"><i class="fas ${iconClass} ${toggleClass}" data-target=".endereco-${enderecoId}"></i> ${endereco.nome}</td>
                             <td class="text-end">${parseFloat(endereco.total_caixas).toFixed(3)}</td>
                             <td class="text-end">${parseFloat(endereco.total_quilos).toFixed(3)}</td>
                             <td class="text-end">
                                 <button class="btn btn-success btn-sm btn-alocar-item" data-id="${endereco.endereco_id}" data-nome="${endereco.nome}">Alocar Item</button>
                             </td>
                         </tr>
                     `;
                     
                     if (temItens) {
                         // A linha da sub-tabela agora tem UMA CÉLULA que ocupa TODAS as 5 colunas
                         html += `
                             <tr class="endereco-${enderecoId}" style="display: none;">
                                 <td colspan="5">
                                     <div class="ps-5 pt-2 pb-2 border border-primary-subtle rounded shadow-sm">
                                         <table class="table table-sm table-bordered mb-0">
                                             <thead class="table-light">
                                                 <tr>
                                                     <th>Produto</th>
                                                     <th>Lote</th>
                                                     <th>Qtd.</th>
                                                     <th class="text-center">Ação</th>
                                                 </tr>
                                             </thead>
                                     <tbody>`;
                         $.each(endereco.itens, function (i, item) {
                             html += `<tr>
                                         <td>${item.produto}</td>
                                         <td>${item.lote}</td>
                                         <td>${parseFloat(item.quantidade).toFixed(3)}</td>
                                         <td class="text-center">
                                             <button class="btn btn-danger btn-xs btn-desalocar-item-especifico" data-alocacao-id="${item.alocacao_id}" title="Desalocar este item">
                                                 <i class="fas fa-times"></i>
                                             </button>
                                         </td>
                                      </tr>`;
                         });
                         html += `</tbody></table>
                                     </div>
                                 </td>
                             </tr>`;
                     }
                     
                 });
             } else {
                 html += `<tr class="camara-${camaraId}" style="display: none;"><td colspan="5" class="text-muted ps-5">Nenhum endereço cadastrado nesta câmara.</td></tr>`;
             }
         });
 
         html += '</tbody></table>';
         $container.html(html);
     } */

    function construirArvore(data) {
        $container.empty();
        if (Object.keys(data).length === 0) {
            $container.html('<p class="text-muted">Nenhuma câmara cadastrada.</p>');
            return;
        }

        // 1. NOVO CABEÇALHO PRINCIPAL
        let html = `
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th style="width: 40px;"></th>
                    <th class="align-middle">Descrição (Câmara / Endereço)</th>
                    <th class="text-center align-middle" style="width: 120px;">Caixas Físicas</th>
                    <th class="text-center align-middle" style="width: 120px;">Caixas Reserv.</th>
                    <th class="text-center align-middle" style="width: 120px;">Caixas Disp.</th>
                    <th class="text-center align-middle" style="width: 120px;">Quilos (kg)</th>
                    <th class="text-center align-middle" style="width: 150px;">Ação</th>
                </tr>
            </thead>
            <tbody>
    `;

        $.each(data, function (camaraId, camara) {
            // 2. NOVAS COLUNAS PARA AS CÂMARAS
            const caixasDisponiveisCamara = camara.total_caixas - camara.total_caixas_reservadas;
            html += `
            <tr class="table-primary" style="border-top: 2px solid #a9c6e8; border-bottom: 1px solid #a9c6e8;">
                <td><i class="fas fa-plus-square toggle-btn" data-target=".camara-${camaraId}"></i></td>
                <td><strong>${camara.nome} (${camara.codigo})</strong></td>
                <td class="text-center"><strong>${parseFloat(camara.total_caixas).toFixed(3)}</strong></td>
                <td class="text-center text-danger"><strong>${parseFloat(camara.total_caixas_reservadas).toFixed(3)}</strong></td>
                <td class="text-center text-success fw-bolder"><strong>${parseFloat(caixasDisponiveisCamara).toFixed(3)}</strong></td>
                <td class="text-center"><strong>${parseFloat(camara.total_quilos).toFixed(3)}</strong></td>
                <td></td>
            </tr>
        `;

            if (Object.keys(camara.enderecos).length > 0) {
                $.each(camara.enderecos, function (enderecoId, endereco) {
                    const temItens = endereco.itens.length > 0;
                    const iconClass = temItens ? 'fa-plus-square' : 'fa-square text-muted';
                    const toggleClass = temItens ? 'toggle-btn' : '';
                    const caixasDisponiveisEndereco = endereco.total_caixas - endereco.total_caixas_reservadas;

                    // 3. NOVAS COLUNAS PARA OS ENDEREÇOS
                    html += `
                    <tr class="camara-${camaraId}" style="display: none;">
                        <td></td>
                        <td class="ps-4"><i class="fas ${iconClass} ${toggleClass}" data-target=".endereco-${enderecoId}"></i> ${endereco.nome}</td>
                        <td class="text-center">${parseFloat(endereco.total_caixas).toFixed(3)}</td>
                        <td class="text-center text-danger">${parseFloat(endereco.total_caixas_reservadas).toFixed(3)}</td>
                        <td class="text-center text-success fw-bolder">${parseFloat(caixasDisponiveisEndereco).toFixed(3)}</td>
                        <td class="text-center">${parseFloat(endereco.total_quilos).toFixed(3)}</td>
                        <td class="text-center">
                            <button class="btn btn-success btn-sm btn-alocar-item" data-id="${endereco.endereco_id}" data-nome="${endereco.nome}">Alocar Item</button>
                        </td>
                    </tr>
                `;

                    /* if (temItens) {
                         // 4. NOVA SUB-TABELA DE ITENS
                         html += `
                         <tr class="endereco-${enderecoId}" style="display: none;">
                             <td colspan="7">
                                 <div class="ps-5 p-3 border rounded shadow-sm bg-white">
                                     <table class="table table-sm table-bordered mb-0">
                                         <thead class="table-light">
                                             <tr>
                                                 <th class="text-center align-middle" style="width: 55%;">Produto</th>
                                                 <th class="text-center align-middle" style="width: 10%;">Lote</th>
                                                 <th class="text-center align-middle" style="width: 10%;">Qtd. Física</th>
                                                 <th class="text-center align-middle" style="width: 10%;">Qtd. Reservada</th>
                                                 <th class="text-center align-middle" style="width: 10%;">Qtd. Disponível</th>
                                                 <th class="text-center align-middle" style="width: 5%;">Ação</th>
                                             </tr>
                                         </thead>
                                         <tbody>`;
                         $.each(endereco.itens, function (i, item) {
                             const qtdDisponivel = item.quantidade_fisica - item.quantidade_reservada;
                             html += `<tr>
                                     <td class="align-middle">${item.produto}</td>
                                     <td class="text-center align-middle">${item.lote}</td>
                                     <td class="text-center align-middle">${parseFloat(item.quantidade_fisica).toFixed(3)}</td>
                                     <td class="text-center align-middle text-danger">${parseFloat(item.quantidade_reservada).toFixed(3)}</td>
                                     <td class="text-center align-middle  text-success fw-bolder">${parseFloat(qtdDisponivel).toFixed(3)}</td>
                                     <td class="text-center">
                                         <button class="btn btn-danger btn-xs btn-desalocar-item-especifico" data-alocacao-id="${item.alocacao_id}" title="Desalocar este item">
                                             <i class="fas fa-trash"></i>
                                         </button>
                                     </td>
                                     </tr>`;
                         });
                         html += `</tbody></table>
                                 </div>
                             </td>
                         </tr>`;
                     } */

                    if (temItens) {
                        html += `
                                <tr class="endereco-${enderecoId}" style="display: none;">
                                    <td colspan="7">
                                        <div class="ps-5 p-3 border rounded shadow-sm bg-white">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="text-center align-middle">Produto</th>
                                                        <th class="text-center align-middle">Lote</th>
                                                        <th class="text-center align-middle">Qtd. Física</th>
                                                        <th class="text-center align-middle">Qtd. Reservada</th>
                                                        <th class="text-center align-middle">Qtd. Disponível</th>
                                                        <th class="text-center align-middle">Ação</th>
                                                    </tr>
                                                </thead>
                                                <tbody>`;
                        $.each(endereco.itens, function (i, item) {
                            const qtdDisponivel = item.quantidade_fisica - item.quantidade_reservada;
                            // A quantidade reservada é um link se for maior que zero
                            let reservadoHtml = parseFloat(item.quantidade_reservada).toFixed(3);
                            if (item.quantidade_reservada > 0) {
                                reservadoHtml = `<a href="#" class="link-reserva text-danger fw-bold" data-alocacao-id="${item.alocacao_id}">${reservadoHtml}</a>`;
                            }

                            html += `<tr>
                                        <td class="align-middle">${item.produto}</td>
                                        <td class="text-center align-middle">${item.lote}</td>
                                        <td class="text-center align-middle">${parseFloat(item.quantidade_fisica).toFixed(3)}</td>
                                        <td class="text-center align-middle">${reservadoHtml}</td>
                                        <td class="text-center align-middle text-success fw-bolder">${parseFloat(qtdDisponivel).toFixed(3)}</td>
                                        <td class="text-center align-middle">
                                            <button class="btn btn-danger btn-xs btn-desalocar-item-especifico" data-alocacao-id="${item.alocacao_id}" title="Desalocar este item">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                        </tr>`;
                        });
                        html += `</tbody></table>
                                    </div>
                                </td>
                            </tr>`;
                    }

                });
            } else {
                html += `<tr class="camara-${camaraId}" style="display: none;"><td colspan="7" class="text-muted ps-5">Nenhum endereço cadastrado nesta câmara.</td></tr>`;
            }
        });

        html += '</tbody></table>';
        $container.html(html);
    }

    function carregarDados() {
        $.ajax({
            url: 'ajax_router.php?action=getVisaoEstoqueHierarquico',
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                construirArvore(response.data);
            } else {
                $container.html(`<div class="alert alert-danger">${response.message}</div>`);
            }
        });
    }
    carregarDados();

    $container.on('click', '.toggle-btn', function () {
        const targetClass = $(this).data('target');
        $(targetClass).toggle();
        $(this).toggleClass('fa-plus-square fa-minus-square');
    });

    // Evento para abrir o modal de alocação (sem alterações)
    $container.on('click', '.btn-alocar-item', function () {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        $formAlocar[0].reset();
        $('#alocar_endereco_id').val(id);
        $('#alocar-endereco-nome').text(nome);
        $('#select-item-para-alocar').val(null).trigger('change');
        $modalAlocar.modal('show');
    });

    // Evento para submeter o formulário de alocação (sem alterações)
    $formAlocar.on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        $.ajax({
            url: 'ajax_router.php?action=alocarItemEndereco',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalAlocar.modal('hide');
                notificacaoSucesso('Sucesso!', response.message);
                carregarDados();
            } else {
                notificacaoErro('Erro ao Alocar', response.message);
            }
        });
    });

    // --- NOVO EVENTO PARA DESALOCAR UM ITEM ESPECÍFICO ---
    $container.on('click', '.btn-desalocar-item-especifico', function () {
        const alocacaoId = $(this).data('alocacao-id');
        confirmacaoAcao('Desalocar Item?', 'Tem a certeza que deseja remover este item do endereço?')
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_router.php?action=desalocarItemEndereco',
                        type: 'POST',
                        data: { alocacao_id: alocacaoId, csrf_token: csrfToken },
                        dataType: 'json'
                    }).done(function (response) {
                        if (response.success) {
                            notificacaoSucesso('Sucesso!', response.message);
                            carregarDados(); // Recarrega a árvore para refletir a mudança
                        } else {
                            notificacaoErro('Erro!', response.message);
                        }
                    });
                }
            });
    });

    // --- EVENTO PARA DETALHES DA RESERVA ---
    $container.on('click', '.link-reserva', function (e) {
        e.preventDefault();
        const alocacaoId = $(this).data('alocacao-id');
        const $modal = $('#modal-reserva-detalhes');
        const $containerDetalhes = $('#reserva-detalhes-container');

        $containerDetalhes.html('<p class="text-center">Carregando detalhes...</p>');
        $modal.modal('show');

        $.ajax({
            url: 'ajax_router.php?action=getReservaDetalhes',
            type: 'POST',
            data: { alocacao_id: alocacaoId, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data.length > 0) {
                let tabelaHtml = `
                    <table class="table table-striped table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center align-middle">Nº da Ordem</th>
                                <th class="text-center align-middle">Cliente</th>
                                <th class="text-center align-middle">Pedido do Cliente</th>
                                <th class="text-center align-middle">Qtd. Reservada</th>
                            </tr>
                        </thead>
                        <tbody>`;
                response.data.forEach(reserva => {
                    tabelaHtml += `
                        <tr>
                            <td class="text-center align-middle">${reserva.oe_numero}</td>
                            <td class="align-middle">${reserva.cliente_nome}</td>
                            <td class="text-center align-middle">${reserva.oep_numero_pedido || 'N/A'}</td>
                            <td class="text-center align-middle">${parseFloat(reserva.oei_quantidade).toFixed(3)}</td>
                        </tr>`;
                });
                tabelaHtml += `</tbody></table>`;
                $containerDetalhes.html(tabelaHtml);
            } else {
                $containerDetalhes.html('<p class="text-center text-muted">Nenhuma reserva encontrada para este item.</p>');
            }
        });
    });

});