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

        // --- Bloco de busca de cliente e endereço ---
        $cliente = $clienteId ? $entidadeRepo->find($clienteId) : null;
        $endereco = $clienteId ? $entidadeRepo->findEnderecoPrincipal($clienteId) : null;

        // --- Carregamento do Template ---
        $zplTemplate = @file_get_contents(__DIR__ . '/../../templates/etiqueta_padrao.prn');
        if ($zplTemplate === false) {
            return null; // Erro: não foi possível ler o arquivo de template
        }

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
}