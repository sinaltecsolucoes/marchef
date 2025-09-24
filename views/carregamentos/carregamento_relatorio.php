<?php
// public/carregamento_relatorio.php
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\Carregamentos\CarregamentoRepository;

if (!isset($_SESSION['codUsuario'])) {
    die("Acesso negado.");
}

$carregamentoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$carregamentoId) {
    die("Erro: ID do carregamento não fornecido.");
}

try {
    $pdo = Database::getConnection();
    $repo = new CarregamentoRepository($pdo);
    $dados = $repo->getDadosCompletosParaRelatorio($carregamentoId);

    if (empty($dados) || !$dados['header']) {
        die("Nenhum dado encontrado para este carregamento.");
    }

    $header = $dados['header'];
    $filas = $dados['filas'];
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/";

    // ### NOVO: Coleta todas as fotos para exibir no final ###
    $todasAsFotos = [];
    foreach ($filas as $fila) {
        if (!empty($fila['fotos'])) {
            $todasAsFotos[$fila['fila_numero']] = $fila['fotos'];
        }
    }

} catch (Exception $e) {
    die("Ocorreu um erro inesperado no servidor.<p>Detalhe: " . $e->getMessage() . "</p>");
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Carregamento - Nº <?php echo htmlspecialchars($header['car_numero']); ?></title>
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/img/icone_2.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/relatorios.css" rel="stylesheet">
    <link href="libs/lightbox2/dist/css/lightbox.min.css" rel="stylesheet" />
</head>

<body>
    <div class="container report-container">

        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-3">
                    <img src="img/logo_marchef.png" alt="Logo" style="max-width: 150px;">
                </div>
                <div class="col-9">
                    <h4 class="mb-1">Relatório de Carregamento</h4>
                    <p class="mb-0"><strong>Nº Carregamento:</strong>
                        <?php echo htmlspecialchars($header['car_numero']); ?></p>
                    <p class="mb-0"><strong>Data:</strong>
                        <?php echo (new DateTime($header['car_data']))->format('d/m/Y'); ?></p>
                    <p class="mb-0"><strong>OE Origem:</strong>
                        <?php echo htmlspecialchars($header['oe_numero'] ?: 'N/A'); ?></p>
                    <p class="mb-0"><strong>Motorista:</strong>
                        <?php echo htmlspecialchars($header['car_motorista_nome'] ?: 'N/A'); ?> |
                        <strong>Placa:</strong> <?php echo htmlspecialchars($header['car_placas'] ?: 'N/A'); ?></p>
                </div>
            </div>

            <div class="mt-3">
                <button class="btn btn-primary no-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir / Salvar PDF
                </button>
            </div>
        </div>

        <?php foreach ($filas as $fila): ?>
            <div class="fila-container my-4">
                <h5 class="fila-title">Fila <?php echo $fila['fila_numero']; ?></h5>

                <h6>Itens Carregados:</h6>

                <?php
                // --- INÍCIO DA NOVA LÓGICA DE PROCESSAMENTO DE DADOS ---
            
                // 1. Agrega (soma) itens idênticos
                $itensAgregados = [];
                foreach ($fila['itens'] as $item) {
                    $chave = $item['cliente_nome'] . '|' . $item['prod_codigo_interno'] . '|' . $item['lote_completo'] . '|' . ($item['car_item_motivo_divergencia'] ?? '');
                    if (!isset($itensAgregados[$chave])) {
                        $itensAgregados[$chave] = $item;
                    } else {
                        $itensAgregados[$chave]['qtd_carregada'] += $item['qtd_carregada'];
                    }
                }

                // 2. Agrupa os itens para a renderização com rowspan
                $gruposParaTabela = [];
                foreach (array_values($itensAgregados) as $item) {
                    $gruposParaTabela[$item['cliente_nome']][$item['prod_descricao']][] = $item;
                }

                // --- FIM DA NOVA LÓGICA ---
                ?>

                <table class="table table-sm table-bordered" style="width: 100%; table-layout: fixed;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 20%;" class="text-center align-middle">Cliente</th>
                            <th style="width: 10%;" class="text-center align-middle">Cód. Interno</th>
                            <th style="width: 25%;" class="text-center align-middle">Produto</th>
                            <th style="width: 15%;" class="text-center align-middle">Lote</th>
                            <th style="width: 5%;" class="text-center align-middle">Qtd.</th>
                            <th style="width: 25%;" class="text-center align-middle">Divergência (Motivo)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($gruposParaTabela)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Nenhum item carregado nesta fila.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($gruposParaTabela as $nomeCliente => $produtosDoCliente): ?>
                                <?php
                                // Calcula o rowspan para o cliente
                                $clienteRowspan = 0;
                                foreach ($produtosDoCliente as $itensDoProduto) {
                                    $clienteRowspan += count($itensDoProduto);
                                }
                                $primeiraLinhaCliente = true;
                                ?>
                                <?php foreach ($produtosDoCliente as $nomeProduto => $itensDoProduto): ?>
                                    <?php
                                    // Calcula o rowspan para o produto
                                    $produtoRowspan = count($itensDoProduto);
                                    $primeiraLinhaProduto = true;
                                    ?>
                                    <?php foreach ($itensDoProduto as $item): ?>
                                        <tr>
                                            <?php if ($primeiraLinhaCliente):
                                                $primeiraLinhaCliente = false; ?>
                                                <td rowspan="<?php echo $clienteRowspan; ?>" class="align-middle">
                                                    <?php echo htmlspecialchars($nomeCliente); ?>
                                                </td>
                                            <?php endif; ?>

                                            <?php if ($primeiraLinhaProduto):
                                                $primeiraLinhaProduto = false; ?>
                                                <td rowspan="<?php echo $produtoRowspan; ?>" class="text-center align-middle">
                                                    <?php echo htmlspecialchars($item['prod_codigo_interno']); ?>
                                                </td>
                                                <td rowspan="<?php echo $produtoRowspan; ?>" class="align-middle">
                                                    <?php echo htmlspecialchars($nomeProduto); ?>
                                                </td>
                                            <?php endif; ?>

                                            <td class="text-center align-middle"><?php echo htmlspecialchars($item['lote_completo']); ?>
                                            </td>
                                            <td class="text-center align-middle">
                                                <?php echo number_format((float) $item['qtd_carregada'], 0); ?>
                                            </td>
                                            <td class="align-middle">
                                                <?php echo htmlspecialchars($item['car_item_motivo_divergencia'] ?: ''); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>


        <?php if (!empty($todasAsFotos)): ?>
            <div class="page-break"></div>
            <div class="report-header">
                <h4 class="mb-3">Relatório Fotográfico</h4>
            </div>

            <?php foreach ($todasAsFotos as $filaNumero => $fotosDaFila): ?>
                <div class="fotos-section mb-4">
                    <h6>Fotos da Fila <?php echo $filaNumero; ?>:</h6>
                    <div class="row">
                        <?php foreach ($fotosDaFila as $foto_path): ?>
                            <div class="col-3 mb-3">
                                <a href="<?php echo $baseUrl . $foto_path; ?>" data-lightbox="relatorio-fotografico"
                                    data-title="Fila <?php echo $filaNumero; ?>">
                                    <img src="<?php echo $baseUrl . $foto_path; ?>" class="img-fluid img-thumbnail">
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="libs/jquery/jquery-3.7.1.min.js"></script>
    <script src="libs/lightbox2/dist/js/lightbox.min.js"></script>
</body>

</html>