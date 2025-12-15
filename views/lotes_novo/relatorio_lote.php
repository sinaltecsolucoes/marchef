<?php
// views/lotes_novo/relatorio_lote.php

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\Lotes\LoteNovoRepository;
use App\Core\PdfFooter;

// Validação de Sessão e ID
if (!isset($_SESSION['codUsuario'])) die("Acesso negado.");
$loteId = filter_input(INPUT_GET, 'lote_id', FILTER_VALIDATE_INT);
if (!$loteId) die("ID do lote inválido.");

try {
    $pdo = Database::getConnection();
    $repo = new LoteNovoRepository($pdo);
    $dados = $repo->getDadosRelatorioLote($loteId);

    if (empty($dados)) {
        throw new RuntimeException("Lote não encontrado.");
    }

    // Variáveis
    $h = $dados['header'];
    $loteCompleto = $h['lote_completo_calculado'];
    $nomeCliente = $h['nome_cliente'];

    $recebimento = $dados['recebimento'];
    $producao = $dados['producao'];
    $embalagem = $dados['embalagem'];

    // --- RECEBIMENTO ---
    $totalRecebidoKg = array_sum(
        array_map(
            fn($r) => (float)$r['item_receb_peso_nota_fiscal'],
            $recebimento
        )
    );
    $numeroNF     = $recebimento[0]['item_receb_nota_fiscal'];
    $gramFaz      = $recebimento[0]['item_receb_gram_faz'];
    $gramaInd     = $recebimento[0]['item_receb_gram_lab'];
    $totalCaixas  = $recebimento[0]['item_receb_total_caixas'];
    $pesoMedioFaz = $totalCaixas > 0 ? $totalRecebidoKg / $totalCaixas : 0;



    // --- PRODUÇÃO ---
    $pesoProduzidoTotal = 0; // Peso Real 
    $pesoBeneficiadoTotal = 0; // Peso Teórico (Entrada Calculada)

    foreach ($producao as $p) {
        $qtd     = (float)$p['item_prod_quantidade'];
        $pesoUn  = (float)$p['prod_peso_embalagem'];
        $fator   = (float)$p['fator_atual'] / 100;
        $unidade = strtoupper($p['prod_unidade']);

        // 1. Calcula o Peso Real Produzido (Kg)
        $pesoItemProduzido = ($unidade === 'KG') ? $qtd : $qtd * $pesoUn;
        $pesoProduzidoTotal += $pesoItemProduzido; // Soma ao total Geral

        // Cálculo do Peso Beneficiado: Peso Produzido / Fator
        // Evita divisão por zero se o fator não tiver sido cadastrado corretamente
        $pesoBeneficiadoTotal += ($fator > 0) ? $pesoItemProduzido / $fator : 0;
    }

    // --- CÁLCULOS FINAIS (APROVEITAMENTO) ---
    // Quanto rendeu em relação ao que chegou de Nota Fiscal

    // Diferença em Quilos (Produção Teórica Beneficiada - Entrada Real)
    // AprovQuilo = Peso Beneficiado Calculado - Total Recebido Real
    $aprovQuilo = $pesoBeneficiadoTotal - $totalRecebidoKg;

    // Rendimento % = (AprovQuilo / Total Recebido) * 100
    // Se AprovQuilo for negativo (perda), a porcentagem  será negativa.
    $rendimento = ($totalRecebidoKg > 0) ? ($aprovQuilo / $totalRecebidoKg) * 100 : 0;

    // 1. LÓGICA DE COR PARA APROVEITAMENTO (Texto)
    // Se for maior que 0: Azul. Caso contrário (negativo ou zero): Vermelho.
    $corAprov = ($aprovQuilo > 0) ? '#007bff' : '#dc3545'; // Azul Bootstrap vs Vermelho Bootstrap

    // 2. LÓGICA DE COR PARA SITUAÇÃO (Fundo do Badge)
    // Traduzindo a lógica JS para PHP/CSS
    $status = strtoupper($h['lote_status']); // Garante maiúsculas para comparar
    $bgBadge = '#6c757d'; // bg-secondary (Cinza padrão)
    $textBadge = '#ffffff'; // Texto branco padrão

    switch ($status) {
        case 'EM ANDAMENTO':
            $bgBadge = '#ffc107'; // bg-warning (Amarelo)
            $textBadge = '#000000'; // Texto preto para contraste no amarelo
            break;
        case 'PARCIALMENTE FINALIZADO':
            $bgBadge = '#17a2b8'; // bg-info (Azul Ciano)
            break;
        case 'FINALIZADO':
            $bgBadge = '#28a745'; // bg-success (Verde)
            break;
        case 'CANCELADO':
            $bgBadge = '#dc3545'; // bg-danger (Vermelho)
            break;
    }

    // --- EMBALAGEM (SECUNDÁRIA) ---
    $totalEmbaladoKg = 0;
    foreach ($embalagem as $e) {
        $pesoUn = ($e['prod_unidade'] == 'KG') ? 1 : (float)$e['prod_peso_embalagem'];
        $qtd = (float)$e['item_emb_qtd_sec'];
        $totalEmbaladoKg += ($pesoUn * $qtd);
    }
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// --- LOGO (Base64) ---
$pathLogo = __DIR__ . '/../../public/img/logo_marchef.png';
$logoSrc = '';
if (file_exists($pathLogo)) {
    $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
    $data = file_get_contents($pathLogo);
    $logoSrc = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

// --- NOME E CAMINHO DO ARQUIVO ---
// Formata data para pasta: dez_25
$meses = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];
$dataFab = new DateTime($h['lote_data_fabricacao']);
$mesExtenso = $meses[$dataFab->format('m')];
$anoCurto = $dataFab->format('y');
$nomePasta = "{$mesExtenso}_{$anoCurto}";

$dirBase = __DIR__ . '/../../public/uploads/lotes/' . $nomePasta;
if (!is_dir($dirBase)) {
    mkdir($dirBase, 0777, true);
}

// Nome do arquivo: lote_3586.pdf
$nomeArquivo = "lote_" . preg_replace('/[^0-9]/', '', $h['lote_numero']) . ".pdf";
$caminhoFisico = $dirBase . '/' . $nomeArquivo;
$urlPublica = BASE_URL . "/uploads/lotes/{$nomePasta}/{$nomeArquivo}";

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 15px 15px 40px 15px;
            /* segue a sequencia TRBL (Top, Right, Bottom, Left). */
        }

        body {
            font-family: sans-serif;
            font-size: 10px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 7px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 3px;
            vertical-align: middle;
        }

        th {
            background-color: #eee;
            text-align: center;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .header-table td {
            border: none;
        }

        .section-title {
            background-color: #ddd;
            font-weight: bold;
            padding: 4px;
            margin-top: 4px;
            border: 1px solid #000;
            text-transform: uppercase;
        }

        .no-border {
            border: none !important;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin: 0 auto;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <script type="text/php">
        if (isset($pdf)) {
        // Posição Y (Altura da página - 40px)
        $y = $pdf->get_height() - 40; 
        $w = $pdf->get_width();
        
        // Configuração de fonte (Helvetica padrão)
        $font = null; 
        $size = 7;
        $color = array(0.3, 0.3, 0.3); // Cinza

        // 1. LINHA SEPARADORA (X1, Y1, X2, Y2, Cor, Espessura)
        $pdf->line(20, $y - 5, $w - 20, $y - 5, array(0, 0, 0), 1);

        // 2. TEXTO ESQUERDA
        $pdf->page_text(20, $y, "Gerado eletronicamente pelo sistema", $font, $size, $color);

        // 3. Texto Direita com o LOTE INJETADO VIA PHP
        $textoDireita = "Resumo Lote (<?= $h['lote_numero'] ?>) - pág. {PAGE_NUM} de {PAGE_COUNT}";
        
        $pdf->page_text($w - 131, $y, $textoDireita, $font, $size, $color);
    }
    </script>

    <table class="header-table">
        <tr>
            <td width="20%"><img src="<?= $logoSrc ?>" style="height: 50px;"></td>
            <td width="60%" class="text-center">
                <h2 style="margin:0;">RESUMO DE PRODUÇÃO (LOTE)</h2>
                <h3>PRODUTO: CAMARÃO</h3>
            </td>
            <td width="20%" class="text-right" style="font-size: 9px;">
                Emissão: <?= date('d/m/Y') ?><br>
                Hora: <?= date('H:i') ?><br>
                Usuário: <?= $_SESSION['nomeUsuario'] ?>
            </td>
        </tr>
    </table>

    <div class="section-title">RESUMO LOTE</div>
    <table border="1">
        <!-- LINHA 01: LOTE E NOTA FISCAL -->
        <tr>
            <td width="15%"><strong>Lote:</strong></td>
            <td width="35%"><?= $loteCompleto ?>

                <span style="background-color: <?= $bgBadge ?>; 
                    color: <?= $textBadge ?>; 
                    padding: 2px 6px; 
                    border-radius: 3px; 
                    font-size: 8px;
                    font-weight: bold;
                    margin-left: 10px;
                    vertical-align:middle;
                    text-transform: uppercase;">
                    <?= $h['lote_status'] ?>
                </span>
            </td>
            <td width="15%"><strong>N. Fiscal:</strong></td>
            <td width="35%"><?= $numeroNF ?? '-' ?></td>
        </tr>

        <!-- LINHA 02: DATA LOTE E PESO NOTA FISCAL -->
        <tr>
            <td><strong>Data::</strong></td>
            <td><?= date('d/m/Y', strtotime($h['lote_data_fabricacao'])) ?></td>
            <td><strong>P. N. Fiscal:</strong></td>
            <td><?= number_format($totalRecebidoKg, 3, ',', '.') ?> kg</td>
        </tr>

        <!-- LINHA 03: CLIENTE E GRAMATURAS -->
        <tr>
            <td><strong>Cliente:</strong></td>
            <td><?= $nomeCliente ?></td>
            <td><strong>Gram. Faz / Lab:</strong></td>
            <td>
                <?= (number_format($gramFaz, 2, ',', '.') ?? '-') ?>g /
                <?= (number_format($gramaInd, 2, ',', '.')) ?>g
            </td>
        </tr>

        <!-- LINHA 04: FORNECEDOR E TOTAL BENEFICIADO -->
        <tr>
            <td><strong>Fornecedor:</strong></td>
            <td><?= $h['nome_fornecedor'] ?></td>
            <td><strong>T. Benef. (Entrada):</strong></td>
            <td><?= number_format($pesoBeneficiadoTotal, 2, ',', '.') ?> kg</td>
        </tr>

        <!-- LINHA 05: VIVEIRO E TOTAL PRODUZIDO -->
        <tr>
            <td><strong>Viv.:</strong></td>
            <td><?= $h['lote_viveiro'] ?></td>
            <td><strong>Produção (Saída):</strong></td>
            <td><?= number_format($pesoProduzidoTotal, 3, ',', '.') ?> kg</td>
        </tr>

        <!-- LINHA 06: PESO MEDIO (IND) E APROVEITAMENTO -->
        <tr>
            <td><strong>P. Médio (Ind):</strong></td>
            <td><?= number_format($recebimento[0]['item_receb_peso_medio_ind'], 2, ',', '.') ?? '-' ?> kg/cx</td>
            <td><strong>Aprov. (kg / %):</strong></td>
            <td>
                <span style="color: <?= $corAprov ?>; font-weight: bold;">
                    <?= number_format($aprovQuilo, 2, ',', '.') ?> kg /
                    <?= number_format($rendimento, 2, ',', '.') ?> %
                </span>
            </td>
        </tr>

        <!-- LINHA 07: OBSERVACAO -->
        <tr>
            <td><strong>Observação:</strong></td>
            <td colspan="3"><?= $h['lote_observacao'] ?? '' ?></td>
        </tr>
    </table>

    <div style="font-weight: bold; margin-top: 15px;">DETALHAMENTO</div>

    <div class="section-title">RECEBIMENTO (MATÉRIA PRIMA)</div>
    <table border="1">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="10%">Data</th>
                <th width="10%">NF</th>
                <th width="15%">Origem</th>
                <th width="20%">Produto</th>
                <th width="10%">Peso NF</th>
                <th width="10%">Caixas</th>
                <th width="10%">P. Médio</th>
                <th width="10%">Gram.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recebimento as $i => $r):

            ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td class="text-center"><?= date('d/m/Y', strtotime($h['lote_data_fabricacao'])) ?></td>
                    <td class="text-center"><?= $r['item_receb_nota_fiscal'] ?></td>
                    <td><?= $r['origem_nome'] ?? $h['nome_fornecedor'] ?></td>
                    <td><?= $r['prod_descricao'] ?></td>
                    <td class="text-center"><?= number_format($r['item_receb_peso_nota_fiscal'], 3, ',', '.') ?></td>
                    <td class="text-center"><?= $totalCaixas ?></td>
                    <td class="text-center"><?= number_format($pesoMedioFaz, 2, ',', '.') ?></td>
                    <td class="text-center"><?= number_format($r['item_receb_gram_faz'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">PRODUÇÃO (PRIMÁRIA)</div>
    <table border="1">
        <thead>
            <tr>
                <th width="10%">Cód.</th>
                <th width="40%">Descrição</th>
                <th width="15%">Marca</th>
                <th width="5%">Und</th>
                <th width="10%">Qtd</th>
                <th width="10%">Peso</th>
                <th width="10%">P. Benef.</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($producao as $p):
                $pesoTotalItem = $p['prod_peso_embalagem'] * $p['item_prod_quantidade'];
                $fator = (float)$p['fator_atual'] / 100;
                if ($fator > 0) {
                    $pesoBeneficiadoItem = $pesoTotalItem / $fator;
                } else {
                    $pesoBeneficiadoItem = 0;
                }

            ?>
                <tr>
                    <td class="text-center"><?= $p['prod_codigo_interno'] ?></td>
                    <td><?= $p['prod_descricao'] ?></td>
                    <td class="text-center"><?= $p['prod_marca'] ?></td>
                    <td class="text-center"><?= $p['prod_unidade'] ?></td>
                    <td class="text-center"><?= number_format($p['item_prod_quantidade'], 3, ',', '.') ?></td>
                    <td class="text-center"><?= number_format($pesoTotalItem, 3, ',', '.') ?></td>
                    <td class="text-center"><?= number_format($pesoBeneficiadoItem, 2, ',', '.') ?> kg</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">EMBALAGEM (SECUNDÁRIA)</div>
    <table border="1">
        <thead>
            <tr>
                <th width="10%">Cód.</th>
                <th width="40%">Descrição</th>
                <th width="15%">Marca</th>
                <th width="5%">Und</th>
                <th width="10%">Qtd Cxs</th>
                <th width="10%">Peso Total</th>
                <th width="10%">% Total</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $totalGeralEmb = 0;
            foreach ($embalagem as $e) {
                $pesoTotalItem = $e['prod_peso_embalagem'] * $e['item_emb_qtd_sec'];
                $totalGeralEmb += $pesoTotalItem;
            }
            ?>
            <?php foreach ($embalagem as $e):
                $pesoTotalItem = $e['prod_peso_embalagem'] * $e['item_emb_qtd_sec'];
                $perc = ($totalEmbaladoKg > 0) ? ($pesoTotalItem / $totalEmbaladoKg) * 100 : 0;
            ?>
                <tr>
                    <td class="text-center"><?= $e['prod_codigo_interno'] ?></td>
                    <td><?= $e['prod_descricao'] ?></td>
                    <td class="text-center"><?= $e['prod_marca'] ?></td>
                    <td class="text-center"><?= $e['prod_unidade'] ?></td>
                    <td class="text-center"><?= (int)$e['item_emb_qtd_sec'] ?></td>
                    <td class="text-center"><?= number_format($pesoTotalItem, 3, ',', '.') ?> kg</td>
                    <td class="text-center"><?= number_format($perc, 2, ',', '.') ?> %</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br><br><br><br><br><br><br><br><br><br>

    <div style="text-align: center;">
        <div class="signature-line"></div>
        <p>Responsável pelos dados</p>
    </div>
</body>

</html>
<?php
// GERA O PDF
$html = ob_get_clean();

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$output = $dompdf->output();
file_put_contents($caminhoFisico, $output);

// Redireciona o JS para o arquivo gerado
header("Content-type: application/json");
echo json_encode(['success' => true, 'url' => $urlPublica]);
exit;
?>