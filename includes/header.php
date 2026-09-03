<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';
exigirLogin();

$perfilAtual = $_SESSION['perfil'];
$pageTitle = $pageTitle ?? 'Painel';
$pageDescription = $pageDescription ?? '';
$pageActions = $pageActions ?? '';

$menus = [
    'Administrador' => [
        ['painel.php', 'grid', 'Visão geral'],
        ['admin/cursos.php', 'book', 'Cursos'],
        ['admin/disciplinas.php', 'layers', 'Disciplinas'],
        ['admin/docentes.php', 'users', 'Docentes'],
        ['admin/salas.php', 'door', 'Salas'],
        ['admin/turmas.php', 'calendar', 'Turmas'],
        ['admin/utilizadores.php', 'shield', 'Utilizadores'],
        ['relatorios/carga_docente.php', 'chart', 'Carga docente'],
        ['relatorios/ocupacao_salas.php', 'building', 'Ocupação de salas'],
    ],
    'Coordenador' => [
        ['painel.php', 'grid', 'Visão geral'],
        ['coordenador/escolher_curso.php', 'book', 'Os meus cursos'],
        ['coordenador/editor_horario.php', 'calendar', 'Editor de horário'],
        ['coordenador/conflitos.php', 'alert', 'Conflitos'],
        ['coordenador/publicar.php', 'send', 'Publicar horário'],
        ['coordenador/historico.php', 'history', 'Histórico'],
        ['relatorios/carga_docente.php', 'chart', 'Carga docente'],
        ['relatorios/ocupacao_salas.php', 'building', 'Ocupação de salas'],
        ['relatorios/exportar.php', 'download', 'Exportar'],
    ],
    'Docente' => [
        ['painel.php', 'grid', 'Visão geral'],
        ['docente/meu_horario.php', 'calendar', 'Meu horário'],
        ['docente/notificacoes.php', 'bell', 'Notificações'],
    ],
];

$caminhoAtual = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$baseAtual = ltrim(BASE_URL, '/');
if ($baseAtual !== '' && str_starts_with($caminhoAtual, $baseAtual)) {
    $caminhoAtual = ltrim(substr($caminhoAtual, strlen($baseAtual)), '/');
}

function navAtivo(string $caminho, string $atual): string
{
    return $caminho === $atual ? 'active' : '';
}

$iniciais = mb_strtoupper(mb_substr($_SESSION['nome'] ?? 'U', 0, 1));
$notificacoesPorLer = 0;
if ($perfilAtual === 'Docente') {
    $stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE utilizador_id = ? AND lida = 0");
    $stmtNotif->execute([$_SESSION['utilizador_id']]);
    $notificacoesPorLer = (int)$stmtNotif->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/ucm_logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilo.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilo.css') ?>">
</head>
<body>
<div class="app-shell">
    <div class="sidebar-overlay" data-sidebar-close></div>
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= BASE_URL ?>/painel.php">
            <span class="brand-mark"><img src="<?= BASE_URL ?>/assets/img/ucm_logo.png" alt=""></span>
            <span><strong>Gestão de Horários</strong><small>FAGRENM · UCM</small></span>
        </a>

        <div class="profile-mini">
            <span class="avatar avatar-soft"><?= htmlspecialchars($iniciais) ?></span>
            <span><strong><?= htmlspecialchars($_SESSION['nome']) ?></strong><small><?= htmlspecialchars($perfilAtual) ?></small></span>
        </div>

        <nav class="main-nav" aria-label="Navegação principal">
            <span class="nav-label">MENU PRINCIPAL</span>
            <?php foreach ($menus[$perfilAtual] ?? [] as [$caminho, $ic, $rotulo]): ?>
                <a class="nav-item <?= navAtivo($caminho, $caminhoAtual) ?>" href="<?= BASE_URL . '/' . $caminho ?>">
                    <?= icone($ic) ?><span><?= htmlspecialchars($rotulo) ?></span>
                    <?php if ($caminho === 'docente/notificacoes.php' && $notificacoesPorLer > 0): ?><em><?= $notificacoesPorLer ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <a class="nav-item logout-link" href="<?= BASE_URL ?>/auth/logout.php"><?= icone('logout') ?><span>Terminar sessão</span></a>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <button class="icon-button menu-button" type="button" aria-label="Abrir menu" data-sidebar-open><?= icone('menu') ?></button>
            <div class="breadcrumb"><span>Sistema académico</span><i>/</i><strong><?= htmlspecialchars($pageTitle) ?></strong></div>
            <div class="topbar-actions">
                <?php if ($perfilAtual === 'Docente'): ?>
                    <a class="icon-button" href="<?= BASE_URL ?>/docente/notificacoes.php" aria-label="Notificações"><?= icone('bell') ?><?php if ($notificacoesPorLer > 0): ?><span class="notification-dot"></span><?php endif; ?></a>
                <?php endif; ?>
                <div class="topbar-user"><span class="avatar"><?= htmlspecialchars($iniciais) ?></span><span><strong><?= htmlspecialchars($_SESSION['nome']) ?></strong><small><?= htmlspecialchars($perfilAtual) ?></small></span></div>
            </div>
        </header>
        <section class="page-content">
            <div class="page-heading">
                <div><h1><?= htmlspecialchars($pageTitle) ?></h1><?php if ($pageDescription): ?><p><?= htmlspecialchars($pageDescription) ?></p><?php endif; ?></div>
                <?php if ($pageActions): ?><div class="page-actions"><?= $pageActions ?></div><?php endif; ?>
            </div>
