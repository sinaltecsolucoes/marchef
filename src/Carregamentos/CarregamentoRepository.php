<?php
// /src/Carregamentos/CarregamentoRepository.php
namespace App\Carregamentos;

use PDO;
use Exception; // Adicionado para o 'catch'
use App\Core\AuditLoggerService; // Adicionado para a auditoria


class CarregamentoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Calcula o próximo número sequencial para um novo carregamento.
     */
    public function getNextNumeroCarregamento(): string
    {
        // Busca o maior valor numérico na coluna car_numero
        $stmt = $this->pdo->query("SELECT MAX(CAST(car_numero AS UNSIGNED)) FROM tbl_carregamentos");
        $ultimoNumero = $stmt->fetchColumn() ?: 0;
        $proximoNumero = $ultimoNumero + 1;

        // Formata com 4 dígitos à esquerda, ex: 0001, 0057, etc.
        return str_pad($proximoNumero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cria um novo registro de cabeçalho de carregamento.
     */
    public function createHeader(array $data, int $userId): int
    {
        // A query agora insere apenas os dados que vêm do formulário.
        // O status será 'EM ANDAMENTO' por defeito do banco de dados.
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
            ':hora_inicio' => $data['car_hora_inicio'] ?? null, // Futuramente podemos adicionar este campo ao modal
            ':ordem_expedicao' => $data['car_ordem_expedicao'] ?? null,
            ':user_id' => $userId
        ]);

        $novoId = (int) $this->pdo->lastInsertId();

        // AUDITORIA: Registar a criação do cabeçalho do carregamento
        if ($novoId > 0) {
            // Prepara os dados que foram realmente inseridos para o log
            $dadosLog = $data;
            $dadosLog['car_status'] = 'EM ANDAMENTO'; // Adiciona o status padrão ao log
            $this->auditLogger->log('CREATE', $novoId, 'tbl_carregamentos', null, $dadosLog);
        }

        return $novoId;
    }

    /**
     * Cria uma nova fila e salva um lote de leituras de QR Code.
     * Usa uma transação para garantir a integridade dos dados.
     */
    public function createFilaWithLeituras(int $carregamentoId, int $clienteId, array $leituras): int
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Inserir o registro da fila na tabela 'tbl_carregamento_filas'
            $sqlFila = "INSERT INTO tbl_carregamento_filas (fila_carregamento_id, fila_entidade_id_cliente) VALUES (:car_id, :cli_id)";
            $stmtFila = $this->pdo->prepare($sqlFila);
            $stmtFila->execute([
                ':car_id' => $carregamentoId,
                ':cli_id' => $clienteId
            ]);
            $filaId = (int) $this->pdo->lastInsertId();

            // 2. Preparar a inserção para as leituras
            $sqlLeitura = "INSERT INTO tbl_carregamento_leituras (leitura_fila_id, leitura_qrcode_conteudo) VALUES (:fila_id, :conteudo)";
            $stmtLeitura = $this->pdo->prepare($sqlLeitura);

            // 3. Inserir cada leitura da lista na tabela 'tbl_carregamento_leituras'
            foreach ($leituras as $conteudoQr) {
                $stmtLeitura->execute([
                    ':fila_id' => $filaId,
                    ':conteudo' => $conteudoQr
                ]);
            }

            // AUDITORIA: Registar a criação da fila. O log é feito dentro da transação.
            if ($filaId > 0) {
                $dadosNovosFila = [
                    'fila_carregamento_id' => $carregamentoId,
                    'fila_entidade_id_cliente' => $clienteId,
                    'quantidade_leituras' => count($leituras)
                ];
                $this->auditLogger->log('CREATE', $filaId, 'tbl_carregamento_filas', null, $dadosNovosFila);
            }

            // 4. Se tudo deu certo, confirma todas as operações no banco de dados
            $this->pdo->commit();
            return $filaId;

        } catch (\Exception $e) {
            // 5. Se qualquer passo falhar, desfaz todas as operações
            $this->pdo->rollBack();
            // Re-lança a exceção para que a API possa reportar o erro
            throw $e;
        }
    }

    /**
     * Adiciona um item a um carregamento ou atualiza a sua quantidade.
     * (Versão Simplificada - sem verificação de estoque)
     *
     * @param int $carregamentoId O ID do carregamento.
     * @param int $loteItemId O ID do item do lote (tbl_lote_itens).
     * @param float $quantidade A quantidade a ser adicionada.
     * @return int O ID do item de carregamento (novo ou existente).
     * @throws Exception
     */
    public function adicionarItem(int $carregamentoId, int $loteItemId, float $quantidade): int
    {
        // 1. Verificar se este item de lote já foi adicionado a este carregamento
        $itemExistente = $this->findCarregamentoItem($carregamentoId, $loteItemId);

        if ($itemExistente) {
            // Se já existe, ATUALIZA a quantidade (soma a nova quantidade)
            $id = (int) $itemExistente['car_item_id'];
            $novaQuantidade = (float) $itemExistente['car_item_quantidade'] + $quantidade;
            $dadosAntigos = $itemExistente;

            $sql = "UPDATE tbl_carregamento_itens SET car_item_quantidade = :qtd WHERE car_item_id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':qtd' => $novaQuantidade, ':id' => $id]);

            $this->auditLogger->log('UPDATE', $id, 'tbl_carregamento_itens', $dadosAntigos, ['car_item_quantidade' => $novaQuantidade]);
            return $id;

        } else {
            // Se não existe, INSERE um novo registo
            $dadosNovos = [
                'car_item_carregamento_id' => $carregamentoId,
                'car_item_lote_item_id' => $loteItemId,
                'car_item_quantidade' => $quantidade
            ];

            $sql = "INSERT INTO tbl_carregamento_itens (car_item_carregamento_id, car_item_lote_item_id, car_item_quantidade) VALUES (:car_id, :lote_item_id, :qtd)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':car_id' => $carregamentoId,
                ':lote_item_id' => $loteItemId,
                ':qtd' => $quantidade
            ]);
            $novoId = (int) $this->pdo->lastInsertId();
            $this->auditLogger->log('CREATE', $novoId, 'tbl_carregamento_itens', null, $dadosNovos);
            return $novoId;
        }
    }

    /**
     * Função de apoio para encontrar um item específico dentro de um carregamento.
     */
    private function findCarregamentoItem(int $carregamentoId, int $loteItemId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tbl_carregamento_itens 
             WHERE car_item_carregamento_id = :car_id AND car_item_lote_item_id = :lote_item_id"
        );
        $stmt->execute([':car_id' => $carregamentoId, ':lote_item_id' => $loteItemId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca dados de carregamentos para o DataTables com paginação, busca e filtro por status.
     *
     * @param array $params Parâmetros vindos do DataTables e dos nossos filtros.
     * @return array
     */
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

        // --- Construção da Cláusula WHERE e Parâmetros ---
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

        // --- Contagem de Registros Filtrados ---
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(c.car_id) $baseQuery $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // --- Busca dos Dados da Página Atual ---
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
     * Marca um carregamento como 'AGUARDANDO CONFERENCIA'.
     * Esta é a ação intermediária acionada pelo aplicativo ou pela criação inicial na web.
     * A baixa de estoque real ocorrerá numa outra função, após a conferência do gestor.
     *
     * @param int $carregamentoId O ID do carregamento a ser finalizado.
     * @return bool
     * @throws Exception
     */
    public function finalize(int $carregamentoId): bool
    {
        // PASSO 1 DE AUDITORIA: Buscar dados antigos ANTES de atualizar
        $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamentos WHERE car_id = :id");
        $stmtAntigo->execute([':id' => $carregamentoId]);
        $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

        if (!$dadosAntigos) {
            throw new Exception("Carregamento com ID {$carregamentoId} não encontrado.");
        }

        // Regra de negócio: só pode "enviar para conferência" se estiver 'EM ANDAMENTO'
        if ($dadosAntigos['car_status'] !== 'EM ANDAMENTO') {
            throw new Exception("Este carregamento já foi processado e não pode ser alterado.");
        }

        // Ação principal: Mudar o status para o nosso novo estado intermediário
        $novoStatus = 'AGUARDANDO CONFERENCIA';
        $sql = "UPDATE tbl_carregamentos SET car_status = :status WHERE car_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':status' => $novoStatus, ':id' => $carregamentoId]);

        $success = $stmt->rowCount() > 0;

        // PASSO 2 DE AUDITORIA: Se a atualização foi bem-sucedida, registar o log
        if ($success) {
            $dadosNovos = $dadosAntigos;
            $dadosNovos['car_status'] = $novoStatus;
            $this->auditLogger->log('UPDATE_STATUS', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, $dadosNovos);
        }

        return $success;
    }

    /**
     * Busca um carregamento com todos os seus itens e detalhes dos produtos.
     *
     * @param int $carregamentoId O ID do carregamento.
     * @return array|null Os dados do carregamento e seus itens.
     */
    public function findCarregamentoComItens(int $carregamentoId): ?array
    {
        // 1. Busca o cabeçalho do carregamento
        $stmtHeader = $this->pdo->prepare(
            "SELECT c.*, e.ent_razao_social 
             FROM tbl_carregamentos c
             LEFT JOIN tbl_entidades e ON c.car_entidade_id_organizador = e.ent_codigo
             WHERE c.car_id = :id"
        );
        $stmtHeader->execute([':id' => $carregamentoId]);
        $header = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            return null; // Retorna nulo se o carregamento não for encontrado
        }

        // 2. Busca os itens associados a este carregamento
        $stmtItens = $this->pdo->prepare(
            "SELECT ci.*, p.prod_descricao, li.lote_num_completo
             FROM tbl_carregamento_itens ci
             JOIN tbl_lote_itens li ON ci.car_item_lote_item_id = li.item_id
             JOIN tbl_produtos p ON li.item_produto_id = p.prod_codigo
             WHERE ci.car_item_carregamento_id = :id
             ORDER BY ci.car_item_id DESC"
        );
        $stmtItens->execute([':id' => $carregamentoId]);
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        return ['header' => $header, 'itens' => $itens];
    }
}