<?php
// /src/Carregamentos/CarregamentoRepository.php
namespace App\Carregamentos;

use PDO;
use Exception;
use App\Core\AuditLoggerService;

class CarregamentoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    public function getNextNumeroCarregamento(): string
    {
        $stmt = $this->pdo->query("SELECT MAX(CAST(car_numero AS UNSIGNED)) FROM tbl_carregamentos");
        $ultimoNumero = $stmt->fetchColumn() ?: 0;
        $proximoNumero = $ultimoNumero + 1;
        return str_pad($proximoNumero, 4, '0', STR_PAD_LEFT);
    }

    public function createHeader(array $data, int $userId): int
    {
        $sql = "INSERT INTO tbl_carregamentos (
                    car_numero, car_data, car_entidade_id_organizador, car_lacre,
                    car_placa_veiculo, car_hora_inicio, car_ordem_expedicao, car_usuario_id_responsavel
                ) VALUES (
                    :numero, :data, :clienteOrganizadorId, :lacre,
                    :placa, :hora_inicio, :ordem_expedicao, :user_id
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':numero' => $data['car_numero'],
            ':data' => $data['car_data'],
            ':clienteOrganizadorId' => $data['car_entidade_id_organizador'],
            ':lacre' => $data['car_lacre'] ?? null,
            ':placa' => $data['car_placa_veiculo'] ?? null,
            ':hora_inicio' => $data['car_hora_inicio'] ?? null,
            ':ordem_expedicao' => $data['car_ordem_expedicao'] ?? null,
            ':user_id' => $userId
        ]);

        $novoId = (int)$this->pdo->lastInsertId();

        if ($novoId > 0) {
            $dadosLog = $data;
            $dadosLog['car_status'] = 'EM ANDAMENTO';
            $this->auditLogger->log('CREATE', $novoId, 'tbl_carregamentos', null, $dadosLog);
        }

        return $novoId;
    }

    public function getProdutosDisponiveisEmEstoque(): array
    {
        $sql = "
        SELECT DISTINCT 
            p.prod_codigo as id,
            p.prod_descricao as text
        FROM tbl_estoque es
        JOIN tbl_produtos p ON es.estoque_produto_id = p.prod_codigo
        GROUP BY p.prod_codigo, p.prod_descricao
        HAVING SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END) > 0
        ORDER BY text ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';
        $filtroStatus = $params['filtro_status'] ?? 'Todos';

        $baseQuery = "FROM tbl_carregamentos c 
                      LEFT JOIN tbl_entidades e ON c.car_entidade_id_organizador = e.ent_codigo";

        $totalRecords = $this->pdo->query("SELECT COUNT(c.car_id) FROM tbl_carregamentos c")->fetchColumn();

        $whereConditions = [];
        $queryParams = [];

        if ($filtroStatus !== 'Todos' && !empty($filtroStatus)) {
            $whereConditions[] = "c.car_status = :status";
            $queryParams[':status'] = $filtroStatus;
        }

        if (!empty($searchValue)) {
            $whereConditions[] = "(c.car_numero LIKE :search OR e.ent_razao_social LIKE :search)";
            $queryParams[':search'] = '%' . $searchValue . '%';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(c.car_id) $baseQuery $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT c.car_id, c.car_numero, c.car_data, c.car_status, e.ent_razao_social 
                    $baseQuery $whereClause 
                    ORDER BY c.car_data DESC, c.car_id DESC 
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        foreach ($queryParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => (int)$draw,
            "recordsTotal" => (int)$totalRecords,
            "recordsFiltered" => (int)$totalFiltered,
            "data" => $data
        ];
    }

    public function findCarregamentoComItens(int $carregamentoId): ?array
    {
        $stmtHeader = $this->pdo->prepare(
            "SELECT c.*, e.ent_razao_social
             FROM tbl_carregamentos c
             LEFT JOIN tbl_entidades e ON c.car_entidade_id_organizador = e.ent_codigo
             WHERE c.car_id = :id"
        );
        $stmtHeader->execute([':id' => $carregamentoId]);
        $header = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            return null;
        }

        $stmtItens = $this->pdo->prepare(
            "SELECT
                ci.*,
                p.prod_descricao,
                lnh.lote_completo_calculado as lote_num_completo
             FROM tbl_carregamento_itens ci
             JOIN tbl_produtos p ON ci.car_item_produto_id = p.prod_codigo
             LEFT JOIN tbl_lotes_novo_header lnh ON ci.car_item_lote_id = lnh.lote_id
             WHERE ci.car_item_carregamento_id = :id
             ORDER BY ci.car_item_id DESC"
        );
        $stmtItens->execute([':id' => $carregamentoId]);
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        return ['header' => $header, 'itens' => $itens];
    }

    public function getItensParaConferencia(int $carregamentoId): array
    {
        $sql = "SELECT
                ci.car_item_id,
                ci.car_item_produto_id as produto_id,
                ci.car_item_lote_id as lote_id,
                p.prod_descricao,
                lnh.lote_completo_calculado,
                ci.car_item_quantidade,
                (SELECT SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END)
                 FROM tbl_estoque es
                 WHERE es.estoque_produto_id = ci.car_item_produto_id AND es.estoque_lote_id = ci.car_item_lote_id) as saldo_estoque_lote
            FROM tbl_carregamento_itens ci
            JOIN tbl_produtos p ON ci.car_item_produto_id = p.prod_codigo
            LEFT JOIN tbl_lotes_novo_header lnh ON ci.car_item_lote_id = lnh.lote_id
            WHERE ci.car_item_carregamento_id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $carregamentoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function confirmarBaixaDeEstoque(int $carregamentoId, bool $forcarBaixa): void
    {
        $this->pdo->beginTransaction();
        try {
            // Usamos a função já corrigida para buscar os itens
            $itensParaBaixa = $this->getItensParaConferencia($carregamentoId);

            if (empty($itensParaBaixa)) {
                throw new Exception("Este carregamento não contém itens para finalizar.");
            }

            foreach ($itensParaBaixa as $item) {
                if (!$forcarBaixa && (float)$item['car_item_quantidade'] > (float)$item['saldo_estoque_lote']) {
                    throw new Exception("Estoque insuficiente para o produto '{$item['prod_descricao']}' do lote '{$item['lote_completo_calculado']}'. Operação cancelada.");
                }

                $stmtEstoque = $this->pdo->prepare(
                    "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
                     VALUES (:prod_id, :lote_id, :qtd, 'SAIDA', :obs)"
                );
                $stmtEstoque->execute([
                    ':prod_id'      => $item['produto_id'],
                    ':lote_id'      => $item['lote_id'],
                    ':qtd'          => $item['car_item_quantidade'],
                    ':obs'          => "Saída para Carregamento Nº {$carregamentoId}"
                ]);
            }

            $stmtUpdateCar = $this->pdo->prepare(
                "UPDATE tbl_carregamentos SET car_status = 'FINALIZADO', car_data_finalizacao = NOW() WHERE car_id = :id"
            );
            $stmtUpdateCar->execute([':id' => $carregamentoId]);

            $this->auditLogger->log('FINALIZE_CARREGAMENTO', $carregamentoId, 'tbl_carregamentos', null, ['itens' => $itensParaBaixa]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function adicionarFila(int $carregamentoId): int
    {
        $proximoNumeroFila = $this->getProximoNumeroFila($carregamentoId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO tbl_carregamento_filas (fila_carregamento_id, fila_numero_sequencial) VALUES (?, ?)"
        );
        $stmt->execute([$carregamentoId, $proximoNumeroFila]);
        $novoId = (int)$this->pdo->lastInsertId();

        $this->auditLogger->log('CREATE', $novoId, 'tbl_carregamento_filas', null, [
            'fila_carregamento_id' => $carregamentoId,
            'fila_numero_sequencial' => $proximoNumeroFila
        ]);

        return $novoId;
    }

    public function findCarregamentoComFilasEItens(int $carregamentoId): ?array
    {
        $stmtHeader = $this->pdo->prepare("SELECT c.*, e.ent_razao_social FROM tbl_carregamentos c LEFT JOIN tbl_entidades e ON c.car_entidade_id_organizador = e.ent_codigo WHERE c.car_id = :id");
        $stmtHeader->execute([':id' => $carregamentoId]);
        $header = $stmtHeader->fetch(PDO::FETCH_ASSOC);
        if (!$header) return null;

        $stmtFilas = $this->pdo->prepare("SELECT f.fila_id, f.fila_numero_sequencial FROM tbl_carregamento_filas f WHERE f.fila_carregamento_id = :id ORDER BY f.fila_id");
        $stmtFilas->execute([':id' => $carregamentoId]);
        $filas = $stmtFilas->fetchAll(PDO::FETCH_ASSOC);

        $stmtItens = $this->pdo->prepare(
            "SELECT
            ci.car_item_fila_numero,
            ci.car_item_quantidade,
            e_destino.ent_razao_social as cliente_razao_social,
            p.prod_descricao,
            p.prod_codigo_interno,
            lnh.lote_completo_calculado,
            COALESCE(e_lote.ent_nome_fantasia, e_lote.ent_razao_social) as cliente_lote_nome
         FROM tbl_carregamento_itens ci
         JOIN tbl_entidades e_destino ON ci.car_item_cliente_id = e_destino.ent_codigo
         JOIN tbl_produtos p ON ci.car_item_produto_id = p.prod_codigo
         LEFT JOIN tbl_lotes_novo_header lnh ON ci.car_item_lote_id = lnh.lote_id
         LEFT JOIN tbl_entidades e_lote ON lnh.lote_cliente_id = e_lote.ent_codigo
         WHERE ci.car_item_carregamento_id = :id"
        );

        $stmtItens->execute([':id' => $carregamentoId]);
        $todosOsItens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $key => $fila) {
            $filas[$key]['itens'] = array_values(array_filter($todosOsItens, function ($item) use ($fila) {
                return $item['car_item_fila_numero'] == $fila['fila_id'];
            }));
        }

        return ['header' => $header, 'filas' => $filas];
    }

    public function findLotesComSaldoPorProduto(int $produtoId): array
    {
        $sql = "
        SELECT
            lnh.lote_id as id,
            CONCAT('Lote: ', lnh.lote_completo_calculado, ' | Saldo: ', CAST(SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END) AS DECIMAL(10,3))) as text
        FROM tbl_estoque es
        LEFT JOIN tbl_lotes_novo_embalagem lne ON es.estoque_lote_item_id = lne.item_emb_id
        JOIN tbl_lotes_novo_header lnh ON lnh.lote_id = COALESCE(es.estoque_lote_id, lne.item_emb_lote_id)
        WHERE es.estoque_produto_id = :produto_id
        GROUP BY lnh.lote_id, lnh.lote_completo_calculado
        HAVING SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END) > 0
        ORDER BY lnh.lote_data_fabricacao DESC;
    ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':produto_id' => $produtoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removerFilaCompleta(int $filaId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmtItens = $this->pdo->prepare("DELETE FROM tbl_carregamento_itens WHERE car_item_fila_numero = :fila_id");
            $stmtItens->execute([':fila_id' => $filaId]);
            $stmtFila = $this->pdo->prepare("DELETE FROM tbl_carregamento_filas WHERE fila_id = :fila_id");
            $stmtFila->execute([':fila_id' => $filaId]);
            $this->auditLogger->log('DELETE', $filaId, 'tbl_carregamento_filas', null, ['removida_fila_completa' => $filaId]);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erro ao remover a fila: " . $e->getMessage());
        }
    }

    private function getProximoNumeroFila(int $carregamentoId): int
    {
        $stmt = $this->pdo->prepare("SELECT MAX(fila_numero_sequencial) FROM tbl_carregamento_filas WHERE fila_carregamento_id = :car_id");
        $stmt->execute([':car_id' => $carregamentoId]);
        $ultimoNumero = $stmt->fetchColumn() ?: 0;
        return (int)$ultimoNumero + 1;
    }

   /* public function findFilaComClientesEItens(int $filaId): ?array
    {
        $stmtFila = $this->pdo->prepare(
            "SELECT f.fila_id, f.fila_numero_sequencial
             FROM tbl_carregamento_filas f 
             WHERE f.fila_id = :id"
        );
        $stmtFila->execute([':id' => $filaId]);
        $fila = $stmtFila->fetch(PDO::FETCH_ASSOC);

        if (!$fila) return null;

        $stmtItens = $this->pdo->prepare(
            "SELECT
                ci.car_item_lote_id as loteId,
                ci.car_item_quantidade as quantidade,
                ci.car_item_cliente_id as clienteId,
                ci.car_item_produto_id as produtoId,
                e.ent_razao_social as clienteNome,
                CONCAT(p.prod_descricao, ' (Lote: ', lnh.lote_completo_calculado, ')') as produtoTexto
             FROM tbl_carregamento_itens ci
             JOIN tbl_entidades e ON ci.car_item_cliente_id = e.ent_codigo
             JOIN tbl_produtos p ON ci.car_item_produto_id = p.prod_codigo
             LEFT JOIN tbl_lotes_novo_header lnh ON ci.car_item_lote_id = lnh.lote_id
             WHERE ci.car_item_fila_numero = :fila_id"
        );

        $stmtItens->execute([':fila_id' => $filaId]);
        $todosOsItens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        $clientes = [];
        foreach ($todosOsItens as $item) {
            $clienteId = $item['clienteId'];
            if (!isset($clientes[$clienteId])) {
                $clientes[$clienteId] = [
                    'clienteId' => $clienteId,
                    'clienteNome' => $item['clienteNome'],
                    'produtos' => []
                ];
            }
            $clientes[$clienteId]['produtos'][] = [
                'loteId' => $item['loteId'],
                'quantidade' => $item['quantidade'],
                'produtoId' => $item['produtoId'],
                'produtoTexto' => $item['produtoTexto']
            ];
        }

        $fila['clientes'] = array_values($clientes);
        return $fila;
    }*/

    public function reabrir(int $carregamentoId, string $motivo): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamentos WHERE car_id = :id");
            $stmtAntigo->execute([':id' => $carregamentoId]);
            $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

            if (!$dadosAntigos || $dadosAntigos['car_status'] !== 'FINALIZADO') {
                throw new Exception("Apenas carregamentos finalizados podem ser reabertos.");
            }

            $stmtItens = $this->pdo->prepare(
                "SELECT car_item_produto_id, car_item_lote_id, car_item_quantidade 
                 FROM tbl_carregamento_itens 
                 WHERE car_item_carregamento_id = :id"
            );
            $stmtItens->execute([':id' => $carregamentoId]);
            $itensParaEstornar = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itensParaEstornar as $item) {
                $stmtEstoque = $this->pdo->prepare(
                    "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
                     VALUES (:prod_id, :lote_id, :qtd, 'ENTRADA POR ESTORNO', :obs)"
                );
                $stmtEstoque->execute([
                    ':prod_id' => $item['car_item_produto_id'],
                    ':lote_id' => $item['car_item_lote_id'],
                    ':qtd' => $item['car_item_quantidade'],
                    ':obs' => "Estorno do Carregamento Nº {$dadosAntigos['car_numero']} (Reabertura)"
                ]);
            }

            $stmtUpdateCarregamento = $this->pdo->prepare("UPDATE tbl_carregamentos SET car_status = 'EM ANDAMENTO' WHERE car_id = :id");
            $stmtUpdateCarregamento->execute([':id' => $carregamentoId]);

            $dadosNovos = $dadosAntigos;
            $dadosNovos['car_status'] = 'EM ANDAMENTO';
            $this->auditLogger->log('REOPEN', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, $dadosNovos, $motivo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // As funções abaixo (cancelar, excluir, etc.) precisam ser validadas ou podem ser mantidas se não tocarem na lógica de itens.
    // Incluindo versões corrigidas por segurança.

    public function excluir(int $carregamentoId): bool
    {
        $dadosAntigos = $this->pdo->query("SELECT * FROM tbl_carregamentos WHERE car_id = {$carregamentoId}")->fetch(PDO::FETCH_ASSOC);

        if (!$dadosAntigos) {
            throw new Exception("Carregamento não encontrado.");
        }

        if ($dadosAntigos['car_status'] === 'FINALIZADO') {
            throw new Exception("Não é possível excluir um carregamento finalizado. Cancele-o ou reabra-o primeiro.");
        }

        $this->pdo->beginTransaction();
        try {
            $stmtItens = $this->pdo->prepare("DELETE FROM tbl_carregamento_itens WHERE car_item_carregamento_id = :id");
            $stmtItens->execute([':id' => $carregamentoId]);
            $stmtFilas = $this->pdo->prepare("DELETE FROM tbl_carregamento_filas WHERE fila_carregamento_id = :id");
            $stmtFilas->execute([':id' => $carregamentoId]);
            $stmtHeader = $this->pdo->prepare("DELETE FROM tbl_carregamentos WHERE car_id = :id");
            $stmtHeader->execute([':id' => $carregamentoId]);
            $this->auditLogger->log('DELETE', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, null);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erro ao excluir o carregamento: " . $e->getMessage());
        }
    }

    public function reativar(int $carregamentoId): bool
    {
        $dadosAntigos = $this->pdo->query("SELECT * FROM tbl_carregamentos WHERE car_id = {$carregamentoId}")->fetch(PDO::FETCH_ASSOC);
        if (!$dadosAntigos || $dadosAntigos['car_status'] !== 'CANCELADO') {
            throw new Exception("Apenas carregamentos cancelados podem ser reativados.");
        }

        $stmt = $this->pdo->prepare("UPDATE tbl_carregamentos SET car_status = 'EM ANDAMENTO' WHERE car_id = :id");
        $success = $stmt->execute([':id' => $carregamentoId]);

        if ($success) {
            $dadosNovos = $dadosAntigos;
            $dadosNovos['car_status'] = 'EM ANDAMENTO';
            $this->auditLogger->log('REACTIVATE', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, $dadosNovos);
        }
        return $success;
    }

    public function cancelar(int $carregamentoId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamentos WHERE car_id = :id");
            $stmtAntigo->execute([':id' => $carregamentoId]);
            $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

            if (!$dadosAntigos) {
                throw new Exception("Carregamento não encontrado.");
            }

            // A lógica de estorno só se aplica se o carregamento já foi finalizado
            if ($dadosAntigos['car_status'] === 'FINALIZADO') {
                // A lógica de estorno é a mesma de 'reabrir'
                $this->reabrir($carregamentoId, "Cancelamento de carregamento finalizado");

                // Precisamos buscar os dados novamente pois o status mudou para 'EM ANDAMENTO'
                $stmtAntigo->execute([':id' => $carregamentoId]);
                $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);
            }

            $stmt = $this->pdo->prepare("UPDATE tbl_carregamentos SET car_status = 'CANCELADO' WHERE car_id = :id");
            $success = $stmt->execute([':id' => $carregamentoId]);

            if ($success) {
                $dadosNovos = $dadosAntigos;
                $dadosNovos['car_status'] = 'CANCELADO';
                $this->auditLogger->log('CANCEL', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, $dadosNovos);
            }

            $this->pdo->commit();
            return $success;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function salvarFilaComposta(int $carregamentoId, array $filaData): void
    {
        $this->pdo->beginTransaction();
        try {
            $filaId = $this->adicionarFila($carregamentoId);

            foreach ($filaData as $dadosCliente) {
                $clienteId = $dadosCliente['clienteId'];
                $produtos = $dadosCliente['produtos'];

                if (empty($produtos)) continue;

                foreach ($produtos as $produto) {
                    $this->adicionarItemAFila(
                        $filaId,
                        $produto['produtoId'],
                        $produto['loteId'],
                        $produto['quantidade'],
                        $carregamentoId,
                        $clienteId
                    );
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erro ao salvar os dados da fila: " . $e->getMessage());
        }
    }

    public function atualizarFilaComposta(int $filaId, int $carregamentoId, array $filaData): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmtDelete = $this->pdo->prepare("DELETE FROM tbl_carregamento_itens WHERE car_item_fila_numero = :fila_id");
            $stmtDelete->execute([':fila_id' => $filaId]);
            $this->auditLogger->log('UPDATE', $filaId, 'tbl_carregamento_filas', null, ['observacao' => 'Limpeza de itens para atualização.']);

            foreach ($filaData as $dadosCliente) {
                $clienteId = $dadosCliente['clienteId'];
                $produtos = $dadosCliente['produtos'];

                if (empty($produtos)) continue;

                foreach ($produtos as $produto) {
                    $this->adicionarItemAFila(
                        $filaId,
                        $produto['produtoId'],
                        $produto['loteId'],
                        $produto['quantidade'],
                        $carregamentoId,
                        $clienteId
                    );
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erro ao atualizar os dados da fila: " . $e->getMessage());
        }
    }

    public function findFilaComClientesEItens(int $filaId): ?array
    {
        $stmtFila = $this->pdo->prepare(
            "SELECT f.fila_id, f.fila_numero_sequencial
             FROM tbl_carregamento_filas f 
             WHERE f.fila_id = :id"
        );
        $stmtFila->execute([':id' => $filaId]);
        $fila = $stmtFila->fetch(PDO::FETCH_ASSOC);

        if (!$fila) return null;

        $stmtItens = $this->pdo->prepare(
            "SELECT
                ci.car_item_lote_id as loteId,
                ci.car_item_quantidade as quantidade,
                ci.car_item_cliente_id as clienteId,
                ci.car_item_produto_id as produtoId,
                e.ent_razao_social as clienteNome,
                CONCAT(p.prod_descricao, ' (Lote: ', lnh.lote_completo_calculado, ')') as produtoTexto
             FROM tbl_carregamento_itens ci
             JOIN tbl_entidades e ON ci.car_item_cliente_id = e.ent_codigo
             JOIN tbl_produtos p ON ci.car_item_produto_id = p.prod_codigo
             LEFT JOIN tbl_lotes_novo_header lnh ON ci.car_item_lote_id = lnh.lote_id
             WHERE ci.car_item_fila_numero = :fila_id"
        );

        $stmtItens->execute([':fila_id' => $filaId]);
        $todosOsItens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        $clientes = [];
        foreach ($todosOsItens as $item) {
            $clienteId = $item['clienteId'];
            if (!isset($clientes[$clienteId])) {
                $clientes[$clienteId] = [
                    'clienteId' => $clienteId,
                    'clienteNome' => $item['clienteNome'],
                    'produtos' => []
                ];
            }
            $clientes[$clienteId]['produtos'][] = [
                'loteId' => $item['loteId'], // Alterado para loteId
                'quantidade' => $item['quantidade'],
                'produtoId' => $item['produtoId'],
                'produtoTexto' => $item['produtoTexto']
            ];
        }

        $fila['clientes'] = array_values($clientes);
        return $fila;
    }
}
