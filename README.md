# Sistema de Gestão de Horários — FAGRENM/UCM

Projeto do grupo — PHP + MySQL.

## Configuração local
1. Importar `sql/schema.sql` no phpMyAdmin.
2. Importar `sql/seed.sql` no phpMyAdmin.
3. Login de teste: **admin@fagrenm.test** / **admin123**
4. Ver o "Guia de Configuração do Ambiente e Trabalho em Equipa" para o passo a passo completo.

## Importante para todos os membros
- **BASE_URL** está definida em `config/db.php`. Usar sempre `BASE_URL` nos links
  e redirects (ex.: `header('Location: ' . BASE_URL . '/painel.php');`) —
  nunca caminhos absolutos tipo `/admin/...`, que quebram por o projeto viver
  numa subpasta do htdocs.
- Nos includes, usar sempre **`require_once`** com `__DIR__`
  (ex.: `require_once __DIR__ . '/../includes/auth.php';`) — o `_once` evita
  que o mesmo ficheiro corra duas vezes (o que causa avisos tipo
  "Constant BASE_URL already defined").
- Depois do login, todos os perfis vão para **`painel.php`** (página de boas-vindas
  com os módulos do perfil). Cada módulo que ainda não existe aparece como
  "Em desenvolvimento" — desaparece automaticamente assim que criarem o ficheiro.
- O design (azul/branco/dourado) está em `assets/css/estilo.css`. Usar as classes
  existentes (`.cartao`, `.tabela`, `.btn`, `.msg--erro`, `.msg--ok`, `.campo`,
  `.pagina-titulo`, `.regua`) em vez de criar estilos novos por página.

## Divisão de tarefas
- Yazdan — Base de dados, autenticação, layout, design, painel, integração
- Anancintia — `admin/` (Cursos, Disciplinas, Docentes, Salas, Turmas)
- Eliana — `coordenador/editor_horario.php`, `coordenador/conflitos.php`, `includes/funcoes_conflitos.php`
- Darleny — `coordenador/escolher_curso.php`, `coordenador/publicar.php`, `coordenador/historico.php`
- Amélia — `docente/`, `relatorios/`
