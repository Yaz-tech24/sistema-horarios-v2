<?php
/* ============================================================
   Configuração central do projeto
   — Lê tudo de variáveis de ambiente (produção), com valores por
     omissão para desenvolvimento local no XAMPP — assim continua a
     funcionar sem configuração nenhuma em localhost.
   — Em produção, as variáveis vêm de um ficheiro .env na raiz do
     projeto (nunca commitado — ver .env.example e .gitignore).
   — BASE_URL: caminho do projeto a partir da raiz do domínio.
     '/sistema-horarios-fagrenm' no XAMPP local (subpasta de htdocs);
     '' (vazio) em produção, quando o domínio aponta direto para a
     pasta do projeto — ver .env.example.
   — $pdo: ligação à base de dados (PDO).
   ============================================================ */

// Carrega o .env da raiz do projeto, se existir (produção). Nunca
// sobrepõe uma variável já definida a sério no ambiente (Apache
// SetEnv, systemd, etc.) — só preenche o que faltar.
$ficheiroEnv = __DIR__ . '/../.env';
if (file_exists($ficheiroEnv)) {
    foreach (file($ficheiroEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) { continue; }
        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor, " \t\n\r\0\x0B\"'");
        if (getenv($chave) === false) { putenv("$chave=$valor"); }
    }
}

$producao = getenv('APP_ENV') === 'producao';

// Em produção nunca mostrar erros do PHP ao utilizador — podem revelar
// caminhos do servidor, queries, etc. Continuam sempre a ir para o
// log de erros do servidor, para se poder investigar.
error_reporting(E_ALL);
ini_set('display_errors', $producao ? '0' : '1');

if (!defined('BASE_URL')) {
    define('BASE_URL', getenv('BASE_URL') !== false ? getenv('BASE_URL') : '/sistema-horarios-fagrenm');
}

$host   = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$dbname = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'horarios_fagrenm';
$user   = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro de ligação à base de dados: ' . $e->getMessage());
    if ($producao) {
        http_response_code(500);
        die('O sistema está temporariamente indisponível. Tenta novamente daqui a pouco.');
    }
    die("Erro de ligação à base de dados: " . $e->getMessage());
}
