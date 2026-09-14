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
- `sql/migracao_remover_disponibilidades.sql` — remove a disponibilidade
  semanal dos docentes (e a RN05) e acrescenta índices ao motor de conflitos.
- `sql/migracao_pedidos.sql` — cria a tabela `pedidos` (canal de retorno do
  docente para o coordenador).
- `sql/migracao_sala_disciplina.sql` — adiciona `disciplinas.sala_id`
  (laboratório de disciplinas Prática/Laboratorial).

## Hospedagem em produção (VPS)

Testado para uma VPS Ubuntu com Apache — ex.: Hostinger VPS. Os passos:

**1. Servidor** (Ubuntu 22.04+, como root ou via `sudo`):
```bash
apt update && apt install -y apache2 php php-mysql php-mbstring mysql-server certbot python3-certbot-apache
a2enmod headers rewrite
systemctl enable --now apache2 mysql
```

**2. Base de dados:**
```bash
mysql -u root -p
```
```sql
CREATE DATABASE horarios_fagrenm CHARACTER SET utf8mb4;
CREATE USER 'horarios_app'@'localhost' IDENTIFIED BY 'uma-password-forte-aqui';
GRANT ALL PRIVILEGES ON horarios_fagrenm.* TO 'horarios_app'@'localhost';
FLUSH PRIVILEGES;
```
Depois importar (pela ordem): `sql/schema.sql`, `sql/seed.sql`. **Não** importar
`sql/seed2_contas_teste.sql` em produção — são contas de demonstração com
passwords públicas (ver README).

**3. Código:**
```bash
cd /var/www
git clone https://github.com/Yaz-tech24/sistema-horarios-v2.git horarios
cd horarios
cp .env.example .env
nano .env   # preencher DB_USER/DB_PASS (os de cima) e deixar BASE_URL vazio
chown -R www-data:www-data /var/www/horarios
```

**4. Apache — VirtualHost** (`/etc/apache2/sites-available/horarios.conf`):
```apache
<VirtualHost *:80>
    ServerName horarios.o-teu-dominio.mz
    DocumentRoot /var/www/horarios
    <Directory /var/www/horarios>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/horarios_error.log
</VirtualHost>
```
```bash
a2ensite horarios.conf && systemctl reload apache2
certbot --apache -d horarios.o-teu-dominio.mz   # HTTPS automático (Let's Encrypt)
```

O domínio (`horarios.o-teu-dominio.mz` acima) tem de já apontar para o IP da
VPS (registo DNS tipo A) antes de correr o `certbot`.

**5. Confirmar:** abrir `https://horarios.o-teu-dominio.mz/auth/login.php` —
com `.env` presente, `config/db.php` liga à base de dados de produção e
`display_errors` fica desligado automaticamente (`APP_ENV=producao`).

O `.htaccess` da raiz e de `sql/` já bloqueiam o acesso direto a `.sql`,
`.env`, `.md`, `.bat`, `.git` e listagem de pastas — não precisas de mais
nada aí.

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
- O mesmo vale para as **disciplinas**: em `coordenador/disciplinas.php` o
  `curso_id` vem sempre da sessão (nunca do formulário) e qualquer edição ou
  eliminação confirma antes que a disciplina é mesmo do curso em sessão — um
  coordenador não consegue tocar nas disciplinas de um curso que não gere,
  mesmo forjando o pedido. O Administrador continua a ver e a gerir as de
  todos os cursos em **Admin → Disciplinas**.

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
- Palavra-passe: cada utilizador muda a sua em **A minha conta** (`conta.php`),
  confirmando sempre a atual. O Administrador pode definir a de qualquer conta
  em **Utilizadores** (campo vazio = manter a atual). Mínimo 6 caracteres nos
  dois caminhos.

## Módulos
- **Admin**: cursos, disciplinas, docentes (+disciplinas que leciona),
  **conflitos de toda a faculdade** (leitura, agrupados por curso, com
  revalidação global),
  salas, turmas, utilizadores/permissões.
- **Coordenador**: escolher curso (só os seus) → disciplinas do curso
  (criar/editar, incluindo quem é o docente regente e o assistente) → editor
  de horário em grelha com verificação de conflitos (RN01-RN04) →
  conflitos/revalidação → publicação (RN15: zero conflitos) → histórico,
  mais os **pedidos dos docentes** sobre os horários do curso.
- **Docente**: horário agregado de todos os cursos, notificações e envio de
  pedidos ao coordenador sobre uma aula concreta.
- **Todos os perfis**: **A minha conta** — dados de acesso e alteração da
  própria palavra-passe.
- **Relatórios**: ocupação de salas, **salas livres** (grelha semanal de que
  sala está livre em cada bloco), carga docente, exportação imprimível.

### RN16 — laboratórios das disciplinas práticas
Uma disciplina com `tipo_aula` **Prática** ou **Laboratorial** pode ter um
laboratório fixo (`disciplinas.sala_id`, definido em **Admin → Disciplinas**).
Quando tem:
- a geração automática coloca-a sempre nesse laboratório, em vez de escolher
  sala pela capacidade;
- o editor manual **força** essa sala no servidor — o campo Sala fica travado
  e o valor que vier do POST é ignorado (`coordenador/editor_horario.php`);
- a RN02 (sala ocupada) continua a valer e olha para **todos os cursos e
  horários não arquivados**, por isso o mesmo laboratório nunca fica com duas
  aulas à mesma hora, seja de que curso for;
- se o laboratório estiver ocupado em todos os blocos livres da turma, a
  disciplina fica por agendar e o resumo da geração diz porquê.

### RN07 — subgrupos
Uma aula pode ter um **subgrupo** (campo livre no editor, ex.: `T1`, `P2`).
Duas aulas da mesma turma à mesma hora só são conflito quando têm o *mesmo*
subgrupo — é isto que permite dividir a turma em turnos práticos. O subgrupo
aparece entre parênteses na grelha do editor, no horário do docente, na folha
impressa e na coluna própria do CSV.

### RN05 — removida
A disponibilidade semanal dos docentes deixou de existir (tabela
`disponibilidades`, grelha em Admin → Docentes e o aviso correspondente).
Só o Administrador a podia preencher, docente a docente e bloco a bloco, e o
aviso que gerava não bloqueava nada — custava trabalho real sem impedir
erros. Os conflitos que interessam (docente em dois sítios, sala ocupada,
turma sobreposta) continuam todos, e esses são bloqueantes. Ver
`sql/migracao_remover_disponibilidades.sql`.

## Divisão de tarefas original
- Yazdan — BD, autenticação, layout, design, painel, utilizadores, integração
- Anancintia — admin/
- Eliana — editor_horario, conflitos, funcoes_conflitos
- Darleny — escolher_curso, publicar, historico
- Amélia — docente/, relatorios/, funcoes_notificacoes
