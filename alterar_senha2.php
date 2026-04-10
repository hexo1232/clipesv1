<?php
//allterar_senha2.php
include "verifica_login.php";
include "conexao.php"; // Deve ser a versão PDO que configuramos
include "info_usuario.php";


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario_logado = $_SESSION['usuario'];
$id_usuario = $usuario_logado['id_usuario'];
$mensagem = "";
$tipo_mensagem = "error";
$redirecionar = false;
$id_perfil = $_SESSION['usuario']['idperfil'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmacao = $_POST['confirmacao'] ?? '';

    try {
        // 1. Buscar hash atual
        $stmt = $conexao->prepare("SELECT senha_hash FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario_db || !password_verify($senha_atual, $usuario_db['senha_hash'])) {
            $mensagem = "A Senha actual está incorrecta.";
        } elseif ($nova_senha !== $confirmacao) {
            $mensagem = "A nova senha e a confirmação não coincidem.";
        } else {
            // Iniciar transação para garantir que ambos os updates ocorram
            $conexao->beginTransaction();

            // 2. Atualizar senha
            $nova_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $conexao->prepare("UPDATE usuario SET senha_hash = ?, primeira_senha = '0' WHERE id_usuario = ?");
            $stmt->execute([$nova_hash, $id_usuario]);

            // 3. Registrar no histórico
            $stmt_hist = $conexao->prepare("INSERT INTO historico_senhas (id_usuario, senha_hash) VALUES (?, ?)");
            $stmt_hist->execute([$id_usuario, $nova_hash]);

            $conexao->commit();

            $mensagem = "Senha atualizada com sucesso! Você será desconectado para aplicar as mudanças.";
            $tipo_mensagem = "success";
            $redirecionar = true;
        }
    } catch (PDOException $e) {
        if ($conexao->inTransaction()) $conexao->rollBack();
        $mensagem = "Erro no banco de dados: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Alterar Senha</title>
    <link rel="stylesheet" href="css/admin.css">
       <link rel="stylesheet" href="css/basico.css">
         <script src="js/darkmode1.js"></script>
          <script src="js/dropdown2.js"></script>
                <script src="js/mostrarSenha.js"></script>

                <style>
.editar{
background: #ff6600;
color: #fff;
border: none;
}

.editar:hover{
    background: #be5006ff;
    transform:scale(1.1);
}
                </style>
</head>
<body>
<div class="conteudo">
    <h2>Alterar Senha</h2>

    <?php if (!empty($mensagem)): ?>
        <div class="mensagem <?= $tipo_mensagem ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

  <sidebar class="sidebar">
       <h2>Menu Do(a) <?= htmlspecialchars($usuario['nome'] ?? 'Usuário') ?></h2>
<?php if($id_perfil=1):?> 
        <a href="dashboard.php">Voltar</a>
     <?php else: ?>
<a href="index.php">Voltar</a>

  <?php endif; ?>

           <!-- Rodapé da sidebar -->

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
                        <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar" id="iconecadeado"> 
                        Alterar Senha
                    </a>
                    <a href="logout.php">
                        <img class="iconelogout" src="icones/logout1.png" alt="Logout" title="Sair">  
                        Sair
                    </a>
                </div>
            </div>
            <img class="dark-toggle" id="darkToggle"
                   src="icones/lua.png"
                   alt="Modo Escuro"
                   title="Alternar modo escuro">
        </div>
    </sidebar>

<div class="container">
<form method="POST" action="">
  <label>Senha atual:</label>
  
  <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="senha_atual" class="campo-senha-atual" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Mostrar senha atual"
         class="toggle-senha"
         data-target="campo-senha-atual"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div>

  <label>Nova senha:</label>
  <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="nova_senha" class="campo-senha-nova" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Mostrar nova senha"
         class="toggle-senha"
         data-target="campo-senha-nova"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div>

  <label>Confirmar nova senha:</label>
  <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="confirmacao" class="campo-senha-confirmacao" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Mostrar confirmação de senha"
         class="toggle-senha"
         data-target="campo-senha-confirmacao"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div>

  <input class="editar"  type="submit" value="Atualizar Senha">
</form>

</div>
</div>

<?php if ($redirecionar): ?>
<script>
    setTimeout(() => {
        window.location.href = 'logout.php';
    }, 3000);
</script>
<?php endif; ?>
</body>
</html>