<?php
// views/lotes_novo/relatorio_mensal_lotes.php

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. LOGO BASE64 (Para não ter erro de imagem quebrada)
$pathLogo = __DIR__ . '/../../public/img/logo_marchef.png';
$logoSrc = '';
if (file_exists($pathLogo)) {
    $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
    $data = file_get_contents($pathLogo);
    $logoSrc = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

// 2. HELPER DE GRAMATURA (Decimais opcionais + sufixo "g")
$formatarGramatura = function ($valorBruto) {
    if (empty($valorBruto)) return '-';

    // Divide caso tenha múltiplos (ex: "18 / 19.5")
    $partes = explode(' / ', $valorBruto);
    $partesFormatadas = array_map(function ($val) {
        $num = (float)$val;
        // Se for inteiro exato, sem decimais. Se não, 2 casas.
        $str = (floor($num) == $num)
            ? number_format($num, 0, ',', '.')
            : number_format($num, 2, ',', '.');
        return $str . 'g';
    }, $partes);

    return implode(' / ', $partesFormatadas);
};

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Lotes</title>
    <style>
        @page {
            margin: 15px 35px 40px 15px;
            size: A4 landscape;
        }

        body {
            font-family: Helvetica, sans-serif;
            font-size: 8pt;
            color: #333;
        }

        table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }



        th,
        td {
            border: 1px solid #999;
            padding: 5px;
            vertical-align: middle;
            line-height: 1.2;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 7pt;
            text-transform: uppercase;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        /* Larguras Otimizadas */
        .w-data {
            width: 60px;
        }

        .w-lote {
            width: 100px;
        }

        .w-forn {
            width: 190px;

        }

        .w-peso {
            width: 80px;
        }

        .w-cx {
            width: 40px;
        }

        .w-gram {
            width: 80px;
        }

        .w-reproc {
            width: 100px;
            font-size: 7pt;
        }

        .w-obs {
            width: 180px;
            font-size: 7pt;
        }

        .ultima_coluna {
            width: 1px;
            /* mínima largura */
            padding: 0;
            border-left: 1px solid #999;
            /* força a borda */
            border-right: none;
            /* não precisa desenhar do outro lado */
        }
    </style>
</head>

<body>

    <table style="border: none; margin-bottom: 10px;">
        <tr style="border: none;">
            <td style="border: none; width: 20%; padding: 0;">
                <?php if ($logoSrc): ?>
                    <img src="<?= $logoSrc ?>" style="max-height: 50px;">
                <?php endif; ?>
            </td>
            <td style="border: none; width: 60%; text-align: center;">
                <h2 style="margin: 0; font-size: 16pt;">ABERTURA DE LOTES</h2>
                <h3 style="margin: 5px 0 0 0; font-size: 10pt; font-weight: normal; color: #555;">PERÍODO: <strong><?= $periodoTexto ?></strong></h3>

                <?php if (!empty($nomesClientesStr)): ?>
                    <h3 style="margin: 2px 0 0 0; font-size: 9pt; font-weight: normal; color: #333;">
                        FORNECEDOR(ES): <strong><?= mb_strtoupper($nomesClientesStr) ?></strong>
                    </h3>
                <?php endif; ?>

                <?php if (!empty($nomesProdutosStr)): ?>
                    <h3 style="margin: 2px 0 0 0; font-size: 9pt; font-weight: normal; color: #333;">
                        PRODUTO(S): <strong><?= mb_strtoupper($nomesProdutosStr) ?></strong>
                    </h3>
                <?php endif; ?>
            </td>
            <td style="border: none; width: 20%; text-align: right; font-size: 8pt; vertical-align: bottom;">
                Emissão: <?= date('d/m/Y H:i') ?>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th class="w-data">Data</th>
                <th class="w-lote">Lote</th>
                <th class="w-forn">Fornecedor</th>
                <th class="w-peso">Quantidade Recebida (Kg)</th>
                <th class="w-cx">Quant. Basquetas</th>
                <th class="w-gram">Gram. Faz</th>
                <th class="w-gram">Gram. Benef</th>
                <th class="w-reproc">Lote Reprocesso</th>
                <th class="w-obs">Observações</th>
                <th class="ultima_coluna"></th>
            </tr>
        </thead>

        <tbody>
            <?php
            $totalPeso = 0;
            $totalCaixas = 0;

            // 1. Variáveis para Média Ponderada (Acumuladores)
            $somaPonderadaFaz = 0;
            $somaPonderadaBenef = 0;
            $divisorFaz = 0;
            $divisorBenef = 0;

            if (empty($dados)): ?>
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px;">Nenhum registro encontrado.</td>
                </tr>
                <?php else:
                foreach ($dados as $d):
                    $peso = (float)$d['total_peso'];
                    $caixas = (int)$d['total_caixas'];

                    // Acumula totais simples
                    $totalPeso += $peso;
                    $totalCaixas += $caixas;

                    // Lógica da Média Ponderada: (Gramatura * Peso do Lote)
                    // Usaremos o $peso como o "peso" da média ponderada.

                    if (!empty($d['gram_faz'])) {
                        $partes = explode(' / ', $d['gram_faz']);
                        // Se houver mais de uma gramatura no mesmo lote, tiramos a média simples delas 
                        // para representar o lote, e depois ponderamos pelo peso total do lote.
                        $somaLocal = 0;
                        $qtdLocal = 0;
                        foreach ($partes as $p) {
                            if (is_numeric($p)) {
                                $somaLocal += (float)$p;
                                $qtdLocal++;
                            }
                        }
                        if ($qtdLocal > 0) {
                            $mediaLocal = $somaLocal / $qtdLocal;
                            $somaPonderadaFaz += ($mediaLocal * $peso);
                            $divisorFaz += $peso;
                        }
                    }

                    if (!empty($d['gram_benef'])) {
                        $partes = explode(' / ', $d['gram_benef']);
                        $somaLocal = 0;
                        $qtdLocal = 0;
                        foreach ($partes as $p) {
                            if (is_numeric($p)) {
                                $somaLocal += (float)$p;
                                $qtdLocal++;
                            }
                        }
                        if ($qtdLocal > 0) {
                            $mediaLocal = $somaLocal / $qtdLocal;
                            $somaPonderadaBenef += ($mediaLocal * $peso);
                            $divisorBenef += $peso;
                        }
                    }
                ?>
                    <tr>
                        <td class="text-center"><?= date('d/m/Y', strtotime($d['lote_data_fabricacao'])) ?></td>
                        <td class="text-center fw-bold"><?= $d['lote_completo_calculado'] ?></td>
                        <td><?= mb_strimwidth($d['fornecedor_nome'], 0, 22, '...') ?></td>
                        <td class="text-right"><?= number_format($peso, 3, ',', '.') ?></td>
                        <td class="text-center"><?= $caixas ?></td>

                        <td class="text-center"><?= $formatarGramatura($d['gram_faz']) ?></td>
                        <td class="text-center"><?= $formatarGramatura($d['gram_benef']) ?></td>

                        <td class="text-center"><?= $d['lote_reprocesso_origem'] ?: '-' ?></td>
                        <td><?= nl2br(htmlspecialchars($d['lote_observacao'] ?? '')) ?></td>
                        <td class="ultima_coluna"></td>
                    </tr>

            <?php endforeach;
            endif;

            // Cálculo das Médias Ponderadas Finais
            $mediaFaz = ($divisorFaz > 0) ? ($somaPonderadaFaz / $divisorFaz) : 0;
            $mediaBenef = ($divisorBenef > 0) ? ($somaPonderadaBenef / $divisorBenef) : 0;
            ?>
        </tbody>

        <tfoot>
            <tr style="background-color: #e9ecef; font-weight: bold;">
                <td colspan="3" class="text-right">TOTAIS / MÉDIA:</td>
                <td class="text-right"><?= number_format($totalPeso, 3, ',', '.') ?></td>
                <td class="text-center"><?= $totalCaixas ?></td>
                <td class="text-center" style="font-size: 7pt;">
                    <?= $mediaFaz > 0 ? number_format($mediaFaz, 2, ',', '.') . 'g' : '-' ?>
                </td>

                <td class="text-center" style="font-size: 7pt;">
                    <?= $mediaBenef > 0 ? number_format($mediaBenef, 2, ',', '.') . 'g' : '-' ?>
                </td>
                <td colspan="4" style="background-color: #fff; border: none;"></td>
            </tr>
        </tfoot>

    </table>

    <div style="margin-top: 40px; text-align: center; page-break-inside: avoid;">
        <div style="width: 40%; margin: 19 auto; border-top: 1px solid #000; padding-top: 5px; font-size: 7pt;">
            GERENTE DE PRODUÇÃO
        </div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("helvetica", "normal");
            $size = 7;
            $color = [0.4, 0.4, 0.4];  // cinza

            $y = $pdf->get_height() - 30; 
            $w = $pdf->get_width();

            // Margens laterais iguais às do page
            $left_margin_pt  = 12;
            $right_margin_pt = 12;

            // Texto esquerdo
            $pdf->page_text($left_margin_pt, $y, "Gerado eletronicamente pelo sistema", $font, $size, $color);

            // Texto direito (página X de Y) 
            $pdf->page_text($w - $right_margin_pt - 55, $y, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, $size, $color);

            // Linha horizontal full entre as margens (sem sobras)
            $pdf->page_line(
                $left_margin_pt,          // x1
                $y - 8,                   // y1 (8pt acima do texto)
                $w - $right_margin_pt,    // x2
                $y - 8,                   // y2
                [0.5, 0.5, 0.5],          // cor cinza
                0.5                       // espessura
            );
        }
    </script>

</body>

</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isPhpEnabled', true); // Necessário para o script de rodapé
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Relatorio_Lotes_$ano.pdf", ["Attachment" => false]);
