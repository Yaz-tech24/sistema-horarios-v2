<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';
require_once __DIR__ . '/../includes/funcoes_conflitos.php';
require_once __DIR__ . '/../includes/funcoes_notificacoes.php';

$curso = exigirCursoDoCoordenador($pdo);

$erro = $ok = null; $avisos = []; $errosBloq = []; $resultadoGeracao = null;
if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }
if (isset($_SESSION['resultado_geracao'])) {
    $resultadoGeracao = $_SESSION['resultado_geracao'];
    unset($_SESSION['resultado_geracao']);
}

// Escolha da turma do curso atual
if (isset($_GET['turma'])) {
    $tid = (int)$_GET['turma'];
    $stmt = $pdo->prepare("SELECT 1 FROM turmas WHERE id=? AND curso_id=?");
    $stmt->execute([$tid, $curso['id']]);
    if ($stmt->fetch()) {
        $_SESSION['turma_id_atual'] = $tid;
        unset($_SESSION['horario_id_atual']);
    }
    header('Location: editor_horario.php'); exit;
}

$turmas = turmasDoCurso($pdo, (int)$curso['id']);
$turmaId = (int)($_SESSION['turma_id_atual'] ?? 0);

// A turma em sessão tem de pertencer ao curso atual
$turmaAtual = null;
foreach ($turmas as $t) { if ($t['id'] == $turmaId) { $turmaAtual = $t; break; } }
if (!$turmaAtual) { $turmaId = 0; unset($_SESSION['turma_id_atual'], $_SESSION['horario_id_atual']); }

