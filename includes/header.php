<?php
require_once __DIR__ . '/auth.php';

$perfilAtual = $_SESSION['perfil'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema de Gestão de Horários — FAGRENM</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilo.css">
</head>
<body>
<header class="app-header">
    <div class="app-header-inner">
        <a class="app-marca" href="<?= BASE_URL ?>/painel.php">
            <span class="mini-monograma">FG</span>
            <span class="nome">
                Gestão de Horários
                <small>FAGRENM · UCM</small>
            </span>
        </a>

        <?php if (utilizadorAutenticado()): ?>
            <nav class="app-nav">
                <?php if ($perfilAtual === 'Administrador'): ?>
                    <a href="<?= BASE_URL ?>/admin/cursos.php">Cursos</a>
                    <a href="<?= BASE_URL ?>/admin/disciplinas.php">Disciplinas</a>
                    <a href="<?= BASE_URL ?>/admin/docentes.php">Docentes</a>
                    <a href="<?= BASE_URL ?>/admin/salas.php">Salas</a>
                    <a href="<?= BASE_URL ?>/admin/turmas.php">Turmas</a>
                <?php elseif ($perfilAtual === 'Coordenador'): ?>
                    <a href="<?= BASE_URL ?>/coordenador/escolher_curso.php">Curso</a>
                    <a href="<?= BASE_URL ?>/coordenador/editor_horario.php">Editor</a>
                    <a href="<?= BASE_URL ?>/coordenador/conflitos.php">Conflitos</a>
                    <a href="<?= BASE_URL ?>/coordenador/publicar.php">Publicar</a>
                <?php elseif ($perfilAtual === 'Docente'): ?>
                    <a href="<?= BASE_URL ?>/docente/meu_horario.php">Meu Horário</a>
                    <a href="<?= BASE_URL ?>/docente/notificacoes.php">Notificações</a>
                <?php endif; ?>
            </nav>

            <div class="app-utilizador">
                <span>
                    <span class="perfil-etiqueta"><?= htmlspecialchars($perfilAtual) ?></span>
                    <?= htmlspecialchars($_SESSION['nome']) ?>
                </span>
                <a href="<?= BASE_URL ?>/auth/logout.php">Sair</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="regua"></div>
</header>
<main class="app-main">
