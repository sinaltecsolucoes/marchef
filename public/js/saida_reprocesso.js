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
                    let btnDetalhes = `<a href="index.php?page=carregamento_detalhes&id=${data}" 
                                        class="btn btn-warning btn-sm me-1 d-inline-flex align-items-center" 
                                        title="Detalhes/Editar"><i class="fas fa-pencil-alt me-1"></i>Editar</a>`;

                    let btnExcluir = '';
                    let btnCancelar = '';
                    let btnReabrir = '';


                    if (row.car_status === 'EM ANDAMENTO' || row.car_status === 'AGUARDANDO CONFERENCIA') {
                        btnCancelar = `<button class="btn btn-secondary btn-sm btn-cancelar me-1 d-inline-flex align-items-center" 
                                        data-id="${data}" title="Cancelar"><i class="fas fa-times me-1"></i>Cancelar</button>`;
                    }

                    if (row.car_status === 'FINALIZADO' || row.car_status === 'CANCELADO') {
                        btnReabrir = `<button class="btn btn-info btn-sm btn-reabrir me-1 d-inline-flex align-items-center" 
                                        data-id="${data}" 
                                        title="Reabrir"><i class="fas fa-redo me-1"></i>Reabrir</button>`;
                    }

                    // Só pode excluir se NÃO estiver finalizado
                    if (row.car_status !== 'FINALIZADO') {
                        btnExcluir = `<button class="btn btn-danger btn-sm btn-excluir me-1 d-inline-flex align-items-center" 
                                        data-id="${data}" 
                                        title="Excluir Permanentemente"><i class="fas fa-trash-alt me-1"></i>Excluir</button>`;
                    }

                    return `<div class="btn-group">${btnDetalhes}${btnReabrir}${btnExcluir}${btnCancelar}</div>`;
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
});