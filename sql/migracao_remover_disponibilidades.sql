-- ============================================================
-- Migração: remove a disponibilidade semanal dos docentes (RN05)
-- e acrescenta índices de desempenho ao motor de conflitos.
--
-- Porquê remover: a disponibilidade só podia ser preenchida pelo
-- Administrador, docente a docente, bloco a bloco. Na prática ficava
-- por preencher ou desatualizada, e a RN05 era só um aviso não
-- bloqueante — ou seja, custava trabalho real e não impedia nada.
-- Os conflitos que interessam (docente em dois sítios, sala ocupada,
-- turma sobreposta) continuam todos, e esses são bloqueantes.
--
-- Os índices: verificarConflitos() procura sempre aulas pelo mesmo
-- trio (dia_semana, hora_inicio, hora_fim) e revalidarHorario() repete
-- essa procura uma vez por cada aula do horário. Sem índice, cada
-- verificação percorre a tabela `aulas` inteira — com poucas aulas não
-- se nota, mas o custo cresce ao quadrado à medida que a faculdade
-- enche o sistema.
-- ============================================================

USE horarios_fagrenm;

-- 1) Conflitos do tipo 'Disponibilidade' deixam de existir. Apagam-se
--    primeiro para o ENUM poder ser reduzido sem perder linhas válidas.
DELETE FROM conflitos WHERE tipo = 'Disponibilidade';

ALTER TABLE conflitos
  MODIFY tipo ENUM('Docente','Sala','Turma','Capacidade','Pastoral','Carga') NOT NULL;

-- 2) A tabela da disponibilidade deixa de ser usada por qualquer página.
DROP TABLE IF EXISTS disponibilidades;

-- 3) Índices para a deteção de conflitos (RN01-RN03) e para os
--    relatórios de ocupação/carga.
ALTER TABLE aulas
  ADD INDEX idx_aulas_slot (dia_semana, hora_inicio, hora_fim),
  ADD INDEX idx_aulas_sala (sala_id),
  ADD INDEX idx_aulas_regente (docente_regente_id),
  ADD INDEX idx_aulas_horario (horario_id);

ALTER TABLE conflitos
  ADD INDEX idx_conflitos_estado (estado);
