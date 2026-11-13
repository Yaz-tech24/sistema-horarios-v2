<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php'; // calcularTurno()

$erro = $ok = null; $editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    if (isset($_POST['eliminar'])) {
        try {
            $pdo->prepare("DELETE FROM turmas WHERE id = ?")->execute([(int)postTexto('eliminar', 12)]);
            $sucesso = true; $mensagem = 'Turma eliminada.';
        } catch (PDOException $e) {
            $sucesso = false; $mensagem = 'Não é possível eliminar: esta turma já tem um horário associado.';
        }
        if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem); }
        definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
        header('Location: turmas.php'); exit;
    }

    $curso_id = (int)postTexto('curso_id', 12);
    $ano      = (int)postTexto('ano_curricular', 3);
    // ENUM na base de dados — só os dois valores do formulário.
    $regime   = ($_POST['regime'] ?? '') === 'Pos-Laboral' ? 'Pos-Laboral' : 'Laboral';
    // O turno NUNCA vem do formulário — é sempre calculado no servidor a
    // partir de (ano_curricular, regime). O <select> livre foi removido.
    $turno    = calcularTurno($ano, $regime);
    $nome     = postTexto('nome_turma', 10) ?: 'A';
    $alunos   = max(0, min(2000, (int)postTexto('num_alunos', 6)));
    $salaPadrao = (int)postTexto('sala_padrao_id', 12) ?: null;
    $id       = (int)postTexto('id', 12);

    if ($curso_id <= 0 || $ano < 1 || $ano > 5) {
        $erro = "Escolhe o curso e um ano válido.";
    } else {
        // Curso, ano e regime definem o horário da turma: as disciplinas que
        // lá podem estar (curso + ano) e os blocos da grelha (o turno, que
        // vem do ano e do regime). Com aulas já marcadas, mudar qualquer um
        // deles deixava o horário com disciplinas de outro ano/curso, ou com
        // aulas fora da grelha — "desaparecidas" do Editor sem terem sido
        // removidas. Antes só se travava a mudança de turno; mudar o ano de
        // 1º para 3º (os dois de Manhã) passava e deixava as disciplinas do
        // 1º ano no horário do 3º.
        $bloqueadoPorAulas = false;
        $mudaEstrutura = false;
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT curso_id, ano_curricular, regime, turno FROM turmas WHERE id = ?");
            $stmt->execute([$id]);
            $antiga = $stmt->fetch();

            if ($antiga && ((int)$antiga['curso_id'] !== $curso_id || (int)$antiga['ano_curricular'] !== $ano
                            || $antiga['regime'] !== $regime)) {
                $mudaEstrutura = true;
                // Só contam aulas a sério. A Pastoral e o Estudo Autónomo são
                // criados pelo sistema e voltam a ser criados no sítio certo
                // (são apagados abaixo) — contá-los deixava a turma presa para
                // sempre assim que alguém abrisse o Editor.
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM aulas a JOIN horarios h ON a.horario_id = h.id
                     WHERE h.turma_id = ? AND h.estado != 'Arquivado' AND a.tipo_bloco = 'Aula'");
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $bloqueadoPorAulas = true;
                    $traduz = fn($t) => str_replace(['Manha','Tarde'], ['Manhã','Tarde'], $t);
                    $erro = "Não é possível mudar o curso, o ano ou o regime desta turma: o horário atual já tem aulas "
                          . "marcadas para o " . (int)$antiga['ano_curricular'] . "º ano em regime " . rotuloRegime($antiga['regime'])
                          . " (turno da " . $traduz($antiga['turno']) . "). Arquiva o horário desta turma primeiro "
                          . "(Coordenador → Publicar → Arquivar) ou remove as aulas no Editor de Horário.";
                }
            }
        }

        if (!$bloqueadoPorAulas) {
            try {
                if ($id > 0) {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE turmas SET curso_id=?, ano_curricular=?, regime=?, turno=?, nome_turma=?, num_alunos=?, sala_padrao_id=? WHERE id=?");
                    $stmt->execute([$curso_id, $ano, $regime, $turno, $nome, $alunos, $salaPadrao, $id]);
                    if ($mudaEstrutura) {
                        // Sem aulas (verificado acima): só restam blocos não letivos
                        // do turno/ano antigo. A Pastoral volta a ser criada no
                        // bloco certo da próxima vez que o Editor abrir.
                        $stmt = $pdo->prepare(
                            "SELECT a.id, a.horario_id FROM aulas a JOIN horarios h ON a.horario_id = h.id
                             WHERE h.turma_id = ? AND h.estado != 'Arquivado' AND a.tipo_bloco != 'Aula'");
                        $stmt->execute([$id]);
                        $porHorario = [];
                        foreach ($stmt->fetchAll() as $b) { $porHorario[(int)$b['horario_id']][] = (int)$b['id']; }
                        foreach ($porHorario as $hid => $ids) { removerBlocos($pdo, $hid, $ids); }
                    }
                    $pdo->commit();
                    definirFlash('ok', 'Turma atualizada.');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$curso_id, $ano, $regime, $turno, $nome, $alunos, $salaPadrao]);
                    definirFlash('ok', 'Turma criada.');
                }
                header('Location: turmas.php'); exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('Erro ao guardar turma: ' . $e->getMessage());
                $erro = "Não foi possível guardar: o curso ou a sala escolhida já não existe. Atualiza a página.";
            }
        }
    }
}

