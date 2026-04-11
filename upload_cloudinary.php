<?php
// ════════════════════════════════════════════════════════════════════════════
//  cadastrar_video.php
//  — GET  : renderiza o formulário HTML normalmente
//  — POST com url_previa definido (fase AJAX): devolve JSON puro
//  — POST sem url_previa (submissão directa, fallback): renderiza HTML
// ════════════════════════════════════════════════════════════════════════════

include "verifica_login.php";
include "conexao.php";
include "info_usuario.php";

$conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    // Se for pedido AJAX responde em JSON; caso contrário redireciona
    if (!empty($_POST['url_previa'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'erro', 'mensagem' => 'Sessão expirada. Faça login novamente.']);
        exit;
    }
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];

// ── Detectar se é chamada AJAX (fase 3) ──────────────────────────────────────
// O JS envia sempre url_previa quando está na fase 3 de gravação na BD.
$is_ajax = $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['url_previa']);

// ════════════════════════════════════════════════════════════════════════════
//  BLOCO JSON — só corre quando é chamada AJAX
// ════════════════════════════════════════════════════════════════════════════
if ($is_ajax) {
    header('Content-Type: application/json');

    // Recolher dados recebidos (para debug em caso de erro)
    $dados_recebidos = [
        'nome_video'   => $_POST['nome_video']   ?? null,
        'descricao'    => $_POST['descricao']    ?? null,
        'preco'        => $_POST['preco']        ?? null,
        'duracao'      => $_POST['duracao']      ?? null,
        'categorias'   => $_POST['categorias']   ?? [],
        'url_previa'   => $_POST['url_previa']   ?? null,
        'url_imagem'   => $_POST['url_imagem']   ?? null,
        'id_usuario'   => $usuario['id_usuario'] ?? null,
    ];

    $nome_video              = trim($_POST['nome_video']  ?? '');
    $descricao               = trim($_POST['descricao']   ?? '');
    $preco                   = floatval($_POST['preco']   ?? 0);
    $duracao_raw             = trim($_POST['duracao']     ?? '');
    $duracao                 = $duracao_raw !== '' ? $duracao_raw : null;
    $categorias_selecionadas = $_POST['categorias']       ?? [];
    $caminho_previa          = trim($_POST['url_previa']  ?? '');
    $caminho_imagem          = trim($_POST['url_imagem']  ?? '');

    // ── Validações ────────────────────────────────────────────────────────
    if ($nome_video === '') {
        echo json_encode([
            'status'   => 'erro',
            'mensagem' => 'O nome do vídeo é obrigatório.',
            'debug'    => $dados_recebidos,
        ]);
        exit;
    }
    if (empty($categorias_selecionadas)) {
        echo json_encode([
            'status'   => 'erro',
            'mensagem' => 'Selecione pelo menos uma categoria.',
            'debug'    => $dados_recebidos,
        ]);
        exit;
    }
    if ($caminho_previa === '') {
        echo json_encode([
            'status'   => 'erro',
            'mensagem' => 'A URL da prévia não foi recebida. Tente novamente.',
            'debug'    => $dados_recebidos,
        ]);
        exit;
    }
    if ($caminho_imagem === '') {
        echo json_encode([
            'status'   => 'erro',
            'mensagem' => 'A URL da imagem não foi recebida. Tente novamente.',
            'debug'    => $dados_recebidos,
        ]);
        exit;
    }

    // ── Gravar na base de dados ───────────────────────────────────────────
    try {
        $conexao->beginTransaction();

        $stmt_video = $conexao->prepare("
            INSERT INTO video
                (nome_video, descricao, preco, duracao, caminho_previa, id_usuario)
            VALUES (?, ?, ?, ?, ?, ?)
            RETURNING id_video
        ");
        $stmt_video->execute([
            $nome_video,
            $descricao,
            $preco,
            $duracao,
            $caminho_previa,
            $usuario['id_usuario'],
        ]);

        $row      = $stmt_video->fetch(PDO::FETCH_ASSOC);
        $id_video = $row['id_video'] ?? null;

        if (empty($id_video)) {
            throw new Exception("RETURNING não devolveu id_video. Row: " . json_encode($row));
        }

        // Categorias
        $stmt_cat = $conexao->prepare(
            "INSERT INTO video_categoria (id_video, id_categoria) VALUES (?, ?)"
        );
        foreach ($categorias_selecionadas as $id_categoria) {
            $stmt_cat->execute([$id_video, (int)$id_categoria]);
        }

        // Imagem de destaque
        $conexao->prepare(
            "INSERT INTO video_imagem (id_video, caminho_imagem, imagem_principal)
             VALUES (?, ?, true)"
        )->execute([$id_video, $caminho_imagem]);

        $conexao->commit();

        echo json_encode([
            'status'   => 'ok',
            'id_video' => $id_video,
            'mensagem' => 'Vídeo cadastrado com sucesso!',
        ]);

    } catch (Exception $e) {
        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }
        $msg_erro = $e->getMessage();
        error_log("ERRO CADASTRO VIDEO: " . $msg_erro . " | Trace: " . $e->getTraceAsString());

        echo json_encode([
            'status'   => 'erro',
            'mensagem' => 'Erro ao gravar na base de dados: ' . $msg_erro,
            'debug'    => $dados_recebidos,
        ]);
    }

    exit; // Nunca renderiza HTML quando é AJAX
}

