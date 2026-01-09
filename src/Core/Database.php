<?php
// /src/Core/Database.php

// A namespace ajuda a organizar e evitar conflito de nomes de classes.
namespace App\Core;

// Importa a classe PDO para que possamos usá-la.
use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    // O método é estático para que possamos chamá-lo sem criar um objeto "new Database()".
    public static function getConnection(): PDO
    {
        // Se a conexão ainda não foi criada, cria.
        if (self::$pdo === null) {
            // Carrega as configurações do nosso novo arquivo.
            $config = require_once __DIR__ . '/../../config/database.php';

            // Define o DSN (Data Source Name)
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";

            // Adiciona a porta ao DSN, se ela estiver definida no arquivo de configuração
            if (isset($config['port'])) {
                $dsn .= ";port={$config['port']}";
            }

            $opcoes_pdo = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci"
            ];

            try {
                self::$pdo = new PDO($dsn, $config['user'], $config['password'], $opcoes_pdo);
            } catch (PDOException $e) {
                // Em um ambiente de produção, logue o erro em um arquivo em vez de exibi-lo.
                throw new PDOException("Erro de conexão com o banco de dados: " . $e->getMessage(), (int) $e->getCode());
            }
        }

        // Retorna a conexão existente.
        return self::$pdo;
    }
}