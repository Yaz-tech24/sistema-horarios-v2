# Sistema de Gestão de Horários — FAGRENM/UCM

Projeto do grupo — PHP + MySQL.

## Configuração local
1. Importar `sql/schema.sql` no phpMyAdmin.
2. Importar `sql/seed.sql` no phpMyAdmin.
3. Login de teste: admin@fagrenm.test / admin123
4. Ver o "Guia de Configuração do Ambiente e Trabalho em Equipa" para o passo a passo completo.

## Divisão de tarefas
- Yazdan — Base de dados, autenticação, layout, integração
- Anancintia — admin/ (Cursos, Disciplinas, Docentes, Salas, Turmas)
- Eliana — coordenador/editor_horario.php, coordenador/conflitos.php, includes/funcoes_conflitos.php
- Darleny — coordenador/escolher_curso.php, coordenador/publicar.php, coordenador/historico.php
- Amélia — docente/, relatorios/
