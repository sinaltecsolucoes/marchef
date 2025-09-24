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

function formatCnpjCpf($value)
{
    // Remove qualquer caractere que não seja número
    $value = preg_replace('/\D/', '', $value);
    $len = strlen($value);

    if ($len === 11) { // É um CPF
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $value);
    } elseif ($len === 14) { // É um CNPJ
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $value);
    }

    // Se não for CPF ou CNPJ, retorna o valor original (ou N/A)
    return htmlspecialchars($value ?: 'N/A');
}

/**
 * FUNÇÃO PARA DESENHAR O CABEÇALHO
 */
function renderReportHeader($header)
{
    // 1. Prepara dados da OE
    $oe_numero = htmlspecialchars($header['ordem_expedicao_numero']);
    $data_geracao = (new DateTime($header['fat_data_geracao']))->format('d/m/Y H:i:s');
    $usuario = htmlspecialchars($header['usuario_nome']);

    // 2. Prepara dados da Transportadora
    $transp_nome = htmlspecialchars($header['transportadora_razao'] ?: $header['transportadora_nome'] ?: 'N/A');
    $transp_cnpj = formatCnpjCpf($header['transportadora_cnpj']);
    $transp_ie = htmlspecialchars($header['transportadora_ie'] ?: 'N/A');

    $transp_end = 'N/A';
    if ($header['transportadora_end_logradouro']) {
        $transp_end = htmlspecialchars($header['transportadora_end_logradouro']) . ', ' .
            htmlspecialchars($header['transportadora_end_numero']) . ' - ' .
            htmlspecialchars($header['transportadora_end_bairro']) . ', ' .
            htmlspecialchars($header['transportadora_end_cidade']) . '/' .
            htmlspecialchars($header['transportadora_end_uf']);
    }

    // 3. Prepara dados do Motorista
    $motorista = htmlspecialchars($header['fat_motorista_nome'] ?: 'N/A');
    $cpf = formatCnpjCpf($header['fat_motorista_cpf']);
    $placa = htmlspecialchars($header['fat_veiculo_placa'] ?: 'N/A');

    // 4. Renderiza o HTML
    echo '<div class="report-header">';
    echo '  <div class="row g-0 align-items-center">'; // g-0 remove o gutter, align-items-start para alinhamento no topo

    // Coluna 1: Logo
    echo '    <div class="col-3 text-start">';
    echo '      <img src="img/logo_marchef.png" alt="Logo Marchef" style="max-width: 150px;">';
    echo '    </div>';

    // Coluna 2: Dados da Geração
    echo '    <div class="col-4 text-start">';
    echo '      <h5 class="mb-1">Resumo para Faturamento</h5>';
    echo "      <p class='mb-0'><strong>Ordem de Expedição:</strong> $oe_numero</p>";
    echo "      <p class='mb-0'><strong>Data de Geração:</strong> $data_geracao</p>";
    echo "      <p class='mb-0'><strong>Gerado por:</strong> $usuario</p>";
    echo '    </div>';

    // Coluna 3: Dados de Transporte
    echo '    <div class="col-5 text-start transport-details">';
    echo "      <p class='mb-0'><strong>Transportadora:</strong> $transp_nome</p>";
    echo "      <p class='mb-0'><strong>CNPJ:</strong> $transp_cnpj <strong>IE:</strong> $transp_ie</p>";
    echo "      <p class='mb-0'><strong>Endereço:</strong> $transp_end</p>";
    echo "      <p class='mb-0'><strong>Motorista:</strong> $motorista <strong>CPF:</strong> $cpf</p>";
    echo "      <p class='mb-0'><strong>Placa Veículo:</strong> $placa</p>";
    echo '    </div>';

    echo '  </div>'; // Fecha a .row
    echo '  <button class="btn btn-primary mt-3 no-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir / Salvar PDF</button>';
    echo '</div>';
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Resumo de Faturamento - Nº <?php echo $resumoId; ?></title>
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/img/icone_2.ico" type="image/x-icon">
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
                           <td colspan='3' class='text-end total-row'>Subtotal Pedido:</td>
                            <td class='text-center align-middle total-row'>" . formatCaixa($totalClientePedidoCaixas) . "</td>
                            <td class='text-center align-middle total-row'>" . formatQuilo($totalClientePedidoQuilos) . "</td>
                            <td class='text-center align-middle total-row'></td> 
                            <td class='text-center align-middle total-row'>" . formatCurrency($totalClientePedidoValor) . "</td>
                          </tr>";

                    // Fechar o total da Fazenda anterior
                    echo "<tr class='total-row' style='background-color: #e9ecef; border-top: 2px solid #adb5bd;'>
                            <td colspan='3' class='text-end' style='font-weight: bold;'>Total da Fazenda ({$currentFazenda}):</td>
                            <td class='text-center align-middle' style='font-weight: bold;'>" . formatCaixa($totalFazendaCaixas) . " (CX)</td>
                            <td class='text-center align-middle' style='font-weight: bold;'>" . formatQuilo($totalFazendaQuilos) . " (KG)</td>
                            <td class='text-center align-middle' style='font-weight: bold;'></td> 
                            <td class='text-center align-middle' style='font-weight: bold;'>" . formatCurrency($totalFazendaValor) . "</td>
                        </tr>";

                    echo "</tbody></table></div></div>"; // Fecha a tabela, client-info e o INVOICE-PANEL
        
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

                echo "<h5 class='section-title mt-1'>FAZENDA: " . htmlspecialchars($currentFazenda) . "</h5>";
            endif;

            // 2. Mudança de Cliente/Pedido (Início do Painel-no-Painel)
            $chaveClientePedido = $item['cliente_nome'] . $item['fatn_numero_pedido'];
            if ($chaveClientePedido !== $currentCliente . $currentPedido):
                if ($index > 0 && $currentCliente != ''): // Fecha a tabela e o painel do cliente anterior
                    echo "<tr>
                            <td colspan='3' class='text-end total-row'>Subtotal Pedido:</td>
                            <td class='text-center align-middle total-row'>" . formatCaixa($totalClientePedidoCaixas) . "</td>
                            <td class='text-center align-middle total-row'>" . formatQuilo($totalClientePedidoQuilos) . "</td>
                            <td class='text-center align-middle total-row'></td> 
                            <td class='text-center align-middle total-row'>" . formatCurrency($totalClientePedidoValor) . "</td>
                        </tr>";
                    echo "</tbody></table></div></div>"; // Fecha table, client-info e invoice-panel
        

                endif;

                $currentCliente = $item['cliente_nome'];
                $currentPedido = $item['fatn_numero_pedido'];
                $totalClientePedidoCaixas = 0;
                $totalClientePedidoQuilos = 0;
                $totalClientePedidoValor = 0;

                // INICIA O CONTAINER MAIOR
                echo "<div class='invoice-panel'>";

                // Exibe os dados cadastrais do cliente
                echo "<div class='client-info'>";
                echo "<strong>CLIENTE:</strong> " . htmlspecialchars($item['cliente_razao_social']) . " (Fantasia: " . htmlspecialchars($item['cliente_nome']) . ")<br>";
                echo "<strong>CNPJ/CPF:</strong> " . formatCnpjCpf($item['ent_cnpj'] ?: $item['ent_cpf']) . " <strong>IE:</strong> " . htmlspecialchars($item['ent_inscricao_estadual']) . "<br>";
                echo "<strong>Endereço:</strong> " . htmlspecialchars($item['end_logradouro']) . ", " . htmlspecialchars($item['end_numero']) . " - " . htmlspecialchars($item['end_bairro']) . ", " . htmlspecialchars($item['end_cidade']) . "/" . htmlspecialchars($item['end_uf']) . " - CEP: " . htmlspecialchars($item['end_cep']);
                echo "</div>";

                // Inicia a tabela de itens
                echo "<div class='p-2'>"; // Um padding para a tabela não colar no painel
        
                $condPag = htmlspecialchars($item['condicao_pag_descricao'] ?: 'N/A');
                $obs = htmlspecialchars($item['fatn_observacao'] ?: 'Nenhuma');

                $numNF = htmlspecialchars($item['fatn_numero_nota_fiscal'] ?: 'N/A');

                echo "<div class='pedido-info mb-1'>";
                // Linha única para Pedido e Condição de Pagamento
                echo "  <p class='mb-0'><strong>N° Pedido Cliente:</strong> " . htmlspecialchars($currentPedido ?: 'N/A') .
                    "  <span class='mx-2'>|</span>  " .
                    "<strong>Cond. Pagamento:</strong> $condPag" .
                    "  <span class='mx-2'>|</span>  " . // Adiciona mais um separador
                    "<strong>N° Nota Fiscal:</strong> $numNF</p>"; // Adiciona a NF        
        
                // Linha separada para Observação
                echo "  <p class='mb-0'><strong>Observação do Pedido:</strong> $obs</p>";
                echo "</div>";

                echo "<table class='table table-sm table-bordered mt-2 item-table'>
                        <thead class='table-light'>
                            <tr>
                                <th class='text-center align-middle'>Produto</th>
                                <th class='text-center align-middle'>Lote</th>
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

            // 3. Exibe a linha do item
            echo "<tr>
                    <td>" . htmlspecialchars($item['produto_descricao']) . "</td>
                    <td class='text-center align-middle'    >" . htmlspecialchars($item['lote_completo_calculado']) . "</td>
                    <td class='text-center align-middle'>" . formatCurrency($precoUnit) . "</td>
                    <td class='text-center align-middle'>" . formatCaixa($qtdCaixas) . "</td>
                    <td class='text-center align-middle'>" . formatQuilo($qtdQuilos) . "</td>
                    <td class='text-center align-middle'>" . htmlspecialchars($item['fati_preco_unidade_medida']) . "</td>
                    <td class='text-center align-middle'>" . formatCurrency($valorTotal) . "</td>
                  </tr>";

        endforeach;

        // 4. Fecha os últimos totais (do último cliente e última fazenda)
        echo "<tr>
               <td colspan='3' class='text-end total-row'>Subtotal Pedido:</td>
               <td class='text-center align-middle total-row'>" . formatCaixa($totalClientePedidoCaixas) . "</td>
               <td class='text-center align-middle total-row'>" . formatQuilo($totalClientePedidoQuilos) . "</td>
               <td class='text-center align-middle total-row'></td> 
               <td class='text-center align-middle total-row'>" . formatCurrency($totalClientePedidoValor) . "</td>
            </tr>";

        echo "<tr class='total-row' style='background-color: #e9ecef; border-top: 2px solid #adb5bd;'>
         <td colspan='3' class='text-end' style='font-weight: bold;'>Total da Fazenda ({$currentFazenda}):</td>
        <td class='text-center align-middle' style='font-weight: bold;'>" . formatCaixa($totalFazendaCaixas) . " (CX)</td>
        <td class='text-center align-middle' style='font-weight: bold;'>" . formatQuilo($totalFazendaQuilos) . " (KG)</td>
        <td class='text-center align-middle' style='font-weight: bold;'></td> 
        <td class='text-center align-middle' style='font-weight: bold;'>" . formatCurrency($totalFazendaValor) . "</td>
      </tr>";
        echo "</tbody></table></div></div>"; // Fecha table, p-2, client-info e invoice-panel
        
        // Total da Fazenda com todos os campos
        
        ?>
    </div>

</body>

</html>