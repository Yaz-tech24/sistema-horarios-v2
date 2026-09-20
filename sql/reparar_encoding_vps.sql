-- ============================================================
-- Repara nomes com acentos corrompidos por uma importação anterior de
-- sql/seed3_dados_reais.sql sem --default-character-set=utf8mb4 (ex.:
-- "Administração" gravado como "AdministraÃ§Ã£o").
--
-- Reverte exatamente essa corrupção — os bytes UTF-8 corretos foram
-- interpretados como Latin-1 e gravados de novo como utf8mb4; isto
-- desfaz esse passo. Só toca nas linhas que têm mesmo o padrão de
-- corrupção (WHERE ... LIKE '%Ã%') — linhas já corretas não mudam.
--
-- Seguro correr mais do que uma vez: depois de reparada, uma linha
-- deixa de ter 'Ã' e a segunda corrida não lhe mexe.
-- ============================================================

-- IMPORTANTE: "LIKE BINARY", nunca "LIKE" sozinho. A colação por omissão
-- do utf8mb4 trata letras acentuadas como equivalentes entre si na
-- comparação normal (ex.: 'ó' combina com 'Ã') — um "LIKE '%Ã%'" sem
-- BINARY apanhava também nomes já corretos ("Laboratório", "Mário",
-- "Ética") e destruía-os. Testado: com BINARY, só toca mesmo nas linhas
-- corrompidas; sem BINARY, corrompia as boas. Ver histórico do commit.
UPDATE salas
SET nome = CONVERT(CAST(CONVERT(nome USING latin1) AS BINARY) USING utf8mb4)
WHERE nome LIKE BINARY '%Ã%';

UPDATE docentes
SET nome = CONVERT(CAST(CONVERT(nome USING latin1) AS BINARY) USING utf8mb4)
WHERE nome LIKE BINARY '%Ã%';

UPDATE docentes
SET categoria = CONVERT(CAST(CONVERT(categoria USING latin1) AS BINARY) USING utf8mb4)
WHERE categoria LIKE BINARY '%Ã%';

UPDATE disciplinas
SET nome = CONVERT(CAST(CONVERT(nome USING latin1) AS BINARY) USING utf8mb4)
WHERE nome LIKE BINARY '%Ã%';

SELECT 'Reparação concluída.' AS resultado,
       (SELECT COUNT(*) FROM salas WHERE nome LIKE BINARY '%Ã%') AS salas_ainda_corrompidas,
       (SELECT COUNT(*) FROM docentes WHERE nome LIKE BINARY '%Ã%' OR categoria LIKE BINARY '%Ã%') AS docentes_ainda_corrompidos,
       (SELECT COUNT(*) FROM disciplinas WHERE nome LIKE BINARY '%Ã%') AS disciplinas_ainda_corrompidas;
