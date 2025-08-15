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
            $this->auditLogger->log('UPDATE', $resultId, 'tbl_lotes_novo_header', $dadosAntigos, $data);
        } else { // Log de CREATE
            $this->auditLogger->log('CREATE', $resultId, 'tbl_lotes_novo_header', null, $data);
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
            $this->auditLogger->log('CREATE', $novoId, 'tbl_lotes_novo_producao', null, $params);
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
            $this->auditLogger->log('CREATE', $novoId, 'tbl_lotes_novo_embalagem', null, $params);
            $this->auditLogger->log('UPDATE', $prodPrimId, 'tbl_lotes_novo_producao', ['item_prod_saldo' => $saldoAtualPrimario], ['item_prod_saldo' => $novoSaldo]);

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
            $this->auditLogger->log('DELETE', $itemProdId, 'tbl_lotes_novo_producao', $item, null);
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

            $this->auditLogger->log('DELETE', $itemEmbId, 'tbl_lotes_novo_embalagem', $item, null);
            $this->auditLogger->log('UPDATE', $prodPrimItemId, 'tbl_lotes_novo_producao', ['saldo_revertido' => $qtdAReverter], null);

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
            $this->auditLogger->log('UPDATE', $itemProdId, 'tbl_lotes_novo_producao', $itemAtual, $data);
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
    /*    public function atualizarItemEmbalagem(int $itemEmbId, array $data): bool
        {
            $this->pdo->beginTransaction();
            try {
                // 1. Busca os dados atuais do item de embalagem
                $stmtItem = $this->pdo->prepare("SELECT * FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id");
                $stmtItem->execute([':id' => $itemEmbId]);
                $itemAtual = $stmtItem->fetch(PDO::FETCH_ASSOC);

                if (!$itemAtual)
                    throw new Exception("Item de embalagem não encontrado.");

                // 2. Calcula o NOVO consumo
                $novoProdSecId = $data['item_emb_prod_sec_id'];
                $novaQtdSec = (float) $data['item_emb_qtd_sec'];
                $sqlPesos = "SELECT IF(p_prim.prod_peso_embalagem > 0, p_sec.prod_peso_embalagem / p_prim.prod_peso_embalagem, 0) AS ratio FROM tbl_produtos p_sec JOIN tbl_produtos p_prim ON p_sec.prod_primario_id = p_prim.prod_codigo WHERE p_sec.prod_codigo = :id_sec";
                $stmtPesos = $this->pdo->prepare($sqlPesos);
                $stmtPesos->execute([':id_sec' => $novoProdSecId]);
                $unidadesPorEmbalagem = (float) $stmtPesos->fetchColumn();
                $novoConsumoPrimario = $novaQtdSec * $unidadesPorEmbalagem;

                // 3. Calcula a DIFERENÇA de consumo para ajustar o saldo
                $consumoAntigo = (float) $itemAtual['item_emb_qtd_prim_cons'];
                $diferencaDeConsumo = $novoConsumoPrimario - $consumoAntigo;
                $prodPrimItemId = $itemAtual['item_emb_prod_prim_id']; // ID do item de produção

                // 4. Valida se o saldo do item de produção suporta a MUDANÇA
                $stmtSaldo = $this->pdo->prepare("SELECT item_prod_saldo FROM tbl_lotes_novo_producao WHERE item_prod_id = :id FOR UPDATE");
                $stmtSaldo->execute([':id' => $prodPrimItemId]);
                $saldoAtual = (float) $stmtSaldo->fetchColumn();

                if ($saldoAtual < $diferencaDeConsumo) {
                    throw new Exception("Saldo insuficiente no item de produção para cobrir o ajuste. Saldo disponível: {$saldoAtual}, ajuste necessário: {$diferencaDeConsumo}.");
                }

                // 5. ATUALIZA o saldo do item de produção com a diferença
                $stmtAjusteSaldo = $this->pdo->prepare("UPDATE tbl_lotes_novo_producao SET item_prod_saldo = item_prod_saldo - :diferenca WHERE item_prod_id = :id");
                $stmtAjusteSaldo->execute([':diferenca' => $diferencaDeConsumo, ':id' => $prodPrimItemId]);

                // 6. ATUALIZA o próprio item de embalagem
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

                $this->auditLogger->log('UPDATE', $itemEmbId, 'tbl_lotes_novo_embalagem', $itemAtual, $data);

                $this->pdo->commit();
                return true;

            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }*/

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

            // --- LÓGICA CORRIGIDA: REVERTER E REAPLICAR ---

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

            $this->auditLogger->log('UPDATE', $itemEmbId, 'tbl_lotes_novo_embalagem', $itemAtual, $data);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Processa a finalização parcial ou total de itens de um lote, atualizando o stock.
     *
     * @param int $loteId O ID do lote.
     * @param array $itensParaFinalizar Um array de itens, onde cada item é ['item_id' => id, 'quantidade' => qtd].
     * @return bool
     * @throws Exception
     */
    /* public function finalizarLoteParcialmente(int $loteId, array $itensParaFinalizar): bool
     {
         if (empty($itensParaFinalizar)) {
             throw new Exception("Nenhum item foi selecionado para finalização.");
         }

         $this->pdo->beginTransaction();
         try {
             $stmt_item = $this->pdo->prepare(
                 "SELECT item_emb_prod_sec_id, (item_emb_qtd_sec - item_emb_qtd_finalizada) AS disponivel 
              FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id FOR UPDATE"
             );
             $stmt_update_item = $this->pdo->prepare(
                 "UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = item_emb_qtd_finalizada + :qtd 
              WHERE item_emb_id = :id"
             );
             // ATENÇÃO: Ajuste os nomes das colunas de tbl_estoque conforme o seu banco de dados
             $stmt_insert_estoque = $this->pdo->prepare(
                 "INSERT INTO tbl_estoque (est_lote_item_id, est_produto_id, est_saldo, est_tipo_mov, est_data_mov) 
              VALUES (:lote_item_id, :produto_id, :saldo, 'ENTRADA', NOW())"
             );

             foreach ($itensParaFinalizar as $item) {
                 $itemId = $item['item_id'];
                 $qtdAFinalizar = (float) $item['quantidade'];

                 if ($qtdAFinalizar <= 0)
                     continue; // Pula itens com quantidade zerada

                 // Valida se a quantidade a finalizar é permitida
                 $stmt_item->execute([':id' => $itemId]);
                 $itemDb = $stmt_item->fetch(PDO::FETCH_ASSOC);

                 if (!$itemDb || $qtdAFinalizar > (float) $itemDb['disponivel']) {
                     throw new Exception("Quantidade a finalizar para o item ID {$itemId} é maior que a disponível.");
                 }

                 // 1. Insere no stock
                 $stmt_insert_estoque->execute([
                     ':lote_item_id' => $itemId,
                     ':produto_id' => $itemDb['item_emb_prod_sec_id'],
                     ':saldo' => $qtdAFinalizar
                 ]);

                 // 2. Atualiza a quantidade finalizada no item de embalagem
                 $stmt_update_item->execute([':qtd' => $qtdAFinalizar, ':id' => $itemId]);

                 $this->auditLogger->log('CREATE', $this->pdo->lastInsertId(), 'tbl_estoque', null, ['item_origem_id' => $itemId, 'quantidade' => $qtdAFinalizar]);
             }

             // 3. Verifica o status final do lote e atualiza o cabeçalho
             $itensAindaAbertos = (int) $this->getItensParaFinalizar($loteId);
             $novoStatus = (count($itensAindaAbertos) === 0) ? 'FINALIZADO' : 'PARCIALMENTE FINALIZADO';

             $stmt_update_header = $this->pdo->prepare("UPDATE tbl_lotes_novo_header SET lote_status = :status WHERE lote_id = :id");
             $stmt_update_header->execute([':status' => $novoStatus, ':id' => $loteId]);

             $this->auditLogger->log('UPDATE', $loteId, 'tbl_lotes_novo_header', null, ['novo_status' => $novoStatus]);

             $this->pdo->commit();
             return true;

         } catch (Exception $e) {
             $this->pdo->rollBack();
             throw $e;
         }
     }*/
    /**
     * Processa a finalização parcial ou total de itens de um lote, atualizando o stock.
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
            $stmt_item = $this->pdo->prepare(
                "SELECT item_emb_prod_sec_id, (item_emb_qtd_sec - item_emb_qtd_finalizada) AS disponivel 
             FROM tbl_lotes_novo_embalagem WHERE item_emb_id = :id FOR UPDATE"
            );
            $stmt_update_item = $this->pdo->prepare(
                "UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = item_emb_qtd_finalizada + :qtd 
             WHERE item_emb_id = :id"
            );

            $stmt_insert_estoque = $this->pdo->prepare(
                "INSERT INTO tbl_estoque (estoque_lote_item_id, estoque_produto_id, estoque_quantidade, estoque_tipo_movimento) 
             VALUES (:lote_item_id, :produto_id, :quantidade, 'ENTRADA')"
            );

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

                $stmt_insert_estoque->execute([
                    ':lote_item_id' => $itemId,
                    ':produto_id' => $itemDb['item_emb_prod_sec_id'],
                    ':quantidade' => $qtdAFinalizar
                ]);

                $stmt_update_item->execute([':qtd' => $qtdAFinalizar, ':id' => $itemId]);

                $this->auditLogger->log('CREATE', $this->pdo->lastInsertId(), 'tbl_estoque', null, ['item_origem_id' => $itemId, 'quantidade' => $qtdAFinalizar]);
            }

            $itensAindaAbertos = $this->getItensParaFinalizar($loteId);
            $novoStatus = (count($itensAindaAbertos) === 0) ? 'FINALIZADO' : 'PARCIALMENTE FINALIZADO';

            $stmt_update_header = $this->pdo->prepare("UPDATE tbl_lotes_novo_header SET lote_status = :status WHERE lote_id = :id");
            $stmt_update_header->execute([':status' => $novoStatus, ':id' => $loteId]);

            $this->auditLogger->log('UPDATE', $loteId, 'tbl_lotes_novo_header', null, ['novo_status' => $novoStatus]);

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
            $whereClause = " WHERE (l.lote_completo_calculado LIKE :search OR f.ent_razao_social LIKE :search)";
            $queryParams[':search'] = '%' . $searchValue . '%';
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
        if (!empty($searchValue)) {
            $stmt->bindValue(':search', $queryParams[':search']);
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
        $stmt = $this->pdo->query("SELECT MAX(lote_numero) FROM tbl_lotes_novo_header");
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
}
