<?php
// /views/backup/pagina_backup.php
?>

<h4 class="fw-bold mb-3">Backup do Sistema</h4>

<div class="card shadow mb-4 card-custom">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Gerar Cópia de Segurança Manual</h6>
    </div>
    <div class="card-body">
        <p>
            Clique no botão abaixo para gerar uma cópia de segurança completa da base de dados do sistema.
            O processo pode demorar alguns segundos. Um ficheiro com a extensão <code>.sql</code> será descarregado para o seu computador.
        </p>
        <p>
            <strong>Importante:</strong> Recomenda-se guardar este ficheiro num local seguro e externo (ex: um disco rígido externo, na nuvem, etc.).
        </p>
        <hr>
        
        <button id="btn-criar-backup" class="btn btn-lg btn-success">
            <i class="fas fa-database me-2"></i> Criar Backup Agora
        </button>

        <div id="backup-status" class="mt-3">
            </div>
    </div>
</div>