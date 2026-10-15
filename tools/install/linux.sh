#!/usr/bin/env bash
# Установка SquirrelStock на чистый Debian 12 / Ubuntu 24.04 без Docker: Apache + mod_php, MariaDB, rclone, cron для копий.
# Запуск от root:
#   tools/install/linux.sh                     сайт из этой git-копии (www/), зависимости — composer
#   tools/install/linux.sh --zip=<пакет.zip>   сайт из пакета tools/release/build.sh (там vendor уже есть)
#   tools/install/linux.sh --demo              демо-база вместо пустой (только при первой установке)
#   --dir=/var/www/squirrelstock  --port=80  --servername=_  --no-profile (не применять профиль благотворительной организации)
#
# Повторный запуск безопасен: пакеты доустанавливаются, код сайта обновляется (config.php, upload/, logs/ не трогаются),
# база, ее пользователь и пароль admin не меняются. Пароль admin печатается один раз — при создании базы.
#
# Почему mod_php, а не php-fpm: одна служба вместо двух, меньше памяти на слабом компьютере, и так же устроены
# Docker-образ стенда (php:8.2-apache) и обычный хостинг — .htaccess и настройки PHP ведут себя одинаково.
set -euo pipefail

DIR=/var/www/squirrelstock
PORT=80
SERVERNAME=_
ZIP=
DEMO=0
PROFILE=1
for a in "$@"; do
  case $a in
    --dir=*) DIR=${a#*=} ;;
    --port=*) PORT=${a#*=} ;;
    --servername=*) SERVERNAME=${a#*=} ;;
    --zip=*) ZIP=$(realpath "${a#*=}") ;;
    --demo) DEMO=1 ;;
    --no-profile) PROFILE=0 ;;
    -h|--help) sed -n '2,13p' "$0"; exit 0 ;;
    *) echo "неизвестный параметр: $a" >&2; exit 2 ;;
  esac
done
[[ $(id -u) == 0 ]] || { echo "запускать от root (sudo $0)" >&2; exit 1; }
REPO=$(cd "$(dirname "$0")/../.." && pwd)
DB=squirrelstock
DBUSER=squirrelstock
BACKUP=$(dirname "$DIR")/squirrelstock-backup   # папка копий по умолчанию (App\SQS\BackupJob::defaults)
say() { echo "== $*"; }

# службы: systemd, если есть (обычная машина), иначе service (контейнер проверки)
svc() {   # svc <служба> <start|reload|restart>
  if [[ -d /run/systemd/system ]]; then
    [[ $2 == start ]] && systemctl enable --now "$1" >/dev/null || systemctl "$2" "$1"
  else
    service "$1" "$2" >/dev/null
  fi
}

say "пакеты"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq --no-install-recommends apache2 libapache2-mod-php php-cli php-mysql php-zip php-gmp php-gd php-mbstring \
  php-curl php-xml php-intl mariadb-server rclone cron unzip rsync ca-certificates openssl >/dev/null
[[ -n $ZIP ]] || apt-get install -y -qq --no-install-recommends composer git >/dev/null
svc mariadb start
svc cron start || true

say "сайт в $DIR"
mkdir -p "$DIR"
# код обновляется, а настройки, загруженные файлы и журналы остаются
if [[ -n $ZIP ]]; then
  stage=$(mktemp -d); trap 'rm -rf "$stage"' EXIT
  unzip -q "$ZIP" -d "$stage"
  rsync -a --delete --exclude=/config/config.php --exclude=/upload/ --exclude=/logs/ "$stage/" "$DIR/"
else
  rsync -a --delete --exclude=/config/config.php --exclude=/upload/ --exclude=/logs/ --exclude=/vendor/ "$REPO/www/" "$DIR/"
  (cd "$DIR" && COMPOSER_ALLOW_SUPERUSER=1 composer install -q --no-dev --no-interaction --prefer-dist --optimize-autoloader)
fi
rm -f "$DIR/script_examples.zip"   # примеры скриптов автора Zippy на сайте не нужны
mkdir -p "$DIR/upload" "$DIR/logs" "$BACKUP"

say "база"
CONF=$DIR/config/config.php
if [[ -f $CONF ]] && ! grep -q "'DB_PASS'" "$CONF"; then
  DBPASS=$(php -r "include '$CONF'; echo \$_config['db']['pass'];")
else
  DBPASS=$(openssl rand -hex 16)
  cp "$REPO/tools/release/config.php" "$CONF"
  sed -i "s/'DB_NAME'/'$DB'/; s/'DB_USER'/'$DBUSER'/; s/'DB_PASS'/'$DBPASS'/" "$CONF"
