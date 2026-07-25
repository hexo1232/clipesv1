<?php
// gerenciar_categorias.php
include "verifica_login.php";
include "conexao.php"; // Deve ser a versão PDO que configuramos
include "info_usuario.php";


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$id_perfil = $usuario['idperfil'] ?? null;

// Apenas Admin
if ($id_perfil != 1) {
    header("Location: ver_videos.php");
    exit;
}

$mensagem = "";
$tipo_mensagem = "";

// --- LÓGICA PDO ---

// Adicionar categoria
if (isset($_POST['adicionar'])) {
    $nome = trim($_POST['nome_categoria']);
    $descricao = trim($_POST['descricao']);
    
    if (!empty($nome)) {
        try {
            $stmt = $conexao->prepare("INSERT INTO categoria (nome_categoria, descricao) VALUES (:nome, :descricao)");
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':descricao', $descricao);
            if ($stmt->execute()) {
                $mensagem = "✅ Categoria adicionada com sucesso!";
                $tipo_mensagem = "success";
            } else {
                $mensagem = "❌ Erro ao adicionar categoria.";
                $tipo_mensagem = "error";
            }
        } catch (PDOException $e) {
            $mensagem = "❌ Erro: " . $e->getMessage();
            $tipo_mensagem = "error";
        }
    }
}

// Editar categoria
if (isset($_POST['editar'])) {
    $id = intval($_POST['id_categoria']);
    $nome = trim($_POST['nome_categoria']);
    $descricao = trim($_POST['descricao']);
    
    if ($id > 0 && !empty($nome)) {
        try {
            $stmt = $conexao->prepare("UPDATE categoria SET nome_categoria=:nome, descricao=:descricao WHERE id_categoria=:id");
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $mensagem = "✅ Categoria atualizada com sucesso!";
                $tipo_mensagem = "success";
            } else {
                $mensagem = "❌ Erro ao atualizar categoria.";
                $tipo_mensagem = "error";
            }
        } catch (PDOException $e) {
            $mensagem = "❌ Erro: " . $e->getMessage();
            $tipo_mensagem = "error";
        }
    }
}

// Excluir categoria (com remoção de vínculos)
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    
    try {
        // Verificar quantos vídeos estão vinculados
        $stmtCheck = $conexao->prepare("SELECT COUNT(*) AS total FROM video_categoria WHERE id_categoria = :id");
        $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        $res = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $total = $res['total'];
        
        // Iniciar transação
        $conexao->beginTransaction();
        
        // 1. Remover todos os vínculos da categoria com vídeos
        if ($total > 0) {
            $stmtRemoveVinculos = $conexao->prepare("DELETE FROM video_categoria WHERE id_categoria = :id");
            $stmtRemoveVinculos->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtRemoveVinculos->execute();
        }
        
        // 2. Excluir a categoria
        $stmtDelete = $conexao->prepare("DELETE FROM categoria WHERE id_categoria = :id");
        $stmtDelete->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtDelete->execute();
        
        // Confirmar transação
        $conexao->commit();
        
        if ($total > 0) {
            $mensagem = "✅ Categoria excluída com sucesso! Foram removidos os vínculos com $total vídeo(s), mas os vídeos foram mantidos.";
        } else {
            $mensagem = "✅ Categoria excluída com sucesso!";
        }
        $tipo_mensagem = "success";
        
    } catch (Exception $e) {
        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }
        $mensagem = "❌ Erro ao excluir categoria: " . $e->getMessage();
        $tipo_mensagem = "error";
    }
}

// Carregar categorias
$query = "SELECT c.*, 
    (SELECT COUNT(*) FROM video_categoria vc WHERE vc.id_categoria = c.id_categoria) AS total_videos
    FROM categoria c ORDER BY c.nome_categoria";
