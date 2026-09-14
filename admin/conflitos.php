<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';
require_once __DIR__ . '/../includes/funcoes_conflitos.php';
require_once __DIR__ . '/../includes/icons.php'; // icone() usada em $pageActions, antes do header

// Vista de leitura, à escala da faculdade: o painel do Administrador
// mostrava a contagem de conflitos pendentes de TODOS os cursos mas não
// havia página onde os pudesse ver — só o Coordenador, e apenas os do
// horário que tinha aberto. Quem resolve continua a ser o coordenador do
// curso (é ele que mexe no horário); esta página serve para o
// Administrador saber onde estão e a quem pedir.

$erro = $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    if (isset($_POST['revalidar_tudo'])) {
        // Revalida todos os horários não arquivados — útil depois de mexer
        // em salas/disciplinas, que podem criar ou resolver conflitos sem
        // que ninguém tenha aberto o editor.
        $horarios = $pdo->query("SELECT id FROM horarios WHERE estado != 'Arquivado'")->fetchAll();
        $total = 0;
        foreach ($horarios as $h) { $total += revalidarHorario($pdo, (int)$h['id']); }
        definirFlash('ok', count($horarios) . ' horário(s) revalidado(s) — ' . $total . ' conflito(s) pendente(s).');
        header('Location: conflitos.php'); exit;
    }
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$conflitos = $pdo->query(
    "SELECT co.id, co.tipo, co.detetado_em,
            a1.dia_semana, a1.hora_inicio, a1.hora_fim,
            d1.nome AS disc1, d2.nome AS disc2,
            c.sigla AS curso_sigla, c.nome AS curso_nome, c.id AS curso_id,
            t.ano_curricular, t.nome_turma, t.regime,
            h.id AS horario_id, h.estado AS horario_estado,
            GROUP_CONCAT(DISTINCT u.nome ORDER BY u.nome SEPARATOR ', ') AS coordenadores
     FROM conflitos co
     JOIN aulas a1 ON co.aula_id_1 = a1.id
     JOIN aulas a2 ON co.aula_id_2 = a2.id
     JOIN horarios h ON a1.horario_id = h.id
     JOIN turmas t ON h.turma_id = t.id
     JOIN cursos c ON t.curso_id = c.id
     LEFT JOIN disciplinas d1 ON a1.disciplina_id = d1.id
     LEFT JOIN disciplinas d2 ON a2.disciplina_id = d2.id
     LEFT JOIN coordenador_curso cc ON cc.curso_id = c.id
     LEFT JOIN utilizadores u ON u.id = cc.utilizador_id AND u.ativo = 1
     WHERE co.estado = 'Pendente'
     GROUP BY co.id
     ORDER BY c.sigla, t.ano_curricular,
              FIELD(a1.dia_semana,'Segunda','Terca','Quarta','Quinta','Sexta'), a1.hora_inicio")
    ->fetchAll();

// Agrupa por curso para o Administrador ver logo onde está o problema
$porCurso = [];
foreach ($conflitos as $c) { $porCurso[$c['curso_sigla']][] = $c; }

$nHorarios = (int)$pdo->query("SELECT COUNT(*) FROM horarios WHERE estado != 'Arquivado'")->fetchColumn();
$nCursosAfetados = count($porCurso);

$pageTitle = 'Conflitos';
$pageDescription = 'Todos os conflitos pendentes da faculdade, agrupados por curso.';
$pageActions = '<form method="post" style="display:inline">' . campoCSRF()
    . '<button type="submit" name="revalidar_tudo" value="1" class="btn btn-secondary">'
    . icone('alert', 17) . ' Revalidar todos os horários</button></form>';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="grid stats-grid" style="margin-bottom:20px">
    <article class="stat-card <?= $conflitos ? 'accent-danger' : '' ?>">
        <div class="stat-card-top"><span class="stat-icon"><?= icone('alert') ?></span></div>
        <div class="stat-value"><?= count($conflitos) ?></div>
        <div class="stat-label">Conflitos pendentes</div>
    </article>
    <article class="stat-card accent-teal">
        <div class="stat-card-top"><span class="stat-icon"><?= icone('book') ?></span></div>
        <div class="stat-value"><?= $nCursosAfetados ?></div>
        <div class="stat-label">Cursos afetados</div>
    </article>
    <article class="stat-card accent-purple">
        <div class="stat-card-top"><span class="stat-icon"><?= icone('calendar') ?></span></div>
        <div class="stat-value"><?= $nHorarios ?></div>
        <div class="stat-label">Horários ativos</div>
    </article>
</div>

<?php if (!$conflitos): ?>
    <div class="estado-vazio">
        <p><strong>Não há conflitos pendentes em nenhum curso.</strong></p>
        <p>Se mexeste há pouco em salas ou disciplinas, usa <strong>Revalidar todos os horários</strong> para confirmar.</p>
    </div>
<?php else: ?>
    <?php foreach ($porCurso as $sigla => $lista): ?>
    <section class="card" style="margin-bottom:18px">
        <div class="card-header">
            <div>
                <h2><?= htmlspecialchars($sigla) ?> — <?= htmlspecialchars($lista[0]['curso_nome']) ?></h2>
                <p>Coordenação: <?= $lista[0]['coordenadores'] ? htmlspecialchars($lista[0]['coordenadores']) : 'sem coordenador ativo atribuído' ?></p>
            </div>
            <span class="badge badge-danger"><span class="status-dot"></span><?= count($lista) ?> pendente(s)</span>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead><tr><th>Turma</th><th>Quando</th><th>Tipo</th><th>Aula</th><th>Em conflito com</th><th>Detetado</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $c): ?>
                <tr>
                    <td><?= $c['ano_curricular'] ?>º <?= htmlspecialchars($c['nome_turma']) ?><br><small style="color:var(--ink-soft)"><?= htmlspecialchars($c['regime']) ?></small></td>
                    <td><?= htmlspecialchars($c['dia_semana']) ?><br><small style="color:var(--ink-soft)"><?= substr($c['hora_inicio'],0,5) ?>–<?= substr($c['hora_fim'],0,5) ?></small></td>
                    <td><span class="badge badge-warning"><?= htmlspecialchars($c['tipo']) ?></span></td>
                    <td><?= htmlspecialchars($c['disc1'] ?? 'bloco sem disciplina') ?></td>
                    <td><?= htmlspecialchars($c['disc2'] ?? 'bloco sem disciplina') ?></td>
                    <td><small style="color:var(--ink-soft)"><?= htmlspecialchars(substr($c['detetado_em'], 0, 16)) ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
