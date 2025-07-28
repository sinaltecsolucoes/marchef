<?php
// /src/Usuarios/UsuarioRepository.php
namespace App\Usuarios;

use PDO;
use PDOException;

class UsuarioRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca dados para o DataTables com paginação, busca e ordenação.
     * Melhora a performance em relação ao 'listar_usuarios.php' original.
     */
    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';
        
        $totalRecords = $this->pdo->query("SELECT COUNT(usu_codigo) FROM tbl_usuarios")->fetchColumn();

        $whereClause = '';
        $queryParams = [];
        if (!empty($searchValue)) {
            $whereClause = "WHERE usu_nome LIKE :search OR usu_login LIKE :search OR usu_tipo LIKE :search";
            $queryParams[':search'] = '%' . $searchValue . '%';
        }

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(usu_codigo) FROM tbl_usuarios $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT * FROM tbl_usuarios $whereClause ORDER BY usu_nome ASC LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        if (!empty($searchValue)) {
            $stmt->bindValue(':search', $queryParams[':search']);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ["draw" => (int)$draw, "recordsTotal" => (int)$totalRecords, "recordsFiltered" => (int)$totalFiltered, "data" => $data];
    }

    /**
     * Busca um único usuário pelo ID.
     * Lógica de 'get_user_data.php'.
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
     * Lógica de 'cadastrar_usuarios.php'.
     */
    public function create(array $data): bool
    {
        $senha_hashed = password_hash($data['usu_senha'], PASSWORD_DEFAULT);
        $situacao = isset($data['usu_situacao']) ? 'A' : 'I';

        $stmt = $this->pdo->prepare("INSERT INTO tbl_usuarios (usu_nome, usu_login, usu_senha, usu_tipo, usu_situacao) VALUES (:nome, :login, :senha, :tipo, :situacao)");
        return $stmt->execute([
            ':nome' => $data['usu_nome'],
            ':login' => $data['usu_login'],
            ':senha' => $senha_hashed,
            ':tipo' => $data['usu_tipo'],
            ':situacao' => $situacao
        ]);
    }

    /**
     * Atualiza um usuário existente.
     * Lógica de 'editar-perfil.php'.
     */
    public function update(int $id, array $data): bool
    {
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
        return $stmt->execute($params);
    }

    /**
     * Exclui um usuário.
     * Lógica de 'excluir_usuario.php'.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tbl_usuarios WHERE usu_codigo = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}