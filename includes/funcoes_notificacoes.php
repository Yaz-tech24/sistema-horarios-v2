<?php
/* Notificações automáticas aos docentes.
   Regente e assistente são ambos notificados — os dois "têm" a aula. */

function notificarDocentesDoHorario(PDO $pdo, int $horarioId, string $mensagem): int {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT u.id AS utilizador_id
         FROM aulas a
         JOIN docentes u2 ON u2.id = a.docente_regente_id OR u2.id = a.docente_assistente_id
         JOIN utilizadores u ON u.id = u2.utilizador_id
         WHERE a.horario_id = ?");
    $stmt->execute([$horarioId]);
    $ins = $pdo->prepare(
        "INSERT INTO notificacoes (utilizador_id, mensagem) VALUES (?,?)");
    $n = 0;
    foreach ($stmt->fetchAll() as $u) { $ins->execute([$u['utilizador_id'], $mensagem]); $n++; }
    return $n;
}

/* $manterLigacaoAula: false quando a aula está prestes a ser apagada (ex.:
   coordenador/editor_horario.php ao remover um bloco) — a consulta ainda
   precisa da aula para saber quem notificar, mas a notificação grava-se
   sem aula_id, senão fica a apontar para uma aula que já não existe e o
   DELETE seguinte falha por violação de chave estrangeira
   (notificacoes.aula_id → aulas.id). */
function notificarDocenteDaAula(PDO $pdo, int $aulaId, string $mensagem, bool $manterLigacaoAula = true): void {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT u.id AS utilizador_id
         FROM aulas a
         JOIN docentes u2 ON u2.id = a.docente_regente_id OR u2.id = a.docente_assistente_id
         JOIN utilizadores u ON u.id = u2.utilizador_id
         WHERE a.id = ?");
    $stmt->execute([$aulaId]);
    $ins = $pdo->prepare(
        "INSERT INTO notificacoes (utilizador_id, aula_id, mensagem) VALUES (?,?,?)");
    $aulaIdGravado = $manterLigacaoAula ? $aulaId : null;
    foreach ($stmt->fetchAll() as $u) { $ins->execute([$u['utilizador_id'], $aulaIdGravado, $mensagem]); }
}
