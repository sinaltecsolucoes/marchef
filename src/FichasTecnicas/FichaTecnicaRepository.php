<?php
// /src/FichasTecnicas/FichaTecnicaRepository.php
namespace App\FichasTecnicas;

use PDO;
use App\Core\AuditLoggerService;
use App\Entidades\EntidadeRepository;
use App\Produtos\ProdutoRepository;

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
     * Busca todas as Fichas Técnicas para a DataTable.
     */
    public function findAllForDataTable(array $params): array
    {
        $baseQuery = "FROM tbl_fichas_tecnicas ft
                      JOIN tbl_produtos p ON ft.ficha_produto_id = p.prod_codigo";

        $totalRecords = $this->pdo->query("SELECT COUNT(ft.ficha_id) FROM tbl_fichas_tecnicas ft")->fetchColumn();
        // Por enquanto, a contagem filtrada é a mesma
        $totalFiltered = $totalRecords;

        $sqlData = "SELECT 
                        ft.ficha_id,
                        p.prod_descricao,
                        p.prod_marca,
                        p.prod_ncm,
                        ft.ficha_data_modificacao
                    $baseQuery 
                    ORDER BY ft.ficha_id DESC
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) ($params['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($params['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data
        ];
    }

    /**
     * Busca uma Ficha Técnica completa pelo seu ID, incluindo critérios.
     */
    public function findCompletaById(int $fichaId): ?array
    {
        $ficha = [];
        // 1. Busca o cabeçalho
        $stmtHeader = $this->pdo->prepare("SELECT * FROM tbl_fichas_tecnicas WHERE ficha_id = :id");
        $stmtHeader->execute([':id' => $fichaId]);
        $ficha['header'] = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$ficha['header']) {
            return null;
        }

        // 2. Busca os critérios laboratoriais
        $stmtCriterios = $this->pdo->prepare("SELECT * FROM tbl_fichas_tecnicas_criterios WHERE criterio_ficha_id = :id ORDER BY criterio_id");
        $stmtCriterios->execute([':id' => $fichaId]);
        $ficha['criterios'] = $stmtCriterios->fetchAll(PDO::FETCH_ASSOC);

        // (No futuro, buscaremos as fotos aqui também)
        $ficha['fotos'] = [];

        return $ficha;
    }

    /**
     * Busca produtos que AINDA NÃO têm uma Ficha Técnica associada.
     * Usado para popular o dropdown de criação.
     */
    public function findProdutosSemFichaTecnica(string $term = ''): array
    {
        $params = [];
        $sqlWhereTerm = "";

        if (!empty($term)) {
            $sqlWhereTerm = " AND (p.prod_descricao LIKE :term OR p.prod_codigo_interno LIKE :term)";
            $params[':term'] = '%' . $term . '%';
        }

        $sql = "SELECT 
                    p.prod_codigo AS id,
                    CONCAT(p.prod_descricao, ' (Cód: ', COALESCE(p.prod_codigo_interno, 'N/A'), ')') AS text
                FROM tbl_produtos p
                LEFT JOIN tbl_fichas_tecnicas ft ON p.prod_codigo = ft.ficha_produto_id
                WHERE ft.ficha_id IS NULL -- A condição principal: só traz produtos sem ficha
                {$sqlWhereTerm}
                ORDER BY p.prod_descricao"; // <-- LIMIT removido

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca entidades que podem ser fabricantes (Fornecedores).
     * Reutiliza a função já existente no EntidadeRepository, mas a colocamos aqui por organização.
     */
    public function getFabricanteOptions(string $term = ''): array
    {
        $params = [];
        $sqlWhereTerm = "";

        if (!empty($term)) {
            $sqlWhereTerm = " AND (ent_nome_fantasia LIKE :term OR ent_razao_social LIKE :term OR ent_codigo_interno LIKE :term)";
            $params[':term'] = '%' . $term . '%';
        }

        $sql = "SELECT 
                    ent_codigo AS id,
                    CONCAT(COALESCE(ent_nome_fantasia, ent_razao_social), ' (Cód: ', COALESCE(ent_codigo_interno, 'N/A'), ')') AS text
                FROM tbl_entidades 
                WHERE (ent_tipo_entidade = 'Cliente' OR ent_tipo_entidade = 'Cliente e Fornecedor') 
                AND ent_situacao = 'A'
                {$sqlWhereTerm}
                ORDER BY text ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca os detalhes de um produto específico para exibir na tela da Ficha Técnica.
     */
    public function getProdutoDetalhes(int $produtoId): ?array
    {
        // Reutilizamos o ProdutoRepository que já tem a função find()
        $produtoRepo = new ProdutoRepository($this->pdo);
        return $produtoRepo->find($produtoId);
    }
}