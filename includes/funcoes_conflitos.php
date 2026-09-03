<?php
/* Motor de deteção de conflitos (RN01-RN09).
   Verifica uma aula candidata contra TODAS as aulas do sistema,
   em qualquer curso — docentes e salas são recursos partilhados. */

function verificarConflitos(PDO $pdo, array $aula, ?int $ignorarAulaId = null): array {
    $problemas = [];

    // RN08 — bloco fixo da Pastoral Universitária (Quarta, 2º bloco do
    // turno, só em regime Laboral). Verifica-se ANTES de tudo — mesmo
    // antes da RN06 — porque é precisamente para blocos não-letivos
    // (Estudo Autónomo, outra Atividade) que alguém tentaria usar este
    // horário. Só a própria Pastoral (Atividade_Nao_Letiva neste slot
    // exato) tem permissão de lá estar.
    if (!empty($aula['turma_id']) && ($aula['dia_semana'] ?? '') === 'Quarta') {
        $stmt = $pdo->prepare("SELECT regime, turno FROM turmas WHERE id = ?");
        $stmt->execute([$aula['turma_id']]);
        $turmaInfo = $stmt->fetch();
        if ($turmaInfo && $turmaInfo['regime'] === 'Laboral') {
            $bp = blocoPastoral($turmaInfo['turno']);
            $ehEsseSlot = $bp && $aula['hora_inicio'] === $bp[0] && $aula['hora_fim'] === $bp[1];
            if ($ehEsseSlot && ($aula['tipo_bloco'] ?? 'Aula') !== 'Atividade_Nao_Letiva') {
                $stmt2 = $pdo->prepare(
                    "SELECT id FROM aulas WHERE horario_id=? AND dia_semana='Quarta'
                     AND hora_inicio=? AND hora_fim=? AND tipo_bloco='Atividade_Nao_Letiva'");
                $stmt2->execute([$aula['horario_id'] ?? 0, $bp[0], $bp[1]]);
                $pastoralExistente = $stmt2->fetch();
                $problemas[] = ['tipo' => 'Pastoral', 'bloqueante' => true,
                    'outra_aula_id' => $pastoralExistente ? (int)$pastoralExistente['id'] : null,
                    'mensagem' => "Quarta-feira {$bp[0]}–{$bp[1]} é reservada para a Pastoral Universitária "
                                . "(obrigatória em regime Laboral) e não pode ser substituída."];
                return $problemas; // já bloqueado, não há mais nada a validar
            }
        }
    }

    // RN06 — Estudo Autónomo / Atividade Não Letiva não geram conflito
    if (($aula['tipo_bloco'] ?? 'Aula') !== 'Aula') {
        return $problemas;
    }

    // RN09 — uma disciplina não pode passar da sua carga_horaria (máx. 2x
    // por semana) na mesma turma, nem repetir-se no mesmo dia, nem ficar
    // em dias consecutivos (tem de haver pelo menos um dia de intervalo
    // entre as duas sessões — ex.: Segunda + Quarta é válido, Segunda +
    // Terça não é). Nenhum destes três casos foi pedido explicitamente
    // nas regras de negócio originais, mas fazem pouco sentido pedagógico
    // de outra forma, por isso tratam-se também como bloqueantes.
    if (!empty($aula['disciplina_id']) && !empty($aula['horario_id'])) {
        $sql = "SELECT id, dia_semana FROM aulas
                WHERE horario_id = ? AND disciplina_id = ? AND tipo_bloco = 'Aula'";
        $params = [$aula['horario_id'], $aula['disciplina_id']];
        if ($ignorarAulaId) { $sql .= " AND id != ?"; $params[] = $ignorarAulaId; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $existentes = $stmt->fetchAll();

        $stmt2 = $pdo->prepare("SELECT nome, carga_horaria FROM disciplinas WHERE id = ?");
        $stmt2->execute([$aula['disciplina_id']]);
        $disc = $stmt2->fetch();
        $limite = $disc ? max(1, min(2, (int)$disc['carga_horaria'])) : 2;
        $nomeDisc = $disc['nome'] ?? 'Esta disciplina';

        $mesmoDia = null;
        foreach ($existentes as $e) { if ($e['dia_semana'] === $aula['dia_semana']) { $mesmoDia = $e; break; } }

        $diaAdjacente = null;
        if (!$mesmoDia) {
            $idxCandidato = array_search($aula['dia_semana'], DIAS_SEMANA, true);
            foreach ($existentes as $e) {
                $idxExistente = array_search($e['dia_semana'], DIAS_SEMANA, true);
                if ($idxCandidato !== false && $idxExistente !== false && abs($idxCandidato - $idxExistente) === 1) {
                    $diaAdjacente = $e;
                    break;
                }
            }
        }

        if ($mesmoDia) {
            $problemas[] = ['tipo' => 'Carga', 'bloqueante' => true, 'outra_aula_id' => (int)$mesmoDia['id'],
                'mensagem' => "\"$nomeDisc\" já está marcada este dia ({$aula['dia_semana']}) — escolhe outro dia para a próxima sessão."];
        } elseif ($diaAdjacente) {
            $problemas[] = ['tipo' => 'Carga', 'bloqueante' => true, 'outra_aula_id' => (int)$diaAdjacente['id'],
                'mensagem' => "\"$nomeDisc\" já está marcada em {$diaAdjacente['dia_semana']} — tem de haver pelo menos um dia de intervalo entre as duas sessões (não pode ser em dias consecutivos)."];
        } elseif (count($existentes) >= $limite) {
            $problemas[] = ['tipo' => 'Carga', 'bloqueante' => true, 'outra_aula_id' => (int)$existentes[0]['id'],
                'mensagem' => "\"$nomeDisc\" já está marcada $limite vez(es) esta semana nesta turma (limite atingido)."];
        }
    }

    $sql = "SELECT au.*, d.nome AS disciplina_nome, c.sigla AS curso_sigla,
                   t.ano_curricular
            FROM aulas au
            JOIN horarios h ON au.horario_id = h.id
            JOIN turmas t ON h.turma_id = t.id
            JOIN cursos c ON t.curso_id = c.id
            LEFT JOIN disciplinas d ON au.disciplina_id = d.id
            WHERE au.dia_semana = ?
              AND au.hora_inicio < ?
              AND au.hora_fim > ?
              AND au.tipo_bloco = 'Aula'
              AND h.estado != 'Arquivado'";
    $params = [$aula['dia_semana'], $aula['hora_fim'], $aula['hora_inicio']];
    if ($ignorarAulaId) { $sql .= " AND au.id != ?"; $params[] = $ignorarAulaId; }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // RN01 — Conflito de Docente. Considera regente E assistente: uma
    // dupla marcação de qualquer um dos dois, em qualquer papel do outro
    // lado, é conflito bloqueante da mesma forma.
    $candRegente    = $aula['docente_regente_id'] ?? $aula['docente_id'] ?? null;
    $candAssistente = $aula['docente_assistente_id'] ?? null;

    foreach ($stmt->fetchAll() as $outra) {
        $onde = $outra['curso_sigla'] . ' ' . $outra['ano_curricular'] . 'º — '
              . ($outra['disciplina_nome'] ?? 'aula');

        $outraRegente    = $outra['docente_regente_id'] ?: $outra['docente_id'];
        $outraAssistente = $outra['docente_assistente_id'] ?? null;

        $papel = null;
        if ($candRegente && ($candRegente == $outraRegente || $candRegente == $outraAssistente)) {
            $papel = 'regente';
        } elseif ($candAssistente && ($candAssistente == $outraRegente || $candAssistente == $outraAssistente)) {
            $papel = 'assistente';
        }
        if ($papel) {
            $problemas[] = ['tipo' => 'Docente', 'bloqueante' => true, 'outra_aula_id' => (int)$outra['id'],
                'mensagem' => "O docente ($papel) já tem outra aula nesse horário ($onde)."];
        }

        // RN02 — Conflito de Sala (qualquer curso)
        if (!empty($aula['sala_id']) && $outra['sala_id'] == $aula['sala_id']) {
            $problemas[] = ['tipo' => 'Sala', 'bloqueante' => true, 'outra_aula_id' => (int)$outra['id'],
                'mensagem' => "A sala já está ocupada nesse horário ($onde)."];
        }
        // RN03 — Conflito de Turma (mesmo horário e mesmo subgrupo — RN07)
        $mesmoSub = ($outra['subgrupo'] ?? null) === ($aula['subgrupo'] ?? null);
        if ($outra['horario_id'] == $aula['horario_id'] && $mesmoSub) {
            $problemas[] = ['tipo' => 'Turma', 'bloqueante' => true, 'outra_aula_id' => (int)$outra['id'],
                'mensagem' => "Esta turma já tem \"" . ($outra['disciplina_nome'] ?? 'uma aula')
                            . "\" marcada nesse horário."];
        }
    }

    // RN04 — Capacidade de sala (aviso)
    if (!empty($aula['sala_id']) && !empty($aula['turma_id'])) {
        $stmt = $pdo->prepare(
            "SELECT s.capacidade, t.num_alunos FROM salas s, turmas t
             WHERE s.id = ? AND t.id = ?");
        $stmt->execute([$aula['sala_id'], $aula['turma_id']]);
        $cap = $stmt->fetch();
        if ($cap && $cap['num_alunos'] > 0 && $cap['num_alunos'] > $cap['capacidade']) {
            $problemas[] = ['tipo' => 'Capacidade', 'bloqueante' => false, 'outra_aula_id' => null,
                'mensagem' => "A sala tem {$cap['capacidade']} lugares, mas a turma tem {$cap['num_alunos']} alunos."];
        }
    }

    // RN05 — Disponibilidade do docente (aviso). Só avisa se NINGUÉM dos
    // envolvidos está disponível: havendo regente e assistente, basta um
    // dos dois estar livre para a aula decorrer — não faz sentido avisar
    // só porque um marcou indisponibilidade se o outro cobre a aula.
    $docentesEnvolvidos = array_unique(array_filter([$candRegente, $candAssistente]));
    if ($docentesEnvolvidos) {
        $marcas = implode(',', array_fill(0, count($docentesEnvolvidos), '?'));
        $stmt = $pdo->prepare(
            "SELECT DISTINCT docente_id FROM disponibilidades
             WHERE docente_id IN ($marcas) AND dia_semana = ? AND disponivel = 0
               AND hora_inicio < ? AND hora_fim > ?");
        $stmt->execute([...array_values($docentesEnvolvidos), $aula['dia_semana'],
                        $aula['hora_fim'], $aula['hora_inicio']]);
        $indisponiveis = array_column($stmt->fetchAll(), 'docente_id');
        $todosIndisponiveis = !array_diff($docentesEnvolvidos, $indisponiveis);

        if ($todosIndisponiveis) {
            $mensagem = count($docentesEnvolvidos) > 1
                ? "O regente e o assistente indicaram que normalmente não estão disponíveis nesse horário."
                : "O docente indicou que normalmente não está disponível nesse horário.";
            $problemas[] = ['tipo' => 'Disponibilidade', 'bloqueante' => false, 'outra_aula_id' => null,
                'mensagem' => $mensagem];
        }
    }

    return $problemas;
}

/* Revalida TODAS as aulas de um horário e regista os conflitos
   bloqueantes na tabela `conflitos`. Devolve o nº de conflitos pendentes.

   Ao contrário de uma limpeza total a cada chamada, isto faz um diff:
   um conflito que já não é detetado passa a 'Resolvido' (fica no
   histórico, não é apagado); um que já estava pendente mantém-se tal
   como está (não perde o detetado_em original); só os realmente novos
   são inseridos. Isto é o que torna `conflitos.estado = 'Resolvido'`
   (previsto no schema) num valor que alguma vez é gravado. */
function revalidarHorario(PDO $pdo, int $horarioId): int {
    $stmt = $pdo->prepare(
        "SELECT co.id, co.aula_id_1, co.aula_id_2, co.tipo FROM conflitos co
         JOIN aulas a ON co.aula_id_1 = a.id
         WHERE a.horario_id = ? AND co.estado = 'Pendente'");
    $stmt->execute([$horarioId]);
    $existentes = [];
    foreach ($stmt->fetchAll() as $c) {
        $existentes[$c['aula_id_1'] . '|' . $c['aula_id_2'] . '|' . $c['tipo']] = (int)$c['id'];
    }

    $stmt = $pdo->prepare(
        "SELECT a.*, h.turma_id FROM aulas a
         JOIN horarios h ON a.horario_id = h.id
         WHERE a.horario_id = ?");
    $stmt->execute([$horarioId]);

    $encontrados = [];
    $ins = $pdo->prepare("INSERT INTO conflitos (aula_id_1, aula_id_2, tipo) VALUES (?,?,?)");
    foreach ($stmt->fetchAll() as $aula) {
        foreach (verificarConflitos($pdo, $aula, (int)$aula['id']) as $p) {
            if ($p['bloqueante'] && $p['outra_aula_id']) {
                $chave = $aula['id'] . '|' . $p['outra_aula_id'] . '|' . $p['tipo'];
                $encontrados[$chave] = true;
                if (!isset($existentes[$chave])) {
                    $ins->execute([(int)$aula['id'], $p['outra_aula_id'], $p['tipo']]);
                }
            }
        }
    }

    $resolvidos = array_diff_key($existentes, $encontrados);
    if ($resolvidos) {
        $upd = $pdo->prepare("UPDATE conflitos SET estado='Resolvido' WHERE id=?");
        foreach ($resolvidos as $id) { $upd->execute([$id]); }
    }

    return count($encontrados);
}

function conflitosPendentes(PDO $pdo, int $horarioId): array {
    $stmt = $pdo->prepare(
        "SELECT co.*, d1.nome AS disc1, d2.nome AS disc2,
                a1.dia_semana, a1.hora_inicio, a1.hora_fim
         FROM conflitos co
         JOIN aulas a1 ON co.aula_id_1 = a1.id
         JOIN aulas a2 ON co.aula_id_2 = a2.id
         LEFT JOIN disciplinas d1 ON a1.disciplina_id = d1.id
         LEFT JOIN disciplinas d2 ON a2.disciplina_id = d2.id
         WHERE a1.horario_id = ? AND co.estado = 'Pendente'
         ORDER BY FIELD(a1.dia_semana,'Segunda','Terca','Quarta','Quinta','Sexta'),
                  a1.hora_inicio");
    $stmt->execute([$horarioId]);
    return $stmt->fetchAll();
}

function contarConflitosPendentes(PDO $pdo, int $horarioId): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS n FROM conflitos co
         JOIN aulas a ON co.aula_id_1 = a.id
         WHERE a.horario_id = ? AND co.estado = 'Pendente'
           AND co.tipo IN ('Docente','Sala','Turma','Pastoral','Carga')");
    $stmt->execute([$horarioId]);
    return (int)$stmt->fetch()['n'];
}
