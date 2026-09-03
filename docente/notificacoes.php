<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';
exigirPerfil(['Docente']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    if (isset($_POST['lida'])) {
        $pdo->prepare("UPDATE notificacoes SET lida=1 WHERE id=? AND utilizador_id=?")
            ->execute([(int)$_POST['lida'], $_SESSION['utilizador_id']]);
    } elseif (isset($_POST['todas'])) {
        $pdo->prepare("UPDATE notificacoes SET lida=1 WHERE utilizador_id=?")
            ->execute([$_SESSION['utilizador_id']]);
    }
    header('Location: notificacoes.php'); exit;
}

$stmt = $pdo->prepare(
    "SELECT * FROM notificacoes WHERE utilizador_id=? ORDER BY data_envio DESC LIMIT 100");
$stmt->execute([$_SESSION['utilizador_id']]);
$notifs = $stmt->fetchAll();
$porLer = count(array_filter($notifs, fn($n) => !$n['lida']));

$pageTitle = 'Notificações';
$pageDescription = 'Avisos automáticos sobre publicações e alterações ao teu horário.';
$pageActions = $porLer ? '<form method="post">' . campoCSRF() . '<button type="submit" name="todas" value="1" class="btn btn-secondary">' . icone('check', 17) . ' Marcar todas como lidas</button></form>' : '';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$notifs): ?>
    <div class="estado-vazio">
        <p><strong>Sem notificações.</strong></p>
    </div>
<?php else: ?>
<section class="card">
    <div class="card-header"><div><h2>Todas as notificações</h2><p><?= $porLer ?> por ler</p></div></div>
    <div class="notification-list">
        <?php foreach ($notifs as $n): ?>
        <div class="notification-item <?= $n['lida'] ? '' : 'unread' ?>">
            <span class="stat-icon"><?= icone('bell') ?></span>
            <div class="notification-content">
                <h3><?= $n['lida'] ? 'Notificação' : 'Nova notificação' ?></h3>
                <p><?= htmlspecialchars($n['mensagem']) ?></p>
                <time><?= htmlspecialchars($n['data_envio']) ?></time>
            </div>
            <?php if (!$n['lida']): ?>
            <form method="post" style="align-self:center">
                <?= campoCSRF() ?>
                <input type="hidden" name="lida" value="<?= $n['id'] ?>">
                <button type="submit" class="link-acao">marcar lida</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
