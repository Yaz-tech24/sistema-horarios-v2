-- ============================================================
-- Migração: sala habitual (padrão) de cada turma
--
-- Até aqui a sala só era escolhida aula a aula, no editor de horário. As
-- fichas reais por curso mostram que, na prática, cada turma já tem uma
-- sala fixa para as aulas teóricas (ex.: Direito 1º Ano Laboral — Turma A
-- funciona sempre em "Santo André"). Guardar isso permite à geração
-- automática sugerir logo essa sala em vez de escolher só por capacidade.
--
-- Prioridade quando a sala não é forçada manualmente: laboratório da
-- disciplina (RN16) > sala padrão da turma > escolha automática por
-- capacidade — ver gerarHorarioAutomatico() em
-- includes/funcoes_coordenador.php.
-- ============================================================

USE horarios_fagrenm;

ALTER TABLE turmas
  ADD COLUMN sala_padrao_id INT NULL AFTER num_alunos,
  ADD CONSTRAINT fk_turmas_sala_padrao
    FOREIGN KEY (sala_padrao_id) REFERENCES salas(id);
