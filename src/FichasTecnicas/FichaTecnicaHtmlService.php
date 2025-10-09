<?php
// /src/FichasTecnicas/FichaTecnicaHtmlService.php
namespace App\FichasTecnicas;

use DateTime;
use Exception;

// ATENÇÃO: Se as funções formatarTextoTabela e formatarPesoBrasileiro
// estiverem definidas *fora* da classe neste arquivo, remova-as e use as
// closures que estão dentro do método renderHtml abaixo.

class FichaTecnicaHtmlService
{
    private FichaTecnicaRepository $repository;

    public function __construct(FichaTecnicaRepository $repository)
    {
        $this->repository = $repository;
    }

    public function renderHtml(int $fichaId): string
    {
        // 1. BUSCA DE DADOS (Manter como estava)
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
            // Usa BASE_URL para gerar caminhos absolutos para o DomPDF
            $caminhosFotos[$foto['foto_tipo']] = BASE_URL . '/' . $foto['foto_path'];
        }

        // 2. FUNÇÕES DE FORMATAÇÃO (closures)

        // Remove nl2br para evitar quebras de linha indesejadas no PDF
        $formatarTextoTabela = function (?string $texto): string {
            if (empty($texto)) {
                return 'N/A';
            }
            return htmlspecialchars($texto);
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
                /* Estilos adaptados para a compatibilidade com DomPDF */

                @page {
                    size: A4 portrait;
                    /* Força A4 Retrato */
                    margin: 1cm;

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
                        font-size: 8pt;
                        color: #888;
                    }
                }

                body {
                    font-family: Arial, sans-serif;
                    font-size: 8pt;
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

                .criterios-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 8px;
                    table-layout: fixed;
                }

                .criterios-table th,
                .criterios-table td {
                    font-size: 8px;
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
                }

                .assinatura-section {
                    margin-top: 5px;
                    border-top: 1px solid #000;
                    padding-top: 5px;
                    font-size: 9pt;
                }

                /* Garante que imagens aninhadas em td tenham tamanho máximo */
                .img-fluid {
                    max-width: 100%;
                    height: auto;
                    display: block;
                    margin: 0 auto;
                }

                /* REGRAS CRÍTICAS PARA O LAYOUT INFERIOR */
                .layout-master-table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                    /* ESSENCIAL para 50%/50% */
                    margin-top: 5px;
                }

                .layout-master-table>tbody>tr>td {
                    /* Aplica 50% de largura para as duas células mestras */
                    width: 50%;
                    padding: 0;
                    /* Remove padding que pode quebrar a conta */
                    vertical-align: top;
                }
            </style>
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
                        <td colspan="7"><?php echo $formatarTextoTabela($ficha['header']['ficha_conservantes'] ?? null); ?></td>
                    </tr>
                    <tr>
                        <th colspan="3">Alergênicos</th>
                        <td colspan="7"><?php echo $formatarTextoTabela($ficha['header']['ficha_alergenicos'] ?? null); ?></td>
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
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_temp_estocagem_transporte'] ?? null); ?>
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
                        <td colspan="7"><?php echo $formatarTextoTabela($ficha['header']['ficha_desc_emb_primaria'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="1">Peso Líquido</td>
                        <td colspan="7">
                            <?php echo $formatarPesoBrasileiro($ficha['header']['prod_peso_embalagem_primaria'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="1">Medidas</td>
                        <td colspan="7">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_medidas_emb_primaria'] ?? null); ?>
                        </td>

                    </tr>




                    <tr>
                        <th rowspan="3" colspan="2">Embalagem secundária</th>
                        <td colspan="1">Material</td>
                        <td colspan="7">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_desc_emb_secundaria'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="1">Peso Líquido</td>
                        <td colspan="7">
                            <?php echo $formatarPesoBrasileiro($ficha['header']['peso_embalagem'] ?? null); ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="1">Medidas</td>
                        <td colspan="7">
                            <?php echo $formatarTextoTabela($ficha['header']['ficha_medidas_emb_secundaria'] ?? null); ?>
                        </td>
                    </tr>


                    <tr>
                        <th colspan="3">Paletização</th>
                        <td colspan="7"><?php echo $formatarTextoTabela($ficha['header']['ficha_paletizacao'] ?? null); ?></td>
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
                        <td colspan="7"><?php echo $formatarTextoTabela($ficha['header']['ficha_gestao_qualidade'] ?? null); ?>
                        </td>
                    </tr>
                </table>


                <table class="ficha-table" style="width: 100%; table-layout: fixed; margin-top: 5px;">
                    <tr>
                        <td style="width: 50%; padding: 0; border: none;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 0; text-align: center; height: 320px; vertical-align: top;">
                                        <table style="width: 100%; border: 1px solid #000; border-collapse: collapse;">
                                            <tr>
                                                <td style="padding: 5px; border: none;">
                                                    <h4 style="font-size: 10pt; font-weight: bold; margin-bottom: 2px;">
                                                        INFORMAÇÃO NUTRICIONAL</h4>
                                                    <img src="<?php echo htmlspecialchars($caminhosFotos['TABELA_NUTRICIONAL'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                                        alt="Tabela Nutricional" class="img-fluid" style="max-height: 300px;">
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
                                <tr>
                                    <th colspan="2" class="section-header" style="font-size: 9pt; border: 1px solid #000;">FOTOS
                                        DO PRODUTO</th>
                                </tr>
                                <tr>
                                    <th class="text-center" style="width: 50%;border: 1px solid #000;">EMBALAGEM PRIMÁRIA</th>
                                    <th class="text-center" style="width: 50%;border: 1px solid #000;">EMBALAGEM SECUNDÁRIA</th>
                                </tr>
                                <tr>
                                    <td class="text-center" style="height: 180px; border: 1px solid #000; padding: 0;">
                                        <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_PRIMARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                            alt="Embalagem Primária" class="img-fluid" style="max-height: 90%;">
                                    </td>
                                    <td class="text-center" style="height: 180px; border: 1px solid #000; padding: 0;">
                                        <img src="<?php echo htmlspecialchars($caminhosFotos['EMBALAGEM_SECUNDARIA'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                            alt="Embalagem Secundária" class="img-fluid" style="max-height: 90%;">
                                    </td>
                                </tr>
                            </table>
                        </td>

                        <td style="width: 50%; padding: 0; border: none;">
                            <table class="criterios-table" style="width: 100%; border-collapse: collapse; height: 320px;">
                            </table>

                            <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
                                <tr>
                                    <td class="text-center"
                                        style="height: 180px; border: 1px solid #000; vertical-align: middle;">
                                        <img src="<?php echo htmlspecialchars($caminhosFotos['SIF'] ?? BASE_URL . '/assets/img/placeholder.png'); ?>"
                                            alt="Selo SIF" class="img-fluid" style="max-width: 90%; max-height: 90%;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <div class="assinatura-section" style="border-top: 1px solid #000; padding-top: 5px;">
                </div>


            </div>
        </body>

        </html>

        <?php
        return ob_get_clean();
    }
}