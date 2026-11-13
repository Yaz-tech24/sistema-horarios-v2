<?php
require_once __DIR__ . '/../includes/auth.php';
exigirPerfil(['Coordenador']);
require_once __DIR__ . '/../includes/funcoes_coordenador.php';
require_once __DIR__ . '/../includes/funcoes_conflitos.php';
require_once __DIR__ . '/../includes/funcoes_notificacoes.php';

$curso = exigirCursoDoCoordenador($pdo);

$erro = $ok = null; $avisos = []; $errosBloq = []; $resultadoGeracao = null;
// true quando o formulário de aula foi enviado e NÃO gravou (conflito, erro
// ou aviso por confirmar): o formulário volta a abrir por cima da grelha,
// com os valores que a pessoa escreveu e a mensagem lá dentro.
$falhouFormulario = false;
if ($f = lerFlash()) { ${$f['tipo']} = $f['texto']; }
if (isset($_SESSION['resultado_geracao'])) {
    $resultadoGeracao = $_SESSION['resultado_geracao'];
    unset($_SESSION['resultado_geracao']);
}
$resultadoCurso = $_SESSION['resultado_geracao_curso'] ?? null;
unset($_SESSION['resultado_geracao_curso']);

// Gerar todas as turmas do curso de uma vez (não precisa de turma escolhida).
// As turmas com horário já publicado ficam de fora — ver gerarHorariosDoCurso().
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_curso') {
    validarCSRF();
    @set_time_limit(120);
    $salaForcada = (int)postTexto('sala_id', 12) ?: null;
    if ($salaForcada) {
        $stmt = $pdo->prepare("SELECT 1 FROM salas WHERE id = ?");
        $stmt->execute([$salaForcada]);
        if (!$stmt->fetch()) { $salaForcada = null; }
    }
    $pdo->beginTransaction();
    try {
        $_SESSION['resultado_geracao_curso'] = gerarHorariosDoCurso($pdo, $curso, $salaForcada)['turmas'];
        $pdo->commit();
        definirFlash('ok', 'Geração automática do curso concluída.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        unset($_SESSION['resultado_geracao_curso']);
        error_log('Geração automática do curso falhou: ' . $e->getMessage());
        definirFlash('erro', 'A geração automática do curso falhou e nada foi alterado. Tenta novamente.');
    }
    header('Location: editor_horario.php'); exit;
}

// Escolha da turma do curso atual
if (isset($_GET['turma'])) {
    $tid = (int)$_GET['turma'];
    $stmt = $pdo->prepare("SELECT 1 FROM turmas WHERE id=? AND curso_id=?");
    $stmt->execute([$tid, $curso['id']]);
    if ($stmt->fetch()) {
        $_SESSION['turma_id_atual'] = $tid;
        unset($_SESSION['horario_id_atual']);
    }
    header('Location: editor_horario.php'); exit;
}

$turmas = turmasDoCurso($pdo, (int)$curso['id']);
$turmaId = (int)($_SESSION['turma_id_atual'] ?? 0);

// A turma em sessão tem de pertencer ao curso atual
$turmaAtual = null;
foreach ($turmas as $t) { if ($t['id'] == $turmaId) { $turmaAtual = $t; break; } }
if (!$turmaAtual) { $turmaId = 0; unset($_SESSION['turma_id_atual'], $_SESSION['horario_id_atual']); }

