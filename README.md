# Sistema de Gestão de Horários — FAGRENM/UCM

Sistema completo em PHP + MySQL — todos os módulos funcionais.

## Configuração local
1. Importar `sql/schema.sql` no phpMyAdmin.
2. Importar `sql/seed.sql`.
3. Importar `sql/seed2_contas_teste.sql` (contas de teste + dados de exemplo).
4. Importar `sql/seed3_dados_reais.sql` (cursos, salas, turmas, disciplinas e
   docentes reais da faculdade — ver "Dados reais" abaixo).

Se importares pela linha de comandos em vez do phpMyAdmin, usa sempre
`--default-character-set=utf8mb4`:
```bash
mysql --default-character-set=utf8mb4 -u root -p horarios_fagrenm < sql/schema.sql
```
Sem isto, o cliente `mysql` troca o charset por omissão da ligação e todos os
nomes com acentos ficam corrompidos ao gravar (ex.: "Ética" vira "├ëtica") —
o phpMyAdmin já trata disto sozinho, só a linha de comandos precisa da flag.

Depois de configurada a base de dados, corre `iniciar_sistema.bat` (raiz do
projeto) para ligar o Apache/MySQL do XAMPP e abrir o sistema no browser
automaticamente.

Instalação de raiz (os 4 passos acima, numa base de dados nova) já fica com
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
- `sql/migracao_sala_padrao_turma.sql` — adiciona `turmas.sala_padrao_id`
  (sala habitual da turma, ver "Dados reais" abaixo).
- `sql/migracao_seguranca_v2.sql` — mudança obrigatória de palavra-passe no
  primeiro acesso, data da última entrada, registo de atividade e links de
  recuperação de palavra-passe (ver "Segurança" abaixo). **É a única que
  falta a quem já tinha o sistema a funcionar antes desta versão.** Sem ela
  o sistema continua a abrir e a funcionar, mas essas quatro coisas ficam
  desligadas (e o motivo fica escrito no log de erros do servidor).
- `sql/migracao_limpeza_blocos.sql` — limpa dados gravados por versões
  anteriores do editor: blocos de Estudo Autónomo/Atividade Não Letiva com
  docente ou sala "fantasma" (apareciam no horário do docente e ocupavam a
  sala em "Salas livres") e subgrupos com minúsculas/espaços. Não apaga
  aulas. Depois de a correr, usa **Admin → Conflitos → Revalidar todos os
  horários** (as regras de subgrupos abaixo podem mostrar conflitos que
  antes passavam despercebidos).

Requisitos: PHP 8.0 ou superior (com `pdo_mysql`, `mbstring` e `openssl`) e
MySQL 5.7+/MariaDB 10.4+. O XAMPP atual já cumpre tudo.

## Dados reais
`sql/seed3_dados_reais.sql` povoa o sistema com os cursos, salas, turmas,
disciplinas e docentes reais da faculdade (Tecnologias de Informação,
Contabilidade e Auditoria, Gestão de Recursos Humanos, Direito, Economia e
Gestão, Administração Pública, Gestão Ambiental). É idempotente — procura
tudo por nome antes de inserir, corrê-lo mais do que uma vez não duplica
nada.

Duas notas sobre a proveniência dos dados:
- Ficaram de fora dois conjuntos que as próprias fichas originais indicavam
  como desatualizados: "Administração e Gestão Hospitalar" (2016) e uma
  ficha antiga de Gestão de Recursos Humanos (2016). O 2º ano de GRH também
  ficou sem disciplinas — a ficha fornecida não permitia lê-las com confiança.
