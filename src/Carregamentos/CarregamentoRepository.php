<?php
// /src/Carregamentos/CarregamentoRepository.php
namespace App\Carregamentos;

use PDO;
use Exception; // Adicionado para o 'catch'
use App\Core\AuditLoggerService; // Adicionado para a auditoria


class CarregamentoRepository
{
    private PDO $pdo;
    private AuditLoggerService $auditLogger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditLogger = new AuditLoggerService($pdo);
    }

    /**
     * Calcula o próximo número sequencial para um novo carregamento.
     */
    public function getNextNumeroCarregamento(): string
    {
        // Busca o maior valor numérico na coluna car_numero
        $stmt = $this->pdo->query("SELECT MAX(CAST(car_numero AS UNSIGNED)) FROM tbl_carregamentos");
        $ultimoNumero = $stmt->fetchColumn() ?: 0;
        $proximoNumero = $ultimoNumero + 1;

        // Formata com 4 dígitos à esquerda, ex: 0001, 0057, etc.
        return str_pad($proximoNumero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cria um novo registro de cabeçalho de carregamento.
     */
    public function createHeader(array $data, int $userId): int
    {
        $sql = "INSERT INTO tbl_carregamentos (
                car_numero, car_data, car_entidade_id_organizador, car_lacre,
                car_placa_veiculo, car_hora_inicio, car_ordem_expedicao, car_usuario_id_responsavel
            ) VALUES (
                :numero, :data, :clienteOrganizadorId, :lacre,
                :placa, :hora_inicio, :ordem_expedicao, :user_id
            )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':numero' => $data['numero'],
            ':data' => $data['data'],
            ':clienteOrganizadorId' => $data['clienteOrganizadorId'],
            ':lacre' => $data['lacre'] ?? null,
            ':placa' => $data['placa'] ?? null,
            ':hora_inicio' => $data['hora_inicio'] ?? null,
            ':ordem_expedicao' => $data['ordem_expedicao'] ?? null,
            ':user_id' => $userId
        ]);

        $novoId = (int) $this->pdo->lastInsertId();

        // AUDITORIA: Registar a criação do cabeçalho do carregamento
        if ($novoId > 0) {
            $this->auditLogger->log('CREATE', $novoId, 'tbl_carregamentos', null, $data);
        }

        return $novoId;
    }

    /**
     * Cria uma nova fila e salva um lote de leituras de QR Code.
     * Usa uma transação para garantir a integridade dos dados.
     */
    public function createFilaWithLeituras(int $carregamentoId, int $clienteId, array $leituras): int
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Inserir o registro da fila na tabela 'tbl_carregamento_filas'
            $sqlFila = "INSERT INTO tbl_carregamento_filas (fila_carregamento_id, fila_entidade_id_cliente) VALUES (:car_id, :cli_id)";
            $stmtFila = $this->pdo->prepare($sqlFila);
            $stmtFila->execute([
                ':car_id' => $carregamentoId,
                ':cli_id' => $clienteId
            ]);
            $filaId = (int) $this->pdo->lastInsertId();

            // 2. Preparar a inserção para as leituras
            $sqlLeitura = "INSERT INTO tbl_carregamento_leituras (leitura_fila_id, leitura_qrcode_conteudo) VALUES (:fila_id, :conteudo)";
            $stmtLeitura = $this->pdo->prepare($sqlLeitura);

            // 3. Inserir cada leitura da lista na tabela 'tbl_carregamento_leituras'
            foreach ($leituras as $conteudoQr) {
                $stmtLeitura->execute([
                    ':fila_id' => $filaId,
                    ':conteudo' => $conteudoQr
                ]);
            }

            // AUDITORIA: Registar a criação da fila. O log é feito dentro da transação.
            if ($filaId > 0) {
                $dadosNovosFila = [
                    'fila_carregamento_id' => $carregamentoId,
                    'fila_entidade_id_cliente' => $clienteId,
                    'quantidade_leituras' => count($leituras)
                ];
                $this->auditLogger->log('CREATE', $filaId, 'tbl_carregamento_filas', null, $dadosNovosFila);
            }

            // 4. Se tudo deu certo, confirma todas as operações no banco de dados
            $this->pdo->commit();
            return $filaId;

        } catch (\Exception $e) {
            // 5. Se qualquer passo falhar, desfaz todas as operações
            $this->pdo->rollBack();
            // Re-lança a exceção para que a API possa reportar o erro
            throw $e;
        }
    }

    /**
     * Atualiza o caminho da foto para uma fila específica.
     */
    public function updateFilaPhotoPath(int $filaId, string $filePath): bool
    {
        // PASSO 1: Buscar dados antigos ANTES de atualizar
        $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamento_filas WHERE fila_id = :id");
        $stmtAntigo->execute([':id' => $filaId]);
        $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

        $sql = "UPDATE tbl_carregamento_filas SET fila_foto_path = :path WHERE fila_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([':path' => $filePath, ':id' => $filaId]);

        // PASSO 2: Se a atualização foi bem-sucedida, registar o log
        if ($success && $dadosAntigos) {
            $dadosNovos = $dadosAntigos;
            $dadosNovos['fila_foto_path'] = $filePath; // Atualiza o campo modificado
            $this->auditLogger->log('UPDATE', $filaId, 'tbl_carregamento_filas', $dadosAntigos, $dadosNovos);
        }

        return $success;
    }

    /**
     * Finaliza um carregamento, atualizando seu status e data de finalização.
     * Retorna true se a atualização foi bem-sucedida, false caso contrário.
     */
    public function finalize(int $carregamentoId): bool
    {
        // PASSO 1: Buscar dados antigos ANTES de atualizar
        $stmtAntigo = $this->pdo->prepare("SELECT * FROM tbl_carregamentos WHERE car_id = :id");
        $stmtAntigo->execute([':id' => $carregamentoId]);
        $dadosAntigos = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

        $sql = "UPDATE tbl_carregamentos SET car_status = 'FINALIZADO', car_data_finalizacao = NOW() WHERE car_id = :id AND car_status = 'EM ANDAMENTO'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $carregamentoId]);
        $success = $stmt->rowCount() > 0;

        // PASSO 2: Se a atualização foi bem-sucedida, registar o log
        if ($success && $dadosAntigos) {
            $dadosNovos = $dadosAntigos;
            $dadosNovos['car_status'] = 'FINALIZADO'; // Representa a principal alteração
            $this->auditLogger->log('UPDATE', $carregamentoId, 'tbl_carregamentos', $dadosAntigos, $dadosNovos);
        }

        return $success;
    }
}