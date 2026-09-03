<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';

$curso = exigirCursoDoCoordenador($pdo);
$horarioId = (int)($_SESSION['horario_id_atual'] ?? 0);
if (!$horarioId) { header('Location: ' . BASE_URL . '/coordenador/editor_horario.php'); exit; }

$stmt = $pdo->prepare(
    "SELECT hv.*, u.nome AS utilizador FROM historico_versoes hv
     JOIN utilizadores u ON hv.utilizador_id = u.id
     WHERE hv.horario_id = ? ORDER BY hv.data_alteracao DESC");
$stmt->execute([$horarioId]);
$historico = $stmt->fetchAll();

$pageTitle = 'Histórico';
$pageDescription = 'Todas as alterações registadas no horário de ' . $curso['sigla'] . ', da mais recente para a mais antiga.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$historico): ?>
    <div class="estado-vazio">
        <p><strong>Ainda não há alterações registadas.</strong></p>
    </div>
<?php else: ?>
<section class="card">
    <div class="toolbar">
        <div class="search-box"><?= icone('search', 18) ?><input type="search" placeholder="Pesquisar no histórico…" data-filtro-tabela="#tabela-historico"></div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table" id="tabela-historico">
            <thead><tr><th>Data</th><th>Quem</th><th>O quê</th></tr></thead>
            <tbody>
            <?php foreach ($historico as $h): ?>
            <tr>
                <td><?= htmlspecialchars($h['data_alteracao']) ?></td>
                <td><div class="cell-main"><span class="cell-icon"><?= htmlspecialchars(mb_strtoupper(mb_substr($h['utilizador'], 0, 2))) ?></span><span><strong><?= htmlspecialchars($h['utilizador']) ?></strong></span></div></td>
                <td><?= htmlspecialchars($h['descricao']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