- Nomes de sala e de docente foram normalizados quando eram claramente a
  mesma pessoa/sala escrita de forma diferente em fichas de departamentos
  diferentes (ex.: "S. André"/"Sto. André"/"Santa André" → "Santo André").
  Capacidade das salas e número de alunos por turma não vinham nas fichas —
  ficaram com valores por omissão (50 lugares, 45 alunos), ajustáveis em
  **Admin → Salas** / **Admin → Turmas**.

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
Depois importar (pela ordem, sempre com `--default-character-set=utf8mb4` —
ver "Configuração local" acima): `sql/schema.sql`, `sql/seed.sql`,
`sql/seed3_dados_reais.sql`. **Não** importar `sql/seed2_contas_teste.sql`
em produção — são contas de demonstração com passwords públicas (ver
README).

`sql/seed.sql` cria o Administrador de origem (`admin@fagrenm.test` /
`admin123`). **Entra com ele logo a seguir à instalação**: o sistema obriga
a escolher uma palavra-passe nova nesse primeiro acesso, e só então a conta
deixa de ter uma palavra-passe que está escrita neste README. Depois, em
**A minha conta**, troca também o e-mail para um endereço real.

**3. Código:**
```bash
cd /var/www
git clone https://github.com/Yaz-tech24/sistema-horarios-v2.git horarios
cd horarios
cp .env.example .env
nano .env   # preencher DB_USER/DB_PASS (os de cima), APP_URL, e deixar BASE_URL vazio
# O código fica do root, só com leitura para o Apache — a aplicação nunca
# escreve em disco, por isso o servidor web não precisa de o poder alterar.
chown -R root:www-data /var/www/horarios
chmod -R u=rwX,g=rX,o= /var/www/horarios
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

O `.htaccess` da raiz já bloqueia o acesso direto a `.sql`, `.env`, `.md`,
`.bat`, `.yml`, `Dockerfile`, tudo o que comece por ponto (`.git`…) e a
listagem de pastas; `sql/`, `includes/` e `config/` têm cada uma o seu, a
negar tudo. Só funcionam com `AllowOverride All` (já está no VirtualHost
acima) — confirma abrindo `https://o-teu-dominio/sql/schema.sql` e
`https://o-teu-dominio/.env`: as duas têm de dar 403/404, nunca o ficheiro.

## Envio de email aos docentes (opcional)

Quando um coordenador publica um horário, além da notificação interna (que
já funciona sempre, dentro do próprio site), o sistema tenta enviar um
email a cada docente com conta e email cadastrados. Isto só acontece se o
SMTP estiver configurado no `.env` — sem isso, publicar continua a
funcionar normalmente, só sem o email. Preencher no `.env`:

```
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_USER=horarios@o-teu-dominio.mz
SMTP_PASS=a-palavra-passe-dessa-conta-de-email
SMTP_FROM=horarios@o-teu-dominio.mz
SMTP_FROM_NAME=Sistema de Horarios FAGRENM
```

Porta 465 usa TLS implícito (`SMTP_SECURE=ssl`, o valor por omissão); para
um servidor que só aceite STARTTLS (porta 587), definir `SMTP_SECURE=tls`.
Não é preciso nenhuma biblioteca — `includes/email.php` fala SMTP
diretamente por um socket, tal como o resto do projeto não usa Composer.
Uma falha de envio (credenciais erradas, servidor em baixo, etc.) fica só
registada no log de erros do servidor — nunca impede a publicação nem
aparece ao coordenador como erro.

## Atualizar a base de dados na VPS (Docker)

Se estiveres a correr com `docker-compose.yml` (`docker compose up -d
--build`) em vez do Apache direto: o serviço `db` só monta `sql/schema.sql`
e `sql/seed.sql` em `/docker-entrypoint-initdb.d`, e a imagem oficial do
MySQL só corre os ficheiros dessa pasta **uma vez**, quando o volume
`horarios_db_data` está vazio. Depois disso, nenhuma migração nem nenhum
seed novo corre sozinho — nem com `git pull` + `docker compose up --build`.
É preciso aplicá-los à mão contra o contentor já a correr:

