<?php

include "conexao.php";
include "verifica_login.php"; 
include "info_usuario.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// ========================================
// ESTATÍSTICAS GERAIS
// ========================================

// Total de vídeos
$queryTotalVideos = "SELECT COUNT(*) as total FROM video WHERE ativo = 1";
$totalVideos = $conexao->query($queryTotalVideos)->fetch_assoc()['total'];

// Total de visualizações
$queryTotalVisualizacoes = "SELECT SUM(visualizacoes) as total FROM video";
$totalVisualizacoes = $conexao->query($queryTotalVisualizacoes)->fetch_assoc()['total'] ?? 0;

// Total de downloads de prévias
$queryTotalDownloads = "SELECT COUNT(*) as total FROM video_download_previa";
$totalDownloads = $conexao->query($queryTotalDownloads)->fetch_assoc()['total'];

// Total de categorias
$queryTotalCategorias = "SELECT COUNT(*) as total FROM categoria";
$totalCategorias = $conexao->query($queryTotalCategorias)->fetch_assoc()['total'];

// ========================================
// TOP 10 VÍDEOS MAIS VISTOS
// ========================================
$queryTopVideos = "SELECT nome_video, visualizacoes 
                   FROM video 
                   WHERE ativo = 1 
                   ORDER BY visualizacoes DESC 
                   LIMIT 10";
$resultTopVideos = $conexao->query($queryTopVideos);
$topVideos = [];
$topVideosViews = [];
while ($row = $resultTopVideos->fetch_assoc()) {
    $topVideos[] = mb_substr($row['nome_video'], 0, 30) . (mb_strlen($row['nome_video']) > 30 ? '...' : '');
    $topVideosViews[] = (int)$row['visualizacoes'];
}

// ========================================
// VISUALIZAÇÕES POR CATEGORIA
// ========================================
$queryCategoriasViews = "SELECT c.nome_categoria, SUM(v.visualizacoes) as total_views
                         FROM categoria c
                         INNER JOIN video_categoria vc ON c.id_categoria = vc.id_categoria
                         INNER JOIN video v ON vc.id_video = v.id_video
                         WHERE v.ativo = 1
                         GROUP BY c.id_categoria
                         ORDER BY total_views DESC";
$resultCategorias = $conexao->query($queryCategoriasViews);
$categorias = [];
$categoriasViews = [];
while ($row = $resultCategorias->fetch_assoc()) {
    $categorias[] = $row['nome_categoria'];
    $categoriasViews[] = (int)$row['total_views'];
}

// ========================================
// TOP 10 VÍDEOS MAIS BAIXADOS (PRÉVIAS)
// ========================================
$queryTopDownloads = "SELECT v.nome_video, COUNT(vdp.id_download) as total_downloads
                      FROM video v
                      INNER JOIN video_download_previa vdp ON v.id_video = vdp.id_video
                      WHERE v.ativo = 1
                      GROUP BY v.id_video
                      ORDER BY total_downloads DESC
                      LIMIT 10";
$resultTopDownloads = $conexao->query($queryTopDownloads);
$topDownloadVideos = [];
$topDownloadCounts = [];
while ($row = $resultTopDownloads->fetch_assoc()) {
    $topDownloadVideos[] = mb_substr($row['nome_video'], 0, 30) . (mb_strlen($row['nome_video']) > 30 ? '...' : '');
    $topDownloadCounts[] = (int)$row['total_downloads'];
}

// ========================================
// DISTRIBUIÇÃO DE VÍDEOS POR CATEGORIA
// ========================================
$queryDistribuicao = "SELECT c.nome_categoria, COUNT(vc.id_video) as total_videos
                      FROM categoria c
                      LEFT JOIN video_categoria vc ON c.id_categoria = vc.id_categoria
                      GROUP BY c.id_categoria
                      ORDER BY total_videos DESC";
