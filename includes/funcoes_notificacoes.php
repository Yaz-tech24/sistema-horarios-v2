<?php
require_once __DIR__ . '/email.php';

/* Notificações automáticas aos docentes.
   Regente e assistente são ambos notificados — os dois "têm" a aula. */

/* Além da notificação interna (sempre gravada, para todos), tenta enviar
   um email a cada docente com conta e email válidos — best-effort: se o
   SMTP não estiver configurado (smtpConfigurado()) ou uma mensagem falhar,
   isso nunca impede a publicação nem afeta as restantes, só faz o
   contador de emails enviados ficar mais baixo (ver funcoes_conflitos.php
   para o mesmo princípio aplicado a conflitos). $assuntoEmail é opcional;
   sem ele (ex.: notificação de uma única aula editada) só a notificação
   interna é gravada, sem tentativa de email.
   Devolve ['internas' => nº de notificações internas, 'emails' => nº de
   emails enviados com sucesso — só relevante quando $assuntoEmail é dado]. */
function notificarDocentesDoHorario(PDO $pdo, int $horarioId, string $mensagem, ?string $assuntoEmail = null): array {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT u.id AS utilizador_id, u.nome, u.email
         FROM aulas a
         JOIN docentes u2 ON u2.id = a.docente_regente_id OR u2.id = a.docente_assistente_id
         JOIN utilizadores u ON u.id = u2.utilizador_id
         WHERE a.horario_id = ?");
    $stmt->execute([$horarioId]);
    $docentes = $stmt->fetchAll();

    $ins = $pdo->prepare(
        "INSERT INTO notificacoes (utilizador_id, mensagem) VALUES (?,?)");
    $internas = 0;
    $emails = 0;
    $tentarEmail = $assuntoEmail !== null && smtpConfigurado();
    foreach ($docentes as $u) {
        $ins->execute([$u['utilizador_id'], $mensagem]);
        $internas++;
        if ($tentarEmail && !empty($u['email'])) {
            try {
                if (enviarEmailSmtp($u['email'], $u['nome'], $assuntoEmail, $mensagem)) { $emails++; }
            } catch (Throwable $e) {
                error_log('Falha ao enviar email de notificação: ' . $e->getMessage());
            }
        }
    }
    return ['internas' => $internas, 'emails' => $emails];
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
