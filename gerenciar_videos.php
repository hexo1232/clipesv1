<?php
// gerenciar_videos.php
include "conexao.php";
include "verifica_login.php";
include "info_usuario.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario   = $_SESSION['usuario'];
$id_perfil = $usuario['idperfil'] ?? null;

if ($id_perfil != 1) {
    header("Location: login.php");
    exit;
}

// ── Categorias ──
$categorias = $conexao->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria")->fetchAll();

// ── Filtros ──
$params   = [];
$sql_base = " FROM video v
              LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = true
              LEFT JOIN usuario u ON v.id_usuario = u.id_usuario
              WHERE 1=1";

if (!empty($_GET['categoria'])) {
    $sql_base .= " AND EXISTS (SELECT 1 FROM video_categoria vc WHERE vc.id_video = v.id_video AND vc.id_categoria = ?)";
    $params[]  = $_GET['categoria'];
}
if (!empty($_GET['busca'])) {
    $busca     = "%" . trim($_GET['busca']) . "%";
    $sql_base .= " AND (v.nome_video ILIKE ? OR v.descricao ILIKE ?)";
    $params[]  = $busca;
    $params[]  = $busca;
}
if (isset($_GET['ativo']) && $_GET['ativo'] !== '') {
    $sql_base .= " AND v.ativo = ?";
    $params[]  = ($_GET['ativo'] == '1') ? true : false;
}

// ── Paginação ──
$limite       = 9;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$offset       = ($pagina_atual - 1) * $limite;

// ── Contagem ──
$stmt_count = $conexao->prepare("SELECT COUNT(*) " . $sql_base);
$stmt_count->execute($params);
$total_registros = (int) $stmt_count->fetchColumn();
$total_paginas   = ceil($total_registros / $limite);

