<?php
// Tenta pegar a URL do Render, se não existir (local), usa uma string padrão
$dbUrl = getenv('DATABASE_URL');

if ($dbUrl) {
    // No Render: Extrai as informações da URL do Neon
    $p = parse_url($dbUrl);
    
    $host     = $p['host'];
    $port     = $p['port'] ?? 5432;
    $user     = $p['user'];
    $pass     = $p['pass'];
    $dbname   = ltrim($p['path'], '/');
} else {
    // Configurações Locais (Caso você instale o Postgres no seu PC)
    $host     = "localhost";
    $port     = 5432;
    $user     = "postgres";
    $pass     = "sua_senha_local";
    $dbname   = "clipesv1";
}

try {
    // String de conexão para PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    $conexao = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Como o seu index.php usa o objeto $conexao como se fosse mysqli em alguns pontos,
    // o PDO vai permitir que você execute queries, mas a sintaxe de busca mudará um pouco.

} catch (PDOException $e) {
    die("Erro na conexão com o banco Neon: " . $e->getMessage());
}
?>