$horario = null; $aulas = []; $blocosGrelha = BLOCOS_HORARIOS; $edicao = null;
if ($turmaAtual) {
    $horario = obterHorarioDaTurma($pdo, $turmaId);
    $_SESSION['horario_id_atual'] = (int)$horario['id'];
    $blocosGrelha = blocosDoTurno($turmaAtual['turno']); // regra 2 — só os 3 blocos do turno da turma

    // Conjunto de blocos válidos ("HH:MM|HH:MM") para este turno — nunca
    // confiar num horário arbitrário vindo do POST.
    $blocosValidos = [];
    foreach ($blocosGrelha as [$hi, $hf]) { $blocosValidos[$hi . '|' . $hf] = true; }

    // RN08 — garante que a Pastoral Universitária existe (turmas Laboral)
    garantirPastoral($pdo, $horario, $turmaAtual);

    // Carregar uma aula existente para edição (form pré-preenchido abaixo)
    if (isset($_GET['editar_aula'])) {
        $stmt = $pdo->prepare("SELECT * FROM aulas WHERE id=? AND horario_id=? AND tipo_bloco != 'Atividade_Nao_Letiva'");
        $stmt->execute([(int)$_GET['editar_aula'], $horario['id']]);
        $edicao = $stmt->fetch() ?: null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validarCSRF();
        $acao = $_POST['acao'] ?? '';

        // Geração automática do horário a partir das disciplinas do curso+ano
        if (in_array($acao, ['gerar_auto', 'gerar_auto_limpar'], true)) {
            $limpar = $acao === 'gerar_auto_limpar';
            // Sala fixa opcional — só aceite se existir mesmo, senão volta
            // à escolha automática por capacidade (comportamento anterior).
            $salaForcada = (int)($_POST['sala_id'] ?? 0) ?: null;
            if ($salaForcada) {
                $stmt = $pdo->prepare("SELECT 1 FROM salas WHERE id = ?");
                $stmt->execute([$salaForcada]);
                if (!$stmt->fetch()) { $salaForcada = null; }
            }
            $_SESSION['resultado_geracao'] = gerarHorarioAutomatico($pdo, $horario, $turmaAtual, $curso, $limpar, $salaForcada);
            definirFlash('ok', 'Geração automática concluída.');
            header('Location: editor_horario.php'); exit;
        }

        // Apagar aula
        if ($acao === 'apagar') {
            $aid = (int)($_POST['aula_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT a.*, d.nome AS disc FROM aulas a
                                    LEFT JOIN disciplinas d ON a.disciplina_id=d.id
                                    WHERE a.id=? AND a.horario_id=?");
            $stmt->execute([$aid, $horario['id']]);
            if ($a = $stmt->fetch()) {
                // RN08 — o bloco fixo da Pastoral não pode ser removido pelo Coordenador
                if ($a['tipo_bloco'] === 'Atividade_Nao_Letiva'
                    && ehBlocoPastoral($turmaAtual, $a['dia_semana'], substr($a['hora_inicio'],0,5), substr($a['hora_fim'],0,5))) {
                    $sucesso = false;
                    $mensagem = 'Este bloco é a Pastoral Universitária (obrigatória em regime Laboral) e não pode ser removido.';
                } else {
                    notificarDocenteDaAula($pdo, $aid,
                        "A aula de " . ($a['disc'] ?? 'bloco') . " (" . $a['dia_semana'] . " "
                        . substr($a['hora_inicio'],0,5) . ") foi removida do horário.", false);
                    $pdo->prepare("DELETE FROM conflitos WHERE aula_id_1=? OR aula_id_2=?")->execute([$aid,$aid]);
                    // Os pedidos dos docentes sobrevivem à aula: perde-se a
                    // ligação mas o texto de referência mantém-nos legíveis.
                    $pdo->prepare("UPDATE pedidos SET aula_id=NULL WHERE aula_id=?")->execute([$aid]);
                    $pdo->prepare("DELETE FROM aulas WHERE id=?")->execute([$aid]);
                    registarHistorico($pdo, (int)$horario['id'],
                        "Removida aula de " . ($a['disc'] ?? 'bloco') . " (" . $a['dia_semana'] . ")");
                    $sucesso = true;
                    $mensagem = 'Aula removida.';
                }
            } else {
                $sucesso = false;
                $mensagem = 'Este bloco já não existe (talvez já tenha sido removido).';
            }
            if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem); }
            definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
            header('Location: editor_horario.php'); exit;
        }

        // Criar ou atualizar aula
        if (isset($_POST['dia_semana'])) {
            $aulaId = (int)($_POST['aula_id'] ?? 0);
            $bloco  = $_POST['bloco'] ?? '';
            $regenteId    = (int)($_POST['docente_regente_id'] ?? 0) ?: null;
            $assistenteId = (int)($_POST['docente_assistente_id'] ?? 0) ?: null;
            $partesBloco  = explode('|', $bloco);
            $aula = [
                'horario_id'    => (int)$horario['id'],
                'turma_id'      => $turmaId,
                'tipo_bloco'    => $_POST['tipo_bloco'] ?? 'Aula',
                'disciplina_id' => (int)($_POST['disciplina_id'] ?? 0) ?: null,
                'docente_id'    => $regenteId, // espelha docente_regente_id — ver nota em funcoes_coordenador.php
                'docente_regente_id'    => $regenteId,
                'docente_assistente_id' => $assistenteId,
                'sala_id'       => (int)($_POST['sala_id'] ?? 0) ?: null,
                'subgrupo'      => trim($_POST['subgrupo'] ?? '') ?: null,
                'dia_semana'    => $_POST['dia_semana'],
                'hora_inicio'   => $partesBloco[0] ?? '',
                'hora_fim'      => $partesBloco[1] ?? '',
            ];

            // RN16 — uma disciplina Prática/Laboratorial com laboratório
            // próprio (admin/disciplinas.php) tem esse laboratório como
            // SUGESTÃO no formulário (JS pré-preenche o campo Sala quando a
            // disciplina é escolhida), mas o coordenador pode trocar para
            // qualquer outra sala manualmente — a sala escolhida no POST é
            // respeitada tal como está. Quem continua a impedir duas aulas
            // na mesma sala ao mesmo tempo, seja lá qual for a sala, é a
            // RN02 (verificarConflitos, abaixo), que olha para TODOS os
            // cursos e horários não arquivados.

            if (!in_array($aula['dia_semana'], DIAS_SEMANA, true) || !isset($blocosValidos[$bloco])) {
                $erro = "Escolhe o dia e o bloco horário (tem de ser um dos blocos do turno desta turma).";
            } elseif ($aula['tipo_bloco'] === 'Aula' && !$aula['disciplina_id']) {
                $erro = "Uma aula precisa de uma disciplina.";
            } else {
                $problemas = verificarConflitos($pdo, $aula, $aulaId ?: null);
                $errosBloq = array_filter($problemas, fn($p) => $p['bloqueante']);
                $avisosNovos = array_filter($problemas, fn($p) => !$p['bloqueante']);

                if ($errosBloq) {
                    // Não grava — mostra os conflitos
                } elseif ($avisosNovos && !isset($_POST['confirmar_aviso'])) {
                    $avisos = $avisosNovos; // pede confirmação
                } elseif ($aulaId > 0) {
                    $pdo->prepare(
                        "UPDATE aulas SET disciplina_id=?, docente_id=?, docente_regente_id=?, docente_assistente_id=?,
                            sala_id=?, tipo_bloco=?, subgrupo=?, dia_semana=?, hora_inicio=?, hora_fim=?
                         WHERE id=? AND horario_id=?")
                        ->execute([
                            $aula['disciplina_id'], $aula['docente_id'], $aula['docente_regente_id'],
                            $aula['docente_assistente_id'], $aula['sala_id'], $aula['tipo_bloco'],
                            $aula['subgrupo'], $aula['dia_semana'], $aula['hora_inicio'], $aula['hora_fim'],
                            $aulaId, $horario['id'],
                        ]);
                    // Os conflitos antigos referentes a esta aula ficam desatualizados
                    // assim que ela muda — a próxima revalidação (página Conflitos)
                    // deteta o estado novo e faz o diff correto (ver revalidarHorario).
                    registarHistorico($pdo, (int)$horario['id'], "Editada aula (" . $aula['dia_semana'] . " " . $aula['hora_inicio'] . ")");
                    definirFlash('ok', 'Aula atualizada.');
                    header('Location: editor_horario.php'); exit;
                } else {
                    $pdo->prepare(
                        "INSERT INTO aulas (horario_id, disciplina_id, docente_id, docente_regente_id, docente_assistente_id,
                            sala_id, tipo_bloco, subgrupo, dia_semana, hora_inicio, hora_fim)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([
                            $aula['horario_id'], $aula['disciplina_id'], $aula['docente_id'],
                            $aula['docente_regente_id'], $aula['docente_assistente_id'],
                            $aula['sala_id'], $aula['tipo_bloco'], $aula['subgrupo'],
                            $aula['dia_semana'], $aula['hora_inicio'], $aula['hora_fim'],
                        ]);
                    $novaAulaId = (int)$pdo->lastInsertId();
                    registarHistorico($pdo, (int)$horario['id'], "Adicionada aula (" . $aula['dia_semana'] . " " . $aula['hora_inicio'] . ")");
                    if ($horario['estado'] === 'Publicado') {
                        notificarDocenteDaAula($pdo, $novaAulaId,
                            "Foi-te atribuída uma nova aula: " . $aula['dia_semana'] . " às " . $aula['hora_inicio'] . ".");
                    } else {
                        iniciarRevisaoSeRascunho($pdo, $horario);
                    }
                    definirFlash('ok', 'Aula guardada sem conflitos.');
                    header('Location: editor_horario.php'); exit;
                }
            }
        }
    }

    // Mantém a tabela `conflitos` atual antes de desenhar a grelha — sem
    // isto, o destaque "⚠ conflito pendente" só refletia o que tinha sido
    // revalidado da última vez que alguém visitou Conflitos ou Publicar,
    // podendo mostrar conflitos já corrigidos ou esconder novos.
    revalidarHorario($pdo, (int)$horario['id']);

    // Aulas atuais para a grelha
    $stmt = $pdo->prepare(
        "SELECT a.*, d.nome AS disciplina, doc.nome AS docente,
                dr.nome AS regente, da.nome AS assistente, s.nome AS sala
         FROM aulas a
         LEFT JOIN disciplinas d ON a.disciplina_id=d.id
         LEFT JOIN docentes doc ON a.docente_id=doc.id
         LEFT JOIN docentes dr ON a.docente_regente_id=dr.id
         LEFT JOIN docentes da ON a.docente_assistente_id=da.id
         LEFT JOIN salas s ON a.sala_id=s.id
         WHERE a.horario_id=?");
    $stmt->execute([$horario['id']]);
    foreach ($stmt->fetchAll() as $a) {
        $aulas[$a['dia_semana']][substr($a['hora_inicio'],0,5)][] = $a;
    }

    // Aulas com conflito bloqueante pendente, para destacar na grelha
    $stmt = $pdo->prepare(
        "SELECT aula_id_1 AS id FROM conflitos co JOIN aulas a1 ON co.aula_id_1=a1.id
         WHERE a1.horario_id=? AND co.estado='Pendente'
         UNION
         SELECT aula_id_2 AS id FROM conflitos co JOIN aulas a1 ON co.aula_id_1=a1.id
         WHERE a1.horario_id=? AND co.estado='Pendente'");
    $stmt->execute([$horario['id'], $horario['id']]);
    $aulasComConflito = array_column($stmt->fetchAll(), 'id');
}

// Dados para o formulário (disciplinas do curso, docentes, salas)
$disciplinas = [];
if ($turmaAtual) {
    $stmt = $pdo->prepare(
        "SELECT * FROM disciplinas WHERE curso_id=? AND ano_curricular=? ORDER BY nome");
    $stmt->execute([$curso['id'], $turmaAtual['ano_curricular']]);
    $disciplinas = $stmt->fetchAll();
}
$docentes = $pdo->query("SELECT * FROM docentes ORDER BY nome")->fetchAll();
$salas    = $pdo->query("SELECT * FROM salas ORDER BY nome")->fetchAll();

// Mapa disciplina → regente/assistente/laboratório, para pré-preencher o
// formulário em JS (o laboratório é só sugestão — continua a dar para
// escolher outra sala manualmente).
$mapaDocentesDisciplina = [];
foreach ($disciplinas as $d) {
    $mapaDocentesDisciplina[$d['id']] = [
        'regente' => $d['docente_regente_id'] ?: 0,
        'assistente' => $d['docente_assistente_id'] ?: 0,
        'sala' => $d['sala_id'] ?: 0,
    ];
}

// Mapa disciplina → docentes autorizados a lecioná-la (admin/docentes.php).
// Usa-se para restringir os <select> de regente/assistente no JS; uma
// disciplina sem ninguém marcado não restringe nada (mostra todos), para
// não bloquear o fluxo de quem ainda não preencheu essa lista.
$mapaDisciplinaDocentes = [];
foreach ($pdo->query("SELECT disciplina_id, docente_id FROM docente_disciplina")->fetchAll() as $r) {
    $mapaDisciplinaDocentes[$r['disciplina_id']][] = (int)$r['docente_id'];
}

$pageTitle = 'Editor de horário';
$pageDescription = htmlspecialchars($curso['nome']) . ($horario ? ' · Estado: ' . str_replace('_',' ',$horario['estado']) . ' · Turno: ' . str_replace(['Manha','Tarde'], ['Manhã','Tarde'], $turmaAtual['turno']) : '');
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card" style="margin-bottom:20px">
    <div class="card-body">
        <form method="get" class="form-row" style="grid-template-columns:1fr">
            <div class="campo"><label>Turma</label>
                <select class="form-control form-select" name="turma" onchange="this.form.submit()">
                    <option value="">— escolher turma —</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($t['id']==$turmaId)?'selected':'' ?>>
                        <?= $t['ano_curricular'] ?>º Ano · <?= $t['regime'] ?> · <?= str_replace(['Manha','Tarde'], ['Manhã','Tarde'], $t['turno']) ?> · Turma <?= htmlspecialchars($t['nome_turma']) ?>
                    </option>
                    <?php endforeach; ?>
                </select></div>
        </form>
        <?php if (!$turmas): ?>
            <div class="estado-vazio" style="margin-top:16px">
                <p><strong>Este curso ainda não tem turmas.</strong></p>
                <p>Pede ao Administrador para as criar em <strong>Admin → Turmas</strong> — é sobre uma turma que se constrói um horário.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php foreach ($errosBloq as $p): ?>
    <div class="alert alert-error">ERRO (<?= htmlspecialchars($p['tipo']) ?>) — <?= htmlspecialchars($p['mensagem']) ?></div>
<?php endforeach; ?>

<?php if ($resultadoGeracao): ?>
<section class="card resumo-geracao" style="margin-bottom:20px">
    <div class="card-header"><div><h2>Resultado da geração automática</h2></div></div>
    <div class="card-body">
        <div class="resumo-grelha">
            <div class="resumo-item resumo-item--ok">
                <span class="resumo-numero"><?= $resultadoGeracao['criadas'] ?></span>
                <span>aula(s) de disciplina criada(s)</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-numero"><?= $resultadoGeracao['estudo'] ?></span>
                <span>bloco(s) preenchidos com Estudo Autónomo</span>
            </div>
            <div class="resumo-item <?= $resultadoGeracao['nao_alocadas'] ? 'resumo-item--aviso' : '' ?>">
                <span class="resumo-numero"><?= count($resultadoGeracao['nao_alocadas']) ?></span>
                <span>disciplina(s) por agendar manualmente</span>
            </div>
        </div>
        <?php if ($resultadoGeracao['nao_alocadas']): ?>
        <div class="alert alert-error" style="margin:16px 0 0">
            Não foi possível agendar automaticamente: <?= htmlspecialchars(implode(', ', $resultadoGeracao['nao_alocadas'])) ?>.
            Atribui estas disciplinas manualmente no editor abaixo (pode não haver slots livres sem conflito, ou falta o docente regente).
        </div>
        <?php endif; ?>
        <?php if (!empty($resultadoGeracao['avisos'])): ?>
        <div class="alert" style="margin:16px 0 0;color:var(--warning);background:#fff7e7;border-color:var(--warning)">
            <p style="font-weight:600;margin:0 0 6px">Avisos (não impedem o horário, mas vale a pena rever):</p>
            <?php foreach ($resultadoGeracao['avisos'] as $a): ?>
                <p style="margin:0 0 3px">• <?= htmlspecialchars($a) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($turmaAtual): ?>

<section class="card" style="margin-bottom:20px">
    <div class="card-header"><div><h2>Geração automática</h2><p>Preenche as disciplinas do <?= $turmaAtual['ano_curricular'] ?>º ano nos blocos livres do turno desta turma e completa o resto com Estudo Autónomo. Nunca escreve por cima de aulas já colocadas manualmente.</p></div></div>
    <div class="card-body">
        <form method="post" data-loading-label="A gerar…">
            <?= campoCSRF() ?>
            <div class="campo" style="max-width:340px;margin-bottom:14px">
                <label>Sala</label>
                <select class="form-control form-select" name="sala_id">
                    <option value="0">— automática, pela capacidade da turma —</option>
                    <?php foreach ($salas as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?> (<?= $s['capacidade'] ?> lugares)</option>
                    <?php endforeach; ?>
                </select>
                <p style="margin:6px 0 0;color:var(--ink-soft);font-size:.76rem">
                    Escolhe uma sala fixa para todas as aulas geradas, ou deixa em automática para o sistema escolher pela capacidade.
                </p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="submit" name="acao" value="gerar_auto" class="btn btn-primary" style="width:auto;padding-inline:1.8rem"><?= icone('send', 17) ?> Gerar automaticamente</button>
                <button type="submit" name="acao" value="gerar_auto_limpar" class="btn btn-secondary" style="width:auto;padding-inline:1.8rem"
                    data-confirmar="Isto apaga TODAS as aulas atuais deste horário (incluindo as criadas manualmente) e gera tudo de novo. Continuar?">
                    Limpar tudo e gerar de novo</button>
            </div>
        </form>
    </div>
</section>

<?php if ($avisos): ?>
<section class="card" style="margin-bottom:20px;border-left:4px solid var(--yellow)">
    <div class="card-body">
        <p style="font-weight:600;margin:0 0 10px">Atenção — confirma antes de gravar:</p>
        <?php foreach ($avisos as $p): ?>
            <p style="margin:0 0 6px;color:var(--ink-soft);font-size:.85rem">• (<?= htmlspecialchars($p['tipo']) ?>) <?= htmlspecialchars($p['mensagem']) ?></p>
        <?php endforeach; ?>
        <form method="post" style="margin-top:10px">
            <?= campoCSRF() ?>
            <?php foreach (['aula_id','tipo_bloco','disciplina_id','docente_regente_id','docente_assistente_id','sala_id','subgrupo','dia_semana','bloco'] as $fld): ?>
                <input type="hidden" name="<?= $fld ?>" value="<?= htmlspecialchars($_POST[$fld] ?? '') ?>">
            <?php endforeach; ?>
            <input type="hidden" name="confirmar_aviso" value="1">
            <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:1.6rem">Gravar mesmo assim</button>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="card <?= $edicao ? 'cartao--em-edicao' : '' ?>" style="margin-bottom:20px" id="form-nova-aula" data-em-edicao="<?= $edicao ? '1' : '0' ?>">
    <div class="card-header"><div><h2><?= $edicao ? 'A editar aula / bloco' : 'Nova aula / bloco' ?></h2>
        <p><?= $edicao
            ? 'Estás a alterar o bloco destacado na grelha, mais abaixo. Grava para atualizar, ou cancela para voltar a criar um novo.'
            : 'Dica: clica num quadrado vazio da grelha para pré-preencher o dia e o bloco horário aqui.' ?></p>
    </div></div>
    <div class="card-body">
        <form method="post" class="form-row" data-loading-label="A gravar…">
            <?= campoCSRF() ?>
            <input type="hidden" name="aula_id" value="<?= $edicao['id'] ?? 0 ?>">
            <div class="campo"><label>Tipo de bloco</label>
                <select class="form-control form-select" name="tipo_bloco" id="f-tipo-bloco">
                    <?php foreach (['Aula' => 'Aula', 'Estudo_Autonomo' => 'Estudo Autónomo', 'Atividade_Nao_Letiva' => 'Atividade Não Letiva'] as $v => $r): ?>
                    <option value="<?= $v ?>" <?= (($edicao['tipo_bloco'] ?? 'Aula') === $v) ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo campo-so-aula"><label>Disciplina (<?= $turmaAtual['ano_curricular'] ?>º ano)</label>
                <select class="form-control form-select" name="disciplina_id" id="f-disciplina">
                    <option value="0">—</option>
                    <?php foreach ($disciplinas as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (($edicao['disciplina_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo campo-so-aula"><label>Docente regente</label>
                <select class="form-control form-select" name="docente_regente_id" id="f-regente">
                    <option value="0">—</option>
                    <?php foreach ($docentes as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (($edicao['docente_regente_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo campo-so-aula"><label>Docente assistente (opcional)</label>
                <select class="form-control form-select" name="docente_assistente_id" id="f-assistente">
                    <option value="0">—</option>
                    <?php foreach ($docentes as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (($edicao['docente_assistente_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo campo-so-aula"><label>Sala</label>
                <select class="form-control form-select" name="sala_id" id="f-sala">
                    <option value="0">—</option>
                    <?php foreach ($salas as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (($edicao['sala_id'] ?? 0) == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nome']) ?> (<?= $s['capacidade'] ?>)</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label>Dia</label>
                <select class="form-control form-select" name="dia_semana" id="f-dia" required>
                    <?php foreach (DIAS_SEMANA as $d): ?><option <?= (($edicao['dia_semana'] ?? '') === $d) ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?>
                </select></div>
            <div class="campo"><label>Bloco horário (turno da turma)</label>
                <select class="form-control form-select" name="bloco" id="f-bloco" required>
                    <?php $blocoEdicao = $edicao ? substr($edicao['hora_inicio'],0,5) . '|' . substr($edicao['hora_fim'],0,5) : null; ?>
                    <?php foreach ($blocosGrelha as [$i,$f]): ?>
                    <option value="<?= $i ?>|<?= $f ?>" <?= ($blocoEdicao === "$i|$f") ? 'selected' : '' ?>><?= $i ?> – <?= $f ?></option>
                    <?php endforeach; ?>
                </select>
                <small id="f-sala-nota" hidden style="color:var(--ink-soft);font-size:.75rem">Laboratório sugerido para esta disciplina — podes escolher outra sala, se precisares.</small></div>
            <div class="campo campo-so-aula"><label>Subgrupo (opcional)</label>
                <input class="form-control" type="text" name="subgrupo" maxlength="10" placeholder="ex.: A ou B" value="<?= htmlspecialchars($edicao['subgrupo'] ?? '') ?>"></div>
            <div class="campo" style="grid-column:1/-1;display:flex;gap:8px">
                <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem"><?= $edicao ? 'Atualizar aula' : 'Guardar aula' ?></button>
                <?php if ($edicao): ?><a class="btn btn-secondary" style="width:auto;padding-inline:1.2rem" href="editor_horario.php">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>
</section>

<div class="legenda-blocos" data-no-print style="margin-bottom:16px">
    <span class="legenda-item"><i class="marca marca--aula"></i> Aula</span>
    <span class="legenda-item"><i class="marca marca--estudo"></i> Estudo Autónomo</span>
    <span class="legenda-item"><i class="marca marca--atividade"></i> Atividade Não Letiva / Pastoral</span>
    <span class="legenda-item"><i class="marca marca--conflito"></i> Com conflito pendente</span>
</div>

<div class="tabela-grelha-scroll">
<table class="tabela-grelha">
    <thead><tr><th class="col-hora">Hora</th><?php foreach (DIAS_SEMANA as $d): ?><th><?= $d ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($blocosGrelha as [$hi, $hf]): ?>
    <tr>
        <td class="col-hora"><strong><?= $hi ?></strong><?= $hf ?></td>
        <?php foreach (DIAS_SEMANA as $d):
            $ocupado = !empty($aulas[$d][$hi]);
        ?>
        <td class="<?= $ocupado ? '' : 'slot-vazio' ?>"
            <?= $ocupado ? '' : 'data-dia="'.$d.'" data-bloco="'.$hi.'|'.$hf.'" tabindex="0" role="button" title="Adicionar aula: '.$d.' '.$hi.'–'.$hf.'"' ?>>
            <?php foreach (($aulas[$d][$hi] ?? []) as $a):
                $classeTipo = $a['tipo_bloco'] === 'Aula' ? 'bloco-aula'
                    : ($a['tipo_bloco'] === 'Estudo_Autonomo' ? 'bloco-estudo' : 'bloco-atividade');
                $temConflito = in_array($a['id'], $aulasComConflito ?? []);
                $ehPastoral = $a['tipo_bloco'] === 'Atividade_Nao_Letiva'
                    && ehBlocoPastoral($turmaAtual, $a['dia_semana'], substr($a['hora_inicio'],0,5), substr($a['hora_fim'],0,5));
                $emEdicao = $edicao && (int)$edicao['id'] === (int)$a['id'];
            ?>
                <div class="bloco-grelha <?= $classeTipo ?> <?= $temConflito ? 'bloco-conflito' : '' ?> <?= $emEdicao ? 'bloco-em-edicao' : '' ?>">
                    <?php if ($a['tipo_bloco'] === 'Aula'): ?>
                        <strong><?= htmlspecialchars($a['disciplina'] ?? '—') ?></strong>
                        <?= $a['subgrupo'] ? '<em>('.htmlspecialchars($a['subgrupo']).')</em>' : '' ?>
                        <small>Regente: <?= htmlspecialchars($a['regente'] ?? $a['docente'] ?? '—') ?><?php if ($a['assistente']): ?> · Assist.: <?= htmlspecialchars($a['assistente']) ?><?php endif; ?></small>
                        <small><?= htmlspecialchars($a['sala'] ?? '—') ?></small>
                    <?php elseif ($ehPastoral): ?>
                        <em>Pastoral Universitária</em>
                    <?php else: ?>
                        <em><?= str_replace('_',' ',$a['tipo_bloco']) ?></em>
                    <?php endif; ?>
                    <?php if ($temConflito): ?><small class="aviso-conflito">⚠ conflito pendente</small><?php endif; ?>
                    <?php if ($ehPastoral): ?>
                        <small>obrigatório · não pode ser removido</small>
                    <?php else: ?>
                        <span class="actions">
                            <a class="link-acao" href="?editar_aula=<?= $a['id'] ?>">editar</a>
                            <form method="post" style="display:inline" data-confirmar="Remover este bloco?" data-ajax-remover=".bloco-grelha">
                                <?= campoCSRF() ?>
                                <input type="hidden" name="acao" value="apagar">
                                <input type="hidden" name="aula_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="link-acao link-acao--perigo">remover</button>
                            </form>
                        </span>
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

<script>
    var mapaDocentesDisciplina = <?= json_encode($mapaDocentesDisciplina) ?>;
    var mapaDisciplinaDocentes = <?= json_encode($mapaDisciplinaDocentes) ?>;
    (function () {
        var selTipo = document.getElementById('f-tipo-bloco');
        var selDisc = document.getElementById('f-disciplina');
        var selRegente = document.getElementById('f-regente');
        var selAssistente = document.getElementById('f-assistente');
        var selSala = document.getElementById('f-sala');
        var camposSoAula = document.querySelectorAll('.campo-so-aula');

        // Mostra os campos de disciplina/docentes/sala/subgrupo só quando
        // o bloco é mesmo uma "Aula" — Estudo Autónomo e Atividade Não
        // Letiva não precisam de nenhum deles.
        function alternarCampos() {
            var ehAula = !selTipo || selTipo.value === 'Aula';
            camposSoAula.forEach(function (c) { c.style.display = ehAula ? '' : 'none'; });
        }
        if (selTipo) { selTipo.addEventListener('change', alternarCampos); alternarCampos(); }

        // Restringe as opções de regente/assistente a quem está marcado em
        // Admin → Docentes como podendo lecionar a disciplina escolhida
        // (docente_disciplina). Sem marcações para essa disciplina, mostra
        // todos — não bloqueia quem ainda não preencheu essa lista.
        function filtrarDocentes(select, permitidos) {
            if (!select) { return; }
            Array.prototype.forEach.call(select.options, function (opt) {
                if (opt.value === '0' || !permitidos || !permitidos.length) { opt.hidden = false; return; }
                opt.hidden = permitidos.indexOf(parseInt(opt.value, 10)) === -1;
            });
            if (select.selectedOptions[0] && select.selectedOptions[0].hidden) { select.value = '0'; }
        }

        var notaSala = document.getElementById('f-sala-nota');
        // A sala do laboratório é só uma SUGESTÃO (RN16) — o campo nunca é
        // desativado, o coordenador pode sempre trocar para outra sala.
        // `forcarValor` só é true quando a disciplina muda por ação do
        // coordenador (não ao carregar a página), para não sobrepor uma
        // sala já guardada quando se está a editar uma aula existente.
        function aplicarLabDaDisciplina(salaId, forcarValor) {
            if (!selSala) { return; }
            var temLab = !!salaId;
            if (temLab && forcarValor) { selSala.value = salaId; }
            if (notaSala) { notaSala.hidden = !temLab; }
        }

        if (selDisc) {
            selDisc.addEventListener('change', function () {
                var info = mapaDocentesDisciplina[this.value];
                if (info) {
                    if (info.regente) { selRegente.value = info.regente; }
                    if (info.assistente) { selAssistente.value = info.assistente; }
                    // Laboratório da disciplina (admin/disciplinas.php) —
                    // só pré-preenche o campo Sala; continua editável.
                    if (selSala) { aplicarLabDaDisciplina(info.sala, true); }
                }
                var permitidos = mapaDisciplinaDocentes[this.value] || [];
                filtrarDocentes(selRegente, permitidos);
                filtrarDocentes(selAssistente, permitidos);
            });
            // Estado inicial (ex.: ao entrar em modo de edição já com disciplina escolhida)
            filtrarDocentes(selRegente, mapaDisciplinaDocentes[selDisc.value] || []);
            filtrarDocentes(selAssistente, mapaDisciplinaDocentes[selDisc.value] || []);
            var infoInicial = mapaDocentesDisciplina[selDisc.value];
            aplicarLabDaDisciplina(infoInicial ? infoInicial.sala : 0, false);
        }

        // A clicar em "editar" num bloco, a página recarrega com o
        // formulário já pré-preenchido — mas lá em cima, fora de vista se
        // a grelha for grande, o que facilmente parece "não fez nada".
        // Salta logo para o formulário e faz o cartão piscar, para o
        // resultado do clique ficar óbvio.
        var cartaoForm = document.getElementById('form-nova-aula');
        if (cartaoForm && cartaoForm.dataset.emEdicao === '1') {
            cartaoForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    })();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
