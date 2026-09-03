# Sistema de Gestão de Horários — FAGRENM/UCM

Sistema completo em PHP + MySQL — todos os módulos funcionais.

## Configuração local
1. Importar `sql/schema.sql` no phpMyAdmin.
2. Importar `sql/seed.sql`.
3. Importar `sql/seed2_contas_teste.sql` (contas de teste + dados de exemplo).

Depois de configurada a base de dados, corre `iniciar_sistema.bat` (raiz do
projeto) para ligar o Apache/MySQL do XAMPP e abrir o sistema no browser
automaticamente.

Instalação de raiz (os 3 passos acima, numa base de dados nova) já fica com
tudo o que os ficheiros de migração abaixo adicionam — **não** precisas de
os correr. Eles só existem para quem já tinha a base de dados criada antes
dessas alterações:
- `sql/migracao_regente_assistente.sql` — adiciona regente/assistente por
  disciplina/aula, RN08 (Pastoral) e RN09 (limite 2x/semana).
- `sql/migracao_seguranca_login.sql` — adiciona o bloqueio temporário de
  conta por tentativas de login falhadas (ver "Segurança" abaixo).
- `sql/migracao_remover_sabado.sql` — restringe os dias letivos a
  Segunda–Sexta (remove Sábado do ENUM `dia_semana`).

## Contas de teste
| Perfil        | E-mail                     | Password    |
|---------------|----------------------------|-------------|
| Administrador | admin@fagrenm.test         | admin123    |
| Coordenador   | coordenador@fagrenm.test   | coord123    |
| Docente       | docente@fagrenm.test       | docente123  |

O coordenador de teste gere apenas **IT e GA** — é assim que se testa o
controlo de acesso por curso (não consegue entrar em Direito, AP, etc.).

## Como funciona o controlo de acesso por curso
- A tabela `coordenador_curso` diz que cursos cada coordenador gere.
- O Administrador define isso em **Utilizadores e Permissões**.
- `includes/funcoes_coordenador.php` → `exigirCursoDoCoordenador()` valida
  em TODAS as páginas do coordenador que o curso em sessão lhe pertence;
  se não pertencer, é reenviado para a escolha de curso.

## Convenções (obrigatórias para todos)
- `require_once` + `__DIR__` em todos os includes.
- `BASE_URL` (definida em `config/db.php`) em todos os links e redirects.
- Queries sempre com PDO preparado — nunca concatenar input do utilizador.
- Todo o `<form method="post">` que altera estado leva `<?= campoCSRF() ?>`
  (dentro do `<form>`) e o handler correspondente chama `validarCSRF()`
  como primeira linha do bloco POST — ver `includes/csrf.php`. Ações
  destrutivas (eliminar, ativar/desativar, publicar, arquivar) são sempre
  POST, nunca GET.
- Depois de um POST que grava/elimina com sucesso, faz sempre
  `definirFlash('ok'|'erro', '...')` + `header('Location: ...'); exit;`
  (padrão Post-Redirect-Get) — nunca voltar a renderizar a mesma página
  com o mesmo POST, para um F5 não reenviar o formulário. Ver
  `includes/flash.php`.
- Design: usar as classes de `assets/css/estilo.css`
  (`.cartao`, `.tabela` dentro de `.tabela-scroll`, `.btn`, `.msg--erro`,
  `.msg--ok`, `.campo`, `.form-linha`, `.pagina-titulo`, `.regua`,
  `.link-acao` para ações em forma de link dentro de tabelas).

## Segurança
- CSRF: token por sessão (`includes/csrf.php`), obrigatório em todos os POST.
- Cookie de sessão com `HttpOnly` + `SameSite=Lax` + `Secure` automático em
  HTTPS (`includes/auth.php`).
- Login com bloqueio temporário (5 tentativas falhadas → 5 minutos) —
  colunas `tentativas_falhadas`/`bloqueado_ate` em `utilizadores`.

## Módulos
- **Admin**: cursos, disciplinas, docentes (+disciplinas que leciona),
  salas, turmas, utilizadores/permissões.
- **Coordenador**: escolher curso (só os seus) → editor de horário em grelha
  com verificação de conflitos (RN01-RN05) → conflitos/revalidação →
  publicação (RN15: zero conflitos) → histórico.
- **Docente**: horário agregado de todos os cursos + notificações.
- **Relatórios**: ocupação de salas, carga docente, exportação imprimível.

## Divisão de tarefas original
- Yazdan — BD, autenticação, layout, design, painel, utilizadores, integração
- Anancintia — admin/
- Eliana — editor_horario, conflitos, funcoes_conflitos
- Darleny — escolher_curso, publicar, historico
- Amélia — docente/, relatorios/, funcoes_notificacoes
