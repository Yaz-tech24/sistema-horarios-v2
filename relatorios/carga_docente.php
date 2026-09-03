<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador','Coordenador']);

// Conta como carga do docente tanto as aulas em que é regente como aquelas
// em que é assistente — as duas são trabalho real dessa semana.
$carga = $pdo->query(
    "SELECT doc.nome, doc.categoria,
            COUNT(a.id) AS aulas_semanais,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.hora_inicio, a.hora_fim)),0)/60 AS horas,
            COUNT(DISTINCT t.curso_id) AS n_cursos
     FROM docentes doc
     LEFT JOIN aulas a ON (a.docente_regente_id = doc.id OR a.docente_assistente_id = doc.id) AND a.tipo_bloco = 'Aula'
     LEFT JOIN horarios h ON a.horario_id = h.id AND h.estado != 'Arquivado'
     LEFT JOIN turmas t ON h.turma_id = t.id
     GROUP BY doc.id ORDER BY horas DESC, doc.nome")->fetchAll();

$maxHoras = 0; $somaHoras = 0;
foreach ($carga as $c) { $maxHoras = max($maxHoras, (float)$c['horas']); $somaHoras += (float)$c['horas']; }
$media = $carga ? $somaHoras / count($carga) : 0;

$pageTitle = 'Carga docente';
$pageDescription = 'Horas semanais por docente, somando TODOS os cursos — a visão que nenhum ficheiro Excel isolado conseguia mostrar.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$carga): ?>
    <div class="estado-vazio">
        <p><strong>Ainda não há docentes cadastrados.</strong></p>
        <p>Cria docentes em <strong>Admin → Docentes</strong> para veres aqui a carga horária.</p>
    </div>
<?php else: ?>

<div class="grid stats-grid" style="margin-bottom:20px">
    <article class="stat-card"><div class="stat-card-top"><span class="stat-icon"><?= icone('users') ?></span></div><div class="stat-value"><?= count($carga) ?></div><div class="stat-label">Docentes cadastrados</div></article>
    <article class="stat-card accent-teal"><div class="stat-card-top"><span class="stat-icon"><?= icone('chart') ?></span></div><div class="stat-value"><?= number_format($media, 1) ?></div><div class="stat-label">Média semanal (horas)</div></article>
    <article class="stat-card accent-purple"><div class="stat-card-top"><span class="stat-icon"><?= icone('clock') ?></span></div><div class="stat-value"><?= number_format($maxHoras, 1) ?></div><div class="stat-label">Carga máxima (horas)</div></article>
</div>

<section class="card">
    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr><th>Docente</th><th>Categoria</th><th>Aulas/semana</th><th>Cursos</th><th>Horas/semana</th></tr></thead>
            <tbody>
            <?php foreach ($carga as $c): $pct = $maxHoras > 0 ? round((float)$c['horas'] / $maxHoras * 100) : 0; ?>
            <tr>
                <td><div class="cell-main"><span class="cell-icon"><?= htmlspecialchars(mb_strtoupper(mb_substr($c['nome'], 0, 2))) ?></span><span><strong><?= htmlspecialchars($c['nome']) ?></strong></span></div></td>
                <td><?= htmlspecialchars($c['categoria'] ?? '—') ?></td>
                <td><?= $c['aulas_semanais'] ?></td>
                <td><?= $c['n_cursos'] ?></td>
                <td style="min-width:180px">
                    <div class="progress"><span style="width:<?= $c['horas'] > 0 ? max($pct, 3) : 0 ?>%"></span></div>
                    <small style="color:var(--ink-soft)"><?= number_format($c['horas'], 1) ?>h</small>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
