<?php
// login.php
session_start();
include "conexao.php";
$erro = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entrada = trim($_POST["entrada"] ?? '');
    $senha   = $_POST["senha"] ?? '';
    if (isset($_GET['redir'])) {
        $_SESSION['url_destino'] = basename($_GET['redir']);
    }
    if (!empty($entrada) && !empty($senha)) {
        // CORREÇÃO: Trocado 'email' por 'apelido' para bater com seu banco de dados
      $stmt = $conexao->prepare("SELECT * FROM usuario WHERE TRIM(nome) ILIKE ? OR TRIM(apelido) ILIKE ? LIMIT 1");
$stmt->execute([$entrada, $entrada]);
        $usuario = $stmt->fetch();
        if ($usuario) {
if (password_verify($senha, $usuario['senha_hash'])) {
    $_SESSION['usuario'] = $usuario;
    $idPerfil = (int)$usuario['idperfil'];
    // Se for Admin, ignora qualquer url_destino antiga e vai pro Dashboard
    if ($idPerfil === 1) {
        unset($_SESSION['url_destino']); 
        header("Location: dashboard.php");
        exit;
    }
    // Se não for admin, segue o fluxo normal
    if (isset($_SESSION['url_destino'])) {
        $urlDestino = $_SESSION['url_destino'];
        unset($_SESSION['url_destino']);
        header("Location: " . $urlDestino);
        exit;
    }
    header("Location: index.php");
    exit;
} else {
        $erro = "Senha incorreta.";
    }
} else {
    $erro = "Usuário não encontrado.";
}
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Login — ObsidianPlay</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<link rel="stylesheet" href="css/admin.css">
<script src="js/darkmode2.js" defer></script>
<script src="js/mostrarSenha.js" defer></script>

<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='18' fill='%23070610'/%3E%3Cpath d='M14 18h36L32 52 14 18Z' fill='%238b5cf6'/%3E%3Cpath d='M22 18h20L32 39 22 18Z' fill='%23ffb84d' opacity='.95'/%3E%3C/svg%3E">

<style>
*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --bg: #060608;
    --card: #121219;
    --text: #f5f5f8;
    --muted: #9a9aab;
    --border: rgba(255,255,255,0.10);
    --red: #8b5cf6;
    --gold: #ffb84d;
    --danger: #ff6b81;
    --shadow: 0 30px 90px rgba(0,0,0,0.75);
}

html, body {
    height: 100%;
}

body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background:
        radial-gradient(circle at 15% 5%, rgba(139,92,246,0.30), transparent 34%),
        radial-gradient(circle at 88% 0%, rgba(255,184,77,0.16), transparent 28%),
        linear-gradient(180deg, #060608 0%, #0a0a12 48%, #060608 100%);
    color: var(--text);
    font-family: 'Manrope', sans-serif;
}

.login-card {
    width: 100%;
    max-width: 400px;
    background: rgba(18,18,25,0.88);
    border: 1px solid var(--border);
    border-radius: 26px;
    padding: 40px 32px 32px;
    box-shadow: var(--shadow);
    backdrop-filter: blur(20px);
    text-align: center;
}

.login-brandmark {
    width: 56px;
    height: 56px;
    margin: 0 auto 18px;
    border-radius: 17px;
    background:
        linear-gradient(135deg, var(--red), #4c1d95),
        radial-gradient(circle at 30% 20%, rgba(255,255,255,0.5), transparent 30%);
    display: grid;
    place-items: center;
    box-shadow: 0 0 34px rgba(139,92,246,0.5);
}

.login-brandmark i {
    color: white;
    font-size: 1.5rem;
}

.login-card h1 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 2.1rem;
    letter-spacing: 1px;
    margin-bottom: 4px;
}

.login-card .subtitle {
    color: var(--gold);
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2.6px;
    margin-bottom: 30px;
    display: block;
}

.field {
    text-align: left;
    margin-bottom: 18px;
}

.field label {
    display: block;
    margin-bottom: 8px;
    color: var(--muted);
    font-size: 0.72rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 1.2px;
}

.field input {
    width: 100%;
    height: 50px;
    border: 1px solid rgba(255,255,255,0.12);
    background: #0e0e15;
    border-radius: 14px;
    padding: 0 14px;
    color: white;
    outline: none;
    font-family: inherit;
    font-size: 0.95rem;
    font-weight: 600;
    transition: 0.2s ease;
}

.field input::placeholder {
    color: rgba(255,255,255,0.32);
}

.field input:focus {
    border-color: var(--red);
    box-shadow: 0 0 0 4px rgba(139,92,246,0.18);
}

.password-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.password-wrap input {
    padding-right: 44px;
}

.toggle-senha {
    position: absolute;
    right: 12px;
    width: 20px;
    height: 20px;
    cursor: pointer;
    opacity: 0.65;
    filter: invert(1);
    transition: opacity 0.2s ease;
}

.toggle-senha:hover {
    opacity: 1;
}

.btn-login {
    width: 100%;
    min-height: 50px;
    margin-top: 6px;
    border: none;
    border-radius: 14px;
    background: var(--red);
    color: white;
    font-family: inherit;
    font-size: 0.96rem;
    font-weight: 900;
    cursor: pointer;
    box-shadow: 0 18px 40px rgba(139,92,246,0.4);
    transition: 0.22s ease;
}

.btn-login:hover {
    transform: translateY(-2px);
    filter: brightness(1.1);
}

.mensagem.error {
    margin-top: 18px;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(255,107,129,0.12);
    border: 1px solid rgba(255,107,129,0.32);
    color: var(--danger);
    font-size: 0.86rem;
    font-weight: 700;
}

@media (max-width: 480px) {
    .login-card {
        padding: 32px 22px 26px;
        border-radius: 20px;
    }
}
</style>
</head>
<body>

<form method="POST" class="login-card novo_user">
    <div class="login-brandmark">
        <i class="fas fa-play"></i>
    </div>

    <h1>ObsidianPlay</h1>
    <span class="subtitle">Premium Vault Access</span>

    <div class="field">
        <label>User</label>
        <input type="text" name="entrada" placeholder="nome, email ou número" required>
    </div>

    <div class="field">
        <label for="senha">Password</label>
        <div class="password-wrap">
            <input type="password" name="senha" class="campo-senha" required>
            <img src="icones/olho_fechado1.png"
                 alt="Mostrar senha"
                 class="toggle-senha"
                 data-target="campo-senha">
        </div>
    </div>

    <button type="submit" class="btn-login">
        <i class="fas fa-right-to-bracket"></i> Login
    </button>

    <!-- <p style="margin-top:10px;">
        Não tem conta? <a href="cadastro.php">Clique aqui</a>
    </p> -->

    <?php if (!empty($erro)): ?>
        <p class="mensagem error"><?= $erro ?></p>
    <?php endif; ?>
</form>

</body>
</html>