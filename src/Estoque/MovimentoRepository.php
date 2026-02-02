<?php
// /src/Estoque/MovimentoRepository.php
namespace App\Estoque;

use PDO;
use Exception;

class MovimentoRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Registra uma movimentação no Kardex.
     */

    public function registrar(


        string $tipo,
        int $loteItemId,
        float $quantidade,
        int $usuarioId,
        ?int $origemId = null,
        ?int $destinoId = null,
        ?string $obs = null,
        ?int $docRef = null
    ): bool {
        // 1.  CONFIGURAÇÃO DE FUSO HORÁRIO
        // Força o PHP a usar o horário de Brasília  (ou o da região local)
        date_default_timezone_set('America/Sao_Paulo');
        $dataHoraLocal = date('Y-m-d H:i:s');

        $sql = "INSERT INTO tbl_estoque_movimento 
                    (movimento_tipo, movimento_lote_item_id, movimento_quantidade, movimento_usuario_id, 
                    movimento_endereco_origem_id, movimento_endereco_destino_id, movimento_observacao, movimento_documento_ref, movimento_data)
                    VALUES 
                    (:tipo, :item_id, :qtd, :user, :origem, :destino, :obs, :doc_ref, :data_mov)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':tipo' => $tipo,
            ':item_id' => $loteItemId,
            ':qtd' => $quantidade,
            ':user' => $usuarioId,
            ':origem' => $origemId,
            ':destino' => $destinoId,
            ':obs' => $obs,
            ':doc_ref' => $docRef,
            ':data_mov' => $dataHoraLocal
        ]);
    }

    /**
     * Busca o extrato (Kardex) de um item específico do lote.
     */
    public function getKardexPorItem(int $loteItemId): array
    {
        $sql = "SELECT m.*, 
                       u.nome_usuario,
                       eo.endereco_completo as nome_origem,
                       ed.endereco_completo as nome_destino
                FROM tbl_estoque_movimento m
                LEFT JOIN tbl_usuarios u ON m.movimento_usuario_id = u.id_usuario
                LEFT JOIN tbl_estoque_enderecos eo ON m.movimento_endereco_origem_id = eo.endereco_id
                LEFT JOIN tbl_estoque_enderecos ed ON m.movimento_endereco_destino_id = ed.endereco_id
                WHERE m.movimento_lote_item_id = :item_id
                ORDER BY m.movimento_data DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':item_id' => $loteItemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarKardexDataTable(array $params): array
    {
        // 1. Definição das colunas para ordenação (Indexadas pelo DataTables)
        $columns = [
            0 => 'm.movimento_data',
            1 => 'm.movimento_tipo',
            2 => 'p.prod_descricao',
            3 => 'eo.endereco_completo',
            4 => 'ed.endereco_completo',
            5 => 'm.movimento_quantidade',
            6 => 'u.usu_nome',
            7 => 'm.movimento_observacao'
        ];

        // 2. Query Base (JOINs necessários para transformar IDs em Nomes)
        $sqlBase = "FROM tbl_estoque_movimento m
                    JOIN tbl_lotes_novo_embalagem lne ON m.movimento_lote_item_id = lne.item_emb_id
                    JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
                    JOIN tbl_produtos p ON lne.item_emb_prod_sec_id = p.prod_codigo
                    LEFT JOIN tbl_usuarios u ON m.movimento_usuario_id = u.usu_codigo
                    LEFT JOIN tbl_estoque_enderecos eo ON m.movimento_endereco_origem_id = eo.endereco_id
                    LEFT JOIN tbl_estoque_enderecos ed ON m.movimento_endereco_destino_id = ed.endereco_id
                    WHERE 1=1 ";

        $queryParams = [];

        // -------------------------------------------------------------------
        // 3. PESQUISA GLOBAL (Campo de busca geral do DataTables)
        // -------------------------------------------------------------------
        if (!empty($params['search']['value'])) {
            $term = $params['search']['value'];
            $likeTerm = '%' . $term . '%';

            // Aqui usamos nomes DIFERENTES para cada campo (:g_lote, :g_prod, etc)
            // para evitar o erro "Invalid parameter number"
            $sqlBase .= " AND (
                lnh.lote_completo_calculado LIKE :g_lote OR 
                p.prod_descricao LIKE :g_prod OR 
                p.prod_codigo_interno LIKE :g_cod_int OR
                u.usu_nome LIKE :g_user OR
                m.movimento_observacao LIKE :g_obs
            )";

            $queryParams[':g_lote'] = $likeTerm;
            $queryParams[':g_prod'] = $likeTerm;
            $queryParams[':g_cod_int'] = $likeTerm;
            $queryParams[':g_user'] = $likeTerm;
            $queryParams[':g_obs'] = $likeTerm;
        }

        // -------------------------------------------------------------------
        // 4. FILTROS ESPECÍFICOS (Campos do topo da tela)
        // -------------------------------------------------------------------

        // Filtro Lote
        if (!empty($params['filtro_lote'])) {
            $sqlBase .= " AND lnh.lote_completo_calculado LIKE :f_lote ";
            $queryParams[':f_lote'] = '%' . $params['filtro_lote'] . '%';
        }

        // Filtro Produto
        if (!empty($params['filtro_produto'])) {
            // Note: Usei nomes distintos (:f_prod_desc, :f_prod_cod) mesmo sendo o mesmo valor
            $sqlBase .= " AND (p.prod_descricao LIKE :f_prod_desc OR p.prod_codigo_interno LIKE :f_prod_cod) ";
            $queryParams[':f_prod_desc'] = '%' . $params['filtro_produto'] . '%';
            $queryParams[':f_prod_cod'] = '%' . $params['filtro_produto'] . '%';
        }

        // Filtro Data Início
        if (!empty($params['data_inicio'])) {
            $sqlBase .= " AND DATE(m.movimento_data) >= :d_ini ";
            $queryParams[':d_ini'] = $params['data_inicio'];
        }

        // Filtro Data Fim
        if (!empty($params['data_fim'])) {
            $sqlBase .= " AND DATE(m.movimento_data) <= :d_fim ";
            $queryParams[':d_fim'] = $params['data_fim'];
        }

        // Filtro Tipo Movimento
        if (!empty($params['filtro_tipo'])) {
            $sqlBase .= " AND m.movimento_tipo = :f_tipo ";
            $queryParams[':f_tipo'] = $params['filtro_tipo'];
        }

        // -------------------------------------------------------------------
        // 5. CONTAGEM E PAGINAÇÃO
        // -------------------------------------------------------------------

        // Total Filtrado
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) " . $sqlBase);
        foreach ($queryParams as $k => $v) {
            $stmtCount->bindValue($k, $v); // Bind seguro
        }
        $stmtCount->execute();
        $totalRecords = $stmtCount->fetchColumn();

        // Ordenação
        $orderBy = " ORDER BY m.movimento_data DESC "; // Padrão
        if (isset($params['order'][0]['column'])) {
            $colIndex = $params['order'][0]['column'];
            $dir = $params['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
            if (isset($columns[$colIndex])) {
                $orderBy = " ORDER BY " . $columns[$colIndex] . " " . $dir;
            }
        }

        // Limite (Paginação)
        $limit = "";
        if (isset($params['start']) && $params['length'] != -1) {
            $limit = " LIMIT :start, :length ";
        }

        // 6. EXECUÇÃO FINAL
        $sqlFinal = "SELECT 
                        m.*,
                        p.prod_descricao as produto_descricao,
                        lnh.lote_completo_calculado as lote_numero,
                        u.usu_nome as usuario_nome,
                        COALESCE(eo.endereco_completo, 'PRODUÇÃO/EXTERNO') as origem_nome,
                        COALESCE(ed.endereco_completo, 'EXPEDIÇÃO/EXTERNO') as destino_nome
                     " . $sqlBase . $orderBy . $limit;

        $stmt = $this->pdo->prepare($sqlFinal);

        // Bind dos filtros e busca
        foreach ($queryParams as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        // Bind da paginação (Inteiros)
        if ($limit) {
            $stmt->bindValue(':start', (int)$params['start'], PDO::PARAM_INT);
            $stmt->bindValue(':length', (int)$params['length'], PDO::PARAM_INT);
        }

        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => intval($totalRecords),
            "recordsFiltered" => intval($totalRecords),
            "data" => $resultados
        ];
    }
}
