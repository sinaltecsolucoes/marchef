<?php
// /src/Lotes/LoteNovoRepository.php
namespace App\Lotes;

use PDO;
use Exception;
use App\Core\AuditLoggerService;

class LoteNovoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
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
            ':ciclo' => $data['lote_ciclo'],
            ':viveiro' => $data['lote_viveiro'],
            ':completo' => $data['lote_completo_calculado'],
        ];

        if ($id) {
            // Se já existe um ID, fazemos um UPDATE
            $sql = "UPDATE tbl_lotes_novo_header 
                    SET lote_numero = :numero, lote_data_fabricacao = :data_fab, 
                        lote_fornecedor_id = :fornecedor, lote_cliente_id = :cliente,  
                        lote_ciclo = :ciclo, lote_viveiro = :viveiro, 
                        lote_completo_calculado = :completo 
                    WHERE lote_id = :id";
            $params[':id'] = $id;
        } else {
            // Se não existe ID, fazemos um INSERT
            $sql = "INSERT INTO tbl_lotes_novo_header (
                        lote_numero, lote_data_fabricacao, lote_fornecedor_id, lote_cliente_id,
                        lote_ciclo, lote_viveiro, lote_completo_calculado, lote_usuario_id
                    ) VALUES (
                        :numero, :data_fab, :fornecedor, :cliente, 
                        :ciclo, :viveiro, :completo, :user_id
                    )";
            $params[':user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultId = $id ?: (int) $this->pdo->lastInsertId();

        // Lógica de Auditoria
        if ($id) { // Log de UPDATE
            $this->auditLogger->log('UPDATE', $resultId, 'tbl_lotes_novo_header', $dadosAntigos, $data,"");
        } else { // Log de CREATE
            $this->auditLogger->log('CREATE', $resultId, 'tbl_lotes_novo_header', null, $data,"");
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
            $this->auditLogger->log('CREATE', $novoId, 'tbl_lotes_novo_producao', null, $params,"");
        }

        return $novoId;
    }

    /**
     * Adiciona um item de embalagem (secundária), consumindo o saldo de um item de produção (primário).
     *
     * @param array $data Contém os IDs e quantidades necessários.
     * @return int O ID do novo item de embalagem criado.
     * @throws Exception
     */
    public function adicionarItemEmbalagem(array $data): int
    {
        // Validação dos dados de entrada (esta parte está correta)
        $loteId = filter_var($data['item_emb_lote_id'], FILTER_VALIDATE_INT);
        $prodSecId = filter_var($data['item_emb_prod_sec_id'], FILTER_VALIDATE_INT);
        $prodPrimId = filter_var($data['item_emb_prod_prim_id'], FILTER_VALIDATE_INT);
        $qtdSec = filter_var($data['item_emb_qtd_sec'], FILTER_VALIDATE_FLOAT);

        if (!$loteId || !$prodSecId || !$prodPrimId || !$qtdSec || $qtdSec <= 0) {
            throw new Exception("Dados insuficientes ou inválidos para adicionar o item de embalagem.");
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Busca os pesos necessários para o cálculo
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
                throw new Exception("Não foi possível calcular o rácio de consumo. Verifique a associação e os pesos dos produtos.");
            }

            // 2. Calcula o rácio (unidades por embalagem)
            $unidadesPorEmbalagem = (float) $pesos['peso_secundario'] / (float) $pesos['peso_primario'];

            if ($unidadesPorEmbalagem <= 0) {
                throw new Exception("O produto de embalagem secundária não tem uma quantidade de unidades primárias válida configurada.");
            }

            // 3. Calcula a quantidade total de embalagens primárias que serão consumidas
            $qtdPrimConsumida = $qtdSec * $unidadesPorEmbalagem;

            // 4. Busca o item de produção e bloqueia a linha para evitar concorrência (FOR UPDATE)
            $stmtProdPrim = $this->pdo->prepare("SELECT item_prod_saldo FROM tbl_lotes_novo_producao WHERE item_prod_id = :id FOR UPDATE");
            $stmtProdPrim->execute([':id' => $prodPrimId]);
            $saldoAtualPrimario = (float) $stmtProdPrim->fetchColumn();

            // 5. Verifica se há saldo suficiente
            if ($saldoAtualPrimario < $qtdPrimConsumida) {
                throw new Exception("Saldo insuficiente. Saldo disponível: {$saldoAtualPrimario}, Quantidade necessária: {$qtdPrimConsumida}.");
            }

            // 6. Atualiza o saldo do item de produção (subtrai o que foi consumido)
            $novoSaldo = $saldoAtualPrimario - $qtdPrimConsumida;
            $stmtUpdateSaldo = $this->pdo->prepare("UPDATE tbl_lotes_novo_producao SET item_prod_saldo = :saldo WHERE item_prod_id = :id");
            $stmtUpdateSaldo->execute([':saldo' => $novoSaldo, ':id' => $prodPrimId]);

            // 7. Insere o novo registo na tabela de embalagens
            $params = [
                ':lote_id' => $loteId,
                ':prod_sec_id' => $prodSecId,
                ':prod_prim_id' => $prodPrimId,
                ':qtd_sec' => $qtdSec,
                ':qtd_prim_cons' => $qtdPrimConsumida,
            ];
            $sqlInsert = "INSERT INTO tbl_lotes_novo_embalagem (
                        item_emb_lote_id, item_emb_prod_sec_id, item_emb_prod_prim_id, 
                        item_emb_qtd_sec, item_emb_qtd_prim_cons
                    ) VALUES (
                        :lote_id, :prod_sec_id, :prod_prim_id, 
                        :qtd_sec, :qtd_prim_cons
                    )";
            $stmtInsert = $this->pdo->prepare($sqlInsert);
            $stmtInsert->execute($params);

            $novoId = (int) $this->pdo->lastInsertId();

            // 8. Lógica de Auditoria
            $this->auditLogger->log('CREATE', $novoId, 'tbl_lotes_novo_embalagem', null, $params,"");
            $this->auditLogger->log('UPDATE', $prodPrimId, 'tbl_lotes_novo_producao', ['item_prod_saldo' => $saldoAtualPrimario], ['item_prod_saldo' => $novoSaldo],"");

            // 9. Se tudo correu bem, confirma as operações
            $this->pdo->commit();

            return $novoId;
        } catch (Exception $e) {
            // 10. Se algo deu errado, desfaz tudo
            $this->pdo->rollBack();
            throw $e; // Lança a exceção para que a camada superior (API) possa tratá-la
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
            $this->auditLogger->log('DELETE', $itemProdId, 'tbl_lotes_novo_producao', $item, null,"");
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

            $this->auditLogger->log('DELETE', $itemEmbId, 'tbl_lotes_novo_embalagem', $item, null,"");
            $this->auditLogger->log('UPDATE', $prodPrimItemId, 'tbl_lotes_novo_producao', ['saldo_revertido' => $qtdAReverter], null,"");

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
            $this->auditLogger->log('UPDATE', $itemProdId, 'tbl_lotes_novo_producao', $itemAtual, $data,"");
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

            $this->auditLogger->log('UPDATE', $itemEmbId, 'tbl_lotes_novo_embalagem', $itemAtual, $data,"");

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

            $this->auditLogger->log('CANCEL', $loteId, 'tbl_lotes_novo_header', ['lote_status' => $loteAtual['lote_status']], ['lote_status' => 'CANCELADO'],"");

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
     * @param int $loteId O ID do lote a ser reativado.
     * @return bool
     * @throws Exception
     */
    public function reativarLote(int $loteId): bool
    {
        // 1. Busca os dados do lote para validação e auditoria
        $stmtLote = $this->pdo->prepare("SELECT lote_status FROM tbl_lotes_novo_header WHERE lote_id = :id");
        $stmtLote->execute([':id' => $loteId]);
        $loteAtual = $stmtLote->fetch(PDO::FETCH_ASSOC);

        if (!$loteAtual) {
            throw new Exception("Lote não encontrado.");
        }

        // 2. Regra de negócio: Só se pode reativar um lote que esteja 'CANCELADO'
        if ($loteAtual['lote_status'] !== 'CANCELADO') {
            throw new Exception("Apenas lotes com o status 'CANCELADO' podem ser reativados.");
        }

        // 3. Atualiza o status do lote para 'EM ANDAMENTO'
        $stmtUpdate = $this->pdo->prepare("UPDATE tbl_lotes_novo_header SET lote_status = 'EM ANDAMENTO' WHERE lote_id = :id");
        $success = $stmtUpdate->execute([':id' => $loteId]);

        if ($success) {
            $this->auditLogger->log('REACTIVATE', $loteId, 'tbl_lotes_novo_header', ['lote_status' => 'CANCELADO'], ['lote_status' => 'EM ANDAMENTO'],"");
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
     * Processa a finalização parcial ou total de itens de um lote, agrupando por produto antes de atualizar o estoque.
     *
     * @param int $loteId O ID do lote.
     * @param array $itensParaFinalizar Um array de itens, onde cada item é ['item_id' => id, 'quantidade' => qtd].
     * @return bool
     * @throws Exception
     */
    public function finalizarLoteParcialmente(int $loteId, array $itensParaFinalizar): bool
    {
        if (empty($itensParaFinalizar)) {
            throw new Exception("Nenhum item foi selecionado para finalização.");
        }

        $this->pdo->beginTransaction();
        try {
            // Busca o número do lote para usar na observação
            $stmt_lote_header = $this->pdo->prepare("SELECT lote_numero FROM tbl_lotes_novo_header WHERE lote_id = :id");
            $stmt_lote_header->execute([':id' => $loteId]);
            $numeroDoLote = $stmt_lote_header->fetchColumn();

            if (!$numeroDoLote) {
                throw new Exception("Lote com ID {$loteId} não encontrado para finalização.");
            }

            // Array para agrupar as quantidades totais por ID de produto
            $quantidadesAgrupadasPorProduto = [];

            // --- PREPARA AS QUERIES QUE SERÃO USADAS NOS LOOPS ---
            $stmt_item = $this->pdo->prepare(
                "SELECT item_emb_prod_sec_id, (item_emb_qtd_sec - item_emb_qtd_finalizada) AS disponivel 
             FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id FOR UPDATE"
            );
            $stmt_update_item = $this->pdo->prepare(
                "UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = item_emb_qtd_finalizada + :qtd 
             WHERE item_emb_id = :id"
            );

            // --- PRIMEIRO LOOP: Validar, atualizar itens individuais e AGRUPAR por produto ---
            foreach ($itensParaFinalizar as $item) {
                $itemId = $item['item_id'];
                $qtdAFinalizar = (float) $item['quantidade'];

                if ($qtdAFinalizar <= 0)
                    continue;

                $stmt_item->execute([':id' => $itemId]);
                $itemDb = $stmt_item->fetch(PDO::FETCH_ASSOC);

                if (!$itemDb || $qtdAFinalizar > (float) $itemDb['disponivel']) {
                    throw new Exception("Quantidade a finalizar para o item ID {$itemId} é maior que a disponível.");
                }

                $produtoId = $itemDb['item_emb_prod_sec_id'];

                // Agrega a quantidade no array de agrupamento
                if (!isset($quantidadesAgrupadasPorProduto[$produtoId])) {
                    $quantidadesAgrupadasPorProduto[$produtoId] = 0;
                }
                $quantidadesAgrupadasPorProduto[$produtoId] += $qtdAFinalizar;

                // Atualiza a quantidade finalizada do item de embalagem individual
                $stmt_update_item->execute([':qtd' => $qtdAFinalizar, ':id' => $itemId]);
            }

            // Atualiza o status do lote (FINALIZADO ou PARCIALMENTE FINALIZADO)
            $itensAindaAbertos = $this->getItensParaFinalizar($loteId);
            $novoStatus = (empty($itensAindaAbertos)) ? 'FINALIZADO' : 'PARCIALMENTE FINALIZADO';

            // Prepara as partes da query
            $setClauses = ["lote_status = :status"];
            $params_update_header = [
                ':status' => $novoStatus,
                ':id' => $loteId
            ];

            // APENAS adiciona a data de finalização se o status for 'FINALIZADO'
            if ($novoStatus === 'FINALIZADO') {
                $setClauses[] = "lote_data_finalizacao = NOW()";
            }

            // Monta a query final garantindo que o WHERE venha por último
            $sql_update_header = "UPDATE tbl_lotes_novo_header SET " . implode(", ", $setClauses) . " WHERE lote_id = :id";

            $stmt_update_header = $this->pdo->prepare($sql_update_header);
            $stmt_update_header->execute($params_update_header);

            $this->auditLogger->log('UPDATE', $loteId, 'tbl_lotes_novo_header', null, ['novo_status' => $novoStatus],"");

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Busca os dados para a tabela de listagem de novos lotes (server-side).
     *
     * @param array $params Parâmetros do DataTables (busca, paginação, etc.).
     * @return array
     */
    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        $baseQuery = "FROM tbl_lotes_novo_header l 
                      LEFT JOIN tbl_entidades f ON l.lote_fornecedor_id = f.ent_codigo";

        $totalRecords = $this->pdo->query("SELECT COUNT(l.lote_id) FROM tbl_lotes_novo_header l")->fetchColumn();

        $whereClause = "";
        $queryParams = [];
        if (!empty($searchValue)) {
            $searchTerm = '%' . $searchValue . '%';

            $whereClause = " WHERE (l.lote_completo_calculado LIKE :search0 OR f.ent_razao_social LIKE :search1)";
            $queryParams[':search0'] = $searchTerm;
            $queryParams[':search1'] = $searchTerm;
        }

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(l.lote_id) $baseQuery $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT l.*, f.ent_razao_social AS fornecedor_razao_social 
                    $baseQuery $whereClause 
                    ORDER BY l.lote_data_cadastro DESC 
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);

        if (!empty($queryParams)) {
            foreach ($queryParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw']),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data
        ];
    }

    /**
     * Busca um lote novo completo (cabeçalho, produção e embalagem) pelo seu ID.
     * @param int $id O ID do lote (da tbl_lotes_novo_header).
     * @return array|null
     */
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
            "SELECT p.*, prod.prod_descricao 
             FROM tbl_lotes_novo_producao p 
             JOIN tbl_produtos prod ON p.item_prod_produto_id = prod.prod_codigo 
             WHERE p.item_prod_lote_id = :id ORDER BY p.item_prod_id"
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

    public function getVisaoGeralEstoque(array $params): array
    {
        // Parâmetros do DataTables
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        // Query Base para funcionar com lançamentos antigos e novos
        $baseQuery = "
        FROM tbl_estoque es
        JOIN tbl_produtos p ON es.estoque_produto_id = p.prod_codigo
        
        -- Join para buscar o item de embalagem (para compatibilidade com registros antigos)
        LEFT JOIN tbl_lotes_novo_embalagem lne ON es.estoque_lote_item_id = lne.item_emb_id
        
        -- Join inteligente para buscar o cabeçalho do lote:
        -- Tenta primeiro pelo novo campo 'estoque_lote_item_id'. 
        -- Se for nulo (registro antigo), tenta pelo caminho antigo através de 'lne.item_emb_lote_id'.
        LEFT JOIN tbl_lotes_novo_header lnh ON lnh.lote_id = CASE 
                                                                WHEN lne.item_emb_id IS NOT NULL THEN lne.item_emb_lote_id 
                                                                ELSE es.estoque_lote_item_id 
                                                             END
        LEFT JOIN tbl_entidades e ON lnh.lote_cliente_id = e.ent_codigo
        ";

        // Agrupamento correto que já implementamos
        $groupByClause = "GROUP BY p.prod_codigo, lnh.lote_id";

        $whereClause = "";
        $queryParams = []; // Array para guardar os parâmetros da busca

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

        // Contagem Total
        $totalRecordsQuery = "SELECT COUNT(*) FROM (SELECT 1 {$baseQuery} {$groupByClause}) as subquery";
        $totalRecords = $this->pdo->query($totalRecordsQuery)->fetchColumn();

        // Contagem Filtrada
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(*) FROM (SELECT 1 {$baseQuery} {$whereClause} {$groupByClause}) as subquery");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // Query Principal para buscar os dados
        $sqlData = "
        SELECT 
            p.prod_tipo as tipo_produto,
            p.prod_subtipo as subtipo,
            p.prod_classificacao as classificacao,
            p.prod_codigo_interno as codigo_interno,
            p.prod_descricao as descricao_produto,
            p.prod_peso_embalagem as peso_embalagem,
            COALESCE(lnh.lote_completo_calculado, 'Lote Avulso') as lote,
            COALESCE(NULLIF(e.ent_nome_fantasia, ''), e.ent_razao_social) as cliente_lote_nome,
            lnh.lote_data_fabricacao as data_fabricacao,
            SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END) as total_caixas,
            SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END) * p.prod_peso_embalagem as peso_total
        
        {$baseQuery}
        {$whereClause}
        
        {$groupByClause}

        ORDER BY descricao_produto ASC, lote ASC
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

    /**
     * Busca os detalhes completos de um único item de lote do novo sistema.
     * @param int $loteItemId ID do item da tabela tbl_lotes_novo_embalagem
     * @return array|null
     */
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

            // 7. Auditoria
            $this->auditLogger->log('CREATE', $novoMistaHeaderId, 'tbl_caixas_mistas_header', null, $data, 'Criação de Caixa Mista');

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
    public function excluirCaixaMista(int $mistaId): bool
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

            $this->auditLogger->log('DELETE', $mistaId, 'tbl_caixas_mistas_header', ['mista_id' => $mistaId], null, 'Exclusão de Caixa Mista sem itens de origem.');

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
}
