<?php
// /public/relatorio_ficha_tecnica.php

require_once __DIR__ . '/../src/bootstrap.php';

use App\Core\Database;
use App\FichasTecnicas\FichaTecnicaRepository;

// Pega o ID da ficha da URL (ex: ...?id=1)
$fichaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$fichaId) {
    die("Erro: ID da Ficha Técnica não fornecido ou inválido.");
}

try {
    $pdo = Database::getConnection();
    $repo = new FichaTecnicaRepository($pdo);
    $ficha = $repo->findCompletaById($fichaId);

    if (!$ficha) {
        die("Ficha Técnica não encontrada.");
    }

    // Organiza os dados para fácil acesso
    $header = $ficha['header'];
    $produto = $repo->getProdutoDetalhes($header['ficha_produto_id']); // Busca detalhes do produto
    $criterios = $ficha['criterios'];
    $fotos = $repo->getFotosByFichaId($fichaId);

    // Mapeia os tipos de foto para fácil acesso
    $fotosMap = [];
    foreach ($fotos as $foto) {
        $fotosMap[$foto['foto_tipo']] = $foto['foto_path'];
    }

} catch (Exception $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha Técnica - <?php echo htmlspecialchars($produto['prod_descricao'] ?? 'N/A'); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 95%;
            margin: 20px auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
        }

        .logo {
            max-width: 150px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 4px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }

        .label {
            font-weight: bold;
            width: 150px;
        }

        .content {}

        .section-title {
            background-color: #ccc;
            text-align: center;
            font-weight: bold;
            padding: 5px;
        }

        .photo-section {
            text-align: center;
        }

        .photo-section img {
            max-width: 150px;
            max-height: 150px;
            border: 1px solid #ccc;
            margin: 5px;
        }

        .footer {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>FICHA TÉCNICA CAMARÃO MARCHEF PESCADOS</h1>
            </div>
            <img src="assets/img/logo_marchef.png" alt="Logo Marchef" class="logo">
        </div>

        <table>
            <tr>
                <td class="label">FABRICANTE:</td>
                <td class="content" colspan="3">
                    <?php echo htmlspecialchars($header['fabricante_nome'] ?? 'MONTEIRO INDÚSTRIA DE PESCADOS'); ?></td>
                <td class="label">UNIDADE PRODUTORA:</td>
                <td class="content">ITAREMA-CEARÁ</td>
            </tr>
            <tr>
                <td class="label">CLASSIFICAÇÃO:</td>
                <td class="content" colspan="3">
                    <?php echo htmlspecialchars($produto['prod_classificacao'] ?? '11 a 15 unidades na embalagem'); ?>
                </td>
                <td class="label">FICHA TÉC./REV:</td>
                <td class="content">...</td>
            </tr>
            <tr>
                <td class="label">PRODUTO:</td>
                <td class="content" colspan="3"><?php echo htmlspecialchars($produto['prod_descricao'] ?? 'N/A'); ?>
                </td>
                <td class="label">CÓDIGO INTERNO PRODUTO:</td>
                <td class="content"><?php echo htmlspecialchars($produto['prod_codigo_interno'] ?? 'N/A'); ?></td>
            </tr>
        </table>

        <table>
            <tr>
                <th colspan="2">ESPECIFICAÇÕES TÉCNICAS</th>
            </tr>
            <tr>
                <td class="label">Marca</td>
                <td class="content"><?php echo htmlspecialchars($produto['prod_marca'] ?? 'MARCHEF'); ?></td>
            </tr>
            <tr>
                <td class="label">Denominação de venda</td>
                <td class="content">
                    <?php echo htmlspecialchars($produto['prod_classe'] ?? 'CAMARÃO CINZA INTEIRO CONGELADO'); ?></td>
            </tr>
            <tr>
                <td class="label">Classificação (unidades na embalagem)</td>
                <td class="content"><?php echo htmlspecialchars($produto['prod_classificacao'] ?? '11 a 15'); ?></td>
            </tr>
            <tr>
                <td class="label">Espécie</td>
                <td class="content">Penaeus vannamei - CAMARÃO DE CULTIVO</td>
            </tr>
            <tr>
                <td class="label">Conservantes</td>
                <td class="content">
                    <?php echo htmlspecialchars($header['ficha_conservantes'] ?? 'METABISSULFITO DE SÓDIO'); ?></td>
            </tr>
            <tr>
                <td class="label">Alergicos</td>
                <td class="content">
                    <?php echo htmlspecialchars($header['ficha_alergenicos'] ?? 'CONTÉM CRUSTÁCEO (CAMARÃO) E NÃO CONTÉM GLÚTEM'); ?>
                </td>
            </tr>
            <tr>
                <td class="label">Validade</td>
                <td class="content"><?php echo htmlspecialchars($produto['prod_validade_meses'] ?? '18'); ?> meses</td>
            </tr>
            <tr>
                <td class="label">Temperatura de estocagem e transporte</td>
                <td class="content">
                    <?php echo htmlspecialchars($header['ficha_temp_estocagem_transporte'] ?? 'Mantenha congelado a -18 ºC ou mais frio'); ?>
                </td>
            </tr>
            <tr>
                <td class="label">Registro no MAPA/SIF/ DIPOA</td>
                <td class="content">
                    <?php echo htmlspecialchars($header['ficha_registro_embalagem'] ?? '0517/3218 TRES M 0515/3218 ITAUEIRA'); ?>
                </td>
            </tr>
            <tr>
                <td class="label">Origem</td>
                <td class="content"><?php echo htmlspecialchars($header['ficha_origem'] ?? 'INDÚSTRIA BRASILEIRA'); ?>
                </td>
            </tr>

            <tr>
                <td class="label" rowspan="3">Embalagem primária</td>
                <td><?php echo htmlspecialchars($header['ficha_desc_emb_primaria'] ?? 'Saco composto por filme coextrudado...'); ?>
                </td>
            </tr>
            <tr>
                <td><b>Peso Líquido:</b> <?php echo htmlspecialchars($produto['peso_embalagem_primaria'] ?? '1kg'); ?>
                </td>
            </tr>
            <tr>
                <td><b>Medidas:</b>
                    <?php echo htmlspecialchars($header['ficha_medidas_emb_primaria'] ?? '220 x 310 mm'); ?></td>
            </tr>

            <tr>
                <td class="label" rowspan="3">Embalagem Secundária</td>
                <td><?php echo htmlspecialchars($header['ficha_desc_emb_secundaria'] ?? 'Caixa de papelão ondulado...'); ?>
                </td>
            </tr>
            <tr>
                <td><b>Peso Líquido:</b> <?php echo htmlspecialchars($produto['prod_peso_embalagem'] ?? '10kg'); ?></td>
            </tr>
            <tr>
                <td><b>Medidas:</b>
                    <?php echo htmlspecialchars($header['ficha_medidas_emb_secundaria'] ?? '420 x 325 x 120mm'); ?></td>
            </tr>

            <tr>
                <td class="label">Paletização</td>
                <td class="content">
                    <?php echo htmlspecialchars($header['ficha_paletizacao'] ?? '7 cx de base x 12 cx de altura'); ?>
                </td>
            </tr>
            <tr>
                <td class="label">G-TIN - EAN 13</td>
                <td class="content"><?php echo htmlspecialchars($produto['ean13_final'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td class="label">G-TIN - EAN 14</td>
                <td class="content"><?php echo htmlspecialchars($produto['prod_dun14'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td class="label">NCM</td>
                <td class="content"><?php echo htmlspecialchars($produto['prod_ncm'] ?? 'N/A'); ?></td>
            </tr>
        </table>

        <table style="border: none;">
            <tr style="border: none;">
                <td style="width: 50%; padding-right: 5px; border: none; vertical-align: top;">
                    <table>
                        <tr>
                            <th colspan="4">INFORMAÇÃO NUTRICIONAL</th>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="content" style="text-align: center;"><b>100 g</b></td>
                            <td class="content" style="text-align: center;"><b>60 g</b></td>
                            <td class="content" style="text-align: center;"><b>%VD*</b></td>
                        </tr>
                        <tr>
                            <td>Valor Energético (kcal)</td>
                            <td style="text-align: center;">82</td>
                            <td style="text-align: center;">49</td>
                            <td style="text-align: center;">2</td>
                        </tr>
                        <tr>
                            <td>Proteínas (g)</td>
                            <td style="text-align: center;">18</td>
                            <td style="text-align: center;">11</td>
                            <td style="text-align: center;">22</td>
                        </tr>
                    </table>
                    <table>
                        <tr>
                            <th colspan="3">Fotos do Produto</th>
                        </tr>
                        <tr>
                            <td class="photo-section" style="width: 33%;">
                                <?php if (isset($fotosMap['EMBALAGEM_PRIMARIA'])): ?>
                                    <img src="<?php echo htmlspecialchars($fotosMap['EMBALAGEM_PRIMARIA']); ?>"
                                        alt="Embalagem Primária">
                                <?php endif; ?>
                            </td>
                            <td class="photo-section" style="width: 33%;">
                                <?php if (isset($fotosMap['EMBALAGEM_SECUNDARIA'])): ?>
                                    <img src="<?php echo htmlspecialchars($fotosMap['EMBALAGEM_SECUNDARIA']); ?>"
                                        alt="Embalagem Secundária">
                                <?php endif; ?>
                            </td>
                            <td class="photo-section" style="width: 33%;">
                                <?php if (isset($fotosMap['SIF'])): ?>
                                    <img src="<?php echo htmlspecialchars($fotosMap['SIF']); ?>" alt="Selo SIF">
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%; padding-left: 5px; border: none; vertical-align: top;">
                    <table>
                        <tr>
                            <th colspan="3">Critérios Laboratoriais</th>
                        </tr>
                        <tr>
                            <th>Microbiologia</th>
                            <th>Unidade</th>
                            <th>Padrão</th>
                        </tr>
                        <?php foreach ($criterios as $criterio):
                            if ($criterio['criterio_grupo'] == 'MICROBIOLOGICO'): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($criterio['criterio_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($criterio['criterio_unidade']); ?></td>
                                    <td><?php echo htmlspecialchars($criterio['criterio_valor']); ?></td>
                                </tr>
                            <?php endif; endforeach; ?>

                        <tr>
                            <th>Físico-químico</th>
                            <th>Unidade</th>
                            <th>Padrão</th>
                        </tr>
                        <?php foreach ($criterios as $criterio):
                            if ($criterio['criterio_grupo'] == 'FISICO-QUIMICO'): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($criterio['criterio_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($criterio['criterio_unidade']); ?></td>
                                    <td><?php echo htmlspecialchars($criterio['criterio_valor']); ?></td>
                                </tr>
                            <?php endif; endforeach; ?>
                    </table>
                </td>
            </tr>
        </table>

        <div class="footer">
            <span>CONTROLE DE QUALIDADE</span>
            <span>Data emissão: <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>
</body>

</html>