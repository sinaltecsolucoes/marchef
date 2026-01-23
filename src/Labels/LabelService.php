<?php
// /src/Labels/LabelService.php
namespace App\Labels;

require_once __DIR__ . '/../../public/libs/phpqrcode/phpqrcode.php';

use PDO;
use Exception;
use App\Etiquetas\RegraRepository;
use App\Etiquetas\TemplateRepository;
use App\Produtos\ProdutoRepository;

class LabelService
{
    private PDO $pdo;
    private RegraRepository $regraRepo;
    private TemplateRepository $templateRepo;
    private ProdutoRepository $produtoRepo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->regraRepo = new RegraRepository($pdo);
        $this->templateRepo = new TemplateRepository($pdo);
        $this->produtoRepo = new ProdutoRepository($pdo);
    }

    public function gerarZplParaItemLote(int $itemId, string $itemType, ?int $clienteId): ?array
    {

        // 1. Buscar os dados do item com base no seu tipo.
        $dados = null;
        if ($itemType === 'producao') {
            $dados = $this->findDadosItemProducao($itemId, $clienteId);
        } elseif ($itemType === 'embalagem') {
            $dados = $this->findDadosItemEmbalagem($itemId, null);
        }

        // --- LOG 2: VERIFICAÇÃO DE DADOS ---
        if (!$dados) {
            throw new Exception("Dados para a etiqueta do item ID {$itemId} (Tipo: {$itemType}) não foram encontrados.");
        }

        // Agora, usamos o ID do cliente que foi encontrado na consulta.
        $clienteParaRegra = $dados['lote_cliente_id'] ?? $clienteId;

        // 2. Usar o RegraRepository para descobrir qual template usar.
        $templateId = $this->regraRepo->findTemplateIdByRule($dados['prod_codigo'], $clienteParaRegra);

        // --- LOG 3: VERIFICAÇÃO DE REGRA ---
        if ($templateId === null) {
            throw new Exception("Nenhuma regra de etiqueta aplicável foi encontrada para esta combinação de produto e cliente.");
        }

        // 3. Buscar o conteúdo ZPL do template encontrado.
        $template = $this->templateRepo->find($templateId);
        if (!$template || empty($template['template_conteudo_zpl'])) {
            throw new Exception("O template de etiqueta (ID: {$templateId}) definido pela regra não foi encontrado ou está vazio.");
        }

        // 4. LÓGICA PARA SUBSTITUIR OS PLACEHOLDERS.
        $zplFinal = $this->substituirPlaceholders($template['template_conteudo_zpl'], $dados);

        // 5. Gerar um nome de arquivo.
        $nomeArquivo = sprintf(
            'etiqueta_%s_%s.pdf',
            preg_replace('/[^a-zA-Z0-9_-]/', '', $dados['prod_codigo_interno'] ?? 'produto'),
            preg_replace('/[^a-zA-Z0-9_-]/', '', $dados['lote_num_completo'] ?? 'lote')
        );

        return ['zpl' => $zplFinal, 'filename' => $nomeArquivo];
    }

    /**
     * FUNÇÃO DE BUSCA: Busca dados para etiqueta de um item de PRODUÇÃO.
     */
    private function findDadosItemProducao(int $itemProdId, ?int $clienteId): ?array
    {
        $sql = "SELECT 
                    p.*,
                    lnh.lote_completo_calculado as lote_num_completo,
                    lnh.lote_data_fabricacao,
                    lnp.item_prod_data_validade as lote_item_data_val,
                    lnp.item_prod_quantidade as lote_item_qtd,
                    c.ent_razao_social, c.ent_cnpj, c.ent_inscricao_estadual, c.ent_nome_fantasia,
                    end.end_logradouro, end.end_numero, end.end_complemento, end.end_bairro, end.end_cidade, end.end_uf, end.end_cep
                FROM tbl_lotes_novo_producao lnp
                JOIN tbl_lotes_novo_header lnh ON lnp.item_prod_lote_id = lnh.lote_id
                JOIN tbl_produtos p ON lnp.item_prod_produto_id = p.prod_codigo
                LEFT JOIN tbl_entidades c ON c.ent_codigo = :cliente_id
                LEFT JOIN (
                    SELECT *, ROW_NUMBER() OVER(PARTITION BY end_entidade_id ORDER BY CASE end_tipo_endereco WHEN 'Principal' THEN 1 ELSE 2 END) as rn 
                    FROM tbl_enderecos
                ) end ON c.ent_codigo = end.end_entidade_id AND end.rn = 1
                WHERE lnp.item_prod_id = :item_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':item_id' => $itemProdId, ':cliente_id' => $clienteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * FUNÇÃO DE BUSCA: Busca dados para etiqueta de um item de EMBALAGEM.
     */
    private function findDadosItemEmbalagem(int $itemEmbId, ?int $clienteId): ?array
    {
        $sql = "SELECT
                p.*,
                lnh.lote_completo_calculado as lote_num_completo,
                lnh.lote_data_fabricacao,
                lne.item_emb_qtd_sec as lote_item_qtd,
                
                -- Busca os dados do cliente do Lote de Destino, se houver
                lnh.lote_cliente_id,
                c.ent_razao_social, c.ent_cnpj, c.ent_inscricao_estadual, c.ent_nome_fantasia,
                end.end_logradouro, end.end_numero, end.end_complemento, end.end_bairro, end.end_cidade, end.end_uf, end.end_cep,

                -- Lógica para a data de validade
                IF(lne.item_emb_prod_prim_id IS NULL, lnh.lote_data_fabricacao, lnp.item_prod_data_validade) as lote_item_data_val
                
            FROM tbl_lotes_novo_embalagem lne
            JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
            JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
            -- LEFT JOIN para lidar com caixas mistas (item_emb_prod_prim_id será NULL)
            LEFT JOIN tbl_lotes_novo_producao lnp ON lne.item_emb_prod_prim_id = lnp.item_prod_id
            -- Agora, buscamos o cliente usando o ID do lote de destino
            LEFT JOIN tbl_entidades c ON c.ent_codigo = lnh.lote_cliente_id
            LEFT JOIN (
                SELECT *, ROW_NUMBER() OVER(PARTITION BY end_entidade_id ORDER BY CASE end_tipo_endereco WHEN 'Principal' THEN 1 ELSE 2 END) as rn
                FROM tbl_enderecos
            ) end ON c.ent_codigo = end.end_entidade_id AND end.rn = 1
            WHERE lne.item_emb_id = :item_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':item_id' => $itemEmbId]); // Não precisamos mais do :cliente_id aqui
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function substituirPlaceholders(string $zpl, array $dados): string
    {
        if (strpos($zpl, '^CI28') === false) {
            $zpl = str_replace('^XA', '^XA^CI28', $zpl);
        }

        // --- LÓGICA DE FONTE DINÂMICA ---
        $nomeProduto = $dados['prod_descricao'] ?? '';
        $tamanhoNome = strlen($nomeProduto);
        if ($tamanhoNome <= 30)
            $comandoFonte = '^A0N,40,40';
        elseif ($tamanhoNome <= 45)
            $comandoFonte = '^A0N,32,32';
        else
            $comandoFonte = '^A0N,28,28';

        // --- LÓGICA DE CÓDIGOS DE BARRAS (EAN vs DUN) ---
        $tipoEmbalagem = $dados['prod_tipo_embalagem'] ?? 'INDEFINIDO';
        $eanDb = $dados['prod_ean13'] ?? '';
        $dunDb = $dados['prod_dun14'] ?? '';

        // Seleção Lógica
        if ($tipoEmbalagem === 'SECUNDARIA') {
            $codBarraBruto = $dunDb;
        } else {
            $codBarraBruto = $eanDb;
        }

        // Higienização
        $dadosBarras1D = trim((string) $codBarraBruto);
        if (empty($dadosBarras1D)) {
            $dadosBarras1D = '00000000000000'; // Valor padrão para evitar erro visual
        }

        // Gera a string de dados GS1 para o QR Code
        $dadosQrCode = $this->buildGs1DataString($dados, $dadosBarras1D);

        // --- LÓGICA DE CAMPOS COMPOSTOS ---
        /*  $partesClasse = array_filter([$dados['prod_classificacao'], $dados['prod_classe']]);
          $linhaProdutoClasse = implode(' ', $partesClasse);*/

        // Removemos a barra '/' da classificação caso ela exista
        $classificacaoLimpa = isset($dados['prod_classificacao'])
            ? str_replace('/', '', $dados['prod_classificacao'])
            : '';

        // Montamos o array usando a variável limpa
        $partesClasse = array_filter([$classificacaoLimpa, $dados['prod_classe']]);
        $linhaProdutoClasse = implode(' ', $partesClasse);

        $linhaEspecieOrigem = "Espécie: " . ($dados['prod_especie'] ?? '') . "     Origem: " . ($dados['prod_origem'] ?? '');
        $nomeEspecie = "Espécie: " . ($dados['prod_especie'] ?? '');
        $nomeOrigem = "Origem: " . ($dados['prod_origem'] ?? '');

        $linhaClassificacao = "CLASSIFICAÇÃO: " . $this->buildClassificationLine($dados);
        $linhaDescricao = "CLASSIFICAÇÃO: " . $this->construirLinhaClassificacao($dados);
        $linhaConteudo = $this->construirFraseConteudo($dados);

        $linhaLote = "LOTE: " . ($dados['lote_num_completo'] ?? '');
        $codigoInternoProduto = "COD.: " . ($dados['prod_codigo_interno'] ?? '');

        $dataFab = isset($dados['lote_data_fabricacao']) ? date('d/m/Y', strtotime($dados['lote_data_fabricacao'])) : '';
        $dataVal = isset($dados['lote_item_data_val']) ? date('d/m/Y', strtotime($dados['lote_item_data_val'])) : '';
        $linhaFabEValidade = "FAB.: {$dataFab}        VAL.: {$dataVal}";

        $pesoFormatado = str_replace('.', ',', (string) ((float) ($dados['prod_peso_embalagem'] ?? 0)));
        $linhaPesoLiquido = "PESO LÍQUIDO: " . $pesoFormatado . "kg";

        $partesEndereco = array_filter([($dados['end_logradouro'] ?? '') . ', ' . ($dados['end_numero'] ?? ''), $dados['end_complemento'] ?? '', $dados['end_bairro'] ?? '']);
        $linhaEndereco = implode(' - ', $partesEndereco);

        $linhaCidadeUfCep = ($dados['end_cidade'] ?? '') . ' / ' . ($dados['end_uf'] ?? '') . '     CEP: ' . ($dados['end_cep'] ?? '');
        $linhaCnpjIe = "CNPJ: " . $this->formatCnpj($dados['ent_cnpj'] ?? '') . "     I.E.: " . ($dados['ent_inscricao_estadual'] ?? '');

        // --- MAPA COMPLETO DE PLACEHOLDERS ---
        $placeholders = [
            // Simples
            'nomeProduto' => $nomeProduto,
            '{produto_cod_interno}' => $dados['prod_codigo'] ?? '',
            'codigoProduto' => $codigoInternoProduto,
            'numeroLote' => $dados['lote_num_completo'] ?? '',
            'cliente_nome' => $dados['ent_razao_social'] ?? '',
            'nomeCliente' => $dados['ent_razao_social'] ?? '',
            'nomeFantasia' => $dados['ent_nome_fantasia'] ?? '',
            '{data_fabricacao}' => $dataFab,
            '{data_validade}' => $dataVal,
            'fabricacaoLote' => $dataFab,
            'validadeLote' => $dataVal,
            '{quantidade}' => $dados['lote_item_qtd'] ?? '',
            '{categoria}' => $dados['prod_categoria'] ?? '',

            // Compostos e de Negócio
            '{fonte_produto_nome}' => $comandoFonte,
            'linhaProduto' => $linhaProdutoClasse,
            'linhaEspecie' => $linhaEspecieOrigem,
            'nomeEspecie' => $nomeEspecie,
            'nomeOrigem' => $nomeOrigem,
            'linhaClassificacao' => $linhaClassificacao,
            'linhaDescricao' => $linhaDescricao,
            'linhaPecas' => $linhaConteudo,
            '{linha_lote_completo}' => $linhaLote,
            'linhaLote' => $linhaLote,
            'linhaDatas' => $linhaFabEValidade,
            'linhaPeso' => $linhaPesoLiquido,
            '{linha_cliente_endereco}' => $linhaEndereco,
            'enderecoCliente' => $linhaEndereco,
            '{linha_cliente_cidade_uf_cep}' => $linhaCidadeUfCep,
            'cidadeCliente' => $linhaCidadeUfCep,
            '{linha_cliente_cnpj_ie}' => $linhaCnpjIe,
            'cnpjCliente' => $linhaCnpjIe,

            // Códigos de Barras
            '12345678' => $dadosBarras1D ?? '',
            // O '10qrcode' será tratado abaixo com lógica especial de posicionamento
        ];

        // 1. Aplica substituições normais primeiro
        $zpl = str_replace(array_keys($placeholders), array_values($placeholders), $zpl);

        // 2. CORREÇÃO DE POSICIONAMENTO DO QR CODE (Troca FT por FO)
        // Procura a linha que define o QR Code (que contém o placeholder '10qrcode') e usa comando ^FT
        if (preg_match('/\^FT(\d+),(\d+)(.*?)10qrcode/', $zpl, $matches)) {
            $x = $matches[1];
            $y = $matches[2];
            $meio_do_comando = $matches[3]; // Pega o trecho entre a coordenada e o placeholder (ex: ^BQN,2,10^FH\^FDLA,>)
            $string_original = $matches[0];

            // Nova string: ^FO[X],[Y_CALCULADO]...10qrcode
            $string_nova = "^FO{$x},{$y}{$meio_do_comando}10qrcode";

            // Substitui no arquivo ZPL apenas essa ocorrência
            $zpl = str_replace($string_original, $string_nova, $zpl);
        }

        // 3. Finalmente, injeta os dados do QR Code no placeholder (agora posicionado corretamente)
        $zpl = str_replace('10qrcode', $dadosQrCode, $zpl);

        return $zpl;
    }

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
        $pesoParaFormatar = 0;

        // --- ETAPA 1: Adiciona a Classificação/Peças (Comum a ambos) ---
        if (!empty($produto['prod_classificacao'])) {
            $partes[] = $produto['prod_classificacao'];
        }
        if (!empty($produto['prod_total_pecas'])) {
            $partes[] = $produto['prod_total_pecas'];
        }

        // --- ETAPA 2: Encontra o Peso Correto ---
        if ($produto['prod_tipo_embalagem'] === 'SECUNDARIA' && !empty($produto['prod_primario_id'])) {
            // Se for SECUNDÁRIA, busca o peso do PRIMÁRIO
            $produtoPrimario = $this->produtoRepo->find($produto['prod_primario_id']);
            if ($produtoPrimario) {
                $pesoParaFormatar = (float) ($produtoPrimario['prod_peso_embalagem'] ?? 0);
            }
        } else if ($produto['prod_tipo_embalagem'] === 'PRIMARIA') {
            // Se for PRIMÁRIA, usa o seu PRÓPRIO peso
            $pesoParaFormatar = (float) ($produto['prod_peso_embalagem'] ?? 0);
        }

        // --- ETAPA 3: Formata e Adiciona o Peso (se encontrado) ---
        if ($pesoParaFormatar > 0) {
            $pesoFormatado = '';
            $sufixo = '';

            if ($pesoParaFormatar < 1) {
                // Lógica para gramas (< 1kg)
                // Multiplica por 1000 (ex.: 0.300 vira 300)
                $valorGramas = $pesoParaFormatar * 1000;

                // Convertemos para float e depois string para remover zeros decimais  desnecessários (300.0 vira 300)
                $pesoFormatado = (string) ((float) $valorGramas);
                $sufixo = 'g';
            } else {
                // Lógica para quilos (>=1kg)
                // Mantém formatação original com vírgula
                $pesoFormatado = str_replace('.', ',', (string) $pesoParaFormatar);
                $sufixo = 'kg';
            }
            $partes[] = "UNIDADES/" . $pesoFormatado . $sufixo;
        }

        return implode(' ', $partes);
    }

    private function construirLinhaClassificacao(array $produto): string
    {
        $partes = [];
        $pesoParaFormatar = 0;

        // --- ETAPA 1: Adiciona Peças (Comum a ambos) ---
        if (!empty($produto['prod_total_pecas'])) {
            $partes[] = $produto['prod_total_pecas'];
        }

        // --- ETAPA 2: Encontra o Peso Correto ---
        if ($produto['prod_tipo_embalagem'] === 'SECUNDARIA' && !empty($produto['prod_primario_id'])) {
            // Se for SECUNDÁRIA, busca o peso do PRIMÁRIO
            $produtoPrimario = $this->produtoRepo->find($produto['prod_primario_id']);
            if ($produtoPrimario) {
                $pesoParaFormatar = (float) ($produtoPrimario['prod_peso_embalagem'] ?? 0);
            }
        } else if ($produto['prod_tipo_embalagem'] === 'PRIMARIA') {
            // Se for PRIMÁRIA, usa o seu PRÓPRIO peso
            $pesoParaFormatar = (float) ($produto['prod_peso_embalagem'] ?? 0);
        }

        // --- ETAPA 3: Formata e Adiciona o Peso (se encontrado) ---
        if ($pesoParaFormatar > 0) {
            $pesoFormatado = '';
            $sufixo = '';

            if ($pesoParaFormatar < 1) {
                // Lógica para gramas (< 1kg)
                // Multiplica por 1000 (ex.: 0.300 vira 300)
                $valorGramas = $pesoParaFormatar * 1000;

                // Convertemos para float e depois string para remover zeros decimais  desnecessários (300.0 vira 300)
                $pesoFormatado = (string) ((float) $valorGramas);
                $sufixo = 'g';
            } else {
                // Lógica para quilos (>=1kg)
                // Mantém formatação original com vírgula
                $pesoFormatado = str_replace('.', ',', (string) $pesoParaFormatar);
                $sufixo = 'kg';
            }
            $partes[] = "UNIDADES/" . $pesoFormatado . $sufixo;
        }

        return implode(' ', $partes);
    }

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

    private function construirFraseConteudo(array $produto): string
    {
        // 1. Pega as peças (Ex: "450 A 550") vindo do banco
        $pecas = $produto['prod_total_pecas'] ?? '';

        // Se não tiver informação de peças, retorna vazio para não quebrar o layout?
        // Ou retorna um texto padrão. Vou assumir que se não tiver peças, não mostra a frase.
        if (empty($pecas)) {
            return '';
        }

        // 2. Lógica do Peso (Reaproveitada para garantir o valor correto)
        $pesoParaFormatar = 0;
        if (($produto['prod_tipo_embalagem'] ?? '') === 'SECUNDARIA' && !empty($produto['prod_primario_id'])) {
            $produtoPrimario = $this->produtoRepo->find($produto['prod_primario_id']);
            if ($produtoPrimario) {
                $pesoParaFormatar = (float) ($produtoPrimario['prod_peso_embalagem'] ?? 0);
            }
        } else if (($produto['prod_tipo_embalagem'] ?? '') === 'PRIMARIA') {
            $pesoParaFormatar = (float) ($produto['prod_peso_embalagem'] ?? 0);
        }

        // 3. Formatação do Peso (g ou kg)
        $textoPeso = '';
        if ($pesoParaFormatar > 0) {
            if ($pesoParaFormatar < 1) {
                $valorGramas = $pesoParaFormatar * 1000;
                $pesoFormatado = (string) ((float) $valorGramas);
                $textoPeso = $pesoFormatado . 'g';
            } else {
                $pesoFormatado = str_replace('.', ',', (string) $pesoParaFormatar);
                $textoPeso = $pesoFormatado . 'kg';
            }
        }

        // 4. Montagem da Frase
        // Resultado esperado: "ESTA EMBALAGEM CONTÉM ENTRE 450 A 550 PEÇAS/5kg"

        $frase = "ESTA EMBALAGEM CONTÉM ENTRE {$pecas} PEÇAS";

        if (!empty($textoPeso)) {
            $frase .= "/" . $textoPeso;
        }

        return $frase;
    }
}
