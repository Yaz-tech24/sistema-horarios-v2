<?php
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

// Página da própria conta — qualquer perfil pode mudar a SUA palavra-passe
// aqui. O Administrador continua a poder definir a de qualquer utilizador
// em admin/utilizadores.php (por exemplo, para quem se esqueceu da sua).
const CONTA_MIN_PASSWORD = 6;

$erro = $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'email') {
    validarCSRF();

    $atual    = $_POST['senha_atual_email'] ?? '';
    $novoEmail = trim($_POST['email_novo'] ?? '');

    $stmt = $pdo->prepare("SELECT password_hash FROM utilizadores WHERE id = ?");
    $stmt->execute([$_SESSION['utilizador_id']]);
    $hashAtual = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ? AND id != ?");
    $stmt->execute([$novoEmail, $_SESSION['utilizador_id']]);
    $emailEmUso = (bool)$stmt->fetch();

    if (!$hashAtual || !password_verify($atual, $hashAtual)) {
        // Pede sempre a password atual antes de mudar o e-mail — sem isto,
        // uma sessão roubada (ex.: computador destrancado) podia trocar o
        // e-mail de acesso sem a vítima dar por isso.
        $erro = "A palavra-passe está errada.";
    } elseif (!filter_var($novoEmail, FILTER_VALIDATE_EMAIL)) {
        $erro = "Este e-mail não parece válido.";
    } elseif ($emailEmUso) {
        $erro = "Já existe uma conta com este e-mail.";
    } else {
        $pdo->prepare("UPDATE utilizadores SET email = ? WHERE id = ?")
            ->execute([$novoEmail, $_SESSION['utilizador_id']]);
        definirFlash('ok', 'E-mail atualizado. Usa o novo da próxima vez que entrares.');
        header('Location: conta.php'); exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $atual    = $_POST['senha_atual'] ?? '';
    $nova     = $_POST['senha_nova'] ?? '';
    $confirma = $_POST['senha_confirma'] ?? '';

    $stmt = $pdo->prepare("SELECT password_hash FROM utilizadores WHERE id = ?");
    $stmt->execute([$_SESSION['utilizador_id']]);
    $hashAtual = $stmt->fetchColumn();

    if (!$hashAtual || !password_verify($atual, $hashAtual)) {
        // Mensagem propositadamente igual à do login: não confirma nem
        // desmente nada a quem esteja a tentar adivinhar.
        $erro = "A palavra-passe atual está errada.";
    } elseif (strlen($nova) < CONTA_MIN_PASSWORD) {
        $erro = "A nova palavra-passe tem de ter pelo menos " . CONTA_MIN_PASSWORD . " caracteres.";
    } elseif ($nova !== $confirma) {
        $erro = "A confirmação não coincide com a nova palavra-passe.";
    } elseif (password_verify($nova, $hashAtual)) {
        $erro = "A nova palavra-passe tem de ser diferente da atual.";
    } else {
        $pdo->prepare("UPDATE utilizadores SET password_hash = ? WHERE id = ?")
            ->execute([password_hash($nova, PASSWORD_DEFAULT), $_SESSION['utilizador_id']]);

        // Novo ID de sessão depois de mudar credenciais — mesma precaução
        // que no login (evita fixação de sessão).
        session_regenerate_id(true);

        definirFlash('ok', 'Palavra-passe alterada. Usa a nova da próxima vez que entrares.');
        header('Location: conta.php'); exit;
    }
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$stmt = $pdo->prepare("SELECT nome, email, perfil FROM utilizadores WHERE id = ?");
$stmt->execute([$_SESSION['utilizador_id']]);
$eu = $stmt->fetch();

$pageTitle = 'A minha conta';
$pageDescription = 'Os teus dados de acesso e a alteração da palavra-passe.';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="grid two-column">
    <div style="display:flex;flex-direction:column;gap:20px">
        <section class="card">
            <div class="card-header"><div><h2>Alterar palavra-passe</h2><p>Pede a atual por segurança, mesmo já tendo sessão iniciada.</p></div></div>
            <div class="card-body">
                <form method="post" class="form-row">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="acao" value="senha">
                    <div class="campo" style="grid-column:1/-1">
                        <label for="senha_atual">Palavra-passe atual</label>
                        <input class="form-control" type="password" id="senha_atual" name="senha_atual" required autocomplete="current-password">
                    </div>
                    <div class="campo">
                        <label for="senha_nova">Nova palavra-passe</label>
                        <input class="form-control" type="password" id="senha_nova" name="senha_nova"
                               minlength="<?= CONTA_MIN_PASSWORD ?>" required autocomplete="new-password">
                    </div>
                    <div class="campo">
                        <label for="senha_confirma">Confirmar nova</label>
                        <input class="form-control" type="password" id="senha_confirma" name="senha_confirma"
                               minlength="<?= CONTA_MIN_PASSWORD ?>" required autocomplete="new-password">
                    </div>
                    <div class="campo" style="grid-column:1/-1">
                        <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem">Guardar nova palavra-passe</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><div><h2>Alterar e-mail</h2><p>Também pede a palavra-passe atual, pelo mesmo motivo.</p></div></div>
            <div class="card-body">
                <form method="post" class="form-row">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="acao" value="email">
                    <div class="campo" style="grid-column:1/-1">
                        <label for="email_novo">Novo e-mail</label>
                        <input class="form-control" type="email" id="email_novo" name="email_novo"
                               value="<?= htmlspecialchars($eu['email'] ?? '') ?>" required>
                    </div>
                    <div class="campo" style="grid-column:1/-1">
                        <label for="senha_atual_email">Palavra-passe atual</label>
                        <input class="form-control" type="password" id="senha_atual_email" name="senha_atual_email" required autocomplete="current-password">
                    </div>
                    <div class="campo" style="grid-column:1/-1">
                        <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem">Guardar novo e-mail</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <aside class="card">
        <div class="card-header"><div><h2>Os teus dados</h2></div></div>
        <div class="card-body">
            <div class="field-grid" style="grid-template-columns:1fr">
                <div class="field-chip"><b><?= htmlspecialchars($eu['nome'] ?? '') ?></b>Nome</div>
                <div class="field-chip"><b><?= htmlspecialchars($eu['email'] ?? '') ?></b>E-mail de acesso</div>
                <div class="field-chip"><b><?= htmlspecialchars($eu['perfil'] ?? '') ?></b>Perfil</div>
            </div>
            <p style="margin:14px 0 0;color:var(--ink-soft);font-size:.82rem">
                O e-mail e a palavra-passe também dão para alterar aqui ao lado.
                O nome e o perfil só o Administrador muda, em <strong>Utilizadores</strong>.
            </p>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
