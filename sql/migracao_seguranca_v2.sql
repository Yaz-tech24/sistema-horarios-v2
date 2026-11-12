-- ============================================================
-- Migração: reforço de segurança (v2)
--
-- Só para bases de dados criadas ANTES desta alteração — uma instalação
-- nova a partir de sql/schema.sql já traz tudo isto.
-- Idempotente: cada passo confirma primeiro se ainda falta, por isso é
-- seguro correr mais do que uma vez. Não apaga nem altera dados existentes
-- (a única escrita é o passo 5, e só toca em contas que ainda estejam com
-- a palavra-passe pública das contas de origem/teste).
--
-- Como correr:
--   mysql --default-character-set=utf8mb4 -u root -p horarios_fagrenm < sql/migracao_seguranca_v2.sql
-- (em Docker, sql/atualizar_producao_vps.sql já inclui estes passos)
--
-- O que acrescenta:
--   1) utilizadores.deve_mudar_senha — obriga a escolher palavra-passe
--      nova no primeiro acesso (contas de origem e palavras-passe
--      definidas pelo Administrador);
--   2) utilizadores.ultimo_login — data da última entrada;
--   3) registo_auditoria — entradas, falhas de login e alterações de
--      contas (Admin → Registo de atividade), e base da limitação de
--      tentativas por IP;
--   4) recuperacoes_senha — links de "Esqueci-me da palavra-passe".
-- ============================================================

-- 1) Mudança obrigatória de palavra-passe
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'utilizadores' AND COLUMN_NAME = 'deve_mudar_senha');
SET @sql := IF(@c = 0,
  'ALTER TABLE utilizadores ADD COLUMN deve_mudar_senha TINYINT(1) NOT NULL DEFAULT 0 AFTER ativo',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Última entrada
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'utilizadores' AND COLUMN_NAME = 'ultimo_login');
SET @sql := IF(@c = 0,
  'ALTER TABLE utilizadores ADD COLUMN ultimo_login DATETIME NULL AFTER bloqueado_ate',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Registo de auditoria
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

-- 4) Links de recuperação de palavra-passe
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

-- 5) Contas que AINDA têm a palavra-passe pública de origem (os hashes
-- abaixo são exatamente os de sql/seed.sql e sql/seed2_contas_teste.sql —
-- admin123, coord123, docente123, que estão escritas no README). Passam a
-- ter de a mudar no próximo acesso. Quem já a mudou tem outro hash e não
-- é afetado.
UPDATE utilizadores SET deve_mudar_senha = 1
WHERE password_hash IN (
  '$2y$10$dDk7H9KgE022NjXFiEU2Iu7p1ZNdCC5RVZyonuYQxskOgkOLB3r8e',
  '$2y$10$b4IY6hRLpS3/ikzQn3MQwOrTTtPx48NmYUkKaV1xRkYJQpg.9d4me',
  '$2y$10$WvLozLb0aNogxEJifZeM..a7hJPWPvtSCbO/tdHQiOChj6Iz3RiGC'
);

SELECT 'Migração de segurança v2 aplicada.' AS resultado;
