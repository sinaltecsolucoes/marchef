<?php
// Inclui a conexão com o banco de dados
require_once('../conexao.php');

// Define os tipos de usuários (perfis) disponíveis
// É crucial que esta lista esteja sincronizada com os tipos de usuário que você usa no seu sistema (ex: Admin, Gerente, Producao)
$perfis_disponiveis = ['Admin', 'Gerente', 'Producao']; 

// Define as páginas que podem ter permissões.
// Esta lista DEVE estar sincronizada com o array $paginasPermitidas no seu index.php.
// Você pode adicionar um título mais amigável para cada página aqui.
$paginas_disponiveis = [
    'home' => 'Página Inicial',
    'usuarios' => 'Usuários',
    'clientes' => 'Clientes',
    'fornecedores' => 'Fornecedores',
    'produtos' => 'Produtos',
    // A página 'permissoes' (esta própria tela) não precisa ser listada para controle de acesso,
    // pois o acesso a ela já é restrito a Admin pelo menu.
];

// Carrega as permissões atuais do banco de dados
// A estrutura será: $permissoes_atuais['nome_da_pagina']['nome_do_perfil'] = true;
$permissoes_atuais = [];
try {
    $stmt = $pdo->query("SELECT permissao_pagina, permissao_perfil FROM tbl_permissoes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissoes_atuais[$row['permissao_pagina']][$row['permissao_perfil']] = true;
    }
} catch (PDOException $e) {
    error_log("Erro ao carregar permissões do banco de dados: " . $e->getMessage());
    // Em produção, você pode exibir uma mensagem amigável ao usuário
    echo '<div class="alert alert-danger" role="alert">Erro ao carregar permissões. Por favor, tente novamente.</div>';
}
?>

<div class="container-fluid mt-3">
    <h1>Gerenciar Permissões</h1>
    <p>Defina quais perfis de usuário podem acessar cada tela do sistema. Marque para permitir, desmarque para negar.</p>

    <div class="alert alert-info" role="alert">
        <strong>Aviso:</strong> A página "Gerenciar Permissões" só pode ser acessada por administradores.
    </div>

    <form id="form-gerenciar-permissoes">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Página</th>
                        <?php foreach ($perfis_disponiveis as $perfil): ?>
                            <th class="text-center"><?php echo htmlspecialchars($perfil); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginas_disponiveis as $nome_pagina_chave => $nome_pagina_amigavel): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($nome_pagina_amigavel); ?></td>
                            <?php foreach ($perfis_disponiveis as $perfil): ?>
                                <td class="text-center">
                                    <?php 
                                    // 'Admin' sempre tem acesso total, não deve ser desmarcado aqui
                                    // Para 'Admin', o checkbox pode ser desabilitado e marcado
                                    $is_admin_column = ($perfil === 'Admin');
                                    $is_checked = isset($permissoes_atuais[$nome_pagina_chave][$perfil]);
                                    ?>
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" role="switch" 
                                               id="switch-<?php echo htmlspecialchars($nome_pagina_chave); ?>-<?php echo htmlspecialchars($perfil); ?>" 
                                               name="permissoes[<?php echo htmlspecialchars($nome_pagina_chave); ?>][]" 
                                               value="<?php echo htmlspecialchars($perfil); ?>"
                                               <?php echo $is_checked ? 'checked' : ''; ?>
                                               <?php echo $is_admin_column ? 'disabled' : ''; ?>>
                                        <label class="form-check-label" for="switch-<?php echo htmlspecialchars($nome_pagina_chave); ?>-<?php echo htmlspecialchars($perfil); ?>"></label>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Salvar Permissões</button>
    </form>

    <div id="mensagem-permissoes" class="mt-3"></div>
  
</div>