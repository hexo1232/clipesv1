<?php
// index.php
$paypal_client_id = getenv('PAYPAL_CLIENT_ID');
include "verifica_login_opcional.php";
include "conexao.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$usuarioLogado = $_SESSION['usuario'] ?? null;
$id_perfil     = $usuarioLogado['idperfil'] ?? null;
$idUsuario     = $usuarioLogado['id_usuario'] ?? null;


// ── INSIRA O SEU LINK DO TELEGRAM AQUI ──
$TELEGRAM_LINK = "https://t.me/donaldo258";

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
$videos            = $stmt->fetchAll();
$total_encontrados = count($videos);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DarkVelvetClub — Premium Video Store</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/basico.css">
<script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypal_client_id) ?>&currency=USD"></script>
<!-- SVG favicon: faceted diamond on dark background -->
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='13' fill='%230a0a0f'/%3E%3Cpath d='M32 8 L56 26 L46 56 L18 56 L8 26 Z' fill='none' stroke='%23d4a843' stroke-width='2' stroke-linejoin='round' opacity='0.5'/%3E%3Cpath d='M32 8 L56 26 L32 38 L8 26 Z' fill='%23d4a843' opacity='0.9'/%3E%3Cpath d='M32 38 L56 26 L46 56 Z' fill='%23b8861e' opacity='0.75'/%3E%3Cpath d='M32 38 L8 26 L18 56 Z' fill='%23c49430' opacity='0.6'/%3E%3Cpath d='M32 38 L46 56 L18 56 Z' fill='%238a6010' opacity='0.5'/%3E%3Cellipse cx='32' cy='28' rx='5' ry='3' fill='white' opacity='0.15' transform='rotate(-15 32 28)'/%3E%3C/svg%3E">

<style>
/* ── RESET & BASE ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:         #0a0a0f;
    --surface:    #12121a;
    --surface2:   #1a1a26;
    --border:     rgba(255,255,255,0.07);
    --gold:       #d4a843;
    --gold-light: #f0c96a;
    --teal:       #2AABEE;
    --teal-dark:  #1a8fc4;
    --text:       #e8e8f0;
    --muted:      #7a7a96;
    --radius:     14px;
    --radius-sm:  8px;
    --shadow:     0 20px 60px rgba(0,0,0,0.5);
}

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    line-height: 1.6;
    min-height: 100vh;
}

/* ── TOPBAR ── */
.topbar {
    background: rgba(10,10,15,0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 100;
    padding: 0 24px;
}
.topbar .container {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
}

/* ── LOGO ── */
.logo {
    display: flex;
    align-items: center;
    gap: 11px;
    text-decoration: none;
}
.logo-icon { width: 38px; height: 38px; flex-shrink: 0; }
.logo-text {
    display: flex;
    flex-direction: column;
    line-height: 1;
    gap: 1px;
}
.logo-text .l-dark  {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.6rem;
    font-weight: 600;
    letter-spacing: 4px;
    color: var(--muted);
    text-transform: uppercase;
}
.logo-text .l-velvet {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.45rem;
    letter-spacing: 3px;
    color: var(--gold);
    line-height: 1;
}
.logo-text .l-club {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.6rem;
    font-weight: 600;
    letter-spacing: 4px;
    color: var(--muted);
    text-transform: uppercase;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 6px;
}
.nav-links a {
    color: var(--muted);
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 500;
    padding: 6px 16px;
    border-radius: 50px;
    transition: all 0.2s;
    letter-spacing: 0.3px;
}
.nav-links a:hover { color: var(--text); background: var(--surface2); }

/* ── HERO BANNER ── */
.hero {
    background: linear-gradient(135deg, #0a0a0f 0%, #12121a 40%, #0d1520 100%);
    border-bottom: 1px solid var(--border);
    padding: 56px 24px 48px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 70% 60% at 50% 0%, rgba(212,168,67,0.08) 0%, transparent 70%);
    pointer-events: none;
}
.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(212,168,67,0.1);
    border: 1px solid rgba(212,168,67,0.25);
    color: var(--gold);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    padding: 5px 14px;
    border-radius: 50px;
    margin-bottom: 20px;
}
.hero h1 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(2.8rem, 6vw, 5rem);
    letter-spacing: 3px;
    line-height: 1;
    color: var(--text);
    margin-bottom: 14px;
}
.hero h1 em { font-style: normal; color: var(--gold); }
.hero p {
    color: var(--muted);
    font-size: 1rem;
    max-width: 480px;
    margin: 0 auto 28px;
}
.hero-trust {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 24px;
    flex-wrap: wrap;
}
.trust-pill {
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--muted);
    font-size: 0.82rem;
    font-weight: 500;
}
.trust-pill i { color: var(--gold); font-size: 0.9rem; }

