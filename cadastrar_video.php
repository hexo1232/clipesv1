<?php
// cadastrar_video.php
include "conexao.php";
include "verifica_login.php";
include "info_usuario.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$id_perfil = $usuario['id_perfil'] ?? null;
$mensagem = "";
$tipo_mensagem = "info";
$redirecionar = false;

$categorias = $conexao->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_video = trim($_POST['nome_video'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = floatval($_POST['preco'] ?? 0);
    $duracao = trim($_POST['duracao'] ?? '');
    $categorias_selecionadas = $_POST['categorias'] ?? [];

    $arquivo_previa = $_FILES['video_previa'] ?? null;
    $arquivo_imagem = $_FILES['imagem_destaque'] ?? null;

    if (empty($nome_video) || empty($categorias_selecionadas)) {
        $mensagem = "⚠️ Nome do vídeo e pelo menos uma categoria são obrigatórios.";
        $tipo_mensagem = "error";
    } elseif (!isset($arquivo_previa) || $arquivo_previa['error'] != UPLOAD_ERR_OK) {
        $mensagem = "⚠️ A prévia do vídeo é obrigatória.";
        $tipo_mensagem = "error";
    } elseif (!isset($arquivo_imagem) || $arquivo_imagem['error'] != UPLOAD_ERR_OK) {
        $mensagem = "⚠️ A imagem de destaque é obrigatória.";
        $tipo_mensagem = "error";
    } else {
        $conexao->begin_transaction();
        try {
            $dir_previa = "uploads/videos/previas/";
            if (!is_dir($dir_previa)) mkdir($dir_previa, 0777, true);
            $ext_previa = pathinfo($arquivo_previa['name'], PATHINFO_EXTENSION);
            $nome_previa = uniqid("previa_") . "." . strtolower($ext_previa);
            $caminho_previa = $dir_previa . $nome_previa;

            $tipos_video_permitidos = ['video/mp4', 'video/webm', 'video/ogg'];
            if (!in_array($arquivo_previa['type'], $tipos_video_permitidos))
                throw new Exception("Formato de vídeo não permitido. Use MP4, WebM ou OGG.");
            if ($arquivo_previa['size'] > 100 * 1024 * 1024)
                throw new Exception("A prévia é muito grande. Limite: 100MB.");
            if (!move_uploaded_file($arquivo_previa['tmp_name'], $caminho_previa))
                throw new Exception("Erro ao fazer upload da prévia.");

            $dir_imagem = "uploads/videos/imagens/";
            if (!is_dir($dir_imagem)) mkdir($dir_imagem, 0777, true);
            $ext_imagem = pathinfo($arquivo_imagem['name'], PATHINFO_EXTENSION);
            $nome_imagem = uniqid("img_") . "." . strtolower($ext_imagem);
            $caminho_imagem = $dir_imagem . $nome_imagem;

            $tipos_imagem_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!in_array($arquivo_imagem['type'], $tipos_imagem_permitidos))
                throw new Exception("Formato de imagem não permitido. Use JPG, PNG ou WebP.");
            if ($arquivo_imagem['size'] > 5 * 1024 * 1024)
                throw new Exception("A imagem é muito grande. Limite: 5MB.");
            if (!move_uploaded_file($arquivo_imagem['tmp_name'], $caminho_imagem))
                throw new Exception("Erro ao fazer upload da imagem.");

            $sql_video = "INSERT INTO video (nome_video, descricao, preco, duracao, caminho_previa, id_usuario) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_video = $conexao->prepare($sql_video);
            $stmt_video->bind_param("ssdssi", $nome_video, $descricao, $preco, $duracao, $caminho_previa, $usuario['id_usuario']);
            $stmt_video->execute();
            $id_video = $stmt_video->insert_id;

            $stmt_cat = $conexao->prepare("INSERT INTO video_categoria (id_video, id_categoria) VALUES (?, ?)");
            foreach ($categorias_selecionadas as $id_categoria) {
                $stmt_cat->bind_param("ii", $id_video, $id_categoria);
                $stmt_cat->execute();
            }

            $stmt_img = $conexao->prepare("INSERT INTO video_imagem (id_video, caminho_imagem, imagem_principal) VALUES (?, ?, 1)");
            $stmt_img->bind_param("is", $id_video, $caminho_imagem);
            $stmt_img->execute();

            $conexao->commit();
            $mensagem = "✅ Vídeo cadastrado com sucesso!";
            $tipo_mensagem = "success";
            $redirecionar = true;

        } catch (Exception $e) {
            $conexao->rollback();
            $mensagem = "❌ Erro: " . $e->getMessage();
            $tipo_mensagem = "error";
            if (isset($caminho_previa) && file_exists($caminho_previa)) unlink($caminho_previa);
            if (isset($caminho_imagem) && file_exists($caminho_imagem)) unlink($caminho_imagem);
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
        /* ── Drop zones ── */
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
        .preview-container img { max-width: 300px; border-radius: 8px; }
        .preview-container video { max-width: 500px; border-radius: 8px; }

        /* ── Progress bar wrapper ── */
        .upload-progress-wrapper {
            display: none;
            margin-top: 14px;
            text-align: left;
        }
        .upload-progress-wrapper.visible { display: block; }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #555;
        }
        .progress-status { font-size: 0.78rem; color: #888; }

        .progress-track {
            width: 100%;
            height: 10px;
            background: #dce1e7;
            border-radius: 99px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            border-radius: 99px;
            transition: width 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        /* shimmer animation */
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0; left: -60%;
            width: 60%; height: 100%;
            background: rgba(255,255,255,0.35);
            animation: shimmer 1.2s infinite;
        }
        @keyframes shimmer {
            to { left: 110%; }
        }

        /* colours per type */
        .progress-bar.video { background: linear-gradient(90deg, #667eea, #764ba2); }
        .progress-bar.image { background: linear-gradient(90deg, #11998e, #38ef7d); }
        .progress-bar.done  { background: linear-gradient(90deg, #27ae60, #2ecc71); }
        .progress-bar.done::after { display: none; }

        .progress-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 4px;
            font-size: 0.75rem;
            color: #999;
        }

        /* ── Submit overlay ── */
        #uploadOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }
        #uploadOverlay.visible { display: flex; }

        .overlay-card {
            background: #fff;
            border-radius: 16px;
            padding: 32px 40px;
            min-width: 340px;
            max-width: 480px;
            width: 90%;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }
        .overlay-card h3 {
            margin: 0 0 20px;
            font-size: 1.1rem;
            color: #333;
        }
        .overlay-section { margin-bottom: 18px; }
        .overlay-section:last-child { margin-bottom: 0; }
        .overlay-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #555;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .overlay-label i { font-size: 1rem; }
        .overlay-label .pct {
            margin-left: auto;
            font-weight: 800;
            color: #333;
        }
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
                    <input type="text" name="nome_video" id="nome_video" value="<?= htmlspecialchars($_POST['nome_video'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea name="descricao" id="descricao" rows="4"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="preco">Preço (opcional)</label>
                    <input type="number" name="preco" id="preco" step="0.01" min="0" value="<?= htmlspecialchars($_POST['preco'] ?? '0.00') ?>">
                </div>

                <div class="form-group">
                    <label for="duracao">Duração (HH:MM:SS)</label>
                    <input type="text" name="duracao" id="duracao" placeholder="00:03:45" value="<?= htmlspecialchars($_POST['duracao'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Categorias *</label>
                    <div class="checkbox-group">
                        <?php $categorias->data_seek(0); while ($cat = $categorias->fetch_assoc()): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="categorias[]" id="cat_<?= $cat['id_categoria'] ?>" value="<?= $cat['id_categoria'] ?>"
                                    <?= isset($_POST['categorias']) && in_array($cat['id_categoria'], $_POST['categorias']) ? 'checked' : '' ?>>
                                <label for="cat_<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nome_categoria']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Upload Prévia -->
                <div class="form-group">
                    <label>Prévia do Vídeo * (MP4, WebM ou OGG — Máx: 100MB)</label>
                    <input type="file" name="video_previa" id="video_previa" accept="video/*" class="file-input" required>
                    <div class="drop-zone" id="dropZonePrevia">
                        <div class="drop-zone-text">Arraste e solte a prévia aqui</div>
                        <button type="button" onclick="document.getElementById('video_previa').click()">
                            Ou Clique para Escolher
                        </button>
                        <p class="file-name" id="fileNamePrevia"></p>

                        <!-- Progress bar – prévia -->
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

                <!-- Upload Imagem -->
                <div class="form-group">
                    <label>Imagem de Destaque * (JPG, PNG ou WebP — Máx: 5MB)</label>
                    <input type="file" name="imagem_destaque" id="imagem_destaque" accept="image/*" class="file-input" required>
                    <div class="drop-zone" id="dropZoneImagem">
                        <div class="drop-zone-text">Arraste e solte a imagem aqui</div>
                        <button type="button" onclick="document.getElementById('imagem_destaque').click()">
                            Ou Clique para Escolher
                        </button>
                        <p class="file-name" id="fileNameImagem"></p>

                        <!-- Progress bar – imagem -->
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

    <!-- Overlay de envio global -->
    <div id="uploadOverlay">
        <div class="overlay-card">
            <h3>⬆️ Enviando arquivos… Por favor aguarde.</h3>

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
        <script>
            setTimeout(() => { window.location.href = 'gerenciar_videos.php'; }, 2000);
        </script>
    <?php endif; ?>

    <script>
    // ─────────────────────────────────────────────
    //  Drag & drop + preview local (sem upload ainda)
    // ─────────────────────────────────────────────
    setupDropZone('dropZonePrevia',  'video_previa',      'fileNamePrevia',  'previewPrevia',  'video');
    setupDropZone('dropZoneImagem',  'imagem_destaque',   'fileNameImagem',  'previewImagem',  'image');

    function setupDropZone(dropZoneId, inputId, fileNameId, previewId, type) {
        const dropZone   = document.getElementById(dropZoneId);
        const fileInput  = document.getElementById(inputId);
        const nameEl     = document.getElementById(fileNameId);
        const previewEl  = document.getElementById(previewId);

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

        // mostrar barra de "leitura local" imediatamente
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

    // Simula a leitura local do arquivo (carregamento no browser) com progresso real via FileReader
    function showLocalReadProgress(file, suffix) {
        const wrapper  = document.getElementById('progressWrapper' + suffix);
        const bar      = document.getElementById('progressBar'    + suffix);
        const pctEl    = document.getElementById('progressPct'    + suffix);
        const labelEl  = document.getElementById('progressLabel'  + suffix);
        const sizeEl   = document.getElementById('progressSize'   + suffix);
        const speedEl  = document.getElementById('progressSpeed'  + suffix);

        wrapper.classList.add('visible');
        bar.classList.remove('done');
        bar.style.width = '0%';
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

            bar.style.width  = pct + '%';
            pctEl.textContent = pct + '%';
            sizeEl.textContent  = `${formatBytes(e.loaded)} / ${formatBytes(e.total)}`;
            speedEl.textContent = `${formatBytes(speed)}/s`;
            labelEl.textContent = 'Lendo arquivo…';
        };

        reader.onload = () => {
            bar.style.width   = '100%';
            pctEl.textContent = '100%';
            bar.classList.add('done');
            labelEl.textContent = '✅ Arquivo pronto para envio';
            sizeEl.textContent  = formatBytes(file.size);
            speedEl.textContent = '';
        };

        reader.onerror = () => {
            labelEl.textContent = '❌ Erro ao ler arquivo';
        };

        reader.readAsArrayBuffer(file);
    }

    // ─────────────────────────────────────────────
    //  Envio via XHR com progresso real de upload
    // ─────────────────────────────────────────────
    document.getElementById('uploadForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const form      = this;
        const overlay   = document.getElementById('uploadOverlay');
        const submitBtn = document.getElementById('submitBtn');

        // Validação básica antes do XHR
        const filePrevia  = document.getElementById('video_previa').files[0];
        const fileImagem  = document.getElementById('imagem_destaque').files[0];
        const nomeVideo   = document.getElementById('nome_video').value.trim();

        if (!nomeVideo) { alert('Por favor, preencha o nome do vídeo.'); return; }
        if (!filePrevia)  { alert('Por favor, selecione a prévia do vídeo.'); return; }
        if (!fileImagem)  { alert('Por favor, selecione a imagem de destaque.'); return; }

        overlay.classList.add('visible');
        submitBtn.disabled = true;

        // reset overlay bars
        resetOverlayBar('Previa');
        resetOverlayBar('Imagem');

        const formData = new FormData(form);

        const xhr = new XMLHttpRequest();
        const started = Date.now();

        // Tamanhos individuais para split da barra
        const sizePrevia = filePrevia.size;
        const sizeImagem = fileImagem.size;
        const sizeTotal  = sizePrevia + sizeImagem;

        xhr.upload.onprogress = function (e) {
            if (!e.lengthComputable) return;

            const elapsed = (Date.now() - started) / 1000 || 0.001;
            const speed   = e.loaded / elapsed;

            // ── Dividir o progresso global pelos dois arquivos ──
            // Fase 1: prévia (0 → sizePrevia)
            // Fase 2: imagem (sizePrevia → sizeTotal)
            const loadedPrevia = Math.min(e.loaded, sizePrevia);
            const loadedImagem = Math.max(0, e.loaded - sizePrevia);

            const pctPrevia = Math.round((loadedPrevia / sizePrevia) * 100);
            const pctImagem = sizeImagem > 0
                ? Math.round((loadedImagem / sizeImagem) * 100)
                : 0;

            updateBar('Previa', pctPrevia, loadedPrevia, sizePrevia, speed);
            updateBar('Imagem', pctImagem, loadedImagem, sizeImagem, speed);
        };

        xhr.onload = function () {
            // Marcar ambas como 100%
            updateBar('Previa', 100, sizePrevia, sizePrevia, 0);
            updateBar('Imagem', 100, sizeImagem, sizeImagem, 0);

            // Aguarda um instante e redireciona / recarrega
            setTimeout(() => {
                overlay.classList.remove('visible');
                // Redireciona igual ao comportamento PHP
                window.location.href = window.location.href;
            }, 800);
        };

        xhr.onerror = function () {
            overlay.classList.remove('visible');
            submitBtn.disabled = false;
            alert('Erro de rede durante o envio. Tente novamente.');
        };

        xhr.open('POST', form.action || window.location.href, true);
        xhr.send(formData);
    });

    // ─────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────
    function updateBar(suffix, pct, loaded, total, speed) {
        const bar     = document.getElementById('overlayBar'   + suffix);
        const pctEl   = document.getElementById('overlayPct'   + suffix);
        const sizeEl  = document.getElementById('overlaySize'  + suffix);
        const speedEl = document.getElementById('overlaySpeed' + suffix);

        bar.style.width   = pct + '%';
        pctEl.textContent = pct + '%';
        sizeEl.textContent  = `${formatBytes(loaded)} / ${formatBytes(total)}`;
        speedEl.textContent = speed > 0 ? `${formatBytes(speed)}/s` : '';

        if (pct >= 100) {
            bar.classList.add('done');
            speedEl.textContent = '✅ Concluído';
        }
    }

    function resetOverlayBar(suffix) {
        const bar    = document.getElementById('overlayBar'   + suffix);
        const pctEl  = document.getElementById('overlayPct'   + suffix);
        const sizeEl = document.getElementById('overlaySize'  + suffix);
        const spEl   = document.getElementById('overlaySpeed' + suffix);
        bar.style.width   = '0%';
        bar.classList.remove('done');
        pctEl.textContent  = '0%';
        sizeEl.textContent = '';
        spEl.textContent   = '';
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024, sizes = ['B','KB','MB','GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    </script>
</body>
</html>