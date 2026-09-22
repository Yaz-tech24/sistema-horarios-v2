<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';
require_once __DIR__ . '/../includes/funcoes_conflitos.php';
require_once __DIR__ . '/../includes/icons.php';

$curso = exigirCursoDoCoordenador($pdo);
$horarioId = (int)($_SESSION['horario_id_atual'] ?? 0);
$ok = null;

if (!$horarioId) {
    header('Location: ' . BASE_URL . '/coordenador/editor_horario.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revalidar'])) {
    validarCSRF();
    $n = revalidarHorario($pdo, $horarioId);
    $ok = $n === 0 ? "Revalidação concluída: nenhum conflito encontrado."
                   : "Revalidação concluída: $n conflito(s) registado(s).";
}

$conflitos = conflitosPendentes($pdo, $horarioId);

$stmt = $pdo->prepare(
    "SELECT co.*, d1.nome AS disc1, d2.nome AS disc2,
            a1.dia_semana, a1.hora_inicio, a1.hora_fim
     FROM conflitos co
     JOIN aulas a1 ON co.aula_id_1 = a1.id
     JOIN aulas a2 ON co.aula_id_2 = a2.id
     LEFT JOIN disciplinas d1 ON a1.disciplina_id = d1.id
     LEFT JOIN disciplinas d2 ON a2.disciplina_id = d2.id
     WHERE a1.horario_id = ? AND co.estado = 'Resolvido'
     ORDER BY co.detetado_em DESC LIMIT 20");
// Conflitos pendentes de antes desta coluna existir ainda não têm
// mensagem detalhada gravada (só é preenchida quando o conflito é
// detetado de novo) — mostra uma descrição genérica nesse caso.
$descricaoGenerica = fn(array $c) => "Em conflito com \"" . ($c['disc2'] ?? 'outra aula') . "\".";
$stmt->execute([$horarioId]);
$resolvidos = $stmt->fetchAll();

$iconesTipo = ['Docente' => 'users', 'Sala' => 'door', 'Turma' => 'users', 'Pastoral' => 'alert', 'Carga' => 'calendar'];

$pageTitle = 'Conflitos';
$pageDescription = 'Conflitos bloqueantes pendentes do horário de ' . $curso['sigla'] . '. Resolve-os no Editor e revalida.';
$pageActions = '<form method="post">' . campoCSRF() . '<button type="submit" name="revalidar" value="1" class="btn btn-primary">' . icone('history', 17) . ' Revalidar horário</button></form>';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="grid stats-grid" style="margin-bottom:20px">
    <article class="stat-card"><div class="stat-card-top"><span class="stat-icon"><?= icone('alert') ?></span></div><div class="stat-value"><?= count($conflitos) ?></div><div class="stat-label">Conflitos pendentes</div></article>
    <article class="stat-card accent-teal"><div class="stat-card-top"><span class="stat-icon"><?= icone('check') ?></span></div><div class="stat-value"><?= count($resolvidos) ?></div><div class="stat-label">Resolvidos recentemente</div></article>
</div>

<section class="card">
    <div class="card-header"><div><h2>Pendentes</h2><p>Bloqueiam a publicação (RN15) enquanto não forem corrigidos.</p></div><?php if ($conflitos): ?><span class="badge badge-danger"><span class="status-dot"></span><?= count($conflitos) ?> pendente(s)</span><?php endif; ?></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
        <?php if (!$conflitos): ?>
            <p style="margin:0;color:var(--ink-soft);font-size:.85rem">Sem conflitos pendentes registados.</p>
        <?php else: foreach ($conflitos as $c): ?>
            <div class="conflict-card">
                <span class="conflict-icon"><?= icone($iconesTipo[$c['tipo']] ?? 'alert') ?></span>
                <div>
                    <h3><?= htmlspecialchars($c['tipo']) ?> — <?= htmlspecialchars($c['disc1'] ?? 'Aula') ?></h3>
                    <p><?= htmlspecialchars($c['mensagem'] ?? $descricaoGenerica($c)) ?></p>
                    <div class="conflict-meta">
                        <span class="badge badge-info"><?= htmlspecialchars($c['dia_semana']) ?></span>
                        <span class="badge badge-info"><?= substr($c['hora_inicio'],0,5) ?>–<?= substr($c['hora_fim'],0,5) ?></span>
                        <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/coordenador/editor_horario.php">Corrigir no editor</a>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</section>

<?php if ($resolvidos): ?>
<section class="card" style="margin-top:20px">
    <div class="card-header"><div><h2>Resolvidos recentemente</h2><p>Últimos 20 conflitos que deixaram de ser detetados.</p></div></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ($resolvidos as $c): ?>
            <div class="conflict-card resolvido">
                <span class="conflict-icon"><?= icone($iconesTipo[$c['tipo']] ?? 'check') ?></span>
                <div>
                    <h3><?= htmlspecialchars($c['tipo']) ?> — <?= htmlspecialchars($c['disc1'] ?? 'Aula') ?></h3>
                    <p><?= htmlspecialchars($c['mensagem'] ?? $descricaoGenerica($c)) ?></p>
                    <div class="conflict-meta">
                        <span class="badge badge-success"><span class="status-dot"></span>Resolvido</span>
                        <span class="badge badge-info"><?= htmlspecialchars($c['dia_semana']) ?></span>
                        <span class="badge badge-info"><?= substr($c['hora_inicio'],0,5) ?>–<?= substr($c['hora_fim'],0,5) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
