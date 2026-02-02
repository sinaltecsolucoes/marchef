<?php
// /src/Lotes/LoteNovoRepository.php
namespace App\Lotes;

use PDO;
use Exception;
use App\Core\AuditLoggerService;
use App\Estoque\MovimentoRepository;

class LoteNovoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;
    private MovimentoRepository $movimentoRepo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        try {
            $this->pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
        } catch (Exception $e) {
            // Se der erro ao tentar mudar (raro), segue a vida
        }

        $this->auditLogger = new AuditLoggerService($pdo);
        $this->movimentoRepo = new MovimentoRepository($pdo);
    }

    /**
     * Salva (cria ou atualiza) o cabeçalho de um novo lote na tabela tbl_lotes_novo_header.
     *
     * @param array $data Os dados do formulário (ex: $_POST).
     * @param int $userId O ID do utilizador que está a realizar a ação.
     * @return int O ID do lote que foi salvo.
     * @throws Exception
     */
    public function saveHeader(array $data, int $userId): int
    {
        $id = filter_var($data['lote_id'] ?? null, FILTER_VALIDATE_INT);
        $dadosAntigos = null;

        if ($id) {
            // Se estivermos a editar, busca os dados antigos para a auditoria
            $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_lotes_novo_header WHERE lote_id = :id");
            $stmtAntigo->execute([':id' => $id]);
            $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);
        }

        $params = [
            ':numero' => $data['lote_numero'],
            ':data_fab' => $data['lote_data_fabricacao'],
            ':fornecedor' => $data['lote_fornecedor_id'] ?: null,
            ':cliente' => $data['lote_cliente_id'] ?: null,
            ':ciclo' => $data['lote_ciclo'] ?? null,
            ':viveiro' => $data['lote_viveiro'],
            ':so2' => !empty($data['lote_so2']) ? $data['lote_so2'] : null,
            ':observacao' => $data['lote_observacao'] ?? null,
            ':completo' => $data['lote_completo_calculado'],
        ];

        if ($id) {
            // Se já existe um ID, fazemos um UPDATE
            $sql = "UPDATE tbl_lotes_novo_header 
                    SET lote_numero = :numero, lote_data_fabricacao = :data_fab, 
                        lote_fornecedor_id = :fornecedor, lote_cliente_id = :cliente,  
                        lote_ciclo = :ciclo, lote_viveiro = :viveiro,
                        lote_so2 = :so2, lote_observacao = :observacao, 
                        lote_completo_calculado = :completo 
                    WHERE lote_id = :id";
            $params[':id'] = $id;
        } else {
            // Se não existe ID, fazemos um INSERT
            $sql = "INSERT INTO tbl_lotes_novo_header (
                        lote_numero, lote_data_fabricacao, lote_fornecedor_id, lote_cliente_id,
                        lote_ciclo, lote_viveiro, lote_so2, lote_observacao, 
                        lote_completo_calculado, lote_usuario_id
                    ) VALUES (
                        :numero, :data_fab, :fornecedor, :cliente, 
                        :ciclo, :viveiro, :so2, :observacao,
                        :completo, :user_id
                    )";
            $params[':user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultId = $id ?: (int) $this->pdo->lastInsertId();

        // Lógica de Auditoria
        if ($id) { // Log de UPDATE
            $this->auditLogger->log(
                'UPDATE',
                $resultId,
                'tbl_lotes_novo_header',
                $dadosAntigos,
                $data,
                ""
            );
        } else { // Log de CREATE
            $this->auditLogger->log(
                'CREATE',
                $resultId,
                'tbl_lotes_novo_header',
                null,
                $data,
                ""
            );
        }

        return $resultId;
    }

    /**
     * Adiciona um novo item de produção (embalagem primária) a um lote.
     *
     * @param array $data Os dados do formulário do item (lote_id, produto_id, quantidade, etc.).
     * @return int O ID do novo item de produção criado.
     * @throws Exception
     */
    public function adicionarItemProducao(array $data): int
    {
        // Validação básica dos dados recebidos
        if (empty($data['item_prod_lote_id']) || empty($data['item_prod_produto_id']) || empty($data['item_prod_quantidade'])) {
            throw new Exception("Dados insuficientes para adicionar o item de produção.");
        }

        $quantidade = (float) $data['item_prod_quantidade'];

        // A lógica de negócio principal: o saldo inicial é igual à quantidade produzida.
        $params = [
            ':lote_id' => $data['item_prod_lote_id'],
            ':produto_id' => $data['item_prod_produto_id'],
            ':quantidade' => $quantidade,
            ':saldo' => $quantidade, // O saldo inicial é a própria quantidade
            ':data_validade' => $data['item_prod_data_validade'] ?: null,
        ];

        $sql = "INSERT INTO tbl_lotes_novo_producao (
                    item_prod_lote_id, item_prod_produto_id, 
                    item_prod_quantidade, item_prod_saldo, item_prod_data_validade
                ) VALUES (
                    :lote_id, :produto_id, 
                    :quantidade, :saldo, :data_validade
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $novoId = (int) $this->pdo->lastInsertId();

        // Lógica de Auditoria
        if ($novoId > 0) {
            $this->auditLogger->log('CREATE', $novoId, 'tbl_lotes_novo_producao', null, $params, "");
        }

        return $novoId;
    }

    public function adicionarItemEmbalagem(array $data): int
    {
        // 1. Validações Iniciais
        $loteId = filter_var($data['item_emb_lote_id'], FILTER_VALIDATE_INT);
        $prodSecId = filter_var($data['item_emb_prod_sec_id'], FILTER_VALIDATE_INT);
        $prodPrimId = filter_var($data['item_emb_prod_prim_id'], FILTER_VALIDATE_INT); // Agora isso é o ID do Produto!
        $qtdSec = filter_var($data['item_emb_qtd_sec'], FILTER_VALIDATE_FLOAT);

        if (!$loteId || !$prodSecId || !$prodPrimId || !$qtdSec || $qtdSec <= 0) {
            throw new Exception("Dados insuficientes ou inválidos para adicionar o item de embalagem.");
        }

        $this->pdo->beginTransaction();
        try {
            // 2. Calcula o consumo total necessário (Unidades * Peso)
            $sqlPesos = "SELECT 
                        p_sec.prod_peso_embalagem AS peso_secundario,
                        p_prim.prod_peso_embalagem AS peso_primario
                    FROM tbl_produtos AS p_sec
                    LEFT JOIN tbl_produtos AS p_prim ON p_sec.prod_primario_id = p_prim.prod_codigo
                    WHERE p_sec.prod_codigo = :id_secundario";

            $stmtPesos = $this->pdo->prepare($sqlPesos);
            $stmtPesos->execute([':id_secundario' => $prodSecId]);
            $pesos = $stmtPesos->fetch(PDO::FETCH_ASSOC);

            if (!$pesos || empty($pesos['peso_primario']) || $pesos['peso_primario'] <= 0) {
                throw new Exception("Erro de configuração de pesos nos produtos.");
            }

            $unidadesPorEmbalagem = (float) $pesos['peso_secundario'] / (float) $pesos['peso_primario'];
            $consumoTotalNecessario = $qtdSec * $unidadesPorEmbalagem; // Ex: 50 * 10 = 500 unidades primárias

            // 3. Busca itens de produção disponíveis para este produto (FIFO - Mais antigos primeiro)
            // IMPORTANTE: Lock (FOR UPDATE) para evitar concorrência
            $stmtEstoque = $this->pdo->prepare("
                SELECT item_prod_id, item_prod_saldo 
                FROM tbl_lotes_novo_producao 
                WHERE item_prod_lote_id = :lote_id 
                  AND item_prod_produto_id = :prod_id 
                  AND item_prod_saldo > 0 
                ORDER BY item_prod_id ASC 
                FOR UPDATE
            ");
            $stmtEstoque->execute([':lote_id' => $loteId, ':prod_id' => $prodPrimId]);
            $itensDisponiveis = $stmtEstoque->fetchAll(PDO::FETCH_ASSOC);

            // 4. Lógica de Distribuição (FIFO)
            $qtdRestanteParaConsumir = $consumoTotalNecessario;
            $novoIdPrincipal = 0; // Guardaremos o ID do primeiro insert para retornar

            foreach ($itensDisponiveis as $item) {
                if ($qtdRestanteParaConsumir <= 0) break; // Já consumimos tudo que precisava

                $saldoItem = (float) $item['item_prod_saldo'];
                $itemProdId = $item['item_prod_id'];

                // Quanto vamos tirar deste item específico?
                $consumirDeste = min($saldoItem, $qtdRestanteParaConsumir);

                // Atualiza o saldo na produção
                $novoSaldo = $saldoItem - $consumirDeste;
                $this->pdo->prepare("UPDATE tbl_lotes_novo_producao SET item_prod_saldo = :saldo WHERE item_prod_id = :id")
                    ->execute([':saldo' => $novoSaldo, ':id' => $itemProdId]);

                // Insere o registro na embalagem
                // Nota: Se o consumo for fracionado entre dois itens, isso criará DUAS linhas na tabela de embalagem.
                // Isso é necessário para manter a rastreabilidade exata do banco de dados.

                // Calculamos a proporção da Qtd Secundária para este fragmento
                // (Regra de 3: Se ConsumoTotal gera QtdSecTotal, ConsumirDeste gera X)
                $qtdSecProporcional = ($consumirDeste / $unidadesPorEmbalagem);

                $sqlInsert = "INSERT INTO tbl_lotes_novo_embalagem (
                            item_emb_lote_id, item_emb_prod_sec_id, item_emb_prod_prim_id, 
                            item_emb_qtd_sec, item_emb_qtd_prim_cons
                        ) VALUES (
                            :lote_id, :prod_sec_id, :prod_prim_id, 
                            :qtd_sec, :qtd_prim_cons
                        )";

                $this->pdo->prepare($sqlInsert)->execute([
                    ':lote_id' => $loteId,
                    ':prod_sec_id' => $prodSecId,
                    ':prod_prim_id' => $itemProdId, // Linka com a linha específica que cedeu o saldo
                    ':qtd_sec' => $qtdSecProporcional,
                    ':qtd_prim_cons' => $consumirDeste
                ]);

                if ($novoIdPrincipal === 0) {
                    $novoIdPrincipal = (int) $this->pdo->lastInsertId();
                }

                $qtdRestanteParaConsumir -= $consumirDeste;
            }

            // 5. Verifica se conseguiu consumir tudo
            if ($qtdRestanteParaConsumir > 0.001) { // Margem de erro float
                // Se sobrou algo para consumir, é porque não tinha saldo suficiente
                throw new Exception("Saldo insuficiente no total agrupado! Faltaram: " . number_format($qtdRestanteParaConsumir, 3));
            }

            // 6. Auditoria e Commit
            $this->auditLogger->log('CREATE', $novoIdPrincipal, 'tbl_lotes_novo_embalagem', null, $data, "Embalagem adicionada (Consumo agrupado)");
            $this->pdo->commit();

            return $novoIdPrincipal;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Exclui permanentemente um lote e todos os seus itens associados,
     * após validar as regras de segurança.
     *
     * @param int $loteId O ID do lote a ser excluído.
     * @return bool
     * @throws Exception
     */

    public function excluirLote(int $loteId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Busca os dados do lote para validação e auditoria
            $stmtLote = $this->pdo->prepare("SELECT * FROM tbl_lotes_novo_header WHERE lote_id = :id");
            $stmtLote->execute([':id' => $loteId]);
            $loteAtual = $stmtLote->fetch(PDO::FETCH_ASSOC);

            if (!$loteAtual) {
                throw new Exception("Lote não encontrado.");
            }

            // 2. VALIDAÇÃO: Permite a exclusão apenas de lotes 'CANCELADO' ou 'EM ANDAMENTO'.
            if (!in_array($loteAtual['lote_status'], ['CANCELADO', 'EM ANDAMENTO'])) {
                throw new Exception("Apenas lotes com status 'EM ANDAMENTO' ou 'CANCELADO' podem ser excluídos. Por favor, cancele ou reabra o lote primeiro.");
            }

            // --------------------------------------------------------------------------
            // 3. Apaga permanentemente todos os movimentos de estoque.
            // --------------------------------------------------------------------------
            $stmtDeleteEstoque = $this->pdo->prepare("DELETE FROM tbl_estoque WHERE estoque_lote_item_id = :lote_id");
            $stmtDeleteEstoque->execute([':lote_id' => $loteId]);


            // 4. Se todas as validações passaram, executa a exclusão em cascata
            $this->pdo->prepare("DELETE FROM tbl_lotes_novo_embalagem WHERE item_emb_lote_id = :id")->execute([':id' => $loteId]);
            $this->pdo->prepare("DELETE FROM tbl_lotes_novo_producao WHERE item_prod_lote_id = :id")->execute([':id' => $loteId]);

            // Finalmente, exclui o cabeçalho do lote
            $stmtDeleteHeader = $this->pdo->prepare("DELETE FROM tbl_lotes_novo_header WHERE lote_id = :id");
            $success = $stmtDeleteHeader->execute([':id' => $loteId]);

            // 5. Log de auditoria com a mensagem atualizada para refletir a ação real
            $this->auditLogger->log('DELETE', $loteId, 'tbl_lotes_novo_header', $loteAtual, null, "Exclusão permanente do lote e de seus movimentos de estoque associados.");

            $this->pdo->commit();
            return $success;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Exclui um item de produção, se ele não tiver sido utilizado.
     * @param int $itemProdId O ID do item de produção a ser excluído.
     * @return bool
     * @throws Exception
     */
    public function excluirItemProducao(int $itemProdId): bool
    {
        // 1. Busca o item para validar
        $stmtItem = $this->pdo->prepare("SELECT item_prod_quantidade, item_prod_saldo FROM tbl_lotes_novo_producao WHERE item_prod_id = :id");
        $stmtItem->execute([':id' => $itemProdId]);
        $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("Item de produção não encontrado.");
        }

        // 2. Valida a regra de negócio
        if ((float) $item['item_prod_quantidade'] !== (float) $item['item_prod_saldo']) {
            throw new Exception("Este item não pode ser excluído pois seu saldo já foi consumido por itens de embalagem.");
        }

        // 3. Se a validação passar, exclui o item
        $stmtDelete = $this->pdo->prepare("DELETE FROM tbl_lotes_novo_producao WHERE item_prod_id = :id");
        $success = $stmtDelete->execute([':id' => $itemProdId]);

        if ($success) {
            $this->auditLogger->log('DELETE', $itemProdId, 'tbl_lotes_novo_producao', $item, null, "");
        }

        return $success;
    }

    /**
     * Exclui um item de embalagem e reverte o saldo consumido do item de produção.
     * @param int $itemEmbId O ID do item de embalagem a ser excluído.
     * @return bool
     * @throws Exception
     */
    public function excluirItemEmbalagem(int $itemEmbId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Busca o item de embalagem para saber o que reverter
            $stmtItem = $this->pdo->prepare("SELECT item_emb_prod_prim_id, item_emb_qtd_prim_cons FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id");
            $stmtItem->execute([':id' => $itemEmbId]);
            $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new Exception("Item de embalagem não encontrado.");
            }

            $prodPrimItemId = $item['item_emb_prod_prim_id'];
            $qtdAReverter = $item['item_emb_qtd_prim_cons'];

            // 2. Adiciona o saldo de volta ao item de produção (operação de reversão)
            $stmtReverte = $this->pdo->prepare(
                "UPDATE tbl_lotes_novo_producao 
             SET item_prod_saldo = item_prod_saldo + :qtd 
             WHERE item_prod_id = :id"
            );
            $stmtReverte->execute([':qtd' => $qtdAReverter, ':id' => $prodPrimItemId]);

            // 3. Exclui o item de embalagem
            $stmtDelete = $this->pdo->prepare("DELETE FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id");
            $stmtDelete->execute([':id' => $itemEmbId]);

            $this->auditLogger->log('DELETE', $itemEmbId, 'tbl_lotes_novo_embalagem', $item, null, "");
            $this->auditLogger->log('UPDATE', $prodPrimItemId, 'tbl_lotes_novo_producao', ['saldo_revertido' => $qtdAReverter], null, "");

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza um item de produção existente.
     * @param int $itemProdId O ID do item a ser atualizado.
     * @param array $data Os novos dados do formulário.
     * @return bool
     * @throws Exception
     */
    public function atualizarItemProducao(int $itemProdId, array $data): bool
    {
        // 1. Busca os dados atuais do item
        $stmtItem = $this->pdo->prepare("SELECT item_prod_quantidade, item_prod_saldo FROM tbl_lotes_novo_producao WHERE item_prod_id = :id");
        $stmtItem->execute([':id' => $itemProdId]);
        $itemAtual = $stmtItem->fetch(PDO::FETCH_ASSOC);

        if (!$itemAtual) {
            throw new Exception("Item de produção não encontrado.");
        }

        $novaQuantidade = (float) $data['item_prod_quantidade'];
        $quantidadeAntiga = (float) $itemAtual['item_prod_quantidade'];
        $saldoAntigo = (float) $itemAtual['item_prod_saldo'];

        // 2. Validação: A nova quantidade não pode ser menor que o que já foi consumido
        $quantidadeConsumida = $quantidadeAntiga - $saldoAntigo;
        if ($novaQuantidade < $quantidadeConsumida) {
            throw new Exception("A quantidade não pode ser menor que o valor já consumido ({$quantidadeConsumida}).");
        }

        // 3. Calcula o novo saldo
        $diferenca = $novaQuantidade - $quantidadeAntiga;
        $novoSaldo = $saldoAntigo + $diferenca;

        // 4. Prepara e executa a atualização
        $sql = "UPDATE tbl_lotes_novo_producao SET
                item_prod_produto_id = :produto_id,
                item_prod_quantidade = :quantidade,
                item_prod_saldo = :saldo,
                item_prod_data_validade = :validade
            WHERE item_prod_id = :id";

        $params = [
            ':produto_id' => $data['item_prod_produto_id'],
            ':quantidade' => $novaQuantidade,
            ':saldo' => $novoSaldo,
            ':validade' => $data['item_prod_data_validade'] ?: null,
            ':id' => $itemProdId
        ];

        $stmtUpdate = $this->pdo->prepare($sql);
        $success = $stmtUpdate->execute($params);

        if ($success) {
            $this->auditLogger->log('UPDATE', $itemProdId, 'tbl_lotes_novo_producao', $itemAtual, $data, "");
        }

        return $success;
    }

    /**
     * Atualiza um item de embalagem e reajusta o saldo do item de produção.
     * @param int $itemEmbId O ID do item de embalagem a ser atualizado.
     * @param array $data Os novos dados do formulário.
     * @return bool
     * @throws Exception
     */
    public function atualizarItemEmbalagem(int $itemEmbId, array $data): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Busca os dados atuais do item de embalagem que está a ser editado
            $stmtItem = $this->pdo->prepare("SELECT * FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id");
            $stmtItem->execute([':id' => $itemEmbId]);
            $itemAtual = $stmtItem->fetch(PDO::FETCH_ASSOC);

            if (!$itemAtual)
                throw new Exception("Item de embalagem não encontrado.");

            $consumoAntigo = (float) $itemAtual['item_emb_qtd_prim_cons'];
            $prodPrimItemId = $itemAtual['item_emb_prod_prim_id'];

            // 2. Calcula o NOVO consumo que a operação terá
            $novoProdSecId = $data['item_emb_prod_sec_id'];
            $novaQtdSec = (float) $data['item_emb_qtd_sec'];
            $sqlPesos = "SELECT IF(p_prim.prod_peso_embalagem > 0, p_sec.prod_peso_embalagem / p_prim.prod_peso_embalagem, 0) AS ratio FROM tbl_produtos p_sec JOIN tbl_produtos p_prim ON p_sec.prod_primario_id = p_prim.prod_codigo WHERE p_sec.prod_codigo = :id_sec";
            $stmtPesos = $this->pdo->prepare($sqlPesos);
            $stmtPesos->execute([':id_sec' => $novoProdSecId]);
            $unidadesPorEmbalagem = (float) $stmtPesos->fetchColumn();
            $novoConsumoPrimario = $novaQtdSec * $unidadesPorEmbalagem;

            // 3. Busca o saldo ATUAL do item de produção
            $stmtSaldo = $this->pdo->prepare("SELECT item_prod_saldo FROM tbl_lotes_novo_producao WHERE item_prod_id = :id FOR UPDATE");
            $stmtSaldo->execute([':id' => $prodPrimItemId]);
            $saldoAtual = (float) $stmtSaldo->fetchColumn();

            // 4. REVERTE: Calcula qual seria o saldo se a operação antiga não existisse
            $saldoRevertido = $saldoAtual + $consumoAntigo;

            // 5. VALIDA: Verifica se o saldo (já revertido) suporta o novo consumo
            if ($saldoRevertido < $novoConsumoPrimario) {
                throw new Exception("Saldo insuficiente no item de produção. Saldo total disponível para a operação: {$saldoRevertido}, novo consumo necessário: {$novoConsumoPrimario}.");
            }

            // 6. REAPLICA: Calcula o saldo final
            $novoSaldoFinal = $saldoRevertido - $novoConsumoPrimario;

            // 7. ATUALIZA o saldo do item de produção com o valor final absoluto
            $stmtAjusteSaldo = $this->pdo->prepare("UPDATE tbl_lotes_novo_producao SET item_prod_saldo = :novo_saldo WHERE item_prod_id = :id");
            $stmtAjusteSaldo->execute([':novo_saldo' => $novoSaldoFinal, ':id' => $prodPrimItemId]);

            // 8. ATUALIZA o próprio item de embalagem com os novos dados
            $sqlUpdate = "UPDATE tbl_lotes_novo_embalagem SET 
                        item_emb_prod_sec_id = :prod_sec_id,
                        item_emb_qtd_sec = :qtd_sec,
                        item_emb_qtd_prim_cons = :qtd_prim_cons
                      WHERE item_emb_id = :id";
            $paramsUpdate = [
                ':prod_sec_id' => $novoProdSecId,
                ':qtd_sec' => $novaQtdSec,
                ':qtd_prim_cons' => $novoConsumoPrimario,
                ':id' => $itemEmbId
            ];
            $stmtUpdate = $this->pdo->prepare($sqlUpdate);
            $stmtUpdate->execute($paramsUpdate);

            $this->auditLogger->log('UPDATE', $itemEmbId, 'tbl_lotes_novo_embalagem', $itemAtual, $data, "");

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cancela um lote. Se o lote já tiver gerado estoque, o estoque é revertido.
     *
     * @param int $loteId O ID do lote a ser cancelado.
     * @return bool
     * @throws Exception
     */
    public function cancelarLote(int $loteId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Busca os dados do lote para validação e auditoria
            $stmtLote = $this->pdo->prepare("SELECT lote_numero, lote_status FROM tbl_lotes_novo_header WHERE lote_id = :id");
            $stmtLote->execute([':id' => $loteId]);
            $loteAtual = $stmtLote->fetch(PDO::FETCH_ASSOC);

            if (!$loteAtual) {
                throw new Exception("Lote não encontrado.");
            }
            if ($loteAtual['lote_status'] === 'CANCELADO') {
                throw new Exception("Este lote já está cancelado.");
            }

            // 2. Verifica se o lote já gerou estoque (se está PARCIALMENTE FINALIZADO)
            if ($loteAtual['lote_status'] === 'PARCIALMENTE FINALIZADO') {
                // Busca todos os itens que já foram finalizados para reverter
                $stmtItensFinalizados = $this->pdo->prepare(
                    "SELECT item_emb_id, item_emb_prod_sec_id, item_emb_qtd_finalizada 
                 FROM tbl_lotes_novo_embalagem 
                 WHERE item_emb_lote_id = :lote_id AND item_emb_qtd_finalizada > 0"
                );
                $stmtItensFinalizados->execute([':lote_id' => $loteId]);
                $itensParaReverter = $stmtItensFinalizados->fetchAll(PDO::FETCH_ASSOC);

                foreach ($itensParaReverter as $item) {
                    $quantidadeReverter = (float) $item['item_emb_qtd_finalizada'];

                    if ($quantidadeReverter > 0) {

                        // Zera a quantidade finalizada no item de embalagem
                        $stmtUpdateItem = $this->pdo->prepare(
                            "UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = 0 WHERE item_emb_id = :id"
                        );
                        $stmtUpdateItem->execute([':id' => $item['item_emb_id']]);
                    }
                }
            }

            // 3. Finalmente, atualiza o status do lote para CANCELADO
            $stmtUpdateHeader = $this->pdo->prepare("UPDATE tbl_lotes_novo_header SET lote_status = 'CANCELADO' WHERE lote_id = :id");
            $stmtUpdateHeader->execute([':id' => $loteId]);

            $this->auditLogger->log('CANCEL', $loteId, 'tbl_lotes_novo_header', ['lote_status' => $loteAtual['lote_status']], ['lote_status' => 'CANCELADO'], "");

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reativa um lote que foi cancelado, mudando seu status para 'EM ANDAMENTO'.
     *
     * @param int $loteId O ID do lote.
     * @param string $motivo O motivo da reativação.
     * @return bool
     * @throws Exception
     */
    public function reativarLote(int $loteId, string $motivo): bool
    {
        if (empty(trim($motivo))) {
            throw new Exception("O motivo da reativação é obrigatório.");
        }

        // 1. Busca os dados do lote para validação
        $stmtLote = $this->pdo->prepare("SELECT lote_status FROM tbl_lotes_novo_header WHERE lote_id = :id");
        $stmtLote->execute([':id' => $loteId]);
        $loteAtual = $stmtLote->fetch(PDO::FETCH_ASSOC);

        if (!$loteAtual) {
            throw new Exception("Lote não encontrado.");
        }

        if ($loteAtual['lote_status'] !== 'CANCELADO') {
            throw new Exception("Apenas lotes com o status 'CANCELADO' podem ser reativados.");
        }

        // 2. Atualiza o status
        $stmtUpdate = $this->pdo->prepare("UPDATE tbl_lotes_novo_header SET lote_status = 'EM ANDAMENTO' WHERE lote_id = :id");
        $success = $stmtUpdate->execute([':id' => $loteId]);

        // 3. Registra na Auditoria COM O MOTIVO
        if ($success) {
            $this->auditLogger->log(
                'REACTIVATE',
                $loteId,
                'tbl_lotes_novo_header',
                ['lote_status' => 'CANCELADO'],
                ['lote_status' => 'EM ANDAMENTO'],
                $motivo // <--- Motivo gravado aqui
            );
        }

        return $success;
    }

    /**
     * Reabre um lote finalizado, revertendo o estoque gerado e alterando o status para 'EM ANDAMENTO'.
     *
     * @param int $loteId O ID do lote a ser reaberto.
     * @param string $motivo O motivo da reabertura, para fins de auditoria.
     * @return bool
     * @throws Exception
     */
    public function reabrirLote(int $loteId, string $motivo): bool
    {
        if (empty(trim($motivo))) {
            throw new Exception("É obrigatório fornecer um motivo para reabrir o lote.");
        }

        $this->pdo->beginTransaction();
        try {
            $stmtLote = $this->pdo->prepare("SELECT lote_numero, lote_status FROM tbl_lotes_novo_header WHERE lote_id = :id");
            $stmtLote->execute([':id' => $loteId]);
            $loteAtual = $stmtLote->fetch(PDO::FETCH_ASSOC);

            if (!$loteAtual) {
                throw new Exception("Lote não encontrado.");
            }
            if ($loteAtual['lote_status'] !== 'FINALIZADO' && $loteAtual['lote_status'] !== 'PARCIALMENTE FINALIZADO') {
                throw new Exception("Apenas lotes finalizados ou parcialmente finalizados podem ser reabertos.");
            }

            // Busca todos os itens que já foram finalizados para reverter
            $stmtItensFinalizados = $this->pdo->prepare(
                "SELECT item_emb_id, item_emb_prod_sec_id, item_emb_qtd_finalizada 
             FROM tbl_lotes_novo_embalagem 
             WHERE item_emb_lote_id = :lote_id AND item_emb_qtd_finalizada > 0"
            );
            $stmtItensFinalizados->execute([':lote_id' => $loteId]);
            $itensParaReverter = $stmtItensFinalizados->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itensParaReverter as $item) {
                $quantidadeReverter = (float) $item['item_emb_qtd_finalizada'];
                if ($quantidadeReverter > 0) {

                    // Zera a quantidade finalizada no item de embalagem
                    $stmtUpdateItem = $this->pdo->prepare("UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = 0 WHERE item_emb_id = :id");
                    $stmtUpdateItem->execute([':id' => $item['item_emb_id']]);
                }
            }

            // Atualiza o status do lote para 'EM ANDAMENTO'
            $stmtUpdateHeader = $this->pdo->prepare("UPDATE tbl_lotes_novo_header SET lote_status = 'EM ANDAMENTO' WHERE lote_id = :id");
            $stmtUpdateHeader->execute([':id' => $loteId]);

            $this->auditLogger->log('REOPEN', $loteId, 'tbl_lotes_novo_header', ['lote_status' => $loteAtual['lote_status']], ['lote_status' => 'EM ANDAMENTO'], $motivo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Processa a finalização parcial ou total de itens de um lote.
     * Atualiza as quantidades e define se o lote deve ser fechado.
     */
    public function finalizarLoteParcialmente(int $loteId, array $itensParaFinalizar, int $usuarioId): bool
    {
        if (empty($itensParaFinalizar)) {
            throw new Exception("Nenhum item foi selecionado para finalização.");
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Busca o número do lote para validação
            $stmt_lote_header = $this->pdo->prepare("SELECT lote_numero FROM tbl_lotes_novo_header WHERE lote_id = :id");
            $stmt_lote_header->execute([':id' => $loteId]);
            $numeroDoLote = $stmt_lote_header->fetchColumn();

            if (!$numeroDoLote) {
                throw new Exception("Lote com ID {$loteId} não encontrado para finalização.");
            }

            // --- PREPARA AS QUERIES PARA O LOOP ---
            $stmt_item = $this->pdo->prepare(
                "SELECT item_emb_prod_sec_id, (item_emb_qtd_sec - item_emb_qtd_finalizada) AS disponivel 
                 FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id FOR UPDATE"
            );

            $stmt_update_item = $this->pdo->prepare(
                "UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = item_emb_qtd_finalizada + :qtd 
                 WHERE item_emb_id = :id"
            );

            // --- LOOP: ATUALIZAR ITENS ---
            foreach ($itensParaFinalizar as $item) {
                // Validação de Segurança (Evita Erro 500 "Undefined array key")
                if (!isset($item['item_id']) || !isset($item['quantidade'])) {
                    continue;
                }

                $itemId = $item['item_id'];

                // Trabalhamos com INTEIROS para caixas/unidades
                $qtdAFinalizar = (int) $item['quantidade'];

                if ($qtdAFinalizar <= 0)
                    continue;

                // Verifica disponibilidade
                $stmt_item->execute([':id' => $itemId]);
                $itemDb = $stmt_item->fetch(PDO::FETCH_ASSOC);

                if (!$itemDb || $qtdAFinalizar > (float) $itemDb['disponivel']) {
                    throw new Exception("Quantidade a finalizar para o item ID {$itemId} é maior que a disponível.");
                }

                // Atualiza a quantidade finalizada
                $stmt_update_item->execute([':qtd' => $qtdAFinalizar, ':id' => $itemId]);
            }


            // Verifica se ainda existe algum item de embalagem pendente.
            // Usamos margem de 0.1 para cobrir qualquer resíduo, já que trabalhamos com inteiros.
            // Ignoramos saldo de matéria-prima (Produção).
            $sqlEmbPendente = "SELECT COUNT(*) FROM tbl_lotes_novo_embalagem 
                               WHERE item_emb_lote_id = :id 
                               AND (item_emb_qtd_sec - item_emb_qtd_finalizada) > 0.1";

            $stmtCheckEmb = $this->pdo->prepare($sqlEmbPendente);
            $stmtCheckEmb->execute([':id' => $loteId]);
            $qtdItensPendentes = $stmtCheckEmb->fetchColumn();

            // Se 0 itens pendentes = FINALIZADO
            if ($qtdItensPendentes == 0) {
                $novoStatus = 'FINALIZADO';
            } else {
                $novoStatus = 'PARCIALMENTE FINALIZADO';
            }

            // --- ATUALIZAÇÃO DO CABEÇALHO ---
            $setClauses = ["lote_status = :status"];
            $params_update_header = [
                ':status' => $novoStatus,
                ':id' => $loteId
            ];

            // Se finalizou agora, grava a data
            if ($novoStatus === 'FINALIZADO') {
                $setClauses[] = "lote_data_finalizacao = NOW()";
            }

            $sql_update_header = "UPDATE tbl_lotes_novo_header SET " . implode(", ", $setClauses) . " WHERE lote_id = :id";
            $stmt_update_header = $this->pdo->prepare($sql_update_header);
            $stmt_update_header->execute($params_update_header);

            $this->auditLogger->log(
                'UPDATE',
                $loteId,
                'tbl_lotes_novo_header',
                null,
                ['novo_status' => $novoStatus],
                "Finalização de itens"
            );

            // Se o lote acabou de ser totalmente FINALIZADO, registramos a entrada oficial no estoque.
            if ($novoStatus === 'FINALIZADO') {
                $this->registrarEntradaProducaoKardex($loteId, $usuarioId);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findAllForDataTable(array $params): array
    {
        // 1. Parâmetros básicos do DataTables
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        // 2. Definição da Query Base e Joins
        // Importante: Usamos LEFT JOIN para garantir que traga o lote mesmo se não tiver detalhes
        $baseQuery = "FROM tbl_lotes_novo_header l         
                  LEFT JOIN tbl_entidades c ON l.lote_cliente_id = c.ent_codigo
                  LEFT JOIN tbl_entidades f ON l.lote_fornecedor_id = f.ent_codigo
                  LEFT JOIN tbl_lote_novo_recebdetalhes r ON l.lote_id = r.item_receb_lote_id";

        // 3. Contagem Total (Sem filtros)
        $totalRecords = $this->pdo->query("SELECT COUNT(l.lote_id) FROM tbl_lotes_novo_header l")->fetchColumn();

        // 4. Lógica de Pesquisa (Filtro)
        // $whereClause = "";
        $whereClause = " WHERE 1=1 ";
        $queryParams = [];

        if (!empty($searchValue)) {
            $searchTerm = '%' . $searchValue . '%';

            // 2. Termo Numérico (Para Pesos e Gramaturas)
            // Remove 'g', 'kg', espaços e converte formato BR (1.000,50) para US (1000.50)

            // Remove letras (g, kg) e espaços
            $cleanVal = preg_replace('/[a-zA-Z\s]/', '', $searchValue);

            // Se tiver vírgula, assumimos que é decimal BR
            if (strpos($cleanVal, ',') !== false) {
                $cleanVal = str_replace('.', '', $cleanVal);
                $cleanVal = str_replace(',', '.', $cleanVal);
            }

            // Proteção: Se sobrou string vazia (ex: user digitou só "kg"), usa o original para não quebrar
            $searchNumeric = empty($cleanVal) ? $searchTerm : '%' . $cleanVal . '%';

            $whereClause .= " AND (
                        l.lote_completo_calculado LIKE :search0 OR
                        l.lote_numero LIKE :search1 OR
                        c.ent_razao_social LIKE :search2 OR
                        c.ent_nome_fantasia LIKE :search3 OR
                        EXISTS(
                            SELECT 1 FROM tbl_lote_novo_recebdetalhes sub_d
                            WHERE sub_d.item_receb_lote_id = l.lote_id
                            AND (
                                sub_d.item_receb_gram_faz LIKE :search4 OR
                                sub_d.item_receb_gram_lab LIKE :search5 OR
                                CAST(sub_d.item_receb_peso_nota_fiscal AS CHAR) LIKE :search6
                            )
                        ) OR 
                        CAST((
                            SELECT SUM(sub_s.item_receb_peso_nota_fiscal) 
                            FROM tbl_lote_novo_recebdetalhes sub_s 
                            WHERE sub_s.item_receb_lote_id = l.lote_id
                        ) AS CHAR) LIKE :search8 OR
                        DATE_FORMAT(l.lote_data_fabricacao, '%d/%m/%Y') LIKE :search7
                    )";

            $queryParams = [
                ':search0' => $searchTerm,
                ':search1' => $searchTerm,
                ':search2' => $searchTerm,
                ':search3' => $searchTerm,
                ':search4' => $searchNumeric,
                ':search5' => $searchNumeric,
                ':search6' => $searchNumeric,
                ':search7' => $searchTerm,
                ':search8' => $searchNumeric,
            ];
        }

        // --- FILTROS DINÂMICOS ---

        // 1. Filtro de Ano
        if (!empty($params['ano'])) {
            $whereClause .= " AND YEAR(l.lote_data_fabricacao) = :filtro_ano";
            $queryParams[':filtro_ano'] = $params['ano'];
        }

        // 2. Filtro de Meses (Múltiplos)
        if (!empty($params['meses']) && is_array($params['meses'])) {
            $placeholders = [];
            foreach ($params['meses'] as $i => $mes) {
                $key = ":mes$i";
                $placeholders[] = $key;
                $queryParams[$key] = $mes;
            }
            $whereClause .= " AND MONTH(l.lote_data_fabricacao) IN (" . implode(',', $placeholders) . ")";
        }

        // 3. Filtro de Situações
         if (!empty($params['situacoes']) && is_array($params['situacoes'])) {
            $placeholders = [];
            foreach ($params['situacoes'] as $i => $sit) {
                $key = ":sit$i";
                $placeholders[] = $key;
                $queryParams[$key] = $sit;
            }
            $whereClause .= " AND l.lote_status IN (" . implode(',', $placeholders) . ")";
        }

        // 4. Filtro de Fornecedores
        if (!empty($params['fornecedores']) && is_array($params['fornecedores'])) {
            $placeholders = [];
            foreach ($params['fornecedores'] as $i => $forn) {
                $key = ":forn$i";
                $placeholders[] = $key;
                $queryParams[$key] = $forn;
            }
            $whereClause .= " AND l.lote_cliente_id IN (" . implode(',', $placeholders) . ")";
        }

        // 5. Contagem dos Filtrados (Considerando DISTINCT por causa do JOIN 1:N)
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(DISTINCT l.lote_id) $baseQuery $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // Query para calculo de Totais (Peso e Médias ponderadas de gramaturas)
        $sqlTotais = "SELECT 
                SUM(r.item_receb_peso_nota_fiscal) as totalPesoFiltrado,
                SUM(r.item_receb_gram_faz * r.item_receb_peso_nota_fiscal) / NULLIF(SUM(r.item_receb_peso_nota_fiscal), 0) as mediaFazendaPonderada,
                SUM(r.item_receb_gram_lab * r.item_receb_peso_nota_fiscal) / NULLIF(SUM(r.item_receb_peso_nota_fiscal), 0) as mediaLabPonderada
              FROM tbl_lotes_novo_header l
              LEFT JOIN tbl_entidades c ON l.lote_cliente_id = c.ent_codigo
              LEFT JOIN tbl_entidades f ON l.lote_fornecedor_id = f.ent_codigo
              LEFT JOIN tbl_lote_novo_recebdetalhes r ON l.lote_id = r.item_receb_lote_id
              $whereClause";

        $stmtTotais = $this->pdo->prepare($sqlTotais);
       /* foreach ($queryParams as $key => $value) {
            $stmtTotais->bindValue($key, $value);
        }*/
        $stmtTotais->execute($queryParams);
        $totais = $stmtTotais->fetch(PDO::FETCH_ASSOC);

        // Definimos as variáveis que receberão os totais de peso e médias de gramaturas
        $totalPesoFiltrado = $totais['totalPesoFiltrado'] ?? 0;
        $mediaFazendaPonderada = $totais['mediaFazendaPonderada'] ?? 0;
        $mediaLabPonderada = $totais['mediaLabPonderada'] ?? 0;

        // 6. MAPEAMENTO DE COLUNAS PARA ORDENAÇÃO
        // A ordem aqui deve ser EXATAMENTE a mesma ordem das colunas no Javascript
        $columnsMap = [
            0 => 'l.lote_completo_calculado',
            1 => 'cliente_razao_social',    // Alias definido no SELECT abaixo
            2 => 'gramaturas_fazenda',      // Alias do group_concat (ordenação aqui é string)
            3 => 'gramaturas_laboratorio',  // Alias do group_concat
            4 => 'peso_total_nota',         // Alias da soma
            5 => 'l.lote_data_fabricacao',
            6 => 'l.lote_status'
        ];

        // Pega a ordenação enviada pelo DataTables (ou usa padrão)
        $colIndex = $params['order'][0]['column'] ?? 2; // Padrão: Data (índice 2)
        $dir = $params['order'][0]['dir'] ?? 'desc';    // Padrão: Decrescente

        // Proteção: Se o índice não existir no mapa, volta para data
        $orderBy = $columnsMap[$colIndex] ?? 'l.lote_data_fabricacao';

        // Validação simples de segurança para direção
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        // 7. Query Final com Agrupamento e Ordenação
        $sqlData = "SELECT l.*, 
                COALESCE(NULLIF(c.ent_nome_fantasia,''), c.ent_razao_social) AS cliente_razao_social, 
                GROUP_CONCAT(DISTINCT r.item_receb_gram_faz SEPARATOR ' / ') as gramaturas_fazenda,
                GROUP_CONCAT(DISTINCT r.item_receb_gram_lab SEPARATOR ' / ') as gramaturas_laboratorio,
                SUM(r.item_receb_peso_nota_fiscal) as peso_total_nota
                $baseQuery $whereClause 
                GROUP BY l.lote_id
                ORDER BY $orderBy $dir 
                LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);

        foreach ($queryParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($draw),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "totalPesoFiltrado" => (float) $totalPesoFiltrado,
            "mediaFazendaPonderada" => (float) $mediaFazendaPonderada,
            "mediaLabPonderada" => (float) $mediaLabPonderada,
            "data" => $data
        ];
    }


    /**
     * Busca um lote novo completo (cabeçalho, produção e embalagem) pelo seu ID.
     * @param int $id O ID do lote (da tbl_lotes_novo_header).
     * @return array|null
     */

    /* public function findAllForDataTable(array $params): array
    {
        // Log inicial para depuração (ver error_log ou arquivo de log)
        error_log("findAllForDataTable - Params Recebidos: " . json_encode($params));

        // 1. Parâmetros básicos do DataTables
        $draw = (int) ($params['draw'] ?? 1);
        $start = (int) ($params['start'] ?? 0);
        $length = (int) ($params['length'] ?? 10);
        $searchValue = trim($params['search']['value'] ?? '');

        // 2. Filtros Customizados (agora tratados!)
        $ano = !empty($params['ano']) ? (int) $params['ano'] : (int) date('Y');
        $meses = !empty($params['meses']) ? array_map('intval', (array) $params['meses']) : [];
        $situacoes = !empty($params['situacoes']) ? array_map('strtoupper', (array) $params['situacoes']) : ['ABERTO', 'FINALIZADO'];
        $fornecedores = !empty($params['fornecedores']) ? array_map('intval', (array) $params['fornecedores']) : [];

        // 3. Definição da Query Base e Joins (igual)
        $baseQuery = "FROM tbl_lotes_novo_header l         
                  LEFT JOIN tbl_entidades c ON l.lote_cliente_id = c.ent_codigo
                  LEFT JOIN tbl_entidades f ON l.lote_fornecedor_id = f.ent_codigo
                  LEFT JOIN tbl_lote_novo_recebdetalhes r ON l.lote_id = r.item_receb_lote_id";

        // 4. Contagem Total (Sem filtros) - igual, mas log
        $totalRecords = $this->pdo->query("SELECT COUNT(l.lote_id) FROM tbl_lotes_novo_header l")->fetchColumn();
        error_log("Total Records (sem filtro): " . $totalRecords);

        // 5. Construção do WHERE (agora com todos os filtros!)
        $where = [];
        $queryParams = [];

        // Filtro Ano
        if ($ano > 0) {
            $where[] = "YEAR(l.lote_data_fabricacao) = :ano";
            $queryParams[':ano'] = $ano;
        }
        // Filtro Meses (array)
        if (!empty($meses)) {
            $mesPlaceholders = [];
            foreach ($meses as $i => $mes) {
                $key = ":mes_{$i}";
                $mesPlaceholders[] = $key;
                $queryParams[$key] = (int)$mes;
            }
            $where[] = "MONTH(l.lote_data_fabricacao) IN (" . implode(', ', $mesPlaceholders) . ")";
        }

        // Filtro Situações (array)
        if (!empty($situacoes)) {
            $sitPlaceholders = [];
            foreach ($situacoes as $i => $sit) {
                $key = ":sit_{$i}";
                $sitPlaceholders[] = $key;
                $queryParams[$key] = $sit;
            }
            $where[] = "l.lote_status IN (" . implode(', ', $sitPlaceholders) . ")";
        }

        // Filtro Fornecedores (array)
        if (!empty($fornecedores)) {
            $fornPlaceholders = [];
            foreach ($fornecedores as $i => $forn) {
                $key = ":forn_{$i}";
                $fornPlaceholders[] = $key;
                $queryParams[$key] = (int)$forn;
            }
            $where[] = "l.lote_fornecedor_id IN (" . implode(', ', $fornPlaceholders) . ")";
        }

        // Filtro de Busca Global (refinado: use um :searchText e :searchNum)
        if (!empty($searchValue)) {
            $searchTerm = '%' . $searchValue . '%';
            $cleanVal = preg_replace('/[a-zA-Z\s]/', '', $searchValue);
            if (strpos($cleanVal, ',') !== false) {
                $cleanVal = str_replace('.', '', $cleanVal);
                $cleanVal = str_replace(',', '.', $cleanVal);
            }
            $searchNumeric = '%' . $cleanVal . '%';

            $where[] = "(l.lote_completo_calculado LIKE :searchText OR
                     l.lote_numero LIKE :searchText OR
                     c.ent_razao_social LIKE :searchText OR
                     c.ent_nome_fantasia LIKE :searchText OR
                     EXISTS (SELECT 1 FROM tbl_lote_novo_recebdetalhes sub_d WHERE sub_d.item_receb_lote_id = l.lote_id 
                             AND (sub_d.item_receb_gram_faz LIKE :searchNum OR sub_d.item_receb_gram_lab LIKE :searchNum OR 
                                  sub_d.item_receb_peso_nota_fiscal LIKE :searchNum)) OR
                     (SELECT SUM(sub_s.item_receb_peso_nota_fiscal) FROM tbl_lote_novo_recebdetalhes sub_s 
                      WHERE sub_s.item_receb_lote_id = l.lote_id) LIKE :searchNum OR
                     DATE_FORMAT(l.lote_data_fabricacao, '%d/%m/%Y') LIKE :searchText)";

            $queryParams[':searchText'] = $searchTerm;
            $queryParams[':searchNum'] = $searchNumeric;
        }

        $whereClause = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

        // 6. Contagem dos Filtrados (agora com filtros customizados)
        //$sqlFiltered = "SELECT COUNT(DISTINCT l.lote_id) $baseQuery $whereClause";
        // $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        //$stmtFiltered->execute($queryParams); // Agora usa os params de filtros
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(DISTINCT l.lote_id) $baseQuery $whereClause");
        $stmtFiltered->execute($queryParams);  // Agora OK!
        $totalFiltered = $stmtFiltered->fetchColumn();
        error_log("Filtered Records: " . $totalFiltered . " | Where: " . $whereClause);

        // 7. Ordenação (igual, mas log)
        $columnsMap = [
            0 => 'l.lote_completo_calculado',
            1 => 'cliente_razao_social',
            2 => 'gramaturas_fazenda',
            3 => 'gramaturas_laboratorio',
            4 => 'peso_total_nota',
            5 => 'l.lote_data_fabricacao',
            6 => 'l.lote_status'
        ];
        $colIndex = $params['order'][0]['column'] ?? 5; // Padrão: Data (índice 5 no seu JS)
        $dir = strtoupper($params['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $orderBy = $columnsMap[$colIndex] ?? 'l.lote_data_fabricacao';
        error_log("Ordenação: $orderBy $dir");

        // 8. Query Final (igual, mas com whereClause completo)
        $sqlData = "SELECT l.*, 
                COALESCE(NULLIF(c.ent_nome_fantasia,''), c.ent_razao_social) AS cliente_razao_social, 
                GROUP_CONCAT(DISTINCT r.item_receb_gram_faz SEPARATOR ' / ') as gramaturas_fazenda,
                GROUP_CONCAT(DISTINCT r.item_receb_gram_lab SEPARATOR ' / ') as gramaturas_laboratorio,
                SUM(r.item_receb_peso_nota_fiscal) as peso_total_nota
                $baseQuery $whereClause 
                GROUP BY l.lote_id
                ORDER BY $orderBy $dir 
                LIMIT :start, :length";

        error_log("Query Final: " . $sqlData); // Log da query para depuração

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute($queryParams);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 9. Cálculo dos Campos Extras (novo: para cards no JS)
        $sqlExtras = "SELECT 
                    SUM(peso_total_nota) AS totalPesoFiltrado,
                    SUM(peso_total_nota * gram_faz_media) / NULLIF(SUM(peso_total_nota), 0) AS mediaFazendaPonderada,
                    SUM(peso_total_nota * gram_lab_media) / NULLIF(SUM(peso_total_nota), 0) AS mediaLabPonderada
                  FROM (
                      SELECT l.lote_id,
                             SUM(r.item_receb_peso_nota_fiscal) AS peso_total_nota,
                             AVG(r.item_receb_gram_faz) AS gram_faz_media,
                             AVG(r.item_receb_gram_lab) AS gram_lab_media
                      $baseQuery $whereClause
                      GROUP BY l.lote_id
                  ) AS aggregated";
        $stmtExtras = $this->pdo->prepare($sqlExtras);
        $stmtExtras->execute($queryParams);
        $extras = $stmtExtras->fetch(PDO::FETCH_ASSOC);
        error_log("Extras Calculados: " . json_encode($extras));

        // 10. Retorno Final (agora com extras)
        return [
            'draw' => $draw,
            'recordsTotal' => (int) $totalRecords,
            'recordsFiltered' => (int) $totalFiltered,
            'data' => $data,
            'totalPesoFiltrado' => (float) ($extras['totalPesoFiltrado'] ?? 0),
            'mediaFazendaPonderada' => (float) ($extras['mediaFazendaPonderada'] ?? 0),
            'mediaLabPonderada' => (float) ($extras['mediaLabPonderada'] ?? 0)
        ];
    } */

    public function findLoteNovoCompleto(int $id): ?array
    {
        // 1. Busca o cabeçalho
        $headerStmt = $this->pdo->prepare("SELECT * FROM tbl_lotes_novo_header WHERE lote_id = :id");
        $headerStmt->execute([':id' => $id]);
        $header = $headerStmt->fetch(PDO::FETCH_ASSOC);

        if (!$header)
            return null;

        // 2. Busca os itens de produção
        $producaoStmt = $this->pdo->prepare(
            "SELECT p.*, prod.prod_descricao, prod.prod_unidade
             FROM tbl_lotes_novo_producao p 
             JOIN tbl_produtos prod ON p.item_prod_produto_id = prod.prod_codigo 
             WHERE p.item_prod_lote_id = :id ORDER BY prod.prod_descricao"
        );
        $producaoStmt->execute([':id' => $id]);
        $producao = $producaoStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Busca os itens de embalagem (faremos no futuro, por agora fica vazio)
        $embalagem = [];

        return [
            'header' => $header,
            'producao' => $producao,
            'embalagem' => $embalagem
        ];
    }

    /**
     * Busca todos os itens de embalagem de um lote para exibição na tabela.
     *
     * @param int $loteId O ID do lote.
     * @return array
     */
    public function findEmbalagemByLoteId(int $loteId): array
    {
        $sql = "SELECT 
                emb.*,
                p_sec.prod_descricao AS produto_secundario_nome,
                p_prim.prod_descricao AS produto_primario_nome
            FROM tbl_lotes_novo_embalagem AS emb
            -- Join para buscar o nome do produto secundário (a embalagem)
            JOIN tbl_produtos AS p_sec ON emb.item_emb_prod_sec_id = p_sec.prod_codigo
            -- Join para buscar o item de produção de onde o saldo foi consumido
            JOIN tbl_lotes_novo_producao AS lnp ON emb.item_emb_prod_prim_id = lnp.item_prod_id
            -- Join para buscar o nome do produto primário original
            JOIN tbl_produtos AS p_prim ON lnp.item_prod_produto_id = p_prim.prod_codigo
            WHERE emb.item_emb_lote_id = :lote_id
            ORDER BY emb.item_emb_id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_id' => $loteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula o próximo número sequencial para um novo lote.
     * @return string O próximo número formatado com 4 dígitos.
     */
    public function getNextNumero(): string
    {
        $stmt = $this->pdo->query("SELECT MAX(CAST(lote_numero AS UNSIGNED)) FROM tbl_lotes_novo_header");
        $proximo_numero = ($stmt->fetchColumn() ?: 0) + 1;
        return str_pad($proximo_numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Busca os itens de embalagem de um lote que ainda têm saldo para serem finalizados.
     *
     * @param int $loteId O ID do lote.
     * @return array
     */
    public function getItensParaFinalizar(int $loteId): array
    {
        $sql = "SELECT 
                emb.item_emb_id,
                p.prod_descricao,
                emb.item_emb_qtd_sec AS quantidade_total,
                emb.item_emb_qtd_finalizada AS quantidade_ja_finalizada,
                (emb.item_emb_qtd_sec - emb.item_emb_qtd_finalizada) AS quantidade_disponivel
            FROM tbl_lotes_novo_embalagem AS emb
            JOIN tbl_produtos AS p ON emb.item_emb_prod_sec_id = p.prod_codigo
            WHERE 
                emb.item_emb_lote_id = :lote_id 
                AND emb.item_emb_qtd_sec > emb.item_emb_qtd_finalizada";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_id' => $loteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gera um resumo do lote para a tela de finalização.
     * Retorna itens de embalagem e itens de sobra (matéria-prima não consumida).
     */
    public function getResumoFinalizacao(int $loteId): array
    {
        // 1. EMBALAGENS (Produtos Secundários Gerados)
        $sqlEmb = "SELECT 
                    p_sec.prod_codigo_interno,
                    p_sec.prod_descricao,
                    p_sec.prod_unidade,
                    SUM(emb.item_emb_qtd_sec) as total_cxs_sec,
                    SUM(emb.item_emb_qtd_prim_cons) as total_prim_consumido
                FROM tbl_lotes_novo_embalagem emb
                JOIN tbl_produtos p_sec ON emb.item_emb_prod_sec_id = p_sec.prod_codigo
                WHERE emb.item_emb_lote_id = :lote_id
                GROUP BY p_sec.prod_codigo";

        $stmtEmb = $this->pdo->prepare($sqlEmb);
        $stmtEmb->execute([':lote_id' => $loteId]);
        $embalagens = $stmtEmb->fetchAll(PDO::FETCH_ASSOC);

        // 2. SOBRAS (Produtos Primários com Saldo Positivo)
        // Isso pega o que não foi usado em nenhuma embalagem (ou o resto parcial)
        $sqlSobras = "SELECT 
                        p.prod_codigo_interno,
                        p.prod_descricao,
                        p.prod_unidade,
                        SUM(lnp.item_prod_saldo) as total_sobra_liquida
                      FROM tbl_lotes_novo_producao lnp
                      JOIN tbl_produtos p ON lnp.item_prod_produto_id = p.prod_codigo
                      WHERE lnp.item_prod_lote_id = :lote_id 
                        AND lnp.item_prod_saldo > 0.001
                      GROUP BY p.prod_codigo";

        $stmtSobras = $this->pdo->prepare($sqlSobras);
        $stmtSobras->execute([':lote_id' => $loteId]);
        $sobras = $stmtSobras->fetchAll(PDO::FETCH_ASSOC);

        // 3. Totais Gerais para o Rodapé
        $sqlTotais = "SELECT 
                        SUM(item_prod_quantidade) as total_produzido,
                        SUM(item_prod_saldo) as total_sobras
                    FROM tbl_lotes_novo_producao 
                    WHERE item_prod_lote_id = :lote_id";
        $stmtTotais = $this->pdo->prepare($sqlTotais);
        $stmtTotais->execute([':lote_id' => $loteId]);
        $totaisGerais = $stmtTotais->fetch(PDO::FETCH_ASSOC);

        return [
            'embalagens' => $embalagens,
            'sobras' => $sobras,
            'totais' => $totaisGerais
        ];
    }

    /**
     * Atualiza apenas o status do lote (Finalização Gerencial).
     */
    public function atualizarStatusLote(int $loteId, string $novoStatus, int $usuarioId): bool
    {
        // Valida status permitidos
        if (!in_array($novoStatus, ['FINALIZADO', 'PARCIALMENTE FINALIZADO'])) {
            throw new Exception("Status inválido.");
        }

        $sql = "UPDATE tbl_lotes_novo_header SET lote_status = :status";

        // Se for finalizado, grava a data de hoje. Se for parcial, não mexe na data 
        if ($novoStatus === 'FINALIZADO') {
            $sql .= ", lote_data_finalizacao = NOW()";
        }

        $sql .= " WHERE lote_id = :id";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([':status' => $novoStatus, ':id' => $loteId]);

        if ($success) {
            $this->auditLogger->log(
                'UPDATE',
                $loteId,
                'tbl_lotes_novo_header',
                null,
                ['status' => $novoStatus],
                "Alteração de status via Finalização Gerencial"
            );

            // Se o status mudou para FINALIZADO, registramos a entrada oficial no estoque.
            if ($novoStatus === 'FINALIZADO') {
                $this->registrarEntradaProducaoKardex($loteId, $usuarioId);
            }
            // -----------------------------------------------
        }
        return $success;
    }

    /**
     * Busca os detalhes completos de um único item de lote do novo sistema.
     * @param int $loteItemId ID do item da tabela tbl_lotes_novo_embalagem
     * @return array|null
     */

    public function getVisaoGeralEstoque(array $params): array
    {
        // Parâmetros do DataTables
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        // --- MUDANÇA PRINCIPAL AQUI ---
        // Em vez de olhar tabela de movimento (tbl_estoque), olhamos a tabela de SALDO ATUAL (alocacoes)
        // Agrupamos tudo o que está alocado, ignorando o endereço específico.
        $baseQuery = "
        FROM tbl_estoque_alocacoes ea
        JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
        JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
        JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
        LEFT JOIN tbl_entidades e ON lnh.lote_cliente_id = e.ent_codigo
    ";

        // Agrupamos por Produto e Lote. 
        // Assim, se tiver 10 caixas na Camara 1 e 20 na Camara 2, ele vai mostrar uma linha só com 30 caixas.
        $groupByClause = "GROUP BY p.prod_codigo, lnh.lote_id";

        $whereClause = "";
        $queryParams = [];

        // --- FILTROS DE PESQUISA ---
        if (!empty($searchValue)) {
            $searchableColumns = [
                'p.prod_descricao',
                'lnh.lote_completo_calculado',
                'p.prod_codigo_interno',
                'COALESCE(NULLIF(e.ent_nome_fantasia, \'\'), e.ent_razao_social)'
            ];
            $searchConditions = [];

            foreach ($searchableColumns as $index => $column) {
                $placeholder = ":search{$index}";
                $searchConditions[] = "{$column} LIKE {$placeholder}";
                $queryParams[$placeholder] = '%' . $searchValue . '%';
            }

            $whereClause = "WHERE (" . implode(' OR ', $searchConditions) . ")";
        }

        // --- CONTAGENS (Obrigatório para o DataTables) ---

        // Total sem filtro
        $totalRecordsQuery = "SELECT COUNT(*) FROM (SELECT 1 {$baseQuery} {$groupByClause}) as subquery";
        $totalRecords = $this->pdo->query($totalRecordsQuery)->fetchColumn();

        // Total com filtro
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(*) FROM (SELECT 1 {$baseQuery} {$whereClause} {$groupByClause}) as subquery");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // --- BUSCA DOS DADOS ---
        $sqlData = "
        SELECT 
            p.prod_tipo as tipo_produto,
            p.prod_subtipo as subtipo,
            p.prod_classificacao as classificacao,
            p.prod_codigo_interno as codigo_interno,
            p.prod_descricao as descricao_produto,
            p.prod_peso_embalagem as peso_embalagem,
            lnh.lote_completo_calculado as lote,
            COALESCE(NULLIF(e.ent_nome_fantasia, ''), e.ent_razao_social) as cliente_lote_nome,
            lnh.lote_data_fabricacao as data_fabricacao,
            
            -- AQUI ESTÁ A MÁGICA: Somamos a quantidade alocada
            SUM(ea.alocacao_quantidade) as total_caixas,
            
            -- Calculamos o peso total baseado na soma
            (SUM(ea.alocacao_quantidade) * p.prod_peso_embalagem) as peso_total
        
        {$baseQuery}
        {$whereClause}
        {$groupByClause}

        -- Ordenação Padrão (Pode ser melhorada para pegar o order do DataTables)
        ORDER BY p.prod_descricao ASC, lnh.lote_completo_calculado ASC
        LIMIT :start, :length
    ";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);

        foreach ($queryParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => (int) $draw,
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data
        ];
    }
    public function findLoteNovoItemDetalhes(int $loteItemId): ?array
    {
        $sql = "
        SELECT
            lne.item_emb_id as lote_item_id,
            p.prod_codigo as produto_id,
            p.prod_descricao,
            p.prod_codigo_interno,
            CONCAT('Lote: ', lnh.lote_completo_calculado, ' - Saldo: ', CAST((lne.item_emb_qtd_sec - lne.item_emb_qtd_finalizada) AS DECIMAL(10,3))) as lote_texto
        FROM tbl_lotes_novo_embalagem lne
        JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
        JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
        WHERE lne.item_emb_id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $loteItemId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Conta lotes por um status específico.
     * Usado pelo KPI "Lotes em Produção".
     */
    public function countByStatus(string $status): int
    {
        $sqlStatus = ($status === 'Aberto') ? 'EM ANDAMENTO' : $status;
        $stmt = $this->pdo->prepare("SELECT COUNT(lote_id) FROM tbl_lotes_novo_header WHERE lote_status = :status");
        $stmt->execute([':status' => $sqlStatus]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna a contagem de lotes finalizados por dia nos últimos X dias.
     * Usado pelo gráfico do dashboard.
     */
    public function getDailyFinalizedCountForLastDays(int $days = 7): array
    {
        // Cria um array de datas para os últimos 7 dias no formato 'd/m'
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('d/m', strtotime("-$i days"));
        }

        // Query que agrupa os lotes finalizados por dia
        $sql = "SELECT DATE_FORMAT(lote_data_finalizacao, '%d/%m') AS dia, COUNT(lote_id) AS total
                FROM tbl_lotes_novo_header
                WHERE lote_data_finalizacao >= CURDATE() - INTERVAL :days DAY
                AND lote_status = 'FINALIZADO'
                GROUP BY dia
                ORDER BY lote_data_finalizacao ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Cria um array [ 'dia' => total ]

        // Combina os resultados com o array de labels para garantir que dias sem lotes tenham valor 0
        $data = [];
        foreach ($labels as $label) {
            $data[] = $results[$label] ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Busca lotes ativos para os painéis dos dashboards.
     */
    public function findActiveLots(int $limit = 5): array
    {
        $sql = "SELECT lote_id, lote_completo_calculado, lote_status, lote_data_cadastro 
                FROM tbl_lotes_novo_header 
                WHERE lote_status = 'EM ANDAMENTO' 
                ORDER BY lote_data_cadastro ASC 
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca os lotes finalizados mais recentemente para o painel de produção.
     */
    public function findRecentlyFinishedLots(int $limit = 5): array
    {
        $sql = "SELECT lote_id, lote_completo_calculado, lote_data_finalizacao 
                FROM tbl_lotes_novo_header 
                WHERE lote_status = 'FINALIZADO' 
                ORDER BY lote_data_finalizacao DESC 
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSaldosDeProducaoFinalizados(): array
    {
        $sql = "SELECT 
                lnp.item_prod_id, 
                p.prod_descricao,
                p.prod_codigo as produto_id, 
                lnh.lote_completo_calculado, 
                lnh.lote_id,
                lnp.item_prod_saldo,
                COALESCE(f.ent_razao_social, f.ent_nome_fantasia) AS fornecedor_nome,
                lnh.lote_data_fabricacao
            FROM tbl_lotes_novo_producao lnp
            JOIN tbl_lotes_novo_header lnh ON lnp.item_prod_lote_id = lnh.lote_id
            JOIN tbl_produtos p ON lnp.item_prod_produto_id = p.prod_codigo
            LEFT JOIN tbl_entidades f ON lnh.lote_fornecedor_id = f.ent_codigo
            WHERE 
                lnh.lote_status = 'FINALIZADO' 
                AND lnp.item_prod_saldo > 0.001
            ORDER BY lnh.lote_completo_calculado, p.prod_descricao";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca lotes que estão "EM ANDAMENTO" para serem usados como destino de caixas mistas.
     * Formatado para o Select2.
     *
     * @return array
     */
    public function findOpenLotsForSelect(): array
    {
        $sql = "SELECT 
                lote_id AS id,
                CONCAT(lote_completo_calculado, ' (Data: ', DATE_FORMAT(lote_data_fabricacao, '%d/%m/%Y'), ')') AS text
            FROM tbl_lotes_novo_header
            WHERE lote_status = 'EM ANDAMENTO'
            ORDER BY lote_data_cadastro DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria uma nova Caixa Mista.
     * Esta é uma transação complexa que:
     * 1. Valida o saldo de todas as sobras selecionadas (itens de produção).
     * 2. Cria o cabeçalho da Caixa Mista (tbl_caixas_mistas_header).
     * 3. Consome (baixa) o saldo das sobras (UPDATE tbl_lotes_novo_producao).
     * 4. Registra os itens consumidos na "receita" (INSERT tbl_caixas_mistas_itens).
     * 5. Cria o novo item de embalagem (INSERT tbl_lotes_novo_embalagem) no Lote de Destino.
     * 6. Vincula o novo item de embalagem ao cabeçalho da caixa mista.
     *
     * @param array $data Dados do formulário (produto_final_id, lote_destino_id, e o array de itens)
     * @param int $usuarioId ID do usuário logado
     * @return int O ID do NOVO item de embalagem (item_emb_id) gerado, para impressão da etiqueta.
     * @throws Exception
     */
    public function criarCaixaMista(array $data, int $usuarioId): int
    {
        // 1. Validação de dados de entrada
        $produtoFinalId = filter_var($data['mista_produto_final_id'], FILTER_VALIDATE_INT);
        $loteDestinoId = filter_var($data['mista_lote_destino_id'], FILTER_VALIDATE_INT);
        $itensConsumo = $data['itens'] ?? [];

        if (!$produtoFinalId || !$loteDestinoId || empty($itensConsumo)) {
            throw new Exception("Dados insuficientes. Produto Final, Lote de Destino e Itens são obrigatórios.");
        }

        $this->pdo->beginTransaction();
        try {
            // 2. Loop de Validação (Lock e Verificação de Saldo)
            // Prepara a query de validação uma vez
            $stmtCheckSaldo = $this->pdo->prepare(
                "SELECT item_prod_saldo FROM tbl_lotes_novo_producao WHERE item_prod_id = :id FOR UPDATE"
            );

            foreach ($itensConsumo as $item) {
                $itemIdSobra = filter_var($item['item_id'], FILTER_VALIDATE_INT);
                $qtdConsumir = (float) $item['quantidade'];

                $stmtCheckSaldo->execute([':id' => $itemIdSobra]);
                $saldoDisponivel = (float) $stmtCheckSaldo->fetchColumn();

                if ($qtdConsumir > $saldoDisponivel) {
                    throw new Exception("Saldo insuficiente para o item ID {$itemIdSobra}. Disponível: {$saldoDisponivel}, Solicitado: {$qtdConsumir}.");
                }
            }

            // 3. Cria o Cabeçalho da Caixa Mista (Tabela 1)
            $stmtHeader = $this->pdo->prepare(
                "INSERT INTO tbl_caixas_mistas_header 
                (mista_produto_final_id, mista_lote_destino_id, mista_usuario_id) 
             VALUES (:prod_final_id, :lote_destino_id, :user_id)"
            );
            $stmtHeader->execute([
                ':prod_final_id' => $produtoFinalId,
                ':lote_destino_id' => $loteDestinoId,
                ':user_id' => $usuarioId
            ]);
            $novoMistaHeaderId = (int) $this->pdo->lastInsertId();

            // Prepara queries para o Loop 2
            $stmtConsumirSobra = $this->pdo->prepare(
                "UPDATE tbl_lotes_novo_producao SET item_prod_saldo = item_prod_saldo - :qtd WHERE item_prod_id = :id"
            );
            $stmtRegistrarItemMisto = $this->pdo->prepare(
                "INSERT INTO tbl_caixas_mistas_itens (item_mista_header_id, item_prod_origem_id, item_mista_qtd_consumida)
             VALUES (:header_id, :origem_id, :qtd)"
            );

            // 4. Loop de Execução (Consumo e Registro)
            foreach ($itensConsumo as $item) {
                $itemIdSobra = $item['item_id'];
                $qtdConsumir = (float) $item['quantidade'];

                // 4a. Consome o saldo da sobra
                $stmtConsumirSobra->execute([':qtd' => $qtdConsumir, ':id' => $itemIdSobra]);

                // 4b. Registra na "receita"
                $stmtRegistrarItemMisto->execute([
                    ':header_id' => $novoMistaHeaderId,
                    ':origem_id' => $itemIdSobra,
                    ':qtd' => $qtdConsumir
                ]);
            }

            // 5. Cria o novo item de Embalagem (a Caixa Mista física para o estoque)
            $stmtCriarPacote = $this->pdo->prepare(
                "INSERT INTO tbl_lotes_novo_embalagem 
                (item_emb_lote_id, item_emb_prod_sec_id, item_emb_qtd_sec, item_emb_prod_prim_id) 
             VALUES (:lote_destino, :prod_sec_id, 1, NULL)"
            );
            $stmtCriarPacote->execute([
                ':lote_destino' => $loteDestinoId,
                ':prod_sec_id' => $produtoFinalId
            ]);
            $novoItemEmbIdGerado = (int) $this->pdo->lastInsertId(); // Este é o ID da etiqueta!

            // 6. Vincula o novo item de embalagem ao cabeçalho da caixa mista
            $stmtLink = $this->pdo->prepare(
                "UPDATE tbl_caixas_mistas_header SET mista_item_embalagem_id_gerado = :item_emb_id WHERE mista_id = :mista_id"
            );
            $stmtLink->execute([
                ':item_emb_id' => $novoItemEmbIdGerado,
                ':mista_id' => $novoMistaHeaderId
            ]);

            // Registra a entrada dessa nova caixa mista no estoque
            $this->movimentoRepo->registrar(
                'PRODUCAO',                             // Tipo
                $novoItemEmbIdGerado,             // ID do Item (Caixa Mista)
                1,                                // Quantidade (Geralmente 1 caixa mista fechada)
                $usuarioId,                        // Usuário
                null,                               // Origem
                null,                              // Destino
                'Caixa Mista criada a partir de sobras',
                $loteDestinoId                        // Lote Referência
            );

            // 7. Auditoria
            $this->auditLogger->log(
                'CREATE',
                $novoMistaHeaderId,
                'tbl_caixas_mistas_header',
                null,
                $data,
                'Criação de Caixa Mista'
            );

            $this->pdo->commit();

            // 8. Retorna o ID da etiqueta
            return $novoItemEmbIdGerado;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e; // Lança o erro para o Controller (AJAX Router)
        }
    }

    /**
     * Busca todas as caixas mistas para exibição no DataTables.
     */
    public function findAllCaixasMistasForDataTable(array $params): array
    {
        try {
            $draw = (int) ($params['draw'] ?? 1);
            $start = (int) ($params['start'] ?? 0);
            $length = (int) ($params['length'] ?? 10);
            $searchValue = trim($params['search']['value'] ?? '');

            // Query base para COUNT e SELECT (sem WHERE para total)
            $totalStmt = $this->pdo->query("SELECT COUNT(mh.mista_id) FROM tbl_caixas_mistas_header mh");
            $totalRecords = (int) $totalStmt->fetchColumn();

            $whereClause = '';
            $searchBindings = [];
            if (!empty($searchValue)) {
                $whereClause = " WHERE p.prod_descricao LIKE :search_produto OR lh.lote_completo_calculado LIKE :search_lote";
                $searchBindings = [
                    ':search_produto' => '%' . $searchValue . '%',
                    ':search_lote' => '%' . $searchValue . '%'
                ];
            }

            // COUNT filtrado
            $filteredSql = "SELECT COUNT(mh.mista_id) FROM tbl_caixas_mistas_header mh
                        JOIN tbl_lotes_novo_header lh ON mh.mista_lote_destino_id = lh.lote_id
                        JOIN tbl_produtos p ON mh.mista_produto_final_id = p.prod_codigo
                        LEFT JOIN tbl_entidades f ON lh.lote_fornecedor_id = f.ent_codigo
                        LEFT JOIN tbl_entidades c ON lh.lote_cliente_id = c.ent_codigo" . $whereClause;
            $filteredStmt = $this->pdo->prepare($filteredSql);
            $filteredStmt->execute($searchBindings);
            $totalFiltered = (int) $filteredStmt->fetchColumn();

            // SELECT com dados
            $dataSql = "SELECT 
                        mh.mista_id,
                        p.prod_descricao AS produto_final,
                        lh.lote_completo_calculado AS lote_destino,
                        mh.mista_data_criacao AS data_criacao,
                        COALESCE((SELECT SUM(mi.item_mista_qtd_consumida) FROM tbl_caixas_mistas_itens mi WHERE mi.item_mista_header_id = mh.mista_id), 0) AS total_qtd_consumida,
                        mh.mista_item_embalagem_id_gerado
                    FROM tbl_caixas_mistas_header mh
                    JOIN tbl_lotes_novo_header lh ON mh.mista_lote_destino_id = lh.lote_id
                    JOIN tbl_produtos p ON mh.mista_produto_final_id = p.prod_codigo
                    LEFT JOIN tbl_entidades f ON lh.lote_fornecedor_id = f.ent_codigo
                    LEFT JOIN tbl_entidades c ON lh.lote_cliente_id = c.ent_codigo" . $whereClause . "
                    ORDER BY mh.mista_data_criacao DESC
                    LIMIT :start, :length";

            $dataStmt = $this->pdo->prepare($dataSql);
            $dataStmt->bindValue(':start', $start, PDO::PARAM_INT);
            $dataStmt->bindValue(':length', $length, PDO::PARAM_INT);
            foreach ($searchBindings as $key => $value) {
                $dataStmt->bindValue($key, $value);
            }
            $dataStmt->execute();
            $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];  // Garante array vazio se null/false

            error_log("DataTables Response: recordsTotal={$totalRecords}, recordsFiltered={$totalFiltered}, data_count=" . count($data));  // Log para debug

            return [
                "draw" => $draw,
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $totalFiltered,
                "data" => $data
            ];
        } catch (\PDOException $e) {
            error_log('PDO Error em findAllCaixasMistasForDataTable: ' . $e->getMessage() . ' | Query: ' . $dataSql ?? 'N/A');
            return [
                "draw" => (int) ($params['draw'] ?? 1),
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => 'Erro no banco: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log('General Error em findAllCaixasMistasForDataTable: ' . $e->getMessage());
            return [
                "draw" => (int) ($params['draw'] ?? 1),
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca os detalhes dos itens consumidos de uma caixa mista específica.
     */
    public function getDetalhesCaixaMista(int $mistaId): array
    {
        try {
            $sql = "SELECT 
                    p.prod_descricao,
                    lh.lote_completo_calculado,
                    mi.item_mista_qtd_consumida AS qtd_consumida
                FROM tbl_caixas_mistas_itens mi
                JOIN tbl_lotes_novo_producao lp ON mi.item_prod_origem_id = lp.item_prod_id
                JOIN tbl_produtos p ON lp.item_prod_produto_id = p.prod_codigo
                JOIN tbl_lotes_novo_header lh ON lp.item_prod_lote_id = lh.lote_id
                WHERE mi.item_mista_header_id = :mista_id
                ORDER BY p.prod_descricao ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':mista_id' => $mistaId]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return [
                'success' => true,
                'itens' => $itens,
                'total_itens' => count($itens)
            ];
        } catch (\PDOException $e) {
            error_log('PDO Error em getDetalhesCaixaMista: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro no banco ao buscar detalhes: ' . $e->getMessage(),
                'itens' => []
            ];
        } catch (Exception $e) {
            error_log('General Error em getDetalhesCaixaMista: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro interno ao buscar detalhes: ' . $e->getMessage(),
                'itens' => []
            ];
        }
    }

    /**
     * Exclui uma caixa mista e reverte o saldo consumido de volta para os itens de produção de origem.
     * Esta é uma operação crítica e é feita dentro de uma transação.
     *
     * @param int $mistaId O ID da caixa mista (da tabela tbl_caixas_mistas_header).
     * @return bool Retorna true se a operação for bem-sucedida.
     * @throws Exception Se a caixa mista não for encontrada ou se houver um erro no processo.
     */
    public function excluirCaixaMista(int $mistaId, int $usuarioId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Busca os detalhes dos itens consumidos para reverter o saldo
            $stmtItens = $this->pdo->prepare(
                "SELECT item_prod_origem_id, item_mista_qtd_consumida FROM tbl_caixas_mistas_itens WHERE item_mista_header_id = :mista_id"
            );
            $stmtItens->execute([':mista_id' => $mistaId]);
            $itensConsumidos = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            if (empty($itensConsumidos)) {
                // Se não há itens consumidos, só remove o cabeçalho e o item de embalagem gerado
                $this->pdo->rollBack();
                return $this->deleteCaixaMistaWithoutReversion($mistaId);
            }

            // 2. Itera sobre os itens consumidos e reverte o saldo
            $stmtReverterSaldo = $this->pdo->prepare(
                "UPDATE tbl_lotes_novo_producao SET item_prod_saldo = item_prod_saldo + :qtd WHERE item_prod_id = :id"
            );
            foreach ($itensConsumidos as $item) {
                $origemId = $item['item_prod_origem_id'];
                $qtdConsumida = $item['item_mista_qtd_consumida'];
                $stmtReverterSaldo->execute([
                    ':qtd' => $qtdConsumida,
                    ':id' => $origemId
                ]);
            }

            // 3. Exclui o item de embalagem que foi gerado
            $stmtItemEmbId = $this->pdo->prepare("SELECT mista_item_embalagem_id_gerado FROM tbl_caixas_mistas_header WHERE mista_id = :mista_id");
            $stmtItemEmbId->execute([':mista_id' => $mistaId]);
            $itemEmbIdGerado = $stmtItemEmbId->fetchColumn();

            if ($itemEmbIdGerado) {
                // =================================================================================
                // KARDEX: Saída por Desmonte/Exclusão (ESTORNO) 
                // =================================================================================
                // Tiramos a caixa do estoque antes de apagá-la do banco
                $this->movimentoRepo->registrar(
                    'SAIDA',                        // Tipo: Saiu do estoque
                    $itemEmbIdGerado,         // O Item
                    1,                        // Quantidade
                    $usuarioId,                // Usuário 
                    null,
                    null,
                    'Exclusão/Desmonte de Caixa Mista',
                    null
                );
                // =================================================================================

                $stmtDeleteEmb = $this->pdo->prepare("DELETE FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id");
                $stmtDeleteEmb->execute([':id' => $itemEmbIdGerado]);
            }

            // 4. Exclui os itens da caixa mista e o cabeçalho em cascata
            // As chaves estrangeiras com ON DELETE CASCADE na tabela `tbl_caixas_mistas_itens` e `tbl_caixas_mistas_header`
            // devem lidar com a exclusão dos itens automaticamente. Mas faremos a exclusão explícita por segurança.
            $stmtDeleteItens = $this->pdo->prepare("DELETE FROM tbl_caixas_mistas_itens WHERE item_mista_header_id = :mista_id");
            $stmtDeleteItens->execute([':mista_id' => $mistaId]);

            $stmtDeleteHeader = $this->pdo->prepare("DELETE FROM tbl_caixas_mistas_header WHERE mista_id = :mista_id");
            $stmtDeleteHeader->execute([':mista_id' => $mistaId]);

            // 5. Log de auditoria (precisa buscar os dados antigos antes da exclusão)
            $this->auditLogger->log('DELETE', $mistaId, 'tbl_caixas_mistas_header', ['mista_id' => $mistaId, 'itens' => $itensConsumidos], null, 'Exclusão de Caixa Mista e reversão de saldos.');

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e; // Relança a exceção para que o controlador possa capturá-la
        }
    }

    /**
     * Função auxiliar para deletar uma caixa mista que não tem itens consumidos.
     * @param int $mistaId
     * @return bool
     * @throws Exception
     */
    private function deleteCaixaMistaWithoutReversion(int $mistaId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmtItemEmbId = $this->pdo->prepare("SELECT mista_item_embalagem_id_gerado FROM tbl_caixas_mistas_header WHERE mista_id = :mista_id");
            $stmtItemEmbId->execute([':mista_id' => $mistaId]);
            $itemEmbIdGerado = $stmtItemEmbId->fetchColumn();

            if ($itemEmbIdGerado) {
                $stmtDeleteEmb = $this->pdo->prepare("DELETE FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id");
                $stmtDeleteEmb->execute([':id' => $itemEmbIdGerado]);
            }

            $stmtDeleteHeader = $this->pdo->prepare("DELETE FROM tbl_caixas_mistas_header WHERE mista_id = :mista_id");
            $stmtDeleteHeader->execute([':mista_id' => $mistaId]);

            $this->auditLogger->log(
                'DELETE',
                $mistaId,
                'tbl_caixas_mistas_header',
                ['mista_id' => $mistaId],
                null,
                'Exclusão de Caixa Mista sem itens de origem.'
            );

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Gera um relatório completo dos lotes com dados de Produção, Cliente, Fornecedor e status da Ficha Técnica.
     * @return array
     * @throws Exception
     */
    public function getRelatorioLotesCompleto(): array
    {
        $sql = "
            SELECT
                lote.lote_numero AS 'Numero Lote',
                lote.lote_data_fabricacao AS 'Data de Fabricacao',
                prod.item_prod_data_validade AS 'Data de Validade',
                cli.ent_razao_social AS 'Cliente',
                forn.ent_razao_social AS 'Fornecedor / Fazenda',
                lote.lote_viveiro AS 'Viveiro',
                produt.prod_descricao AS 'Descricao Produto',
                CASE 
                    WHEN ficha.ficha_id IS NOT NULL THEN 'COM FICHA TECNICA' 
                    ELSE 'SEM FICHA TECNICA' 
                END AS 'Ficha Tecnica'
            FROM
                tbl_lotes_novo_header lote
            INNER JOIN
                tbl_lotes_novo_producao prod ON lote.lote_id = prod.item_prod_lote_id
            INNER JOIN
                tbl_produtos produt ON prod.item_prod_produto_id = produt.prod_codigo
            LEFT JOIN
                tbl_entidades cli ON lote.lote_cliente_id = cli.ent_codigo
            LEFT JOIN
                tbl_entidades forn ON lote.lote_fornecedor_id = forn.ent_codigo
            LEFT JOIN
                tbl_fichas_tecnicas ficha ON produt.prod_codigo = ficha.ficha_produto_id
            ORDER BY
                lote.lote_numero DESC, prod.item_prod_data_validade ASC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            // Retorna todos os resultados como um array associativo
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {

            // Registramos o erro e lançamos uma exceção genérica.
            error_log("Erro no SQL do Relatório de Lotes: " . $e->getMessage());
            throw new Exception("Ocorreu um erro ao gerar o relatório. Detalhes: " . $e->getMessage());
        }
    }

    /**
     * Gera um relatório detalhado das Caixas Mistas, listando o produto final 
     * e todos os itens (lotes/sobras) que foram consumidos para a sua criação.
     * @return array
     * @throws Exception
     */
    public function getRelatorioCaixasMistasDetalhado(): array
    {
        $sql = "
            SELECT
                -- DADOS DA CAIXA MISTA (PRODUTO FINAL)
                mh.mista_id AS 'ID Caixa Mista',
                p_final.prod_descricao AS 'Produto Final Gerado',
                lh_destino.lote_completo_calculado AS 'Lote Destino',
                mh.mista_data_criacao AS 'Data Criacao',
                
                -- DADOS DA SOBRA CONSUMIDA (ITEM DE ORIGEM)
                p_origem.prod_descricao AS 'Produto de Origem (Sobra)',
                lh_origem.lote_completo_calculado AS 'Lote de Origem',
                mi.item_mista_qtd_consumida AS 'Qtd Consumida (Kg)'
            FROM
                tbl_caixas_mistas_header mh
            -- 1. Junta com o PRODUTO FINAL (o produto que foi gerado)
            INNER JOIN
                tbl_produtos p_final ON mh.mista_produto_final_id = p_final.prod_codigo
            -- 2. Junta com o LOTE DESTINO (o lote ao qual pertence a Caixa Mista)
            INNER JOIN
                tbl_lotes_novo_header lh_destino ON mh.mista_lote_destino_id = lh_destino.lote_id
            -- 3. Junta com os ITENS CONSUMIDOS (as sobras)
            INNER JOIN
                tbl_caixas_mistas_itens mi ON mh.mista_id = mi.item_mista_header_id
            -- 4. Junta com a PRODUCAO ORIGINAL do item consumido (para o produto/lote)
            INNER JOIN
                tbl_lotes_novo_producao lp_origem ON mi.item_prod_origem_id = lp_origem.item_prod_id
            -- 5. Junta com o PRODUTO de ORIGEM
            INNER JOIN
                tbl_produtos p_origem ON lp_origem.item_prod_produto_id = p_origem.prod_codigo
            -- 6. Junta com o LOTE de ORIGEM
            INNER JOIN
                tbl_lotes_novo_header lh_origem ON lp_origem.item_prod_lote_id = lh_origem.lote_id
            ORDER BY
                mh.mista_id DESC, mi.item_mista_qtd_consumida DESC;
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            // Retorna todos os resultados como um array associativo
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erro no SQL do Relatório de Caixas Mistas: " . $e->getMessage());
            throw new Exception("Ocorreu um erro ao gerar o relatório de caixas mistas.");
        }
    }

    // --- FUNÇÕES PARA DETALHES DE RECEBIMENTO ---

    public function adicionarItemRecebimento(array $data)
    {
        // Gramaturas geralmente podem ser nulas
        // $gramFaz = !empty($data['item_receb_gram_faz']) ? $data['item_receb_gram_faz'] : null;
        // $gramLab = !empty($data['item_receb_gram_lab']) ? $data['item_receb_gram_lab'] : null;

        $pesoNf = $this->numeroParaSql($data['item_receb_peso_nota_fiscal'] ?? 0);
        $pesoMedio = $this->numeroParaSql($data['item_receb_peso_medio_ind'] ?? 0);
        $gramFaz = $this->numeroParaSql($data['item_receb_gram_faz'] ?? null);
        $gramLab = $this->numeroParaSql($data['item_receb_gram_lab'] ?? null);

        // Tratamento de nulos para campos opcionais
        $loteOrigem = !empty($data['item_receb_lote_origem_id']) ? $data['item_receb_lote_origem_id'] : null;

        $sql = "INSERT INTO tbl_lote_novo_recebdetalhes (
                    item_receb_lote_id, item_receb_produto_id, item_receb_lote_origem_id,
                    item_receb_nota_fiscal, item_receb_peso_nota_fiscal, 
                    item_receb_total_caixas, item_receb_peso_medio_ind,
                    item_receb_gram_faz, item_receb_gram_lab
                ) VALUES (
                    :lote_id, :produto_id, :lote_origem,
                    :nf, :peso_nf, 
                    :total_caixas, :peso_medio,
                    :gram_faz, :gram_lab
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':lote_id' => $data['item_receb_lote_id'],
            ':produto_id' => $data['item_receb_produto_id'],
            ':lote_origem' => $loteOrigem,
            ':nf' => $data['item_receb_nota_fiscal'],
            //':peso_nf' => !empty($data['item_receb_peso_nota_fiscal']) ? $data['item_receb_peso_nota_fiscal'] : 0,
            ':peso_nf' => $pesoNf,
            ':total_caixas' => !empty($data['item_receb_total_caixas']) ? $data['item_receb_total_caixas'] : 0,
            ':peso_medio' => $pesoMedio,

            ':gram_faz' => $gramFaz,
            ':gram_lab' => $gramLab
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getItensRecebimento(int $loteId): array
    {
        $sql = "SELECT 
                    r.*,
                    p.prod_descricao,
                    p.prod_codigo_interno,
                    -- Lógica de exibição da Origem solicitada
                    CASE
                        WHEN p.prod_tipo = 'CAMARAO' AND p.prod_congelamento = 'IN NATURA' THEN 'DESPESCA'
                        WHEN p.prod_tipo <> 'CAMARAO' AND p.prod_congelamento = 'IN NATURA' THEN p.prod_origem
                        WHEN r.item_receb_lote_origem_id IS NOT NULL THEN lh.lote_completo_calculado
                        ELSE '-'
                    END AS origem_formatada
                FROM tbl_lote_novo_recebdetalhes r
                JOIN tbl_produtos p ON r.item_receb_produto_id = p.prod_codigo
                LEFT JOIN tbl_lotes_novo_header lh ON r.item_receb_lote_origem_id = lh.lote_id
                WHERE r.item_receb_lote_id = :lote_id
                ORDER BY r.item_receb_id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_id' => $loteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function excluirItemRecebimento(int $itemId, int $userId)
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Verifica se existe vínculo com OE (pelo ID do item recebido)
            // A "etiqueta" que gravamos foi: "REF_RECEB: {ID}"
            $tagBusca = "REF_RECEB: " . $itemId;

            // Busca os itens de OE que têm essa tag
            $sqlBuscaItens = "SELECT i.oei_id, i.oei_pedido_id, p.oep_ordem_id 
                              FROM tbl_ordens_expedicao_itens i
                              JOIN tbl_ordens_expedicao_pedidos p ON i.oei_pedido_id = p.oep_id
                              WHERE i.oei_observacao = :tag";

            $stmtBusca = $this->pdo->prepare($sqlBuscaItens);
            $stmtBusca->execute([':tag' => $tagBusca]);
            $itensOE = $stmtBusca->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($itensOE)) {
                // Guarda IDs para verificação posterior
                $pedidoIdsAfetados = array_unique(array_column($itensOE, 'oei_pedido_id'));
                $oeIdsAfetadas = array_unique(array_column($itensOE, 'oep_ordem_id'));

                // 2. Remove os Itens da OE (Libera a reserva)
                $sqlDelItem = "DELETE FROM tbl_ordens_expedicao_itens WHERE oei_observacao = :tag";
                $stmtDel = $this->pdo->prepare($sqlDelItem);
                $stmtDel->execute([':tag' => $tagBusca]);

                // 3. Limpeza de Pedidos Vazios
                foreach ($pedidoIdsAfetados as $pedId) {
                    $qtdItens = $this->pdo->query("SELECT COUNT(*) FROM tbl_ordens_expedicao_itens WHERE oei_pedido_id = $pedId")->fetchColumn();
                    if ($qtdItens == 0) {
                        $this->pdo->exec("DELETE FROM tbl_ordens_expedicao_pedidos WHERE oep_id = $pedId");
                    }
                }

                // 4. Limpeza de Headers (OEs) Vazios
                foreach ($oeIdsAfetadas as $ordemId) {
                    $qtdPedidos = $this->pdo->query("SELECT COUNT(*) FROM tbl_ordens_expedicao_pedidos WHERE oep_ordem_id = $ordemId")->fetchColumn();
                    if ($qtdPedidos == 0) {
                        $this->pdo->exec("DELETE FROM tbl_ordens_expedicao_header WHERE oe_id = $ordemId");
                    }
                }
            }

            // 5. Exclui o item do Recebimento (Lote Novo)
            $sqlDelReceb = "DELETE FROM tbl_lote_novo_recebdetalhes WHERE item_receb_id = :id";
            $stmtDelReceb = $this->pdo->prepare($sqlDelReceb);
            $stmtDelReceb->execute([':id' => $itemId]);

            $this->auditLogger->log('EXCLUIR_ITEM_RECEB', $itemId, 'tbl_lote_novo_recebdetalhes', null, null, "Item excluído e reserva estornada.");

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getLotesFinalizadosParaSelect(string $term = ''): array
    {
        $sql = "SELECT lote_id AS id, lote_completo_calculado AS text 
                FROM tbl_lotes_novo_header 
                WHERE lote_status = 'FINALIZADO'";
        //ORDER BY lote_data_finalizacao DESC LIMIT 50";
        $params = [];
        if (!empty($term)) {
            $sql .= " AND lote_completo_calculado LIKE :term ";
            $params[':term'] = '%' . $term . '%';
        }

        $sql .= " ORDER BY lote_data_finalizacao DESC LIMIT 50";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um único item de detalhe para preencher o formulário de edição.
     */
    public function getItemRecebimento(int $itemId): array
    {
        $sql = "SELECT 
                    d.*, 
                    p.prod_descricao,
                    
                    -- Adicione/Verifique estas linhas para o Reprocesso:
                    lo.lote_completo_calculado AS lote_origem_numero,
                    po.prod_descricao AS lote_origem_produto_nome

                FROM tbl_lote_novo_recebdetalhes d
                LEFT JOIN tbl_produtos p ON d.item_receb_produto_id = p.prod_codigo
                -- Join com o Lote de Origem
                LEFT JOIN tbl_lotes_novo_header lo ON d.item_receb_lote_origem_id = lo.lote_id
                -- Join para pegar o nome do produto do lote de origem (opcional, mas bom para o label)
                LEFT JOIN tbl_lotes_novo_embalagem le ON lo.lote_id = le.item_emb_lote_id
                LEFT JOIN tbl_produtos po ON le.item_emb_prod_sec_id = po.prod_codigo
                
                WHERE d.item_receb_id = :id
                GROUP BY d.item_receb_id"; // Group by evita duplicatas se o lote tiver varios itens

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Atualiza um item de detalhe existente.
     */
    public function atualizarItemRecebimento(array $data): bool
    {
        $id = filter_var($data['item_receb_id'], FILTER_VALIDATE_INT);
        $loteOrigem = !empty($data['item_receb_lote_origem_id']) ? $data['item_receb_lote_origem_id'] : null;

        $sql = "UPDATE tbl_lote_novo_recebdetalhes SET
                    item_receb_produto_id = :produto_id,
                    item_receb_lote_origem_id = :lote_origem,
                    item_receb_nota_fiscal = :nf,
                    item_receb_peso_nota_fiscal = :peso_nf,
                    item_receb_total_caixas = :total_caixas,
                    item_receb_peso_medio_ind = :peso_medio,
                    item_receb_gram_faz = :gram_faz,
                    item_receb_gram_lab = :gram_lab
                WHERE item_receb_id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':produto_id' => $data['item_receb_produto_id'],
            ':lote_origem' => $loteOrigem,
            ':nf' => $data['item_receb_nota_fiscal'],
            ':peso_nf' => $data['item_receb_peso_nota_fiscal'],
            ':total_caixas' => $data['item_receb_total_caixas'],
            ':peso_medio' => $data['item_receb_peso_medio_ind'],
            ':gram_faz' => $data['item_receb_gram_faz'],
            ':gram_lab' => $data['item_receb_gram_lab'],
            ':id' => $id
        ]);
    }

    public function getDadosBasicosLoteReprocesso(int $loteId): array
    {
        // Usamos ALIASES (AS ...) para coincidir com o que o JavaScript espera (d.lote_nota_fiscal, etc)
        $sql = "
    SELECT
        rd.item_receb_nota_fiscal      AS lote_nota_fiscal,
        rd.item_receb_peso_nota_fiscal AS lote_peso_nota_fiscal,
        rd.item_receb_total_caixas     AS lote_total_caixas,
        rd.item_receb_peso_medio_ind   AS lote_peso_medio_industria,
        rd.item_receb_gram_faz         AS lote_gramatura_fazenda,
        rd.item_receb_gram_lab         AS lote_gramatura_lab,
        
        -- Calculamos o peso médio fazenda na query para garantir precisão se não estiver salvo
        TRUNCATE((rd.item_receb_peso_nota_fiscal / NULLIF(rd.item_receb_total_caixas, 0)), 3) AS lote_peso_medio_fazenda

    FROM tbl_lote_novo_recebdetalhes rd
    INNER JOIN tbl_lotes_novo_header h ON h.lote_id = rd.item_receb_lote_id
    WHERE h.lote_id = :lote_id
      AND h.lote_status = 'FINALIZADO'
    ORDER BY rd.item_receb_id DESC
    LIMIT 1
    ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_id' => $loteId]);

        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        // Retorna array vazio se não achar, ao invés de false, para tratar melhor no Controller
        return $dados ?: [];
    }

    /**
     * Busca TODOS os dados do lote para a geração do relatório PDF.
     */
    public function getDadosRelatorioLote(int $loteId): array
    {
        // 1. Cabeçalho + Entidades
        $sqlHeader = "SELECT 
                        h.*,
                        COALESCE(NULLIF(f.ent_nome_fantasia, ''), f.ent_razao_social) AS nome_fornecedor,
                        COALESCE(NULLIF(c.ent_nome_fantasia, ''), c.ent_razao_social) AS nome_cliente
                      FROM tbl_lotes_novo_header h
                      LEFT JOIN tbl_entidades f ON h.lote_fornecedor_id = f.ent_codigo
                      LEFT JOIN tbl_entidades c ON h.lote_cliente_id = c.ent_codigo
                      WHERE h.lote_id = :id";
        $stmtH = $this->pdo->prepare($sqlHeader);
        $stmtH->execute([':id' => $loteId]);
        $header = $stmtH->fetch(PDO::FETCH_ASSOC);

        if (!$header) return [];

        // 2. Itens de Recebimento (Matéria Prima) - COM A LÓGICA DE ORIGEM FORMATADA
        $sqlReceb = "SELECT 
                        r.*, 
                        p.prod_descricao,
                        p.prod_especie,
                        -- Adicionamos a lógica da origem aqui também para o PDF
                        CASE
                            WHEN p.prod_tipo = 'CAMARAO' AND p.prod_congelamento = 'IN NATURA' THEN 'DESPESCA'
                            WHEN p.prod_tipo <> 'CAMARAO' AND p.prod_congelamento = 'IN NATURA' THEN p.prod_origem
                            WHEN r.item_receb_lote_origem_id IS NOT NULL THEN lh.lote_completo_calculado
                            ELSE '-'
                        END AS origem_formatada
                     FROM tbl_lote_novo_recebdetalhes r
                     JOIN tbl_produtos p ON r.item_receb_produto_id = p.prod_codigo
                     LEFT JOIN tbl_lotes_novo_header lh ON r.item_receb_lote_origem_id = lh.lote_id
                     WHERE r.item_receb_lote_id = :id";

        $stmtR = $this->pdo->prepare($sqlReceb);
        $stmtR->execute([':id' => $loteId]);
        $recebimento = $stmtR->fetchAll(PDO::FETCH_ASSOC);

        // 3. Itens de Produção (Primária) - AGRUPADO POR PRODUTO
        $sqlProd = "SELECT 
                        p.prod_descricao, 
                        p.prod_codigo_interno,
                        p.prod_marca,
                        p.prod_unidade,
                        p.prod_peso_embalagem,
                        p.prod_fator_producao AS fator_atual,
                        SUM(lp.item_prod_quantidade) as item_prod_quantidade
                    FROM tbl_lotes_novo_producao lp
                    JOIN tbl_produtos p ON lp.item_prod_produto_id = p.prod_codigo
                    WHERE lp.item_prod_lote_id = :id
                    GROUP BY p.prod_codigo, p.prod_descricao, p.prod_codigo_interno, p.prod_marca, p.prod_unidade, p.prod_peso_embalagem, p.prod_fator_producao
                    ORDER BY p.prod_descricao";

        $stmtP = $this->pdo->prepare($sqlProd);
        $stmtP->execute([':id' => $loteId]);
        $producao = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        // 4. Itens de Embalagem (Secundária) - AGRUPADO POR PRODUTO
        $sqlEmb = "SELECT 
                        p.prod_descricao, 
                        p.prod_codigo_interno,
                        p.prod_marca,
                        p.prod_unidade,
                        p.prod_peso_embalagem,
                        SUM(le.item_emb_qtd_sec) as item_emb_qtd_sec
                    FROM tbl_lotes_novo_embalagem le
                    JOIN tbl_produtos p ON le.item_emb_prod_sec_id = p.prod_codigo
                    WHERE le.item_emb_lote_id = :id
                    GROUP BY p.prod_codigo, p.prod_descricao, p.prod_codigo_interno, p.prod_marca, p.prod_unidade, p.prod_peso_embalagem
                    ORDER BY p.prod_descricao";

        $stmtE = $this->pdo->prepare($sqlEmb);
        $stmtE->execute([':id' => $loteId]);
        $embalagem = $stmtE->fetchAll(PDO::FETCH_ASSOC);

        return [
            'header' => $header,
            'recebimento' => $recebimento,
            'producao' => $producao,
            'embalagem' => $embalagem
        ];
    }

    /**
     * Método auxiliar privado para registrar a entrada lógica (PRODUÇÃO) no Kardex.
     * Deve ser chamado apenas quando o lote transiciona para FINALIZADO.
     */
    private function registrarEntradaProducaoKardex(int $loteId, int $usuarioId): void
    {
        // 1. Busca os itens produzidos (Caixas/Embalagens Secundárias)
        $sqlItens = "SELECT item_emb_id, item_emb_qtd_sec 
                     FROM tbl_lotes_novo_embalagem 
                     WHERE item_emb_lote_id = ?";
        $stmtItens = $this->pdo->prepare($sqlItens);
        $stmtItens->execute([$loteId]);
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        // 2. Registra cada item no Kardex
        foreach ($itens as $item) {
            // Verifica se já não foi registrado para não duplicar (segurança extra)
            // Se quiser confiar apenas na chamada do método, pode pular essa verificação

            $this->movimentoRepo->registrar(
                'PRODUCAO',                      // Tipo
                $item['item_emb_id'],      // Item ID
                $item['item_emb_qtd_sec'], // Quantidade
                $usuarioId,                 // Usuário
                null,                        // Origem (Nasceu)
                null,                       // Destino (Ainda sem endereço)
                'Produção Finalizada',            // Obs
                $loteId                        // Documento Ref
            );
        }
    }

    public function getRelatorioMensalData(array $meses, int $ano, array $fornecedores = []): array
    {
        if (empty($meses)) return [];

        // Cria string de interrogações para o SQL: "?,?,?"
        $placeholders = implode(',', array_fill(0, count($meses), '?'));

        $sql = "SELECT 
                    h.lote_data_fabricacao,
                    h.lote_completo_calculado,
                    COALESCE(NULLIF(f.ent_nome_fantasia,''), f.ent_razao_social) as fornecedor_nome,
                    h.lote_observacao,
                    
                    SUM(d.item_receb_peso_nota_fiscal) as total_peso,
                    SUM(d.item_receb_total_caixas) as total_caixas,
                    
                    GROUP_CONCAT(DISTINCT d.item_receb_gram_faz ORDER BY d.item_receb_gram_faz SEPARATOR ' / ') as gram_faz,
                    GROUP_CONCAT(DISTINCT d.item_receb_gram_lab ORDER BY d.item_receb_gram_lab SEPARATOR ' / ') as gram_benef,
                    GROUP_CONCAT(DISTINCT l_origem.lote_completo_calculado SEPARATOR ', ') as lote_reprocesso_origem

                FROM tbl_lotes_novo_header h
                LEFT JOIN tbl_entidades f ON h.lote_cliente_id = f.ent_codigo
                LEFT JOIN tbl_lote_novo_recebdetalhes d ON h.lote_id = d.item_receb_lote_id
                LEFT JOIN tbl_lotes_novo_header l_origem ON d.item_receb_lote_origem_id = l_origem.lote_id
                
                WHERE YEAR(h.lote_data_fabricacao) = ?
                  AND MONTH(h.lote_data_fabricacao) IN ($placeholders)
                  AND h.lote_status != 'CANCELADO'";

        // Parâmetros: [Ano, Mes1, Mes2, Mes3...]
        $params = array_merge([$ano], $meses);

        // --- FILTRO DE FORNECEDORES ---
        if (!empty($fornecedores)) {
            // Cria placeholders para fornecedores
            $placeholdersForn = implode(',', array_fill(0, count($fornecedores), '?'));

            $sql .= " AND h.lote_cliente_id IN ($placeholdersForn)";

            // Adiciona os IDs dos fornecedores ao array de parâmetros
            $params = array_merge($params, $fornecedores);
        }

        $sql .= " GROUP BY h.lote_id
                  ORDER BY h.lote_data_fabricacao ASC, h.lote_id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNomesClientesPorIds(array $ids): string
    {
        if (empty($ids)) return '';

        // Cria os placeholders ?,?,?
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Busca Nome Fantasia (ou Razão Social se não tiver fantasia)
        $sql = "SELECT COALESCE(NULLIF(ent_nome_fantasia, ''), ent_razao_social) as nome 
                FROM tbl_entidades 
                WHERE ent_codigo IN ($placeholders)
                ORDER BY nome ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);

        // Pega todos os nomes e junta com vírgula
        $nomes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return implode(', ', $nomes);
    }

    public function getClientesComLotesOptions()
    {
        $sql = "SELECT DISTINCT 
                    e.ent_codigo as id, 
                    COALESCE(NULLIF(e.ent_nome_fantasia, ''), e.ent_razao_social) as text
                FROM tbl_lotes_novo_header h
                INNER JOIN tbl_entidades e ON h.lote_cliente_id = e.ent_codigo
                WHERE h.lote_status != 'CANCELADO'
                ORDER BY text ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Importa um lote antigo (Legado) para permitir reprocesso.
     * Cria o lote já finalizado e lança o estoque, respeitando a estrutura exata do banco.
     */
    public function importarLoteLegado(array $dados, int $usuarioId)
    {
        // --- LOG DE DEPURAÇÃO (Ver no arquivo de log do Apache/PHP ou error_log) ---
        error_log("==========================================");
        error_log("DEBUG IMPORTAÇÃO LEGADO - DADOS RECEBIDOS:");
        error_log(print_r($dados, true)); // Imprime o array inteiro
        error_log("==========================================");

        $this->pdo->beginTransaction();

        try {
            // =================================================================================
            // 1. CRIAR O CABEÇALHO DO LOTE (tbl_lotes_novo_header)
            // =================================================================================
            $sqlHeader = "INSERT INTO tbl_lotes_novo_header 
            (lote_numero, lote_completo_calculado, lote_data_fabricacao, 
             lote_cliente_id, lote_status, lote_observacao, 
             lote_usuario_id, lote_data_finalizacao)
            VALUES 
            (:numero, :completo, :data_fab, 
             :cliente, 'FINALIZADO', :obs, 
             :user, NOW())";

            // --- LÓGICA DE EXTRAÇÃO DE NÚMERO ---
            $codigoOriginal = $dados['lote_codigo'];

            if (strpos($codigoOriginal, '/') !== false) {
                $parteAntesDaBarra = explode('/', $codigoOriginal)[0];
            } else {
                $parteAntesDaBarra = $codigoOriginal;
            }

            $numeroSerial = preg_replace('/[^0-9]/', '', $parteAntesDaBarra);
            if (empty($numeroSerial)) $numeroSerial = '0000';

            $stmtH = $this->pdo->prepare($sqlHeader);
            $stmtH->execute([
                ':numero'   => substr($numeroSerial, 0, 50),
                ':completo' => $dados['lote_codigo'],
                ':data_fab' => $dados['data_fabricacao'],
                ':cliente'  => $dados['cliente_id'],
                ':obs'      => 'CARGA INICIAL (LEGADO). ' . ($dados['observacao'] ?? ''),
                ':user'     => $usuarioId
            ]);

            $loteId = $this->pdo->lastInsertId();

            // =================================================================================
            // 2. BUSCAR PRODUTO PRIMÁRIO E PESOS ASSOCIADOS
            // =================================================================================
            $sqlBuscaSecundario = "SELECT prod_primario_id, prod_peso_embalagem AS peso_sec
                               FROM tbl_produtos 
                               WHERE prod_codigo = :prod_sec_id 
                               AND prod_tipo_embalagem = 'SECUNDARIA'
                               LIMIT 1";

            $stmtSec = $this->pdo->prepare($sqlBuscaSecundario);
            $stmtSec->execute([':prod_sec_id' => $dados['produto_id']]);
            $secData = $stmtSec->fetch(PDO::FETCH_ASSOC);

            if (!$secData || !$secData['prod_primario_id']) {
                throw new Exception("Produto secundário inválido ou sem primário associado (ID {$dados['produto_id']}).");
            }

            $prodPrimId = $secData['prod_primario_id'];
            $pesoSec = (float) $secData['peso_sec'] ?: 10.0;

            $sqlBuscaPrimario = "SELECT prod_peso_embalagem AS peso_prim
                             FROM tbl_produtos 
                             WHERE prod_codigo = :prod_prim_id 
                             AND prod_tipo_embalagem = 'PRIMARIA'
                             LIMIT 1";

            $stmtPrim = $this->pdo->prepare($sqlBuscaPrimario);
            $stmtPrim->execute([':prod_prim_id' => $prodPrimId]);
            $primData = $stmtPrim->fetch(PDO::FETCH_ASSOC);

            if (!$primData) {
                throw new Exception("Produto primário não encontrado (ID {$prodPrimId}).");
            }

            $pesoPrim = (float) $primData['peso_prim'] ?: 1.0;

            if ($pesoPrim <= 0 || $pesoSec <= 0) {
                throw new Exception("Pesos de embalagem inválidos para os produtos.");
            }

            $pesoTotal = (float) $dados['peso_total'];

            if ($pesoTotal <= 0) {
                throw new Exception("Peso total inválido.");
            }

            $qtdPrim = $pesoTotal / $pesoPrim;
            $qtdSec = floor($pesoTotal / $pesoSec);

            // =================================================================================
            // 3. CRIAR O ITEM DE PRODUÇÃO PRIMÁRIA (ATUALIZADO COM VALIDADE)
            // =================================================================================
            // Alteração: Incluído 'item_prod_data_validade'
            $sqlProducao = "INSERT INTO tbl_lotes_novo_producao
            (item_prod_lote_id, item_prod_produto_id, item_prod_quantidade, item_prod_saldo, item_prod_data_validade)
            VALUES
            (:lote_id, :prod_prim_id, :qtd_prim, :qtd_saldo, :data_val)";

            // Tratamento da Data de Validade (pode vir vazia se não calculada)
            $dataValidade = !empty($dados['data_validade']) ? $dados['data_validade'] : null;

            $stmtProd = $this->pdo->prepare($sqlProducao);
            $stmtProd->execute([
                ':lote_id'      => $loteId,
                ':prod_prim_id' => $prodPrimId,
                ':qtd_prim'     => $qtdPrim,
                ':qtd_saldo'    => $qtdPrim,
                ':data_val'     => $dataValidade // <--- Nova variável aqui
            ]);

            $itemProdId = $this->pdo->lastInsertId();

            // =================================================================================
            // 4. CRIAR O ITEM DE EMBALAGEM SECUNDÁRIA
            // =================================================================================
            $sqlItem = "INSERT INTO tbl_lotes_novo_embalagem
            (item_emb_lote_id, 
             item_emb_prod_sec_id, 
             item_emb_prod_prim_id, 
             item_emb_qtd_sec, 
             item_emb_qtd_prim_cons, 
             item_emb_qtd_finalizada, 
             item_emb_data_cadastro
            )
            VALUES
            (:lote_id, 
             :prod_sec_id, 
             :item_prod_id, 
             :qtd_sec, 
             :qtd_prim_cons, 
             :qtd_fin, 
             NOW())";

            $stmtI = $this->pdo->prepare($sqlItem);
            $stmtI->execute([
                ':lote_id'        => $loteId,
                ':prod_sec_id'    => $dados['produto_id'],
                ':item_prod_id'   => $itemProdId,
                ':qtd_sec'        => $qtdSec,
                ':qtd_prim_cons'  => $qtdPrim,
                ':qtd_fin'        => $qtdSec
            ]);

            $itemLoteId = $this->pdo->lastInsertId();

            // =================================================================================
            // 5. ALOCAR NO ESTOQUE
            // =================================================================================
            $estoqueRepo = new \App\Estoque\EstoqueRepository($this->pdo);

            $estoqueRepo->alocarItem(
                (int)$dados['endereco_id'],
                (int)$itemLoteId,
                (int)$usuarioId,
                (float)$qtdSec
            );

            // =================================================================================
            // 6. REGISTRAR NO KARDEX
            // =================================================================================
            $tipoMovimento = 'CARGA_INICIAL';

            $this->movimentoRepo->registrar(
                $tipoMovimento,
                $itemLoteId,
                $qtdSec,
                $usuarioId,
                null,
                $dados['endereco_id'],
                'Importação de Saldo Legado: ' . $dados['lote_codigo'],
                $loteId
            );

            // =================================================================================
            // 7. AUDITORIA
            // =================================================================================
            if (isset($this->auditLogger)) {
                $this->auditLogger->log(
                    'IMPORTACAO',
                    $loteId,
                    'tbl_lotes_novo_header',
                    null,
                    $dados,
                    'Carga Inicial de Lote Legado com Produção Primária'
                );
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cancela um lote legado e remove seu saldo do estoque.
     * Só permite se o saldo atual for igual ao saldo inicial (ninguém mexeu ainda).
     */
    public function estornarLoteLegado(int $loteId, int $usuarioId, string $motivo): array
    {
        $this->pdo->beginTransaction();

        try {
            // 1. Busca dados do lote e do item
            $sql = "SELECT l.lote_status, i.item_emb_id, i.item_emb_qtd_sec
                    FROM tbl_lotes_novo_header l
                    JOIN tbl_lotes_novo_embalagem i ON l.lote_id = i.item_emb_lote_id
                    WHERE l.lote_id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $loteId]);
            $lote = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lote) {
                throw new Exception("Lote não encontrado.");
            }

            if ($lote['lote_status'] !== 'FINALIZADO') {
                throw new Exception("Apenas lotes com status FINALIZADO podem ser estornados.");
            }

            // 2. Verifica se o saldo alocado ainda está intacto
            // O saldo atual (soma das alocações) deve ser igual à quantidade inicial do lote
            $sqlSaldo = "SELECT SUM(alocacao_quantidade) as total 
                         FROM tbl_estoque_alocacoes 
                         WHERE alocacao_lote_item_id = ?";
            $stmtSaldo = $this->pdo->prepare($sqlSaldo);
            $stmtSaldo->execute([$lote['item_emb_id']]);
            $saldoAtual = (float) $stmtSaldo->fetchColumn();
            $qtdOriginal = (float) $lote['item_emb_qtd_sec'];

            // Compara Float com margem de segurança
            if (abs($saldoAtual - $qtdOriginal) > 0.001) {
                throw new Exception("Não é possível estornar: O estoque já foi movimentado. Saldo Atual: $saldoAtual / Original: $qtdOriginal");
            }

            // 3. Remove as Alocações (Zera o Estoque Físico)
            // Como validamos que o saldo está cheio, podemos deletar as alocações deste item
            $sqlDelAloc = "DELETE FROM tbl_estoque_alocacoes WHERE alocacao_lote_item_id = :id";
            $this->pdo->prepare($sqlDelAloc)->execute([':id' => $lote['item_emb_id']]);

            // 4. Marca Lote como CANCELADO
            $motivoCompleto = "ESTORNO LEGADO: " . $motivo;
            $sqlUpdate = "UPDATE tbl_lotes_novo_header 
                          SET lote_status = 'CANCELADO', 
                              lote_observacao = CONCAT(COALESCE(lote_observacao, ''), ' | ', :motivo) 
                          WHERE lote_id = :id";
            $this->pdo->prepare($sqlUpdate)->execute([
                ':motivo' => $motivoCompleto,
                ':id' => $loteId
            ]);

            // 5. Registra Kardex de SAÍDA (Estorno)
            $this->movimentoRepo->registrar(
                'SAIDA',                            // Tipo
                $lote['item_emb_id'],         // Item ID
                $lote['item_emb_qtd_sec'],    // Quantidade (remove tudo que entrou)
                $usuarioId,                    // Usuário
                null,                           // Origem
                null,                          // Destino
                'Estorno de Importação: ' . $motivo, // Obs
                $loteId                           // Doc Ref (Lote ID)
            );

            // 6. Auditoria (Log)
            if (isset($this->auditLogger)) {
                $this->auditLogger->log(
                    'ESTORNO',                        // Ação
                    $loteId,                    // Registro ID
                    'tbl_lotes_novo_header', // Tabela
                    $lote,                    // Dados Antigos (Array do select)
                    ['status' => 'CANCELADO'],  // Dados Novos (Resumo)
                    $motivo                     // Observação
                );
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Lote estornado e saldo revertido com sucesso.'];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listarLegadosRecentes(): array
    {
        // Buscamos lotes Finalizados e que tenham 'LEGADO' na observação
        // (Ou você pode criar uma coluna 'lote_tipo' no futuro, mas o filtro por obs funciona bem agora)
        $sql = "SELECT 
                    h.lote_id, 
                    h.lote_completo_calculado, 
                    DATE_FORMAT(h.lote_data_fabricacao, '%d/%m/%Y') as data_formatada,
                    p.prod_descricao,
                    i.item_emb_qtd_sec
                FROM tbl_lotes_novo_header h
                JOIN tbl_lotes_novo_embalagem i ON h.lote_id = i.item_emb_lote_id
                JOIN tbl_produtos p ON i.item_emb_prod_sec_id = p.prod_codigo
                WHERE h.lote_status = 'FINALIZADO'
                AND (h.lote_observacao LIKE '%LEGADO%' OR h.lote_observacao LIKE '%CARGA INICIAL%')
                ORDER BY h.lote_id DESC 
                LIMIT 20";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista produtos que existem no estoque de um lote (Origem).
     */
    public function listarProdutosDoLote(int $loteId): array
    {
        $sql = "SELECT DISTINCT p.prod_codigo, p.prod_descricao
                FROM tbl_lotes_novo_embalagem e
                JOIN tbl_produtos p ON e.item_emb_prod_sec_id = p.prod_codigo
                WHERE e.item_emb_lote_id = :lote_id
                ORDER BY p.prod_descricao";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_id' => $loteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca saldo de REPROCESSO olhando para o ESTOQUE (Embalagem)
     * e não para o Recebimento.
     */
    public function getDadosReprocessoPorProduto(int $loteOrigemId, int $produtoId): array
    {
        // LÓGICA:
        // 1. Baseamos a busca na tabela tbl_estoque_alocacoes (O que está fisicamente na prateleira).
        // 2. Fazemos um LEFT JOIN com tbl_ordens_expedicao_itens para ver se esse estoque já está reservado em outra OE pendente.
        // 3. O saldo final é: (Estoque Físico) - (Reservas em Aberto).

        $sql = "SELECT 
                    p.prod_peso_embalagem,
                    CONCAT('REPROCESSO: ', h.lote_completo_calculado) as referencia_nota,
                    
                    -- CÁLCULO DA QUANTIDADE (CAIXAS)
                    (
                        SUM(a.alocacao_quantidade) - 
                        COALESCE(SUM(CASE 
                            WHEN i.oei_status NOT IN ('FINALIZADA', 'CANCELADA') THEN i.oei_quantidade 
                            ELSE 0 
                        END), 0)
                    ) as total_caixas,

                    -- CÁLCULO DO PESO TOTAL (Baseado nas caixas disponíveis)
                    (
                        SUM(a.alocacao_quantidade) - 
                        COALESCE(SUM(CASE 
                            WHEN i.oei_status NOT IN ('FINALIZADA', 'CANCELADA') THEN i.oei_quantidade 
                            ELSE 0 
                        END), 0)
                    ) * p.prod_peso_embalagem as peso_total_calculado

                FROM tbl_estoque_alocacoes a
                
                -- Vínculo com a produção para chegar no Produto e Lote
                JOIN tbl_lotes_novo_embalagem e ON a.alocacao_lote_item_id = e.item_emb_id
                JOIN tbl_produtos p ON e.item_emb_prod_sec_id = p.prod_codigo
                JOIN tbl_lotes_novo_header h ON e.item_emb_lote_id = h.lote_id
                
                -- Vínculo com Ordens de Expedição para checar RESERVAS
                LEFT JOIN tbl_ordens_expedicao_itens i ON i.oei_alocacao_id = a.alocacao_id

                WHERE e.item_emb_lote_id = :lote_id 
                AND e.item_emb_prod_sec_id = :prod_id
                AND a.alocacao_quantidade > 0  -- Ignora endereços vazios

                GROUP BY p.prod_codigo
                
                -- Garante que só retorna se tiver saldo positivo real
                HAVING total_caixas > 0";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_id' => $loteOrigemId, ':prod_id' => $produtoId]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        return $dados ?: [];
    }


    // LOGICA PARA BAIXA DE REPROCESSO

    /**
     * Passo 1 do Picking: Lista as ALOCAÇÕES (Endereços) onde tem saldo.
     * Agora olhando para tbl_estoque_alocacoes.
     */
    public function getSaldosPorEndereco(int $loteId, int $prodId): array
    {
        $sql = "SELECT 
                    a.alocacao_id AS item_emb_id, -- ALIAS IMPORTANTE: O JS espera 'item_emb_id', mas enviamos o ID da alocação
                    a.alocacao_quantidade AS saldo_caixas,
                    (a.alocacao_quantidade * p.prod_peso_embalagem) AS saldo_peso,
                    
                    -- Busca o nome do endereço na tabela correta
                    COALESCE(end.endereco_completo, 'ENDEREÇO NÃO DEFINIDO') AS endereco_nome,
                    
                    p.prod_peso_embalagem

                FROM tbl_estoque_alocacoes a
                -- Link com a embalagem (Lote/Produto)
                JOIN tbl_lotes_novo_embalagem e ON a.alocacao_lote_item_id = e.item_emb_id
                JOIN tbl_produtos p ON e.item_emb_prod_sec_id = p.prod_codigo
                -- Link com o Endereço físico
                JOIN tbl_estoque_enderecos end ON a.alocacao_endereco_id = end.endereco_id
                
                WHERE e.item_emb_lote_id = :lote_id 
                AND e.item_emb_prod_sec_id = :prod_id
                AND a.alocacao_quantidade > 0 -- Só mostra onde tem saldo físico
                ORDER BY a.alocacao_quantidade DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_id' => $loteId, ':prod_id' => $prodId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Passo 2 do Picking: Realiza a baixa nas ALOCAÇÕES, atualiza Kardex e Salva.
     */
    public function salvarReprocessoComBaixa(array $dadosRecebimento, array $distribuicaoEstoque, int $usuarioId)
    {
        try {
            $this->pdo->beginTransaction();

            // Log para debug (mantenha para testes)
            error_log("salvarReprocessoComBaixa: Distribuicao Estoque = " . print_r($distribuicaoEstoque, true));

            // 1. Salva a ENTRADA (Novo Lote)
            $recebId = $this->adicionarItemRecebimentoInterno($dadosRecebimento);

            // 2. Processa a SAÍDA (Baixa nas Alocações)
            foreach ($distribuicaoEstoque as $item) {

                // Verificação: Garante que 'id' e 'qtd' existam e sejam válidos
                if (!isset($item['id']) || !is_numeric($item['id'])) {
                    throw new Exception("Item de distribuição inválido: faltando ou inválido 'id'. Item: " . print_r($item, true));
                }
                if (!isset($item['qtd']) || !is_numeric($item['qtd']) || (float)$item['qtd'] <= 0) {
                    throw new Exception("Item de distribuição inválido: faltando ou inválida 'qtd' (deve ser > 0). Item: " . print_r($item, true));
                }

                $alocacaoId = (int)$item['id'];   // Agora isso é o ID da tbl_estoque_alocacoes
                $qtdBaixar  = (float)$item['qtd'];

                if ($qtdBaixar <= 0) continue;

                // A. Busca dados da alocação atual (para log e validação)
                $sqlGet = "SELECT a.alocacao_quantidade, a.alocacao_lote_item_id, 
                                  e.item_emb_lote_id, e.item_emb_prod_sec_id 
                           FROM tbl_estoque_alocacoes a
                           JOIN tbl_lotes_novo_embalagem e ON a.alocacao_lote_item_id = e.item_emb_id
                           WHERE a.alocacao_id = :id";
                $stmtGet = $this->pdo->prepare($sqlGet);
                $stmtGet->execute([':id' => $alocacaoId]);
                $atual = $stmtGet->fetch(PDO::FETCH_ASSOC);

                if (!$atual) {
                    throw new Exception("Alocação ID $alocacaoId não encontrada.");
                }

                // B. Atualiza a tabela de ALOCAÇÕES (Reduz o saldo do endereço)
                $sqlUpdate = "UPDATE tbl_estoque_alocacoes 
                              SET alocacao_quantidade = alocacao_quantidade - :qtd 
                              WHERE alocacao_id = :id";
                $stmtUp = $this->pdo->prepare($sqlUpdate);
                $stmtUp->execute([':qtd' => $qtdBaixar, ':id' => $alocacaoId]);

                // C. Insere no KARDEX
                $sucessoRegistro = $this->movimentoRepo->registrar(
                    'SAIDA_REPROCESSO',                          // ou 'BAIXA_REPROCESSO' se preferir um tipo específico
                    $atual['alocacao_lote_item_id'],  // FK para tbl_lotes_novo_embalagem (item_emb_id)
                    $qtdBaixar,
                    $usuarioId,
                    null,                         // Se souber o endereço origem, passe aqui
                    null,                        // Se for para algum destino específico
                    "Baixa por reprocesso - Recebimento ID: $recebId (Picking)",
                    $recebId                        // Referência ao item_receb_id
                );

                if (!$sucessoRegistro) {
                    throw new Exception("Falha ao registrar movimentação no estoque para alocação $alocacaoId");
                }

                // D. Opcional: Registrar na tabela tbl_estoque (Log de Movimento Geral)
                /* $sqlEstoqueLog = "INSERT INTO tbl_estoque 
                    (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao)
                    VALUES 
                    (:prod, :lote_item, :qtd, 'SAIDA', 'Reprocesso ID: $recebId')";
                $stmtLog = $this->pdo->prepare($sqlEstoqueLog);
                $stmtLog->execute([
                    ':prod' => $atual['item_emb_prod_sec_id'],
                    ':lote_item' => $atual['alocacao_lote_item_id'],
                    ':qtd' => $qtdBaixar
                ]);*/
            }

            // 3. Auditoria
            $this->auditLogger->log(
                'INSERIR_REPROCESSO',
                $recebId,
                'tbl_lote_novo_recebdetalhes',
                null,               // Não temos "antes" nesse caso (inserção nova)
                $dadosRecebimento,    // Dados inseridos (útil para rastreio)
                "Reprocesso com picking de alocações - Total baixado: " . array_sum(array_column($distribuicaoEstoque, 'qtd')) . " unidades"
            );
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Erro em salvarReprocessoComBaixa: " . $e->getMessage());
            throw $e;
        }
    }

    // Método privado usado tanto pelo 'adicionarItemRecebimento' quanto pelo 'salvarReprocessoComBaixa'
    private function adicionarItemRecebimentoInterno(array $data)
    {

        // 1. Tratamento de Números (CRÍTICO: Converte vírgula para ponto)
        $pesoNf = $this->numeroParaSql($data['item_receb_peso_nota_fiscal'] ?? 0);
        $pesoMedio = $this->numeroParaSql($data['item_receb_peso_medio_ind'] ?? 0);
        $gramFaz = $this->numeroParaSql($data['item_receb_gram_faz'] ?? null);
        $gramLab = $this->numeroParaSql($data['item_receb_gram_lab'] ?? null);

        $loteOrigem = !empty($data['item_receb_lote_origem_id']) ? $data['item_receb_lote_origem_id'] : null;

        // 2. Validação básica para evitar Constraint Violation 1452
        if (empty($data['item_receb_produto_id'])) {
            throw new Exception("ID do Produto é obrigatório para salvar o recebimento.");
        }

        $sql = "INSERT INTO tbl_lote_novo_recebdetalhes (
                    item_receb_lote_id, item_receb_produto_id, item_receb_lote_origem_id,
                    item_receb_nota_fiscal, item_receb_peso_nota_fiscal, 
                    item_receb_total_caixas, item_receb_peso_medio_ind,
                    item_receb_gram_faz, item_receb_gram_lab
                ) VALUES (
                    :lote_id, :produto_id, :lote_origem,
                    :nf, :peso_nf, :total_caixas, :peso_medio,
                    :gram_faz, :gram_lab
                )";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':lote_id'    => $data['item_receb_lote_id'],
            ':produto_id' => $data['item_receb_produto_id'],
            ':lote_origem' => $loteOrigem,
            ':nf'         => $data['item_receb_nota_fiscal'],
            ':peso_nf'    => $pesoNf,       // Agora vai como float correto (ex: 150.50)
            ':total_caixas' => $data['item_receb_total_caixas'],
            ':peso_medio' => $pesoMedio,    // Agora vai como float correto
            ':gram_faz'   => $gramFaz,
            ':gram_lab'   => $gramLab
        ]);

        return $this->pdo->lastInsertId();
    }

    // Função auxiliar para converter "1.234,56" para "1234.56"
    private function numeroParaSql($valor)
    {
        if (empty($valor)) return 0;
        // Se já for numérico (float/int), retorna direto
        if (is_numeric($valor)) return $valor;
        // Remove pontos de milhar e troca vírgula por ponto
        return str_replace(',', '.', str_replace('.', '', $valor));
    }

    public function editarItemRecebimento(array $data, int $usuarioId)
    {
        // Tratamento de Números (Essencial para não dar erro SQL)
        $pesoNf = $this->numeroParaSql($data['item_receb_peso_nota_fiscal'] ?? 0);
        $pesoMedio = $this->numeroParaSql($data['item_receb_peso_medio_ind'] ?? 0);
        $gramFaz = $this->numeroParaSql($data['item_receb_gram_faz'] ?? null);
        $gramLab = $this->numeroParaSql($data['item_receb_gram_lab'] ?? null);

        // Lógica de Reprocesso vs MP
        $loteOrigem = !empty($data['item_receb_lote_origem_id']) ? $data['item_receb_lote_origem_id'] : null;

        $sql = "UPDATE tbl_lote_novo_recebdetalhes SET 
                    item_receb_produto_id = :prod_id,
                    item_receb_lote_origem_id = :lote_origem,
                    item_receb_nota_fiscal = :nf,
                    item_receb_peso_nota_fiscal = :peso_nf,
                    item_receb_total_caixas = :caixas,
                    item_receb_peso_medio_ind = :peso_medio,
                    item_receb_gram_faz = :gram_faz,
                    item_receb_gram_lab = :gram_lab
                WHERE item_receb_id = :id";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':prod_id'    => $data['item_receb_produto_id'],
            ':lote_origem' => $loteOrigem,
            ':nf'         => $data['item_receb_nota_fiscal'],
            ':peso_nf'    => $pesoNf,
            ':caixas'     => $data['item_receb_total_caixas'],
            ':peso_medio' => $pesoMedio,
            ':gram_faz'   => $gramFaz,
            ':gram_lab'   => $gramLab,
            ':id'         => $data['item_receb_id']
        ]);

        // Registrar Auditoria (Opcional)
        $this->auditLogger->log('EDICAO', $data['item_receb_id'], 'tbl_lote_novo_recebdetalhes', null, $data, 'Edição de item de recebimento');

        return true;
    }

    public function salvarReprocessoGerandoOE(array $dadosRecebimento, int $usuarioId)
    {
        try {
            $this->pdo->beginTransaction();

            // DADOS DO FORMULÁRIO
            $lotePaiId = $dadosRecebimento['item_receb_lote_id']; // O Lote que está sendo Produzido
            $loteOrigemId = $dadosRecebimento['item_receb_lote_origem_id']; // O Lote que será consumido
            $qtdCaixasSolicitada = (float) $dadosRecebimento['item_receb_total_caixas'];

            if ($qtdCaixasSolicitada <= 0) throw new Exception("Quantidade inválida.");

            // 1. SALVA O ITEM NO RECEBIMENTO (Tabela de Lotes)
            $recebId = $this->adicionarItemRecebimentoInterno($dadosRecebimento);

            // BUSCA O NÚMERO COMPLETO DO LOTE PAI PARA USAR NA OE
            $stmtLote = $this->pdo->prepare("SELECT lote_completo_calculado FROM tbl_lotes_novo_header WHERE lote_id = :id");
            $stmtLote->execute([':id' => $lotePaiId]);
            $lotePaiNum = $stmtLote->fetchColumn(); // Ex: "LOTE 0042/25"

            // 2. AGRUPAMENTO: Verifica se já existe OE para este Lote Pai
            // Define o padrão do número da OE
            $numeroOEEsperado = "REP." . $lotePaiNum; // Ex: "REP. LOTE 0042/25"

            // Busca se já existe OE com esse número exato
            $sqlBuscaOE = "SELECT oe_id FROM tbl_ordens_expedicao_header 
                           WHERE oe_numero = :num 
                           AND oe_tipo_operacao = 'REPROCESSO' 
                           AND oe_status = 'EM ELABORAÇÃO' 
                           LIMIT 1";
            $stmtBusca = $this->pdo->prepare($sqlBuscaOE);
            $stmtBusca->execute([':num' => $numeroOEEsperado]);
            $oeExistente = $stmtBusca->fetch(PDO::FETCH_ASSOC);

            if ($oeExistente) {
                // USA A OE JÁ ABERTA
                $oeId = $oeExistente['oe_id'];
            } else {
                // CRIA UMA NOVA OE (HEADER)
                // Removemos 'oe_observacao' pois não existe na tabela
                $sqlHeader = "INSERT INTO tbl_ordens_expedicao_header 
                    (oe_numero, oe_data, oe_usuario_id, oe_status, oe_tipo_operacao) 
                    VALUES 
                    (:num, NOW(), :user, 'EM ELABORAÇÃO', 'REPROCESSO')";

                $stmtH = $this->pdo->prepare($sqlHeader);
                $stmtH->execute([
                    ':num'  => $numeroOEEsperado, // Ex: REP.100
                    ':user' => $usuarioId
                ]);
                $oeId = $this->pdo->lastInsertId();
            }

            // 3. CRIA O PEDIDO
            /* $sqlPedido = "INSERT INTO tbl_ordens_expedicao_pedidos 
                (oep_ordem_id, oep_cliente_id, oep_numero_pedido) 
                VALUES 
                (:oe_id, 1, :desc_pedido)";

            // Define o padrão do Pedido
            $descPedido = "PEDIDO: REPROCESSO " . $lotePaiNum;

            $stmtP = $this->pdo->prepare($sqlPedido);
            $stmtP->execute([
                ':oe_id'       => $oeId,
                ':desc_pedido' => $descPedido
            ]);
            $pedidoId = $this->pdo->lastInsertId();*/

            // 3. VERIFICA SE JÁ EXISTE PEDIDO NA OE (AGRUPAMENTO POR PEDIDO)
            $descPedido = "REPROCESSO " . $lotePaiNum;
            $sqlBuscaPedido = "SELECT oep_id FROM tbl_ordens_expedicao_pedidos 
                   WHERE oep_ordem_id = :oe_id 
                   AND oep_numero_pedido = :desc_pedido 
                   AND oep_cliente_id = 1 
                   LIMIT 1";
            $stmtBuscaPedido = $this->pdo->prepare($sqlBuscaPedido);
            $stmtBuscaPedido->execute([
                ':oe_id' => $oeId,
                ':desc_pedido' => $descPedido
            ]);
            $pedidoExistente = $stmtBuscaPedido->fetch(PDO::FETCH_ASSOC);

            if ($pedidoExistente) {
                // USA O PEDIDO EXISTENTE
                $pedidoId = $pedidoExistente['oep_id'];
            } else {
                // CRIA UM NOVO PEDIDO
                $sqlPedido = "INSERT INTO tbl_ordens_expedicao_pedidos 
        (oep_ordem_id, oep_cliente_id, oep_numero_pedido) 
        VALUES 
        (:oe_id, 1, :desc_pedido)";  // Cliente fixo como 1 (INTERNO)

                $stmtP = $this->pdo->prepare($sqlPedido);
                $stmtP->execute([
                    ':oe_id' => $oeId,
                    ':desc_pedido' => $descPedido
                ]);
                $pedidoId = $this->pdo->lastInsertId();
            }

            // 4. RESERVA AUTOMÁTICA (Busca Alocações)
            // Faz o Join para achar onde o lote está guardado
            $sqlEstoque = "SELECT a.alocacao_id, a.alocacao_quantidade 
                           FROM tbl_estoque_alocacoes a
                           JOIN tbl_lotes_novo_embalagem e ON a.alocacao_lote_item_id = e.item_emb_id
                           WHERE e.item_emb_lote_id = :lote_id 
                             AND a.alocacao_quantidade > 0
                           ORDER BY a.alocacao_data ASC";

            $stmtEstoque = $this->pdo->prepare($sqlEstoque);
            $stmtEstoque->execute([':lote_id' => $loteOrigemId]);
            $alocacoes = $stmtEstoque->fetchAll(PDO::FETCH_ASSOC);

            $totalEstoque = array_sum(array_column($alocacoes, 'alocacao_quantidade'));
            if ($totalEstoque < $qtdCaixasSolicitada) {
                throw new Exception("Saldo insuficiente no estoque. Disponível: $totalEstoque cx.");
            }

            // 5. CRIA OS ITENS DA OE
            $qtdRestante = $qtdCaixasSolicitada;

            // Usaremos 'oei_observacao' para linkar com o ID do recebimento (para poder estornar depois)
            $sqlItem = "INSERT INTO tbl_ordens_expedicao_itens 
                (oei_pedido_id, oei_alocacao_id, oei_quantidade, oei_status, oei_observacao) 
                VALUES (:ped_id, :aloc_id, :qtd, 'PENDENTE', :obs)";
            $stmtItem = $this->pdo->prepare($sqlItem);

            foreach ($alocacoes as $aloc) {
                if ($qtdRestante <= 0) break;

                $qtdParaPegar = min($qtdRestante, (float)$aloc['alocacao_quantidade']);

                $stmtItem->execute([
                    ':ped_id'  => $pedidoId,
                    ':aloc_id' => $aloc['alocacao_id'],
                    ':qtd'     => $qtdParaPegar,
                    ':obs'     => "REF_RECEB: " . $recebId // LINK VITAL PARA O ESTORNO
                ]);
                $qtdRestante -= $qtdParaPegar;
            }

            // Auditoria
            //  $this->auditLogger->log('CRIAR_OE_REPROCESSO', $oeId, 'tbl_ordens_expedicao_header', null, $dadosRecebimento, "Adicionado Lote $loteOrigemId na OE $numeroOEEsperado");

            $msgAudit = $pedidoExistente ? "Adicionado ao Pedido Existente na OE $numeroOEEsperado." : "Novo Pedido criado na OE $numeroOEEsperado.";
            $this->auditLogger->log('CRIAR_OE_REPROCESSO', $oeId, 'tbl_ordens_expedicao_header', null, $dadosRecebimento, "Adicionado Lote $loteOrigemId - $msgAudit");

            $this->pdo->commit();
            return ['success' => true, 'oe_id' => $oeId, 'message' => "Lote inserido na OE de Reprocesso #$numeroOEEsperado."];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
