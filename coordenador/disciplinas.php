<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';

// Tudo nesta página é do curso em sessão — e exigirCursoDoCoordenador() já
// garante que esse curso é mesmo de quem está autenticado. O curso NUNCA vem
// do formulário: um coordenador não pode criar nem mexer em disciplinas de um
// curso que não gere, mesmo forjando o POST.
$curso = exigirCursoDoCoordenador($pdo);
$cursoId = (int)$curso['id'];

$erro = $ok = null; $editar = null;

/* Confirma que a disciplina existe E pertence ao curso em sessão. Usada antes
   de qualquer edição/eliminação — é o que impede alterar a disciplina de outro
   curso apenas trocando o id no pedido. */
function disciplinaDoCurso(PDO $pdo, int $id, int $cursoId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM disciplinas WHERE id = ? AND curso_id = ?");
    $stmt->execute([$id, $cursoId]);
    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    if (isset($_POST['eliminar'])) {
        $elimId = (int)$_POST['eliminar'];
        if (!disciplinaDoCurso($pdo, $elimId, $cursoId)) {
            $sucesso = false; $mensagem = 'Essa disciplina não pertence ao curso que estás a gerir.';
        } else {
            try {
                // "Pode lecionar" é só uma lista de aptidão (admin/docentes.php),
                // não um registo de horário — limpar aqui não perde nada real.
                $pdo->prepare("DELETE FROM docente_disciplina WHERE disciplina_id = ?")->execute([$elimId]);
                $pdo->prepare("DELETE FROM disciplinas WHERE id = ?")->execute([$elimId]);
                $sucesso = true; $mensagem = 'Disciplina eliminada.';
            } catch (PDOException $e) {
                $sucesso = false; $mensagem = 'Não é possível eliminar: esta disciplina já tem aulas marcadas em algum horário.';
            }
        }
        if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem); }
        definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
        header('Location: disciplinas.php'); exit;
    }

    $codigo = trim($_POST['codigo'] ?? '');
    $nome   = trim($_POST['nome'] ?? '');
    $ano    = (int)($_POST['ano_curricular'] ?? 0);
    // RN09 — carga_horaria é sempre "vezes por semana", 1 ou 2.
    $carga  = max(1, min(2, (int)($_POST['carga_horaria'] ?? 2)));
    $tipo   = in_array($_POST['tipo_aula'] ?? '', ['Teorica','Pratica','Laboratorial'], true)
        ? $_POST['tipo_aula'] : 'Teorica';
    // RN16 — laboratório só faz sentido em disciplinas não-teóricas.
    $salaId = $tipo !== 'Teorica' ? ((int)($_POST['sala_id'] ?? 0) ?: null) : null;
    $regenteId    = (int)($_POST['docente_regente_id'] ?? 0) ?: null;
    $assistenteId = (int)($_POST['docente_assistente_id'] ?? 0) ?: null;
    $id     = (int)($_POST['id'] ?? 0);

    if ($id > 0 && !disciplinaDoCurso($pdo, $id, $cursoId)) {
        $erro = "Essa disciplina não pertence ao curso que estás a gerir.";
    } elseif ($nome === '' || $ano < 1 || $ano > 5) {
        $erro = "Preenche o nome e um ano curricular válido (1-5).";
    } elseif (!$regenteId) {
        $erro = "Escolhe o docente regente da disciplina.";
    } elseif ($assistenteId && $assistenteId === $regenteId) {
        $erro = "O assistente tem de ser um docente diferente do regente.";
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE disciplinas SET codigo=?, nome=?, ano_curricular=?, carga_horaria=?,
                    tipo_aula=?, sala_id=?, docente_regente_id=?, docente_assistente_id=?
                 WHERE id=? AND curso_id=?");
            $stmt->execute([$codigo, $nome, $ano, $carga, $tipo, $salaId, $regenteId, $assistenteId, $id, $cursoId]);
            definirFlash('ok', 'Disciplina atualizada.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO disciplinas (curso_id, codigo, nome, ano_curricular, carga_horaria,
                    tipo_aula, sala_id, docente_regente_id, docente_assistente_id)
                 VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$cursoId, $codigo, $nome, $ano, $carga, $tipo, $salaId, $regenteId, $assistenteId]);
            definirFlash('ok', 'Disciplina criada.');
        }
        header('Location: disciplinas.php'); exit;
    }
}

