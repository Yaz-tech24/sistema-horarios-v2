<?php
/* ============================================================
   Configuração central do projeto
   — BASE_URL: caminho do projeto dentro do htdocs.
     Se renomearem a pasta, mudar SÓ aqui.
   — $pdo: ligação à base de dados (PDO).
   ============================================================ */

if (!defined('BASE_URL')) {
    define('BASE_URL', '/sistema-horarios-fagrenm');
}

$host   = 'localhost';
$dbname = 'horarios_fagrenm';
$user   = 'root';
$pass   = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro de ligação à base de dados: " . $e->getMessage());
}
