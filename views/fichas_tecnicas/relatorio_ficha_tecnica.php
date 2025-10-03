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

    $ficha = $repo->findCompletaById($fichaId);
    $fotos = $repo->getFotosByFichaId($fichaId);

    if (!$ficha) {
        die("Nenhum dado encontrado para esta Ficha Técnica.");
    }

    // Adicionamos os detalhes do produto que não estavam na query principal
    $produtoDetalhes = $repo->getProdutoDetalhes($ficha['header']['ficha_produto_id']);
    $ficha['header']['prod_classificacao'] = $produtoDetalhes['prod_classe'] ?? 'N/A';
    $ficha['header']['prod_ean13'] = $produtoDetalhes['prod_ean13'] ?? 'N/A';
    $ficha['header']['prod_dun14'] = $produtoDetalhes['prod_dun14'] ?? 'N/A';
    $ficha['header']['prod_peso_embalagem_primaria'] = $produtoDetalhes['peso_embalagem_primaria'] ?? 'N/A';

    $caminhosFotos = [];
    foreach ($fotos as $foto) {
        $caminhosFotos[$foto['foto_tipo']] = BASE_URL . '/' . $foto['foto_path'];
    }

} catch (Exception $e) {
    die("Erro ao carregar os dados da ficha: " . $e->getMessage());
}

function formatarTextoTabela(?string $texto): string
{
    if (empty($texto)) {
        return 'N/A';
    }
    return nl2br(htmlspecialchars($texto));
}

/**
 * Formata um valor numérico para o formato de peso brasileiro (com kg).
 * Exibe o valor inteiro se não houver casas decimais, ou com vírgula e duas casas se houver.
 *
 * @param float|null $valor O valor numérico a ser formatado.
 * @return string O valor formatado com a unidade 'kg'.
 */
function formatarPesoBrasileiro(?float $valor): string
{
    if ($valor === null || $valor === '') {
        return 'N/A';
    }

    // Verifica se o valor é um número inteiro
    if (fmod($valor, 1.0) == 0) {
        // Formata como um número inteiro
        $valorFormatado = number_format($valor, 0, '', '.');
    } else {
        // Formata com duas casas decimais
        $valorFormatado = number_format($valor, 2, ',', '.');
    }

    return htmlspecialchars($valorFormatado) . ' kg';
}


