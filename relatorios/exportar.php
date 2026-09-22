<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador','Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';
require_once __DIR__ . '/../includes/icons.php';

// Um Coordenador só pode exportar turmas dos cursos que lhe foram atribuídos;
// o Administrador vê todas. Nunca confiar só na lista mostrada na interface —
// o filtro de turmas válidas é o que decide o que ?turma=/?curso= pode devolver.
if ($_SESSION['perfil'] === 'Coordenador') {
    $meusCursoIds = array_column(cursosDoCoordenador($pdo), 'id');
    if (!$meusCursoIds) {
        $turmas = [];
    } else {
        $marcas = implode(',', array_fill(0, count($meusCursoIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT t.*, c.sigla, c.nome AS curso_nome FROM turmas t
             JOIN cursos c ON t.curso_id = c.id
             WHERE t.curso_id IN ($marcas)
             ORDER BY c.sigla, t.ano_curricular, t.regime, t.nome_turma");
        $stmt->execute($meusCursoIds);
        $turmas = $stmt->fetchAll();
    }
} else {
    $turmas = $pdo->query(
        "SELECT t.*, c.sigla, c.nome AS curso_nome FROM turmas t
         JOIN cursos c ON t.curso_id = c.id
         ORDER BY c.sigla, t.ano_curricular, t.regime, t.nome_turma")->fetchAll();
}

// Cursos distintos entre as turmas já filtradas por permissão — para o
// segundo modo de exportação ("curso inteiro").
$cursosDisponiveis = [];
foreach ($turmas as $t) {
    $cursosDisponiveis[$t['curso_id']] = $t['sigla'] . ' — ' . $t['curso_nome'];
}

$turmaId = (int)($_GET['turma'] ?? 0);
$cursoId = (int)($_GET['curso'] ?? 0);

$turmaSel = null;
if ($turmaId) {
    foreach ($turmas as $t) { if ($t['id'] == $turmaId) { $turmaSel = $t; break; } }
}
$turmasDoCursoSel = [];
if ($cursoId && isset($cursosDisponiveis[$cursoId])) {
    foreach ($turmas as $t) { if ($t['curso_id'] == $cursoId) { $turmasDoCursoSel[] = $t; } }
}

/* Todas as aulas (não arquivadas) de uma turma, já organizadas em grelha
   [dia][hora_inicio] => lista de aulas — usado tanto no ecrã como no CSV. */
function aulasDaTurma(PDO $pdo, int $turmaId): array {
    $stmt = $pdo->prepare(
        "SELECT a.*, d.nome AS disciplina, doc.nome AS docente,
                dr.nome AS regente, da.nome AS assistente, s.nome AS sala
         FROM aulas a
         JOIN horarios h ON a.horario_id = h.id
         LEFT JOIN disciplinas d ON a.disciplina_id = d.id
         LEFT JOIN docentes doc ON a.docente_id = doc.id
         LEFT JOIN docentes dr ON a.docente_regente_id = dr.id
         LEFT JOIN docentes da ON a.docente_assistente_id = da.id
         LEFT JOIN salas s ON a.sala_id = s.id
         WHERE h.turma_id = ? AND h.estado != 'Arquivado'
         ORDER BY FIELD(a.dia_semana,'Segunda','Terca','Quarta','Quinta','Sexta'), a.hora_inicio");
    $stmt->execute([$turmaId]);
    return $stmt->fetchAll();
}

function nomeTurma(array $t): string {
    return $t['sigla'] . ' ' . $t['ano_curricular'] . 'º Ano (' . $t['regime'] . ', Turma ' . $t['nome_turma'] . ')';
}

/* Neutraliza "CSV/Formula Injection": nomes de disciplina/docente/sala são
   texto livre (Admin) e, ao abrir o CSV no Excel, uma célula a começar por
   =, +, -, @ é interpretada como fórmula. Um apóstrofo à frente força-a a
   ser lida como texto, sem mudar o que se vê na célula. */
function celulaCsvSegura(?string $valor): string {
    $valor = (string)$valor;
    return $valor !== '' && strpbrk($valor[0], "=+-@") !== false ? "'" . $valor : $valor;
}

/* Texto de uma aula/bloco para uma célula da grelha CSV — as mesmas 3
   linhas que a grelha no ecrã mostra (disciplina, regente/assistente,
   sala), juntas com quebra de linha; o Excel mostra isto envolvido dentro
   de uma única célula. */
function celulaAulaTexto(array $a): string {
    if ($a['tipo_bloco'] !== 'Aula') {
        return str_replace('_', ' ', $a['tipo_bloco']);
    }
    $titulo = $a['disciplina'] ?? '—';
    if (!empty($a['subgrupo'])) { $titulo .= " (Subgrupo {$a['subgrupo']})"; }
    $linhaDocente = 'Regente: ' . ($a['regente'] ?? $a['docente'] ?? '—');
    if (!empty($a['assistente'])) { $linhaDocente .= ' · Assistente: ' . $a['assistente']; }
    return implode("\n", [$titulo, $linhaDocente, 'Sala: ' . ($a['sala'] ?? '—')]);
}

// ---- Exportação em CSV (Excel) — responde e termina antes de qualquer HTML ----
// Formato em grelha (Hora × Dia), a mesma organização da grelha no ecrã e no
// PDF — uma lista plana (uma linha por aula, incluindo Estudo Autónomo/
// Atividade Não Letiva) ficava confusa de ler, sobretudo a exportar um
// curso inteiro com várias turmas misturadas na mesma lista.
if (($_GET['formato'] ?? '') === 'csv') {
    $turmasParaCsv = $turmasDoCursoSel ?: ($turmaSel ? [$turmaSel] : []);
    if ($turmasParaCsv) {
        $nomeFicheiro = $cursoId ? ($cursosDisponiveis[$cursoId] ?? 'horario') : nomeTurma($turmaSel);
        $nomeFicheiro = preg_replace('/[^A-Za-z0-9]+/', '_', $nomeFicheiro);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="horario_' . $nomeFicheiro . '_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM — o Excel só respeita acentos com isto
        // "sep=;" como primeira linha do ficheiro obriga o Excel a ler com
        // ; como separador de colunas, seja qual for a definição regional
        // do Windows de quem abre o ficheiro (com vírgula por definição, um
        // CSV separado por ; sem esta linha abre tudo numa só coluna).
        fputs($out, "sep=;\r\n");

        $primeiraTurma = true;
        foreach ($turmasParaCsv as $t) {
            if (!$primeiraTurma) { fputcsv($out, [], ';'); } // linha em branco entre turmas
            $primeiraTurma = false;

            fputcsv($out, [celulaCsvSegura(nomeTurma($t) . ' — ' . $t['curso_nome'])], ';');
            fputcsv($out, ['Turno: ' . str_replace(['Manha','Tarde'], ['Manhã','Tarde'], $t['turno'])
                . ' · Gerado em ' . date('d/m/Y H:i')], ';');

            $grelha = [];
            foreach (aulasDaTurma($pdo, (int)$t['id']) as $a) {
                $grelha[$a['dia_semana']][substr($a['hora_inicio'], 0, 5)][] = $a;
            }

            fputcsv($out, array_merge(['Hora'], DIAS_SEMANA), ';');
            foreach (blocosDoTurno($t['turno']) as [$hi, $hf]) {
                $linha = [$hi . '–' . $hf];
                foreach (DIAS_SEMANA as $d) {
                    $partes = [];
                    foreach (($grelha[$d][$hi] ?? []) as $a) { $partes[] = celulaAulaTexto($a); }
                    $linha[] = celulaCsvSegura(implode("\n\n", $partes));
                }
                fputcsv($out, $linha, ';');
            }
        }
        fclose($out);
        exit;
    }
}

// Título da página/aba — o browser usa isto como nome sugerido ao "Guardar como PDF"
if ($turmasDoCursoSel) {
    $pageTitle = 'Horário ' . $cursosDisponiveis[$cursoId] . ' — Exportação';
} elseif ($turmaSel) {
    $pageTitle = 'Horário ' . nomeTurma($turmaSel) . ' — Exportação';
} else {
    $pageTitle = 'Exportar Horário — FAGRENM';
}
$pageDescription = 'Escolhe uma turma, ou um curso inteiro, e exporta em PDF (impressão) ou CSV (Excel).';

require_once __DIR__ . '/../includes/header.php';

function grelhaImpressao(PDO $pdo, array $t): void {
    $grelha = []; foreach (aulasDaTurma($pdo, (int)$t['id']) as $a) { $grelha[$a['dia_semana']][substr($a['hora_inicio'],0,5)][] = $a; }
    $blocosGrelha = blocosDoTurno($t['turno']);
    ?>
    <section class="folha-exportacao">
        <div class="card">
            <div class="card-header">
                <div><span class="brand-mark" style="display:inline-grid;margin-bottom:8px">UCM</span><h2 style="margin:0"><?= htmlspecialchars(nomeTurma($t)) ?></h2>
                <p><?= htmlspecialchars($t['curso_nome']) ?> · Turno: <?= str_replace(['Manha','Tarde'], ['Manhã','Tarde'], $t['turno']) ?> · Gerado em <?= date('d/m/Y H:i') ?></p></div>
            </div>
            <div class="tabela-grelha-scroll">
            <table class="tabela-grelha">
                <thead><tr><th class="col-hora">Hora</th><?php foreach (DIAS_SEMANA as $d): ?><th><?= $d ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($blocosGrelha as [$hi, $hf]): ?>
                <tr>
                    <td class="col-hora"><strong><?= $hi ?></strong><?= $hf ?></td>
                    <?php foreach (DIAS_SEMANA as $d): ?>
                    <td>
                        <?php foreach (($grelha[$d][$hi] ?? []) as $a): ?>
                            <div class="bloco-grelha <?= $a['tipo_bloco'] === 'Aula' ? 'bloco-aula' : ($a['tipo_bloco'] === 'Estudo_Autonomo' ? 'bloco-estudo' : 'bloco-atividade') ?>">
                            <?php if ($a['tipo_bloco'] === 'Aula'): ?>
                                <strong><?= htmlspecialchars($a['disciplina'] ?? '—') ?></strong><?= $a['subgrupo'] ? ' <em>(' . htmlspecialchars($a['subgrupo']) . ')</em>' : '' ?>
                                <small>Regente: <?= htmlspecialchars($a['regente'] ?? $a['docente'] ?? '—') ?><?php if ($a['assistente']): ?> · Assist.: <?= htmlspecialchars($a['assistente']) ?><?php endif; ?></small>
                                <small><?= htmlspecialchars($a['sala'] ?? '—') ?></small>
                            <?php else: ?>
                                <em><?= str_replace('_',' ',$a['tipo_bloco']) ?></em>
                            <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="card-footer"><span>Universidade Católica de Moçambique · FAGRENM</span><span>Sistema de Gestão de Horários</span></div>
        </div>
    </section>
    <?php
}
?>

<section class="card" style="margin-bottom:20px" data-no-print>
    <div class="card-body">
        <div class="form-row">
            <form method="get" class="campo">
                <label>Uma turma</label>
                <select class="form-control form-select" name="turma" onchange="this.form.submit()">
                    <option value="">— escolher —</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($t['id']==$turmaId)?'selected':'' ?>>
                        <?= $t['sigla'] ?> · <?= $t['ano_curricular'] ?>º · <?= $t['regime'] ?> · <?= htmlspecialchars($t['nome_turma']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form method="get" class="campo">
                <label>Ou um curso inteiro (todas as turmas)</label>
                <select class="form-control form-select" name="curso" onchange="this.form.submit()">
                    <option value="">— escolher —</option>
                    <?php foreach ($cursosDisponiveis as $cid => $rotulo): ?>
                    <option value="<?= $cid ?>" <?= ($cid==$cursoId)?'selected':'' ?>><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if (!$turmas): ?>
            <p style="margin:14px 0 0;color:var(--ink-soft);font-size:.85rem">
                <?= $_SESSION['perfil'] === 'Coordenador'
                    ? 'Ainda não tens turmas para exportar — só aparecem aqui turmas dos cursos que te foram atribuídos.'
                    : 'Ainda não há turmas cadastradas.' ?>
            </p>
        <?php endif; ?>

        <?php if ($turmaSel || $turmasDoCursoSel): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
            <button type="button" class="btn btn-primary" style="width:auto;padding-inline:1.8rem" onclick="window.print()"><?= icone('printer', 17) ?> Imprimir / Exportar PDF</button>
            <a class="btn btn-secondary" style="width:auto;padding-inline:1.8rem"
               href="?<?= $cursoId ? 'curso='.$cursoId : 'turma='.$turmaId ?>&formato=csv"><?= icone('download', 17) ?> Exportar CSV (Excel)</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($turmasDoCursoSel): ?>
    <?php foreach ($turmasDoCursoSel as $t): grelhaImpressao($pdo, $t); endforeach; ?>
<?php elseif ($turmaSel): ?>
    <?php grelhaImpressao($pdo, $turmaSel); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
