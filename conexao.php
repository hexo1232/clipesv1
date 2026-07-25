<?php
$dbUrl = getenv('DATABASE_URL');

if ($dbUrl) {
    $p = parse_url($dbUrl);
    
    // Remove o pooler do host se existir — transações PDO não funcionam com PgBouncer
    $host   = str_replace('-pooler', '', $p['host']);
    $port   = $p['port'] ?? 5432;
    $user   = $p['user'];
    $pass   = $p['pass'];
    $dbname = ltrim($p['path'], '/');
} else {
    $host   = "localhost";
    $port   = 5432;
    $user   = "postgres";
    $pass   = "sua_senha_local";
    $dbname = "clipesv1";
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    $conexao = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Erro na conexão com o banco Neon: " . $e->getMessage());
}
?>