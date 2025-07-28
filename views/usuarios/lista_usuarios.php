<h4 class="fw-bold mb-3">Gestão de Usuários</h4>

<a href="#" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-usuario" id="btn-adicionar-usuario">Adicionar Usuário</a>

<div id="feedback-message-area-usuario" class="mt-3"></div>

<div class="table-responsive">
    <table id="tabela-usuarios" class="table table-hover my-4" style="width:100%">
        <thead>
            <tr>
                <th>Situação</th>
                <th>Nome</th>
                <th>Login</th>
                <th>Nível</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<div class="modal fade" id="modal-usuario" tabindex="-1" role="dialog" aria-labelledby="modal-usuario-label" aria-hidden="true">
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
                        <small class="form-text text-muted">Deixe em branco para não alterar na edição.</small>
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
                            <input class="form-check-input" type="checkbox" role="switch" id="usu-situacao" name="usu_situacao" value="A" checked>
                            <label class="form-check-label" for="usu-situacao">Ativo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary" id="btn-salvar-usuario">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>