<?php
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

// Página da própria conta — qualquer perfil pode mudar a SUA palavra-passe
// aqui. O Administrador continua a poder definir a de qualquer utilizador
// em admin/utilizadores.php (por exemplo, para quem se esqueceu da sua).
// A regra da palavra-passe é uma só para todo o sistema — ver
// validarPalavraPasse() em includes/seguranca.php.

// Quem está aqui a adivinhar a palavra-passe atual já tem a sessão (ex.:
// computador deixado destrancado) — sem limite, podia tentar à vontade até
// acertar e depois trocar o e-mail e a palavra-passe da vítima.
const CONTA_MAX_FALHAS = 5;
const CONTA_JANELA_MINUTOS = 15;

$erro = $ok = null;
$meuId = (int)$_SESSION['utilizador_id'];

$stmt = $pdo->prepare("SELECT nome, email, perfil, password_hash FROM utilizadores WHERE id = ?");
$stmt->execute([$meuId]);
$eu = $stmt->fetch();

function contaFalhasRecentes(PDO $pdo, int $utilizadorId): int {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM registo_auditoria
             WHERE acao = 'senha_atual_errada' AND utilizador_id = ?
               AND criado_em > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([$utilizadorId, CONTA_JANELA_MINUTOS]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $acao = postTexto('acao', 20);
    $demasiadasFalhas = contaFalhasRecentes($pdo, $meuId) >= CONTA_MAX_FALHAS;

    if ($demasiadasFalhas) {
        $erro = "Demasiadas tentativas com a palavra-passe atual errada. Tenta novamente daqui a "
              . CONTA_JANELA_MINUTOS . " minutos.";
    } elseif ($acao === 'email' && empty($_SESSION['deve_mudar_senha'])) {
        $atual     = postSenha('senha_atual_email');
        $novoEmail = postTexto('email_novo', 150);

        $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ? AND id != ?");
        $stmt->execute([$novoEmail, $meuId]);
        $emailEmUso = (bool)$stmt->fetch();

        if (!$eu || !password_verify($atual, $eu['password_hash'])) {
            // Pede sempre a password atual antes de mudar o e-mail — sem isto,
            // uma sessão roubada (ex.: computador destrancado) podia trocar o
            // e-mail de acesso sem a vítima dar por isso.
            registarAuditoria($pdo, 'senha_atual_errada', 'ao mudar o e-mail');
            $erro = "A palavra-passe está errada.";
        } elseif (!filter_var($novoEmail, FILTER_VALIDATE_EMAIL)) {
            $erro = "Este e-mail não parece válido.";
        } elseif ($emailEmUso) {
            $erro = "Já existe uma conta com este e-mail.";
        } else {
            $pdo->prepare("UPDATE utilizadores SET email = ? WHERE id = ?")
                ->execute([$novoEmail, $meuId]);
            registarAuditoria($pdo, 'email_alterado', $eu['email'] . ' → ' . $novoEmail);
            session_regenerate_id(true);
            definirFlash('ok', 'E-mail atualizado. Usa o novo da próxima vez que entrares.');
            header('Location: ' . BASE_URL . '/conta.php'); exit;
        }
    } else {
        $atual    = postSenha('senha_atual');
        $nova     = postSenha('senha_nova');
        $confirma = postSenha('senha_confirma');

        if (!$eu || !password_verify($atual, $eu['password_hash'])) {
            registarAuditoria($pdo, 'senha_atual_errada', 'ao mudar a palavra-passe');
            $erro = "A palavra-passe atual está errada.";
        } elseif ($erroSenha = validarPalavraPasse($nova, $eu['email'])) {
            $erro = $erroSenha;
        } elseif ($nova !== $confirma) {
            $erro = "A confirmação não coincide com a nova palavra-passe.";
        } elseif (password_verify($nova, $eu['password_hash'])) {
            $erro = "A nova palavra-passe tem de ser diferente da atual.";
        } else {
            $novoHash = password_hash($nova, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE utilizadores SET password_hash = ? WHERE id = ?")
                ->execute([$novoHash, $meuId]);
            definirMudancaObrigatoria($pdo, $meuId, false);
            registarAuditoria($pdo, 'senha_alterada', 'pelo próprio');

            // Novo ID de sessão depois de mudar credenciais (evita fixação
            // de sessão) e nova "marca" da palavra-passe: esta sessão
            // continua válida, todas as outras desta conta — noutros
            // browsers ou dispositivos — terminam no pedido seguinte.
            session_regenerate_id(true);
            $_SESSION['senha_marca'] = marcaDaSenha($novoHash);
            $_SESSION['deve_mudar_senha'] = false;

            definirFlash('ok', 'Palavra-passe alterada. As outras sessões desta conta foram terminadas.');
            header('Location: ' . BASE_URL . '/conta.php'); exit;
        }
    }
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE id = ?");
$stmt->execute([$meuId]);
$eu = $stmt->fetch();
$obrigadoAMudar = !empty($_SESSION['deve_mudar_senha']);

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
                        <label for="senha_nova">Nova palavra-passe (mínimo <?= SENHA_MIN ?> caracteres)</label>
                        <input class="form-control" type="password" id="senha_nova" name="senha_nova"
                               minlength="<?= SENHA_MIN ?>" maxlength="<?= SENHA_MAX_BYTES ?>" required autocomplete="new-password">
                    </div>
                    <div class="campo">
                        <label for="senha_confirma">Confirmar nova</label>
                        <input class="form-control" type="password" id="senha_confirma" name="senha_confirma"
                               minlength="<?= SENHA_MIN ?>" maxlength="<?= SENHA_MAX_BYTES ?>" required autocomplete="new-password">
                    </div>
                    <div class="campo" style="grid-column:1/-1">
                        <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem">Guardar nova palavra-passe</button>
                    </div>
                </form>
            </div>
        </section>

        <?php if (!$obrigadoAMudar): ?>
        <section class="card">
            <div class="card-header"><div><h2>Alterar e-mail</h2><p>Também pede a palavra-passe atual, pelo mesmo motivo.</p></div></div>
            <div class="card-body">
                <form method="post" class="form-row">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="acao" value="email">
                    <div class="campo" style="grid-column:1/-1">
                        <label for="email_novo">Novo e-mail</label>
                        <input class="form-control" type="email" id="email_novo" name="email_novo" maxlength="150"
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
        <?php endif; ?>
    </div>

    <aside class="card">
        <div class="card-header"><div><h2>Os teus dados</h2></div></div>
        <div class="card-body">
            <div class="field-grid" style="grid-template-columns:1fr">
                <div class="field-chip"><b><?= htmlspecialchars($eu['nome'] ?? '') ?></b>Nome</div>
                <div class="field-chip"><b><?= htmlspecialchars($eu['email'] ?? '') ?></b>E-mail de acesso</div>
                <div class="field-chip"><b><?= htmlspecialchars($eu['perfil'] ?? '') ?></b>Perfil</div>
                <?php if (!empty($eu['ultimo_login'])): ?>
                <div class="field-chip"><b><?= htmlspecialchars(date('d/m/Y H:i', strtotime($eu['ultimo_login']))) ?></b>Última entrada</div>
                <?php endif; ?>
            </div>
            <p style="margin:14px 0 0;color:var(--ink-soft);font-size:.82rem">
                A sessão termina sozinha ao fim de <?= SESSAO_INATIVIDADE_MINUTOS ?> minutos sem atividade.
                Num computador partilhado, usa sempre <strong>Terminar sessão</strong> quando acabares.
            </p>
            <p style="margin:14px 0 0;color:var(--ink-soft);font-size:.82rem">
                O e-mail e a palavra-passe também dão para alterar aqui ao lado.
                O nome e o perfil só o Administrador muda, em <strong>Utilizadores</strong>.
            </p>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
