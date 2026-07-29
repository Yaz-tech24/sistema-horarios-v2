<?php
require_once __DIR__ . '/includes/auth.php';
exigirLogin();
require_once __DIR__ . '/includes/header.php';

$perfil = $_SESSION['perfil'];

// Módulos por perfil: [título, descrição, link, dono]
$modulos = [
    'Administrador' => [
        ['Cursos', 'Cadastrar e gerir os cursos da faculdade.', '/admin/cursos.php', 'Anancintia'],
        ['Disciplinas', 'Disciplinas de cada curso, com carga horária.', '/admin/disciplinas.php', 'Anancintia'],
        ['Docentes', 'Docentes, categorias e disponibilidade semanal.', '/admin/docentes.php', 'Anancintia'],
        ['Salas', 'Salas, tipos e capacidade.', '/admin/salas.php', 'Anancintia'],
        ['Turmas', 'Turmas por curso, ano, regime e turno.', '/admin/turmas.php', 'Anancintia'],
    ],
    'Coordenador' => [
        ['Escolher Curso', 'Selecionar o curso com que vais trabalhar.', '/coordenador/escolher_curso.php', 'Darleny'],
        ['Editor de Horário', 'Criar as aulas na grelha semanal.', '/coordenador/editor_horario.php', 'Eliana'],
        ['Conflitos', 'Ver e resolver conflitos pendentes.', '/coordenador/conflitos.php', 'Eliana'],
        ['Publicar', 'Publicar o horário quando estiver sem conflitos.', '/coordenador/publicar.php', 'Darleny'],
        ['Histórico', 'Registo de todas as alterações feitas.', '/coordenador/historico.php', 'Darleny'],
    ],
    'Docente' => [
        ['Meu Horário', 'As tuas aulas, em todos os cursos onde lecionas.', '/docente/meu_horario.php', 'Amélia'],
        ['Notificações', 'Avisos de alterações ao teu horário.', '/docente/notificacoes.php', 'Amélia'],
    ],
];

$meusModulos = $modulos[$perfil] ?? [];
?>

<div class="pagina-cabecalho">
    <h1 class="pagina-titulo">Olá, <?= htmlspecialchars(explode(' ', $_SESSION['nome'])[0]) ?></h1>
    <p class="pagina-sub">
        Estás com sessão iniciada como <strong><?= htmlspecialchars($perfil) ?></strong>.
        Escolhe por onde queres começar.
    </p>
    <div class="regua"></div>
</div>

<div class="grelha-cartoes">
    <?php foreach ($meusModulos as [$titulo, $descricao, $link, $dono]):
        $existe = file_exists(__DIR__ . $link);
    ?>
        <a class="cartao-modulo" href="<?= $existe ? BASE_URL . $link : '#' ?>">
            <h3><?= htmlspecialchars($titulo) ?></h3>
            <p><?= htmlspecialchars($descricao) ?></p>
            <?php if (!$existe): ?>
                <span class="estado">Em desenvolvimento · <?= htmlspecialchars($dono) ?></span>
            <?php else: ?>
                <span class="estado">Disponível</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
