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

    function construirArvore(data) {
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

                    // --- INÍCIO DA CORREÇÃO PRINCIPAL ---
                    if (temItens) {
                        // A linha da sub-tabela agora tem UMA CÉLULA que ocupa TODAS as 5 colunas
                        html += `
                            <tr class="endereco-${enderecoId}" style="display: none;">
                                <td colspan="5">
                                    <div class="ps-5 pt-2 pb-2">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light"><tr><th>Produto</th><th>Lote</th><th>Qtd.</th><th class="text-center">Ação</th></tr></thead>
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
                    // --- FIM DA CORREÇÃO PRINCIPAL ---
                });
            } else {
                html += `<tr class="camara-${camaraId}" style="display: none;"><td colspan="5" class="text-muted ps-5">Nenhum endereço cadastrado nesta câmara.</td></tr>`;
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
});