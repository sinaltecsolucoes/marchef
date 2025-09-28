<?php
// views/fichas_tecnicas/relatorio_ficha_tecnica.php
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\FichasTecnicas\FichaTecnicaRepository;

if (!isset($_SESSION['codUsuario'])) {
    die("Acesso negado. Por favor, faça o login.");
}

$fichaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$fichaId) {
    die("Erro: ID da Ficha Técnica não fornecido.");
}

try {
    $pdo = Database::getConnection();
    $repo = new FichaTecnicaRepository($pdo);

    // Busca os dados completos da ficha técnica
    $ficha = $repo->findCompletaById($fichaId);
    $fotos = $repo->getFotosByFichaId($fichaId);

    if (!$ficha) {
        die("Nenhum dado encontrado para esta Ficha Técnica.");
    }

    // Organiza as fotos em um array associativo para fácil acesso
    $caminhosFotos = [];
    foreach ($fotos as $foto) {
        $caminhosFotos[$foto['foto_tipo']] = BASE_URL . '/' . $foto['foto_path'];
    }

} catch (Exception $e) {
    die("Erro ao carregar os dados da ficha: " . $e->getMessage());
}

/**
 * Função para formatar o texto para exibição em tabelas.
 * @param string|null $texto
 * @return string
 */
function formatarTextoTabela(?string $texto): string
{
    if (empty($texto)) {
        return 'N/A';
    }
    return nl2br(htmlspecialchars($texto));
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Ficha Técnica #<?php echo str_pad($fichaId, 4, '0', STR_PAD_LEFT); ?></title>
    <link href="<?php echo BASE_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/css/relatorios.css" rel="stylesheet">
    <style>
        /* Ajustes específicos para a Ficha Técnica */
        @page {
            size: A4 portrait;
            /* MUDANÇA: Formato Retrato */
        }

        .container {
            max-width: 210mm;
            /* MUDANÇA: Largura para A4 Retrato */
            margin: 0 auto;
        }

        .ficha-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            table-layout: fixed;
            margin-bottom: 15px;
        }

        .ficha-table th,
        .ficha-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
            text-align: left;
        }

        .ficha-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-transform: uppercase;
        }

        .criterios-table th,
        .criterios-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 8pt;
        }

        .section-header {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
            padding: 5px;
        }

        .image-container {
            width: 100%;
            height: 200px;
            /* Altura fixa para alinhar as imagens */
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid #ccc;
            margin-bottom: 5px;
        }

        .image-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .assinatura {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        .assinatura-box {
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 5px;
            width: 45%;
        }

        .text-center {
            text-align: center;
        }

        .text-start {
            text-align: left;
        }
    </style>
</head>

<body>
    <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0">FICHA TÉCNICA CAMARÃO MARCHEF PESCADOS</h4>
            <img src="<?php echo BASE_URL; ?>/img/logo_marchef.png" alt="Logo Marchef" style="max-height: 50px;">
        </div>

        <table class="ficha-table">
            <thead>
                <tr>
                    <td colspan="4" class="text-start">
                        <h4 class="fw-bold m-0" style="font-size: 11pt;">FICHA TÉCNICA CAMARÃO MARCHEF PESCADOS</h4>
                    </td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="width: 15%;"><b>FABRICANTE:</b></td>
                    <td style="width: 35%;">
                        <?php echo htmlspecialchars($ficha['header']['fabricante_nome'] ?? 'N/A'); ?></td>
                    <td style="width: 15%;"><b>UNIDADE PRODUTORA:</b></td>
                    <td style="width: 35%;">MONTEIRO INDÚSTRIA DE PESCADOS</td>
                </tr>
                <tr>
                    <td><b>PRODUTO:</b></td>
                    <td><?php echo htmlspecialchars($ficha['header']['produto_nome'] ?? 'N/A'); ?></td>
                    <td><b>FICHA TÉC. REV.:</b></td>
                    <td>rev00</td>
                </tr>
                <tr>
                    <td><b>CLASSIFICAÇÃO:</b></td>
                    <td><?php echo htmlspecialchars($ficha['header']['ficha_classificacao'] ?? 'N/A'); ?></td>
                    <td><b>CÓD. INTERNO PRODUTO:</b></td>
                    <td><?php echo htmlspecialchars($ficha['header']['prod_codigo_interno'] ?? 'N/A'); ?></td>
                </tr>
            </tbody>
        </table>

        <h6 class="section-header">ESPECIFICAÇÕES TÉCNICAS</h6>
        <table class="ficha-table">
            <tr>
                <td style="width: 20%;"><b>Marca</b></td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['prod_marca'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><b>Denominação de Venda</b></td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['prod_descricao'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><b>CLASSIFICAÇÃO</b> (unidades na embalagem)</td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['prod_classificacao'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><b>Espécie</b></td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['prod_especie'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><b>Conservantes</b></td>
                <td colspan="3"><?php echo formatarTextoTabela($ficha['header']['ficha_conservantes'] ?? null); ?></td>
            </tr>
            <tr>
                <td><b>Alergênicos</b></td>
                <td colspan="3"><?php echo formatarTextoTabela($ficha['header']['ficha_alergenicos'] ?? null); ?></td>
            </tr>
            <tr>
                <td><b>Validade</b></td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['prod_validade_meses'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><b>Temperatura de estocagem e transporte</b></td>
                <td colspan="3">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_temp_estocagem_transporte'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td><b>Registro no MAPA/SIF/DIPOA</b></td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['ficha_registro_embalagem'] ?? 'N/A'); ?>
                </td>
            </tr>
            <tr>
                <td><b>Origem</b></td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['ficha_origem'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td rowspan="2"><b>Embalagem primária</b></td>
                <td style="width: 15%;">Material</td>
                <td colspan="2"><?php echo formatarTextoTabela($ficha['header']['ficha_desc_emb_primaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td>Medidas</td>
                <td colspan="2">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_medidas_emb_primaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td rowspan="2"><b>Embalagem secundária</b></td>
                <td>Material</td>
                <td colspan="2">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_desc_emb_secundaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td>Medidas</td>
                <td colspan="2">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_medidas_emb_secundaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td><b>Paletização</b></td>
                <td colspan="3"><?php echo formatarTextoTabela($ficha['header']['ficha_paletizacao'] ?? null); ?></td>
            </tr>
            <tr>
                <td><b>G-TIN - EAN 13</b></td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['prod_ean13'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><b>G-TIN - EAN 14</b></td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['prod_dun14'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><b>NCM</b></td>
                <td colspan="3"><?php echo htmlspecialchars($ficha['header']['prod_ncm'] ?? 'N/A'); ?></td>
            </tr>
        </table>

        <div class="row g-0">
            <div class="col-7">
                <h6 class="section-header">GESTAO DA QUALIDADE</h6>
                <div style="border: 1px solid #000; padding: 5px; height: 100%;">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_gestao_qualidade'] ?? null); ?>
                </div>
            </div>
            <div class="col-5">
                <h6 class="section-header">Critérios Laboratoriais</h6>
                <table class="criterios-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Critério</th>
                            <th>Unidade</th>
                            <th>Padrão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($ficha['criterios'])): ?>
                            <?php foreach ($ficha['criterios'] as $criterio): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($criterio['criterio_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($criterio['criterio_unidade'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($criterio['criterio_valor']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">Nenhum critério adicionado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <h6 class="section-header" style="margin-top: 15px;">IMAGENS</h6>
        <div class="row g-2">
            <div class="col-4">
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['TABELA_NUTRICIONAL'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Tabela Nutricional" class="img-fluid">
                </div>
                <p class="text-center">Tabela Nutricional</p>
            </div>
            <div class="col-4">
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_PRIMARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Embalagem Primária" class="img-fluid">
                </div>
                <p class="text-center">Embalagem Primária</p>
            </div>
            <div class="col-4">
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_SECUNDARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Embalagem Secundária" class="img-fluid">
                </div>
                <p class="text-center">Embalagem Secundária</p>
            </div>
        </div>

        <div class="row g-2 mt-2">
            <div class="col-6">
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['SIF'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Selo SIF" class="img-fluid">
                </div>
                <p class="text-center">Selo SIF</p>
            </div>
            <div class="col-6">
                <div style="border: 1px solid #000; padding: 10px; height: 100%;">
                    <p class="fw-bold m-0">CONTROLE DE QUALIDADE</p>
                    <hr class="my-2">
                    <p>NOME DO USUARIO</p>
                    <p>Data emissão: <?php echo date('d/m/Y'); ?></p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>