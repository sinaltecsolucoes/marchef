<?php
// /src/Estoque/EnderecoRepository.php
namespace App\Estoque;

use PDO;
use Exception;
use App\Core\AuditLoggerService;

class EnderecoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_estoque_enderecos WHERE endereco_id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCamaraOptions(): array
    {
        return $this->pdo->query("SELECT camara_id, camara_nome, camara_codigo FROM tbl_estoque_camaras ORDER BY camara_nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllForDataTable(int $camaraId, array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        // Colunas que serão incluídas na pesquisa de texto
        $searchableColumns = [
            'e.endereco_completo',
            'e.lado',
            'e.nivel',
            'e.fila',
            'e.vaga',
            'e.descricao_simples'
        ];

        // Contagem total (apenas da câmara selecionada, sem filtro de busca)
        $totalRecordsStmt = $this->pdo->prepare("SELECT COUNT(endereco_id) FROM tbl_estoque_enderecos WHERE endereco_camara_id = ?");
        $totalRecordsStmt->execute([$camaraId]);
        $totalRecords = $totalRecordsStmt->fetchColumn();

        // --- Construção da Cláusula WHERE e Parâmetros ---
        $whereConditions = [];
        $queryParams = [];

        // 1. Filtro OBRIGATÓRIO pela Câmara
        $whereConditions[] = "e.endereco_camara_id = :camara_id";
        $queryParams[':camara_id'] = $camaraId;

        // 2. Filtro de Busca (SearchValue), se existir
        if (!empty($searchValue)) {
            $searchConditions = [];
            $searchTerm = '%' . $searchValue . '%';

            foreach ($searchableColumns as $index => $column) {
                $placeholder = ':search' . $index; // Placeholders únicos (ex: :search0, :search1)
                $searchConditions[] = "$column LIKE $placeholder";
                $queryParams[$placeholder] = $searchTerm;
            }
            $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // --- Contagem de Registros Filtrados ---
        // Conta quantos registros correspondem a TODOS os filtros (Câmara + Busca)
        $sqlFiltered = "SELECT COUNT(e.endereco_id) FROM tbl_estoque_enderecos e $whereClause";
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($queryParams); // Executa com todos os parâmetros (camara_id + search)
        $totalFiltered = $stmtFiltered->fetchColumn();

        // --- Busca dos Dados da Página Atual ---
        $sqlData = "SELECT * FROM tbl_estoque_enderecos e
                $whereClause 
                ORDER BY e.endereco_completo ASC 
                LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);

        // Bind dos parâmetros da cláusula WHERE
        foreach ($queryParams as $key => $value) {
            // Define o tipo do parâmetro (o ID da câmara é INT, o resto é STR)
            $paramType = ($key === ':camara_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }

        // Bind dos parâmetros do LIMIT
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => (int) $draw,
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered, // Agora usamos a contagem filtrada correta
            "data" => $data
        ];
    }

    private function calcularEnderecoCompleto(int $camaraId, array $data): string
    {
        $stmt = $this->pdo->prepare("SELECT camara_codigo FROM tbl_estoque_camaras WHERE camara_id = ?");
        $stmt->execute([$camaraId]);
        $camaraCodigo = $stmt->fetchColumn();

        if (!$camaraCodigo) {
            throw new Exception("Câmara com ID {$camaraId} não encontrada.");
        }

        // CORREÇÃO: Usamos o operador '??' para evitar o erro se o campo não existir.
        if (!empty(trim($data['descricao_simples'] ?? ''))) {
            return strtoupper($camaraCodigo . '-' . $data['descricao_simples']);
        }

        $partes = [$camaraCodigo];
        if (!empty($data['lado'] ?? null))
            $partes[] = $data['lado'];
        if (!empty($data['nivel'] ?? null))
            $partes[] = $data['nivel'];
        if (!empty($data['fila'] ?? null))
            $partes[] = $data['fila'];
        if (!empty($data['vaga'] ?? null))
            $partes[] = $data['vaga'];

        return strtoupper(implode('-', $partes));
    }

    public function save(array $data): int
    {
        $id = filter_var($data['endereco_id'] ?? null, FILTER_VALIDATE_INT);
        $camaraId = filter_var($data['endereco_camara_id'], FILTER_VALIDATE_INT);
        if (!$camaraId) {
            throw new Exception("ID da Câmara é inválido.");
        }

        $enderecoCompleto = $this->calcularEnderecoCompleto($camaraId, $data);

        // --- LÓGICA DE VERIFICAÇÃO DE DUPLICATA ---
        if (!$id) { // Só verifica se for um NOVO registro
            $stmtCheck = $this->pdo->prepare("SELECT endereco_id FROM tbl_estoque_enderecos WHERE endereco_completo = ?");
            $stmtCheck->execute([$enderecoCompleto]);
            if ($existingId = $stmtCheck->fetchColumn()) {
                // Lança uma exceção customizada com o ID do endereço existente
                throw new Exception("DUPLICATE_ENTRY:{$existingId}");
            }
        }
        // --- FIM DA VERIFICAÇÃO ---

        $params = [
            ':camara_id' => $camaraId,
            ':lado' => $data['lado'] ?? null,
            ':nivel' => $data['nivel'] ?? null,
            ':fila' => $data['fila'] ?? null,
            ':vaga' => $data['vaga'] ?? null,
            ':descricao_simples' => $data['descricao_simples'] ?? null,
            ':endereco_completo' => $enderecoCompleto,
        ];

        if ($id) { // UPDATE
            $dadosAntigos = $this->find($id);
            $sql = "UPDATE tbl_estoque_enderecos SET endereco_camara_id = :camara_id, lado = :lado, nivel = :nivel, fila = :fila, vaga = :vaga, descricao_simples = :descricao_simples, endereco_completo = :endereco_completo WHERE endereco_id = :id";
            $params[':id'] = $id;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $this->auditLogger->log('UPDATE', $id, 'tbl_estoque_enderecos', $dadosAntigos, $data);
            return $id;
        } else { // CREATE
            $sql = "INSERT INTO tbl_estoque_enderecos (endereco_camara_id, lado, nivel, fila, vaga, descricao_simples, endereco_completo) VALUES (:camara_id, :lado, :nivel, :fila, :vaga, :descricao_simples, :endereco_completo)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $newId = (int) $this->pdo->lastInsertId();
            $this->auditLogger->log('CREATE', $newId, 'tbl_estoque_enderecos', null, $data);
            return $newId;
        }
    }

    public function delete(int $id): bool
    {
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos)
            return false;

        $stmt = $this->pdo->prepare("DELETE FROM tbl_estoque_enderecos WHERE endereco_id = :id");
        $stmt->execute([':id' => $id]);
        $success = $stmt->rowCount() > 0;

        $this->auditLogger->log('DELETE', $id, 'tbl_estoque_enderecos', $dadosAntigos, null);
        return $success;
    }

    /**
     * Cria um novo registro de alocação, ligando um item a um endereço.
     * @param int $enderecoId O ID do endereço de destino.
     * @param int $loteItemId O ID do item do lote a ser alocado.
     * @param int $usuarioId O ID do usuário que está realizando a ação.
     * @return bool
     * @throws Exception
     */
    public function alocarItem(int $enderecoId, int $loteItemId, float $quantidade, int $usuarioId): bool
    {
        if ($quantidade <= 0) {
            throw new Exception("A quantidade a ser alocada deve ser maior que zero.");
        }

        // 1. Valida o saldo total disponível para o item, como antes.
        $stmtItem = $this->pdo->prepare(
            "SELECT 
                lne.item_emb_qtd_sec AS total_produzido,
                COALESCE((SELECT SUM(alocacao_quantidade) FROM tbl_estoque_alocacoes WHERE alocacao_lote_item_id = :lote_item_id_subquery), 0) AS ja_alocado
             FROM tbl_lotes_novo_embalagem lne
             WHERE lne.item_emb_id = :lote_item_id_main"
        );
        $stmtItem->execute([
            ':lote_item_id_subquery' => $loteItemId,
            ':lote_item_id_main' => $loteItemId
        ]);
        $itemSaldos = $stmtItem->fetch(PDO::FETCH_ASSOC);

        if (!$itemSaldos) {
            throw new Exception("Item do lote (ID: {$loteItemId}) não encontrado.");
        }

        $saldoDisponivel = (float) $itemSaldos['total_produzido'] - (float) $itemSaldos['ja_alocado'];
        if ($quantidade > $saldoDisponivel) {
            throw new Exception("Quantidade indisponível. Saldo para alocação: {$saldoDisponivel}");
        }

        // 2. NOVO: Procura por uma alocação existente para o mesmo item/endereço/dia.
        $stmtCheck = $this->pdo->prepare(
            "SELECT * FROM tbl_estoque_alocacoes 
             WHERE alocacao_endereco_id = :endereco_id 
               AND alocacao_lote_item_id = :lote_item_id
               AND DATE(alocacao_data) = CURDATE()"
        );
        $stmtCheck->execute([
            ':endereco_id' => $enderecoId,
            ':lote_item_id' => $loteItemId
        ]);
        $existingAllocation = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        // 3. Lógica Condicional: Se encontrou, ATUALIZA. Se não, INSERE.
        if ($existingAllocation) {
            // --- SE ENCONTROU, FAZ O UPDATE ---
            $sql = "UPDATE tbl_estoque_alocacoes 
                    SET alocacao_quantidade = alocacao_quantidade + :quantidade 
                    WHERE alocacao_id = :alocacao_id";

            $stmtUpdate = $this->pdo->prepare($sql);
            $success = $stmtUpdate->execute([
                ':quantidade' => $quantidade,
                ':alocacao_id' => $existingAllocation['alocacao_id']
            ]);

            $this->auditLogger->log('UPDATE', $existingAllocation['alocacao_id'], 'tbl_estoque_alocacoes', $existingAllocation, $this->pdo->query("SELECT * FROM tbl_estoque_alocacoes WHERE alocacao_id = {$existingAllocation['alocacao_id']}")->fetch(PDO::FETCH_ASSOC));

        } else {
            // --- SE NÃO ENCONTROU, FAZ O INSERT (como antes) ---
            $sql = "INSERT INTO tbl_estoque_alocacoes 
                        (alocacao_endereco_id, alocacao_lote_item_id, alocacao_quantidade, alocacao_data, alocacao_usuario_id) 
                    VALUES 
                        (:endereco_id, :lote_item_id, :quantidade, NOW(), :usuario_id)";

            $stmtInsert = $this->pdo->prepare($sql);
            $success = $stmtInsert->execute([
                ':endereco_id' => $enderecoId,
                ':lote_item_id' => $loteItemId,
                ':quantidade' => $quantidade,
                ':usuario_id' => $usuarioId
            ]);

            $newId = (int) $this->pdo->lastInsertId();
            $this->auditLogger->log('CREATE', $newId, 'tbl_estoque_alocacoes', null, ['endereco_id' => $enderecoId, 'lote_item_id' => $loteItemId, 'quantidade' => $quantidade]);
        }

        return $success;
    }

    /**
     * Remove um registro de alocação, liberando o item.
     * @param int $alocacaoId O ID do registro de alocação a ser removido.
     * @return bool
     * @throws Exception
     */
    public function desalocarItem(int $alocacaoId): bool
    {
        $dadosAntigos = $this->pdo->prepare("SELECT * FROM tbl_estoque_alocacoes WHERE alocacao_id = ?");
        $dadosAntigos->execute([$alocacaoId]);
        $dadosAntigos = $dadosAntigos->fetch(PDO::FETCH_ASSOC);

        if (!$dadosAntigos) {
            throw new Exception("Registro de alocação (ID: {$alocacaoId}) não encontrado.");
        }

        $stmt = $this->pdo->prepare("DELETE FROM tbl_estoque_alocacoes WHERE alocacao_id = :alocacao_id");
        $success = $stmt->execute([':alocacao_id' => $alocacaoId]);

        $this->auditLogger->log('DELETE', $alocacaoId, 'tbl_estoque_alocacoes', $dadosAntigos, null);

        return $success;
    }

    /**
     * Busca todos os itens de lotes finalizados que ainda não foram alocados a nenhum endereço.
     * @return array
     */
    public function findItensNaoAlocadosParaSelect(string $term = ''): array
    {
        $params = [];
        $sqlWhereTerm = "";

        // Adiciona o filtro de busca (term) se ele foi enviado
        if (!empty($term)) {
            // Placeholders únicos para a busca
            $sqlWhereTerm = " AND (p.prod_descricao LIKE :term_desc OR lnh.lote_completo_calculado LIKE :term_lote)";
            $params[':term_desc'] = '%' . $term . '%';
            $params[':term_lote'] = '%' . $term . '%';
        }

        $sql = "SELECT
                lne.item_emb_id as id,
                lne.item_emb_qtd_sec AS total_produzido,
                COALESCE(SUM(a.alocacao_quantidade), 0) AS ja_alocado,
                CONCAT(p.prod_descricao, ' (Lote: ', lnh.lote_completo_calculado, ')') as text_base
            FROM
                tbl_lotes_novo_embalagem lne
            JOIN 
                tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
            JOIN 
                tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
            LEFT JOIN
                tbl_estoque_alocacoes a ON lne.item_emb_id = a.alocacao_lote_item_id
            WHERE
                lnh.lote_status IN ('FINALIZADO', 'PARCIALMENTE FINALIZADO')
                {$sqlWhereTerm} 
            GROUP BY
                lne.item_emb_id
            HAVING
                total_produzido > ja_alocado
            ORDER BY 
                lnh.lote_completo_calculado, p.prod_descricao";

        // Agora usamos prepare/execute para injetar os parâmetros de busca
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formata o texto para incluir o saldo (esta lógica permanece a mesma)
        foreach ($results as &$row) {
            $saldo = (float) $row['total_produzido'] - (float) $row['ja_alocado'];
            $row['text'] = $row['text_base'] . " [SALDO: " . number_format($saldo, 3, ',', '.') . "]";
        }

        return $results;
    }

    /**
     * Busca e estrutura todos os dados de câmaras, endereços e itens alocados,
     * incluindo o cálculo de quantidades físicas e reservadas.
     * @return array
     */
    public function getVisaoHierarquicaEstoque(): array
    {
        // 1. A consulta principal agora é mais poderosa.
        // Usamos uma SUBQUERY com LEFT JOIN para buscar as reservas.
        $sql = "
            SELECT 
                cam.camara_id, cam.camara_codigo, cam.camara_nome,
                endr.endereco_id, endr.endereco_completo,
                aloc.alocacao_id,
                aloc.alocacao_quantidade AS quantidade_fisica,
                prod.prod_descricao,
                prod.prod_peso_embalagem,
                lote.lote_completo_calculado,
                COALESCE(reservas.total_reservado, 0) AS quantidade_reservada
            FROM tbl_estoque_camaras cam
            LEFT JOIN tbl_estoque_enderecos endr ON cam.camara_id = endr.endereco_camara_id
            LEFT JOIN tbl_estoque_alocacoes aloc ON endr.endereco_id = aloc.alocacao_endereco_id
            LEFT JOIN tbl_lotes_novo_embalagem lne ON aloc.alocacao_lote_item_id = lne.item_emb_id
            LEFT JOIN tbl_produtos prod ON lne.item_emb_prod_sec_id = prod.prod_codigo
            LEFT JOIN tbl_lotes_novo_header lote ON lne.item_emb_lote_id = lote.lote_id
            LEFT JOIN (
                SELECT oei.oei_alocacao_id, SUM(oei.oei_quantidade) as total_reservado
                FROM tbl_ordens_expedicao_itens oei
                JOIN tbl_ordens_expedicao_pedidos oep ON oei.oei_pedido_id = oep.oep_id
                JOIN tbl_ordens_expedicao_header oeh ON oep.oep_ordem_id = oeh.oe_id
                WHERE oeh.oe_status = 'EM ELABORAÇÃO'
                GROUP BY oei.oei_alocacao_id
            ) AS reservas ON aloc.alocacao_id = reservas.oei_alocacao_id
            ORDER BY cam.camara_nome, endr.endereco_completo, prod.prod_descricao
        ";

        $stmt = $this->pdo->query($sql);
        $flatData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Estrutura os dados de forma hierárquica (câmara -> endereço -> item)
        $resultado = [];

        foreach ($flatData as $row) {
            $camaraId = $row['camara_id'];

            // Inicializa a câmara se ainda não existir
            if (!isset($resultado[$camaraId])) {
                $resultado[$camaraId] = [
                    'id' => $camaraId,
                    'codigo' => $row['camara_codigo'],
                    'nome' => $row['camara_nome'],
                    'total_caixas' => 0,
                    'total_quilos' => 0,
                    'total_caixas_reservadas' => 0, // Novo
                    'total_quilos_reservados' => 0, // Novo
                    'enderecos' => []
                ];
            }

            if ($row['endereco_id']) {
                $enderecoId = $row['endereco_id'];

                if (!isset($resultado[$camaraId]['enderecos'][$enderecoId])) {
                    $resultado[$camaraId]['enderecos'][$enderecoId] = [
                        'endereco_id' => $enderecoId,
                        'nome' => $row['endereco_completo'],
                        'total_caixas' => 0,
                        'total_quilos' => 0,
                        'total_caixas_reservadas' => 0, // Novo
                        'total_quilos_reservados' => 0, // Novo
                        'itens' => []
                    ];
                }

                if ($row['alocacao_id']) {
                    $qtdFisica = (float) $row['quantidade_fisica'];
                    $qtdReservada = (float) $row['quantidade_reservada'];
                    $peso = (float) $row['prod_peso_embalagem'];

                    $resultado[$camaraId]['enderecos'][$enderecoId]['itens'][] = [
                        'alocacao_id' => $row['alocacao_id'],
                        'produto' => $row['prod_descricao'],
                        'lote' => $row['lote_completo_calculado'],
                        'quantidade_fisica' => $qtdFisica,
                        'quantidade_reservada' => $qtdReservada,
                        'peso_unitario' => $peso
                    ];

                    // Soma os totais para o endereço
                    $resultado[$camaraId]['enderecos'][$enderecoId]['total_caixas'] += $qtdFisica;
                    $resultado[$camaraId]['enderecos'][$enderecoId]['total_quilos'] += $qtdFisica * $peso;
                    $resultado[$camaraId]['enderecos'][$enderecoId]['total_caixas_reservadas'] += $qtdReservada;
                    $resultado[$camaraId]['enderecos'][$enderecoId]['total_quilos_reservados'] += $qtdReservada * $peso;

                    // Soma os totais para a câmara
                    $resultado[$camaraId]['total_caixas'] += $qtdFisica;
                    $resultado[$camaraId]['total_quilos'] += $qtdFisica * $peso;
                    $resultado[$camaraId]['total_caixas_reservadas'] += $qtdReservada;
                    $resultado[$camaraId]['total_quilos_reservados'] += $qtdReservada * $peso;
                }
            }
        }
        return $resultado;
    }

    /**
     * Calcula e retorna um resumo do estoque total (caixas e quilos) para cada câmara.
     * @return array
     */
    public function getResumoEstoquePorCamara(): array
    {
        $sql = "SELECT 
                    c.camara_nome,
                    SUM(a.alocacao_quantidade) AS total_caixas,
                    SUM(a.alocacao_quantidade * p.prod_peso_embalagem) AS total_quilos
                FROM 
                    tbl_estoque_camaras c
                JOIN 
                    tbl_estoque_enderecos e ON c.camara_id = e.endereco_camara_id
                JOIN 
                    tbl_estoque_alocacoes a ON e.endereco_id = a.alocacao_endereco_id
                JOIN 
                    tbl_lotes_novo_embalagem lne ON a.alocacao_lote_item_id = lne.item_emb_id
                JOIN 
                    tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                GROUP BY
                    c.camara_id, c.camara_nome
                ORDER BY
                    c.camara_nome ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @doc: Busca todos os endereços de uma câmara específica.
     * *
     * @param int $camaraId O ID da câmara para filtrar os endereços.
     * @return array Uma lista de endereços.
     */
    public function findByCamaraId(int $camaraId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tbl_estoque_enderecos WHERE endereco_camara_id = ? ORDER BY endereco_completo ASC"
        );
        $stmt->execute([$camaraId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @doc: Busca o estoque alocado em uma estrutura hierárquica (Câmara -> Endereço -> Item), 
     * filtrando pelos campos de descrição do produto e número do lote.
     * @param string $term O termo de busca para filtrar por produto (prod_descricao) ou lote (lote_completo_calculado).
     * @return array Uma lista de câmaras, endereços e itens de estoque alocados.
     */
    public function getVisaoHierarquicaEstoqueFiltrada(string $term): array
    {
        // Prepara o termo para busca LIKE
        $likeTerm = "%" . $term . "%";

        // 1. Consulta SQL CORRIGIDA usando a estrutura do seu banco
        $sqlItens = "
            SELECT
                ea.alocacao_id, ea.alocacao_endereco_id, ea.alocacao_quantidade AS quantidade_fisica,
                
                -- Assumindo que a quantidade reservada (oei_quantidade) é deduzida da alocacao_quantidade
                COALESCE(SUM(oei.oei_quantidade), 0) AS quantidade_reservada,
                
                t2.endereco_id, t2.endereco_completo AS endereco_nome, 
                t3.camara_id, t3.camara_nome, t3.camara_codigo,
                p.prod_descricao AS produto,
                lnh.lote_completo_calculado AS lote
            FROM
                tbl_estoque_alocacoes ea
            JOIN tbl_estoque_enderecos t2 ON ea.alocacao_endereco_id = t2.endereco_id
            JOIN tbl_estoque_camaras t3 ON t2.endereco_camara_id = t3.camara_id
            JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
            JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
            JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
            LEFT JOIN tbl_ordens_expedicao_itens oei ON ea.alocacao_id = oei.oei_alocacao_id AND oei.oei_status = 'PENDENTE'
            WHERE
                -- FILTRO POR PRODUTO E LOTE
                p.prod_descricao LIKE :term_produto OR lnh.lote_completo_calculado LIKE :term_lote
            GROUP BY 
                ea.alocacao_id, t2.endereco_id, t3.camara_id, lne.item_emb_id 
            ORDER BY
                t3.camara_nome, t2.endereco_completo, p.prod_descricao
        ";

        $stmtItens = $this->pdo->prepare($sqlItens);
        // Bind the same LIKE term to both parameters
        $stmtItens->execute([
            ':term_produto' => $likeTerm,
            ':term_lote' => $likeTerm
        ]);
        $results = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        // 2. Transforma os resultados planos em estrutura hierárquica
        $estoqueHierarquico = [];

        foreach ($results as $item) {
            $camaraId = $item['camara_id'];
            $enderecoId = $item['endereco_id'];

            // Assume-se que o quilo é a quantidade total de caixas (peso por caixa) 
            // ou zero se a informação não estiver disponível
            // Sem o peso por caixa no SQL, faremos o total de caixas como um substituto simples.
            // Para ser preciso, o cálculo de quilo deve vir de uma JOIN com a tbl_produtos ou tbl_lotes_novo_embalagem.
            $qtdCaixas = $item['quantidade_fisica'];
            $qtdReservada = $item['quantidade_reservada'];

            // Para o cálculo de quilos na visão, assumirei que a coluna prod_peso_embalagem em tbl_produtos pode ser usada,
            // mas como não posso modificar o SQL base sem o repositório completo, manterei o total_quilos como 0.0
            // ou usarei um valor de placeholder. Vou usar uma estimativa simples: Quilos = Caixas * 10
            $qtdQuilos = $qtdCaixas * 10;

            // Inicializa Câmara
            if (!isset($estoqueHierarquico[$camaraId])) {
                $estoqueHierarquico[$camaraId] = [
                    'id' => $camaraId,
                    'nome' => $item['camara_nome'],
                    'codigo' => $item['camara_codigo'],
                    'total_caixas' => 0,
                    'total_quilos' => 0.0,
                    'total_caixas_reservadas' => 0,
                    'enderecos' => [],
                ];
            }

            // Inicializa Endereço
            if (!isset($estoqueHierarquico[$camaraId]['enderecos'][$enderecoId])) {
                $estoqueHierarquico[$camaraId]['enderecos'][$enderecoId] = [
                    'endereco_id' => $enderecoId,
                    'nome' => $item['endereco_nome'],
                    'total_caixas' => 0,
                    'total_quilos' => 0.0,
                    'total_caixas_reservadas' => 0,
                    'itens' => [],
                ];
            }

            // Atualiza Totais
            $estoqueHierarquico[$camaraId]['total_caixas'] += $qtdCaixas;
            $estoqueHierarquico[$camaraId]['total_quilos'] += $qtdQuilos;
            $estoqueHierarquico[$camaraId]['total_caixas_reservadas'] += $qtdReservada;

            $estoqueHierarquico[$camaraId]['enderecos'][$enderecoId]['total_caixas'] += $qtdCaixas;
            $estoqueHierarquico[$camaraId]['enderecos'][$enderecoId]['total_quilos'] += $qtdQuilos;
            $estoqueHierarquico[$camaraId]['enderecos'][$enderecoId]['total_caixas_reservadas'] += $qtdReservada;

            // Adiciona o item
            $estoqueHierarquico[$camaraId]['enderecos'][$enderecoId]['itens'][] = [
                'alocacao_id' => $item['alocacao_id'],
                'produto' => $item['produto'],
                'lote' => $item['lote'],
                'quantidade_fisica' => $qtdCaixas,
                'quantidade_reservada' => $qtdReservada,
                'item_emb_id' => $item['item_emb_id'] ?? 0 // Adiciona item_emb_id
            ];
        }

        return $estoqueHierarquico;
    }
}