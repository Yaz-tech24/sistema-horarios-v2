-- ============================================================
-- Migração: canal de retorno do docente (tabela `pedidos`)
--
-- Até aqui as notificações só iam num sentido: o sistema avisava o
-- docente e o docente não tinha forma de responder dentro do sistema.
-- Esta tabela guarda os pedidos/observações que o docente envia ao
-- coordenador do curso sobre uma aula concreta do seu horário.
--
-- `referencia` guarda em texto a aula a que o pedido dizia respeito
-- (ex.: "Segunda 07:00–08:40 · Programação Móvel"), para o pedido
-- continuar a fazer sentido mesmo depois de essa aula ser removida —
-- nesse caso aula_id passa a NULL e o texto mantém-se.
-- ============================================================

USE horarios_fagrenm;

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