/* ── MAIN CONTAINER ── */
.main-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px 24px 64px;
}

/* ── FILTER TOGGLE BTN ── */
.filter-toggle-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 10px 20px;
    border-radius: 50px;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    font-weight: 500;
    margin-bottom: 16px;
    transition: all 0.2s;
}
.filter-toggle-btn:hover { border-color: var(--gold); color: var(--gold); }
.filter-toggle-btn .fa-chevron-down {
    transition: transform 0.3s;
    font-size: 0.75rem;
    color: var(--muted);
}
.filter-toggle-btn.active .fa-chevron-down { transform: rotate(180deg); }

/* ── FILTERS PANEL ── */
.filters {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 28px;
    margin-bottom: 28px;
    display: none;
}
.filters.show { display: block; }
.filters h2 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gold);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.filter-group label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 7px;
}
.filter-group input,
.filter-group select {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    padding: 9px 13px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    outline: none;
    transition: border-color 0.2s;
    -webkit-appearance: none;
    appearance: none;
}
.filter-group input:focus,
.filter-group select:focus { border-color: var(--gold); }
.filter-group input::placeholder { color: var(--muted); }
.filter-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-filter {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 22px;
    border-radius: 50px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.btn-primary { background: var(--gold); color: #0a0a0f; }
.btn-primary:hover { background: var(--gold-light); }
.btn-secondary { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
.btn-secondary:hover { color: var(--text); border-color: var(--muted); }

/* ── COUNT BAR ── */
.count-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.count {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    font-size: 0.88rem;
    font-weight: 500;
}
.count i { color: var(--gold); }
.count strong { color: var(--text); }

/* ── VIDEO GRID ── */
.videos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 22px;
}

/* ── VIDEO CARD ── */
.video-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
    display: flex;
    flex-direction: column;
}
.video-card:hover {
    transform: translateY(-5px);
    border-color: rgba(212,168,67,0.3);
    box-shadow: 0 24px 48px rgba(0,0,0,0.4), 0 0 0 1px rgba(212,168,67,0.1);
}

/* ── THUMBNAIL ── */
.video-thumbnail-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: var(--surface2);
}
.video-thumbnail {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.35s ease;
}
.video-card:hover .video-thumbnail { transform: scale(1.04); }

