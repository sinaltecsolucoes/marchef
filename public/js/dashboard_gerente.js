// CÓDIGO JS COMPLETO PARA O DASHBOARD DO GERENTE
document.addEventListener('DOMContentLoaded', function () {

    console.log('Dashboard Gerente JS carregado!');

    // --- Funções Auxiliares ---
    function calculateDaysAgo(dateString) {
        const today = new Date();
        const pastDate = new Date(dateString);
        const diffTime = Math.abs(today - pastDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        if (diffDays === 1) return 'ontem';
        if (diffDays === 0) return 'hoje';
        return `${diffDays} dias atrás`;
    }

    // --- Funções de Renderização de HTML ---

    // Exatamente a mesma função do dashboard do Admin
    function renderKpiCards(data) {
        return `
            <div class="col-12 mb-4">
                <h3 class="border-bottom pb-2">Visão Geral da Operação</h3>
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

    // Exatamente a mesma função do dashboard do Admin
    function renderChartContainer() {
        return `
            <div class="col-12 mt-4 mb-4">
                 <h3 class="border-bottom pb-2">Produtividade Recente</h3>
            </div>
            <div class="col-lg-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Lotes Finalizados na Última Semana</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-bar">
                            <canvas id="lotesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Ações Rápidas personalizadas para o GERENTE
    function renderQuickActionsGerente() {
        return `
            <div class="col-12 mt-4 mb-4">
                <h3 class="border-bottom pb-2">Ações Gerenciais</h3>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <a href="${BASE_URL}/index.php?page=lotes_embalagem" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm action-card"><div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                        <i class="fas fa-boxes fa-3x text-primary mb-3"></i>
                        <p class="card-text fs-5">Gerenciar Lotes</p>
                    </div></div>
                </a>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <a href="${BASE_URL}/index.php?page=carregamentos" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm action-card"><div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                        <i class="fas fa-truck fa-3x text-success mb-3"></i>
                        <p class="card-text fs-5">Gerenciar Carregamentos</p>
                    </div></div>
                </a>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <a href="${BASE_URL}/index.php?page=visao_estoque_enderecos" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm action-card"><div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                        <i class="fas fa-warehouse fa-3x text-info mb-3"></i>
                        <p class="card-text fs-5">Consultar Estoque</p>
                    </div></div>
                </a>
            </div>
        `;
    }
    
    function renderLotesChart(chartData) {
        const ctx = document.getElementById('lotesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Lotes Finalizados',
                    data: chartData.data,
                    backgroundColor: 'rgba(78, 115, 223, 0.7)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1,
                    hoverBackgroundColor: 'rgba(78, 115, 223, 0.9)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                plugins: { legend: { display: false } }
            }
        });
    }

    function renderLotesAtivosPanel(lotes) {
        if (!lotes || lotes.length === 0) {
            return '<div class="list-group-item">Nenhum lote em andamento.</div>';
        }
        return lotes.map(lote => `
            <a href="${BASE_URL}/index.php?page=lotes&id=${lote.lote_id}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold">${lote.lote_completo_calculado}</div>
                    <small class="text-muted">Criado ${calculateDaysAgo(lote.lote_data_cadastro)}</small>
                </div>
                <span class="badge bg-primary rounded-pill">${lote.lote_status.replace('_', ' ')}</span>
            </a>
        `).join('');
    }

    function renderCarregamentosAbertosPanel(carregamentos) {
        if (!carregamentos || carregamentos.length === 0) {
            return '<div class="list-group-item">Nenhum carregamento em aberto.</div>';
        }
        return carregamentos.map(car => `
            <a href="${BASE_URL}/index.php?page=carregamento_detalhes&id=${car.car_id}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold">Nº ${car.car_numero}</div>
                    <small class="text-muted">${car.ent_razao_social || 'Cliente não definido'}</small>
                </div>
                <span class="badge bg-warning text-dark rounded-pill">${car.car_status}</span>
            </a>
        `).join('');
    }

    function renderResumoEstoqueCard() {
        return `
            <div class="col-xl-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Resumo do Estoque por Câmara</h6>
                    </div>
                    <div class="card-body">
                        <div id="painel-resumo-estoque">
                            <p class="text-center text-muted">A carregar dados do estoque...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function carregarResumoEstoque() {
        const painel = $('#painel-resumo-estoque');
        $.ajax({
            url: 'ajax_router.php?action=getKpiEstoquePorCamara',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                painel.empty();
                if (response.success && response.data.length > 0) {
                    let tableHtml = '<table class="table table-sm table-hover">';
                    tableHtml += `<thead class="table-light">
                                    <tr>
                                       <th>Câmara</th>
                                       <th class="text-end">Total Caixas</th>
                                       <th class="text-end">Total Quilos (kg)</th>
                                       </tr>
                                    </thead>
                                <tbody>`;
                    response.data.forEach(function (camara) {
                        tableHtml += `<tr><td>${camara.camara_nome}</td><td class="text-end">${parseFloat(camara.total_caixas).toFixed(3)}</td><td class="text-end">${parseFloat(camara.total_quilos).toFixed(3)}</td></tr>`;
                    });
                    tableHtml += '</tbody></table>';
                    painel.html(tableHtml);
                } else {
                    painel.html('<p class="text-center text-muted">Nenhum item alocado no estoque para exibir.</p>');
                }
            },
            error: function () {
                painel.html('<p class="text-center text-danger">Não foi possível carregar o resumo do estoque.</p>');
            }
        });
    }

    // Função principal, adaptada para chamar as funções corretas
    async function initDashboard() {
        console.log('Iniciando o dashboard gerencial...');
        const dashboardContent = document.getElementById('dashboard-content');
        try {
            // Usamos as mesmas chamadas de API do Admin
            const responses = await Promise.all([
                fetch(`${BASE_URL}/ajax_router.php?action=getKpiLotesAtivos`),
                fetch(`${BASE_URL}/ajax_router.php?action=getKpiCarregamentosHoje`),
                fetch(`${BASE_URL}/ajax_router.php?action=getKpiTotalProdutos`),
                fetch(`${BASE_URL}/ajax_router.php?action=getKpiTotalUsuarios`),
                fetch(`${BASE_URL}/ajax_router.php?action=getGraficoLotesFinalizados`),
                fetch(`${BASE_URL}/ajax_router.php?action=getPainelLotesAtivos`),
                fetch(`${BASE_URL}/ajax_router.php?action=getPainelCarregamentosAbertos`)

            ]);

            const results = await Promise.all(responses.map(res => res.json()));

            for (const result of results) {
                if (result.success === false) {
                    throw new Error(result.message || 'Uma das chamadas à API falhou.');
                }
            }

            const kpiData = {
                lotesAtivos: results[0].count,
                carregamentosHoje: results[1].count,
                totalProdutos: results[2].count,
                totalUsuarios: results[3].count,
            };
            const chartData = results[4].data;
            const lotesAtivosData = results[5].data;
            const carregamentosAbertosData = results[6].data;

            const finalHtml = `
                ${renderKpiCards(kpiData)}

                <div class="col-lg-6 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Lotes em Andamento (Mais Antigos)</h6></div>
                        <div class="card-body p-0"><div class="list-group list-group-flush" id="lotes-ativos-list"></div></div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Carregamentos em Aberto</h6></div>
                        <div class="card-body p-0"><div class="list-group list-group-flush" id="carregamentos-abertos-list"></div></div>
                    </div>
                </div>
                ${renderResumoEstoqueCard()}
                ${renderChartContainer()}
                ${renderQuickActionsGerente()}
            `;

            // Montamos o HTML final com os componentes
            dashboardContent.innerHTML = finalHtml;
            // Renderiza todos os componentes dinâmicos
            renderLotesChart(chartData);
            document.getElementById('lotes-ativos-list').innerHTML = renderLotesAtivosPanel(lotesAtivosData);
            document.getElementById('carregamentos-abertos-list').innerHTML = renderCarregamentosAbertosPanel(carregamentosAbertosData);
            carregarResumoEstoque();
        } catch (error) {
            console.error('Erro ao carregar dados do dashboard gerencial:', error);
            dashboardContent.innerHTML = `<div class="alert alert-danger">Não foi possível carregar os dados do dashboard. Detalhe: ${error.message}</div>`;
        }
    }

    initDashboard();

});