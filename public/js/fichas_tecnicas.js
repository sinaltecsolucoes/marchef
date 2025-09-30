// /public/js/fichas_tecnicas.js

$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // --- LÓGICA DA PÁGINA DE LISTAGEM ---
    if ($('#tabela-fichas-tecnicas').length) {
        $('#tabela-fichas-tecnicas').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: "ajax_router.php?action=listarFichasTecnicas",
                type: "POST",
                data: { csrf_token: csrfToken }
            },
            columns: [
                {
                    data: "ficha_id",
                    render: data => String(data).padStart(4, '0')
                },
                { data: "prod_descricao" },
                { data: "prod_marca" },
                { data: "prod_ncm" },
                {
                    data: "ficha_data_modificacao",
                    render: data => data ? new Date(data).toLocaleString('pt-BR') : ''
                },
                {
                    data: "ficha_id",
                    orderable: false,
                    className: "text-center",
                    render: function (data, type, row) {
                        let btnEditar = `<a href="index.php?page=ficha_tecnica_detalhes&id=${data}" class="btn btn-warning btn-sm me-1" title="Editar"><i class="fas fa-pencil-alt me-1"></i>Editar</a>`;
                        let btnCopiar = `<button class="btn btn-info btn-sm btn-copiar-ficha me-1" data-id="${data}" title="Copiar"><i class="fas fa-copy me-1"></i>Copiar</button>`;
                        let btnExcluir = `<button class="btn btn-danger btn-sm btn-excluir-ficha" data-id="${data}" data-nome="${row.prod_descricao}" title="Excluir"><i class="fas fa-trash-alt me-1"></i>Excluir</button>`;
                        return `<div class="btn-group">${btnEditar}${btnCopiar}${btnExcluir}</div>`;
                    }
                }
            ],
            language: { url: `${BASE_URL}/libs/DataTables-1.10.23/Portuguese-Brasil.json` },
            order: [[4, 'desc']]
        });

        $('#tabela-fichas-tecnicas').on('click', '.btn-copiar-ficha', function () {
            const fichaId = $(this).data('id');
            $.post('ajax_router.php?action=getFichaTecnicaCompleta', {
                ficha_id: fichaId,
                csrf_token: csrfToken
            },
                (response) => {
                    if (response.success) {
                        sessionStorage.setItem('fichaTecnicaCopiada',
                            JSON.stringify(response.data));
                        window.location.href = 'index.php?page=ficha_tecnica_detalhes';
                    } else { Swal.fire('Erro', response.message, 'error'); }
                }, 'json');
        });

        $('#tabela-fichas-tecnicas').on('click', '.btn-excluir-ficha', function () {
            const fichaId = $(this).data('id');
            const produtoNome = $(this).data('nome');
            Swal.fire({
                title: 'Tem certeza?',
                text: `Deseja realmente excluir a ficha técnica do produto "${produtoNome}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sim, excluir!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('ajax_router.php?action=excluirFichaTecnica', {
                        ficha_id: fichaId,
                        csrf_token: csrfToken
                    },
                        (response) => {
                            if (response.success) {
                                Swal.fire('Excluído!', 'A ficha foi excluída.', 'success');
                                $('#tabela-fichas-tecnicas').DataTable().ajax.reload();
                            } else { Swal.fire('Erro!', response.message, 'error'); }
                        }, 'json');
                }
            });
        });
    }

    // --- LÓGICA DA PÁGINA DE DETALHES ---
    if ($('#main-title').length) {
        // --- LÓGICA DAS ABAS 1 E 2 ---
        const $produtoInfoDisplay = $('#produto-info-display');
        const select2ProdutoConfig = {
            placeholder: "Selecione um produto...",
            theme: "bootstrap-5",
            ajax: {
                url: "ajax_router.php?action=getProdutosSemFichaTecnica",
                dataType: 'json',
                delay: 250,
                data: p => ({ term: p.term }),
                processResults: d => d
            }
        };
        function formatarPeso(peso) {
            if (peso === null || peso === undefined) return 'N/A';
            const numero = parseFloat(peso);
            return (numero % 1 === 0) ? numero.toFixed(0) + ' kg' : numero.toFixed(3) + ' kg';
        }

        function preencherFormularioFicha(fichaData, isCopy = false) {
            const header = fichaData.header;
            if (!header) return;
            $('#ficha_id').val(isCopy ? '' : header.ficha_id);
            $('#ficha_conservantes').val(header.ficha_conservantes);
            $('#ficha_alergenicos').val(header.ficha_alergenicos);
            $('#ficha_temp_estocagem_transporte').val(header.ficha_temp_estocagem_transporte);
            $('#ficha_origem').val(header.ficha_origem);
            $('#ficha_registro_embalagem').val(header.ficha_registro_embalagem);
            $('#ficha_desc_emb_primaria').val(header.ficha_desc_emb_primaria);
            $('#ficha_desc_emb_secundaria').val(header.ficha_desc_emb_secundaria);
            $('#ficha_medidas_emb_primaria').val(header.ficha_medidas_emb_primaria);
            $('#ficha_medidas_emb_secundaria').val(header.ficha_medidas_emb_secundaria);
            $('#ficha_paletizacao').val(header.ficha_paletizacao);
            $('#ficha_gestao_qualidade').val(header.ficha_gestao_qualidade);
            if (header.ficha_fabricante_id && header.fabricante_nome) {
                $('#ficha_fabricante_id').append(new Option(
                    header.fabricante_nome,
                    header.ficha_fabricante_id,
                    true, true)).trigger('change');
            }
            if (header.ficha_produto_id && header.produto_nome) {
                const $selectProduto = $('#ficha_produto_id');
                $selectProduto.select2('destroy').prop('disabled', true);
                $selectProduto.html(new Option(
                    header.produto_nome,
                    header.ficha_produto_id,
                    true, true)).trigger('change');
            }
            $('#criterios-tab, #midia-tab').prop('disabled', false).removeClass('disabled');
            $('#main-title').text(isCopy ? 'Nova Ficha Técnica (Cópia)' : 'Editar Ficha Técnica #' + header.ficha_id);
            if (isCopy) {
                $('#ficha_produto_id').prop('disabled', false).select2(select2ProdutoConfig);
            }
        }

        function resetarFormularioCriterios() {
            const $form = $('#form-ficha-criterios');
            $form[0].reset();
            $form.find('#criterio_id').val('');
            const $btnSalvar = $('#btn-salvar-criterio');
            $btnSalvar.html('<i class="fas fa-plus me-2"></i>Adicionar').removeClass('btn-primary').addClass('btn-success');
            $('#btn-cancelar-edicao-criterio').remove();
        }

        function carregarCriterios(fichaId) {
            if (!fichaId) return;
            const $tbody = $('#tbody-criterios');
            $tbody.html('<tr><td colspan="5" class="text-center">Carregando...</td></tr>');
            $.get(`ajax_router.php?action=listarCriteriosFicha&ficha_id=${fichaId}`, function (response) {
                $tbody.empty();
                if (response.success && response.data.length > 0) {
                    response.data.forEach(criterio => {
                        const row = `<tr>
                                        <td class="text-center align-middle">${criterio.criterio_grupo}</td>
                                        <td class="text-center align-middle">${criterio.criterio_nome}</td>
                                        <td class="text-center align-middle">${criterio.criterio_unidade || ''}</td>
                                        <td class="text-center align-middle">${criterio.criterio_valor}</td>
                                        <td class="text-center align-middle">
                                            <button class="btn btn-sm btn-warning btn-editar-criterio me-1" title="Editar"><i class="fas fa-pencil-alt me-2"></i>Editar</button>
                                            <button class="btn btn-sm btn-danger btn-excluir-criterio" data-id="${criterio.criterio_id}" title="Excluir"><i class="fas fa-trash me-2"></i>Excluir</button>
                                            </td>
                                    </tr>`;
                        $(row).data('criterio-data', criterio).appendTo($tbody);
                    });
                } else {
                    $tbody.html('<tr><td colspan="5" class="text-center text-muted">Nenhum critério adicionado.</td></tr>');
                }
            }, 'json');
        }

        $('#ficha_produto_id').select2(select2ProdutoConfig);
        $('#ficha_fabricante_id').select2({
            placeholder: "Selecione um fabricante...",
            theme: "bootstrap-5",
            allowClear: true,
            ajax: {
                url: "ajax_router.php?action=getFabricanteOptionsFT",
                dataType: 'json',
                delay: 250, data: p => ({
                    term: p.term
                }),
                processResults: d => d
            }
        });

        $('#ficha_produto_id').on('change', function () {
            const produtoId = $(this).val();
            if (!produtoId) {
                $produtoInfoDisplay.hide().empty();
                return;
            }
            $.post('ajax_router.php?action=getProdutoDetalhesParaFicha', {
                produto_id: produtoId,
                csrf_token: csrfToken
            },
                function (response) {
                    if (response.success) {
                        const p = response.data;
                        const infoHtml = `<div class="row">
                                          <div class="col-md-6"><small class="text-muted">Denominação:</small><p class="fw-bold">${p.prod_classe || 'N/A'}</p></div>
                                          <div class="col-md-3"><small class="text-muted">Marca:</small><p class="fw-bold">${p.prod_marca || 'N/A'}</p></div>
                                          <div class="col-md-3"><small class="text-muted">NCM:</small><p class="fw-bold">${p.prod_ncm || 'N/A'}</p></div>
                                      </div>
                                      <div class="row mt-2">
                                            <div class="col-md-2"><small class="text-muted">Emb. Primária:</small><p class="fw-bold">${formatarPeso(p.peso_embalagem_primaria)}</p></div>
                                            <div class="col-md-2"><small class="text-muted">Emb. Secundária:</small><p class="fw-bold">${formatarPeso(p.prod_peso_embalagem)}</p></div>
                                            <div class="col-md-2"><small class="text-muted">Validade:</small><p class="fw-bold">${p.prod_validade_meses ? p.prod_validade_meses + ' meses' : 'N/A'}</p></div>
                                            <div class="col-md-3"><small class="text-muted">EAN-13:</small><p class="fw-bold">${p.ean13_final || 'N/A'}</p></div>
                                            <div class="col-md-3"><small class="text-muted">DUN-14:</small><p class="fw-bold">${p.prod_dun14 || 'N/A'}</p></div>
                                      </div>`;
                        $produtoInfoDisplay.html(infoHtml).slideDown();
                    } else { $produtoInfoDisplay.hide().empty(); }
                }, 'json');
        });

        $('#form-ficha-geral').on('submit', function (e) {
            e.preventDefault();
            const $btn = $('#btn-salvar-ficha-geral');
            const originalHtml = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Salvando...').prop('disabled', true);

            $('#ficha_produto_id').prop('disabled', false);
            const formSerialized = $(this).serialize();

            $('#ficha_produto_id').prop('disabled', true);

            $.post('ajax_router.php?action=salvarFichaTecnicaGeral', formSerialized, function (response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    $('#ficha_id').val(response.ficha_id);
                    $('#main-title').text('Editar Ficha Técnica #' + response.ficha_id);
                    $('#ficha_produto_id').prop('disabled', true);
                    $('#criterios-tab, #midia-tab').prop('disabled', false).removeClass('disabled');
                    new bootstrap.Tab($('#criterios-tab')).show();
                } else { Swal.fire('Erro', response.message, 'error'); }
            }, 'json')
                .fail(() => Swal.fire('Erro', 'Não foi possível se comunicar com o servidor.', 'error'))
                .always(() => $btn.html(originalHtml).prop('disabled', false));
        });

        $('#criterios-tab').on('shown.bs.tab', () => {
            const id = $('#ficha_id').val();
            $('#criterio_ficha_id').val(id);
            carregarCriterios(id);
        });

        $('#form-ficha-criterios').on('submit', function (e) {
            e.preventDefault();
            const $btn = $('#btn-salvar-criterio');
            $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
            const formData = $(this).serialize() + '&' + $.param({ csrf_token: csrfToken });
            $.post('ajax_router.php?action=salvarCriterioFicha', formData, function (response) {
                if (response.success) {
                    Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Critério salvo.', timer: 1500, showConfirmButton: false });
                    resetarFormularioCriterios();
                    carregarCriterios($('#ficha_id').val());
                } else { Swal.fire('Erro!', response.message, 'error'); }
            }, 'json').always(() => $btn.prop('disabled', false));
        });

        $('#tbody-criterios').on('click', '.btn-editar-criterio', function () {
            const data = $(this).closest('tr').data('criterio-data');
            $('#criterio_id').val(data.criterio_id);
            $('#criterio_grupo').val(data.criterio_grupo);
            $('#criterio_nome').val(data.criterio_nome);
            $('#criterio_unidade').val(data.criterio_unidade);
            $('#criterio_valor').val(data.criterio_valor);
            $('#btn-salvar-criterio').html('<i class="fas fa-save me-2"></i>Atualizar').removeClass('btn-success').addClass('btn-primary');
            if ($('#btn-cancelar-edicao-criterio').length === 0) {
                $('#botoes-acao-criterio').append(' <button type="button" class="btn btn-secondary ms-2" id="btn-cancelar-edicao-criterio"><i class="fas fa-times me-2"></i>Cancelar</button>');
            }
        });

        $('#form-ficha-criterios').on('click', '#btn-cancelar-edicao-criterio', resetarFormularioCriterios);

        $('#tbody-criterios').on('click', '.btn-excluir-criterio', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Tem certeza?',
                text: "Deseja realmente excluir este critério?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sim, excluir!'
            })
                .then((result) => {
                    if (result.isConfirmed) {
                        $.post('ajax_router.php?action=excluirCriterioFicha', {
                            criterio_id: id,
                            csrf_token: csrfToken
                        },
                            (response) => {
                                if (response.success) {
                                    Swal.fire('Excluído!', 'O critério foi removido.', 'success');
                                    carregarCriterios($('#ficha_id').val());
                                } else {
                                    Swal.fire('Erro!',
                                        response.message, 'error');
                                }
                            }, 'json');
                    }
                });
        });

        // --- LÓGICA DA ABA DE MÍDIA (COM CROPPER.JS) ---
        const placeholderImg = 'assets/img/placeholder.png';
        if ($('#modal-crop-image').length) {
            const modalCropElement = document.getElementById('modal-crop-image');
            const modalCrop = new bootstrap.Modal(modalCropElement);
            const imageToCrop = document.getElementById('image-to-crop');
            let cropper;
            let currentUploadInfo = {};

            function carregarFotos(fichaId) {
                if (!fichaId) return;
                $('.preview-container img').attr('src', placeholderImg);
                $('.btn-remover-foto').hide();
                $.get(`ajax_router.php?action=listarFotosFicha&ficha_id=${fichaId}`, function (response) {
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(foto => {
                            let idSufixo = '';
                            switch (foto.foto_tipo) {
                                case 'TABELA_NUTRICIONAL': idSufixo = 'nutricional'; break;
                                case 'EMBALAGEM_PRIMARIA': idSufixo = 'primaria'; break;
                                case 'EMBALAGEM_SECUNDARIA': idSufixo = 'secundaria'; break;
                                case 'SIF': idSufixo = 'sif'; break;
                            }
                            if (idSufixo) {
                                $(`#preview-${idSufixo}`).attr('src', foto.foto_path + '?t=' + new Date().getTime());
                                $(`.btn-remover-foto[data-tipo="${foto.foto_tipo}"]`).show();
                            }
                        });
                    }
                }, 'json');
            }

            $('#midia-tab').on('shown.bs.tab', () => carregarFotos($('#ficha_id').val()));

            $('#midia-pane').on('change', '.form-upload input[type="file"]',
                function () {
                    const fichaId = $('#ficha_id').val();
                    if (!fichaId) {
                        Swal.fire('Atenção!', 'Salve os "Dados Gerais" antes de enviar imagens.', 'warning');
                        $(this).val(''); return;
                    }
                    const file = this.files[0];
                    if (file) {
                        const form = $(this).closest('form');
                        const tipoFoto = form.data('tipo');
                        let idSufixo = '';
                        switch (tipoFoto) {
                            case 'TABELA_NUTRICIONAL': idSufixo = 'nutricional'; break;
                            case 'EMBALAGEM_PRIMARIA': idSufixo = 'primaria'; break;
                            case 'EMBALAGEM_SECUNDARIA': idSufixo = 'secundaria'; break;
                            case 'SIF': idSufixo = 'sif'; break;
                        }
                        currentUploadInfo = {
                            fichaId,
                            tipoFoto,
                            form,
                            previewImg: $(`#preview-${idSufixo}`)
                        };

                        const reader = new FileReader();
                        reader.onload = function (e) {
                            imageToCrop.src = e.target.result;
                            modalCrop.show();
                        };
                        reader.readAsDataURL(file);
                    }
                });

            $(modalCropElement).on('shown.bs.modal', () => {
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: NaN,
                    viewMode: 1,
                    autoCropArea: 0.9
                });
            }).on('hidden.bs.modal', () => {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
            });

            $('#btn-crop-upload').on('click', function () {
                if (!cropper) return;
                const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Cortando...');

                cropper.getCroppedCanvas({
                    width: 1024,
                    imageSmoothingQuality: 'high'
                }).toBlob((blob) => {
                    const formData = new FormData();
                    formData.append('ficha_id', currentUploadInfo.fichaId);
                    formData.append('foto_tipo', currentUploadInfo.tipoFoto);
                    formData.append('foto_arquivo', blob, 'cropped_image.jpg');
                    formData.append('csrf_token', csrfToken);

                    currentUploadInfo.previewImg.css('opacity', 0.5);

                    $.ajax({
                        url: 'ajax_router.php?action=uploadFotoFicha',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json'
                    }).done((response) => {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Imagem enviada.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            currentUploadInfo.previewImg.css('opacity', 1);
                            carregarFotos(currentUploadInfo.fichaId);
                        } else {
                            Swal.fire('Erro!', response.message, 'error');
                            currentUploadInfo.previewImg.css('opacity', 1);
                        }
                    }).fail(() => {
                        Swal.fire('Erro', 'Não foi possível enviar a imagem.', 'error');
                        currentUploadInfo.previewImg.css('opacity', 1);
                    }).always(() => {
                        currentUploadInfo.form[0].reset();
                        $btn.prop('disabled', false).text('Cortar e Enviar');
                        modalCrop.hide();
                    });
                }, 'image/jpeg', 0.9);
            });

            $('#midia-pane').on('click', '.btn-remover-foto',
                function () {
                    const fichaId = $('#ficha_id').val();
                    const tipoFoto = $(this).data('tipo');
                    Swal.fire({
                        title: 'Tem certeza?',
                        text: "Deseja remover esta imagem?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonText: 'Cancelar',
                        confirmButtonText: 'Sim, remover!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.post('ajax_router.php?action=excluirFotoFicha', {
                                ficha_id: fichaId,
                                foto_tipo: tipoFoto,
                                csrf_token: csrfToken
                            },
                                (response) => {
                                    if (response.success) {
                                        //Swal.fire('Removida!', 'A imagem foi removida.', 'success');
                                        carregarFotos(fichaId);
                                    } else { Swal.fire('Erro!', response.message, 'error'); }
                                }, 'json');
                        }
                    });
                });

            // --- LÓGICA DO BOTÃO DE IMPRIMIR ---
            const fichaIdParaImpressao = new URLSearchParams(window.location.search).get('id');
            const $btnImprimir = $('#btn-imprimir-ficha');

            if (fichaIdParaImpressao) {
                // Esconda o botão atual
                $btnImprimir.hide();

                if ($('#btn-relatorio-ft').length === 0) {
                    const btnRelatorio = `<a id="btn-relatorio-ft" href="index.php?page=relatorio_ficha_tecnica&id=${fichaIdParaImpressao}" target="_blank" class="btn btn-success">
                                <i class="fas fa-print me-2"></i>Imprimir Ficha</a>`;
                    $('.card-body .d-flex.justify-content-between.align-items-center.mb-3 > div').prepend(btnRelatorio);
                }
            }

        }

        // --- LÓGICA DE CARREGAMENTO INICIAL ---
        const urlParams = new URLSearchParams(window.location.search);
        const fichaId = urlParams.get('id');
        const dadosCopiados = sessionStorage.getItem('fichaTecnicaCopiada');
        if (fichaId) {
            $.post('ajax_router.php?action=getFichaTecnicaCompleta', {
                ficha_id: fichaId,
                csrf_token: csrfToken
            },
                (response) => {
                    if (response.success) {
                        preencherFormularioFicha(response.data, false);
                    }
                    else { Swal.fire('Erro', 'Não foi possível carregar os dados desta ficha.', 'error'); }
                }, 'json');
        } else if (dadosCopiados) {
            sessionStorage.removeItem('fichaTecnicaCopiada');
            preencherFormularioFicha(JSON.parse(dadosCopiados), true);
            Swal.fire({
                icon: 'info',
                title: 'Ficha Copiada',
                text: 'Selecione um novo produto antes de salvar.',
                showConfirmButton: true
            });
        }
    }
});