```bash
# a partir da pasta do projeto na VPS, onde está o docker-compose.yml;
# carrega o .env para a shell primeiro, para $MYSQL_ROOT_PASSWORD e
# $DB_NAME ficarem definidos (docker compose lê o .env sozinho, mas só
# para preencher o docker-compose.yml — a shell não fica com as mesmas
# variáveis só por estares nesta pasta):
set -a; source .env; set +a

docker compose exec -T db mysql --default-character-set=utf8mb4 \
  -u root -p"$MYSQL_ROOT_PASSWORD" "$DB_NAME" < sql/atualizar_producao_vps.sql
docker compose exec -T db mysql --default-character-set=utf8mb4 \
  -u root -p"$MYSQL_ROOT_PASSWORD" "$DB_NAME" < sql/seed3_dados_reais.sql
```

**`--default-character-set=utf8mb4` nestes dois comandos não é opcional** —
sem ele, todos os nomes com acentos ficam gravados corrompidos (ex.:
"Administração" vira "AdministraÃ§Ã£o"). Se isso já te aconteceu, corre
`sql/reparar_encoding_vps.sql` (o mesmo `docker compose exec`, mas este
não precisa da flag porque não insere texto novo) — repara os nomes já
gravados sem duplicar nada nem tocar nos que já estavam corretos.

Depois de atualizar o código para esta versão, acrescenta também ao `.env`
da VPS `APP_URL=https://o-teu-dominio` (ver `.env.example`) e volta a subir
a aplicação com `docker compose up -d --build` — o `docker-compose.yml`
passou a definir `PROXY_CONFIAVEL=1`, que é o que põe a flag `Secure` no
cookie de sessão atrás do Traefik. Toda a gente terá de entrar outra vez
(o cookie de sessão mudou de nome).

`sql/atualizar_producao_vps.sql` é o equivalente a todos os
`sql/migracao_*.sql` juntos, escrito para correr contra o contentor sem
precisares de saber quais já foram aplicados — cada alteração confirma
primeiro que ainda não existe (idempotente, seguro correr mais do que
uma vez). `$MYSQL_ROOT_PASSWORD` e `$DB_NAME` já estão no `.env` da VPS.

## Contas de teste
| Perfil        | E-mail                     | Password    |
|---------------|----------------------------|-------------|
| Administrador | admin@fagrenm.test         | admin123    |
| Coordenador   | coordenador@fagrenm.test   | coord123    |
| Docente       | docente@fagrenm.test       | docente123  |

O Administrador nasce com palavra-passe provisória: no primeiro acesso o
sistema leva-te a **A minha conta** e só deixa continuar depois de definires
uma nova (mínimo 8 caracteres, e `admin123` já não é aceite). As contas de
Coordenador e Docente (`seed2`, só para desenvolvimento) entram diretamente —
a não ser numa base de dados antiga onde tenhas corrido
`sql/migracao_seguranca_v2.sql`, que também as marca como provisórias.

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
O que é comum a todas as páginas está em `includes/seguranca.php`.

**Sessão** (`includes/auth.php`)
- Cookie com `HttpOnly` + `SameSite=Lax` + `Secure` sempre que o site é
  servido por HTTPS — incluindo quando o HTTPS termina num proxy à frente
  (`PROXY_CONFIAVEL=1`, já definido no `docker-compose.yml`).
- A sessão é confirmada **a cada pedido** contra a base de dados: desativar
  uma conta, ou mudar-lhe a palavra-passe, termina as sessões abertas dessa
  conta no pedido seguinte; mudar o perfil também vale de imediato.
- Termina sozinha ao fim de 60 minutos sem atividade, e de 12 horas no
  total (constantes no topo de `includes/seguranca.php`).
- **Terminar sessão** é um POST com token CSRF, como qualquer outra ação.

**Entrada** (`auth/login.php`)
- Bloqueio por conta: 5 tentativas falhadas → 5 minutos.
- Limite por IP: 30 falhas em 15 minutos, para quem experimenta poucas
  palavras-passe em muitas contas.
