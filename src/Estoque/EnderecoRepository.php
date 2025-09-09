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

    /* public function findAllForDataTable(int $camaraId, array $params): array
     {
         $totalRecords = $this->pdo->prepare("SELECT COUNT(endereco_id) FROM tbl_estoque_enderecos WHERE endereco_camara_id = ?");
         $totalRecords->execute([$camaraId]);

         $sql = "SELECT * FROM tbl_estoque_enderecos WHERE endereco_camara_id = :camara_id ORDER BY endereco_completo ASC LIMIT :start, :length";
         $stmt = $this->pdo->prepare($sql);
         $stmt->bindValue(':camara_id', $camaraId, PDO::PARAM_INT);
         $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
         $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
         $stmt->execute();
         $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

         return [
             "draw" => intval($params['draw'] ?? 1),
             "recordsTotal" => (int) $totalRecords->fetchColumn(),
             "recordsFiltered" => (int) $totalRecords->fetchColumn(), // Simplificado
             "data" => $data
         ];
     }*/

    /*  public function findAllForDataTable(int $camaraId, array $params): array
      {
          $totalRecordsStmt = $this->pdo->prepare("SELECT COUNT(endereco_id) FROM tbl_estoque_enderecos WHERE endereco_camara_id = ?");
          $totalRecordsStmt->execute([$camaraId]);
          $totalRecords = $totalRecordsStmt->fetchColumn();

          $sql = "SELECT 
                      e.*,
                      p.prod_descricao,
                      lnh.lote_completo_calculado
                  FROM 
                      tbl_estoque_enderecos e
                  LEFT JOIN 
                      tbl_produtos p ON e.produto_id_alocado = p.prod_codigo
                  LEFT JOIN 
                      tbl_lotes_novo_embalagem lne ON e.lote_item_id_alocado = lne.item_emb_id
                  LEFT JOIN 
                      tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                  WHERE 
                      e.endereco_camara_id = :camara_id 
                  ORDER BY 
                      e.endereco_completo ASC 
                  LIMIT :start, :length";

          $stmt = $this->pdo->prepare($sql);
          $stmt->bindValue(':camara_id', $camaraId, PDO::PARAM_INT);
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
      }*/

    public function findAllForDataTable(int $camaraId, array $params): array
    {
        $totalRecordsStmt = $this->pdo->prepare("SELECT COUNT(endereco_id) FROM tbl_estoque_enderecos WHERE endereco_camara_id = ?");
        $totalRecordsStmt->execute([$camaraId]);
        $totalRecords = $totalRecordsStmt->fetchColumn();

        // --- QUERY SIMPLIFICADA ---
        // Apenas busca os dados da própria tabela de endereços, sem os JOINs que removi.
        $sql = "SELECT 
                    *
                FROM 
                    tbl_estoque_enderecos e
                WHERE 
                    e.endereco_camara_id = :camara_id 
                ORDER BY 
                    e.endereco_completo ASC 
                LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':camara_id', $camaraId, PDO::PARAM_INT);
        $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalRecords, // Simplificado
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
        // CORREÇÃO: Usamos o operador '??' para cada campo opcional.
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

    /*  public function save(array $data): int
      {
          $id = filter_var($data['endereco_id'] ?? null, FILTER_VALIDATE_INT);
          $camaraId = filter_var($data['endereco_camara_id'], FILTER_VALIDATE_INT);
          if (!$camaraId) {
              throw new Exception("ID da Câmara é inválido.");
          }

          $enderecoCompleto = $this->calcularEnderecoCompleto($camaraId, $data);

          // Usamos o operador '??' para garantir que todos os campos existam.
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
      }*/

    /*  public function save(array $data): int
      {
          $id = filter_var($data['endereco_id'] ?? null, FILTER_VALIDATE_INT);
          $camaraId = filter_var($data['endereco_camara_id'], FILTER_VALIDATE_INT);
          if (!$camaraId) {
              throw new Exception("ID da Câmara é inválido.");
          }

          $enderecoCompleto = $this->calcularEnderecoCompleto($camaraId, $data);

          $params = [
              ':camara_id' => $camaraId,
              ':lado' => $data['lado'] ?: null,
              ':nivel' => $data['nivel'] ?: null,
              ':fila' => $data['fila'] ?: null,
              ':vaga' => $data['vaga'] ?: null,
              ':descricao_simples' => $data['descricao_simples'] ?: null,
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
      }*/

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
            // (O resto da lógica de UPDATE continua igual)
            $dadosAntigos = $this->find($id);
            $sql = "UPDATE tbl_estoque_enderecos SET endereco_camara_id = :camara_id, lado = :lado, nivel = :nivel, fila = :fila, vaga = :vaga, descricao_simples = :descricao_simples, endereco_completo = :endereco_completo WHERE endereco_id = :id";
            $params[':id'] = $id;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $this->auditLogger->log('UPDATE', $id, 'tbl_estoque_enderecos', $dadosAntigos, $data);
            return $id;
        } else { // CREATE
            // (O resto da lógica de CREATE continua igual)
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
    public function findItensNaoAlocadosParaSelect(): array
    {
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
                GROUP BY
                    lne.item_emb_id
                HAVING
                    total_produzido > ja_alocado
                ORDER BY 
                    lnh.lote_completo_calculado, p.prod_descricao";

        $stmt = $this->pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formata o texto para incluir o saldo
        foreach ($results as &$row) {
            $saldo = (float) $row['total_produzido'] - (float) $row['ja_alocado'];
            $row['text'] = $row['text_base'] . " [SALDO: " . number_format($saldo, 3, ',', '.') . "]";
        }

        return $results;
    }

    /**
     * Busca e organiza todos os dados de estoque de forma hierárquica.
     * @return array
     */
    public function getVisaoHierarquicaEstoque(): array
    {
        // QUERY ATUALIZADA: Adicionamos p.prod_peso_embalagem para podermos calcular o peso total
        $sql = "SELECT 
                    c.camara_id, c.camara_codigo, c.camara_nome,
                    e.endereco_id, e.endereco_completo,
                    a.alocacao_id, a.alocacao_quantidade, a.alocacao_data,
                    p.prod_descricao, p.prod_peso_embalagem,
                    lnh.lote_completo_calculado
                FROM 
                    tbl_estoque_camaras c
                LEFT JOIN 
                    tbl_estoque_enderecos e ON c.camara_id = e.endereco_camara_id
                LEFT JOIN 
                    tbl_estoque_alocacoes a ON e.endereco_id = a.alocacao_endereco_id
                LEFT JOIN 
                    tbl_lotes_novo_embalagem lne ON a.alocacao_lote_item_id = lne.item_emb_id
                LEFT JOIN 
                    tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                LEFT JOIN 
                    tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                ORDER BY 
                    c.camara_codigo, e.endereco_completo, lnh.lote_completo_calculado";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tree = [];
        foreach ($rows as $row) {
            $camaraId = $row['camara_id'];
            if (!isset($tree[$camaraId])) {
                $tree[$camaraId] = [
                    'nome' => $row['camara_nome'],
                    'codigo' => $row['camara_codigo'],
                    'enderecos' => [],
                    'total_caixas' => 0, // Inicializa os totalizadores da câmara
                    'total_quilos' => 0
                ];
            }

            if ($row['endereco_id']) {
                $enderecoId = $row['endereco_id'];
                if (!isset($tree[$camaraId]['enderecos'][$enderecoId])) {
                    $tree[$camaraId]['enderecos'][$enderecoId] = [
                        'endereco_id' => (int) $row['endereco_id'],
                        'nome' => $row['endereco_completo'],
                        'itens' => [],
                        'total_caixas' => 0, // Inicializa os totalizadores do endereço
                        'total_quilos' => 0
                    ];
                }

                if ($row['alocacao_id']) {
                    $quantidade = (float) $row['alocacao_quantidade'];
                    $peso_embalagem = (float) $row['prod_peso_embalagem'];
                    $peso_item = $quantidade * $peso_embalagem;

                    // Adiciona o item ao endereço
                    $tree[$camaraId]['enderecos'][$enderecoId]['itens'][] = [
                        'alocacao_id' => $row['alocacao_id'],
                        'produto' => $row['prod_descricao'],
                        'lote' => $row['lote_completo_calculado'],
                        'quantidade' => $row['alocacao_quantidade'],
                        'data' => $row['alocacao_data']
                    ];
                    // Soma os totais para o endereço
                    $tree[$camaraId]['enderecos'][$enderecoId]['total_caixas'] += $quantidade;
                    $tree[$camaraId]['enderecos'][$enderecoId]['total_quilos'] += $peso_item;
                }
            }
        }

        // Loop final para somar os totais dos endereços para as câmaras
        foreach ($tree as $camaraId => &$camara) { // O '&' é importante aqui
            foreach ($camara['enderecos'] as $endereco) {
                $camara['total_caixas'] += $endereco['total_caixas'];
                $camara['total_quilos'] += $endereco['total_quilos'];
            }
        }

        return $tree;
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
}