// ════════════════════════════════════════════════════════════════════════════
//  BLOCO HTML — GET ou POST de fallback (sem AJAX)
// ════════════════════════════════════════════════════════════════════════════

$mensagem      = "";
$tipo_mensagem = "info";
$redirecionar  = false;

// Categorias para o formulário
$lista_categorias = $conexao
    ->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria")
    ->fetchAll();

// POST de fallback (navegadores sem JS, por exemplo)
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome_video              = trim($_POST['nome_video']  ?? '');
    $descricao               = trim($_POST['descricao']   ?? '');
    $preco                   = floatval($_POST['preco']   ?? 0);
    $duracao_raw             = trim($_POST['duracao']     ?? '');
    $duracao                 = $duracao_raw !== '' ? $duracao_raw : null;
    $categorias_selecionadas = $_POST['categorias']       ?? [];
    $caminho_previa          = trim($_POST['url_previa']  ?? '');
    $caminho_imagem          = trim($_POST['url_imagem']  ?? '');

    if ($nome_video === '') {
        $mensagem = "⚠️ O nome do vídeo é obrigatório.";
        $tipo_mensagem = "error";
    } elseif (empty($categorias_selecionadas)) {
        $mensagem = "⚠️ Selecione pelo menos uma categoria.";
        $tipo_mensagem = "error";
    } elseif ($caminho_previa === '') {
        $mensagem = "⚠️ A URL da prévia não foi recebida. Tente novamente.";
        $tipo_mensagem = "error";
    } elseif ($caminho_imagem === '') {
        $mensagem = "⚠️ A URL da imagem não foi recebida. Tente novamente.";
        $tipo_mensagem = "error";
    } else {
        try {
            $conexao->beginTransaction();

            $stmt_video = $conexao->prepare("
                INSERT INTO video
                    (nome_video, descricao, preco, duracao, caminho_previa, id_usuario)
                VALUES (?, ?, ?, ?, ?, ?)
                RETURNING id_video
            ");
            $stmt_video->execute([
                $nome_video, $descricao, $preco, $duracao,
                $caminho_previa, $usuario['id_usuario'],
            ]);

            $row      = $stmt_video->fetch(PDO::FETCH_ASSOC);
            $id_video = $row['id_video'] ?? null;

            if (empty($id_video)) {
                throw new Exception("Não foi possível obter o id do vídeo após inserção.");
            }

            $stmt_cat = $conexao->prepare(
                "INSERT INTO video_categoria (id_video, id_categoria) VALUES (?, ?)"
            );
            foreach ($categorias_selecionadas as $id_categoria) {
                $stmt_cat->execute([$id_video, (int)$id_categoria]);
            }

            $conexao->prepare(
                "INSERT INTO video_imagem (id_video, caminho_imagem, imagem_principal)
                 VALUES (?, ?, true)"
            )->execute([$id_video, $caminho_imagem]);

            $conexao->commit();
            $mensagem      = "✅ Vídeo cadastrado com sucesso!";
            $tipo_mensagem = "success";
            $redirecionar  = true;

        } catch (Exception $e) {
            if ($conexao->inTransaction()) $conexao->rollBack();
            error_log("ERRO CADASTRO VIDEO: " . $e->getMessage());
            $mensagem      = "❌ Erro ao gravar na base de dados: " . $e->getMessage();
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
    <title>Cadastrar Vídeo</title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>
    <style>
        .drop-zone {
            width: 100%; min-height: 150px; padding: 20px; margin-bottom: 20px;
            text-align: center; border: 2px dashed #3498db; border-radius: 10px;
            background-color: #ecf0f1; transition: all 0.3s;
        }
        .drop-zone.drag-over { background-color: #d0e7f7; border-color: #2980b9; }
        .drop-zone-text { color: #7f8c8d; font-size: 1.1em; margin-bottom: 10px; }
        .file-input { display: none; }
        .file-name { font-weight: bold; color: #27ae60; margin-top: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; }
        .preview-container { margin-top: 15px; }
        .preview-container img  { max-width: 300px; border-radius: 8px; }
        .preview-container video { max-width: 500px; border-radius: 8px; }

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

        /* ── Overlay global de envio ── */
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
        <a href="gerenciar_videos.php">Voltar à área de Vídeos</a>
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
            <h1>Cadastrar Novo Vídeo</h1>

            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?= htmlspecialchars($tipo_mensagem) ?>">
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" enctype="multipart/form-data" class="form-container" id="uploadForm">

                <div class="form-group">
                    <label for="nome_video">Nome do Vídeo *</label>
                    <input type="text" name="nome_video" id="nome_video"
                           value="<?= htmlspecialchars($_POST['nome_video'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea name="descricao" id="descricao" rows="4"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="preco">Preço (opcional)</label>
                    <input type="number" name="preco" id="preco" step="0.01" min="0"
                           value="<?= htmlspecialchars($_POST['preco'] ?? '0.00') ?>">
                </div>

                <div class="form-group">
                    <label for="duracao">Duração (HH:MM:SS)</label>
                    <input type="text" name="duracao" id="duracao" placeholder="00:03:45"
                           value="<?= htmlspecialchars($_POST['duracao'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Categorias *</label>
                    <div class="checkbox-group">
                        <?php foreach ($lista_categorias as $cat): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="categorias[]"
                                       id="cat_<?= $cat['id_categoria'] ?>"
                                       value="<?= $cat['id_categoria'] ?>"
                                       <?= isset($_POST['categorias']) && in_array($cat['id_categoria'], $_POST['categorias']) ? 'checked' : '' ?>>
                                <label for="cat_<?= $cat['id_categoria'] ?>">
                                    <?= htmlspecialchars($cat['nome_categoria']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── Upload Prévia ── -->
                <div class="form-group">
                    <label>Prévia do Vídeo * (MP4, WebM ou OGG — Máx: 100MB)</label>
                    <input type="file" name="video_previa" id="video_previa" accept="video/*" class="file-input">
                    <div class="drop-zone" id="dropZonePrevia">
                        <div class="drop-zone-text">Arraste e solte a prévia aqui</div>
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

                <!-- ── Upload Imagem ── -->
                <div class="form-group">
                    <label>Imagem de Destaque * (JPG, PNG ou WebP — Máx: 5MB)</label>
                    <input type="file" name="imagem_destaque" id="imagem_destaque" accept="image/*" class="file-input">
                    <div class="drop-zone" id="dropZoneImagem">
                        <div class="drop-zone-text">Arraste e solte a imagem aqui</div>
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

                <button type="submit" id="submitBtn">Cadastrar Vídeo</button>
            </form>
        </div>
    </div>

    <!-- ── Overlay global de envio ── -->
    <div id="uploadOverlay">
        <div class="overlay-card">
            <h3>⬆️ Enviando arquivos… Por favor aguarde.</h3>
            <p class="overlay-step" id="overlayStep">A preparar…</p>

            <div class="overlay-section">
                <div class="overlay-label">
                    🎬 Prévia do vídeo
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

            <div class="overlay-section">
                <div class="overlay-label">
                    🖼️ Imagem de destaque
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
        nameEl.textContent = `Arquivo: ${file.name} (${formatBytes(file.size)})`;
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
    //  SUBMISSÃO EM 3 FASES:
    //   1. Upload do vídeo  → upload_cloudinary.php
    //   2. Upload da imagem → upload_cloudinary.php
    //   3. Gravar metadados → cadastrar_video.php (devolve JSON)
    // ════════════════════════════════════════════════════════
    document.getElementById('uploadForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const form       = this;
        const overlay    = document.getElementById('uploadOverlay');
        const submitBtn  = document.getElementById('submitBtn');
        const stepEl     = document.getElementById('overlayStep');
        const filePrevia = document.getElementById('video_previa').files[0];
        const fileImagem = document.getElementById('imagem_destaque').files[0];
        const nomeVideo  = document.getElementById('nome_video').value.trim();

        // Validações rápidas no cliente
        if (!nomeVideo)  { mostrarErroInline('⚠️ Por favor, preencha o nome do vídeo.'); return; }
        if (!filePrevia) { mostrarErroInline('⚠️ Por favor, selecione a prévia do vídeo.'); return; }
        if (!fileImagem) { mostrarErroInline('⚠️ Por favor, selecione a imagem de destaque.'); return; }

        overlay.classList.add('visible');
        submitBtn.disabled = true;
        resetOverlayBar('Previa');
        resetOverlayBar('Imagem');

        try {
            // ── Fase 1: Upload do vídeo ──────────────────────────────────
            stepEl.textContent = 'Passo 1/3 — A enviar a prévia do vídeo…';
            const urlPrevia = await uploadComProgresso(filePrevia, 'video', 'Previa');

            // ── Fase 2: Upload da imagem ─────────────────────────────────
            stepEl.textContent = 'Passo 2/3 — A enviar a imagem de destaque…';
            const urlImagem = await uploadComProgresso(fileImagem, 'imagem', 'Imagem');

            // ── Fase 3: Gravar na BD (resposta JSON) ─────────────────────
            stepEl.textContent = 'Passo 3/3 — A guardar na base de dados…';

            const formData = new FormData(form);
            formData.delete('video_previa');
            formData.delete('imagem_destaque');
            formData.append('url_previa', urlPrevia);
            formData.append('url_imagem', urlImagem);

            const resp = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
            });

            // Garantir que a resposta existe
            if (!resp.ok) {
                throw new Error(`Erro HTTP ${resp.status} ao gravar na base de dados.`);
            }

            // Fazer parse do JSON — se falhar, mostrar o texto raw para diagnóstico
            let data;
            const rawText = await resp.text();
            try {
                data = JSON.parse(rawText);
            } catch (_) {
                // O servidor devolveu HTML em vez de JSON — mostrar os primeiros 300 chars
                overlay.classList.remove('visible');
                submitBtn.disabled = false;
                mostrarErroInline(
                    '❌ Resposta inesperada do servidor (não é JSON). ' +
                    'Verifique os logs do Render. Início da resposta: ' +
                    rawText.substring(0, 300).replace(/</g, '&lt;')
                );
                console.error('Resposta raw do servidor:', rawText);
                return;
            }

            overlay.classList.remove('visible');
            submitBtn.disabled = false;

            if (data.status === 'ok') {
                // ✅ Sucesso — mostrar mensagem e redirecionar
                mostrarSucessoInline('✅ ' + (data.mensagem || 'Vídeo cadastrado com sucesso!'));
                setTimeout(() => { window.location.href = 'gerenciar_videos.php'; }, 2000);
            } else {
                // ❌ Erro devolvido pelo servidor em JSON
                const detalhe = data.mensagem || 'Erro desconhecido.';
                mostrarErroInline('❌ ' + detalhe);
                // Mostrar debug na consola para diagnóstico
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

    // ── Upload com barra de progresso ─────────────────────────────────────────
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
                    reject(new Error(`Erro HTTP ${xhr.status} no upload do ficheiro.`));
                    return;
                }
                let data;
                try {
                    data = JSON.parse(xhr.responseText);
                } catch (_) {
                    reject(new Error('Resposta inválida do servidor de upload (não é JSON).'));
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

        if (pct >= 100) {
            bar.classList.add('done');
            speedEl.textContent = '✅ Concluído';
        }
    }

    function resetOverlayBar(suffix) {
        const bar = document.getElementById('overlayBar' + suffix);
        bar.style.width = '0%';
        bar.classList.remove('done');
        document.getElementById('overlayPct'   + suffix).textContent = '0%';
        document.getElementById('overlaySize'  + suffix).textContent = '';
        document.getElementById('overlaySpeed' + suffix).textContent = '';
    }

    function mostrarErroInline(texto) {
        _mostrarMensagem(texto, 'error');
    }

    function mostrarSucessoInline(texto) {
        _mostrarMensagem(texto, 'success');
    }

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