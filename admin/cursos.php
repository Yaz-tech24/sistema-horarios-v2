<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador']);

$erro = $ok = null;
$editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    if (isset($_POST['eliminar'])) {
        try {
            $pdo->prepare("DELETE FROM cursos WHERE id = ?")->execute([(int)$_POST['eliminar']]);
            $sucesso = true; $mensagem = 'Curso eliminado.';
        } catch (PDOException $e) {
            $sucesso = false; $mensagem = 'Não é possível eliminar: há disciplinas, turmas ou coordenadores associados a este curso.';
        }
        if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem); }
        definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
        header('Location: cursos.php'); exit;
    }

    $nome  = trim($_POST['nome'] ?? '');
    $sigla = strtoupper(trim($_POST['sigla'] ?? ''));
    $id    = (int)($_POST['id'] ?? 0);

    if ($nome === '' || $sigla === '') {
        $erro = "Preenche o nome e a sigla.";
    } else {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE cursos SET nome = ?, sigla = ? WHERE id = ?");
                $stmt->execute([$nome, $sigla, $id]);
                definirFlash('ok', 'Curso atualizado.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO cursos (nome, sigla) VALUES (?, ?)");
                $stmt->execute([$nome, $sigla]);
                definirFlash('ok', 'Curso criado.');
            }
            header('Location: cursos.php'); exit;
        } catch (PDOException $e) {
            $erro = "Não foi possível guardar (sigla repetida?).";
        }
    }
}

if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$cursos = $pdo->query("SELECT * FROM cursos ORDER BY nome")->fetchAll();

$pageTitle = 'Cursos';
$pageDescription = 'Cadastrar e gerir os cursos da faculdade.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<section class="card <?= $editar ? 'cartao--em-edicao' : '' ?>" style="margin-bottom:20px">
    <div class="card-header"><div><h2><?= $editar ? 'Editar curso' : 'Novo curso' ?></h2><p>O nome completo e a sigla usada em todo o sistema.</p></div></div>
    <div class="card-body">
        <form method="post" class="form-row">
            <?= campoCSRF() ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
            <div class="campo">
                <label for="f-nome">Nome do curso</label>
                <input class="form-control" type="text" id="f-nome" name="nome" required
                       value="<?= htmlspecialchars($editar['nome'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="f-sigla">Sigla</label>
                <input class="form-control" type="text" id="f-sigla" name="sigla" maxlength="10" required
                       value="<?= htmlspecialchars($editar['sigla'] ?? '') ?>">
            </div>
            <div class="campo" style="grid-column:1/-1">
                <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem"><?= $editar ? 'Atualizar' : 'Guardar curso' ?></button>
                <?php if ($editar): ?><a class="btn btn-secondary" href="cursos.php" style="width:auto;padding-inline:2rem;margin-left:8px">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>
</section>

<?php if (!$cursos): ?>
    <div class="estado-vazio">
        <p><strong>Ainda não há cursos cadastrados.</strong></p>
        <p>Usa o formulário acima para criar o primeiro curso da faculdade.</p>
    </div>
<?php else: ?>
<section class="card">
    <div class="toolbar">
        <div class="search-box"><?= icone('search', 18) ?><input type="search" placeholder="Pesquisar curso…" data-filtro-tabela="#tabela-cursos"></div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table" id="tabela-cursos">
            <thead><tr><th>Curso</th><th>Sigla</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($cursos as $c): ?>
            <tr>
                <td><div class="cell-main"><span class="cell-icon"><?= htmlspecialchars(mb_strtoupper(mb_substr($c['sigla'], 0, 2))) ?></span><span><strong><?= htmlspecialchars($c['nome']) ?></strong><small>Curso #<?= $c['id'] ?></small></span></div></td>
                <td><?= htmlspecialchars($c['sigla']) ?></td>
                <td>
                    <div class="actions">
                        <a class="btn btn-secondary btn-icon" title="Editar" href="?editar=<?= $c['id'] ?>"><?= icone('edit', 16) ?></a>
                        <form method="post" data-confirmar="Eliminar o curso &quot;<?= htmlspecialchars($c['nome']) ?>&quot;? Esta ação não pode ser desfeita." data-ajax-remover="tr">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="eliminar" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-icon" title="Eliminar"><?= icone('trash', 16) ?></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
