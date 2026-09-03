<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';

if (isset($_POST['curso_id'])) {
    validarCSRF();
    $cid = (int)$_POST['curso_id'];
    // Só aceita cursos realmente atribuídos a este coordenador
    $stmt = $pdo->prepare("SELECT 1 FROM coordenador_curso WHERE utilizador_id=? AND curso_id=?");
    $stmt->execute([$_SESSION['utilizador_id'], $cid]);
    if ($stmt->fetch()) {
        $_SESSION['curso_id_atual'] = $cid;
        unset($_SESSION['turma_id_atual'], $_SESSION['horario_id_atual']);
        header('Location: ' . BASE_URL . '/coordenador/editor_horario.php');
        exit;
    }
}

$cursos = cursosDoCoordenador($pdo);

$pageTitle = 'Escolher curso';
$pageDescription = 'Só vês aqui os cursos que te foram atribuídos pelo Administrador.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$cursos): ?>
    <div class="alert alert-error">Ainda não tens nenhum curso atribuído.
        Pede ao Administrador para te associar a um curso em Utilizadores e Permissões.</div>
<?php else: ?>
<form method="post">
    <?= campoCSRF() ?>
    <div class="grid three-column">
        <?php foreach ($cursos as $c): ?>
        <button type="submit" name="curso_id" value="<?= $c['id'] ?>" class="course-card">
            <div class="course-card-top"><span class="course-code"><?= htmlspecialchars($c['sigla']) ?></span><span class="badge badge-success"><span class="status-dot"></span>Ativo</span></div>
            <h3><?= htmlspecialchars($c['nome']) ?></h3>
            <p>Entrar no curso e ir para o editor de horário.</p>
        </button>
        <?php endforeach; ?>
    </div>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
