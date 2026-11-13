<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/layout_auth.php';

/* "Esqueci-me da palavra-passe" — passo 1: pedir o link.
   Só existe com SMTP configurado e um endereço público de confiança
   (recuperacaoDisponivel()); sem isso, continua a ser o Administrador a
   definir uma palavra-passe nova em Utilizadores, como sempre foi. */

if (utilizadorAutenticado()) {
    header('Location: ' . BASE_URL . '/painel.php');
    exit;
}
if (!recuperacaoDisponivel()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

const RECUPERACAO_VALIDADE_MINUTOS = 60;
const RECUPERACAO_MAX_POR_CONTA_HORA = 3;
const RECUPERACAO_MAX_POR_IP = 5; // por IP_JANELA_MINUTOS

$erro = $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = postTexto('email', 150);

    if (!csrfValido()) {
        $erro = "Esta página esteve aberta demasiado tempo. Tenta novamente.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Este e-mail não parece válido.";
    } elseif (acoesRecentesDoIp($pdo, ['recuperacao_pedida'], IP_JANELA_MINUTOS) >= RECUPERACAO_MAX_POR_IP) {
        $erro = "Demasiados pedidos a partir desta rede. Tenta novamente daqui a alguns minutos.";
    } else {
        registarAuditoria($pdo, 'recuperacao_pedida', $email);

        try {
            $stmt = $pdo->prepare("SELECT id, nome, email FROM utilizadores WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $conta = $stmt->fetch();

            // Links usados ou expirados há mais de 30 dias já não servem para
            // nada — a tabela não cresce para sempre.
            $pdo->exec("DELETE FROM recuperacoes_senha WHERE expira_em < DATE_SUB(NOW(), INTERVAL 30 DAY)");

            if ($conta) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM recuperacoes_senha
                     WHERE utilizador_id = ? AND criado_em > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                $stmt->execute([$conta['id']]);

                if ((int)$stmt->fetchColumn() < RECUPERACAO_MAX_POR_CONTA_HORA) {
                    // O token só existe em claro no e-mail; na base de dados
                    // fica o SHA-256 dele — quem conseguir ler a tabela não
                    // fica com links válidos na mão.
                    $token = bin2hex(random_bytes(32));
                    // Um pedido novo invalida os links anteriores ainda por usar.
                    $pdo->prepare("UPDATE recuperacoes_senha SET usado_em = NOW()
                                   WHERE utilizador_id = ? AND usado_em IS NULL")->execute([$conta['id']]);
                    $pdo->prepare(
                        "INSERT INTO recuperacoes_senha (utilizador_id, token_hash, expira_em)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))")
                        ->execute([$conta['id'], hash('sha256', $token), RECUPERACAO_VALIDADE_MINUTOS]);

                    $link = urlPublica() . '/auth/redefinir.php?token=' . $token;
                    $corpo = "Olá " . $conta['nome'] . ",\r\n\r\n"
                        . "Foi pedido um link para definir uma nova palavra-passe no Sistema de Gestão de Horários da FAGRENM.\r\n\r\n"
                        . "Abre este endereço para a definir (válido durante " . RECUPERACAO_VALIDADE_MINUTOS . " minutos, uma única vez):\r\n"
                        . $link . "\r\n\r\n"
                        . "Se não foste tu a pedir, ignora este e-mail — a tua palavra-passe atual continua a funcionar.\r\n";
                    if (!enviarEmailSmtp($conta['email'], $conta['nome'], 'Recuperação de palavra-passe', $corpo)) {
                        error_log('Recuperação de palavra-passe: o e-mail não saiu para a conta #' . $conta['id']);
                    }
                }
            }
        } catch (Throwable $e) {
            // Tabela ainda por criar, SMTP em baixo, etc. — fica no log; a
            // resposta ao browser é a mesma de sempre (ver abaixo).
            error_log('Recuperação de palavra-passe falhou: ' . $e->getMessage());
        }

        // A resposta é a mesma quer a conta exista quer não, quer o e-mail
        // tenha saído quer não: esta página nunca confirma que um endereço
        // tem conta no sistema. (O tempo de resposta ainda pode variar — o
        // envio por SMTP demora — e é por isso que os pedidos estão
        // limitados por IP, acima.)
        $ok = "Se existir uma conta ativa com esse e-mail, enviámos-lhe um link para definir uma nova palavra-passe. "
            . "Confirma também a pasta de spam.";
    }
}

authInicio('Recuperar palavra-passe', 'RECUPERAÇÃO', 'Esqueci-me da palavra-passe',
    'Indica o e-mail da tua conta — enviamos-te um link para definires uma nova.',
    ['Indica o teu e-mail', 'Abre o link recebido', 'Define a nova palavra-passe']);
?>
            <?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

            <?php if (!$ok): ?>
            <form class="login-form" method="post" novalidate>
                <?= campoCSRF() ?>
                <div class="form-group">
                    <label for="email">E-mail da conta</label>
                    <div class="input-with-icon">
                        <?= icone('mail') ?>
                        <input class="form-control" type="email" id="email" name="email" maxlength="150"
                               placeholder="nome@fagrenm.ucm.ac.mz" autocomplete="username"
                               value="<?= htmlspecialchars(postTexto('email', 150)) ?>" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary login-submit">Enviar link <?= icone('chevron', 17) ?></button>
            </form>
            <?php endif; ?>
<?php authFim(); ?>
