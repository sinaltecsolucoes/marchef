// CÓDIGO JS COMPLETO E FINAL PARA O DASHBOARD DE PRODUÇÃO
document.addEventListener('DOMContentLoaded', function () {

    console.log('Dashboard Produção JS carregado!');

    // --- Funções Auxiliares ---
    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'Data indisponível';
        const date = new Date(dateTimeString);
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    // --- Funções de Renderização de HTML ---

    function renderActionButton() {
        return `
            <div class="col-12 mb-4 text-center">
                <a href="${BASE_URL}/index.php?page=lotes" class="btn btn-primary btn-lg p-3">
                    <i class="fa-solid fa-plus me-2"></i> INICIAR NOVO LOTE DE PRODUÇÃO
                </a>
            </div>
        `;
    }

    function renderLotesEmAndamentoPanel(lotes) {
        let itemsHtml = '';
        if (!lotes || lotes.length === 0) {
            itemsHtml = '<div class="list-group-item text-center p-3">Parabéns, nenhum lote em andamento!</div>';
        } else {
            itemsHtml = lotes.map(lote => `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">${lote.lote_completo_calculado}</h5>
                        <p class="mb-1 text-muted">Status: ${lote.lote_status}</p>
                    </div>
                    <a href="${BASE_URL}/index.php?page=lotes&id=${lote.lote_id}" class="btn btn-success">
                        <i class="fa-solid fa-arrow-right me-1"></i> TRABALHAR
                    </a>
                </div>
            `).join('');
        }
        return `<div class="list-group list-group-flush">${itemsHtml}</div>`;
    }

    // NOVA FUNÇÃO para renderizar os lotes finalizados
    function renderLotesFinalizadosPanel(lotes) {
        let itemsHtml = '';
        if (!lotes || lotes.length === 0) {
            itemsHtml = '<div class="list-group-item text-center p-3">Nenhum lote finalizado recentemente.</div>';
        } else {
            itemsHtml = lotes.map(lote => `
                <a href="${BASE_URL}/index.php?page=lotes&id=${lote.lote_id}" class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${lote.lote_completo_calculado}</h6>
                        <small class="text-success"><i class="fa-solid fa-check"></i></small>
                    </div>
                    <small>Finalizado em: ${formatDateTime(lote.lote_data_finalizacao)}</small>
                </a>
            `).join('');
        }
        return `<div class="list-group list-group-flush">${itemsHtml}</div>`;
    }

    // --- Função Principal de Orquestração ---
    async function initDashboard() {
        console.log('Iniciando o painel de produção...');
        const dashboardContent = document.getElementById('dashboard-content');

        // Estrutura HTML do layout dos painéis
        dashboardContent.innerHTML = `
            ${renderActionButton()}
            <div class="col-lg-8 mb-4">
                <div class="card shadow">
                    <div class="card-header"><h5 class="m-0 font-weight-bold text-primary">Lotes em Andamento</h5></div>
                    <div id="lotes-andamento-list"> <div class="list-group-item text-center p-3">Carregando...</div> </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card shadow">
                    <div class="card-header"><h6 class="m-0 font-weight-bold text-secondary">Últimos Lotes Finalizados</h6></div>
                    <div id="lotes-finalizados-list"> <div class="list-group-item text-center p-3">Carregando...</div> </div>
                </div>
            </div>
        `;

        try {
            // Usamos Promise.all para buscar os dados das duas listas ao mesmo tempo
            const [andamentoResponse, finalizadosResponse] = await Promise.all([
                fetch(`${BASE_URL}/ajax_router.php?action=getPainelProducaoLotes`),
                fetch(`${BASE_URL}/ajax_router.php?action=getPainelProducaoLotesFinalizados`)
            ]);

            const andamentoResult = await andamentoResponse.json();
            const finalizadosResult = await finalizadosResponse.json();

            if (!andamentoResult.success || !finalizadosResult.success) {
                throw new Error('Falha ao buscar dados de um dos painéis.');
            }

            // Popula os painéis com os dados recebidos
            document.getElementById('lotes-andamento-list').innerHTML = renderLotesEmAndamentoPanel(andamentoResult.data);
            document.getElementById('lotes-finalizados-list').innerHTML = renderLotesFinalizadosPanel(finalizadosResult.data);

        } catch (error) {
            console.error('Erro ao carregar dados do painel de produção:', error);
            dashboardContent.innerHTML = `<div class="alert alert-danger">Não foi possível carregar os lotes. Detalhe: ${error.message}</div>`;
        }
    }

    // Inicia tudo
    initDashboard();
});