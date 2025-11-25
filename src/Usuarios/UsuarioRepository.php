<?php
// /src/Usuarios/UsuarioRepository.php
namespace App\Usuarios;

use PDO;
use PDOException;
use Exception;
use App\Core\AuditLoggerService;

class UsuarioRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Busca dados para o DataTables com paginação, busca e ordenação.
     */
    public function findAllForDataTable(array $params): array
    {
        try {
            $draw = $params['draw'] ?? 1;
            $start = $params['start'] ?? 0;
            $length = $params['length'] ?? 10;
            $searchValue = $params['search']['value'] ?? '';

            $totalRecords = $this->pdo->query("SELECT COUNT(usu_codigo) FROM tbl_usuarios")->fetchColumn();

            $whereClause = '';
            if (!empty($searchValue)) {
                $whereClause = "WHERE usu_nome LIKE :search_nome OR usu_login LIKE :search_login OR usu_tipo LIKE :search_tipo";
            }

            $stmtFiltered = $this->pdo->prepare("SELECT COUNT(usu_codigo) FROM tbl_usuarios $whereClause");
            if (!empty($searchValue)) {
                $stmtFiltered->execute([
                    ':search_nome' => '%' . $searchValue . '%',
                    ':search_login' => '%' . $searchValue . '%',
                    ':search_tipo' => '%' . $searchValue . '%'
                ]);
            } else {
                $stmtFiltered->execute();
            }
            $totalFiltered = $stmtFiltered->fetchColumn();

            $sqlData = "SELECT * FROM tbl_usuarios $whereClause ORDER BY usu_nome ASC LIMIT :start, :length";
            $stmt = $this->pdo->prepare($sqlData);
            $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
            $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
            if (!empty($searchValue)) {
                $stmt->bindValue(':search_nome', '%' . $searchValue . '%');
                $stmt->bindValue(':search_login', '%' . $searchValue . '%');
                $stmt->bindValue(':search_tipo', '%' . $searchValue . '%');
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ["draw" => (int) $draw, "recordsTotal" => (int) $totalRecords, "recordsFiltered" => (int) $totalFiltered, "data" => $data];
        } catch (PDOException $e) {
            error_log('Erro em findAllForDataTable: ' . $e->getMessage());
            throw new Exception('Erro ao buscar usuários: ' . $e->getMessage());
        }
    }

    /**
     * Busca um único usuário pelo ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT usu_codigo, usu_nome, usu_login, usu_situacao, usu_tipo FROM tbl_usuarios WHERE usu_codigo = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Cria um novo usuário.
     */
    public function create(array $data): bool
    {
        $senha_hashed = password_hash($data['usu_senha'], PASSWORD_DEFAULT);
        $situacao = isset($data['usu_situacao']) ? 'A' : 'I';

        $stmt = $this->pdo->prepare("INSERT INTO tbl_usuarios (usu_nome, usu_login, usu_senha, usu_tipo, usu_situacao) 
                                            VALUES (:nome, :login, :senha, :tipo, :situacao)");
        return $stmt->execute([
            ':nome' => $data['usu_nome'],
            ':login' => $data['usu_login'],
            ':senha' => $senha_hashed,
            ':tipo' => $data['usu_tipo'],
            ':situacao' => $situacao
        ]);

        if ($success) {
            $novoId = (int) $this->pdo->lastInsertId();

            // Prepara os dados novos para o log, removendo a senha por segurança.
            $dadosNovos = $data;
            unset($dadosNovos['usu_senha']);

            $this->auditLogger->log(
                'CREATE',           // Ação
                $novoId,            // ID do novo registo
                'tbl_usuarios',     // Tabela afetada
                null,               // Dados antigos (não existem na criação)
                $dadosNovos         // Dados novos
            );
        }

        return $success;
    }

    /**
     * Atualiza um usuário existente.
     */
    public function update(int $id, array $data): bool
    {
        // ====================================================================
        // PASSO 1: Buscar o estado do registo ANTES de qualquer alteração.
        // ====================================================================
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos) {
            // Se o usuário não existe, não há nada a atualizar ou auditar.
            return false;
        }

        $sql_parts = ["usu_nome = :nome", "usu_login = :login", "usu_tipo = :tipo", "usu_situacao = :situacao"];
        $params = [
            ':nome' => $data['usu_nome'],
            ':login' => $data['usu_login'],
            ':tipo' => $data['usu_tipo'],
            ':situacao' => isset($data['usu_situacao']) ? 'A' : 'I',
            ':id' => $id
        ];

        if (!empty($data['usu_senha'])) {
            $senha_hashed = password_hash($data['usu_senha'], PASSWORD_DEFAULT);
            $sql_parts[] = "usu_senha = :senha";
            $params[':senha'] = $senha_hashed;
        }

        $sql = "UPDATE tbl_usuarios SET " . implode(", ", $sql_parts) . " WHERE usu_codigo = :id";
        $stmt = $this->pdo->prepare($sql);

        // ====================================================================
        // PASSO 2: Executar a atualização e guardar o resultado (sucesso/falha).
        // ====================================================================
        $success = $stmt->execute($params);

        // ====================================================================
        // PASSO 3: Se a atualização foi bem-sucedida, registar no log de auditoria.
        // ====================================================================
        if ($success) {
            // Prepara os dados novos. Por segurança, nunca guardamos a senha em texto claro no log.
            $dadosNovos = $data;
            unset($dadosNovos['usu_senha']);

            $this->auditLogger->log(
                'UPDATE',               // Ação
                $id,                    // ID do registo afetado
                'tbl_usuarios',         // Tabela afetada
                $dadosAntigos,          // Como os dados eram ANTES
                $dadosNovos,             // Como os dados ficaram DEPOIS
                ""
            );
        }

        // ====================================================================
        // PASSO 4: Retornar o resultado da operação de UPDATE.
        // ====================================================================
        return $success;
    }

    /**
     * Exclui um usuário.
     */
    public function delete(int $id): bool
    {
        // PASSO 1: Buscar os dados antigos ANTES de apagar
        $dadosAntigos = $this->find($id);
        if (!$dadosAntigos) {
            // Se o usuário não existe, não há nada a apagar.
            return false;
        }
        $stmt = $this->pdo->prepare("DELETE FROM tbl_usuarios WHERE usu_codigo = :id");
        $success = $stmt->execute([':id' => $id]);

        // PASSO 2: Se a exclusão foi bem-sucedida, registar o log
        if ($success && $stmt->rowCount() > 0) {
            $this->auditLogger->log(
                'DELETE',           // Ação
                $id,                // ID do registo apagado
                'tbl_usuarios',     // Tabela afetada
                $dadosAntigos,      // Como os dados eram ANTES
                null,                // Dados novos (não existem na exclusão)
                ""
            );
            return true;
        }
        return false;
    }

    /**
     * Busca um único usuário pelo seu login.
     * Necessário para o processo de autenticação.
     */
    public function findByLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tbl_usuarios WHERE usu_login = :login");
        $stmt->execute([':login' => $login]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Valida as credenciais de um usuário.
     * Retorna os dados do usuário se a senha estiver correta, ou null se falhar.
     */
    public function validateCredentials(string $login, string $password): ?array
    {
        $user = $this->findByLogin($login);
        if ($user && password_verify($password, $user['usu_senha'])) {
            unset($user['usu_senha']); // Nunca retorne a senha
            return $user;
        }
        return null;
    }

    /**
     * Salva um novo token de API para um usuário.
     */
    public function saveApiToken(int $userId, string $tokenHash, string $expiresAt): bool
    {
        $sql = "INSERT INTO tbl_api_tokens (token_usuario_id, token_hash, token_expires_at) VALUES (:user_id, :token_hash, :expires_at)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt
        ]);
    }

    /**
     * Encontra um usuário válido e ativo a partir de um token de API.
     */
    public function findUserByToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);

        // Busca um token na tabela que corresponda ao hash e que ainda não tenha expirado
        $sql = "SELECT u.* FROM tbl_api_tokens t
        JOIN tbl_usuarios u ON t.token_usuario_id = u.usu_codigo
        WHERE t.token_hash = :token_hash AND t.token_expires_at > NOW()";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':token_hash' => $tokenHash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Conta o número total de usuários no sistema.
     * @return int
     */
    public function countAll(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(usu_codigo) FROM tbl_usuarios");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca todos os usuários para preencher um campo <select> ou similar.
     * @return array
     */
    public function findAllForOptions(): array
    {
        $stmt = $this->pdo->query("SELECT usu_codigo, usu_nome FROM tbl_usuarios ORDER BY usu_nome ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
