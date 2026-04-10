<?php
// editar_video.php
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
$id_perfil = $usuario['idperfil'] ?? null;

if ($id_perfil != 1) {
    header("Location: ver_videos.php");
    exit;
}

$id_video = intval($_GET['id_video'] ?? 0);
if ($id_video <= 0) {
    header("Location: gerenciar_videos.php");
    exit;
}

$mensagem = "";
$tipo_mensagem = "";
$redirecionar = false;

$stmt = $conexao->prepare("SELECT v.*, vi.caminho_imagem 
                           FROM video v 
                           LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = 1 
                           WHERE v.id_video = ?");
$stmt->bind_param("i", $id_video);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) die("Vídeo não encontrado.");

$stmtCatAtual = $conexao->prepare("SELECT id_categoria FROM video_categoria WHERE id_video = ?");
$stmtCatAtual->bind_param("i", $id_video);
$stmtCatAtual->execute();
$resCatAtual = $stmtCatAtual->get_result();
$categorias_atuais = [];
while ($row = $resCatAtual->fetch_assoc()) $categorias_atuais[] = $row['id_categoria'];

$categorias = $conexao->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome_video  = trim($_POST['nome_video']);
    $descricao   = trim($_POST['descricao']);
    $preco       = floatval($_POST['preco']);
    $duracao     = trim($_POST['duracao']);
    $categorias_selecionadas = $_POST['categorias'] ?? [];

    $remover_previa = isset($_POST['remover_previa']);
    $remover_imagem = isset($_POST['remover_imagem']);
    $nova_previa    = $_FILES['video_previa']['name']    ?? "";
    $nova_imagem    = $_FILES['imagem_destaque']['name'] ?? "";

    $houveAlteracao = (
        $nome_video !== $video['nome_video'] ||
        $descricao  !== $video['descricao']  ||
        $preco      != $video['preco']        ||
        $duracao    !== $video['duracao']     ||
        array_diff($categorias_selecionadas, $categorias_atuais) ||
        array_diff($categorias_atuais, $categorias_selecionadas) ||
        !empty($nova_previa) || !empty($nova_imagem) ||
        $remover_previa || $remover_imagem
    );

    if (!$houveAlteracao) {
        $mensagem = "Nenhuma alteração foi feita.";
        $tipo_mensagem = "error";
    } else {
        $conexao->begin_transaction();
        try {
            $caminho_previa_atual = $video['caminho_previa'];
            $caminho_imagem_atual = $video['caminho_imagem'];

            if ($remover_previa && $caminho_previa_atual && file_exists($caminho_previa_atual)) {
                unlink($caminho_previa_atual);
                $caminho_previa_atual = null;
            }

            if (!empty($nova_previa) && $_FILES['video_previa']['error'] === UPLOAD_ERR_OK) {
                $dir_previa = "uploads/videos/previas/";
                if (!is_dir($dir_previa)) mkdir($dir_previa, 0777, true);
                $ext_previa = pathinfo($_FILES['video_previa']['name'], PATHINFO_EXTENSION);
                $novo_caminho_previa = $dir_previa . uniqid("previa_") . "." . strtolower($ext_previa);

                $tipos_video = ['video/mp4', 'video/webm', 'video/ogg'];
                if (!in_array($_FILES['video_previa']['type'], $tipos_video))
                    throw new Exception("Formato de vídeo não permitido.");
                if ($_FILES['video_previa']['size'] > 100 * 1024 * 1024)
                    throw new Exception("Prévia muito grande. Limite: 100MB.");

                if (move_uploaded_file($_FILES['video_previa']['tmp_name'], $novo_caminho_previa)) {
                    if ($caminho_previa_atual && file_exists($caminho_previa_atual)) unlink($caminho_previa_atual);
                    $caminho_previa_atual = $novo_caminho_previa;
                }
            }

            if ($remover_imagem && $caminho_imagem_atual && file_exists($caminho_imagem_atual)) {
                unlink($caminho_imagem_atual);
                $conexao->query("DELETE FROM video_imagem WHERE id_video = $id_video AND imagem_principal = 1");
                $caminho_imagem_atual = null;
            }

            if (!empty($nova_imagem) && $_FILES['imagem_destaque']['error'] === UPLOAD_ERR_OK) {
                $dir_imagem = "uploads/videos/imagens/";
                if (!is_dir($dir_imagem)) mkdir($dir_imagem, 0777, true);
                $ext_imagem = pathinfo($_FILES['imagem_destaque']['name'], PATHINFO_EXTENSION);
                $novo_caminho_imagem = $dir_imagem . uniqid("img_") . "." . strtolower($ext_imagem);

                $tipos_imagem = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!in_array($_FILES['imagem_destaque']['type'], $tipos_imagem))
                    throw new Exception("Formato de imagem não permitido.");
                if ($_FILES['imagem_destaque']['size'] > 5 * 1024 * 1024)
                    throw new Exception("Imagem muito grande. Limite: 5MB.");

                if (move_uploaded_file($_FILES['imagem_destaque']['tmp_name'], $novo_caminho_imagem)) {
                    if ($caminho_imagem_atual && file_exists($caminho_imagem_atual)) unlink($caminho_imagem_atual);
                    $conexao->query("DELETE FROM video_imagem WHERE id_video = $id_video AND imagem_principal = 1");
                    $stmtImg = $conexao->prepare("INSERT INTO video_imagem (id_video, caminho_imagem, imagem_principal) VALUES (?, ?, 1)");
                    $stmtImg->bind_param("is", $id_video, $novo_caminho_imagem);
                    $stmtImg->execute();
                }
            }

            $sql_update = "UPDATE video SET nome_video=?, descricao=?, preco=?, duracao=?, caminho_previa=? WHERE id_video=?";
            $stmt_up = $conexao->prepare($sql_update);
            $stmt_up->bind_param("ssdssi", $nome_video, $descricao, $preco, $duracao, $caminho_previa_atual, $id_video);
            $stmt_up->execute();

            $conexao->query("DELETE FROM video_categoria WHERE id_video = $id_video");
            $stmtCat = $conexao->prepare("INSERT INTO video_categoria (id_video, id_categoria) VALUES (?, ?)");
            foreach ($categorias_selecionadas as $id_categoria) {
                $stmtCat->bind_param("ii", $id_video, $id_categoria);
                $stmtCat->execute();
            }

            $conexao->commit();
            $mensagem = "✅ Vídeo atualizado com sucesso!";
            $tipo_mensagem = "success";
            $redirecionar = true;

            $stmt = $conexao->prepare("SELECT v.*, vi.caminho_imagem FROM video v 
                                       LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = 1 
                                       WHERE v.id_video = ?");
            $stmt->bind_param("i", $id_video);
            $stmt->execute();
            $video = $stmt->get_result()->fetch_assoc();

        } catch (Exception $e) {
            $conexao->rollback();
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
<script src="logout_auto.js"></script>
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
.file-input { display: none; }
.file-name { font-weight: bold; color: #27ae60; margin-top: 10px; }
.checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.checkbox-item { display: flex; align-items: center; gap: 8px; }
.preview-container { margin-top: 15px; }
.preview-container img, .preview-container video { max-width: 100%; border-radius: 8px; }
.current-file { background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
.current-file a { color: #27ae60; text-decoration: none; font-weight: bold; }

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

.progress-bar::after {
    content: '';
    position: absolute;
    top: 0; left: -60%;
    width: 60%; height: 100%;
    background: rgba(255,255,255,0.35);
    animation: shimmer 1.2s infinite;
}
@keyframes shimmer { to { left: 110%; } }

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
.overlay-card h3 { margin: 0 0 20px; font-size: 1.1rem; color: #333; }
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
            <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>
                <img class="icone" src="icones/user1.png" alt="Editar"> Editar Dados Pessoais
            </a>
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

    <?php if ($mensagem): ?>
      <div class="mensagem <?= htmlspecialchars($tipo_mensagem) ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="uploadForm">

      <div class="form-group">
        <label>Nome do Vídeo *</label>
        <input type="text" name="nome_video" value="<?= htmlspecialchars($video['nome_video']) ?>" required>
      </div>

      <div class="form-group">
        <label>Descrição</label>
        <textarea name="descricao" rows="4"><?= htmlspecialchars($video['descricao']) ?></textarea>
      </div>

      <div class="form-group">
        <label>Preço</label>
        <input type="number" name="preco" step="0.01" min="0" value="<?= $video['preco'] ?>">
      </div>

      <div class="form-group">
        <label>Duração (HH:MM:SS)</label>
        <input type="text" name="duracao" placeholder="00:03:45" value="<?= htmlspecialchars($video['duracao']) ?>">
      </div>

      <div class="form-group">
        <label>Categorias *</label>
        <div class="checkbox-group">
          <?php $categorias->data_seek(0); while ($cat = $categorias->fetch_assoc()): ?>
            <div class="checkbox-item">
              <input type="checkbox" name="categorias[]" id="cat_<?= $cat['id_categoria'] ?>"
                     value="<?= $cat['id_categoria'] ?>"
                     <?= in_array($cat['id_categoria'], $categorias_atuais) ? 'checked' : '' ?>>
              <label for="cat_<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nome_categoria']) ?></label>
            </div>
          <?php endwhile; ?>
        </div>
      </div>

      <!-- Prévia Atual -->
      <div class="form-group">
        <label>Prévia do Vídeo</label>
        <?php if ($video['caminho_previa'] && file_exists($video['caminho_previa'])): ?>
          <div class="current-file">
            <p>📹 <a href="<?= $video['caminho_previa'] ?>" target="_blank">Ver prévia atual</a></p>
            <video src="<?= $video['caminho_previa'] ?>" controls style="max-width: 400px; border-radius: 8px;"></video>
            <br><br>
            <label><input type="checkbox" name="remover_previa"> Remover prévia atual</label>
          </div>
        <?php else: ?>
          <p><em>Nenhuma prévia anexada.</em></p>
        <?php endif; ?>

        <input type="file" name="video_previa" id="video_previa" accept="video/*" class="file-input">
        <div class="drop-zone" id="dropZonePrevia">
          <p>Arraste nova prévia aqui ou
            <button type="button" onclick="document.getElementById('video_previa').click()">clique para escolher</button>
          </p>
          <p id="fileNamePrevia" class="file-name"></p>

          <!-- Progress bar – prévia -->
          <div class="upload-progress-wrapper" id="progressWrapperPrevia">
            <div class="progress-header">
              <span id="progressLabelPrevia">Aguardando envio…</span>
              <span id="progressPctPrevia" style="font-size:.78rem;color:#888;">0%</span>
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

      <!-- Imagem Atual -->
      <div class="form-group">
        <label>Imagem de Destaque</label>
        <?php if ($video['caminho_imagem'] && file_exists($video['caminho_imagem'])): ?>
          <div class="current-file">
            <p>🖼️ <a href="<?= $video['caminho_imagem'] ?>" target="_blank">Ver imagem atual</a></p>
            <img src="<?= $video['caminho_imagem'] ?>" style="max-width: 300px; border-radius: 8px;">
            <br><br>
            <label><input type="checkbox" name="remover_imagem"> Remover imagem atual</label>
          </div>
        <?php else: ?>
          <p><em>Nenhuma imagem anexada.</em></p>
        <?php endif; ?>

        <input type="file" name="imagem_destaque" id="imagem_destaque" accept="image/*" class="file-input">
        <div class="drop-zone" id="dropZoneImagem">
          <p>Arraste nova imagem aqui ou
            <button type="button" onclick="document.getElementById('imagem_destaque').click()">clique para escolher</button>
          </p>
          <p id="fileNameImagem" class="file-name"></p>

          <!-- Progress bar – imagem -->
          <div class="upload-progress-wrapper" id="progressWrapperImagem">
            <div class="progress-header">
              <span id="progressLabelImagem">Aguardando envio…</span>
              <span id="progressPctImagem" style="font-size:.78rem;color:#888;">0%</span>
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

      <button type="submit" id="submitBtn"
              style="background:#27ae60;color:white;padding:15px 30px;
                     border:none;border-radius:8px;cursor:pointer;
                     font-size:1.1em;font-weight:bold;">
        💾 Salvar Alterações
      </button>
    </form>
  </div>
</div>

<!-- Overlay de envio global -->
<div id="uploadOverlay">
  <div class="overlay-card">
    <h3>⬆️ Salvando alterações… Por favor aguarde.</h3>

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

    <div id="overlaySectionTexto" style="text-align:center;padding:10px 0;color:#555;font-size:.9rem;">
      💾 Salvando dados do vídeo…
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
//  Drag & drop + leitura local com progresso
// ─────────────────────────────────────────────
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

// Progresso real de leitura local via FileReader
function showLocalReadProgress(file, suffix) {
    const wrapper  = document.getElementById('progressWrapper' + suffix);
    const bar      = document.getElementById('progressBar'    + suffix);
    const pctEl    = document.getElementById('progressPct'    + suffix);
    const labelEl  = document.getElementById('progressLabel'  + suffix);
    const sizeEl   = document.getElementById('progressSize'   + suffix);
    const speedEl  = document.getElementById('progressSpeed'  + suffix);

    wrapper.classList.add('visible');
    bar.classList.remove('done');
    bar.style.width   = '0%';
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

// ─────────────────────────────────────────────
//  Envio via XHR com progresso real
//  Adaptado para edição: só mostra barras dos
//  arquivos que foram realmente selecionados
// ─────────────────────────────────────────────
document.getElementById('uploadForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const form      = this;
    const overlay   = document.getElementById('uploadOverlay');
    const submitBtn = document.getElementById('submitBtn');

    const filePrevia = document.getElementById('video_previa').files[0]    || null;
    const fileImagem = document.getElementById('imagem_destaque').files[0] || null;

    const temArquivo = filePrevia || fileImagem;

    // Mostrar seções relevantes no overlay
    document.getElementById('overlaySectionPrevia').style.display = filePrevia ? 'block' : 'none';
    document.getElementById('overlaySectionImagem').style.display = fileImagem ? 'block' : 'none';
    document.getElementById('overlaySectionTexto').style.display  = temArquivo ? 'none'  : 'block';

    overlay.classList.add('visible');
    submitBtn.disabled = true;

    if (filePrevia) resetOverlayBar('Previa');
    if (fileImagem) resetOverlayBar('Imagem');

    const formData = new FormData(form);
    const xhr      = new XMLHttpRequest();
    const started  = Date.now();

    const sizePrevia = filePrevia ? filePrevia.size : 0;
    const sizeImagem = fileImagem ? fileImagem.size : 0;
    const sizeTotal  = sizePrevia + sizeImagem;

    xhr.upload.onprogress = function (e) {
        if (!e.lengthComputable) return;

        const elapsed = (Date.now() - started) / 1000 || 0.001;
        const speed   = e.loaded / elapsed;

        if (filePrevia && sizePrevia > 0) {
            const loadedPrevia = Math.min(e.loaded, sizePrevia);
            const pctPrevia    = Math.round((loadedPrevia / sizePrevia) * 100);
            updateOverlayBar('Previa', pctPrevia, loadedPrevia, sizePrevia, speed);
        }

        if (fileImagem && sizeImagem > 0) {
            const loadedImagem = Math.max(0, e.loaded - sizePrevia);
            const pctImagem    = Math.round((loadedImagem / sizeImagem) * 100);
            updateOverlayBar('Imagem', pctImagem, loadedImagem, sizeImagem, speed);
        }
    };

    xhr.onload = function () {
        if (filePrevia) updateOverlayBar('Previa', 100, sizePrevia, sizePrevia, 0);
        if (fileImagem) updateOverlayBar('Imagem', 100, sizeImagem, sizeImagem, 0);

        setTimeout(() => {
            overlay.classList.remove('visible');
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
function updateOverlayBar(suffix, pct, loaded, total, speed) {
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
    const bar    = document.getElementById('overlayBar'   + suffix);
    const pctEl  = document.getElementById('overlayPct'   + suffix);
    const sizeEl = document.getElementById('overlaySize'  + suffix);
    const spEl   = document.getElementById('overlaySpeed' + suffix);
    bar.style.width  = '0%';
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