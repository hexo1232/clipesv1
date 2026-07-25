<?php
// index.php
$paypal_client_id = getenv('PAYPAL_CLIENT_ID');
$TELEGRAM_LINK = getenv('TELEGRAM_LINK') ?: "https://t.me/";

include "verifica_login_opcional.php";
include "conexao.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$usuarioLogado = $_SESSION['usuario'] ?? null;
$id_perfil     = $usuarioLogado['idperfil'] ?? null;
$idUsuario     = $usuarioLogado['id_usuario'] ?? null;

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

        echo json_encode([
            'success' => true,
            'visualizacoes' => $stmtCount->fetchColumn()
        ]);
    } else {
        $stmtCount = $conexao->prepare("SELECT visualizacoes FROM video WHERE id_video = ?");
        $stmtCount->execute([$idVideo]);

        echo json_encode([
            'success' => true,
            'already_viewed' => true,
            'visualizacoes' => $stmtCount->fetchColumn()
        ]);
    }

    exit;
}

// ── Categorias para filtro ──
$lista_categorias = $conexao
    ->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria")
    ->fetchAll();

// ── Montar query base com filtros ──
$filtros  = [];
$sql_base = "FROM video v
             LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = true
             WHERE v.ativo = true";

if (!empty($_GET['categoria'])) {
    $sql_base .= " AND EXISTS (
        SELECT 1 FROM video_categoria vc
        WHERE vc.id_video = v.id_video
        AND vc.id_categoria = ?
    )";
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

// ── Buscar vídeos ──
$stmt = $conexao->prepare("SELECT v.*, vi.caminho_imagem " . $sql_base . " ORDER BY v.data_cadastro DESC");
$stmt->execute($filtros);

$videos            = $stmt->fetchAll();
$total_encontrados = count($videos);

$videoDestaque = !empty($videos) ? $videos[0] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>VelvetStream — Premium Video Experience</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<link rel="stylesheet" href="css/basico.css">

<?php if (!empty($paypal_client_id)): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypal_client_id) ?>&currency=USD"></script>
<?php endif; ?>

<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='18' fill='%23060606'/%3E%3Cpath d='M14 18h36L32 52 14 18Z' fill='%23e50914'/%3E%3Cpath d='M22 18h20L32 39 22 18Z' fill='%23ffcc70' opacity='.95'/%3E%3C/svg%3E">

<style>
*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --bg: #050505;
    --bg2: #0b0b0f;
    --card: #111116;
    --card2: #181820;
    --text: #ffffff;
    --muted: #a7a7b3;
    --soft: rgba(255,255,255,0.08);
    --border: rgba(255,255,255,0.12);
    --red: #e50914;
    --red2: #ff2d3d;
    --gold: #ffcc70;
    --orange: #ff8a00;
    --telegram: #2AABEE;
    --green: #22c55e;
    --shadow: 0 30px 90px rgba(0,0,0,0.75);
    --radius: 22px;
}

html {
    scroll-behavior: smooth;
}

