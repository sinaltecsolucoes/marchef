<?php
require_once('../../conexao.php');

//echo "Olá, o arquivo está funcionando!";

$nome = $_POST['usu_nome'];
$login = $_POST['usu_login'];
$senha = $_POST['usu_senha'];
$nivel = $_POST['usu_tipo'];
$situacao_form = $_POST['usu_situacao'] ?? '0';

// Converte a situação de 1/0 para A/I
$situacao = ($situacao_form == '1') ? 'A' : 'I';

// Criptografa a senha para maior segurança
// CORREÇÃO DE SEGURANÇA: Usando password_hash() que é seguro e moderno
$senha_cript = password_hash($senha, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // Verifica se o login já existe para evitar duplicatas
    $query = $pdo->prepare("SELECT COUNT(*) FROM tbl_usuarios WHERE usu_login = :login");
    $query->bindValue(':login', $login);
    $query->execute();
    $login_existe = $query->fetchColumn();

    if ($login_existe > 0) {
        $pdo->rollBack();
        echo json_encode(array('success' => false, 'message' => 'Erro: Login já cadastrado!'));
        exit();
    }

    // Prepara a query de inserção
    $query = $pdo->prepare("INSERT INTO tbl_usuarios (usu_login, usu_senha, usu_nome, usu_situacao, usu_tipo) VALUES (:login, :senha, :nome, :situacao, :nivel)");

    // Vincula os parâmetros
    $query->bindValue(':login', $login);
    $query->bindValue(':senha', $senha_cript); // Vincula o hash seguro
    $query->bindValue(':nome', $nome);
    $query->bindValue(':situacao', $situacao);
    $query->bindValue(':nivel', $nivel);

    // Executa a query
    $query->execute();

    $pdo->commit();

    echo json_encode(array('success' => true, 'message' => 'Usuário cadastrado com sucesso!'));

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Erro ao cadastrar usuário: ' . $e->getMessage());
    echo json_encode(array('success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()));
}
?>