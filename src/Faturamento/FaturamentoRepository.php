<?php
// /src/Faturamento/FaturamentoRepository.php
namespace App\Faturamento;

use PDO;
use App\Core\AuditLoggerService;

class FaturamentoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Busca todos os itens de uma Ordem de Expedição e os agrupa para o faturamento.
     * @param int $ordemId
     * @return array
     */
    public function getDadosAgrupadosPorOrdemExpedicao(int $ordemId): array
    {
        $sql = "
            SELECT
                -- Nível 1: FAZENDA (Cliente do Lote)
                fazenda.ent_codigo AS fazenda_id, -- CORRIGIDO DE ent_id PARA ent_codigo
                COALESCE(fazenda.ent_nome_fantasia, fazenda.ent_razao_social) AS fazenda_nome,
                
                -- Nível 2: CLIENTE (Comprador)
                cliente.ent_codigo AS cliente_id, -- CORRIGIDO DE ent_id PARA ent_codigo
                COALESCE(cliente.ent_nome_fantasia, cliente.ent_razao_social) AS cliente_nome,
                
                -- Nível 3: PEDIDO
                oep.oep_numero_pedido,
                
                -- Nível 4: PRODUTO
                p_sec.prod_codigo AS produto_id,
                p_sec.prod_descricao AS produto_descricao,
                
                -- Nível 5: LOTE
                lnh.lote_id,
                lnh.lote_completo_calculado,
                
                -- Dados Agregados
                SUM(oei.oei_quantidade) AS total_caixas,
                SUM(oei.oei_quantidade * p_sec.prod_peso_embalagem) AS total_quilos
            
            FROM tbl_ordens_expedicao_itens oei
            
            JOIN tbl_ordens_expedicao_pedidos oep ON oei.oei_pedido_id = oep.oep_id
            JOIN tbl_entidades cliente ON oep.oep_cliente_id = cliente.ent_codigo
            
            JOIN tbl_estoque_alocacoes ea ON oei.oei_alocacao_id = ea.alocacao_id
            JOIN tbl_lotes_novo_embalagem lne ON ea.alocacao_lote_item_id = lne.item_emb_id
            JOIN tbl_produtos p_sec ON lne.item_emb_prod_sec_id = p_sec.prod_codigo
            JOIN tbl_lotes_novo_header lnh ON lne.item_emb_lote_id = lnh.lote_id
            
            LEFT JOIN tbl_entidades fazenda ON lnh.lote_cliente_id = fazenda.ent_codigo
            
            WHERE oep.oep_ordem_id = :ordem_id
            
            GROUP BY
                fazenda.ent_codigo, -- CORRIGIDO
                cliente.ent_codigo, -- CORRIGIDO
                oep.oep_numero_pedido,
                p_sec.prod_codigo,
                lnh.lote_id
                
            ORDER BY
                fazenda_nome,
                cliente_nome,
                oep.oep_numero_pedido,
                produto_descricao
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ordem_id' => $ordemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gera e salva um novo resumo de faturamento a partir de uma Ordem de Expedição.
     * @param int $ordemId
     * @param int $usuarioId
     * @return int O ID do novo resumo de faturamento criado.
     * @throws \Exception
     */
    /*  public function salvarResumo(int $ordemId, int $usuarioId): int
      {
          // 1. Prevenção de duplicatas: Verifica se já existe um resumo para esta Ordem
          $stmtCheck = $this->pdo->prepare("SELECT fat_id FROM tbl_faturamento_resumos WHERE fat_ordem_expedicao_id = :ordem_id");
          $stmtCheck->execute([':ordem_id' => $ordemId]);
          if ($stmtCheck->fetch()) {
              throw new \Exception("Já existe um resumo de faturamento gerado para esta Ordem de Expedição.");
          }

          // 2. Busca os dados agrupados que vamos salvar
          $itensAgrupados = $this->getDadosAgrupadosPorOrdemExpedicao($ordemId);
          if (empty($itensAgrupados)) {
              throw new \Exception("Não há itens nesta Ordem de Expedição para gerar um resumo.");
          }

          // 3. Inicia uma transação para garantir a integridade dos dados
          $this->pdo->beginTransaction();
          try {
              // 4. Insere o cabeçalho do resumo
              $sqlHeader = "INSERT INTO tbl_faturamento_resumos (fat_ordem_expedicao_id, fat_usuario_id, fat_status) 
                            VALUES (:ordem_id, :usuario_id, 'EM ELABORAÇÃO')";
              $stmtHeader = $this->pdo->prepare($sqlHeader);
              $stmtHeader->execute([
                  ':ordem_id' => $ordemId,
                  ':usuario_id' => $usuarioId
              ]);
              $novoResumoId = (int) $this->pdo->lastInsertId();

              // 5. Prepara a query para inserir os itens
              $sqlItens = "INSERT INTO tbl_faturamento_itens 
                              (fati_resumo_id, fati_fazenda_id, fati_cliente_id, fati_numero_pedido, fati_produto_id, fati_lote_id, fati_qtd_caixas, fati_qtd_quilos) 
                           VALUES 
                              (:resumo_id, :fazenda_id, :cliente_id, :numero_pedido, :produto_id, :lote_id, :qtd_caixas, :qtd_quilos)";
              $stmtItens = $this->pdo->prepare($sqlItens);

              // 6. Loop para inserir cada item agrupado
              foreach ($itensAgrupados as $item) {
                  $stmtItens->execute([
                      ':resumo_id' => $novoResumoId,
                      ':fazenda_id' => $item['fazenda_id'],
                      ':cliente_id' => $item['cliente_id'],
                      ':numero_pedido' => $item['oep_numero_pedido'],
                      ':produto_id' => $item['produto_id'],
                      ':lote_id' => $item['lote_id'],
                      ':qtd_caixas' => $item['total_caixas'],
                      ':qtd_quilos' => $item['total_quilos']
                  ]);
              }

              // 7. Se tudo correu bem, confirma a transação
              $this->pdo->commit();

              // Log de auditoria (opcional, mas bom ter)
              $this->auditLogger->log('CREATE', $novoResumoId, 'tbl_faturamento_resumos', null, ['ordem_id' => $ordemId]);

              return $novoResumoId;

          } catch (\Exception $e) {
              // 8. Se algo deu errado, desfaz tudo
              $this->pdo->rollBack();
              throw $e; // Re-lança a exceção para ser capturada pelo controller
          }
      } */

    /**
     * Busca os detalhes de um item de faturamento específico.
     * @param int $fatiId
     * @return array|null
     */
    public function findItemDetalhes(int $fatiId): ?array
    {
        // Precisamos dos nomes (descrições) para exibir no modal
        $sql = "SELECT 
                    f.*,
                    p.prod_descricao,
                    lnh.lote_completo_calculado
                FROM tbl_faturamento_itens f
                JOIN tbl_produtos p ON f.fati_produto_id = p.prod_codigo
                JOIN tbl_lotes_novo_header lnh ON f.fati_lote_id = lnh.lote_id
                WHERE f.fati_id = :fati_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fati_id' => $fatiId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualiza os dados de preço e observação de um item de faturamento.
     * @param int $fatiId
     * @param array $data
     * @return bool
     */
    /* public function updateItem(int $fatiId, array $data): bool
     {
         $dadosAntigos = $this->findItemDetalhes($fatiId);

         $sql = "UPDATE tbl_faturamento_itens SET
                     fati_preco_unitario = :preco,
                     fati_preco_unidade_medida = :unidade,
                     fati_observacao = :observacao
                 WHERE fati_id = :fati_id";

         $stmt = $this->pdo->prepare($sql);
         $success = $stmt->execute([
             ':preco' => $data['fati_preco_unitario'] ?: null,
             ':unidade' => $data['fati_preco_unidade_medida'],
             ':observacao' => $data['fati_observacao'] ?: null,
             ':fati_id' => $fatiId
         ]);

         $this->auditLogger->log('UPDATE', $fatiId, 'tbl_faturamento_itens', $dadosAntigos, $data);
         return $success;
     } */

    /**
     * Atualiza os dados de preço e observação de um item de faturamento.
     * @param int $fatiId
     * @param array $data
     * @return bool
     */
    public function updateItem(int $fatiId, array $data): bool
    {
        $dadosAntigos = $this->findItemDetalhes($fatiId);

        // SQL CORRIGIDO: Removida a 'fati_observacao' do UPDATE
        $sql = "UPDATE tbl_faturamento_itens SET
                    fati_preco_unitario = :preco,
                    fati_preco_unidade_medida = :unidade
                WHERE fati_id = :fati_id";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':preco' => $data['fati_preco_unitario'] ?: null,
            ':unidade' => $data['fati_preco_unidade_medida'],
            ':fati_id' => $fatiId
            // Parâmetro ':observacao' removido
        ]);

        // A auditoria ainda registra a mudança completa (o $_POST)
        $this->auditLogger->log('UPDATE', $fatiId, 'tbl_faturamento_itens', $dadosAntigos, $data);
        return $success;
    }

    /**
     * GERA E SALVA um novo resumo de faturamento NA NOVA ESTRUTURA DE 3 TABELAS.
     * @param int $ordemId
     * @param int $usuarioId
     * @return int O ID do novo resumo de faturamento criado (da tabela header).
     * @throws \Exception
     */
    public function salvarResumo(int $ordemId, int $usuarioId): int
    {
        // 1. Prevenção de duplicatas 
        $stmtCheck = $this->pdo->prepare("SELECT fat_id FROM tbl_faturamento_resumos WHERE fat_ordem_expedicao_id = :ordem_id");
        $stmtCheck->execute([':ordem_id' => $ordemId]);
        if ($stmtCheck->fetch()) {
            throw new \Exception("Já existe um resumo de faturamento gerado para esta Ordem de Expedição.");
        }

        // 2. Busca os dados agrupados 
        $itensAgrupados = $this->getDadosAgrupadosPorOrdemExpedicao($ordemId);
        if (empty($itensAgrupados)) {
            throw new \Exception("Não há itens nesta Ordem de Expedição para gerar um resumo.");
        }

        $this->pdo->beginTransaction();
        try {
            // 3. Insere o cabeçalho (Tabela 1: Resumos)
            $sqlHeader = "INSERT INTO tbl_faturamento_resumos (fat_ordem_expedicao_id, fat_usuario_id, fat_status) 
                          VALUES (:ordem_id, :usuario_id, 'EM ELABORAÇÃO')";
            $stmtHeader = $this->pdo->prepare($sqlHeader);
            $stmtHeader->execute([':ordem_id' => $ordemId, ':usuario_id' => $usuarioId]);
            $novoResumoId = (int) $this->pdo->lastInsertId();

            // Prepara as queries para as tabelas filhas
            $sqlNotaGrupo = "INSERT INTO tbl_faturamento_notas_grupo 
                                (fatn_resumo_id, fatn_cliente_id, fatn_numero_pedido) 
                             VALUES (:resumo_id, :cliente_id, :pedido_num)";
            $stmtNotaGrupo = $this->pdo->prepare($sqlNotaGrupo);

            $sqlItens = "INSERT INTO tbl_faturamento_itens 
                            (fati_nota_id, fati_fazenda_id, fati_produto_id, fati_lote_id, fati_qtd_caixas, fati_qtd_quilos) 
                         VALUES 
                            (:nota_id, :fazenda_id, :produto_id, :lote_id, :qtd_caixas, :qtd_quilos)";
            $stmtItens = $this->pdo->prepare($sqlItens);

            $currentClientePedidoKey = null;
            $currentNotaId = null;

            // 4. Loop pelos itens agrupados
            foreach ($itensAgrupados as $item) {
                $itemKey = $item['cliente_id'] . '-' . $item['oep_numero_pedido'];

                // 5. Verifica se é um novo grupo de Cliente/Pedido
                if ($itemKey !== $currentClientePedidoKey) {
                    // É um novo grupo, então cria a "Nota" (Tabela 2: Notas Grupo)
                    $stmtNotaGrupo->execute([
                        ':resumo_id' => $novoResumoId,
                        ':cliente_id' => $item['cliente_id'],
                        ':pedido_num' => $item['oep_numero_pedido']
                    ]);
                    $currentNotaId = (int) $this->pdo->lastInsertId();
                    $currentClientePedidoKey = $itemKey;
                }

                // 6. Insere o item (Tabela 3: Itens) ligado à "Nota" que acabamos de criar
                $stmtItens->execute([
                    ':nota_id' => $currentNotaId, // <-- Chave estrangeira para a tabela do meio
                    ':fazenda_id' => $item['fazenda_id'],
                    ':produto_id' => $item['produto_id'],
                    ':lote_id' => $item['lote_id'],
                    ':qtd_caixas' => $item['total_caixas'],
                    ':qtd_quilos' => $item['total_quilos']
                ]);
            }

            $this->pdo->commit();
            $this->auditLogger->log('CREATE', $novoResumoId, 'tbl_faturamento_resumos', null, ['ordem_id' => $ordemId]);
            return $novoResumoId;

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Busca um resumo salvo para exibição na tela.
     * @param int $resumoId
     * @return array
     */
    public function getResumoSalvo(int $resumoId): array
    {
        $dados = [
            'header' => null,
            'notas' => [] // Vamos retornar uma estrutura aninhada
        ];

        // 1. Busca o cabeçalho do resumo (Tabela 1) e dados da Transportadora
        $sqlHeader = "SELECT 
                        fr.*, 
                        oeh.oe_numero AS ordem_expedicao_numero,
                        t.ent_nome_fantasia AS transportadora_nome
                    FROM tbl_faturamento_resumos fr
                    JOIN tbl_ordens_expedicao_header oeh ON fr.fat_ordem_expedicao_id = oeh.oe_id
                    LEFT JOIN tbl_entidades t ON fr.fat_transportadora_id = t.ent_codigo
                    WHERE fr.fat_id = :resumo_id";
        $stmtHeader = $this->pdo->prepare($sqlHeader);
        $stmtHeader->execute([':resumo_id' => $resumoId]);
        $dados['header'] = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$dados['header']) {
            throw new \Exception("Resumo de faturamento não encontrado.");
        }

        // 2. Busca todos os grupos de NOTA (Tabela 2) e seus ITENS (Tabela 3)
        // Precisamos organizar por Fazenda
        $sqlNotasEItens = "
            SELECT
                nota.fatn_id,
                nota.fatn_numero_pedido,
                nota.fatn_observacao,
                cliente.ent_nome_fantasia AS cliente_nome,
                cond.cond_descricao AS condicao_pag_descricao,
                
                item.fati_id,
                item.fati_qtd_caixas,
                item.fati_qtd_quilos,
                item.fati_preco_unitario,
                item.fati_preco_unidade_medida,
                
                fazenda.ent_nome_fantasia AS fazenda_nome,
                prod.prod_descricao AS produto_descricao,
                lote.lote_completo_calculado
                
            FROM tbl_faturamento_notas_grupo nota
            JOIN tbl_faturamento_itens item ON nota.fatn_id = item.fati_nota_id
            JOIN tbl_entidades cliente ON nota.fatn_cliente_id = cliente.ent_codigo
            JOIN tbl_entidades fazenda ON item.fati_fazenda_id = fazenda.ent_codigo
            JOIN tbl_produtos prod ON item.fati_produto_id = prod.prod_codigo
            JOIN tbl_lotes_novo_header lote ON item.fati_lote_id = lote.lote_id
            LEFT JOIN tbl_condicoes_pagamento cond ON nota.fatn_condicao_pag_id = cond.cond_id
            
            WHERE nota.fatn_resumo_id = :resumo_id
            
            ORDER BY
                fazenda_nome,
                cliente_nome,
                nota.fatn_numero_pedido,
                prod.prod_descricao
        ";

        $stmtNotas = $this->pdo->prepare($sqlNotasEItens);
        $stmtNotas->execute([':resumo_id' => $resumoId]);

        $itensDoResumo = $stmtNotas->fetchAll(PDO::FETCH_ASSOC);

        // 3. Agora, vamos processar essa lista de itens em uma hierarquia (PHP)
        $notasAgrupadas = [];
        foreach ($itensDoResumo as $row) {
            // A chave da "Nota" é o ID dela
            $notaKey = $row['fatn_id'];

            // Se ainda não vimos essa nota, criamos o cabeçalho dela
            if (!isset($notasAgrupadas[$notaKey])) {
                $notasAgrupadas[$notaKey] = [
                    'fatn_id' => $row['fatn_id'],
                    'cliente_nome' => $row['cliente_nome'],
                    'numero_pedido' => $row['fatn_numero_pedido'],
                    'condicao_pagamento' => $row['condicao_pag_descricao'], // Já vem pronta
                    'observacao' => $row['fatn_observacao'],
                    'fazenda_nome_principal' => $row['fazenda_nome'], // Usado para agrupar por Fazenda
                    'itens' => []
                ];
            }

            // Adiciona o item dentro da sua respectiva nota
            $notasAgrupadas[$notaKey]['itens'][] = $row;
        }

        // 4. Finalmente, agrupamos as NOTAS por FAZENDA
        $gruposDeFazenda = [];
        foreach ($notasAgrupadas as $nota) {
            $fazendaNome = $nota['fazenda_nome_principal'];
            if (!isset($gruposDeFazenda[$fazendaNome])) {
                $gruposDeFazenda[$fazendaNome] = [];
            }
            $gruposDeFazenda[$fazendaNome][] = $nota;
        }

        $dados['grupos_fazenda'] = $gruposDeFazenda;
        return $dados;
    }

    /**
     * Busca todos os Resumos de Faturamento para a DataTable.
     */
    public function findAllForDataTable(array $params): array
    {
        $baseQuery = "FROM tbl_faturamento_resumos fr
                      JOIN tbl_usuarios u ON fr.fat_usuario_id = u.usu_codigo
                      JOIN tbl_ordens_expedicao_header oeh ON fr.fat_ordem_expedicao_id = oeh.oe_id";

        $totalRecords = $this->pdo->query("SELECT COUNT(fr.fat_id) FROM tbl_faturamento_resumos fr")->fetchColumn();

        $sqlData = "SELECT 
                        fr.fat_id,
                        oeh.oe_numero AS ordem_numero,
                        fr.fat_data_geracao,
                        fr.fat_status,
                        u.usu_nome AS usuario_nome
                    $baseQuery 
                    ORDER BY fr.fat_id DESC
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
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

    /**
     * FUNÇÃO PÚBLICA DE DADOS
     * Busca todos os dados de um resumo, enriquecidos com dados cadastrais completos 
     * dos clientes para a geração de relatórios.
     * * @param int $resumoId
     * @return array
     */
    /* public function getDadosCompletosParaRelatorio(int $resumoId): array
     {
         $sql = "
             SELECT
                 f.*, 
                 fazenda.ent_razao_social AS fazenda_razao_social,
                 fazenda.ent_nome_fantasia AS fazenda_nome,

                 -- Dados completos do Cliente Final (para a Nota Fiscal)
                 cliente.ent_razao_social AS cliente_razao_social,
                 cliente.ent_nome_fantasia AS cliente_nome,
                 cliente.ent_cnpj,
                 cliente.ent_inscricao_estadual,
                 endr.end_logradouro,
                 endr.end_numero,
                 endr.end_bairro,
                 endr.end_cidade,
                 endr.end_uf,
                 endr.end_cep,

                 f.fati_numero_pedido,
                 p.prod_descricao AS produto_descricao,
                 lnh.lote_completo_calculado
             FROM tbl_faturamento_itens f

             -- Joins para Fazenda e Produto/Lote
             JOIN tbl_entidades fazenda ON f.fati_fazenda_id = fazenda.ent_codigo
             JOIN tbl_produtos p ON f.fati_produto_id = p.prod_codigo
             JOIN tbl_lotes_novo_header lnh ON f.fati_lote_id = lnh.lote_id

             -- Joins para Cliente Final e seu Endereço Principal
             JOIN tbl_entidades cliente ON f.fati_cliente_id = cliente.ent_codigo
             LEFT JOIN tbl_enderecos endr ON cliente.ent_codigo = endr.end_entidade_id AND endr.end_tipo_endereco = 'Principal'

             WHERE f.fati_resumo_id = :resumo_id

             -- Ordenação crucial para o relatório
             ORDER BY
                 fazenda_nome,
                 cliente_nome,
                 f.fati_numero_pedido,
                 produto_descricao
         ";
         $stmt = $this->pdo->prepare($sql);
         $stmt->execute([':resumo_id' => $resumoId]);
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
     } */

    /**
     * FUNÇÃO PÚBLICA DE DADOS (CORRIGIDA)
     * Busca todos os dados de um resumo, enriquecidos com dados cadastrais completos 
     * dos clientes para a geração de relatórios.
     * * @param int $resumoId
     * @return array
     */
    public function getDadosCompletosParaRelatorio(int $resumoId): array
    {
        // SQL CORRIGIDO PARA A ESTRUTURA DE 3 TABELAS
        $sql = "
            SELECT
                -- Nível 3 (Item)
                item.fati_id,
                item.fati_qtd_caixas,
                item.fati_qtd_quilos,
                item.fati_preco_unitario,
                item.fati_preco_unidade_medida,
                
                -- Nível 2 (Nota/Pedido)
                nota.fatn_numero_pedido,
                nota.fatn_observacao,
                cond.cond_descricao AS condicao_pag_descricao,
                
                -- Dados da Fazenda (do Item)
                fazenda.ent_razao_social AS fazenda_razao_social,
                fazenda.ent_nome_fantasia AS fazenda_nome,
                
                -- Dados do Produto/Lote (do Item)
                prod.prod_descricao AS produto_descricao,
                lote.lote_completo_calculado,
                
                -- Dados completos do Cliente (da Nota)
                cliente.ent_razao_social AS cliente_razao_social,
                cliente.ent_nome_fantasia AS cliente_nome,
                cliente.ent_cnpj,
                cliente.ent_inscricao_estadual,
                
                -- Dados do Endereço (do Cliente)
                endr.end_logradouro,
                endr.end_numero,
                endr.end_bairro,
                endr.end_cidade,
                endr.end_uf,
                endr.end_cep
                
            FROM tbl_faturamento_notas_grupo nota -- COMEÇA PELA TABELA DO MEIO
            
            -- Joins para baixo (itens) e seus dados
            JOIN tbl_faturamento_itens item ON nota.fatn_id = item.fati_nota_id
            JOIN tbl_entidades fazenda ON item.fati_fazenda_id = fazenda.ent_codigo
            JOIN tbl_produtos prod ON item.fati_produto_id = prod.prod_codigo
            JOIN tbl_lotes_novo_header lote ON item.fati_lote_id = lote.lote_id
            
            -- Joins para cima (cliente) e seus dados
            JOIN tbl_entidades cliente ON nota.fatn_cliente_id = cliente.ent_codigo
            LEFT JOIN tbl_condicoes_pagamento cond ON nota.fatn_condicao_pag_id = cond.cond_id
            LEFT JOIN tbl_enderecos endr ON cliente.ent_codigo = endr.end_entidade_id AND endr.end_tipo_endereco = 'Principal'
            
            WHERE nota.fatn_resumo_id = :resumo_id -- Filtra pelo ID do Resumo na tabela 'nota'
            
            -- Ordenação crucial para o relatório
            ORDER BY
                fazenda_nome,
                cliente_nome,
                nota.fatn_numero_pedido,
                prod.prod_descricao
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':resumo_id' => $resumoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* public function findResumoHeaderInfo(int $resumoId): ?array
     {
         $sql = "SELECT 
                     fr.*,  -- Pega todos os campos do resumo, incluindo os novos de transporte
                     oeh.oe_numero AS ordem_expedicao_numero,
                     t.ent_nome_fantasia AS transportadora_nome,
                     t.ent_razao_social AS transportadora_razao,
                     u.usu_nome AS usuario_nome
                 FROM tbl_faturamento_resumos fr
                 JOIN tbl_ordens_expedicao_header oeh ON fr.fat_ordem_expedicao_id = oeh.oe_id
                 JOIN tbl_usuarios u ON fr.fat_usuario_id = u.usu_codigo
                 LEFT JOIN tbl_entidades t ON fr.fat_transportadora_id = t.ent_codigo
                 WHERE fr.fat_id = :resumo_id";

         $stmt = $this->pdo->prepare($sql);
         $stmt->execute([':resumo_id' => $resumoId]);
         return $stmt->fetch(PDO::FETCH_ASSOC);
     } */

    /**
     * Busca todas as Condições de Pagamento ativas para um dropdown (Select2).
     * @return array
     */
    public function getCondicoesPagamentoOptions(): array
    {
        $sql = "SELECT cond_id AS id, CONCAT(cond_descricao, ' (Cód: ', cond_codigo, ')') AS text 
                 FROM tbl_condicoes_pagamento 
                 WHERE cond_ativo = 1 
                 ORDER BY cond_descricao";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca os dados de cabeçalho de um resumo e sua ordem de origem.
     * (ATUALIZADA PARA INCLUIR DADOS CADASTRAIS DA TRANSPORTADORA)
     * @param int $resumoId
     * @return array|null
     */
    public function findResumoHeaderInfo(int $resumoId): ?array
    {
        $sql = "SELECT 
                    fr.*,
                    oeh.oe_numero AS ordem_expedicao_numero,
                    u.usu_nome AS usuario_nome,
                    
                    -- Dados completos da Transportadora
                    t.ent_razao_social AS transportadora_razao,
                    t.ent_nome_fantasia AS transportadora_nome,
                    t.ent_cnpj AS transportadora_cnpj,
                    t.ent_inscricao_estadual AS transportadora_ie,
                    tend.end_logradouro AS transportadora_end_logradouro,
                    tend.end_numero AS transportadora_end_numero,
                    tend.end_bairro AS transportadora_end_bairro,
                    tend.end_cidade AS transportadora_end_cidade,
                    tend.end_uf AS transportadora_end_uf,
                    tend.end_cep AS transportadora_end_cep
                    
                FROM tbl_faturamento_resumos fr
                JOIN tbl_ordens_expedicao_header oeh ON fr.fat_ordem_expedicao_id = oeh.oe_id
                JOIN tbl_usuarios u ON fr.fat_usuario_id = u.usu_codigo
                
                -- Join para Transportadora (entidade)
                LEFT JOIN tbl_entidades t ON fr.fat_transportadora_id = t.ent_codigo
                -- Join para Endereço da Transportadora (usando alias 'tend' para diferenciar)
                LEFT JOIN tbl_enderecos tend ON t.ent_codigo = tend.end_entidade_id AND tend.end_tipo_endereco = 'Principal'
                
                WHERE fr.fat_id = :resumo_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':resumo_id' => $resumoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca os detalhes de um único grupo de Nota (para o modal de edição de Cond/Obs).
     * @param int $fatnId
     * @return array|null
     */
    public function getNotaGrupoDetalhes(int $fatnId): ?array
    {
        $sql = "SELECT 
                    nota.fatn_id,
                    nota.fatn_condicao_pag_id,
                    nota.fatn_observacao,
                    cliente.ent_nome_fantasia AS cliente_nome,
                    nota.fatn_numero_pedido
                FROM tbl_faturamento_notas_grupo nota
                JOIN tbl_entidades cliente ON nota.fatn_cliente_id = cliente.ent_codigo
                WHERE nota.fatn_id = :fatn_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fatn_id' => $fatnId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza a Condição de Pagamento e a Observação de um grupo de Nota.
     * @param int $fatnId
     * @param array $data
     * @return bool
     */
    public function updateNotaGrupo(int $fatnId, array $data): bool
    {
        $dadosAntigos = $this->pdo->query("SELECT * FROM tbl_faturamento_notas_grupo WHERE fatn_id = $fatnId")->fetch(PDO::FETCH_ASSOC);

        $sql = "UPDATE tbl_faturamento_notas_grupo SET
                    fatn_condicao_pag_id = :condicao_id,
                    fatn_observacao = :observacao
                WHERE fatn_id = :fatn_id";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':condicao_id' => $data['fatn_condicao_pag_id'] ?: null,
            ':observacao' => $data['fatn_observacao'] ?: null,
            ':fatn_id' => $fatnId
        ]);

        $this->auditLogger->log('UPDATE', $fatnId, 'tbl_faturamento_notas_grupo', $dadosAntigos, $data);
        return $success;
    }

    /**
     * Salva os dados de transporte no cabeçalho do Resumo de Faturamento.
     * @param int $resumoId
     * @param array $data
     * @return bool
     */
    public function salvarDadosTransporte(int $resumoId, array $data): bool
    {
        $dadosAntigos = $this->findResumoHeaderInfo($resumoId); // Reutiliza a função que já temos

        $sql = "UPDATE tbl_faturamento_resumos SET
                    fat_transportadora_id = :transp_id,
                    fat_motorista_nome = :motorista_nome,
                    fat_motorista_cpf = :motorista_cpf,
                    fat_veiculo_placa = :placa
                WHERE fat_id = :resumo_id";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            ':transp_id' => $data['fat_transportadora_id'] ?: null,
            ':motorista_nome' => $data['fat_motorista_nome'] ?: null,
            ':motorista_cpf' => preg_replace('/\D/', '', $data['fat_motorista_cpf'] ?? ''), // Salva só números
            ':placa' => $data['fat_veiculo_placa'] ?: null,
            ':resumo_id' => $resumoId
        ]);

        $this->auditLogger->log('UPDATE', $resumoId, 'tbl_faturamento_resumos', $dadosAntigos, $data);
        return $success;
    }
}