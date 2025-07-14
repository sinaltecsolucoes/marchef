<?php
// editar-perfil.php

// Inicia a sessão para acessar $_SESSION['codUsuario'] e atualizar os dados da sessão após o salvamento
session_start();

// Inclui o manipulador de erros global para garantir tratamento consistente de exceções
require_once('../../includes/error_handler.php'); // CORREÇÃO AQUI: Caminho atualizado

// Inclui o arquivo de conexão com o banco de dados.
require_once('../../conexao.php'); // CORREÇÃO AQUI: Caminho atualizado

// Inclui o arquivo de funções auxiliares (onde as funções de validação foram movidas)
require_once('../../includes/helpers.php'); // CORREÇÃO AQUI: Caminho atualizado

// Define o cabeçalho para indicar que a resposta será JSON.
header('Content-Type: application/json');

// Inicializa a variável $response com um estado de falha padrão e uma mensagem genérica.
$response = ['success' => false, 'message' => 'Erro desconhecido.'];

// Verifica se a requisição é do tipo POST (o que é esperado para envio de formulário).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ========================================================================
    // Verificação do Token CSRF
    // ========================================================================
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $submitted_token !== $_SESSION['csrf_token']) {
        $response['message'] = "Erro de segurança: Requisição inválida (CSRF).";
        error_log("[CSRF ALERTA] Tentativa de CSRF detectada em editar-perfil.php. IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode($response);
        exit();
    }

    // --- 1. Validação e Sanitização de Entradas ---
    // Pega o ID do usuário a ser editado (vem do combobox no frontend)
    $id_usuario_editar = filter_input(INPUT_POST, 'usu_codigo_selecionado', FILTER_VALIDATE_INT);

    if (!$id_usuario_editar) {
        $response['message'] = "ID do usuário a ser editado inválido ou ausente.";
        echo json_encode($response);
        exit();
    }

    // Validação do Nome
    $nome_perfil_raw = filter_input(INPUT_POST, 'nome_perfil', FILTER_SANITIZE_STRING);
    $val_nome = validate_string($nome_perfil_raw, 3, 100, '/^[a-zA-Z\sÀ-ú]+$/u'); // Permite letras, espaços e acentos
    if (!$val_nome['valid']) {
        $response['message'] = "Nome: " . $val_nome['message'];
        echo json_encode($response);
        exit();
    }
    $nome_perfil = $val_nome['value'];

    // Validação do Login (assumindo que pode ser um e-mail ou um nome de usuário simples)
    $login_perfil_raw = filter_input(INPUT_POST, 'login_perfil', FILTER_SANITIZE_STRING);
    // Se o login deve ser um e-mail:
    if (!filter_var($login_perfil_raw, FILTER_VALIDATE_EMAIL)) {
        // Se não for um e-mail válido, valide como nome de usuário simples
        $val_login = validate_string($login_perfil_raw, 3, 50, '/^[a-zA-Z0-9_.-]+$/'); // Permite letras, números, _ . -
        if (!$val_login['valid']) {
            $response['message'] = "Login: " . $val_login['message'] . " (ou formato de e-mail inválido)";
            echo json_encode($response);
            exit();
        }
        $login_perfil = $val_login['value'];
    } else {
        $login_perfil = $login_perfil_raw; // Já validado como e-mail pelo filter_var
    }

    $senha_perfil = $_POST['senha_perfil'] ?? ''; // A senha não é sanitizada aqui, será hasheada.

    // Validação da Situação
    $situacao_perfil_raw = filter_input(INPUT_POST, 'situacao_perfil', FILTER_SANITIZE_STRING);
    $val_situacao = validate_selection($situacao_perfil_raw, ['1']); // '1' se marcado, vazio se desmarcado
    $situacao_perfil = $val_situacao['valid'] ? 'A' : 'I'; // Se '1' foi enviado, é 'A', senão 'I'

    // Validação do Nível de Acesso
    $nivel_perfil_raw = filter_input(INPUT_POST, 'nivel_perfil', FILTER_SANITIZE_STRING);
    $allowed_niveis = ['Admin', 'Gerente', 'Producao'];
    $val_nivel = validate_selection($nivel_perfil_raw, $allowed_niveis);
    if (!$val_nivel['valid']) {
        $response['message'] = "Nível de Acesso: " . $val_nivel['message'];
        echo json_encode($response);
        exit();
    }
    $nivel_perfil = $val_nivel['value'];


    // --- 2. Lógica de Negócio e Interação com o Banco de Dados ---
    try {
        // Verifica se o novo login (e-mail) já está sendo usado por OUTRO usuário
        // A verificação agora exclui o ID do usuário que está sendo editado
        $queryVerificaLogin = $pdo->prepare("SELECT usu_codigo FROM tbl_usuarios WHERE usu_login = :login AND usu_codigo != :id_usuario_editar");
        $queryVerificaLogin->bindParam(':login', $login_perfil);
        $queryVerificaLogin->bindParam(':id_usuario_editar', $id_usuario_editar, PDO::PARAM_INT);
        $queryVerificaLogin->execute();

        if ($queryVerificaLogin->rowCount() > 0) {
            $response['message'] = "Este login já está em uso por outro usuário. Por favor, escolha outro.";
            echo json_encode($response);
            exit();
        }

        // Constrói a query SQL de atualização
        $sql = "UPDATE tbl_usuarios SET usu_nome = :nome, usu_login = :login, usu_situacao = :situacao, usu_tipo = :nivel";

        // Se o campo de senha não estiver vazio, significa que o usuário deseja alterar a senha.
        // NUNCA salve a senha em texto puro! Sempre use password_hash().
        if (!empty($senha_perfil)) {
            // Validação da Senha (se for alterada)
            if (mb_strlen($senha_perfil) < 6) { // Exemplo: mínimo de 6 caracteres
                $response['message'] = "Senha: Mínimo de 6 caracteres.";
                echo json_encode($response);
                exit();
            }
            $senha_hashed = password_hash($senha_perfil, PASSWORD_DEFAULT);
            $sql .= ", usu_senha = :senha"; // Adiciona a coluna de senha na query
        }

        $sql .= " WHERE usu_codigo = :id_usuario_editar"; // Usa o ID do usuário a ser editado

        $stmt = $pdo->prepare($sql);

        // Bind dos parâmetros
        $stmt->bindParam(':nome', $nome_perfil);
        $stmt->bindParam(':login', $login_perfil);
        $stmt->bindParam(':situacao', $situacao_perfil);
        $stmt->bindParam(':nivel', $nivel_perfil);

        if (!empty($senha_perfil)) {
            $stmt->bindParam(':senha', $senha_hashed); // Bind da senha hasheada
        }

        $stmt->bindParam(':id_usuario_editar', $id_usuario_editar, PDO::PARAM_INT); // Bind do ID do usuário a ser editado

        // Executa a query
        $stmt->execute();

        // --- 3. Atualização da Sessão (se o usuário logado for o que foi editado) ---
        // É importante manter os dados da sessão sincronizados com o banco de dados
        // APENAS SE O USUÁRIO LOGADO FOR O MESMO QUE ESTÁ SENDO EDITADO.
        if (isset($_SESSION['codUsuario']) && $_SESSION['codUsuario'] == $id_usuario_editar) {
            $_SESSION['nomeUsuario'] = $nome_perfil;
            $_SESSION['logUsuario'] = $login_perfil;
            $_SESSION['sitUsuario'] = $situacao_perfil;
            $_SESSION['tipoUsuario'] = $nivel_perfil;
        }

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
