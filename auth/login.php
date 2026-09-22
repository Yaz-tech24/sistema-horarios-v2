<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../config/db.php';

// Se já tem sessão, segue direto para o painel
if (utilizadorAutenticado()) {
    header('Location: ' . BASE_URL . '/painel.php');
    exit;
}

$erro = null;

// Limite de tentativas falhadas antes de bloquear a conta temporariamente
// (mitiga força bruta sobre a password — RN de segurança, não do domínio).
const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_BLOQUEIO_MINUTOS = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    // Nota: sem "AND ativo = 1" aqui — precisamos de saber se a password
    // está certa mesmo numa conta ainda inativa (ex.: acabada de criar em
    // auth/registar.php, à espera de aprovação do Administrador), para dar
    // uma mensagem específica em vez do "e-mail ou palavra-passe incorretos"
    // genérico. Só revela essa distinção depois de confirmar a password —
    // com a password errada, continua a dar sempre o erro genérico.
    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    $utilizador = $stmt->fetch();

    if ($utilizador && $utilizador['bloqueado_ate'] && strtotime($utilizador['bloqueado_ate']) > time()) {
        $minutos = (int)ceil((strtotime($utilizador['bloqueado_ate']) - time()) / 60);
        $erro = "Demasiadas tentativas falhadas. Tenta novamente daqui a $minutos minuto(s).";
    } elseif ($utilizador && !$utilizador['ativo'] && password_verify($senha, $utilizador['password_hash'])) {
        $erro = "A tua conta ainda não foi ativada por um Administrador. Tenta mais tarde ou contacta-o.";
    } elseif ($utilizador && $utilizador['ativo'] && password_verify($senha, $utilizador['password_hash'])) {
        $pdo->prepare("UPDATE utilizadores SET tentativas_falhadas = 0, bloqueado_ate = NULL WHERE id = ?")
            ->execute([$utilizador['id']]);

        // Novo ID de sessão a cada login — evita fixação de sessão
        session_regenerate_id(true);
        $_SESSION['utilizador_id'] = $utilizador['id'];
        $_SESSION['nome']          = $utilizador['nome'];
        $_SESSION['perfil']        = $utilizador['perfil'];

        header('Location: ' . BASE_URL . '/painel.php');
        exit;
    } else {
        if ($utilizador) {
            $tentativas = (int)$utilizador['tentativas_falhadas'] + 1;
            if ($tentativas >= LOGIN_MAX_TENTATIVAS) {
                $pdo->prepare(
                    "UPDATE utilizadores SET tentativas_falhadas = 0,
                        bloqueado_ate = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?")
                    ->execute([LOGIN_BLOQUEIO_MINUTOS, $utilizador['id']]);
                $erro = "Demasiadas tentativas falhadas. Conta bloqueada durante "
                      . LOGIN_BLOQUEIO_MINUTOS . " minutos.";
            } else {
                $pdo->prepare("UPDATE utilizadores SET tentativas_falhadas = ? WHERE id = ?")
                    ->execute([$tentativas, $utilizador['id']]);
                $erro = "E-mail ou palavra-passe incorretos.";
            }
        } else {
            $erro = "E-mail ou palavra-passe incorretos.";
        }
    }
}
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

            <form class="login-form" method="post" novalidate>
                <div class="form-group">
                    <label for="email">E-mail institucional</label>
                    <div class="input-with-icon">
                        <?= icone('mail') ?>
                        <input class="form-control" type="email" id="email" name="email"
                               placeholder="nome@fagrenm.ucm.ac.mz"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="senha">Palavra-passe</label>
                    <div class="input-with-icon">
                        <?= icone('lock') ?>
                        <input class="form-control" type="password" id="senha" name="senha"
                               placeholder="••••••••" required>
                        <button type="button" class="password-toggle" id="btn-mostrar-senha"
                                aria-label="Mostrar palavra-passe" aria-pressed="false">
                            <span id="icone-olho"><?= icone('eye', 18) ?></span>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary login-submit">Entrar <?= icone('chevron', 17) ?></button>
            </form>

            <p class="login-nota">
                Acesso reservado à coordenação e ao corpo docente.<br>
                Problemas com a conta? Contacta o administrador do sistema.
            </p>
        </div>
    </section>
</main>

<script>
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
