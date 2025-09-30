<?php
// /views/usuarios/lista_usuarios.php
?>

<h4 class="fw-bold mb-3">Gestão de Usuários</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 fw-bold text-primary">Gerenciar Registros</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <p>Gerencie todos os usuários</p>
                <button class="btn btn-primary" id="btn-adicionar-usuario">
                    <i class="fas fa-plus me-2"></i> Adicionar Usuário
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registros de Usuário</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-usuarios" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Situação</th>
                        <th class="text-center align-middle">Nome</th>
                        <th class="text-center align-middle">Login</th>
                        <th class="text-center align-middle">Nível</th>
                        <th class="text-center align-middle">Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-usuario" tabindex="-1" role="dialog" aria-labelledby="modal-usuario-label"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-usuario-label">Adicionar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-usuario">
                <div class="modal-body">
                    <input type="hidden" name="usu_codigo" id="usu-codigo">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                    <div id="mensagem-usuario" class="mb-3"></div>

                    <div class="form-group mb-3">
                        <label for="usu-nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="usu-nome" name="usu_nome" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="usu-login" class="form-label">Login</label>
                        <input type="text" class="form-control" id="usu-login" name="usu_login" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="usu-senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="usu-senha" name="usu_senha">
                        <small class="form-text text-muted">Deixe em branco para não alterar na
                            edição.</small>
                    </div>
                    <div class="form-group mb-3">
                        <label for="usu-tipo" class="form-label">Nível de Acesso</label>
                        <select class="form-select" id="usu-tipo" name="usu_tipo" required>
                            <option value="Producao">Produção</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group mt-3">
                        <label class="form-label" for="usu-situacao">Situação</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="usu-situacao"
                                name="usu_situacao" value="A" checked>
                            <label class="form-check-label" for="usu-situacao">Ativo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="btn-salvar-usuario"><i class="fas fa-save me-2"></i>Salvar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Fechar</button>
                </div>
            </form>
        </div>
    </div>
</div>