body {
    min-height: 100vh;
    background:
        radial-gradient(circle at 15% 5%, rgba(229,9,20,0.35), transparent 34%),
        radial-gradient(circle at 88% 0%, rgba(255,204,112,0.18), transparent 28%),
        linear-gradient(180deg, #050505 0%, #08080d 48%, #050505 100%);
    color: var(--text);
    font-family: 'Inter', sans-serif;
    overflow-x: hidden;
}

/* ─────────────────────────────
   TOPBAR
───────────────────────────── */
.topbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: linear-gradient(180deg, rgba(0,0,0,0.88), rgba(0,0,0,0.35));
    backdrop-filter: blur(18px);
    z-index: 999;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.topbar-inner {
    max-width: 1500px;
    height: 78px;
    margin: 0 auto;
    padding: 0 34px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: white;
}

.brand-mark {
    width: 44px;
    height: 44px;
    border-radius: 15px;
    background:
        linear-gradient(135deg, var(--red), #7f0008),
        radial-gradient(circle at 30% 20%, rgba(255,255,255,0.5), transparent 30%);
    display: grid;
    place-items: center;
    box-shadow: 0 0 34px rgba(229,9,20,0.45);
}

.brand-mark i {
    color: white;
    font-size: 1.1rem;
}

.brand-name {
    display: flex;
    flex-direction: column;
    line-height: 1;
}

.brand-name strong {
    font-family: 'Anton', sans-serif;
    font-size: 1.75rem;
    letter-spacing: 1px;
    color: #fff;
}

.brand-name span {
    font-size: 0.68rem;
    color: var(--gold);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2.8px;
}

.nav {
    display: flex;
    align-items: center;
    gap: 10px;
}

.nav a {
    color: rgba(255,255,255,0.78);
    text-decoration: none;
    font-size: 0.92rem;
    font-weight: 700;
    padding: 10px 16px;
    border-radius: 999px;
    transition: 0.22s ease;
}

.nav a:hover {
    color: white;
    background: rgba(255,255,255,0.09);
}

.nav .nav-cta {
    background: var(--red);
    color: white;
    box-shadow: 0 10px 25px rgba(229,9,20,0.35);
}

/* ─────────────────────────────
   HERO
───────────────────────────── */
.hero {
    position: relative;
    min-height: 760px;
    padding: 140px 34px 70px;
    display: flex;
    align-items: center;
    overflow: hidden;
}

.hero-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
}

.hero-bg::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        linear-gradient(90deg, #050505 0%, rgba(5,5,5,0.96) 24%, rgba(5,5,5,0.60) 54%, rgba(5,5,5,0.92) 100%),
        linear-gradient(180deg, transparent 0%, #050505 100%);
    z-index: 2;
}

.hero-bg::after {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 78% 30%, rgba(229,9,20,0.32), transparent 28%),
        radial-gradient(circle at 70% 20%, rgba(255,204,112,0.16), transparent 20%);
    z-index: 3;
}

.hero-poster {
    position: absolute;
    right: 0;
    top: 0;
    width: 62%;
    height: 100%;
    object-fit: cover;
    object-position: center right;
    opacity: 0.52;
    filter: saturate(1.15) contrast(1.08);
    background: #050505;
}

.hero-fallback {
    position: absolute;
    right: 0;
    top: 0;
    width: 62%;
    height: 100%;
    background:
        linear-gradient(135deg, rgba(229,9,20,0.35), rgba(255,204,112,0.10)),
        radial-gradient(circle at 50% 40%, rgba(255,255,255,0.12), transparent 30%),
        #111;
}

.hero-content {
    position: relative;
    z-index: 5;
    max-width: 760px;
}

.hero-kicker {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 8px 15px;
    border-radius: 999px;
    background: rgba(229,9,20,0.18);
    border: 1px solid rgba(229,9,20,0.45);
    color: #fff;
    font-size: 0.76rem;
    font-weight: 900;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 22px;
}

.hero-title {
    font-family: 'Anton', sans-serif;
    font-size: clamp(3.4rem, 9vw, 8.2rem);
    line-height: 0.9;
    letter-spacing: 1px;
    margin-bottom: 20px;
    text-transform: uppercase;
    max-width: 900px;
}

.hero-title span {
    color: var(--red);
    text-shadow: 0 0 44px rgba(229,9,20,0.55);
}

.hero-desc {
    color: rgba(255,255,255,0.78);
    font-size: 1.08rem;
    line-height: 1.8;
    max-width: 600px;
    margin-bottom: 30px;
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 34px;
}

.hero-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 54px;
    padding: 0 26px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.96rem;
    font-weight: 900;
    transition: 0.22s ease;
}

.hero-btn.primary {
    background: var(--red);
    color: white;
    box-shadow: 0 18px 40px rgba(229,9,20,0.35);
}

.hero-btn.secondary {
    background: rgba(255,255,255,0.14);
    color: white;
    backdrop-filter: blur(10px);
}

.hero-btn:hover {
    transform: translateY(-3px);
    filter: brightness(1.1);
}

.hero-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
}

.metric {
    min-width: 132px;
    padding: 16px 18px;
    border: 1px solid rgba(255,255,255,0.11);
    border-radius: 18px;
    background: rgba(255,255,255,0.07);
    backdrop-filter: blur(16px);
}

.metric strong {
    display: block;
    font-size: 1.45rem;
    font-weight: 900;
    color: white;
}

.metric span {
    display: block;
    color: var(--muted);
    font-size: 0.78rem;
    font-weight: 700;
    margin-top: 3px;
}

/* ─────────────────────────────
   PAGE
───────────────────────────── */
.page {
    max-width: 1500px;
    margin: 0 auto;
    padding: 0 34px 90px;
}

/* ─────────────────────────────
   CONTROL PANEL / FILTERS
───────────────────────────── */
.control-panel {
    margin-top: -42px;
    position: relative;
    z-index: 10;
    background: rgba(15,15,20,0.88);
    border: 1px solid var(--border);
    border-radius: 26px;
    box-shadow: var(--shadow);
    backdrop-filter: blur(20px);
    overflow: hidden;
}

.control-header {
    padding: 22px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}

.section-title {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.section-title strong {
    font-size: 1.2rem;
    font-weight: 900;
}

.section-title span {
    color: var(--muted);
    font-size: 0.88rem;
}

.filter-toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 44px;
    padding: 0 18px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.08);
    color: white;
    cursor: pointer;
    font-weight: 900;
    transition: 0.22s ease;
}

.filter-toggle-btn:hover {
    background: var(--red);
    border-color: var(--red);
}

.filter-toggle-btn .fa-chevron-down {
    transition: transform 0.25s ease;
}

.filter-toggle-btn.active .fa-chevron-down {
    transform: rotate(180deg);
}

.filters {
    display: none;
    padding: 24px;
    background: rgba(255,255,255,0.03);
}

.filters.show {
    display: block;
}

.filter-grid {
    display: grid;
    grid-template-columns: 1.3fr 1fr repeat(4, 0.75fr);
    gap: 14px;
    margin-bottom: 18px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--muted);
    font-size: 0.72rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 1.2px;
}

.filter-group input,
.filter-group select {
    width: 100%;
    height: 48px;
    border: 1px solid rgba(255,255,255,0.12);
    background: #0c0c11;
    border-radius: 14px;
    padding: 0 14px;
    color: white;
    outline: none;
    font-family: inherit;
    font-weight: 700;
}