.price-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--gold);
    color: #0a0a0f;
    font-weight: 700;
    font-size: 0.9rem;
    padding: 4px 11px;
    border-radius: 50px;
    letter-spacing: 0.3px;
    box-shadow: 0 4px 12px rgba(212,168,67,0.4);
}
.duration-badge {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(6px);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 500;
    padding: 3px 10px;
    border-radius: 50px;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* ── CARD BODY ── */
.video-info {
    padding: 18px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.video-title {
    font-size: 0.97rem;
    font-weight: 600;
    color: var(--text);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.video-stats {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.78rem;
    color: var(--muted);
}
.video-stats span { display: flex; align-items: center; gap: 5px; }
.online-badge { color: #4ade80 !important; font-weight: 600; }
.online-badge i { font-size: 0.5rem; }

/* ── ACTION BUTTONS ── */
.action-buttons { display: flex; flex-direction: column; gap: 8px; margin-top: auto; }
.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    letter-spacing: 0.2px;
}
.action-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.btn-preview {
    background: var(--surface2);
    color: var(--muted);
    border: 1px solid var(--border);
}
.btn-preview:hover { background: var(--surface); color: var(--text); border-color: var(--muted); }
.btn-telegram {
    background: linear-gradient(135deg, #2AABEE, #1a8fc4);
    color: #fff;
    box-shadow: 0 4px 14px rgba(42,171,238,0.25);
}
.btn-telegram:hover {
    background: linear-gradient(135deg, #1a8fc4, #0f6d9e);
    box-shadow: 0 6px 20px rgba(42,171,238,0.35);
}
.btn-buy {
    background: linear-gradient(135deg, var(--gold), #b8861e);
    color: #0a0a0f;
    font-weight: 700;
    font-size: 0.9rem;
    padding: 12px 14px;
    box-shadow: 0 4px 16px rgba(212,168,67,0.3);
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.btn-buy:hover {
    background: linear-gradient(135deg, var(--gold-light), var(--gold));
    box-shadow: 0 6px 24px rgba(212,168,67,0.45);
    transform: translateY(-1px);
}
.btn-buy i { font-size: 1rem; }

/* ── PAGINATION ── */
.pagination { display: flex; justify-content: center; gap: 6px; margin-top: 48px; flex-wrap: wrap; }
.pagination a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: var(--radius-sm);
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--muted);
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    transition: all 0.2s;
}
.pagination a:hover { border-color: var(--gold); color: var(--gold); }
.pagination a.active { background: var(--gold); border-color: var(--gold); color: #0a0a0f; }

/* ── MODAL ── */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    align-items: center;
    justify-content: center;
}
.modal[style*="display: block"] { display: flex !important; }
.modal-content {
    position: relative;
    width: 90%;
    max-width: 860px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow);
}
.close-modal {
    position: absolute;
    top: 12px;
    right: 16px;
    font-size: 1.6rem;
    color: var(--muted);
    cursor: pointer;
    z-index: 10;
    transition: color 0.2s;
    line-height: 1;
}
.close-modal:hover { color: var(--text); }
.video-player { width: 100%; display: block; max-height: 80vh; background: #000; }

/* ── TOAST ── */
#infoToast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    max-width: 300px;
    background: var(--surface);
    color: var(--text);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    border-left: 3px solid var(--gold);
    animation: slideInToast 0.4s ease;
    font-size: 0.86rem;
    line-height: 1.5;
}
@keyframes slideInToast {
    from { opacity: 0; transform: translateY(24px); }
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
    font-size: 0.9rem;
    color: var(--gold);
    display: flex;
    align-items: center;
    gap: 6px;
}
#infoToast .toast-close {
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    font-size: 0.95rem;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}
#infoToast .toast-close:hover { color: var(--text); }
#infoToast .toast-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: rgba(255,255,255,0.04);
    border-radius: 8px;
    padding: 8px 10px;
}
#infoToast .toast-row i {
    font-size: 1.1rem;
    min-width: 18px;
    text-align: center;
    margin-top: 2px;
    color: var(--gold);
}
#infoToast .toast-row span strong {
    display: block;
    font-size: 0.78rem;
    color: var(--muted);
    font-weight: 600;
    margin-bottom: 1px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
#infoToast.hidden { display: none; }

/* ── EMPTY STATE ── */
.empty-state {
    text-align: center;
    padding: 80px 24px;
    color: var(--muted);
    grid-column: 1 / -1;
}
.empty-state i { font-size: 3rem; margin-bottom: 16px; color: var(--border); }
.empty-state h3 { font-size: 1.1rem; color: var(--text); margin-bottom: 8px; }

#paypalModal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(0,0,0,0.88);
    backdrop-filter: blur(10px);
    align-items: center;
    justify-content: center;
}
#paypalModal.open { display: flex !important; }
.paypal-modal-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 32px 28px;
    width: 90%;
    max-width: 420px;
    box-shadow: var(--shadow);
}
.paypal-modal-box h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 6px;
}
.paypal-modal-box .pm-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gold);
    margin-bottom: 20px;
}
.paypal-modal-box .pm-video-name {
    font-size: 0.85rem;
    color: var(--muted);
    margin-bottom: 20px;
}
#paypal-button-container { min-height: 50px; }
.pm-cancel {
    margin-top: 14px;
    text-align: center;
    font-size: 0.82rem;
    color: var(--muted);
    cursor: pointer;
    transition: color 0.2s;
}
.pm-cancel:hover { color: var(--text); }
#paypal-error-msg {
    display: none;
    background: rgba(239,68,68,0.12);
    border: 1px solid rgba(239,68,68,0.3);
    color: #f87171;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.85rem;
    margin-top: 12px;
    text-align: center;
}


