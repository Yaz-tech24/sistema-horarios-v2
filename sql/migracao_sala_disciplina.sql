-- ============================================================
-- Migração: Laboratório por disciplina (disciplinas.sala_id)
--
-- Só ALTER TABLE — não apaga nem substitui nenhuma linha já existente.
-- Seguro para correr contra a base de dados local já em uso.
--
-- Disciplinas com tipo_aula 'Pratica' ou 'Laboratorial' passam a poder
-- indicar o laboratório onde costumam decorrer (ver admin/disciplinas.php).
-- É só uma sugestão: a geração automática (gerarHorarioAutomatico, em
-- includes/funcoes_coordenador.php) usa-a como preferência quando definida,
-- e o editor manual pré-preenche o campo Sala a partir dela — mas continua
-- a ser possível marcar qualquer aula numa sala diferente.
-- ============================================================

USE horarios_fagrenm;

ALTER TABLE disciplinas
  ADD COLUMN sala_id INT NULL AFTER tipo_aula,
  ADD CONSTRAINT fk_disciplinas_sala
    FOREIGN KEY (sala_id) REFERENCES salas(id);