$stmtList = $conexao->query($query);
// No PDO, costuma-se usar fetchAll para facilitar o loop e a contagem
$categorias_lista = $stmtList->fetchAll(PDO::FETCH_ASSOC);
$total_rows = count($categorias_lista);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gerenciar Categorias</title>
<link rel="stylesheet" href="css/admin.css">

<script src="js/darkmode2.js"></script>
<script src="js/sidebar.js"></script>
<script src="js/dropdown2.js"></script>

<style>
.categoria-card { 
    background: white; 
    padding: 20px; 
    border-radius: 10px; 
    margin-bottom: 20px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
    transition: transform 0.2s; 
}
.categoria-card:hover { 
    transform: translateY(-3px); 
    box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
}
.categoria-card h3 { 
    margin: 0 0 10px 0; 
    color: #2c3e50; 
    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    flex-wrap: wrap; 
    gap: 10px; 
}
.categoria-card p { 
    color: #7f8c8d; 
    margin: 5px 0; 
}
.badge-videos { 
    background: #3498db; 
    color: white; 
    padding: 4px 10px; 
    border-radius: 12px; 
    font-size: 0.85em; 
    font-weight: bold; 
}
.actions { 
    display: flex; 
    gap: 10px; 
    margin-top: 15px; 
}
.actions button, .actions a { 
    padding: 8px 16px; 
    border: none; 
    border-radius: 6px; 
    cursor: pointer; 
    text-decoration: none; 
    font-size: 0.9em; 
    transition: transform 0.2s, opacity 0.2s; 
}
.actions button:hover, .actions a:hover { 
    transform: scale(1.05); 
    opacity: 0.9; 
}
.btn-editar { background: #3498db; color: white; }
.btn-excluir { background: #e74c3c; color: white; }
.form-categoria { 
    background: #ecf0f1; 
    padding: 25px; 
    border-radius: 10px; 
    margin-bottom: 30px; 
}
.form-categoria input, .form-categoria textarea { 
    width: 100%; 
    padding: 12px; 
    margin-bottom: 15px; 
    border: 1px solid #bdc3c7; 
    border-radius: 6px; 
    font-family: inherit; 
}
.form-categoria button { 
    background: #27ae60; 
    color: white; 
    padding: 12px 30px; 
    border: none; 
    border-radius: 6px; 
    cursor: pointer; 
    font-weight: bold; 
    transition: background 0.3s; 
}
.form-categoria button:hover { background: #229954; }
.mensagem { 
    padding: 15px; 
    border-radius: 8px; 
    margin-bottom: 20px; 
    animation: slideDown 0.3s ease; 
}
.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.warning-text { 
    color: #856404; 
    background: #fff3cd; 
    padding: 10px; 
    border-radius: 6px; 
    margin-top: 10px; 
    font-size: 0.9em; 
    border-left: 4px solid #ffc107; 
}
</style>
</head>
<body>

<button class="menu-btn">☰</button>
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
<br><br>

  <a href="dashboard.php"> Voltar á Dashboard</a>
  
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
    <h1> Gerenciar Categorias</h1>

    <?php if ($mensagem): ?>
      <div class="mensagem <?= htmlspecialchars($tipo_mensagem) ?>">
        <?= htmlspecialchars($mensagem) ?>
      </div>
    <?php endif; ?>

    <div class="form-categoria">
      <h2> Adicionar Nova Categoria</h2>
      <form method="post">
        <input type="text" name="nome_categoria" placeholder="Nome da Categoria *" required>
        <textarea name="descricao" rows="3" placeholder="Descrição (opcional)"></textarea>
        <button type="submit" name="adicionar">Adicionar Categoria</button>
      </form>
    </div>

    <h2> Categorias Existentes (<?= $total_rows ?>)</h2>

    <?php if ($total_rows == 0): ?>
      <div style="text-align: center; padding: 40px; color: #7f8c8d;">
        <p style="font-size: 1.2em;">Nenhuma categoria cadastrada ainda.</p>
        <p>Adicione a primeira categoria usando o formulário acima.</p>
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
        <?php foreach ($categorias_lista as $cat): ?>
          <div class="categoria-card">
            <h3>
              <span><?= htmlspecialchars($cat['nome_categoria']) ?></span>
              <span class="badge-videos"><?= $cat['total_videos'] ?> vídeos</span>
            </h3>
            <p><?= $cat['descricao'] ? htmlspecialchars($cat['descricao']) : '<em>Sem descrição</em>' ?></p>
            
            <?php if ($cat['total_videos'] > 0): ?>
              <div class="warning-text">
                ⚠️ Ao excluir, os vínculos com <?= $cat['total_videos'] ?> vídeo(s) serão removidos, mas os vídeos permanecerão no sistema.
              </div>
            <?php endif; ?>
            
            <div class="actions">
              <button onclick="editarCategoria(<?= $cat['id_categoria'] ?>, '<?= addslashes($cat['nome_categoria']) ?>', '<?= addslashes($cat['descricao']) ?>')" 
                      class="btn-editar">Editar</button>
              <a href="?excluir=<?= $cat['id_categoria'] ?>" 
                 onclick="return confirmarExclusao('<?= addslashes($cat['nome_categoria']) ?>', <?= $cat['total_videos'] ?>)" 
                 class="btn-excluir"> Excluir</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<div id="modalEditar" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
     background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:white; padding:30px; border-radius:10px; max-width:500px; width:90%; 
              box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
    <h2> Editar Categoria</h2>
    <form method="post">
      <input type="hidden" name="id_categoria" id="edit_id">
      <input type="text" name="nome_categoria" id="edit_nome" placeholder="Nome da Categoria *" 
             style="width:100%; padding:12px; margin-bottom:15px; border:1px solid #bdc3c7; 
                    border-radius:6px; font-family:inherit;" required>
      <textarea name="descricao" id="edit_descricao" rows="3" placeholder="Descrição"
                style="width:100%; padding:12px; margin-bottom:15px; border:1px solid #bdc3c7; 
                       border-radius:6px; font-family:inherit;"></textarea>
      <div style="display:flex; gap:10px;">
        <button type="submit" name="editar" 
                style="background:#27ae60; color:white; padding:12px 30px; border:none; 
                       border-radius:6px; cursor:pointer; font-weight:bold;">💾 Salvar</button>
        <button type="button" onclick="fecharModal()" 
                style="background:#95a5a6; color:white; padding:12px 30px; border:none; 
                       border-radius:6px; cursor:pointer;">❌ Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
function editarCategoria(id, nome, descricao) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_nome').value = nome;
  document.getElementById('edit_descricao').value = descricao || '';
  document.getElementById('modalEditar').style.display = 'flex';
}

function fecharModal() {
  document.getElementById('modalEditar').style.display = 'none';
}

function confirmarExclusao(nomeCategoria, totalVideos) {
  let mensagem = `Tem certeza que deseja excluir a categoria "${nomeCategoria}"?`;
  
  if (totalVideos > 0) {
    mensagem += `\n\n⚠️ ATENÇÃO: Esta categoria está vinculada a ${totalVideos} vídeo(s).\n`;
    mensagem += `Os vínculos serão removidos, mas os vídeos NÃO serão excluídos.\n`;
    mensagem += `Os vídeos permanecerão no sistema sem esta categoria.`;
  }
  
  return confirm(mensagem);
}

// Fechar modal ao clicar fora
document.getElementById('modalEditar').addEventListener('click', function(e) {
  if (e.target === this) fecharModal();
});

// Fechar modal com tecla ESC
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') fecharModal();
});

// Auto-hide mensagens após 8 segundos
setTimeout(() => {
  const mensagens = document.querySelectorAll('.mensagem');
  mensagens.forEach(msg => {
    msg.style.transition = 'opacity 0.5s';
    msg.style.opacity = '0';
    setTimeout(() => msg.remove(), 500);
  });
}, 8000);
</script>

</body>
</html>