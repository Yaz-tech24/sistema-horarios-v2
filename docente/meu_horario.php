<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Docente']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php'; // DIAS_SEMANA, BLOCOS_HORARIOS
require_once __DIR__ . '/../includes/icons.php';

$erro = $ok = null;

$stmt = $pdo->prepare("SELECT id, nome FROM docentes WHERE utilizador_id = ?");
$stmt->execute([$_SESSION['utilizador_id']]);
$docente = $stmt->fetch();

// Pedido do docente ao coordenador sobre uma aula concreta do seu horário.
// A referência em texto é construída aqui a partir da aula — nunca vem do
// formulário — e fica guardada para o pedido continuar legível mesmo que a
// aula seja removida mais tarde.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $docente) {
    validarCSRF();
    $aulaId = (int)($_POST['aula_id'] ?? 0);
    $mensagem = trim($_POST['mensagem'] ?? '');

    $stmt = $pdo->prepare(
        "SELECT a.id, a.horario_id, a.dia_semana, LEFT(a.hora_inicio,5) AS hi, LEFT(a.hora_fim,5) AS hf,
                d.nome AS disciplina, c.sigla AS curso, t.ano_curricular
         FROM aulas a
         JOIN horarios h ON a.horario_id = h.id
         JOIN turmas t ON h.turma_id = t.id
         JOIN cursos c ON t.curso_id = c.id
         LEFT JOIN disciplinas d ON a.disciplina_id = d.id
         WHERE a.id = ? AND (a.docente_id = ? OR a.docente_regente_id = ? OR a.docente_assistente_id = ?)");
    $stmt->execute([$aulaId, $docente['id'], $docente['id'], $docente['id']]);
    $aulaPedido = $stmt->fetch();

    if (!$aulaPedido) {
        definirFlash('erro', 'Essa aula não é tua.');
    } elseif (mb_strlen($mensagem) < 10) {
        definirFlash('erro', 'Explica o problema em pelo menos 10 caracteres, para o coordenador perceber.');
    } else {
        $referencia = $aulaPedido['dia_semana'] . ' ' . $aulaPedido['hi'] . '–' . $aulaPedido['hf']
            . ' · ' . ($aulaPedido['disciplina'] ?? 'bloco')
            . ' (' . $aulaPedido['curso'] . ' ' . $aulaPedido['ano_curricular'] . 'º)';
        $pdo->prepare(
            "INSERT INTO pedidos (utilizador_id, horario_id, aula_id, referencia, mensagem)
             VALUES (?,?,?,?,?)")
            ->execute([$_SESSION['utilizador_id'], $aulaPedido['horario_id'], $aulaPedido['id'],
                       mb_substr($referencia, 0, 160), mb_substr($mensagem, 0, 500)]);
        definirFlash('ok', 'Pedido enviado ao coordenador do curso.');
    }
    header('Location: meu_horario.php'); exit;
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$aulas = [];
$horas = 0;
if ($docente) {
    // O docente vê as suas aulas tanto nas que é regente como nas que é
    // assistente (docente_id é mantido em espelho de docente_regente_id —
    // ver nota em includes/funcoes_coordenador.php — daí verificar os três).
    $dias = "'Segunda','Terca','Quarta','Quinta','Sexta'";
    $stmt = $pdo->prepare(
        "SELECT a.*, d.nome AS disciplina, s.nome AS sala,
                c.sigla AS curso, t.ano_curricular, t.regime,
                dr.nome AS regente_nome, da.nome AS assistente_nome,
                (CASE WHEN a.docente_assistente_id = ? THEN 'Assistente' ELSE 'Regente' END) AS papel
         FROM aulas a
         JOIN horarios h ON a.horario_id = h.id
         JOIN turmas t ON h.turma_id = t.id
         JOIN cursos c ON t.curso_id = c.id
         LEFT JOIN disciplinas d ON a.disciplina_id = d.id
         LEFT JOIN salas s ON a.sala_id = s.id
         LEFT JOIN docentes dr ON a.docente_regente_id = dr.id
         LEFT JOIN docentes da ON a.docente_assistente_id = da.id
         WHERE (a.docente_id = ? OR a.docente_regente_id = ? OR a.docente_assistente_id = ?)
           AND h.estado = 'Publicado'
         ORDER BY FIELD(a.dia_semana, $dias), a.hora_inicio");
    $stmt->execute([$docente['id'], $docente['id'], $docente['id'], $docente['id']]);
    $aulas = $stmt->fetchAll();
    foreach ($aulas as $a) {
        $horas += (strtotime($a['hora_fim']) - strtotime($a['hora_inicio'])) / 3600;
    }
}

// Grelha por dia/hora — cobre os 9 blocos do dia porque o docente pode
// lecionar em turmas de turnos diferentes (Manhã/Tarde/Noite).
$grelha = [];
foreach ($aulas as $a) { $grelha[$a['dia_semana']][substr($a['hora_inicio'],0,5)][] = $a; }

// Pedidos que este docente já enviou, para ver em que estado ficaram.
$meusPedidos = [];
if ($docente) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE utilizador_id = ? ORDER BY criado_em DESC LIMIT 20");
    $stmt->execute([$_SESSION['utilizador_id']]);
    $meusPedidos = $stmt->fetchAll();
}

$pageTitle = 'Meu horário';
$pageDescription = 'Todas as tuas aulas publicadas, em todos os cursos onde lecionas.' . ($aulas ? ' Carga semanal: ' . number_format($horas, 1) . ' horas.' : '');
$pageActions = $aulas ? '<button class="btn btn-secondary" type="button" onclick="window.print()">' . icone('printer', 17) . ' Imprimir horário</button>' : '';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<?php if (!$docente): ?>
    <div class="alert alert-error">A tua conta ainda não está ligada a um registo de docente.
        Pede ao Administrador para fazer a ligação em Utilizadores e Permissões.</div>
<?php elseif (!$aulas): ?>
    <div class="alert alert-success">Ainda não tens aulas em horários publicados.</div>
<?php else: ?>

<div class="grid stats-grid" style="margin-bottom:20px">
    <article class="stat-card"><div class="stat-card-top"><span class="stat-icon"><?= icone('calendar') ?></span></div><div class="stat-value"><?= count($aulas) ?></div><div class="stat-label">Aulas por semana</div></article>
    <article class="stat-card accent-teal"><div class="stat-card-top"><span class="stat-icon"><?= icone('book') ?></span></div><div class="stat-value"><?= count(array_unique(array_column($aulas, 'disciplina_id'))) ?></div><div class="stat-label">Disciplinas</div></article>
    <article class="stat-card accent-purple"><div class="stat-card-top"><span class="stat-icon"><?= icone('users') ?></span></div><div class="stat-value"><?= count(array_unique(array_map(fn($a) => $a['curso'] . $a['ano_curricular'] . $a['regime'], $aulas))) ?></div><div class="stat-label">Turmas</div></article>
    <article class="stat-card accent-orange"><div class="stat-card-top"><span class="stat-icon"><?= icone('clock') ?></span></div><div class="stat-value"><?= number_format($horas, 1) ?></div><div class="stat-label">Horas semanais</div></article>
</div>

<div class="tabela-grelha-scroll">
<table class="tabela-grelha">
    <thead><tr><th class="col-hora">Hora</th><?php foreach (DIAS_SEMANA as $d): ?><th><?= $d ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach (BLOCOS_HORARIOS as [$hi, $hf]): ?>
    <tr>
        <td class="col-hora"><strong><?= $hi ?></strong><?= $hf ?></td>
        <?php foreach (DIAS_SEMANA as $d): ?>
        <td>
            <?php foreach (($grelha[$d][$hi] ?? []) as $a):
                $colega = $a['papel'] === 'Assistente' ? ($a['regente_nome'] ?? null) : ($a['assistente_nome'] ?? null);
            ?>
                <div class="bloco-grelha bloco-aula">
                    <strong><?= htmlspecialchars($a['disciplina'] ?? '—') ?></strong>
                    <?= $a['subgrupo'] ? '<em>('.htmlspecialchars($a['subgrupo']).')</em>' : '' ?>
                    <small><?= htmlspecialchars($a['curso']) ?> <?= $a['ano_curricular'] ?>º · <?= htmlspecialchars($a['papel']) ?></small>
                    <small><?= htmlspecialchars($a['sala'] ?? '—') ?><?php if ($colega): ?> · com <?= htmlspecialchars($colega) ?><?php endif; ?></small>
                </div>
            <?php endforeach; ?>
        </td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="grid two-column" style="margin-top:20px">
    <section class="card">
        <div class="card-header"><div><h2>Assinalar um problema</h2><p>Vai direto ao coordenador do curso dessa aula.</p></div></div>
        <div class="card-body">
            <form method="post">
                <?= campoCSRF() ?>
                <div class="campo" style="margin-bottom:12px">
                    <label for="p-aula">Aula</label>
                    <select class="form-control form-select" name="aula_id" id="p-aula" required>
                        <option value="">— escolher —</option>
                        <?php foreach ($aulas as $a): ?>
                        <option value="<?= $a['id'] ?>">
                            <?= htmlspecialchars($a['dia_semana']) ?> <?= substr($a['hora_inicio'],0,5) ?> ·
                            <?= htmlspecialchars($a['disciplina'] ?? 'bloco') ?>
                            (<?= htmlspecialchars($a['curso']) ?> <?= $a['ano_curricular'] ?>º)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo" style="margin-bottom:12px">
                    <label for="p-msg">O que se passa</label>
                    <textarea class="form-control" name="mensagem" id="p-msg" rows="4" minlength="10" maxlength="500" required
                              placeholder="Ex.: esta aula choca com a minha orientação de estágio às quartas."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem">Enviar ao coordenador</button>
            </form>
        </div>
    </section>

    <aside class="card">
        <div class="card-header"><div><h2>Os teus pedidos</h2></div></div>
        <?php if (!$meusPedidos): ?>
            <div class="card-body"><p style="margin:0;color:var(--ink-soft);font-size:.85rem">Ainda não enviaste nenhum pedido.</p></div>
        <?php else: ?>
        <div class="notification-list">
            <?php foreach ($meusPedidos as $mp): $aberto = $mp['estado'] === 'Aberto'; ?>
            <div class="notification-item <?= $aberto ? 'unread' : '' ?>">
                <div class="notification-content">
                    <h3><?= htmlspecialchars($mp['referencia']) ?></h3>
                    <p><?= htmlspecialchars($mp['mensagem']) ?></p>
                    <span class="badge <?= $aberto ? 'badge-warning' : 'badge-success' ?>"><span class="status-dot"></span><?= htmlspecialchars($mp['estado']) ?></span>
                    <time style="display:block;margin-top:5px"><?= htmlspecialchars(substr($mp['criado_em'], 0, 16)) ?></time>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </aside>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
