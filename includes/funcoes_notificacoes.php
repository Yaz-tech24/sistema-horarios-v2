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
         WHERE a.horario_id = ? AND a.tipo_bloco = 'Aula'");
    $stmt->execute([$horarioId]);
    $docentes = $stmt->fetchAll();

    $ins = $pdo->prepare(
        "INSERT INTO notificacoes (utilizador_id, mensagem) VALUES (?,?)");
    $internas = 0;
    $emails = 0;
    $tentarEmail = $assuntoEmail !== null && smtpConfigurado();
    // notificacoes.mensagem é VARCHAR(255) — o texto leva nomes de
    // disciplinas/cursos e não pode fazer falhar a publicação por ser longo.
    $mensagemCurta = mb_substr($mensagem, 0, 255);
    foreach ($docentes as $u) {
        $ins->execute([$u['utilizador_id'], $mensagemCurta]);
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

/* Contas de acesso (utilizadores.id) dos docentes — regente e assistente —
   de uma aula, ou de todas as aulas de um horário. Docentes sem conta não
   aparecem (não têm onde receber a notificação). */
function utilizadoresDaAula(PDO $pdo, int $aulaId): array {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT d.utilizador_id FROM aulas a
         JOIN docentes d ON d.id = a.docente_regente_id OR d.id = a.docente_assistente_id
         WHERE a.id = ? AND a.tipo_bloco = 'Aula' AND d.utilizador_id IS NOT NULL");
    $stmt->execute([$aulaId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'utilizador_id'));
}

function utilizadoresDoHorario(PDO $pdo, int $horarioId): array {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT d.utilizador_id FROM aulas a
         JOIN docentes d ON d.id = a.docente_regente_id OR d.id = a.docente_assistente_id
         WHERE a.horario_id = ? AND a.tipo_bloco = 'Aula' AND d.utilizador_id IS NOT NULL");
    $stmt->execute([$horarioId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'utilizador_id'));
}

/* Uma notificação interna para cada conta indicada (repetidas contam uma
   vez). Devolve quantas foram gravadas. */
function notificarUtilizadores(PDO $pdo, array $utilizadorIds, string $mensagem, ?int $aulaId = null): int {
    $ids = array_values(array_unique(array_map('intval', $utilizadorIds)));
    if (!$ids) { return 0; }
    $ins = $pdo->prepare("INSERT INTO notificacoes (utilizador_id, aula_id, mensagem) VALUES (?,?,?)");
    $mensagemCurta = mb_substr($mensagem, 0, 255); // tamanho da coluna
    foreach ($ids as $id) { $ins->execute([$id, $aulaId, $mensagemCurta]); }
    return count($ids);
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
    $mensagemCurta = mb_substr($mensagem, 0, 255); // tamanho da coluna
    foreach ($stmt->fetchAll() as $u) { $ins->execute([$u['utilizador_id'], $aulaIdGravado, $mensagemCurta]); }
}
