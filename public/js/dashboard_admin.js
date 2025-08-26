document.addEventListener('DOMContentLoaded', function () {

    console.log('Dashboard Admin JS carregado com sucesso!');

    // --- Funções de Renderização de HTML ---

    function renderKpiCards(data) {
        // (código do passo anterior, sem alterações)
        return `
            <div class="col-12 mb-4">
                <h3 class="border-bottom pb-2">Visão Geral</h3>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Lotes em Produção</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">${data.lotesAtivos}</div>
                    </div><div class="col-auto"><i class="fas fa-boxes-stacked fa-2x text-gray-300"></i></div></div></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Carregamentos (Hoje)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">${data.carregamentosHoje}</div>
                    </div><div class="col-auto"><i class="fas fa-truck-fast fa-2x text-gray-300"></i></div></div></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                 <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total de Produtos</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">${data.totalProdutos}</div>
                    </div><div class="col-auto"><i class="fas fa-box-open fa-2x text-gray-300"></i></div></div></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                 <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Usuários Ativos</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">${data.totalUsuarios}</div>
                    </div><div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div></div></div>
                </div>
            </div>
        `;
    }

    function renderQuickActions() {
        // (código do passo anterior, sem alterações)
        return `
            <div class="col-12 mt-4 mb-4">
                <h3 class="border-bottom pb-2">Ações Rápidas</h3>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <a href="${BASE_URL}/index.php?page=lotes" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm action-card"><div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                        <i class="fa-solid fa-boxes-stacked fa-3x text-primary mb-3"></i>
                        <p class="card-text fs-5">Novo Lote de Produção</p>
                    </div></div>
                </a>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <a href="${BASE_URL}/index.php?page=carregamentos" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm action-card"><div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                        <i class="fa-solid fa-truck-fast fa-3x text-success mb-3"></i>
                        <p class="card-text fs-5">Iniciar Novo Carregamento</p>
                    </div></div>
                </a>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <a href="${BASE_URL}/index.php?page=usuarios" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm action-card"><div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                        <i class="fa-solid fa-user-plus fa-3x text-info mb-3"></i>
                        <p class="card-text fs-5">Cadastrar Usuário</p>
                    </div></div>
                </a>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <a href="${BASE_URL}/index.php?page=produtos" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm action-card"><div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                        <i class="fa-solid fa-box-open fa-3x text-warning mb-3"></i>
                        <p class="card-text fs-5">Registrar Produto</p>
                    </div></div>
                </a>
            </div>
        `;
    }

    // NOVA FUNÇÃO: Renderiza o card que vai conter o gráfico
    function renderChartContainer() {
        return `
            <div class="col-12 mt-4 mb-4">
                 <h3 class="border-bottom pb-2">Produtividade</h3>
            </div>
            <div class="col-lg-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Lotes Finalizados na Última Semana</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-bar">
                            <canvas id="lotesFinalizadosChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // NOVA FUNÇÃO: Desenha o gráfico usando Chart.js
    function renderLotesChart(chartData) {
        const ctx = document.getElementById('lotesFinalizadosChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels, // ['20/08', '21/08', ...]
                datasets: [{
                    label: 'Lotes Finalizados',
                    data: chartData.data, // [5, 0, 3, ...]
                    backgroundColor: 'rgba(78, 115, 223, 0.7)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1,
                    hoverBackgroundColor: 'rgba(78, 115, 223, 0.9)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1 // Força o eixo Y a ter apenas números inteiros
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false // Esconde a legenda no topo, pois o título do card já informa o que é
                    }
                }
            }
        });
    }

    // --- Função Principal de Orquestração ---

    async function initDashboard() {
        console.log('Iniciando o dashboard e buscando dados...');
        const dashboardContent = document.getElementById('dashboard-content');

        try {
            // Adicionamos a nova chamada de API ao Promise.all
            const responses = await Promise.all([
                fetch(`${BASE_URL}/ajax_router.php?action=getKpiLotesAtivos`),
                fetch(`${BASE_URL}/ajax_router.php?action=getKpiCarregamentosHoje`),
                fetch(`${BASE_URL}/ajax_router.php?action=getKpiTotalProdutos`),
                fetch(`${BASE_URL}/ajax_router.php?action=getKpiTotalUsuarios`),
                fetch(`${BASE_URL}/ajax_router.php?action=getGraficoLotesFinalizados`)
            ]);

            const results = await Promise.all(responses.map(res => res.json()));

            const kpiData = {
                lotesAtivos: results[0].count,
                carregamentosHoje: results[1].count,
                totalProdutos: results[2].count,
                totalUsuarios: results[3].count,
            };
            const chartData = results[4].data; // Os dados do gráfico estão no 5º resultado

            // Renderiza todos os componentes HTML de uma vez
            dashboardContent.innerHTML = renderKpiCards(kpiData) + renderChartContainer() + renderQuickActions();

            // IMPORTANTE: Só podemos renderizar o gráfico DEPOIS que o <canvas> já existe na página
            renderLotesChart(chartData);

        } catch (error) {
            console.error('Erro ao carregar dados do dashboard:', error);
            dashboardContent.innerHTML = '<div class="alert alert-danger">Não foi possível carregar os dados do dashboard. Verifique o console para mais detalhes.</div>';
        }
    }

    initDashboard();

});