$resultDistribuicao = $conexao->query($queryDistribuicao);
$categoriasDistribuicao = [];
$videosDistribuicao = [];
while ($row = $resultDistribuicao->fetch_assoc()) {
    $categoriasDistribuicao[] = $row['nome_categoria'];
    $videosDistribuicao[] = (int)$row['total_videos'];
}

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Repositório de Vídeos</title>
    <script src="logout_auto.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>
    
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(180deg, #d32f2f, #b71c1c);
            color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9em;
            text-transform: uppercase;
            opacity: 0.9;
            color: white;
            text-align: left;
        }

        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 0;
        }

        .stat-card .icon {
            font-size: 2em;
            opacity: 0.3;
            float: right;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        .chart-container h2 {
            margin: 0 0 20px 0;
            font-size: 1.2em;
            color: #333;
            text-align: left;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        .full-width-chart {
            grid-column: 1 / -1;
        }

        /* Dark Mode */
        body.dark-mode .chart-container {
            background: #1a1a1a;
            color: #fff;
        }

        body.dark-mode .chart-container h2 {
            color: #fff;
        }

        body.dark-mode .stat-card {
            background: linear-gradient(180deg, #d32f2f, #b71c1c);
        }

        /* ========================================
           RESPONSIVIDADE TOTAL
        ======================================== */

        /* Tablets grandes e pequenos (768px - 1024px) */
        @media (max-width: 1024px) {
            .dashboard-container {
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin-bottom: 30px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-card .number {
                font-size: 2.2em;
            }

            .charts-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }

            .chart-wrapper {
                height: 280px;
            }
        }

        /* Tablets em modo retrato (768px) */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 12px;
            }

            h1 {
                font-size: 1.5em;
                margin-bottom: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 25px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-card h3 {
                font-size: 0.8em;
                margin-bottom: 8px;
            }

            .stat-card .number {
                font-size: 2em;
            }

            .charts-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-top: 20px;
            }

            .chart-container {
                padding: 20px;
            }

            .chart-container h2 {
                font-size: 1.1em;
                margin-bottom: 15px;
            }

            .chart-wrapper {
                height: 250px;
            }
        }

        /* Mobile grande (576px - 640px) */
        @media (max-width: 640px) {
            .dashboard-container {
                padding: 10px;
            }

            h1 {
                font-size: 1.3em;
                margin-bottom: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h3 {
                font-size: 0.75em;
                margin-bottom: 6px;
            }

            .stat-card .number {
                font-size: 1.8em;
            }

            .chart-container {
                padding: 15px;
            }

            .chart-container h2 {
                font-size: 1em;
                margin-bottom: 12px;
            }

            .chart-wrapper {
                height: 220px;
            }
        }

        /* Mobile médio (480px) */
        @media (max-width: 480px) {
            .dashboard-container {
                padding: 8px;
            }

            h1 {
                font-size: 1.2em;
                margin-bottom: 12px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .stat-card h3 {
                font-size: 0.85em;
                margin: 0;
                flex: 1;
            }

            .stat-card .number {
                font-size: 2em;
                margin-left: 10px;
            }

            .charts-grid {
                gap: 15px;
                margin-top: 15px;
            }

            .chart-container {
                padding: 12px;
            }

            .chart-container h2 {
                font-size: 0.95em;
                margin-bottom: 10px;
            }

            .chart-wrapper {
                height: 200px;
            }
        }

        /* Mobile pequeno (≤ 360px) */
        @media (max-width: 360px) {
            .dashboard-container {
                padding: 5px;
            }

            h1 {
                font-size: 1.1em;
                margin-bottom: 10px;
            }

            .stats-grid {
                gap: 8px;
                margin-bottom: 15px;
            }

            .stat-card {
                padding: 12px;
            }

            .stat-card h3 {
                font-size: 0.75em;
            }

            .stat-card .number {
                font-size: 1.6em;
            }

            .chart-container {
                padding: 10px;
            }

            .chart-container h2 {
                font-size: 0.9em;
                margin-bottom: 8px;
            }

            .chart-wrapper {
                height: 180px;
            }
        }

        /* Landscape em mobile */
        @media (max-width: 900px) and (orientation: landscape) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 12px;
            }

            .stat-card h3 {
                font-size: 0.75em;
            }

            .stat-card .number {
                font-size: 1.5em;
            }

            .charts-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .full-width-chart {
                grid-column: 1 / -1;
            }

            .chart-wrapper {
                height: 200px;
            }
        }

        /* Ajustes para telas muito grandes */
        @media (min-width: 1920px) {
            .dashboard-container {
                max-width: 1800px;
            }

            .stat-card .number {
                font-size: 3em;
            }

            .chart-wrapper {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    
    <button class="menu-btn">☰</button>
    <div class="sidebar-overlay"></div>
    
    <sidebar class="sidebar">
        <br><br>
        <a href="gerenciar_videos.php"> Gerenciar Vídeos</a>
        <a href="gerenciar_categorias.php">Gerenciar Categorias</a>
   

        <div class="sidebar-user-wrapper">
            <div class="sidebar-user" id="usuarioDropdown">
                <div class="usuario-avatar" style="background-color: <?= $corAvatar ?>;">
                    <?= $iniciais ?>
                </div>
                <div class="usuario-dados">
                    <div class="usuario-nome"><?= $nome ?></div>
                    <div class="usuario-apelido"><?= $apelido ?></div>
                </div>
                <div class="usuario-menu" id="menuPerfil">
                    <a href="alterar_senha2.php">
                        <img class="icone" src="icones/cadeado1.png" alt="Alterar"> 
                        Alterar Senha
                    </a>
                    <a href="logout.php">
                        <img class="iconelogout" src="icones/logout1.png" alt="Logout">  
                        Sair
                    </a>
                </div>
            </div>
            <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro">
        </div>
    </sidebar>

    <div class="content">
        <div class="main">
            <div class="dashboard-container">
                <h1>Repositório de Vídeos</h1>

                <!-- Cards de Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="icon"></span>
                        <h3>Total de Vídeos</h3>
                        <div class="number"><?= number_format($totalVideos) ?></div>
                    </div>

                    <div class="stat-card">
                        <span class="icon"></span>
                        <h3>Total de Visualizações</h3>
                        <div class="number"><?= number_format($totalVisualizacoes) ?></div>
                    </div>

            

                    <div class="stat-card">
                        <span class="icon"></span>
                        <h3>Categorias</h3>
                        <div class="number"><?= number_format($totalCategorias) ?></div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="charts-grid">
                    <!-- Gráfico: Top 10 Vídeos Mais Vistos -->
                    <div class="chart-container full-width-chart">
                        <h2> Top 10 Vídeos Mais Vistos</h2>
                        <div class="chart-wrapper">
                            <canvas id="chartTopVideos"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico: Visualizações por Categoria (Pizza) -->
                    <div class="chart-container">
                        <h2> Visualizações por Categoria</h2>
                        <div class="chart-wrapper">
                            <canvas id="chartCategoriasPizza"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico: Visualizações por Categoria (Barras) -->
                    <div class="chart-container">
                        <h2> Visualizações por Categoria</h2>
                        <div class="chart-wrapper">
                            <canvas id="chartCategoriasBarras"></canvas>
                        </div>
                    </div>

                 

                    <!-- Gráfico: Distribuição de Vídeos por Categoria -->
                    <div class="chart-container">
                        <h2> Vídeos por Categoria</h2>
                        <div class="chart-wrapper">
                            <canvas id="chartDistribuicao"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
// ========================================
// CONFIGURAÇÃO DOS GRÁFICOS
// ========================================

// Cores vibrantes para os gráficos
const coresPrincipais = [
    '#d32f2f', '#ff6600', '#3498db', '#e74c3c', '#f39c12', 
    '#9b59b6', '#1abc9c', '#34495e', '#e67e22', '#16a085'
];

// Dados PHP para JavaScript
const topVideos = <?= json_encode($topVideos) ?>;
const topVideosViews = <?= json_encode($topVideosViews) ?>;
const categorias = <?= json_encode($categorias) ?>;
const categoriasViews = <?= json_encode($categoriasViews) ?>;
const topDownloadVideos = <?= json_encode($topDownloadVideos) ?>;
const topDownloadCounts = <?= json_encode($topDownloadCounts) ?>;
const categoriasDistribuicao = <?= json_encode($categoriasDistribuicao) ?>;
const videosDistribuicao = <?= json_encode($videosDistribuicao) ?>;

// ========================================
// GRÁFICO: Top 10 Vídeos Mais Vistos (Barras Horizontais)
// ========================================
const ctxTopVideos = document.getElementById('chartTopVideos').getContext('2d');
const chartTopVideos = new Chart(ctxTopVideos, {
    type: 'bar',
    data: {
        labels: topVideos,
        datasets: [{
            label: 'Visualizações',
            data: topVideosViews,
            backgroundColor: coresPrincipais,
            borderColor: coresPrincipais,
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Visualizações: ${context.parsed.x.toLocaleString('pt-BR')}`;
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('pt-BR');
                    }
                }
            }
        }
    }
});

// ========================================
// GRÁFICO: Visualizações por Categoria (Pizza)
// ========================================
const ctxCategoriasPizza = document.getElementById('chartCategoriasPizza').getContext('2d');
const chartCategoriasPizza = new Chart(ctxCategoriasPizza, {
    type: 'pie',
    data: {
        labels: categorias,
        datasets: [{
            data: categoriasViews,
            backgroundColor: coresPrincipais,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15, font: { size: 11 } }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${context.label}: ${value.toLocaleString('pt-BR')} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// ========================================
// GRÁFICO: Visualizações por Categoria (Barras)
// ========================================
const ctxCategoriasBarras = document.getElementById('chartCategoriasBarras').getContext('2d');
const chartCategoriasBarras = new Chart(ctxCategoriasBarras, {
    type: 'bar',
    data: {
        labels: categorias,
        datasets: [{
            label: 'Visualizações',
            data: categoriasViews,
            backgroundColor: coresPrincipais[0],
            borderColor: coresPrincipais[0],
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Visualizações: ${context.parsed.y.toLocaleString('pt-BR')}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('pt-BR');
                    }
                }
            }
        }
    }
});

// ========================================
// GRÁFICO: Top 10 Prévias Mais Baixadas
// ========================================
const ctxTopDownloads = document.getElementById('chartTopDownloads').getContext('2d');
const chartTopDownloads = new Chart(ctxTopDownloads, {
    type: 'bar',
    data: {
        labels: topDownloadVideos,
        datasets: [{
            label: 'Downloads',
            data: topDownloadCounts,
            backgroundColor: coresPrincipais,
            borderColor: coresPrincipais,
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Downloads: ${context.parsed.x}`;
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});

// ========================================
// GRÁFICO: Distribuição de Vídeos por Categoria (Doughnut)
// ========================================
const ctxDistribuicao = document.getElementById('chartDistribuicao').getContext('2d');
const chartDistribuicao = new Chart(ctxDistribuicao, {
    type: 'doughnut',
    data: {
        labels: categoriasDistribuicao,
        datasets: [{
            data: videosDistribuicao,
            backgroundColor: coresPrincipais,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15, font: { size: 11 } }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${context.label}: ${value} vídeos (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// ========================================
// DARK MODE - Atualizar cores dos gráficos
// ========================================
function updateChartColors() {
    const isDark = document.body.classList.contains('dark-mode');
    const textColor = isDark ? '#fff' : '#333';
    const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

    [chartTopVideos, chartCategoriasPizza, chartCategoriasBarras, chartTopDownloads, chartDistribuicao].forEach(chart => {
        if (chart.options.plugins.legend) {
            chart.options.plugins.legend.labels.color = textColor;
        }
        if (chart.options.scales) {
            Object.keys(chart.options.scales).forEach(scale => {
                chart.options.scales[scale].ticks.color = textColor;
                chart.options.scales[scale].grid.color = gridColor;
            });
        }
        chart.update();
    });
}

// Observar mudanças no dark mode
const observer = new MutationObserver(() => {
    updateChartColors();
});

observer.observe(document.body, {
    attributes: true,
    attributeFilter: ['class']
});
</script>

</body>
</html>