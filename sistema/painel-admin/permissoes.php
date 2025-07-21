<?php
// permissoes.php

// Apenas o Admin pode acessar esta página.
if ($_SESSION['tipoUsuario'] !== 'Admin') {
    echo "<h1 class='text-danger'>Acesso Negado!</h1>";
    exit();
}

// Lista de todas as páginas e ações disponíveis no sistema
// A permissão foi renomeada para ser mais clara sobre sua função.
$paginas_e_acoes_disponiveis = [
    'home' => 'Página Inicial (Home)',
    'usuarios' => 'Gerenciar Usuários (Ver Lista)',
    'clientes' => 'Gerenciar Clientes',
    'fornecedores' => 'Gerenciar Fornecedores',
    'produtos' => 'Gerenciar Produtos',
    'lotes' => 'Gerenciar Lotes',
    'editar_outros_usuarios' => 'Ação: Editar Outros Usuários' // Permissão específica para a ação
];

// Perfis de usuário que podem ter permissões configuradas
$perfis_disponiveis = ['Admin', 'Gerente', 'Producao'];

// Busca as permissões atuais do banco de dados
$permissoes_atuais = [];
try {
    $stmt = $pdo->query("SELECT permissao_pagina, permissao_perfil FROM tbl_permissoes");
    // Agrupa os resultados para facilitar a verificação
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissoes_atuais[$row['permissao_pagina']][$row['permissao_perfil']] = true;
    }
} catch (PDOException $e) {
    error_log("Erro ao carregar permissões do banco de dados: " . $e->getMessage());
    echo '<div class="alert alert-danger" role="alert">Erro ao carregar permissões. Por favor, tente novamente.</div>';
}
?>

<div class="container-fluid mt-3">
    <h4 class="fw-bold mb-3">Gerenciar Permissões</h4>
    <p>Defina quais perfis de usuário podem acessar cada tela e executar ações importantes no sistema.</p>

    <div class="alert alert-info" role="alert">
        <strong>Aviso:</strong> O perfil "Admin" tem acesso total e irrestrito. Suas permissões não podem ser alteradas.
    </div>

    <form id="form-gerenciar-permissoes">
        <!-- Token CSRF para segurança do formulário -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Página / Ação</th>
                        <?php foreach ($perfis_disponiveis as $perfil): ?>
                            <th class="text-center"><?php echo htmlspecialchars($perfil); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginas_e_acoes_disponiveis as $chave_permissao => $descricao): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($descricao); ?></td>
                            <?php foreach ($perfis_disponiveis as $perfil): ?>
                                <td class="text-center">
                                    <?php 
                                    $is_admin_column = ($perfil === 'Admin');
                                    // Para o admin, a permissão está sempre marcada. Para outros, verifica no array.
                                    $is_checked = $is_admin_column || (isset($permissoes_atuais[$chave_permissao][$perfil]));
                                    ?>
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" role="switch" 
                                               id="switch-<?php echo htmlspecialchars($chave_permissao); ?>-<?php echo htmlspecialchars($perfil); ?>" 
                                               name="permissoes[<?php echo htmlspecialchars($perfil); ?>][]" 
                                               value="<?php echo htmlspecialchars($chave_permissao); ?>"
                                               <?php echo $is_checked ? 'checked' : ''; ?>
                                               <?php echo $is_admin_column ? 'disabled' : ''; ?>>
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
