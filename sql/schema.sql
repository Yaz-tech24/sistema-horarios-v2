CREATE DATABASE IF NOT EXISTS horarios_fagrenm CHARACTER SET utf8mb4;
SET NAMES utf8mb4;
USE horarios_fagrenm;

-- Cursos da faculdade
CREATE TABLE cursos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  sigla VARCHAR(10) NOT NULL UNIQUE
);

-- Salas onde as aulas acontecem
CREATE TABLE salas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  tipo ENUM('Normal','Laboratorio','Auditorio') DEFAULT 'Normal',
  capacidade INT NOT NULL,
  equipamento VARCHAR(255)
);

-- Contas de acesso ao sistema
CREATE TABLE utilizadores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  perfil ENUM('Administrador','Coordenador','Docente') NOT NULL,
  ativo TINYINT(1) DEFAULT 1,
  -- Bloqueio temporário por força bruta no login (ver auth/login.php).
  tentativas_falhadas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate DATETIME NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Docentes (podem ou nao ter conta de utilizador)
-- Precisa existir antes de "disciplinas", que agora referencia
-- docente_regente_id / docente_assistente_id.
CREATE TABLE docentes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id INT NULL,
  nome VARCHAR(150) NOT NULL,
  categoria VARCHAR(50),
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id)
);

-- Disciplinas de cada curso (precisa existir antes de docente_disciplina)
-- carga_horaria = nº de vezes por semana que a disciplina é lecionada
-- (sempre 1 ou 2 — RN09, nunca mais de 2x/semana na mesma turma).
-- docente_regente_id é o docente responsável pela disciplina (obrigatório
-- na validação do formulário; a coluna aceite NULL só para não partir
-- disciplinas antigas antes de serem editadas). docente_assistente_id é
-- sempre opcional.
CREATE TABLE disciplinas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL,
  codigo VARCHAR(20),
  nome VARCHAR(150) NOT NULL,
  ano_curricular TINYINT NOT NULL,
  carga_horaria INT DEFAULT 2,
  tipo_aula ENUM('Teorica','Pratica','Laboratorial') DEFAULT 'Teorica',
  -- Laboratório desta disciplina — só faz sentido quando tipo_aula não é
  -- 'Teorica' (ver admin/disciplinas.php). Usado como sugestão/preferência
  -- na geração automática e no editor manual, não é obrigatório.
  sala_id INT NULL,
  docente_regente_id INT NULL,
  docente_assistente_id INT NULL,
  FOREIGN KEY (curso_id) REFERENCES cursos(id),
  FOREIGN KEY (sala_id) REFERENCES salas(id),
  FOREIGN KEY (docente_regente_id) REFERENCES docentes(id),
  FOREIGN KEY (docente_assistente_id) REFERENCES docentes(id)
);

-- Que disciplinas cada docente pode lecionar
CREATE TABLE docente_disciplina (
  docente_id INT NOT NULL,
  disciplina_id INT NOT NULL,
  PRIMARY KEY (docente_id, disciplina_id),
  FOREIGN KEY (docente_id) REFERENCES docentes(id),
  FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id)
);

-- Turmas: curso + ano + regime + turno
CREATE TABLE turmas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL,
  ano_curricular TINYINT NOT NULL,
  regime ENUM('Laboral','Pos-Laboral') NOT NULL,
  turno ENUM('Manha','Tarde','Noite') NOT NULL,
  nome_turma VARCHAR(10) DEFAULT 'A',
  num_alunos INT DEFAULT 0,
  -- Sala habitual desta turma (aulas teóricas) — sugestão usada pela
  -- geração automática quando a disciplina não tem laboratório próprio
  -- (RN16 continua a ter prioridade). Nunca obrigatório.
  sala_padrao_id INT NULL,
  FOREIGN KEY (curso_id) REFERENCES cursos(id),
  FOREIGN KEY (sala_padrao_id) REFERENCES salas(id)
);

-- Um horario = o "documento" com estado, ligado a uma turma
CREATE TABLE horarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  turma_id INT NOT NULL,
  estado ENUM('Rascunho','Em_Revisao','Publicado','Arquivado') DEFAULT 'Rascunho',
  data_publicacao DATETIME NULL,
  FOREIGN KEY (turma_id) REFERENCES turmas(id)
);

