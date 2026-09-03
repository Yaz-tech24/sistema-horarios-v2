<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcoes_coordenador.php';

$perfil = $_SESSION['perfil'];

// Módulos por perfil: [título, descrição, link, dono]
$modulos = [
    'Administrador' => [
        ['Cursos', 'Cadastrar e gerir os cursos da faculdade.', '/admin/cursos.php', 'book'],
        ['Disciplinas', 'Disciplinas de cada curso, com carga horária.', '/admin/disciplinas.php', 'layers'],
        ['Docentes', 'Docentes, categorias e disponibilidade semanal.', '/admin/docentes.php', 'users'],
        ['Salas', 'Salas, tipos e capacidade.', '/admin/salas.php', 'door'],
        ['Turmas', 'Turmas por curso, ano, regime e turno.', '/admin/turmas.php', 'calendar'],
        ['Utilizadores', 'Contas de acesso e cursos de cada coordenador.', '/admin/utilizadores.php', 'shield'],
        ['Relatórios', 'Ocupação de salas e carga docente.', '/relatorios/ocupacao_salas.php', 'chart'],
    ],
    'Coordenador' => [
        ['Escolher Curso', 'Selecionar o curso com que vais trabalhar.', '/coordenador/escolher_curso.php', 'book'],
        ['Editor de Horário', 'Criar as aulas na grelha semanal.', '/coordenador/editor_horario.php', 'calendar'],
        ['Conflitos', 'Ver e resolver conflitos pendentes.', '/coordenador/conflitos.php', 'alert'],
        ['Publicar', 'Publicar o horário quando estiver sem conflitos.', '/coordenador/publicar.php', 'send'],
        ['Histórico', 'Registo de todas as alterações feitas.', '/coordenador/historico.php', 'history'],
    ],
    'Docente' => [
        ['Meu Horário', 'As tuas aulas, em todos os cursos onde lecionas.', '/docente/meu_horario.php', 'calendar'],
        ['Notificações', 'Avisos de alterações ao teu horário.', '/docente/notificacoes.php', 'bell'],
    ],
];

$meusModulos = $modulos[$perfil] ?? [];

/* Estatísticas reais do topo — só números que já existem na base de
   dados; nada de valores decorativos. */
$stats = [];
if ($perfil === 'Administrador') {
    $stats[] = ['book', (int)$pdo->query("SELECT COUNT(*) FROM cursos")->fetchColumn(), 'Cursos ativos', null];
    $stats[] = ['users', (int)$pdo->query("SELECT COUNT(*) FROM docentes")->fetchColumn(), 'Docentes registados', 'accent-teal'];
    $stats[] = ['calendar', (int)$pdo->query("SELECT COUNT(DISTINCT turma_id) FROM horarios WHERE estado='Publicado'")->fetchColumn(), 'Turmas com horário publicado', 'accent-purple'];
    $nConf = (int)$pdo->query("SELECT COUNT(*) FROM conflitos WHERE estado='Pendente'")->fetchColumn();
    $stats[] = ['alert', $nConf, 'Conflitos pendentes', 'accent-orange'];
} elseif ($perfil === 'Coordenador') {
    $meusCursos = cursosDoCoordenador($pdo);
    $idsCursos = array_column($meusCursos, 'id');
    $stats[] = ['book', count($meusCursos), 'Cursos que geres', null];
    if ($idsCursos) {
        $marcas = implode(',', array_fill(0, count($idsCursos), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT h.turma_id) FROM horarios h
            JOIN turmas t ON t.id = h.turma_id WHERE t.curso_id IN ($marcas) AND h.estado='Publicado'");
        $stmt->execute($idsCursos);
        $stats[] = ['calendar', (int)$stmt->fetchColumn(), 'Turmas com horário publicado', 'accent-teal'];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM conflitos co
            JOIN aulas a ON co.aula_id_1 = a.id JOIN horarios h ON a.horario_id = h.id
            JOIN turmas t ON t.id = h.turma_id WHERE t.curso_id IN ($marcas) AND co.estado='Pendente'");
        $stmt->execute($idsCursos);
        $stats[] = ['alert', (int)$stmt->fetchColumn(), 'Conflitos pendentes', 'accent-orange'];
    }
} elseif ($perfil === 'Docente') {
    $stmt = $pdo->prepare("SELECT id FROM docentes WHERE utilizador_id = ?");
    $stmt->execute([$_SESSION['utilizador_id']]);
    $docenteId = $stmt->fetchColumn();
    if ($docenteId) {
        $stmt = $pdo->prepare("SELECT a.disciplina_id, a.hora_inicio, a.hora_fim, h.turma_id FROM aulas a
            JOIN horarios h ON a.horario_id = h.id
            WHERE h.estado = 'Publicado' AND a.tipo_bloco = 'Aula'
              AND (a.docente_regente_id = ? OR a.docente_assistente_id = ?)");
        $stmt->execute([$docenteId, $docenteId]);
        $aulas = $stmt->fetchAll();
        $horas = 0;
        foreach ($aulas as $a) { $horas += (strtotime($a['hora_fim']) - strtotime($a['hora_inicio'])) / 3600; }
        $stats[] = ['calendar', count($aulas), 'Aulas por semana', null];
        $stats[] = ['book', count(array_unique(array_column($aulas, 'disciplina_id'))), 'Disciplinas', 'accent-teal'];
        $stats[] = ['users', count(array_unique(array_column($aulas, 'turma_id'))), 'Turmas', 'accent-purple'];
        $stats[] = ['clock', round($horas, 1), 'Horas semanais', 'accent-orange'];
    }
}

$pageTitle = 'Visão geral';
$pageDescription = 'Olá, ' . explode(' ', $_SESSION['nome'])[0] . '. Estás com sessão iniciada como ' . $perfil . '.';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($stats): ?>
<div class="grid stats-grid">
    <?php foreach ($stats as [$ic, $valor, $label, $accent]): ?>
        <article class="stat-card <?= $accent ?? '' ?>">
            <div class="stat-card-top"><span class="stat-icon"><?= icone($ic) ?></span></div>
            <div class="stat-value"><?= htmlspecialchars((string)$valor) ?></div>
            <div class="stat-label"><?= htmlspecialchars($label) ?></div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="card" style="margin-top:20px">
    <div class="card-header"><div><h2>Acesso rápido</h2><p>Escolhe por onde queres começar.</p></div></div>
    <div class="card-body quick-actions">
        <?php foreach ($meusModulos as [$titulo, $descricao, $link, $ic]):
            $existe = file_exists(__DIR__ . $link);
        ?>
            <a class="quick-action" href="<?= $existe ? BASE_URL . $link : '#' ?>" <?= $existe ? '' : 'data-toast="Em desenvolvimento."' ?>>
                <span class="stat-icon"><?= icone($ic) ?></span>
                <strong><?= htmlspecialchars($titulo) ?></strong>
                <small><?= htmlspecialchars($descricao) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