/* ── RESPONSIVE ── */
@media (max-width: 640px) {
    .hero { padding: 40px 20px 32px; }
    .hero h1 { font-size: 2.4rem; }
    .main-container { padding: 24px 16px 48px; }
    .videos-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
    .hero-trust { gap: 14px; }
    .nav-links a { padding: 6px 10px; font-size: 0.82rem; }
    #infoToast { max-width: calc(100vw - 32px); right: 16px; bottom: 16px; }
    .logo-text .l-velvet { font-size: 1.15rem; }
}
</style>
</head>
<body>

<!-- ── TOAST ── -->
<div id="infoToast">
    <div class="toast-header">
        <span class="toast-title">
            <i class="fas fa-circle-info"></i> How it works
        </span>
        <button class="toast-close" onclick="dismissToast()" title="Dismiss">
            <i class="fas fa-xmark"></i>
        </button>
    </div>
    <div class="toast-row">
        <i class="fas fa-user-clock"></i>
        <span>
            <strong>Access</strong>
            Login is optional. You can browse and buy without an account.
        </span>
    </div>
</div>

<!-- ── TOPBAR ── -->
<div class="topbar">
    <div class="container">
        <a href="index.php" class="logo">
            <!-- Faceted diamond SVG icon -->
            <svg class="logo-icon" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                <rect width="64" height="64" rx="13" fill="#0a0a0f"/>
                <path d="M32 8 L56 26 L46 56 L18 56 L8 26 Z"
                      fill="none" stroke="#d4a843" stroke-width="2"
                      stroke-linejoin="round" opacity="0.45"/>
                <path d="M32 8 L56 26 L32 38 L8 26 Z" fill="#d4a843" opacity="0.92"/>
                <path d="M32 38 L56 26 L46 56 Z"      fill="#b8861e" opacity="0.78"/>
                <path d="M32 38 L8 26 L18 56 Z"       fill="#c49430" opacity="0.62"/>
                <path d="M32 38 L46 56 L18 56 Z"      fill="#8a6010" opacity="0.52"/>
                <ellipse cx="32" cy="26" rx="4.5" ry="2.5" fill="white"
                         opacity="0.18" transform="rotate(-18 32 26)"/>
            </svg>
            <div class="logo-text">
                <span class="l-dark">DARK</span>
                <span class="l-velvet">VELVET</span>
                <span class="l-club">CLUB</span>
            </div>
        </a>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-house"></i> Home</a>
            <a href="#">Videos</a>
            <?php if ($usuarioLogado): ?>
                <a href="logout.php"><i class="fas fa-arrow-right-from-bracket"></i> Sign Out</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-user"></i> Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── HERO ── -->
<div class="hero">
    <div class="hero-eyebrow"><i class="fas fa-gem"></i> Members-Only Collection</div>
    <h1>Dark<em>Velvet</em>Club</h1>
    <p>An exclusive vault of premium videos. Browse freely, buy instantly — no membership required.</p>
    <div class="hero-trust">
        <span class="trust-pill"><i class="fas fa-shield-halved"></i> Secure Purchase</span>
        <span class="trust-pill"><i class="fab fa-telegram"></i> Fast Delivery</span>
        <span class="trust-pill"><i class="fas fa-user-slash"></i> No Account Needed</span>
    </div>
</div>

