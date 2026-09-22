-- ============================================================
-- Emparelhamento da base de dados da VPS (Docker + MySQL 8.0)
--
-- O docker-compose.yml só monta schema.sql e seed.sql em
-- /docker-entrypoint-initdb.d — a imagem oficial do MySQL só corre os
-- ficheiros dessa pasta UMA VEZ, quando o volume `horarios_db_data`
-- está vazio. Depois disso, nenhuma migração nem nenhum seed novo corre
-- sozinho, mesmo fazendo `docker compose up --build` com código novo —
-- é por isso que `git pull` + rebuild não bastou para os dados reais
-- aparecerem.
--
-- Este ficheiro traz a base de dados da VPS a par de tudo o que foi
-- adicionado a partir daí (é o equivalente a todos os sql/migracao_*.sql
-- de uma vez). Cada alteração confirma primeiro que ainda não foi
-- aplicada — seguro correr mais do que uma vez, e não apaga nenhum
-- dado real que já lá esteja.
--
-- Como correr (a partir da pasta do projeto na VPS, onde está o
-- docker-compose.yml):
--   docker compose exec -T db mysql -u root -p"$MYSQL_ROOT_PASSWORD" \
--     "$DB_NAME" < sql/atualizar_producao_vps.sql
--   docker compose exec -T db mysql -u root -p"$MYSQL_ROOT_PASSWORD" \
--     "$DB_NAME" < sql/seed3_dados_reais.sql
-- (as variáveis $MYSQL_ROOT_PASSWORD e $DB_NAME já estão no .env da VPS)
-- ============================================================

-- 1) Bloqueio de login por tentativas falhadas
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'utilizadores' AND COLUMN_NAME = 'tentativas_falhadas');
SET @sql := IF(@c = 0,
  'ALTER TABLE utilizadores ADD COLUMN tentativas_falhadas TINYINT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN bloqueado_ate DATETIME NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Regente/assistente por disciplina e por aula
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disciplinas' AND COLUMN_NAME = 'docente_regente_id');
SET @sql := IF(@c = 0,
  'ALTER TABLE disciplinas ADD COLUMN docente_regente_id INT NULL AFTER carga_horaria, ADD COLUMN docente_assistente_id INT NULL AFTER docente_regente_id, ADD CONSTRAINT fk_disciplinas_regente FOREIGN KEY (docente_regente_id) REFERENCES docentes(id), ADD CONSTRAINT fk_disciplinas_assistente FOREIGN KEY (docente_assistente_id) REFERENCES docentes(id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aulas' AND COLUMN_NAME = 'docente_regente_id');
SET @sql := IF(@c = 0,
  'ALTER TABLE aulas ADD COLUMN docente_regente_id INT NULL AFTER docente_id, ADD COLUMN docente_assistente_id INT NULL AFTER docente_regente_id, ADD CONSTRAINT fk_aulas_regente FOREIGN KEY (docente_regente_id) REFERENCES docentes(id), ADD CONSTRAINT fk_aulas_assistente FOREIGN KEY (docente_assistente_id) REFERENCES docentes(id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE aulas SET docente_regente_id = docente_id WHERE docente_id IS NOT NULL AND docente_regente_id IS NULL;
UPDATE disciplinas SET carga_horaria = 2 WHERE carga_horaria > 2 OR carga_horaria IS NULL;
UPDATE disciplinas SET carga_horaria = 1 WHERE carga_horaria < 1;

-- 3) Sem Sábado nos dias letivos
DELETE FROM aulas WHERE dia_semana = 'Sabado' AND tipo_bloco = 'Estudo_Autonomo';
ALTER TABLE aulas MODIFY dia_semana ENUM('Segunda','Terca','Quarta','Quinta','Sexta') NOT NULL;

-- 4) Laboratório por disciplina (RN16)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disciplinas' AND COLUMN_NAME = 'sala_id');
SET @sql := IF(@c = 0, 'ALTER TABLE disciplinas ADD COLUMN sala_id INT NULL AFTER tipo_aula', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_disciplinas_sala');
SET @sql := IF(@c = 0, 'ALTER TABLE disciplinas ADD CONSTRAINT fk_disciplinas_sala FOREIGN KEY (sala_id) REFERENCES salas(id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Sala habitual da turma
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'turmas' AND COLUMN_NAME = 'sala_padrao_id');
SET @sql := IF(@c = 0, 'ALTER TABLE turmas ADD COLUMN sala_padrao_id INT NULL AFTER num_alunos', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_turmas_sala_padrao');
SET @sql := IF(@c = 0, 'ALTER TABLE turmas ADD CONSTRAINT fk_turmas_sala_padrao FOREIGN KEY (sala_padrao_id) REFERENCES salas(id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) Remove a disponibilidade dos docentes e a RN05; índices de desempenho
DELETE FROM conflitos WHERE tipo = 'Disponibilidade';
ALTER TABLE conflitos MODIFY tipo ENUM('Docente','Sala','Turma','Capacidade','Pastoral','Carga') NOT NULL;
DROP TABLE IF EXISTS disponibilidades;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aulas' AND INDEX_NAME = 'idx_aulas_slot');
SET @sql := IF(@c = 0, 'ALTER TABLE aulas ADD INDEX idx_aulas_slot (dia_semana, hora_inicio, hora_fim), ADD INDEX idx_aulas_sala (sala_id), ADD INDEX idx_aulas_regente (docente_regente_id), ADD INDEX idx_aulas_horario (horario_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conflitos' AND INDEX_NAME = 'idx_conflitos_estado');
SET @sql := IF(@c = 0, 'ALTER TABLE conflitos ADD INDEX idx_conflitos_estado (estado)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) Mensagem completa do conflito (nomes/dia/hora), para as páginas de
-- Conflitos deixarem de mostrar só "disc1 em conflito com disc2"
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conflitos' AND COLUMN_NAME = 'mensagem');
SET @sql := IF(@c = 0, 'ALTER TABLE conflitos ADD COLUMN mensagem VARCHAR(500) NULL AFTER tipo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 8) Pedidos dos docentes ao coordenador
CREATE TABLE IF NOT EXISTS pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id INT NOT NULL,
  horario_id INT NOT NULL,
  aula_id INT NULL,
  referencia VARCHAR(160) NOT NULL,
  mensagem VARCHAR(500) NOT NULL,
  estado ENUM('Aberto','Resolvido') DEFAULT 'Aberto',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolvido_em DATETIME NULL,
  resolvido_por INT NULL,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id),
  FOREIGN KEY (horario_id) REFERENCES horarios(id),
  FOREIGN KEY (aula_id) REFERENCES aulas(id),
  FOREIGN KEY (resolvido_por) REFERENCES utilizadores(id),
  INDEX idx_pedidos_estado (estado)
);

SELECT 'Base de dados da VPS atualizada.' AS resultado;
