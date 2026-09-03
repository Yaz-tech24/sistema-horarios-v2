-- ============================================================
-- Migração: aulas só de Segunda a Sexta (remove Sábado)
--
-- Instalação de raiz a partir de schema.sql já fica sem Sábado — não
-- precisa de correr isto. Só para quem já tinha a base de dados criada
-- antes desta alteração.
--
-- 1) Remove os blocos de Estudo Autónomo que a geração automática tinha
--    colocado ao Sábado (não apaga nenhuma Aula real: se por acaso
--    existir uma Aula de verdade marcada ao Sábado, o DELETE abaixo não
--    a atinge — só tipo_bloco='Estudo_Autonomo' — e o ALTER TABLE final
--    falha de propósito para não perder dados sem se dar por isso).
-- 2) Estreita o ENUM de dia_semana para não aceitar mais 'Sabado'.
-- ============================================================

USE horarios_fagrenm;

DELETE FROM aulas WHERE dia_semana = 'Sabado' AND tipo_bloco = 'Estudo_Autonomo';

ALTER TABLE disponibilidades
  MODIFY dia_semana ENUM('Segunda','Terca','Quarta','Quinta','Sexta') NOT NULL;

ALTER TABLE aulas
  MODIFY dia_semana ENUM('Segunda','Terca','Quarta','Quinta','Sexta') NOT NULL;
