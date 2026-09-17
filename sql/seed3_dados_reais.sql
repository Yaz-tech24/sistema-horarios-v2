-- ============================================================
-- Dados reais: salas, turmas, disciplinas e docentes por curso
-- (cursos/salas base e as 3 contas de teste já vêm de seed.sql
-- e seed2_contas_teste.sql — correr este ficheiro depois desses
-- dois, numa base de dados já com sql/migracao_sala_padrao_turma.sql
-- aplicada).
--
-- Idempotente: cada INSERT procura tudo por nome e só grava se
-- ainda não existir — corrê-lo outra vez não duplica nada.
--
-- Dois conjuntos das fichas originais ficaram de fora, por o
-- próprio pedido os ter assinalado como desatualizados: a ficha
-- de "Administração e Gestão Hospitalar" (2016) e a ficha antiga
-- de Gestão de Recursos Humanos (2016, salas 109/110/Auditório II).
--
-- Nomes de sala/docente foram normalizados quando eram variantes
-- óbvias do mesmo nome em fichas de departamentos diferentes (ex.:
-- "S. André"/"Sto. André"/"Santa André" -> "Santo André").
-- No Direito, que não distingue Regente de Assistente, o primeiro
-- nome listado foi tratado como regente e o segundo, quando existe,
-- como assistente.
-- ============================================================

USE horarios_fagrenm;

INSERT INTO cursos (nome, sigla)
SELECT 'Contabilidade e Auditoria', 'CA'
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE sigla = 'CA');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'Santo Alberto Magno', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'Santo Alberto Magno');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'Santo António', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'Santo António');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'São Francisco de Assis', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'São Francisco de Assis');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'Santo André', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'Santo André');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'São Jerónimo', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'São Jerónimo');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'Beata Anuarite', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'Beata Anuarite');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'Santo Carlos Lwanga', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'Santo Carlos Lwanga');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'Santo Ireneu', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'Santo Ireneu');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'Santa Madalena', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'Santa Madalena');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'Santo Xavier de Loyola', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'Santo Xavier de Loyola');

