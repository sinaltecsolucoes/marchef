<?php
// Ficheiro: src/Estoque/EstoqueRepository.php

namespace App\Estoque;

use PDO;
use DateTime;
use Exception;
use App\Core\AuditLoggerService;
use App\Estoque\MovimentoRepository;

class EstoqueRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;
    private MovimentoRepository $movimentoRepo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
        $this->movimentoRepo = new MovimentoRepository($pdo);
    }

    /**
     * @doc: Aloca um item em um endereço de estoque.
     * Se já existir uma alocação para o mesmo item no mesmo endereço, a quantidade é somada.
     * Caso contrário, um novo registo de alocação é criado.
     * @param int $enderecoId O ID do endereço de destino.
     * @param int $loteItemId O ID do item do lote (lote_item_id) vindo da validação.
     * @param int $usuarioId O ID do usuário que está a realizar a operação.
     * @param float $quantidade A quantidade a ser alocada (normalmente 1 para cada leitura).
     * @return int O ID da alocação (seja ela nova ou atualizada).
     */
    /* public function alocarItem(int $enderecoId, int $loteItemId, int $usuarioId, float $quantidade = 1.0): int
    {
        // Inicia uma transação para garantir a consistência dos dados
        $this->pdo->beginTransaction();

        try {
            // Passo 1: Verifica se já existe uma alocação para ESTE item NESTE endereço.
            $stmtCheck = $this->pdo->prepare(
                "SELECT alocacao_id, alocacao_quantidade FROM tbl_estoque_alocacoes 
             WHERE alocacao_lote_item_id = :lote_item_id AND alocacao_endereco_id = :endereco_id"
            );
            $stmtCheck->execute([
                ':lote_item_id' => $loteItemId,
                ':endereco_id' => $enderecoId
            ]);
            $alocacaoExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($alocacaoExistente) {
                // Passo 2a: Se EXISTE, faz o UPDATE para somar a quantidade.
                $novaQuantidade = $alocacaoExistente['alocacao_quantidade'] + $quantidade;
                $alocacaoId = $alocacaoExistente['alocacao_id'];

                $stmtUpdate = $this->pdo->prepare(
                    "UPDATE tbl_estoque_alocacoes SET alocacao_quantidade = :quantidade WHERE alocacao_id = :id"
                );
                $stmtUpdate->execute([
                    ':quantidade' => $novaQuantidade,
                    ':id' => $alocacaoId
                ]);

                $idRetorno = $alocacaoId;

            } else {
                // Passo 2b: Se NÃO EXISTE, faz o INSERT de um novo registo.
                $sql = "
                INSERT INTO tbl_estoque_alocacoes 
                    (alocacao_endereco_id, alocacao_lote_item_id, alocacao_quantidade, alocacao_data, alocacao_usuario_id)
                VALUES 
                    (:endereco_id, :lote_item_id, :quantidade, NOW(), :usuario_id)
            ";

                $stmtInsert = $this->pdo->prepare($sql);
                $stmtInsert->execute([
                    ':endereco_id' => $enderecoId,
                    ':lote_item_id' => $loteItemId,
                    ':quantidade' => $quantidade,
                    ':usuario_id' => $usuarioId
                ]);

                $idRetorno = (int) $this->pdo->lastInsertId();
            }

            // Se tudo correu bem, confirma a transação
            $this->pdo->commit();

            return $idRetorno;

        } catch (Exception $e) {
            // Em caso de erro, desfaz a transação
            $this->pdo->rollBack();
            // Relança a exceção para ser tratada pela API
            throw new Exception("Ocorreu um erro no banco de dados ao tentar alocar o item: " . $e->getMessage());
        }
    }*/

    /**
     * @doc: Aloca um item em um endereço de estoque.
     * (Versão compatível com Transações Aninhadas)
     */
    public function alocarItem(int $enderecoId, int $loteItemId, int $usuarioId, float $quantidade = 1.0): int
    {
        // 1. VERIFICA SE JÁ EXISTE UMA TRANSAÇÃO EM ABERTO (vinda do Importar Lote)
        $transacaoExterna = $this->pdo->inTransaction();

        // Só abre transação nova se NÃO existir uma externa
        if (!$transacaoExterna) {
            $this->pdo->beginTransaction();
        }

        try {
            // Passo 1: Verifica se já existe uma alocação
            $stmtCheck = $this->pdo->prepare(
                "SELECT alocacao_id, alocacao_quantidade FROM tbl_estoque_alocacoes 
                 WHERE alocacao_lote_item_id = :lote_item_id AND alocacao_endereco_id = :endereco_id"
            );
            $stmtCheck->execute([
                ':lote_item_id' => $loteItemId,
                ':endereco_id' => $enderecoId
            ]);
            $alocacaoExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($alocacaoExistente) {
                // Passo 2a: UPDATE
                $novaQuantidade = $alocacaoExistente['alocacao_quantidade'] + $quantidade;
                $alocacaoId = $alocacaoExistente['alocacao_id'];

                $stmtUpdate = $this->pdo->prepare(
                    "UPDATE tbl_estoque_alocacoes SET alocacao_quantidade = :quantidade WHERE alocacao_id = :id"
                );
                $stmtUpdate->execute([
                    ':quantidade' => $novaQuantidade,
                    ':id' => $alocacaoId
                ]);

                $idRetorno = $alocacaoId;
            } else {
                // Passo 2b: INSERT
                $sql = "INSERT INTO tbl_estoque_alocacoes 
                        (alocacao_endereco_id, alocacao_lote_item_id, alocacao_quantidade, alocacao_data, alocacao_usuario_id)
                        VALUES 
                        (:endereco_id, :lote_item_id, :quantidade, NOW(), :usuario_id)";

                $stmtInsert = $this->pdo->prepare($sql);
                $stmtInsert->execute([
                    ':endereco_id' => $enderecoId,
                    ':lote_item_id' => $loteItemId,
                    ':quantidade' => $quantidade,
                    ':usuario_id' => $usuarioId
                ]);

                $idRetorno = (int) $this->pdo->lastInsertId();
            }

            // 2. COMMIT CONDICIONAL
            // Só damos commit se fomos nós que abrimos a transação.
            // Se veio do 'importarLoteLegado', deixamos o pai dar o commit final.
            if (!$transacaoExterna) {
                $this->pdo->commit();
            }

            return $idRetorno;
        } catch (Exception $e) {
            // 3. ROLLBACK CONDICIONAL
            // Só damos rollback se fomos nós que abrimos.
            if (!$transacaoExterna && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Relança a exceção para o pai saber que deu erro
            throw new Exception("Erro ao alocar item: " . $e->getMessage());
        }
    }

    /**
     * @doc: Exclui um registo de alocação de estoque com base no seu ID.
     * @param int $alocacaoId O ID do registo a ser excluído.
     * @return bool Retorna true se a exclusão for bem-sucedida.
     */
    public function excluirAlocacao(int $alocacaoId): bool
    {
        $sql = "DELETE FROM tbl_estoque_alocacoes WHERE alocacao_id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $alocacaoId]);
    }

    /**
     * @doc: Atualiza a quantidade de um registo de alocação de estoque.
     * @param int $alocacaoId O ID do registo a ser atualizado.
     * @param float $novaQuantidade A nova quantidade a ser definida.
     * @return bool Retorna true se a atualização for bem-sucedida.
     */
    public function editarQuantidade(int $alocacaoId, float $novaQuantidade): bool
    {
        // Regra de negócio: não permitir quantidade zero ou negativa. Se for o caso, exclui.
        if ($novaQuantidade <= 0) {
            return $this->excluirAlocacao($alocacaoId);
        }

        $sql = "UPDATE tbl_estoque_alocacoes SET alocacao_quantidade = :quantidade WHERE alocacao_id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':quantidade' => $novaQuantidade,
            ':id' => $alocacaoId
        ]);
    }

    /**
     * @doc: Busca todas as alocações de entrada feitas hoje para um endereço específico.
     * @param int $enderecoId O ID do endereço para filtrar os resultados.
     * @return array Uma lista de alocações com detalhes do produto e lote.
     */
    public function findEntradasDoDiaPorEndereco(int $enderecoId): array
    {
        $sql = "
        SELECT
            ea.alocacao_id,
            p.prod_descricao AS produto,
            lnh.lote_completo_calculado AS lote,
            ea.alocacao_quantidade AS quantidade
        FROM
            tbl_estoque_alocacoes ea
        JOIN
            tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
        JOIN
            tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
        JOIN
            tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
        WHERE
            ea.alocacao_endereco_id = :endereco_id
            AND DATE(ea.alocacao_data) = CURDATE()
        ORDER BY
            ea.alocacao_data DESC
    ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':endereco_id' => $enderecoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

   /* public function processarInventarioCSV($caminhoArquivo, $usuarioId, $somenteValidar = true)
    {
        $sucessos = 0;
        $falhas = [];
        $previa = [];
        $mapaLotes = [];

        if (($handle = fopen($caminhoArquivo, "r")) !== FALSE) {
            fgetcsv($handle, 0, ";"); // Pula cabeçalho

            while (($linha = fgetcsv($handle, 0, ";")) !== FALSE) {
                if (empty($linha[0]) || count($linha) < 7) continue;

                // Validação Básica de Existência (Antes de agrupar)
                $loteTxt = trim($linha[0]);
                $dataFab = trim($linha[1]);
                $fantasia = trim($linha[2]);
                $codInterno = trim($linha[3]);
                $qtdSec = (float)str_replace(',', '.', $linha[5]);
                $nomeEnd = trim($linha[6]);

                $idEntidade = $this->buscarIdPorNomeFantasia($fantasia);
                $infoProd = $this->buscarInfoProduto($codInterno);
                $idEnd = $this->buscarIdEndereco($nomeEnd);

                if (!$idEntidade || !$infoProd || !$idEnd) {
                    $erroMsg = !$idEntidade ? "Fornecedor não encontrado." : (!$infoProd ? "Produto não encontrado." : "Endereço inexistente.");
                    $falhas[] = ['lote' => $linha[0], 'erro' => $erroMsg];
                }

                $mapaLotes[$loteTxt]['info'] = ['data' => $dataFab, 'fantasia' => $fantasia];

                // Soma as quantidades por produto e endereço
                $mapaLotes[$loteTxt]['produtos'][$codInterno]['enderecos'][$nomeEnd] =
                    ($mapaLotes[$loteTxt]['produtos'][$codInterno]['enderecos'][$nomeEnd] ?? 0) + $qtdSec;
            }
            fclose($handle);

            // Se for apenas validação, paramos aqui e retornamos o status
            if ($somenteValidar) {
                return ['status' => 'validacao', 'pode_processar' => empty($falhas), 'falhas' => $falhas, 'total_lotes' => count($mapaLotes)];
            }

            foreach ($mapaLotes as $loteCompleto => $dadosLote) {

                // Verifica se a chave info existe antes de usar
                if (!isset($dadosLote['info'])) {
                    error_log("Aviso: Lote $loteCompleto ignorado por falta de dados de cabeçalho.");
                    continue;
                }

                try {
                    // Verificação de segurança: Só inicia se não houver transação aberta
                    if (!$this->pdo->inTransaction()) {
                        $this->pdo->beginTransaction();
                    }

                    $idEntidade = $this->buscarIdPorNomeFantasia($dadosLote['info']['fantasia']);
                    if (!$idEntidade) throw new Exception("Fornecedor '{$dadosLote['info']['fantasia']}' não cadastrado.");

                    $numLote = (int)preg_replace('/[^0-9]/', '', explode('/', $loteCompleto)[0]);
                    $dataSql = $this->formatarDataSQL($dadosLote['info']['data']);

                    // 1. HEADER (Se já existir, pegamos o ID, senão criamos)
                    $loteId = $this->buscarLoteIdExistente($loteCompleto);
                    if (!$loteId) {
                        $sqlH = "INSERT INTO tbl_lotes_novo_header (lote_numero, lote_data_fabricacao, lote_cliente_id, lote_completo_calculado, lote_status, lote_usuario_id, lote_data_finalizacao) 
                             VALUES (?, ?, ?, ?, 'LOTE_LEGADO', ?, NOW())";
                        $this->pdo->prepare($sqlH)->execute([$numLote, $dataSql, $idEntidade, $loteCompleto,  $usuarioId]);
                        $loteId = $this->pdo->lastInsertId();
                    }

                    foreach ($dadosLote['produtos'] as $codInterno => $dadosProduto) {
                        $infoProd = $this->buscarInfoProduto($codInterno);
                        if (!$infoProd) throw new Exception("Cód. Interno '{$codInterno}' não encontrado ou não é Secundário.");

                        $qtdTotalDoProduto = array_sum($dadosProduto['enderecos']);
                        $pesoTotal = $qtdTotalDoProduto * (float)$infoProd['prod_peso_embalagem'];

                        // 2. PRODUÇÃO E EMBALAGEM (Evitar duplicar itens dentro do mesmo lote)

                        $mesesValidade = (int)($infoProd['prod_validade_meses'] ?? 0);
                        $dataValidade = $this->calcularValidadeArredondandoParaCima($dataSql, $mesesValidade);

                        $prodId = $this->verificarItemProducaoExistente($loteId, $infoProd['prod_primario_id']);
                        if (!$prodId) {
                            $sqlP = "INSERT INTO tbl_lotes_novo_producao (item_prod_lote_id, item_prod_produto_id, item_prod_quantidade, item_prod_saldo, item_prod_data_validade) VALUES (?, ?, ?, ?, ?)";
                            $this->pdo->prepare($sqlP)->execute([$loteId, $infoProd['prod_primario_id'], $pesoTotal, 0,$dataValidade]);
                            $prodId = $this->pdo->lastInsertId();
                        }

                        $embId = $this->verificarItemEmbalagemExistente($loteId, $infoProd['prod_codigo']);
                        if (!$embId) {
                            $sqlE = "INSERT INTO tbl_lotes_novo_embalagem (item_emb_lote_id, item_emb_prod_sec_id, item_emb_prod_prim_id, item_emb_qtd_sec, item_emb_qtd_finalizada, item_emb_data_cadastro) VALUES (?, ?, ?, ?, ?, NOW())";
                            $this->pdo->prepare($sqlE)->execute([$loteId, $infoProd['prod_codigo'], $prodId, $qtdTotalDoProduto, $qtdTotalDoProduto]);
                            $embId = $this->pdo->lastInsertId();
                        }

                        // 3. ESTOQUE (Endereçamento)
                        foreach ($dadosProduto['enderecos'] as $nomeEndereco => $qtdNoEndereco) {
                            $idEnd = $this->buscarIdEndereco($nomeEndereco);

                            if (!$idEnd) throw new Exception("Endereço '{$nomeEndereco}' não localizado.");

                            // Antes de inserir a alocação, verifique se já existe
                            $stmtCheck = $this->pdo->prepare(
                                "SELECT COUNT(*) FROM tbl_estoque_alocacoes 
                                        WHERE alocacao_endereco_id = ? AND alocacao_lote_item_id = ?"
                            );
                            $stmtCheck->execute([$idEnd, $embId]);
                            if ($stmtCheck->fetchColumn() > 0) {
                                continue; // Já existe alocação para este item neste endereço, pula
                            }

                            $sqlEst = "INSERT INTO tbl_estoque_alocacoes (alocacao_endereco_id, alocacao_lote_item_id, alocacao_usuario_id, alocacao_quantidade, alocacao_data) VALUES (?, ?, ?, ?, NOW())";
                            $this->pdo->prepare($sqlEst)->execute([$idEnd, $embId, $usuarioId, $qtdNoEndereco]);

                            // REGISTRO NO KARDEX (Método que você enviou)
                            $this->movimentoRepo->registrar(
                                'ENTRADA_INVENTARIO',
                                $embId,
                                $qtdNoEndereco,
                                $usuarioId,
                                null,
                                $idEnd,
                                "Carga Inicial via Inventário CSV"
                            );
                        }
                    }

                    // LOG DE AUDITORIA (Método que você enviou)
                    $this->auditLogger->log(
                        'CREATE',
                        $loteId,
                        'tbl_lotes_novo_header',
                        null,
                        ['lote' => $loteCompleto],
                        "Inventário via CSV processado."
                    );

                    $this->pdo->commit();
                    $sucessos++;
                } catch (Exception $e) {
                    if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                    $falhas[] = ['lote' => $loteCompleto, 'erro' => $e->getMessage()];
                }
            }
        }
        return ['status' => 'sucesso', 'sucessos' => $sucessos, 'falhas' => $falhas];
    } */


    /** 
     * Função auxiliar para evitar duplicar o lote se ele já estiver no banco
     */
    public function processarInventarioCSV($caminhoArquivo, $usuarioId, $somenteValidar = true)
    {
        set_time_limit(0);
        $sucessos = 0;
        $falhas = [];
        $previa = [];
        $mapaLotes = [];

        if (($handle = fopen($caminhoArquivo, "r")) !== FALSE) {
            // 1. Detectar o delimitador (alguns Excel usam ; outros ,)
            $primeiraLinha = fgets($handle);
            $delimitador = (strpos($primeiraLinha, ';') !== false) ? ';' : ',';
            rewind($handle); // Volta para o início

            fgetcsv($handle, 0, $delimitador); // Pula cabeçalho

            while (($linhaRaw = fgetcsv($handle, 0, $delimitador)) !== FALSE) {
                // 2. Proteção contra linhas vazias no final do arquivo
                if (empty($linhaRaw) || !isset($linhaRaw[0]) || trim($linhaRaw[0]) == '') continue;
                if (count($linhaRaw) < 7) continue;

                // 3. Converter de ANSI para UTF-8 (Resolve o problema do formato 2 do Excel)
                $linha = array_map(function ($campo) {
                    $campoUtf8 = mb_convert_encoding($campo, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
                    return trim($campoUtf8);
                }, $linhaRaw);

                try {

                    $loteTxt = trim($linha[0]);
                    $dataFab = trim($linha[1]);
                    $fantasia = trim($linha[2]);
                    $codInterno = trim($linha[3]);
                    $descricao = trim($linha[4]); // Pegamos a descrição para a prévia
                    $qtdSec = (float)str_replace(',', '.', $linha[5]);
                    $nomeEnd = trim($linha[6]);

                    $idEntidade = $this->buscarIdPorNomeFantasia($fantasia);
                    $infoProd = $this->buscarInfoProduto($codInterno);
                    $idEnd = $this->buscarIdEndereco($nomeEnd);

                    if (!$idEntidade || !$infoProd || !$idEnd) {
                        $erroMsg = !$idEntidade ? "Entidade '{$fantasia}' não encontrada." : (!$infoProd ? "Produto '{$codInterno}' não encontrado." : "Endereço '{$nomeEnd}' inexistente.");
                        $falhas[] = ['lote' => $loteTxt, 'erro' => $erroMsg];
                        continue;
                    }

                    // Agrupamento para processamento
                    $mapaLotes[$loteTxt]['info'] = ['data' => $dataFab, 'fantasia' => $fantasia];
                    $mapaLotes[$loteTxt]['produtos'][$codInterno]['enderecos'][$nomeEnd] =
                        ($mapaLotes[$loteTxt]['produtos'][$codInterno]['enderecos'][$nomeEnd] ?? 0) + $qtdSec;

                    // Se for validação, alimentamos a prévia para o JS
                    //  if ($somenteValidar && $infoProd) {
                    if ($somenteValidar) {
                        // 1. Transformamos a data do CSV (BR) em SQL para o cálculo
                        $dataSql = $this->formatarDataSQL($dataFab);
                        $mesesVal = (int)($infoProd['prod_validade_meses'] ?? 0);
                        $validadeCalculada = $this->calcularValidadeArredondandoParaCima($dataSql, $mesesVal);

                        // 3. Criamos o objeto de data para formatar a exibição na prévia
                        $dtExibicao = new DateTime($validadeCalculada);

                        $previa[] = [
                            'lote' => $loteTxt,
                            'produto' => $codInterno . " - " . $descricao,
                            'fabricacao' => $dataFab,
                            'validade' => ($dtExibicao)->format('d/m/Y'),
                            'quantidade' => $qtdSec . " cx"
                        ];
                    }
                } catch (Exception $e) {
                    // Se a data falhar, não deixa o PHP morrer, registra o erro na prévia
                    // $falhas[] = ['lote' => $loteTxt, 'erro' => "Erro no cálculo de data: " . $e->getMessage()];
                    // Captura qualquer erro de sistema (Throwable captura Erros e Exceptions)
                    $falhas[] = ['lote' => $linha[0] ?? 'Erro na linha', 'erro' => $e->getMessage()];
                }
            }

            fclose($handle);

            // Retorno da Validação (Previa)
            if ($somenteValidar) {
                return [
                    'status' => 'validacao',
                    'pode_processar' => empty($falhas),
                    'falhas' => $falhas,
                    'total_lotes' => count($mapaLotes),
                    'previa' => $previa
                ];
            }

            // --- PROCESSAMENTO REAL (COMMIT) ---
            foreach ($mapaLotes as $loteCompleto => $dadosLote) {
                if (!isset($dadosLote['info'])) continue;

                try {
                    if (!$this->pdo->inTransaction()) {
                        $this->pdo->beginTransaction();
                    }

                    $idEntidade = $this->buscarIdPorNomeFantasia($dadosLote['info']['fantasia']);
                    if (!$idEntidade) throw new Exception("Fornecedor '{$dadosLote['info']['fantasia']}' não cadastrado.");

                    $numLote = (int)preg_replace('/[^0-9]/', '', explode('/', $loteCompleto)[0]);
                    $dataSql = $this->formatarDataSQL($dadosLote['info']['data']);

                    // 1. HEADER
                    $loteId = $this->buscarLoteIdExistente($loteCompleto);
                    if (!$loteId) {
                        $sqlH = "INSERT INTO tbl_lotes_novo_header (lote_numero, lote_data_fabricacao, lote_cliente_id, lote_completo_calculado, lote_status, lote_usuario_id, lote_data_finalizacao) 
                             VALUES (?, ?, ?, ?, 'LOTE_LEGADO', ?, NOW())";
                        $this->pdo->prepare($sqlH)->execute([$numLote, $dataSql, $idEntidade, $loteCompleto, $usuarioId]);
                        $loteId = $this->pdo->lastInsertId();
                    }

                    foreach ($dadosLote['produtos'] as $codInterno => $dadosProduto) {
                        $infoProd = $this->buscarInfoProduto($codInterno);
                        if (!$infoProd) throw new Exception("Cód. Interno '{$codInterno}' não encontrado.");

                        $qtdTotalDoProduto = array_sum($dadosProduto['enderecos']);
                        $pesoTotal = $qtdTotalDoProduto * (float)$infoProd['prod_peso_embalagem'];

                        // CALCULO DA VALIDADE (Regra consolidada)
                        $mesesValidade = (int)($infoProd['prod_validade_meses'] ?? 0);
                        $dataValidade = $this->calcularValidadeArredondandoParaCima($dataSql, $mesesValidade);

                        // 2. PRODUÇÃO
                        $prodId = $this->verificarItemProducaoExistente($loteId, $infoProd['prod_primario_id']);
                        if (!$prodId) {
                            $sqlP = "INSERT INTO tbl_lotes_novo_producao (item_prod_lote_id, item_prod_produto_id, item_prod_quantidade, item_prod_saldo, item_prod_data_validade) 
                                 VALUES (?, ?, ?, ?, ?)";
                            $this->pdo->prepare($sqlP)->execute([$loteId, $infoProd['prod_primario_id'], $pesoTotal, 0, $dataValidade]);
                            $prodId = $this->pdo->lastInsertId();
                        }

                        // 3. EMBALAGEM
                        $embId = $this->verificarItemEmbalagemExistente($loteId, $infoProd['prod_codigo']);
                        if (!$embId) {
                            $sqlE = "INSERT INTO tbl_lotes_novo_embalagem (item_emb_lote_id, item_emb_prod_sec_id, item_emb_prod_prim_id, item_emb_qtd_sec, item_emb_qtd_finalizada, item_emb_data_cadastro) 
                                 VALUES (?, ?, ?, ?, ?, NOW())";
                            $this->pdo->prepare($sqlE)->execute([$loteId, $infoProd['prod_codigo'], $prodId, $qtdTotalDoProduto, $qtdTotalDoProduto]);
                            $embId = $this->pdo->lastInsertId();
                        }

                        // 4. ENDEREÇAMENTO E KARDEX
                        foreach ($dadosProduto['enderecos'] as $nomeEndereco => $qtdNoEndereco) {
                            $idEnd = $this->buscarIdEndereco($nomeEndereco);
                            if (!$idEnd) throw new Exception("Endereço '{$nomeEndereco}' não localizado.");

                            // Alocação
                            $sqlEst = "INSERT INTO tbl_estoque_alocacoes (alocacao_endereco_id, alocacao_lote_item_id, alocacao_usuario_id, alocacao_quantidade, alocacao_data) 
                                   VALUES (?, ?, ?, ?, NOW())";
                            $this->pdo->prepare($sqlEst)->execute([$idEnd, $embId, $usuarioId, $qtdNoEndereco]);

                            // Kardex
                            $this->movimentoRepo->registrar('ENTRADA_INVENTARIO', $embId, $qtdNoEndereco, $usuarioId, null, $idEnd, "Carga Inicial via Inventário CSV");
                        }
                    }

                    $this->auditLogger->log('CREATE', $loteId, 'tbl_lotes_novo_header', null, ['lote' => $loteCompleto], "Inventário via CSV processado.");
                    $this->pdo->commit();
                    $sucessos++;
                } catch (Exception $e) {
                    if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                    $falhas[] = ['lote' => $loteCompleto, 'erro' => $e->getMessage()];
                }
            }
        }
        return ['status' => 'sucesso', 'sucessos' => $sucessos, 'falhas' => $falhas];
    }

    private function buscarLoteIdExistente($loteCompleto)
    {
        $stmt = $this->pdo->prepare("SELECT lote_id FROM tbl_lotes_novo_header WHERE lote_completo_calculado = ? LIMIT 1");
        $stmt->execute([$loteCompleto]);
        return $stmt->fetchColumn();
    }

    // Busca o ID do Cliente pelo Nome Fantasia
    private function buscarIdPorNomeFantasia($nome)
    {
        $sql = "SELECT ent_codigo FROM tbl_entidades WHERE TRIM(UPPER(ent_nome_fantasia)) = TRIM(UPPER(?)) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$nome]);
        return $stmt->fetchColumn();
    }

    // Busca IDs do Produto (Secundário e seu Primário vinculado) pelo Código Interno
    private function buscarInfoProduto($codInterno)
    {
        $sql = "SELECT prod_codigo, prod_primario_id, prod_peso_embalagem, prod_validade_meses 
            FROM tbl_produtos 
            WHERE TRIM(prod_codigo_interno) = TRIM(?) 
            AND prod_tipo_embalagem = 'SECUNDARIA' 
            LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$codInterno]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Busca ID do Endereço pela descrição exata
    private function buscarIdEndereco($descricao)
    {
        $sql = "SELECT endereco_id FROM tbl_estoque_enderecos WHERE TRIM(endereco_completo) = TRIM(?) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$descricao]);
        return $stmt->fetchColumn();
    }

    private function verificarItemProducaoExistente($loteId, $prodPrimarioId)
    {
        $stmt = $this->pdo->prepare("SELECT item_prod_id FROM tbl_lotes_novo_producao WHERE item_prod_lote_id = ? AND item_prod_produto_id = ?");
        $stmt->execute([$loteId, $prodPrimarioId]);
        return $stmt->fetchColumn();
    }

    private function verificarItemEmbalagemExistente($loteId, $prodSecId)
    {
        $stmt = $this->pdo->prepare("SELECT item_emb_id FROM tbl_lotes_novo_embalagem WHERE item_emb_lote_id = ? AND item_emb_prod_sec_id = ?");
        $stmt->execute([$loteId, $prodSecId]);
        return $stmt->fetchColumn();
    }

    private function formatarDataSQL($data)
    {
        $data = trim($data);
        if (strpos($data, '/') !== false) {
            $p = explode('/', $data);
            return "{$p[2]}-{$p[1]}-{$p[0]}"; // Converte DD/MM/YYYY para YYYY-MM-DD
        }
        return $data;
    }

    private function calcularValidadeArredondandoParaCima($dataFabricacao, $mesesValidade)
    {
        $dt = new DateTime($dataFabricacao);
        $diaOriginal = (int)$dt->format('d');

        // Adiciona os meses (No PHP, Jan 31 + 1 mês vira Março 02 ou 03)
        $dt->modify("+$mesesValidade months");

        // Se o dia mudou (houve estouro de mês), arredondamos para o dia 1 do mês subsequente
        if ((int)$dt->format('d') !== $diaOriginal) {
            $dt->modify('first day of next month');
        }

        return $dt->format('Y-m-d');
    }
}
