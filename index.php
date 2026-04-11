9<?php
// index.php
include "verifica_login_opcional.php";
include "conexao.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$usuarioLogado = $_SESSION['usuario'] ?? null;
$id_perfil     = $usuarioLogado['idperfil'] ?? null;
$idUsuario     = $usuarioLogado['id_usuario'] ?? null;

$WHATSAPP_NUMBER = "258871054204";

// ── Registrar visualização (POST AJAX) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_visualizacao'])) {
    header('Content-Type: application/json');
    $idVideo = intval($_POST['id_video']);
    $ip      = $_SERVER['REMOTE_ADDR'];

    if ($idUsuario) {
        $stmt = $conexao->prepare("SELECT id_download FROM video_download_previa WHERE id_video = ? AND id_usuario = ?");
        $stmt->execute([$idVideo, $idUsuario]);
    } else {
        $stmt = $conexao->prepare("SELECT id_download FROM video_download_previa WHERE id_video = ? AND ip_address = ? AND id_usuario IS NULL");
        $stmt->execute([$idVideo, $ip]);
    }

    if ($stmt->rowCount() == 0) {
        $conexao->prepare("INSERT INTO video_download_previa (id_video, id_usuario, ip_address) VALUES (?, ?, ?)")
                ->execute([$idVideo, $idUsuario, $ip]);

        $conexao->prepare("UPDATE video SET visualizacoes = visualizacoes + 1 WHERE id_video = ?")
                ->execute([$idVideo]);

        $stmtCount = $conexao->prepare("SELECT visualizacoes FROM video WHERE id_video = ?");
        $stmtCount->execute([$idVideo]);
        echo json_encode(['success' => true, 'visualizacoes' => $stmtCount->fetchColumn()]);
    } else {
        $stmtCount = $conexao->prepare("SELECT visualizacoes FROM video WHERE id_video = ?");
        $stmtCount->execute([$idVideo]);
        echo json_encode(['success' => true, 'already_viewed' => true, 'visualizacoes' => $stmtCount->fetchColumn()]);
    }
    exit;
}

// ── Categorias para filtro ──
$lista_categorias = $conexao->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria")->fetchAll();

// ── Montar query base com filtros ──
$filtros  = [];
$sql_base = "FROM video v
             LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = true
             WHERE v.ativo = true"; 

if (!empty($_GET['categoria'])) {
    $sql_base .= " AND EXISTS (SELECT 1 FROM video_categoria vc WHERE vc.id_video = v.id_video AND vc.id_categoria = ?)";
    $filtros[] = $_GET['categoria'];
}
if (!empty($_GET['busca'])) {
    $busca     = "%" . trim($_GET['busca']) . "%";
    $sql_base .= " AND (v.nome_video ILIKE ? OR v.descricao ILIKE ?)";
    $filtros[] = $busca;
    $filtros[] = $busca;
}
if (!empty($_GET['duracao_min'])) {
    $sql_base .= " AND EXTRACT(EPOCH FROM v.duracao::interval) >= ?";
    $filtros[] = intval($_GET['duracao_min']) * 60;
}
if (!empty($_GET['duracao_max'])) {
    $sql_base .= " AND EXTRACT(EPOCH FROM v.duracao::interval) <= ?";
    $filtros[] = intval($_GET['duracao_max']) * 60;
}
if (!empty($_GET['preco_min'])) {
    $sql_base .= " AND v.preco >= ?";
    $filtros[] = floatval($_GET['preco_min']);
}
if (!empty($_GET['preco_max'])) {
    $sql_base .= " AND v.preco <= ?";
    $filtros[] = floatval($_GET['preco_max']);
}

// ── Paginação ──
$limite       = 12;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$offset       = ($pagina_atual - 1) * $limite;

// ── Contagem total ──
$stmt_count = $conexao->prepare("SELECT COUNT(*) " . $sql_base);
$stmt_count->execute($filtros);
$total_registros = (int) $stmt_count->fetchColumn();
$total_paginas   = ceil($total_registros / $limite);

