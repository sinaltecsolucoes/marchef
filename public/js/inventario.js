$(document).ready(function () {

    $('#form-importar-inventario').on('submit', function (e) {
        e.preventDefault();

        // Pegamos o arquivo do input
        let formData = new FormData(this);
        formData.append('action', 'importarInventario'); // Define a ação para o router

        // Interface: Desabilita o botão e mostra que está processando
        let btn = $(this).find('button[type="submit"]');
        let btnHtmlOriginal = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Processando...').prop('disabled', true);

        // Limpa resultados anteriores
        $('#resultado-importacao').hide();
        $('#tabela-erros tbody').empty();

        $.ajax({
            url: 'ajax_router.php', // O seu switch/case está aqui
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function (response) {
                if (response.sucessos > 0) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Inventário Processado',
                        text: `Sucesso! ${response.sucessos} lotes foram consolidados e importados.`
                    });
                }

                // Se houver falhas, montamos a tabela de erros
                if (response.falhas && response.falhas.length > 0) {
                    $('#resultado-importacao').fadeIn();
                    let rows = '';
                    response.falhas.forEach(function (f) {
                        rows += `
                            <tr>
                                <td class="fw-bold text-danger">${f.lote}</td>
                                <td><span class="badge bg-warning text-dark">${f.erro}</span></td>
                            </tr>`;
                    });
                    $('#tabela-erros tbody').html(rows);

                    if (response.sucessos === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Falha na Importação',
                            text: 'Nenhum item pôde ser importado. Verifique a lista de erros abaixo.'
                        });
                    }
                }
            },
            error: function () {
                Swal.fire('Erro!', 'Ocorreu um erro crítico no servidor.', 'error');
            },
            complete: function () {
                // Restaura o botão
                btn.html(btnHtmlOriginal).prop('disabled', false);
            }
        });
    });
});