$horario = null; $aulas = []; $blocosGrelha = BLOCOS_HORARIOS; $edicao = null;
if ($turmaAtual) {
    $horario = obterHorarioDaTurma($pdo, $turmaId);
    $_SESSION['horario_id_atual'] = (int)$horario['id'];
    $blocosGrelha = blocosDoTurno($turmaAtual['turno']); // regra 2 — só os 3 blocos do turno da turma

    // Conjunto de blocos válidos ("HH:MM|HH:MM") para este turno — nunca
    // confiar num horário arbitrário vindo do POST.
    $blocosValidos = [];
    foreach ($blocosGrelha as [$hi, $hf]) { $blocosValidos[$hi . '|' . $hf] = true; }

    // RN08 — garante que a Pastoral Universitária existe (turmas Laboral)
    garantirPastoral($pdo, $horario, $turmaAtual);

    // Carregar uma aula existente para edição (form pré-preenchido abaixo)
    if (isset($_GET['editar_aula'])) {
        $stmt = $pdo->prepare("SELECT * FROM aulas WHERE id=? AND horario_id=?");
        $stmt->execute([(int)$_GET['editar_aula'], $horario['id']]);
        $edicao = $stmt->fetch() ?: null;
        // Só a Pastoral (RN08) não se edita — antes ficava de fora qualquer
        // Atividade Não Letiva, e o link "editar" dessas abria o formulário
        // em modo "nova aula" sem dizer nada.
        if ($edicao && $edicao['tipo_bloco'] === 'Atividade_Nao_Letiva'
            && ehBlocoPastoral($turmaAtual, $edicao['dia_semana'], substr($edicao['hora_inicio'],0,5), substr($edicao['hora_fim'],0,5))) {
            $edicao = null;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validarCSRF();
        $acao = $_POST['acao'] ?? '';

        // Geração automática do horário a partir das disciplinas do curso+ano
        if (in_array($acao, ['gerar_auto', 'gerar_auto_limpar'], true)) {
            $limpar = $acao === 'gerar_auto_limpar';
            // Sala fixa opcional — só aceite se existir mesmo, senão volta
            // à escolha automática por capacidade (comportamento anterior).
            $salaForcada = (int)($_POST['sala_id'] ?? 0) ?: null;
            if ($salaForcada) {
                $stmt = $pdo->prepare("SELECT 1 FROM salas WHERE id = ?");
                $stmt->execute([$salaForcada]);
                if (!$stmt->fetch()) { $salaForcada = null; }
            }
            // Tudo ou nada: a geração apaga (se pedido) e cria dezenas de
            // linhas — um erro a meio deixava o horário meio apagado, meio
            // gerado. Em transação, ou fica completo ou fica como estava.
            $pdo->beginTransaction();
            try {
                // Num horário já Publicado, a geração muda o horário que os
                // docentes estão a usar — avisa quem tinha e quem passa a ter aulas.
                $publicado = $horario['estado'] === 'Publicado';
                $docentesAntes = $publicado ? utilizadoresDoHorario($pdo, (int)$horario['id']) : [];
                $resultado = gerarHorarioAutomatico($pdo, $horario, $turmaAtual, $curso, $limpar, $salaForcada);
                if ($publicado && ($limpar || $resultado['criadas'] > 0)) {
                    notificarUtilizadores($pdo,
                        array_merge($docentesAntes, utilizadoresDoHorario($pdo, (int)$horario['id'])),
                        "O horário de " . $curso['sigla'] . " " . (int)$turmaAtual['ano_curricular'] . "º ano (turma "
                        . $turmaAtual['nome_turma'] . ") foi alterado pelo coordenador. Confirma o teu horário.");
                }
                $_SESSION['resultado_geracao'] = $resultado;
                $pdo->commit();
                definirFlash('ok', 'Geração automática concluída.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                unset($_SESSION['resultado_geracao']);
                error_log('Geração automática falhou: ' . $e->getMessage());
                definirFlash('erro', 'A geração automática falhou e nada foi alterado. Tenta novamente.');
            }
            header('Location: editor_horario.php'); exit;
        }

        // Apagar aula
        if ($acao === 'apagar') {
            $aid = (int)postTexto('aula_id', 12);
            $stmt = $pdo->prepare("SELECT a.*, d.nome AS disc FROM aulas a
                                    LEFT JOIN disciplinas d ON a.disciplina_id=d.id
                                    WHERE a.id=? AND a.horario_id=?");
            $stmt->execute([$aid, $horario['id']]);
            if ($a = $stmt->fetch()) {
                // RN08 — o bloco fixo da Pastoral não pode ser removido pelo Coordenador
                if ($a['tipo_bloco'] === 'Atividade_Nao_Letiva'
                    && ehBlocoPastoral($turmaAtual, $a['dia_semana'], substr($a['hora_inicio'],0,5), substr($a['hora_fim'],0,5))) {
                    $sucesso = false;
                    $mensagem = 'Este bloco é a Pastoral Universitária (obrigatória em regime Laboral) e não pode ser removido.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        // Só os docentes de um horário já Publicado sabem da
                        // aula — num rascunho, avisar que "foi removida" uma
                        // aula que nunca viram só gerava confusão.
                        if ($horario['estado'] === 'Publicado') {
                            notificarDocenteDaAula($pdo, $aid,
                                "A aula de " . ($a['disc'] ?? 'bloco') . " (" . rotuloDia($a['dia_semana']) . " "
                                . substr($a['hora_inicio'],0,5) . ") foi removida do horário.", false);
                        }
                        $pdo->prepare("DELETE FROM conflitos WHERE aula_id_1=? OR aula_id_2=?")->execute([$aid,$aid]);
                        // Os pedidos dos docentes sobrevivem à aula: perde-se a
                        // ligação mas o texto de referência mantém-nos legíveis.
                        $pdo->prepare("UPDATE pedidos SET aula_id=NULL WHERE aula_id=?")->execute([$aid]);
                        $pdo->prepare("UPDATE notificacoes SET aula_id=NULL WHERE aula_id=?")->execute([$aid]);
                        $pdo->prepare("DELETE FROM aulas WHERE id=? AND horario_id=?")->execute([$aid, $horario['id']]);
                        registarHistorico($pdo, (int)$horario['id'],
                            "Removida aula de " . ($a['disc'] ?? rotuloTipoBloco($a['tipo_bloco'])) . " (" . rotuloDia($a['dia_semana']) . " " . substr($a['hora_inicio'],0,5) . ")");
                        $pdo->commit();
                        $sucesso = true;
                        $mensagem = 'Aula removida.';
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        error_log('Erro ao remover aula: ' . $e->getMessage());
                        $sucesso = false;
                        $mensagem = 'Não foi possível remover este bloco. Tenta novamente.';
                    }
                }
            } else {
                $sucesso = false;
                $mensagem = 'Este bloco já não existe (talvez já tenha sido removido).';
            }
            if (ehPedidoAjax()) { responderAjax($sucesso, $mensagem); }
            definirFlash($sucesso ? 'ok' : 'erro', $mensagem);
            header('Location: editor_horario.php#grelha'); exit;
        }

        // Criar ou atualizar aula
        if (isset($_POST['dia_semana'])) {
            $falhouFormulario = true; // os caminhos de sucesso saem com redirect
            $aulaId = (int)postTexto('aula_id', 12);
            $bloco  = postTexto('bloco', 11);
            $tipoBloco = in_array($_POST['tipo_bloco'] ?? '', ['Aula','Estudo_Autonomo','Atividade_Nao_Letiva'], true)
                ? $_POST['tipo_bloco'] : 'Aula';
            $ehAula = $tipoBloco === 'Aula';
            // Disciplina, docentes, sala e subgrupo só existem numa "Aula".
            // Os campos ficam escondidos no formulário para os outros tipos,
            // mas continuam a ser enviados com o último valor escolhido —
            // sem os limpar aqui, um bloco de Estudo Autónomo ficava gravado
            // com um docente e uma sala "fantasma" que mais ninguém via.
            $regenteId    = $ehAula ? ((int)postTexto('docente_regente_id', 12) ?: null) : null;
            $assistenteId = $ehAula ? ((int)postTexto('docente_assistente_id', 12) ?: null) : null;
            $partesBloco  = explode('|', $bloco);
            $aula = [
                'horario_id'    => (int)$horario['id'],
                'turma_id'      => $turmaId,
                'tipo_bloco'    => $tipoBloco,
                'disciplina_id' => $ehAula ? ((int)postTexto('disciplina_id', 12) ?: null) : null,
                'docente_id'    => $regenteId, // espelha docente_regente_id — ver nota em funcoes_coordenador.php
                'docente_regente_id'    => $regenteId,
                'docente_assistente_id' => $assistenteId,
                'sala_id'       => $ehAula ? ((int)postTexto('sala_id', 12) ?: null) : null,
                // RN07 — gravado já normalizado ("p1 " → "P1"), para a grelha,
                // as exportações e o motor de conflitos verem o mesmo subgrupo.
                'subgrupo'      => $ehAula ? normalizarSubgrupo(postTexto('subgrupo', 10)) : null,
                'dia_semana'    => postTexto('dia_semana', 10),
                'hora_inicio'   => $partesBloco[0] ?? '',
                'hora_fim'      => $partesBloco[1] ?? '',
            ];

            // O que vem do formulário são ids — confirma-se que cada um
            // aponta para algo que este coordenador pode mesmo usar aqui.
            $existe = function (string $sql, array $params) use ($pdo): bool {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return (bool)$stmt->fetch();
            };
            $aulaAtual = null;
            if ($aulaId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM aulas WHERE id=? AND horario_id=?");
                $stmt->execute([$aulaId, $horario['id']]);
                $aulaAtual = $stmt->fetch() ?: null;
            }

            // RN16 — uma disciplina Prática/Laboratorial com laboratório
            // próprio (admin/disciplinas.php) tem esse laboratório como
            // SUGESTÃO no formulário (JS pré-preenche o campo Sala quando a
            // disciplina é escolhida), mas o coordenador pode trocar para
            // qualquer outra sala manualmente — a sala escolhida no POST é
            // respeitada tal como está. Quem continua a impedir duas aulas
            // na mesma sala ao mesmo tempo, seja lá qual for a sala, é a
            // RN02 (verificarConflitos, abaixo), que olha para TODOS os
            // cursos e horários não arquivados.

            if (!in_array($aula['dia_semana'], DIAS_SEMANA, true) || !isset($blocosValidos[$bloco])) {
                $erro = "Escolhe o dia e o bloco horário (tem de ser um dos blocos do turno desta turma).";
            } elseif ($aulaId > 0 && !$aulaAtual) {
                $erro = "Esse bloco já não existe neste horário (talvez tenha sido removido). Atualiza a página.";
            } elseif ($aulaAtual && $aulaAtual['tipo_bloco'] === 'Atividade_Nao_Letiva'
                && ehBlocoPastoral($turmaAtual, $aulaAtual['dia_semana'], substr($aulaAtual['hora_inicio'],0,5), substr($aulaAtual['hora_fim'],0,5))) {
                // RN08 — o link "editar" nem aparece na Pastoral, mas o id
                // dela podia vir num pedido feito à mão.
                $erro = "O bloco da Pastoral Universitária é obrigatório e não pode ser alterado.";
            } elseif ($ehAula && !$aula['disciplina_id']) {
                $erro = "Uma aula precisa de uma disciplina.";
            } elseif ($ehAula && !$existe("SELECT 1 FROM disciplinas WHERE id=? AND curso_id=? AND ano_curricular=?",
                    [$aula['disciplina_id'], $curso['id'], $turmaAtual['ano_curricular']])) {
                // Sem isto dava para marcar, no horário deste curso, uma
                // disciplina de um curso que o coordenador não gere.
                $erro = "Essa disciplina não é do " . (int)$turmaAtual['ano_curricular'] . "º ano deste curso.";
            } elseif ($regenteId && !$existe("SELECT 1 FROM docentes WHERE id=?", [$regenteId])) {
                $erro = "O docente regente escolhido já não existe. Atualiza a página.";
            } elseif ($assistenteId && !$existe("SELECT 1 FROM docentes WHERE id=?", [$assistenteId])) {
                $erro = "O docente assistente escolhido já não existe. Atualiza a página.";
            } elseif ($assistenteId && $assistenteId === $regenteId) {
                $erro = "O assistente tem de ser um docente diferente do regente.";
            } elseif ($aula['sala_id'] && !$existe("SELECT 1 FROM salas WHERE id=?", [$aula['sala_id']])) {
                $erro = "A sala escolhida já não existe. Atualiza a página.";
            } else {
                $problemas = verificarConflitos($pdo, $aula, $aulaId ?: null);
                $errosBloq = array_filter($problemas, fn($p) => $p['bloqueante']);
                $avisosNovos = array_filter($problemas, fn($p) => !$p['bloqueante']);

                // Os outros blocos desta turma neste mesmo dia/bloco (o que
                // está a ser editado não conta). O motor de conflitos só
                // compara aulas com aulas (RN06) — sem isto, uma aula marcada
                // por cima de um Estudo Autónomo ficava com os dois blocos no
                // mesmo quadrado da grelha, da exportação e da folha impressa.
                //  - aula da turma inteira sobre Estudo Autónomo → a aula
                //    substitui-o (o Estudo Autónomo é só o preenchimento dos
                //    blocos livres);
                //  - aula de um subgrupo sobre Estudo Autónomo → ficam os dois
                //    (os outros alunos continuam em estudo autónomo);
                //  - qualquer bloco sobre uma Atividade Não Letiva, e Estudo/
                //    Atividade sobre outro bloco não letivo ou sobre uma aula
                //    da turma inteira → recusado, com o motivo.
                $erroOcupado = null;
                $estudosASubstituir = [];
                if (!$errosBloq) {
                    $stmt = $pdo->prepare(
                        "SELECT a.id, a.tipo_bloco, a.subgrupo, a.dia_semana, a.hora_inicio, a.hora_fim, d.nome AS disc
                         FROM aulas a LEFT JOIN disciplinas d ON d.id = a.disciplina_id
                         WHERE a.horario_id=? AND a.dia_semana=? AND a.hora_inicio=? AND a.hora_fim=? AND a.id != ?");
                    $stmt->execute([$horario['id'], $aula['dia_semana'], $aula['hora_inicio'], $aula['hora_fim'], $aulaId]);
                    foreach ($stmt->fetchAll() as $o) {
                        if ($o['tipo_bloco'] === 'Estudo_Autonomo') {
                            if ($ehAula && $aula['subgrupo'] === null) { $estudosASubstituir[] = (int)$o['id']; }
                            elseif (!$ehAula) { $erroOcupado = "Este bloco já tem Estudo Autónomo."; }
                        } elseif ($o['tipo_bloco'] === 'Atividade_Nao_Letiva') {
                            $erroOcupado = ehBlocoPastoral($turmaAtual, $o['dia_semana'], substr($o['hora_inicio'],0,5), substr($o['hora_fim'],0,5))
                                ? "Este bloco é o da Pastoral Universitária — escolhe outro."
                                : "Este bloco já tem uma Atividade Não Letiva — remove-a primeiro ou escolhe outro bloco.";
                        } elseif (!$ehAula && normalizarSubgrupo($o['subgrupo']) === null) {
                            $erroOcupado = "A turma já tem aula de \"" . ($o['disc'] ?? '—')
                                . "\" neste bloco — remove-a primeiro ou escolhe outro bloco.";
                        }
                    }
                }

                if ($errosBloq) {
                    // Não grava — mostra os conflitos
                } elseif ($erroOcupado) {
                    $erro = $erroOcupado;
                } elseif ($avisosNovos && !isset($_POST['confirmar_aviso'])) {
                    $avisos = $avisosNovos; // pede confirmação
                } else {
                    $nomeDisciplina = function (?int $id) use ($pdo): string {
                        if (!$id) { return 'bloco'; }
                        $s = $pdo->prepare("SELECT nome FROM disciplinas WHERE id=?");
                        $s->execute([$id]);
                        return (string)($s->fetchColumn() ?: 'aula');
                    };
                    $publicado = $horario['estado'] === 'Publicado';
                    $quando = rotuloDia($aula['dia_semana']) . " às " . $aula['hora_inicio'];
                    $ondeQuando = rotuloDia($aula['dia_semana']) . " " . $aula['hora_inicio'];
                    $oQue = $aula['disciplina_id'] ? $nomeDisciplina($aula['disciplina_id']) : rotuloTipoBloco($aula['tipo_bloco']);

                    $pdo->beginTransaction();
                    try {
                        if ($aulaId > 0) {
                            // Quem tinha a aula ANTES da alteração (para o caso de
                            // o regente/assistente mudar, ou de deixar de ser aula).
                            $docentesAntes = $publicado ? utilizadoresDaAula($pdo, $aulaId) : [];
                            $pdo->prepare(
                                "UPDATE aulas SET disciplina_id=?, docente_id=?, docente_regente_id=?, docente_assistente_id=?,
                                    sala_id=?, tipo_bloco=?, subgrupo=?, dia_semana=?, hora_inicio=?, hora_fim=?
                                 WHERE id=? AND horario_id=?")
                                ->execute([
                                    $aula['disciplina_id'], $aula['docente_id'], $aula['docente_regente_id'],
                                    $aula['docente_assistente_id'], $aula['sala_id'], $aula['tipo_bloco'],
                                    $aula['subgrupo'], $aula['dia_semana'], $aula['hora_inicio'], $aula['hora_fim'],
                                    $aulaId, $horario['id'],
                                ]);
                            $aulaGravada = $aulaId;
                            // Os conflitos antigos referentes a esta aula ficam desatualizados
                            // assim que ela muda — a revalidação (logo abaixo, ao desenhar a
                            // grelha) deteta o estado novo e faz o diff (ver revalidarHorario).
                            registarHistorico($pdo, (int)$horario['id'], "Editado bloco: $oQue ($ondeQuando)");
                        } else {
                            $docentesAntes = [];
                            $pdo->prepare(
                                "INSERT INTO aulas (horario_id, disciplina_id, docente_id, docente_regente_id, docente_assistente_id,
                                    sala_id, tipo_bloco, subgrupo, dia_semana, hora_inicio, hora_fim)
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                                ->execute([
                                    $aula['horario_id'], $aula['disciplina_id'], $aula['docente_id'],
                                    $aula['docente_regente_id'], $aula['docente_assistente_id'],
                                    $aula['sala_id'], $aula['tipo_bloco'], $aula['subgrupo'],
                                    $aula['dia_semana'], $aula['hora_inicio'], $aula['hora_fim'],
                                ]);
                            $aulaGravada = (int)$pdo->lastInsertId();
                            registarHistorico($pdo, (int)$horario['id'], "Adicionado bloco: $oQue ($ondeQuando)");
                            if (!$publicado) { iniciarRevisaoSeRascunho($pdo, $horario); }
                        }

                        if ($estudosASubstituir) {
                            removerBlocos($pdo, (int)$horario['id'], $estudosASubstituir);
                            registarHistorico($pdo, (int)$horario['id'],
                                "Estudo Autónomo de $ondeQuando substituído pela aula");
                        }

                        // Num horário já Publicado, mexer numa aula muda o dia a dia
                        // de alguém: quem a deixou de ter, quem a passou a ter e quem
                        // a continua a ter (com outro dia, hora ou sala).
                        if ($publicado) {
                            $docentesDepois = utilizadoresDaAula($pdo, $aulaGravada);
                            $disc = $nomeDisciplina($aula['disciplina_id']);
                            if ($aulaAtual) {
                                $antes = $nomeDisciplina($aulaAtual['disciplina_id'] ? (int)$aulaAtual['disciplina_id'] : null)
                                    . " (" . rotuloDia($aulaAtual['dia_semana']) . " " . substr($aulaAtual['hora_inicio'], 0, 5) . ")";
                                notificarUtilizadores($pdo, array_diff($docentesAntes, $docentesDepois),
                                    "A aula de $antes deixou de estar atribuída a ti.");
                            }
                            notificarUtilizadores($pdo, array_diff($docentesDepois, $docentesAntes),
                                "Foi-te atribuída uma aula de $disc: $quando.", $aulaGravada);
                            notificarUtilizadores($pdo, array_intersect($docentesDepois, $docentesAntes),
                                "A tua aula de $disc foi alterada: agora é $quando. Confirma no teu horário.", $aulaGravada);
                        }
                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) { $pdo->rollBack(); }
                        throw $e; // erro inesperado: vai para o log (includes/seguranca.php)
                    }

                    definirFlash('ok', $aulaId > 0 ? 'Aula atualizada.' : 'Aula guardada sem conflitos.');
                    // Volta à grelha, já na aula gravada (o JS centra-a e
                    // fá-la piscar) — em vez de voltar ao topo da página.
                    header('Location: editor_horario.php#aula-' . $aulaGravada); exit;
                }
            }
        }
    }

    // Mantém a tabela `conflitos` atual antes de desenhar a grelha — sem
    // isto, o destaque "⚠ conflito pendente" só refletia o que tinha sido
    // revalidado da última vez que alguém visitou Conflitos ou Publicar,
    // podendo mostrar conflitos já corrigidos ou esconder novos.
    revalidarHorario($pdo, (int)$horario['id']);

    // Aulas atuais para a grelha
    $stmt = $pdo->prepare(
        "SELECT a.*, d.nome AS disciplina, doc.nome AS docente,
                dr.nome AS regente, da.nome AS assistente, s.nome AS sala
         FROM aulas a
         LEFT JOIN disciplinas d ON a.disciplina_id=d.id
         LEFT JOIN docentes doc ON a.docente_id=doc.id
         LEFT JOIN docentes dr ON a.docente_regente_id=dr.id
         LEFT JOIN docentes da ON a.docente_assistente_id=da.id
         LEFT JOIN salas s ON a.sala_id=s.id
         WHERE a.horario_id=?");
    $stmt->execute([$horario['id']]);
    foreach ($stmt->fetchAll() as $a) {
        $aulas[$a['dia_semana']][substr($a['hora_inicio'],0,5)][] = $a;
    }

    // Aulas com conflito bloqueante pendente, para destacar na grelha
    $stmt = $pdo->prepare(
        "SELECT aula_id_1 AS id FROM conflitos co JOIN aulas a1 ON co.aula_id_1=a1.id
         WHERE a1.horario_id=? AND co.estado='Pendente'
         UNION
         SELECT aula_id_2 AS id FROM conflitos co JOIN aulas a1 ON co.aula_id_1=a1.id
         WHERE a1.horario_id=? AND co.estado='Pendente'");
    $stmt->execute([$horario['id'], $horario['id']]);
    $aulasComConflito = array_column($stmt->fetchAll(), 'id');
}

// Dados para o formulário (disciplinas do curso, docentes, salas)
$disciplinas = [];
if ($turmaAtual) {
    $stmt = $pdo->prepare(
        "SELECT * FROM disciplinas WHERE curso_id=? AND ano_curricular=? ORDER BY nome");
    $stmt->execute([$curso['id'], $turmaAtual['ano_curricular']]);
    $disciplinas = $stmt->fetchAll();
}
$docentes = $pdo->query("SELECT * FROM docentes ORDER BY nome")->fetchAll();
$salas    = $pdo->query("SELECT * FROM salas ORDER BY nome")->fetchAll();

// Mapa disciplina → regente/assistente/laboratório, para pré-preencher o
// formulário em JS (o laboratório é só sugestão — continua a dar para
// escolher outra sala manualmente).
$mapaDocentesDisciplina = [];
foreach ($disciplinas as $d) {
    $mapaDocentesDisciplina[$d['id']] = [
        'regente' => $d['docente_regente_id'] ?: 0,
        'assistente' => $d['docente_assistente_id'] ?: 0,
        'sala' => $d['sala_id'] ?: 0,
    ];
}

// Mapa disciplina → docentes autorizados a lecioná-la (admin/docentes.php).
// Usa-se para restringir os <select> de regente/assistente no JS; uma
// disciplina sem ninguém marcado não restringe nada (mostra todos), para
// não bloquear o fluxo de quem ainda não preencheu essa lista.
$mapaDisciplinaDocentes = [];
foreach ($pdo->query("SELECT disciplina_id, docente_id FROM docente_disciplina")->fetchAll() as $r) {
    $mapaDisciplinaDocentes[$r['disciplina_id']][] = (int)$r['docente_id'];
}

// Valores do formulário de aula: os que a pessoa enviou (se não gravou), os
// da aula em edição (?editar_aula=) ou os de uma aula nova.
$v = ['aula_id' => 0, 'tipo_bloco' => 'Aula', 'disciplina_id' => 0, 'docente_regente_id' => 0,
      'docente_assistente_id' => 0, 'sala_id' => 0, 'subgrupo' => '', 'dia_semana' => 'Segunda', 'bloco' => ''];
if ($falhouFormulario) {
    foreach (array_keys($v) as $k) { $v[$k] = postTexto($k, 40); }
} elseif ($edicao) {
    foreach (array_keys($v) as $k) { if (array_key_exists($k, $edicao)) { $v[$k] = (string)($edicao[$k] ?? ''); } }
    $v['aula_id'] = (string)$edicao['id']; // na base de dados chama-se "id"
    $v['bloco'] = substr($edicao['hora_inicio'], 0, 5) . '|' . substr($edicao['hora_fim'], 0, 5);
}
$emEdicaoForm = (int)$v['aula_id'] > 0;

// Dados de cada bloco editável da grelha, para o JS abrir o formulário já
// preenchido sem recarregar a página (e sem a levar para o topo).
$aulasEditaveis = [];
foreach ($aulas as $porHora) { foreach ($porHora as $lista) { foreach ($lista as $a) {
    if ($a['tipo_bloco'] === 'Atividade_Nao_Letiva'
        && ehBlocoPastoral($turmaAtual, $a['dia_semana'], substr($a['hora_inicio'],0,5), substr($a['hora_fim'],0,5))) { continue; }
    $aulasEditaveis[(int)$a['id']] = [
        'aula_id' => (int)$a['id'], 'tipo_bloco' => $a['tipo_bloco'],
        'disciplina_id' => (int)$a['disciplina_id'], 'docente_regente_id' => (int)$a['docente_regente_id'],
        'docente_assistente_id' => (int)$a['docente_assistente_id'], 'sala_id' => (int)$a['sala_id'],
        'subgrupo' => (string)$a['subgrupo'], 'dia_semana' => $a['dia_semana'],
        'bloco' => substr($a['hora_inicio'],0,5) . '|' . substr($a['hora_fim'],0,5),
    ];
} } }

$pageTitle = 'Editor de horário';
// (sem htmlspecialchars aqui — o header já escapa $pageDescription; escapar
// duas vezes mostrava "&amp;" no ecrã em nomes de curso com "&")
$pageDescription = $curso['nome'] . ($horario ? ' · Estado: ' . rotuloEstado($horario['estado']) . ' · Turno: ' . rotuloTurno($turmaAtual['turno']) : '');

// Estado do horário de cada turma, para a lista de turmas (ver de relance
// quais estão publicadas, em revisão ou ainda por começar).
$estadoPorTurma = [];
if ($turmas) {
    $ids = array_map(fn($t) => (int)$t['id'], $turmas);
    $stmt = $pdo->prepare("SELECT turma_id, estado FROM horarios WHERE estado != 'Arquivado' AND turma_id IN ("
        . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY id");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $r) { $estadoPorTurma[(int)$r['turma_id']] = $r['estado']; }
}
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card" style="margin-bottom:20px">
    <div class="card-body">
        <form method="get" class="form-row" style="grid-template-columns:1fr">
            <div class="campo"><label>Turma</label>
                <select class="form-control form-select" name="turma" data-auto-submit>
                    <option value="">— escolher turma —</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($t['id']==$turmaId)?'selected':'' ?>>
                        <?= (int)$t['ano_curricular'] ?>º Ano · <?= rotuloRegime($t['regime']) ?> · <?= rotuloTurno($t['turno']) ?> · Turma <?= htmlspecialchars($t['nome_turma']) ?>
                        — <?= isset($estadoPorTurma[(int)$t['id']]) ? rotuloEstado($estadoPorTurma[(int)$t['id']]) : 'sem horário' ?>
                    </option>
                    <?php endforeach; ?>
                </select></div>
        </form>
        <?php if (!$turmas): ?>
            <div class="estado-vazio" style="margin-top:16px">
                <p><strong>Este curso ainda não tem turmas.</strong></p>
                <p>Pede ao Administrador para as criar em <strong>Admin → Turmas</strong> — é sobre uma turma que se constrói um horário.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php /* Os erros do formulário de aula aparecem DENTRO do formulário (que
   reabre por cima da grelha) — aqui só os restantes (geração, remoção…). */ ?>
<?php if ($erro && !$falhouFormulario): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if ($ok):   ?><div class="alert alert-success" data-mensagem-ok="<?= htmlspecialchars($ok) ?>"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<?php if ($resultadoGeracao): ?>
<section class="card resumo-geracao" style="margin-bottom:20px">
    <div class="card-header"><div><h2>Resultado da geração automática</h2></div></div>
    <div class="card-body">
        <div class="resumo-grelha">
            <div class="resumo-item resumo-item--ok">
                <span class="resumo-numero"><?= $resultadoGeracao['criadas'] ?></span>
                <span>aula(s) de disciplina criada(s)</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-numero"><?= $resultadoGeracao['estudo'] ?></span>
                <span>bloco(s) preenchidos com Estudo Autónomo</span>
            </div>
            <div class="resumo-item <?= $resultadoGeracao['nao_alocadas'] ? 'resumo-item--aviso' : '' ?>">
                <span class="resumo-numero"><?= count($resultadoGeracao['nao_alocadas']) ?></span>
                <span>disciplina(s) por completar</span>
            </div>
        </div>
        <?php if ($resultadoGeracao['nao_alocadas']): ?>
        <div class="alert alert-error" style="margin:16px 0 0">
            <p style="font-weight:600;margin:0 0 6px">Ficaram por completar — e porquê:</p>
            <?php foreach ($resultadoGeracao['nao_alocadas'] as $n): ?>
                <p style="margin:0 0 3px">• <?= htmlspecialchars($n) ?></p>
            <?php endforeach; ?>
            <p style="margin:8px 0 0">Corrige a causa (docente, sala, disciplina) e volta a carregar em <strong>Gerar automaticamente</strong> — as aulas já colocadas mantêm-se — ou marca estas sessões à mão no editor abaixo.</p>
        </div>
        <?php endif; ?>
        <?php if (!empty($resultadoGeracao['avisos'])): ?>
        <div class="alert" style="margin:16px 0 0;color:var(--warning);background:#fff7e7;border-color:var(--warning)">
            <p style="font-weight:600;margin:0 0 6px">Avisos (não impedem o horário, mas vale a pena rever):</p>
            <?php foreach ($resultadoGeracao['avisos'] as $a): ?>
                <p style="margin:0 0 3px">• <?= htmlspecialchars($a) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($resultadoCurso): ?>
<section class="card resumo-geracao" style="margin-bottom:20px">
    <div class="card-header"><div><h2>Resultado da geração do curso</h2><p>Todas as turmas de <?= htmlspecialchars($curso['sigla']) ?> geradas em conjunto (as turmas do mesmo ano partilham os docentes).</p></div></div>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr><th>Turma</th><th>Aulas criadas</th><th>Estudo Autónomo</th><th>Por completar</th></tr></thead>
            <tbody>
            <?php foreach ($resultadoCurso as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['turma']) ?></strong></td>
                <?php if (!empty($r['ignorada'])): ?>
                    <td colspan="3"><small style="color:var(--ink-soft)"><?= htmlspecialchars($r['ignorada']) ?></small></td>
                <?php else: ?>
                    <td><?= (int)$r['criadas'] ?></td>
                    <td><?= (int)$r['estudo'] ?></td>
                    <td>
                        <?php if (!$r['nao_alocadas']): ?><span class="badge badge-success"><span class="status-dot"></span>completo</span>
                        <?php else: foreach ($r['nao_alocadas'] as $n): ?>
                            <small style="display:block;color:var(--danger);margin-bottom:3px">• <?= htmlspecialchars($n) ?></small>
                        <?php endforeach; endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($turmas): ?>
<section class="card" style="margin-bottom:20px">
    <div class="card-header"><div><h2>Geração automática</h2><p>
        <?php if ($turmaAtual): ?>
            Coloca as disciplinas do <?= (int)$turmaAtual['ano_curricular'] ?>º ano nos blocos livres do turno desta turma: procura a arrumação
            que coloca mais sessões, com as aulas espalhadas pela semana, e completa o resto com Estudo Autónomo.
            As aulas marcadas à mão mantêm-se; o Estudo Autónomo é refeito.
        <?php else: ?>
            Escolhe uma turma acima para gerar só essa, ou gera de uma vez todas as turmas do curso.
        <?php endif; ?>
    </p></div></div>
    <div class="card-body">
        <form method="post" data-loading-label="A gerar…">
            <?= campoCSRF() ?>
            <div class="campo" style="max-width:340px;margin-bottom:14px">
                <label>Sala</label>
                <select class="form-control form-select" name="sala_id">
                    <option value="0">— automática, pela capacidade da turma —</option>
                    <?php foreach ($salas as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?> (<?= $s['capacidade'] ?> lugares)</option>
                    <?php endforeach; ?>
                </select>
                <p style="margin:6px 0 0;color:var(--ink-soft);font-size:.76rem">
                    Escolhe uma sala fixa para todas as aulas geradas, ou deixa em automática para o sistema escolher pela capacidade.
                </p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <?php if ($turmaAtual): ?>
                <button type="submit" name="acao" value="gerar_auto" class="btn btn-primary" style="width:auto;padding-inline:1.8rem"><?= icone('send', 17) ?> Gerar automaticamente</button>
                <button type="submit" name="acao" value="gerar_auto_limpar" class="btn btn-secondary" style="width:auto;padding-inline:1.8rem"
                    data-confirmar="Isto apaga TODAS as aulas atuais deste horário (incluindo as criadas manualmente) e gera tudo de novo. Continuar?">
                    Limpar tudo e gerar de novo</button>
                <?php endif; ?>
                <button type="submit" name="acao" value="gerar_curso" class="btn btn-secondary" style="width:auto;padding-inline:1.8rem"
                    data-confirmar="Gerar todas as turmas de <?= htmlspecialchars($curso['sigla']) ?> que ainda não têm horário publicado? As aulas marcadas à mão mantêm-se; o Estudo Autónomo é refeito. Pode demorar alguns segundos.">
                    <?= icone('layers', 17) ?> Gerar todas as turmas do curso</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($turmaAtual): ?>

<?php
    $sel = fn($atual, $valor) => (string)$atual === (string)$valor ? 'selected' : '';
    // O formulário abre logo por cima da grelha quando a página chega com uma
    // aula em edição (?editar_aula=) ou depois de um envio que não gravou.
    $abrirForm = $emEdicaoForm || $falhouFormulario;
?>
<div id="form-nova-aula-lugar" hidden></div>
<section class="card <?= $emEdicaoForm ? 'cartao--em-edicao' : '' ?>" style="margin-bottom:20px" id="form-nova-aula"
         data-abrir="<?= $abrirForm ? '1' : '0' ?>" role="region" aria-labelledby="form-aula-titulo">
    <div class="card-header"><div><h2 id="form-aula-titulo"><?= $emEdicaoForm ? 'A editar aula / bloco' : 'Nova aula / bloco' ?></h2>
        <p data-dica-form>Dica: clica num quadrado vazio da grelha (ou em "editar" num bloco) — o formulário abre por cima, sem sair do sítio.</p>
    </div>
    <button type="button" class="icon-button botao-fechar-form" data-fechar-formulario aria-label="Fechar formulário" hidden><?= icone('x', 18) ?></button>
    </div>
    <div class="card-body">
        <?php if ($falhouFormulario && ($erro || $errosBloq || $avisos)): ?>
        <div class="erros-formulario">
            <?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <?php foreach ($errosBloq as $p): ?>
                <div class="alert alert-error"><strong><?= htmlspecialchars($p['tipo']) ?>:</strong> <?= htmlspecialchars($p['mensagem']) ?></div>
            <?php endforeach; ?>
            <?php if ($avisos): ?>
            <div class="alert" style="color:var(--warning);background:#fff7e7;border-color:var(--warning)">
                <p style="font-weight:600;margin:0 0 6px">Atenção — confirma antes de gravar:</p>
                <?php foreach ($avisos as $p): ?>
                    <p style="margin:0 0 3px">• (<?= htmlspecialchars($p['tipo']) ?>) <?= htmlspecialchars($p['mensagem']) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <form method="post" class="form-row" data-loading-label="A gravar…">
            <?= campoCSRF() ?>
            <input type="hidden" name="aula_id" value="<?= (int)$v['aula_id'] ?>">
            <div class="campo"><label for="f-tipo-bloco">Tipo de bloco</label>
                <select class="form-control form-select" name="tipo_bloco" id="f-tipo-bloco">
                    <?php foreach (['Aula' => 'Aula', 'Estudo_Autonomo' => 'Estudo Autónomo', 'Atividade_Nao_Letiva' => 'Atividade Não Letiva'] as $valor => $rotulo): ?>
                    <option value="<?= $valor ?>" <?= $sel($v['tipo_bloco'] ?: 'Aula', $valor) ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo campo-so-aula"><label for="f-disciplina">Disciplina (<?= $turmaAtual['ano_curricular'] ?>º ano)</label>
                <select class="form-control form-select" name="disciplina_id" id="f-disciplina">
                    <option value="0">—</option>
                    <?php foreach ($disciplinas as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $sel($v['disciplina_id'], $d['id']) ?>><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo campo-so-aula"><label for="f-regente">Docente regente</label>
                <select class="form-control form-select" name="docente_regente_id" id="f-regente">
                    <option value="0">—</option>
                    <?php foreach ($docentes as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $sel($v['docente_regente_id'], $d['id']) ?>><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo campo-so-aula"><label for="f-assistente">Docente assistente (opcional)</label>
                <select class="form-control form-select" name="docente_assistente_id" id="f-assistente">
                    <option value="0">—</option>
                    <?php foreach ($docentes as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $sel($v['docente_assistente_id'], $d['id']) ?>><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo campo-so-aula"><label for="f-sala">Sala</label>
                <select class="form-control form-select" name="sala_id" id="f-sala">
                    <option value="0">—</option>
                    <?php foreach ($salas as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sel($v['sala_id'], $s['id']) ?>><?= htmlspecialchars($s['nome']) ?> (<?= $s['capacidade'] ?>)</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label for="f-dia">Dia</label>
                <select class="form-control form-select" name="dia_semana" id="f-dia" required>
                    <?php foreach (DIAS_SEMANA as $d): ?><option value="<?= $d ?>" <?= $sel($v['dia_semana'], $d) ?>><?= rotuloDia($d) ?></option><?php endforeach; ?>
                </select></div>
            <div class="campo"><label for="f-bloco">Bloco horário (turno da turma)</label>
                <select class="form-control form-select" name="bloco" id="f-bloco" required>
                    <?php foreach ($blocosGrelha as [$i,$f]): ?>
                    <option value="<?= $i ?>|<?= $f ?>" <?= $sel($v['bloco'], "$i|$f") ?>><?= $i ?> – <?= $f ?></option>
                    <?php endforeach; ?>
                </select>
                <small id="f-sala-nota" hidden style="color:var(--ink-soft);font-size:.75rem">Laboratório sugerido para esta disciplina — podes escolher outra sala, se precisares.</small></div>
            <div class="campo campo-so-aula"><label for="f-subgrupo">Subgrupo (opcional)</label>
                <input class="form-control" type="text" name="subgrupo" id="f-subgrupo" maxlength="10" placeholder="ex.: A ou B" value="<?= htmlspecialchars($v['subgrupo']) ?>"></div>
            <div class="campo" style="grid-column:1/-1;display:flex;gap:8px;flex-wrap:wrap">
                <button type="submit" class="btn btn-primary" style="width:auto;padding-inline:2rem" data-botao-guardar><?= $emEdicaoForm ? 'Atualizar aula' : 'Guardar aula' ?></button>
                <?php if ($avisos): ?>
                <button type="submit" name="confirmar_aviso" value="1" class="btn btn-secondary" style="width:auto;padding-inline:1.4rem">Gravar mesmo assim</button>
                <?php endif; ?>
                <a class="btn btn-secondary" style="width:auto;padding-inline:1.2rem" href="editor_horario.php#grelha"
                   data-fechar-formulario <?= $abrirForm ? '' : 'hidden' ?>>Cancelar</a>
            </div>
        </form>
    </div>
</section>

<div class="legenda-blocos" data-no-print style="margin-bottom:16px">
    <span class="legenda-item"><i class="marca marca--aula"></i> Aula</span>
    <span class="legenda-item"><i class="marca marca--estudo"></i> Estudo Autónomo</span>
    <span class="legenda-item"><i class="marca marca--atividade"></i> Atividade Não Letiva / Pastoral</span>
    <span class="legenda-item"><i class="marca marca--conflito"></i> Com conflito pendente</span>
</div>

<div class="tabela-grelha-scroll" id="grelha">
<table class="tabela-grelha">
    <thead><tr><th class="col-hora">Hora</th><?php foreach (DIAS_SEMANA as $d): ?><th><?= rotuloDia($d) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($blocosGrelha as [$hi, $hf]): ?>
    <tr>
        <td class="col-hora"><strong><?= $hi ?></strong><?= $hf ?></td>
        <?php foreach (DIAS_SEMANA as $d):
            $ocupado = !empty($aulas[$d][$hi]);
        ?>
        <td class="<?= $ocupado ? '' : 'slot-vazio' ?>" data-slot="<?= $d . '|' . $hi . '|' . $hf ?>"
            <?= $ocupado ? '' : 'data-dia="'.$d.'" data-bloco="'.$hi.'|'.$hf.'" tabindex="0" role="button" title="Adicionar aula: '.$d.' '.$hi.'–'.$hf.'"' ?>>
            <?php foreach (($aulas[$d][$hi] ?? []) as $a):
                $classeTipo = $a['tipo_bloco'] === 'Aula' ? 'bloco-aula'
                    : ($a['tipo_bloco'] === 'Estudo_Autonomo' ? 'bloco-estudo' : 'bloco-atividade');
                $temConflito = in_array($a['id'], $aulasComConflito ?? []);
                $ehPastoral = $a['tipo_bloco'] === 'Atividade_Nao_Letiva'
                    && ehBlocoPastoral($turmaAtual, $a['dia_semana'], substr($a['hora_inicio'],0,5), substr($a['hora_fim'],0,5));
                $emEdicao = $emEdicaoForm && (int)$v['aula_id'] === (int)$a['id'];
            ?>
                <div class="bloco-grelha <?= $classeTipo ?> <?= $temConflito ? 'bloco-conflito' : '' ?> <?= $emEdicao ? 'bloco-em-edicao' : '' ?>" id="aula-<?= (int)$a['id'] ?>">
                    <?php if ($a['tipo_bloco'] === 'Aula'): ?>
                        <strong><?= htmlspecialchars($a['disciplina'] ?? '—') ?></strong>
                        <?= $a['subgrupo'] ? '<em>('.htmlspecialchars($a['subgrupo']).')</em>' : '' ?>
                        <small>Regente: <?= htmlspecialchars($a['regente'] ?? $a['docente'] ?? '—') ?><?php if ($a['assistente']): ?> · Assist.: <?= htmlspecialchars($a['assistente']) ?><?php endif; ?></small>
                        <small><?= htmlspecialchars($a['sala'] ?? '—') ?></small>
                    <?php elseif ($ehPastoral): ?>
                        <em>Pastoral Universitária</em>
                    <?php else: ?>
                        <em><?= rotuloTipoBloco($a['tipo_bloco']) ?></em>
                    <?php endif; ?>
                    <?php if ($temConflito): ?><small class="aviso-conflito">⚠ conflito pendente</small><?php endif; ?>
                    <?php if ($ehPastoral): ?>
                        <small>obrigatório · não pode ser removido</small>
                    <?php else: ?>
                        <span class="actions">
                            <a class="link-acao" href="?editar_aula=<?= (int)$a['id'] ?>" data-editar-aula="<?= (int)$a['id'] ?>">editar</a>
                            <form method="post" style="display:inline" data-confirmar="Remover este bloco?" data-ajax-remover=".bloco-grelha">
                                <?= campoCSRF() ?>
                                <input type="hidden" name="acao" value="apagar">
                                <input type="hidden" name="aula_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="link-acao link-acao--perigo">remover</button>
                            </form>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<script nonce="<?= nonceCSP() ?>">
    <?php $jsonSeguro = JSON_HEX_TAG | JSON_HEX_AMP; // um "</script>" dentro de um valor nunca fecha este bloco ?>
    var mapaDocentesDisciplina = <?= json_encode($mapaDocentesDisciplina, $jsonSeguro) ?>;
    var mapaDisciplinaDocentes = <?= json_encode($mapaDisciplinaDocentes, $jsonSeguro) ?>;
    var aulasEditaveis = <?= json_encode((object)$aulasEditaveis, $jsonSeguro) ?>;
    (function () {
        var selTipo = document.getElementById('f-tipo-bloco');
        var selDisc = document.getElementById('f-disciplina');
        var selRegente = document.getElementById('f-regente');
        var selAssistente = document.getElementById('f-assistente');
        var selSala = document.getElementById('f-sala');
        var camposSoAula = document.querySelectorAll('.campo-so-aula');

        // Mostra os campos de disciplina/docentes/sala/subgrupo só quando
        // o bloco é mesmo uma "Aula" — Estudo Autónomo e Atividade Não
        // Letiva não precisam de nenhum deles.
        function alternarCampos() {
            var ehAula = !selTipo || selTipo.value === 'Aula';
            camposSoAula.forEach(function (c) { c.style.display = ehAula ? '' : 'none'; });
        }
        if (selTipo) { selTipo.addEventListener('change', alternarCampos); alternarCampos(); }

        // Restringe as opções de regente/assistente a quem está marcado em
        // Admin → Docentes como podendo lecionar a disciplina escolhida
        // (docente_disciplina). Sem marcações para essa disciplina, mostra
        // todos — não bloqueia quem ainda não preencheu essa lista.
        // manterSelecionado: ao abrir uma aula já gravada, o docente que lá
        // está continua visível e escolhido mesmo que não esteja na lista
        // (senão o formulário trocava-o por "—" sem ninguém dar por isso).
        function filtrarDocentes(select, permitidos, manterSelecionado) {
            if (!select) { return; }
            Array.prototype.forEach.call(select.options, function (opt) {
                if (opt.value === '0' || !permitidos || !permitidos.length) { opt.hidden = false; return; }
                opt.hidden = permitidos.indexOf(parseInt(opt.value, 10)) === -1 && !(manterSelecionado && opt.selected);
            });
            if (select.selectedOptions[0] && select.selectedOptions[0].hidden) { select.value = '0'; }
        }

        var notaSala = document.getElementById('f-sala-nota');
        // A sala do laboratório é só uma SUGESTÃO (RN16) — o campo nunca é
        // desativado, o coordenador pode sempre trocar para outra sala.
        // `forcarValor` só é true quando a disciplina muda por ação do
        // coordenador (não ao carregar a página), para não sobrepor uma
        // sala já guardada quando se está a editar uma aula existente.
        function aplicarLabDaDisciplina(salaId, forcarValor) {
            if (!selSala) { return; }
            var temLab = !!salaId;
            if (temLab && forcarValor) { selSala.value = salaId; }
            if (notaSala) { notaSala.hidden = !temLab; }
        }

        if (selDisc) {
            selDisc.addEventListener('change', function () {
                var info = mapaDocentesDisciplina[this.value];
                if (info) {
                    if (info.regente) { selRegente.value = info.regente; }
                    if (info.assistente) { selAssistente.value = info.assistente; }
                    // Laboratório da disciplina (admin/disciplinas.php) —
                    // só pré-preenche o campo Sala; continua editável.
                    if (selSala) { aplicarLabDaDisciplina(info.sala, true); }
                }
                var permitidos = mapaDisciplinaDocentes[this.value] || [];
                filtrarDocentes(selRegente, permitidos);
                filtrarDocentes(selAssistente, permitidos);
            });
        }

        // Acerta os filtros e a nota do laboratório depois de o formulário
        // ser preenchido (ao carregar a página ou ao abrir um bloco).
        function atualizarDependentes() {
            alternarCampos();
            if (!selDisc) { return; }
            filtrarDocentes(selRegente, mapaDisciplinaDocentes[selDisc.value] || [], true);
            filtrarDocentes(selAssistente, mapaDisciplinaDocentes[selDisc.value] || [], true);
            var info = mapaDocentesDisciplina[selDisc.value];
            aplicarLabDaDisciplina(info ? info.sala : 0, false);
        }
        atualizarDependentes();

        // ============================================================
        // O formulário abre POR CIMA da grelha, no sítio onde se clicou —
        // "editar" num bloco ou um quadrado vazio já não levam a página
        // para o topo. Ao gravar, a página volta à grelha, na aula gravada
        // (#aula-ID: centrada e a piscar). Sem JavaScript, os links
        // continuam a funcionar como antes (?editar_aula=…).
        // ============================================================
        var cartao = document.getElementById('form-nova-aula');
        var lugar = document.getElementById('form-nova-aula-lugar');
        var form = cartao ? cartao.querySelector('form') : null;
        if (!cartao || !form) { return; }
        var titulo = document.getElementById('form-aula-titulo');
        var btnGuardar = form.querySelector('[data-botao-guardar]');
        var fechos = cartao.querySelectorAll('[data-fechar-formulario]');
        var selDia = document.getElementById('f-dia');
        var selBloco = document.getElementById('f-bloco');
        var fundo = document.createElement('div');
        fundo.className = 'fundo-formulario';
        document.body.appendChild(fundo);
        var aberto = false, focoAntes = null;
        var CAMPOS = ['aula_id', 'tipo_bloco', 'disciplina_id', 'docente_regente_id', 'docente_assistente_id',
                      'sala_id', 'subgrupo', 'dia_semana', 'bloco'];

        function preencher(dados) {
            CAMPOS.forEach(function (k) {
                var campo = form.elements[k];
                if (!campo) { return; }
                var valor = dados[k];
                if (valor === null || valor === undefined) { valor = campo.tagName === 'SELECT' ? '0' : ''; }
                campo.value = String(valor);
                if (campo.tagName === 'SELECT' && campo.selectedIndex === -1) { campo.selectedIndex = 0; }
            });
            atualizarDependentes();
        }
        var dica = cartao.querySelector('[data-dica-form]');
        var dicaOriginal = dica ? dica.textContent : '';
        function modo(edicao) {
            if (dica) {
                dica.textContent = edicao
                    ? 'Muda o que precisares e carrega em "Atualizar aula" — voltas à grelha, já nesta aula. Esc ou Cancelar fecha sem gravar.'
                    : 'Escolhe a disciplina e carrega em "Guardar aula" — voltas à grelha, já na aula nova. Esc ou Cancelar fecha sem gravar.';
            }
            titulo.textContent = edicao ? 'A editar aula / bloco' : 'Nova aula / bloco';
            btnGuardar.textContent = edicao ? 'Atualizar aula' : 'Guardar aula';
            cartao.classList.toggle('cartao--em-edicao', edicao);
        }
        function limparMensagens() {
            var erros = cartao.querySelector('.erros-formulario');
            if (erros) { erros.remove(); }
            var mesmoAssim = form.querySelector('[name="confirmar_aviso"]');
            if (mesmoAssim) { mesmoAssim.remove(); }
        }
        function marcarBloco(id) {
            document.querySelectorAll('.bloco-grelha.bloco-em-edicao').forEach(function (b) { b.classList.remove('bloco-em-edicao'); });
            var b = id ? document.getElementById('aula-' + id) : null;
            if (b) { b.classList.add('bloco-em-edicao'); }
        }
        function abrir() {
            if (!aberto) {
                focoAntes = document.activeElement;
                // O lugar vazio fica com a altura do formulário: a página por
                // baixo não "salta" quando ele passa a flutuar.
                lugar.style.height = cartao.getBoundingClientRect().height + 'px'; // altura exata (com decimais)
                lugar.style.marginBottom = getComputedStyle(cartao).marginBottom; // a margem que o cartão perde a flutuar
                lugar.hidden = false;
                cartao.classList.add('cartao-flutuante');
                fundo.classList.add('aberto');
                fechos.forEach(function (f) { f.hidden = false; });
                aberto = true;
            }
            var primeiro = selTipo && selTipo.value === 'Aula' && selDisc ? selDisc : selTipo;
            if (primeiro) { primeiro.focus({ preventScroll: true }); }
        }
        function fechar() {
            cartao.classList.remove('cartao-flutuante');
            fundo.classList.remove('aberto');
            lugar.hidden = true;
            fechos.forEach(function (f) { f.hidden = true; });
            aberto = false;
            limparMensagens();
            preencher({ aula_id: 0, tipo_bloco: 'Aula', dia_semana: selDia.value, bloco: selBloco.value });
            modo(false);
            if (dica) { dica.textContent = dicaOriginal; }
            marcarBloco(null);
            if (location.search.indexOf('editar_aula') !== -1) { history.replaceState(null, '', location.pathname); }
            if (focoAntes && typeof focoAntes.focus === 'function') { focoAntes.focus({ preventScroll: true }); }
        }

        // Quadrado vazio da grelha (ver assets/js/script.js) → aula nova nesse dia/bloco.
        window.abrirFormularioAula = function (dia, bloco) {
            limparMensagens();
            preencher({ aula_id: 0, tipo_bloco: 'Aula', dia_semana: dia, bloco: bloco });
            modo(false);
            marcarBloco(null);
            abrir();
        };

        // "editar" num bloco → formulário preenchido com esse bloco, por cima.
        document.querySelectorAll('[data-editar-aula]').forEach(function (link) {
            link.addEventListener('click', function (ev) {
                var dados = aulasEditaveis[link.dataset.editarAula];
                if (!dados) { return; } // segue o link normal
                ev.preventDefault();
                limparMensagens();
                preencher(dados);
                modo(true);
                marcarBloco(dados.aula_id);
                abrir();
            });
        });

        fechos.forEach(function (f) {
            f.addEventListener('click', function (ev) { ev.preventDefault(); fechar(); });
        });
        fundo.addEventListener('click', fechar);
        document.addEventListener('keydown', function (ev) {
            var confirmacao = document.querySelector('.modal-fundo.aberto');
            if (ev.key === 'Escape' && aberto && !confirmacao) { fechar(); }
        });

        // Página aberta com uma aula em edição, ou depois de um envio que não
        // gravou (conflito, aviso por confirmar): a grelha fica no sítio dessa
        // aula e o formulário abre por cima, com a mensagem lá dentro.
        if (cartao.dataset.abrir === '1') {
            var idAula = form.elements.aula_id.value;
            var alvo = idAula !== '0' ? document.getElementById('aula-' + idAula)
                : document.querySelector('[data-slot^="' + selDia.value + '|' + selBloco.value.split('|')[0] + '"]');
            if (alvo) { alvo.scrollIntoView({ block: 'center' }); }
            marcarBloco(idAula !== '0' ? idAula : null);
            modo(idAula !== '0');
            abrir();
        }

        // Depois de gravar (#aula-ID) ou de remover (#grelha): mostra a grelha,
        // destaca a aula e repete a confirmação num aviso no canto (a mensagem
        // verde do topo fica fora de vista).
        // (no "load": o aviso do canto e o assets/js/script.js só existem
        // depois do rodapé, e o browser já fez o salto normal para a âncora)
        window.addEventListener('load', function () {
            var ancora = location.hash;
            var destino = /^#aula-\d+$/.test(ancora) ? document.querySelector(ancora)
                        : (ancora === '#grelha' ? document.getElementById('grelha') : null);
            if (!destino) { return; }
            destino.scrollIntoView({ block: destino.id === 'grelha' ? 'start' : 'center' });
            if (destino.id !== 'grelha') {
                destino.classList.add('bloco-destacado');
                setTimeout(function () { destino.classList.remove('bloco-destacado'); }, 3200);
            }
            var ok = document.querySelector('[data-mensagem-ok]');
            if (ok && window.showToast) { window.showToast(ok.dataset.mensagemOk); }
            history.replaceState(null, '', location.pathname + location.search);
        });
    })();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