// ── Buscar vídeos ──
$stmt = $conexao->prepare("SELECT v.*, vi.caminho_imagem " . $sql_base . " ORDER BY v.data_cadastro DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($filtros, [$limite, $offset]));
$videos           = $stmt->fetchAll();
$total_encontrados = count($videos);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Repository</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/basico.css">
<style>
.btn-whatsapp {
    background: linear-gradient(135deg, #25D366, #128C7E);
    color: white;
}
.btn-whatsapp:hover {
    background: linear-gradient(135deg, #128C7E, #075E54);
}

#infoToast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    max-width: 320px;
    background: #1e1e2e;
    color: #f0f0f0;
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.35);
    padding: 18px 20px 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    border-left: 4px solid #25D366;
    animation: slideInToast 0.4s ease;
    font-size: 0.88rem;
    line-height: 1.5;
}
@keyframes slideInToast {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}
#infoToast .toast-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
#infoToast .toast-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: #25D366;
    display: flex;
    align-items: center;
    gap: 6px;
}
#infoToast .toast-close {
    background: none;
    border: none;
    color: #aaa;
    cursor: pointer;
    font-size: 1rem;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}
#infoToast .toast-close:hover { color: #fff; }
#infoToast .toast-row {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.05);
    border-radius: 8px;
    padding: 8px 10px;
}
#infoToast .toast-row i {
    font-size: 1.2rem;
    min-width: 20px;
    text-align: center;
}
#infoToast .toast-row.whatsapp i { color: #25D366; }
#infoToast .toast-row.telegram  i { color: #2AABEE; }
#infoToast .toast-row.account   i { color: #f1c40f; }

#infoToast .toast-row span strong {
    display: block;
    font-size: 0.82rem;
    color: #ccc;
    font-weight: 600;
    margin-bottom: 1px;
}
#infoToast.hidden { display: none; }
</style>
</head>
<body>

<div id="infoToast">
    <div class="toast-header">
        <span class="toast-title">
            <i class="fas fa-circle-info"></i> How it works
        </span>
        <button class="toast-close" onclick="dismissToast()" title="Dismiss">
            <i class="fas fa-xmark"></i>
        </button>
    </div>
    
    <div class="toast-row account">
        <i class="fas fa-user-clock"></i>
        <span>
            <strong>Access</strong>
            Login is optional. You can browse and buy without an account.
        </span>
    </div>

    <div class="toast-row whatsapp">
        <i class="fab fa-whatsapp"></i>
        <span>
            <strong>Negotiation</strong>
            All purchases are negotiated via WhatsApp.
        </span>
    </div>
    <div class="toast-row telegram">
        <i class="fab fa-telegram"></i>
        <span>
            <strong>Delivery</strong>
            Videos are delivered through Telegram after payment.
        </span>
    </div>
</div>

