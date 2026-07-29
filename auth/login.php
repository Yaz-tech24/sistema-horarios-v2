<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

// Se já tem sessão, segue direto para o painel
if (utilizadorAutenticado()) {
    header('Location: ' . BASE_URL . '/painel.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ? AND ativo = 1");
    $stmt->execute([$email]);
    $utilizador = $stmt->fetch();

    if ($utilizador && password_verify($senha, $utilizador['password_hash'])) {
        $_SESSION['utilizador_id'] = $utilizador['id'];
        $_SESSION['nome']          = $utilizador['nome'];
        $_SESSION['perfil']        = $utilizador['perfil'];

        header('Location: ' . BASE_URL . '/painel.php');
        exit;
    } else {
        $erro = "E-mail ou palavra-passe incorretos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — Sistema de Gestão de Horários · FAGRENM</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilo.css">
</head>
<body class="login-body">

    <!-- Painel de identidade -->
    <aside class="login-brand">
        <div class="brand-topo">
            <div class="monograma">FG</div>
            <p class="brand-eyebrow">FAGRENM · UCM</p>
            <h1 class="brand-titulo">Sistema de Gestão de Horários</h1>
            <p class="brand-texto">
                Uma única plataforma para construir, validar e publicar os
                horários de todos os cursos da faculdade — com deteção
                automática de conflitos de docentes, salas e turmas.
            </p>
        </div>
        <div class="brand-rodape">
            <div class="regua regua--clara"></div>
            Universidade Católica de Moçambique · Tete
        </div>
    </aside>

    <!-- Painel do formulário -->
    <main class="login-form-painel">
        <div class="login-cartao">
            <div class="regua"></div>
            <h1>Bem-vindo de volta</h1>
            <p class="sub">Inicia sessão para aceder à tua área.</p>

            <?php if ($erro): ?>
                <div class="msg msg--erro"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <div class="campo">
                    <label for="email">E-mail institucional</label>
                    <input type="email" id="email" name="email"
                           placeholder="nome@fagrenm.ucm.ac.mz"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>
                <div class="campo">
                    <label for="senha">Palavra-passe</label>
                    <input type="password" id="senha" name="senha"
                           placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn">Entrar</button>
            </form>

            <p class="login-nota">
                Acesso reservado à coordenação e ao corpo docente.<br>
                Problemas com a conta? Contacta o administrador do sistema.
            </p>
        </div>
    </main>

</body>
</html>
