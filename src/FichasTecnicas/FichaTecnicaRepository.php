<?php
// /src/FichasTecnicas/FichaTecnicaRepository.php
namespace App\FichasTecnicas;

use PDO;
use PDOException;
use Exception;
use App\Core\AuditLoggerService;
use App\Core\RelatorioService;
use App\FichasTecnicas\FichaTecnicaHtmlService;


// Adotando o padrão de parâmetros únicos do LoteNovoRepository
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
            // Usando placeholders com nomes únicos (:term0, :term1)
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
            // Usando placeholders com nomes únicos (:term0, :term1, :term2)
            $sqlWhereTerm = " AND (ent_nome_fantasia LIKE :term0 OR ent_razao_social LIKE :term1 OR ent_codigo LIKE :term2)";
            $params[':term0'] = '%' . $term . '%';
            $params[':term1'] = '%' . $term . '%';
            $params[':term2'] = '%' . $term . '%';
        }

        $sql = "SELECT
                    ent_codigo AS id,
                    CONCAT(COALESCE(NULLIF(ent_nome_fantasia, ''), ent_razao_social), ' (Cód: ', ent_codigo_interno, ')') AS text
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

    /* public function findAllForDataTable(array $params): array
     {
         try {
             $draw = $params['draw'] ?? 1;
             $start = $params['start'] ?? 0;
             $length = $params['length'] ?? 10;
             $searchValue = $params['search']['value'] ?? '';

             $baseQuery = "FROM tbl_fichas_tecnicas ft JOIN tbl_produtos p ON ft.ficha_produto_id = p.prod_codigo";

             $totalRecords = $this->pdo->query("SELECT COUNT(ft.ficha_id) $baseQuery")->fetchColumn();

             $whereClause = '';
             if (!empty($searchValue)) {
                 $whereClause = " WHERE p.prod_descricao LIKE :search_descricao OR p.prod_marca LIKE :search_marca OR p.prod_ncm LIKE :search_ncm";
             }

             $stmtFiltered = $this->pdo->prepare("SELECT COUNT(ft.ficha_id) $baseQuery $whereClause");
             if (!empty($searchValue)) {
                 $stmtFiltered->execute([
                     ':search_descricao' => '%' . $searchValue . '%',
                     ':search_marca' => '%' . $searchValue . '%',
                     ':search_ncm' => '%' . $searchValue . '%'
                 ]);
             } else {
                 $stmtFiltered->execute();
             }
             $totalFiltered = $stmtFiltered->fetchColumn();

             $sqlData = "SELECT ft.ficha_id, p.prod_descricao, p.prod_marca, p.prod_ncm, ft.ficha_data_modificacao $baseQuery $whereClause ORDER BY ft.ficha_id DESC LIMIT :start, :length";
             $stmt = $this->pdo->prepare($sqlData);
             $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
             $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
             if (!empty($searchValue)) {
                 $stmt->bindValue(':search_descricao', '%' . $searchValue . '%');
                 $stmt->bindValue(':search_marca', '%' . $searchValue . '%');
                 $stmt->bindValue(':search_ncm', '%' . $searchValue . '%');
             }
             $stmt->execute();
             $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

             return ["draw" => (int) $draw, "recordsTotal" => (int) $totalRecords, "recordsFiltered" => (int) $totalFiltered, "data" => $data];
         } catch (PDOException $e) {
             error_log('Erro em findAllForDataTable: ' . $e->getMessage());
             throw new Exception('Erro ao buscar fichas técnicas: ' . $e->getMessage());
         }
     } */

    public function findAllForDataTable(array $params): array
    {
        try {
            $draw = $params['draw'] ?? 1;
            $start = $params['start'] ?? 0;
            $length = $params['length'] ?? 10;
            $searchValue = $params['search']['value'] ?? '';

            $baseQuery = "FROM tbl_fichas_tecnicas ft JOIN tbl_produtos p ON ft.ficha_produto_id = p.prod_codigo";

            $totalRecords = $this->pdo->query("SELECT COUNT(ft.ficha_id) $baseQuery")->fetchColumn();

            $whereClause = '';
            if (!empty($searchValue)) {
                $whereClause = " WHERE p.prod_descricao LIKE :search_descricao OR p.prod_marca LIKE :search_marca OR p.prod_ncm LIKE :search_ncm";
            }

            $stmtFiltered = $this->pdo->prepare("SELECT COUNT(ft.ficha_id) $baseQuery $whereClause");
            if (!empty($searchValue)) {
                $stmtFiltered->execute([
                    ':search_descricao' => '%' . $searchValue . '%',
                    ':search_marca' => '%' . $searchValue . '%',
                    ':search_ncm' => '%' . $searchValue . '%'
                ]);
            } else {
                $stmtFiltered->execute();
            }
            $totalFiltered = $stmtFiltered->fetchColumn();

            // $sqlData = "SELECT ft.ficha_id, p.prod_codigo_interno, p.prod_descricao, p.prod_marca, p.prod_ncm, ft.ficha_data_modificacao $baseQuery $whereClause ORDER BY ft.ficha_id DESC LIMIT :start, :length";
            $sqlData = "SELECT 
                ft.ficha_id, 
                p.prod_codigo_interno, 
                p.prod_descricao, 
                p.prod_marca, 
                p.prod_ncm, 
                ft.ficha_data_modificacao 
            $baseQuery $whereClause 
            ORDER BY ft.ficha_id DESC 
            LIMIT :start, :length";
            $stmt = $this->pdo->prepare($sqlData);
            $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
            $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
            if (!empty($searchValue)) {
                $stmt->bindValue(':search_descricao', '%' . $searchValue . '%');
                $stmt->bindValue(':search_marca', '%' . $searchValue . '%');
                $stmt->bindValue(':search_ncm', '%' . $searchValue . '%');
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ["draw" => (int) $draw, "recordsTotal" => (int) $totalRecords, "recordsFiltered" => (int) $totalFiltered, "data" => $data];
        } catch (PDOException $e) {
            error_log('Erro em findAllForDataTable: ' . $e->getMessage());
            throw new Exception('Erro ao buscar fichas técnicas: ' . $e->getMessage());
        }
    }

    public function findCompletaById(int $fichaId): ?array
    {
        $ficha = [];
        $stmtHeader = $this->pdo->prepare("
          SELECT 
                ft.*,
                p.prod_descricao AS produto_nome,
                p.prod_tipo AS produto_tipo,
                p.prod_total_pecas AS produto_total_pecas,
                p.prod_codigo_interno AS prod_codigo_interno,
                p.prod_marca AS produto_marca,
                p.prod_classe AS produto_denominacao,
                p.prod_especie AS produto_especie,
                p.prod_origem AS produto_origem,
                p.prod_validade_meses AS produto_validade,
                p.prod_classificacao AS produto_classificacao,
                p.prod_peso_embalagem AS peso_embalagem,
                p.prod_ncm AS produto_ncm,
                CONCAT(COALESCE(NULLIF(e.ent_nome_fantasia, ''), e.ent_razao_social), ' (Cód: ', e.ent_codigo_interno, ')') AS fabricante_nome,
                CONCAT(COALESCE(NULLIF(e.ent_nome_fantasia, ''), e.ent_razao_social)) AS fabricante_unidade,
                CONCAT(ee.end_cidade, ' - ', ee.end_uf) AS fabricante_endereco,
                p2.prod_peso_embalagem AS peso_embalagem_primaria,
                p2.prod_ean13 AS ean13
            FROM 
                tbl_fichas_tecnicas ft
            JOIN 
                tbl_produtos p ON ft.ficha_produto_id = p.prod_codigo
            LEFT JOIN 
                tbl_entidades e ON ft.ficha_fabricante_id = e.ent_codigo
            LEFT JOIN 
                tbl_enderecos ee ON ft.ficha_fabricante_id = ee.end_entidade_id
            LEFT JOIN
                tbl_produtos p2 ON p.prod_primario_id = p2.prod_codigo
            WHERE 
                ft.ficha_id = :id
        ");
        $stmtHeader->execute([':id' => $fichaId]);
        $ficha['header'] = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$ficha['header']) {
            return null;
        }

        $stmtCriterios = $this->pdo->prepare("SELECT * FROM tbl_fichas_tecnicas_criterios WHERE criterio_ficha_id = :id ORDER BY criterio_id");
        $stmtCriterios->execute([':id' => $fichaId]);
        $ficha['criterios'] = $stmtCriterios->fetchAll(PDO::FETCH_ASSOC);
        $ficha['fotos'] = [];

        return $ficha;
    }

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

    public function saveHeader(array $data, int $usuarioId): int
    {
        $fichaId = !empty($data['ficha_id']) ? (int) $data['ficha_id'] : null;

        if (empty($data['ficha_produto_id'])) {
            throw new Exception("O campo 'Produto' é obrigatório.");
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
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Erro em saveHeader FichaTecnica: " . $e->getMessage());
            throw new Exception("Erro ao salvar os dados no banco. Verifique os logs do servidor.");
        }
    }

    /**
     * Exclui uma Ficha Técnica e seus registros associados (via cascade do BD).
     *
     * @param int $fichaId O ID da ficha a ser excluída.
     * @return bool
     * @throws Exception
     */
    public function delete(int $fichaId): bool
    {
        // Log de auditoria antes de deletar, para termos o registro do que foi apagado
        $dadosAntigos = $this->findCompletaById($fichaId)['header'] ?? null;
        if ($dadosAntigos) {
            $this->auditLogger->log('DELETE', $fichaId, 'tbl_fichas_tecnicas', $dadosAntigos, null,"");
        }

        $stmt = $this->pdo->prepare("DELETE FROM tbl_fichas_tecnicas WHERE ficha_id = :id");
        $stmt->bindValue(':id', $fichaId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Busca todos os critérios de uma ficha técnica específica.
     * @param int $fichaId
     * @return array
     */
    public function getCriteriosByFichaId(int $fichaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tbl_fichas_tecnicas_criterios
            WHERE criterio_ficha_id = :ficha_id
            ORDER BY criterio_grupo, criterio_nome
        ");
        $stmt->execute([':ficha_id' => $fichaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Salva (cria ou atualiza) um critério.
     * @param array $data
     * @return int ID do critério salvo.
     */
    public function saveCriterio(array $data): int
    {
        $id = filter_var($data['criterio_id'] ?? null, FILTER_VALIDATE_INT);

        $params = [
            ':ficha_id' => $data['criterio_ficha_id'],
            ':grupo' => $data['criterio_grupo'],
            ':nome' => $data['criterio_nome'],
            ':unidade' => $data['criterio_unidade'] ?: null,
            ':valor' => $data['criterio_valor']
        ];

        if ($id) {
            // Lógica de UPDATE
            $sql = "UPDATE tbl_fichas_tecnicas_criterios SET
                        criterio_ficha_id = :ficha_id,
                        criterio_grupo = :grupo,
                        criterio_nome = :nome,
                        criterio_unidade = :unidade,
                        criterio_valor = :valor
                    WHERE criterio_id = :id";
            $params[':id'] = $id;
        } else {
            // Lógica de INSERT
            $sql = "INSERT INTO tbl_fichas_tecnicas_criterios
                        (criterio_ficha_id, criterio_grupo, criterio_nome, criterio_unidade, criterio_valor)
                    VALUES
                        (:ficha_id, :grupo, :nome, :unidade, :valor)";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $id ?: (int) $this->pdo->lastInsertId();
    }

    /**
     * Exclui um critério específico.
     * @param int $criterioId
     * @return bool
     */
    public function deleteCriterio(int $criterioId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tbl_fichas_tecnicas_criterios WHERE criterio_id = :id");
        return $stmt->execute([':id' => $criterioId]);
    }


    /**
     * Busca todos os caminhos de fotos de uma ficha técnica.
     * @param int $fichaId
     * @return array
     */
    public function getFotosByFichaId(int $fichaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT foto_tipo, foto_path 
            FROM tbl_fichas_tecnicas_fotos
            WHERE foto_ficha_id = :ficha_id
        ");
        $stmt->execute([':ficha_id' => $fichaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Salva o caminho de uma foto no banco, garantindo que só haja uma por tipo.
     * @param int $fichaId
     * @param string $fotoTipo
     * @param string $filePath
     * @return bool
     */
    public function saveFotoPath(int $fichaId, string $fotoTipo, string $filePath): bool
    {
        $sql = "INSERT INTO tbl_fichas_tecnicas_fotos (foto_ficha_id, foto_tipo, foto_path)
                VALUES (:ficha_id, :tipo, :path)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':ficha_id' => $fichaId,
            ':tipo' => $fotoTipo,
            ':path' => $filePath
        ]);
    }

    /**
     * Exclui o registro de uma foto do banco de dados e retorna seu caminho.
     * @param int $fichaId
     * @param string $fotoTipo
     * @return string|null O caminho do arquivo que foi removido do banco.
     */
    public function deleteFoto(int $fichaId, string $fotoTipo): ?string
    {
        // Primeiro, busca o caminho do arquivo para poder retorná-lo
        $stmtSelect = $this->pdo->prepare("
            SELECT foto_path FROM tbl_fichas_tecnicas_fotos 
            WHERE foto_ficha_id = :ficha_id AND foto_tipo = :tipo
        ");
        $stmtSelect->execute([':ficha_id' => $fichaId, ':tipo' => $fotoTipo]);
        $path = $stmtSelect->fetchColumn();

        // Se encontrou um registro, apaga
        if ($path) {
            $stmtDelete = $this->pdo->prepare("
                DELETE FROM tbl_fichas_tecnicas_fotos 
                WHERE foto_ficha_id = :ficha_id AND foto_tipo = :tipo
            ");
            $stmtDelete->execute([':ficha_id' => $fichaId, ':tipo' => $fotoTipo]);
        }

        return $path ?: null;
    }

    /**
     * Busca o código interno do produto associado a uma ficha técnica.
     * @param int $fichaId
     * @return string|null
     */
    public function getCodigoInternoProdutoByFichaId(int $fichaId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT p.prod_codigo_interno
            FROM tbl_fichas_tecnicas ft
            JOIN tbl_produtos p ON ft.ficha_produto_id = p.prod_codigo
            WHERE ft.ficha_id = :id
        ");
        $stmt->execute([':id' => $fichaId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Gera o relatório PDF (chamando os serviços), salva na pasta de mídias e retorna o caminho público.
     * @param int $fichaId
     * @return string Caminho relativo público do arquivo PDF salvo.
     * @throws Exception
     */
    public function gerarRelatorioPdf(int $fichaId): string
    {
        // 1. Busca os dados da ficha para obter o código do produto
        $fichaData = $this->findCompletaById($fichaId);
        if (!$fichaData || !$fichaData['header']) {
            throw new Exception("Ficha Técnica não encontrada.");
        }
        $codigoInternoProduto = $fichaData['header']['prod_codigo_interno'];

        if (empty($codigoInternoProduto)) {
            throw new Exception("O produto associado não possui código interno para salvar o relatório.");
        }

        // 2. Geração do HTML (usando o Template Service)
        $htmlService = new FichaTecnicaHtmlService($this); // $this é a instância do Repository
        $htmlContent = $htmlService->renderHtml($fichaId);

        // 3. Conversão de HTML para PDF (usando o Serviço Global)
        $relatorioService = new RelatorioService();

        try {
            $pdfContent = $relatorioService->generatePdfContent($htmlContent);
        } catch (Exception $e) {
            // Captura erros específicos de DomPDF
            throw new Exception("Erro na conversão para PDF: " . $e->getMessage());
        }

        // 4. Define o caminho de armazenamento
        $nomePasta = preg_replace('/[^a-zA-Z0-9_-]/', '', $codigoInternoProduto);
        $pastaProdutoDir = __DIR__ . '/../../public/uploads/fichas_tecnicas/' . $nomePasta . '/';

        // Cria a pasta se não existir
        if (!is_dir($pastaProdutoDir)) {
            mkdir($pastaProdutoDir, 0775, true);
        }

        $nomeArquivo = "FICHA_TECNICA_" . $nomePasta . ".pdf";
        $caminhoCompleto = $pastaProdutoDir . $nomeArquivo;
        $caminhoPublico = 'uploads/fichas_tecnicas/' . $nomePasta . '/' . $nomeArquivo;

        // 5. Salva o conteúdo binário no disco (CACHE)
        if (file_put_contents($caminhoCompleto, $pdfContent) === false) {
            throw new Exception("Falha ao salvar o arquivo PDF em disco. Verifique as permissões da pasta: {$pastaProdutoDir}");
        }

        return $caminhoPublico;
    }
}
