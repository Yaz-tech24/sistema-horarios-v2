<?php
require '../includes/auth.php';

// Limpa todas as variáveis de sessão e termina a sessão
$_SESSION = [];
session_destroy();

header('Location: /auth/login.php');
exit;
