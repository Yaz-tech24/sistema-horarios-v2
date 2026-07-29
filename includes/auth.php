<?php
session_start();

function utilizadorAutenticado() {
    return isset($_SESSION['utilizador_id']);
}

function exigirLogin() {
    if (!utilizadorAutenticado()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function exigirPerfil($perfisPermitidos) {
    exigirLogin();
    if (!in_array($_SESSION['perfil'], $perfisPermitidos)) {
        die("Acesso negado: esta página não está disponível para o teu perfil.");
    }
}