.filter-group input:focus,
.filter-group select:focus {
    border-color: var(--red);
    box-shadow: 0 0 0 4px rgba(229,9,20,0.15);
}

.filter-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.btn-filter {
    min-height: 46px;
    padding: 0 22px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 900;
    transition: 0.22s ease;
}

.btn-primary {
    background: var(--red);
    color: white;
}

.btn-secondary {
    background: rgba(255,255,255,0.10);
    color: white;
}

.btn-filter:hover {
    transform: translateY(-2px);
    filter: brightness(1.1);
}

/* ─────────────────────────────
   COLLECTION HEADER
───────────────────────────── */
.rail-header {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 20px;
    margin: 46px 0 22px;
}

.rail-title h2 {
    font-size: clamp(1.6rem, 3vw, 2.4rem);
    font-weight: 900;
    letter-spacing: -1px;
}

.rail-title p {
    color: var(--muted);
    margin-top: 6px;
}

.count-pill {
    padding: 10px 16px;
    border-radius: 999px;
    background: rgba(255,255,255,0.08);
    border: 1px solid var(--border);
    color: var(--muted);
    font-weight: 800;
    white-space: nowrap;
}

.count-pill strong {
    color: white;
}

/* ─────────────────────────────
   VIDEO GRID / CARD
───────────────────────────── */
.videos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
    gap: 22px;
}

.video-card {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    background: var(--card);
    border: 1px solid rgba(255,255,255,0.08);
    min-height: 470px;
    box-shadow: 0 22px 50px rgba(0,0,0,0.35);
    transition: 0.25s ease;
}

.video-card:hover {
    transform: translateY(-8px);
    border-color: rgba(229,9,20,0.55);
    box-shadow: 0 28px 80px rgba(0,0,0,0.72);
}

/* ─────────────────────────────
   THUMBNAIL / CAPA / HOVER PREVIEW
───────────────────────────── */
.video-thumbnail-wrapper {
    position: relative;
    width: 100%;
    height: 240px;
    overflow: hidden;
    background: #111;
    border-radius: 20px 20px 0 0;
}

/* Imagem da capa */
.video-thumbnail {
    position: absolute;
    inset: 0;
    z-index: 1;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center center;
    display: block;
    opacity: 1;
    transition: transform 0.45s ease, opacity 0.28s ease;
}

/* Fallback sem imagem */
.no-thumb {
    position: absolute;
    inset: 0;
    z-index: 1;
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    background:
        radial-gradient(circle, rgba(229,9,20,0.20), transparent 38%),
        linear-gradient(135deg, #16161f, #09090d);
}

.no-thumb i {
    font-size: 3.5rem;
    color: rgba(255,255,255,0.18);
}

.preview-zone {
    cursor: pointer;
}

/* Vídeo inline do hover */
.inline-preview-video {
    position: absolute;
    inset: 0;
    z-index: 2;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center center;
    background: #000;
    opacity: 0;
    transform: scale(1.03);
    transition: opacity 0.28s ease, transform 0.28s ease;
}

/* Overlay leve para dar contraste sem esconder a capa */
.video-overlay {
    position: absolute;
    inset: 0;
    z-index: 3;
    pointer-events: none;
    background: linear-gradient(
        180deg,
        rgba(0,0,0,0.02) 0%,
        rgba(0,0,0,0.06) 42%,
        rgba(0,0,0,0.42) 100%
    );
}

/* Hover normal da imagem */
.video-card:hover .video-thumbnail {
    transform: scale(1.05);
    opacity: 0.96;
}

/* Quando a prévia estiver a tocar */
.preview-zone.preview-playing .inline-preview-video {
    opacity: 1;
    transform: scale(1);
}

.preview-zone.preview-playing .preview-cover {
    opacity: 0;
}

/* Legenda Preview */
.preview-hint {
    position: absolute;
    left: 50%;
    top: 50%;
    z-index: 6;
    transform: translate(-50%, -50%) scale(0.92);
    min-width: 112px;
    height: 44px;
    padding: 0 18px;
    border-radius: 999px;
    background: rgba(0,0,0,0.72);
    border: 1px solid rgba(255,255,255,0.20);
    backdrop-filter: blur(12px);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    font-weight: 900;
    opacity: 0;
    transition: 0.22s ease;
    pointer-events: none;
}

.preview-zone:hover .preview-hint {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
}

.preview-zone.preview-playing .preview-hint {
    opacity: 0;
}

/* Borda vermelha no hover */
.preview-zone::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 5;
    border: 2px solid transparent;
    border-radius: 20px 20px 0 0;
    pointer-events: none;
    transition: border-color 0.22s ease;
}

.preview-zone:hover::after {
    border-color: rgba(229,9,20,0.65);
}

/* Badges */
.quality-badge {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 7;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.22);
    backdrop-filter: blur(10px);
    color: white;
    padding: 7px 11px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 900;
}

.price-badge {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 7;
    background: var(--red);
    color: white;
    padding: 8px 13px;
    border-radius: 999px;
    font-weight: 900;
    box-shadow: 0 14px 30px rgba(229,9,20,0.35);
}

