<?php
// /src/Permissions/PermissionRepository.php
namespace App\Permissions;

use PDO;
use PDOException;

class PermissionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Salva as permissões para os perfis.
     * A lógica é deletar as permissões existentes e inserir as novas.
     * @param array $permissions Um array vindo do formulário, no formato ['perfil' => ['pagina1', 'pagina2']]
     */
    public function save(array $permissions): bool
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Limpa todas as permissões existentes, exceto as do perfil 'Admin'.
            // Isso garante que não possamos remover permissões de Admin por engano.
            $this->pdo->prepare("DELETE FROM tbl_permissoes WHERE permissao_perfil != 'Admin'")->execute();

            // 2. Prepara a query de inserção.
            $stmt = $this->pdo->prepare("INSERT INTO tbl_permissoes (permissao_perfil, permissao_pagina) VALUES (:perfil, :pagina)");

            // 3. Itera sobre os perfis e suas páginas permitidas para inserir no banco.
            foreach ($permissions as $perfil => $paginas) {
                // Medida de segurança: nunca processar permissões para o Admin a partir do formulário.
                if ($perfil === 'Admin') {
                    continue;
                }

                if (is_array($paginas)) {
                    foreach ($paginas as $pagina) {
                        $stmt->execute([':perfil' => $perfil, ':pagina' => $pagina]);
                    }
                }
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            // Lança a exceção para que o controlador no ajax_router possa capturá-la.
            throw $e;
        }
    }
}