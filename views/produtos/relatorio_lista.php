<?php
// views/produtos/relatorio_lista.php
// Relatório de Listagem Geral de Produtos (Versão Otimizada para PDF Rápido)

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\Produtos\ProdutoRepository;

// --- 1. CONFIGURAÇÕES INICIAIS E FILTROS ---
if (!isset($_SESSION['codUsuario'])) die("Acesso negado.");

// 1. Captura Filtros (Recebe como STRING da URL)
$modo              = filter_input(INPUT_GET, 'modo', FILTER_DEFAULT) ?? 'html';
$filtroSituacaoStr = filter_input(INPUT_GET, 'filtro', FILTER_DEFAULT) ?? 'TODOS';
$filtroTipoStr     = filter_input(INPUT_GET, 'tipo', FILTER_DEFAULT) ?? 'TODOS';
$filtroMarcasStr   = filter_input(INPUT_GET, 'marcas', FILTER_DEFAULT) ?? 'TODOS';
$search            = filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '';

// 2. Converte para ARRAYS
$filtroSituacao = ($filtroSituacaoStr === 'TODOS' || empty($filtroSituacaoStr)) ? ['TODOS'] : explode(',', $filtroSituacaoStr);
$filtroTipo     = ($filtroTipoStr === 'TODOS' || empty($filtroTipoStr)) ? ['TODOS'] : explode(',', $filtroTipoStr);
$filtroMarcas   = ($filtroMarcasStr === 'TODOS' || empty($filtroMarcasStr)) ? ['TODOS'] : explode(',', $filtroMarcasStr);

// --- 2. BUSCA DE DADOS ---
try {
    $pdo = Database::getConnection();
    $produtoRepo = new ProdutoRepository($pdo);
    // Passamos os ARRAYS, que é o que a função pede
    $produtos = $produtoRepo->getDadosRelatorioGeral($filtroSituacao, $search, $filtroTipo, $filtroMarcas);
} catch (Exception $e) {
    die("Erro ao buscar produtos: " . $e->getMessage());
}

// --- 3. PREPARAÇÃO DO TÍTULO E NOME DO ARQUIVO ---

// A) Subtítulo Visual
$subtituloParts = [];

// 1. Situação (COM TRADUÇÃO A -> ATIVO, I -> INATIVO)
if (!in_array('TODOS', $filtroSituacao)) {
    // Traduz cada sigla do array
    $sitLegiveis = array_map(function ($sigla) {
        if ($sigla === 'A') return 'ATIVO';
        if ($sigla === 'I') return 'INATIVO';
        return $sigla;
    }, $filtroSituacao);

    $subtituloParts[] = "Situação: " . implode(', ', $sitLegiveis);
}

// 2. Tipo
if (!in_array('TODOS', $filtroTipo)) {
    $subtituloParts[] = "Tipo Embalagem: " . implode(', ', $filtroTipo);
}

// 3. Marcas
if (!in_array('TODOS', $filtroMarcas)) {
    $qtd = count($filtroMarcas);
    // Se tiver muitas marcas, mostra só a quantidade para não poluir
    $txtMarcas = ($qtd > 3) ? "$qtd marcas" : implode(', ', $filtroMarcas);
    $subtituloParts[] = "Marcas: " . $txtMarcas;
}

// 4. Busca
if (!empty($search)) {
    $subtituloParts[] = "Busca: '" . htmlspecialchars($search) . "'";
}

$textoSubtitulo = !empty($subtituloParts) ? "[ " . implode(" | ", $subtituloParts) . " ]" : "[ Geral ]";

// B) Nome do Arquivo
// Cria um hash único baseado nos filtros para o cache funcionar, mas mantém nome limpo
$sufixoArquivo = md5($filtroSituacaoStr . $filtroTipoStr . $filtroMarcasStr . $search);
$nomeArquivo = 'listagem_produtos_' . substr($sufixoArquivo, 0, 8) . '.pdf';