- A resposta é igual quer o e-mail exista quer não (mesmas frases, mesmo
  bloqueio, e o mesmo trabalho de verificação antes de responder) — a
  página não serve para descobrir quem tem conta.
- Token CSRF também no formulário de login.

**Palavras-passe**
- Uma só regra (`validarPalavraPasse()`): mínimo 8 caracteres, recusa as
  mais comuns e as das contas de teste, e não pode ser igual ao e-mail.
- **Provisórias**: a do Administrador de origem e qualquer uma definida pelo
  Administrador em **Utilizadores** obrigam a pessoa a escolher a sua no
  primeiro acesso — até lá só chega a **A minha conta**.
- Cada utilizador muda a sua em **A minha conta**, confirmando sempre a
  atual (com limite de tentativas).
- **Esqueci-me da palavra-passe** (`auth/recuperar.php`): link por e-mail,
  válido 60 minutos e uma única vez. Só aparece com SMTP configurado **e**
  `APP_URL` definido no `.env` — o link nunca é construído a partir do
  endereço que vem no pedido.

**Pedidos e respostas**
- CSRF: token por sessão (`includes/csrf.php`), obrigatório em todos os POST.
- Cabeçalhos em todas as páginas: `Content-Security-Policy` (scripts só do
  próprio site ou com o *nonce* do pedido — os `<script>` inline levam
  `nonce="<?= nonceCSP() ?>"`, e não há `onclick=`/`onchange=` no HTML),
  `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy` e, em HTTPS, `Strict-Transport-Security`.
- Erros inesperados vão para o log do servidor; no browser aparece só uma
  frase neutra (em desenvolvimento local mostra-se o erro, para depurar).

**Registo de atividade** (**Admin → Registo de atividade**)
- Entradas e saídas, entradas falhadas (com o e-mail tentado e o IP),
  alterações de contas e de palavras-passe, publicações. Guardado um ano.
- O painel do Administrador avisa quando há contas de docente registadas à
  espera de ativação.

**Variáveis do `.env` ligadas a isto:** `APP_URL`, `PROXY_CONFIAVEL`,
`APP_TIMEZONE`, `PERMITIR_REGISTO` — explicadas no `.env.example`.

### Lista de verificação antes de pôr em produção
1. `.env` criado a partir do `.env.example`, com `APP_ENV=producao`, uma
   palavra-passe forte em `DB_PASS` e `APP_URL` preenchido.
2. `sql/seed2_contas_teste.sql` **não** importado.
3. Entrar com o Administrador de origem e definir a palavra-passe nova.
4. HTTPS ativo (certbot ou Traefik) e, se houver proxy, `PROXY_CONFIAVEL=1`.
5. `https://o-teu-dominio/.env` e `/sql/schema.sql` a dar 403/404.
6. Numa base de dados que já existia: correr `sql/migracao_seguranca_v2.sql`
   e `sql/migracao_limpeza_blocos.sql` (ou só `sql/atualizar_producao_vps.sql`,
   em Docker, que inclui as duas) e depois **Admin → Conflitos → Revalidar
   todos os horários**.

## Módulos
- **Admin**: cursos, disciplinas, docentes (+disciplinas que leciona),
  **conflitos de toda a faculdade** (leitura, agrupados por curso, com
  revalidação global),
  salas, turmas, utilizadores/permissões (com a data da última entrada de
  cada conta e os registos novos por ativar), **registo de atividade** e
  exportação de horários.
- **Coordenador**: escolher curso (só os seus) → disciplinas do curso
  (criar/editar, incluindo quem é o docente regente e o assistente) → editor
  de horário em grelha com verificação de conflitos (RN01-RN04) →
  conflitos/revalidação → publicação (RN15: zero conflitos) → histórico,
  mais os **pedidos dos docentes** sobre os horários do curso.
- **Docente**: horário agregado de todos os cursos, notificações e envio de
  pedidos ao coordenador sobre uma aula concreta.
