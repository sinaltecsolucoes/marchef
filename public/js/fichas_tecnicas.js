// /public/js/fichas_tecnicas.js
$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Verifica se estamos na página de detalhes
    if ($('#main-title').length) {

        // Inicializa o Select2 para Produtos
        $('#ficha_produto_id').select2({
            placeholder: "Selecione um produto...",
            theme: "bootstrap-5",
            ajax: {
                url: "ajax_router.php?action=getProdutosSemFichaTecnica",
                dataType: 'json',
                delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return data; }
            }
        });

        // Inicializa o Select2 para Fabricantes
        $('#ficha_fabricante_id').select2({
            placeholder: "Selecione um fabricante (opcional)...",
            theme: "bootstrap-5",
            allowClear: true,
            ajax: {
                url: "ajax_router.php?action=getFabricanteOptionsFT",
                dataType: 'json',
                delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return data; }
            }
        });

        const $produtoInfoDisplay = $('#produto-info-display');

        // Evento que dispara quando um produto é selecionado
        $('#ficha_produto_id').on('change', function () {
            const produtoId = $(this).val();

            if (!produtoId) {
                $produtoInfoDisplay.hide().empty();
                return;
            }

            // Busca os detalhes do produto selecionado via AJAX
            $.post('ajax_router.php?action=getProdutoDetalhesParaFicha', { produto_id: produtoId, csrf_token: csrfToken }, function (response) {
                if (response.success) {
                    const p = response.data;
                    // Constrói um HTML simples com os dados e o exibe
                    const infoHtml = `
                        <div class="row">
                            <div class="col-md-4"><small class="text-muted">Denominação (Classe):</small><p class="fw-bold">${p.prod_classe || 'N/A'}</p></div>
                            <div class="col-md-4"><small class="text-muted">Marca:</small><p class="fw-bold">${p.prod_marca || 'N/A'}</p></div>
                            <div class="col-md-4"><small class="text-muted">NCM:</small><p class="fw-bold">${p.prod_ncm || 'N/A'}</p></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4"><small class="text-muted">Embalagem Primária:</small><p>${p.prod_peso_embalagem} kg</p></div>
                            <div class="col-md-4"><small class="text-muted">EAN-13:</small><p>${p.prod_ean13 || 'N/A'}</p></div>
                            <div class="col-md-4"><small class="text-muted">DUN-14:</small><p>${p.prod_dun14 || 'N/A'}</p></div>
                        </div>
                    `;
                    $produtoInfoDisplay.html(infoHtml).slideDown();
                } else {
                    $produtoInfoDisplay.hide().empty();
                }
            }, 'json');
        });

        // Verifica se há dados de uma cópia na memória do navegador
        const dadosCopiados = sessionStorage.getItem('fichaTecnicaCopiada');

        if (dadosCopiados) {
            // Limpa a memória para não usar de novo por acidente
            sessionStorage.removeItem('fichaTecnicaCopiada');

            const ficha = JSON.parse(dadosCopiados);

            console.log('Preenchendo formulário com dados copiados:', ficha);

            // Futuramente, aqui entrará a lógica para preencher todos os campos do seu formulário
            // de detalhes com os dados do objeto 'ficha'.
            // Ex: $('#select-produto').val(ficha.header.ficha_produto_id).trigger('change');
            // Ex: $('#ficha_conservantes').val(ficha.header.ficha_conservantes);

            $('#main-title').text('Nova Ficha Técnica (Cópia)');

            Swal.fire({
                icon: 'info',
                title: 'Ficha Copiada',
                text: 'Os dados foram pré-preenchidos. Verifique as informações e salve como uma nova ficha.',
                timer: 3000,
                showConfirmButton: false
            });
        }
    }

    // Adicionamos um IF para garantir que este código só rode na página de listagem
    if ($('#tabela-fichas-tecnicas').length) {
        $('#tabela-fichas-tecnicas').DataTable({
            "serverSide": true,
            "ajax": {
                "url": "ajax_router.php?action=listarFichasTecnicas",
                "type": "POST",
                "data": { csrf_token: csrfToken }
            },
            "columns": [
                { "data": "ficha_id" },
                { "data": "prod_descricao" },
                { "data": "prod_marca" },
                { "data": "prod_ncm" },
                {
                    "data": "ficha_data_modificacao",
                    "render": function (data) {
                        if (!data) return '';
                        return new Date(data).toLocaleString('pt-BR');
                    }
                },
                {
                    "data": "ficha_id",
                    "orderable": false,
                    "className": "text-center",
                    "render": function (data, type, row) {
                        let btnEditar = `<a href="index.php?page=ficha_tecnica_detalhes&id=${data}" class="btn btn-warning btn-sm me-1">Editar</a>`;
                        let btnCopiar = `<button class="btn btn-info btn-sm btn-copiar-ficha" data-id="${data}">Copiar</button>`;
                        return `<div class="btn-group">${btnEditar}${btnCopiar}</div>`;
                    }
                }
            ],
            "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" },
            "order": [[4, 'desc']]
        });
    }

    // Evento de clique para o botão de copiar (pode ficar aqui ou dentro do IF)
    $('#tabela-fichas-tecnicas').on('click', '.btn-copiar-ficha', function () {
        const fichaId = $(this).data('id');
        $.post('ajax_router.php?action=getFichaTecnicaCompleta', { ficha_id: fichaId, csrf_token: csrfToken }, function (response) {
            if (response.success) {
                sessionStorage.setItem('fichaTecnicaCopiada', JSON.stringify(response.data));
                window.location.href = 'index.php?page=ficha_tecnica_detalhes';
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        }, 'json');
    });
});