-- Cada aula/bloco dentro de um horario
-- docente_id mantém-se (compatibilidade com relatórios/consultas antigas) e
-- é sempre espelhado a partir de docente_regente_id — ver comentário em
-- includes/funcoes_coordenador.php sobre esta decisão. docente_assistente_id
-- é novo e sempre opcional (RN01 verifica sobreposição para os dois).
CREATE TABLE aulas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  horario_id INT NOT NULL,
  disciplina_id INT NULL,
  docente_id INT NULL,
  docente_regente_id INT NULL,
  docente_assistente_id INT NULL,
  sala_id INT NULL,
  tipo_bloco ENUM('Aula','Estudo_Autonomo','Atividade_Nao_Letiva') DEFAULT 'Aula',
  subgrupo VARCHAR(10) NULL,
  dia_semana ENUM('Segunda','Terca','Quarta','Quinta','Sexta') NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fim TIME NOT NULL,
  FOREIGN KEY (horario_id) REFERENCES horarios(id),
  FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id),
  FOREIGN KEY (docente_id) REFERENCES docentes(id),
  FOREIGN KEY (docente_regente_id) REFERENCES docentes(id),
  FOREIGN KEY (docente_assistente_id) REFERENCES docentes(id),
  FOREIGN KEY (sala_id) REFERENCES salas(id),
  -- Índices para a deteção de conflitos (procura sempre pelo trio
  -- dia/hora, uma vez por cada aula do horário) e para os relatórios.
  INDEX idx_aulas_slot (dia_semana, hora_inicio, hora_fim),
  INDEX idx_aulas_sala (sala_id),
  INDEX idx_aulas_regente (docente_regente_id),
  INDEX idx_aulas_horario (horario_id)
);

-- Conflitos detetados entre pares de aulas
-- 'Pastoral' = RN08 (bloco fixo de Quarta substituído/ocupado indevidamente)
-- 'Carga'    = RN09 (disciplina agendada mais do que carga_horaria permite,
--              ou repetida no mesmo dia)
CREATE TABLE conflitos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aula_id_1 INT NOT NULL,
  aula_id_2 INT NOT NULL,
  tipo ENUM('Docente','Sala','Turma','Capacidade','Pastoral','Carga') NOT NULL,
  -- Frase completa gerada por verificarConflitos() no momento em que o
  -- conflito foi detetado (nomes de docente/sala, dia/hora, papel de
  -- cada um) — sem isto, as páginas de Conflitos só tinham disc1/disc2/
  -- dia/hora genéricos, bem menos claros do que o aviso que já aparecia
  -- no editor ao tentar guardar a aula.
  mensagem VARCHAR(500) NULL,
  estado ENUM('Pendente','Resolvido') DEFAULT 'Pendente',
  detetado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aula_id_1) REFERENCES aulas(id),
  FOREIGN KEY (aula_id_2) REFERENCES aulas(id),
  INDEX idx_conflitos_estado (estado)
);

-- Historico de alteracoes a um horario (auditoria)
CREATE TABLE historico_versoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  horario_id INT NOT NULL,
  utilizador_id INT NOT NULL,
  descricao VARCHAR(255),
  data_alteracao DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (horario_id) REFERENCES horarios(id),
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id)
);

-- Notificacoes enviadas aos docentes
CREATE TABLE notificacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id INT NOT NULL,
  aula_id INT NULL,
  mensagem VARCHAR(255) NOT NULL,
  lida TINYINT(1) DEFAULT 0,
  data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id),
  FOREIGN KEY (aula_id) REFERENCES aulas(id)
);

-- Pedidos/observações que o docente envia ao coordenador sobre o seu
-- horário (ex.: "esta aula choca com outra atividade minha"). É o único
-- canal de retorno do docente — as notificações só vão no sentido
-- contrário. `referencia` guarda em texto a aula a que o pedido dizia
-- respeito, para continuar legível mesmo que essa aula venha a ser
-- removida (nesse caso aula_id fica NULL).
CREATE TABLE pedidos (
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

-- Que coordenador gere qual curso
CREATE TABLE coordenador_curso (
  utilizador_id INT NOT NULL,
  curso_id INT NOT NULL,
  PRIMARY KEY (utilizador_id, curso_id),
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id),
  FOREIGN KEY (curso_id) REFERENCES cursos(id)
);
