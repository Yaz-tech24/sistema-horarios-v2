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
    $pdo->prepare(
        "INSERT INTO historico_versoes (horario_id, utilizador_id, descricao) VALUES (?,?,?)")
        ->execute([$horarioId, $_SESSION['utilizador_id'], $descricao]);
}

const DIAS_SEMANA = ['Segunda','Terca','Quarta','Quinta','Sexta'];
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

/* Geração automática do horário de uma turma a partir das disciplinas do
   curso+ano. Nunca reimplementa deteção de conflitos: cada slot candidato
   passa sempre por verificarConflitos() (definida em funcoes_conflitos.php
   — tem de estar incluída antes de esta função ser CHAMADA, não definida).
   Não escreve por cima de aulas já existentes; $limparPrimeiro é uma ação
   à parte, explícita, para recomeçar do zero.
   $salaForcada: quando indicada, todas as aulas geradas usam sempre essa
   sala (ex.: a sala fixa da turma) em vez da escolha automática por
   capacidade — continua sujeita ao RN02 (conflito de sala) como qualquer
   outra, por isso um slot com essa sala já ocupada simplesmente é
   ignorado, tal como acontecia antes. */
function gerarHorarioAutomatico(PDO $pdo, array $horario, array $turma, array $curso, bool $limparPrimeiro = false, ?int $salaForcada = null): array {
    if ($limparPrimeiro) {
        $pdo->prepare(
            "DELETE FROM conflitos WHERE aula_id_1 IN (SELECT id FROM aulas WHERE horario_id=?)
                                       OR aula_id_2 IN (SELECT id FROM aulas WHERE horario_id=?)")
            ->execute([$horario['id'], $horario['id']]);
        // Aulas de um horário já Publicado podem ter notificações antigas
        // ligadas por aula_id ("Foi-te atribuída uma nova aula..."). Sem
        // isto, o DELETE seguinte falhava por violação de chave
        // estrangeira (notificacoes.aula_id → aulas.id) sempre que havia
        // pelo menos uma notificação por resolver.
        $pdo->prepare(
            "UPDATE notificacoes SET aula_id = NULL
             WHERE aula_id IN (SELECT id FROM aulas WHERE horario_id=?)")
            ->execute([$horario['id']]);
        $pdo->prepare("DELETE FROM aulas WHERE horario_id=?")->execute([$horario['id']]);
        registarHistorico($pdo, (int)$horario['id'], "Horário limpo antes de gerar automaticamente");
    }

    garantirPastoral($pdo, $horario, $turma);

    $blocos = blocosDoTurno($turma['turno']);

    // Slots livres = todos os (dia, bloco) do turno da turma, exceto os
    // já ocupados (incluindo a Pastoral, já garantida acima).
    $stmt = $pdo->prepare("SELECT dia_semana, hora_inicio, hora_fim FROM aulas WHERE horario_id=?");
    $stmt->execute([$horario['id']]);
    $ocupados = [];
    foreach ($stmt->fetchAll() as $o) {
        $ocupados[$o['dia_semana'] . '|' . substr($o['hora_inicio'], 0, 5) . '|' . substr($o['hora_fim'], 0, 5)] = true;
    }
    $slotsLivres = [];
    foreach (DIAS_SEMANA as $d) {
        foreach ($blocos as [$hi, $hf]) {
            if (!isset($ocupados["$d|$hi|$hf"])) { $slotsLivres[] = [$d, $hi, $hf]; }
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM disciplinas WHERE curso_id=? AND ano_curricular=? ORDER BY nome");
    $stmt->execute([$curso['id'], $turma['ano_curricular']]);
    $disciplinas = $stmt->fetchAll();
    $salas = $pdo->query("SELECT * FROM salas ORDER BY capacidade")->fetchAll();

    $insAula = $pdo->prepare(
        "INSERT INTO aulas (horario_id, disciplina_id, docente_id, docente_regente_id, docente_assistente_id,
            sala_id, tipo_bloco, dia_semana, hora_inicio, hora_fim)
         VALUES (?,?,?,?,?,?,'Aula',?,?,?)");

    $criadas = 0;
    $naoAlocadas = [];
    $avisos = [];

    foreach ($disciplinas as $disc) {
        if (empty($disc['docente_regente_id'])) {
            $naoAlocadas[] = $disc['nome'] . ' (sem docente regente definido)';
            continue;
        }

        $sessoesNecessarias = max(1, min(2, (int)$disc['carga_horaria']));
        $stmt = $pdo->prepare(
            "SELECT dia_semana FROM aulas WHERE horario_id=? AND disciplina_id=? AND tipo_bloco='Aula'");
        $stmt->execute([$horario['id'], $disc['id']]);
        $diasUsados = array_column($stmt->fetchAll(), 'dia_semana');
        $sessoesRestantes = $sessoesNecessarias - count($diasUsados);

        for ($i = 0; $i < $sessoesRestantes; $i++) {
            $alocado = false;

            foreach ($slotsLivres as $idx => [$d, $hi, $hf]) {
                if (in_array($d, $diasUsados, true)) { continue; } // RN09: nunca 2x no mesmo dia

                if ($salaForcada) {
                    $salaId = $salaForcada;
                } elseif (!empty($disc['sala_id'])) {
                    // Disciplina Prática/Laboratorial com laboratório próprio
                    // (admin/disciplinas.php) — usa-o em vez da escolha por
                    // capacidade, é a sala onde a disciplina costuma decorrer.
                    $salaId = $disc['sala_id'];
                } else {
                    $salaId = null;
                    foreach ($salas as $s) {
                        if ($s['capacidade'] >= (int)$turma['num_alunos']) { $salaId = $s['id']; break; }
                    }
                    if ($salaId === null && $salas) { $salaId = $salas[array_key_last($salas)]['id']; }
                }

                $candidato = [
                    'horario_id' => (int)$horario['id'], 'turma_id' => (int)$turma['id'],
                    'tipo_bloco' => 'Aula', 'disciplina_id' => (int)$disc['id'],
                    'docente_id' => (int)$disc['docente_regente_id'],
                    'docente_regente_id' => (int)$disc['docente_regente_id'],
                    'docente_assistente_id' => $disc['docente_assistente_id'] ? (int)$disc['docente_assistente_id'] : null,
                    'sala_id' => $salaId, 'subgrupo' => null,
                    'dia_semana' => $d, 'hora_inicio' => $hi, 'hora_fim' => $hf,
                ];

                $problemas = verificarConflitos($pdo, $candidato);
                if (array_filter($problemas, fn($p) => $p['bloqueante'])) { continue; }

                $insAula->execute([
                    $horario['id'], $disc['id'], $candidato['docente_id'], $candidato['docente_regente_id'],
                    $candidato['docente_assistente_id'], $salaId, $d, $hi, $hf,
                ]);

                // Capacidade/Disponibilidade não bloqueiam a geração automática
                // (ao contrário do editor manual, aqui não há ninguém para
                // confirmar "gravar mesmo assim") — por isso ficam pelo menos
                // visíveis no resumo, em vez de silenciosamente ignoradas.
                foreach (array_filter($problemas, fn($p) => !$p['bloqueante']) as $p) {
                    $avisos[] = "{$disc['nome']} ($d {$hi}–{$hf}): {$p['mensagem']}";
                }

                unset($slotsLivres[$idx]);
                $diasUsados[] = $d;
                $criadas++;
                $alocado = true;
                break;
            }

            if (!$alocado) {
                // Quando a disciplina tem laboratório fixo (RN16), a causa mais
                // provável é o laboratório estar ocupado por outro curso nesses
                // horários — dizê-lo poupa a investigação ao coordenador.
                $motivo = '';
                if (!empty($disc['sala_id'])) {
                    $stmtSala = $pdo->prepare("SELECT nome FROM salas WHERE id = ?");
                    $stmtSala->execute([$disc['sala_id']]);
                    if ($nomeSala = $stmtSala->fetchColumn()) {
                        $motivo = " (o laboratório \"$nomeSala\" está ocupado nos blocos livres desta turma)";
                    }
                }
                $naoAlocadas[] = $disc['nome'] . $motivo;
                break;
            }
        }
    }

    // O que sobrar dentro do turno preenche-se com Estudo Autónomo.
    $insEstudo = $pdo->prepare(
        "INSERT INTO aulas (horario_id, tipo_bloco, dia_semana, hora_inicio, hora_fim)
         VALUES (?, 'Estudo_Autonomo', ?, ?, ?)");
    $preenchidasEstudo = 0;
    foreach ($slotsLivres as [$d, $hi, $hf]) {
        $insEstudo->execute([$horario['id'], $d, $hi, $hf]);
        $preenchidasEstudo++;
    }

    iniciarRevisaoSeRascunho($pdo, $horario);
    registarHistorico($pdo, (int)$horario['id'],
        "Geração automática: $criadas aula(s) criada(s), $preenchidasEstudo bloco(s) de Estudo Autónomo"
        . ($naoAlocadas ? ', ' . count($naoAlocadas) . ' disciplina(s) por agendar' : ''));

    return ['criadas' => $criadas, 'estudo' => $preenchidasEstudo, 'nao_alocadas' => $naoAlocadas, 'avisos' => $avisos];
}
