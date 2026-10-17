#!/usr/bin/env bash
# Установка SquirrelStock на чистый Debian 13 (рекомендуется: PHP 8.4, MariaDB 11.8) / Debian 12 (PHP 8.2) / Ubuntu 24.04 (PHP 8.3)
# без Docker: Apache + mod_php, MariaDB, rclone, cron для копий. PHP и MariaDB — из пакетов самого дистрибутива (их обновления
# безопасности приходят через apt), сторонние репозитории не подключаются; нужен PHP 8.2 или новее.
# Запуск от root:
#   tools/install/linux.sh                     сайт из этой git-копии (www/), зависимости — composer
#   tools/install/linux.sh --zip=<пакет.zip>   сайт из пакета tools/release/build.sh (там vendor уже есть)
#   tools/install/linux.sh --demo              демо-база вместо пустой (только при первой установке)
#   --dir=/var/www/squirrelstock  --port=80  --servername=_  --no-profile (не применять профиль благотворительной организации)
#
# Повторный запуск безопасен: пакеты доустанавливаются, код сайта обновляется (config.php, upload/, logs/ не трогаются),
# база, ее пользователь и пароль admin не меняются. Пароль admin печатается один раз — при создании базы.
# Обновление (повторный запуск с новой git-копией или новым пакетом): сначала резервная копия базы и файлов в
# <папка копий>/before-update/, потом замена кода (файлы, которых нет в новой версии, удаляются), досоздание таблиц;
# версия «было → стало» печатается и пишется в logs/update.log. Порядок — docs/UPDATE.ru.md.
#
# Почему mod_php, а не php-fpm: одна служба вместо двух, меньше памяти на слабом компьютере, и так же устроены
# Docker-образ стенда (php:8.4-apache) и обычный хостинг — .htaccess и настройки PHP ведут себя одинаково.
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
    -h|--help) sed -n '2,18p' "$0"; exit 0 ;;
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
  php-curl php-xml php-intl mariadb-server rclone cron logrotate unzip rsync ca-certificates openssl >/dev/null
[[ -n $ZIP ]] || apt-get install -y -qq --no-install-recommends composer git >/dev/null
php -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' || { echo "нужен PHP 8.2 или новее, в этой системе $(php -r 'echo PHP_VERSION;'): Debian 13, Debian 12 или Ubuntu 24.04" >&2; exit 1; }
svc mariadb start
svc cron start || true

