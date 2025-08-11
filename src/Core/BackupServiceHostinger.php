<?php
// /src/Core/BackupService.php (Versão Corrigida para Hostinger)
namespace App\Core;

use PDO;
use Exception;

class BackupService
{
    private PDO $pdo;
    private array $config;

    public function __construct()
    {
        // Obtém a conexão e as configurações através da nossa classe Database
        $this->pdo = Database::getConnection();
        $this->config = require __DIR__ . '/../../config/database.php';
    }

    /**
     * Gera um backup completo da base de dados usando apenas PHP.
     * Compatível com ambientes de alojamento restritivos.
     *
     * @return string O nome do arquivo de backup gerado.
     * @throws Exception
     */
    public function gerarBackup(): string
    {
        try {
            // Define o nome e o caminho do arquivo de backup
            $backupDir = __DIR__ . '/../../public/backups/';
            if (!is_dir($backupDir)) {
                if (!mkdir($backupDir, 0775, true)) {
                    throw new Exception("Não foi possível criar o diretório de backups.");
                }
            }
            $filename = "backup-" . $this->config['dbname'] . "-" . date('Y-m-d_H-i-s') . ".sql";
            $filePath = $backupDir . $filename;

            $handle = fopen($filePath, 'w');
            if ($handle === false) {
                throw new Exception("Não foi possível abrir o arquivo para escrita: " . $filePath);
            }

            // Cabeçalho do arquivo SQL
            fwrite($handle, "-- Backup da Base de Dados: {$this->config['dbname']}\n");
            fwrite($handle, "-- Gerado em: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, "-- Host: {$this->config['host']}\n\n");
            fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            // Obtém todas as tabelas
            $tablesStmt = $this->pdo->query('SHOW TABLES');
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                // 1. Estrutura da Tabela (CREATE TABLE)
                fwrite($handle, "-- ----------------------------\n");
                fwrite($handle, "-- Estrutura da tabela: {$table}\n");
                fwrite($handle, "-- ----------------------------\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                
                $createTableStmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
                $createTable = $createTableStmt->fetch(PDO::FETCH_ASSOC);
                fwrite($handle, $createTable['Create Table'] . ";\n\n");

                // 2. Dados da Tabela (INSERT INTO)
                $dataStmt = $this->pdo->query("SELECT * FROM `{$table}`");
                $numFields = $dataStmt->columnCount();

                if ($dataStmt->rowCount() > 0) {
                    fwrite($handle, "-- ----------------------------\n");
                    fwrite($handle, "-- Dados da tabela: {$table}\n");
                    fwrite($handle, "-- ----------------------------\n");

                    while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                        $sql = "INSERT INTO `{$table}` VALUES(";
                        $values = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                // Escapa os valores para evitar erros de sintaxe SQL
                                $values[] = $this->pdo->quote($value);
                            }
                        }
                        $sql .= implode(', ', $values) . ");\n";
                        fwrite($handle, $sql);
                    }
                    fwrite($handle, "\n");
                }
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);

            return $filename;

        } catch (Exception $e) {
            // Garante que o arquivo seja fechado em caso de erro
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            // Lança a exceção para ser capturada pelo ajax_router
            throw new Exception("Erro ao gerar o backup: " . $e->getMessage());
        }
    }
}