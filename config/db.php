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
ini_set('log_errors', '1');

// Exceções que nenhuma página apanhe: log completo no servidor, frase
// neutra no browser (ver includes/seguranca.php).
require_once __DIR__ . '/../includes/seguranca.php';
instalarTratamentoDeErros($producao);

// Fuso horário único para o PHP e para a ligação MySQL (mais abaixo).
// Sem isto cada um usava o seu por omissão — e como o bloqueio do login
// grava a hora com o NOW() do MySQL mas era comparado com o time() do
// PHP, um desfasamento entre os dois tornava o bloqueio de 5 minutos em
// horas (ou em nada). Também é o que faz as datas do histórico e das
// notificações aparecerem na hora de Moçambique e não em UTC.
$fuso = getenv('APP_TIMEZONE') ?: 'Africa/Maputo';
if (!@date_default_timezone_set($fuso)) { date_default_timezone_set('Africa/Maputo'); }

if (!defined('BASE_URL')) {
    // Sem barra final e só caracteres de caminho — vai para dentro de
    // href/Location em todas as páginas.
    $base = getenv('BASE_URL') !== false ? getenv('BASE_URL') : '/sistema-horarios-fagrenm';
    $base = rtrim(preg_replace('#[^A-Za-z0-9/_\-.~]#', '', $base), '/');
    define('BASE_URL', $base);
}

$host   = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$porta  = getenv('DB_PORT') !== false ? (int)getenv('DB_PORT') : 0;
$dbname = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'horarios_fagrenm';
$user   = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    $opcoes = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    // Uma instrução por chamada: mesmo que alguma query viesse a ser
    // construída com input do utilizador, não dava para lhe colar um
    // segundo comando ("; DROP TABLE ...").
    // (Pdo\Mysql existe a partir do PHP 8.4, onde a constante antiga em
    // PDO:: passou a estar marcada como obsoleta.)
    if (class_exists('Pdo\Mysql')) {
        $opcoes[\Pdo\Mysql::ATTR_MULTI_STATEMENTS] = false;
    } elseif (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
        $opcoes[constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS')] = false;
    }
    $pdo = new PDO(
        "mysql:host=$host" . ($porta ? ";port=$porta" : '') . ";dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        $opcoes
    );
    // date('P') = desvio do fuso do PHP (ex.: "+02:00") — funciona mesmo
    // que o MySQL não tenha as tabelas de fusos horários carregadas.
    $pdo->exec("SET time_zone = " . $pdo->quote(date('P')));
} catch (PDOException $e) {
    error_log('Erro de ligação à base de dados: ' . $e->getMessage());
    if ($producao) {
        http_response_code(500);
        die('O sistema está temporariamente indisponível. Tenta novamente daqui a pouco.');
    }
    die("Erro de ligação à base de dados: " . $e->getMessage());
}
