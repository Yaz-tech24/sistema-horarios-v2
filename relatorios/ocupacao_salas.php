<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador','Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php'; // DIAS_SEMANA, BLOCOS_HORARIOS

// Calculado a partir das constantes (nunca hardcoded) para nunca desalinhar
// se os dias letivos ou os blocos horários por dia mudarem no futuro.
$TOTAL_BLOCOS = count(DIAS_SEMANA) * count(BLOCOS_HORARIOS);

$ocupacao = $pdo->query(
    "SELECT s.nome, s.capacidade, COUNT(a.id) AS blocos
     FROM salas s
     LEFT JOIN aulas a ON a.sala_id = s.id AND a.tipo_bloco = 'Aula'
     LEFT JOIN horarios h ON a.horario_id = h.id AND h.estado != 'Arquivado'
     GROUP BY s.id ORDER BY blocos DESC, s.nome")->fetchAll();

$mediaOcupacao = 0; $salaMaisUsada = null;
if ($ocupacao) {
    $somaPct = 0;
    foreach ($ocupacao as $o) { $somaPct += $o['blocos'] / $TOTAL_BLOCOS * 100; }
    $mediaOcupacao = $somaPct / count($ocupacao);
    $salaMaisUsada = $ocupacao[0];
}

$pageTitle = 'Ocupação de salas';
$pageDescription = 'Blocos ocupados por sala, sobre um máximo de ' . $TOTAL_BLOCOS . ' blocos semanais (' . count(DIAS_SEMANA) . ' dias × ' . count(BLOCOS_HORARIOS) . ' blocos).';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$ocupacao): ?>
    <div class="estado-vazio">
        <p><strong>Ainda não há salas cadastradas.</strong></p>
        <p>Cria salas em <strong>Admin → Salas</strong> para veres aqui a ocupação.</p>
    </div>
<?php else: ?>

<div class="grid stats-grid" style="margin-bottom:20px">
    <article class="stat-card"><div class="stat-card-top"><span class="stat-icon"><?= icone('building') ?></span></div><div class="stat-value"><?= count($ocupacao) ?></div><div class="stat-label">Salas cadastradas</div></article>
    <article class="stat-card accent-teal"><div class="stat-card-top"><span class="stat-icon"><?= icone('chart') ?></span></div><div class="stat-value"><?= number_format($mediaOcupacao, 0) ?>%</div><div class="stat-label">Ocupação média</div></article>
    <article class="stat-card accent-purple"><div class="stat-card-top"><span class="stat-icon"><?= icone('door') ?></span></div><div class="stat-value"><?= htmlspecialchars($salaMaisUsada['nome']) ?></div><div class="stat-label">Sala mais utilizada</div></article>
    <article class="stat-card accent-orange"><div class="stat-card-top"><span class="stat-icon"><?= icone('calendar') ?></span></div><div class="stat-value"><?= $TOTAL_BLOCOS ?></div><div class="stat-label">Blocos/semana no máximo</div></article>
</div>

<section class="card">
    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr><th>Sala</th><th>Capacidade</th><th>Blocos ocupados</th><th>Ocupação</th></tr></thead>
            <tbody>
            <?php foreach ($ocupacao as $o): $pct = round($o['blocos'] / $TOTAL_BLOCOS * 100); ?>
            <tr>
                <td><div class="cell-main"><span class="cell-icon"><?= icone('door', 16) ?></span><span><strong><?= htmlspecialchars($o['nome']) ?></strong></span></div></td>
                <td><?= $o['capacidade'] ?></td>
                <td><?= $o['blocos'] ?>/<?= $TOTAL_BLOCOS ?></td>
                <td style="min-width:180px">
                    <div class="progress"><span style="width:<?= $pct ?>%"></span></div>
                    <small style="color:var(--ink-soft)"><?= $pct ?>%</small>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
