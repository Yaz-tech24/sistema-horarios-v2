<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador']);

$erro = $ok = null; $editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    if (isset($_POST['eliminar'])) {
        $elimId = (int)$_POST['eliminar'];
        try {
            // "Pode lecionar" é só uma lista de aptidão (admin/docentes.php),
            // não um registo de horário — limpar aqui não perde nada real e
            // evita que ela bloqueie a eliminação por engano (a mensagem de
            // erro abaixo fica assim exata: só dispara por aulas de verdade).
            $pdo->prepare("DELETE FROM docente_disciplina WHERE disciplina_id = ?")->execute([$elimId]);
            $pdo->prepare("DELETE FROM disciplinas WHERE id = ?")->execute([$elimId]);
            $sucesso = true; $mensagem = 'Disciplina eliminada.';
        } catch (PDOException $e) {
            $sucesso = false; $mensagem = 'Não é possível eliminar: esta disciplina já tem aulas marcadas em algum horário.';
        }
        if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem); }
        definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
        header('Location: disciplinas.php'); exit;
    }

    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $codigo   = trim($_POST['codigo'] ?? '');
    $nome     = trim($_POST['nome'] ?? '');
    $ano      = (int)($_POST['ano_curricular'] ?? 0);
    // RN09 — carga_horaria representa sempre "vezes por semana", 1 ou 2.
    // Nunca confiar no que vier do POST: fixa-se sempre ao intervalo válido.
    $carga    = max(1, min(2, (int)($_POST['carga_horaria'] ?? 2)));
    $tipo     = $_POST['tipo_aula'] ?? 'Teorica';
    // Laboratório só se aplica a disciplinas não-teóricas — nunca confiar
    // no que vier do POST se o tipo escolhido for Teórica.
    $salaId   = $tipo !== 'Teorica' ? ((int)($_POST['sala_id'] ?? 0) ?: null) : null;
    $regenteId    = (int)($_POST['docente_regente_id'] ?? 0) ?: null;
    $assistenteId = (int)($_POST['docente_assistente_id'] ?? 0) ?: null;
    $id       = (int)($_POST['id'] ?? 0);

    if ($curso_id <= 0 || $nome === '' || $ano < 1 || $ano > 5) {
        $erro = "Preenche o curso, o nome e um ano válido (1-5).";
    } elseif (!$regenteId) {
        // Regente é obrigatório na validação da aplicação, mesmo que a
        // coluna aceite NULL na BD (disciplinas antigas antes de editadas).
        $erro = "Escolhe o docente regente da disciplina.";
    } elseif ($assistenteId && $assistenteId === $regenteId) {
        $erro = "O assistente tem de ser um docente diferente do regente.";
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE disciplinas SET curso_id=?, codigo=?, nome=?, ano_curricular=?, carga_horaria=?,
                    tipo_aula=?, sala_id=?, docente_regente_id=?, docente_assistente_id=? WHERE id=?");
            $stmt->execute([$curso_id, $codigo, $nome, $ano, $carga, $tipo, $salaId, $regenteId, $assistenteId, $id]);
            definirFlash('ok', 'Disciplina atualizada.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO disciplinas (curso_id, codigo, nome, ano_curricular, carga_horaria, tipo_aula,
                    sala_id, docente_regente_id, docente_assistente_id) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$curso_id, $codigo, $nome, $ano, $carga, $tipo, $salaId, $regenteId, $assistenteId]);
            definirFlash('ok', 'Disciplina criada.');
        }
        header('Location: disciplinas.php'); exit;
    }
}

