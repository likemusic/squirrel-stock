#!/usr/bin/env bash
# Приемка пакета перед выкладкой (TODO 2.23): пакет ставится так, как его ставят на хостинг, и проходится сценарий показа
# (.local/DEMO_SCRIPT.md) под admin и под ролью «Склад». Пакет — владельцу только после приемки.
#
#   tools/release/accept.sh dist/squirrel-stock-<версия>.zip          рядом должен лежать squirrel-stock-<версия>-demo.sql
#   tools/release/accept.sh dist/squirrel-stock-<версия>.zip --keep   контейнеры не удаляются — для осмотра руками
#
# Копия хостинга ISPmanager: Apache + PHP 8.4 без своих настроек (образ squirrelstock-web:release из build.sh без
# squirrelstock.ini и правил upload/ — их дает .htaccess пакета), open_basedir — корень сайта, upload_tmp_dir (там же
# сессии) и папка копий, без /tmp; отдельная MariaDB 11.4 с бинарным журналом, пользователь базы — только со своей базой.
# Пакет распаковывается в корень сайта, config.php — из config.example.php, -demo.sql грузится консольным mariadb от
# пользователя базы (как phpMyAdmin), пароль admin — свой, известный; затем cli/role-warehouse.php --user=sklad.
# Проверки: tools/release/check-site.sh и Playwright tests/accept/demo.spec.js (в npm test не входит). Снимки главной и
# меню обеих ролей — tests/test-results/accept/shots/. Нужен Playwright из tests/ (npm ci в tests/).
# Порт на этом компьютере — SQS_TEST_PORT (по умолчанию 8096). Прогон — несколько минут.
set -euo pipefail
cd "$(dirname "$0")/../.."
ZIP= KEEP=0
for a in "$@"; do
  case $a in
    --keep) KEEP=1 ;;
    *.zip) ZIP=$(realpath "$a") ;;
    *) echo "неизвестный параметр: $a" >&2; exit 2 ;;
  esac
done
[[ -f $ZIP ]] || { sed -n '2,16p' "$0"; exit 2; }
SQL=${ZIP%.zip}-demo.sql
[[ -f $SQL ]] || { echo "нет $SQL (собирается вместе с пакетом: tools/release/build.sh)" >&2; exit 2; }
[[ -d tests/node_modules/@playwright ]] || { echo "нет tests/node_modules (cd tests && npm ci)" >&2; exit 2; }
PORT=${SQS_TEST_PORT:-8096}
NET=sqs-accept WEB=sqs-accept-web DB=sqs-accept-db IMAGE=squirrelstock-web:release
SITE=/var/www/html TMPD=/var/www/mod-tmp BK=/var/www/squirrelstock-backup
DBNAME=u1_demo DBUSER=u1_demo
rnd() { python3 -I -c 'import secrets; print(secrets.token_urlsafe(12))'; }
DBPASS=$(rnd) ADMIN_PASS=Admin-$(rnd) SKLAD_PASS=Sklad-$(rnd)
ok() { echo "OK   $*"; }
fail() { echo "FAIL $*"; exit 1; }
dx() { docker exec $WEB "$@"; }
dbq() { docker exec -i $DB mariadb -h127.0.0.1 -u$DBUSER -p"$DBPASS" -N $DBNAME "$@"; }
cleanup() { docker rm -f $WEB $DB >/dev/null 2>&1 || true; docker network rm $NET >/dev/null 2>&1 || true; }

cleanup
if [[ $KEEP == 1 ]]; then
  trap 'echo; echo "Контейнеры оставлены: http://127.0.0.1:$PORT  admin / $ADMIN_PASS  sklad / $SKLAD_PASS"; echo "Удалить: docker rm -f $WEB $DB && docker network rm $NET"' EXIT
else
  trap cleanup EXIT
fi
docker image inspect $IMAGE >/dev/null 2>&1 || docker build -q -f docker/Dockerfile -t $IMAGE . >/dev/null
docker network create $NET >/dev/null
docker run -d --name $DB --network $NET -e MARIADB_ROOT_PASSWORD="$(rnd)" -e MARIADB_DATABASE=$DBNAME -e MARIADB_USER=$DBUSER \
  -e MARIADB_PASSWORD="$DBPASS" mariadb:11.4 --log-bin=mysql-bin --log-bin-trust-function-creators=1 >/dev/null