fi
mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE USER IF NOT EXISTS '$DBUSER'@'localhost' IDENTIFIED BY '$DBPASS';
ALTER USER '$DBUSER'@'localhost' IDENTIFIED BY '$DBPASS';
GRANT ALL PRIVILEGES ON $DB.* TO '$DBUSER'@'localhost';
FLUSH PRIVILEGES;
SQL
ADMINPASS=
if [[ $(mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB'") == 0 ]]; then
  if [[ $DEMO == 1 ]]; then
    unzip -p "$REPO/db/demo/squirrelstock-demo.zip" database.sql | mysql -uroot "$DB"
  else
    cat "$REPO/db/db.sql" "$REPO/db/squirrelstock.sql" | mysql -uroot "$DB"
  fi
  # вместо admin / admin — случайный пароль и случайная «соль», как в пакете tools/release/build.sh
  ADMINPASS=$(openssl rand -base64 12 | tr -d '/+=' | cut -c1-14)
  HASH=$(printf '%s' "$ADMINPASS" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);')
  SALT=$(openssl rand -hex 32)
  mysql -uroot "$DB" -e "UPDATE users SET userpass = '$HASH' WHERE userlogin = 'admin'; REPLACE INTO keyval (keyd, vald) VALUES ('salt', '$SALT');"
  # таблицы и пункты меню модулей (app/sqs/modules) — до профиля и перевода, чтобы те их видели
  php "$DIR/cli/modules.php" >/dev/null
  if [[ $DEMO == 0 && $PROFILE == 1 ]]; then
    php "$DIR/cli/charity-profile.php" >/dev/null
    php "$DIR/cli/localize-db.php" ru >/dev/null
  fi
else
  # дополнения форка — безопасно повторять (новые таблицы и пункты меню после обновления кода)
  mysql -uroot "$DB" < "$REPO/db/squirrelstock.sql"
  php "$DIR/cli/modules.php" >/dev/null   # новые таблицы и пункты меню модулей
fi
chown -R www-data:www-data "$DIR" "$BACKUP"
chmod 640 "$CONF"

say "PHP и Apache"
for d in /etc/php/*/apache2/conf.d /etc/php/*/cli/conf.d; do
  cp "$REPO/docker/php.ini" "$d/90-squirrelstock.ini"
done
a2enmod -q rewrite headers >/dev/null
[[ $PORT == 80 ]] || grep -q "^Listen $PORT$" /etc/apache2/ports.conf || echo "Listen $PORT" >> /etc/apache2/ports.conf
cat > /etc/apache2/sites-available/squirrelstock.conf <<CONF
# SquirrelStock (tools/install/linux.sh)
<VirtualHost *:$PORT>
    ServerName $SERVERNAME
    DocumentRoot $DIR
    <Directory $DIR>
        AllowOverride All
        Require all granted
    </Directory>
    # служебные папки не отдаются по HTTP (vendor нужен: из него грузятся скрипты фреймворка)
    <DirectoryMatch "^$DIR/(config|logs|cli)">
        Require all denied
    </DirectoryMatch>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    ErrorLog \${APACHE_LOG_DIR}/squirrelstock-error.log
    CustomLog \${APACHE_LOG_DIR}/squirrelstock-access.log combined
</VirtualHost>
CONF
a2dissite -q 000-default >/dev/null 2>&1 || true
a2ensite -q squirrelstock >/dev/null
apache2ctl configtest 2>&1 | grep -v "Syntax OK" | grep -v AH00558 || true
if [[ -f /var/run/apache2/apache2.pid ]] && kill -0 "$(cat /var/run/apache2/apache2.pid)" 2>/dev/null; then svc apache2 reload; else svc apache2 start; fi

say "резервная копия по расписанию"
cat > /etc/cron.d/squirrelstock <<CRON
# SquirrelStock: копия раз в сутки; проверка каждый час — если компьютер был выключен, копия сделается после включения
17 * * * * www-data php $DIR/cli/backup.php --if-older=20 >> $DIR/logs/backup-cron.log 2>&1
CRON
chmod 644 /etc/cron.d/squirrelstock

ADDR=$(hostname -I 2>/dev/null | awk '{print $1}')
echo
echo "Готово: http://${ADDR:-localhost}$([[ $PORT == 80 ]] || echo ":$PORT")/"
if [[ -n $ADMINPASS ]]; then
  echo "Вход: admin / $ADMINPASS"
  echo "Запишите пароль: он нигде не сохраняется и больше не показывается. Сменить — в профиле пользователя."
else
  echo "База уже была: пароль admin не менялся."
fi
echo "Копии базы: $BACKUP (каждые сутки), настройки — «Сервис → Резервная копия»."
