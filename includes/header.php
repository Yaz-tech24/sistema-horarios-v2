<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Gestão de Horários — FAGRENM</title>
    <link rel="stylesheet" href="/assets/css/estilo.css">
</head>
<body>
<header>
    <h1>Sistema de Gestão de Horários</h1>
    <?php if (utilizadorAutenticado()): ?>
        <span>Olá, <?= htmlspecialchars($_SESSION['nome']) ?>
        (<?= $_SESSION['perfil'] ?>)</span>
        <a href="/auth/logout.php">Sair</a>
    <?php endif; ?>
</header>
<main>
