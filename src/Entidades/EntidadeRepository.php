<?php
// /src/Entidades/EntidadeRepository.php
namespace App\Entidades;

use PDO;
use PDOException;

class EntidadeRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lógica de listar_entidades.php
     */
    public function findAllForDataTable(array $params): array
    {
        // Parâmetros do DataTables
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';
        $orderColumnIndex = $params['order'][0]['column'] ?? 3;
        $orderDir = $params['order'][0]['dir'] ?? 'asc';
        $filtroSituacao = $params['filtro_situacao'] ?? 'Todos';
        $pageType = $params['tipo_entidade'] ?? 'cliente';
        $filtroTipoEntidade = $params['filtro_tipo_entidade'] ?? 'Todos';

        $columns = ['ent_situacao', 'ent_tipo_entidade', 'ent_codigo_interno', 'ent_razao_social', 'ent_cpf', 'end_logradouro'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'ent_razao_social';

        $searchableColumns = ['ent_razao_social', 'ent_cpf', 'ent_cnpj', 'ent_codigo_interno'];

        $sqlBase = "FROM tbl_entidades ent LEFT JOIN (SELECT end_entidade_id, end_logradouro, end_numero, ROW_NUMBER() OVER(PARTITION BY end_entidade_id ORDER BY CASE end_tipo_endereco WHEN 'Principal' THEN 1 WHEN 'Comercial' THEN 2 ELSE 3 END, end_codigo ASC) as rn FROM tbl_enderecos) end ON ent.ent_codigo = end.end_entidade_id AND end.rn = 1";

        $conditions = [];
        $queryParams = [];

        if ($filtroTipoEntidade !== 'Todos') {
            $conditions[] = "ent.ent_tipo_entidade = :filtro_tipo_entidade";
            $queryParams[':filtro_tipo_entidade'] = $filtroTipoEntidade;
        } else {
            if (strtolower($pageType) === 'cliente') {
                $conditions[] = "(ent.ent_tipo_entidade = 'Cliente' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
            } elseif (strtolower($pageType) === 'fornecedor') {
                $conditions[] = "(ent.ent_tipo_entidade = 'Fornecedor' OR ent.ent_tipo_entidade = 'Cliente e Fornecedor')";
            }
        }

        if ($filtroSituacao !== 'Todos') {
            $conditions[] = "ent.ent_situacao = :filtro_situacao";
            $queryParams[':filtro_situacao'] = $filtroSituacao;
        }

        if (!empty($searchValue)) {
            $searchConditions = [];
            $searchTerm = '%' . $searchValue . '%';
            foreach ($searchableColumns as $index => $column) {
                $placeholder = ':search' . $index;
                $searchConditions[] = "$column LIKE $placeholder";
                $queryParams[$placeholder] = $searchTerm;
            }
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        $whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

        $totalRecords = $this->pdo->query("SELECT COUNT(ent_codigo) FROM tbl_entidades")->fetchColumn();
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(DISTINCT ent.ent_codigo) $sqlBase $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT ent.*, end.end_logradouro, end.end_numero $sqlBase $whereClause ORDER BY $orderColumn " . strtoupper($orderDir) . " LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
        foreach ($queryParams as $key => &$value) {
            $stmt->bindParam($key, $value);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ["draw" => (int) $draw, "recordsTotal" => (int) $totalRecords, "recordsFiltered" => (int) $totalFiltered, "data" => $data];
    }

    /**
     * Lógica de get_entidade_data.php
     */
    public function find(int $id): ?array
    {
        $query = $this->pdo->prepare("SELECT ent.*, end.end_cep, end.end_logradouro, end.end_numero, end.end_complemento, end.end_bairro, end.end_cidade, end.end_uf FROM tbl_entidades ent LEFT JOIN (SELECT *, ROW_NUMBER() OVER(PARTITION BY end_entidade_id ORDER BY CASE end_tipo_endereco WHEN 'Principal' THEN 1 ELSE 2 END) as rn FROM tbl_enderecos) end ON ent.ent_codigo = end.end_entidade_id AND end.rn = 1 WHERE ent.ent_codigo = :id");
        $query->execute([':id' => $id]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Lógica de cadastrar_entidade.php
     */
    public function create(array $data, int $userId): ?int
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Prepara os dados para a tabela de entidades
            $tipoPessoa = $data['ent_tipo_pessoa'] ?? 'F';
            $cpf = ($tipoPessoa === 'F') ? preg_replace('/\D/', '', $data['ent_cpf_cnpj']) : null;
            $cnpj = ($tipoPessoa === 'J') ? preg_replace('/\D/', '', $data['ent_cpf_cnpj']) : null;
            $situacao = isset($data['ent_situacao']) && $data['ent_situacao'] === 'A' ? 'A' : 'I';

            // 2. Insere na tabela principal 'tbl_entidades'
            $sqlEntidade = "INSERT INTO tbl_entidades (
                                ent_tipo_pessoa, ent_cpf, ent_cnpj, ent_razao_social, 
                                ent_nome_fantasia, ent_codigo_interno, ent_inscricao_estadual,
                                ent_tipo_entidade, ent_situacao, ent_usuario_cadastro_id
                            ) VALUES (
                                :tipo_pessoa, :cpf, :cnpj, :razao_social,
                                :nome_fantasia, :codigo_interno, :inscricao_estadual,
                                :tipo_entidade, :situacao, :user_id
                            )";

            $stmtEntidade = $this->pdo->prepare($sqlEntidade);
            $stmtEntidade->execute([
                ':tipo_pessoa' => $tipoPessoa,
                ':cpf' => $cpf,
                ':cnpj' => $cnpj,
                ':razao_social' => $data['ent_razao_social'],
                ':nome_fantasia' => $data['ent_nome_fantasia'] ?? null,
                ':codigo_interno' => $data['ent_codigo_interno'] ?? null,
                ':inscricao_estadual' => $data['ent_inscricao_estadual'] ?? null,
                ':tipo_entidade' => $data['ent_tipo_entidade'],
                ':situacao' => $situacao,
                ':user_id' => $userId
            ]);

            // 3. Pega o ID da entidade recém-criada
            $entidadeId = $this->pdo->lastInsertId();

            // 4. Se um endereço foi fornecido, insere na 'tbl_enderecos' como 'Principal'
            if (!empty($data['end_cep'])) {
                // (O código para inserir endereço principal que já fizemos antes continua aqui)
                $sqlEndereco = "INSERT INTO tbl_enderecos (
                                    end_entidade_id, end_tipo_endereco, end_cep, end_logradouro, 
                                    end_numero, end_complemento, end_bairro, end_cidade, end_uf, 
                                    end_usuario_cadastro_id
                                ) VALUES (
                                    :ent_id, 'Principal', :cep, :logradouro,
                                    :numero, :complemento, :bairro, :cidade, :uf,
                                    :user_id
                                )";
                $stmtEndereco = $this->pdo->prepare($sqlEndereco);
                $stmtEndereco->execute([
                    ':ent_id' => $entidadeId,
                    ':cep' => $data['end_cep'],
                    ':logradouro' => $data['end_logradouro'],
                    ':numero' => $data['end_numero'],
                    ':complemento' => $data['end_complemento'] ?? null,
                    ':bairro' => $data['end_bairro'],
                    ':cidade' => $data['end_cidade'],
                    ':uf' => $data['end_uf'],
                    ':user_id' => $userId
                ]);
            }

            // 5. Com base no tipo, insere nas tabelas relacionadas (clientes/fornecedores)
            $tipoEntidade = $data['ent_tipo_entidade'];
            if ($tipoEntidade === 'Cliente' || $tipoEntidade === 'Cliente e Fornecedor') {
                $stmtCli = $this->pdo->prepare("INSERT INTO tbl_clientes (cli_entidade_id, cli_usuario_cadastro_id) VALUES (:id, :user_id)");
                $stmtCli->execute([':id' => $entidadeId, ':user_id' => $userId]);
            }
            if ($tipoEntidade === 'Fornecedor' || $tipoEntidade === 'Cliente e Fornecedor') {
                $stmtForn = $this->pdo->prepare("INSERT INTO tbl_fornecedores (forn_entidade_id, forn_usuario_cadastro_id) VALUES (:id, :user_id)");
                $stmtForn->execute([':id' => $entidadeId, ':user_id' => $userId]);
            }

            $this->pdo->commit();
            return $entidadeId;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza uma entidade, seu endereço principal e gerencia os vínculos em tbl_clientes e tbl_fornecedores.
     */
    public function update(int $id, array $data, int $userId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Prepara e atualiza os dados na 'tbl_entidades'
            $tipoPessoa = $data['ent_tipo_pessoa'] ?? 'F';
            $cpf = ($tipoPessoa === 'F') ? preg_replace('/\D/', '', $data['ent_cpf_cnpj']) : null;
            $cnpj = ($tipoPessoa === 'J') ? preg_replace('/\D/', '', $data['ent_cpf_cnpj']) : null;
            $situacao = isset($data['ent_situacao']) && $data['ent_situacao'] === 'A' ? 'A' : 'I';

            $sqlEntidade = "UPDATE tbl_entidades SET
                                ent_tipo_pessoa = :tipo_pessoa, ent_cpf = :cpf, ent_cnpj = :cnpj, 
                                ent_razao_social = :razao_social, ent_nome_fantasia = :nome_fantasia, 
                                ent_codigo_interno = :codigo_interno, ent_inscricao_estadual = :inscricao_estadual,
                                ent_tipo_entidade = :tipo_entidade, ent_situacao = :situacao
                            WHERE ent_codigo = :id";

            $stmtEntidade = $this->pdo->prepare($sqlEntidade);
            $stmtEntidade->execute([
                ':id' => $id,
                ':tipo_pessoa' => $tipoPessoa,
                ':cpf' => $cpf,
                ':cnpj' => $cnpj,
                ':razao_social' => $data['ent_razao_social'],
                ':nome_fantasia' => $data['ent_nome_fantasia'] ?? null,
                ':codigo_interno' => $data['ent_codigo_interno'] ?? null,
                ':inscricao_estadual' => $data['ent_inscricao_estadual'] ?? null,
                ':tipo_entidade' => $data['ent_tipo_entidade'],
                ':situacao' => $situacao
            ]);

            // 2. Atualiza ou Insere (UPSERT) o endereço principal
            if (!empty($data['end_cep'])) {
                // Verifica se já existe um endereço principal para essa entidade
                $stmtCheck = $this->pdo->prepare("SELECT end_codigo FROM tbl_enderecos WHERE end_entidade_id = :id AND end_tipo_endereco = 'Principal'");
                $stmtCheck->execute([':id' => $id]);
                $existingEnderecoId = $stmtCheck->fetchColumn();

                $params = [
                    ':cep' => $data['end_cep'],
                    ':logradouro' => $data['end_logradouro'],
                    ':numero' => $data['end_numero'],
                    ':complemento' => $data['end_complemento'] ?? null,
                    ':bairro' => $data['end_bairro'],
                    ':cidade' => $data['end_cidade'],
                    ':uf' => $data['end_uf']
                ];

                if ($existingEnderecoId) {
                    // ATUALIZA o endereço principal existente
                    $sqlEndereco = "UPDATE tbl_enderecos SET
                                        end_cep = :cep, end_logradouro = :logradouro, 
                                        end_numero = :numero, end_complemento = :complemento, 
                                        end_bairro = :bairro, end_cidade = :cidade, end_uf = :uf
                                    WHERE end_codigo = :end_id";
                    $params[':end_id'] = $existingEnderecoId;
                } else {
                    // CRIA um novo endereço principal se não existir
                    $sqlEndereco = "INSERT INTO tbl_enderecos (
                                        end_entidade_id, end_tipo_endereco, end_cep, end_logradouro, end_numero, 
                                        end_complemento, end_bairro, end_cidade, end_uf, end_usuario_cadastro_id
                                    ) VALUES (
                                        :ent_id, 'Principal', :cep, :logradouro, :numero, 
                                        :complemento, :bairro, :cidade, :uf, :user_id
                                    )";
                    $params[':ent_id'] = $id;
                    $params[':user_id'] = $userId;
                }

                $stmtEndereco = $this->pdo->prepare($sqlEndereco);
                $stmtEndereco->execute($params);
            }

            // 3. Gerencia as tabelas relacionadas (clientes/fornecedores) com base na mudança de tipo
            $novoTipo = $data['ent_tipo_entidade'];

            // Lógica para Clientes
            if ($novoTipo === 'Cliente' || $novoTipo === 'Cliente e Fornecedor') {
                $stmt = $this->pdo->prepare("INSERT IGNORE INTO tbl_clientes (cli_entidade_id, cli_usuario_cadastro_id) VALUES (:id, :user_id)");
                $stmt->execute([':id' => $id, ':user_id' => $userId]);
            } else {
                $stmt = $this->pdo->prepare("DELETE FROM tbl_clientes WHERE cli_entidade_id = :id");
                $stmt->execute([':id' => $id]);
            }

            // Lógica para Fornecedores
            if ($novoTipo === 'Fornecedor' || $novoTipo === 'Cliente e Fornecedor') {
                $stmt = $this->pdo->prepare("INSERT IGNORE INTO tbl_fornecedores (forn_entidade_id, forn_usuario_cadastro_id) VALUES (:id, :user_id)");
                $stmt->execute([':id' => $id, ':user_id' => $userId]);
            } else {
                $stmt = $this->pdo->prepare("DELETE FROM tbl_fornecedores WHERE forn_entidade_id = :id");
                $stmt->execute([':id' => $id]);
            }

            $this->pdo->commit();
            return true;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lógica de inativar_entidade.php
     */
    public function inactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE tbl_entidades SET ent_situacao = 'I' WHERE ent_codigo = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Busca todos os endereços de uma entidade.
     */
    public function findEnderecosByEntidadeId(int $entidadeId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_enderecos WHERE end_entidade_id = :id ORDER BY end_tipo_endereco");
        $stmt->execute([':id' => $entidadeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um único endereço pelo seu ID.
     */
    public function findEndereco(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_enderecos WHERE end_codigo = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Salva (cria ou atualiza) um endereço.
     */
    public function saveEndereco(array $data, int $userId): bool
    {
        $id = filter_var($data['end_codigo'] ?? null, FILTER_VALIDATE_INT);
        if ($id) { // Atualiza
            $sql = "UPDATE tbl_enderecos SET end_tipo_endereco = :tipo, end_cep = :cep, end_logradouro = :log, end_numero = :num, end_complemento = :comp, end_bairro = :bairro, end_cidade = :cidade, end_uf = :uf WHERE end_codigo = :id";
        } else { // Cria
            $sql = "INSERT INTO tbl_enderecos (end_entidade_id, end_tipo_endereco, end_cep, end_logradouro, end_numero, end_complemento, end_bairro, end_cidade, end_uf, end_usuario_cadastro_id) VALUES (:ent_id, :tipo, :cep, :log, :num, :comp, :bairro, :cidade, :uf, :user_id)";
        }

        $stmt = $this->pdo->prepare($sql);
        $params = [
            ':tipo' => $data['end_tipo_endereco'],
            ':cep' => $data['end_cep'],
            ':log' => $data['end_logradouro'],
            ':num' => $data['end_numero'],
            ':comp' => $data['end_complemento'] ?? null,
            ':bairro' => $data['end_bairro'],
            ':cidade' => $data['end_cidade'],
            ':uf' => $data['end_uf'],
        ];
        if ($id) {
            $params[':id'] = $id;
        } else {
            $params[':ent_id'] = $data['end_entidade_id'];
            $params[':user_id'] = $userId;
        }

        return $stmt->execute($params);
    }

    /**
     * Exclui um endereço.
     */
    public function deleteEndereco(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tbl_enderecos WHERE end_codigo = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getFornecedorOptions(): array
    {
        $stmt = $this->pdo->query("SELECT ent_codigo, ent_razao_social, ent_codigo_interno FROM tbl_entidades WHERE (ent_tipo_entidade = 'Fornecedor' OR ent_tipo_entidade = 'Cliente e Fornecedor') AND ent_situacao = 'A' ORDER BY ent_razao_social ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}