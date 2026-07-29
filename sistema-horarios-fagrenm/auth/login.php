<?php
require '../includes/auth.php';
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ? AND ativo = 1");
    $stmt->execute([$email]);
    $utilizador = $stmt->fetch();

    if ($utilizador && password_verify($senha, $utilizador['password_hash'])) {
        $_SESSION['utilizador_id'] = $utilizador['id'];
        $_SESSION['nome']          = $utilizador['nome'];
        $_SESSION['perfil']        = $utilizador['perfil'];

        // Envia cada perfil para a sua área inicial
        switch ($utilizador['perfil']) {
            case 'Administrador':
                header('Location: /admin/cursos.php'); break;
            case 'Coordenador':
                header('Location: /coordenador/escolher_curso.php'); break;
            case 'Docente':
                header('Location: /docente/meu_horario.php'); break;
        }
        exit;
    } else {
        $erro = "E-mail ou palavra-passe incorretos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head><meta charset="UTF-8"><title>Login</title></head>
<body>
    <h1>Sistema de Gestão de Horários</h1>
    <?php if (isset($erro)): ?>
        <p style="color:red;"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>
    <form method="post">
        <label>E-mail: <input type="email" name="email" required></label><br>
        <label>Palavra-passe: <input type="password" name="senha" required></label><br>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
