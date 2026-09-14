<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador','Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php'; // DIAS_SEMANA, BLOCOS_HORARIOS

// Grelha semanal de ocupação das salas. Antes desta página só se
// descobria que uma sala estava ocupada ao tentar gravar a aula e bater
// no conflito (RN02) — este é o mesmo dado, mas visto antes de decidir.
// Conta apenas horários não arquivados, tal como a deteção de conflitos.

$filtroTipo = $_GET['tipo'] ?? '';
$tiposValidos = ['Normal','Laboratorio','Auditorio'];
if (!in_array($filtroTipo, $tiposValidos, true)) { $filtroTipo = ''; }

$sqlSalas = "SELECT * FROM salas" . ($filtroTipo ? " WHERE tipo = ?" : "") . " ORDER BY tipo, nome";
$stmt = $pdo->prepare($sqlSalas);
$stmt->execute($filtroTipo ? [$filtroTipo] : []);
$salas = $stmt->fetchAll();

// Ocupação: uma entrada por (sala, dia, bloco). Só aulas reais ocupam a
// sala — blocos de Estudo Autónomo/Atividade não têm sala atribuída.
$ocupacao = [];
$stmt = $pdo->query(
    "SELECT a.sala_id, a.dia_semana, LEFT(a.hora_inicio,5) AS hi, LEFT(a.hora_fim,5) AS hf,
            d.nome AS disciplina, c.sigla AS curso, t.ano_curricular, t.nome_turma
     FROM aulas a
     JOIN horarios h ON a.horario_id = h.id
     JOIN turmas t ON h.turma_id = t.id
     JOIN cursos c ON t.curso_id = c.id
     LEFT JOIN disciplinas d ON a.disciplina_id = d.id
     WHERE a.sala_id IS NOT NULL AND h.estado != 'Arquivado'");
foreach ($stmt->fetchAll() as $o) {
    $ocupacao[$o['sala_id']][$o['dia_semana'] . '|' . $o['hi'] . '|' . $o['hf']] = $o;
}

$totalSlots = count($salas) * count(DIAS_SEMANA) * count(BLOCOS_HORARIOS);
$totalOcupados = 0;
foreach ($salas as $s) { $totalOcupados += count($ocupacao[$s['id']] ?? []); }
$totalLivres = $totalSlots - $totalOcupados;

$pageTitle = 'Salas livres';
$pageDescription = 'Que salas estão livres em cada bloco da semana — antes de marcares a aula.';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid stats-grid" style="margin-bottom:20px">
    <article class="stat-card accent-teal">
        <div class="stat-card-top"><span class="stat-icon"><?= icone('door') ?></span></div>
        <div class="stat-value"><?= count($salas) ?></div>
        <div class="stat-label">Salas <?= $filtroTipo ? 'do tipo ' . htmlspecialchars($filtroTipo) : 'no total' ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-card-top"><span class="stat-icon"><?= icone('check') ?></span></div>
        <div class="stat-value"><?= $totalLivres ?></div>
        <div class="stat-label">Blocos livres esta semana</div>
    </article>
    <article class="stat-card accent-orange">
        <div class="stat-card-top"><span class="stat-icon"><?= icone('calendar') ?></span></div>
        <div class="stat-value"><?= $totalOcupados ?></div>
        <div class="stat-label">Blocos ocupados</div>
    </article>
</div>

<section class="card">
    <div class="toolbar">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <label for="f-tipo" style="font-size:.79rem;font-weight:600;color:var(--ink-soft)">Tipo de sala</label>
            <select class="form-control form-select filter-select" name="tipo" id="f-tipo" onchange="this.form.submit()">
                <option value="">Todas</option>
                <?php foreach ($tiposValidos as $t): ?>
                <option value="<?= $t ?>" <?= $filtroTipo === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="legenda-blocos" style="border:0;background:transparent;padding:0;margin-left:auto">
            <span class="legenda-item"><span class="marca" style="background:#EAEFF7;border:1px solid var(--line-dark)"></span>Livre</span>
            <span class="legenda-item"><span class="marca marca--aula"></span>Ocupada</span>
        </div>
    </div>

    <?php if (!$salas): ?>
        <div class="estado-vazio" style="border:0">
            <p><strong>Nenhuma sala <?= $filtroTipo ? 'do tipo ' . htmlspecialchars($filtroTipo) : '' ?> cadastrada.</strong></p>
        </div>
    <?php else: ?>
    <div class="tabela-grelha-scroll">
        <table class="tabela-grelha">
            <thead>
                <tr>
                    <th class="col-hora">Sala</th>
                    <?php foreach (DIAS_SEMANA as $d): ?>
                        <th colspan="<?= count(BLOCOS_HORARIOS) ?>"><?= $d ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th class="col-hora"></th>
                    <?php foreach (DIAS_SEMANA as $d): ?>
                        <?php foreach (BLOCOS_HORARIOS as [$hi, $hf]): ?>
                            <th class="col-hora" style="font-size:.58rem"><?= substr($hi, 0, 5) ?></th>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($salas as $s): ?>
                <tr>
                    <td class="col-hora" style="width:auto;min-width:130px;text-align:left;padding:8px 10px">
                        <strong style="display:block"><?= htmlspecialchars($s['nome']) ?></strong>
                        <?= htmlspecialchars($s['tipo']) ?> · <?= (int)$s['capacidade'] ?> lug.
                    </td>
                    <?php foreach (DIAS_SEMANA as $d): ?>
                        <?php foreach (BLOCOS_HORARIOS as [$hi, $hf]): ?>
                            <?php $oc = $ocupacao[$s['id']][$d . '|' . $hi . '|' . $hf] ?? null; ?>
                            <?php if ($oc): ?>
                                <td style="padding:0">
                                    <div class="bloco-grelha" style="min-height:44px;height:100%;padding:5px 6px"
                                         title="<?= htmlspecialchars(($oc['disciplina'] ?? 'Aula') . ' — ' . $oc['curso'] . ' ' . $oc['ano_curricular'] . 'º ' . $oc['nome_turma'] . ' (' . $hi . '–' . $hf . ')') ?>">
                                        <strong style="font-size:.58rem"><?= htmlspecialchars($oc['curso']) ?> <?= $oc['ano_curricular'] ?>º</strong>
                                    </div>
                                </td>
                            <?php else: ?>
                                <td style="background:#EAEFF7" title="<?= htmlspecialchars($s['nome'] . ' livre — ' . $d . ' ' . $hi . '–' . $hf) ?>"></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <span style="color:var(--ink-soft);font-size:.75rem">
            Passa o rato por cima de um bloco para ver que turma o ocupa. Horários arquivados não contam.
        </span>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
