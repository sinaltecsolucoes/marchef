<?php
// /src/Entrada/EntradaRepository.php
namespace App\Entrada;

use PDO;
use Exception;
use App\Core\AuditLoggerService;

class EntradaRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    public function getCamaraOptions(): array
    {
        $stmt = $this->pdo->query("SELECT camara_id as id, camara_nome as nome FROM tbl_estoque_camaras ORDER BY camara_nome ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEnderecoOptions(): array
    {
        $stmt = $this->pdo->query("SELECT endereco_id as id, endereco_completo as nome FROM tbl_estoque_enderecos ORDER BY endereco_completo ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Salva uma nova entrada, criando um item e alocando-o no endereço.
     * @param int $enderecoId O ID do endereço de destino.
     * @param int $usuarioId O ID do usuário logado.
     * @param array $leitura Os dados da leitura (produtoId, loteId, quantidade).
     * @return bool
     * @throws Exception
     */
    public function salvarEntrada(int $enderecoId, int $usuarioId, array $leitura): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Cria o novo item de embalagem na tabela de lotes.
            $stmtItem = $this->pdo->prepare(
                "INSERT INTO tbl_lotes_novo_embalagem (item_emb_lote_id, item_emb_prod_sec_id, item_emb_qtd_sec, item_emb_qtd_finalizada)
                 VALUES (:lote_id, :prod_id, :quantidade, :quantidade)"
            );
            $stmtItem->execute([
                ':lote_id' => $leitura['loteId'],
                ':prod_id' => $leitura['produtoId'],
                ':quantidade' => $leitura['quantidade']
            ]);
            $loteItemId = (int) $this->pdo->lastInsertId();

            // 2. Cria a alocação para este item no endereço selecionado.
            $stmtAlocacao = $this->pdo->prepare(
                "INSERT INTO tbl_estoque_alocacoes (alocacao_endereco_id, alocacao_lote_item_id, alocacao_quantidade, alocacao_data, alocacao_usuario_id)
                 VALUES (:endereco_id, :lote_item_id, :quantidade, NOW(), :usuario_id)"
            );
            $stmtAlocacao->execute([
                ':endereco_id' => $enderecoId,
                ':lote_item_id' => $loteItemId,
                ':quantidade' => $leitura['quantidade'],
                ':usuario_id' => $usuarioId
            ]);

            // 3. O TRIGGER cuidará do registro na tabela `tbl_estoque`.

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}