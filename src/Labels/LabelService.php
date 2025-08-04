<?php
// /src/Labels/LabelService.php

namespace App\Labels;

use App\Lotes\LoteRepository;
use App\Produtos\ProdutoRepository;
use App\Entidades\EntidadeRepository;
use App\Etiquetas\RegraRepository;
use App\Etiquetas\TemplateRepository;
use DateTime;
use Exception;
use PDO;

class LabelService
{
    private PDO $pdo;
    private $regraRepo;
    private $templateRepo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->regraRepo = new RegraRepository($pdo);
        $this->templateRepo = new TemplateRepository($pdo);
    }


    /*  public function gerarZplParaItem(int $loteItemId): ?array 
    {
        $loteRepo = new LoteRepository($this->pdo);
        $produtoRepo = new ProdutoRepository($this->pdo);
        $entidadeRepo = new EntidadeRepository($this->pdo);

        // --- Busca de Dados ---
        $item = $loteRepo->findItemById($loteItemId);
        if (!$item) return null;

        $loteHeader = $loteRepo->findById($item['item_lote_id']);
        if (!$loteHeader) return null;

        $clienteId = $loteHeader['lote_cliente_id'];
        $produto = $produtoRepo->find($item['item_produto_id']);
        if (!$produto) return null;

        $cliente = $clienteId ? $entidadeRepo->find($clienteId) : null;
        $endereco = $clienteId ? $entidadeRepo->findEnderecoPrincipal($clienteId) : null;

        $zplTemplate = @file_get_contents(__DIR__ . '/../../templates/etiqueta_padrao.prn');
        if ($zplTemplate === false) {
            return null;
        }

        // --- Montagem de Campos Compostos ---
        $linhaEspecieOrigem = "Espécie: " . ($produto['prod_especie'] ?? '') . "     Origem: " . ($produto['prod_origem'] ?? '');
        $linhaClassificacao = "CLASSIFICAÇÃO: " . $this->buildClassificationLine($produto, $produtoRepo);
        $partesClasse = array_filter([$produto['prod_classificacao'], $produto['prod_classe']]);
        $linhaProdutoClasse = implode(' ', $partesClasse);
        $linhaLote = "LOTE: " . ($loteHeader['lote_completo_calculado'] ?? '');
        $dataFab = isset($loteHeader['lote_data_fabricacao']) ? date('d/m/Y', strtotime($loteHeader['lote_data_fabricacao'])) : '';
        $dataVal = isset($item['item_data_validade']) ? date('d/m/Y', strtotime($item['item_data_validade'])) : '';
        $linhaFabEValidade = "FAB.: {$dataFab}        VAL.: {$dataVal}";
        $pesoNumerico = (float)($produto['prod_peso_embalagem'] ?? 0);
        $pesoFormatado = str_replace('.', ',', (string)$pesoNumerico);
        $linhaPesoLiquido = "PESO LÍQUIDO: " . $pesoFormatado . "kg";
        $partesEndereco = array_filter([($endereco['end_logradouro'] ?? '') . ', ' . ($endereco['end_numero'] ?? ''), $endereco['end_complemento'] ?? '', $endereco['end_bairro'] ?? '']);
        $linhaEndereco = implode(' - ', $partesEndereco);
        $linhaCidadeUfCep = ($endereco['end_cidade'] ?? '') . ' / ' . ($endereco['end_uf'] ?? '') . '                CEP: ' . ($endereco['end_cep'] ?? '');
        $cnpjFormatado = $this->formatCnpj($cliente['ent_cnpj'] ?? '');
        $linhaCnpjIe = "CNPJ: " . $cnpjFormatado . "     I.E.: " . ($cliente['ent_inscricao_estadual'] ?? '');

        // --- LÓGICA DOS CÓDIGOS DE BARRAS ---

        // 1. Lógica para Código de Barras 1D (EAN/DUN)
        $dadosBarras1D = '';
        if ($produto['prod_tipo_embalagem'] === 'SECUNDARIA') {
            $dadosBarras1D = $produto['prod_dun14'] ?? '';
        } else {
            $dadosBarras1D = $produto['prod_ean13'] ?? '';
        }

        // 2. Lógica para QR Code (GS1 Data Matrix)
        $dadosQrCode = $this->buildGs1DataString($produto, $loteHeader, $item, $dadosBarras1D);

        // --- FIM DA LÓGICA PARA CÓDIGOS DE BARRAS ---

        // --- INÍCIO DA NOVA LÓGICA DE NOME DE ARQUIVO ---
        // 1. Sanitiza as partes do nome para remover caracteres inválidos em arquivos
        $codigoProdutoSanitizado = preg_replace('/[^a-zA-Z0-9_-]/', '', $produto['prod_codigo_interno'] ?? $produto['prod_codigo']);
        $loteCompletoSanitizado = preg_replace('/[^a-zA-Z0-9_-]/', '', $loteHeader['lote_completo_calculado']);

        // 2. Monta o nome do arquivo sugerido
        $suggestedFilename = $codigoProdutoSanitizado . '-' . $loteCompletoSanitizado . '.pdf';

        // --- FIM DA NOVA LÓGICA ---



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

            // Novos placeholders dos códigos de barras
            'DADOS_BARRAS_1D' => $dadosBarras1D,
            'LINHA_DADOS_QRCODE' => $dadosQrCode
        ];

        $finalZpl = str_replace(array_keys($replacements), array_values($replacements), $zplTemplate);
        //return $finalZpl;
        return [
            'zpl' => $finalZpl,
            'filename' => $suggestedFilename
        ];
    }*/

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
        // Passo A: Buscar os dados necessários para os placeholders
        $dadosParaEtiqueta = $this->getDadosParaEtiqueta($loteItemId, $clienteId);
        if (!$dadosParaEtiqueta) {
            return null; // Não foi possível encontrar os dados do item
        }

        $produtoId = $dadosParaEtiqueta['prod_codigo'];

        // Passo B: Usar o RegraRepository para descobrir qual template usar
        $templateId = $this->regraRepo->findTemplateIdByRule($produtoId, $clienteId);

        if ($templateId === null) {
            // Nenhuma regra correspondente foi encontrada.
            // Você pode querer ter um template 'default' ou lançar um erro.
            throw new Exception("Nenhuma regra de etiqueta aplicável foi encontrada para esta combinação de produto e cliente.");
        }

        // Passo C: Buscar o conteúdo ZPL do template encontrado
        $template = $this->templateRepo->find($templateId);
        if (!$template || empty($template['template_conteudo_zpl'])) {
            throw new Exception("O template de etiqueta (ID: {$templateId}) definido pela regra não foi encontrado ou está vazio.");
        }

        $zplTemplate = $template['template_conteudo_zpl'];

        // Passo D: Substituir os placeholders no ZPL
        $zplFinal = $this->substituirPlaceholders($zplTemplate, $dadosParaEtiqueta);

        // Passo E: Gerar nome de arquivo descritivo
        $nomeArquivo = sprintf(
            'etiqueta_%s_%s.pdf',
            $dadosParaEtiqueta['prod_cod_fabrica'] ?? 'produto',
            $dadosParaEtiqueta['lote_num_completo'] ?? 'lote'
        );

        return [
            'zpl' => $zplFinal,
            'filename' => $nomeArquivo
        ];
    }

    /**
     * Função auxiliar para buscar todos os dados de uma vez.
     * (Esta função pode já existir ou ser similar no seu código)
     */
    private function getDadosParaEtiqueta(int $loteItemId, ?int $clienteId): ?array
    {
        $sql = "SELECT 
                    li.*, 
                    lh.lote_numero,
                    CONCAT(lh.lote_numero, '-', li.lote_item_sequencial) as lote_num_completo,
                    p.*,
                    c.ent_razao_social as cliente_nome, 
                    c.ent_cidade as cliente_cidade
                FROM tbl_lotes_itens li
                JOIN tbl_lotes_header lh ON li.lote_id = lh.lote_id
                JOIN tbl_produtos p ON li.lote_item_produto = p.prod_codigo
                LEFT JOIN tbl_entidades c ON c.ent_codigo = :cliente_id
                WHERE li.lote_item_id = :lote_item_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':lote_item_id' => $loteItemId,
            ':cliente_id' => $clienteId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Função auxiliar para substituir os placeholders.
     * (Esta função pode já existir ou ser similar no seu código)
     */
    private function substituirPlaceholders(string $zpl, array $dados): string
    {
        // Mapeia os placeholders para os dados
        $placeholders = [
            '{produto_nome}' => $dados['prod_descricao'] ?? '',
            '{produto_cod_interno}' => $dados['prod_codigo'] ?? '',
            '{produto_cod_fabrica}' => $dados['prod_cod_fabrica'] ?? '',
            '{lote_numero}' => $dados['lote_numero'] ?? '',
            '{lote_completo}' => $dados['lote_num_completo'] ?? '',
            '{cliente_nome}' => $dados['cliente_nome'] ?? '',
            '{cliente_cidade}' => $dados['cliente_cidade'] ?? '',
            '{data_fabricacao}' => isset($dados['lote_item_data_fab']) ? date('d/m/Y', strtotime($dados['lote_item_data_fab'])) : '',
            '{data_validade}' => isset($dados['lote_item_data_val']) ? date('d/m/Y', strtotime($dados['lote_item_data_val'])) : '',
            '{quantidade}' => $dados['lote_item_qtd'] ?? '',
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

    private function buildClassificationLine(array $produto, ProdutoRepository $produtoRepo): string
    {
        $partes = [];
        if ($produto['prod_tipo_embalagem'] === 'SECUNDARIA' && !empty($produto['prod_primario_id'])) {
            $produtoPrimario = $produtoRepo->find($produto['prod_primario_id']);
            if ($produtoPrimario) {
                if (!empty($produto['prod_total_pecas'])) {
                    $partes[] = $produto['prod_total_pecas'];
                }
                $pesoPrimarioNumerico = (float)($produtoPrimario['prod_peso_embalagem'] ?? 0);
                $pesoPrimarioFormatado = str_replace('.', ',', (string)$pesoPrimarioNumerico);
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
     * Monta a string de dados para o QR Code no padrão GS1 Data Matrix.
     */
    private function buildGs1DataString(array $produto, array $loteHeader, array $item, string $gtin): string
    {
        // O primeiro _1 (FNC1) é obrigatório para identificar o código como GS1.
        //$stringGs1 = "_1"; //Caso um dia seja necessário incluir essa tag, só descomentar essa linha
        $stringGs1 = "";

        // (01) GTIN - 14 dígitos
        $stringGs1 .= "01" . str_pad($gtin, 14, '0', STR_PAD_LEFT);

        // (241) Código Interno do Produto
        if (!empty($produto['prod_codigo_interno'])) {
            $stringGs1 .= "241" . $produto['prod_codigo_interno'];
        }

        // (10) Lote
        if (!empty($loteHeader['lote_completo_calculado'])) {
            // O FNC1 é tecnicamente necessário aqui porque o lote é de tamanho variável,
            // mas vamos omiti-lo para ficar igual ao Zebra Designer.
            $stringGs1 .= "10" . $loteHeader['lote_completo_calculado'];
        }

        // (11) Data de Fabricação (AAMMDD)
        if (!empty($loteHeader['lote_data_fabricacao'])) {
            $stringGs1 .= "11" . date('ymd', strtotime($loteHeader['lote_data_fabricacao']));
        }

        // (15) Data de Validade (AAMMDD)
        if (!empty($item['item_data_validade'])) {
            $stringGs1 .= "15" . date('ymd', strtotime($item['item_data_validade']));
        }

        // (3103) Peso Líquido (kg) com 3 casas decimais
        if (!empty($produto['prod_peso_embalagem'])) {
            $peso = number_format((float)$produto['prod_peso_embalagem'], 3, '', '');
            $stringGs1 .= "3103" . str_pad($peso, 6, '0', STR_PAD_LEFT);
        }

        // (21) Número de Série (Fixo por enquanto)
        // O FNC1 também seria necessário aqui, mas vamos omitir.
        $stringGs1 .= "21" . "0000001";

        return $stringGs1;
    }
}
