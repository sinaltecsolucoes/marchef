<?php
// Ficheiro: src/Estoque/EstoqueRepository.php

namespace App\Estoque;

use PDO;
use Exception;

class EstoqueRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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
}
