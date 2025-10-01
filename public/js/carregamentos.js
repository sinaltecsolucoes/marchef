// /public/js/carregamentos.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').first().val();
    const $modalNovoCarregamento = $('#modal-novo-carregamento');
    const $formNovoCarregamento = $('#form-novo-carregamento');
    const $tabelaCarregamentos = $('#tabela-carregamentos'); // Cache da tabela

    // 1. Inicializar a DataTable
    const dataTable = $tabelaCarregamentos.DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarCarregamentos",
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            {
                "data": "car_numero",
                "className": "text-center align-middle",
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
                "className": "text-center align-middle"
            },
            {
                "data": "car_motorista_nome",
                "className": "text-center align-middle"
            },
            {
                "data": "car_placas",
                "className": "text-center align-middle"
            },
            {
                "data": "car_status",
                "className": "text-center align-middle"
            },
            {
                "data": "car_id",
                "orderable": false,
                "className": "text-center align-middle",
                "render": function (data, type, row) {
                    let btnDetalhes = `<a href="index.php?page=carregamento_detalhes&id=${data}" class="btn btn-warning btn-sm me-1" title="Detalhes/Editar"><i class="fas fa-pencil-alt me-1"></i>Editar</a>`;

                    let btnExcluir = '';
                    let btnCancelar = '';
                    let btnReabrir = '';


                    if (row.car_status === 'EM ANDAMENTO' || row.car_status === 'AGUARDANDO CONFERENCIA') {
                        btnCancelar = `<button class="btn btn-secondary btn-sm me-1 btn-cancelar" data-id="${data}" title="Cancelar"><i class="fas fa-times me-1"></i>Cancelar</button>`;
                    }

                    if (row.car_status === 'FINALIZADO' || row.car_status === 'CANCELADO') {
                        btnReabrir = `<button class="btn btn-warning btn-sm me-1 btn-reabrir" data-id="${data}" title="Reabrir"><i class="fas fa-redo me-1"></i>Reabrir</button>`;
                    }

                    // Só pode excluir se NÃO estiver finalizado
                    if (row.car_status !== 'FINALIZADO') {
                        btnExcluir = `<button class="btn btn-danger btn-sm me-1 btn-excluir" data-id="${data}" title="Excluir Permanentemente"><i class="fas fa-trash-alt me-1"></i>Excluir</button>`;
                    }

                    return `<div class="btn-group">${btnDetalhes}${btnReabrir}${btnExcluir}${btnCancelar}</div>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[1, 'desc']]
    });

    // 2. Lógica do Modal de Novo Carregamento

    // Ao abrir o modal
    $modalNovoCarregamento.on('show.bs.modal', function () {
        $formNovoCarregamento[0].reset();

        // Resetar Select2
        $('#car_entidade_id_organizador').val(null).trigger('change');
        $('#car_transportadora_id').val(null).trigger('change');
        $('#car_ordem_expedicao_id').val(null).trigger('change');

        $.post('ajax_router.php?action=getProximoNumeroCarregamento', { csrf_token: csrfToken }, function (response) {
            if (response.success) {
                $('#car_numero').val(response.proximo_numero);
            }
        }, 'json');

        $('#car_data').val(new Date().toISOString().split('T')[0]);
    });

    // Inicializar Select2 para Cliente Responsável
    $('#car_entidade_id_organizador').select2({
        placeholder: "Selecione o cliente responsável",
        dropdownParent: $modalNovoCarregamento,
        theme: "bootstrap-5",
        ajax: {
            url: 'ajax_router.php?action=getClienteOptions',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { term: params.term }; },
            processResults: function (data) { return { results: data.data }; }
        }
    });

    // *** Inicializar Select2 para Transportadora ***
    $('#car_transportadora_id').select2({
        placeholder: "Selecione a transportadora",
        dropdownParent: $modalNovoCarregamento,
        theme: "bootstrap-5",
        allowClear: true, // Permite limpar o campo
        ajax: {
            url: 'ajax_router.php?action=getTransportadoraOptions',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { term: params.term }; },
            processResults: function (data) { return { results: data.results }; }
        }
    });

    // Inicializar Select2 para Ordem de Expedição (Base)
    $('#car_ordem_expedicao_id').select2({
        placeholder: "Selecione a Ordem de Expedição (base)",
        dropdownParent: $modalNovoCarregamento,
        theme: "bootstrap-5",
        ajax: {
            url: 'ajax_router.php?action=getOrdensParaCarregamentoSelect',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { term: params.term, csrf_token: csrfToken }; },
            type: 'POST',
            processResults: function (data) { return { results: data.results }; }
        }
    });

    // Evento para definir o tipo de carregamento com base na seleção da OE
    $('#car_ordem_expedicao_id').on('change', function () {
        const oeId = $(this).val();
        if (oeId) {
            $('#car_tipo').val('ORDEM_EXPEDICAO');
        } else {
            $('#car_tipo').val('AVULSA');
        }
    });

    // *** Inicializar Máscaras (Placa e CPF) ***

    // Máscara de CPF
    $('#car_motorista_cpf').mask('000.000.000-00', { clearIfNotMatch: true });

    // Máscara de PLACA (exatamente como você enviou)
    $('#car_placas').mask('SSS-0A00 / SSS-0A00', {
        translation: {
            'S': { pattern: /[A-Za-z]/ }, // Aceita Letras
            'A': { pattern: /[A-Za-z0-9]/ } // Aceita Letra ou Número (para Mercosul)
        },
        onKeyPress: function (val, e, field, options) {
            // 1. Força tudo para MAIÚSCULAS
            field.val(val.toUpperCase());

            // 2. Lógica para pular a barra '/'
            if (val.length === 8) {
                if (val.charAt(7) !== ' ') {
                    let charExtra = val.charAt(7);
                    let newVal = val.substring(0, 7) + ' / ' + charExtra;
                    field.val(newVal);
                    field.mask('SSS-0A00 / SSS-0A00', options); // Reaplica
                }
            }
        },
        // 3. Não apaga o campo se digitar só a primeira placa
        clearIfNotMatch: true
    });

    // 3. Salvar o Cabeçalho
    $formNovoCarregamento.on('submit', function (e) {
        e.preventDefault();

        const $cpfField = $('#car_motorista_cpf');
        const cpfLimpo = $cpfField.cleanVal(); // Pega o valor limpo
        const formData = $(this).serialize() + '&car_motorista_cpf_limpo=' + cpfLimpo;

        $.ajax({
            url: 'ajax_router.php?action=salvarCarregamentoHeader',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $modalNovoCarregamento.modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Carregamento criado. Redirecionando para os detalhes...',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = `index.php?page=carregamento_detalhes&id=${response.carregamento_id}`;
                    });
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro', 'Não foi possível conectar ao servidor.', 'error');
            }
        });
    });

    function handleAcaoCarregamento(id, action, title, text, successMessage) {
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
                        dataTable.ajax.reload(); // Recarrega a tabela
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    // Cancelar
    $tabelaCarregamentos.on('click', '.btn-cancelar', function () {
        const id = $(this).data('id');
        handleAcaoCarregamento(id, 'cancelarCarregamento',
            'Cancelar Carregamento?',
            'Esta ação irá cancelar o carregamento. Nenhum estoque será baixado.',
            'Carregamento cancelado.'
        );
    });

    // Reabrir
    $tabelaCarregamentos.on('click', '.btn-reabrir', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        const numero = $(this).closest('tr').find('td').eq(0).text(); // Pega o número da tabela

        $('#reabrir-carregamento-id').val(id);
        $('#reabrir-carregamento-numero').text(numero);
        $('#reabrir-motivo').val(''); // Limpa o campo

        $('#modal-reabrir-motivo').modal('show');
    });

    $('#btn-confirmar-reabertura').on('click', function () {
        const id = $('#reabrir-carregamento-id').val();
        const motivo = $('#reabrir-motivo').val().trim();

        if (motivo === '') {
            Swal.fire('Erro', 'O motivo é obrigatório para reabrir o carregamento.', 'error');
            return;
        }

        // Faz a chamada AJAX (com a action na URL)
        $.post('ajax_router.php?action=reabrirCarregamento', {
            carregamento_id: id,
            motivo: motivo, // Envia o motivo no POST
            csrf_token: csrfToken
        }, function (response) {
            $('#modal-reabrir-motivo').modal('hide');
            if (response.success) {
                Swal.fire('Reaberto!', 'Carregamento reaberto e estoque estornado.', 'success');
                dataTable.ajax.reload();
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        }, 'json');
    });

    $tabelaCarregamentos.on('click', '.btn-excluir', function (e) {
        e.preventDefault(); // Necessário
        const id = $(this).data('id');

        // Usamos a mesma função 'handleAcaoCarregamento'
        handleAcaoCarregamento(id, 'excluirCarregamento', // <-- Nova Action
            'Excluir Carregamento?',
            'Esta ação é IRREVERSÍVEL e apagará o carregamento, filas e itens. O estoque será estornado (se aplicável). Deseja continuar?',
            'Carregamento excluído permanentemente.'
        );
    });
});