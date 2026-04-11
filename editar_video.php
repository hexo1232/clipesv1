<?php
// ════════════════════════════════════════════════════════════════════════════
//  editar_video.php
//  — GET  : renderiza o formulário HTML com os dados actuais do vídeo
//  — POST com ajax_request=1 : devolve JSON puro (fase AJAX)
//  — POST sem ajax_request   : fallback HTML (sem JS)
// ════════════════════════════════════════════════════════════════════════════

include "verifica_login.php";
include "conexao.php";
include "info_usuario.php";

$conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    if (!empty($_POST['ajax_request'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'erro', 'mensagem' => 'Sessão expirada. Faça login novamente.']);
        exit;
    }
    header("Location: login.php");
    exit;
}

$usuario   = $_SESSION['usuario'];
$id_perfil = $usuario['idperfil'] ?? null;

// Só admins (perfil 1) podem editar
if ($id_perfil != 1) {
    if (!empty($_POST['ajax_request'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão.']);
        exit;
    }
    header("Location: ver_videos.php");
    exit;
}

$id_video = intval($_GET['id_video'] ?? $_POST['id_video'] ?? 0);
if ($id_video <= 0) {
    if (!empty($_POST['ajax_request'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID de vídeo inválido.']);
        exit;
    }
    header("Location: gerenciar_videos.php");
    exit;
}

// ── Buscar vídeo actual ───────────────────────────────────────────────────────
$stmt = $conexao->prepare(
    "SELECT v.*, vi.caminho_imagem
     FROM video v
     LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = true
     WHERE v.id_video = ?"
);
$stmt->execute([$id_video]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    if (!empty($_POST['ajax_request'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'erro', 'mensagem' => 'Vídeo não encontrado.']);
        exit;
    }
    header("Location: gerenciar_videos.php");
    exit;
}

// ── Categorias actuais do vídeo ───────────────────────────────────────────────
$stmtCatAtual = $conexao->prepare("SELECT id_categoria FROM video_categoria WHERE id_video = ?");
$stmtCatAtual->execute([$id_video]);
$categorias_atuais = $stmtCatAtual->fetchAll(PDO::FETCH_COLUMN);

// ── Lista de todas as categorias ─────────────────────────────────────────────
$lista_categorias = $conexao
    ->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria")
    ->fetchAll(PDO::FETCH_ASSOC);

// ── Detectar se é chamada AJAX ────────────────────────────────────────────────
$is_ajax = $_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['ajax_request']);

// ════════════════════════════════════════════════════════════════════════════
//  BLOCO JSON — só corre quando é chamada AJAX
// ════════════════════════════════════════════════════════════════════════════
if ($is_ajax) {
    header('Content-Type: application/json');

    $nome_video              = trim($_POST['nome_video']  ?? '');
    $descricao               = trim($_POST['descricao']   ?? '');
    $preco                   = floatval($_POST['preco']   ?? 0);
    $duracao_raw             = trim($_POST['duracao']     ?? '');
    $duracao                 = $duracao_raw !== '' ? $duracao_raw : null;
    $categorias_selecionadas = $_POST['categorias']       ?? [];
    $remover_previa          = !empty($_POST['remover_previa']);
    $remover_imagem          = !empty($_POST['remover_imagem']);

    // URLs vindas da Cloudinary (ou mantidas do vídeo actual se não houve novo upload)
    $url_previa_nova = trim($_POST['url_previa_nova'] ?? '');
    $url_imagem_nova = trim($_POST['url_imagem_nova'] ?? '');

    // Dados para debug em caso de erro
    $dados_recebidos = [
        'id_video'        => $id_video,
        'nome_video'      => $nome_video,
        'descricao'       => $descricao,
        'preco'           => $preco,
        'duracao'         => $duracao,
        'categorias'      => $categorias_selecionadas,
        'remover_previa'  => $remover_previa,
        'remover_imagem'  => $remover_imagem,
        'url_previa_nova' => $url_previa_nova,
        'url_imagem_nova' => $url_imagem_nova,
    ];

    // ── Validações ────────────────────────────────────────────────────────
    if ($nome_video === '') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'O nome do vídeo é obrigatório.', 'debug' => $dados_recebidos]);
        exit;
    }
    if (empty($categorias_selecionadas)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Selecione pelo menos uma categoria.', 'debug' => $dados_recebidos]);
        exit;
    }

    // Determinar caminhos finais para a BD
    // — Prévia: usa nova URL se foi enviada, remove se checkbox marcado, ou mantém a actual
    if ($url_previa_nova !== '') {
        $caminho_previa_final = $url_previa_nova;
    } elseif ($remover_previa) {
        $caminho_previa_final = null;
    } else {
        $caminho_previa_final = $video['caminho_previa'];
    }

    // — Imagem: usa nova URL se foi enviada, ou mantém a actual (remoção sem substituição é tratada abaixo)
    if ($url_imagem_nova !== '') {
        $caminho_imagem_final = $url_imagem_nova;
    } elseif ($remover_imagem) {
        $caminho_imagem_final = null;
    } else {
        $caminho_imagem_final = $video['caminho_imagem'];
    }

    // ── Verificar se houve alguma alteração ──────────────────────────────
    $duracao_bd = $video['duracao'] !== null ? rtrim($video['duracao'], ' ') : null;
    $houveAlteracao = (
        $nome_video              !== $video['nome_video']    ||
        $descricao               !== ($video['descricao'] ?? '')  ||
        $preco                   != $video['preco']          ||
        $duracao_raw             !== ($duracao_bd ?? '')     ||
        $caminho_previa_final    !== $video['caminho_previa']||
        $caminho_imagem_final    !== $video['caminho_imagem']||
        !empty(array_diff($categorias_selecionadas, $categorias_atuais)) ||
        !empty(array_diff($categorias_atuais, $categorias_selecionadas))
    );

    if (!$houveAlteracao) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Nenhuma alteração foi feita.']);
        exit;
    }

    // ── Gravar na base de dados ───────────────────────────────────────────
    try {
        $conexao->beginTransaction();

        // Actualizar dados principais do vídeo
        $conexao->prepare("
            UPDATE video
            SET nome_video = ?, descricao = ?, preco = ?, duracao = ?, caminho_previa = ?
            WHERE id_video = ?
        ")->execute([$nome_video, $descricao, $preco, $duracao, $caminho_previa_final, $id_video]);

        // Actualizar imagem de destaque se mudou
        if ($caminho_imagem_final !== $video['caminho_imagem']) {
            // Remove registo anterior
            $conexao->prepare("DELETE FROM video_imagem WHERE id_video = ? AND imagem_principal = true")
                    ->execute([$id_video]);
            // Insere novo se há imagem
            if ($caminho_imagem_final !== null) {
                $conexao->prepare(
                    "INSERT INTO video_imagem (id_video, caminho_imagem, imagem_principal) VALUES (?, ?, true)"
                )->execute([$id_video, $caminho_imagem_final]);
            }
        }

        // Actualizar categorias
        $conexao->prepare("DELETE FROM video_categoria WHERE id_video = ?")->execute([$id_video]);
        $stmtCat = $conexao->prepare("INSERT INTO video_categoria (id_video, id_categoria) VALUES (?, ?)");
        foreach ($categorias_selecionadas as $id_categoria) {
            $stmtCat->execute([$id_video, (int)$id_categoria]);
        }

        $conexao->commit();

        echo json_encode([
            'status'   => 'ok',
            'id_video' => $id_video,
            'mensagem' => 'Vídeo actualizado com sucesso!',
        ]);

    } catch (Exception $e) {
        if ($conexao->inTransaction()) $conexao->rollBack();
        $msg_erro = $e->getMessage();
        error_log("ERRO EDITAR VIDEO: " . $msg_erro . " | Trace: " . $e->getTraceAsString());
        echo json_encode([
            'status'   => 'erro',
            'mensagem' => 'Erro ao gravar na base de dados: ' . $msg_erro,
            'debug'    => $dados_recebidos,
        ]);
    }

    exit; // Nunca renderiza HTML quando é AJAX
}

