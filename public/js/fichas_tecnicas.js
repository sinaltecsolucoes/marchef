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

        /*     $('#ficha_produto_id').on('change', function () {
                 const produtoId = $(this).val();
                 const $produtoInfoDisplay = $('#produto-info-display');
     
                 if (!produtoId) {
                     $produtoInfoDisplay.hide().empty();
                     return;
                 }
     
                 function formatarPeso(peso) {
                     if (peso === null || peso === undefined) return 'N/A';
                     const numero = parseFloat(peso);
                     if (numero % 1 === 0) {
                         return numero.toFixed(0) + ' kg';
                     } else {
                         return numero.toFixed(3) + ' kg';
                     }
                 }
     
                 $.post('ajax_router.php?action=getProdutoDetalhesParaFicha', { produto_id: produtoId, csrf_token: csrfToken }, function (response) {
                     if (response.success) {
                         const p = response.data;
     
                         const pesoPrimarioFormatado = formatarPeso(p.peso_embalagem_primaria);
                         const pesoSecundarioFormatado = formatarPeso(p.prod_peso_embalagem);
     
                         // --- AQUI ESTÁ A MUDANÇA NO LAYOUT ---
                         const infoHtml = `
                     <div class="row">
                         <div class="col-md-6"><small class="text-muted">Denominação (Classe):</small><p class="fw-bold">${p.prod_classe || 'N/A'}</p></div>
                         <div class="col-md-3"><small class="text-muted">Marca:</small><p class="fw-bold">${p.prod_marca || 'N/A'}</p></div>
                         <div class="col-md-3"><small class="text-muted">NCM:</small><p class="fw-bold">${p.prod_ncm || 'N/A'}</p></div>
                     </div>
                     <div class="row mt-2">
                         <div class="col-md-3"><small class="text-muted">Emb. Primária:</small><p class="fw-bold">${pesoPrimarioFormatado}</p></div>
                         <div class="col-md-3"><small class="text-muted">Emb. Secundária:</small><p class="fw-bold">${pesoSecundarioFormatado}</p></div>
                         <div class="col-md-3"><small class="text-muted">EAN-13 (Primário):</small><p class="fw-bold">${p.ean13_final || 'N/A'}</p></div>
                         <div class="col-md-3"><small class="text-muted">DUN-14:</small><p class="fw-bold">${p.prod_dun14 || 'N/A'}</p></div>
                     </div>
                 `;
                         $produtoInfoDisplay.html(infoHtml).slideDown();
                     } else {
                         $produtoInfoDisplay.hide().empty();
                     }
                 }, 'json');
             }); */


        $('#ficha_produto_id').on('change', function () {
            const produtoId = $(this).val();
            const $produtoInfoDisplay = $('#produto-info-display');

            if (!produtoId) {
                $produtoInfoDisplay.hide().empty();
                return;
            }

            function formatarPeso(peso) {
                if (peso === null || peso === undefined) return 'N/A';
                const numero = parseFloat(peso);
                if (numero % 1 === 0) {
                    return numero.toFixed(0) + ' kg';
                } else {
                    return numero.toFixed(3) + ' kg';
                }
            }

            $.post('ajax_router.php?action=getProdutoDetalhesParaFicha', { produto_id: produtoId, csrf_token: csrfToken }, function (response) {
                if (response.success) {
                    const p = response.data;

                    const pesoPrimarioFormatado = formatarPeso(p.peso_embalagem_primaria);
                    const pesoSecundarioFormatado = formatarPeso(p.prod_peso_embalagem);
                    const validade = p.prod_validade_meses ? `${p.prod_validade_meses} meses` : 'N/A';

                    // Layout com 6 colunas na segunda linha para melhor alinhamento
                    const infoHtml = `
                <div class="row">
                    <div class="col-md-6"><small class="text-muted">Denominação (Classe):</small><p class="fw-bold">${p.prod_classe || 'N/A'}</p></div>
                    <div class="col-md-3"><small class="text-muted">Marca:</small><p class="fw-bold">${p.prod_marca || 'N/A'}</p></div>
                    <div class="col-md-3"><small class="text-muted">NCM:</small><p class="fw-bold">${p.prod_ncm || 'N/A'}</p></div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-2"><small class="text-muted">Emb. Primária:</small><p class="fw-bold">${pesoPrimarioFormatado}</p></div>
                    <div class="col-md-2"><small class="text-muted">Emb. Secundária:</small><p class="fw-bold">${pesoSecundarioFormatado}</p></div>
                    <div class="col-md-2"><small class="text-muted">Validade:</small><p class="fw-bold">${validade}</p></div>
                    <div class="col-md-3"><small class="text-muted">EAN-13 (Primário):</small><p class="fw-bold">${p.ean13_final || 'N/A'}</p></div>
                    <div class="col-md-3"><small class="text-muted">DUN-14:</small><p class="fw-bold">${p.prod_dun14 || 'N/A'}</p></div>
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

    $('#form-ficha-geral').on('submit', function (e) {
        e.preventDefault(); // Impede o envio tradicional do formulário

        const $btn = $('#btn-salvar-ficha-geral');
        const originalHtml = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Salvando...').prop('disabled', true);

        const formData = $(this).serialize(); // Pega todos os dados do formulário

        $.post('ajax_router.php?action=salvarFichaTecnicaGeral', formData, function (response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });

                // Atualiza o ID oculto no formulário com o ID retornado pelo backend
                $('#ficha_id').val(response.ficha_id);

                // Desabilita o select de produto para não poder ser trocado após salvar
                $('#ficha_produto_id').prop('disabled', true);

                // Habilita e ativa a próxima aba (Critérios Laboratoriais)
                const criteriosTab = new bootstrap.Tab($('#criterios-tab'));
                $('#criterios-tab').prop('disabled', false).removeClass('disabled');
                criteriosTab.show();

            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        }, 'json').fail(function () {
            Swal.fire('Erro de Conexão', 'Não foi possível se comunicar com o servidor.', 'error');
        }).always(function () {
            // Restaura o botão ao estado original
            $btn.html(originalHtml).prop('disabled', false);
        });
    });
});