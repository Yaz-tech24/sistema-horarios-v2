-- Seed adicional: contas de teste para Coordenador e Docente,
-- disciplinas e turmas de exemplo. Importar DEPOIS do seed.sql.
-- Logins: coordenador@fagrenm.test / coord123
--         docente@fagrenm.test    / docente123
USE horarios_fagrenm;
SET NAMES utf8mb4;

INSERT INTO utilizadores (nome, email, password_hash, perfil) VALUES
('Coordenador Teste', 'coordenador@fagrenm.test', '$2y$10$b4IY6hRLpS3/ikzQn3MQwOrTTtPx48NmYUkKaV1xRkYJQpg.9d4me', 'Coordenador'),
('Jean Muhire', 'docente@fagrenm.test', '$2y$10$WvLozLb0aNogxEJifZeM..a7hJPWPvtSCbO/tdHQiOChj6Iz3RiGC', 'Docente');

-- O coordenador de teste gere IT e GA (controlo de acesso por curso)
INSERT INTO coordenador_curso (utilizador_id, curso_id)
SELECT u.id, c.id FROM utilizadores u, cursos c
WHERE u.email = 'coordenador@fagrenm.test' AND c.sigla IN ('IT','GA');

-- Docente ligado à conta
INSERT INTO docentes (utilizador_id, nome, categoria)
SELECT id, 'Jean Muhire', 'Mestre' FROM utilizadores
WHERE email = 'docente@fagrenm.test';

INSERT INTO docentes (nome, categoria) VALUES
('Elizete Macie', 'Mestre'),
('Daniel Cuinhane', 'Mestre');

-- Disciplinas de exemplo (IT 3º ano e GA 1º ano)
INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula)
SELECT id, 'Programação Móvel', 3, 2, 'Pratica' FROM cursos WHERE sigla='IT';
INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula)
SELECT id, 'Ética Profissional para TI', 3, 1, 'Teorica' FROM cursos WHERE sigla='IT';
INSERT INTO disciplinas (curso_id, nome, ano_curricular, carga_horaria, tipo_aula)
SELECT id, 'Climatologia', 1, 2, 'Teorica' FROM cursos WHERE sigla='GA';

-- O docente Jean pode lecionar as duas de IT
INSERT INTO docente_disciplina (docente_id, disciplina_id)
SELECT d.id, di.id FROM docentes d, disciplinas di
JOIN cursos c ON di.curso_id = c.id
WHERE d.nome='Jean Muhire' AND d.utilizador_id IS NOT NULL AND c.sigla='IT';

-- Turmas de exemplo
INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos)
SELECT id, 3, 'Pos-Laboral', 'Noite', 'A', 28 FROM cursos WHERE sigla='IT';
INSERT INTO turmas (curso_id, ano_curricular, regime, turno, nome_turma, num_alunos)
SELECT id, 1, 'Laboral', 'Manha', 'A', 52 FROM cursos WHERE sigla='GA';