if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM disciplinas WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$cursos = $pdo->query("SELECT * FROM cursos ORDER BY nome")->fetchAll();
$docentes = $pdo->query("SELECT * FROM docentes ORDER BY nome")->fetchAll();
$laboratorios = $pdo->query("SELECT * FROM salas WHERE tipo = 'Laboratorio' ORDER BY nome")->fetchAll();
$disciplinas = $pdo->query(
    "SELECT d.*, c.sigla, dr.nome AS regente_nome, da.nome AS assistente_nome, s.nome AS sala_nome
     FROM disciplinas d
     JOIN cursos c ON d.curso_id = c.id
     LEFT JOIN docentes dr ON d.docente_regente_id = dr.id
     LEFT JOIN docentes da ON d.docente_assistente_id = da.id
     LEFT JOIN salas s ON d.sala_id = s.id
     ORDER BY c.sigla, d.ano_curricular, d.nome")->fetchAll();

$pageTitle = 'Disciplinas';
$pageDescription = 'Disciplinas de cada curso, por ano curricular — cada uma com um docente regente e, opcionalmente, um assistente.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if (!$docentes): ?><div class="alert alert-error">Ainda não há docentes cadastrados. Cria docentes em <strong>Admin → Docentes</strong> antes de conseguires escolher um regente aqui.</div><?php endif; ?>

<section class="card <?= $editar ? 'cartao--em-edicao' : '' ?>" style="margin-bottom:20px">
    <div class="card-header"><div><h2><?= $editar ? 'Editar disciplina' : 'Nova disciplina' ?></h2><p>Curso, ano curricular, carga semanal e o docente responsável.</p></div></div>
    <div class="card-body">
        <form method="post" class="form-row">
            <?= campoCSRF() ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
            <div class="campo"><label>Curso</label>
                <select class="form-control form-select" name="curso_id" required>
                    <option value="">— escolher —</option>
                    <?php foreach ($cursos as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($editar['curso_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['sigla'] . ' — ' . $c['nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label>Código</label>
                <input class="form-control" type="text" name="codigo" value="<?= htmlspecialchars($editar['codigo'] ?? '') ?>"></div>
            <div class="campo"><label>Nome</label>
                <input class="form-control" type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>"></div>
            <div class="campo"><label>Ano curricular</label>
                <input class="form-control" type="number" name="ano_curricular" min="1" max="5" required
                       value="<?= $editar['ano_curricular'] ?? '' ?>"></div>
            <div class="campo"><label>Vezes por semana</label>
                <select class="form-control form-select" name="carga_horaria">
                    <?php foreach ([1, 2] as $n): ?>
                    <option value="<?= $n ?>" <?= (int)($editar['carga_horaria'] ?? 2) === $n ? 'selected' : '' ?>><?= $n ?>x</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label>Tipo</label>
                <select class="form-control form-select" name="tipo_aula" id="disc-tipo-aula">
                    <?php foreach (['Teorica' => 'Teórica', 'Pratica' => 'Prática', 'Laboratorial' => 'Laboratorial'] as $v => $r): ?>
                    <option value="<?= $v ?>" <?= (($editar['tipo_aula'] ?? '') === $v) ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo" id="disc-campo-lab"><label>Laboratório</label>
                <select class="form-control form-select" name="sala_id">
                    <option value="0">— a escolher depois —</option>
                    <?php foreach ($laboratorios as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (($editar['sala_id'] ?? 0) == $s['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nome']) ?> (<?= $s['capacidade'] ?> lugares)</option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$laboratorios): ?><small style="color:var(--ink-soft)">Sem salas do tipo Laboratório — cria uma em <strong>Admin → Salas</strong>.</small><?php endif; ?>
            </div>
            <div class="campo"><label>Docente regente</label>
                <select class="form-control form-select" name="docente_regente_id" required>
                    <option value="">— escolher —</option>
                    <?php foreach ($docentes as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (($editar['docente_regente_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label>Docente assistente (opcional)</label>
                <select class="form-control form-select" name="docente_assistente_id">
                    <option value="0">— nenhum —</option>
                    <?php foreach ($docentes as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (($editar['docente_assistente_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo" style="grid-column:1/-1">
                <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem"><?= $editar ? 'Atualizar' : 'Guardar' ?></button>
                <?php if ($editar): ?><a class="btn btn-secondary" href="disciplinas.php" style="width:auto;padding-inline:2rem;margin-left:8px">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>
</section>

<?php if (!$disciplinas): ?>
    <div class="estado-vazio">
        <p><strong>Ainda não há disciplinas cadastradas.</strong></p>
        <p><?= $cursos ? 'Usa o formulário acima para criar a primeira disciplina.' : 'Cria primeiro um curso, para depois poderes atribuir-lhe disciplinas.' ?></p>
    </div>
<?php else: ?>
<section class="card">
    <div class="toolbar">
        <div class="search-box"><?= icone('search', 18) ?><input type="search" placeholder="Pesquisar disciplina…" data-filtro-tabela="#tabela-disciplinas"></div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table" id="tabela-disciplinas">
            <thead><tr><th>Curso</th><th>Ano</th><th>Nome</th><th>x/semana</th><th>Tipo</th><th>Laboratório</th><th>Regente</th><th>Assistente</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($disciplinas as $d): ?>
            <tr>
                <td><span class="badge badge-info"><?= htmlspecialchars($d['sigla']) ?></span></td>
                <td><?= $d['ano_curricular'] ?>º</td>
                <td><div class="cell-main"><span class="cell-icon"><?= htmlspecialchars(mb_strtoupper(mb_substr($d['nome'], 0, 2))) ?></span><span><strong><?= htmlspecialchars($d['nome']) ?></strong><small><?= htmlspecialchars($d['codigo'] ?? ('Disciplina #' . $d['id'])) ?></small></span></div></td>
                <td><?= $d['carga_horaria'] ?>x</td>
                <td><?= htmlspecialchars($d['tipo_aula']) ?></td>
                <td><?= $d['sala_nome'] ? htmlspecialchars($d['sala_nome']) : '—' ?></td>
                <td><?= $d['regente_nome'] ? htmlspecialchars($d['regente_nome']) : '<span class="badge badge-danger">por definir</span>' ?></td>
                <td><?= $d['assistente_nome'] ? htmlspecialchars($d['assistente_nome']) : '—' ?></td>
                <td>
                    <div class="actions">
                        <a class="btn btn-secondary btn-icon" title="Editar" href="?editar=<?= $d['id'] ?>"><?= icone('edit', 16) ?></a>
                        <form method="post" data-confirmar="Eliminar a disciplina &quot;<?= htmlspecialchars($d['nome']) ?>&quot;?" data-ajax-remover="tr">
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

<script>
(function () {
    var selTipo = document.getElementById('disc-tipo-aula');
    var campoLab = document.getElementById('disc-campo-lab');
    if (!selTipo || !campoLab) { return; }
    function atualizar() { campoLab.style.display = selTipo.value === 'Teorica' ? 'none' : ''; }
    selTipo.addEventListener('change', atualizar);
    atualizar();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
