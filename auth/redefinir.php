<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/layout_auth.php';

/* "Esqueci-me da palavra-passe" — passo 2: quem abre o link recebido por
   e-mail (auth/recuperar.php) define aqui a nova palavra-passe. */

// O token vem no URL: nada dele pode seguir para outro site no cabeçalho
// Referer (a página carrega tipos de letra de fora).
header('Referrer-Policy: no-referrer');

if (utilizadorAutenticado()) {
    header('Location: ' . BASE_URL . '/painel.php');
    exit;
}

$tokenRecebido = $_POST['token'] ?? $_GET['token'] ?? '';
$token = is_string($tokenRecebido) && preg_match('/^[a-f0-9]{64}$/', $tokenRecebido) ? $tokenRecebido : '';

/* O pedido de recuperação a que este token corresponde — só se ainda não
   foi usado, não expirou e a conta continua ativa. */
function pedidoDeRecuperacao(PDO $pdo, string $token): ?array {
    if ($token === '') { return null; }
    try {
        $stmt = $pdo->prepare(
            "SELECT r.id, r.utilizador_id, u.email, u.nome
             FROM recuperacoes_senha r
             JOIN utilizadores u ON u.id = r.utilizador_id
             WHERE r.token_hash = ? AND r.usado_em IS NULL AND r.expira_em > NOW() AND u.ativo = 1");
        $stmt->execute([hash('sha256', $token)]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('Redefinição de palavra-passe indisponível: ' . $e->getMessage());
        return null;
    }
}

$pedido = pedidoDeRecuperacao($pdo, $token);
$erro = null;

if ($pedido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova     = postSenha('senha_nova');
    $confirma = postSenha('senha_confirma');

    if (!csrfValido()) {
        $erro = "Esta página esteve aberta demasiado tempo. Volta a preencher e a enviar.";
    } elseif ($erroSenha = validarPalavraPasse($nova, $pedido['email'])) {
        $erro = $erroSenha;
    } elseif ($nova !== $confirma) {
        $erro = "A confirmação não coincide com a nova palavra-passe.";
    } else {
        $pdo->beginTransaction();
        try {
            // "AND usado_em IS NULL" + rowCount: se o mesmo link for enviado
            // duas vezes ao mesmo tempo, só um dos pedidos passa daqui.
            $marcar = $pdo->prepare("UPDATE recuperacoes_senha SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL");
            $marcar->execute([$pedido['id']]);
            if ($marcar->rowCount() !== 1) { throw new RuntimeException('link já utilizado'); }

            $pdo->prepare(
                "UPDATE utilizadores SET password_hash = ?, tentativas_falhadas = 0, bloqueado_ate = NULL WHERE id = ?")
                ->execute([password_hash($nova, PASSWORD_DEFAULT), $pedido['utilizador_id']]);
            // Qualquer outro link pendente desta conta deixa de servir.
            $pdo->prepare("UPDATE recuperacoes_senha SET usado_em = NOW() WHERE utilizador_id = ? AND usado_em IS NULL")
                ->execute([$pedido['utilizador_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Redefinição de palavra-passe falhou: ' . $e->getMessage());
            $pedido = null; // cai na mensagem de "link inválido" abaixo
        }

        if ($pedido) {
            definirMudancaObrigatoria($pdo, (int)$pedido['utilizador_id'], false);
            registarAuditoria($pdo, 'senha_redefinida', 'por link de recuperação', (int)$pedido['utilizador_id']);
            // A palavra-passe mudou → as sessões que existissem com a antiga
            // terminam sozinhas (ver validarSessaoAtual() em includes/auth.php).
            $_SESSION['aviso_login'] = 'Palavra-passe definida. Já podes entrar com a nova.';
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit;
        }
    }
}

authInicio('Nova palavra-passe', 'RECUPERAÇÃO', 'Definir nova palavra-passe',
    $pedido ? 'Conta: ' . $pedido['email'] : 'Este link já não pode ser usado.',
    ['Indica o teu e-mail', 'Abre o link recebido', 'Define a nova palavra-passe']);
?>
            <?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

            <?php if (!$pedido): ?>
                <div class="alert alert-error">O link é inválido, já foi usado ou expirou.
                    <?php if (recuperacaoDisponivel()): ?>
                        <a href="<?= BASE_URL ?>/auth/recuperar.php" style="font-weight:600;text-decoration:underline">Pede um novo</a>.
                    <?php else: ?>
                        Contacta o Administrador do sistema.
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <form class="login-form" method="post" novalidate>
                <?= campoCSRF() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label for="senha_nova">Nova palavra-passe (mínimo <?= SENHA_MIN ?> caracteres)</label>
                    <div class="input-with-icon">
                        <?= icone('lock') ?>
                        <input class="form-control" type="password" id="senha_nova" name="senha_nova"
                               minlength="<?= SENHA_MIN ?>" maxlength="<?= SENHA_MAX_BYTES ?>" placeholder="••••••••"
                               autocomplete="new-password" required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="senha_confirma">Confirmar nova palavra-passe</label>
                    <div class="input-with-icon">
                        <?= icone('lock') ?>
                        <input class="form-control" type="password" id="senha_confirma" name="senha_confirma"
                               minlength="<?= SENHA_MIN ?>" maxlength="<?= SENHA_MAX_BYTES ?>" placeholder="••••••••"
                               autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary login-submit">Guardar palavra-passe <?= icone('chevron', 17) ?></button>
            </form>
            <?php endif; ?>
<?php authFim(); ?>
