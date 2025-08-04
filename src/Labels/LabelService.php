<?php
// /src/Labels/LabelService.php

namespace App\Labels;

use App\Etiquetas\RegraRepository;
use App\Etiquetas\TemplateRepository;
use App\Produtos\ProdutoRepository;
use Exception;
use PDO;

class LabelService
{
    private PDO $pdo;
    private $regraRepo;
    private $templateRepo;
    private ProdutoRepository $produtoRepo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->regraRepo = new RegraRepository($pdo);
        $this->templateRepo = new TemplateRepository($pdo);
        $this->produtoRepo = new ProdutoRepository($pdo);
    }

    /**
     * Gera o ZPL para um item de lote, selecionando dinamicamente o template
     * com base nas regras e no cliente de destino.
     *
     * @param int $loteItemId O ID do item do lote.
     * @param int|null $clienteId O ID do cliente de destino (pode ser nulo).
     * @return array|null Um array com 'zpl' e 'filename' ou null se falhar.
     */
    public function gerarZplParaItem(int $loteItemId, ?int $clienteId): ?array
    {
        // 1. Buscar todos os dados necessários de uma vez
        $dados = $this->getDadosParaEtiqueta($loteItemId, $clienteId);
        if (!$dados) {
            throw new Exception("Dados para a etiqueta do item ID {$loteItemId} não foram encontrados.");
        }

        // 2. Descobrir qual template usar com base nas regras
        $templateId = $this->regraRepo->findTemplateIdByRule($dados['prod_codigo'], $clienteId);
        if ($templateId === null) {
            throw new Exception("Nenhuma regra de etiqueta aplicável foi encontrada para esta combinação de produto e cliente.");
        }

        // 3. Buscar o conteúdo ZPL do template encontrado
        $template = $this->templateRepo->find($templateId);
        if (!$template || empty($template['template_conteudo_zpl'])) {
            throw new Exception("O template de etiqueta (ID: {$templateId}) definido pela regra não foi encontrado ou está vazio.");
        }
        $zplTemplate = $template['template_conteudo_zpl'];

        // 4. Substituir os placeholders no ZPL com a lógica de negócio
        $zplFinal = $this->substituirPlaceholders($zplTemplate, $dados);

        // 5. Gerar um nome de ficheiro descritivo
        $nomeArquivo = sprintf(
            'etiqueta_%s_%s.pdf',
            preg_replace('/[^a-zA-Z0-9_-]/', '', $dados['prod_cod_fabrica'] ?? 'produto'),
            preg_replace('/[^a-zA-Z0-9_-]/', '', $dados['lote_num_completo'] ?? 'lote')
        );

        return ['zpl' => $zplFinal, 'filename' => $nomeArquivo];
    }

    /**
     * Função auxiliar para buscar todos os dados de uma vez.
     * (Esta função pode já existir ou ser similar no seu código)
     */
    private function getDadosParaEtiqueta(int $loteItemId, ?int $clienteId): ?array
    {
        // Esta query já é bastante completa e busca a maioria dos dados que precisamos
        $sql = "SELECT 
                    li.*, lh.*, p.*,
                    CONCAT(lh.lote_numero, '-', li.lote_item_sequencial) as lote_num_completo,
                    c.ent_razao_social, c.ent_cnpj, c.ent_inscricao_estadual,
                    end.end_logradouro, end.end_numero, end.end_complemento, end.end_bairro, end.end_cidade, end.end_uf, end.end_cep
                FROM tbl_lotes_itens li
                JOIN tbl_lotes_header lh ON li.lote_id = lh.lote_id
                JOIN tbl_produtos p ON li.lote_item_produto = p.prod_codigo
                LEFT JOIN tbl_entidades c ON c.ent_codigo = :cliente_id
                LEFT JOIN (
                    SELECT *, ROW_NUMBER() OVER(PARTITION BY end_entidade_id ORDER BY CASE end_tipo_endereco WHEN 'Principal' THEN 1 ELSE 2 END) as rn 
                    FROM tbl_enderecos
                ) end ON c.ent_codigo = end.end_entidade_id AND end.rn = 1
                WHERE li.lote_item_id = :lote_item_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_item_id' => $loteItemId, ':cliente_id' => $clienteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Função auxiliar para substituir os placeholders.
     */
    private function substituirPlaceholders(string $zpl, array $dados): string
    {
        // --- LÓGICA DE FONTE DINÂMICA ---
        $nomeProduto = $dados['prod_descricao'] ?? '';
        $tamanhoNome = strlen($nomeProduto);
        if ($tamanhoNome <= 30)
            $comandoFonte = '^A0N,40,40';
        elseif ($tamanhoNome <= 45)
            $comandoFonte = '^A0N,32,32';
        else
            $comandoFonte = '^A0N,28,28';

        // --- LÓGICA DE CÓDIGOS DE BARRAS ---
        $dadosBarras1D = ($dados['prod_tipo_embalagem'] === 'SECUNDARIA') ? $dados['prod_dun14'] : $dados['prod_ean13'];
        $dadosQrCode = $this->buildGs1DataString($dados, $dadosBarras1D);

        // --- LÓGICA DE CAMPOS COMPOSTOS ---
        $partesClasse = array_filter([$dados['prod_classificacao'], $dados['prod_classe']]);
        $linhaProdutoClasse = implode(' ', $partesClasse);
        $linhaEspecieOrigem = "Espécie: " . ($dados['prod_especie'] ?? '') . "     Origem: " . ($dados['prod_origem'] ?? '');
        $linhaClassificacao = "CLASSIFICAÇÃO: " . $this->buildClassificationLine($dados);
        $linhaLote = "LOTE: " . ($dados['lote_num_completo'] ?? '');
        $dataFab = isset($dados['lote_data_fabricacao']) ? date('d/m/Y', strtotime($dados['lote_data_fabricacao'])) : '';
        $dataVal = isset($dados['lote_item_data_val']) ? date('d/m/Y', strtotime($dados['lote_item_data_val'])) : '';
        $linhaFabEValidade = "FAB.: {$dataFab}        VAL.: {$dataVal}";
        $pesoFormatado = str_replace('.', ',', (string) ((float) ($dados['prod_peso_embalagem'] ?? 0)));
        $linhaPesoLiquido = "PESO LÍQUIDO: " . $pesoFormatado . "kg";
        $partesEndereco = array_filter([($dados['end_logradouro'] ?? '') . ', ' . ($dados['end_numero'] ?? ''), $dados['end_complemento'] ?? '', $dados['end_bairro'] ?? '']);
        $linhaEndereco = implode(' - ', $partesEndereco);
        $linhaCidadeUfCep = ($dados['end_cidade'] ?? '') . ' / ' . ($dados['end_uf'] ?? '') . '                CEP: ' . ($dados['end_cep'] ?? '');
        $linhaCnpjIe = "CNPJ: " . $this->formatCnpj($dados['ent_cnpj'] ?? '') . "     I.E.: " . ($dados['ent_inscricao_estadual'] ?? '');

        // --- MAPA COMPLETO DE PLACEHOLDERS ---
        $placeholders = [
            // Simples
            '{produto_nome}' => $nomeProduto,
            '{produto_cod_interno}' => $dados['prod_codigo'] ?? '',
            '{lote_completo}' => $dados['lote_num_completo'] ?? '',
            '{cliente_nome}' => $dados['ent_razao_social'] ?? '',
            '{data_fabricacao}' => $dataFab,
            '{data_validade}' => $dataVal,
            '{quantidade}' => $dados['lote_item_qtd'] ?? '',
            '{categoria_produto}' => $dados['prod_categoria'] ?? '',

            // Compostos e de Negócio
            '{fonte_produto_nome}' => $comandoFonte,
            '{linha_produto_classe}' => $linhaProdutoClasse,
            '{linha_especie_origem}' => $linhaEspecieOrigem,
            '{linha_classificacao_unidades_peso}' => $linhaClassificacao,
            '{linha_lote_completo}' => $linhaLote,
            '{linha_fab_e_validade}' => $linhaFabEValidade,
            '{linha_peso_liquido}' => $linhaPesoLiquido,
            '{linha_cliente_endereco}' => $linhaEndereco,
            '{linha_cliente_cidade_uf_cep}' => $linhaCidadeUfCep,
            '{linha_cliente_cnpj_ie}' => $linhaCnpjIe,

            // Códigos de Barras
            '{dados_barras_1d}' => $dadosBarras1D ?? '',
            '{dados_qrcode_gs1}' => $dadosQrCode ?? ''
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $zpl);
    }

    // --- FUNÇÕES AJUDANTES (HELPERS) ---
    private function formatCnpj(string $cnpj): string
    { 
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) != 14) {
            return $cnpj;
        }
        return sprintf('%s.%s.%s/%s-%s', substr($cnpj, 0, 2), substr($cnpj, 2, 3), substr($cnpj, 5, 3), substr($cnpj, 8, 4), substr($cnpj, 12, 2));
    }

    private function buildClassificationLine(array $produto): string
    { 
        $partes = [];
        if ($produto['prod_tipo_embalagem'] === 'SECUNDARIA' && !empty($produto['prod_primario_id'])) {
            $produtoPrimario = $this->produtoRepo->find($produto['prod_primario_id']);
            if ($produtoPrimario) {
                if (!empty($produto['prod_total_pecas']))
                    $partes[] = $produto['prod_total_pecas'];
                $pesoPrimarioFormatado = str_replace('.', ',', (string) ((float) ($produtoPrimario['prod_peso_embalagem'] ?? 0)));
                $partes[] = "UNIDADES/" . $pesoPrimarioFormatado . "kg";
            }
        } else {
            if (!empty($produto['prod_classificacao']))
                $partes[] = $produto['prod_classificacao'];
            if (!empty($produto['prod_total_pecas']))
                $partes[] = $produto['prod_total_pecas'];
        }
        return implode(' ', $partes);
    }

    /**
     * Monta a string de dados para o QR Code no padrão GS1 Data Matrix.
     */
    private function buildGs1DataString(array $dados, string $gtin): string
    { 
        $stringGs1 = "";
        $stringGs1 .= "01" . str_pad($gtin, 14, '0', STR_PAD_LEFT);
        if (!empty($dados['prod_codigo_interno']))
            $stringGs1 .= "241" . $dados['prod_codigo_interno'];
        if (!empty($dados['lote_num_completo']))
            $stringGs1 .= "10" . $dados['lote_num_completo'];
        if (!empty($dados['lote_data_fabricacao']))
            $stringGs1 .= "11" . date('ymd', strtotime($dados['lote_data_fabricacao']));
        if (!empty($dados['lote_item_data_val']))
            $stringGs1 .= "15" . date('ymd', strtotime($dados['lote_item_data_val']));
        if (!empty($dados['prod_peso_embalagem'])) {
            $peso = number_format((float) $dados['prod_peso_embalagem'], 3, '', '');
            $stringGs1 .= "3103" . str_pad($peso, 6, '0', STR_PAD_LEFT);
        }
        $stringGs1 .= "21" . "0000001"; // Número de série fixo
        return $stringGs1;
    }
}
