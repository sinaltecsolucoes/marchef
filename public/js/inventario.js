$(document).ready(function () {
    // Referências de UI para facilitar a manutenção
    const $form = $('#form-importar-inventario');
    const $btnSubmit = $form.find('button[type="submit"]');
    const $resultadoDiv = $('#resultado-importacao');
    const $tabelaErros = $('#tabela-erros tbody');
    const $progressBarDiv = $('.progress');
    const $progressBar = $('.progress-bar');

    const btnHtmlOriginal = $btnSubmit.html();

    /**
     * Evento Principal de Submissão
     */
    $form.on('submit', function (e) {
        e.preventDefault();
        // Sempre inicia validando primeiro
        executarImportacao(true);
    });

    /**
     * Função que gerencia tanto a Validação quanto o Processamento Real
     * @param {boolean} somenteValidar 
     */
    function executarImportacao(somenteValidar) {
        let formData = new FormData($form[0]);

        // Parâmetros de controle
        formData.append('action', 'importarInventario');
        formData.append('validar_apenas', somenteValidar);

        // CSRF Token (Segurança)
        const csrfToken = $('input[name="csrf_token"]').val();
        if (csrfToken) formData.append('csrf_token', csrfToken);

        // Reset da Interface
        $btnSubmit.html('<i class="fas fa-spinner fa-spin"></i> Processando...').prop('disabled', true);
        $resultadoDiv.hide();
        $tabelaErros.empty();

        // Exibe e reseta a barra de progresso
        $progressBarDiv.show();
        $progressBar.css('width', '0%').text('0%');

        $.ajax({
            url: 'ajax_router.php?action=importarInventario',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                // Monitora o progresso do UPLOAD do arquivo
                xhr.upload.addEventListener("progress", function (evt) {
                    if (evt.lengthComputable) {
                        let percent = Math.round((evt.loaded / evt.total) * 100);
                        $progressBar.css('width', percent + '%').text(percent + '%');
                    }
                }, false);
                return xhr;
            },
            success: function (response) {
                if (somenteValidar) {
                    lidarComValidacao(response);
                } else {
                    lidarComProcessamentoFinal(response);
                }
            },
            error: function () {
                Swal.fire('Erro!', 'Ocorreu um erro crítico de comunicação com o servidor.', 'error');
            },
            complete: function () {
                $btnSubmit.html(btnHtmlOriginal).prop('disabled', false);
                // Esconde a barra após um pequeno delay se chegar em 100%
                setTimeout(() => { if ($progressBar.text() === '100%') $progressBarDiv.fadeOut(); }, 2000);
            }
        });
    }

    /**
     * Trata o retorno do modo de simulação/validação
     */
    function lidarComValidacao(response) {
        if (response.pode_processar) {
            // Se não há erros, pede confirmação para gravar
            Swal.fire({
                title: 'Arquivo Validado!',
                text: `Identificamos ${response.total_lotes} lotes prontos para importação. Deseja processar a entrada no estoque agora?`,
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: '<i class="fas fa-check"></i> Sim, Processar Agora!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    executarImportacao(false); // Chama novamente, mas agora para gravar
                }
            });
        } else {
            // Se houver falhas na validação (fornecedor, produto ou endereço inexistente)
            exibirTabelaErros(response.falhas, 'Erros de Validação encontrados. Corrija o arquivo antes de processar.');
        }
    }

    /**
     * Trata o retorno do processamento real (após o commit no banco)
     */
    function lidarComProcessamentoFinal(response) {
        if (response.sucessos > 0 && (!response.falhas || response.falhas.length === 0)) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso Total!',
                text: `${response.sucessos} lotes foram importados e registrados no Kardex.`,
                timer: 3000
            }).then(() => location.reload());
        } else {
            // Se processou alguns mas falhou em outros (erro de transação, etc)
            exibirTabelaErros(response.falhas, `Importados: ${response.sucessos}. Falhas: ${response.falhas.length}`);
        }
    }

    /**
     * Helper para montar a tabela de erros
     */
    function exibirTabelaErros(falhas, mensagem) {
        let rows = '';
        falhas.forEach(function (f) {
            rows += `
                <tr>
                    <td class="fw-bold text-danger">${f.lote}</td>
                    <td><span class="badge bg-warning text-dark">${f.erro}</span></td>
                </tr>`;
        });

        $tabelaErros.html(rows);
        $resultadoDiv.fadeIn();

        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: mensagem
        });
    }
});