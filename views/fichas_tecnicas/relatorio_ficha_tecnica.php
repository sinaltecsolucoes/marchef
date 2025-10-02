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

        <table class="ficha-table header-table bordered-header">
            <tr>
                <td style="width: 90%; vertical-align: middle;">
                    <h4 class="fw-bold m-0 text-center" style="font-size: 11pt;">FICHA TÉCNICA
                        <?php echo htmlspecialchars($ficha['header']['produto_tipo'] ?? 'N/A'); ?> MARCHEF PESCADOS
                    </h4>
                </td>
                <td style="text-align: center; width: 10%;">
                    <img src="<?php echo BASE_URL; ?>/img/logo_marchef.png" alt="Logo Marchef"
                        style="max-height: 70px;">
                </td>
            </tr>
        </table>

        <table class="ficha-table header-table" style="width: 100%; table-layout: fixed;">
            <colgroup>
                <col style="width: 12%;">
                <col style="width: 54%;">
                <col style="width: 15%;">
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
                <td class="section-header" colspan="8">ESPECIFICAÇÕES TÉCNICAS</td>
            </tr>

            <tr>
                <th colspan="2">Marca</th>
                <td colspan="6"><?php echo htmlspecialchars($ficha['header']['produto_marca'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th colspan="2">Denominação de Venda</th>
                <td colspan="6"><?php echo htmlspecialchars($ficha['header']['produto_denominacao'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th colspan="2">Classificação (unidades na embalagem)</th>
                <td colspan="6" class="uppercase">
                    <?php echo htmlspecialchars($ficha['header']['produto_total_pecas'] ?? 'N/A'); ?> UNIDADES NA
                    EMBALAGEM
                </td>
            </tr>
            <tr>
                <th colspan="2">Espécie</th>
                <td colspan="6">
                    <span
                        class="italico"><?php echo htmlspecialchars($ficha['header']['produto_especie'] ?? 'N/A'); ?></span>
                    - <?php echo htmlspecialchars($ficha['header']['produto_tipo'] ?? 'N/A'); ?>
                    DE <?php echo htmlspecialchars($ficha['header']['produto_origem'] ?? 'N/A'); ?>
                </td>
            </tr>
            <tr>
                <th colspan="2">Conservantes</th>
                <td colspan="6"><?php echo formatarTextoTabela($ficha['header']['ficha_conservantes'] ?? null); ?></td>
            </tr>
            <tr>
                <th colspan="2">Alergênicos</th>
                <td colspan="6"><?php echo formatarTextoTabela($ficha['header']['ficha_alergenicos'] ?? null); ?></td>
            </tr>
            <tr>
                <th colspan="2">Validade</th>
                <td colspan="6">
                    <?php echo htmlspecialchars($ficha['header']['produto_validade'] ?? 'N/A') . ' MESES'; ?>
                </td>
            </tr>
            <tr>
                <th colspan="2">Temperatura de estocagem e transporte</th>
                <td colspan="6">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_temp_estocagem_transporte'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <th colspan="2">Registro no MAPA/SIF/DIPOA</th>
                <td colspan="6"><?php echo htmlspecialchars($ficha['header']['ficha_registro_embalagem'] ?? 'N/A'); ?>
                </td>
            </tr>
            <tr>
                <th colspan="2">Origem</th>
                <td colspan="6"><?php echo htmlspecialchars($ficha['header']['ficha_origem'] ?? 'N/A'); ?></td>
            </tr>

            <tr>
                <th rowspan="2" colspan="2">Embalagem primária</th>
                <td colspan="1">Material</td>
                <td colspan="5"><?php echo formatarTextoTabela($ficha['header']['ficha_desc_emb_primaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td colspan="1">Medidas</td>
                <td colspan="5">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_medidas_emb_primaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <th rowspan="2" colspan="2">Embalagem secundária</th>
                <td colspan="1">Material</td>
                <td colspan="5">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_desc_emb_secundaria'] ?? null); ?>
                </td>
            </tr>
            <tr>
                <td colspan="1">Medidas</td>
                <td colspan="5">
                    <?php echo formatarTextoTabela($ficha['header']['ficha_medidas_emb_secundaria'] ?? null); ?>
                </td>
            </tr>

            <tr>
                <th colspan="2">Paletização</th>
                <td colspan="6"><?php echo formatarTextoTabela($ficha['header']['ficha_paletizacao'] ?? null); ?></td>
            </tr>
            <tr>
                <th colspan="2">G-TIN - EAN 13</th>
                <td colspan="6"><?php echo htmlspecialchars($ficha['header']['prod_ean13'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th colspan="2">G-TIN - EAN 14</th>
                <td colspan="6"><?php echo htmlspecialchars($ficha['header']['prod_dun14'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th colspan="2">NCM</th>
                <td colspan="6"><?php echo htmlspecialchars($ficha['header']['prod_ncm'] ?? 'N/A'); ?></td>
            </tr>

            <tr>
                <th colspan="2">GESTÃO DA QUALIDADE</th>
                <td colspan="6"><?php echo formatarTextoTabela($ficha['header']['ficha_gestao_qualidade'] ?? null); ?>
                </td>
            </tr>
        </table>

        <table class="ficha-table" style="width: 100%; table-layout: fixed;">
            <tr>
                <td colspan="4" style="vertical-align: top;">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['TABELA_NUTRICIONAL'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Tabela Nutricional" class="img-fluid" style="max-width: 40%;">
                    <p style="text-align: center;">Tabela Nutricional</p>
                </td>


                <td colspan="4" style="vertical-align: top;">
                    <h4 style="margin-bottom: 10px;">CRITÉRIOS LABORATORIAIS</h4>
                    <table class="criterios-table" style="width: 100%; border: none;">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Critério</th>
                                <th style="width: 30%;">Unidade</th>
                                <th style="width: 30%;">Padrão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($ficha['criterios'])):
                                $categoriaAtual = null;
                                foreach ($ficha['criterios'] as $criterio):
                                    $categoria = strtoupper($criterio['criterio_categoria'] ?? 'OUTROS');
                                    if ($categoria !== $categoriaAtual):
                                        $categoriaAtual = $categoria;
                                        ?>
                                        <!-- Subtítulo da categoria -->
                                        <tr>
                                            <td colspan="3" style="font-weight: bold; padding-top: 10px;">
                                                <?php echo $categoriaAtual; ?></td>
                                        </tr>
                                        <?php
                                    endif;
                                    ?>
                                    <!-- Critério -->
                                    <tr>
                                        <td><?php echo htmlspecialchars($criterio['criterio_nome']); ?></td>
                                        <td><?php echo htmlspecialchars($criterio['criterio_unidade'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($criterio['criterio_valor']); ?></td>
                                    </tr>
                                    <?php
                                endforeach;
                            else:
                                ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Nenhum critério adicionado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </td>



            </tr>
        </table>

        <div class="section-header">IMAGENS DO PRODUTO</div>
        <div class="image-section">
            <div class="image-item">
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_PRIMARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Embalagem Primária" class="img-fluid">
                </div>
                <p>Embalagem Primária</p>
            </div>
            <div class="image-item">
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_SECUNDARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Embalagem Secundária" class="img-fluid">
                </div>
                <p>Embalagem Secundária</p>
            </div>
            <div class="image-item" style="width: 50%;">
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($caminhosFotos['SIF'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                        alt="Selo SIF" class="img-fluid">
                </div>
                <p>Selo SIF</p>
            </div>
        </div>

        <div class="assinatura-section">
            <p style="margin-bottom: 5px; font-weight: bold;">CONTROLE DE QUALIDADE</p>
            <div style="border-top: 1px solid #000; padding-top: 5px;">
                <p>NOME DO USUÁRIO</p>
                <p>Data emissão: <?php echo date('d/m/Y'); ?></p>
            </div>
        </div>

    </div>
</body>

</html>