<?php
// /src/Core/AuditLoggerService.php

namespace App\Core;

use PDO;

class AuditLoggerService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Regista uma ação no log de auditoria.
     *
     * @param string $acao Ação realizada (CREATE, UPDATE, DELETE, etc.).
     * @param ?int $registroId O ID do registo afetado.
     * @param ?string $tabelaAfetada O nome da tabela afetada.
     * @param ?array $dadosAntigos Array com os dados antes da alteração.
     * @param ?array $dadosNovos Array com os dados depois da alteração.
     */
    public function log(string $acao, ?int $registroId = null, ?string $tabelaAfetada = null, ?array $dadosAntigos = null, ?array $dadosNovos = null, ?string $observacao = null): void
    {
        // Obtém os dados do usuário da sessão atual
        $usuarioId = $_SESSION['codUsuario'] ?? null;
        $usuarioNome = $_SESSION['nomeUsuario'] ?? 'Sistema'; // 'Sistema' para ações não atreladas a um usuário

        // Converte os arrays de dados para JSON, se não forem nulos
        $jsonAntigo = $dadosAntigos ? json_encode($dadosAntigos, JSON_UNESCAPED_UNICODE) : null;
        $jsonNovo = $dadosNovos ? json_encode($dadosNovos, JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO tbl_auditoria_logs 
                    (log_usuario_id, 
                    log_usuario_nome, 
                    log_acao,
                    log_tabela_afetada, 
                    log_registro_id, 
                    log_dados_antigos, 
                    log_dados_novos,
                    log_observacao) 
                VALUES 
                    (:usuario_id,
                     :usuario_nome, 
                     :acao,
                     :tabela, 
                     :registro_id, 
                     :json_antigo,
                     :json_novo,
                     :observacao)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':usuario_nome' => $usuarioNome,
            ':acao' => $acao,
            ':tabela' => $tabelaAfetada,
            ':registro_id' => $registroId,
            ':json_antigo' => $jsonAntigo,
            ':json_novo' => $jsonNovo,
            ':observacao' => $observacao,
        ]);
    }
}
