-- ============================================================
-- Migração: Regente + Assistente por disciplina/aula, RN08 (Pastoral)
-- e RN09 (limite de 2x/semana por disciplina)
--
-- Só ALTER TABLE / UPDATE — não apaga nem substitui nenhuma linha já
-- existente. Seguro para correr contra a base de dados local já em uso.
--
-- Decisão de desenho (ver includes/funcoes_coordenador.php e
-- includes/funcoes_conflitos.php para o código correspondente):
-- `aulas.docente_id` MANTÉM-SE tal como estava (é usado por
-- docente/meu_horario.php, relatórios, notificações, motor de conflitos
-- RN01/RN04/RN05). A partir de agora o valor gravado em docente_id é
-- sempre igual a docente_regente_id (o código passa sempre os dois juntos).
-- Isto evita reescrever/arriscar todas as queries existentes que já
-- confiam em docente_id, mantendo o mesmo significado: "o docente
-- principal desta aula". docente_assistente_id é o campo novo e opcional.
-- ============================================================

USE horarios_fagrenm;

-- 1) Disciplinas: regente (obrigatório na aplicação, NULL permitido na BD
--    para não partir disciplinas antigas antes de serem editadas) +
--    assistente (sempre opcional).
ALTER TABLE disciplinas
  ADD COLUMN docente_regente_id INT NULL AFTER carga_horaria,
  ADD COLUMN docente_assistente_id INT NULL AFTER docente_regente_id,
  ADD CONSTRAINT fk_disciplinas_regente
    FOREIGN KEY (docente_regente_id) REFERENCES docentes(id),
  ADD CONSTRAINT fk_disciplinas_assistente
    FOREIGN KEY (docente_assistente_id) REFERENCES docentes(id);

-- 2) Aulas: espelha os mesmos dois campos, preenchidos a partir da
--    disciplina no momento da criação da aula (mas ajustáveis manualmente
--    nessa aula específica — ver coordenador/editor_horario.php).
ALTER TABLE aulas
  ADD COLUMN docente_regente_id INT NULL AFTER docente_id,
  ADD COLUMN docente_assistente_id INT NULL AFTER docente_regente_id,
  ADD CONSTRAINT fk_aulas_regente
    FOREIGN KEY (docente_regente_id) REFERENCES docentes(id),
  ADD CONSTRAINT fk_aulas_assistente
    FOREIGN KEY (docente_assistente_id) REFERENCES docentes(id);

-- Backfill: aulas já existentes passam a ter docente_regente_id = docente_id
-- (o "docente" que já tinham era, na prática, o regente).
UPDATE aulas SET docente_regente_id = docente_id WHERE docente_id IS NOT NULL;

-- 3) Conflitos: novos tipos bloqueantes RN08 (Pastoral) e RN09 (Carga).
ALTER TABLE conflitos
  MODIFY tipo ENUM('Docente','Sala','Turma','Capacidade','Disponibilidade','Pastoral','Carga') NOT NULL;

-- 4) carga_horaria (disciplinas) passa a significar sempre "vezes por
--    semana" (1 ou 2 — RN09). Não muda de tipo, só limitamos valores fora
--    do intervalo que possam já existir, por segurança.
UPDATE disciplinas SET carga_horaria = 2 WHERE carga_horaria > 2 OR carga_horaria IS NULL;
UPDATE disciplinas SET carga_horaria = 1 WHERE carga_horaria < 1;