// ════════════════════════════════════════════════════════════════════════════
//  BLOCO HTML — GET ou POST de fallback
// ════════════════════════════════════════════════════════════════════════════

$mensagem      = "";
$tipo_mensagem = "";
$redirecionar  = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Fallback sem JS — não há uploads para Cloudinary neste caminho,
    // apenas actualiza os campos de texto e categorias.
    $nome_video              = trim($_POST['nome_video']  ?? '');
    $descricao               = trim($_POST['descricao']   ?? '');
    $preco                   = floatval($_POST['preco']   ?? 0);
    $duracao_raw             = trim($_POST['duracao']     ?? '');
    $duracao                 = $duracao_raw !== '' ? $duracao_raw : null;
    $categorias_selecionadas = $_POST['categorias']       ?? [];

    if ($nome_video === '') {
        $mensagem = "⚠️ O nome do vídeo é obrigatório.";
        $tipo_mensagem = "error";
    } elseif (empty($categorias_selecionadas)) {
        $mensagem = "⚠️ Selecione pelo menos uma categoria.";
        $tipo_mensagem = "error";
    } else {
        try {
            $conexao->beginTransaction();

            $conexao->prepare("
                UPDATE video SET nome_video=?, descricao=?, preco=?, duracao=? WHERE id_video=?
            ")->execute([$nome_video, $descricao, $preco, $duracao, $id_video]);

            $conexao->prepare("DELETE FROM video_categoria WHERE id_video=?")->execute([$id_video]);
            $stmtCat = $conexao->prepare("INSERT INTO video_categoria (id_video, id_categoria) VALUES (?,?)");
            foreach ($categorias_selecionadas as $id_cat) {
                $stmtCat->execute([$id_video, (int)$id_cat]);
            }

            $conexao->commit();
            $mensagem = "✅ Vídeo actualizado com sucesso!";
            $tipo_mensagem = "success";
            $redirecionar  = true;

            // Recarregar dados
            $stmt->execute([$id_video]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            if ($conexao->inTransaction()) $conexao->rollBack();
            error_log("ERRO EDITAR VIDEO: " . $e->getMessage());
            $mensagem = "❌ Erro: " . $e->getMessage();
            $tipo_mensagem = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Vídeo</title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>
    <style>
        .drop-zone {
            width: 100%; min-height: 130px; padding: 20px; margin-bottom: 10px;
            text-align: center; border: 2px dashed #3498db; border-radius: 10px;
            background-color: #ecf0f1; transition: all 0.3s;
        }
        .drop-zone.drag-over { background-color: #d0e7f7; border-color: #2980b9; }
        .drop-zone-text { color: #7f8c8d; font-size: 1em; margin-bottom: 10px; }
        .file-input { display: none; }
        .file-name { font-weight: bold; color: #27ae60; margin-top: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; }
        .preview-container { margin-top: 15px; }
        .preview-container img  { max-width: 300px; border-radius: 8px; }
        .preview-container video { max-width: 500px; border-radius: 8px; }
        .current-file {
            background: #e8f5e9; padding: 14px 16px; border-radius: 8px; margin-bottom: 12px;
        }
        .current-file a { color: #27ae60; text-decoration: none; font-weight: bold; }
        .current-file video, .current-file img { display: block; margin-top: 10px; max-width: 400px; border-radius: 8px; }
        .remover-label { display: flex; align-items: center; gap: 6px; margin-top: 10px; font-size: .9rem; color: #c0392b; cursor: pointer; }

        /* ── Barras de progresso ── */
        .upload-progress-wrapper { display: none; margin-top: 14px; text-align: left; }
        .upload-progress-wrapper.visible { display: block; }
        .progress-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 5px; font-size: 0.82rem; font-weight: 600; color: #555;
        }
        .progress-status { font-size: 0.78rem; color: #888; }
        .progress-track { width: 100%; height: 10px; background: #dce1e7; border-radius: 99px; overflow: hidden; }
        .progress-bar {
            height: 100%; width: 0%; border-radius: 99px;
            transition: width 0.25s ease; position: relative; overflow: hidden;
        }
        .progress-bar::after {
            content: ''; position: absolute; top: 0; left: -60%;
            width: 60%; height: 100%; background: rgba(255,255,255,0.35);
            animation: shimmer 1.2s infinite;
        }
        @keyframes shimmer { to { left: 110%; } }
        .progress-bar.video { background: linear-gradient(90deg, #667eea, #764ba2); }
        .progress-bar.image { background: linear-gradient(90deg, #11998e, #38ef7d); }
        .progress-bar.done  { background: linear-gradient(90deg, #27ae60, #2ecc71); }
        .progress-bar.done::after { display: none; }
        .progress-meta { display: flex; justify-content: space-between; margin-top: 4px; font-size: 0.75rem; color: #999; }

        /* ── Overlay global ── */
        #uploadOverlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); z-index: 9999;
            flex-direction: column; align-items: center; justify-content: center; gap: 20px;
        }
        #uploadOverlay.visible { display: flex; }
        .overlay-card {
            background: #fff; border-radius: 16px; padding: 32px 40px;
            min-width: 340px; max-width: 480px; width: 90%;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }
        .overlay-card h3 { margin: 0 0 6px; font-size: 1.1rem; color: #333; }
        .overlay-step { font-size: 0.82rem; color: #888; margin-bottom: 20px; }
        .overlay-section { margin-bottom: 18px; }
        .overlay-section:last-child { margin-bottom: 0; }
        .overlay-label {
            font-size: 0.82rem; font-weight: 700; color: #555; margin-bottom: 6px;
            display: flex; align-items: center; gap: 6px;
        }
        .overlay-label .pct { margin-left: auto; font-weight: 800; color: #333; }
    </style>
</head>
<body>

    <button class="menu-btn">☰</button>
    <div class="sidebar-overlay"></div>

    <sidebar class="sidebar">
        <br><br>
        <a href="gerenciar_videos.php">Voltar à Gestão de Vídeos</a>
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
            <h1>Editar Vídeo</h1>

            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?= htmlspecialchars($tipo_mensagem) ?>">
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="editar_video.php?id_video=<?= $id_video ?>"
                  enctype="multipart/form-data" class="form-container" id="uploadForm">

                <!-- id_video como campo oculto para o AJAX saber qual vídeo editar -->
                <input type="hidden" name="id_video" value="<?= $id_video ?>">

                <div class="form-group">
                    <label for="nome_video">Nome do Vídeo *</label>
                    <input type="text" name="nome_video" id="nome_video"
                           value="<?= htmlspecialchars($video['nome_video']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea name="descricao" id="descricao" rows="4"><?= htmlspecialchars($video['descricao'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="preco">Preço (opcional)</label>
                    <input type="number" name="preco" id="preco" step="0.01" min="0"
                           value="<?= htmlspecialchars($video['preco'] ?? '0.00') ?>">
                </div>

                <div class="form-group">
                    <label for="duracao">Duração (HH:MM:SS)</label>
                    <input type="text" name="duracao" id="duracao" placeholder="00:03:45"
                           value="<?= htmlspecialchars($video['duracao'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Categorias *</label>
                    <div class="checkbox-group">
                        <?php foreach ($lista_categorias as $cat): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="categorias[]"
                                       id="cat_<?= $cat['id_categoria'] ?>"
                                       value="<?= $cat['id_categoria'] ?>"
                                       <?= in_array($cat['id_categoria'], $categorias_atuais) ? 'checked' : '' ?>>
                                <label for="cat_<?= $cat['id_categoria'] ?>">
                                    <?= htmlspecialchars($cat['nome_categoria']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── Prévia actual + upload de nova ── -->
                <div class="form-group">
                    <label>Prévia do Vídeo</label>

                    <?php if (!empty($video['caminho_previa'])): ?>
                        <div class="current-file" id="currentFilePrevia">
                            <p>📹 <a href="<?= htmlspecialchars($video['caminho_previa']) ?>" target="_blank">Ver prévia actual</a></p>
                            <video src="<?= htmlspecialchars($video['caminho_previa']) ?>"
                                   controls style="max-width:400px;"></video>
                            <label class="remover-label">
                                <input type="checkbox" name="remover_previa" id="removerPrevia">
                                🗑️ Remover prévia actual
                            </label>
                        </div>
                    <?php else: ?>
                        <p><em>Nenhuma prévia anexada.</em></p>
                    <?php endif; ?>

                    <input type="file" name="video_previa" id="video_previa" accept="video/*" class="file-input">
                    <div class="drop-zone" id="dropZonePrevia">
                        <div class="drop-zone-text">Arraste nova prévia aqui</div>
                        <button type="button" onclick="document.getElementById('video_previa').click()">
                            Ou Clique para Escolher
                        </button>
                        <p class="file-name" id="fileNamePrevia"></p>
                        <div class="upload-progress-wrapper" id="progressWrapperPrevia">
                            <div class="progress-header">
                                <span id="progressLabelPrevia">Aguardando envio…</span>
                                <span class="progress-status" id="progressPctPrevia">0%</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-bar video" id="progressBarPrevia"></div>
                            </div>
                            <div class="progress-meta">
                                <span id="progressSizePrevia"></span>
                                <span id="progressSpeedPrevia"></span>
                            </div>
                        </div>
                        <div class="preview-container" id="previewPrevia"></div>
                    </div>
                </div>

                <!-- ── Imagem actual + upload de nova ── -->
                <div class="form-group">
                    <label>Imagem de Destaque</label>

                    <?php if (!empty($video['caminho_imagem'])): ?>
                        <div class="current-file" id="currentFileImagem">
                            <p>🖼️ <a href="<?= htmlspecialchars($video['caminho_imagem']) ?>" target="_blank">Ver imagem actual</a></p>
                            <img src="<?= htmlspecialchars($video['caminho_imagem']) ?>"
                                 style="max-width:300px;">
                            <label class="remover-label">
                                <input type="checkbox" name="remover_imagem" id="removerImagem">
                                🗑️ Remover imagem actual
                            </label>
                        </div>
                    <?php else: ?>
                        <p><em>Nenhuma imagem anexada.</em></p>
                    <?php endif; ?>

                    <input type="file" name="imagem_destaque" id="imagem_destaque" accept="image/*" class="file-input">
                    <div class="drop-zone" id="dropZoneImagem">
                        <div class="drop-zone-text">Arraste nova imagem aqui</div>
                        <button type="button" onclick="document.getElementById('imagem_destaque').click()">
                            Ou Clique para Escolher
                        </button>
                        <p class="file-name" id="fileNameImagem"></p>
                        <div class="upload-progress-wrapper" id="progressWrapperImagem">
                            <div class="progress-header">
                                <span id="progressLabelImagem">Aguardando envio…</span>
                                <span class="progress-status" id="progressPctImagem">0%</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-bar image" id="progressBarImagem"></div>
                            </div>
                            <div class="progress-meta">
                                <span id="progressSizeImagem"></span>
                                <span id="progressSpeedImagem"></span>
                            </div>
                        </div>
                        <div class="preview-container" id="previewImagem"></div>
                    </div>
                </div>

                <button type="submit" id="submitBtn">💾 Salvar Alterações</button>
            </form>
        </div>
    </div>

    <!-- ── Overlay global ── -->
    <div id="uploadOverlay">
        <div class="overlay-card">
            <h3>💾 Salvando alterações… Por favor aguarde.</h3>
            <p class="overlay-step" id="overlayStep">A preparar…</p>

            <div class="overlay-section" id="overlaySectionPrevia" style="display:none;">
                <div class="overlay-label">
                    🎬 Nova prévia do vídeo
                    <span class="pct" id="overlayPctPrevia">0%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-bar video" id="overlayBarPrevia"></div>
                </div>
                <div class="progress-meta">
                    <span id="overlaySizePrevia"></span>
                    <span id="overlaySpeedPrevia"></span>
                </div>
            </div>

            <div class="overlay-section" id="overlaySectionImagem" style="display:none;">
                <div class="overlay-label">
                    🖼️ Nova imagem de destaque
                    <span class="pct" id="overlayPctImagem">0%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-bar image" id="overlayBarImagem"></div>
                </div>
                <div class="progress-meta">
                    <span id="overlaySizeImagem"></span>
                    <span id="overlaySpeedImagem"></span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($redirecionar): ?>
        <script>setTimeout(() => { window.location.href = 'gerenciar_videos.php'; }, 2000);</script>
    <?php endif; ?>

    <script>
    // ════════════════════════════════════════════════════════
    //  DROP ZONES — pré-visualização local dos ficheiros
    // ════════════════════════════════════════════════════════
    setupDropZone('dropZonePrevia', 'video_previa',    'fileNamePrevia', 'previewPrevia', 'video');
    setupDropZone('dropZoneImagem', 'imagem_destaque', 'fileNameImagem', 'previewImagem', 'image');

    function setupDropZone(dropZoneId, inputId, fileNameId, previewId, type) {
        const dropZone  = document.getElementById(dropZoneId);
        const fileInput = document.getElementById(inputId);
        const nameEl    = document.getElementById(fileNameId);
        const previewEl = document.getElementById(previewId);

        ['dragenter','dragover','dragleave','drop'].forEach(ev =>
            dropZone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }));
        ['dragenter','dragover'].forEach(ev =>
            dropZone.addEventListener(ev, () => dropZone.classList.add('drag-over')));
        ['dragleave','drop'].forEach(ev =>
            dropZone.addEventListener(ev, () => dropZone.classList.remove('drag-over')));

        dropZone.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files.length) { fileInput.files = files; handleFile(files[0], nameEl, previewEl, type); }
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) handleFile(fileInput.files[0], nameEl, previewEl, type);
        });
    }

    function handleFile(file, nameEl, previewEl, type) {
        nameEl.textContent = `Novo arquivo: ${file.name} (${formatBytes(file.size)})`;
        previewEl.innerHTML = '';
        const suffix = type === 'video' ? 'Previa' : 'Imagem';
        showLocalReadProgress(file, suffix);
        if (type === 'video') {
            const video = document.createElement('video');
            video.src = URL.createObjectURL(file);
            video.controls = true;
            video.style.maxWidth = '100%';
            previewEl.appendChild(video);
        } else {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.style.maxWidth = '100%';
            previewEl.appendChild(img);
        }
    }

    function showLocalReadProgress(file, suffix) {
        const wrapper = document.getElementById('progressWrapper' + suffix);
        const bar     = document.getElementById('progressBar'     + suffix);
        const pctEl   = document.getElementById('progressPct'     + suffix);
        const labelEl = document.getElementById('progressLabel'   + suffix);
        const sizeEl  = document.getElementById('progressSize'    + suffix);
        const speedEl = document.getElementById('progressSpeed'   + suffix);

        wrapper.classList.add('visible');
        bar.classList.remove('done');
        bar.style.width     = '0%';
        labelEl.textContent = 'Lendo arquivo…';
        sizeEl.textContent  = `0 B / ${formatBytes(file.size)}`;
        speedEl.textContent = '';

        const reader  = new FileReader();
        const started = Date.now();

        reader.onprogress = e => {
            if (!e.lengthComputable) return;
            const pct     = Math.round((e.loaded / e.total) * 100);
            const elapsed = (Date.now() - started) / 1000 || 0.001;
            const speed   = e.loaded / elapsed;
            bar.style.width     = pct + '%';
            pctEl.textContent   = pct + '%';
            sizeEl.textContent  = `${formatBytes(e.loaded)} / ${formatBytes(e.total)}`;
            speedEl.textContent = `${formatBytes(speed)}/s`;
            labelEl.textContent = 'Lendo arquivo…';
        };
        reader.onload = () => {
            bar.style.width     = '100%';
            pctEl.textContent   = '100%';
            bar.classList.add('done');
            labelEl.textContent = '✅ Arquivo pronto para envio';
            sizeEl.textContent  = formatBytes(file.size);
            speedEl.textContent = '';
        };
        reader.onerror = () => { labelEl.textContent = '❌ Erro ao ler arquivo'; };
        reader.readAsArrayBuffer(file);
    }

    // ════════════════════════════════════════════════════════
    //  SUBMISSÃO — fases dinâmicas:
    //   Se há nova prévia  → Fase 1: upload vídeo  → upload_cloudinary.php
    //   Se há nova imagem  → Fase 2: upload imagem → upload_cloudinary.php
    //   Sempre             → Fase final: gravar na BD → editar_video.php (JSON)
    // ════════════════════════════════════════════════════════
    document.getElementById('uploadForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const form       = this;
        const overlay    = document.getElementById('uploadOverlay');
        const submitBtn  = document.getElementById('submitBtn');
        const stepEl     = document.getElementById('overlayStep');
        const filePrevia = document.getElementById('video_previa').files[0]    || null;
        const fileImagem = document.getElementById('imagem_destaque').files[0] || null;
        const nomeVideo  = document.getElementById('nome_video').value.trim();

        if (!nomeVideo) { mostrarErroInline('⚠️ Por favor, preencha o nome do vídeo.'); return; }

        // Mostrar/ocultar secções do overlay conforme ficheiros seleccionados
        document.getElementById('overlaySectionPrevia').style.display = filePrevia ? 'block' : 'none';
        document.getElementById('overlaySectionImagem').style.display = fileImagem ? 'block' : 'none';

        overlay.classList.add('visible');
        submitBtn.disabled = true;

        if (filePrevia) resetOverlayBar('Previa');
        if (fileImagem) resetOverlayBar('Imagem');

        let urlPreviaNova = '';
        let urlImagemNova = '';

        try {
            // ── Fase 1 (condicional): Upload da nova prévia ───────────────
            if (filePrevia) {
                stepEl.textContent = 'A enviar nova prévia do vídeo…';
                urlPreviaNova = await uploadComProgresso(filePrevia, 'video', 'Previa');
            }

            // ── Fase 2 (condicional): Upload da nova imagem ───────────────
            if (fileImagem) {
                stepEl.textContent = 'A enviar nova imagem de destaque…';
                urlImagemNova = await uploadComProgresso(fileImagem, 'imagem', 'Imagem');
            }

            // ── Fase final: Gravar na BD ──────────────────────────────────
            stepEl.textContent = 'A guardar alterações na base de dados…';

            const formData = new FormData(form);
            // Remove ficheiros binários — as URLs já foram obtidas
            formData.delete('video_previa');
            formData.delete('imagem_destaque');
            // Sinaliza que é chamada AJAX
            formData.append('ajax_request', '1');
            // Envia as URLs obtidas (vazias se não houve upload)
            formData.append('url_previa_nova', urlPreviaNova);
            formData.append('url_imagem_nova', urlImagemNova);

            const resp = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
            });

            if (!resp.ok) {
                throw new Error(`Erro HTTP ${resp.status} ao gravar na base de dados.`);
            }

            let data;
            const rawText = await resp.text();
            try {
                data = JSON.parse(rawText);
            } catch (_) {
                overlay.classList.remove('visible');
                submitBtn.disabled = false;
                mostrarErroInline(
                    '❌ Resposta inesperada do servidor (não é JSON). ' +
                    'Início da resposta: ' + rawText.substring(0, 300).replace(/</g, '&lt;')
                );
                console.error('Resposta raw:', rawText);
                return;
            }

            overlay.classList.remove('visible');
            submitBtn.disabled = false;

            if (data.status === 'ok') {
                mostrarSucessoInline('✅ ' + (data.mensagem || 'Vídeo actualizado com sucesso!'));
                setTimeout(() => { window.location.href = 'gerenciar_videos.php'; }, 2000);
            } else {
                mostrarErroInline('❌ ' + (data.mensagem || 'Erro desconhecido.'));
                if (data.debug) {
                    console.group('🔍 Debug — dados recebidos pelo servidor');
                    console.table(data.debug);
                    console.groupEnd();
                }
            }

        } catch (err) {
            overlay.classList.remove('visible');
            submitBtn.disabled = false;
            mostrarErroInline('❌ ' + err.message);
            console.error('Erro na submissão:', err);
        }
    });

    // ── Upload de ficheiro para a Cloudinary ─────────────────────────────────
    function uploadComProgresso(file, tipo, suffix) {
        return new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('arquivo', file);
            fd.append('tipo', tipo);

            const xhr     = new XMLHttpRequest();
            const started = Date.now();

            xhr.upload.onprogress = function (e) {
                if (!e.lengthComputable) return;
                const elapsed = (Date.now() - started) / 1000 || 0.001;
                const speed   = e.loaded / elapsed;
                const pct     = Math.round((e.loaded / e.total) * 100);
                updateBar(suffix, pct, e.loaded, e.total, speed);
            };

            xhr.onload = function () {
                if (xhr.status !== 200) {
                    reject(new Error(`Erro HTTP ${xhr.status} no upload.`));
                    return;
                }
                let data;
                try {
                    data = JSON.parse(xhr.responseText);
                } catch (_) {
                    reject(new Error('Resposta inválida do servidor de upload.'));
                    return;
                }
                if (data.erro) {
                    reject(new Error(data.erro));
                    return;
                }
                if (!data.url) {
                    reject(new Error('O servidor de upload não devolveu uma URL.'));
                    return;
                }
                updateBar(suffix, 100, file.size, file.size, 0);
                resolve(data.url);
            };

            xhr.onerror   = () => reject(new Error('Erro de rede durante o upload.'));
            xhr.ontimeout = () => reject(new Error('Timeout: o upload demorou demasiado.'));

            xhr.open('POST', 'upload_cloudinary.php', true);
            xhr.send(fd);
        });
    }

    // ════════════════════════════════════════════════════════
    //  UTILITÁRIOS
    // ════════════════════════════════════════════════════════
    function updateBar(suffix, pct, loaded, total, speed) {
        const bar     = document.getElementById('overlayBar'   + suffix);
        const pctEl   = document.getElementById('overlayPct'   + suffix);
        const sizeEl  = document.getElementById('overlaySize'  + suffix);
        const speedEl = document.getElementById('overlaySpeed' + suffix);
        bar.style.width     = pct + '%';
        pctEl.textContent   = pct + '%';
        sizeEl.textContent  = `${formatBytes(loaded)} / ${formatBytes(total)}`;
        speedEl.textContent = speed > 0 ? `${formatBytes(speed)}/s` : '';
        if (pct >= 100) { bar.classList.add('done'); speedEl.textContent = '✅ Concluído'; }
    }

    function resetOverlayBar(suffix) {
        const bar = document.getElementById('overlayBar' + suffix);
        bar.style.width = '0%';
        bar.classList.remove('done');
        document.getElementById('overlayPct'   + suffix).textContent = '0%';
        document.getElementById('overlaySize'  + suffix).textContent = '';
        document.getElementById('overlaySpeed' + suffix).textContent = '';
    }

    function mostrarErroInline(texto) { _mostrarMensagem(texto, 'error'); }
    function mostrarSucessoInline(texto) { _mostrarMensagem(texto, 'success'); }

    function _mostrarMensagem(texto, tipo) {
        const existente = document.querySelector('.mensagem');
        if (existente) existente.remove();
        const div = document.createElement('div');
        div.className   = 'mensagem ' + tipo;
        div.textContent = texto;
        document.querySelector('.main h1').insertAdjacentElement('afterend', div);
        div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    </script>
</body>
</html>