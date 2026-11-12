<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/email.php'; // recuperacaoDisponivel()

// Se já tem sessão, segue direto para o painel
if (utilizadorAutenticado()) {
    header('Location: ' . BASE_URL . '/painel.php');
    exit;
}

$erro = null;
$email = '';

// Aviso deixado por quem terminou a sessão anterior (expirou, conta
// desativada, palavra-passe redefinida…) — mostra-se uma vez.
$aviso = $_SESSION['aviso_login'] ?? null;
unset($_SESSION['aviso_login']);

// As duas frases que um login falhado pode devolver. São EXATAMENTE as
// mesmas quer o e-mail exista quer não — incluindo o bloqueio ao fim de
// LOGIN_MAX_TENTATIVAS — para a página nunca servir para descobrir que
// e-mails têm conta (ver falhasRecentesDoEmail() em includes/seguranca.php).
const LOGIN_ERRO_GENERICO = "E-mail ou palavra-passe incorretos.";
const LOGIN_ERRO_BLOQUEIO = "Demasiadas tentativas falhadas. Tenta novamente dentro de "
    . LOGIN_BLOQUEIO_MINUTOS . " minutos.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = postTexto('email', 150);
    $senha = postSenha('senha');

    if (!csrfValido()) {
        // Token CSRF também no login: sem ele, um site malicioso podia
        // autenticar a vítima numa conta do atacante sem ela dar por isso.
        $erro = "Esta página esteve aberta demasiado tempo. Tenta entrar novamente.";
    } elseif (acoesRecentesDoIp($pdo, ['login_falhado'], IP_JANELA_MINUTOS) >= IP_MAX_FALHAS) {
        // Trava quem anda a experimentar palavras-passe em muitas contas
        // diferentes a partir do mesmo sítio (o bloqueio por conta, sozinho,
        // não o apanhava: nunca falha 5 vezes na mesma).
        $erro = "Demasiadas tentativas a partir desta rede. Tenta novamente daqui a alguns minutos.";
    } else {
        // Nota: sem "AND ativo = 1" aqui — precisamos de saber se a password
        // está certa mesmo numa conta ainda inativa (ex.: acabada de criar em
        // auth/registar.php, à espera de aprovação do Administrador), para dar
        // uma mensagem específica em vez do "e-mail ou palavra-passe incorretos"
        // genérico. Só revela essa distinção depois de confirmar a password —
        // com a password errada, continua a dar sempre o erro genérico.
        // O bloqueio é decidido pelo relógio do MySQL (foi ele que gravou
        // bloqueado_ate), nunca comparando com o time() do PHP.
        $stmt = $pdo->prepare(
            "SELECT u.*, (u.bloqueado_ate IS NOT NULL AND u.bloqueado_ate > NOW()) AS esta_bloqueado
             FROM utilizadores u WHERE u.email = ?");
        $stmt->execute([$email]);
        $utilizador = $stmt->fetch();

        if ($utilizador) {
            $senhaCerta = password_verify($senha, $utilizador['password_hash']);
        } else {
            // Faz o mesmo trabalho que faria para uma conta real, para o
            // tempo de resposta não denunciar que o e-mail não existe.
            password_hash($senha, PASSWORD_DEFAULT);
            $senhaCerta = false;
        }

        if ($utilizador && $utilizador['esta_bloqueado']) {
            $erro = LOGIN_ERRO_BLOQUEIO;
        } elseif ($utilizador && $senhaCerta && !$utilizador['ativo']) {
            $erro = "A tua conta ainda não foi ativada por um Administrador. Tenta mais tarde ou contacta-o.";
        } elseif ($utilizador && $senhaCerta) {
            $pdo->prepare("UPDATE utilizadores SET tentativas_falhadas = 0, bloqueado_ate = NULL WHERE id = ?")
                ->execute([$utilizador['id']]);
            try {
                $pdo->prepare("UPDATE utilizadores SET ultimo_login = NOW() WHERE id = ?")
                    ->execute([$utilizador['id']]);
            } catch (PDOException $e) {
                // coluna ainda não existe (migração por correr) — não impede a entrada
            }

            // Hashes antigos (custo mais baixo) são refeitos com o algoritmo
            // atual na primeira vez que a pessoa entra — é o único momento em
            // que temos a palavra-passe em claro para o poder fazer.
            if (password_needs_rehash($utilizador['password_hash'], PASSWORD_DEFAULT)) {
                $utilizador['password_hash'] = password_hash($senha, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE utilizadores SET password_hash = ? WHERE id = ?")
                    ->execute([$utilizador['password_hash'], $utilizador['id']]);
            }

            iniciarSessaoUtilizador($utilizador);
            registarAuditoria($pdo, 'login_ok');

            header('Location: ' . BASE_URL . (!empty($utilizador['deve_mudar_senha']) ? '/conta.php' : '/painel.php'));
            exit;
        } elseif ($utilizador) {
            $tentativas = (int)$utilizador['tentativas_falhadas'] + 1;
            registarAuditoria($pdo, 'login_falhado', $email, (int)$utilizador['id']);
            if ($tentativas >= LOGIN_MAX_TENTATIVAS) {
                $pdo->prepare(
                    "UPDATE utilizadores SET tentativas_falhadas = 0,
                        bloqueado_ate = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?")
                    ->execute([LOGIN_BLOQUEIO_MINUTOS, $utilizador['id']]);
                registarAuditoria($pdo, 'conta_bloqueada', $email, (int)$utilizador['id']);
                $erro = LOGIN_ERRO_BLOQUEIO;
            } else {
                $pdo->prepare("UPDATE utilizadores SET tentativas_falhadas = ? WHERE id = ?")
                    ->execute([$tentativas, $utilizador['id']]);
                $erro = LOGIN_ERRO_GENERICO;
            }
        } else {
            registarAuditoria($pdo, 'login_falhado', $email);
            $erro = falhasRecentesDoEmail($pdo, $email, LOGIN_BLOQUEIO_MINUTOS) >= LOGIN_MAX_TENTATIVAS
                ? LOGIN_ERRO_BLOQUEIO : LOGIN_ERRO_GENERICO;
        }
    }
}

// (O link para auth/registar.php não é anunciado aqui — decisão do commit
// a81a5d2; a página continua a existir para quem tiver o endereço direto.)
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — Sistema de Gestão de Horários · FAGRENM</title>
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
            <h1>O horário<br>é a faculdade<br><span>em movimento.</span></h1>
            <p>Uma área de trabalho para coordenar docentes, salas e turmas sem perder o contexto.</p>
            <div class="login-features">
                <span class="login-feature"><b>01</b> Estrutura académica</span>
                <span class="login-feature"><b>02</b> Horários e conflitos</span>
                <span class="login-feature"><b>03</b> Publicação</span>
            </div>
        </div>
        <small class="login-credit">UCM · Faculdade de Gestão de Recursos Florestais</small>
    </section>
    <section class="login-panel">
        <div class="login-box">
            <span class="login-number">ACESSO</span>
            <h2>Entrar no sistema</h2>
            <p>Acesso institucional — FAGRENM · Tete</p>

            <?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <?php if ($aviso && !$erro): ?><div class="alert alert-success"><?= htmlspecialchars($aviso) ?></div><?php endif; ?>

            <form class="login-form" method="post" novalidate>
                <?= campoCSRF() ?>
                <div class="form-group">
                    <label for="email">E-mail institucional</label>
                    <div class="input-with-icon">
                        <?= icone('mail') ?>
                        <input class="form-control" type="email" id="email" name="email"
                               placeholder="nome@fagrenm.ucm.ac.mz" maxlength="150" autocomplete="username"
                               value="<?= htmlspecialchars($email) ?>"
                               required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="senha">Palavra-passe</label>
                    <div class="input-with-icon">
                        <?= icone('lock') ?>
                        <input class="form-control" type="password" id="senha" name="senha"
                               placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" id="btn-mostrar-senha"
                                aria-label="Mostrar palavra-passe" aria-pressed="false">
                            <span id="icone-olho"><?= icone('eye', 18) ?></span>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary login-submit">Entrar <?= icone('chevron', 17) ?></button>
            </form>

            <p class="login-nota">
                <?php if (recuperacaoDisponivel()): ?>
                    <a href="<?= BASE_URL ?>/auth/recuperar.php" style="color:var(--cobalt);font-weight:600">Esqueci-me da palavra-passe</a><br>
                <?php endif; ?>
                Acesso reservado à coordenação e ao corpo docente.<br>
                Problemas com a conta? Contacta o administrador do sistema.
            </p>
        </div>
    </section>
</main>

<script nonce="<?= nonceCSP() ?>">
    (function () {
        var btn = document.getElementById('btn-mostrar-senha');
        var campo = document.getElementById('senha');
        var icone = document.getElementById('icone-olho');
        if (!btn || !campo) { return; }
        btn.addEventListener('click', function () {
            var visivel = campo.type === 'text';
            campo.type = visivel ? 'password' : 'text';
            btn.setAttribute('aria-pressed', String(!visivel));
            btn.setAttribute('aria-label', visivel ? 'Mostrar palavra-passe' : 'Esconder palavra-passe');
            icone.innerHTML = visivel
                ? '<?= addslashes(icone('eye', 18)) ?>'
                : '<?= addslashes(icone('eye-off', 18)) ?>';
        });
    })();
</script>

</body>
</html>