<div class="topbar">
    <div class="container">
        <div class="logo">🎬 VideoHub</div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="#">Videos</a>
            <?php if ($usuarioLogado): ?>
                <a href="logout.php">Sign Out</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="main-container">

    <button class="filter-toggle-btn" id="filterToggle">
        <i class="fas fa-filter"></i>
        <span>Show Filters</span>
        <i class="fas fa-chevron-down"></i>
    </button>

    <div class="filters" id="filtersContainer">
        <h2><i class="fas fa-filter"></i> Search Filters</h2>
        <form method="get">
            <div class="filter-grid">

                <div class="filter-group">
                    <label>Search Video</label>
                    <input type="text" name="busca" placeholder="Video name..."
                           value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">
                </div>

                <div class="filter-group">
                    <label>Category</label>
                    <select name="categoria">
                        <option value="">All</option>
                        <?php foreach ($lista_categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>"
                                <?= isset($_GET['categoria']) && $_GET['categoria'] == $cat['id_categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome_categoria']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Minimum Duration (min)</label>
                    <input type="number" name="duracao_min" placeholder="e.g. 5" min="0"
                           value="<?= htmlspecialchars($_GET['duracao_min'] ?? '') ?>">
                </div>

                <div class="filter-group">
                    <label>Maximum Duration (min)</label>
                    <input type="number" name="duracao_max" placeholder="e.g. 60" min="0"
                           value="<?= htmlspecialchars($_GET['duracao_max'] ?? '') ?>">
                </div>

                <div class="filter-group">
                    <label>Minimum Price ($)</label>
                    <input type="number" name="preco_min" placeholder="e.g. 10" min="0" step="0.01"
                           value="<?= htmlspecialchars($_GET['preco_min'] ?? '') ?>">
                </div>

                <div class="filter-group">
                    <label>Maximum Price ($)</label>
                    <input type="number" name="preco_max" placeholder="e.g. 100" min="0" step="0.01"
                           value="<?= htmlspecialchars($_GET['preco_max'] ?? '') ?>">
                </div>

            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="button" onclick="window.location='index.php'" class="btn-filter btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </form>
    </div>

    <div class="count">
        <i class="fas fa-video"></i> <?= $total_encontrados ?> video(s) found
    </div>

    <div class="videos-grid">
        <?php foreach ($videos as $v): ?>
            <div class="video-card">
                <div class="video-thumbnail-wrapper">
                    <?php if (!empty($v['caminho_imagem'])): ?>
                        <img src="<?= htmlspecialchars($v['caminho_imagem']) ?>" class="video-thumbnail">
                    <?php else: ?>
                        <div class="video-thumbnail" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                    <?php endif; ?>

                    <div class="price-badge">$<?= number_format($v['preco'], 2) ?></div>
                    <?php if (!empty($v['duracao'])): ?>
                        <div class="duration-badge">
                            <i class="far fa-clock"></i> <?= $v['duracao'] ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="video-info">
                    <h3 class="video-title"><?= htmlspecialchars($v['nome_video']) ?></h3>

                    <div class="video-stats">
                        <span><i class="fas fa-eye"></i> <?= number_format($v['visualizacoes']) ?></span>
                        <span class="online-badge"><i class="fas fa-circle"></i> Online</span>
                    </div>

                    <div class="action-buttons">
                        <button onclick="abrirPreview(<?= $v['id_video'] ?>, '<?= addslashes($v['caminho_previa']) ?>')"
                                class="action-btn btn-preview">
                            <i class="far fa-play-circle"></i> Preview
                        </button>

                        <?php
                            $mensagem_whatsapp = urlencode(
                                "Hello! I'm interested in purchasing the following video:\n\n" .
                                "🎬 *Video:* " . $v['nome_video'] . "\n" .
                                "💰 *Price:* $" . number_format($v['preco'], 2) . "\n" .
                                "⏱ *Duration:* " . ($v['duracao'] ?? 'N/A') . "\n\n" .
                                "Please let me know how to proceed with the payment."
                            );
                            $link_whatsapp = "https://wa.me/" . $WHATSAPP_NUMBER . "?text=" . $mensagem_whatsapp;
                        ?>
                        <a href="<?= $link_whatsapp ?>" target="_blank" class="action-btn btn-whatsapp">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="<?= $link_whatsapp ?>" target="_blank" class="action-btn btn-pay">
                            <i class="fas fa-credit-card"></i> Buy Now
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_paginas; $i++):
                $params          = $_GET;
                $params['pagina'] = $i;
                $url             = '?' . http_build_query($params);
            ?>
                <a href="<?= $url ?>" class="<?= $i == $pagina_atual ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

</div>

<div id="modalPreview" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="fecharPreview()">&times;</span>
        <video id="videoPreview" class="video-player" controls>
            <source id="videoSource" src="" type="video/mp4">
        </video>
    </div>
</div>

<script>
function dismissToast() {
    const toast = document.getElementById('infoToast');
    toast.style.animation  = 'none';
    toast.style.opacity    = '0';
    toast.style.transform  = 'translateY(30px)';
    toast.style.transition = 'opacity 0.3s, transform 0.3s';
    setTimeout(() => toast.classList.add('hidden'), 300);
    sessionStorage.setItem('toastDismissed', '1');
}

window.addEventListener('DOMContentLoaded', () => {
    if (!sessionStorage.getItem('toastDismissed')) {
        const toast = document.getElementById('infoToast');
        toast.style.display = 'none';
        setTimeout(() => { toast.style.display = 'flex'; }, 1500);
    } else {
        document.getElementById('infoToast').classList.add('hidden');
    }
});

// Toggle Filters
const filterToggle     = document.getElementById('filterToggle');
const filtersContainer = document.getElementById('filtersContainer');

filterToggle.addEventListener('click', function () {
    filtersContainer.classList.toggle('show');
    filterToggle.classList.toggle('active');
    const span = filterToggle.querySelector('span');
    span.textContent = filtersContainer.classList.contains('show') ? 'Hide Filters' : 'Show Filters';
});

// Preview
function abrirPreview(idVideo, caminho) {
    document.getElementById('modalPreview').style.display = 'block';
    document.getElementById('videoSource').src = caminho;
    document.getElementById('videoPreview').load();
    document.body.style.overflow = 'hidden';

    const formData = new FormData();
    formData.append('registrar_visualizacao', '1');
    formData.append('id_video', idVideo);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .catch(err => console.error('Error registering view:', err));
}

function fecharPreview() {
    document.getElementById('modalPreview').style.display = 'none';
    const player = document.getElementById('videoPreview');
    player.pause();
    player.currentTime = 0;
    document.body.style.overflow = 'auto';
}

window.onclick = function (event) {
    if (event.target == document.getElementById('modalPreview')) fecharPreview();
};

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') fecharPreview();
});
</script>

</body>
</html>