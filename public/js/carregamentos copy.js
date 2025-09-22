// /public/js/carregamentos.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').first().val();
    const $modalNovoCarregamento = $('#modal-novo-carregamento');
    const $formNovoCarregamento = $('#form-novo-carregamento');

    // 1. Inicializar a DataTable
    $('#tabela-carregamentos').DataTable({
        "serverSide": true,
        "ajax": {
            "url": "ajax_router.php?action=listarCarregamentos", 
            "type": "POST",
            "data": { csrf_token: csrfToken }
        },
        "columns": [
            { "data": "car_numero" },
            {
                "data": "car_data",
                "render": function (data) {
                    if (!data) return '';
                    const date = new Date(data + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR');
                }
            },
            { "data": "oe_numero" }, 
            { "data": "car_motorista_nome" },
            { "data": "car_placas" },
            { "data": "car_status" },
            {
                "data": "car_id",
                "orderable": false,
                "className": "text-center",
                "render": function (data, type, row) {
                    return `<a href="index.php?page=detalhes_carregamento&id=${data}" class="btn btn-warning btn-sm">Detalhes</a>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
        "order": [[1, 'desc']]
    });

    // 2. Lógica do Modal de Novo Carregamento

    // Ao abrir o modal, buscar o próximo número
    $modalNovoCarregamento.on('show.bs.modal', function () {
        $formNovoCarregamento[0].reset();

        // Busca o próximo número de carregamento
        $.post('ajax_router.php?action=getProximoNumeroCarregamento', { csrf_token: csrfToken }, function (response) {
            if (response.success) {
                $('#car_numero').val(response.proximo_numero);
            }
        }, 'json');

        // Pré-seta a data de hoje
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
            data: function (params) {
                return { term: params.term };
            },
            processResults: function (data) {
                return { results: data.data };
            }
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
            data: function (params) {
                return { term: params.term, csrf_token: csrfToken }; // Envia CSRF
            },
            type: 'POST', 
            processResults: function (data) {
                return { results: data.results };
            }
        }
    });

    // 3. Salvar o Cabeçalho do Novo Carregamento
    $formNovoCarregamento.on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'ajax_router.php?action=salvarCarregamentoHeader', 
            type: 'POST',
            data: $(this).serialize(),
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
                        // Redireciona para a nova página de detalhes
                        window.location.href = `index.php?page=detalhes_carregamento&id=${response.carregamento_id}`;
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
});