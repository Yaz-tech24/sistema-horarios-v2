<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador']);

$erro = $ok = null; $editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    if (isset($_POST['eliminar'])) {
        try {
            $pdo->prepare("DELETE FROM docente_disciplina WHERE docente_id = ?")->execute([(int)$_POST['eliminar']]);
            $pdo->prepare("DELETE FROM docentes WHERE id = ?")->execute([(int)$_POST['eliminar']]);
            $sucesso = true; $mensagem = 'Docente eliminado.';
        } catch (PDOException $e) {
            $sucesso = false; $mensagem = 'Não é possível eliminar: este docente é regente/assistente de disciplinas ou já tem aulas marcadas.';
        }
        if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem); }
        definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
        header('Location: docentes.php'); exit;
    }

    $nome = trim($_POST['nome'] ?? '');
    $cat  = trim($_POST['categoria'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);
    $disc = array_map('intval', $_POST['disciplinas'] ?? []);

    if ($nome === '') {
        $erro = "Preenche o nome do docente.";
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE docentes SET nome=?, categoria=? WHERE id=?");
            $stmt->execute([$nome, $cat, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO docentes (nome, categoria) VALUES (?,?)");
            $stmt->execute([$nome, $cat]);
            $id = (int)$pdo->lastInsertId();
        }
        // Reatribui as disciplinas que este docente pode lecionar — esta
        // lista é usada para filtrar os selects de regente/assistente no
        // Editor de Horário (ver coordenador/editor_horario.php).
        $pdo->prepare("DELETE FROM docente_disciplina WHERE docente_id=?")->execute([$id]);
        $ins = $pdo->prepare("INSERT INTO docente_disciplina (docente_id, disciplina_id) VALUES (?,?)");
        foreach ($disc as $did) { $ins->execute([$id, $did]); }
        definirFlash('ok', 'Docente guardado.');
        header('Location: docentes.php'); exit;
    }
}

$minhasDisc = [];
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM docentes WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
    if ($editar) {
        $stmt = $pdo->prepare("SELECT disciplina_id FROM docente_disciplina WHERE docente_id=?");
        $stmt->execute([$editar['id']]);
        $minhasDisc = array_column($stmt->fetchAll(), 'disciplina_id');
    }
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$disciplinas = $pdo->query(
    "SELECT d.id, d.nome, c.sigla FROM disciplinas d JOIN cursos c ON d.curso_id=c.id
     ORDER BY c.sigla, d.nome")->fetchAll();
$docentes = $pdo->query(
    "SELECT doc.*, COUNT(DISTINCT dd.disciplina_id) AS n_disc
     FROM docentes doc
     LEFT JOIN docente_disciplina dd ON dd.docente_id = doc.id
     GROUP BY doc.id ORDER BY doc.nome")->fetchAll();

$pageTitle = 'Docentes';
$pageDescription = 'Um docente é cadastrado uma única vez, mesmo que lecione em vários cursos.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<section class="card <?= $editar ? 'cartao--em-edicao' : '' ?>" style="margin-bottom:20px">
    <div class="card-header"><div><h2><?= $editar ? 'Editar docente' : 'Novo docente' ?></h2><p>Categoria e disciplinas que pode lecionar.</p></div></div>
    <div class="card-body">
        <form method="post">
            <?= campoCSRF() ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
            <div class="form-row">
                <div class="campo"><label>Nome completo</label>
                    <input class="form-control" type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>"></div>
                <div class="campo"><label>Categoria</label>
                    <input class="form-control" type="text" name="categoria" placeholder="Mestre, Doutor, Assistente…"
                           value="<?= htmlspecialchars($editar['categoria'] ?? '') ?>"></div>
            </div>

            <div class="campo" style="margin-top:15px">
                <label>Disciplinas que pode lecionar</label>
                <p style="margin:-2px 0 8px;color:var(--ink-soft);font-size:.76rem;">
                    Restringe as opções de docente regente/assistente no Editor de Horário a quem aqui estiver marcado
                    (uma disciplina sem ninguém marcado continua a mostrar todos os docentes, para não bloquear o fluxo).
                </p>
                <div class="grelha-checkboxes" style="max-height:200px;overflow:auto;border:1px solid var(--line);border-radius:1px;padding:12px;background:var(--surface)">
                    <?php foreach ($disciplinas as $d): ?>
                    <label>
                        <input type="checkbox" name="disciplinas[]" value="<?= $d['id'] ?>"
                            <?= in_array($d['id'], $minhasDisc) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($d['sigla'] . ' · ' . $d['nome']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top:18px">
                <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem;">
                    <?= $editar ? 'Atualizar docente' : 'Guardar docente' ?></button>
                <?php if ($editar): ?><a class="btn btn-secondary" href="docentes.php" style="width:auto;padding-inline:2rem;margin-left:8px">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>
</section>

<?php if (!$docentes): ?>
    <div class="estado-vazio">
        <p><strong>Ainda não há docentes cadastrados.</strong></p>
        <p>Usa o formulário acima para criar o primeiro — depois podes atribuir-lhe as disciplinas que pode lecionar.</p>
    </div>
<?php else: ?>
<section class="card">
    <div class="toolbar">
        <div class="search-box"><?= icone('search', 18) ?><input type="search" placeholder="Pesquisar docente…" data-filtro-tabela="#tabela-docentes"></div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table" id="tabela-docentes">
            <thead><tr><th>Docente</th><th>Categoria</th><th>Disciplinas</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($docentes as $d): ?>
            <tr>
                <td><div class="cell-main"><span class="cell-icon"><?= htmlspecialchars(mb_strtoupper(mb_substr($d['nome'], 0, 2))) ?></span><span><strong><?= htmlspecialchars($d['nome']) ?></strong><small>Docente #<?= $d['id'] ?></small></span></div></td>
                <td><?= htmlspecialchars($d['categoria'] ?? '') ?></td>
                <td><?= $d['n_disc'] ?></td>
                <td>
                    <div class="actions">
                        <a class="btn btn-secondary btn-icon" title="Editar" href="?editar=<?= $d['id'] ?>"><?= icone('edit', 16) ?></a>
                        <form method="post" data-confirmar="Eliminar o docente &quot;<?= htmlspecialchars($d['nome']) ?>&quot;?" data-ajax-remover="tr">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="eliminar" value="<?= $d['id'] ?>">
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
