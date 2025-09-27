<?php
// /src/FichasTecnicas/FichaTecnicaRepository.php
namespace App\FichasTecnicas;

use PDO;
use App\Core\AuditLoggerService;

// VERSÃO FINAL CORRIGIDA - Adotando o padrão de parâmetros únicos do LoteNovoRepository
class FichaTecnicaRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Busca produtos que AINDA NÃO têm uma Ficha Técnica associada.
     */
    public function findProdutosSemFichaTecnica(string $term = ''): array
    {
        $params = [];
        $sqlWhereTerm = "";

        if (!empty($term)) {
            // CORREÇÃO: Usando placeholders com nomes únicos (:term0, :term1)
            $sqlWhereTerm = " AND (p.prod_descricao LIKE :term0 OR p.prod_codigo_interno LIKE :term1)";
            $params[':term0'] = '%' . $term . '%';
            $params[':term1'] = '%' . $term . '%';
        }

        $sql = "SELECT
                    p.prod_codigo AS id,
                    CONCAT(p.prod_descricao, ' (Cód: ', COALESCE(p.prod_codigo_interno, 'N/A'), ')') AS text
                FROM tbl_produtos p
                LEFT JOIN tbl_fichas_tecnicas ft ON p.prod_codigo = ft.ficha_produto_id
                WHERE ft.ficha_id IS NULL{$sqlWhereTerm}
                ORDER BY p.prod_descricao";

        $stmt = $this->pdo->prepare($sql);

        // O método de binding em loop, que é robusto
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca entidades que podem ser fabricantes (CLIENTES).
     */
    public function getFabricanteOptions(string $term = ''): array
    {
        $params = [];
        $sqlWhereTerm = "";

        if (!empty($term)) {
            // CORREÇÃO: Usando placeholders com nomes únicos (:term0, :term1, :term2)
            $sqlWhereTerm = " AND (ent_nome_fantasia LIKE :term0 OR ent_razao_social LIKE :term1 OR ent_codigo LIKE :term2)";
            $params[':term0'] = '%' . $term . '%';
            $params[':term1'] = '%' . $term . '%';
            $params[':term2'] = '%' . $term . '%';
        }

        $sql = "SELECT
                    ent_codigo AS id,
                    CONCAT(COALESCE(NULLIF(ent_nome_fantasia, ''), ent_razao_social), ' (Cód: ', ent_codigo, ')') AS text
                FROM tbl_entidades 
                WHERE (ent_tipo_entidade = 'Cliente' OR ent_tipo_entidade = 'Cliente e Fornecedor') 
                AND ent_situacao = 'A'{$sqlWhereTerm}
                ORDER BY text ASC";

        $stmt = $this->pdo->prepare($sql);

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- Funções originais do arquivo que não estavam relacionadas ao problema ---
    public function findAllForDataTable(array $params): array
    {
        $baseQuery = "FROM tbl_fichas_tecnicas ft JOIN tbl_produtos p ON ft.ficha_produto_id = p.prod_codigo";
        $totalRecords = $this->pdo->query("SELECT COUNT(ft.ficha_id) FROM tbl_fichas_tecnicas ft")->fetchColumn();
        $sqlData = "SELECT ft.ficha_id, p.prod_descricao, p.prod_marca, p.prod_ncm, ft.ficha_data_modificacao $baseQuery ORDER BY ft.ficha_id DESC LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        return ["draw" => intval($params['draw'] ?? 1), "recordsTotal" => (int) $totalRecords, "recordsFiltered" => (int) $totalRecords, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }
    public function findCompletaById(int $fichaId): ?array
    {
        $stmtHeader = $this->pdo->prepare("SELECT * FROM tbl_fichas_tecnicas WHERE ficha_id = :id");
        $stmtHeader->execute([':id' => $fichaId]);
        $ficha['header'] = $stmtHeader->fetch(PDO::FETCH_ASSOC);
        if (!$ficha['header'])
            return null;
        $stmtCriterios = $this->pdo->prepare("SELECT * 
                                                     FROM tbl_fichas_tecnicas_criterios 
                                                     WHERE criterio_ficha_id = :id 
                                                     ORDER BY criterio_id");
        $stmtCriterios->execute([':id' => $fichaId]);
        $ficha['criterios'] = $stmtCriterios->fetchAll(PDO::FETCH_ASSOC);
        $ficha['fotos'] = [];
        return $ficha;
    }
    /* public function getProdutoDetalhes(int $produtoId): ?array
     {
         $stmt = $this->pdo->prepare("SELECT * FROM tbl_produtos WHERE prod_codigo = :id");
         $stmt->execute([':id' => $produtoId]);
         return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
     } */

    public function getProdutoDetalhes(int $produtoId): ?array
    {
        $sql = "SELECT 
                    p_sec.*, 
                    COALESCE(p_prim.prod_ean13, p_sec.prod_ean13) AS ean13_final,
                    p_prim.prod_peso_embalagem AS peso_embalagem_primaria
                FROM tbl_produtos AS p_sec
                LEFT JOIN tbl_produtos AS p_prim ON p_sec.prod_primario_id = p_prim.prod_codigo
                WHERE p_sec.prod_codigo = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $produtoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Salva ou atualiza o cabeçalho (dados gerais) de uma Ficha Técnica.
     *
     * @param array $data Dados do formulário ($_POST)
     * @param int $usuarioId ID do usuário que está realizando a ação
     * @return int O ID da ficha salva ou atualizada
     * @throws \Exception
     */
    /* public function saveHeader(array $data, int $usuarioId): int
     {
         $fichaId = !empty($data['ficha_id']) ? (int) $data['ficha_id'] : null;

         // Validação básica
         if (empty($data['ficha_produto_id'])) {
             throw new \Exception("O campo 'Produto' é obrigatório.");
         }

         $this->pdo->beginTransaction();

         try {
             if ($fichaId) {
                 // --- ATUALIZAÇÃO ---
                 $sql = "UPDATE tbl_fichas_tecnicas SET
                             ficha_fabricante_id = :ficha_fabricante_id,
                             ficha_conservantes = :ficha_conservantes,
                             ficha_alergenicos = :ficha_alergenicos,
                             ficha_temp_estocagem_transporte = :ficha_temp_estocagem_transporte,
                             ficha_origem = :ficha_origem,
                             ficha_desc_embalagem = :ficha_desc_embalagem,
                             ficha_medidas_embalagem = :ficha_medidas_embalagem,
                             ficha_paletizacao = :ficha_paletizacao,
                             ficha_gestao_qualidade = :ficha_gestao_qualidade,
                             ficha_usuario_id = :usuario_id
                         WHERE ficha_id = :ficha_id";

                 $stmt = $this->pdo->prepare($sql);
                 $stmt->bindValue(':ficha_id', $fichaId, PDO::PARAM_INT);
             } else {
                 // --- CRIAÇÃO ---
                 $sql = "INSERT INTO tbl_fichas_tecnicas (
                             ficha_produto_id, ficha_fabricante_id, ficha_conservantes, ficha_alergenicos,
                             ficha_temp_estocagem_transporte, ficha_origem, ficha_desc_embalagem,
                             ficha_medidas_embalagem, ficha_paletizacao, ficha_gestao_qualidade,
                             ficha_usuario_id
                         ) VALUES (
                             :ficha_produto_id, :ficha_fabricante_id, :ficha_conservantes, :ficha_alergenicos,
                             :ficha_temp_estocagem_transporte, :ficha_origem, :ficha_desc_embalagem,
                             :ficha_medidas_embalagem, :ficha_paletizacao, :ficha_gestao_qualidade,
                             :usuario_id
                         )";

                 $stmt = $this->pdo->prepare($sql);
                 $stmt->bindValue(':ficha_produto_id', $data['ficha_produto_id'], PDO::PARAM_INT);
             }

             // Bind dos parâmetros comuns a ambas as operações
             $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
             $stmt->bindValue(':ficha_fabricante_id', !empty($data['ficha_fabricante_id']) ? $data['ficha_fabricante_id'] : null, PDO::PARAM_INT);
             $stmt->bindValue(':ficha_conservantes', $data['ficha_conservantes'] ?? null);
             $stmt->bindValue(':ficha_alergenicos', $data['ficha_alergenicos'] ?? null);
             $stmt->bindValue(':ficha_temp_estocagem_transporte', $data['ficha_temp_estocagem_transporte'] ?? null);
             $stmt->bindValue(':ficha_origem', $data['ficha_origem'] ?? 'INDÚSTRIA BRASILEIRA');
             $stmt->bindValue(':ficha_desc_embalagem', $data['ficha_desc_embalagem'] ?? null);
             $stmt->bindValue(':ficha_medidas_embalagem', $data['ficha_medidas_embalagem'] ?? null);
             $stmt->bindValue(':ficha_paletizacao', $data['ficha_paletizacao'] ?? null);
             $stmt->bindValue(':ficha_gestao_qualidade', $data['ficha_gestao_qualidade'] ?? null);

             $stmt->execute();

             if (!$fichaId) {
                 $fichaId = (int) $this->pdo->lastInsertId();
             }

             $this->pdo->commit();

             return $fichaId;

         } catch (\PDOException $e) {
             $this->pdo->rollBack();
             error_log("Erro em saveHeader FichaTecnica: " . $e->getMessage()); // Log para o servidor
             throw new \Exception("Erro ao salvar os dados no banco. Verifique os logs do servidor.");
         }
     } */

    public function saveHeader(array $data, int $usuarioId): int
    {
        $fichaId = !empty($data['ficha_id']) ? (int) $data['ficha_id'] : null;

        if (empty($data['ficha_produto_id'])) {
            throw new \Exception("O campo 'Produto' é obrigatório.");
        }

        $this->pdo->beginTransaction();

        try {
            if ($fichaId) {
                // --- ATUALIZAÇÃO ---
                $sql = "UPDATE tbl_fichas_tecnicas SET
                            ficha_fabricante_id = :ficha_fabricante_id,
                            ficha_conservantes = :ficha_conservantes,
                            ficha_alergenicos = :ficha_alergenicos,
                            ficha_temp_estocagem_transporte = :ficha_temp_estocagem_transporte,
                            ficha_origem = :ficha_origem,
                            ficha_desc_emb_primaria = :ficha_desc_emb_primaria,
                            ficha_desc_emb_secundaria = :ficha_desc_emb_secundaria,
                            ficha_medidas_emb_primaria = :ficha_medidas_emb_primaria,
                            ficha_medidas_emb_secundaria = :ficha_medidas_emb_secundaria,
                            ficha_paletizacao = :ficha_paletizacao,
                            ficha_registro_embalagem = :ficha_registro_embalagem,
                            ficha_gestao_qualidade = :ficha_gestao_qualidade,
                            ficha_usuario_id = :usuario_id
                        WHERE ficha_id = :ficha_id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':ficha_id', $fichaId, PDO::PARAM_INT);
            } else {
                // --- CRIAÇÃO ---
                $sql = "INSERT INTO tbl_fichas_tecnicas (
                            ficha_produto_id, ficha_fabricante_id, ficha_conservantes, ficha_alergenicos,
                            ficha_temp_estocagem_transporte, ficha_origem,
                            ficha_desc_emb_primaria, ficha_desc_emb_secundaria,
                            ficha_medidas_emb_primaria, ficha_medidas_emb_secundaria,
                            ficha_paletizacao, ficha_registro_embalagem, ficha_gestao_qualidade,
                            ficha_usuario_id
                        ) VALUES (
                            :ficha_produto_id, :ficha_fabricante_id, :ficha_conservantes, :ficha_alergenicos,
                            :ficha_temp_estocagem_transporte, :ficha_origem,
                            :ficha_desc_emb_primaria, :ficha_desc_emb_secundaria,
                            :ficha_medidas_emb_primaria, :ficha_medidas_emb_secundaria,
                            :ficha_paletizacao, :ficha_registro_embalagem, :ficha_gestao_qualidade,
                            :usuario_id
                        )";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':ficha_produto_id', $data['ficha_produto_id'], PDO::PARAM_INT);
            }

            // Bind dos parâmetros comuns
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':ficha_fabricante_id', !empty($data['ficha_fabricante_id']) ? $data['ficha_fabricante_id'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':ficha_conservantes', $data['ficha_conservantes'] ?? null);
            $stmt->bindValue(':ficha_alergenicos', $data['ficha_alergenicos'] ?? null);
            $stmt->bindValue(':ficha_temp_estocagem_transporte', $data['ficha_temp_estocagem_transporte'] ?? null);
            $stmt->bindValue(':ficha_origem', $data['ficha_origem'] ?? 'INDÚSTRIA BRASILEIRA');
            $stmt->bindValue(':ficha_desc_emb_primaria', $data['ficha_desc_emb_primaria'] ?? null);
            $stmt->bindValue(':ficha_desc_emb_secundaria', $data['ficha_desc_emb_secundaria'] ?? null);
            $stmt->bindValue(':ficha_medidas_emb_primaria', $data['ficha_medidas_emb_primaria'] ?? null);
            $stmt->bindValue(':ficha_medidas_emb_secundaria', $data['ficha_medidas_emb_secundaria'] ?? null);
            $stmt->bindValue(':ficha_paletizacao', $data['ficha_paletizacao'] ?? null);
            $stmt->bindValue(':ficha_registro_embalagem', $data['ficha_registro_embalagem'] ?? null);
            $stmt->bindValue(':ficha_gestao_qualidade', $data['ficha_gestao_qualidade'] ?? null);

            $stmt->execute();

            if (!$fichaId) {
                $fichaId = (int) $this->pdo->lastInsertId();
            }

            $this->pdo->commit();
            return $fichaId;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Erro em saveHeader FichaTecnica: " . $e->getMessage());
            throw new \Exception("Erro ao salvar os dados no banco. Verifique os logs do servidor.");
        }
    }
}