# обновление: копия базы и файлов до замены кода — старой версией программы (cli/backup.php; копию можно восстановить
# на странице «Сервис → Резервная копия»), а если она не сработала — mysqldump и архив config.php и upload/
OLDVER=
if [[ -f $DIR/config/config.php && $(mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB'") != 0 ]]; then
  OLDVER=$(cat "$DIR/config/version.txt" 2>/dev/null || echo "без номера")
  say "резервная копия перед обновлением (версия $OLDVER)"
  mkdir -p "$BACKUP/before-update"
  chown www-data:www-data "$BACKUP" "$BACKUP/before-update"
  if [[ -f $DIR/cli/backup.php ]] && runuser -u www-data -- php "$DIR/cli/backup.php" --only=local "$BACKUP/before-update" >/dev/null 2>&1; then
    echo "копия: $(ls -t "$BACKUP"/before-update/*.zip | head -1)"
  else
    ts=$(date +%Y-%m-%d-%H%M%S)
    mysqldump -uroot --single-transaction --routines "$DB" | gzip > "$BACKUP/before-update/database-$ts.sql.gz"
    tar -czf "$BACKUP/before-update/files-$ts.tar.gz" -C "$DIR" config/config.php upload
    echo "копия (mysqldump): $BACKUP/before-update/database-$ts.sql.gz, файлы: files-$ts.tar.gz"
    # запасные копии храним последние 5 (копии программой выше ограничивает ее настройка «сколько хранить»)
    for p in database files; do
      ls -1t "$BACKUP"/before-update/$p-*.gz 2>/dev/null | tail -n +6 | xargs -r rm -f
    done
  fi
fi

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
  # версия для «Сервис → Обновление программы» и подвала меню: как в пакете (tools/release/build.sh), без git — dev
  if git -C "$REPO" rev-parse HEAD >/dev/null 2>&1; then
    v=$(git -C "$REPO" log -1 --format=%cd --date=format:%Y.%m.%d HEAD)-$(git -C "$REPO" rev-parse --short=9 HEAD)
    [[ -z $(git -C "$REPO" status --porcelain --untracked-files=no 2>/dev/null) ]] || v=$v-dirty
    echo "$v" > "$DIR/config/version.txt"
  fi
fi
NEWVER=$(cat "$DIR/config/version.txt" 2>/dev/null || echo dev)
rm -f "$DIR/script_examples.zip"   # примеры скриптов автора Zippy на сайте не нужны
mkdir -p "$DIR/upload" "$DIR/logs" "$BACKUP"
# upload/ выше исключена из копирования (там файлы пользователей), но ее .htaccess — часть программы
cp "$([[ -n $ZIP ]] && echo "$stage" || echo "$REPO/www")/upload/.htaccess" "$DIR/upload/.htaccess"

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
    cat "$REPO/db/db.sql" "$REPO/www/app/sqs/schema.sql" | mysql -uroot "$DB"
  fi
  # вместо admin / admin — случайный пароль и случайная «соль», как в пакете tools/release/build.sh
  ADMINPASS=$(openssl rand -base64 12 | tr -d '/+=' | cut -c1-14)
  HASH=$(printf '%s' "$ADMINPASS" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);')
  SALT=$(openssl rand -hex 32)
  mysql -uroot "$DB" -e "UPDATE users SET userpass = '$HASH' WHERE userlogin = 'admin'; REPLACE INTO keyval (keyd, vald) VALUES ('salt', '$SALT');"
  # таблицы платформы и модулей, пункты меню модулей — до профиля и перевода, чтобы те их видели
  php "$DIR/cli/modules.php" >/dev/null
  if [[ $DEMO == 0 && $PROFILE == 1 ]]; then
    php "$DIR/cli/charity-profile.php" >/dev/null
    php "$DIR/cli/localize-db.php" ru >/dev/null
  fi
else
  # новые таблицы платформы (app/sqs/schema.sql) и модулей, пункты меню модулей после обновления кода
  php "$DIR/cli/modules.php" >/dev/null
fi
if [[ -n $OLDVER ]]; then
  echo "$(date '+%Y-%m-%d %H:%M:%S') обновление: $OLDVER -> $NEWVER (tools/install/linux.sh)" >> "$DIR/logs/update.log"
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
    # загруженные файлы: только картинки, PHP не выполняется (правила здесь — восстановление из копии пишет в upload/)
    <Directory $DIR/upload>
        AllowOverride None
        Require all denied
        <FilesMatch "(?i)\.(jpe?g|png|gif|webp)\$">
            Require all granted
        </FilesMatch>
        <FilesMatch "(?i)\.(php\d*|pht|phtml|phar|cgi|pl|py|sh|shtml)\.">
            Require all denied
        </FilesMatch>
        <IfModule php_module>
            php_admin_flag engine off
        </IfModule>
    </Directory>
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
# журналы копий по расписанию и обновлений растут без конца (журнал программы log-*.txt чистит она сама — 7 дней)
cat > /etc/logrotate.d/squirrelstock <<ROT
$DIR/logs/backup-cron.log $DIR/logs/update.log {
  monthly
  rotate 12
  compress
  missingok
  notifempty
  su www-data www-data
}
ROT
chmod 644 /etc/logrotate.d/squirrelstock

ADDR=$(hostname -I 2>/dev/null | awk '{print $1}')
echo
echo "Готово: http://${ADDR:-localhost}$([[ $PORT == 80 ]] || echo ":$PORT")/"
if [[ -n $OLDVER ]]; then
  echo "Обновлено: $OLDVER -> $NEWVER. Копия до обновления — в $BACKUP/before-update."
else
  echo "Версия: $NEWVER"
fi
if [[ -n $ADMINPASS ]]; then
  echo "Вход: admin / $ADMINPASS"
  echo "Запишите пароль: он нигде не сохраняется и больше не показывается. Сменить — в профиле пользователя."
else
  echo "База уже была: пароль admin не менялся."
fi
echo "PHP $(php -r 'echo PHP_VERSION;'), $(mysql -uroot -N -e 'SELECT VERSION()')"
echo "Копии базы: $BACKUP (каждые сутки), настройки — «Сервисные страницы → Резервная копия»."
