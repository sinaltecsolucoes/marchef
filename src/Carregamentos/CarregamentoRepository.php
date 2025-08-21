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

    /*  public function createHeader(array $data, int $userId): int
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

        $novoId = (int) $this->pdo->lastInsertId();

        if ($novoId > 0) {
            $dadosLog = $data;
            $dadosLog['car_status'] = 'EM ANDAMENTO';
            $this->auditLogger->log('CREATE', $novoId, 'tbl_carregamentos', null, $dadosLog);
        }

        return $novoId;
    }*/

    // Em src/Carregamentos/CarregamentoRepository.php

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
            ':numero' => $data['numero'],
            ':data' => $data['data'],
            ':clienteOrganizadorId' => $data['clienteOrganizadorId'],

            ':lacre' => $data['lacre'] ?? null,
            ':placa' => $data['placa'] ?? null,
            ':hora_inicio' => $data['hora_inicio'] ?? null,
            ':ordem_expedicao' => $data['ordem_expedicao'] ?? null,

            ':user_id' => $userId
        ]);

        $novoId = (int) $this->pdo->lastInsertId();

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

        if (!$header)
            return null;

        $stmtFilas = $this->pdo->prepare(
            "SELECT f.fila_id, f.fila_numero_sequencial
             FROM tbl_carregamento_filas f
             WHERE f.fila_carregamento_id = :id
             ORDER BY f.fila_numero_sequencial ASC"
        );
        $stmtFilas->execute([':id' => $carregamentoId]);
        $filas = $stmtFilas->fetchAll(PDO::FETCH_ASSOC);

        $data = ['header' => $header, 'filas' => []];

        foreach ($filas as &$fila) {
            $stmtItens = $this->pdo->prepare(
                "SELECT 
                    ci.car_item_id,
                    ci.car_item_quantidade,
                    e.ent_razao_social AS cliente_razao_social,
                    p.prod_descricao AS produto_descricao,
                    lnh.lote_completo_calculado AS lote_completo_calculado,
                    f.ent_razao_social AS fornecedor_razao_social
                 FROM tbl_carregamento_itens ci
                 JOIN tbl_entidades e ON ci.car_item_cliente_id = e.ent_codigo
                 JOIN tbl_lotes_novo_embalagem lne ON ci.car_item_lote_novo_item_id = lne.item_emb_id
                 JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                 JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                 JOIN tbl_entidades f ON lnh.lote_fornecedor_id = f.ent_codigo
                 WHERE ci.car_item_fila_id = :fila_id"
            );
            $stmtItens->execute([':fila_id' => $fila['fila_id']]);
            $fila['itens'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
            $data['filas'][] = $fila;
        }

        return $data;
    }

    public function findCarregamentoComFilasEItens(int $carregamentoId): ?array
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

        $stmtFilas = $this->pdo->prepare(
            "SELECT f.fila_id, f.fila_numero_sequencial 
             FROM tbl_carregamento_filas f 
             WHERE f.fila_carregamento_id = :id 
             ORDER BY f.fila_numero_sequencial ASC"
        );
        $stmtFilas->execute([':id' => $carregamentoId]);
        $filas = $stmtFilas->fetchAll(PDO::FETCH_ASSOC);

        $stmtItens = $this->pdo->prepare(
            "SELECT
                ci.car_item_fila_id,
                ci.car_item_quantidade,
                e_destino.ent_razao_social as cliente_razao_social,
                p.prod_descricao,
                p.prod_codigo_interno,
                lnh.lote_completo_calculado,
                COALESCE(e_lote.ent_nome_fantasia, e_lote.ent_razao_social) as cliente_lote_nome
             FROM tbl_carregamento_itens ci
             JOIN tbl_entidades e_destino ON ci.car_item_cliente_id = e_destino.ent_codigo
             JOIN tbl_lotes_novo_embalagem lne ON ci.car_item_lote_novo_item_id = lne.item_emb_id
             JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
             LEFT JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
             LEFT JOIN tbl_entidades e_lote ON lnh.lote_fornecedor_id = e_lote.ent_codigo
             WHERE ci.car_item_carregamento_id = :id"
        );

        $stmtItens->execute([':id' => $carregamentoId]);
        $todosOsItens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $key => $fila) {
            $filas[$key]['itens'] = array_values(array_filter($todosOsItens, function ($item) use ($fila) {
                return $item['car_item_fila_id'] == $fila['fila_id'];
            }));
        }

        return ['header' => $header, 'filas' => $filas];
    }

    /**
     * Salva uma fila completa com seus clientes e produtos.
     * Nenhuma alteração necessária aqui, mas incluído para contexto.
     */
    public function salvarFilaComposta(int $carregamentoId, array $filaData): void
    {
        $this->pdo->beginTransaction();
        try {
            $filaId = $this->adicionarFila($carregamentoId);

            foreach ($filaData as $dadosCliente) {
                $clienteId = $dadosCliente['clienteId'];
                $produtos = $dadosCliente['produtos'];

                if (empty($produtos))
                    continue;

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
            // Re-lança a exceção para que o ajax_router possa capturá-la e exibir a mensagem de erro.
            throw new Exception("Erro ao salvar os dados da fila: " . $e->getMessage());
        }
    }

    public function atualizarFilaComposta(int $filaId, int $carregamentoId, array $filaData): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmtDelete = $this->pdo->prepare("DELETE FROM tbl_carregamento_itens WHERE car_item_fila_id = :fila_id");
            $stmtDelete->execute([':fila_id' => $filaId]);
            $this->auditLogger->log('UPDATE', $filaId, 'tbl_carregamento_filas', null, ['observacao' => 'Limpeza de itens para atualização.']);

            foreach ($filaData as $dadosCliente) {
                $clienteId = $dadosCliente['clienteId'];
                $produtos = $dadosCliente['produtos'];

                if (empty($produtos))
                    continue;

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

    /* public function findFilaComClientesEItens(int $filaId): ?array
    {
        $stmtFila = $this->pdo->prepare(
            "SELECT f.fila_id, f.fila_numero_sequencial
             FROM tbl_carregamento_filas f 
             WHERE f.fila_id = :id"
        );
        $stmtFila->execute([':id' => $filaId]);
        $fila = $stmtFila->fetch(PDO::FETCH_ASSOC);

        if (!$fila)
            return null;

        $stmtItens = $this->pdo->prepare(
            "SELECT
                ci.car_item_lote_novo_item_id as loteId,
                ci.car_item_quantidade as quantidade,
                ci.car_item_cliente_id as clienteId,
                e.ent_razao_social as clienteNome,
                CONCAT(p.prod_descricao, ' (Lote: ', lnh.lote_completo_calculado, ')') as produtoTexto
             FROM tbl_carregamento_itens ci
             JOIN tbl_entidades e ON ci.car_item_cliente_id = e.ent_codigo
             JOIN tbl_produtos p ON ci.car_item_produto_id = p.prod_codigo
             LEFT JOIN tbl_lotes_novo_header lnh ON ci.car_item_lote_id = lnh.lote_id
             WHERE ci.car_item_fila_id = :fila_id"
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

    // Em src/Carregamentos/CarregamentoRepository.php


    // Em src/Carregamentos/CarregamentoRepository.php

    public function findFilaComClientesEItens(int $filaId): ?array
    {
        $stmtFila = $this->pdo->prepare(
            "SELECT f.fila_id, f.fila_numero_sequencial
             FROM tbl_carregamento_filas f 
             WHERE f.fila_id = :id"
        );
        $stmtFila->execute([':id' => $filaId]);
        $fila = $stmtFila->fetch(PDO::FETCH_ASSOC);

        if (!$fila)
            return null;

        $stmtItens = $this->pdo->prepare(
            "SELECT
                ci.car_item_lote_novo_item_id as loteId,
                ci.car_item_quantidade as quantidade,
                ci.car_item_cliente_id as clienteId,
                lne.item_emb_prod_sec_id as produtoId, -- Buscando o ID do produto através da tabela de embalagem
                e.ent_razao_social as clienteNome,
                CONCAT(p.prod_descricao, ' (Lote: ', lnh.lote_completo_calculado, ')') as produtoTexto
             FROM tbl_carregamento_itens ci
             JOIN tbl_entidades e ON ci.car_item_cliente_id = e.ent_codigo
             -- O caminho correto para o produto passa pela tabela de embalagem
             JOIN tbl_lotes_novo_embalagem lne ON ci.car_item_lote_novo_item_id = lne.item_emb_id
             JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
             LEFT JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
             WHERE ci.car_item_fila_id = :fila_id"
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
    }


    public function findLotesComSaldoPorProduto(int $produtoId): array
    {
        $sql = "
        SELECT
            lnh.lote_id AS id,
            CONCAT('Lote: ', COALESCE(lnh.lote_completo_calculado, 'Avulso'), ' | Saldo: ', 
                   CAST(SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade 
                                ELSE -es.estoque_quantidade END) AS DECIMAL(10,3))) AS text
        FROM tbl_estoque es
        LEFT JOIN tbl_lotes_novo_embalagem lne ON es.estoque_lote_item_id = lne.item_emb_id
        LEFT JOIN tbl_lotes_novo_header lnh ON lnh.lote_id = lne.item_emb_lote_id
        WHERE es.estoque_produto_id = :produto_id
        GROUP BY lnh.lote_id, lnh.lote_completo_calculado
        HAVING SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade 
                       ELSE -es.estoque_quantidade END) > 0
        ORDER BY COALESCE(lnh.lote_data_fabricacao, '1970-01-01') DESC
        LIMIT 0, 25";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':produto_id' => $produtoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Adiciona uma nova fila (vazia) a um carregamento.
     *
     * @param integer $carregamentoId
     * @return integer O ID da nova fila criada.
     */
    public function adicionarFila(int $carregamentoId): int
    {
        $proximoNumeroFila = $this->getProximoNumeroFila($carregamentoId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO tbl_carregamento_filas (fila_carregamento_id, fila_numero_sequencial) 
             VALUES (:carregamento_id, :sequencial)"
        );
        $stmt->execute([
            ':carregamento_id' => $carregamentoId,
            ':sequencial' => $proximoNumeroFila
        ]);
        $novoId = (int) $this->pdo->lastInsertId();

        $this->auditLogger->log('CREATE', $novoId, 'tbl_carregamento_filas', null, [
            'fila_carregamento_id' => $carregamentoId,
            'fila_numero_sequencial' => $proximoNumeroFila
        ]);

        return $novoId;
    }

    /**
     * Adiciona um item (produto/lote) a uma fila existente.
     * @param integer $filaId
     * @param integer $produtoId O ID do produto selecionado.
     * @param integer $loteId O ID do lote_header selecionado.
     * @param float $quantidade
     * @param integer $carregamentoId
     * @param integer $clienteId
     * @return boolean
     */
    public function adicionarItemAFila(int $filaId, int $produtoId, int $loteId, float $quantidade, int $carregamentoId, int $clienteId): bool
    {
        // ETAPA 1: Encontrar o ID da embalagem (item_emb_id) usando o loteId e produtoId.
        $stmtFindItem = $this->pdo->prepare(
            "SELECT item_emb_id FROM tbl_lotes_novo_embalagem 
             WHERE item_emb_lote_id = :lote_id AND item_emb_prod_sec_id = :produto_id
             LIMIT 1"
        );
        $stmtFindItem->execute([
            ':lote_id' => $loteId,
            ':produto_id' => $produtoId
        ]);
        $loteNovoItemId = $stmtFindItem->fetchColumn();

        // Se não encontrar uma embalagem correspondente, lança um erro.
        if (!$loteNovoItemId) {
            throw new Exception("Não foi possível encontrar a embalagem para o produto ID {$produtoId} no lote ID {$loteId}.");
        }

        // ETAPA 2: Inserir o item de carregamento com o ID da embalagem correto.
        $sql = "INSERT INTO tbl_carregamento_itens (
                    car_item_carregamento_id, 
                    car_item_fila_id,
                    car_item_cliente_id, 
                    car_item_lote_novo_item_id, 
                    car_item_quantidade
                ) VALUES (
                    :carregamento_id, 
                    :fila_id, 
                    :cliente_id,
                    :lote_novo_item_id, 
                    :qtd
                )";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':carregamento_id' => $carregamentoId,
            ':fila_id' => $filaId,
            ':cliente_id' => $clienteId,
            ':lote_novo_item_id' => $loteNovoItemId, // Usando o ID que encontramos
            ':qtd' => $quantidade
        ]);

        if ($success) {
            $this->auditLogger->log('CREATE', $this->pdo->lastInsertId(), 'tbl_carregamento_itens', null, [
                'fila_id' => $filaId,
                'cliente_id' => $clienteId,
                'lote_novo_item_id' => $loteNovoItemId,
                'quantidade' => $quantidade
            ]);
        }

        return $success;
    }

    /**
     * Calcula o próximo número sequencial para uma nova fila dentro de um carregamento.
     * @param integer $carregamentoId
     * @return integer
     */
    private function getProximoNumeroFila(int $carregamentoId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT MAX(fila_numero_sequencial) 
             FROM tbl_carregamento_filas 
             WHERE fila_carregamento_id = :car_id"
        );
        $stmt->execute([':car_id' => $carregamentoId]);
        $ultimoNumero = $stmt->fetchColumn() ?: 0;
        return (int) $ultimoNumero + 1;
    }

    /**
     * Busca os itens de um carregamento para a tela de conferência de forma consolidada.
     *
     * @param int $carregamentoId
     * @return array
     */
    public function getItensParaConferencia(int $carregamentoId): array
    {
        $sql = "SELECT
                    -- Agrupando por item de lote, que representa um produto/lote específico
                    ci.car_item_lote_novo_item_id,
                    lne.item_emb_prod_sec_id as item_produto_id,
                    p.prod_descricao,
                    lnh.lote_completo_calculado,
                    -- Somando a quantidade de todas as ocorrências deste item no carregamento
                    SUM(ci.car_item_quantidade) as car_item_quantidade,
                    -- A subconsulta de estoque já funciona corretamente para o item agrupado
                    (SELECT SUM(CASE WHEN es.estoque_tipo_movimento LIKE 'ENTRADA%' THEN es.estoque_quantidade ELSE -es.estoque_quantidade END)
                     FROM tbl_estoque es
                     WHERE es.estoque_lote_item_id = ci.car_item_lote_novo_item_id) as estoque_pendente
                FROM tbl_carregamento_itens ci
                JOIN tbl_lotes_novo_embalagem lne ON ci.car_item_lote_novo_item_id = lne.item_emb_id
                JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                WHERE ci.car_item_carregamento_id = :id
                -- Agrupando os resultados para consolidar os totais
                GROUP BY
                    ci.car_item_lote_novo_item_id,
                    lne.item_emb_prod_sec_id,
                    p.prod_descricao,
                    lnh.lote_completo_calculado";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $carregamentoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executa a baixa final do estoque para um carregamento.
     *
     * @param int $carregamentoId
     * @param bool $forcarBaixa Se deve permitir estoque negativo.
     * @throws Exception
     */
    public function confirmarBaixaDeEstoque(int $carregamentoId, bool $forcarBaixa): void
    {
        $this->pdo->beginTransaction();
        try {
            // Agora, esta chamada retorna os itens já somados e agrupados
            $itensParaBaixa = $this->getItensParaConferencia($carregamentoId);

            if (empty($itensParaBaixa)) {
                throw new Exception("Este carregamento não contém itens para finalizar.");
            }

            // O loop agora processa os totais de cada produto/lote
            foreach ($itensParaBaixa as $item) {
                if (!$forcarBaixa && (float) $item['car_item_quantidade'] > (float) $item['estoque_pendente']) {
                    throw new Exception("Estoque insuficiente para o produto '{$item['prod_descricao']}'. A operação foi cancelada.");
                }

                // 1. Cria um único movimento de SAÍDA no estoque com a quantidade total
                $stmtEstoque = $this->pdo->prepare(
                    "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
                     VALUES (:prod_id, :lote_item_id, :qtd, 'SAIDA', :obs)"
                );
                $stmtEstoque->execute([
                    ':prod_id' => $item['item_produto_id'],
                    ':lote_item_id' => $item['car_item_lote_novo_item_id'],
                    ':qtd' => $item['car_item_quantidade'],
                    ':obs' => "Saída para Carregamento Nº {$carregamentoId}"
                ]);

                // 2. Atualiza a quantidade finalizada no item de EMBALAGEM com o total
                $stmtUpdateItem = $this->pdo->prepare(
                    "UPDATE tbl_lotes_novo_embalagem 
                     SET item_emb_qtd_finalizada = item_emb_qtd_finalizada + :qtd 
                     WHERE item_emb_id = :id"
                );
                $stmtUpdateItem->execute([
                    ':qtd' => $item['car_item_quantidade'],
                    ':id' => $item['car_item_lote_novo_item_id']
                ]);
            }

            // 3. Atualiza o status final do carregamento
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

    /**
     * Remove uma fila completa e todos os seus itens associados.
     *
     * @param int $filaId O ID da fila a ser removida (da tbl_carregamento_filas).
     * @return bool
     * @throws Exception
     */
    public function removerFilaCompleta(int $filaId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // Passo único: Apagar a própria fila. O banco de dados cuida do resto.
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

    /**
     * Atualiza o caminho da foto para uma fila específica.
     */
    public function updateFilaPhotoPath(int $filaId, string $filePath): bool
    {
        // PASSO 1: Buscar dados antigos ANTES de atualizar
        $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamento_filas WHERE fila_id = :id");
        $stmtAntigo->execute([':id' => $filaId]);
        $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

        $sql = "UPDATE tbl_carregamento_filas SET fila_foto_path = :path WHERE fila_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([':path' => $filePath, ':id' => $filaId]);

        // PASSO 2: Se a atualização foi bem-sucedida, registar o log
        if ($success && $dadosAntigos) {
            $dadosNovos = $dadosAntigos;
            $dadosNovos['fila_foto_path'] = $filePath; // Atualiza o campo modificado
            $this->auditLogger->log('UPDATE', $filaId, 'tbl_carregamento_filas', $dadosAntigos, $dadosNovos);
        }

        return $success;
    }

    /**
     * Altera o status de um carregamento para 'CANCELADO'.
     * Se o carregamento já estiver 'FINALIZADO', reverte os movimentos de estoque.
     * @param int $carregamentoId
     * @return bool
     * @throws Exception
     */
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

            // Se o carregamento já foi finalizado, precisamos reverter o estoque.
            if ($dadosAntigos['car_status'] === 'FINALIZADO') {
                $stmtItens = $this->pdo->prepare(
                    "SELECT 
                        ci.car_item_lote_novo_item_id, 
                        ci.car_item_quantidade, 
                        lne.item_emb_prod_sec_id 
                     FROM tbl_carregamento_itens ci
                     JOIN tbl_lotes_novo_embalagem lne ON lne.item_emb_id = ci.car_item_lote_novo_item_id
                     WHERE ci.car_item_carregamento_id = :id"
                );
                $stmtItens->execute([':id' => $carregamentoId]);
                $itensParaEstornar = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

                foreach ($itensParaEstornar as $item) {
                    $stmtUpdateItem = $this->pdo->prepare(
                        "UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = item_emb_qtd_finalizada - :qtd 
                         WHERE item_emb_id = :id"
                    );
                    $stmtUpdateItem->execute([':qtd' => $item['car_item_quantidade'], ':id' => $item['car_item_lote_novo_item_id']]);

                    // Cria o movimento de ENTRADA (estorno) no estoque
                    $stmtEstoque = $this->pdo->prepare(
                        "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
                         VALUES (:prod_id, :lote_item_id, :qtd, 'ENTRADA POR CANCELAMENTO', :obs)"
                    );
                    $stmtEstoque->execute([
                        ':prod_id' => $item['item_emb_prod_sec_id'],
                        ':lote_item_id' => $item['car_item_lote_novo_item_id'],
                        ':qtd' => $item['car_item_quantidade'],
                        ':obs' => "Cancelamento do Carregamento Nº {$dadosAntigos['car_numero']}"
                    ]);
                }
            }

            // Finalmente, atualiza o status do carregamento para 'CANCELADO'
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

    /**
     * Exclui permanentemente um carregamento e todos os seus dados associados.
     * @param int $carregamentoId
     * @return bool
     * @throws Exception
     */
    public function excluir(int $carregamentoId): bool
    {

        $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamentos WHERE car_id = :id");
        $stmtAntigo->execute([':id' => $carregamentoId]);
        $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

        if (!$dadosAntigos) {
            throw new Exception("Carregamento não encontrado.");
        }

        if ($dadosAntigos['car_status'] === 'FINALIZADO') {
            throw new Exception("Não é possível excluir um carregamento finalizado. Cancele-o primeiro se necessário.");
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

    /**
     * Altera o status de um carregamento de 'CANCELADO' de volta para 'EM ANDAMENTO'.
     *
     * @param int $carregamentoId
     * @return bool
     * @throws Exception
     */
    public function reativar(int $carregamentoId): bool
    {
        $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamentos WHERE car_id = :id");
        $stmtAntigo->execute([':id' => $carregamentoId]);
        $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

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

    /**
     * Reabre um carregamento finalizado, revertendo todos os movimentos de estoque.
     */
    public function reabrir(int $carregamentoId, string $motivo): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Validações Iniciais
            $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamentos WHERE car_id = :id");
            $stmtAntigo->execute([':id' => $carregamentoId]);
            $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

            if (!$dadosAntigos || $dadosAntigos['car_status'] !== 'FINALIZADO') {
                throw new Exception("Apenas carregamentos finalizados podem ser reabertos.");
            }

            // 2. Busca todos os itens que foram baixados por este carregamento
            $stmtItens = $this->pdo->prepare(
                "SELECT ci.car_item_lote_novo_item_id, ci.car_item_quantidade, lne.item_emb_prod_sec_id 
                 FROM tbl_carregamento_itens ci
                 JOIN tbl_lotes_novo_embalagem lne ON lne.item_emb_id = ci.car_item_lote_novo_item_id
                 WHERE ci.car_item_carregamento_id = :id"
            );
            $stmtItens->execute([':id' => $carregamentoId]);
            $itensParaEstornar = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            // 3. Itera sobre cada item para reverter o estoque
            foreach ($itensParaEstornar as $item) {
                // 3a. DEVOLVE a quantidade para o lote de origem (SUBTRAI da qtd finalizada)
                // Esta lógica está correta para reverter a finalização.
                $stmtUpdateItem = $this->pdo->prepare(
                    "UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = item_emb_qtd_finalizada - :qtd 
                     WHERE item_emb_id = :id"
                );
                $stmtUpdateItem->execute([
                    ':qtd' => $item['car_item_quantidade'],
                    ':id' => $item['car_item_lote_novo_item_id']
                ]);

                // 3b. Cria o movimento de ENTRADA (estorno) no estoque, o que AUMENTA o saldo.
                // Esta lógica está correta para devolver o produto ao estoque.
                $stmtEstoque = $this->pdo->prepare(
                    "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
                     VALUES (:prod_id, :lote_item_id, :qtd, 'ENTRADA POR ESTORNO', :obs)"
                );
                $stmtEstoque->execute([
                    ':prod_id' => $item['item_emb_prod_sec_id'],
                    ':lote_item_id' => $item['car_item_lote_novo_item_id'],
                    ':qtd' => $item['car_item_quantidade'],
                    ':obs' => "Estorno do Carregamento Nº {$dadosAntigos['car_numero']} (Reabertura)"
                ]);
            }

            // 4. Altera o status do carregamento de volta para 'EM ANDAMENTO'
            $stmtUpdateCarregamento = $this->pdo->prepare("UPDATE tbl_carregamentos SET car_status = 'EM ANDAMENTO' WHERE car_id = :id");
            $stmtUpdateCarregamento->execute([':id' => $carregamentoId]);

            // 5. Regista a auditoria
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

    // Em src/Carregamentos/CarregamentoRepository.php

    /**
     * Valida uma string de QR Code complexa (GS1), verificando se o produto/lote existe.
     */
    /* public function validarQrCode(string $qrCodeContent): array
     {
         // ==========================================================
         // NOVA LÓGICA DE PARSE INTELIGENTE
         // ==========================================================
         function parseGS1(string $code): array
         {
             $data = [];
             $i = 0;
             while ($i < strlen($code)) {
                 // Tenta identificar os AIs (Application Identifiers) conhecidos
                 if (strpos($code, '01', $i) === $i) { // GTIN (sempre 14 dígitos)
                     $data['01'] = substr($code, $i + 2, 14);
                     $i += 16;
                 } elseif (strpos($code, '241', $i) === $i) { // Código do Produto (comprimento variável, buscamos pelo próximo AI)
                     $start = $i + 3;
                     // O próximo AI conhecido é '11' ou '10'. Adicione outros se necessário.
                     $nextAiPos11 = strpos($code, '11', $start);
                     $nextAiPos10 = strpos($code, '10', $start);

                     $end = PHP_INT_MAX;
                     if ($nextAiPos11 !== false)
                         $end = min($end, $nextAiPos11);
                     if ($nextAiPos10 !== false)
                         $end = min($end, $nextAiPos10);

                     if ($end === PHP_INT_MAX)
                         $end = strlen($code);

                     $value = substr($code, $start, $end - $start);
                     $data['241'] = $value;
                     $i = $end;
                 } elseif (strpos($code, '10', $i) === $i) { // Lote (comprimento variável)
                     $start = $i + 2;
                     $nextAiPos11 = strpos($code, '11', $start);
                     $end = ($nextAiPos11 !== false) ? $nextAiPos11 : strlen($code);

                     $value = substr($code, $start, $end - $start);
                     $data['10'] = $value;
                     $i = $end;
                 } else {
                     // Se encontrar um caractere desconhecido, para o parse para evitar loop infinito
                     break;
                 }
             }
             return $data;
         }

         $parsedData = parseGS1($qrCodeContent);

         // Extraímos o GTIN e o código do produto
         $gtin = $parsedData['01'] ?? null;
         $codigoProduto = $parsedData['241'] ?? null;
         $lote = $parsedData['10'] ?? null;

         if (!$gtin || !$codigoProduto || !$lote) {
             return ['success' => false, 'message' => 'QR Code não contém GTIN (01), Produto (241) ou Lote (10).'];
         }

         // A consulta SQL para usar o código do produto
         $sql = "
             SELECT 
                 lne.item_emb_id as lote_item_id,
                 p.prod_descricao
             FROM tbl_produtos p
             JOIN tbl_lotes_novo_embalagem lne ON p.prod_codigo = lne.item_emb_prod_sec_id
             JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
             WHERE p.prod_dun14 = :gtin 
               AND p.prod_codigo_interno = :codigo_produto
               AND lnh.lote_completo_calculado = :lote
             LIMIT 1;
         ";

         $stmt = $this->pdo->prepare($sql);
         $stmt->execute([':gtin' => $gtin, ':codigo_produto' => $codigoProduto, ':lote' => $lote]);
         $item = $stmt->fetch(PDO::FETCH_ASSOC);

         if ($item) {
             return [
                 'success' => true,
                 'message' => 'Item válido.',
                 'produto' => $item['prod_descricao'],
                 'lote' => $lote,
                 'lote_item_id' => $item['lote_item_id']
             ];
         } else {
             return ['success' => false, 'message' => "Produto/Lote não encontrado. GTIN: $gtin, CodProd: $codigoProduto, Lote: $lote"];
         }
     }*/

    // Em src/Carregamentos/CarregamentoRepository.php

    /**
     * Valida uma string de QR Code complexa (GS1) usando Expressão Regular.
     */
    public function validarQrCode(string $qrCodeContent): array
    {
        // ==========================================================
        // NOVA LÓGICA DE PARSE COM EXPRESSÃO REGULAR (RegEx)
        // ==========================================================
        $gtin = null;
        $codigoProduto = null;
        $lote = null;

        // Este "molde" procura pelas tags 01, 241 e 10 em sequência
        // e captura o que vem depois delas, até a próxima tag.
        $pattern = '/^01(\d{14})241(.+?)10(.+?)11/';

        if (preg_match($pattern, $qrCodeContent, $matches)) {
            $gtin = $matches[1] ?? null;          // O que foi capturado no primeiro ( )
            $codigoProduto = $matches[2] ?? null; // O que foi capturado no segundo ( )
            $lote = $matches[3] ?? null;          // O que foi capturado no terceiro ( )
        }

        if (!$gtin || !$codigoProduto || !$lote) {
            return ['success' => false, 'message' => "Parse Falhou. GTIN(01), Produto(241) ou Lote(10) não encontrados no formato esperado."];
        }

        // Agora, usamos os dados extraídos para buscar no banco.
        $sql = "
            SELECT 
                lne.item_emb_id as lote_item_id,
                p.prod_descricao
            FROM tbl_produtos p
            JOIN tbl_lotes_novo_embalagem lne ON p.prod_codigo = lne.item_emb_prod_sec_id
            JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
            WHERE p.prod_dun14 = :gtin 
              AND p.prod_codigo_interno = :codigo_produto
              AND lnh.lote_completo_calculado = :lote
            LIMIT 1;
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':gtin' => $gtin, ':codigo_produto' => $codigoProduto, ':lote' => $lote]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                return [
                    'success' => true,
                    'message' => 'Item válido.',
                    'produto' => $item['prod_descricao'],
                    'lote' => $lote,
                    'lote_item_id' => $item['lote_item_id']
                ];
            } else {
                return ['success' => false, 'message' => "Produto/Lote não encontrado no sistema. Verifique os dados."];
            }
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Erro de SQL: ' . $e->getMessage()];
        }
    }

    /**
     * Altera o status de um carregamento para AGUARDANDO CONFERENCIA.
     *
     * @param int $carregamentoId O ID do carregamento a ser alterado.
     * @param int $userId O ID do usuário que realizou a ação.
     * @return bool
     */
    public function marcarComoAguardandoConferencia(int $carregamentoId, int $userId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamentos WHERE car_id = :id");
            $stmtAntigo->execute([':id' => $carregamentoId]);
            $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

            if (!$dadosAntigos || $dadosAntigos['car_status'] !== 'EM ANDAMENTO') {
                throw new Exception("Apenas carregamentos 'EM ANDAMENTO' podem ser enviados para conferência.");
            }

            $stmt = $this->pdo->prepare(
                "UPDATE tbl_carregamentos SET car_status = 'AGUARDANDO CONFERENCIA' WHERE car_id = :id"
            );
            $stmt->execute([':id' => $carregamentoId]);

            $rowCount = $stmt->rowCount();

            if ($rowCount > 0) {
                $dadosNovos = $dadosAntigos;
                $dadosNovos['car_status'] = 'AGUARDANDO CONFERENCIA';
                $this->auditLogger->log('STATUS_CHANGE', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, $dadosNovos, "Enviado para conferência pelo App (Usuário ID: {$userId})");
            }

            $this->pdo->commit();
            return $rowCount > 0;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e; // Propaga a exceção para ser capturada pela API
        }
    }

    /**
     * Busca os últimos carregamentos com status 'EM ANDAMENTO'.
     */
    public function findAtivos(int $limit = 3): array
    {
        $sql = "SELECT 
                    c.car_id as carregamentoId,
                    c.car_numero as numero, 
                    c.car_data as data, 
                    e.ent_nome_fantasia as nome_cliente,
                    u.usu_nome as responsavel
                FROM tbl_carregamentos c
                JOIN tbl_entidades e ON c.car_entidade_id_organizador = e.ent_codigo
                JOIN tbl_usuarios u ON c.car_usuario_id_responsavel = u.usu_codigo
                WHERE c.car_status = 'EM ANDAMENTO'
                ORDER BY c.car_data DESC, c.car_id DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca os últimos carregamentos com status 'FINALIZADO'.
     */
    public function findFinalizados(int $limit = 3): array
    {
        $sql = "SELECT 
                    c.car_id as carregamentoId,
                    c.car_numero as numero, 
                    c.car_data as data, 
                    e.ent_nome_fantasia as nome_cliente,
                    u.usu_nome as responsavel
                FROM tbl_carregamentos c
                JOIN tbl_entidades e ON c.car_entidade_id_organizador = e.ent_codigo
                JOIN tbl_usuarios u ON c.car_usuario_id_responsavel = u.usu_codigo
                WHERE c.car_status = 'FINALIZADO' 
                ORDER BY c.car_data DESC, c.car_id DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todas as filas de um carregamento específico com contagem de clientes e itens.
     */
    public function findFilasByCarregamentoId(int $carregamentoId): array
    {
        // Esta query é um exemplo. Pode ser que a sua tabela de itens
        // não se chame tbl_carregamento_itens. Ajustaremos se necessário.
        $sql = "SELECT 
                    f.fila_id, 
                    f.fila_numero_sequencial,
                    (SELECT COUNT(DISTINCT ci.car_item_cliente_id) 
                     FROM tbl_carregamento_itens ci 
                     WHERE ci.car_item_fila_id = f.fila_id) as total_clientes,
                    (SELECT SUM(ci.car_item_quantidade) 
                     FROM tbl_carregamento_itens ci 
                     WHERE ci.car_item_fila_id = f.fila_id) as total_quantidade
                FROM tbl_carregamento_filas f
                WHERE f.fila_carregamento_id = :carregamento_id
                ORDER BY f.fila_numero_sequencial ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':carregamento_id' => $carregamentoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza os dados do cabeçalho de um carregamento existente.
     */
    public function updateHeader(int $carregamentoId, array $data, int $userId): bool
    {
        // Busca os dados antigos para o log de auditoria
        $dadosAntigos = $this->findHeaderById($carregamentoId);

        $sql = "UPDATE tbl_carregamentos SET
                    car_lacre = :lacre,
                    car_placa_veiculo = :placa,
                    car_hora_inicio = :hora_inicio,
                    car_ordem_expedicao = :ordem_expedicao
                WHERE car_id = :carregamento_id";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':lacre' => $data['lacre'] ?? null,
            ':placa' => $data['placa'] ?? null,
            ':hora_inicio' => $data['hora_inicio'] ?? null,
            ':ordem_expedicao' => $data['ordem_expedicao'] ?? null,
            ':carregamento_id' => $carregamentoId
        ]);

        if ($success) {
            $this->auditLogger->log('UPDATE', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, $data);
        }

        return $success;
    }

    /**
     * Busca os dados completos do cabeçalho de um único carregamento.
     */
    public function findHeaderById(int $carregamentoId): ?array
    {
        $sql = "SELECT 
                    c.car_numero,
                    c.car_data,
                    c.car_lacre,
                    c.car_placa_veiculo,
                    c.car_hora_inicio,
                    c.car_ordem_expedicao,
                    e.ent_nome_fantasia as nome_cliente
                FROM tbl_carregamentos c
                JOIN tbl_entidades e ON c.car_entidade_id_organizador = e.ent_codigo
                WHERE c.car_id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $carregamentoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