<!-- ── MAIN ── -->
<div class="main-container">

    <button class="filter-toggle-btn" id="filterToggle">
        <i class="fas fa-sliders"></i>
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
                        <option value="">All Categories</option>
                        <?php foreach ($lista_categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>"
                                <?= isset($_GET['categoria']) && $_GET['categoria'] == $cat['id_categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome_categoria']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Min Duration (min)</label>
                    <input type="number" name="duracao_min" placeholder="e.g. 5" min="0"
                           value="<?= htmlspecialchars($_GET['duracao_min'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>Max Duration (min)</label>
                    <input type="number" name="duracao_max" placeholder="e.g. 60" min="0"
                           value="<?= htmlspecialchars($_GET['duracao_max'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>Min Price ($)</label>
                    <input type="number" name="preco_min" placeholder="e.g. 10" min="0" step="0.01"
                           value="<?= htmlspecialchars($_GET['preco_min'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>Max Price ($)</label>
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

    <div class="count-bar">
        <div class="count">
            <i class="fas fa-video"></i>
            <strong><?= $total_encontrados ?></strong> video<?= $total_encontrados !== 1 ? 's' : '' ?> found
        </div>
    </div>

    <div class="videos-grid">
        <?php if (empty($videos)): ?>
            <div class="empty-state">
                <i class="fas fa-film"></i>
                <h3>No videos found</h3>
                <p>Try adjusting your filters or clear the search.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($videos as $v):
            $mensagem_telegram = urlencode(
                "Hello! I'm interested in purchasing:\n\n" .
                "🎬 Video: " . $v['nome_video'] . "\n" .
                "💰 Price: $" . number_format($v['preco'], 2) . "\n" .
                "⏱ Duration: " . ($v['duracao'] ?? 'N/A') . "\n\n" .
                "How do I proceed with payment?"
            );
            $link_telegram = $TELEGRAM_LINK . "?text=" . $mensagem_telegram;
        ?>
            <div class="video-card">
                <div class="video-thumbnail-wrapper">
                    <?php if (!empty($v['caminho_imagem'])): ?>
                        <img src="<?= htmlspecialchars($v['caminho_imagem']) ?>"
                             class="video-thumbnail"
                             alt="<?= htmlspecialchars($v['nome_video']) ?>"
                             loading="lazy">
                    <?php else: ?>
                        <div class="video-thumbnail" style="background:linear-gradient(135deg,#1a1a26 0%,#12121a 100%);display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-film" style="font-size:3rem;color:rgba(212,168,67,0.2);"></i>
                        </div>
                    <?php endif; ?>

                    <div class="price-badge">$<?= number_format($v['preco'], 2) ?></div>
                    <?php if (!empty($v['duracao'])): ?>
                        <div class="duration-badge">
                            <i class="far fa-clock"></i> <?= htmlspecialchars($v['duracao']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="video-info">
                    <h3 class="video-title"><?= htmlspecialchars($v['nome_video']) ?></h3>

                    <div class="video-stats">
                        <span><i class="fas fa-eye"></i> <?= number_format($v['visualizacoes']) ?> views</span>
                        <span class="online-badge"><i class="fas fa-circle"></i> Available</span>
                    </div>

                    <div class="action-buttons">
                        <div class="action-row">
                            <button onclick="abrirPreview(<?= $v['id_video'] ?>, '<?= addslashes($v['caminho_previa']) ?>')"
                                    class="action-btn btn-preview">
                                <i class="far fa-play-circle"></i> Preview
                            </button>
                            <a href="<?= $link_telegram ?>" target="_blank" rel="noopener"
                               class="action-btn btn-telegram">
                                <i class="fab fa-telegram"></i> Telegram
                            </a>
                        </div>
                      <button
    class="action-btn btn-buy"
    onclick="abrirPayPal(<?= $v['id_video'] ?>, '<?= addslashes($v['nome_video']) ?>', <?= $v['preco'] ?>)">
    <i class="fab fa-paypal"></i> PayPal — $<?= number_format($v['preco'], 2) ?>
</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_paginas; $i++):
                $params           = $_GET;
                $params['pagina'] = $i;
                $url              = '?' . http_build_query($params);
            ?>
                <a href="<?= $url ?>" class="<?= $i == $pagina_atual ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

</div>

<!-- ── MODAL PREVIEW ── -->
<div id="modalPreview" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="fecharPreview()">&times;</span>
        <video id="videoPreview" class="video-player" controls playsinline>
            <source id="videoSource" src="" type="video/mp4">
        </video>
    </div>
</div>

<div id="paypalModal">
    <div class="paypal-modal-box">
        <h3 id="pm-title">Complete your purchase</h3>
        <div class="pm-video-name" id="pm-video-name"></div>
        <div class="pm-price" id="pm-price"></div>

        <div style="background:rgba(212,168,67,0.07);border:1px solid rgba(212,168,67,0.18);border-radius:10px;padding:12px 14px;margin-bottom:20px;font-size:0.82rem;color:var(--muted);line-height:1.6;">
            <div style="font-weight:700;color:var(--gold);margin-bottom:8px;font-size:0.85rem;">
                <i class="fas fa-circle-info" style="margin-right:5px;"></i> How does it work?
            </div>
            <div style="display:flex;flex-direction:column;gap:7px;">
                <span><i class="fab fa-paypal" style="color:var(--gold);width:16px;"></i> Click the PayPal button and complete your payment securely.</span>
                <span><i class="fab fa-telegram" style="color:var(--gold);width:16px;"></i> After payment, you'll be automatically redirected to our Telegram.</span>
                <span><i class="fas fa-bolt" style="color:var(--gold);width:16px;"></i> Share your transaction ID and receive your video access instantly.</span>
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

function abrirPayPal(idVideo, nomeVideo, preco) {
    document.getElementById('pm-video-name').textContent = nomeVideo;
    document.getElementById('pm-price').textContent      = '$' + parseFloat(preco).toFixed(2);
    document.getElementById('paypal-error-msg').style.display = 'none';
    document.getElementById('paypalModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    // Limpar botões anteriores se existirem
    document.getElementById('paypal-button-container').innerHTML = '';
    if (paypalButtons) {
        paypalButtons.close();
        paypalButtons = null;
    }

    paypalButtons = paypal.Buttons({
        // 1. Criar ordem no backend
        createOrder: async function () {
            try {
                const res = await fetch('/paypal/create-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_video: idVideo }),
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                return data.id; // order ID do PayPal
            } catch (err) {
                console.error('createOrder error:', err);
                mostrarErroPayPal();
            }
        },

        // 2. Após aprovação do utilizador — capturar
        onApprove: async function (data) {
            try {
                // Redireciona para o capture, que trata de tudo e redireciona para Telegram
                window.location.href = '/paypal/capture-order.php?token=' + data.orderID;
            } catch (err) {
                console.error('onApprove error:', err);
                mostrarErroPayPal();
            }
        },

        // 3. Cancelamento
        onCancel: function () {
            fecharPayPal();
        },

        // 4. Erro no widget PayPal
        onError: function (err) {
            console.error('PayPal error:', err);
            mostrarErroPayPal();
        },

        // Estilo do botão
        style: {
            layout: 'vertical',
            color:  'gold',
            shape:  'rect',
            label:  'paypal',
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

// Mensagem de feedback após retorno
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
        setTimeout(() => { toast.style.display = 'flex'; }, 1800);
    }
});

// ── Filter toggle ──
const filterToggle     = document.getElementById('filterToggle');
const filtersContainer = document.getElementById('filtersContainer');
filterToggle.addEventListener('click', function () {
    filtersContainer.classList.toggle('show');
    filterToggle.classList.toggle('active');
    const span = filterToggle.querySelector('span');
    span.textContent = filtersContainer.classList.contains('show') ? 'Hide Filters' : 'Show Filters';
});

// ── Preview modal ──
function abrirPreview(idVideo, caminho) {
    document.getElementById('modalPreview').style.display = 'block';
    document.getElementById('videoSource').src = caminho;
    document.getElementById('videoPreview').load();
    document.body.style.overflow = 'hidden';

    const fd = new FormData();
    fd.append('registrar_visualizacao', '1');
    fd.append('id_video', idVideo);
    fetch(window.location.href, { method: 'POST', body: fd })
        .catch(err => console.error('View register error:', err));
}
function fecharPreview() {
    document.getElementById('modalPreview').style.display = 'none';
    const player = document.getElementById('videoPreview');
    player.pause();
    player.currentTime = 0;
    document.body.style.overflow = 'auto';
}
window.onclick = function (e) {
    if (e.target === document.getElementById('modalPreview')) fecharPreview();
};
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fecharPreview();
});
</script>

</body>
</html>