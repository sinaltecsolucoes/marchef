<?php
// editar-perfil.php

// Inicia a sessão para acessar $_SESSION['codUsuario'] e atualizar os dados da sessão após o salvamento
session_start();

// Inclui o arquivo de conexão com o banco de dados.
// Ajuste o caminho se seu 'conexao.php' não estiver um nível acima do 'editar-perfil.php'.
// Ex: se estiver no mesmo nível, seria 'conexao.php'.
require_once("../conexao.php");

// Define o cabeçalho para indicar que a resposta será JSON.
// Isso é crucial para que o JavaScript interprete a resposta corretamente.
header('Content-Type: application/json');

// Inicializa a variável $response com um estado de falha padrão e uma mensagem genérica.
$response = ['success' => false, 'message' => 'Erro desconhecido.'];

// Verifica se a requisição é do tipo POST (o que é esperado para envio de formulário).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 1. Validação e Sanitização de Entradas ---
    // Use htmlspecialchars para prevenir XSS ao exibir os dados no futuro (embora aqui seja para DB).
    // Use o operador de coalescência de nulo (?? '') para garantir que a variável exista e seja uma string,
    // evitando warnings se algum campo não for enviado.

    $nome_perfil = htmlspecialchars(trim($_POST['nome_perfil'] ?? ''));
    $login_perfil = htmlspecialchars(trim($_POST['login_perfil'] ?? ''));
    $senha_perfil = $_POST['senha_perfil'] ?? ''; // A senha não é htmlspecialchars porque será hasheada.
    
    // A situação do usuário:
    // O checkbox só envia 'value' se estiver marcado. Se não for enviado, significa que está desmarcado.
    // '1' para Ativo (marcado), '0' para Inativo (desmarcado).
    $situacao_perfil = isset($_POST['situacao_perfil']) ? 'A' : 'I';
    
    $nivel_perfil = htmlspecialchars(trim($_POST['nivel_perfil'] ?? ''));

    // --- 2. Obtenção do ID do Usuário Logado ---
    // O ID do usuário deve vir da sessão, nunca de um campo oculto no formulário por segurança.
    $id_usuario_logado = $_SESSION['codUsuario'] ?? null;

    if (!$id_usuario_logado) {
        $response['message'] = "Erro: Usuário não identificado na sessão. Faça login novamente.";
        echo json_encode($response);
        exit(); // Encerra o script se o ID do usuário não for encontrado
    }

    // --- 3. Lógica de Negócio e Interação com o Banco de Dados ---
    try {
        // Verifica se o novo login (e-mail) já está sendo usado por OUTRO usuário
        $queryVerificaLogin = $pdo->prepare("SELECT usu_codigo FROM tbl_usuarios WHERE usu_login = :login AND usu_codigo != :id_usuario");
        $queryVerificaLogin->bindParam(':login', $login_perfil);
        $queryVerificaLogin->bindParam(':id_usuario', $id_usuario_logado, PDO::PARAM_INT);
        $queryVerificaLogin->execute();

        if ($queryVerificaLogin->rowCount() > 0) {
            $response['message'] = "Este e-mail já está em uso por outro usuário. Por favor, escolha outro.";
            echo json_encode($response);
            exit();
        }

        // Constrói a query SQL de atualização
        $sql = "UPDATE tbl_usuarios SET usu_nome = :nome, usu_login = :login, usu_situacao = :situacao, usu_tipo = :nivel";
        
        // Se o campo de senha não estiver vazio, significa que o usuário deseja alterar a senha.
        // NUNCA salve a senha em texto puro! Sempre use password_hash().
        if (!empty($senha_perfil)) {
            $senha_hashed = password_hash($senha_perfil, PASSWORD_DEFAULT);
            $sql .= ", usu_senha = :senha"; // Adiciona a coluna de senha na query
        }
        
        $sql .= " WHERE usu_codigo = :id_usuario";

        $stmt = $pdo->prepare($sql);

        // Bind dos parâmetros
        $stmt->bindParam(':nome', $nome_perfil);
        $stmt->bindParam(':login', $login_perfil);
        $stmt->bindParam(':situacao', $situacao_perfil);
        $stmt->bindParam(':nivel', $nivel_perfil);
        
        if (!empty($senha_perfil)) {
            $stmt->bindParam(':senha', $senha_hashed); // Bind da senha hasheada
        }
        
        $stmt->bindParam(':id_usuario', $id_usuario_logado, PDO::PARAM_INT);

        // Executa a query
        $stmt->execute();

        // --- 4. Atualização da Sessão (se a atualização do DB foi bem-sucedida) ---
        // É importante manter os dados da sessão sincronizados com o banco de dados
        $_SESSION['nomeUsuario'] = $nome_perfil;
        $_SESSION['logUsuario'] = $login_perfil;
        // Não atualize $_SESSION['senhaUsuario'] com a senha pura ou hasheada aqui
        // (ela só é usada para validação de login, não é exibida).
        $_SESSION['sitUsuario'] = $situacao_perfil;
        $_SESSION['tipoUsuario'] = $nivel_perfil;

        $response['success'] = true;
        $response['message'] = "Perfil atualizado com sucesso!";

    } catch (PDOException $e) {
        // Em caso de erro do banco de dados (PDOException), captura e retorna uma mensagem de erro.
        $response['message'] = "Erro ao atualizar perfil: " . $e->getMessage();
        // É crucial logar o erro detalhado no servidor para depuração, mas não exibir ao usuário.
        error_log("Erro em editar-perfil.php: " . $e->getMessage());
    }

} else {
    // Se a requisição não for POST, retorna uma mensagem de erro apropriada.
    $response['message'] = "Método de requisição inválido. Apenas requisições POST são permitidas.";
}

// Retorna a resposta final em formato JSON.
echo json_encode($response);
exit(); // Encerra o script
?>