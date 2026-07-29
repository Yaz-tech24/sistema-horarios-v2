<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carrega a configuração (BASE_URL + ligação $pdo).
// require_once garante que só corre uma vez por pedido.
require_once __DIR__ . '/../config/db.php';

function utilizadorAutenticado() {
    return isset($_SESSION['utilizador_id']);
}

function exigirLogin() {
    if (!utilizadorAutenticado()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function exigirPerfil($perfisPermitidos) {
    exigirLogin();
    if (!in_array($_SESSION['perfil'], $perfisPermitidos)) {
        die("Acesso negado: esta página não está disponível para o teu perfil.");
    }
}
