-- ============================================================
-- Migração: limpeza de dados dos blocos de horário
--
-- Só para bases de dados que já existiam — numa instalação nova não há
-- nada para limpar. Não apaga nenhuma aula. Seguro correr mais do que uma
-- vez (da segunda vez não altera nada).
--
-- Como correr:
--   mysql --default-character-set=utf8mb4 -u root -p horarios_fagrenm < sql/migracao_limpeza_blocos.sql
-- (em Docker, sql/atualizar_producao_vps.sql já inclui estes passos)
--
-- 1) Blocos de Estudo Autónomo / Atividade Não Letiva com disciplina,
--    docente ou sala "fantasma". O editor escondia esses campos para estes
--    tipos de bloco mas enviava-os na mesma com o último valor escolhido,
--    e ficavam gravados: o docente via o Estudo Autónomo de outra turma no
--    "Meu horário" como se fosse uma aula sua, e a sala aparecia ocupada
--    em "Salas livres". O editor já não faz isto; aqui limpa-se o que ficou.
-- 2) Subgrupos (RN07) gravados com espaços ou minúsculas ("p1 ", "a"):
--    passam ao formato que o sistema usa agora ("P1", "A"), para "a" e "A"
--    serem reconhecidos como o mesmo subgrupo em todas as páginas.
-- ============================================================

UPDATE aulas
SET disciplina_id = NULL, docente_id = NULL, docente_regente_id = NULL,
    docente_assistente_id = NULL, sala_id = NULL, subgrupo = NULL
WHERE tipo_bloco <> 'Aula'
  AND (disciplina_id IS NOT NULL OR docente_id IS NOT NULL OR docente_regente_id IS NOT NULL
       OR docente_assistente_id IS NOT NULL OR sala_id IS NOT NULL OR subgrupo IS NOT NULL);

UPDATE aulas
SET subgrupo = NULLIF(UPPER(TRIM(subgrupo)), '')
WHERE subgrupo IS NOT NULL
  AND (TRIM(subgrupo) = '' OR CAST(subgrupo AS BINARY) <> CAST(UPPER(TRIM(subgrupo)) AS BINARY));

SELECT 'Limpeza dos blocos aplicada. Abre Admin -> Conflitos e usa "Revalidar todos os horarios".' AS resultado;
