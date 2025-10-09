<?php
// criar_admin_inicial.php - Script para ser executado UMA ÚNICA VEZ para configurar o admin inicial.
// Não deve ser mantido em login.php!

require_once("conexao.php"); 

try {
    $query = $pdo->query("SELECT * FROM tbl_usuarios WHERE usu_tipo = 'Admin'");
    $res = $query->fetchAll(PDO::FETCH_ASSOC);
    $total_reg = count($res);

    if ($total_reg == 0) {
        $senha_padrao_admin = 'adm@adm';
        $senha_hashed = password_hash($senha_padrao_admin, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO tbl_usuarios (usu_nome, usu_login, usu_senha, usu_tipo, usu_situacao) VALUES (:nome, :login, :senha, :tipo, :situacao)");
        $stmt->bindValue(":nome", 'Administrador');
        $stmt->bindValue(":login", 'adm');
        $stmt->bindValue(":senha", $senha_hashed);
        $stmt->bindValue(":tipo", 'Admin');
        $stmt->bindValue(":situacao", 'A');
        $stmt->execute();
        echo "Usuário Administrador padrão criado com sucesso!";
    } else {
        echo "Usuário Administrador já existe.";
    }
} catch (PDOException $e) {
    error_log("Erro ao verificar/inserir usuário ADM inicial: " . $e->getMessage());
    echo "Ocorreu um erro ao criar o usuário Administrador: " . $e->getMessage();
}
?>