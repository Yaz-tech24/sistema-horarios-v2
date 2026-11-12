<?php
/* Moldura das páginas públicas de conta (recuperar / redefinir a
   palavra-passe): o mesmo ecrã dividido do login — painel institucional à
   esquerda, caixa do formulário à direita. authInicio() abre até dentro
   da caixa; authFim() fecha tudo. Todo o texto recebido é escapado aqui. */
require_once __DIR__ . '/icons.php';

function authInicio(string $tituloAba, string $etiqueta, string $titulo, string $subtitulo, array $passos): void {
    ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($tituloAba) ?> — Sistema de Gestão de Horários · FAGRENM</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/ucm_logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilo.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilo.css') ?>">
</head>
<body>
<main class="login-page">
    <section class="login-visual" data-year="<?= date('Y') ?>">
        <div class="login-brand">
            <span class="brand-mark"><img src="<?= BASE_URL ?>/assets/img/ucm_logo.png" alt=""></span>
            <span><strong>Gestão de Horários</strong><small>Universidade Católica de Moçambique</small></span>
        </div>
        <div class="login-copy">
            <p class="eyebrow">FAGRENM · TETE</p>
            <h1>Recuperar<br>o acesso<br><span>em três passos.</span></h1>
            <p>O link de recuperação chega ao e-mail da tua conta e só pode ser usado uma vez.</p>
            <div class="login-features">
                <?php foreach ($passos as $i => $passo): ?>
                <span class="login-feature"><b><?= sprintf('%02d', $i + 1) ?></b> <?= htmlspecialchars($passo) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <small class="login-credit">UCM · Faculdade de Gestão de Recursos Florestais</small>
    </section>
    <section class="login-panel">
        <div class="login-box">
            <span class="login-number"><?= htmlspecialchars($etiqueta) ?></span>
            <h2><?= htmlspecialchars($titulo) ?></h2>
            <p><?= htmlspecialchars($subtitulo) ?></p>
    <?php
}

function authFim(): void {
    ?>
            <p class="login-nota">
                <a href="<?= BASE_URL ?>/auth/login.php" style="color:var(--cobalt);font-weight:600">Voltar à entrada</a>
            </p>
        </div>
    </section>
</main>
</body>
</html>
    <?php
}
