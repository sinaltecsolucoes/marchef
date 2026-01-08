<?php
// /src/FichasTecnicas/FichaTecnicaHtmlService.php
namespace App\FichasTecnicas;

use DateTime;
use Exception;

class FichaTecnicaHtmlService
{
    private FichaTecnicaRepository $repository;

    public function __construct(FichaTecnicaRepository $repository)
    {
        $this->repository = $repository;
    }

    public function renderHtml(int $fichaId): string
    {
        // 1. BUSCA DE DADOS
        $ficha = $this->repository->findCompletaById($fichaId);
        $fotos = $this->repository->getFotosByFichaId($fichaId);

        if (!$ficha) {
            throw new Exception("Nenhum dado encontrado para esta Ficha Técnica.");
        }

        $produtoDetalhes = $this->repository->getProdutoDetalhes($ficha['header']['ficha_produto_id']);

        $ficha['header']['prod_classificacao'] = $produtoDetalhes['prod_classe'] ?? 'N/A';
        $ficha['header']['prod_ean13'] = $produtoDetalhes['prod_ean13'] ?? 'N/A';
        $ficha['header']['prod_dun14'] = $produtoDetalhes['prod_dun14'] ?? 'N/A';
        $ficha['header']['prod_peso_embalagem_primaria'] = $produtoDetalhes['peso_embalagem_primaria'] ?? 'N/A';

        $caminhosFotos = [];
        foreach ($fotos as $foto) {
            $caminhosFotos[$foto['foto_tipo']] = BASE_URL . '/' . $foto['foto_path'];
        }

        // 2. FUNÇÕES DE FORMATAÇÃO (closures)
        $formatarTextoTabela = function (?string $texto): string {
            if (empty($texto)) {
                return 'N/A';
            }

            // 1. Substitui os caracteres especiais por tags HTML
            $texto = str_replace('₂', '<sub>2</sub>', $texto);
            $texto = str_replace('³', '<sup>3</sup>', $texto);
            $texto = str_replace('¹', '<sup>1</sup>', $texto);

            // 2. Escapa HTML, mas preserva as tags <sub> e <sup>
            $textoSeguro = htmlspecialchars($texto, ENT_NOQUOTES, 'UTF-8');
            $textoSeguro = str_replace(
                ['&lt;sub', '&lt;/sub&gt;', '&lt;sup&gt;', '&lt;/sup&gt;'],
                ['<sub', '</sub>', '<sup>', '</sup>'],
                $textoSeguro
            );

            return $textoSeguro;
        };

        $formatarPesoBrasileiro = function (?float $valor): string {
            if ($valor === null || $valor === '') {
                return 'N/A';
            }
            if (fmod($valor, 1.0) == 0) {
                $valorFormatado = number_format($valor, 0, '', '.');
            } else {
                $valorFormatado = number_format($valor, 2, ',', '.');
            }
            return htmlspecialchars($valorFormatado) . ' kg';
        };

        // 3. INÍCIO DA RENDERIZAÇÃO DO HTML
        ob_start();
?>
        <!DOCTYPE html>
        <html lang="pt-br">

        <head>
            <meta charset="UTF-8">
            <title>Ficha Técnica #<?php echo str_pad($fichaId, 4, '0', STR_PAD_LEFT); ?></title>
            <style>
                /* Estilos adaptados do relatorios.css para compatibilidade com DomPDF */

                @page {
                    size: A4 portrait;
                    margin: 0.3cm;

                    @top-left {
                        content: "";
                    }

                    @top-center {
                        content: "";
                    }

                    @top-right {
                        content: "";
                    }

                    @bottom-left {
                        content: "";
                    }

                    @bottom-center {
                        content: "";
                    }

                    @bottom-right {
                        content: "Página " counter(page) " de " counter(pages);
                        font-family: Arial, sans-serif;
                        font-size: 7pt;
                        color: #888;
                    }
                }

                body {
                    font-family: 'DejaVu Sans', sans-serif;
                    font-size: 8pt;
                }

                .linha-dados {
                    /* Define uma altura mínima para a linha. */
                    height: 12px;
                    /* Garante que o conteúdo fique centralizado verticalmente */
                    vertical-align: middle !important;
                    /* O !important ajuda a sobrepor o vertical-align: top da regra geral do .ficha-table td/th */
                }

                .ficha-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 8px;
                    table-layout: fixed;
                    margin-bottom: 4px;
                }