// ── Vídeos ──
$stmt = $conexao->prepare(
    "SELECT v.*, vi.caminho_imagem, u.nome AS usuario_nome, u.apelido AS usuario_apelido"
    . $sql_base
    . " ORDER BY v.data_cadastro DESC LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$limite, $offset]));
$resultado = $stmt->fetchAll();

// ── Totais para os cards de estatística ──
$totalDownloads = $conexao->query("SELECT COUNT(*) FROM video_download_previa")->fetchColumn();
$totalVis       = $conexao->query("SELECT COALESCE(SUM(visualizacoes), 0) FROM video")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gerenciar Vídeos</title>
<link rel="stylesheet" href="css/admin.css">
<script src="logout_auto.js"></script>
<script src="js/darkmode2.js"></script>
<script src="js/sidebar.js"></script>
<script src="js/dropdown2.js"></script>

<style>
    /* ── Filtros ── */
    .filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
    .filters input, .filters select { padding: 10px; border: 1px solid #ddd; border-radius: 6px; }

    /* ── Estatísticas ── */
    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: linear-gradient(180deg, #d32f2f, #b71c1c);
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
    }
    .stat-card h3 { margin: 0 0 10px 0; font-size: 2em; }
    .stat-card p  { margin: 0; opacity: 0.9; }

    /* ── Toolbar ── */
    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #eee;
    }

    /* ── Grid de vídeos ── */
    .video-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    @media (max-width: 900px) { .video-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px) { .video-grid { grid-template-columns: 1fr; } }

    /* ── Card ── */
    .video-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform 0.2s;
        border: 1px solid #eee;
    }
    .video-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.15);
    }

    /* Imagem */
    .card-image-wrapper {
        position: relative;
        width: 100%;
        height: 180px;
        background: #ecf0f1;
    }
    .card-image-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Checkbox de seleção */
    .card-select {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 10;
        transform: scale(1.3);
        cursor: pointer;
    }

    /* Tag de status */
    .card-status-tag {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 5px 10px;
        border-radius: 4px;
        font-weight: bold;
        color: white;
        font-size: 0.85em;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .tag-ativo   { background: #e74c3c; }
    .tag-inativo { background: #7f8c8d; }

    /* Duração */
    .card-duration {
        position: absolute;
        bottom: 10px;
        right: 10px;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.8em;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Corpo */
    .card-body {
        padding: 15px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .card-title {
        font-size: 1.1em;
        font-weight: bold;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #2c3e50;
    }
    .card-meta {
        font-size: 0.85em;
        color: #7f8c8d;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .meta-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 5px;
    }
    .dot-green { background-color: #27ae60; }
    .dot-red   { background-color: #e74c3c; }

    /* Botões de ação */
    .card-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        border-top: 1px solid #eee;
    }
    .card-actions a,
    .card-actions button {
        padding: 12px;
        text-align: center;
        text-decoration: none;
        font-size: 0.9em;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: background 0.2s;
    }
    .action-edit { background: #3498db; color: white; }
    .action-edit:hover { background: #2980b9; }
    .action-toggle { background: #27ae60; color: white; }
    .action-toggle.is-active { background: #f39c12; }
    .action-toggle:hover { opacity: 0.9; }

    /* Dark mode */
    body.dark-mode .video-card { background: #2c3e50; border-color: #34495e; }
    body.dark-mode .card-title { color: #ecf0f1; }
    body.dark-mode .toolbar    { background: #34495e; border-color: #2c3e50; }
</style>
</head>
<body>

<button class="menu-btn">☰</button>
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
    <br><br>
    <a href="dashboard.php">Voltar ao Início</a>
    <a href="cadastrar_video.php">Adicionar Vídeo</a>

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
                    <img class="icone" src="icones/cadeado1.png" alt="Alterar"> Alterar Senha
                </a>
                <a href="logout.php">
                    <img class="iconelogout" src="icones/logout1.png" alt="Logout"> Sair
                </a>
            </div>
        </div>
        <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro">
    </div>
</sidebar>

<div class="content">
  <div class="main">
    <h1>Gerenciar Vídeos</h1>

    <!-- ── Estatísticas ── -->
    <div class="stats">
        <div class="stat-card">
            <h3><?= $total_registros ?></h3>
            <p>Total de Vídeos</p>
        </div>
        <div class="stat-card">
            <h3><?= $totalDownloads ?></h3>
            <p>Downloads de Prévias</p>
        </div>
        <div class="stat-card">
            <h3><?= number_format($totalVis) ?></h3>
            <p>Total de Visualizações</p>
        </div>
    </div>

    <!-- ── Filtros ── -->
    <form method="get" class="filters">
        <input type="text" name="busca" placeholder="Buscar vídeo..."
               value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">

        <select name="categoria">
            <option value="">Todas as Categorias</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id_categoria'] ?>"
                    <?= isset($_GET['categoria']) && $_GET['categoria'] == $cat['id_categoria'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nome_categoria']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="ativo">
            <option value="">Todos os Status</option>
            <option value="1" <?= isset($_GET['ativo']) && $_GET['ativo'] === '1' ? 'selected' : '' ?>>Ativos</option>
            <option value="0" <?= isset($_GET['ativo']) && $_GET['ativo'] === '0' ? 'selected' : '' ?>>Inativos</option>
        </select>

        <button type="submit"
                style="background:#3498db;color:white;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;">
            Filtrar
        </button>
        <button type="button" onclick="window.location='gerenciar_videos.php'"
                style="background:#95a5a6;color:white;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;">
            Limpar
        </button>
    </form>

    <!-- ── Grid + bulk delete ── -->
    <form method="post" action="excluir_videos.php" id="formExcluir">

        <div class="toolbar">
            <div>
                <label style="cursor:pointer;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" id="selectAll">
                    <strong>Selecionar Todos da Página</strong>
                </label>
            </div>
            <div>
                <span style="color:#7f8c8d;margin-right:15px;">
                    <?= count($resultado) ?> de <?= $total_registros ?> vídeos
                </span>
                <button type="submit"
                        onclick="return confirm('Excluir vídeos selecionados?')"
                        style="background:#e74c3c;color:white;padding:8px 16px;border:none;border-radius:4px;cursor:pointer;font-weight:bold;">
                    🗑️ Excluir Selecionados
                </button>
            </div>
        </div>

        <div class="video-grid">
            <?php foreach ($resultado as $v): ?>
                <div class="video-card">

                    <!-- Imagem + overlays -->
                    <div class="card-image-wrapper">
                        <input type="checkbox" name="videos_ids[]"
                               value="<?= $v['id_video'] ?>" class="card-select">

                        <?php if (!empty($v['caminho_imagem']) && file_exists($v['caminho_imagem'])): ?>
                            <img src="<?= $v['caminho_imagem'] ?>"
                                 alt="<?= htmlspecialchars($v['nome_video']) ?>">
                        <?php else: ?>
                            <div style="width:100%;height:100%;background:#bdc3c7;
                                        display:flex;align-items:center;justify-content:center;color:white;">
                                Sem Imagem
                            </div>
                        <?php endif; ?>

                        <span class="card-status-tag <?= $v['ativo'] ? 'tag-ativo' : 'tag-inativo' ?>">
                            <?= $v['ativo'] ? 'ATIVO' : 'INATIVO' ?>
                        </span>

                        <span class="card-duration">
                            ⏱ <?= $v['duracao'] ?? '00:00' ?>
                        </span>
                    </div>

                    <!-- Corpo -->
                    <div class="card-body">
                        <h3 class="card-title" title="<?= htmlspecialchars($v['nome_video']) ?>">
                            <?= htmlspecialchars($v['nome_video']) ?>
                        </h3>

                        <div class="card-meta">

                            <!-- Categorias -->
                            <div class="meta-row"
                                 style="color:#3498db;font-size:0.9em;font-weight:500;">
                                <?php
                                    $stmtCat = $conexao->prepare(
                                        "SELECT c.nome_categoria
                                         FROM categoria c
                                         INNER JOIN video_categoria vc ON c.id_categoria = vc.id_categoria
                                         WHERE vc.id_video = ?"
                                    );
                                    $stmtCat->execute([$v['id_video']]);
                                    $cats      = $stmtCat->fetchAll(PDO::FETCH_COLUMN);
                                    $catString = implode(', ', $cats);
                                    echo htmlspecialchars(mb_strimwidth($catString, 0, 30, '...'));
                                ?>
                            </div>

                            <!-- Status + visualizações -->
                            <div class="meta-row">
                                <span>
                                    <span class="status-indicator <?= $v['ativo'] ? 'dot-green' : 'dot-red' ?>"></span>
                                    <?= $v['ativo'] ? 'Online' : 'Offline' ?>
                                </span>
                                <span>👁 <?= number_format($v['visualizacoes']) ?></span>
                            </div>

                            <!-- Usuário + data -->
                            <div class="meta-row" style="font-size:0.8em;margin-top:5px;">
                                <span>👤 <?= htmlspecialchars($v['usuario_apelido'] ?? '') ?></span>
                                <span>📅 <?= date('d/m/y', strtotime($v['data_cadastro'])) ?></span>
                            </div>

                        </div>
                    </div>

                    <!-- Acções -->
                    <div class="card-actions">
                        <a href="editar_video.php?id_video=<?= $v['id_video'] ?>"
                           class="action-edit">
                            ✏️ Editar
                        </a>
                        <a href="toggle_video_status.php?id_video=<?= $v['id_video'] ?>&status=<?= $v['ativo'] ? 0 : 1 ?>"
                           class="action-toggle <?= $v['ativo'] ? 'is-active' : '' ?>"
                           onclick="return confirm('Deseja realmente alterar o status deste vídeo?')">
                            <?= $v['ativo'] ? '🚫 Desativar' : '✅ Ativar' ?>
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    </form>

    <!-- ── Paginação ── -->
    <div style="margin-top:30px;text-align:center;">
        <?php
        for ($i = 1; $i <= $total_paginas; $i++):
            $params_url = array_filter([
                'pagina'    => $i,
                'busca'     => $_GET['busca']     ?? '',
                'categoria' => $_GET['categoria'] ?? '',
                'ativo'     => $_GET['ativo']     ?? '',
            ], fn($v) => $v !== '');
        ?>
            <a href="?<?= http_build_query($params_url) ?>"
               style="padding:8px 12px;margin:0 3px;border-radius:4px;text-decoration:none;
                      background:<?= $i == $pagina_atual ? '#3498db' : '#ecf0f1' ?>;
                      color:<?= $i == $pagina_atual ? 'white' : '#2c3e50' ?>;">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>

  </div>
</div>

<script>
document.getElementById('selectAll').addEventListener('change', function () {
    document.querySelectorAll('input[name="videos_ids[]"]')
            .forEach(cb => cb.checked = this.checked);
});
</script>

</body>
</html>