<?php
include "verifica_login.php";
include "conexao.php"; // Deve ser a versão PDO que configuramos
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

<script src="js/darkmode2.js"></script>
<script src="js/sidebar.js"></script>
<script src="js/dropdown2.js"></script>

<style>
    /* ── Grid de vídeos ── */
    .video-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    /* ── Card Refatorado ── */
    .video-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
        border: 1px solid #edf2f7;
        position: relative;
    }
    
    .video-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.12);
    }

    /* Wrapper da Imagem */
    .card-image-wrapper {
        position: relative;
        width: 100%;
        height: 180px;
        background: #f7fafc;
        overflow: hidden;
    }
    
    .card-image-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .video-card:hover .card-image-wrapper img {
        transform: scale(1.05);
    }

    /* Checkbox Estilizado */
    .card-select-container {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 10;
        background: rgba(255, 255, 255, 0.9);
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .card-select-container input {
        cursor: pointer;
        margin: 0;
        width: 16px;
        height: 16px;
    }

    /* Tags de Status e Duração */
    .card-status-tag {
        position: absolute;
        top: 12px;
        right: 12px;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 700;
        color: white;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        z-index: 5;
    }
    .tag-ativo   { background: #38a169; }
    .tag-inativo { background: #718096; }

    .card-duration {
        position: absolute;
        bottom: 10px;
        right: 10px;
        background: rgba(0,0,0,0.8);
        color: #fff;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Corpo do Card */
    .card-body {
        padding: 16px;
        flex-grow: 1;
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 700;
        margin: 0 0 10px 0;
        color: #2d3748;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 2.8em;
    }

    .card-meta {
        font-size: 0.85rem;
        color: #718096;
    }

    .meta-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
    }

    .cat-badge {
        color: #3182ce;
        font-weight: 600;
        background: #ebf8ff;
        padding: 2px 6px;
        border-radius: 4px;
    }

    /* Botões de Ação */
    .card-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-top: 1px solid #edf2f7;
    }
    
    .card-actions a {
        padding: 12px;
        text-align: center;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 700;
        transition: all 0.2s;
    }
    
    .action-edit { 
        background: #fff; 
        color: #3182ce; 
    }
    .action-edit:hover { background: #f7fafc; }
    
    .action-toggle { 
        background: #fff; 
        color: #e53e3e; 
        border-left: 1px solid #edf2f7;
    }
    .action-toggle.is-active { color: #dd6b20; }
    .action-toggle:hover { background: #fff5f5; }

    /* Dark Mode */
    body.dark-mode .video-card { background: #1a202c; border-color: #2d3748; }
    body.dark-mode .card-title { color: #f7fafc; }
    body.dark-mode .action-edit, body.dark-mode .action-toggle { background: #2d3748; border-color: #4a5568; }
    body.dark-mode .card-select-container { background: #2d3748; }
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
    <div class="card-image-wrapper">
        <div class="card-select-container">
            <input type="checkbox" name="videos_ids[]" value="<?= $v['id_video'] ?>">
        </div>

        <span class="card-status-tag <?= $v['ativo'] ? 'tag-ativo' : 'tag-inativo' ?>">
            <?= $v['ativo'] ? 'ATIVO' : 'INATIVO' ?>
        </span>

        <?php if (!empty($v['caminho_imagem'])): ?>
            <img src="<?= htmlspecialchars($v['caminho_imagem']) ?>" alt="Capa" loading="lazy" />
        <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#cbd5e0;">Sem imagem</div>
        <?php endif; ?>

        <span class="card-duration">
            <?= $v['duracao'] ? '⏱ ' . $v['duracao'] : '00:00' ?>
        </span>
    </div>

    <div class="card-body">
        <h3 class="card-title" title="<?= htmlspecialchars($v['nome_video']) ?>">
            <?= htmlspecialchars($v['nome_video']) ?>
        </h3>

        <div class="card-meta">
            <div class="meta-row">
                <span class="cat-badge">
                    <?php
                        $stmtCat = $conexao->prepare("SELECT c.nome_categoria FROM categoria c INNER JOIN video_categoria vc ON c.id_categoria = vc.id_categoria WHERE vc.id_video = ?");
                        $stmtCat->execute([$v['id_video']]);
                        $cats = $stmtCat->fetchAll(PDO::FETCH_COLUMN);
                        echo htmlspecialchars(mb_strimwidth(implode(', ', $cats), 0, 25, '...'));
                    ?>
                </span>
                <span>👁 <?= number_format($v['visualizacoes']) ?></span>
            </div>

            <div class="meta-row" style="margin-top:10px; color:#a0aec0; font-size:0.75rem;">
                <span>👤 <?= htmlspecialchars($v['usuario_apelido'] ?? 'Admin') ?></span>
                <span>📅 <?= date('d/m/y', strtotime($v['data_cadastro'])) ?></span>
            </div>
        </div>
    </div>

    <div class="card-actions">
        <a href="editar_video.php?id_video=<?= $v['id_video'] ?>" class="action-edit">
            ✏️ Editar
        </a>
        <a href="toggle_video_status.php?id_video=<?= $v['id_video'] ?>&status=<?= $v['ativo'] ? 0 : 1 ?>" 
           class="action-toggle <?= $v['ativo'] ? 'is-active' : '' ?>"
           onclick="return confirm('Alterar status deste vídeo?')">
            <?= $v['ativo'] ? '🚫 Desativar' : '✅ Ativar' ?>
        </a>
    </div>
</div>            <?php endforeach; ?>
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