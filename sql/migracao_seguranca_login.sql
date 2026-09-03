-- ============================================================
-- Migração: bloqueio temporário de login por força bruta
-- (ver auth/login.php).
--
-- Só ALTER TABLE — seguro para correr contra uma base de dados local já
-- em uso que ainda não tenha estas colunas. Quem instalar de raiz a
-- partir de schema.sql já as tem e NÃO precisa de correr este ficheiro.
-- ============================================================

USE horarios_fagrenm;

ALTER TABLE utilizadores
  ADD COLUMN tentativas_falhadas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN bloqueado_ate DATETIME NULL;
