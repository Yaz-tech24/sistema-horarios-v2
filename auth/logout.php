<?php
require_once __DIR__ . '/../includes/auth.php';

// Terminar sessão é uma ação que altera estado — só por POST e com token
// CSRF, como todas as outras. Por GET, bastava uma imagem escondida noutra
// página (<img src=".../logout.php">) para terminar a sessão de quem a
// visitasse.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValido()) {
    header('Location: ' . BASE_URL . (utilizadorAutenticado() ? '/painel.php' : '/auth/login.php'));
    exit;
}

if (utilizadorAutenticado()) {
    registarAuditoria($pdo, 'logout');
}

// Apaga os dados no servidor E o cookie no browser — só session_destroy()
// deixava o cookie antigo a circular até o browser fechar.
$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires'  => time() - 42000,
    'path'     => $p['path'],
    'domain'   => $p['domain'],
    'secure'   => $p['secure'],
    'httponly' => $p['httponly'],
    'samesite' => $p['samesite'] ?: 'Lax',
]);
session_destroy();

header('Location: ' . BASE_URL . '/auth/login.php');
exit;
