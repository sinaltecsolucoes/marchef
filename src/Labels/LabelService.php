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

    public function gerarZplParaItem(int $loteItemId, ?int $clienteId = null): ?string
    {
        $loteRepo = new LoteRepository($this->pdo);
        $produtoRepo = new ProdutoRepository($this->pdo);
        $entidadeRepo = new EntidadeRepository($this->pdo);

        // --- Busca de Dados ---
        $item = $loteRepo->findItemById($loteItemId);
        if (!$item) return null;

        $loteHeader = $loteRepo->findById($item['item_lote_id']);
        
        $produto = $produtoRepo->find($item['item_produto_id']);

        $cliente = null;
        $endereco = null;
        if ($clienteId) {
            $cliente = $entidadeRepo->find($clienteId);
            $endereco = $entidadeRepo->findEnderecoPrincipal($clienteId);
        }

        // --- Carregamento do Template ---
        $zplTemplate = @file_get_contents(__DIR__ . '/../../templates/etiqueta_padrao.prn');
        if ($zplTemplate === false) {
            return null; // Erro: não foi possível ler o arquivo de template
        }

        // --- Mapeamento e Substituição ---
        $replacements = [
            'CLASSE_PRODUTO' => $produto['prod_classe'] ?? '',
            'CLASSIFICACAO_PRODUTO' => $produto['prod_classificacao'] ?? '',
            'ESPECIE_PRODUTO' => $produto['prod_especie'] ?? '',
            'CATEGORIA_PRODUTO' => $produto['prod_categoria'] ?? '',
            'ORIGEM_PRODUTO' => $produto['prod_origem'] ?? '',
            'TPECAS_PRODUTOS' => $produto['prod_total_pecas'] ?? '',
            'PESO_EMBALAGEM_PRIMARIA' => ($produto['prod_tipo_embalagem'] === 'PRIMARIA') ? $produto['prod_peso_embalagem'] : '',
            'PESO_EMBALAGEM_SECUNDARIA' => ($produto['prod_tipo_embalagem'] === 'SECUNDARIA') ? $produto['prod_peso_embalagem'] : '',
            
            'LOTE_COMPLETO' => $loteHeader['lote_completo_calculado'] ?? '',
            'DATA_FABRICACAO_LOTE' => isset($loteHeader['lote_data_fabricacao']) ? date('d/m/Y', strtotime($loteHeader['lote_data_fabricacao'])) : '',
            'DATA_VALIDADE_ITEM' => isset($item['item_data_validade']) ? date('d/m/Y', strtotime($item['item_data_validade'])) : '',
            
            'RAZAO_SOCIAL_CLIENTE' => $cliente['ent_razao_social'] ?? '',
            'LOGRADOURO_CLIENTE' => isset($endereco['end_logradouro']) ? $endereco['end_logradouro'] . ', ' . $endereco['end_numero'] : '',
            'COMPLEMENTO_ENDERECO_CLIENTE' => $endereco['end_complemento'] ?? '',
            'BAIRRO_CLIENTE' => $endereco['end_bairro'] ?? '',
            'CIDADE_ENDERECO_CLIENTE' => $endereco['end_cidade'] ?? '',
            'UF_ENDERECO_CLIENTE' => $endereco['end_uf'] ?? '',
            'CEP_ENDERECO_CLIENTE' => $endereco['end_cep'] ?? '',
            'CNPJ_CLIENTE' => $cliente['ent_cnpj'] ?? '', // Coluna correta é ent_cnpj
            'INSCRICAO_ESTADUAL_CLIENTE' => $cliente['ent_inscricao_estadual'] ?? '', // Coluna correta é ent_inscricao_estadual

            // Placeholder para o código de barras - PRECISAMOS DEFINIR A LÓGICA
            'DADOS_CODIGO_BARRAS' => '_101278989740913011125073115260731100001/25-11/1A 3832_13104100000210001' // Valor fixo por enquanto
        ];

        $finalZpl = str_replace(array_keys($replacements), array_values($replacements), $zplTemplate);

        return $finalZpl;
    }
}