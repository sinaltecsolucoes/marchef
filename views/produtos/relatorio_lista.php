<?php
// views/produtos/relatorio_lista.php
// Relatório de Listagem Geral de Produtos (Versão Otimizada para PDF Rápido)

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\Produtos\ProdutoRepository;

// --- 1. CONFIGURAÇÕES INICIAIS E FILTROS ---
if (!isset($_SESSION['codUsuario'])) die("Acesso negado.");

// Captura Filtros (Com fallback para 'Todos')
$modo = filter_input(INPUT_GET, 'modo', FILTER_DEFAULT) ?? 'html';
$filtroSituacao = filter_input(INPUT_GET, 'filtro', FILTER_DEFAULT) ?? 'Todos';
$filtroTipo = filter_input(INPUT_GET, 'tipo', FILTER_DEFAULT) ?? 'Todos';
$search = filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '';

// --- 2. BUSCA DE DADOS ---
try {
    $pdo = Database::getConnection();
    $produtoRepo = new ProdutoRepository($pdo);
    // Passamos os filtros para o repositório
    $produtos = $produtoRepo->getDadosRelatorioGeral($filtroSituacao, $search, $filtroTipo);
} catch (Exception $e) {
    die("Erro ao buscar produtos: " . $e->getMessage());
}

// --- 3. PREPARAÇÃO DO TÍTULO E NOME DO ARQUIVO ---

// A) Subtítulo Visual
$subtituloParts = [];
if (strtoupper($filtroSituacao) !== 'TODOS') {
    $subtituloParts[] = "Situação: " . strtoupper($filtroSituacao);
}
if (strtoupper($filtroTipo) !== 'TODOS') {
    $subtituloParts[] = "Tipo: " . strtoupper($filtroTipo);
}
if (!empty($search)) {
    $subtituloParts[] = "Busca: '" . htmlspecialchars($search) . "'";
}
$textoSubtitulo = !empty($subtituloParts) ? "[ " . implode(" | ", $subtituloParts) . " ]" : "[ Geral ]";

// B) Nome do Arquivo (Cache)
$sufixoArquivo = "_" . strtoupper($filtroSituacao) . "_" . strtoupper($filtroTipo);
if (!empty($search)) {
    $sufixoArquivo .= "_BUSCA";
}
$sufixoArquivo = preg_replace('/[^A-Z0-9_]/', '', $sufixoArquivo);
$nomeArquivo = 'listagem_produtos' . $sufixoArquivo . '.pdf';

// C) Pastas
$pastaRelatorios = __DIR__ . '/../../public/uploads/relatorios_gerais';
$caminhoFisico = $pastaRelatorios . '/' . $nomeArquivo;
$urlPublica = BASE_URL . '/uploads/relatorios_gerais/' . $nomeArquivo;

// Verifica existência
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
            margin: 20px 15px;
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
            text-align: left;
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
                const urlGerar = "index.php?page=relatorio_produtos&filtro=<?= $filtroSituacao ?>&tipo=<?= $filtroTipo ?>&search=<?= urlencode($search) ?>&modo=pdf&force=true";

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
                    <th width="28%">Descrição</th>
                    <th width="12%">Tipo</th>
                    <th width="24%">Produto Base (Se Secundário)</th>
                    <th width="12%">EAN</th>
                    <th width="10%">DUN</th>
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

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
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