?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Ficha Técnica #<?php echo str_pad($fichaId, 4, '0', STR_PAD_LEFT); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/relatorios.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <table class="ficha-table header-table bordered-header" style="width: 100%; table-layout: fixed;">
            <colgroup>
                <col style="width: 12%;">
                <col style="width: 50%;">
                <col style="width: 19%;">
                <col style="width: 19%;">
            </colgroup>
            <tr style="height: 30px;">
                <td colspan="3" style="vertical-align: middle;">
                    <h4 class="fw-bold m-0 text-center" style="font-size: 10pt;">FICHA TÉCNICA
                        <?php echo htmlspecialchars($ficha['header']['produto_tipo'] ?? 'N/A'); ?> MARCHEF PESCADOS
                    </h4>
                </td>
                <td style="text-align: center;">
                    <img src="<?php echo BASE_URL; ?>/img/logo_marchef.png" alt="Logo Marchef"
                        style="max-height: 40px;">
                </td>
            </tr>
        </table>

        <table class="ficha-table header-table" style="width: 100%; table-layout: fixed;">
            <colgroup>
                <col style="width: 12%;">
                <col style="width: 50%;">
                <col style="width: 19%;">
                <col style="width: 19%;">
            </colgroup>
            <tr>
                <td class="section-header" colspan="4">INFORMAÇÕES GERAIS</td>
            </tr>
            <tr>
                <th>FABRICANTE:</th>
                <td><?php echo htmlspecialchars($ficha['header']['fabricante_unidade'] ?? 'N/A'); ?></td>
                <th>UNIDADE PRODUTORA:</th>
                <td><?php echo htmlspecialchars($ficha['header']['fabricante_endereco'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>PRODUTO:</th>
                <td><?php echo htmlspecialchars($ficha['header']['produto_nome'] ?? 'N/A'); ?></td>
                <th>FICHA TÉCNICA REV.:</th>
                <td>rev00</td>
            </tr>
            <tr>
                <th>CLASSIFICAÇÃO:</th>
                <td><?php echo htmlspecialchars($ficha['header']['produto_classificacao'] ?? 'N/A'); ?></td>
                <th>CÓDIGO INTERNO PRODUTO:</th>
                <td><?php echo htmlspecialchars($ficha['header']['prod_codigo_interno'] ?? 'N/A'); ?></td>
            </tr>
        </table>

        <table class="ficha-table">
            <tr>
                <td class="section-header" colspan="10">ESPECIFICAÇÕES TÉCNICAS</td>
            </tr>

            <tr>
                <th colspan="3">Marca</th>
                <td colspan="7"><?php echo htmlspecialchars($ficha['header']['produto_marca'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th colspan="3">Denominação de Venda</th>
                <td colspan="7"><?php echo htmlspecialchars($ficha['header']['produto_denominacao'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th colspan="3">Classificação (unidades na embalagem)</th>
                <td colspan="7" class="uppercase">
                    <?php echo htmlspecialchars($ficha['header']['produto_total_pecas'] ?? 'N/A'); ?> UNIDADES NA
                    EMBALAGEM
                </td>
            </tr>
            <tr>
                <th colspan="3">Espécie</th>
                <td colspan="7">
                    <span
                        class="italico"><?php echo htmlspecialchars($ficha['header']['produto_especie'] ?? 'N/A'); ?></span>
                    - <?php echo htmlspecialchars($ficha['header']['produto_tipo'] ?? 'N/A'); ?>
                    DE <?php echo htmlspecialchars($ficha['header']['produto_origem'] ?? 'N/A'); ?>
                </td>
            </tr>
            <tr>
                <th colspan="3">Conservantes</th>
                <td colspan="7"><?php echo formatarTextoTabela($ficha['header']['ficha_conservantes'] ?? null); ?></td>
            </tr>
            <tr>
                <th colspan="3">Alergênicos</th>
                <td colspan="7"><?php echo formatarTextoTabela($ficha['header']['ficha_alergenicos'] ?? null); ?></td>
            </tr>
            <tr>
                <th colspan="3">Validade</th>
                <td colspan="7">
                    <?php echo htmlspecialchars($ficha['header']['produto_validade'] ?? 'N/A') . ' MESES'; ?>
                </td>
            </tr>
            <tr>
                <th colspan="3">Temperatura de estocagem e transporte</th>
                <td colspan="7">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_temp_estocagem_transporte'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <th colspan="3">Registro no MAPA/SIF/DIPOA</th>
                <td colspan="7"><?php echo htmlspecialchars($ficha['header']['ficha_registro_embalagem'] ?? 'N/A'); ?>
                </td>
            </tr>
            <tr>
                <th colspan="3">Origem</th>
                <td colspan="7"><?php echo htmlspecialchars($ficha['header']['ficha_origem'] ?? 'N/A'); ?></td>
            </tr>


            <tr>
                <th rowspan="3" colspan="2">Embalagem primária</th>
                <td colspan="1">Material</td>
                <td colspan="7"><?php echo formatarTextoTabela($ficha['header']['ficha_desc_emb_primaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td colspan="1">Peso Líquido</td>
                <td colspan="7">
                    <?php echo formatarPesoBrasileiro($ficha['header']['prod_peso_embalagem_primaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td colspan="1">Medidas</td>
                <td colspan="7">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_medidas_emb_primaria'] ?? null); ?>
                </td>

            </tr>




            <tr>
                <th rowspan="3" colspan="2">Embalagem secundária</th>
                <td colspan="1">Material</td>
                <td colspan="7">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_desc_emb_secundaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td colspan="1">Peso Líquido</td>
                <td colspan="7">
                    <?php echo formatarPesoBrasileiro($ficha['header']['peso_embalagem'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td colspan="1">Medidas</td>
                <td colspan="7">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_medidas_emb_secundaria'] ?? null); ?>
                </td>
            </tr>


            <tr>
                <th colspan="3">Paletização</th>
                <td colspan="7"><?php echo formatarTextoTabela($ficha['header']['ficha_paletizacao'] ?? null); ?></td>
            </tr>
            <tr>
                <th colspan="3">G-TIN - EAN 13</th>
                <td colspan="7"><?php echo htmlspecialchars($ficha['header']['ean13'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th colspan="3">G-TIN - EAN 14</th>
                <td colspan="7"><?php echo htmlspecialchars($ficha['header']['prod_dun14'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th colspan="3">NCM</th>
                <td colspan="7"><?php echo htmlspecialchars($ficha['header']['produto_ncm'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th colspan="3">GESTÃO DA QUALIDADE</th>
                <td colspan="7"><?php echo formatarTextoTabela($ficha['header']['ficha_gestao_qualidade'] ?? null); ?>
                </td>
            </tr>
        </table>

        <table class="ficha-table" style="width: 100%; table-layout: fixed;">
            <tr>
                <td colspan="3" style="vertical-align: middle; text-align: center;">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['TABELA_NUTRICIONAL'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Tabela Nutricional" class="img-fluid tabela-nutricional-img">
                </td>

                <td colspan="4" style="vertical-align: top;">
                    <table class="criterios-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th colspan="3" class="section-header-criterios">CRITÉRIOS LABORATORIAIS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($ficha['criterios'])):
                                $categoriaAtual = null;
                                foreach ($ficha['criterios'] as $criterio):
                                    $categoria = strtoupper($criterio['criterio_grupo'] ?? 'OUTROS');
                                    if ($categoria !== $categoriaAtual):
                                        $categoriaAtual = $categoria;
                                        ?>
                                        <tr>
                                            <td colspan="3" class="categoria-header">
                                                <?php echo $categoriaAtual; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="th-criterio" style="width: 40%;">CRITÉRIO</th>
                                            <th class="th-criterio" style="width: 20%;">UNIDADE</th>
                                            <th class="th-criterio" style="width: 30%;">PADRÃO</th>
                                        </tr>
                                        <?php
                                    endif;
                                    ?>
                                    <tr>
                                        <td style="width: 40%;"><?php echo htmlspecialchars($criterio['criterio_nome']); ?></td>
                                        <td style="width: 20%;">
                                            <?php echo htmlspecialchars($criterio['criterio_unidade'] ?? 'N/A'); ?>
                                        </td>
                                        <td style="width: 30%;"><?php echo htmlspecialchars($criterio['criterio_valor']); ?>
                                        </td>
                                    </tr>
                                    <?php
                                endforeach;
                            else:
                                ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted"
                                        style="border: 1px solid #000; padding: 8px;">Nenhum critério adicionado.</td>
                                </tr>
                            <?php endif; ?>

                            <tr>
                                <td colspan="3" class="categoria-header" style="font-style: italic;">
                                    (*) Seguimos padrões estabelecidos em legislação vigente.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>

            </tr>
        </table>

        <table style="width: 100%; border: 1px solid #000; border-spacing: 0;">

            <colgroup>
                <col style="width: 70%;">
                <col style="width: 30%;">
            </colgroup>
            <tr>
                <td style="vertical-align: top; padding: 0; border-right: 1px solid #000;">
                    <table style="width: 100%; border-collapse: collapse; height: 100%;">
                        <thead>
                            <tr>
                                <th colspan="2" class="text-center"
                                    style="font-size: 9pt; border-bottom: 1px solid #000;">FOTOS DO PRODUTO</th>
                            </tr>
                            <tr>
                                <th class="text-center"
                                    style="width: 50%;border-bottom: 1px solid #000;border-right:1px solid #000;">
                                    EMBALAGEM PRIMÁRIA</th>
                                <th class="text-center" style="width: 50%;border-bottom: 1px solid #000;">EMBALAGEM
                                    SECUNDÁRIA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center" style="height: 200px; border-right: 1px solid #000;">
                                    <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_PRIMARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                        alt="Embalagem Primária" class="img-fluid" style="max-height: 90%;">
                                </td>
                                <td class="text-center" style="height: 200px;">
                                    <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_SECUNDARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                        alt="Embalagem Secundária" class="img-fluid" style="max-height: 90%;">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>

                <td class="text-center" style="vertical-align: middle; padding: 0;">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['SIF'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Selo SIF" class="img-fluid" style="max-width: 90%; max-height: 90%;">
                </td>
            </tr>
        </table>

        <div class="assinatura-section" style="border-top: 1px solid #000; padding-top: 5px;">
            <p style="margin: 0; font-weight: bold;">
                CONTROLE DE QUALIDADE:
                <span style="font-weight: normal;">PRISCILA CASTRO</span> –
                <span style="font-weight: normal;">Data emissão: <?php echo date('d/m/Y'); ?></span>
            </p>
        </div>
    </div>
</body>

</html>