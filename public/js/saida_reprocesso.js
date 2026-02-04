$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').first().val();
    const $tabela = $('#tabela-reprocesso');

    // 1. Inicializar DataTable filtrando por Reprocesso
    const dataTable = $tabela.DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarCarregamentos",
            "type": "POST",
            "data": function (d) {
                d.csrf_token = csrfToken;
                d.tipo_saida = 'REPROCESSO'; // Flag para o Backend saber o que filtrar
            }
        },
        "responsive": true,
        "columns": [
            {
                "data": "car_numero", "className": "text-center align-middle",
                "render": data => String(data).padStart(4, '0')
            },
            {
                "data": "car_data",
                "className": "text-center align-middle",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            {
                "data": "oe_numero",
                "className": "col-centralizavel align-middle"
            },
            {
                "data": "car_status",
                "className": "col-centralizavel align-middle"
            },
            {
                "data": "car_id",
                "orderable": false,
                "className": "col-centralizavel align-middle",
                "render": (data, type, row) => {
                    const status = row.car_status;
                    const id = row.car_id;
                    const isReprocesso = (row.car_tipo === 'REPROCESSO');

                    let btnHtml = '';   // Botões principais (ícones)
                    let menuItens = ''; // Itens dentro do dropdown "Mais"

                    // 1. LINK DE DETALHES (Sempre visível)
                    btnHtml += `<a href="index.php?page=detalhes_carregamento&id=${id}" class="btn btn-info btn-sm me-1" title="Ver Detalhes">
                        <i class="fas fa-search"></i> Visualizar
                    </a>`;

                    // 2. STATUS: EM ANDAMENTO
                    if (status === 'EM ANDAMENTO') {
                        // No Reprocesso, talvez você não queira o botão "Editar" de cabeçalho na lista,
                        // mas pode manter para o carregamento normal
                        if (!isReprocesso) {
                            btnHtml += `<button class="btn btn-warning btn-sm btn-editar-header me-1" data-id="${id}" title="Editar Cabeçalho">
                                <i class="fas fa-pencil-alt"></i> Editar
                            </button>`;
                        }

                        // Ações de cancelamento ficam no menu "Mais"
                        menuItens += `<li><a class="dropdown-item text-danger btn-cancelar" href="#" data-id="${id}">
                            <i class="fas fa-times-circle me-2"></i>Cancelar</a></li>`;
                    }

                    // 3. STATUS: FINALIZADO
                    else if (status === 'FINALIZADO') {
                        // Botão de Impressão (Sempre útil em finalizados)
                        btnHtml += `<a href="index.php?page=carregamento_relatorio&id=${id}" target="_blank" class="btn btn-secondary btn-sm me-1" title="Imprimir">
                            <i class="fas fa-print"></i>
                        </a>`;

                        // Reabrir fica no menu "Mais"
                        menuItens += `<li><a class="dropdown-item btn-reabrir" href="#" data-id="${id}">
                            <i class="fas fa-undo me-2"></i>Reabrir</a></li>`;
                    }

                    // 4. STATUS: CANCELADO
                    else if (status === 'CANCELADO') {
                        // Em cancelados, geralmente só permitimos reabrir ou excluir
                        menuItens += `<li><a class="dropdown-item btn-reabrir" href="#" data-id="${id}">
                            <i class="fas fa-undo me-2"></i>Reativar / Reabrir</a></li>`;
                    }

                    // 5. AÇÃO GLOBAL: EXCLUIR (Sempre no menu "Mais" e com destaque)
                    if (menuItens !== '') { menuItens += `<li><hr class="dropdown-divider"></li>`; }
                    menuItens += `<li><a class="dropdown-item text-danger btn-excluir" href="#" data-id="${id}">
                        <i class="fas fa-trash me-2"></i>Excluir Permanente</a></li>`;

                    // Montagem do Dropdown
                    let acoesHtml = `<div class="d-flex justify-content-center">${btnHtml}`;

                    if (menuItens) {
                        acoesHtml += `
                            <div class="dropdown">
                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Mais
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    ${menuItens}
                                </ul>
                            </div>`;
                                }

                    acoesHtml += `</div>`;
                    return acoesHtml;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[1, 'desc']]
    });

    // 2. Carregar OEs de Reprocesso no Select ao abrir o modal
    // Definição da Configuração para Dropdown Seleção Saida Reprocesso 
    const configurarSelect2Reprocesso = () => {
        $('#select-oe-reprocesso').select2({
            theme: 'bootstrap-5', // Se estiver usando o tema do Bootstrap
            placeholder: 'Pesquisar Ordem de Reprocesso...',
            allowClear: true,
            minimumInputLength: 0, // Permite listar as primeiras 20 ao clicar
            dropdownParent: $('#modal-nova-saida-reprocesso'), // Evita bug de foco em modais
            ajax: {
                url: 'ajax_router.php?action=getOrdensParaCarregamentoSelect&tipo=REPROCESSO',
                type: 'POST',
                dataType: 'json',
                delay: 250, // Espera o usuário parar de digitar (economiza servidor)
                data: params => ({
                    term: params.term,
                    csrf_token: csrfToken
                }),
                processResults: data => ({
                    results: data.results // O Repository já retorna no formato {id, text}
                }),
                cache: true
            }
        });
    };

    // 2. Chamada da Inicialização
    configurarSelect2Reprocesso();

    // Ao abrir o modal de Saída Reprocesso
    $('#modal-nova-saida-reprocesso').on('show.bs.modal', function () {
        const $form = $('#form-nova-saida-reprocesso');

        // 1. Resetar os campos nativos do formulário
        $form[0].reset();

        // 2. Resetar os campos Select2 (importante para não vir com a OE anterior)
        $('#select-oe-reprocesso').val(null).trigger('change');
        $('#car_transportadora_id_repro').val(null).trigger('change');

        // 3. Buscar o próximo número 
        $.post('ajax_router.php?action=getProximoNumeroCarregamento', { csrf_token: csrfToken }, function (response) {
            if (response.success) {
                $('#car_numero_repro').val(response.proximo_numero);
            }
        }, 'json');

        // 4. Definir a data atual como padrão
        $('#car_data_repro').val(new Date().toISOString().split('T')[0]);
    });

    // 3. Salvar Nova Saída
    $('#form-nova-saida-reprocesso').on('submit', function (e) {
        e.preventDefault();

        // 1. Pegamos os valores dos campos específicos da tela de reprocesso
        const numero = $('#car_numero_repro').val();
        const dataCar = $('#car_data_repro').val();
        // const transportadora = $('#car_transportadora_id_repro').val();
        const oeId = $('#select-oe-reprocesso').val();


        // 2. Montamos o objeto de dados exatamente como o Repository espera
        // Ignoramos o serialize() aqui para ter controle total dos nomes das chaves
        const formData = {
            car_numero: numero, // Aqui convertemos 'car_numero_repro' para 'car_numero'
            car_data: dataCar,
            car_entidade_id_organizador: 9, // Fixo para Marchef
            // car_transportadora_id: transportadora,
            car_ordem_expedicao_id: oeId,
            car_tipo: 'REPROCESSO',
            csrf_token: $('input[name="csrf_token"]').val()
        };

        $.ajax({
            url: 'ajax_router.php?action=salvarCarregamentoHeader',
            type: 'POST',
            data: formData, // Enviamos o objeto mapeado
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#modal-nova-saida-reprocesso').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Saída de Reprocesso criada com sucesso!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Redireciona para a mesma tela de detalhes, que já funciona para qualquer tipo
                        window.location.href = `index.php?page=carregamento_detalhes&id=${response.carregamento_id}`;
                    });
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function () {
                console.error(xhr.responseText); // Ajuda a debugar se houver erro
                Swal.fire('Erro', 'Erro interno no servidor.', 'error');
            }
        });
    });

    // Função auxiliar para ações simples (Cancelar e Excluir)
    function handleAcaoReprocesso(id, action, title, text, successMessage) {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, confirmar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_router.php?action=' + action, {
                    carregamento_id: id,
                    csrf_token: csrfToken
                }, function (response) {
                    if (response.success) {
                        Swal.fire('Sucesso!', successMessage, 'success');
                        dataTable.ajax.reload();
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    // Evento: Cancelar Saída de Reprocesso
    $tabela.on('click', '.btn-cancelar', function () {
        const id = $(this).data('id');
        handleAcaoReprocesso(id, 'cancelarCarregamento',
            'Cancelar Saída de Reprocesso?',
            'Esta ação irá cancelar a saída. Nenhum estoque será movimentado.',
            'Saída de reprocesso cancelada.'
        );
    });

    // Evento: Excluir Saída de Reprocesso
    $tabela.on('click', '.btn-excluir', function (e) {
        e.preventDefault();
        const id = $(this).data('id');

        handleAcaoReprocesso(id, 'excluirCarregamento',
            'Excluir Saída de Reprocesso?',
            'Esta ação é IRREVERSÍVEL e apagará todos os dados vinculados. O estoque será estornado (se já tiver sido finalizada). Deseja continuar?',
            'Saída de reprocesso excluída permanentemente.'
        );
    });

    // Evento: Abrir Modal de Reabertura
    $tabela.on('click', '.btn-reabrir', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        // Tenta pegar o número da saída (coluna 0) para mostrar no modal
        const numero = $(this).closest('tr').find('td').eq(0).text();

        $('#reabrir-carregamento-id').val(id); // O ID do campo no modal pode continuar o mesmo
        $('#reabrir-carregamento-numero').text(numero);
        $('#reabrir-motivo').val('');

        $('#modal-reabrir-motivo').modal('show');
    });

    // Evento: Confirmar Reabertura (Botão dentro do Modal)
    $('#btn-confirmar-reabertura').on('click', function () {
        const id = $('#reabrir-carregamento-id').val();
        const motivo = $('#reabrir-motivo').val().trim();

        if (motivo === '') {
            Swal.fire('Erro', 'O motivo é obrigatório para reabrir.', 'error');
            return;
        }

        $.post('ajax_router.php?action=reabrirCarregamento', {
            carregamento_id: id,
            motivo: motivo,
            csrf_token: csrfToken
        }, function (response) {
            $('#modal-reabrir-motivo').modal('hide');
            if (response.success) {
                Swal.fire('Reaberto!', 'Saída reaberta e estoque estornado.', 'success');
                dataTable.ajax.reload();
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        }, 'json');
    });
});