.duration-badge {
    position: absolute;
    left: 14px;
    bottom: 14px;
    z-index: 7;
    color: white;
    background: rgba(0,0,0,0.62);
    border: 1px solid rgba(255,255,255,0.14);
    backdrop-filter: blur(8px);
    border-radius: 999px;
    padding: 6px 10px;
    font-weight: 900;
    font-size: 0.82rem;
}

/* ─────────────────────────────
   CARD INFO
───────────────────────────── */
.video-info {
    padding: 20px;
}

.video-title {
    font-size: 1.06rem;
    line-height: 1.4;
    font-weight: 900;
    margin-bottom: 12px;
    color: white;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 48px;
}

.video-description {
    color: var(--muted);
    font-size: 0.86rem;
    line-height: 1.6;
    margin-bottom: 16px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 44px;
}

.video-stats {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: var(--muted);
    font-size: 0.82rem;
    font-weight: 800;
    margin-bottom: 16px;
}

.video-stats span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.online-badge {
    color: var(--green);
}

/* ─────────────────────────────
   BUTTONS
───────────────────────────── */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 14px; /* mais respiro entre os grupos */
}

.btn-buy {
    font-size: 0.88rem;
    gap: 10px;
}

.btn-buy .fa-paypal {
    font-size: 1.3rem;
}

.btn-buy-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
}

.btn-buy-text strong {
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.3px;
}

.btn-buy-text small {
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--gold);
}

.action-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.action-btn {
    min-height: 46px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-family: inherit;
    font-size: 0.88rem;
    font-weight: 900;
    transition: 0.22s ease;
}

.btn-preview {
    background: rgba(255,255,255,0.11);
    border: 1px solid rgba(255,255,255,0.11);
}

.btn-telegram {
    background: var(--telegram);
}

.btn-buy {
    background: #003087; /* Navy oficial do PayPal */
    box-shadow: 0 16px 35px rgba(0,48,135,0.40);
}

.btn-buy:hover {
    background: #001c64; /* navy mais escuro no hover */
}

.btn-message {
    background: rgba(255,255,255,0.10);
    border: 1px solid rgba(255,255,255,0.13);
    color: white;
}

.btn-message:hover {
    background: rgba(255,255,255,0.16);
}

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.1);
}
.btn-buy .fa-paypal {
    color: #ffc439; /* amarelo característico do logo PayPal */
}
/* ─────────────────────────────
   EMPTY STATE
───────────────────────────── */
.empty-state {
    grid-column: 1 / -1;
    min-height: 340px;
    border-radius: 26px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.05);
    display: grid;
    place-items: center;
    text-align: center;
    padding: 40px;
}

.empty-state i {
    font-size: 4rem;
    color: rgba(255,255,255,0.16);
    margin-bottom: 18px;
}

.empty-state h3 {
    font-size: 1.7rem;
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--muted);
}

/* ─────────────────────────────
   TOAST
───────────────────────────── */
#infoToast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    width: min(360px, calc(100vw - 40px));
    z-index: 1200;
    background: rgba(14,14,18,0.92);
    border: 1px solid rgba(255,255,255,0.12);
    backdrop-filter: blur(18px);
    border-radius: 20px;
    box-shadow: var(--shadow);
    padding: 18px;
    animation: toastIn 0.35s ease;
}

@keyframes toastIn {
    from {
        opacity: 0;
        transform: translateY(24px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.toast-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.toast-title {
    color: white;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 8px;
}

.toast-title i {
    color: var(--red);
}

.toast-close {
    background: transparent;
    border: none;
    color: var(--muted);
    cursor: pointer;
    font-size: 1rem;
}

.toast-row {
    display: flex;
    gap: 12px;
    color: var(--muted);
    font-size: 0.88rem;
    line-height: 1.5;
    padding: 12px;
    background: rgba(255,255,255,0.06);
    border-radius: 14px;
}

.toast-row i {
    color: var(--gold);
    margin-top: 3px;
}

.toast-row strong {
    display: block;
    color: white;
    margin-bottom: 2px;
}

#infoToast.hidden {
    display: none;
}

/* ─────────────────────────────
   PREVIEW MODAL
───────────────────────────── */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.88);
    backdrop-filter: blur(18px);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.modal[style*="display: block"] {
    display: flex !important;
}

.modal-content {
    position: relative;
    width: min(980px, 96vw);
    background: #050505;
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.16);
    box-shadow: var(--shadow);
}

.close-modal {
    position: absolute;
    top: 14px;
    right: 18px;
    z-index: 10;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    width: 42px;
    height: 42px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    background: rgba(0,0,0,0.55);
}

.video-player {
    width: 100%;
    max-height: 82vh;
    display: block;
    background: black;
}

/* ─────────────────────────────
   PAYPAL MODAL
───────────────────────────── */
#paypalModal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 3000;
    background: rgba(0,0,0,0.88);
    backdrop-filter: blur(14px);
    align-items: center;
    justify-content: center;
    padding: 24px;
}

#paypalModal.open {
    display: flex !important;
}

.paypal-modal-box {
    width: min(440px, 94vw);
    background: #111116;
    border: 1px solid rgba(255,255,255,0.14);
    border-radius: 22px;
    padding: 28px;
    box-shadow: 0 30px 90px rgba(0,0,0,0.75);
}

