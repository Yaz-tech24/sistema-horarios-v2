<?php
/* Funções partilhadas do fluxo do Coordenador.
   O controlo de acesso por curso vive aqui:
   um coordenador SÓ pode trabalhar nos cursos que constam
   da tabela coordenador_curso para o seu utilizador. */

function cursosDoCoordenador(PDO $pdo): array {
    $stmt = $pdo->prepare(
        "SELECT c.* FROM cursos c
         JOIN coordenador_curso cc ON cc.curso_id = c.id
         WHERE cc.utilizador_id = ?
         ORDER BY c.sigla");
    $stmt->execute([$_SESSION['utilizador_id']]);
    return $stmt->fetchAll();
}

function exigirCursoDoCoordenador(PDO $pdo): array {
    $cursoId = (int)($_SESSION['curso_id_atual'] ?? 0);
    if ($cursoId > 0) {
        $stmt = $pdo->prepare(
            "SELECT c.* FROM cursos c
             JOIN coordenador_curso cc ON cc.curso_id = c.id
             WHERE cc.utilizador_id = ? AND c.id = ?");
        $stmt->execute([$_SESSION['utilizador_id'], $cursoId]);
        $curso = $stmt->fetch();
        if ($curso) {
            // Guardado para o header poder mostrar "a trabalhar em: <sigla>"
            // sem precisar de repetir esta query em todas as páginas.
            $_SESSION['curso_sigla_atual'] = $curso['sigla'];
            return $curso;
        }
    }
    // Sem curso escolhido, ou curso que não lhe pertence → volta à escolha
    unset($_SESSION['curso_id_atual'], $_SESSION['turma_id_atual'], $_SESSION['horario_id_atual'], $_SESSION['curso_sigla_atual']);
    header('Location: ' . BASE_URL . '/coordenador/escolher_curso.php');
    exit;
}

function turmasDoCurso(PDO $pdo, int $cursoId): array {
    $stmt = $pdo->prepare(
        "SELECT * FROM turmas WHERE curso_id = ?
         ORDER BY ano_curricular, regime, nome_turma");
    $stmt->execute([$cursoId]);
    return $stmt->fetchAll();
}

/* Devolve (criando se necessário) o horário ativo de uma turma:
   o mais recente que não esteja Arquivado. */
function obterHorarioDaTurma(PDO $pdo, int $turmaId): array {
    $stmt = $pdo->prepare(
        "SELECT * FROM horarios WHERE turma_id = ? AND estado != 'Arquivado'
         ORDER BY id DESC LIMIT 1");
    $stmt->execute([$turmaId]);
    $h = $stmt->fetch();
    if ($h) { return $h; }
    $pdo->prepare("INSERT INTO horarios (turma_id, estado) VALUES (?, 'Rascunho')")
        ->execute([$turmaId]);
    $id = (int)$pdo->lastInsertId();
    return ['id' => $id, 'turma_id' => $turmaId, 'estado' => 'Rascunho', 'data_publicacao' => null];
}

/* Um horário Rascunho passa a Em_Revisao assim que recebe a primeira aula —
   é isso que torna o fluxo Rascunho → Em Revisão → Publicado → Arquivado
   visível e real, em vez de saltar diretamente para Publicado. */
function iniciarRevisaoSeRascunho(PDO $pdo, array &$horario): void {
    if ($horario['estado'] === 'Rascunho') {
        $pdo->prepare("UPDATE horarios SET estado='Em_Revisao' WHERE id=?")->execute([$horario['id']]);
        $horario['estado'] = 'Em_Revisao';
    }
}

function registarHistorico(PDO $pdo, int $horarioId, string $descricao): void {
    // Cortado ao tamanho da coluna (VARCHAR 255): a descrição leva nomes de
    // disciplinas, e um nome comprido não pode fazer falhar a ação em si.
    $pdo->prepare(
        "INSERT INTO historico_versoes (horario_id, utilizador_id, descricao) VALUES (?,?,?)")
        ->execute([$horarioId, $_SESSION['utilizador_id'], mb_substr($descricao, 0, 255)]);
}

/* O horário em sessão (horario_id_atual), confirmado contra o curso que o
   coordenador tem aberto. O id em sessão é sempre posto pelo Editor depois
   de validar a turma, mas Publicar/Conflitos/Histórico agem sobre ele sem
   passar pelo Editor — confirmar aqui garante que nenhuma delas publica,
   arquiva ou mostra um horário de outro curso, aconteça o que acontecer à
   sessão pelo caminho (ex.: o curso ser retirado ao coordenador a meio). */
function horarioDoCursoEmSessao(PDO $pdo, array $curso): ?array {
    $horarioId = (int)($_SESSION['horario_id_atual'] ?? 0);
    if ($horarioId <= 0) { return null; }
    $stmt = $pdo->prepare(
        "SELECT h.* FROM horarios h
         JOIN turmas t ON t.id = h.turma_id
         WHERE h.id = ? AND t.curso_id = ?");
    $stmt->execute([$horarioId, $curso['id']]);
    $horario = $stmt->fetch();
    if (!$horario) {
        unset($_SESSION['horario_id_atual']);
        return null;
    }
    return $horario;
}

/* Apaga blocos de um horário e solta tudo o que lhes aponta (conflitos são
   apagados; notificações e pedidos ficam, só sem ligação ao bloco). */
function removerBlocos(PDO $pdo, int $horarioId, array $aulaIds): void {
    $ids = array_values(array_unique(array_map('intval', $aulaIds)));
    if (!$ids) { return; }
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM conflitos WHERE aula_id_1 IN ($marcas) OR aula_id_2 IN ($marcas)")->execute([...$ids, ...$ids]);
    $pdo->prepare("UPDATE notificacoes SET aula_id = NULL WHERE aula_id IN ($marcas)")->execute($ids);
    $pdo->prepare("UPDATE pedidos SET aula_id = NULL WHERE aula_id IN ($marcas)")->execute($ids);
    $pdo->prepare("DELETE FROM aulas WHERE horario_id = ? AND id IN ($marcas)")->execute([$horarioId, ...$ids]);
}

const DIAS_SEMANA = ['Segunda','Terca','Quarta','Quinta','Sexta'];

/* Nomes para MOSTRAR (com acentos). A base de dados guarda os valores sem
   acentos (ENUMs 'Terca', 'Manha', 'Pos-Laboral', 'Em_Revisao'…) e é isso
   que continua a ir nos formulários e nas consultas — estas funções só
   servem para o texto que as pessoas leem. */
function rotuloDia(?string $d): string { return ['Terca' => 'Terça'][$d] ?? (string)$d; }
function rotuloTurno(?string $t): string { return ['Manha' => 'Manhã'][$t] ?? (string)$t; }
function rotuloRegime(?string $r): string { return ['Pos-Laboral' => 'Pós-Laboral'][$r] ?? (string)$r; }
function rotuloEstado(?string $e): string { return ['Em_Revisao' => 'Em revisão'][$e] ?? (string)$e; }
function rotuloTipoBloco(?string $t): string {
    return ['Estudo_Autonomo' => 'Estudo Autónomo', 'Atividade_Nao_Letiva' => 'Atividade Não Letiva'][$t] ?? (string)$t;
}
const BLOCOS_HORARIOS = [
    ['07:00','08:40'], ['08:45','10:25'], ['10:30','12:10'],
    ['12:30','14:10'], ['14:15','15:55'], ['16:05','17:45'],
    ['17:45','19:25'], ['19:30','21:10'], ['21:15','22:45'],
];

/* O turno de uma turma deixa de ser escolha livre — deriva sempre de
   (ano_curricular, regime). Pós-Laboral é sempre Noite; em Laboral segue
   a paridade do ano (ímpar=Manhã, par=Tarde), o que cobre naturalmente
   1º/3º→Manhã e 2º/4º→Tarde. Para anos além do 4º (se algum curso vier a
   ter), a mesma regra de paridade é a decisão por omissão — não há
   indicação em contrário, e mantém o padrão previsível já usado nos
   anos 1-4. O valor devolvido aqui é SEMPRE o que é gravado; nunca
   confiar num "turno" vindo do formulário. */
function calcularTurno(int $ano, string $regime): string {
    if ($regime === 'Pos-Laboral') { return 'Noite'; }
    return ($ano % 2 === 1) ? 'Manha' : 'Tarde';
}

/* Só os 3 blocos de BLOCOS_HORARIOS que pertencem ao turno indicado —
   é isso que faz a grelha de uma turma da manhã mostrar 3 linhas, não 9.
   Não se aplica à disponibilidade do docente (essa continua com os 9). */
function blocosDoTurno(string $turno): array {
    return match ($turno) {
        'Manha' => array_slice(BLOCOS_HORARIOS, 0, 3),
        'Tarde' => array_slice(BLOCOS_HORARIOS, 3, 3),
        'Noite' => array_slice(BLOCOS_HORARIOS, 6, 3),
        default => BLOCOS_HORARIOS,
    };
}

/* RN08 — bloco fixo da Pastoral Universitária: Quarta-feira, 2º bloco do
   turno da turma. Só existe para regime Laboral (Manhã/Tarde); em
   Pós-Laboral (sempre Noite) não há Pastoral. */
function blocoPastoral(string $turno): ?array {
    if ($turno !== 'Manha' && $turno !== 'Tarde') { return null; }
    $blocos = blocosDoTurno($turno);
    return $blocos[1] ?? null; // 2º bloco do turno
}

/* Verdadeiro se (dia, hora_inicio, hora_fim) corresponde exatamente ao
   bloco fixo da Pastoral desta turma. Aceita horas em formato "HH:MM". */
function ehBlocoPastoral(array $turma, string $diaSemana, string $horaInicio, string $horaFim): bool {
    if (($turma['regime'] ?? '') !== 'Laboral' || $diaSemana !== 'Quarta') { return false; }
    $bp = blocoPastoral($turma['turno'] ?? '');
    return $bp && $bp[0] === $horaInicio && $bp[1] === $horaFim;
}

/* Garante que o bloco de Pastoral Universitária (RN08) existe no horário
   de uma turma Laboral — cria-o como Atividade_Nao_Letiva se ainda não lá
   estiver. Idempotente: chamar em cada carregamento do editor não duplica.
   Não faz nada para turmas Pós-Laboral (blocoPastoral() devolve null). */
function garantirPastoral(PDO $pdo, array $horario, array $turma): void {
    $bp = blocoPastoral($turma['turno'] ?? '');
    if (!$bp) { return; }
    $stmt = $pdo->prepare(
        "SELECT id FROM aulas WHERE horario_id=? AND dia_semana='Quarta'
         AND hora_inicio=? AND hora_fim=? AND tipo_bloco='Atividade_Nao_Letiva'");
    $stmt->execute([$horario['id'], $bp[0], $bp[1]]);
    if ($stmt->fetch()) { return; }
    $pdo->prepare(
        "INSERT INTO aulas (horario_id, tipo_bloco, dia_semana, hora_inicio, hora_fim)
         VALUES (?, 'Atividade_Nao_Letiva', 'Quarta', ?, ?)")
        ->execute([$horario['id'], $bp[0], $bp[1]]);
}

/* Salas a experimentar, por ordem de preferência, para uma aula da geração
   automática ([null] se não houver salas nenhumas):
   - sala fixa escolhida no formulário de geração → só essa;
   - laboratório da disciplina (RN16) → só esse (é onde a disciplina tem
     de decorrer; se estiver sempre ocupado, fica por agendar e o resumo
     diz porquê);
   - senão: a sala habitual da turma primeiro (é só sugestão — se estiver
     ocupada, segue para as outras), depois as salas onde a turma cabe, das
     mais pequenas para as maiores, preferindo salas normais a auditórios e
     a laboratórios (estes ficam livres para as disciplinas práticas). Se
     nenhuma sala tiver lugares que cheguem, todas, das maiores para as
     mais pequenas (a aula fica com aviso de capacidade, RN04). */
function salasCandidatas(array $salas, array $turma, array $disc, ?int $salaForcada): array {
    if ($salaForcada) { return [$salaForcada]; }
    if (!empty($disc['sala_id'])) { return [(int)$disc['sala_id']]; }
    if (!$salas) { return [null]; }

    $alunos = (int)$turma['num_alunos'];
    $ordemTipo = ['Normal' => 0, 'Auditorio' => 1, 'Laboratorio' => 2];
    $cabem = array_values(array_filter($salas, fn($s) => (int)$s['capacidade'] >= $alunos));
    if ($cabem) {
        usort($cabem, fn($a, $b) =>
            [$ordemTipo[$a['tipo']] ?? 3, (int)$a['capacidade'], (int)$a['id']]
            <=> [$ordemTipo[$b['tipo']] ?? 3, (int)$b['capacidade'], (int)$b['id']]);
        $ids = array_map(fn($s) => (int)$s['id'], $cabem);
    } else {
        $todas = $salas;
        usort($todas, fn($a, $b) => [(int)$b['capacidade'], (int)$a['id']] <=> [(int)$a['capacidade'], (int)$b['id']]);
        $ids = array_map(fn($s) => (int)$s['id'], $todas);
    }

    if (!empty($turma['sala_padrao_id'])) {
        $habitual = (int)$turma['sala_padrao_id'];
        $ids = array_merge([$habitual], array_values(array_diff($ids, [$habitual])));
    }
    return $ids;
}

/* A geração automática (gerarHorarioAutomatico, gerarHorariosDoCurso) vive
   em gerador_horarios.php — usa as funções acima e verificarConflitos()
   (funcoes_conflitos.php), que tem de estar incluída antes de a CHAMAR. */
require_once __DIR__ . '/gerador_horarios.php';
