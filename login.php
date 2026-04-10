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
        // Busca flexível compatível com PostgreSQL
        $stmt = $conexao->prepare("SELECT * FROM usuario WHERE nome ILIKE ? OR email ILIKE ? LIMIT 1");
        $stmt->execute([$entrada, $entrada]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            // Verifica a senha usando o hash da imagem (bcrypt)
            if (password_verify($senha, $usuario['senha_hash'])) {
                $_SESSION['usuario'] = $usuario;

                // Forçar alteração se for a primeira senha
                // NOTA: Certifique-se de que a coluna 'primeira_senha' existe no banco
                if (isset($usuario['primeira_senha']) && (int)$usuario['primeira_senha'] === 1) {
                    $_SESSION['id_usuario'] = $usuario['id_usuario'];
                    header("Location: alterar_senha.php?primeiro=1");
                    exit;
                }

                // Redirecionamento
                if (isset($_SESSION['url_destino'])) {
                    $urlDestino = $_SESSION['url_destino'];
                    unset($_SESSION['url_destino']);
                    header("Location: " . $urlDestino);
                } else {
                    // Ajuste o nome da coluna idperfil se necessário
                    $perfil = $usuario['idperfil'] ?? $usuario['id_perfil'] ?? 0;
                    if ((int)$perfil === 1) {
                        header("Location: dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                }
                exit;

            } else {
                $erro = "Senha incorreta.";
            }
        } else {
            // Se cair aqui, o usuário realmente não foi encontrado com o termo digitado
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
    <title>Login</title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="js/mostrarSenha.js"></script>
    <style>
        .logo {
            font-size: 1.5em;
            font-weight: bold;
            color: #d32f2f;
        }
    </style>
</head>
<body>

<form method="POST" style="max-width:400px; margin:50px auto 0; text-align:center;" class="novo_user">

    <h3>Login</h3>

    <img src="icones/logo.png" alt="Logo" style="display:block; margin:10px auto; max-width:150px;">

    <div style="text-align:left; margin-top:10px;">
        <label>Usuário:</label>
        <input type="text" name="entrada" placeholder="nome, email ou número" required>
    </div>

    <label for="senha" style="display:block; text-align:left; margin-top:10px;">Senha:</label>
    <div style="position:relative; display:flex; align-items:center;">
        <input type="password" name="senha" class="campo-senha" required
               style="width:100%; padding-right:35px; box-sizing:border-box;">
        <img src="icones/olho_fechado1.png"
             alt="Mostrar senha"
             class="toggle-senha"
             data-target="campo-senha"
             style="position:absolute; right:10px; cursor:pointer; width:22px; opacity:0.8;">
    </div>

    <button type="submit" style="margin-top:10px;">Entrar</button>

    <p style="margin-top:10px;">
        Não tem conta? <a href="cadastro.php">Clique aqui</a>
    </p>

    <?php if (!empty($erro)): ?>
        <p class="mensagem error" style="text-align:center;"><?= $erro ?></p>
    <?php endif; ?>

</form>

</body>
</html>