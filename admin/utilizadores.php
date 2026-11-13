<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Administrador']);

$erro = $ok = null; $editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    if (isset($_POST['ativar'])) {
        $alvoId = (int)postTexto('ativar', 12);
        $stmt = $pdo->prepare("SELECT perfil, ativo FROM utilizadores WHERE id=?");
        $stmt->execute([$alvoId]);
        $alvo = $stmt->fetch();
        $novoAtivo = null;

        if (!$alvo) {
            $sucesso = false;
            $mensagem = 'Este utilizador já não existe.';
        } elseif ($alvo['perfil'] === 'Administrador' && $alvo['ativo'] && $alvoId === (int)$_SESSION['utilizador_id']) {
            // Vai desativar um Administrador ativo — impede se for a própria conta...
            $sucesso = false;
            $mensagem = 'Não podes desativar a tua própria conta.';
        } elseif ($alvo['perfil'] === 'Administrador' && $alvo['ativo'] && (function () use ($pdo, $alvoId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE perfil='Administrador' AND ativo=1 AND id != ?");
            $stmt->execute([$alvoId]);
            return (int)$stmt->fetchColumn() === 0;
        })()) {
            // ...ou se não sobrar nenhum outro Administrador ativo (senão
            // ninguém conseguiria voltar a entrar para desfazer isto).
            $sucesso = false;
            $mensagem = 'Não é possível desativar: é o único Administrador ativo.';
        } else {
            // Valor explícito em vez de "1 - ativo": dois cliques seguidos
            // (ou dois administradores ao mesmo tempo) acabam sempre no
            // estado que cada um viu e confirmou, nunca num vaivém.
            $novoAtivo = $alvo['ativo'] ? 0 : 1;
            $pdo->prepare("UPDATE utilizadores SET ativo = ? WHERE id=?")->execute([$novoAtivo, $alvoId]);
            // Desativar tem efeito imediato: a sessão dessa pessoa, se
            // estiver aberta, termina no pedido seguinte que ela fizer
            // (validarSessaoAtual() em includes/auth.php).
            registarAuditoria($pdo, $novoAtivo ? 'conta_ativada' : 'conta_desativada', 'conta #' . $alvoId);
            $sucesso = true;
            $mensagem = $novoAtivo ? 'Conta ativada.' : 'Conta desativada.';
        }

        if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem, ['ativo' => $novoAtivo]); }
        definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
        header('Location: utilizadores.php'); exit;
    }

    $nome   = postTexto('nome', 150);
    $email  = postTexto('email', 150);
    $perfil = postTexto('perfil', 20);
    $senha  = postSenha('senha');
    $id     = (int)postTexto('id', 12);
    $cursos = is_array($_POST['cursos'] ?? null) ? array_unique(array_map('intval', $_POST['cursos'])) : [];
    $docente_id = (int)postTexto('docente_id', 12);
    $souEu  = $id === (int)$_SESSION['utilizador_id'];

    // Impede que uma edição deixe de existir nenhum Administrador ativo
    // (ex.: mudar o próprio perfil, ou o do último Administrador, para outro).
    $perderiaUltimoAdmin = false;
    if ($id > 0 && $perfil !== 'Administrador') {
        $stmt = $pdo->prepare("SELECT perfil, ativo FROM utilizadores WHERE id=?");
        $stmt->execute([$id]);
        $atual = $stmt->fetch();
        if ($atual && $atual['perfil'] === 'Administrador' && $atual['ativo']) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE perfil='Administrador' AND ativo=1 AND id != ?");
            $stmt->execute([$id]);
            $perderiaUltimoAdmin = (int)$stmt->fetchColumn() === 0;
        }
    }

    if ($nome === '' || $email === '' || !in_array($perfil, ['Administrador','Coordenador','Docente'], true)) {
        $erro = "Preenche o nome, o e-mail e o perfil.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Este e-mail não parece válido.";
    } elseif (($id === 0 || $senha !== '') && ($erroSenha = validarPalavraPasse($senha, $email))) {
        $erro = $erroSenha;
    } elseif ($perderiaUltimoAdmin) {
        $erro = "Não é possível mudar o perfil: é o único Administrador ativo.";
    } else {
        // Tudo ou nada: sem transação, um erro a meio (ex.: e-mail repetido
        // depois de já se terem apagado os cursos do coordenador) deixava a
        // conta meio gravada.
        $pdo->beginTransaction();
        try {
            $criada = $id === 0;
            $novoHash = $senha !== '' ? password_hash($senha, PASSWORD_DEFAULT) : null;
            if ($id > 0) {
                if ($novoHash !== null) {
                    // Palavra-passe nova = também desfaz um bloqueio por
                    // tentativas falhadas que a conta tivesse.
                    $stmt = $pdo->prepare(
                        "UPDATE utilizadores SET nome=?, email=?, perfil=?, password_hash=?,
                            tentativas_falhadas=0, bloqueado_ate=NULL WHERE id=?");
                    $stmt->execute([$nome, $email, $perfil, $novoHash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE utilizadores SET nome=?, email=?, perfil=? WHERE id=?");
                    $stmt->execute([$nome, $email, $perfil, $id]);
                }
                if ($stmt->rowCount() === 0) {
                    $existe = $pdo->prepare("SELECT 1 FROM utilizadores WHERE id=?");
                    $existe->execute([$id]);
                    if (!$existe->fetch()) { throw new RuntimeException('utilizador inexistente'); }
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO utilizadores (nome, email, password_hash, perfil) VALUES (?,?,?,?)");
                $stmt->execute([$nome, $email, $novoHash, $perfil]);
                $id = (int)$pdo->lastInsertId();
            }

            // Coordenador: define QUAIS cursos pode gerir (controlo de acesso por curso)
            $pdo->prepare("DELETE FROM coordenador_curso WHERE utilizador_id=?")->execute([$id]);
            if ($perfil === 'Coordenador') {
                $ins = $pdo->prepare(
                    "INSERT INTO coordenador_curso (utilizador_id, curso_id)
                     SELECT ?, id FROM cursos WHERE id = ?"); // ignora ids de cursos que não existam
                foreach ($cursos as $cid) { $ins->execute([$id, $cid]); }
            }

            // Docente: liga a conta ao registo na tabela docentes — só a um
            // registo ainda livre (ou já desta conta), nunca "roubando" o de
            // outra conta por um id forjado no pedido.
            $pdo->prepare("UPDATE docentes SET utilizador_id=NULL WHERE utilizador_id=?")->execute([$id]);
            if ($perfil === 'Docente' && $docente_id > 0) {
                $pdo->prepare("UPDATE docentes SET utilizador_id=? WHERE id=? AND utilizador_id IS NULL")
                    ->execute([$id, $docente_id]);
            }
            $pdo->commit();

            if ($novoHash !== null) {
                if ($souEu) {
                    // Mudei a minha própria: a sessão atual continua (com a
                    // marca nova); as outras desta conta terminam.
                    $_SESSION['senha_marca'] = marcaDaSenha($novoHash);
                    definirMudancaObrigatoria($pdo, $id, false);
                } else {
                    // Palavra-passe escolhida pelo Administrador é sempre
                    // provisória: a pessoa troca-a no primeiro acesso, e a
                    // partir daí só ela a conhece.
                    definirMudancaObrigatoria($pdo, $id, true);
                }
            }
            registarAuditoria($pdo, $criada ? 'utilizador_criado' : 'utilizador_editado',
                $email . ' (' . $perfil . ')' . ($novoHash !== null && !$criada ? ' — palavra-passe redefinida' : ''));

            definirFlash('ok', 'Utilizador guardado.'
                . ($novoHash !== null && !$souEu ? ' A palavra-passe é provisória: vai ser pedida uma nova no primeiro acesso.' : ''));
            header('Location: utilizadores.php'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Erro ao guardar utilizador: ' . $e->getMessage());
            $erro = "Não foi possível guardar — confirma se já existe outra conta com este e-mail.";
            if (!empty($criada)) { $id = 0; } // a conta nova não chegou a existir
        }
    }
}

$meusCursos = []; $meuDocente = 0;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE id=?");
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
    if ($editar) {
        $stmt = $pdo->prepare("SELECT curso_id FROM coordenador_curso WHERE utilizador_id=?");
        $stmt->execute([$editar['id']]);
        $meusCursos = array_column($stmt->fetchAll(), 'curso_id');
        $stmt = $pdo->prepare("SELECT id FROM docentes WHERE utilizador_id=?");
        $stmt->execute([$editar['id']]);
        $meuDocente = (int)($stmt->fetch()['id'] ?? 0);
    }
}

if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }

$cursos = $pdo->query("SELECT * FROM cursos ORDER BY sigla")->fetchAll();
// Só docentes ainda sem conta, ou já ligados a este utilizador — senão o
// dropdown deixava "roubar" silenciosamente o registo de outra conta Docente.
$docentesStmt = $pdo->prepare("SELECT * FROM docentes WHERE utilizador_id IS NULL OR utilizador_id = ? ORDER BY nome");
$docentesStmt->execute([$editar['id'] ?? 0]);
$docentes = $docentesStmt->fetchAll();
$lista = $pdo->query(
    "SELECT u.*, GROUP_CONCAT(c.sigla ORDER BY c.sigla SEPARATOR ', ') AS cursos_geridos
     FROM utilizadores u
     LEFT JOIN coordenador_curso cc ON cc.utilizador_id = u.id
     LEFT JOIN cursos c ON c.id = cc.curso_id
     GROUP BY u.id ORDER BY u.perfil, u.nome")->fetchAll();

$pageTitle = 'Utilizadores e Permissões';
$pageDescription = 'Contas de acesso. Um Coordenador só verá os cursos que aqui lhe atribuíres.';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<section class="card <?= $editar ? 'cartao--em-edicao' : '' ?>" style="margin-bottom:20px">
    <div class="card-header"><div><h2><?= $editar ? 'Editar utilizador' : 'Novo utilizador' ?></h2><p>Nome, e-mail, perfil e — consoante o perfil — os cursos que gere ou o registo de docente a que se liga.</p></div></div>
    <div class="card-body">
        <form method="post">
            <?= campoCSRF() ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
            <div class="form-row">
                <div class="campo"><label>Nome</label>
                    <input class="form-control" type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>"></div>
                <div class="campo"><label>E-mail</label>
                    <input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($editar['email'] ?? '') ?>"></div>
                <div class="campo"><label>Perfil</label>
                    <select class="form-control form-select" name="perfil" id="sel-perfil" required>
                        <option value="">— escolher —</option>
                        <?php foreach (['Administrador','Coordenador','Docente'] as $p): ?>
                        <option <?= (($editar['perfil'] ?? '')===$p)?'selected':'' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="campo"><label>Palavra-passe provisória <?= $editar ? '(vazio = manter)' : '' ?></label>
                    <input class="form-control" type="password" name="senha" minlength="<?= SENHA_MIN ?>" maxlength="<?= SENHA_MAX_BYTES ?>"
                           autocomplete="new-password" <?= $editar ? '' : 'required' ?>></div>
            </div>
            <p style="margin:8px 0 0;color:var(--ink-soft);font-size:.76rem">
                Mínimo <?= SENHA_MIN ?> caracteres. Uma palavra-passe definida aqui é provisória: no primeiro acesso,
                o sistema obriga a pessoa a escolher a sua antes de continuar.
            </p>

            <div class="campo" style="margin-top:15px">
                <span class="campo-rotulo" id="rot-cursos-geridos">Cursos que gere (só para Coordenador)</span>
                <div class="grelha-checkboxes" role="group" aria-labelledby="rot-cursos-geridos" style="border:1px solid var(--line);border-radius:1px;padding:12px;background:var(--surface)">
                    <?php foreach ($cursos as $c): ?>
                    <label>
                        <input type="checkbox" name="cursos[]" value="<?= $c['id'] ?>"
                            <?= in_array($c['id'], $meusCursos) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($c['sigla']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="campo" style="margin-top:15px">
                <label>Registo de docente (só para Docente)</label>
                <select class="form-control form-select" name="docente_id" style="max-width:340px">
                    <option value="0">— nenhum —</option>
                    <?php foreach ($docentes as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($meuDocente == $d['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top:18px">
                <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem;">
                    <?= $editar ? 'Atualizar utilizador' : 'Criar utilizador' ?></button>
                <?php if ($editar): ?><a class="btn btn-secondary" href="utilizadores.php" style="width:auto;padding-inline:2rem;margin-left:8px">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>
</section>

<section class="card">
    <div class="toolbar">
        <div class="search-box"><?= icone('search', 18) ?><input type="search" placeholder="Pesquisar utilizador…" data-filtro-tabela="#tabela-utilizadores"></div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table" id="tabela-utilizadores">
            <thead><tr><th>Utilizador</th><th>Perfil</th><th>Cursos geridos</th><th>Última entrada</th><th>Estado</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $u):
                // Inativa e nunca entrou = registo feito em auth/registar.php à espera de ativação.
                $porAtivar = !$u['ativo'] && array_key_exists('ultimo_login', $u) && $u['ultimo_login'] === null;
            ?>
            <tr>
                <td><div class="cell-main"><span class="cell-icon"><?= htmlspecialchars(mb_strtoupper(mb_substr($u['nome'], 0, 2))) ?></span><span><strong><?= htmlspecialchars($u['nome']) ?></strong><small><?= htmlspecialchars($u['email']) ?></small></span></div></td>
                <td><?= htmlspecialchars($u['perfil']) ?></td>
                <td><?= htmlspecialchars($u['cursos_geridos'] ?? '—') ?></td>
                <td>
                    <?php if (!empty($u['ultimo_login'])): ?>
                        <small style="color:var(--ink-soft)"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($u['ultimo_login']))) ?></small>
                    <?php elseif ($porAtivar): ?>
                        <span class="badge badge-info">registo novo — por ativar</span>
                    <?php else: ?>
                        <small style="color:var(--ink-soft)">nunca entrou</small>
                    <?php endif; ?>
                    <?php if (!empty($u['deve_mudar_senha'])): ?><br><small style="color:var(--ink-soft)">palavra-passe provisória</small><?php endif; ?>
                </td>
                <td><span class="badge celula-estado <?= $u['ativo'] ? 'badge-success' : 'badge-warning' ?>"><span class="status-dot"></span><?= $u['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
                <td>
                    <div class="actions">
                        <a class="btn btn-secondary btn-icon" title="Editar" href="?editar=<?= $u['id'] ?>"><?= icone('edit', 16) ?></a>
                        <form method="post" data-confirmar="<?= $u['ativo'] ? 'Desativar' : 'Ativar' ?> esta conta?" data-ajax-alternar="1">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="ativar" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm"><?= $u['ativo'] ? 'Desativar' : 'Ativar' ?></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
