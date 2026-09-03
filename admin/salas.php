<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador']);

$erro = $ok = null; $editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    if (isset($_POST['eliminar'])) {
        try {
            $pdo->prepare("DELETE FROM salas WHERE id = ?")->execute([(int)$_POST['eliminar']]);
            $sucesso = true; $mensagem = 'Sala eliminada.';
        } catch (PDOException $e) {
            $sucesso = false; $mensagem = 'Não é possível eliminar: esta sala já tem aulas marcadas.';
        }
        if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem); }
        definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
        header('Location: salas.php'); exit;
    }

    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? 'Normal';
    $cap  = (int)($_POST['capacidade'] ?? 0);
    $eq   = trim($_POST['equipamento'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);

    if ($nome === '' || $cap <= 0) {
        $erro = "Preenche o nome e uma capacidade válida.";
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE salas SET nome=?, tipo=?, capacidade=?, equipamento=? WHERE id=?");
            $stmt->execute([$nome, $tipo, $cap, $eq, $id]);
            definirFlash('ok', 'Sala atualizada.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO salas (nome, tipo, capacidade, equipamento) VALUES (?,?,?,?)");
            $stmt->execute([$nome, $tipo, $cap, $eq]);
            definirFlash('ok', 'Sala criada.');
        }
        header('Location: salas.php'); exit;
    }
}

if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM salas WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$salas = $pdo->query("SELECT * FROM salas ORDER BY nome")->fetchAll();

$pageTitle = 'Salas';
$pageDescription = 'Salas, tipos, capacidade e equipamento.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<section class="card <?= $editar ? 'cartao--em-edicao' : '' ?>" style="margin-bottom:20px">
    <div class="card-header"><div><h2><?= $editar ? 'Editar sala' : 'Nova sala' ?></h2><p>Tipo, capacidade e equipamento disponível.</p></div></div>
    <div class="card-body">
        <form method="post" class="form-row">
            <?= campoCSRF() ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
            <div class="campo"><label>Nome</label>
                <input class="form-control" type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>"></div>
            <div class="campo"><label>Tipo</label>
                <select class="form-control form-select" name="tipo">
                    <?php foreach (['Normal','Laboratorio','Auditorio'] as $t): ?>
                    <option <?= (($editar['tipo'] ?? '') === $t) ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label>Capacidade</label>
                <input class="form-control" type="number" name="capacidade" min="1" required value="<?= $editar['capacidade'] ?? '' ?>"></div>
            <div class="campo"><label>Equipamento</label>
                <input class="form-control" type="text" name="equipamento" value="<?= htmlspecialchars($editar['equipamento'] ?? '') ?>"></div>
            <div class="campo" style="grid-column:1/-1">
                <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem"><?= $editar ? 'Atualizar' : 'Guardar sala' ?></button>
                <?php if ($editar): ?><a class="btn btn-secondary" href="salas.php" style="width:auto;padding-inline:2rem;margin-left:8px">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>
</section>

<?php if (!$salas): ?>
    <div class="estado-vazio">
        <p><strong>Ainda não há salas cadastradas.</strong></p>
        <p>Usa o formulário acima para criar a primeira sala.</p>
    </div>
<?php else: ?>
<section class="card">
    <div class="toolbar">
        <div class="search-box"><?= icone('search', 18) ?><input type="search" placeholder="Pesquisar sala…" data-filtro-tabela="#tabela-salas"></div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table" id="tabela-salas">
            <thead><tr><th>Sala</th><th>Tipo</th><th>Capacidade</th><th>Equipamento</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($salas as $s): ?>
            <tr>
                <td><div class="cell-main"><span class="cell-icon"><?= icone('door', 16) ?></span><span><strong><?= htmlspecialchars($s['nome']) ?></strong><small>Sala #<?= $s['id'] ?></small></span></div></td>
                <td><?= htmlspecialchars($s['tipo']) ?></td>
                <td><?= $s['capacidade'] ?></td>
                <td><?= htmlspecialchars($s['equipamento'] ?? '') ?></td>
                <td>
                    <div class="actions">
                        <a class="btn btn-secondary btn-icon" title="Editar" href="?editar=<?= $s['id'] ?>"><?= icone('edit', 16) ?></a>
                        <form method="post" data-confirmar="Eliminar a sala &quot;<?= htmlspecialchars($s['nome']) ?>&quot;?" data-ajax-remover="tr">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="eliminar" value="<?= $s['id'] ?>">
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
