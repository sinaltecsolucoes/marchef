<?php
// /src/Auth/AuthService.php

namespace App\Auth;

use PDO;
use PDOException;

class AuthService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Tenta autenticar um usuário. Se for bem-sucedido, preenche a sessão.
     * @param string $username O login ou nome de usuário.
     * @param string $password A senha em texto plano.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function authenticate(string $username, string $password): bool
    {
        try {
            if (empty($username) || empty($password)) {
                $_SESSION['erro_login'] = "Por favor, preencha todos os campos.";
                return false;
            }

            $query = $this->pdo->prepare("SELECT usu_codigo, usu_nome, usu_login, usu_senha, usu_situacao, usu_tipo FROM tbl_usuarios WHERE usu_nome = :nome_usuario OR usu_login = :login_usuario");
            $query->bindParam(":nome_usuario", $username);
            $query->bindParam(":login_usuario", $username);
            $query->execute();
            $userData = $query->fetch();

            if ($userData && password_verify($password, $userData['usu_senha'])) {
                // Senha correta, login bem-sucedido!
                // Preenchemos a sessão com os dados do usuário.
                $_SESSION['codUsuario'] = $userData['usu_codigo'];
                $_SESSION['logUsuario'] = $userData['usu_login'];
                $_SESSION['nomeUsuario'] = $userData['usu_nome'];
                $_SESSION['sitUsuario'] = $userData['usu_situacao'];
                $_SESSION['tipoUsuario'] = $userData['usu_tipo'];
                
                // Limpa qualquer mensagem de erro antiga
                unset($_SESSION['erro_login']);

                return true; // Sucesso!
            }
            
            // Se chegou aqui, ou o usuário não existe ou a senha está errada.
            $_SESSION['erro_login'] = "Login ou senha inválidos.";
            return false;

        } catch (PDOException $e) {
            error_log("Erro no AuthService (BD): " . $e->getMessage());
            $_SESSION['erro_login'] = "Ocorreu um erro inesperado. Tente novamente.";
            return false;
        }
    }
}
