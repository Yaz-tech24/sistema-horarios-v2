USE horarios_fagrenm;
SET NAMES utf8mb4;

-- Utilizador Administrador de teste
-- E-mail: admin@fagrenm.test | Password: admin123
INSERT INTO utilizadores (nome, email, password_hash, perfil) VALUES
('Administrador Teste', 'admin@fagrenm.test',
 '$2y$10$dDk7H9KgE022NjXFiEU2Iu7p1ZNdCC5RVZyonuYQxskOgkOLB3r8e', 'Administrador');

-- Cursos reais da FAGRENM
INSERT INTO cursos (nome, sigla) VALUES
('Tecnologias de Informação', 'IT'),
('Gestão Ambiental', 'GA'),
('Gestão de Recursos Humanos', 'GRH'),
('Administração Pública', 'AP'),
('Economia e Gestão', 'EG'),
('Direito', 'DIR');

-- Salas de teste
INSERT INTO salas (nome, tipo, capacidade) VALUES
('Sala 5', 'Normal', 45),
('Laboratório de Informática', 'Laboratorio', 30),
('Santa Bakita', 'Normal', 50),
('Santo Pedro', 'Normal', 50);
