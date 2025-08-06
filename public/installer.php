<?php
// /public/installer.php - O nosso Assistente de Instalação Inteligente
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$pageTitle = 'Assistente de Instalação do Sistema';
$lockFile = __DIR__ . '/../config/install.lock';
$configFile = __DIR__ . '/../config/database.php';

// 1. VERIFICAÇÃO DE SEGURANÇA PRINCIPAL
if (file_exists($lockFile)) {
    die("<h3>Erro de Segurança</h3><p>O sistema já foi instalado. Para reinstalar, por favor, apague os ficheiros <code>config/install.lock</code> e <code>config/database.php</code> do servidor.</p>");
}

// 2. LÓGICA PARA RECOMEÇAR A INSTALAÇÃO
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    if (file_exists($configFile)) {
        unlink($configFile);
    }
    session_destroy();
    header('Location: installer.php');
    exit;
}

$feedback = '';
// 3. LÓGICA PARA PROCESSAR O FORMULÁRIO DA ETAPA 1
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_db'])) {
    $dbHost = $_POST['db_host'];
    $dbName = $_POST['db_name'];
    $dbUser = $_POST['db_user'];
    $dbPass = $_POST['db_pass'];
    $createDb = isset($_POST['create_db_if_not_exists']);

    try {
        if ($createDb) {
            $pdoTemp = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass);
            $pdoTemp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
        }
        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $configContent = "<?php\n\nreturn [\n    'host' => '{$dbHost}',\n    'dbname' => '{$dbName}',\n    'user' => '{$dbUser}',\n    'password' => '{$dbPass}',\n    'charset' => 'utf8mb4'\n];\n";
        file_put_contents($configFile, $configContent);
        $_SESSION['install_step'] = 2;
        header('Location: installer.php'); // Redireciona para limpar o POST
        exit;

    } catch (PDOException $e) {
        $friendlyMessage = 'Falha na conexão: ' . $e->getMessage();
        if (strpos(strtolower($e->getMessage()), 'access denied') !== false) {
            $friendlyMessage = 'Falha na conexão: Utilizador ou senha incorretos ou sem permissão.';
        }
        $feedback = '<div class="alert alert-danger">' . $friendlyMessage . '</div>';
    }
}

// 4. LÓGICA INTELIGENTE PARA DEFINIR A ETAPA ATUAL
if (!file_exists($configFile)) {
    $step = 1;
} else {
    $step = $_SESSION['install_step'] ?? 2;
}

// ========================================================================
// LÓGICA DE VISUALIZAÇÃO (A "INTERFACE")
// ========================================================================
include_once __DIR__ . '/../views/layouts/header_installer.php';
?>

<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 700px;">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0"><?php echo $pageTitle; ?> - Etapa <?php echo $step; ?> de 3</h4>
        </div>
        <div class="card-body p-4">
            <?php echo $feedback; ?>

            <div id="step-1" style="<?php echo $step == 1 ? '' : 'display:none;'; ?>">
                <h5>Etapa 1: Conexão com o Banco de Dados</h5>
                <p>Por favor, insira os dados de conexão do seu banco de dados MySQL/MariaDB.</p>
                <form method="POST" action="installer.php">
                    <input type="hidden" name="test_db" value="1">
                    <div class="mb-3">
                        <label for="db_host" class="form-label">Host</label>
                        <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_name" class="form-label">Nome do Banco de Dados</label>
                        <input type="text" class="form-control" id="db_name" name="db_name" required>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="create_db_if_not_exists"
                            name="create_db_if_not_exists" value="1" checked>
                        <label class="form-check-label" for="create_db_if_not_exists">Tentar criar o banco de dados se
                            ele não existir</label>
                    </div>
                    <div class="mb-3">
                        <label for="db_user" class="form-label">Utilizador</label>
                        <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_pass" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Testar Conexão e Salvar</button>
                </form>
            </div>

            <div id="step-2" style="<?php echo $step == 2 ? '' : 'display:none;'; ?>">
                <h5>Etapa 2: Estrutura do Banco de Dados</h5>
                <div class="alert alert-success">Conexão bem-sucedida e ficheiro de configuração salvo!</div>
                <p>Clique no botão abaixo para criar todas as tabelas necessárias para o sistema.</p>
                <button id="btn-create-tables" class="btn btn-primary w-100">Criar Tabelas</button>
                <div id="feedback-step-2" class="mt-3"></div>
                <div class="text-center mt-3"><a href="installer.php?action=reset">Recomeçar da Etapa 1</a></div>
            </div>

            <div id="step-3" style="<?php echo $step == 3 ? '' : 'display:none;'; ?>">
                <h5>Etapa 3: Configuração Inicial</h5>
                <div class="alert alert-success">Tabelas criadas com sucesso!</div>
                <p>Agora, preencha os dados da sua empresa e crie a conta do administrador principal.</p>
                <form id="form-final-setup" enctype="multipart/form-data">
                    <hr>
                    <h6>Dados da Empresa</h6>
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Nome da Empresa / Razão Social</label>
                        <input type="text" class="form-control" id="company_name" name="company_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="company_name_fantasia" class="form-label">Nome Fantasia</label>
                        <input type="text" class="form-control" id="company_name_fantasia" name="company_name_fantasia"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="company_cnpj" class="form-label">CNPJ</label>
                        <input type="text" class="form-control" id="company_cnpj" name="company_cnpj">
                    </div>
                    <div class="mb-3">
                        <label for="company_logo" class="form-label">Logomarca</label>
                        <input type="file" class="form-control" id="company_logo" name="company_logo">
                    </div>
                    <hr>
                    <h6>Conta de Administrador</h6>
                    <div class="mb-3">
                        <label for="admin_name" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="admin_name" name="admin_name" value="Administrador"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="admin_login" class="form-label">Login</label>
                        <input type="text" class="form-control" id="admin_login" name="admin_login" value="admin"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="admin_pass" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="admin_pass" name="admin_pass" required>
                    </div>
                    <button id="btn-final-setup" type="submit" class="btn btn-success w-100">Finalizar
                        Instalação</button>
                </form>
                <div id="feedback-step-3" class="mt-3"></div>
                <div class="text-center mt-3"><a href="installer.php?action=reset">Recomeçar da Etapa 1</a></div>
            </div>

            <div id="step-final" style="display:none;">
                <div class="alert alert-success text-center">
                    <h5 class="alert-heading">Instalação Concluída com Sucesso!</h5>
                    <p>O seu sistema foi instalado e configurado.</p>
                    <hr>
                    <p class="mb-0">Por segurança, o instalador foi bloqueado. Agora pode aceder à sua página de login.
                    </p>
                </div>
                <a href="./" class="btn btn-primary w-100">Ir para a Página de Login</a>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="js/configuracao_inicial.js"></script>

<?php include_once __DIR__ . '/../views/layouts/footer_installer.php'; ?>