// C) Pastas
$pastaRelatorios = __DIR__ . '/../../public/uploads/relatorios_gerais';

// Garante que a pasta existe (segurança extra)
if (!is_dir($pastaRelatorios)) {
    mkdir($pastaRelatorios, 0777, true);
}

$caminhoFisico = $pastaRelatorios . '/' . $nomeArquivo;
$urlPublica = BASE_URL . '/uploads/relatorios_gerais/' . $nomeArquivo;

// Verifica existência para cache (opcional)
$arquivoExiste = file_exists($caminhoFisico);
$dataArquivo = $arquivoExiste ? date('d/m/Y H:i', filemtime($caminhoFisico)) : '';

// --- 4. LOGO ---
$pathLogo = __DIR__ . '/../../public/img/logo_marchef.png';
$logoSrc = '';
if (file_exists($pathLogo)) {
    $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
    $data = file_get_contents($pathLogo);
    $logoSrc = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Listagem de Produtos</title>

    <?php if ($modo !== 'pdf'): ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php endif; ?>

    <style>
        @page {
            size: landscape;
            margin: 20px 15px 50px 15px;
            /* top right bottom left — 50px bottom reserva espaço pro rodapé */
        }

        body {
            font-family: sans-serif;
            font-size: 9px;
            color: #333;
            background-color: #fff;
            margin: 0;
        }

        /* Estilos manuais para garantir beleza no PDF sem Bootstrap */
        .header-container {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        table.header-layout {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }

        table.header-layout td {
            border: none;
            vertical-align: middle;
            padding: 5px;
        }

        .company-title {
            font-size: 16px;
            font-weight: bold;
            color: #0d6efd;
            margin: 0;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 5px 0 2px 0;
        }

        .report-subtitle {
            font-size: 10px;
            color: #666;
            font-weight: bold;
        }

        .report-meta {
            font-size: 9px;
            color: #666;
            text-align: right;
        }

        .logo-img {
            max-height: 50px;
            width: auto;
        }

        table.table-data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.table-data th,
        table.table-data td {
            border: 1px solid #ddd;
            padding: 4px 6px;
            vertical-align: middle;
        }

        table.table-data th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .text-end {
            text-align: right;
        }

        /* Cores manuais para substituir as classes do Bootstrap no PDF */
        .badge-prim {
            color: #0d6efd;
            font-weight: bold;
        }

        /* Azul */
        .badge-sec {
            color: #fd7e14;
            font-weight: bold;
        }

        /* Laranja */
        .muted {
            color: #999;
        }

        .actions-bar {
            background: #f8f9fa;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            text-align: right;
        }

        @media print {
            .actions-bar {
                display: none;
            }
        }
    </style>
</head>

<body>

    <?php if ($modo !== 'pdf'): ?>
        <div class="actions-bar d-print-none">
            <button onclick="verificarEBaixarPdf()" class="btn btn-danger btn-sm">
                Baixar PDF
            </button>
            <button onclick="window.print()" class="btn btn-secondary btn-sm ms-2">
                Imprimir
            </button>
        </div>
        <script>
            function verificarEBaixarPdf() {
                const existe = <?= json_encode($arquivoExiste) ?>;
                const data = "<?= $dataArquivo ?>";
                const urlUrl = "<?= $urlPublica ?>";
                // URL para forçar nova geração
                const urlGerar = "index.php?page=relatorio_produtos" +
                    "&filtro=<?= implode(',', $filtroSituacao) ?>" +
                    "&tipo=<?= implode(',', $filtroTipo) ?>" +
                    "&marcas=<?= implode(',', $filtroMarcas) ?>" +
                    "&search=<?= urlencode($search) ?>" +
                    "&modo=pdf&force=true";

                if (existe) {
                    Swal.fire({
                        title: 'Relatório encontrado!',
                        html: `Já existe um arquivo gerado com estes filtros em <b>${data}</b>.<br>Deseja abrir o existente ou gerar um novo?`,
                        icon: 'question',
                        showDenyButton: true,
                        confirmButtonText: 'Abrir Existente',
                        denyButtonText: 'Gerar Novo',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) window.open(urlUrl, '_blank');
                        else if (result.isDenied) window.location.href = urlGerar;
                    });
                } else {
                    window.location.href = urlGerar;
                }
            }
        </script>
    <?php endif; ?>

    <div class="container-fluid">
        <div class="header-container">
            <table class="header-layout">
                <tr>
                    <td width="20%" align="left">
                        <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" alt="Logo" class="logo-img"><?php endif; ?>
                    </td>
                    <td width="60%" align="center">
                        <div class="report-title">Listagem de Produtos</div>
                        <div class="report-subtitle"><?= $textoSubtitulo ?></div>
                    </td>
                    <td width="20%" align="right">
                        <div class="report-meta">
                            Emissão: <?= date('d/m/Y H:i') ?><br>
                            Usuário: <?= $_SESSION['nomeUsuario'] ?? 'Sistema' ?><br>
                            Total: <?= count($produtos) ?> itens
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="table-data">
            <thead>
                <tr>
                    <th width="8%">Cód. Int.</th>
                    <th width="31%">Descrição</th>
                    <th width="8%">Tipo</th>
                    <th width="31%">Produto Base (Se Secundário)</th>
                    <th width="8%">EAN</th>
                    <th width="8%">DUN</th>
                    <th width="6%">NCM</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $prod):
                    $isSec = ($prod['prod_tipo_embalagem'] === 'SECUNDARIA');
                ?>
                    <tr>
                        <td class="text-center"><?= htmlspecialchars($prod['prod_codigo_interno'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($prod['prod_descricao']) ?></td>

                        <td class="text-center">
                            <span class="<?= $isSec ? 'badge-sec' : 'badge-prim' ?>">
                                <?= $isSec ? 'SECUNDÁRIO' : 'PRIMÁRIO' ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($isSec && !empty($prod['nome_primario'])): ?>
                                <div style="line-height: 1.2;">
                                    <?= htmlspecialchars($prod['nome_primario']) ?>
                                    <br>
                                    <span class="muted" style="font-size:8px;">
                                        Cód: <?= $prod['codigo_primario'] ?? 'N/A' ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php
                            // Lógica EAN corrigida
                            if ($isSec) {
                                echo htmlspecialchars($prod['ean_primario'] ?? '');
                            } else {
                                echo htmlspecialchars($prod['prod_ean13'] ?? '');
                            }
                            ?>
                        </td>

                        <td class="text-center">
                            <?php if ($isSec): ?>
                                <?= htmlspecialchars($prod['prod_dun14'] ?? '') ?>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center"><?= htmlspecialchars($prod['prod_ncm'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($produtos)): ?>
                    <tr>
                        <td colspan="7" class="text-center p-3">Nenhum produto encontrado com os filtros atuais.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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

if ($modo === 'pdf') {
    try {
        // Configuração limpa para evitar travamentos
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true); // Necessário para o Logo em base64 ou imagens locais
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'sans-serif');
        $options->set('isPhpEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        // $dompdf->setPaper('A4', 'portrait'); // Para o formato da pagina Retrato
        $dompdf->setPaper('A4', 'landscape'); // Para o formato da pagina Paisagem
        $dompdf->render();
        $output = $dompdf->output();

        // Garante que a pasta existe antes de salvar
        if (!is_dir($pastaRelatorios)) {
            mkdir($pastaRelatorios, 0777, true);
        }

        file_put_contents($caminhoFisico, $output);

        header("Content-type: application/pdf");
        header("Content-Disposition: inline; filename={$nomeArquivo}");
        echo $output;
    } catch (Exception $e) {
        // Se der erro, exibe na tela para debug
        echo "Erro Crítico ao Gerar PDF: " . $e->getMessage();
    }
} else {
    echo $html;
}
?>