.paypal-modal-box h3 {
    color: white;
    font-size: 1.15rem;
    font-weight: 900;
    margin-bottom: 8px;
}

.pm-video-name {
    color: var(--muted);
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 10px;
}

.pm-price {
    color: var(--gold);
    font-size: 1.55rem;
    font-weight: 900;
    margin-bottom: 18px;
}

#paypal-button-container {
    min-height: 50px;
}

#paypal-error-msg {
    display: none;
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(229,9,20,0.12);
    border: 1px solid rgba(229,9,20,0.32);
    color: #ff7b84;
    font-size: 0.88rem;
    text-align: center;
}

.pm-cancel {
    margin-top: 16px;
    color: var(--muted);
    text-align: center;
    cursor: pointer;
    font-weight: 800;
}

.pm-cancel:hover {
    color: white;
}

/* ─────────────────────────────
   RESPONSIVE
───────────────────────────── */
@media (max-width: 1100px) {
    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .hero-poster,
    .hero-fallback {
        width: 100%;
        opacity: 0.34;
    }
}

@media (max-width: 760px) {
    .topbar-inner {
        height: auto;
        min-height: 78px;
        padding: 14px 18px;
        flex-direction: column;
        gap: 12px;
    }

    .nav {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }

    .nav a {
        font-size: 0.82rem;
        padding: 8px 12px;
    }

    .hero {
        min-height: auto;
        padding: 170px 20px 80px;
    }

    .hero-title {
        font-size: 4rem;
    }

    .page {
        padding: 0 16px 70px;
    }

    .control-header,
    .rail-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .videos-grid {
        grid-template-columns: 1fr;
    }

    .action-row {
        grid-template-columns: 1fr;
    }

    .hero-actions {
        flex-direction: column;
    }

    .hero-btn {
        width: 100%;
    }

    .video-thumbnail-wrapper {
        height: 230px;
    }
}
</style>
</head>

<body>

<!-- ── TOAST ── -->
<div id="infoToast">
    <div class="toast-header">
        <span class="toast-title">
            <i class="fas fa-bolt"></i> Instant Access
        </span>
        <button class="toast-close" onclick="dismissToast()" title="Dismiss">
            <i class="fas fa-xmark"></i>
        </button>
    </div>

    <div class="toast-row">
        <i class="fas fa-user-shield"></i>
        <span>
            <strong>No account required</strong>
            Browse previews freely. To buy, send a message and receive fast support.
        </span>
    </div>
</div>

<!-- ── TOPBAR ── -->
<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand">
            <div class="brand-mark">
                <i class="fas fa-play"></i>
            </div>
            <div class="brand-name">
                <strong>VelvetStream</strong>
                <span>Premium Vault</span>
            </div>
        </a>

        <nav class="nav">
            <a href="index.php">Home</a>
            <a href="#collection">Collection</a>
            <a href="#filtersContainer">Filters</a>

            <?php if ($usuarioLogado): ?>
                <a href="logout.php" class="nav-cta">
                    <i class="fas fa-arrow-right-from-bracket"></i> Sign Out
                </a>
            <?php else: ?>
                <a href="login.php" class="nav-cta">
                    <i class="fas fa-user"></i> Login
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- ── HERO ── -->
<section class="hero">
    <div class="hero-bg">
        <?php if ($videoDestaque && !empty($videoDestaque['caminho_imagem'])): ?>
            <img
    src="stream_media.php?id=<?= (int)$videoDestaque['id_video'] ?>&tipo=imagem"
    class="hero-poster"
    alt="<?= htmlspecialchars($videoDestaque['nome_video']) ?>"
>
        <?php else: ?>
            <div class="hero-fallback"></div>
        <?php endif; ?>
    </div>

    <div class="hero-content">
        <div class="hero-kicker">
            <i class="fas fa-fire"></i>
            Premium Video Experience
        </div>

        <h1 class="hero-title">
            Stream the <span>Exclusive</span> Vault
        </h1>

        <p class="hero-desc">
            Discover premium videos, watch previews, filter your favorite content and request instant delivery directly through Telegram.
        </p>

        <div class="hero-actions">
            <a href="#collection" class="hero-btn primary">
                <i class="fas fa-play"></i> Browse Collection
            </a>

            <a href="<?= htmlspecialchars($TELEGRAM_LINK) ?>" target="_blank" rel="noopener" class="hero-btn secondary">
                <i class="fab fa-telegram"></i> Talk on Telegram
            </a>
        </div>

        <div class="hero-metrics">
            <div class="metric">
                <strong><?= number_format($total_encontrados) ?></strong>
                <span>Available videos</span>
            </div>

            <div class="metric">
                <strong>24/7</strong>
                <span>Telegram support</span>
            </div>

            <div class="metric">
                <strong>Fast</strong>
                <span>Delivery process</span>
            </div>
        </div>
    </div>
</section>

