<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

if (utilizadorAutenticado()) {
    header('Location: ' . BASE_URL . '/painel.php');
    exit;
}

const REGISTO_MIN_PASSWORD = 6;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $docenteId = (int)($_POST['docente_id'] ?? 0);
    $email     = trim($_POST['email'] ?? '');
    $senha     = $_POST['senha'] ?? '';
    $confirma  = $_POST['senha_confirma'] ?? '';

    $stmt = $pdo->prepare("SELECT id, nome FROM docentes WHERE id = ? AND utilizador_id IS NULL");
    $stmt->execute([$docenteId]);
    $docente = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    $emailEmUso = (bool)$stmt->fetch();

    if (!$docente) {
        $erro = "Escolhe o teu nome na lista — ou já tens conta, ou a lista mudou entretanto. Atualiza a página.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Este e-mail não parece válido.";
    } elseif ($emailEmUso) {
        $erro = "Já existe uma conta com este e-mail.";
    } elseif (strlen($senha) < REGISTO_MIN_PASSWORD) {
        $erro = "A palavra-passe tem de ter pelo menos " . REGISTO_MIN_PASSWORD . " caracteres.";
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
            $ok = "Conta criada para " . $docente['nome'] . ". Um Administrador tem de a ativar antes de conseguires entrar.";
            $docentes = docentesPorRegistar($pdo);
        } catch (Throwable $e) {
            $pdo->rollBack();
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
                        <option value="<?= $d['id'] ?>" <?= (($_POST['docente_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
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
                               placeholder="nome@fagrenm.ucm.ac.mz"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="senha">Palavra-passe</label>
                    <div class="input-with-icon">
                        <?= icone('lock') ?>
                        <input class="form-control" type="password" id="senha" name="senha"
                               minlength="<?= REGISTO_MIN_PASSWORD ?>" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="senha_confirma">Confirmar palavra-passe</label>
                    <div class="input-with-icon">
                        <?= icone('lock') ?>
                        <input class="form-control" type="password" id="senha_confirma" name="senha_confirma"
                               minlength="<?= REGISTO_MIN_PASSWORD ?>" placeholder="••••••••" required>
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