- **Todos os perfis**: **A minha conta** — dados de acesso e alteração da
  própria palavra-passe e do e-mail; **Esqueci-me da palavra-passe** na
  página de entrada (quando o envio de e-mail está configurado).
- **Relatórios**: ocupação de salas, **salas livres** (grelha semanal de que
  sala está livre em cada bloco), carga docente, exportação imprimível.

### RN16 — laboratórios das disciplinas práticas
Uma disciplina com `tipo_aula` **Prática** ou **Laboratorial** pode ter um
laboratório preferido (`disciplinas.sala_id`, definido em **Admin →
Disciplinas**). Quando tem:
- a geração automática coloca-a sempre nesse laboratório, em vez de escolher
  sala pela capacidade;
- o editor manual **pré-preenche** essa sala assim que a disciplina é
  escolhida (é só sugestão — o coordenador pode trocar para qualquer outra
  sala manualmente, o valor do POST é respeitado tal como está);
- a RN02 (sala ocupada) continua a valer e olha para **todos os cursos e
  horários não arquivados**, por isso a mesma sala nunca fica com duas aulas
  à mesma hora, seja de que curso for — é isto, e não um bloqueio no campo,
  que impede duas aulas na mesma sala;
- se o laboratório estiver ocupado em todos os blocos livres da turma, a
  disciplina fica por agendar na geração automática e o resumo diz porquê.

### RN07 — subgrupos
Uma aula pode ter um **subgrupo** (campo livre no editor, ex.: `T1`, `P2`;
gravado sempre em maiúsculas — `p1` e `P1` são o mesmo). Aula sem subgrupo =
aula da turma inteira.
- Duas aulas da mesma turma à mesma hora só **não** são conflito quando são
  de subgrupos *diferentes* (P1 e P2) — é isto que permite dividir a turma
  em turnos práticos. Uma aula da turma inteira colide com a de qualquer
  subgrupo (os alunos desse subgrupo estariam em dois sítios).
- A RN09 (carga semanal) conta-se por grupo de alunos: o P1 e o P2 têm cada
  um as suas sessões da disciplina; uma sessão da turma inteira conta para
  todos os subgrupos.

O subgrupo aparece entre parênteses na grelha do editor, no horário do
docente, na folha impressa e no CSV.

### Blocos no mesmo dia e hora
- Marcar uma **aula da turma inteira** num bloco que tem **Estudo Autónomo**
  substitui-o (o Estudo Autónomo é só o preenchimento dos blocos livres); a
  substituição fica no histórico. Uma aula de um subgrupo fica ao lado do
  Estudo Autónomo (os outros alunos continuam em estudo).
- Não se pode pôr Estudo Autónomo/Atividade por cima de uma aula da turma
  inteira, nem nada por cima de uma Atividade Não Letiva (incluindo a
  Pastoral) — o editor diz porquê.

### Geração automática — escolha de sala
Para cada aula, experimenta as salas por esta ordem até encontrar uma livre:
a sala habitual da turma (é só sugestão), depois as salas onde a turma cabe,
das mais pequenas para as maiores, preferindo salas normais a auditórios e a
laboratórios. O laboratório de uma disciplina prática (RN16) e a sala fixa
escolhida no formulário de geração continuam a ser obrigatórios.

### Alterações que mexem num horário existente
- Curso, ano e regime de uma **turma** com aulas marcadas não podem mudar
  (as disciplinas e os blocos do horário dependem deles) — arquiva o horário
  ou remove as aulas primeiro. Uma turma que só tem a Pastoral/Estudo
  Autónomo criados pelo sistema pode mudar; esses blocos são refeitos.
- Curso e ano de uma **disciplina** com aulas em horários ativos também não.
- Num horário **Publicado**, criar, editar, remover aulas ou voltar a gerar
  avisa os docentes envolvidos — incluindo quem deixa de ter a aula quando
  o regente/assistente muda.

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
