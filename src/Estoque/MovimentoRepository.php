<?php
// /src/Estoque/MovimentoRepository.php
namespace App\Estoque;

use PDO;
use Exception;

class MovimentoRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Registra uma movimentação no Kardex.
     */
    public function registrar(
        string $tipo,
        int $loteItemId,
        float $quantidade,
        int $usuarioId,
        ?int $origemId = null,
        ?int $destinoId = null,
        ?string $obs = null,
        ?int $docRef = null
    ): bool {
        $sql = "INSERT INTO tbl_estoque_movimento 
                (movimento_tipo, movimento_lote_item_id, movimento_quantidade, movimento_usuario_id, 
                 movimento_endereco_origem_id, movimento_endereco_destino_id, movimento_observacao, movimento_documento_ref, movimento_data)
                VALUES 
                (:tipo, :item_id, :qtd, :user, :origem, :destino, :obs, :doc_ref, NOW())";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':tipo' => $tipo,
            ':item_id' => $loteItemId,
            ':qtd' => $quantidade,
            ':user' => $usuarioId,
            ':origem' => $origemId,
            ':destino' => $destinoId,
            ':obs' => $obs,
            ':doc_ref' => $docRef
        ]);
    }

    /**
     * Busca o extrato (Kardex) de um item específico do lote.
     */
    public function getKardexPorItem(int $loteItemId): array
    {
        $sql = "SELECT m.*, 
                       u.nome_usuario,
                       eo.endereco_completo as nome_origem,
                       ed.endereco_completo as nome_destino
                FROM tbl_estoque_movimento m
                LEFT JOIN tbl_usuarios u ON m.movimento_usuario_id = u.id_usuario
                LEFT JOIN tbl_estoque_enderecos eo ON m.movimento_endereco_origem_id = eo.endereco_id
                LEFT JOIN tbl_estoque_enderecos ed ON m.movimento_endereco_destino_id = ed.endereco_id
                WHERE m.movimento_lote_item_id = :item_id
                ORDER BY m.movimento_data DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':item_id' => $loteItemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
