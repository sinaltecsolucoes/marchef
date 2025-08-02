<?php
// /src/Labels/LabelService.php

namespace App\Labels;

use App\Lotes\LoteRepository;
use App\Produtos\ProdutoRepository;
use App\Entidades\EntidadeRepository;
use PDO;

class LabelService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function gerarZplParaItem(int $loteItemId): ?string
    {
        $loteRepo = new LoteRepository($this->pdo);
        $produtoRepo = new ProdutoRepository($this->pdo);
        $entidadeRepo = new EntidadeRepository($this->pdo);

        // --- Busca de Dados ---
        $item = $loteRepo->findItemById($loteItemId);
        if (!$item)
            return null;

        $loteHeader = $loteRepo->findById($item['item_lote_id']);
        if (!$loteHeader)
            return null;

        $clienteId = $loteHeader['lote_cliente_id'];
        $produto = $produtoRepo->find($item['item_produto_id']);
        if (!$produto)
            return null;

        // --- Bloco de busca de cliente e endereço ---
        $cliente = $clienteId ? $entidadeRepo->find($clienteId) : null;
        $endereco = $clienteId ? $entidadeRepo->findEnderecoPrincipal($clienteId) : null;

        // --- Carregamento do Template ---
        $zplTemplate = @file_get_contents(__DIR__ . '/../../templates/etiqueta_padrao.prn');
        if ($zplTemplate === false) {
            return null; // Erro: não foi possível ler o arquivo de template
        }

        // --- Montagem dos Campos Compostos ---
        $linhaEspecieOrigem = "Espécie: " . ($produto['prod_especie'] ?? '') . "     Origem: " . ($produto['prod_origem'] ?? '');

        $linhaClassificacao = "CLASSIFICAÇÃO: " . $this->buildClassificationLine($produto, $produtoRepo);


        /* $linhaClassificacao = "CLASSIFICAÇÃO: ";
         $partesClassificacao = [];

         // Se o produto atual (a caixa) for SECUNDÁRIO e tiver um produto primário associado
         if ($produto['prod_tipo_embalagem'] === 'SECUNDARIA' && !empty($produto['prod_primario_id'])) {

             // Busca os dados do produto primário para pegar o peso da embalagem dele
             $produtoPrimario = $produtoRepo->find($produto['prod_primario_id']);

             if ($produtoPrimario) {
                 // Pega o total de peças do produto SECUNDÁRIO (a caixa)
                 if (!empty($produto['prod_total_pecas'])) {
                     $partesClassificacao[] = $produto['prod_total_pecas'];
                 }

                 // Pega o peso da embalagem do produto PRIMÁRIO (o pacote dentro da caixa)
                 $pesoPrimarioNumerico = (float) ($produtoPrimario['prod_peso_embalagem'] ?? 0);
                 $pesoPrimarioFormatado = str_replace('.', ',', (string) $pesoPrimarioNumerico);

                 $partesClassificacao[] = "UNIDADES/" . $pesoPrimarioFormatado . "kg";
             }
         } else {
             // Lógica antiga para produtos primários (ou secundários sem primário associado)
             if (!empty($produto['prod_classificacao'])) {
                 $partesClassificacao[] = $produto['prod_classificacao'];
             }
             if (!empty($produto['prod_total_pecas'])) {
                 $partesClassificacao[] = $produto['prod_total_pecas'];
             }
         }
         $linhaClassificacao .= implode(' ', $partesClassificacao);*/

        // --- FORMATAÇÃO DA LINHA DA PRIMEIRA LINHA DA ETIQUETA (CLASSIFICAÇÃO + DESCRIÇÃO PRODUTO)
        /*  $partesClasse = [];
          if (!empty($produto['prod_classificacao'])) {
              $partesClasse[] = $produto['prod_classificacao'];
          }
          if (!empty($produto['prod_classe'])) {
              $partesClasse[] = $produto['prod_classe'];
          }
          $linhaProdutoClasse = implode(' ', $partesClasse);*/

        $linhaProdutoClasse = implode(' ', array_filter([$produto['prod_classificacao'], $produto['prod_classe']]));


        $linhaLote = "LOTE: " . ($loteHeader['lote_completo_calculado'] ?? '');

        $dataFab = isset($loteHeader['lote_data_fabricacao']) ? date('d/m/Y', strtotime($loteHeader['lote_data_fabricacao'])) : '';
        $dataVal = isset($item['item_data_validade']) ? date('d/m/Y', strtotime($item['item_data_validade'])) : '';
        $linhaFabEValidade = "FAB.: {$dataFab}        VAL.: {$dataVal}";

        // --- FORMATAÇÃO DO PESO LÍQUIDO ---
        $pesoNumerico = (float) ($produto['prod_peso_embalagem'] ?? 0); // Converte para número, removendo zeros à direita
        $pesoFormatado = str_replace('.', ',', (string) $pesoNumerico); // Converte de volta para string, trocando . por ,
        $linhaPesoLiquido = "PESO LÍQUIDO: " . $pesoFormatado . "kg";

        // --- FORMATAÇÃO DA LINHA ENDEREÇO ---
        // Cria um array para juntar as partes do endereço na ordem correta
        $partesEndereco = [];

        // 1. Adiciona Logradouro e Número (parte principal)
        $partesEndereco[] = ($endereco['end_logradouro'] ?? '') . ', ' . ($endereco['end_numero'] ?? '');

        // 2. Adiciona o Complemento, se ele não estiver vazio
        if (!empty($endereco['end_complemento'])) {
            $partesEndereco[] = $endereco['end_complemento'];
        }

        // 3. Adiciona o Bairro, se ele não estiver vazio
        if (!empty($endereco['end_bairro'])) {
            $partesEndereco[] = $endereco['end_bairro'];
        }

        // 4. Junta todas as partes que existem com " - " como separador
        $linhaEndereco = implode(' - ', $partesEndereco);

        $linhaCidadeUfCep = ($endereco['end_cidade'] ?? '') . ' / ' . ($endereco['end_uf'] ?? '') . '      CEP: ' . ($endereco['end_cep'] ?? '');

        // --- FORMATAÇÃO DO CNPJ ---
        $cnpjFormatado = $this->formatCnpj($cliente['ent_cnpj'] ?? '');
        $linhaCnpjIe = "CNPJ: " . $cnpjFormatado . "     I.E.: " . ($cliente['ent_inscricao_estadual'] ?? '');

        // 1. Lógica para Código de Barras 1D (EAN/DUN)
        $dadosBarras1D = '';
        if ($produto['prod_tipo_embalagem'] === 'SECUNDARIA') {
            $dadosBarras1D = $produto['prod_dun14'] ?? '';
        } else {
            $dadosBarras1D = $produto['prod_ean13'] ?? '';
        }

        // 2. Lógica para QR Code (GS1 Data Matrix)
        $dadosQrCode = $this->buildGs1DataString($produto, $loteHeader, $item, $dadosBarras1D);



        // --- Mapeamento e Substituição ---
        $replacements = [
            'LINHA_PRODUTO_CLASSE' => $linhaProdutoClasse,
            'LINHA_ESPECIE_ORIGEM' => $linhaEspecieOrigem,
            'LINHA_CLASSIFICACAO_UNIDADES_PESO' => $linhaClassificacao,
            'LINHA_LOTE_COMPLETO' => $linhaLote,
            'LINHA_FAB_E_VALIDADE' => $linhaFabEValidade,
            'LINHA_PESO_LIQUIDO' => $linhaPesoLiquido,
            'CATEGORIA_PRODUTO' => $produto['prod_categoria'] ?? '',
            'PARA_CLIENTE_RAZAO_SOCIAL' => $cliente['ent_razao_social'] ?? '',
            'PARA_CLIENTE_ENDERECO' => $linhaEndereco,
            'PARA_CLIENTE_CIDADE_UF_CEP' => $linhaCidadeUfCep,
            'PARA_CLIENTE_CNPJ_IE' => $linhaCnpjIe,
            'DADOS_CODIGO_BARRAS' => '_101278989740913011125073115260731100001/25-11/1A 3832_13104100000210001' // Ainda fixo
        ];

        $finalZpl = str_replace(array_keys($replacements), array_values($replacements), $zplTemplate);

        return $finalZpl;
    }

    private function buildClassificationLine(array $produto, ProdutoRepository $produtoRepo): string
    {
        $partes = [];
        if ($produto['prod_tipo_embalagem'] === 'SECUNDARIA' && !empty($produto['prod_primario_id'])) {
            $produtoPrimario = $produtoRepo->find($produto['prod_primario_id']);
            if ($produtoPrimario) {
                if (!empty($produto['prod_total_pecas'])) {
                    $partes[] = $produto['prod_total_pecas'];
                }
                $pesoPrimarioNumerico = (float) ($produtoPrimario['prod_peso_embalagem'] ?? 0);
                $pesoPrimarioFormatado = str_replace('.', ',', (string) $pesoPrimarioNumerico);
                $partes[] = "UNIDADES/" . $pesoPrimarioFormatado . "kg";
            }
        } else {
            if (!empty($produto['prod_classificacao'])) {
                $partes[] = $produto['prod_classificacao'];
            }
            if (!empty($produto['prod_total_pecas'])) {
                $partes[] = $produto['prod_total_pecas'];
            }
        }
        return implode(' ', $partes);
    }

    /**
     * Formata uma string de CNPJ com a máscara padrão.
     * @param string $cnpj Apenas os números do CNPJ.
     * @return string O CNPJ formatado ou o valor original se não for válido.
     */
    private function formatCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj); // Remove qualquer caractere não numérico

        if (strlen($cnpj) != 14) {
            return $cnpj; // Retorna o original se não tiver 14 dígitos
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }

    private function buildGs1DataString(array $produto, array $loteHeader, array $item, string $gtin): string
    {
        $gs1Parts = [];
        $fnc = "_1"; // FNC1 character for GS1

        // (01) GTIN - 14 dígitos
        $gs1Parts[] = "01" . str_pad($gtin, 14, '0', STR_PAD_LEFT);

        // (241) Código Interno do Produto
        if (!empty($produto['prod_codigo_interno'])) {
            $gs1Parts[] = "241" . $produto['prod_codigo_interno'];
        }

        // (10) Lote
        if (!empty($loteHeader['lote_completo_calculado'])) {
            $gs1Parts[] = "10" . $loteHeader['lote_completo_calculado'];
        }

        // (11) Data de Fabricação (AAMMDD)
        if (!empty($loteHeader['lote_data_fabricacao'])) {
            $gs1Parts[] = "11" . date('ymd', strtotime($loteHeader['lote_data_fabricacao']));
        }

        // (15) Data de Validade (AAMMDD)
        if (!empty($item['item_data_validade'])) {
            $gs1Parts[] = "15" . date('ymd', strtotime($item['item_data_validade']));
        }

        // (3103) Peso Líquido (kg) com 3 casas decimais
        if (!empty($produto['prod_peso_embalagem'])) {
            // Formata o peso para 6 dígitos, sem ponto. Ex: 5.250kg -> 005250
            $peso = number_format((float) $produto['prod_peso_embalagem'], 3, '', '');
            $gs1Parts[] = "3103" . str_pad($peso, 6, '0', STR_PAD_LEFT);
        }

        // (21) Número de Série (Fixo por enquanto, para a próxima fase)
        $gs1Parts[] = "21" . "0000001";

        return $fnc . implode($fnc, $gs1Parts);
    }
}