                .ficha-table th,
                .ficha-table td {
                    border: 1px solid #000;
                    padding: 1px 5px;
                    vertical-align: top;
                    text-align: left;
                }

                .section-header {
                    text-align: center;
                    font-weight: bold;
                    background-color: #f2f2f2;
                    font-size: 9pt;
                    padding: 2px;
                }

                .section-header-criterios {
                    text-align: center;
                    font-weight: bold;
                    font-size: 8pt;
                    background-color: #f2f2f2;
                    padding: 2px;
                }

                .criterios-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 8px;
                    table-layout: fixed;
                }

                .criterios-table th,
                .criterios-table td {
                    border: 1px solid #000;
                    text-align: center;
                    padding: 2px;
                    vertical-align: middle;
                }

                .criterios-table th {
                    background-color: #e9ecef;
                    font-weight: bold;
                }

                .categoria-header {
                    font-weight: bold;
                    text-align: center;
                    background-color: #f8f9fa;
                    padding: 2px;
                    font-style: normal;
                }

                .categoria-header-italic {
                    font-style: italic;
                }

                .assinatura-section {
                    margin-top: 5px;
                    border: 1px solid #000;
                    padding: 5px;
                    font-size: 8pt;
                }

                .img-fluid {
                    max-width: 100%;
                    max-height: 160px;
                    display: block;
                    margin: 5px;
                }

                .tabela-nutricional-img {
                    width: 78%;
                    max-height: 230px;
                    object-fit: contain;
                }

                .header-table {
                    border: none;
                    margin-bottom: 3px;
                }

                .bordered-header {
                    border: 1px solid #000;
                }

                .uppercase {
                    text-transform: uppercase;
                }

                .italico {
                    font-style: italic;
                }

                /* Layout para fotos */
                .fotos-table {
                    width: 100%;
                    border: 1px solid #000;
                    border-collapse: collapse;
                }

                .foto-cell {
                    height: 100px;
                    /* Ajuste para caber na página */
                    vertical-align: middle;
                    text-align: center;
                }

                .borda-direita {
                    border-right: 1px solid #000;
                }

                /* Evita quebras de página indesejadas */
                .no-break {
                    page-break-inside: avoid;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <table class="ficha-table header-table bordered-header" style="width: 100%;">
                    <tr style="height: 25px;">
                        <td colspan="12" style="vertical-align: middle; text-align: center; width: 81%;">
                            <h4 style="font-size: 10pt; font-weight: bold; margin: 0;">FICHA TÉCNICA
                                <?php echo htmlspecialchars($ficha['header']['produto_tipo'] ?? 'N/A'); ?> MARCHEF PESCADOS
                            </h4>
                        </td>
                        <td colspan="2" style="text-align: center; width: 19%;">
                            <img src="<?php echo BASE_URL; ?>/img/logo_marchef.png" alt="Logo Marchef"
                                style="max-height: 60px;">
                        </td>
                    </tr>
                </table>

                <table class="ficha-table header-table" style="width: 100%;">
                    <tr>
                        <td class="section-header" colspan="14" style="vertical-align: middle; text-align: center;">INFORMAÇÕES
                            GERAIS</td>
                    </tr>
                    <tr>
                        <th colspan="2" class="linha-dados">FABRICANTE:</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['fabricante_unidade'] ?? 'N/A'); ?>
                        </td>
                        <th colspan="3" class="linha-dados">UNIDADE PRODUTORA:</th>
                        <td colspan="2" class=" linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['fabricante_endereco'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2" class="linha-dados">PRODUTO:</th>
                        <td colspan="7" class=" linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['produto_nome'] ?? 'N/A'); ?>
                        </td>
                        <th colspan="3" class="linha-dados">FICHA TÉCNICA REV.:</th>
                        <td colspan="2" class=" linha-dados">rev00</td>
                    </tr>
                    <tr>
                        <th colspan="2" class="linha-dados">CLASSIFICAÇÃO:</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['produto_classificacao'] ?? 'N/A'); ?>
                        </td>
                        <th colspan="3" class="linha-dados">CÓDIGO INTERNO PRODUTO:</th>
                        <td colspan="2" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['prod_codigo_interno'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                </table>

                <table class="ficha-table no-break">
                    <tr>
                        <td class="section-header" colspan="10" style="vertical-align: middle; text-align: center;">
                            ESPECIFICAÇÕES TÉCNICAS</td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Marca</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['produto_marca'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Denominação de Venda</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['produto_denominacao'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Classificação (unidades na embalagem)</th>
                        <td colspan="7" class="uppercase linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['produto_total_pecas'] ?? 'N/A'); ?> UNIDADES NA
                            EMBALAGEM
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Espécie</th>
                        <td colspan="7" class="linha-dados"><span
                                class="italico"><?php echo htmlspecialchars($ficha['header']['produto_especie'] ?? 'N/A'); ?></span>
                            - <?php echo htmlspecialchars($ficha['header']['produto_tipo'] ?? 'N/A'); ?> DE
                            <?php echo htmlspecialchars($ficha['header']['produto_origem'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Conservantes</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_conservantes'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Alergênicos</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_alergenicos'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Validade</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['produto_validade'] ?? 'N/A') . ' MESES'; ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Temperatura de estocagem e transporte</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_temp_estocagem_transporte'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Registro no MAPA/SIF/DIPOA</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['ficha_registro_embalagem'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Origem</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['ficha_origem'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th rowspan="3" colspan="2" class="linha-dados">Embalagem primária</th>
                        <td>Material</td>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_desc_emb_primaria'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="linha-dados">Peso Líquido</td>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarPesoBrasileiro($ficha['header']['prod_peso_embalagem_primaria'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="linha-dados">Medidas</td>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_medidas_emb_primaria'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <th rowspan="3" colspan="2" class="linha-dados">Embalagem secundária</th>
                        <td class="linha-dados">Material</td>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_desc_emb_secundaria'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="linha-dados">Peso Líquido</td>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarPesoBrasileiro($ficha['header']['peso_embalagem'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="linha-dados">Medidas</td>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_medidas_emb_secundaria'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">Paletização</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_paletizacao'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">G-TIN - EAN 13</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['ean13'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">G-TIN - EAN 14</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['prod_dun14'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">NCM</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo htmlspecialchars($ficha['header']['produto_ncm'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="3" class="linha-dados">GESTÃO DA QUALIDADE</th>
                        <td colspan="7" class="linha-dados">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_gestao_qualidade'] ?? null); ?>
                        </td>
                    </tr>
                </table>

                <table class="ficha-table no-break" style="width: 100%;">
                    <tr>
                        <td colspan="3"
                            style="vertical-align: middle; text-align: center; border: 1px solid #000; padding: 1px;">
                            <img src="<?php echo htmlspecialchars($caminhosFotos['TABELA_NUTRICIONAL'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                alt="Tabela Nutricional" class="tabela-nutricional-img">
                        </td>
                        <td colspan="4" style="vertical-align: top; border: 1px solid #000; padding: 2px;">
                            <table class="criterios-table" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <th colspan="6" class="section-header-criterios">CRITÉRIOS LABORATORIAIS</th>
                                </tr>
                                <?php
                                if (!empty($ficha['criterios'])):
                                    $categoriaAtual = null;
                                    foreach ($ficha['criterios'] as $criterio):
                                        $categoria = strtoupper($criterio['criterio_grupo'] ?? 'OUTROS');
                                        if ($categoria !== $categoriaAtual):
                                            $categoriaAtual = $categoria;
                                ?>
                                            <tr>
                                                <td colspan="6" class="categoria-header" style="height: 12px;">
                                                    <?php echo $categoriaAtual; ?>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th colspan="3" style="font-size: 7pt; background-color: #e9ecef;">CRITÉRIO</th>
                                                <th colspan="1" style="font-size: 7pt; background-color: #e9ecef;">UNIDADE</th>
                                                <th colspan="2" style="font-size: 7pt; background-color: #e9ecef;">PADRÃO</th>
                                            </tr>
                                        <?php
                                        endif;
                                        ?>
                                        <tr>
                                            <td colspan="3" class="linha-dados">
                                                <?php echo htmlspecialchars($criterio['criterio_nome']); ?>
                                            </td>
                                            <td colspan="1" class="linha-dados">
                                                <?php echo htmlspecialchars($criterio['criterio_unidade'] ?? 'N/A'); ?>
                                            </td>
                                            <td colspan="2" class="linha-dados">
                                                <?php echo htmlspecialchars($criterio['criterio_valor']); ?>
                                            </td>
                                        </tr>
                                    <?php
                                    endforeach;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #888; padding: 5px;">Nenhum critério
                                            adicionado.</td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="6" style="height: 12px;" class="categoria-header categoria-header-italic">(*)
                                        Seguimos padrões
                                        estabelecidos em legislação vigente.</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table class="fotos-table no-break">
                    <tr>
                        <td style="vertical-align: middle; border-right: 1px solid #000;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <th colspan="2" class="section-header-criterios" style="border: 1px solid #000;">FOTOS DO
                                        PRODUTO</th>
                                </tr>
                                <tr>
                                    <th class="categoria-header"
                                        style="vertical-align: middle; text-align: center; border: 1px solid #000;">EMBALAGEM
                                        PRIMÁRIA</th>
                                    <th class="categoria-header"
                                        style="vertical-align: middle; text-align: center; border: 1px solid #000;">EMBALAGEM
                                        SECUNDÁRIA</th>
                                </tr>
                                <tr>
                                    <td class="foto-cell"
                                        style="vertical-align: middle; text-align: center; border: 1px solid #000; width: 50%; padding: 5px;">
                                        <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_PRIMARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                            alt="Embalagem Primária" class="img-fluid">
                                    </td>
                                    <td class="foto-cell"
                                        style="vertical-align: middle; text-align: center; border: 1px solid #000; width: 50%; padding: 5px;">
                                        <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_SECUNDARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                            alt="Embalagem Secundária" class="img-fluid">
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td style="vertical-align: middle; text-align: center; border: 1px solid #000;">
                            <img src="<?php echo htmlspecialchars($caminhosFotos['SIF'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                alt="Selo SIF" class="img-fluid">
                        </td>
                    </tr>
                </table>

                <div class="assinatura-section" style="border-top: 1px solid #000; padding-top: 10px; margin-top: 15px;">
                    <table style="width: 100%; border-collapse: collapse; font-family: 'DejaVu Sans', sans-serif;">
                        <tr>
                            <td style="width: 65%; vertical-align: middle; font-size: 7pt;">
                                <p style="margin: 0; font-weight: bold; font-size: 8pt;">
                                    CONTROLE DE QUALIDADE:
                                    <span style="font-weight: normal; text-transform: uppercase;">
                                        <?php echo htmlspecialchars($ficha['header']['ficha_responsavel_tecnico'] ?? 'PRISCILA CASTRO'); ?>
                                    </span>
                                </p>
                                <p style="margin-top: 1px;">
                                    Data emissão: <?php echo date('d/m/Y'); ?>
                                </p>
                            </td>

                            <td style="width: 35%; vertical-align: bottom; text-align: right;">
                                <?php if (!empty($caminhosFotos['ASSINATURA'])): ?>
                                    <img src="<?php echo htmlspecialchars($caminhosFotos['ASSINATURA']); ?>"
                                        alt="Assinatura"
                                        style="max-height: 55px; max-width: 180px; object-fit: contain;">
                                <?php else: ?>
                                    <div style="height: 55px;"></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>


            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }
}
