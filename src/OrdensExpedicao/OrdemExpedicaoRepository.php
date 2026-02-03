<?php
// /src/OrdensExpedicao/OrdemExpedicaoRepository.php
namespace App\OrdensExpedicao;

use PDO;
use PDOException;
use Exception;
use App\Core\AuditLoggerService;

class OrdemExpedicaoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Busca todas as Ordens de Expedição para a DataTable.
     */
    public function findAllForDataTable(array $params): array
    {
        $baseQuery = "FROM tbl_ordens_expedicao_header oe
                  JOIN tbl_usuarios u ON oe.oe_usuario_id = u.usu_codigo
                  LEFT JOIN tbl_carregamentos c ON oe.oe_carregamento_id = c.car_id";

        $totalRecords = $this->pdo->query("SELECT COUNT(oe.oe_id) 
                                                  FROM tbl_ordens_expedicao_header oe")->fetchColumn();

        $sqlData = "SELECT 
                    oe.oe_id, oe.oe_numero, oe.oe_data, oe.oe_status,
                    oe.oe_tipo_operacao,
                    oe.oe_carregamento_id,
                    c.car_numero AS carregamento_numero,
                    u.usu_nome AS usuario_nome
                $baseQuery 
                ORDER BY oe.oe_data DESC, oe.oe_id DESC
                LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalRecords, // Simplificado por agora
            "data" => $data
        ];
    }

    public function getNextOrderNumber(): string
    {
        $stmt = $this->pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(oe_numero, '.', 1) AS UNSIGNED)) FROM tbl_ordens_expedicao_header");
        $lastNum = ($stmt->fetchColumn() ?: 0) + 1;
        $sequence = str_pad($lastNum, 4, '0', STR_PAD_LEFT);

        $datePart = date('m.Y');
        return $sequence . '.' . $datePart;
    }

    public function createHeader(array $data, int $usuarioId): int
    {
        $sql = "INSERT INTO tbl_ordens_expedicao_header (oe_numero, oe_data, oe_usuario_id) 
                VALUES (:numero, :data, :usuario_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':numero' => $data['oe_numero'],
            ':data' => $data['oe_data'],
            ':usuario_id' => $usuarioId
        ]);
        $newId = (int) $this->pdo->lastInsertId();
        $this->auditLogger->log('CREATE', $newId, 'tbl_ordens_expedicao_header', null, $data, "");
        return $newId;
    }

    public function findOrdemCompleta(int $id): ?array
    {
        $ordem = [];
        // 1. Busca o cabeçalho
        $stmtHeader = $this->pdo->prepare("SELECT * FROM tbl_ordens_expedicao_header WHERE oe_id = :id");
        $stmtHeader->execute([':id' => $id]);
        $ordem['header'] = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$ordem['header']) {
            return null;
        }

        // 2. Busca os pedidos/clientes
        $stmtPedidos = $this->pdo->prepare(
            "SELECT p.*, COALESCE(e.ent_nome_fantasia, e.ent_razao_social) AS ent_razao_social, end.end_uf
                    FROM tbl_ordens_expedicao_pedidos p
                    JOIN tbl_entidades e ON p.oep_cliente_id = e.ent_codigo
                    LEFT JOIN tbl_enderecos end ON e.ent_codigo = end.end_entidade_id AND end.end_tipo_endereco = 'Principal'
                    WHERE p.oep_ordem_id = :id 
                    GROUP BY p.oep_id
                    ORDER BY p.oep_ordem_carregamento ASC, p.oep_id ASC"
        );
        $stmtPedidos->execute([':id' => $id]);
        $ordem['pedidos'] = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

        // 3. Para cada pedido, busca os itens
        foreach ($ordem['pedidos'] as $key => $pedido) {
            $stmtItens = $this->pdo->prepare(
                "SELECT 
                            i.oei_id,
                            i.oei_alocacao_id,
                            i.oei_quantidade,
                            i.oei_observacao,
                            lne.item_emb_id,                
                            p_sec.prod_codigo_interno,
                            p_sec.prod_descricao,
                            COALESCE(p_prim.prod_peso_embalagem, 0) AS peso_primario,
                            COALESCE(p_sec.prod_peso_embalagem, 0) AS peso_secundario,
                            cam.camara_industria AS industria,
                            COALESCE(ent_lote.ent_nome_fantasia, ent_lote.ent_razao_social, 'N/A') AS cliente_lote_nome,
                            lnh.lote_completo_calculado,
                            ee.endereco_completo
                        FROM tbl_ordens_expedicao_itens i
                       
                        JOIN tbl_estoque_alocacoes ea ON i.oei_alocacao_id = ea.alocacao_id
                        JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                        JOIN tbl_produtos p_sec ON lne.item_emb_prod_sec_id = p_sec.prod_codigo
                        
                        LEFT JOIN tbl_lotes_novo_producao lnp ON lne.item_emb_prod_prim_id = lnp.item_prod_id
                        -- LEFT JOIN tbl_produtos p_prim ON lnp.item_prod_produto_id = p_prim.prod_codigo 
                        LEFT JOIN tbl_produtos p_prim ON lnp.item_prod_id = p_prim.prod_codigo

                        LEFT JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                        LEFT JOIN tbl_entidades ent_lote ON lnh.lote_cliente_id = ent_lote.ent_codigo

                        JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                        JOIN tbl_estoque_camaras cam ON ee.endereco_camara_id = cam.camara_id

                        WHERE i.oei_pedido_id = :pedido_id
                        ORDER BY i.oei_id"
            );

            $stmtItens->execute([':pedido_id' => $pedido['oep_id']]);
            $ordem['pedidos'][$key]['itens'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
        }

        return $ordem;
    }

    public function addPedidoCliente(array $data): int
    {
        $sql = "INSERT INTO tbl_ordens_expedicao_pedidos (oep_ordem_id, oep_cliente_id, oep_numero_pedido) 
                VALUES (:ordem_id, :cliente_id, :numero_pedido)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ordem_id' => $data['oep_ordem_id'],
            ':cliente_id' => $data['oep_cliente_id'],
            ':numero_pedido' => $data['oep_numero_pedido'] ?: null
        ]);
        $newId = (int) $this->pdo->lastInsertId();
        $this->auditLogger->log('CREATE', $newId, 'tbl_ordens_expedicao_pedidos', null, $data, "");
        return $newId;
    }

    public function findEstoqueAlocadoParaSelecao(array $params): array
    {
        $baseQuery = "FROM tbl_estoque_alocacoes ea
                      JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                      JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                      JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                      JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id";

        // Subquery para calcular o total já reservado em outras ordens
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) FROM tbl_ordens_expedicao_itens WHERE oei_alocacao_id = ea.alocacao_id AND oei_status = 'PENDENTE')";

        // Apenas itens com saldo disponível (alocado > reservado)
        $whereClause = " WHERE ea.alocacao_quantidade > {$subQueryReservado}";

        $totalRecords = $this->pdo->query("SELECT COUNT(ea.alocacao_id) $baseQuery $whereClause")->fetchColumn();

        $sqlData = "SELECT 
                        ea.alocacao_id,
                        p.prod_descricao,
                        lnh.lote_completo_calculado,
                        ee.endereco_completo,
                        (ea.alocacao_quantidade - {$subQueryReservado}) AS saldo_disponivel
                    $baseQuery $whereClause 
                    ORDER BY p.prod_descricao, lnh.lote_completo_calculado
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalRecords,
            "data" => $data
        ];
    }

    public function addItemPedido(array $data): int
    {
        // Validações
        $pedidoId = filter_var($data['oei_pedido_id'], FILTER_VALIDATE_INT);
        $alocacaoId = filter_var($data['oei_alocacao_id'], FILTER_VALIDATE_INT);
        $quantidade = filter_var($data['oei_quantidade'], FILTER_VALIDATE_FLOAT);

        if (!$pedidoId || !$alocacaoId || !$quantidade || $quantidade <= 0) {
            throw new Exception("Dados inválidos para adicionar o item.");
        }

        // Validação adicional: Verificar se a quantidade não excede o saldo disponível
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) 
                               FROM tbl_ordens_expedicao_itens 
                               WHERE oei_alocacao_id = ea.alocacao_id 
                               AND oei_status = 'PENDENTE')";
        $query = "SELECT (ea.alocacao_quantidade - {$subQueryReservado}) AS saldo_disponivel
              FROM tbl_estoque_alocacoes ea
              WHERE ea.alocacao_id = :alocacao_id";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':alocacao_id' => $alocacaoId]);
        $saldo = $stmt->fetchColumn();

        if ($saldo === false || $saldo < $quantidade) {
            throw new Exception("Quantidade solicitada excede o saldo disponível no endereço.");
        }

        // Verificar se já existe um item com o mesmo pedido e alocação
        $checkQuery = "SELECT oei_id, oei_quantidade 
                       FROM tbl_ordens_expedicao_itens 
                       WHERE oei_pedido_id = :pedido_id 
                       AND oei_alocacao_id = :alocacao_id LIMIT 1";
        $checkStmt = $this->pdo->prepare($checkQuery);
        $checkStmt->execute([
            ':pedido_id' => $pedidoId,
            ':alocacao_id' => $alocacaoId
        ]);
        $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingItem) {
            // Se existir, soma a quantidade e atualiza o registro existente
            $novaQuantidade = $existingItem['oei_quantidade'] + $quantidade;
            $updateSql = "UPDATE tbl_ordens_expedicao_itens 
                      SET oei_quantidade = :nova_quantidade, oei_observacao = :observacao
                      WHERE oei_id = :oei_id";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([
                ':nova_quantidade' => $novaQuantidade,
                ':observacao' => $data['oei_observacao'] ?? null,  // Atualiza observação se fornecida
                ':oei_id' => $existingItem['oei_id']
            ]);
            $newId = $existingItem['oei_id'];  // Retorna o ID existente
            $this->auditLogger->log('UPDATE', $newId, 'tbl_ordens_expedicao_itens', $existingItem, $data, "");
        } else {
            // Se não existir, insere novo
            $sql = "INSERT INTO tbl_ordens_expedicao_itens (oei_pedido_id, oei_alocacao_id, oei_quantidade, oei_observacao)
                VALUES (:pedido_id, :alocacao_id, :quantidade, :observacao)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':pedido_id' => $pedidoId,
                ':alocacao_id' => $alocacaoId,
                ':quantidade' => $quantidade,
                ':observacao' => $data['oei_observacao'] ?? null
            ]);
            $newId = (int) $this->pdo->lastInsertId();
            $this->auditLogger->log('CREATE', $newId, 'tbl_ordens_expedicao_itens', null, $data, "");
        }

        return $newId;
    }

    public function getProdutosDisponiveisParaSelecao(): array
    {
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) 
                               FROM tbl_ordens_expedicao_itens 
                               WHERE oei_alocacao_id = ea.alocacao_id 
                               AND oei_status = 'PENDENTE')";
        $sql = "SELECT DISTINCT
                    p.prod_codigo AS id,
                    CONCAT(p.prod_descricao, ' (Cód: ', p.prod_codigo_interno, ')') AS text
                FROM tbl_estoque_alocacoes ea
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                WHERE ea.alocacao_quantidade > {$subQueryReservado}
                ORDER BY p.prod_descricao";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLotesDisponiveisPorProduto(int $produtoId): array
    {
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) 
                               FROM tbl_ordens_expedicao_itens 
                               WHERE oei_alocacao_id = ea.alocacao_id 
                               AND oei_status = 'PENDENTE')";
        $sql = "SELECT DISTINCT
                    lne.item_emb_id AS id,
                    CONCAT(
                        lnh.lote_completo_calculado, 
                        -- Lógica para adicionar o nome do cliente se existir
                        CASE 
                            WHEN ent.ent_codigo IS NOT NULL THEN CONCAT(' - ', COALESCE(ent.ent_nome_fantasia, ent.ent_razao_social))
                            ELSE '' 
                        END,
                        ' [Saldo Total: ', FORMAT(SUM(ea.alocacao_quantidade - {$subQueryReservado}), 3, 'de_DE'), ']') 
                        AS text
                FROM tbl_estoque_alocacoes ea
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                LEFT JOIN tbl_entidades ent ON lnh.lote_cliente_id = ent.ent_codigo 
                WHERE lne.item_emb_prod_sec_id = :produto_id
                GROUP BY lne.item_emb_id, lnh.lote_completo_calculado, ent.ent_codigo, ent.ent_nome_fantasia, ent.ent_razao_social
                HAVING SUM(ea.alocacao_quantidade - {$subQueryReservado}) > 0
                ORDER BY lnh.lote_completo_calculado";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':produto_id' => $produtoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEnderecosDisponiveisPorLoteItem(int $loteItemId): array
    {
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) 
                                FROM tbl_ordens_expedicao_itens 
                                WHERE oei_alocacao_id = ea.alocacao_id AND oei_status = 'PENDENTE')";
        $sql = "SELECT 
                     ea.alocacao_id AS id,
                     CONCAT(ee.endereco_completo, ' [Saldo: ', FORMAT((ea.alocacao_quantidade - {$subQueryReservado}), 3, 'de_DE'), ']') as text,
                     (ea.alocacao_quantidade - {$subQueryReservado}) as saldo_disponivel
                 FROM tbl_estoque_alocacoes ea
                 JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                 WHERE ea.alocacao_lote_item_id = :lote_item_id
                 AND ea.alocacao_quantidade > {$subQueryReservado}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lote_item_id' => $loteItemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remove um pedido e todos os seus itens associados.
     * @param int $pedidoId ID do pedido (oep_id)
     * @throws Exception Se o pedido não for encontrado ou houver erro na exclusão
     */
    public function removePedido(int $pedidoId): void
    {
        // Verificar se o pedido existe
        $checkQuery = "SELECT COUNT(*) FROM tbl_ordens_expedicao_pedidos WHERE oep_id = :pedido_id";
        $checkStmt = $this->pdo->prepare($checkQuery);
        $checkStmt->execute([':pedido_id' => $pedidoId]);
        if ($checkStmt->fetchColumn() == 0) {
            throw new Exception("Pedido não encontrado.");
        }

        // Iniciar transação para garantir consistência
        $this->pdo->beginTransaction();
        try {
            // Excluir itens associados ao pedido
            $deleteItens = "DELETE FROM tbl_ordens_expedicao_itens WHERE oei_pedido_id = :pedido_id";
            $deleteItensStmt = $this->pdo->prepare($deleteItens);
            $deleteItensStmt->execute([':pedido_id' => $pedidoId]);

            // Excluir o pedido
            $deletePedido = "DELETE FROM tbl_ordens_expedicao_pedidos WHERE oep_id = :pedido_id";
            $deletePedidoStmt = $this->pdo->prepare($deletePedido);
            $deletePedidoStmt->execute([':pedido_id' => $pedidoId]);

            // Registrar no log de auditoria
            $this->auditLogger->log('DELETE', $pedidoId, 'tbl_ordens_expedicao_pedidos', null, null, "");
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erro ao remover pedido: " . $e->getMessage());
        }
    }

    /**
     * Remove um item de um pedido.
     * @param int $itemId ID do item (oei_id)
     * @throws Exception Se o item não for encontrado ou houver erro na exclusão
     */
    public function removeItem(int $itemId): void
    {
        // Verificar se o item existe
        $checkQuery = "SELECT COUNT(*) FROM tbl_ordens_expedicao_itens WHERE oei_id = :item_id";
        $checkStmt = $this->pdo->prepare($checkQuery);
        $checkStmt->execute([':item_id' => $itemId]);
        if ($checkStmt->fetchColumn() == 0) {
            throw new Exception("Item não encontrado.");
        }

        // Excluir o item
        $sql = "DELETE FROM tbl_ordens_expedicao_itens WHERE oei_id = :item_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':item_id' => $itemId]);

        // Registrar no log de auditoria
        $this->auditLogger->log('DELETE', $itemId, 'tbl_ordens_expedicao_itens', null, null, "");
    }

    /**
     * Busca os detalhes de quais Ordens de Expedição estão reservando um item de estoque específico.
     * @param int $alocacaoId
     * @return array
     */
    public function findReservaDetalhesPorAlocacao(int $alocacaoId): array
    {
        $sql = "SELECT
                    oeh.oe_numero,
                    COALESCE(e.ent_nome_fantasia, e.ent_razao_social) AS cliente_nome,
                    oep.oep_numero_pedido,
                    oei.oei_quantidade
                FROM tbl_ordens_expedicao_itens oei
                JOIN tbl_ordens_expedicao_pedidos oep ON oei.oei_pedido_id = oep.oep_id
                JOIN tbl_ordens_expedicao_header oeh ON oep.oep_ordem_id = oeh.oe_id
                JOIN tbl_entidades e ON oep.oep_cliente_id = e.ent_codigo
                WHERE oei.oei_alocacao_id = :alocacao_id AND oeh.oe_status = 'EM ELABORAÇÃO'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':alocacao_id' => $alocacaoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca os detalhes de um item específico da ordem para preencher o modal de edição.
     * Inclui o cálculo do saldo máximo editável.
     * @param int $oeiId
     * @return array|null
     */
    public function findItemDetalhesParaEdicao(int $oeiId): ?array
    {
        $sql = "SELECT
                    oei.oei_quantidade,
                    oei.oei_observacao,
                    p.prod_descricao,
                    -- A mágica está aqui:
                    -- (Total Físico no Endereço - Total Reservado por TODOS) + Quantidade deste próprio item
                    (
                        ea.alocacao_quantidade - 
                        (SELECT COALESCE(SUM(outras_oei.oei_quantidade), 0) 
                         FROM tbl_ordens_expedicao_itens outras_oei 
                         WHERE outras_oei.oei_alocacao_id = oei.oei_alocacao_id)
                        + oei.oei_quantidade
                    ) as max_quantidade_disponivel
                FROM
                    tbl_ordens_expedicao_itens oei
                JOIN tbl_estoque_alocacoes ea ON oei.oei_alocacao_id = ea.alocacao_id
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                WHERE oei.oei_id = :oei_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':oei_id' => $oeiId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualiza a quantidade e/ou observação de um item existente na Ordem de Expedição.
     * @param int $oeiId
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function updateItem(int $oeiId, array $data): bool
    {
        $novaQuantidade = filter_var($data['oei_quantidade'], FILTER_VALIDATE_FLOAT);
        $novaObservacao = $data['oei_observacao'] ?? null;

        // 1. Validação básica
        if ($novaQuantidade === false || $novaQuantidade <= 0) {
            throw new Exception("A nova quantidade fornecida é inválida.");
        }

        // 2. Busca os dados atuais do item para validação e auditoria
        $dadosAtuais = $this->pdo->prepare("SELECT * FROM tbl_ordens_expedicao_itens WHERE oei_id = :oei_id");
        $dadosAtuais->execute([':oei_id' => $oeiId]);
        $itemAtual = $dadosAtuais->fetch(PDO::FETCH_ASSOC);

        if (!$itemAtual) {
            throw new Exception("Item da ordem não encontrado.");
        }
        $alocacaoId = $itemAtual['oei_alocacao_id'];

        // 3. Validação CRÍTICA de saldo
        $querySaldo = "SELECT (
                            ea.alocacao_quantidade - 
                            (SELECT COALESCE(SUM(oei.oei_quantidade), 0) 
                             FROM tbl_ordens_expedicao_itens oei 
                             WHERE oei.oei_alocacao_id = ea.alocacao_id 
                             AND oei.oei_id != :oei_id_param)
                        ) as max_quantidade_disponivel
                       FROM tbl_estoque_alocacoes ea
                       WHERE ea.alocacao_id = :alocacao_id_param";

        $stmtSaldo = $this->pdo->prepare($querySaldo);
        $stmtSaldo->execute([
            ':oei_id_param' => $oeiId,
            ':alocacao_id_param' => $alocacaoId
        ]);
        $maximoPermitido = $stmtSaldo->fetchColumn();

        if ($novaQuantidade > $maximoPermitido) {
            throw new Exception("Nova quantidade (" . $novaQuantidade . ") excede o saldo disponível no endereço (" . $maximoPermitido . ").");
        }

        // 4. Se todas as validações passaram, executa o UPDATE
        $sqlUpdate = "UPDATE tbl_ordens_expedicao_itens 
                      SET oei_quantidade = :nova_quantidade, 
                          oei_observacao = :nova_observacao 
                      WHERE oei_id = :oei_id";
        $stmtUpdate = $this->pdo->prepare($sqlUpdate);
        $success = $stmtUpdate->execute([
            ':nova_quantidade' => $novaQuantidade,
            ':nova_observacao' => $novaObservacao,
            ':oei_id' => $oeiId
        ]);

        // 5. Registra na auditoria
        $this->auditLogger->log('UPDATE', $oeiId, 'tbl_ordens_expedicao_itens', $itemAtual, $data, "");

        return $success;
    }

    /**
     * Salva a nova sequência de carregamento dos pedidos/clientes.
     * @param array $ordemIds Array de oep_id na nova ordem.
     */
    public function salvarOrdemClientes(array $ordemIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $sql = "UPDATE tbl_ordens_expedicao_pedidos SET oep_ordem_carregamento = :ordem WHERE oep_id = :id";
            $stmt = $this->pdo->prepare($sql);

            foreach ($ordemIds as $index => $id) {
                $stmt->execute([
                    ':ordem' => $index, // Salva a posição (0, 1, 2...)
                    ':id' => (int) $id
                ]);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Retorna uma lista de Ordens de Expedição que AINDA NÃO têm um resumo de faturamento.
     * @return array
     */
    public function findForSelect(): array
    {
        $sql = "SELECT oe.oe_id AS id, oe.oe_numero AS text 
                FROM tbl_ordens_expedicao_header oe
                LEFT JOIN tbl_faturamento_resumos fr ON oe.oe_id = fr.fat_ordem_expedicao_id
                WHERE oe.oe_status = 'EM ELABORAÇÃO' AND fr.fat_id IS NULL
                ORDER BY oe.oe_data DESC, oe.oe_id DESC";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProdutosComEstoqueDisponivel(string $term = ''): array
    {
        $params = [];
        $sqlWhereTerm = "";

        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) 
                               FROM tbl_ordens_expedicao_itens 
                               WHERE oei_alocacao_id = ea.alocacao_id 
                               AND oei_status = 'PENDENTE')";

        // Adiciona o filtro de busca (term) se ele foi enviado
        if (!empty($term)) {
            // Placeholders únicos para a busca
            $sqlWhereTerm = " AND (p.prod_descricao LIKE :term_desc OR p.prod_codigo_interno LIKE :term_cod) ";
            $params[':term_desc'] = '%' . $term . '%';
            $params[':term_cod'] = '%' . $term . '%';
        }

        $sql = "SELECT DISTINCT
                p.prod_codigo AS id,
                CONCAT(p.prod_descricao, ' (Cód: ', COALESCE(p.prod_codigo_interno, 'N/A'), ')') AS text
            FROM tbl_estoque_alocacoes ea
            JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
            JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
            WHERE ea.alocacao_quantidade > {$subQueryReservado}
            {$sqlWhereTerm}  -- Filtro de busca injetado aqui
            ORDER BY p.prod_descricao";

        // Agora usamos prepare/execute para injetar os parâmetros de busca
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna uma lista de Ordens de Expedição que AINDA NÃO têm um carregamento.
     * Formatado para o Select2.
     */
   /* public function findOrdensParaCarregamentoSelect(string $term = ''): array
    {
        $params = [];
        $sqlWhereTerm = "";

        // Adiciona o filtro de busca (term)
        if (!empty($term)) {
            $sqlWhereTerm = " AND (oe.oe_numero LIKE :term) ";
            $params[':term'] = '%' . $term . '%';
        }

        $sql = "SELECT 
                    oe.oe_id AS id, 
                    oe.oe_numero AS text
                FROM tbl_ordens_expedicao_header oe
                LEFT JOIN tbl_carregamentos c ON oe.oe_id = c.car_ordem_expedicao_id AND c.car_status != 'CANCELADO'
                WHERE 
                    c.car_id IS NULL -- O ponto principal: Só traz OE que NÃO estão em um carregamento ativo
                    {$sqlWhereTerm}
                ORDER BY oe.oe_data DESC, oe.oe_id DESC
                LIMIT 20"; // Limita para o Select2

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }*/

    /**
     * Retorna uma lista de Ordens de Expedição disponíveis para o Select2.
     * Filtra por tipo de operação (VENDA/TRANSFERENCIA vs REPROCESSO).
     */
    public function findOrdensParaCarregamentoSelect(string $term = '', string $tipoAlvo = 'NORMAL'): array
    {
        $params = [];
        $sqlWhereTerm = "";

        // Filtro de busca textual
        if (!empty($term)) {
            $sqlWhereTerm = " AND (oe.oe_numero LIKE :term) ";
            $params[':term'] = '%' . $term . '%';
        }

        // --- FILTRO POR TIPO DE OPERAÇÃO ---
        if ($tipoAlvo === 'REPROCESSO') {
            // Na tela de reprocesso, só queremos OEs marcadas como REPROCESSO
            $sqlTipo = " AND oe.oe_tipo_operacao = 'REPROCESSO' ";
        } else {
            // Na tela normal, trazemos VENDA e TRANSFERENCIA (ou simplesmente o que não for REPROCESSO)
            $sqlTipo = " AND oe.oe_tipo_operacao != 'REPROCESSO' ";
        }

        $sql = "SELECT 
                oe.oe_id AS id, 
                oe.oe_numero AS text
            FROM tbl_ordens_expedicao_header oe
            LEFT JOIN tbl_carregamentos c ON oe.oe_id = c.car_ordem_expedicao_id AND c.car_status != 'CANCELADO'
            WHERE 
                c.car_id IS NULL 
                {$sqlTipo}
                {$sqlWhereTerm}
            ORDER BY oe.oe_data DESC, oe.oe_id DESC
            LIMIT 20";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vincula um carregamento a uma OE e atualiza os status.
     */
    public function linkCarregamento(int $ordemExpedicaoId, int $carregamentoId): bool
    {
        // 1. Marca os itens da OE que foram incluídos neste carregamento como 'CARREGADO'
        $sqlItens = "UPDATE tbl_ordens_expedicao_itens 
                     SET oei_status = 'CARREGADO' 
                     WHERE oei_id IN (
                         SELECT DISTINCT car_item_oei_id_origem 
                         FROM tbl_carregamento_itens 
                         WHERE car_item_carregamento_id = :carregamento_id 
                         AND car_item_oei_id_origem IS NOT NULL
                     )";
        $stmtItens = $this->pdo->prepare($sqlItens);
        $stmtItens->execute([':carregamento_id' => $carregamentoId]);

        // 2. Atualiza o cabeçalho da OE para 'GEROU CARREGAMENTO' e salva o ID do carregamento
        $sqlHeader = "UPDATE tbl_ordens_expedicao_header 
                      SET oe_status = 'GEROU CARREGAMENTO', 
                          oe_carregamento_id = :carregamento_id 
                      WHERE oe_id = :oe_id";
        $stmtHeader = $this->pdo->prepare($sqlHeader);
        return $stmtHeader->execute([
            ':carregamento_id' => $carregamentoId,
            ':oe_id' => $ordemExpedicaoId
        ]);
    }

    /**
     * Exclui uma Ordem de Expedição completa (header, pedidos e itens).
     * Apenas OEs com status 'EM ELABORAÇÃO' podem ser excluídas.
     */
    public function delete(int $ordemId): bool
    {
        // 1. Verifica se a OE pode ser excluída
        $stmtCheck = $this->pdo->prepare("SELECT oe_status 
                                                 FROM tbl_ordens_expedicao_header 
                                                 WHERE oe_id = :id");
        $stmtCheck->execute([':id' => $ordemId]);
        $status = $stmtCheck->fetchColumn();

        if ($status !== 'EM ELABORAÇÃO') {
            throw new Exception("Apenas Ordens de Expedição 'EM ELABORAÇÃO' podem ser excluídas.");
        }

        // 2. Inicia uma transação para garantir que tudo seja excluído
        $this->pdo->beginTransaction();
        try {
            // Pega todos os IDs de pedidos (oep_id) desta OE
            $stmtPedidos = $this->pdo->prepare("SELECT oep_id 
                                                       FROM tbl_ordens_expedicao_pedidos 
                                                       WHERE oep_ordem_id = :id");
            $stmtPedidos->execute([':id' => $ordemId]);
            $pedidoIds = $stmtPedidos->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($pedidoIds)) {
                // Deleta todos os itens associados a esses pedidos
                $inQuery = implode(',', array_fill(0, count($pedidoIds), '?'));
                $stmtItens = $this->pdo->prepare("DELETE FROM tbl_ordens_expedicao_itens WHERE oei_pedido_id IN ($inQuery)");
                $stmtItens->execute($pedidoIds);
            }

            // Deleta os pedidos
            $stmtPedidosDelete = $this->pdo->prepare("DELETE FROM tbl_ordens_expedicao_pedidos WHERE oep_ordem_id = :id");
            $stmtPedidosDelete->execute([':id' => $ordemId]);

            // Finalmente, deleta o cabeçalho
            $stmtHeader = $this->pdo->prepare("DELETE FROM tbl_ordens_expedicao_header WHERE oe_id = :id");
            $stmtHeader->execute([':id' => $ordemId]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            // Captura o erro de chave estrangeira específico do faturamento
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'fk_fat_resumo_oe') !== false) {
                throw new Exception("Esta OE não pode ser excluída pois possui um Resumo de Faturamento associado. Por favor, exclua primeiro o resumo na tela de Faturamentos.");
            }
            // Para outros erros, lança a mensagem original
            throw $e; // Re-lança a exceção para o controller
        }
    }

    /**
     * Desvincula uma OE de um carregamento, revertendo seu status.
     * Esta função assume que já está sendo executada dentro de uma transação.
     */
    public function unlinkCarregamento(int $ordemExpedicaoId): bool
    {
        // 1. Reverte o status do cabeçalho da OE e remove o ID do carregamento
        $stmtHeader = $this->pdo->prepare(
            "UPDATE tbl_ordens_expedicao_header 
             SET oe_status = 'EM ELABORAÇÃO', 
                 oe_carregamento_id = NULL 
             WHERE oe_id = :oe_id"
        );
        $headerSuccess = $stmtHeader->execute([':oe_id' => $ordemExpedicaoId]);

        // 2. Reverte o status de todos os itens daquela OE para 'PENDENTE'
        // (Esta query usa um JOIN para encontrar os itens corretos)
        $stmtItens = $this->pdo->prepare(
            "UPDATE tbl_ordens_expedicao_itens oei
             JOIN tbl_ordens_expedicao_pedidos oep ON oei.oei_pedido_id = oep.oep_id
             SET oei.oei_status = 'PENDENTE'
             WHERE oep.oep_ordem_id = :oe_id"
        );
        $itensSuccess = $stmtItens->execute([':oe_id' => $ordemExpedicaoId]);

        // Se ambos os updates funcionarem, retorna true. 
        // Se qualquer um falhar, o PDO lançará uma exceção que será capturada pela função 'reabrir'.
        return $headerSuccess && $itensSuccess;
    }

    /**
     * @doc: Busca Ordens de Expedição prontas para serem carregadas pela API.
     * @return array Uma lista de Ordens de Expedição.
     */
    public function findProntasParaApi(): array
    {
        $sql = "
        SELECT 
            oe.oe_id,
            oe.oe_numero,
            oe.oe_data,
            GROUP_CONCAT(DISTINCT ent.ent_nome_fantasia SEPARATOR ', ') as clientes
        FROM 
            tbl_ordens_expedicao_header oe
        JOIN 
            tbl_ordens_expedicao_pedidos oep ON oe.oe_id = oep.oep_ordem_id
        JOIN 
            tbl_entidades ent ON oep.oep_cliente_id = ent.ent_codigo
        WHERE 
            oe.oe_status = 'EM ELABORAÇÃO'
            AND oe.oe_id NOT IN (
                SELECT car_ordem_expedicao_id 
                FROM tbl_carregamentos 
                WHERE car_ordem_expedicao_id IS NOT NULL AND car_status != 'CANCELADO'
            )
        GROUP BY
            oe.oe_id, oe.oe_numero, oe.oe_data
        ORDER BY 
            oe.oe_data DESC, oe.oe_numero DESC
        ";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @doc: Busca os detalhes de uma OE para pré-preencher um novo carregamento.
     * @param int $oeId O ID da Ordem de Expedição.
     * @return array|null Os detalhes da OE.
     */
    public function findDetalhesParaCarregamento(int $oeId): ?array
    {
        // Esta consulta busca os dados principais do primeiro pedido da OE
        // para usar como sugestão no formulário.
        $sql = "
        SELECT 
            oep.oep_cliente_id as cliente_id,
            c.car_transportadora_id as transportadora_id -- Supondo que a transportadora é definida no carregamento
        FROM tbl_ordens_expedicao_pedidos oep
        LEFT JOIN tbl_carregamentos c ON oep.oep_ordem_id = c.car_ordem_expedicao_id
        WHERE oep.oep_ordem_id = :oe_id
        ORDER BY oep.oep_id ASC
        LIMIT 1
    ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':oe_id' => $oeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
