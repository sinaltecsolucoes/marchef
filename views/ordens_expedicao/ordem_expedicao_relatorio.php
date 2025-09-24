<?php
// public/ordem_expedicao_relatorio.php
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\OrdensExpedicao\OrdemExpedicaoRepository;

/**
 * Formata um número. Se for inteiro, mostra sem casas decimais.
 * Se for decimal, mostra com 3 casas.
 */
function format_peso($valor)
{
    $numero = (float) $valor;
    // Verifica se o número é efetivamente um inteiro (ex: 1.000 ou 5)
    if ($numero == floor($numero)) {
        return number_format($numero, 0); // Ex: 1
    } else {
        return number_format($numero, 3, ',', '.'); // Ex: 0,130
    }
}

if (!isset($_SESSION['codUsuario'])) {
    die("Acesso negado.");
}

$ordemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$ordemId) {
    die("Erro: ID da Ordem de Expedição não fornecido.");
}

try {
    $pdo = Database::getConnection();
    $repo = new OrdemExpedicaoRepository($pdo);
    $ordem = $repo->findOrdemCompleta($ordemId);

    if (!$ordem) {
        die("Nenhum dado encontrado para esta Ordem de Expedição.");
    }
} catch (Exception $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Ordem de Expedição - <?php echo htmlspecialchars($ordem['header']['oe_numero']); ?></title>
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/img/icone_2.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/relatorios.css" rel="stylesheet">

    <style>
        .report-table {
            width: 100%;
            table-layout: fixed;
        }

        .report-table th,
        .report-table td {
            word-break: break-word;
            vertical-align: middle;
        }

        .col-cod {
            width: 8%;
        }

        .col-desc {
            width: 25%;
        }

        .col-p1 {
            width: 5%;
        }

        .col-p2 {
            width: 5%;
        }

        .col-ind {
            width: 8%;
        }

        .col-clote {
            width: 10%;
        }

        .col-lote {
            width: 10%;
        }

        .col-qtdcx {
            width: 5%;
        }

        .col-qtdkg {
            width: 5%;
        }

        .col-end {
            width: 10%;
        }

        .col-obs {
            width: 9%;
        }
    </style>
</head>


<body>
    <div class="container report-container">
        <?php
        $granTotalCaixas = 0;
        $granTotalKg = 0;
        ?>

        <div class="report-header border-bottom pb-3 mb-4">
            <div class="row">
                <div class="col-3">
                    <img src="img/logo_marchef.png" alt="Logo Marchef" style="max-width: 150px;">
                </div>

                <div class="col-9">
                    <h4 class="mb-1">Ordem de Expedição</h4>
                    <p><strong>Nº da Ordem:</strong> <?php echo htmlspecialchars($ordem['header']['oe_numero']); ?></p>
                    <button class="btn btn-primary no-print" onclick="window.print()">Imprimir Roteiro</button>
                </div>
            </div>
        </div>


        <?php foreach ($ordem['pedidos'] as $pedido): ?>
            <div class="pedido-container my-4">
                <h6 class="pedido-title">
                    Cliente: <?php echo htmlspecialchars($pedido['ent_razao_social']); ?>
                    (Pedido: <?php echo htmlspecialchars($pedido['oep_numero_pedido'] ?: 'N/A'); ?>)
                </h6>
                <table class="table table-sm table-bordered report-table">
                    <thead class="table-light">
                        <tr>
                            <th class="col-cod text-center align-middle">CÓD.</th>
                            <th class="col-desc text-center align-middle">DESCRIÇÃO</th>
                            <th class="col-p1 text-center align-middle">P</th>
                            <th class="col-p2 text-center align-middle">S</th>
                            <th class="col-ind text-center align-middle">INDÚSTRIA</th>
                            <th class="col-clote text-center align-middle">FAZENDA</th>
                            <th class="col-lote text-center align-middle">LOTE</th>
                            <th class="col-qtdcx text-center align-middle">QTD CX</th>
                            <th class="col-qtdkg text-center align-middle">QTD KG</th>
                            <th class="col-end text-center align-middle">ENDEREÇO</th>
                            <th class="col-obs text-center align-middle">OBS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedido['itens'])): ?>
                            <tr>
                                <td colspan="11" class="text-center">Nenhum item.</td>
                            </tr>
                        <?php else: ?>
                            <?php
                            // ### INÍCIO - Variáveis de Subtotal por Cliente ###
                            $subTotalCaixas = 0;
                            $subTotalKg = 0;
                            ?>
                            <?php foreach ($pedido['itens'] as $item):
                                $qtdCaixas = (float) ($item['oei_quantidade'] ?? 0);
                                $qtdQuilos = $qtdCaixas * (float) ($item['peso_secundario'] ?? 0);

                                // Acumulando os valores de subtotal
                                $subTotalCaixas += $qtdCaixas;
                                $subTotalKg += $qtdQuilos;
                                ?>
                                <tr>
                                    <td class="text-center align-middle">
                                        <?php echo htmlspecialchars($item['prod_codigo_interno']); ?>
                                    </td>
                                    <td class="align-middle"><?php echo htmlspecialchars($item['prod_descricao']); ?></td>
                                    <td class="text-center align-middle"><?php echo format_peso($item['peso_primario']); ?></td>
                                    <td class="text-center align-middle"><?php echo format_peso($item['peso_secundario']); ?></td>
                                    <td class="text-center align-middle"><?php echo htmlspecialchars($item['industria']); ?></td>
                                    <td class="text-center align-middle"><?php echo htmlspecialchars($item['cliente_lote_nome']); ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php echo htmlspecialchars($item['lote_completo_calculado']); ?>
                                    </td>
                                    <td class="text-center align-middle"><?php echo number_format($qtdCaixas, 0); ?></td>
                                    <td class="text-center align-middle"><?php echo number_format($qtdQuilos, 3, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($item['endereco_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($item['oei_observacao']); ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="table-light fw-bold">
                                <td colspan="7" class="text-end">Subtotal
                                    (<?php echo htmlspecialchars($pedido['ent_razao_social']); ?>):</td>
                                <td class="text-center align-middle"><?php echo number_format($subTotalCaixas, 0); ?></td>
                                <td class="text-center align-middle"><?php echo number_format($subTotalKg, 3, ',', '.'); ?></td>
                                <td colspan="2"></td>
                            </tr>
                            <?php
                            // Acumulando os valores para o Total Geral
                            $granTotalCaixas += $subTotalCaixas;
                            $granTotalKg += $subTotalKg;
                            ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="row mt-3">
            <div class="col-6 offset-6">
                <h5 class="text-end">Total Geral da Ordem de Expedição</h5>
                <table class="table table-sm table-bordered">
                    <tbody>
                        <tr class="table-dark">
                            <th class="text-end align-middle">TOTAL DE CAIXAS:</th>
                            <td class="text-end fs-6 fw-bold"><?php echo number_format($granTotalCaixas, 0); ?></td>
                        </tr>
                        <tr class="table-dark">
                            <th class="text-end align-middle">TOTAL DE QUILOS (KG):</th>
                            <td class="text-end fs-6 fw-bold">
                                <?php echo number_format($granTotalKg, 3, ',', '.'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (!empty($todasAsFotos)): ?>
        <?php endif; ?>

    </div>
</body>

</html>