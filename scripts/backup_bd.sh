#!/bin/sh
# ============================================================
# Cópia de segurança da base de dados do Sistema de Horários.
#
# Uso (na VPS, a partir da pasta do projeto):
#   sh scripts/backup_bd.sh
# Cria  $BACKUP_DIR/horarios_AAAA-MM-DD_HHMM.sql.gz  e guarda só as
# últimas $BACKUP_MANTER cópias (por omissão 30).
#
# Todos os dias às 02:00 (crontab -e, como root):
#   0 2 * * * cd /opt/sistema-horarios-v2 && sh scripts/backup_bd.sh >> /var/log/horarios-backup.log 2>&1
#
# Funciona com Docker (serviço "db" do docker-compose.yml) ou com o MySQL
# instalado diretamente no servidor (usa DB_HOST/DB_USER/DB_PASS do .env).
#
# As cópias ficam FORA da pasta do site por omissão (/var/backups/horarios):
# dentro dela, numa instalação com Apache direto, seriam descarregáveis.
# Guarda também uma cópia noutra máquina — uma cópia no mesmo disco não
# serve de nada se o disco ou a VPS se perderem.
#
# Repor uma cópia (APAGA o estado atual da base de dados):
#   Docker: gunzip -c FICHEIRO.sql.gz | docker compose exec -T db sh -c \
#             'exec mysql --default-character-set=utf8mb4 -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
#   Direto: gunzip -c FICHEIRO.sql.gz | mysql --default-character-set=utf8mb4 -u UTILIZADOR -p NOME_DA_BD
# ============================================================
set -eu

cd "$(dirname "$0")/.."
if [ -f .env ]; then set -a; . ./.env; set +a; fi

BACKUP_DIR="${BACKUP_DIR:-/var/backups/horarios}"
BACKUP_MANTER="${BACKUP_MANTER:-30}"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
umask 077

FICHEIRO="$BACKUP_DIR/horarios_$(date +%Y-%m-%d_%H%M).sql.gz"
OPCOES="--default-character-set=utf8mb4 --single-transaction --routines --triggers --no-tablespaces"

if docker compose ps --status running db >/dev/null 2>&1 && [ -n "$(docker compose ps -q db 2>/dev/null)" ]; then
    # A palavra-passe é lida DENTRO do contentor (variáveis do próprio
    # serviço db) — nunca aparece na lista de processos do servidor.
    docker compose exec -T db sh -c "exec mysqldump $OPCOES -uroot -p\"\$MYSQL_ROOT_PASSWORD\" \"\$MYSQL_DATABASE\"" | gzip > "$FICHEIRO"
else
    # MySQL instalado diretamente: credenciais num ficheiro temporário
    # (MYSQL_PWD/-p na linha de comandos ficariam visíveis a outros utilizadores).
    CNF="$(mktemp)"
    trap 'rm -f "$CNF"' EXIT
    printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "${DB_USER:-root}" "${DB_PASS:-}" "${DB_HOST:-localhost}" > "$CNF"
    [ -n "${DB_PORT:-}" ] && printf 'port=%s\n' "$DB_PORT" >> "$CNF"
    mysqldump --defaults-extra-file="$CNF" $OPCOES "${DB_NAME:-horarios_fagrenm}" | gzip > "$FICHEIRO"
fi

# Uma cópia vazia (ex.: palavra-passe errada) não pode passar por boa.
if [ "$(gzip -dc "$FICHEIRO" | head -c 1000 | wc -c)" -lt 100 ]; then
    echo "$(date '+%F %T') ERRO: a cópia saiu vazia — $FICHEIRO" >&2
    rm -f "$FICHEIRO"
    exit 1
fi

ls -1t "$BACKUP_DIR"/horarios_*.sql.gz 2>/dev/null | tail -n +"$((BACKUP_MANTER + 1))" | while read -r velho; do rm -f -- "$velho"; done
echo "$(date '+%F %T') cópia feita: $FICHEIRO ($(du -h "$FICHEIRO" | cut -f1))"
