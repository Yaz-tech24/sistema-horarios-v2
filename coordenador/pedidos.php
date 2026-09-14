<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';

// Pedidos que os docentes enviaram sobre os horários deste curso. O
// âmbito segue a mesma regra do resto do fluxo do coordenador: o curso
// vem da sessão validada, nunca do pedido, e resolver um pedido confirma
// primeiro que ele pertence mesmo a um horário deste curso.
$curso = exigirCursoDoCoordenador($pdo);
$cursoId = (int)$curso['id'];

$erro = $ok = null;

function pedidoDoCurso(PDO $pdo, int $pedidoId, int $cursoId): ?array {
    $stmt = $pdo->prepare(
        "SELECT p.* FROM pedidos p
         JOIN horarios h ON p.horario_id = h.id
         JOIN turmas t ON h.turma_id = t.id
         WHERE p.id = ? AND t.curso_id = ?");
    $stmt->execute([$pedidoId, $cursoId]);
    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $pid = (int)($_POST['pedido_id'] ?? 0);
    $novoEstado = ($_POST['acao'] ?? '') === 'reabrir' ? 'Aberto' : 'Resolvido';

    if (!pedidoDoCurso($pdo, $pid, $cursoId)) {
        $sucesso = false; $mensagem = 'Esse pedido não pertence ao curso que estás a gerir.';
    } else {
        if ($novoEstado === 'Resolvido') {
            $pdo->prepare("UPDATE pedidos SET estado='Resolvido', resolvido_em=NOW(), resolvido_por=? WHERE id=?")
                ->execute([$_SESSION['utilizador_id'], $pid]);
            $mensagem = 'Pedido marcado como resolvido.';
        } else {
            $pdo->prepare("UPDATE pedidos SET estado='Aberto', resolvido_em=NULL, resolvido_por=NULL WHERE id=?")
                ->execute([$pid]);
            $mensagem = 'Pedido reaberto.';
        }
        $sucesso = true;
    }
    if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem); }
    definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
    header('Location: pedidos.php'); exit;
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$stmt = $pdo->prepare(
    "SELECT p.*, u.nome AS docente_nome, t.ano_curricular, t.nome_turma, t.regime
     FROM pedidos p
     JOIN horarios h ON p.horario_id = h.id
     JOIN turmas t ON h.turma_id = t.id
     JOIN utilizadores u ON p.utilizador_id = u.id
     WHERE t.curso_id = ?
     ORDER BY p.estado = 'Resolvido', p.criado_em DESC");
$stmt->execute([$cursoId]);
$pedidos = $stmt->fetchAll();

$abertos = array_filter($pedidos, fn($p) => $p['estado'] === 'Aberto');

$pageTitle = 'Pedidos dos docentes';
$pageDescription = $curso['sigla'] . ' — o que os docentes assinalaram sobre os horários deste curso.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<?php if (!$pedidos): ?>
    <div class="estado-vazio">
        <p><strong>Ainda não há pedidos neste curso.</strong></p>
        <p>Quando um docente assinalar um problema no horário dele, aparece aqui.</p>
    </div>
<?php else: ?>

<section class="card">
    <div class="card-header">
        <div><h2>Por resolver</h2><p>Enviados pelos docentes a partir do horário deles.</p></div>
        <?php if ($abertos): ?><span class="badge badge-warning"><span class="status-dot"></span><?= count($abertos) ?> aberto(s)</span>
        <?php else: ?><span class="badge badge-success"><span class="status-dot"></span>nenhum aberto</span><?php endif; ?>
    </div>
    <div class="notification-list">
        <?php foreach ($pedidos as $p): $aberto = $p['estado'] === 'Aberto'; ?>
        <div class="notification-item <?= $aberto ? 'unread' : '' ?>">
            <span class="stat-icon"><?= icone($aberto ? 'alert' : 'check') ?></span>
            <div class="notification-content">
                <h3><?= htmlspecialchars($p['docente_nome']) ?>
                    <span style="font-weight:400;color:var(--ink-soft)">·
                    <?= $p['ano_curricular'] ?>º <?= htmlspecialchars($p['nome_turma']) ?> (<?= htmlspecialchars($p['regime']) ?>)</span>
                </h3>
                <p style="color:var(--ink)"><?= nl2br(htmlspecialchars($p['mensagem'])) ?></p>
                <p style="font-size:.72rem"><strong>Aula:</strong> <?= htmlspecialchars($p['referencia']) ?>
                    <?php if (!$p['aula_id']): ?><span class="badge badge-info" style="margin-left:6px">aula já removida</span><?php endif; ?>
                </p>
                <time><?= htmlspecialchars(substr($p['criado_em'], 0, 16)) ?><?= $p['resolvido_em'] ? ' · resolvido em ' . htmlspecialchars(substr($p['resolvido_em'], 0, 16)) : '' ?></time>
            </div>
            <form method="post" style="align-self:center">
                <?= campoCSRF() ?>
                <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                <input type="hidden" name="acao" value="<?= $aberto ? 'resolver' : 'reabrir' ?>">
                <button type="submit" class="btn <?= $aberto ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                    <?= $aberto ? 'Marcar resolvido' : 'Reabrir' ?>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
