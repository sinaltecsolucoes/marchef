// /public/js/lista_faturamentos.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    const dataTable = $('#tabela-faturamentos').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarFaturamentos",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            {
                "data": "fat_id",
                "className": "text-center align-middle",
                "render": function (data, type, row) {
                    // Converte o ID para string e preenche com zeros à esquerda até ter 4 dígitos
                    return String(data).padStart(4, '0');
                }
            },
            { "data": "ordem_numero",
                "className": "text-center align-middle"
             },
            {
                "data": "fat_data_geracao",
                "className": "text-center align-middle",
                "render": function (data) {
                    if (!data) return '';
                    return new Date(data).toLocaleString('pt-BR');
                }
            },
            {
                "data": "fat_status",
                "className": "text-center align-middle"
            },
            {
                "data": "usuario_nome",
                "className": "text-center align-middle"
            },
            {
                "data": "fat_id",
                "orderable": false,
                "className": "text-center align-middle",
                "render": function (data, type, row) {
                    let btnDetalhes = `<a href="index.php?page=faturamento_gerar&resumo_id=${data}" class="btn btn-warning btn-sm me-1"><i class="fas fa-pencil-alt me-1"></i>Editar</a>`;
                    let btnExcluir = `<button class="btn btn-danger btn-sm btn-excluir-faturamento me-1" data-id="${data}"><i class="fas fa-trash-alt me-1"></i>Excluir</button>`;
                    let btnFaturar = `<button class="btn btn-success btn-sm btn-marcar-faturado me-1" data-id="${data}"><i class="fas fa-receipt me-1"></i>Faturar</button>`;
                    let btnCancelar = `<button class="btn btn-secondary btn-sm btn-cancelar-faturamento me-1" data-id="${data}"><i class="fas fa-times me-1"></i>Cancelar</button>`;
                    let btnReabrir = `<button class="btn btn-primary btn-sm btn-reabrir-faturamento me-1" data-id="${data}"><i class="fas fa-redo me-1"></i>Reabrir</button>`;

                    if (row.fat_status === 'FATURADO') {
                        btnDetalhes = `<a href="index.php?page=faturamento_gerar&resumo_id=${data}" class="btn btn-info btn-sm me-2"><i class="fas fa-search me-1"></i>Visualizar</a>`;
                        return `<div class="btn-group">${btnDetalhes}${btnReabrir}</div>`;
                    }

                    if (row.fat_status === 'CANCELADO') {
                        return `<div class="btn-group">${btnReabrir}</div>`;
                    }

                    // Padrão (EM ELABORAÇÃO)
                    return `<div class="btn-group">${btnDetalhes}${btnFaturar}${btnCancelar}${btnExcluir}</div>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[0, 'desc']]
    });

    // Função helper genérica para ações com confirmação
    function handleFaturamentoAction(action, id, title, text) {
        Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true })
            .then((result) => {
                if (result.isConfirmed) {
                    $.post(`ajax_router.php?action=${action}`, { resumo_id: id, csrf_token: csrfToken }, function (response) {
                        if (response.success) {
                            Swal.fire('Sucesso!', response.message, 'success');
                            dataTable.ajax.reload();
                        } else {
                            Swal.fire('Erro!', response.message, 'error');
                        }
                    }, 'json');
                }
            });
    }

    // Eventos de clique para cada botão
    $('#tabela-faturamentos').on('click', '.btn-excluir-faturamento', function () {
        handleFaturamentoAction('excluirFaturamento', $(this).data('id'), 'Excluir Faturamento?', 'Esta ação não pode ser desfeita.');
    });
    $('#tabela-faturamentos').on('click', '.btn-cancelar-faturamento', function () {
        handleFaturamentoAction('cancelarFaturamento', $(this).data('id'), 'Cancelar Faturamento?', 'O status será alterado para "CANCELADO".');
    });
    $('#tabela-faturamentos').on('click', '.btn-reabrir-faturamento', function () {
        handleFaturamentoAction('reabrirFaturamento', $(this).data('id'), 'Reabrir Faturamento?', 'O status voltará para "EM ELABORAÇÃO".');
    });

    // Cache e referências para o Modal de Faturar
    const $modalFaturado = $('#modal-marcar-faturado');
    const $formFaturado = $('#form-marcar-faturado');
    const $notasContainer = $('#notas-fiscais-container');

    // Evento para ABRIR o Modal de "Marcar como Faturado"
    $('#tabela-faturamentos').on('click', '.btn-marcar-faturado', function () {
        const id = $(this).data('id');
        $('#faturado_resumo_id').val(id);
        $notasContainer.html('<p class="text-center text-muted">Buscando grupos de nota...</p>');
        $modalFaturado.modal('show');

        // Busca os grupos de nota para popular o formulário do modal
        $.post('ajax_router.php?action=getGruposDeNotaParaFaturamento', { resumo_id: id, csrf_token: csrfToken }, function (response) {
            $notasContainer.empty();
            if (response.success && response.data.length > 0) {

                // 1. Agrupa os resultados por Fazenda usando JavaScript
                const gruposPorFazenda = response.data.reduce((acc, grupo) => {
                    const fazenda = grupo.fazenda_nome || 'Sem Fazenda Definida';
                    if (!acc[fazenda]) {
                        acc[fazenda] = [];
                    }
                    acc[fazenda].push(grupo);
                    return acc;
                }, {});

                // 2. Constrói o HTML hierárquico
                for (const nomeFazenda in gruposPorFazenda) {
                    // Adiciona o título da Fazenda
                    $notasContainer.append(`<h6 class="fw-bold text-primary border-bottom pb-1 mb-2">${nomeFazenda}</h6>`);

                    const notasDaFazenda = gruposPorFazenda[nomeFazenda];

                    // Loop para cada nota/pedido dentro daquela fazenda
                    notasDaFazenda.forEach(grupo => {
                        const labelText = `${grupo.cliente_nome} (Pedido: ${grupo.fatn_numero_pedido || 'N/A'})`;
                        const inputHtml = `
                            <div class="mb-3">
                                <label class="form-label">${labelText}</label>
                                <input type="text" class="form-control" data-grupo-id="${grupo.fatn_id}" placeholder="Nº da NF-e (opcional)">
                            </div>`;
                        $notasContainer.append(inputHtml);
                    });
                }

            } else {
                $notasContainer.html('<p class="text-danger">Nenhum grupo de nota encontrado para este faturamento.</p>');
            }
        }, 'json');
    });

    // Evento para SALVAR as Notas Fiscais e Marcar como Faturado
    $formFaturado.on('submit', function (e) {
        e.preventDefault();
        const id = $('#faturado_resumo_id').val();
        const notas = [];

        // Coleta os dados dos inputs dinâmicos
        $notasContainer.find('input').each(function () {
            notas.push({
                grupo_id: $(this).data('grupo-id'),
                numero_nf: $(this).val().trim()
            });
        });

        $.post('ajax_router.php?action=marcarComoFaturado', {
            resumo_id: id,
            notas: JSON.stringify(notas), // Envia os dados como uma string JSON
            csrf_token: csrfToken
        }, function (response) {
            if (response.success) {
                $modalFaturado.modal('hide');
                Swal.fire('Sucesso!', response.message, 'success');
                dataTable.ajax.reload();
            } else {
                Swal.fire('Erro!', response.message, 'error');
            }
        }, 'json');
    });
});