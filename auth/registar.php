<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

if (utilizadorAutenticado()) {
    header('Location: ' . BASE_URL . '/painel.php');
    exit;
}

// PERMITIR_REGISTO=0 no .env fecha o registo por completo (a página deixa
// de existir, não fica só sem link) — para quando todas as contas já estão
// criadas e só o Administrador deve poder criar mais.
if (getenv('PERMITIR_REGISTO') === '0') {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Registos por IP em cada janela — esta página é pública, e sem limite
// dava para "ocupar" os nomes de todos os docentes com contas falsas (ficam
// inativas, mas tiram o nome da lista a quem é o verdadeiro dono).
const REGISTO_MAX_POR_IP = 5;

$erro = $ok = null;

// Só quem já é docente cadastrado (Admin → Docentes, ou a importação de
// dados reais) mas ainda não tem conta de acesso é que se pode registar —
// a pessoa escolhe o SEU nome nesta lista, nunca escreve o perfil nem cria
// um docente novo. Isto evita um registo aberto a qualquer pessoa.
function docentesPorRegistar(PDO $pdo): array {
    return $pdo->query(
        "SELECT id, nome, categoria FROM docentes WHERE utilizador_id IS NULL ORDER BY nome")->fetchAll();
}
$docentes = docentesPorRegistar($pdo);

$docenteIdEscolhido = 0;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docenteIdEscolhido = $docenteId = (int)postTexto('docente_id', 12);
    $email     = postTexto('email', 150);
    $senha     = postSenha('senha');
    $confirma  = postSenha('senha_confirma');

    $stmt = $pdo->prepare("SELECT id, nome FROM docentes WHERE id = ? AND utilizador_id IS NULL");
    $stmt->execute([$docenteId]);
    $docente = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    $emailEmUso = (bool)$stmt->fetch();

    if (!csrfValido()) {
        $erro = "Esta página esteve aberta demasiado tempo. Preenche novamente e volta a enviar.";
    } elseif (acoesRecentesDoIp($pdo, ['registo_conta'], 60) >= REGISTO_MAX_POR_IP) {
        $erro = "Demasiados registos a partir desta rede. Tenta mais tarde, ou pede a conta ao Administrador.";
    } elseif (!$docente) {
        $erro = "Escolhe o teu nome na lista — ou já tens conta, ou a lista mudou entretanto. Atualiza a página.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Este e-mail não parece válido.";
    } elseif ($emailEmUso) {
        $erro = "Já existe uma conta com este e-mail.";
    } elseif ($erroSenha = validarPalavraPasse($senha, $email)) {
        $erro = $erroSenha;
    } elseif ($senha !== $confirma) {
        $erro = "A confirmação não coincide com a palavra-passe.";
    } else {
        // Conta nasce inativa — só um Administrador a pode ativar (Admin →
        // Utilizadores, o mesmo botão que já existe), como confirmação de
        // que quem se registou é mesmo quem diz ser.
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO utilizadores (nome, email, password_hash, perfil, ativo) VALUES (?,?,?,?,0)")
                ->execute([$docente['nome'], $email, password_hash($senha, PASSWORD_DEFAULT), 'Docente']);
            $novoId = (int)$pdo->lastInsertId();
            $ligado = $pdo->prepare("UPDATE docentes SET utilizador_id = ? WHERE id = ? AND utilizador_id IS NULL");
            $ligado->execute([$novoId, $docenteId]);
            if ($ligado->rowCount() === 0) {
                // Outra pessoa registou-se para este docente entretanto (corrida rara).
                throw new RuntimeException('docente já foi ligado a outra conta');
            }
            $pdo->commit();
            registarAuditoria($pdo, 'registo_conta', $docente['nome'] . ' <' . $email . '>', $novoId);
            $ok = "Conta criada para " . $docente['nome'] . ". Um Administrador tem de a ativar antes de conseguires entrar.";
            $docentes = docentesPorRegistar($pdo);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Erro ao registar docente: ' . $e->getMessage());
            $erro = "Não foi possível criar a conta — pode já ter sido registada por outra pessoa. Atualiza a página e confirma.";
            $docentes = docentesPorRegistar($pdo);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registar — Sistema de Gestão de Horários · FAGRENM</title>
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
            <h1>O teu horário<br>fica mais perto<br><span>com uma conta.</span></h1>
            <p>Se já és docente cadastrado na faculdade mas ainda não tens acesso ao sistema, cria a tua conta aqui.</p>
            <div class="login-features">
                <span class="login-feature"><b>01</b> Escolhe o teu nome</span>
                <span class="login-feature"><b>02</b> Define a palavra-passe</span>
                <span class="login-feature"><b>03</b> Aguarda ativação</span>
            </div>
        </div>
        <small class="login-credit">UCM · Faculdade de Gestão de Recursos Florestais</small>
    </section>
    <section class="login-panel">
        <div class="login-box">
            <span class="login-number">REGISTO</span>
            <h2>Criar conta de docente</h2>
            <p>Só para docentes já cadastrados na faculdade, sem conta ainda.</p>

            <?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

            <?php if (!$docentes && !$ok): ?>
                <p class="login-nota">Não há docentes por registar de momento — todos os docentes cadastrados já têm conta.
                    Se achas que devias estar nesta lista, contacta o Administrador do sistema.</p>
            <?php elseif (!$ok): ?>
            <form class="login-form" method="post" novalidate>
                <?= campoCSRF() ?>
                <div class="form-group">
                    <label for="docente_id">O teu nome</label>
                    <select class="form-control" id="docente_id" name="docente_id" required autofocus>
                        <option value="">— escolher —</option>
                        <?php foreach ($docentes as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= ($docenteIdEscolhido === (int)$d['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['nome']) ?><?= $d['categoria'] ? ' (' . htmlspecialchars($d['categoria']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <div class="input-with-icon">
                        <?= icone('mail') ?>
                        <input class="form-control" type="email" id="email" name="email"
                               placeholder="nome@fagrenm.ucm.ac.mz" maxlength="150" autocomplete="username"
                               value="<?= htmlspecialchars($email) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="senha">Palavra-passe (mínimo <?= SENHA_MIN ?> caracteres)</label>
                    <div class="input-with-icon">
                        <?= icone('lock') ?>
                        <input class="form-control" type="password" id="senha" name="senha"
                               minlength="<?= SENHA_MIN ?>" maxlength="<?= SENHA_MAX_BYTES ?>" placeholder="••••••••"
                               autocomplete="new-password" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="senha_confirma">Confirmar palavra-passe</label>
                    <div class="input-with-icon">
                        <?= icone('lock') ?>
                        <input class="form-control" type="password" id="senha_confirma" name="senha_confirma"
                               minlength="<?= SENHA_MIN ?>" maxlength="<?= SENHA_MAX_BYTES ?>" placeholder="••••••••"
                               autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary login-submit">Criar conta <?= icone('chevron', 17) ?></button>
            </form>
            <?php endif; ?>

            <p class="login-nota">
                Já tens conta? <a href="<?= BASE_URL ?>/auth/login.php" style="color:var(--cobalt);font-weight:600">Entrar</a>
            </p>
        </div>
    </section>
</main>
</body>
</html>
