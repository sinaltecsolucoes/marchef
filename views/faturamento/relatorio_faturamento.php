<?php
// public/relatorio_faturamento.php 
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\Faturamento\FaturamentoRepository;

if (!isset($_SESSION['codUsuario'])) {
    die("Acesso negado. Por favor, faça o login.");
}

$resumoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$resumoId) {
    die("Erro: ID do resumo de faturamento não fornecido.");
}

try {
    $pdo = Database::getConnection();
    $faturamentoRepo = new FaturamentoRepository($pdo);

    $header = $faturamentoRepo->findResumoHeaderInfo($resumoId);
    $itens = $faturamentoRepo->getDadosCompletosParaRelatorio($resumoId);

    if (!$header || empty($itens)) {
        die("Nenhum dado encontrado para este resumo.");
    }

} catch (Exception $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

// --- FUNÇÕES PÚBLICAS DE FORMATAÇÃO (EM PHP) ---
function formatCaixa($val)
{
    return number_format((float) $val, 0); // Sempre inteiro
}

function formatQuilo($val)
{
    $num = (float) $val;
    if ($num % 1 === 0) { // Se for inteiro
        return number_format($num, 0);
    }
    return number_format($num, 3, ',', '.'); // Se tiver decimal
}

function formatCurrency($val)
{
    return 'R$ ' . number_format((float) $val, 2, ',', '.');
}

/**
 * FUNÇÃO PARA DESENHAR O CABEÇALHO
 */
function renderReportHeader($header)
{
    echo '<div class="report-header">';
    echo '<img src="img/logo_marchef.png" alt="Logo Marchef">';
    echo '<h3 class="mt-3">Resumo para Faturamento</h3>';
    echo '<p class="mb-0"><strong>Ordem de Expedição de Origem:</strong> ' . htmlspecialchars($header['ordem_expedicao_numero']) . '</p>';
    echo '<p class="mb-0"><strong>Data de Geração:</strong> ' . (new DateTime($header['fat_data_geracao']))->format('d/m/Y H:i:s') . '</p>';
    echo '<p class="mb-0"><strong>Gerado por:</strong> ' . htmlspecialchars($header['usuario_nome']) . '</p>';
    echo '<button class="btn btn-primary mt-3 no-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir / Salvar PDF</button>';
    echo '</div>';
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Resumo de Faturamento - Nº <?php echo $resumoId; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/relatorios.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
</head>

<body>
    <div class="container">
        <?php
        $currentFazenda = '';
        $firstFazenda = true; // Flag para controlar o primeiro cabeçalho
        
        foreach ($itens as $index => $item):

            $qtdCaixas = (float) ($item['fati_qtd_caixas'] ?? 0);
            $qtdQuilos = (float) ($item['fati_qtd_quilos'] ?? 0);
            $precoUnit = (float) ($item['fati_preco_unitario'] ?? 0);
            $valorTotal = 0;
            if ($item['fati_preco_unidade_medida'] == 'CX') {
                $valorTotal = $qtdCaixas * $precoUnit;
            } else {
                $valorTotal = $qtdQuilos * $precoUnit;
            }

            // 1. Mudança de Fazenda
            if ($item['fazenda_nome'] !== $currentFazenda):
                if (!$firstFazenda): // Se não for a primeira fazenda, fecha os totais e painéis anteriores
        
                    // Fechar o subtotal do Cliente/Pedido anterior
                    echo "<tr>
                            <td colspan='4' class='text-end total-row'>Subtotal Pedido:</td>
                            <td class='text-center align-middle total-row'>" . formatCaixa($totalClientePedidoCaixas) . "</td>
                            <td class='text-center align-middle total-row'>" . formatQuilo($totalClientePedidoQuilos) . "</td>
                            <td class='text-center align-middle total-row' colspan='2'>" . formatCurrency($totalClientePedidoValor) . "</td>
                          </tr>";
                    echo "</tbody></table></div></div>"; // Fecha a tabela, client-info e o INVOICE-PANEL
        
                    // Fechar o total da Fazenda anterior (REFINAMENTO 5)
                    echo "<div class='d-flex justify-content-end'>
                            <table class='table table-sm table-bordered' style='width: 60%;'>
                             <tr class='text-end align-middle total-row'><td colspan='2'>Total da Fazenda ({$currentFazenda}):</td>
                                <td class='text-center align-middle '>" . formatCaixa($totalFazendaCaixas) . " (CX)</td>
                                <td class='text-center align-middle '>" . formatQuilo($totalFazendaQuilos) . " (KG)</td>
                                <td class='text-center align-middle '>" . formatCurrency($totalFazendaValor) . "</td>
                             </tr>
                            </table>
                          </div>";

                    echo "<div class='page-break'></div>"; // FORÇA QUEBRA DE PÁGINA
                endif;

                $firstFazenda = false; // Desmarca a flag
        
                $currentFazenda = $item['fazenda_nome'];
                $totalFazendaCaixas = 0;
                $totalFazendaQuilos = 0;
                $totalFazendaValor = 0;
                $currentCliente = '';
                $currentPedido = '';

                // ### CHAMA O CABEÇALHO DO RELATÓRIO AQUI ###
                renderReportHeader($header);

                echo "<h4 class='section-title'>FAZENDA: " . htmlspecialchars($currentFazenda) . "</h4>";
            endif;

            // 2. Mudança de Cliente/Pedido (REFINAMENTO 4: Início do Painel-no-Painel)
            $chaveClientePedido = $item['cliente_nome'] . $item['fati_numero_pedido'];
            if ($chaveClientePedido !== $currentCliente . $currentPedido):
                if ($index > 0 && $currentCliente != ''): // Fecha a tabela e o painel do cliente anterior
                    echo "<tr>
                            <td colspan='4' class='text-end total-row'>Subtotal Pedido:</td>
                            <td class='text-center align-middle total-row'>" . formatCaixa($totalClientePedidoCaixas) . "</td>
                            <td class='text-center align-middle total-row'>" . formatQuilo($totalClientePedidoQuilos) . "</td>
                            <td class='text-center align-middle total-row' colspan='2'>" . formatCurrency($totalClientePedidoValor) . "</td>
                        </tr>";
                    echo "</tbody></table></div></div>"; // Fecha table, client-info e invoice-panel
                endif;

                $currentCliente = $item['cliente_nome'];
                $currentPedido = $item['fati_numero_pedido'];
                $totalClientePedidoCaixas = 0;
                $totalClientePedidoQuilos = 0;
                $totalClientePedidoValor = 0;

                // INICIA O CONTAINER MAIOR (O SEU PAINEL)
                echo "<div class='invoice-panel'>";

                // Exibe os dados cadastrais do cliente
                echo "<div class='client-info'>";
                echo "<strong>CLIENTE:</strong> " . htmlspecialchars($item['cliente_razao_social']) . " (Fantasia: " . htmlspecialchars($item['cliente_nome']) . ")<br>";
                echo "<strong>CNPJ/CPF:</strong> " . htmlspecialchars($item['ent_cnpj'] ?: $item['ent_cpf']) . " <strong>IE:</strong> " . htmlspecialchars($item['ent_inscricao_estadual']) . "<br>";
                echo "<strong>Endereço:</strong> " . htmlspecialchars($item['end_logradouro']) . ", " . htmlspecialchars($item['end_numero']) . " - " . htmlspecialchars($item['end_bairro']) . ", " . htmlspecialchars($item['end_cidade']) . "/" . htmlspecialchars($item['end_uf']) . " - CEP: " . htmlspecialchars($item['end_cep']);
                echo "</div>";

                // Inicia a tabela de itens
                echo "<div class='p-2'>"; // Um padding para a tabela não colar no painel
                echo "<strong>N° Pedido: " . htmlspecialchars($currentPedido ?: 'N/A') . "</strong>";
                echo "<table class='table table-sm table-bordered mt-2 item-table'>
                        <thead class='table-light'>
                            <tr>
                                <th class='text-center align-middle'>Produto</th>
                                <th class='text-center align-middle'>Lote</th>
                                <th class='text-center align-middle'>Obs.</th>
                                <th class='text-center align-middle'>Preço Unit.</th>
                                <th class='text-center align-middle'>Qtd. Caixas</th>
                                <th class='text-center align-middle'>Qtd. Quilos</th>
                                <th class='text-center align-middle'>UN</th>
                                <th class='text-center align-middle'>Valor Total</th>
                            </tr>
                        </thead>
                        <tbody>";
            endif;

            // Acumula totais
            $totalClientePedidoCaixas += $qtdCaixas;
            $totalClientePedidoQuilos += $qtdQuilos;
            $totalClientePedidoValor += $valorTotal;
            $totalFazendaCaixas += $qtdCaixas;
            $totalFazendaQuilos += $qtdQuilos;
            $totalFazendaValor += $valorTotal;

            // 3. Exibe a linha do item (REFINAMENTOS 6, 7 e 8: Formatação de números)
            echo "<tr>
                    <td>" . htmlspecialchars($item['produto_descricao']) . "</td>
                    <td>" . htmlspecialchars($item['lote_completo_calculado']) . "</td>
                    <td>" . htmlspecialchars($item['fati_observacao']) . "</td>
                    <td class='text-center align-middle'>" . formatCurrency($precoUnit) . "</td>
                    <td class='text-center align-middle'>" . formatCaixa($qtdCaixas) . "</td>
                    <td class='text-center align-middle'>" . formatQuilo($qtdQuilos) . "</td>
                    <td class='text-center align-middle'>" . htmlspecialchars($item['fati_preco_unidade_medida']) . "</td>
                    <td class='text-center align-middle'>" . formatCurrency($valorTotal) . "</td>
                  </tr>";

        endforeach;

        // 4. Fecha os últimos totais (do último cliente e última fazenda)
        echo "<tr>
                <td colspan='4' class='text-end total-row'>Subtotal Pedido:</td>
                <td class='text-center align-middle total-row'>" . formatCaixa($totalClientePedidoCaixas) . "</td>
                <td class='text-center align-middle total-row'>" . formatQuilo($totalClientePedidoQuilos) . "</td>
                <td class='text-center align-middle total-row' colspan='2'>" . formatCurrency($totalClientePedidoValor) . "</td>
            </tr>";
        echo "</tbody></table></div></div>"; // Fecha table, p-2, client-info e invoice-panel
        
        // REFINAMENTO 5: Total da Fazenda com todos os campos
        echo "<div class='d-flex justify-content-end'>
                <table class='table table-sm table-bordered' style='width: 60%;'>
                    <tr class='total-row'><td colspan='2'>Total da Fazenda ({$currentFazenda}):</td>
                    <td class='text-center align-middle '>" . formatCaixa($totalFazendaCaixas) . " (CX)</td>
                    <td class='text-center align-middle '>" . formatQuilo($totalFazendaQuilos) . " (KG)</td>
                    <td class='text-center align-middle '>" . formatCurrency($totalFazendaValor) . "</td>
                    </tr>
                </table>
              </div>";
        ?>
    </div>

</body>

</html>