INSERT INTO salas (nome, tipo, capacidade)
SELECT 'Santo Anselmo', 'Normal', 50
WHERE NOT EXISTS (SELECT 1 FROM salas WHERE nome = 'Santo Anselmo');

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 1, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo Alberto Magno')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Alberto Magno')
WHERE c.sigla = 'IT' AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 2, 'Laboral', 'Tarde', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo António')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo António')
WHERE c.sigla = 'IT' AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 2, 'Pos-Laboral', 'Noite', 'A', 45, (SELECT id FROM salas WHERE nome = 'São Francisco de Assis')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 2 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'São Francisco de Assis')
WHERE c.sigla = 'IT' AND t.ano_curricular = 2 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'São Francisco de Assis')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'São Francisco de Assis')
WHERE c.sigla = 'IT' AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Pos-Laboral', 'Noite', 'A', 45, (SELECT id FROM salas WHERE nome = 'São Francisco de Assis')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'São Francisco de Assis')
WHERE c.sigla = 'IT' AND t.ano_curricular = 3 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 1, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo André')
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo André')
WHERE c.sigla = 'CA' AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 2, 'Laboral', 'Tarde', 'A', 45, (SELECT id FROM salas WHERE nome = 'São Jerónimo')
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'São Jerónimo')
WHERE c.sigla = 'CA' AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 2, 'Pos-Laboral', 'Noite', 'A', 45, (SELECT id FROM salas WHERE nome = 'São Jerónimo')
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 2 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'São Jerónimo')
WHERE c.sigla = 'CA' AND t.ano_curricular = 2 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'São Jerónimo')
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'São Jerónimo')
WHERE c.sigla = 'CA' AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Pos-Laboral', 'Noite', 'A', 45, (SELECT id FROM salas WHERE nome = 'São Jerónimo')
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'São Jerónimo')
WHERE c.sigla = 'CA' AND t.ano_curricular = 3 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 1, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo António')
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo António')
WHERE c.sigla = 'GRH' AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Beata Anuarite')
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Beata Anuarite')
WHERE c.sigla = 'GRH' AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 1, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo André')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo André')
WHERE c.sigla = 'DIR' AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 1, 'Laboral', 'Manha', 'B', 45, (SELECT id FROM salas WHERE nome = 'Santo Carlos Lwanga')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'B');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Carlos Lwanga')
WHERE c.sigla = 'DIR' AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'B' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 1, 'Pos-Laboral', 'Noite', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo Ireneu')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 1 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Ireneu')
WHERE c.sigla = 'DIR' AND t.ano_curricular = 1 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 2, 'Laboral', 'Tarde', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo André')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo André')
WHERE c.sigla = 'DIR' AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 2, 'Pos-Laboral', 'Noite', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo André')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 2 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo André')
WHERE c.sigla = 'DIR' AND t.ano_curricular = 2 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santa Madalena')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santa Madalena')
WHERE c.sigla = 'DIR' AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Pos-Laboral', 'Noite', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santa Madalena')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santa Madalena')
WHERE c.sigla = 'DIR' AND t.ano_curricular = 3 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 4, 'Laboral', 'Tarde', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo Xavier de Loyola')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 4 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Xavier de Loyola')
WHERE c.sigla = 'DIR' AND t.ano_curricular = 4 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 4, 'Pos-Laboral', 'Noite', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santa Bakita')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 4 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santa Bakita')
WHERE c.sigla = 'DIR' AND t.ano_curricular = 4 AND t.regime = 'Pos-Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 1, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo Carlos Lwanga')
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Carlos Lwanga')
WHERE c.sigla = 'EG' AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 2, 'Laboral', 'Tarde', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo Anselmo')
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Anselmo')
WHERE c.sigla = 'EG' AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo Anselmo')
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Anselmo')
WHERE c.sigla = 'EG' AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 2, 'Laboral', 'Tarde', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo Pedro')
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Pedro')
WHERE c.sigla = 'AP' AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo Pedro')
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Pedro')
WHERE c.sigla = 'AP' AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 1, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santa Bakita')
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santa Bakita')
WHERE c.sigla = 'GA' AND t.ano_curricular = 1 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 2, 'Laboral', 'Tarde', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santa Bakita')
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santa Bakita')
WHERE c.sigla = 'GA' AND t.ano_curricular = 2 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos, sala_padrao_id)
SELECT c.id, 3, 'Laboral', 'Manha', 'A', 45, (SELECT id FROM salas WHERE nome = 'Santo Pedro')
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.curso_id = c.id AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A');
UPDATE turmas t JOIN cursos c ON c.id = t.curso_id SET t.sala_padrao_id = (SELECT id FROM salas WHERE nome = 'Santo Pedro')
WHERE c.sigla = 'GA' AND t.ano_curricular = 3 AND t.regime = 'Laboral' AND t.nome_turma = 'A' AND t.sala_padrao_id IS NULL;

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Fundamentos de Programação', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Jean Muhire'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Fundamentos de Programação' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Jean Muhire')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Fundamentos de Programação' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Fundamentos de Rede', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Elizete Macie'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Fundamentos de Rede' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Elizete Macie')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Fundamentos de Rede' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Plataforma de Hardware e Software', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Danilo Richards'), (SELECT id FROM docentes WHERE nome = 'Edson Rodolfo')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Plataforma de Hardware e Software' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Danilo Richards')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Edson Rodolfo'))
WHERE c.sigla = 'IT' AND d.nome = 'Plataforma de Hardware e Software' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Habilidades de Vida, SSR, HIV/SIDA', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Longo Chuva'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Habilidades de Vida, SSR, HIV/SIDA' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Longo Chuva')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Habilidades de Vida, SSR, HIV/SIDA' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Inglês II', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Inglês II' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Inglês II' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Administração e Manutenção de Sistema', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Elizete Macie'), (SELECT id FROM docentes WHERE nome = 'Edson Rodolfo')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Administração e Manutenção de Sistema' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Elizete Macie')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Edson Rodolfo'))
WHERE c.sigla = 'IT' AND d.nome = 'Administração e Manutenção de Sistema' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Programação Orientada para Objectos', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Jean Muhire'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Programação Orientada para Objectos' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Jean Muhire')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Programação Orientada para Objectos' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Implementação e Base de Dados', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Mário Pires'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Implementação e Base de Dados' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Mário Pires')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Implementação e Base de Dados' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Estudos Avançados de Rede', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Elizete Macie'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Estudos Avançados de Rede' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Elizete Macie')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Estudos Avançados de Rede' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Inglês IV', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Daniel Jariosse'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Inglês IV' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Daniel Jariosse')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Inglês IV' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Fundamentos de Teologia Católica', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Elton Laissone'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Fundamentos de Teologia Católica' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Elton Laissone')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Fundamentos de Teologia Católica' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Administração de Serviços de Rede', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Elizete Macie'), (SELECT id FROM docentes WHERE nome = 'António Almoço')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Administração de Serviços de Rede' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Elizete Macie')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'António Almoço'))
WHERE c.sigla = 'IT' AND d.nome = 'Administração de Serviços de Rede' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Gestão de Serviço de TI', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Danilo Richards'), (SELECT id FROM docentes WHERE nome = 'Márcio Chin')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Gestão de Serviço de TI' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Danilo Richards')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Márcio Chin'))
WHERE c.sigla = 'IT' AND d.nome = 'Gestão de Serviço de TI' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Programação Móvel', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Jean Muhire'), (SELECT id FROM docentes WHERE nome = 'Edilson Malate')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Programação Móvel' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Jean Muhire')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Edilson Malate'))
WHERE c.sigla = 'IT' AND d.nome = 'Programação Móvel' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Programação Visual', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Danilo Richards'), (SELECT id FROM docentes WHERE nome = 'Edson Rodolfo')
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Programação Visual' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Danilo Richards')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Edson Rodolfo'))
WHERE c.sigla = 'IT' AND d.nome = 'Programação Visual' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Práticas em IT', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Alex Chihururu'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Práticas em IT' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Alex Chihururu')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Práticas em IT' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Ética Profissional para TI', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Elizete Macie'), NULL
FROM cursos c WHERE c.sigla = 'IT'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Ética Profissional para TI' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Elizete Macie')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'IT' AND d.nome = 'Ética Profissional para TI' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Matemática II', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Jorge Camisola'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Matemática II' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Jorge Camisola')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Matemática II' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Ética Geral', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Virgílio de Arimateia'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Ética Geral' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Virgílio de Arimateia')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Ética Geral' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Comercial', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Comercial' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Direito Comercial' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Inglês Técnico Para Contabilistas', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Inglês Técnico Para Contabilistas' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Inglês Técnico Para Contabilistas' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Contabilidade Financeira II', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Anussa Mirasse'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Contabilidade Financeira II' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Anussa Mirasse')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Contabilidade Financeira II' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Técnica de Expressão', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'António Alfinar'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Técnica de Expressão' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'António Alfinar')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Técnica de Expressão' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Contabilidade Financeira IV', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'José Sarmento'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Contabilidade Financeira IV' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'José Sarmento')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Contabilidade Financeira IV' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Cálculo Financeiro II', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Anselmo Pedro'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Cálculo Financeiro II' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Anselmo Pedro')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Cálculo Financeiro II' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Estatística II', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Andissene Andissene'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Estatística II' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Andissene Andissene')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Estatística II' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Investigação Operacional I', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Noivado Beula'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Investigação Operacional I' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Noivado Beula')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Investigação Operacional I' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Contabilidade de Custos II', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Carlos Dezembro'), (SELECT id FROM docentes WHERE nome = 'Félquer Diogo')
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Contabilidade de Custos II' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Carlos Dezembro')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Félquer Diogo'))
WHERE c.sigla = 'CA' AND d.nome = 'Contabilidade de Custos II' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Gestão Financeira II', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'José Sarmento'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Gestão Financeira II' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'José Sarmento')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Gestão Financeira II' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Fiscalidade', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Carlos Dezembro'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Fiscalidade' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Carlos Dezembro')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Fiscalidade' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Simulação Empresarial I', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Carla Semente'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Simulação Empresarial I' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Carla Semente')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Simulação Empresarial I' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Auditoria Financeira II', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Anussa Mirasse'), NULL
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Auditoria Financeira II' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Anussa Mirasse')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'CA' AND d.nome = 'Auditoria Financeira II' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Contabilidade Internacional', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'José Sarmento'), (SELECT id FROM docentes WHERE nome = 'Félquer Diogo')
FROM cursos c WHERE c.sigla = 'CA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Contabilidade Internacional' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'José Sarmento')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Félquer Diogo'))
WHERE c.sigla = 'CA' AND d.nome = 'Contabilidade Internacional' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Inglês Geral II', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Inglês Geral II' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Inglês Geral II' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Téc. de Exp. e Comunicação', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Palvina Nhambi'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Téc. de Exp. e Comunicação' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Palvina Nhambi')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Téc. de Exp. e Comunicação' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Princípios de Marketing', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Longo Chuva'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Princípios de Marketing' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Longo Chuva')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Princípios de Marketing' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Ética Geral', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Virgílio de Arimateia'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Ética Geral' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Virgílio de Arimateia')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Ética Geral' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Introdução a Gest. dos RH', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Brito Taimo'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Introdução a Gest. dos RH' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Brito Taimo')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Introdução a Gest. dos RH' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Noções de Direito', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Noções de Direito' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Noções de Direito' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Adm. de Cargo e Remuneração', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Brito Taimo'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Adm. de Cargo e Remuneração' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Brito Taimo')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Adm. de Cargo e Remuneração' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Diagnóstico e Mudança Organizacional', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Nixon Manuel'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Diagnóstico e Mudança Organizacional' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Nixon Manuel')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Diagnóstico e Mudança Organizacional' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Hig. e Segurança no Trabalho', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Alfatílio Húo'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Hig. e Segurança no Trabalho' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Alfatílio Húo')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Hig. e Segurança no Trabalho' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Psicologia de Trabalho', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Carla Semente'), NULL
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Psicologia de Trabalho' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Carla Semente')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GRH' AND d.nome = 'Psicologia de Trabalho' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Proj. de Simulação Empresarial I', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'José Sarmento'), (SELECT id FROM docentes WHERE nome = 'Anussa Mirasse')
FROM cursos c WHERE c.sigla = 'GRH'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Proj. de Simulação Empresarial I' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'José Sarmento')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Anussa Mirasse'))
WHERE c.sigla = 'GRH' AND d.nome = 'Proj. de Simulação Empresarial I' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Introdução ao Estudo de Direito II', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Eloi Gilmar'), (SELECT id FROM docentes WHERE nome = 'Domingos Adany')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Introdução ao Estudo de Direito II' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Eloi Gilmar')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Domingos Adany'))
WHERE c.sigla = 'DIR' AND d.nome = 'Introdução ao Estudo de Direito II' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Constitucional II', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Jacques Kazadi'), (SELECT id FROM docentes WHERE nome = 'Ernesto Camacho')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Constitucional II' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Jacques Kazadi')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Ernesto Camacho'))
WHERE c.sigla = 'DIR' AND d.nome = 'Direito Constitucional II' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Constitucional II', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Jacques Kazadi'), (SELECT id FROM docentes WHERE nome = 'Ernesto Camacho')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Constitucional II' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Jacques Kazadi')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Ernesto Camacho'))
WHERE c.sigla = 'DIR' AND d.nome = 'Direito Constitucional II' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Ciência Política', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Bernardo Sicoche'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Ciência Política' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Bernardo Sicoche')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Ciência Política' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Inglês', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Inglês' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Inglês' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Finanças Públicas e Direito Financeiro', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Gercio Macie'), (SELECT id FROM docentes WHERE nome = 'Cremildo Massuca')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Finanças Públicas e Direito Financeiro' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Gercio Macie')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Cremildo Massuca'))
WHERE c.sigla = 'DIR' AND d.nome = 'Finanças Públicas e Direito Financeiro' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Habilidades de Vida, S.S e HIV/Sida', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Naftal Zafanias'), (SELECT id FROM docentes WHERE nome = 'Carla Semente')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Habilidades de Vida, S.S e HIV/Sida' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Naftal Zafanias')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Carla Semente'))
WHERE c.sigla = 'DIR' AND d.nome = 'Habilidades de Vida, S.S e HIV/Sida' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Português II', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Palvina Nhambi'), (SELECT id FROM docentes WHERE nome = 'Alfinar Laisse')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Português II' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Palvina Nhambi')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Alfinar Laisse'))
WHERE c.sigla = 'DIR' AND d.nome = 'Português II' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Fundamentos de Teologia Católica', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Justino Cesar'), (SELECT id FROM docentes WHERE nome = 'Agnano Laissone')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Fundamentos de Teologia Católica' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Justino Cesar')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Agnano Laissone'))
WHERE c.sigla = 'DIR' AND d.nome = 'Fundamentos de Teologia Católica' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Teoria Geral do Dto Civil II', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Ernesto Camacho'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Teoria Geral do Dto Civil II' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Ernesto Camacho')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Teoria Geral do Dto Civil II' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Administrativo II', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Rosina Zandamela'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Administrativo II' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Rosina Zandamela')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito Administrativo II' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito da Energia: Gás e Petróleo', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista'), (SELECT id FROM docentes WHERE nome = 'Ernesto Gale')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito da Energia: Gás e Petróleo' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Ernesto Gale'))
WHERE c.sigla = 'DIR' AND d.nome = 'Direito da Energia: Gás e Petróleo' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito do Ambiente e Urbanismo', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Gercio Macie'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito do Ambiente e Urbanismo' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Gercio Macie')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito do Ambiente e Urbanismo' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Penal II', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Nahome Cidade'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Penal II' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Nahome Cidade')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito Penal II' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Tributário', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Constâncio Tevete'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Tributário' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Constâncio Tevete')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito Tributário' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Processual Laboral', 3, 2, 'Teorica', NULL, NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Processual Laboral' AND d.ano_curricular = 3);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Sociologia Jurídica', 3, 2, 'Teorica', NULL, NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Sociologia Jurídica' AND d.ano_curricular = 3);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito do Trabalho II', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista'), (SELECT id FROM docentes WHERE nome = 'Rosina Zandamela')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito do Trabalho II' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Rosina Zandamela'))
WHERE c.sigla = 'DIR' AND d.nome = 'Direito do Trabalho II' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito do Trabalho II', 4, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista'), (SELECT id FROM docentes WHERE nome = 'Rosina Zandamela')
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito do Trabalho II' AND d.ano_curricular = 4);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Rosina Zandamela'))
WHERE c.sigla = 'DIR' AND d.nome = 'Direito do Trabalho II' AND d.ano_curricular = 4
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito das Sucessões', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Nahome Cidade'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito das Sucessões' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Nahome Cidade')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito das Sucessões' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Processual Civil Executivo', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Ivete Luis'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Processual Civil Executivo' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Ivete Luis')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito Processual Civil Executivo' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito das Obrigações I/II', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Ivan Taibo'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito das Obrigações I/II' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Ivan Taibo')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito das Obrigações I/II' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito das Sociedades Comerciais', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito das Sociedades Comerciais' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Sérgio Baptista')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito das Sociedades Comerciais' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direitos Reais e de Prop. Intelectual', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Ernesto Camacho'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direitos Reais e de Prop. Intelectual' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Ernesto Camacho')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direitos Reais e de Prop. Intelectual' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Ética e Deontologia Jurídica', 4, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Rosina Zandamela'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Ética e Deontologia Jurídica' AND d.ano_curricular = 4);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Rosina Zandamela')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Ética e Deontologia Jurídica' AND d.ano_curricular = 4
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Processual Executivo', 4, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'João Zinocacassa'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Processual Executivo' AND d.ano_curricular = 4);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'João Zinocacassa')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito Processual Executivo' AND d.ano_curricular = 4
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Bancário e dos Seguros', 4, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Gercio Macie'), NULL
FROM cursos c WHERE c.sigla = 'DIR'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Bancário e dos Seguros' AND d.ano_curricular = 4);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Gercio Macie')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'DIR' AND d.nome = 'Direito Bancário e dos Seguros' AND d.ano_curricular = 4
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Matemática II', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Inelsa Aspirante'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Matemática II' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Inelsa Aspirante')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Matemática II' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Contabilidade Financeira', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Félquer Diogo'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Contabilidade Financeira' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Félquer Diogo')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Contabilidade Financeira' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Ética Geral', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Virgílio de Arimateia'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Ética Geral' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Virgílio de Arimateia')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Ética Geral' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Fundamentos de Gestão', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Brito Taimo'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Fundamentos de Gestão' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Brito Taimo')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Fundamentos de Gestão' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Macroeconomia I', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Anselmo Pedro'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Macroeconomia I' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Anselmo Pedro')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Macroeconomia I' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Técnica de Expressão', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Palvina Nhambi'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Técnica de Expressão' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Palvina Nhambi')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Técnica de Expressão' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Estatística II', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Andissene Andissene'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Estatística II' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Andissene Andissene')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Estatística II' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Economia Internacional', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Nixon Manuel'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Economia Internacional' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Nixon Manuel')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Economia Internacional' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Contabilidade de Gestão', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'José Sarmento'), (SELECT id FROM docentes WHERE nome = 'Félquer Diogo')
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Contabilidade de Gestão' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'José Sarmento')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Félquer Diogo'))
WHERE c.sigla = 'EG' AND d.nome = 'Contabilidade de Gestão' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Cálculo Financeiro', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Anselmo Pedro'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Cálculo Financeiro' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Anselmo Pedro')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Cálculo Financeiro' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Macroeconomia II', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Nixon Vicente'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Macroeconomia II' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Nixon Vicente')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Macroeconomia II' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Projecto de Simulação Empresarial I', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'José Sarmento'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Projecto de Simulação Empresarial I' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'José Sarmento')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Projecto de Simulação Empresarial I' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Mercados e Investimentos Financeiros', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Domingos Saite'), (SELECT id FROM docentes WHERE nome = 'Félquer Diogo')
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Mercados e Investimentos Financeiros' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Domingos Saite')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Félquer Diogo'))
WHERE c.sigla = 'EG' AND d.nome = 'Mercados e Investimentos Financeiros' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Macroeconomia III', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Nixon Vicente'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Macroeconomia III' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Nixon Vicente')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Macroeconomia III' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Econometria II', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Feroz Mussa'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Econometria II' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Feroz Mussa')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Econometria II' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Economia de Ambiente e Recursos', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Anselmo Pedro'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Economia de Ambiente e Recursos' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Anselmo Pedro')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Economia de Ambiente e Recursos' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Economia Pública', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Nixon Vicente'), NULL
FROM cursos c WHERE c.sigla = 'EG'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Economia Pública' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Nixon Vicente')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'EG' AND d.nome = 'Economia Pública' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Relações Públicas e Marketing', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Longo Chuva'), NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Relações Públicas e Marketing' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Longo Chuva')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'AP' AND d.nome = 'Relações Públicas e Marketing' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Administração Pública Comparada', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Deolinda Lurdes Inácio'), NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Administração Pública Comparada' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Deolinda Lurdes Inácio')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'AP' AND d.nome = 'Administração Pública Comparada' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Gestão de RH na Função Pública', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Carla Semente'), NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Gestão de RH na Função Pública' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Carla Semente')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'AP' AND d.nome = 'Gestão de RH na Função Pública' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Reforma do Sector Público', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'José Albuquerque'), NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Reforma do Sector Público' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'José Albuquerque')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'AP' AND d.nome = 'Reforma do Sector Público' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Gestão de Informação na Adm. Pública', 3, 2, 'Teorica', NULL, NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Gestão de Informação na Adm. Pública' AND d.ano_curricular = 3);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Economia Pública', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Domingos Saite'), NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Economia Pública' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Domingos Saite')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'AP' AND d.nome = 'Economia Pública' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Gestão Estratégica', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Deolinda Lurdes Inácio'), NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Gestão Estratégica' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Deolinda Lurdes Inácio')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'AP' AND d.nome = 'Gestão Estratégica' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Finanças e Orçamento Público', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Carlos Dezembro'), NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Finanças e Orçamento Público' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Carlos Dezembro')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'AP' AND d.nome = 'Finanças e Orçamento Público' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Contabilidade Geral', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Marcelino Escova'), NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Contabilidade Geral' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Marcelino Escova')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'AP' AND d.nome = 'Contabilidade Geral' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Direito Administrativo Básico', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Heitor Foia'), NULL
FROM cursos c WHERE c.sigla = 'AP'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Direito Administrativo Básico' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Heitor Foia')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'AP' AND d.nome = 'Direito Administrativo Básico' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Química Ambiental', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Nthete Buleza'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Química Ambiental' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Nthete Buleza')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Química Ambiental' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Cartografia', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Jemusse Gale'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Cartografia' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Jemusse Gale')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Cartografia' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Climatologia', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Daniel Cuinhane'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Climatologia' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Daniel Cuinhane')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Climatologia' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Inglês II', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Inglês II' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Vicente Mpanda')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Inglês II' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Estatística e Probabilidade', 1, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Andissene Andissene'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Estatística e Probabilidade' AND d.ano_curricular = 1);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Andissene Andissene')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Estatística e Probabilidade' AND d.ano_curricular = 1
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Gestão de Riscos Ambientais', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Ringo Victor'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Gestão de Riscos Ambientais' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Ringo Victor')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Gestão de Riscos Ambientais' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Planeamento Regional', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Jemusse Gale'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Planeamento Regional' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Jemusse Gale')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Planeamento Regional' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Recuperação de Áreas Degradadas', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'António Tuzine'), (SELECT id FROM docentes WHERE nome = 'Hélio Andicene')
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Recuperação de Áreas Degradadas' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'António Tuzine')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Hélio Andicene'))
WHERE c.sigla = 'GA' AND d.nome = 'Recuperação de Áreas Degradadas' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Ética Social e Ambiental', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Elton Laissone'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Ética Social e Ambiental' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Elton Laissone')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Ética Social e Ambiental' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Recursos Energéticos e Meio Ambiente', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Alfatílio Húo'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Recursos Energéticos e Meio Ambiente' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Alfatílio Húo')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Recursos Energéticos e Meio Ambiente' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Ecologia Ambiental', 2, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'António Tuzine'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Ecologia Ambiental' AND d.ano_curricular = 2);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'António Tuzine')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Ecologia Ambiental' AND d.ano_curricular = 2
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Ecoturismo', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Longo Chuva'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Ecoturismo' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Longo Chuva')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Ecoturismo' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Recursos Naturais e Sustentabilidade Ambiental', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Trindade Chapare'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Recursos Naturais e Sustentabilidade Ambiental' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Trindade Chapare')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Recursos Naturais e Sustentabilidade Ambiental' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Gestão das Áreas Protegidas', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'António Tuzine'), (SELECT id FROM docentes WHERE nome = 'Hélio Andicene')
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Gestão das Áreas Protegidas' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'António Tuzine')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, (SELECT id FROM docentes WHERE nome = 'Hélio Andicene'))
WHERE c.sigla = 'GA' AND d.nome = 'Gestão das Áreas Protegidas' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Gestão Costeira e Águas Interiores', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Daniel Cuinhane'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Gestão Costeira e Águas Interiores' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Daniel Cuinhane')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Gestão Costeira e Águas Interiores' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Estudo de Avaliação de Impacto Ambiental', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Alfatílio Húo'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Estudo de Avaliação de Impacto Ambiental' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Alfatílio Húo')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Estudo de Avaliação de Impacto Ambiental' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula, docente_regente_id, docente_assistente_id)
SELECT c.id, 'Avaliação Integrada de Recursos Naturais e OT', 3, 2, 'Teorica', (SELECT id FROM docentes WHERE nome = 'Jemusse Gale'), NULL
FROM cursos c WHERE c.sigla = 'GA'
  AND NOT EXISTS (SELECT 1 FROM disciplinas d WHERE d.curso_id = c.id AND d.nome = 'Avaliação Integrada de Recursos Naturais e OT' AND d.ano_curricular = 3);
UPDATE disciplinas d JOIN cursos c ON c.id = d.curso_id
SET d.docente_regente_id = COALESCE(d.docente_regente_id, (SELECT id FROM docentes WHERE nome = 'Jemusse Gale')),
    d.docente_assistente_id = COALESCE(d.docente_assistente_id, NULL)
WHERE c.sigla = 'GA' AND d.nome = 'Avaliação Integrada de Recursos Naturais e OT' AND d.ano_curricular = 3
  AND (d.docente_regente_id IS NULL OR d.docente_assistente_id IS NULL);

