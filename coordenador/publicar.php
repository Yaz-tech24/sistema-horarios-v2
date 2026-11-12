<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';
require_once __DIR__ . '/../includes/funcoes_conflitos.php';
require_once __DIR__ . '/../includes/funcoes_notificacoes.php';

$curso = exigirCursoDoCoordenador($pdo);
$erro = $ok = null;

// Depois de arquivar, o horário sai da sessão (o Editor cria um novo) — a
// página de confirmação mostra-se uma vez, a partir deste aviso.
if (!empty($_SESSION['horario_acabado_de_arquivar'])) {
    unset($_SESSION['horario_acabado_de_arquivar']);
    $f = lerFlash();
    $pageTitle = 'Publicar horário';
    $pageDescription = 'Curso ' . $curso['sigla'] . ' — horário arquivado.';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="alert alert-success"><?= htmlspecialchars($f['texto'] ?? 'Horário arquivado.') ?></div>
    <a class="btn btn-primary" style="display:inline-flex;width:auto;padding-inline:2rem"
       href="<?= BASE_URL ?>/coordenador/editor_horario.php">Ir para o Editor de Horário</a>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Confirma que o horário em sessão é mesmo de uma turma do curso aberto.
$horario = horarioDoCursoEmSessao($pdo, $curso);
if (!$horario) {
    header('Location: ' . BASE_URL . '/coordenador/editor_horario.php'); exit;
}
$horarioId = (int)$horario['id'];

// Revalida sempre antes de contar (RN15 é verificada no servidor, aqui)
$nConf = revalidarHorario($pdo, $horarioId);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM aulas WHERE horario_id = ? AND tipo_bloco = 'Aula'");
$stmt->execute([$horarioId]);
$nAulas = (int)$stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['publicar']) || isset($_POST['arquivar']))) {
    validarCSRF();

    if (isset($_POST['publicar'])) {
        if (!in_array($horario['estado'], ['Rascunho', 'Em_Revisao'], true)) {
            // Sem isto, repetir o pedido (ou um F5) voltava a "publicar" o
            // que já estava publicado e a notificar todos os docentes outra vez.
            $erro = "Este horário já está publicado.";
        } elseif ($nAulas === 0) {
            $erro = "Não é possível publicar um horário sem nenhuma aula marcada.";
        } elseif ($nConf > 0) {
            $erro = "Não é possível publicar: existem $nConf conflito(s) por resolver.";
        } else {
            // Um e-mail por docente pode demorar alguns segundos cada —
            // folga para não ser cortado a meio pelo limite do PHP (30 s).
            @set_time_limit(180);
            $pdo->prepare("UPDATE horarios SET estado='Publicado', data_publicacao=NOW() WHERE id=?")
                ->execute([$horarioId]);
            registarHistorico($pdo, $horarioId, "Horário publicado");
            registarAuditoria($pdo, 'horario_publicado', $curso['sigla'] . ' — horário #' . $horarioId);
            $notif = notificarDocentesDoHorario($pdo, $horarioId,
                "O horário de " . $curso['sigla'] . " foi publicado. Consulta o teu horário atualizado.",
                "Horário de " . $curso['sigla'] . " disponível");
            definirFlash('ok', "Horário publicado com sucesso. {$notif['internas']} docente(s) notificado(s)"
                . (smtpConfigurado() ? " ({$notif['emails']} por email)." : "."));
            // Post-Redirect-Get, como no resto do sistema (ver README).
            header('Location: publicar.php'); exit;
        }
    } else {
        // Só um horário Publicado pode ser arquivado — fecha este ciclo e liberta
        // a turma para um novo horário (Rascunho) na próxima vez que for editada.
        if ($horario['estado'] !== 'Publicado') {
            $erro = "Só é possível arquivar um horário já publicado.";
        } else {
            $pdo->prepare("UPDATE horarios SET estado='Arquivado' WHERE id=?")->execute([$horarioId]);
            registarHistorico($pdo, $horarioId, "Horário arquivado");
            registarAuditoria($pdo, 'horario_arquivado', $curso['sigla'] . ' — horário #' . $horarioId);
            unset($_SESSION['horario_id_atual']);
            $_SESSION['horario_acabado_de_arquivar'] = true;
            definirFlash('ok', 'Horário arquivado. Volta ao Editor para começares um novo horário para esta turma.');
            header('Location: publicar.php'); exit;
        }
    }
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$pageTitle = 'Publicar horário';
$pageDescription = 'Curso ' . $curso['sigla'] . ' — estado atual: ' . str_replace('_', ' ', $horario['estado']) . '.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="grid two-column">
    <section class="card">
        <div class="card-header"><div><h2>Resumo da publicação</h2><p><?= htmlspecialchars($curso['sigla']) ?> — estado <?= str_replace('_',' ', $horario['estado']) ?></p></div></div>
        <div class="card-body">
            <?php if ($horario['estado'] === 'Arquivado'): ?>
                <p style="margin:0;color:var(--ink-soft);font-size:.88rem">Este horário está arquivado e já não pode ser alterado nem publicado novamente.
                    Volta ao <a href="<?= BASE_URL ?>/coordenador/editor_horario.php" style="color:var(--cobalt);font-weight:600">Editor de Horário</a> — a próxima aula que criares
                    para esta turma começa automaticamente um novo horário em Rascunho.</p>
            <?php elseif ($horario['estado'] === 'Publicado'): ?>
                <p style="margin:0 0 16px;color:var(--ink-soft);font-size:.88rem">Este horário já está publicado e visível aos docentes. Quando este período letivo terminar,
                    podes arquivá-lo para começar um novo horário de raiz para esta turma.</p>
                <form method="post" data-confirmar="Arquivar este horário? Deixará de poder ser editado.">
                    <?= campoCSRF() ?>
                    <button type="submit" name="arquivar" value="1" class="btn btn-secondary" style="width:auto;padding-inline:2rem"><?= icone('archive', 17) ?> Arquivar horário</button>
                </form>
            <?php else: ?>
                <?php if ($nAulas === 0): ?>
                    <p style="margin:0 0 16px;color:var(--ink-soft);font-size:.88rem">Este horário ainda não tem nenhuma aula marcada.
                        Cria-as no <a href="<?= BASE_URL ?>/coordenador/editor_horario.php" style="color:var(--cobalt);font-weight:600">Editor de Horário</a> antes de publicar.</p>
                <?php elseif ($nConf > 0): ?>
                    <p style="margin:0 0 16px;color:var(--ink-soft);font-size:.88rem">Resolve primeiro os conflitos na página
                        <a href="<?= BASE_URL ?>/coordenador/conflitos.php" style="color:var(--cobalt);font-weight:600">Conflitos</a>. A regra RN15 impede a publicação enquanto existirem.</p>
                <?php else: ?>
                    <p style="margin:0 0 16px;color:var(--ink-soft);font-size:.88rem">Zero conflitos — o horário pode ser publicado. Todos os docentes com aulas neste horário serão notificados automaticamente.</p>
                <?php endif; ?>
                <form method="post">
                    <?= campoCSRF() ?>
                    <button type="submit" name="publicar" value="1" class="btn btn-primary" style="width:auto;padding-inline:2rem" <?= ($nConf > 0 || $nAulas === 0) ? 'disabled' : '' ?>><?= icone('send', 17) ?> Publicar horário</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <aside class="card">
        <div class="card-header"><div><h2>Lista de verificação</h2><p>Estado atual do horário</p></div></div>
        <div class="card-body activity-list">
            <div class="activity-item"><span class="activity-dot <?= $nAulas > 0 ? 'teal' : 'orange' ?>"></span><strong>Aulas marcadas</strong><p><?= $nAulas ?> aula(s) neste horário</p></div>
            <div class="activity-item"><span class="activity-dot <?= $nConf === 0 ? 'teal' : 'orange' ?>"></span><strong>Conflitos pendentes</strong><p><?= $nConf ?> conflito(s) bloqueante(s)</p></div>
            <div class="activity-item"><span class="activity-dot <?= $horario['estado'] === 'Publicado' ? 'teal' : '' ?>"></span><strong>Publicação</strong><p>Estado: <?= str_replace('_',' ', $horario['estado']) ?></p></div>
            <?php if ($horario['data_publicacao']): ?>
            <div class="activity-item"><span class="activity-dot teal"></span><strong>Publicado em</strong><p><?= htmlspecialchars($horario['data_publicacao']) ?></p></div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
