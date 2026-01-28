<?php
// views/faturamento/relatorio_faturamento.php 
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\Faturamento\FaturamentoRepository;

if (!isset($_SESSION['codUsuario'])) die("Acesso negado.");

$resumoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$resumoId) die("Erro: ID não fornecido.");

try {
    $pdo = Database::getConnection();
    $faturamentoRepo = new FaturamentoRepository($pdo);
    $header = $faturamentoRepo->findResumoHeaderInfo($resumoId);
    $itens = $faturamentoRepo->getDadosCompletosParaRelatorio($resumoId);

    if (!$header || empty($itens)) die("Nenhum dado encontrado.");
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// --- PREPARAÇÃO DE DADOS ---

// 1. Prepara dados da OE
$oe_numero = htmlspecialchars($header['ordem_expedicao_numero']);
$data_geracao = (new DateTime($header['fat_data_geracao']))->format('d/m/Y H:i:s');
$usuario = htmlspecialchars($header['usuario_nome']);

// 2. Prepara dados da Transportadora
$transp_nome = htmlspecialchars($header['transportadora_razao'] ?: $header['transportadora_nome'] ?: 'N/A');
$transp_cnpj = fmtDoc($header['transportadora_cnpj']);
$transp_ie = htmlspecialchars($header['transportadora_ie'] ?: 'N/A');

// Endereço Transportadora
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
$cpf = fmtDoc($header['fat_motorista_cpf']);
$placa = htmlspecialchars($header['fat_veiculo_placa'] ?: 'N/A');

// Funções Helpers
function fmtC($v)
{
    return number_format((float)$v, 0);
}
function fmtK($v)
{
    return ((float)$v % 1 === 0) ? number_format((float)$v, 0) : number_format((float)$v, 3, ',', '.');
}
function fmtM($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function fmtDoc($v)
{
    $v = preg_replace('/\D/', '', $v);
    if (strlen($v) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $v);
    if (strlen($v) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v);
    return $v ?: 'N/A';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Resumo Faturamento - <?= $resumoId ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/relatorios.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />

    <style>
        @page {
            size: A4 landscape;
            margin: 1cm;
        }

        .container {
            max-width: 297mm !important;
            width: 100% !important;
        }

        /* --- TOTAIS E RODAPÉS --- */
        /* .total-fazenda-row td {
            background-color: #e9ecef !important;
            border-top: 2px solid #adb5bd !important;
            font-weight: bold;
            font-size: 10pt;*/
        /* Letra maior para o total */
        /*    padding-top: 8px;
            padding-bottom: 8px;
        }*/

        .total-pedido-row td {
            font-weight: bold;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .text-destaque-verde {
            color: #198754;
            /* Verde Bootstrap */
            font-size: 8pt;
        }

        /* --- LARGURAS DAS COLUNAS (Atualizado para 8 colunas) --- */
        /* 1. Cód */
        .item-table th:nth-child(1),
        .item-table td:nth-child(1) {
            width: 8%;
            text-align: center;
            vertical-align: middle;
        }

        /* 2. Produto */
        .item-table th:nth-child(2),
        .item-table td:nth-child(2) {
            width: 42%;
            vertical-align: middle;
        }

        /* 3. Lote */
        .item-table th:nth-child(3),
        .item-table td:nth-child(3) {
            width: 12%;
            text-align: center;
            vertical-align: middle;
        }

        /* 4. Preço */
        .item-table th:nth-child(4),
        .item-table td:nth-child(4) {
            width: 8%;
            text-align: center;
            vertical-align: middle;
        }

        /* 5. Cx */
        .item-table th:nth-child(5),
        .item-table td:nth-child(5) {
            width: 8%;
            text-align: center;
            vertical-align: middle;
        }

        /* 6. Kg */
        .item-table th:nth-child(6),
        .item-table td:nth-child(6) {
            width: 8%;
            text-align: center;
            vertical-align: middle;
        }

        /* 7. UN */
        .item-table th:nth-child(7),
        .item-table td:nth-child(7) {
            width: 4%;
            text-align: center;
            vertical-align: middle;
        }

        /* 8. Total */
        .item-table th:nth-child(8),
        .item-table td:nth-child(8) {
            width: 10%;
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php
        $currentFazenda = '';
        $firstFazenda = true;
        $currentCliente = '';
        $currentPedido = '';
        $tabelaAberta = false;

        // Totais
        $totFaz = ['cx' => 0, 'kg' => 0, 'val' => 0];
        $totPed = ['cx' => 0, 'kg' => 0, 'val' => 0];

        foreach ($itens as $index => $item):
            $qtdCx = (float)($item['fati_qtd_caixas'] ?? 0);
            $qtdKg = (float)($item['fati_qtd_quilos'] ?? 0);
            $prcUnit = (float)($item['fati_preco_unitario'] ?? 0);
            $valTot = ($item['fati_preco_unidade_medida'] == 'CX') ? ($qtdCx * $prcUnit) : ($qtdKg * $prcUnit);

            // 1. Mudança de Fazenda
            if ($item['fazenda_nome'] !== $currentFazenda):
                if (!$firstFazenda):
                    // FECHA ANTERIOR
        ?>
                    <tr class="total-pedido-row">
                        <td colspan="4" class="text-end">Subtotal Pedido:</td>
                        <td class="text-center"><?= fmtC($totPed['cx']) ?></td>
                        <td class="text-center"><?= fmtK($totPed['kg']) ?></td>
                        <td></td>
                        <td class="text-center"><?= fmtM($totPed['val']) ?></td>
                    </tr>

                    <tr class="total-fazenda-row">
                        <td colspan="4" class="text-end text-uppercase">Total da Fazenda (<?= $currentFazenda ?>):</td>
                        <td class="text-center"><?= fmtC($totFaz['cx']) ?> <small>CX</small></td>
                        <td class="text-center"><?= fmtK($totFaz['kg']) ?> <small>KG</small></td>
                        <td></td>
                        <td class="text-center text-destaque-verde"><?= fmtM($totFaz['val']) ?></td>
                    </tr>

                    </tbody>
                    </table>
    </div>
    </div>
    <div class="page-break"></div>
<?php
                    $tabelaAberta = false;
                    $currentCliente = '';
                    $currentPedido = '';
                endif;

                $firstFazenda = false;
                $currentFazenda = $item['fazenda_nome'];
                $totFaz = ['cx' => 0, 'kg' => 0, 'val' => 0];
?>
<div class="report-header">
    <div class="row g-0 align-items-center">
        <div class="col-3 text-start"><img src="img/logo_marchef.png" alt="Logo"></div>
        <div class="col-4 text-start">
            <h5 class="mb-1">Resumo para Faturamento</h5>
            <p class="mb-0"><strong>Ordem de Expedição:</strong> <?= $oe_numero ?></p>
            <p class="mb-0"><strong>Data de Geração:</strong> <?= $data_geracao ?></p>
            <p class="mb-0"><strong>Gerado por:</strong> <?= $usuario ?></p>
        </div>
        <div class="col-5 text-start transport-details">
            <p class="mb-0"><strong>Transportadora:</strong> <?= $transp_nome ?></p>
            <p class="mb-0"><strong>CNPJ:</strong> <?= $transp_cnpj ?> <strong>IE:</strong> <?= $transp_ie ?></p>
            <p class="mb-0"><strong>Endereço:</strong> <?= $transp_end ?></p>
            <p class="mb-0"><strong>Motorista:</strong> <?= $motorista ?> <strong>CPF:</strong> <?= $cpf ?></p>
            <p class="mb-0"><strong>Placa(s) Veículo:</strong> <?= $placa ?></p>
        </div>
    </div>
</div>
<h5 class="section-title mt-1">FAZENDA: <?= htmlspecialchars($currentFazenda) ?></h5>
<?php
            endif;

            // 2. Mudança de Cliente/Pedido
            $chaveClientePedido = $item['cliente_nome'] . $item['fatn_numero_pedido'];
            if ($chaveClientePedido !== $currentCliente . $currentPedido):
                if ($tabelaAberta):
?>
    <tr class="total-pedido-row">
        <td colspan="4" class="text-end">Subtotal Pedido:</td>
        <td class="text-center"><?= fmtC($totPed['cx']) ?></td>
        <td class="text-center"><?= fmtK($totPed['kg']) ?></td>
        <td></td>
        <td class="text-center"><?= fmtM($totPed['val']) ?></td>
    </tr>
    </tbody>
    </table>
    </div>
    </div>
<?php
                endif;

                $currentCliente = $item['cliente_nome'];
                $currentPedido = $item['fatn_numero_pedido'];
                $totPed = ['cx' => 0, 'kg' => 0, 'val' => 0];
                $tabelaAberta = true;
?>
<div class="invoice-panel">
    <div class="client-info">
        <strong>Cliente:</strong> <?= htmlspecialchars($item['cliente_razao_social']) ?> (<?= htmlspecialchars($item['cliente_nome']) ?>)<br>
        <strong>CNPJ/CPF:</strong> <?= fmtDoc($item['ent_cnpj'] ?: $item['ent_cpf']) ?> <strong>IE:</strong> <?= htmlspecialchars($item['ent_inscricao_estadual']) ?><br>
        <strong>Endereço:</strong> <?= htmlspecialchars($item['end_logradouro']) ?>, <?= htmlspecialchars($item['end_numero']) ?> - <?= htmlspecialchars($item['end_bairro']) ?>, <?= htmlspecialchars($item['end_cidade']) ?>/<?= htmlspecialchars($item['end_uf']) ?>
    </div>
    <div class="p-2">
        <div class="pedido-info mb-1">
            <p class="mb-0">
                <strong>N° Pedido Cliente:</strong> <?= htmlspecialchars($currentPedido ?: 'N/A') ?>
                <span class="mx-2">|</span>
                <strong>Cond. Pagamento:</strong> <?= htmlspecialchars($item['condicao_pag_descricao'] ?: 'N/A') ?>
                <span class="mx-2">|</span>
                <strong>N° Nota Fiscal:</strong> <?= htmlspecialchars($item['fatn_numero_nota_fiscal'] ?: 'N/A') ?>
            </p>
            <p class="mb-0"><strong>Observação do Pedido:</strong> <?= htmlspecialchars($item['fatn_observacao'] ?: 'Nenhuma') ?></p>
        </div>

        <table class="table table-sm table-bordered mt-2 item-table">
            <thead class="table-light">
                <tr>
                    <th>Cód.</th>
                    <th class="text-center align-middle">Produto</th>
                    <th>Lote</th>
                    <th>Preço Unit.</th>
                    <th>Quant. Caixas</th>
                    <th>Quant. Quilos</th>
                    <th>Unid.</th>
                    <th>Valor Total</th>
                </tr>
            </thead>
            <tbody>
            <?php
            endif;

            // Acumula
            $totPed['cx'] += $qtdCx;
            $totPed['kg'] += $qtdKg;
            $totPed['val'] += $valTot;
            $totFaz['cx'] += $qtdCx;
            $totFaz['kg'] += $qtdKg;
            $totFaz['val'] += $valTot;
            ?>
            <tr>
                <td class="text-center"><?= htmlspecialchars($item['prod_codigo_interno'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['produto_descricao']) ?></td>
                <td class="text-center"><?= htmlspecialchars($item['lote_completo_calculado']) ?></td>
                <td class="text-center"><?= fmtM($prcUnit) ?></td>
                <td class="text-center"><?= fmtC($qtdCx) ?></td>
                <td class="text-center"><?= fmtK($qtdKg) ?></td>
                <td class="text-center"><?= htmlspecialchars($item['fati_preco_unidade_medida']) ?></td>
                <td class="text-center"><?= fmtM($valTot) ?></td>
            </tr>

        <?php endforeach; ?>

        <?php if ($tabelaAberta): ?>
            <tr class="total-pedido-row">
                <td colspan="4" class="text-end">Subtotal Pedido:</td>
                <td class="text-center"><?= fmtC($totPed['cx']) ?></td>
                <td class="text-center"><?= fmtK($totPed['kg']) ?></td>
                <td></td>
                <td class="text-center"><?= fmtM($totPed['val']) ?></td>
            </tr>

            <tr class="total-fazenda-row fw-bold">
                <td colspan="4" class="text-end text-uppercase">Total da Fazenda (<?= $currentFazenda ?>):</td>
                <td class="text-center"><?= fmtC($totFaz['cx']) ?> <small>CX</small></td>
                <td class="text-center"><?= fmtK($totFaz['kg']) ?> <small>KG</small></td>
                <td></td>
                <td class="text-center text-destaque-verde"><?= fmtM($totFaz['val']) ?></td>
            </tr>

            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="text-center no-print mt-4 mb-4">
    <button class="btn btn-primary btn-lg" onclick="window.print()">
        <i class="fas fa-print me-2"></i> Imprimir Relatório
    </button>
</div>
</div>
</body>

</html>