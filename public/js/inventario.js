
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
            error: function (xhr, status, error) {
                // Isso vai imprimir no console do navegador EXATAMENTE o que o PHP retornou
                console.group("Erro na Importação - Debug");
                console.error("Status:", status);
                console.error("Erro Detectado:", error);
                console.warn("Resposta bruta do Servidor (O que está quebrando o JSON):");
                console.log(xhr.responseText); // AQUI ESTÁ O SEGREDO
                console.groupEnd();

                Swal.fire('Erro!', 'Veja o console (F12) para o erro técnico.', 'error');
            },
            complete: function () {
                $btnSubmit.html(btnHtmlOriginal).prop('disabled', false);
                // Esconde a barra após um pequeno delay se chegar em 100%
                setTimeout(() => { if ($progressBar.text() === '100%') $progressBarDiv.fadeOut(); }, 2000);
            }
        });
    }

    /**
 * Calcula a validade seguindo a regra de arredondamento para o mês seguinte
 * em caso de estouro de dias.
 */
    /* function calcularValidadeArredondandoParaCima(dataFabricacao, mesesValidade) {
         if (!mesesValidade || mesesValidade <= 0) return '';
 
         const dataCalculada = new Date(dataFabricacao.getTime());
         const diaOriginal = dataCalculada.getDate();
 
         dataCalculada.setMonth(dataCalculada.getMonth() + parseInt(mesesValidade));
 
         // Se o dia mudou (ex: 31 de Março + 1 mês vira 01 de Maio), arredonda
         if (dataCalculada.getDate() !== diaOriginal) {
             dataCalculada.setDate(1);
             dataCalculada.setMonth(dataCalculada.getMonth() + 1);
         }
 
         // Retorna formatado para exibição BR
         return dataCalculada.toLocaleDateString('pt-BR');
     }*/

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

    /*function lidarComValidacao(response) {
        if (response.pode_processar) {
            // --- NOVIDADE: MONTAGEM DE PRÉVIA ---
            let htmlPrevia = `
            <div class="alert alert-info">
                <h5><i class="fas fa-search"></i> Prévia da Importação</h5>
                <p>Os dados abaixo serão registrados com as validades calculadas:</p>
                <div class="table-responsive" style="max-height: 300px;">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Lote</th>
                                <th>Produto</th>
                                <th>Fabr.</th>
                                <th class="text-primary">Validade (Calculada)</th>
                                <th>Qtd</th>
                            </tr>
                        </thead>
                        <tbody>`;

            // O PHP deve retornar esse array 'previa' no modo de validação
            response.previa.forEach(item => {
                htmlPrevia += `
                <tr>
                    <td>${item.lote}</td>
                    <td><small>${item.produto}</small></td>
                    <td>${item.fabricacao}</td>
                    <td class="fw-bold text-primary">${item.validade}</td>
                    <td>${item.quantidade}</td>
                </tr>`;
            });

            htmlPrevia += `</tbody></table></div></div>`;

            // Exibe a prévia na mesma div onde aparecem os erros
            $tabelaErros.closest('div').hide(); // Esconde a tabela de erros padrão
            $resultadoDiv.html(htmlPrevia).fadeIn();

            // SweetAlert de Confirmação
            Swal.fire({
                title: 'Tudo pronto!',
                html: `Validamos <b>${response.total_lotes}</b> lotes. Confira as datas de validade na tabela abaixo antes de confirmar.`,
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Confirmar e Importar',
                cancelButtonText: 'Corrigir Arquivo'
            }).then((result) => {
                if (result.isConfirmed) {
                    executarImportacao(false);
                }
            });
        } else {
            exibirTabelaErros(response.falhas, 'Erros de Validação encontrados.');
        }
    } */

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