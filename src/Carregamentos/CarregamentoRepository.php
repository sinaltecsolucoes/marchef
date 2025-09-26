<?php
// /src/Carregamentos/CarregamentoRepository.php
namespace App\Carregamentos;

use PDO;
use App\Core\AuditLoggerService;
use App\OrdensExpedicao\OrdemExpedicaoRepository;

class CarregamentoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo); // Se usar
    }

    /**
     * Busca carregamentos para a DataTable (REFATORADO)
     */
    public function findAllForDataTable(array $params): array
    {
        // Colunas para busca (se houver)
        $searchableColumns = ['c.car_numero', 'oe.oe_numero', 'c.car_motorista_nome', 'c.car_placas', 'c.car_status'];

        // --- Construção da Query Base ---
        $baseQuery = "FROM tbl_carregamentos c
                      LEFT JOIN tbl_ordens_expedicao_header oe ON c.car_ordem_expedicao_id = oe.oe_id
                      LEFT JOIN tbl_entidades e ON c.car_entidade_id_organizador = e.ent_codigo";

        // --- Filtros (Search e Status) ---
        $where = " WHERE 1=1 ";
        $queryParams = [];

        // Filtro de Busca (DataTables)
        if (!empty($params['search']['value'])) {
            $searchValue = '%' . $params['search']['value'] . '%';
            $whereParts = [];
            foreach ($searchableColumns as $col) {
                $whereParts[] = "$col LIKE :search_value";
            }
            $where .= " AND (" . implode(' OR ', $whereParts) . ")";
            $queryParams[':search_value'] = $searchValue;
        }

        // Você pode adicionar filtros de STATUS aqui se desejar (ex: vindo de um dropdown na tela)
        // if (!empty($params['status'])) {
        //     $where .= " AND c.car_status = :status";
        //     $queryParams[':status'] = $params['status'];
        // }

        // --- Contagem de Registros ---
        $totalRecords = $this->pdo->query("SELECT COUNT(c.car_id) $baseQuery")->fetchColumn();
        $totalFiltered = $this->pdo->prepare("SELECT COUNT(c.car_id) $baseQuery $where");
        $totalFiltered->execute($queryParams);
        $totalFiltered = $totalFiltered->fetchColumn();


        // --- Ordenação ---
        $order = " ORDER BY c.car_data DESC, c.car_id DESC "; // Padrão
        if (isset($params['order'][0]) && $params['columns'][$params['order'][0]['column']]['data']) {
            $colIndex = $params['order'][0]['column'];
            $colName = $params['columns'][$colIndex]['data'];
            $dir = $params['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';

            // Mapeamento seguro de colunas
            $columnMap = [
                'car_numero' => 'c.car_numero',
                'car_data' => 'c.car_data',
                'oe_numero' => 'oe.oe_numero',
                'car_motorista_nome' => 'c.car_motorista_nome',
                'car_placas' => 'c.car_placas',
                'car_status' => 'c.car_status'
            ];

            if (isset($columnMap[$colName])) {
                $order = " ORDER BY " . $columnMap[$colName] . " $dir ";
            }
        }

        // --- Paginação ---
        $limit = " LIMIT :start, :length";
        $queryParams[':start'] = (int) ($params['start'] ?? 0);
        $queryParams[':length'] = (int) ($params['length'] ?? 10);

        // --- Query Final ---
        $sqlData = "SELECT 
                        c.car_id,
                        c.car_numero,
                        c.car_data,
                        c.car_status,
                        c.car_motorista_nome,
                        c.car_placas,
                        oe.oe_numero
                    $baseQuery $where $order $limit";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->execute($queryParams);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data
        ];
    }

    /**
     * Salva o cabeçalho do Carregamento (Função que substitui a 'createHeader' que estava no router)
     * Usa os campos que você acabou de adicionar no banco.
     */
    public function salvarCarregamentoHeader(array $data, int $usuarioId): int
    {
        // Validação: Verifica se a OE já não está em outro carregamento
        $stmtCheck = $this->pdo->prepare("SELECT car_id FROM tbl_carregamentos WHERE car_ordem_expedicao_id = :oe_id AND car_status != 'CANCELADO'");
        $stmtCheck->execute([':oe_id' => $data['car_ordem_expedicao_id']]);
        if ($stmtCheck->fetchColumn()) {
            throw new \Exception("Esta Ordem de Expedição já está sendo usada em outro carregamento.");
        }

        $sql = "INSERT INTO tbl_carregamentos 
                    (car_numero, car_data, car_entidade_id_organizador, 
                    car_transportadora_id, car_ordem_expedicao_id, 
                    car_motorista_nome, car_motorista_cpf, car_placas, 
                    car_lacres, car_usuario_id_responsavel, car_status)
                VALUES 
                    (:car_numero, :car_data, :car_entidade_id_organizador, 
                    :car_transportadora_id, :car_ordem_expedicao_id, :car_motorista_nome, 
                    :car_motorista_cpf, :car_placas, :car_lacres, :usuario_id, 'EM ANDAMENTO')";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':car_numero' => $data['car_numero'],
            ':car_data' => $data['car_data'],
            ':car_entidade_id_organizador' => $data['car_entidade_id_organizador'],
            ':car_transportadora_id' => $data['car_transportadora_id'] ?: null,
            ':car_ordem_expedicao_id' => $data['car_ordem_expedicao_id'],
            ':car_motorista_nome' => $data['car_motorista_nome'] ?: null,
            ':car_motorista_cpf' => $data['car_motorista_cpf_limpo'] ?: null,
            ':car_placas' => $data['car_placas'] ?: null,
            ':car_lacres' => $data['car_lacres'] ?: null,
            ':usuario_id' => $usuarioId
        ]);

        $newId = (int) $this->pdo->lastInsertId();
        // $this->auditLogger->log('CREATE', $newId, 'tbl_carregamentos', null, $data);

        return $newId;
    }

    /**
     * Busca o próximo número de carregamento (você já deve ter essa)
     */
    public function getNextNumeroCarregamento(): string
    {
        $stmt = $this->pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(car_numero, '.', 1) AS UNSIGNED)) FROM tbl_carregamentos");
        $lastNum = ($stmt->fetchColumn() ?: 0) + 1;
        //$sequence = str_pad($lastNum, 4, '0', STR_PAD_LEFT);
        //$datePart = date('m.Y');
        //return $sequence . '.' . $datePart;
        return str_pad($lastNum, 4, '0', STR_PAD_LEFT);

    }

    /**
     * Adiciona uma nova fila (vazia) ao carregamento.
     */
    public function addFila(int $carregamentoId): int
    {
        // Pega o próximo número sequencial
        $stmtSeq = $this->pdo->prepare("SELECT COALESCE(MAX(fila_numero_sequencial), 0) + 1 FROM tbl_carregamento_filas WHERE fila_carregamento_id = :id");
        $stmtSeq->execute([':id' => $carregamentoId]);
        $sequencial = $stmtSeq->fetchColumn();

        $sql = "INSERT INTO tbl_carregamento_filas (fila_carregamento_id, fila_numero_sequencial) VALUES (:id, :seq)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $carregamentoId, ':seq' => $sequencial]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Remove uma fila e todos os itens dela (se houver).
     */
    public function removeFila(int $filaId): bool
    {
        // O banco de dados está configurado com ON DELETE CASCADE,
        // então só precisamos excluir a fila.
        // Mas vamos fazer na mão para garantir a reversão de estoque se necessário (embora itens de carregamento não revertam)

        $this->pdo->beginTransaction();
        try {
            // Exclui itens
            $stmtItens = $this->pdo->prepare("DELETE FROM tbl_carregamento_itens WHERE car_item_fila_id = :fila_id");
            $stmtItens->execute([':fila_id' => $filaId]);

            // Exclui fila
            $stmtFila = $this->pdo->prepare("DELETE FROM tbl_carregamento_filas WHERE fila_id = :fila_id");
            $stmtFila->execute([':fila_id' => $filaId]);

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Remove um item específico do carregamento.
     */
    public function removeItem(int $carItemId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tbl_carregamento_itens WHERE car_item_id = :id");
        return $stmt->execute([':id' => $carItemId]);
    }

    /**
     * Adiciona um item ao carregamento que VEIO DA OE (do gabarito).
     */
    public function addItemFromOE(array $data): int
    {
        // 1. Validar Saldo
        $oeiId = $data['oei_id'];
        $quantidade = $data['quantidade'];

        // Busca o item da OE
        $stmtOE = $this->pdo->prepare("SELECT * FROM tbl_ordens_expedicao_itens WHERE oei_id = :id");
        $stmtOE->execute([':id' => $oeiId]);
        $itemOE = $stmtOE->fetch(PDO::FETCH_ASSOC);

        if (!$itemOE)
            throw new \Exception("Item da Ordem de Expedição não encontrado.");

        $alocacaoId = $itemOE['oei_alocacao_id'];
        $qtdPlanejada = $itemOE['oei_quantidade'];

        // Busca o cliente do item
        $stmtCliente = $this->pdo->prepare("SELECT oep_cliente_id FROM tbl_ordens_expedicao_pedidos WHERE oep_id = :id");
        $stmtCliente->execute([':id' => $itemOE['oei_pedido_id']]);
        $clienteId = $stmtCliente->fetchColumn();

        // Busca o carregamento_id (a partir da fila)
        $stmtCarId = $this->pdo->prepare("SELECT fila_carregamento_id FROM tbl_carregamento_filas WHERE fila_id = :id");
        $stmtCarId->execute([':id' => $data['fila_id']]);
        $carregamentoId = $stmtCarId->fetchColumn();

        // 2. Insere na tabela
        $sql = "INSERT INTO tbl_carregamento_itens 
                    (car_item_carregamento_id, car_item_fila_id, car_item_cliente_id, 
                     car_item_lote_novo_item_id, car_item_alocacao_id, 
                     car_item_quantidade, car_item_oei_id_origem)
                SELECT 
                    :car_id, :fila_id, :cliente_id,
                    ea.alocacao_lote_item_id, ea.alocacao_id,
                    :quantidade, :oei_id
                FROM tbl_estoque_alocacoes ea
                WHERE ea.alocacao_id = :alocacao_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':car_id' => $carregamentoId,
            ':fila_id' => $data['fila_id'],
            ':cliente_id' => $clienteId,
            ':quantidade' => $quantidade,
            ':oei_id' => $oeiId,
            ':alocacao_id' => $alocacaoId
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Adiciona um item de DIVERGÊNCIA (não estava na OE).
     */
    public function addItemDivergencia(array $data): int
    {
        // 1. Validações
        $alocacaoId = $data['div_alocacao_id'];
        $quantidade = $data['div_quantidade'];
        $clienteId = $data['div_cliente_id'];
        $filaId = $data['div_fila_id'];
        $motivo = $data['div_motivo'];

        // Valida saldo (copiado da OrdemExpedicaoRepository)
        // $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) FROM tbl_ordens_expedicao_itens WHERE oei_alocacao_id = ea.alocacao_id)";
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) FROM tbl_ordens_expedicao_itens WHERE oei_alocacao_id = ea.alocacao_id AND oei_status = 'PENDENTE')";
        $query = "SELECT (ea.alocacao_quantidade - {$subQueryReservado}) AS saldo_disponivel
                  FROM tbl_estoque_alocacoes ea
                  WHERE ea.alocacao_id = :alocacao_id";
        $stmtSaldo = $this->pdo->prepare($query);
        $stmtSaldo->execute([':alocacao_id' => $alocacaoId]);
        $saldo = $stmtSaldo->fetchColumn();

        if ($saldo === false || $saldo < $quantidade) {
            throw new \Exception("Quantidade solicitada (" . $quantidade . ") excede o saldo disponível (" . $saldo . ") no endereço.");
        }

        // Busca o carregamento_id (a partir da fila)
        $stmtCarId = $this->pdo->prepare("SELECT fila_carregamento_id FROM tbl_carregamento_filas WHERE fila_id = :id");
        $stmtCarId->execute([':id' => $filaId]);
        $carregamentoId = $stmtCarId->fetchColumn();

        // 2. Insere
        $sql = "INSERT INTO tbl_carregamento_itens 
                    (car_item_carregamento_id, car_item_fila_id, car_item_cliente_id, 
                     car_item_lote_novo_item_id, car_item_alocacao_id, 
                     car_item_quantidade, car_item_motivo_divergencia)
                SELECT 
                    :car_id, :fila_id, :cliente_id,
                    ea.alocacao_lote_item_id, ea.alocacao_id,
                    :quantidade, :motivo
                FROM tbl_estoque_alocacoes ea
                WHERE ea.alocacao_id = :alocacao_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':car_id' => $carregamentoId,
            ':fila_id' => $filaId,
            ':cliente_id' => $clienteId,
            ':quantidade' => $quantidade,
            ':motivo' => $motivo,
            ':alocacao_id' => $alocacaoId
        ]);

        $newId = (int) $this->pdo->lastInsertId();

        $auditData = $data + ['carregamento_id' => $carregamentoId, 'tipo' => 'ITEM_DIVERGENCIA'];
        $this->auditLogger->log('CREATE', $newId, 'tbl_carregamento_itens', null, $auditData, 'Item de DIVERGÊNCIA adicionado: ' . $data['div_motivo']);

        return $newId;
    }

    /**
     * Atualiza o status de um carregamento.
     * Função helper interna.
     */
    private function updateStatus(int $id, string $novoStatus): bool
    {
        $sql = "UPDATE tbl_carregamentos SET car_status = :status WHERE car_id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':status' => $novoStatus, ':id' => $id]);
    }

    /**
     * Finaliza um carregamento, baixa o estoque e VINCULA a OE.
     */
    public function finalizar(int $carregamentoId, OrdemExpedicaoRepository $ordemExpedicaoRepo): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Valida o Carregamento e busca o ID da OE
            $stmtStatus = $this->pdo->prepare("SELECT car_status, car_numero, car_ordem_expedicao_id FROM tbl_carregamentos WHERE car_id = :id FOR UPDATE");
            $stmtStatus->execute([':id' => $carregamentoId]);
            $carregamento = $stmtStatus->fetch(PDO::FETCH_ASSOC);

            if (!$carregamento || $carregamento['car_status'] !== 'EM ANDAMENTO') {
                throw new \Exception("Apenas carregamentos 'EM ANDAMENTO' podem ser finalizados.");
            }

            // 2. Busca todos os itens do carregamento para dar baixa no estoque
            $stmtItens = $this->pdo->prepare(
                "SELECT ci.car_item_lote_novo_item_id, ci.car_item_quantidade, lne.item_emb_prod_sec_id 
                 FROM tbl_carregamento_itens ci
                 JOIN tbl_lotes_novo_embalagem lne ON lne.item_emb_id = ci.car_item_lote_novo_item_id
                 WHERE ci.car_item_carregamento_id = :id"
            );
            $stmtItens->execute([':id' => $carregamentoId]);
            $itensParaBaixar = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            if (empty($itensParaBaixar)) {
                throw new \Exception("Não é possível finalizar um carregamento sem itens.");
            }

            // Prepara as queries que serão usadas dentro do loop
            $stmtUpdateLoteItem = $this->pdo->prepare(
                "UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = item_emb_qtd_finalizada + :qtd 
                 WHERE item_emb_id = :id"
            );
            $stmtMovimentoEstoque = $this->pdo->prepare(
                "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
                 VALUES (:prod_id, :lote_item_id, :qtd, 'SAÍDA POR CARREGAMENTO', :obs)"
            );

            // 3. Loop para dar baixa em cada item individualmente
            foreach ($itensParaBaixar as $item) {
                // Incrementa a quantidade finalizada no item de embalagem original
                $stmtUpdateLoteItem->execute([
                    ':qtd' => $item['car_item_quantidade'],
                    ':id' => $item['car_item_lote_novo_item_id']
                ]);

                // Cria o registro de movimento de SAÍDA na tabela de estoque
                $stmtMovimentoEstoque->execute([
                    ':prod_id' => $item['item_emb_prod_sec_id'],
                    ':lote_item_id' => $item['car_item_lote_novo_item_id'],
                    ':qtd' => $item['car_item_quantidade'],
                    ':obs' => "Saída referente ao Carregamento Nº " . $carregamento['car_numero']
                ]);
            }

            // 4. Atualiza o status do próprio carregamento para 'FINALIZADO'
            $this->updateStatus($carregamentoId, 'FINALIZADO');

            // 5. ATUALIZA E BLOQUEIA A ORDEM DE EXPEDIÇÃO BASE
            if (!empty($carregamento['car_ordem_expedicao_id'])) {
                // Chama a função que criamos no Passo 3
                $ordemExpedicaoRepo->linkCarregamento($carregamento['car_ordem_expedicao_id'], $carregamentoId);
            }

            // 6. Registra na auditoria
            $this->auditLogger->log('FINALIZE', $carregamentoId, 'tbl_carregamentos', ['status_anterior' => 'EM ANDAMENTO'], ['status_novo' => 'FINALIZADO']);

            // 7. Se tudo deu certo, confirma a transação
            $this->pdo->commit();
            return true;

        } catch (\Exception $e) {
            // Se qualquer passo falhar, desfaz tudo
            $this->pdo->rollBack();
            throw $e; // Re-lança a exceção para o controller mostrar o erro
        }
    }

    /**
     * Cancela um carregamento.
     */
    public function cancelar(int $carregamentoId): bool
    {
        // Verifica se já não está finalizado (não pode cancelar o que foi finalizado)
        $stmt = $this->pdo->prepare("SELECT car_status FROM tbl_carregamentos WHERE car_id = :id");
        $stmt->execute([':id' => $carregamentoId]);
        $status = $stmt->fetchColumn();

        if ($status === 'FINALIZADO') {
            throw new \Exception("Não é possível cancelar um carregamento que já foi finalizado.");
        }

        return $this->updateStatus($carregamentoId, 'CANCELADO');
    }

    /**
     * Reabre um carregamento, estorna o estoque e desvincula a OE.
     */
    public function reabrir(int $carregamentoId, string $motivo, \App\OrdensExpedicao\OrdemExpedicaoRepository $ordemExpedicaoRepo): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamentos WHERE car_id = :id");
            $stmtAntigo->execute([':id' => $carregamentoId]);
            $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

            if (!$dadosAntigos || !in_array($dadosAntigos['car_status'], ['FINALIZADO', 'CANCELADO'])) {
                throw new Exception("Apenas carregamentos 'FINALIZADO' ou 'CANCELADO' podem ser reabertos.");
            }

            // Se estava FINALIZADO, estorna o estoque
            if ($dadosAntigos['car_status'] === 'FINALIZADO') {
                $itensParaEstornar = $this->getItensEstornaveis($carregamentoId);
                foreach ($itensParaEstornar as $item) {
                    $this->estornarEstoqueItem($item, "Reabertura do Carregamento Nº {$dadosAntigos['car_numero']}");
                }
            }

            // ### INÍCIO DA NOVA LÓGICA ###
            // Se o carregamento tinha uma OE base, desvincula ela
            if (!empty($dadosAntigos['car_ordem_expedicao_id'])) {
                $ordemExpedicaoRepo->unlinkCarregamento($dadosAntigos['car_ordem_expedicao_id']);
            }
            // ### FIM DA NOVA LÓGICA ###

            // Atualiza o status do carregamento para 'EM ANDAMENTO'
            $this->updateStatus($carregamentoId, 'EM ANDAMENTO');

            // Log de Auditoria
            $dadosNovos = $dadosAntigos;
            $dadosNovos['car_status'] = 'EM ANDAMENTO';
            $this->auditLogger->log('REOPEN', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, $dadosNovos, $motivo); // <-- USA O MOTIVO

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateHeader(int $carregamentoId, array $data): bool
    {
        // Pega a OE atual (não pode ser alterada)
        $stmtOE = $this->pdo->prepare("SELECT car_ordem_expedicao_id FROM tbl_carregamentos WHERE car_id = :id");
        $stmtOE->execute([':id' => $carregamentoId]);
        $ordemExpedicaoId = $stmtOE->fetchColumn();

        $sql = "UPDATE tbl_carregamentos SET
                    car_numero = :car_numero,
                    car_data = :car_data,
                    car_entidade_id_organizador = :car_entidade_id_organizador,
                    car_transportadora_id = :car_transportadora_id,
                    car_motorista_nome = :car_motorista_nome,
                    car_motorista_cpf = :car_motorista_cpf,
                    car_placas = :car_placas,
                    car_lacres = :car_lacres
                    /* Não permitimos alterar a OE base */
                WHERE car_id = :car_id";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':car_numero' => $data['car_numero'],
            ':car_data' => $data['car_data'],
            ':car_entidade_id_organizador' => $data['car_entidade_id_organizador'],
            ':car_transportadora_id' => $data['car_transportadora_id'] ?: null,
            ':car_motorista_nome' => $data['car_motorista_nome'] ?: null,
            ':car_motorista_cpf' => $data['car_motorista_cpf'] ?: null,
            ':car_placas' => $data['car_placas'] ?: null,
            ':car_lacres' => $data['car_lacres'] ?: null,
            ':car_id' => $carregamentoId
        ]);

        // $this->auditLogger->log('UPDATE', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, $data);
        return $success;
    }

    /**
     * Retorna os Clientes que estão na OE (para o Dropdown 1)
     */
    public function getClientesDaOE(int $oeId): array
    {
        $sql = "SELECT DISTINCT
                    e.ent_codigo AS id,
                    COALESCE(e.ent_nome_fantasia, e.ent_razao_social) AS text
                FROM tbl_ordens_expedicao_pedidos oep
                JOIN tbl_entidades e ON oep.oep_cliente_id = e.ent_codigo
                WHERE oep.oep_ordem_id = :oe_id
                ORDER BY text";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':oe_id' => $oeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os Produtos de um Cliente específico na OE (para o Dropdown 2)
     */
    public function getProdutosDoClienteNaOE(int $oeId, int $clienteId): array
    {
        $sql = "SELECT DISTINCT
                    p.prod_codigo AS id,
                    p.prod_descricao AS text
                FROM tbl_ordens_expedicao_itens oei
                JOIN tbl_ordens_expedicao_pedidos oep ON oei.oei_pedido_id = oep.oep_id
                JOIN tbl_estoque_alocacoes ea ON oei.oei_alocacao_id = ea.alocacao_id
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                WHERE oep.oep_ordem_id = :oe_id AND oep.oep_cliente_id = :cliente_id
                ORDER BY text";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':oe_id' => $oeId, ':cliente_id' => $clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os Lotes/Endereços de um Produto/Cliente na OE (para o Dropdown 3)
     */
    public function getLotesDoProdutoNaOE(int $oeId, int $clienteId, int $produtoId, int $carregamentoId): array
    {
        // Subquery para calcular o que já foi carregado
        $subQueryCarregado = "SELECT COALESCE(SUM(ci.car_item_quantidade), 0) 
                              FROM tbl_carregamento_itens ci 
                              WHERE ci.car_item_oei_id_origem = oei.oei_id 
                              AND ci.car_item_carregamento_id = :carregamento_id";

        $sql = "SELECT 
                    oei.oei_alocacao_id AS id,
                    CONCAT(lnh.lote_completo_calculado, ' / ', ee.endereco_completo) AS text,
                    (oei.oei_quantidade - ($subQueryCarregado)) as saldo_disponivel,
                    oei.oei_id -- ID do item da OE
                FROM tbl_ordens_expedicao_itens oei
                JOIN tbl_ordens_expedicao_pedidos oep ON oei.oei_pedido_id = oep.oep_id
                JOIN tbl_estoque_alocacoes ea ON oei.oei_alocacao_id = ea.alocacao_id
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                WHERE oep.oep_ordem_id = :oe_id 
                  AND oep.oep_cliente_id = :cliente_id 
                  AND lne.item_emb_prod_sec_id = :produto_id
                HAVING saldo_disponivel > 0 -- Só mostra o que ainda tem saldo
                ORDER BY text";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':oe_id' => $oeId,
            ':cliente_id' => $clienteId,
            ':produto_id' => $produtoId,
            ':carregamento_id' => $carregamentoId
        ]);

        // Precisamos buscar o oei_id também para o JS
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // O Select2 precisa que os dados estejam no 'data-attribute'
        // Mas a nossa função JS já está pegando 'saldo_disponivel'
        // Vamos modificar o retorno para incluir o OEI_ID
        return array_map(function ($row) {
            return [
                'id' => $row['id'], // ID da Alocação
                'text' => $row['text'] . " [Saldo: " . $row['saldo_disponivel'] . "]",
                'saldo_disponivel' => $row['saldo_disponivel'],
                'oei_id_origem' => $row['oei_id'] // Passa o OEI_ID
            ];
        }, $results);
    }

    /**
     * Adiciona um item vindo do modal CASCATA
     */
    public function addItemCascata(array $data, int $carregamentoId): int
    {
        $filaId = $data['cascata_fila_id'];
        $clienteId = $data['cascata_cliente_id'];
        $alocacaoId = $data['cascata_alocacao_id']; // Este é o ID do Lote/Endereço
        $quantidade = $data['cascata_quantidade'];
        $oeiIdOrigem = $data['cascata_oei_id_origem']; // O ID do item da OE

        // TODO: Validação de Saldo (crucial)
        // (O JS já fez, mas sempre valide no backend)

        // Busca o lote_novo_item_id
        $stmtLote = $this->pdo->prepare("SELECT alocacao_lote_item_id FROM tbl_estoque_alocacoes WHERE alocacao_id = :id");
        $stmtLote->execute([':id' => $alocacaoId]);
        $loteNovoItemId = $stmtLote->fetchColumn();

        $sql = "INSERT INTO tbl_carregamento_itens 
                    (car_item_carregamento_id, car_item_fila_id, car_item_cliente_id, 
                     car_item_lote_novo_item_id, car_item_alocacao_id, 
                     car_item_quantidade, car_item_oei_id_origem)
                VALUES
                    (:car_id, :fila_id, :cliente_id, :lote_item_id, :alocacao_id, :quantidade, :oei_id)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':car_id' => $carregamentoId,
            ':fila_id' => $filaId,
            ':cliente_id' => $clienteId,
            ':lote_item_id' => $loteNovoItemId,
            ':alocacao_id' => $alocacaoId,
            ':quantidade' => $quantidade,
            ':oei_id' => $oeiIdOrigem
        ]);

        $newId = (int) $this->pdo->lastInsertId();

        $auditData = $data + ['carregamento_id' => $carregamentoId, 'tipo' => 'ITEM_OE'];
        $this->auditLogger->log('CREATE', $newId, 'tbl_carregamento_itens', null, $auditData, 'Item adicionado ao carregamento (via OE)');

        return $newId;
    }

    /**
     * Busca TODOS os dados para a página de detalhes
     */
    public function getDetalhesCompletos(int $carregamentoId): array
    {
        $data = [];

        // 1. Buscar Cabeçalho
        $sqlHeader = "SELECT 
                        c.*, 
                        oe.oe_numero, oe.oe_id,
                        COALESCE(e_resp.ent_nome_fantasia, e_resp.ent_razao_social) AS cliente_responsavel_nome,
                        COALESCE(e_transp.ent_nome_fantasia, e_transp.ent_razao_social) AS transportadora_nome
                      FROM tbl_carregamentos c
                      LEFT JOIN tbl_ordens_expedicao_header oe ON c.car_ordem_expedicao_id = oe.oe_id
                      LEFT JOIN tbl_entidades e_resp ON c.car_entidade_id_organizador = e_resp.ent_codigo
                      LEFT JOIN tbl_entidades e_transp ON c.car_transportadora_id = e_transp.ent_codigo
                      WHERE c.car_id = :id";
        $stmtHeader = $this->pdo->prepare($sqlHeader);
        $stmtHeader->execute([':id' => $carregamentoId]);
        $header = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            throw new \Exception("Carregamento não encontrado.");
        }
        $data['header'] = $header;
        $ordemExpedicaoId = $header['oe_id']; // Pega o ID da OE

        // 2. Buscar Execução (Filas e Itens já carregados)
        // $sqlFilas = "SELECT * FROM tbl_carregamento_filas WHERE fila_carregamento_id = :id ORDER BY fila_numero_sequencial";

        $sqlFilas = "SELECT 
                        f.*, 
                        (SELECT COUNT(foto_id) 
                         FROM tbl_carregamento_fila_fotos 
                         WHERE foto_fila_id = f.fila_id) AS total_fotos
                     FROM tbl_carregamento_filas f 
                     WHERE f.fila_carregamento_id = :id 
                     ORDER BY f.fila_numero_sequencial";

        $stmtFilas = $this->pdo->prepare($sqlFilas);
        $stmtFilas->execute([':id' => $carregamentoId]);
        $filas = $stmtFilas->fetchAll(PDO::FETCH_ASSOC);

        $sqlItens = "SELECT 
                        ci.*, ci.car_item_quantidade AS qtd_carregada,
                        ci.car_item_cliente_id,
                        -- Usamos INNER JOIN e COALESCE para garantir que o nome sempre venha
                        COALESCE(e.ent_nome_fantasia, e.ent_razao_social) AS cliente_nome,
                        p.prod_descricao, 
                        p.prod_codigo_interno, 
                        lnh.lote_completo_calculado AS lote_completo,
                        COALESCE(ent_lote.ent_nome_fantasia, ent_lote.ent_razao_social) AS cliente_lote_nome, 
                        ee.endereco_completo, 
                        ci.car_item_motivo_divergencia AS motivo_divergencia
                     FROM tbl_carregamento_itens ci
                     -- Usamos INNER JOIN para garantir que o cliente do item sempre exista
                     INNER JOIN tbl_entidades e ON ci.car_item_cliente_id = e.ent_codigo
                     INNER JOIN tbl_estoque_alocacoes ea ON ci.car_item_alocacao_id = ea.alocacao_id
                     INNER JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                     INNER JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                     INNER JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                     INNER JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                     LEFT JOIN tbl_entidades ent_lote ON lnh.lote_cliente_id = ent_lote.ent_codigo 
                     WHERE ci.car_item_carregamento_id = :carregamento_id
                     ORDER BY ci.car_item_fila_id, cliente_nome";

        $stmtItens = $this->pdo->prepare($sqlItens);
        $stmtItens->execute([':carregamento_id' => $carregamentoId]);
        $todosItens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $key => $fila) {
            $filas[$key]['itens'] = array_values(array_filter($todosItens, function ($item) use ($fila) {
                return $item['car_item_fila_id'] == $fila['fila_id'];
            }));
        }
        $data['execucao'] = $filas;

        // 3. *** Buscar Planejamento (Gabarito da OE) e calcular Saldo ***
        if ($ordemExpedicaoId) {
            $sqlPlanejamento = "SELECT 
                                    oei.oei_id,
                                    oei.oei_alocacao_id,
                                    oei.oei_quantidade AS qtd_planejada,
                                    oep.oep_cliente_id,
                                    lne.item_emb_prod_sec_id AS produto_id,
                                    COALESCE(e.ent_nome_fantasia, e.ent_razao_social) AS cliente_nome,
                                    p.prod_descricao,
                                    lnh.lote_completo_calculado AS lote_completo,
                                    ee.endereco_completo,
                                    COALESCE((SELECT SUM(ci.car_item_quantidade) 
                                              FROM tbl_carregamento_itens ci 
                                              WHERE ci.car_item_oei_id_origem = oei.oei_id 
                                              AND ci.car_item_carregamento_id = :carregamento_id), 0) AS qtd_carregada
                                FROM tbl_ordens_expedicao_itens oei
                                JOIN tbl_ordens_expedicao_pedidos oep ON oei.oei_pedido_id = oep.oep_id
                                JOIN tbl_entidades e ON oep.oep_cliente_id = e.ent_codigo
                                JOIN tbl_estoque_alocacoes ea ON oei.oei_alocacao_id = ea.alocacao_id
                                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                                JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                                JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                                JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                                WHERE oep.oep_ordem_id = :oe_id
                                GROUP BY oei.oei_id
                                ORDER BY e.ent_nome_fantasia, p.prod_descricao";

            $stmtPlanejamento = $this->pdo->prepare($sqlPlanejamento);
            $stmtPlanejamento->execute([
                ':carregamento_id' => $carregamentoId,
                ':oe_id' => $ordemExpedicaoId
            ]);
            $data['planejamento'] = $stmtPlanejamento->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $data['planejamento'] = []; // Carregamento sem OE
        }

        return $data;
    }

    /**
     * Exclui permanentemente um carregamento e todos os seus dados associados.
     * Reverte o estoque se estiver 'FINALIZADO'.
     * @param int $carregamentoId
     * @return bool
     * @throws Exception
     */
    public function excluir(int $carregamentoId): bool
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
                // (Esta lógica é idêntica à de 'reabrir', pois precisamos estornar o estoque)
                $itensParaEstornar = $this->getItensEstornaveis($carregamentoId);
                foreach ($itensParaEstornar as $item) {
                    $this->estornarEstoqueItem($item, "Exclusão do Carregamento Nº {$dadosAntigos['car_numero']}");
                }
            }

            // Deleta itens, filas e o header
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

    /* --- Funções de Apoio (Estorno) --- */

    private function getItensEstornaveis(int $carregamentoId): array
    {
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
        return $stmtItens->fetchAll(PDO::FETCH_ASSOC);
    }

    private function estornarEstoqueItem(array $item, string $observacao): void
    {
        // Devolve a quantidade para o lote de origem (SUBTRAI da qtd finalizada)
        $stmtUpdateItem = $this->pdo->prepare(
            "UPDATE tbl_lotes_novo_embalagem SET item_emb_qtd_finalizada = item_emb_qtd_finalizada - :qtd 
             WHERE item_emb_id = :id"
        );
        $stmtUpdateItem->execute([
            ':qtd' => $item['car_item_quantidade'],
            ':id' => $item['car_item_lote_novo_item_id']
        ]);

        // Cria o movimento de ENTRADA (estorno) no estoque
        $stmtEstoque = $this->pdo->prepare(
            "INSERT INTO tbl_estoque (estoque_produto_id, estoque_lote_item_id, estoque_quantidade, estoque_tipo_movimento, estoque_observacao) 
             VALUES (:prod_id, :lote_item_id, :qtd, 'ENTRADA POR ESTORNO', :obs)"
        );
        $stmtEstoque->execute([
            ':prod_id' => $item['item_emb_prod_sec_id'],
            ':lote_item_id' => $item['car_item_lote_novo_item_id'],
            ':qtd' => $item['car_item_quantidade'],
            ':obs' => $observacao
        ]);
    }

    /**
     * Remove todos os itens de um cliente específico de uma fila.
     */
    public function removeClienteFromFila(int $filaId, int $clienteId): bool
    {
        $sql = "DELETE FROM tbl_carregamento_itens 
                WHERE car_item_fila_id = :fila_id AND car_item_cliente_id = :cliente_id";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':fila_id' => $filaId,
            ':cliente_id' => $clienteId
        ]);

        if (!$success || $stmt->rowCount() == 0) {
            throw new \Exception("Nenhum item encontrado para este cliente nesta fila.");
        }

        // Adicionar log de auditoria
        $this->auditLogger->log('DELETE', $filaId, 'tbl_carregamento_itens', null, ['cliente_id_removido' => $clienteId]);

        return true;
    }

    /**
     * Busca os detalhes de um item de carregamento para edição.
     * Crucial: Calcula o saldo máximo que ele pode ter (baseado na OE ou Físico).
     */
    public function getCarregamentoItemDetalhes(int $carItemId): array
    {
        // 1. Busca os dados do item e o saldo MÁXIMO baseado na OE
        $sql = "SELECT 
                    ci.car_item_id,
                    ci.car_item_quantidade AS qtd_carregada,
                    ci.car_item_oei_id_origem, -- Precisamos disso para saber se é divergência
                    p.prod_descricao,
                    CONCAT(lnh.lote_completo_calculado, ' / ', ee.endereco_completo) AS lote_endereco,
                    
                    -- Lógica de Saldo (Baseada na OE):
                    -- (Total Planejado na OE) - (Total Carregado em OUTROS itens) + (Qtd deste item)
                    COALESCE(oei.oei_quantidade, 0) - 
                        COALESCE((SELECT SUM(outros_ci.car_item_quantidade) 
                                  FROM tbl_carregamento_itens outros_ci
                                  WHERE outros_ci.car_item_oei_id_origem = ci.car_item_oei_id_origem
                                  AND outros_ci.car_item_id != ci.car_item_id), 0)
                    AS max_quantidade_oe

                FROM tbl_carregamento_itens ci
                JOIN tbl_estoque_alocacoes ea ON ci.car_item_alocacao_id = ea.alocacao_id
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                LEFT JOIN tbl_ordens_expedicao_itens oei ON ci.car_item_oei_id_origem = oei.oei_id
                WHERE ci.car_item_id = :item_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':item_id' => $carItemId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            throw new \Exception("Item não encontrado.");
        }

        // 2. Decide qual saldo usar
        $isDivergencia = empty($data['car_item_oei_id_origem']);

        if ($isDivergencia) {
            // --- É DIVERGÊNCIA ---
            $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) 
                                  FROM tbl_ordens_expedicao_itens 
                                  WHERE oei_alocacao_id = ea.alocacao_id 
                                  AND oei_status = 'PENDENTE')";

            // Busca o saldo FÍSICO disponível no endereço
            $stmtSaldoFisico = $this->pdo->prepare(
                "SELECT (ea.alocacao_quantidade - {$subQueryReservado}) 
                 FROM tbl_estoque_alocacoes ea 
                 JOIN tbl_carregamento_itens ci ON ea.alocacao_id = ci.car_item_alocacao_id 
                 WHERE ci.car_item_id = :item_id"
            );
            $stmtSaldoFisico->execute([':item_id' => $carItemId]);
            $saldoFisicoDisponivel = $stmtSaldoFisico->fetchColumn() ?: 0;

            // O máximo que o usuário pode setar é o que está disponível + o que ele já tem
            $data['max_quantidade_disponivel'] = $saldoFisicoDisponivel + $data['qtd_carregada'];

        } else {
            // --- É ITEM DA OE ---
            $data['max_quantidade_disponivel'] = $data['max_quantidade_oe'] + $data['qtd_carregada'];
        }

        // Limpa o campo desnecessário
        unset($data['max_quantidade_oe']);

        return $data;
    }

    /**
     * Atualiza a quantidade de um item de carregamento.
     */
    public function updateCarregamentoItemQuantidade(int $carItemId, float $novaQuantidade): bool
    {
        // Pega os detalhes (incluindo o saldo máximo)
        $detalhes = $this->getCarregamentoItemDetalhes($carItemId);

        if ($novaQuantidade <= 0) {
            throw new \Exception("A quantidade deve ser maior que zero.");
        }

        if ($novaQuantidade > $detalhes['max_quantidade_disponivel']) {
            throw new \Exception("A quantidade ({$novaQuantidade}) excede o saldo disponível ({$detalhes['max_quantidade_disponivel']}).");
        }

        $sql = "UPDATE tbl_carregamento_itens SET car_item_quantidade = :qtd WHERE car_item_id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':qtd' => $novaQuantidade, ':id' => $carItemId]);
    }

    /**
     * Adiciona um item (planejado ou divergente) ao carregamento.
     * 
     */
    public function addItemCarregamento(array $data, int $carregamentoId): int
    {
        // 1. Coleta e Validação de Dados (sem alteração)
        $filaId = filter_var($data['item_fila_id'], FILTER_VALIDATE_INT);
        $clienteId = filter_var($data['item_cliente_id'], FILTER_VALIDATE_INT);
        $alocacaoId = filter_var($data['item_alocacao_id'], FILTER_VALIDATE_INT);
        $quantidadeAdicionada = filter_var($data['item_quantidade'], FILTER_VALIDATE_FLOAT);
        $motivo = trim($data['item_motivo_divergencia'] ?? '');
        $oeiIdOrigem = filter_var($data['item_oei_id_origem'], FILTER_VALIDATE_INT);

        if (!$filaId || !$clienteId || !$alocacaoId || !$quantidadeAdicionada || $quantidadeAdicionada <= 0) {
            throw new \Exception("Dados inválidos. Cliente, Alocação e Quantidade são obrigatórios.");
        }
        if (empty($motivo) && !$oeiIdOrigem) {
            throw new \Exception("Item não planejado (divergência) requer um motivo obrigatório.");
        }

        // 2. Verifica se um item idêntico já existe na fila
        $stmtCheck = $this->pdo->prepare(
            "SELECT car_item_id, car_item_quantidade 
             FROM tbl_carregamento_itens 
             WHERE car_item_fila_id = :fila_id 
               AND car_item_cliente_id = :cliente_id 
               AND car_item_alocacao_id = :alocacao_id 
             LIMIT 1"
        );
        $stmtCheck->execute([':fila_id' => $filaId, ':cliente_id' => $clienteId, ':alocacao_id' => $alocacaoId]);
        $itemExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        // 3. Calcula a quantidade TOTAL a ser validada
        $quantidadeTotalParaValidar = $quantidadeAdicionada;
        if ($itemExistente) {
            $quantidadeTotalParaValidar = $itemExistente['car_item_quantidade'] + $quantidadeAdicionada;
        }

        // 4. VALIDAÇÃO DE SALDO (Executada apenas UMA VEZ)
        $isDivergencia = empty($oeiIdOrigem);
        $saldoDisponivelParaOperacao = 0;
        $tipoDeSaldo = "";

        if ($isDivergencia) {
            $querySaldo = "SELECT ea.alocacao_quantidade as saldo FROM tbl_estoque_alocacoes ea WHERE ea.alocacao_id = :alocacao_id";
            $stmtSaldo = $this->pdo->prepare($querySaldo);
            $stmtSaldo->execute([':alocacao_id' => $alocacaoId]);
            $saldoDisponivelParaOperacao = $stmtSaldo->fetchColumn();
            $tipoDeSaldo = "FÍSICO";
        } else {
            $tipoDeSaldo = "planejado na OE";
            $stmtPlanejado = $this->pdo->prepare("SELECT oei_quantidade FROM tbl_ordens_expedicao_itens WHERE oei_id = :oei_id");
            $stmtPlanejado->execute([':oei_id' => $oeiIdOrigem]);
            $saldoDisponivelParaOperacao = (float) $stmtPlanejado->fetchColumn();
        }

        if ($saldoDisponivelParaOperacao === false || $saldoDisponivelParaOperacao < $quantidadeTotalParaValidar) {
            $saldoFormatado = number_format($saldoDisponivelParaOperacao, 3, ',', '.');
            throw new \Exception("Quantidade total ({$quantidadeTotalParaValidar}) excede o saldo {$tipoDeSaldo} ({$saldoFormatado}) no endereço.");
        }

        // 5. Executa a Ação no Banco de Dados (UPDATE ou INSERT)
        if ($itemExistente) {
            // --- Ação: ATUALIZAR ---
            $stmtUpdate = $this->pdo->prepare("UPDATE tbl_carregamento_itens SET car_item_quantidade = :nova_qtd WHERE car_item_id = :item_id");
            $stmtUpdate->execute([':nova_qtd' => $quantidadeTotalParaValidar, ':item_id' => $itemExistente['car_item_id']]);

            $this->auditLogger->log('UPDATE', $itemExistente['car_item_id'], 'tbl_carregamento_itens', $itemExistente, ['car_item_quantidade' => $quantidadeTotalParaValidar]);
            return (int) $itemExistente['car_item_id'];

        } else {
            // --- Ação: INSERIR ---
            $stmtLote = $this->pdo->prepare("SELECT alocacao_lote_item_id FROM tbl_estoque_alocacoes WHERE alocacao_id = :id");
            $stmtLote->execute([':id' => $alocacaoId]);
            $loteNovoItemId = $stmtLote->fetchColumn();
            if (!$loteNovoItemId)
                throw new \Exception("Lote correspondente à alocação não encontrado.");

            $sql = "INSERT INTO tbl_carregamento_itens (car_item_carregamento_id, car_item_fila_id, car_item_cliente_id, car_item_lote_novo_item_id, car_item_alocacao_id, car_item_quantidade, car_item_oei_id_origem, car_item_motivo_divergencia)
                    VALUES (:car_id, :fila_id, :cliente_id, :lote_item_id, :alocacao_id, :quantidade, :oei_id, :motivo)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':car_id' => $carregamentoId,
                ':fila_id' => $filaId,
                ':cliente_id' => $clienteId,
                ':lote_item_id' => $loteNovoItemId,
                ':alocacao_id' => $alocacaoId,
                ':quantidade' => $quantidadeAdicionada,
                ':oei_id' => $oeiIdOrigem ?: null,
                ':motivo' => !empty($motivo) ? $motivo : null
            ]);
            $newId = (int) $this->pdo->lastInsertId();
            $this->auditLogger->log('CREATE', $newId, 'tbl_carregamento_itens', null, $data);
            return $newId;
        }
    }

    /**
     * Busca endereços por LOTE e PRODUTO.
     */
    public function getEnderecosParaCarregamentoPorLoteItem(int $loteId, int $produtoId): array
    {
        $subQueryReservado = "(SELECT COALESCE(SUM(oei_quantidade), 0) FROM tbl_ordens_expedicao_itens WHERE oei_alocacao_id = ea.alocacao_id AND oei_status = 'PENDENTE')";

        $sql = "SELECT 
                    ea.alocacao_id AS id,
                    CONCAT(ee.endereco_completo, ' [Saldo Físico: ', FORMAT(ea.alocacao_quantidade, 3, 'de_DE'), ']') as text,
                    (ea.alocacao_quantidade - {$subQueryReservado}) as saldo_disponivel,
                    ea.alocacao_quantidade AS saldo_fisico
                FROM tbl_estoque_alocacoes ea
                JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                
                WHERE lne.item_emb_lote_id = :lote_id
                AND lne.item_emb_prod_sec_id = :produto_id
                AND ea.alocacao_quantidade > 0";

        $stmt = $this->pdo->prepare($sql);
        // ### MUDANÇA: Passa os dois parâmetros ###
        $stmt->execute([':lote_id' => $loteId, ':produto_id' => $produtoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca lotes (NÚMEROS ÚNICOS) por produto para o Carregamento.
     * Filtra por Estoque FÍSICO (> 0).
     */
    public function getLotesParaCarregamentoPorProduto(int $produtoId): array
    {
        $sql = "SELECT DISTINCT
                    lnh.lote_id AS id, -- <-- MUDANÇA: O ID agora é o 'lote_id'
                    lnh.lote_completo_calculado AS text -- <-- MUDANÇA: O texto é SÓ o lote
                FROM tbl_estoque_alocacoes ea
                JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                WHERE lne.item_emb_prod_sec_id = :produto_id
                AND ea.alocacao_quantidade > 0 -- Só mostra lotes com estoque físico
                GROUP BY lnh.lote_id, lnh.lote_completo_calculado
                ORDER BY lnh.lote_completo_calculado";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':produto_id' => $produtoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Salva UMA OU MAIS fotos enviadas por upload para uma fila específica.
     */
    public function addFotos(array $postData, array $fileData): int
    {
        $filaId = filter_var($postData['foto_fila_id'], FILTER_VALIDATE_INT);
        if (!$filaId) {
            throw new \Exception("ID da fila inválido.");
        }

        // 1. Validação inicial do array de arquivos
        if (!isset($fileData['foto_upload']) || !is_array($fileData['foto_upload']['name'])) {
            throw new \Exception("Nenhum arquivo enviado ou formato de dados incorreto.");
        }

        $this->pdo->beginTransaction();
        try {
            $successCount = 0;
            $totalFiles = count($fileData['foto_upload']['name']);

            for ($i = 0; $i < $totalFiles; $i++) {
                // Pula se houver algum erro de upload para este arquivo específico
                if ($fileData['foto_upload']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $fileTmpName = $fileData['foto_upload']['tmp_name'][$i];

                // 2. Validação de Segurança (Tipo e Tamanho)
                $allowedTypes = ['image/jpeg', 'image/png'];
                if (!in_array(mime_content_type($fileTmpName), $allowedTypes)) {
                    // Pula este arquivo, mas continua com os outros
                    continue;
                }
                if ($fileData['foto_upload']['size'][$i] > 5 * 1024 * 1024) { // 5 MB
                    continue;
                }

                // 3. Mover o Arquivo
                $uploadDir = __DIR__ . '/../../public/uploads/carregamentos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $ext = pathinfo($fileData['foto_upload']['name'][$i], PATHINFO_EXTENSION);
                $newFileName = "fila_{$filaId}_" . time() . "_{$i}." . $ext; // Adiciona o índice para garantir nome único
                $destination = $uploadDir . $newFileName;
                $publicPath = "uploads/carregamentos/" . $newFileName;

                if (move_uploaded_file($fileTmpName, $destination)) {
                    // 4. Salvar no Banco
                    $sql = "INSERT INTO tbl_carregamento_fila_fotos (foto_fila_id, foto_path) VALUES (:fila_id, :path)";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([':fila_id' => $filaId, ':path' => $publicPath]);
                    $successCount++;
                }
            }

            $this->pdo->commit();

            if ($successCount == 0 && $totalFiles > 0) {
                throw new \Exception("Nenhuma das fotos enviadas era válida (verifique o tipo ou tamanho).");
            }

            return $successCount;

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e; // Re-lança a exceção para o controller
        }
    }

    /**
     * Busca os caminhos das fotos associadas a uma fila.
     * Chamada pela rota 'getFotosDaFila' do ajax_router.
     */
    public function findFotosByFilaId(int $filaId): array
    {
        $sql = "SELECT foto_path FROM tbl_carregamento_fila_fotos 
                WHERE foto_fila_id = :fila_id 
                ORDER BY foto_timestamp DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fila_id' => $filaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compara o planejado (OE) com o executado (Carregamento) e retorna um resumo.
     */
    public function getResumoParaFinalizacao(int $carregamentoId): array
    {
        $detalhes = $this->getDetalhesCompletos($carregamentoId);
        $planejado = $detalhes['planejamento'] ?? [];
        $executado = [];
        foreach ($detalhes['execucao'] as $fila) {
            $executado = array_merge($executado, $fila['itens']);
        }

        $resumo = [];
        // Mapeia os itens por uma chave única (alocacao_id) para fácil comparação
        $mapaPlanejado = [];
        foreach ($planejado as $item) {
            $mapaPlanejado[$item['oei_alocacao_id']] = $item;
        }

        $mapaExecutado = [];
        foreach ($executado as $item) {
            $chave = $item['car_item_alocacao_id'];
            if (!isset($mapaExecutado[$chave])) {
                $mapaExecutado[$chave] = $item;
            } else {
                $mapaExecutado[$chave]['qtd_carregada'] += $item['qtd_carregada'];
            }
        }

        // Compara os dois mapas
        foreach ($mapaPlanejado as $alocacaoId => $itemP) {
            if (!isset($mapaExecutado[$alocacaoId])) {
                $itemP['status_divergencia'] = 'NÃO CARREGADO';
                $resumo[] = $itemP;
            } else {
                $itemE = $mapaExecutado[$alocacaoId];
                if ($itemP['qtd_planejada'] != $itemE['qtd_carregada']) {
                    $itemP['status_divergencia'] = 'QUANTIDADE DIVERGENTE';
                    $itemP['qtd_carregada'] = $itemE['qtd_carregada']; // Atualiza com o valor real
                    $resumo[] = $itemP;
                }
                unset($mapaExecutado[$alocacaoId]);
            }
        }

        foreach ($mapaExecutado as $alocacaoId => $itemE) {
            $itemE['status_divergencia'] = 'ITEM NÃO PLANEJADO';
            $resumo[] = $itemE;
        }

        return $resumo;
    }

    /**
     * Conta o número de carregamentos criados na data de hoje.
     * Usado pelo KPI no Dashboard.
     */
    public function countForToday(): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(car_id) FROM tbl_carregamentos WHERE car_data = CURDATE()");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca todos os dados de um carregamento para a geração de relatório.
     * Retorna um array estruturado com header, filas, itens e fotos.
     */
    public function getDadosCompletosParaRelatorio(int $carregamentoId): array
    {
        $dados = [
            'header' => null,
            'filas' => []
        ];

        // 1. Busca o cabeçalho do Carregamento
        $stmtHeader = $this->pdo->prepare(
            "SELECT c.*, oeh.oe_numero 
             FROM tbl_carregamentos c 
             LEFT JOIN tbl_ordens_expedicao_header oeh ON c.car_ordem_expedicao_id = oeh.oe_id
             WHERE c.car_id = :id"
        );
        $stmtHeader->execute([':id' => $carregamentoId]);
        $dados['header'] = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$dados['header']) {
            return []; // Retorna vazio se não encontrar o carregamento
        }

        // 2. Busca todas as Filas, Itens e Fotos de uma vez
        $sqlItens = "SELECT 
                        f.fila_id, f.fila_numero_sequencial,
                        foto.foto_path,
                        ci.car_item_id,
                        COALESCE(e.ent_nome_fantasia, e.ent_razao_social) AS cliente_nome,
                        p.prod_codigo_interno, p.prod_descricao,
                        lnh.lote_completo_calculado AS lote_completo,
                        COALESCE(ent_lote.ent_nome_fantasia, ent_lote.ent_razao_social) AS cliente_lote_nome,
                        ee.endereco_completo,
                        ci.car_item_quantidade AS qtd_carregada,
                        ci.car_item_motivo_divergencia
                     FROM tbl_carregamento_filas f
                     LEFT JOIN tbl_carregamento_itens ci ON f.fila_id = ci.car_item_fila_id
                     LEFT JOIN tbl_carregamento_fila_fotos foto ON f.fila_id = foto.foto_fila_id
                     LEFT JOIN tbl_entidades e ON ci.car_item_cliente_id = e.ent_codigo
                     LEFT JOIN tbl_estoque_alocacoes ea ON ci.car_item_alocacao_id = ea.alocacao_id
                     LEFT JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
                     LEFT JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                     LEFT JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                     LEFT JOIN tbl_estoque_enderecos ee ON ea.alocacao_endereco_id = ee.endereco_id
                     LEFT JOIN tbl_entidades ent_lote ON lnh.lote_cliente_id = ent_lote.ent_codigo
                     WHERE f.fila_carregamento_id = :id
                     ORDER BY f.fila_numero_sequencial, e.ent_nome_fantasia, p.prod_descricao";

        $stmtItens = $this->pdo->prepare($sqlItens);
        $stmtItens->execute([':id' => $carregamentoId]);
        $results = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        // 3. Organiza os dados em uma estrutura hierárquica
        $filasProcessadas = [];
        foreach ($results as $row) {
            $filaId = $row['fila_id'];
            if (!isset($filasProcessadas[$filaId])) {
                $filasProcessadas[$filaId] = [
                    'fila_numero' => $row['fila_numero_sequencial'],
                    'itens' => [],
                    'fotos' => []
                ];
            }
            // Adiciona item se ele existir (evita duplicatas de LEFT JOIN)
            if ($row['car_item_id'] && !isset($filasProcessadas[$filaId]['itens'][$row['car_item_id']])) {
                $filasProcessadas[$filaId]['itens'][$row['car_item_id']] = $row;
            }
            // Adiciona foto se ela existir (evita duplicatas)
            if ($row['foto_path'] && !in_array($row['foto_path'], $filasProcessadas[$filaId]['fotos'])) {
                $filasProcessadas[$filaId]['fotos'][] = $row['foto_path'];
            }
        }
        $dados['filas'] = array_values($filasProcessadas); // Reindexa o array

        return $dados;
    }

    /**
     * Busca os carregamentos em aberto mais antigos.
     * @param int $limit O número máximo de carregamentos a retornar.
     * @return array
     */
    public function findOpenShipments(int $limit = 5): array
    {
        $sql = "SELECT c.car_id, c.car_numero, c.car_data, c.car_status, e.ent_razao_social
            FROM tbl_carregamentos c
            LEFT JOIN tbl_entidades e ON c.car_entidade_id_organizador = e.ent_codigo 
            WHERE c.car_status NOT IN ('FINALIZADO', 'CANCELADO')
            ORDER BY c.car_data ASC
            LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}