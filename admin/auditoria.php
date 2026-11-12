<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador']);

// Registo de atividade: quem entrou, quem falhou a entrar e de onde, e o
// que foi mudado nas contas. Só leitura — é o sítio onde o Administrador
// vem ver "o que se passou" quando alguém diz que não consegue entrar ou
// quando há suspeita de acesso indevido.

// Rótulo legível + cor do distintivo de cada tipo de acontecimento.
const ACOES_AUDITORIA = [
    'login_ok'            => ['Entrada', 'badge-success'],
    'logout'              => ['Saída', 'badge-info'],
    'login_falhado'       => ['Entrada falhada', 'badge-warning'],
    'conta_bloqueada'     => ['Conta bloqueada (5 falhas)', 'badge-danger'],
    'senha_atual_errada'  => ['Palavra-passe atual errada', 'badge-warning'],
    'senha_alterada'      => ['Palavra-passe alterada', 'badge-info'],
    'senha_redefinida'    => ['Palavra-passe redefinida por e-mail', 'badge-info'],
    'recuperacao_pedida'  => ['Pedido de recuperação', 'badge-info'],
    'email_alterado'      => ['E-mail alterado', 'badge-info'],
    'registo_conta'       => ['Novo registo de docente', 'badge-info'],
    'utilizador_criado'   => ['Utilizador criado', 'badge-info'],
    'utilizador_editado'  => ['Utilizador editado', 'badge-info'],
    'conta_ativada'       => ['Conta ativada', 'badge-success'],
    'conta_desativada'    => ['Conta desativada', 'badge-danger'],
    'horario_publicado'   => ['Horário publicado', 'badge-success'],
    'horario_arquivado'   => ['Horário arquivado', 'badge-info'],
];

const AUDITORIA_POR_PAGINA = 100;

$filtroAcao = $_GET['acao'] ?? '';
if (!is_string($filtroAcao) || !isset(ACOES_AUDITORIA[$filtroAcao])) { $filtroAcao = ''; }
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$offset = ($pagina - 1) * AUDITORIA_POR_PAGINA;

$registos = []; $total = 0; $falhas24h = 0; $entradas24h = 0; $tabelaEmFalta = false;
try {
    $where = $filtroAcao ? "WHERE r.acao = ?" : "";
    $params = $filtroAcao ? [$filtroAcao] : [];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registo_auditoria r $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // LIMIT/OFFSET são inteiros calculados aqui (nunca texto do pedido).
    $stmt = $pdo->prepare(
        "SELECT r.*, u.nome AS utilizador_nome, u.email AS utilizador_email
         FROM registo_auditoria r
         LEFT JOIN utilizadores u ON u.id = r.utilizador_id
         $where
         ORDER BY r.id DESC
         LIMIT " . AUDITORIA_POR_PAGINA . " OFFSET " . (int)$offset);
    $stmt->execute($params);
    $registos = $stmt->fetchAll();

    $falhas24h = (int)$pdo->query(
        "SELECT COUNT(*) FROM registo_auditoria
         WHERE acao = 'login_falhado' AND criado_em > DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
    $entradas24h = (int)$pdo->query(
        "SELECT COUNT(*) FROM registo_auditoria
         WHERE acao = 'login_ok' AND criado_em > DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
} catch (PDOException $e) {
    $tabelaEmFalta = true;
    error_log('Registo de auditoria indisponível: ' . $e->getMessage());
}

$totalPaginas = max(1, (int)ceil($total / AUDITORIA_POR_PAGINA));
$linkPagina = fn(int $p) => '?' . http_build_query(array_filter(['acao' => $filtroAcao, 'pagina' => $p > 1 ? $p : null]));

$pageTitle = 'Registo de atividade';
$pageDescription = 'Entradas no sistema, tentativas falhadas e alterações de contas — do mais recente para o mais antigo.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($tabelaEmFalta): ?>
    <div class="alert alert-error">
        O registo de atividade ainda não existe nesta base de dados. Corre <strong>sql/migracao_seguranca_v2.sql</strong>
        (ou <strong>sql/atualizar_producao_vps.sql</strong>, em Docker) — ver README, secção «Segurança».
    </div>
<?php else: ?>

<div class="grid stats-grid" style="margin-bottom:20px">
    <article class="stat-card accent-teal">
        <div class="stat-card-top"><span class="stat-icon"><?= icone('check') ?></span></div>
        <div class="stat-value"><?= $entradas24h ?></div>
        <div class="stat-label">Entradas nas últimas 24 horas</div>
    </article>
    <article class="stat-card <?= $falhas24h >= IP_MAX_FALHAS ? 'accent-danger' : 'accent-orange' ?>">
        <div class="stat-card-top"><span class="stat-icon"><?= icone('alert') ?></span></div>
        <div class="stat-value"><?= $falhas24h ?></div>
        <div class="stat-label">Entradas falhadas nas últimas 24 horas</div>
    </article>
    <article class="stat-card accent-purple">
        <div class="stat-card-top"><span class="stat-icon"><?= icone('history') ?></span></div>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-label"><?= $filtroAcao ? 'Registos deste tipo' : 'Registos guardados (1 ano)' ?></div>
    </article>
</div>

<section class="card">
    <div class="toolbar">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <label for="f-acao" style="font-size:.79rem;font-weight:600;color:var(--ink-soft)">Tipo</label>
            <select class="form-control form-select filter-select" name="acao" id="f-acao" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach (ACOES_AUDITORIA as $chave => [$rotulo]): ?>
                <option value="<?= $chave ?>" <?= $filtroAcao === $chave ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="search-box"><?= icone('search', 18) ?><input type="search" placeholder="Pesquisar nesta página…" data-filtro-tabela="#tabela-auditoria"></div>
    </div>

    <?php if (!$registos): ?>
        <div class="estado-vazio" style="border:0">
            <p><strong>Sem registos<?= $filtroAcao ? ' deste tipo' : '' ?>.</strong></p>
        </div>
    <?php else: ?>
    <div class="data-table-wrap">
        <table class="data-table" id="tabela-auditoria">
            <thead><tr><th>Quando</th><th>O quê</th><th>Quem</th><th>Detalhe</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($registos as $r):
                [$rotulo, $classe] = ACOES_AUDITORIA[$r['acao']] ?? [$r['acao'], 'badge-info'];
            ?>
            <tr>
                <td><small style="color:var(--ink-soft)"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($r['criado_em']))) ?></small></td>
                <td><span class="badge <?= $classe ?>"><?= htmlspecialchars($rotulo) ?></span></td>
                <td>
                    <?php if ($r['utilizador_nome']): ?>
                        <strong><?= htmlspecialchars($r['utilizador_nome']) ?></strong><br>
                        <small style="color:var(--ink-soft)"><?= htmlspecialchars($r['utilizador_email']) ?></small>
                    <?php else: ?>
                        <small style="color:var(--ink-soft)">— sem conta associada —</small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['detalhe'] ?? '') ?></td>
                <td><small style="color:var(--ink-soft)"><?= htmlspecialchars($r['ip'] ?? '') ?></small></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <span style="color:var(--ink-soft);font-size:.75rem">Página <?= $pagina ?> de <?= $totalPaginas ?> · <?= $total ?> registo(s)</span>
        <span style="display:flex;gap:8px">
            <?php if ($pagina > 1): ?><a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($linkPagina($pagina - 1)) ?>">← Mais recentes</a><?php endif; ?>
            <?php if ($pagina < $totalPaginas): ?><a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($linkPagina($pagina + 1)) ?>">Mais antigos →</a><?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