<main class="page">

    <!-- ── CONTROL PANEL ── -->
    <section class="control-panel">
        <div class="control-header">
            <div class="section-title">
                <strong>Find your next premium video</strong>
                <span>Search by name, category, duration or price range.</span>
            </div>

            <button class="filter-toggle-btn" id="filterToggle">
                <i class="fas fa-sliders"></i>
                <span>Show Filters</span>
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div class="filters" id="filtersContainer">
            <form method="get">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Search</label>
                        <input
                            type="text"
                            name="busca"
                            placeholder="Search video..."
                            value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label>Category</label>
                        <select name="categoria">
                            <option value="">All Categories</option>

                            <?php foreach ($lista_categorias as $cat): ?>
                                <option
                                    value="<?= $cat['id_categoria'] ?>"
                                    <?= isset($_GET['categoria']) && $_GET['categoria'] == $cat['id_categoria'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($cat['nome_categoria']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Min Duration</label>
                        <input
                            type="number"
                            name="duracao_min"
                            placeholder="Min"
                            min="0"
                            value="<?= htmlspecialchars($_GET['duracao_min'] ?? '') ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label>Max Duration</label>
                        <input
                            type="number"
                            name="duracao_max"
                            placeholder="Max"
                            min="0"
                            value="<?= htmlspecialchars($_GET['duracao_max'] ?? '') ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label>Min Price</label>
                        <input
                            type="number"
                            name="preco_min"
                            placeholder="$ Min"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars($_GET['preco_min'] ?? '') ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label>Max Price</label>
                        <input
                            type="number"
                            name="preco_max"
                            placeholder="$ Max"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars($_GET['preco_max'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn-filter btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>

                    <button type="button" onclick="window.location='index.php'" class="btn-filter btn-secondary">
                        <i class="fas fa-rotate-left"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- ── COLLECTION ── -->
    <section id="collection">
        <div class="rail-header">
            <div class="rail-title">
                <h2>Trending Now</h2>
                <p>Premium previews available. Send a message to complete your purchase.</p>
            </div>

            <div class="count-pill">
                <strong><?= $total_encontrados ?></strong>
                video<?= $total_encontrados !== 1 ? 's' : '' ?> found
            </div>
        </div>

        <div class="videos-grid">
            <?php if (empty($videos)): ?>
                <div class="empty-state">
                    <div>
                        <i class="fas fa-film"></i>
                        <h3>No videos found</h3>
                        <p>Try changing your filters or clearing the search.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($videos as $v): ?>
                <?php
$descricaoVideo = !empty($v['descricao'])
    ? $v['descricao']
    : 'No description available';

$mensagem_telegram = rawurlencode(
    "Hello! I'm interested in purchasing this video:\n\n" .
    "🎬 Video: " . $v['nome_video'] . "\n" .
    "🆔 Video ID: " . $v['id_video'] . "\n" .
    "💰 Price: $" . number_format((float)$v['preco'], 2) . "\n" .
    "⏱ Duration: " . ($v['duracao'] ?? 'N/A') . "\n" .
    "👁 Views: " . number_format((int)$v['visualizacoes']) . "\n" .
    "📌 Status: Available\n\n" .
    "📝 Description:\n" . $descricaoVideo . "\n\n" .
    "I have paid / I want to proceed with payment. Please send me the access details."
);

$telegramBase = rtrim($TELEGRAM_LINK, '/');
$link_telegram = $telegramBase . "?text=" . $mensagem_telegram;

// Versão segura para usar dentro do JavaScript
$link_telegram_js = json_encode($link_telegram, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$nome_video_js    = json_encode($v['nome_video'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


                ?>

                <article class="video-card">

<div
    class="video-thumbnail-wrapper preview-zone"
    data-id-video="<?= (int)$v['id_video'] ?>"
    onclick='abrirPreview(<?= (int)$v["id_video"] ?>)'
>
<?php if (!empty($v['caminho_imagem'])): ?>
<img
    src="stream_media.php?id=<?= (int)$v['id_video'] ?>&tipo=imagem"
    class="video-thumbnail preview-cover"
    alt="<?= htmlspecialchars($v['nome_video']) ?>"
    loading="lazy"
>
<?php else: ?>
    <div class="no-thumb preview-cover">
        <i class="fas fa-film"></i>
    </div>
<?php endif; ?>

    <video
        class="inline-preview-video"
        muted
        loop
        playsinline
        preload="metadata"
    ></video>

    <div class="video-overlay"></div>

    <div class="preview-hint">
        <i class="fas fa-play"></i>
        Preview
    </div>

    <div class="quality-badge">
        <i class="fas fa-crown"></i> PREMIUM
    </div>

    <div class="price-badge">
        $<?= number_format($v['preco'], 2) ?>
    </div>

    <?php if (!empty($v['duracao'])): ?>
        <div class="duration-badge">
            <i class="far fa-clock"></i>
            <?= htmlspecialchars($v['duracao']) ?>
        </div>
    <?php endif; ?>
</div>

                    <div class="video-info">
                        <h3 class="video-title">
                            <?= htmlspecialchars($v['nome_video']) ?>
                        </h3>

                        <?php if (!empty($v['descricao'])): ?>
                            <p class="video-description">
                                <?= htmlspecialchars($v['descricao']) ?>
                            </p>
                        <?php else: ?>
                            <p class="video-description">
                                Exclusive premium content available for instant request.
                            </p>
                        <?php endif; ?>

                        <div class="video-stats">
                            <span>
                                <i class="fas fa-eye"></i>
                                10<?= number_format($v['visualizacoes']) ?> views
                            </span>

                            <span class="online-badge">
                                <i class="fas fa-circle"></i>
                                Available
                            </span>
                        </div>

                      <div class="action-buttons">
    <div class="action-row">
<button type="button" onclick='abrirPreview(<?= (int)$v["id_video"] ?>)' class="action-btn btn-preview">

<button
    type="button"
    class="action-btn btn-buy"
    onclick='abrirPayPal(
        <?= (int)$v["id_video"] ?>,
        <?= $nome_video_js ?>,
        <?= (float)$v["preco"] ?>,
        <?= $link_telegram_js ?>
    )'
>
    <i class="fab fa-paypal"></i>
    <span class="btn-buy-text">
        <strong>PayPal</strong>
        <small>$<?= number_format((float)$v['preco'], 2) ?></small>
    </span>
</button>
                            </div>

<!-- <a
    href="<?= htmlspecialchars($link_telegram) ?>"
    target="_blank"
    rel="noopener"
    class="action-btn btn-message"
>a
    <i class="fas fa-paper-plane"></i>
    Send Message
</a> -->
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<!-- ── MODAL PREVIEW ── -->
<div id="modalPreview" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="fecharPreview()">&times;</span>

        <video
            id="videoPreview"
            class="video-player"
            controls
            playsinline
            preload="metadata"
        ></video>

        <div id="preview-error" style="display:none;padding:18px;color:#ff7b84;text-align:center;font-weight:800;">
            <i class="fas fa-circle-exclamation"></i>
            Preview unavailable. Please try again later.
        </div>
    </div>
</div>

<div id="paypalModal">
    <div class="paypal-modal-box">
        <h3 id="pm-title">Complete your purchase</h3>

        <div class="pm-video-name" id="pm-video-name"></div>
        <div class="pm-price" id="pm-price"></div>

        <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.10);border-radius:14px;padding:14px;margin-bottom:18px;font-size:0.86rem;color:var(--muted);line-height:1.6;">
            <div style="font-weight:900;color:white;margin-bottom:8px;">
                <i class="fas fa-circle-info" style="color:var(--gold);margin-right:6px;"></i>
                How does it work?
            </div>

            <div style="display:flex;flex-direction:column;gap:7px;">
                <span><i class="fab fa-paypal" style="color:var(--gold);width:18px;"></i> Complete the secure PayPal payment.</span>
                <span><i class="fab fa-telegram" style="color:var(--gold);width:18px;"></i> After payment, you will be redirected to Telegram.</span>
                <span><i class="fas fa-film" style="color:var(--gold);width:18px;"></i> The Telegram message will include all video details.</span>
            </div>
        </div>

        <div id="paypal-button-container"></div>

        <div id="paypal-error-msg">
            <i class="fas fa-circle-exclamation"></i>
            Payment not completed. Please try again.
        </div>

        <div class="pm-cancel" onclick="fecharPayPal()">
            <i class="fas fa-xmark"></i> Cancel and go back
        </div>
    </div>
</div>

<script>
let paypalButtons = null;
let telegramAfterPayment = null;

function abrirPayPal(idVideo, nomeVideo, preco, telegramLink) {
    telegramAfterPayment = telegramLink;

    document.getElementById('pm-video-name').textContent = nomeVideo;
    document.getElementById('pm-price').textContent = '$' + parseFloat(preco).toFixed(2);
    document.getElementById('paypal-error-msg').style.display = 'none';

    document.getElementById('paypalModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    document.getElementById('paypal-button-container').innerHTML = '';

    if (paypalButtons) {
        paypalButtons.close();
        paypalButtons = null;
    }

    if (typeof paypal === 'undefined') {
        document.getElementById('paypal-error-msg').innerHTML =
            '<i class="fas fa-circle-exclamation"></i> PayPal is unavailable. Please use Send Message.';
        document.getElementById('paypal-error-msg').style.display = 'block';
        return;
    }

    paypalButtons = paypal.Buttons({
        createOrder: async function () {
            try {
                const res = await fetch('/paypal/create-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id_video: idVideo
                    }),
                });

                const data = await res.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                return data.id;
            } catch (err) {
                console.error('createOrder error:', err);
                mostrarErroPayPal();
            }
        },

        onApprove: async function (data) {
            try {
                const redirectTelegram = encodeURIComponent(telegramAfterPayment || '');

                window.location.href =
                    '/paypal/capture-order.php?token=' +
                    encodeURIComponent(data.orderID) +
                    '&id_video=' +
                    encodeURIComponent(idVideo) +
                    '&telegram_redirect=' +
                    redirectTelegram;
            } catch (err) {
                console.error('onApprove error:', err);
                mostrarErroPayPal();
            }
        },

        onCancel: function () {
            fecharPayPal();
        },

        onError: function (err) {
            console.error('PayPal error:', err);
            mostrarErroPayPal();
        },

        style: {
            layout: 'vertical',
            color: 'gold',
            shape: 'rect',
            label: 'paypal',
        },
    });

    paypalButtons.render('#paypal-button-container');
}

function fecharPayPal() {
    document.getElementById('paypalModal').classList.remove('open');
    document.body.style.overflow = 'auto';

    document.getElementById('paypal-button-container').innerHTML = '';

    if (paypalButtons) {
        paypalButtons.close();
        paypalButtons = null;
    }
}

function mostrarErroPayPal() {
    document.getElementById('paypal-error-msg').style.display = 'block';
}

// Mensagem de feedback após retorno do PayPal antigo
window.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const payment = params.get('payment');

    if (payment === 'cancelled') {
        alert('Payment cancelled. You can try again at any time.');
    } else if (payment === 'failed') {
        alert('Payment failed. Please try again or contact support.');
    } else if (payment === 'error') {
        alert('A technical error occurred. Please try again.');
    }
});

// ── Toast ──
function dismissToast() {
    const toast = document.getElementById('infoToast');

    toast.style.transition = 'opacity 0.3s, transform 0.3s';
    toast.style.opacity    = '0';
    toast.style.transform  = 'translateY(24px)';

    setTimeout(() => toast.classList.add('hidden'), 300);

    sessionStorage.setItem('toastDismissed', '1');
}

window.addEventListener('DOMContentLoaded', () => {
    const toast = document.getElementById('infoToast');

    if (sessionStorage.getItem('toastDismissed')) {
        toast.classList.add('hidden');
    } else {
        toast.style.display = 'none';

        setTimeout(() => {
            toast.style.display = 'block';
        }, 1600);
    }
});

// ── Filter toggle ──
const filterToggle     = document.getElementById('filterToggle');
const filtersContainer = document.getElementById('filtersContainer');

filterToggle.addEventListener('click', function () {
    filtersContainer.classList.toggle('show');
    filterToggle.classList.toggle('active');

    const span = filterToggle.querySelector('span');
    span.textContent = filtersContainer.classList.contains('show')
        ? 'Hide Filters'
        : 'Show Filters';
});



function registrarVisualizacao(idVideo) {
    const fd = new FormData();
    fd.append('registrar_visualizacao', '1');
    fd.append('id_video', idVideo);

    fetch(window.location.href, {
        method: 'POST',
        body: fd
    }).catch(err => console.error('View register error:', err));
}


function abrirPreview(idVideo) {
    const src = `stream_media.php?id=${idVideo}&tipo=video`;

    pararPreviewsInline();

    const modal   = document.getElementById('modalPreview');
    const player  = document.getElementById('videoPreview');
    const errorBox = document.getElementById('preview-error');

    errorBox.style.display = 'none';
    player.style.display = 'block';

    player.pause();
    player.removeAttribute('src');
    player.load();

    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    player.src = src;
    player.load();

    player.oncanplay = function () {
        player.play().catch(err => console.warn('Preview autoplay blocked:', err));
    };

    player.onerror = function () {
        console.error('Preview load error:', src);
        player.style.display = 'none';
        errorBox.style.display = 'block';
    };

    registrarVisualizacao(idVideo);
}

function fecharPreview() {
    const modal = document.getElementById('modalPreview');
    const player = document.getElementById('videoPreview');
    const errorBox = document.getElementById('preview-error');

    modal.style.display = 'none';

    player.pause();
    player.removeAttribute('src');
    player.load();

    errorBox.style.display = 'none';
    player.style.display = 'block';

    document.body.style.overflow = 'auto';
}

let inlinePreviewTimer = null;
let currentInlinePreview = null;

function pararPreviewsInline() {
    document.querySelectorAll('.preview-zone').forEach(zone => {
        const video = zone.querySelector('.inline-preview-video');

        zone.classList.remove('preview-playing');

        if (video) {
            video.pause();
            video.removeAttribute('src');
            video.load();
        }
    });

    currentInlinePreview = null;
}

function iniciarPreviewInline(zone) {
    const idVideo = zone.dataset.idVideo;
    const video = zone.querySelector('.inline-preview-video');

    if (!idVideo || !video) return;

    const src = `stream_media.php?id=${idVideo}&tipo=video`;

    if (currentInlinePreview && currentInlinePreview !== zone) {
        pararPreviewsInline();
    }
    currentInlinePreview = zone;

    if (video.getAttribute('src') !== src) {
        video.src = src;
        video.load();
    }

    zone.classList.add('preview-playing');

    video.play().catch(err => {
        console.warn('Inline preview blocked or failed:', err);
        zone.classList.remove('preview-playing');
    });
}

function pararPreviewInline(zone) {
    const video = zone.querySelector('.inline-preview-video');

    zone.classList.remove('preview-playing');

    if (video) {
        video.pause();
        video.currentTime = 0;
        video.removeAttribute('src');
        video.load();
    }

    if (currentInlinePreview === zone) {
        currentInlinePreview = null;
    }
}

document.querySelectorAll('.preview-zone').forEach(zone => {
    zone.addEventListener('mouseenter', function () {
        clearTimeout(inlinePreviewTimer);

        inlinePreviewTimer = setTimeout(() => {
            iniciarPreviewInline(zone);
        }, 350);
    });

    zone.addEventListener('mouseleave', function () {
        clearTimeout(inlinePreviewTimer);
        pararPreviewInline(zone);
    });

    zone.addEventListener('touchstart', function () {
        iniciarPreviewInline(zone);
    }, { passive: true });
});

window.onclick = function (e) {
    if (e.target === document.getElementById('modalPreview')) {
        fecharPreview();
    }
};

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        fecharPreview();
    }
});
</script>

</body>
</html>


