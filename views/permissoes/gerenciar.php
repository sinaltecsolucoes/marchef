<?php
// /views/permissoes/gerenciar.php

// Apenas o Admin pode acessar esta página.
if ($_SESSION['tipoUsuario'] !== 'Admin') {
    echo "<h1 class='text-danger'>Acesso Negado!</h1>";
    return;
}

// Lista de todas as páginas e ações disponíveis no sistema, agora agrupadas por módulo
$paginas_e_acoes_disponiveis = [
    'Página Inicial' => [
        'home' => 'Página Inicial',
    ],

    'Páginas Módulo Cadastro' => [
        'usuarios'               => 'Gerenciar Usuários',
        'clientes'               => 'Gerenciar Fornecedores',
        'fornecedores'           => 'Gerenciar Fazenda (Origem)',
        'transportadoras'        => 'Gerenciar Transportadoras',
        'produtos'               => 'Gerenciar Produtos',
        'fichas_tecnicas'        => 'Gestão de Fichas Técnicas',
        'ficha_tecnica_detalhes' => 'Detalhes Fichas Técnicas',
        'condicoes_pagamento'    => 'Gerenciar Condições de Pagamento',
        'templates'              => 'Modelos de Etiquetas',
        'regras'                 => 'Regras para Etiquetas',
        'estoque_camaras'        => 'Gerenciar Câmaras de Armazenagem',
        'estoque_enderecos'      => 'Gerenciar Endereços Câmaras',
    ],

    'Páginas Módulo Lotes' => [
        'lotes_recebimento'    => 'Gerenciar Lotes (Recebimento)',
        'lotes_producao'       => 'Gerenciar Lotes (Produção)',
        'lotes_embalagem'      => 'Gerenciar Lotes (Embalagem)',
        'gestao_caixas_mistas' => 'Gestão de Caixas Mistas',
    ],

    'Páginas Módulo Estoque' => [
        'estoque'                 => 'Estoque de Produtos (Lista Geral)',
        'visao_estoque_enderecos' => 'Estoque de Produtos por Endereços',
    ],

    'Páginas Módulo Carregamento / Expedição' => [
        'ordens_expedicao'         => 'Gerenciar Ordens de Expedição',
        'ordem_expedicao_detalhes' => 'Detalhes de Ordem de Expedição',
        'carregamentos'            => 'Gerenciar Carregamentos',
        'carregamento_detalhes'    => 'Detalhes Carregamento',
        'saida_reprocesso'         => 'Gerenciar Saídas por Reprocesso',
        'detalhes_reprocesso'      => 'Detalhes Saídas por Reprocesso',
    ],

    'Páginas Módulo Faturamento' => [
        'faturamentos_listar' => 'Gerenciar Faturamentos',
        'faturamento_gerar'   => 'Gerar Resumo Faturamento',
    ],

    'Páginas Módulo Relatórios' => [
        'relatorio_entidade'          => 'Relatórios Clientes, Fornecedores, Transportadoras',
        'relatorio_produtos'          => 'Relatório Produtos',
        'relatorio_ficha_tecnica'     => 'Relatório Ficha Técnica',
        'ordem_expedicao_relatorio'   => 'Relatório de Ordem de Expedição',
        'carregamento_relatorio'      => 'Relatório de Carregamento',
        'relatorio_faturamento'       => 'Relatório Faturamento (PDF)',
        'relatorio_faturamento_excel' => 'Relatório Faturamento (Excel)',
        'relatorio_kardex'            => 'Relatório Kardex',
    ],

    'Páginas Módulo Configurações' => [
        'editar_outros_usuarios' => 'Editar Outros Usuários',
    ],

    'Páginas Módulo Utilitários' => [
        'auditoria' => 'Visualizar Logs de Auditoria',
        'backup'    => 'Backup do Sistema',
    ],
];

// Perfis de usuário que podem ter permissões configuradas
$perfis_disponiveis = ['Admin', 'Financeiro', 'Gerente', 'Logistica', 'Producao'];

// Busca as permissões atuais do banco de dados
$permissoes_atuais = [];
try {
    $stmt = $pdo->query("SELECT permissao_pagina, permissao_perfil FROM tbl_permissoes");
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
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

        <div class="table-responsive tabela-permissoes-wrapper">
            <table class="table table-bordered table-striped table-hover tabela-permissoes">
                <thead class="table-light">
                    <tr>
                        <th>Página / Ação</th>
                        <?php foreach ($perfis_disponiveis as $perfil): ?>
                            <th class="text-center"><?php echo htmlspecialchars($perfil); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginas_e_acoes_disponiveis as $titulo => $paginas): ?>
                        <!-- Linha de título -->
                        <tr class="table-secondary">
                            <td colspan="<?php echo count($perfis_disponiveis) + 1; ?>">
                                <strong><?php echo htmlspecialchars($titulo); ?></strong>
                            </td>
                        </tr>

                        <!-- Linhas das páginas -->
                        <?php foreach ($paginas as $chave_permissao => $descricao): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($descricao); ?></td>
                                <?php foreach ($perfis_disponiveis as $perfil): ?>
                                    <td class="text-center">
                                        <?php
                                        $is_admin_column = ($perfil === 'Admin');
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
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Salvar Permissões</button>
    </form>

    <div id="mensagem-permissoes" class="mt-3"></div>
</div>