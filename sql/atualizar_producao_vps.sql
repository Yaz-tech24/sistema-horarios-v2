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

-- 9) Reforço de segurança (igual a sql/migracao_seguranca_v2.sql)
-- 9a) Mudança obrigatória de palavra-passe no primeiro acesso
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'utilizadores' AND COLUMN_NAME = 'deve_mudar_senha');
SET @sql := IF(@c = 0,
  'ALTER TABLE utilizadores ADD COLUMN deve_mudar_senha TINYINT(1) NOT NULL DEFAULT 0 AFTER ativo',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9b) Data da última entrada
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'utilizadores' AND COLUMN_NAME = 'ultimo_login');
SET @sql := IF(@c = 0,
  'ALTER TABLE utilizadores ADD COLUMN ultimo_login DATETIME NULL AFTER bloqueado_ate',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9c) Registo de auditoria (Admin → Registo de atividade; limitação por IP)
CREATE TABLE IF NOT EXISTS registo_auditoria (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id INT NULL,
  acao VARCHAR(40) NOT NULL,
  detalhe VARCHAR(255) NULL,
  ip VARCHAR(45) NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE SET NULL,
  INDEX idx_auditoria_ip (acao, ip, criado_em),
  INDEX idx_auditoria_data (criado_em)
);

-- 9d) Links de recuperação de palavra-passe
CREATE TABLE IF NOT EXISTS recuperacoes_senha (
  id INT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  usado_em DATETIME NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
  UNIQUE KEY uq_recuperacao_token (token_hash)
);

-- 9e) Contas que ainda têm a palavra-passe pública de origem (admin123,
-- coord123, docente123 — os hashes de seed.sql/seed2_contas_teste.sql)
-- passam a ter de a mudar no próximo acesso. Quem já a mudou não é afetado.
UPDATE utilizadores SET deve_mudar_senha = 1
WHERE password_hash IN (
  '$2y$10$dDk7H9KgE022NjXFiEU2Iu7p1ZNdCC5RVZyonuYQxskOgkOLB3r8e',
  '$2y$10$b4IY6hRLpS3/ikzQn3MQwOrTTtPx48NmYUkKaV1xRkYJQpg.9d4me',
  '$2y$10$WvLozLb0aNogxEJifZeM..a7hJPWPvtSCbO/tdHQiOChj6Iz3RiGC'
);

-- 10) Limpeza dos blocos (igual a sql/migracao_limpeza_blocos.sql)
-- 10a) Estudo Autónomo / Atividade Não Letiva sem disciplina, docente nem
--      sala "fantasma" (gravados por versões anteriores do editor)
UPDATE aulas
SET disciplina_id = NULL, docente_id = NULL, docente_regente_id = NULL,
    docente_assistente_id = NULL, sala_id = NULL, subgrupo = NULL
WHERE tipo_bloco <> 'Aula'
  AND (disciplina_id IS NOT NULL OR docente_id IS NOT NULL OR docente_regente_id IS NOT NULL
       OR docente_assistente_id IS NOT NULL OR sala_id IS NOT NULL OR subgrupo IS NOT NULL);

-- 10b) Subgrupos no formato atual ("p1 " -> "P1")
UPDATE aulas
SET subgrupo = NULLIF(UPPER(TRIM(subgrupo)), '')
WHERE subgrupo IS NOT NULL
  AND (TRIM(subgrupo) = '' OR CAST(subgrupo AS BINARY) <> CAST(UPPER(TRIM(subgrupo)) AS BINARY));

SELECT 'Base de dados da VPS atualizada. Depois: Admin -> Conflitos -> Revalidar todos os horarios.' AS resultado;