if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM turmas WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$cursos = $pdo->query("SELECT * FROM cursos ORDER BY nome")->fetchAll();
$salas = $pdo->query("SELECT * FROM salas ORDER BY nome")->fetchAll();
$turmas = $pdo->query(
    "SELECT t.*, c.sigla, s.nome AS sala_padrao_nome FROM turmas t
     JOIN cursos c ON t.curso_id=c.id
     LEFT JOIN salas s ON t.sala_padrao_id = s.id
     ORDER BY c.sigla, t.ano_curricular, t.regime")->fetchAll();

$pageTitle = 'Turmas';
$pageDescription = 'Curso + ano + regime + turno — a unidade que tem um horário.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<section class="card <?= $editar ? 'cartao--em-edicao' : '' ?>" style="margin-bottom:20px">
    <div class="card-header"><div><h2><?= $editar ? 'Editar turma' : 'Nova turma' ?></h2><p>O turno é sempre calculado a partir do ano e do regime.</p></div></div>
    <div class="card-body">
        <form method="post" class="form-row">
            <?= campoCSRF() ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
            <div class="campo"><label>Curso</label>
                <select class="form-control form-select" name="curso_id" required>
                    <option value="">— escolher —</option>
                    <?php foreach ($cursos as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($editar['curso_id'] ?? 0)==$c['id'])?'selected':'' ?>>
                        <?= htmlspecialchars($c['sigla']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label>Ano</label>
                <input class="form-control" type="number" name="ano_curricular" id="f-ano" min="1" max="5" required
                       value="<?= $editar['ano_curricular'] ?? '' ?>"></div>
            <div class="campo"><label>Regime</label>
                <select class="form-control form-select" name="regime" id="f-regime">
                    <?php foreach (['Laboral','Pos-Laboral'] as $r): ?>
                    <option value="<?= $r ?>" <?= (($editar['regime'] ?? '')===$r)?'selected':'' ?>><?= rotuloRegime($r) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><span class="campo-rotulo" id="rot-turno">Turno</span>
                <p id="turno-calculado" class="badge badge-info" aria-labelledby="rot-turno" aria-live="polite" style="margin:0;padding:9px 12px;font-size:.84rem;">
                    <?= rotuloTurno(calcularTurno((int)($editar['ano_curricular'] ?? 1), $editar['regime'] ?? 'Laboral')) ?>
                    <span style="font-weight:400;"> (calculado)</span>
                </p>
            </div>
            <div class="campo"><label>Turma</label>
                <input class="form-control" type="text" name="nome_turma" maxlength="10" value="<?= htmlspecialchars($editar['nome_turma'] ?? 'A') ?>"></div>
            <div class="campo"><label>Nº alunos</label>
                <input class="form-control" type="number" name="num_alunos" min="0" value="<?= $editar['num_alunos'] ?? 0 ?>"></div>
            <div class="campo"><label>Sala habitual (opcional)</label>
                <select class="form-control form-select" name="sala_padrao_id">
                    <option value="0">— sem sala fixa —</option>
                    <?php foreach ($salas as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (($editar['sala_padrao_id'] ?? 0) == $s['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nome']) ?> (<?= $s['capacidade'] ?> lug.)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p style="grid-column:1/-1;margin:0;color:var(--ink-soft);font-size:.78rem;">
                Laboral: 1º/3º → Manhã, 2º/4º → Tarde. Pós-Laboral: sempre Noite.
                Se a turma já tiver aulas marcadas, mudar o curso, o ano ou o regime fica bloqueado.
                A sala habitual é só sugestão para a geração automática — o laboratório
                de uma disciplina prática (RN16) continua a ter prioridade.
            </p>
            <div class="campo" style="grid-column:1/-1">
                <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem"><?= $editar ? 'Atualizar' : 'Guardar' ?></button>
                <?php if ($editar): ?><a class="btn btn-secondary" href="turmas.php" style="width:auto;padding-inline:2rem;margin-left:8px">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>
</section>

<?php if (!$turmas): ?>
    <div class="estado-vazio">
        <p><strong>Ainda não há turmas cadastradas.</strong></p>
        <p><?= $cursos ? 'Usa o formulário acima para criar a primeira turma — é sobre ela que o Coordenador constrói o horário.' : 'Cria primeiro um curso, para depois poderes atribuir-lhe turmas.' ?></p>
    </div>
<?php else: ?>
<section class="card">
    <div class="toolbar">
        <div class="search-box"><?= icone('search', 18) ?><input type="search" placeholder="Pesquisar turma…" data-filtro-tabela="#tabela-turmas"></div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table" id="tabela-turmas">
            <thead><tr><th>Curso</th><th>Ano</th><th>Regime</th><th>Turno</th><th>Turma</th><th>Alunos</th><th>Sala habitual</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($turmas as $t): ?>
            <tr>
                <td><div class="cell-main"><span class="cell-icon"><?= htmlspecialchars($t['sigla']) ?></span><span><strong><?= htmlspecialchars($t['sigla']) ?> — <?= $t['ano_curricular'] ?>º <?= htmlspecialchars($t['nome_turma']) ?></strong><small><?= htmlspecialchars(rotuloRegime($t['regime'])) ?> · <?= htmlspecialchars(rotuloTurno($t['turno'])) ?></small></span></div></td>
                <td><?= (int)$t['ano_curricular'] ?>º</td>
                <td><?= htmlspecialchars(rotuloRegime($t['regime'])) ?></td>
                <td><?= htmlspecialchars(rotuloTurno($t['turno'])) ?></td>
                <td><?= htmlspecialchars($t['nome_turma']) ?></td>
                <td><?= $t['num_alunos'] ?></td>
                <td><?= $t['sala_padrao_nome'] ? htmlspecialchars($t['sala_padrao_nome']) : '—' ?></td>
                <td>
                    <div class="actions">
                        <a class="btn btn-secondary btn-icon" title="Editar" href="?editar=<?= $t['id'] ?>"><?= icone('edit', 16) ?></a>
                        <form method="post" data-confirmar="Eliminar esta turma?" data-ajax-remover="tr">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="eliminar" value="<?= $t['id'] ?>">
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

<script nonce="<?= nonceCSP() ?>">
// Pré-visualização apenas — o valor gravado vem sempre de calcularTurno()
// no servidor (admin/turmas.php), nunca deste script.
(function () {
    var ano = document.getElementById('f-ano');
    var regime = document.getElementById('f-regime');
    var saida = document.getElementById('turno-calculado');
    if (!ano || !regime || !saida) { return; }
    function atualizar() {
        var a = parseInt(ano.value, 10) || 1;
        var turno = regime.value === 'Pos-Laboral' ? 'Noite' : (a % 2 === 1 ? 'Manhã' : 'Tarde');
        saida.innerHTML = turno + ' <span style="font-weight:400;"> (calculado)</span>';
    }
    ano.addEventListener('input', atualizar);
    regime.addEventListener('change', atualizar);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