if (isset($_GET['editar'])) {
    $editar = disciplinaDoCurso($pdo, (int)$_GET['editar'], $cursoId);
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$docentes = $pdo->query("SELECT * FROM docentes ORDER BY nome")->fetchAll();
$laboratorios = $pdo->query("SELECT * FROM salas WHERE tipo = 'Laboratorio' ORDER BY nome")->fetchAll();

$stmt = $pdo->prepare(
    "SELECT d.*, dr.nome AS regente_nome, da.nome AS assistente_nome, s.nome AS sala_nome
     FROM disciplinas d
     LEFT JOIN docentes dr ON d.docente_regente_id = dr.id
     LEFT JOIN docentes da ON d.docente_assistente_id = da.id
     LEFT JOIN salas s ON d.sala_id = s.id
     WHERE d.curso_id = ?
     ORDER BY d.ano_curricular, d.nome");
$stmt->execute([$cursoId]);
$disciplinas = $stmt->fetchAll();

$pageTitle = 'Disciplinas';
$pageDescription = $curso['nome'] . ' (' . $curso['sigla'] . ') — disciplinas do curso e os docentes que as lecionam.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if (!$docentes): ?><div class="alert alert-error">Ainda não há docentes cadastrados. O Administrador tem de os criar em <strong>Docentes</strong> antes de poderes escolher um regente.</div><?php endif; ?>

<section class="card <?= $editar ? 'cartao--em-edicao' : '' ?>" style="margin-bottom:20px">
    <div class="card-header"><div><h2><?= $editar ? 'Editar disciplina' : 'Nova disciplina' ?></h2><p>Fica sempre no curso que estás a gerir (<?= htmlspecialchars($curso['sigla']) ?>). Para outro curso, muda em <strong>Os meus cursos</strong>.</p></div></div>
    <div class="card-body">
        <form method="post" class="form-row">
            <?= campoCSRF() ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
            <div class="campo"><label>Nome da disciplina</label>
                <input class="form-control" type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>"></div>
            <div class="campo"><label>Código</label>
                <input class="form-control" type="text" name="codigo" value="<?= htmlspecialchars($editar['codigo'] ?? '') ?>"></div>
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
                <?php if (!$laboratorios): ?><small style="color:var(--ink-soft)">Sem salas do tipo Laboratório — o Administrador cria-as em <strong>Salas</strong>.</small><?php endif; ?>
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
        <p><strong>Este curso ainda não tem disciplinas.</strong></p>
        <p>Usa o formulário acima para criar a primeira — depois já podes montar o horário no editor.</p>
    </div>
<?php else: ?>
<section class="card">
    <div class="toolbar">
        <div class="search-box"><?= icone('search', 18) ?><input type="search" placeholder="Pesquisar disciplina…" data-filtro-tabela="#tabela-disciplinas"></div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table" id="tabela-disciplinas">
            <thead><tr><th>Ano</th><th>Disciplina</th><th>x/semana</th><th>Tipo</th><th>Laboratório</th><th>Regente</th><th>Assistente</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($disciplinas as $d): ?>
            <tr>
                <td><?= $d['ano_curricular'] ?>º</td>
                <td><div class="cell-main"><span class="cell-icon"><?= htmlspecialchars(mb_strtoupper(mb_substr($d['nome'], 0, 2))) ?></span><span><strong><?= htmlspecialchars($d['nome']) ?></strong><small><?= htmlspecialchars($d['codigo'] ?: ('Disciplina #' . $d['id'])) ?></small></span></div></td>
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