docker run -d --name $WEB --network $NET -p "127.0.0.1:$PORT:80" --entrypoint sleep $IMAGE infinity >/dev/null

echo "--- пакет $(basename "$ZIP"), копия хостинга на http://127.0.0.1:$PORT"
# PHP и Apache без настроек стенда; корень сайта пустой, как у новой площадки
dx sh -c "rm -f /usr/local/etc/php/conf.d/squirrelstock.ini /etc/apache2/conf-enabled/squirrelstock-upload.conf && rm -rf $SITE/* $SITE/.htaccess $BK/*"
dx sh -c "cat > /etc/apache2/conf-enabled/zz-hosting.conf" <<EOF
# как в настройках сайта ISPmanager: PHP видит только корень сайта, свою временную папку и папку копий
php_admin_value open_basedir "$SITE/:$TMPD/:$BK/"
php_admin_value upload_tmp_dir "$TMPD"
php_admin_value session.save_path "$TMPD"
EOF
docker cp -q "$ZIP" $WEB:/root/package.zip
dx sh -c "mkdir -p $TMPD && cd $SITE && unzip -q /root/package.zip && rm /root/package.zip"
dx sh -c "sed -e \"s/'localhost'/'$DB'/; s/DB_NAME/$DBNAME/; s/DB_USER/$DBUSER/; s/DB_PASS/$DBPASS/\" $SITE/config/config.example.php > $SITE/config/config.php"
dx chown -R www-data:www-data $SITE $TMPD $BK
docker exec -d $WEB apache2-foreground
ver=$(dx cat $SITE/config/version.txt)
ok "пакет распакован, версия $ver"

# база: пока сервер не принимает пользователя по сети, он еще создается
for i in $(seq 60); do dbq -e 'select 1' >/dev/null 2>&1 && break; sleep 2; done
dbq -e 'select 1' >/dev/null 2>&1 || fail "MariaDB не отвечает"
out=$(dbq < "$SQL" 2>&1) && ok "-demo.sql загружен консольным mariadb от пользователя базы" || { echo "$out" | tail -5; fail "загрузка -demo.sql"; }
[[ $(dbq -e "select count(*) from information_schema.triggers where trigger_schema = '$DBNAME'") == 2 ]] && ok "триггеры созданы" || fail "триггеров нет"
hash=$(printf '%s' "$ADMIN_PASS" | docker exec -i $WEB php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);')
dbq -e "update users set userpass = '$hash' where userlogin = 'admin'"
code=$(curl -sL -b '' -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/")
[[ $code == 200 ]] && ok "первое открытие сайта: $code" || fail "первое открытие сайта: $code"
out=$(docker exec -u www-data -e SQS_USER_PASS="$SKLAD_PASS" $WEB php cli/role-warehouse.php --user=sklad 2>&1) || { echo "$out"; fail "cli/role-warehouse.php"; }
echo "$out" | sed 's/^/     /'

echo "--- tools/release/check-site.sh"
rc=0; out=$(SQS_CHECK_PASS="$ADMIN_PASS" SQS_CHECK_DELAY=0 tools/release/check-site.sh "http://127.0.0.1:$PORT" 2>&1) || rc=$?
echo "$out" | sed 's/^/     /'
[[ $rc == 0 ]] && ok "check-site.sh: ошибок нет" || fail "check-site.sh"

echo "--- сценарий показа: admin и sklad (tests/accept/demo.spec.js)"
rc=0
(cd tests && SQS_BASE="http://127.0.0.1:$PORT" SQS_PASS="$ADMIN_PASS" SQS_SKLAD_PASS="$SKLAD_PASS" SQS_WEB_CONTAINER=$WEB \
  npx playwright test --config accept/playwright.config.js) || rc=$?
echo "     снимки главной и меню: tests/test-results/accept/shots/"
[[ $rc == 0 ]] && echo "--- приемка пройдена ($ver)" || fail "сценарий показа (отчет: cd tests && npx playwright show-report playwright-report/accept)"
