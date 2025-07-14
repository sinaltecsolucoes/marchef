    <?php
    // Arquivo: painel-admin/excluir_usuario.php
    // Realiza a exclusão de um usuário no banco de dados.

    session_start(); // Inicia a sessão para verificar o token CSRF
    require_once('../../conexao.php'); // Ajuste o caminho conforme necessário
    require_once('../../includes/error_handler.php'); // Inclui o manipulador de erros

    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => 'Erro desconhecido.'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ========================================================================
        // Verificação do Token CSRF
        // ========================================================================
        $submitted_token = $_POST['csrf_token'] ?? '';
        if (!isset($_SESSION['csrf_token']) || $submitted_token !== $_SESSION['csrf_token']) {
            $response['message'] = "Erro de segurança: Requisição inválida (CSRF).";
            error_log("[CSRF ALERTA] Tentativa de CSRF detectada em excluir_usuario.php. IP: " . $_SERVER['REMOTE_ADDR']);
            echo json_encode($response);
            exit();
        }

        $userId = filter_input(INPUT_POST, 'usu_codigo', FILTER_VALIDATE_INT);

        if (!$userId) {
            $response['message'] = "ID de usuário inválido ou ausente.";
            echo json_encode($response);
            exit();
        }

        // Prevenção: Não permitir que o usuário logado exclua a si mesmo
        if (isset($_SESSION['codUsuario']) && $_SESSION['codUsuario'] == $userId) {
            $response['message'] = "Você não pode excluir seu próprio perfil.";
            echo json_encode($response);
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Exclui o usuário
            $query = $pdo->prepare("DELETE FROM tbl_usuarios WHERE usu_codigo = :id");
            $query->bindParam(':id', $userId, PDO::PARAM_INT);
            $query->execute();

            if ($query->rowCount() > 0) {
                $pdo->commit();
                $response['success'] = true;
                $response['message'] = 'Usuário excluído com sucesso!';
            } else {
                $pdo->rollBack();
                $response['message'] = 'Usuário não encontrado ou já excluído.';
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erro ao excluir usuário (excluir_usuario.php): " . $e->getMessage());
            $response['message'] = "Erro no servidor ao excluir usuário. Por favor, tente novamente mais tarde.";
        }
    } else {
        $response['message'] = "Método de requisição inválido. Apenas requisições POST são permitidas.";
    }

    echo json_encode($response);
    exit();
    ?>
    