<?php
// /src/Core/BackupService.php

namespace App\Core;

use Exception;

class BackupService
{
    private array $dbConfig;
    private string $backupDir;

    /*  public function __construct()
      {
          // Carrega as configurações do banco de dados
          $this->dbConfig = require_once __DIR__ . '/../../config/database.php';

          // Define o diretório onde os backups serão salvos temporariamente
          $this->backupDir = __DIR__ . '/../../public/backups/';

          // Cria o diretório se ele não existir
          if (!is_dir($this->backupDir)) {
              mkdir($this->backupDir, 0775, true);
          }
      }*/

    public function __construct(array $dbConfig)
    {
        // O serviço recebe a configuração
        $this->dbConfig = $dbConfig;

        // Define o diretório onde os backups serão salvos temporariamente
        $this->backupDir = __DIR__ . '/../../public/backups/';

        // Cria o diretório se ele não existir
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0775, true);
        }
    }

    /**
     * Gera um backup do banco de dados usando mysqldump.
     * @return string O nome do ficheiro de backup gerado.
     * @throws Exception Se o backup falhar.
     */
    /* public function gerarBackup(): string
     {
         $dbHost = $this->dbConfig['host'];
         $dbUser = $this->dbConfig['user'];
         $dbPass = $this->dbConfig['pass'];
         $dbName = $this->dbConfig['dbname'];

         // Define o nome do ficheiro de backup com a data e hora
         $filename = 'backup_' . $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';
         $filePath = $this->backupDir . $filename;

         // Monta o comando mysqldump
         // NOTA: Para Windows, o caminho para o mysqldump pode precisar ser explícito
         // Ex: $mysqldumpPath = '"C:\\xampp\\mysql\\bin\\mysqldump.exe"';
         //$mysqldumpPath = 'mysqldump'; 
         $mysqldumpPath = '"C:\\xampp\\mysql\\bin\\mysqldump.exe"';

         $command = sprintf(
             '%s --host=%s --user=%s --password=%s %s > %s',
             $mysqldumpPath,
             escapeshellarg($dbHost),
             escapeshellarg($dbUser),
             escapeshellarg($dbPass),
             escapeshellarg($dbName),
             escapeshellarg($filePath)
         );

         // Executa o comando no servidor
         exec($command, $output, $return_var);

         // Verifica se o comando foi executado com sucesso
         if ($return_var !== 0) {
             throw new Exception('Falha ao gerar o backup. Verifique as configurações e permissões do servidor.');
         }

         // Verifica se o ficheiro foi realmente criado
         if (!file_exists($filePath)) {
             throw new Exception('O ficheiro de backup não foi criado.');
         }

         // Retorna o nome do ficheiro para o controlador
         return $filename;
     }*/


    /**
     * Gera um backup do banco de dados usando mysqldump.
     * @return string O nome do ficheiro de backup gerado.
     * @throws Exception Se o backup falhar.
     */
    public function gerarBackup(): string
    {
        $dbHost = $this->dbConfig['host'];
        $dbUser = $this->dbConfig['user'];
        $dbPass = $this->dbConfig['password'];
        $dbName = $this->dbConfig['dbname'];

        $filename = 'bkp_' . $dbName . '_' . date('Ymd_H_i_s') . '.sql';
        $filePath = $this->backupDir . $filename;
        $mysqldumpPath = '"C:\\xampp\\mysql\\bin\\mysqldump.exe"';

        // Monta o comando mysqldump de forma mais robusta

        // 1. Inicia o comando base com as partes que sempre existem
        $command = sprintf(
            '%s --host=%s --user=%s',
            $mysqldumpPath,
            escapeshellarg($dbHost),
            escapeshellarg($dbUser)
        );

        // 2. Adiciona a senha APENAS se ela não estiver em branco
        if (!empty($dbPass)) {
            $command .= sprintf(' --password=%s', escapeshellarg($dbPass));
        }

        // 3. Adiciona o nome da base de dados e o ficheiro de saída no final
        $command .= sprintf(
            ' %s > %s',
            escapeshellarg($dbName),
            escapeshellarg($filePath)
        );
       
        // Executa o comando no servidor
        exec($command, $output, $return_var);

        // Verifica se o comando foi executado com sucesso
        if ($return_var !== 0) {
            // Adiciona mais detalhes ao erro para depuração futura
            $errorDetails = implode("\n", $output);
            throw new Exception("Falha ao executar o mysqldump. Código de retorno: {$return_var}. Detalhes: {$errorDetails}");
        }

        if (!file_exists($filePath)) {
            throw new Exception('O ficheiro de backup não foi criado, verifique as permissões da pasta.');
        }

        return $filename;
    }
}