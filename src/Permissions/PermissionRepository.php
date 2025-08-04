<?php
// /src/Permissions/PermissionRepository.php
namespace App\Permissions;

use PDO;
use PDOException;
use App\Core\AuditLoggerService;

class PermissionRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
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
            // PASSO 1 DE AUDITORIA: Capturar o estado atual de todas as permissões (exceto Admin)
            $stmtAntigo = $this->pdo->prepare(
                "SELECT permissao_perfil, GROUP_CONCAT(permissao_pagina ORDER BY permissao_pagina) as paginas 
                 FROM tbl_permissoes 
                 WHERE permissao_perfil != 'Admin' 
                 GROUP BY permissao_perfil"
            );
            $stmtAntigo->execute();
            // O fetchAll cria um array associativo como ['Perfil' => 'pagina1,pagina2,...']
            $dadosAntigos = $stmtAntigo->fetchAll(PDO::FETCH_KEY_PAIR);

            // 1. Limpa todas as permissões existentes (lógica original)
            $this->pdo->prepare("DELETE FROM tbl_permissoes WHERE permissao_perfil != 'Admin'")->execute();

            // 2. Prepara a query de inserção (lógica original)
            $stmt = $this->pdo->prepare("INSERT INTO tbl_permissoes (permissao_perfil, permissao_pagina) VALUES (:perfil, :pagina)");

            // 3. Itera e insere as novas permissões (lógica original)
            foreach ($permissions as $perfil => $paginas) {
                if ($perfil === 'Admin') {
                    continue;
                }
                if (is_array($paginas)) {
                    foreach ($paginas as $pagina) {
                        $stmt->execute([':perfil' => $perfil, ':pagina' => $pagina]);
                    }
                }
            }

            // PASSO 2 DE AUDITORIA: Registar a alteração completa antes de confirmar
            $this->auditLogger->log(
                'PERMISSIONS_UPDATED',
                null, // Não há um ID de registo único, a ação é sobre o conjunto
                'tbl_permissoes',
                $dadosAntigos,
                $permissions // Os dados novos são o array que recebemos do formulário
            );

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}