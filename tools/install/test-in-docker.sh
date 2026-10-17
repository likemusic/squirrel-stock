#!/usr/bin/env bash
# Проверка tools/install/linux.sh в чистом контейнере debian:13 (по умолчанию) (без systemd: службы запускаются через service).
#   tools/install/test-in-docker.sh [образ] [параметры linux.sh]     например: debian:12 --demo, ubuntu:24.04
# Что проверяется: установка с нуля; главная отдается; вход admin с напечатанным паролем (Playwright из tests/, если
# установлен); cli/backup.php делает копию; повторный запуск установки проходит, пароль и данные не меняются.
# Контейнер удаляется в конце (и при ошибке). Порт на этом компьютере — SQS_TEST_PORT (по умолчанию 8095).
set -euo pipefail
cd "$(dirname "$0")/../.."
IMAGE=${1:-debian:13}; shift || true
PORT=${SQS_TEST_PORT:-8095}
NAME=sqs-linux-test
trap 'docker rm -f $NAME >/dev/null 2>&1 || true' EXIT
ok() { echo "OK   $*"; }
fail() { echo "FAIL $*"; exit 1; }

docker rm -f $NAME >/dev/null 2>&1 || true
docker run -d --name $NAME -p "127.0.0.1:$PORT:80" "$IMAGE" sleep infinity >/dev/null
# рабочая копия как есть (в том числе незакоммиченное), без node_modules и результатов тестов
git ls-files -co --exclude-standard | grep -v -e '^tests/node_modules' -e '^dist/' | tar -cf - -T - | docker exec -i $NAME sh -c 'mkdir -p /opt/sqs && tar -xf - -C /opt/sqs'

echo "--- установка ($IMAGE $*)"
out=$(docker exec $NAME bash /opt/sqs/tools/install/linux.sh "$@" 2>&1) || { echo "$out" | tail -30; fail "установка"; }
echo "$out" | tail -6
pass=$(echo "$out" | sed -n 's/^Вход: admin \/ //p')
[[ -n $pass ]] && ok "напечатан пароль admin" || fail "пароль не напечатан"

jar=$(mktemp)
# / переадресует на страницу входа; без cookie сессии переадресация повторяется, как у браузера с запретом cookie
code=$(curl -sL -c "$jar" -b "$jar" --max-redirs 5 -o /tmp/sqs-main.html -w '%{http_code}' "http://127.0.0.1:$PORT/" || true)
rm -f "$jar"
[[ $code == 200 ]] && grep -q 'type="password"' /tmp/sqs-main.html && ok "главная: форма входа" || fail "главная: HTTP $code"
for p in config/config.php logs/ cli/backup.php; do
  c=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/$p" || true)
  [[ $c == 403 || $c == 404 ]] && ok "/$p закрыт ($c)" || fail "/$p отдается ($c)"
done
curl -sI "http://127.0.0.1:$PORT/index.php?q=p:App/Pages/UserLogin" | grep -qi "^Content-Security-Policy: default-src 'self'" && ok "заголовок Content-Security-Policy (из .htaccess)" || fail "нет Content-Security-Policy"
c=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/vendor/leon-mbs/zippy/assets/js/zippy-bundle-ua.js")
[[ $c == 200 ]] && ok "скрипты фреймворка из vendor отдаются" || fail "vendor: $c"
docker exec $NAME grep -q "allowed_classes" /var/www/squirrelstock/vendor/leon-mbs/zippy/core/httprequest.php && ok "исправления vendor наложены (cli/patch-vendor.php)" || fail "исправления vendor не наложены"

login() {
  [[ -d tests/node_modules/@playwright ]] || { echo "SKIP вход через браузер: нет tests/node_modules"; return 0; }
  (cd tests && SQS_URL="http://127.0.0.1:$PORT" SQS_PASS="$pass" node -e '
    const { chromium } = require("@playwright/test");
    (async () => {
      const b = await chromium.launch(); const p = await (await b.newContext({ baseURL: process.env.SQS_URL })).newPage();
      await p.goto("/"); await p.locator("#userlog").fill("admin"); await p.locator("input[type=password]").fill(process.env.SQS_PASS);
      await Promise.all([p.waitForLoadState("networkidle"), p.locator("button[type=submit], input[type=submit]").first().click()]);
      const ok = await p.locator("input[type=password]").count() == 0 && /admin/.test(await p.locator("body").innerText());
      console.log(ok ? "вошли" : "не вошли"); await b.close(); process.exit(ok ? 0 : 1);
    })();') && ok "вход admin, главная открылась" || fail "вход"
}
login

docker exec -u www-data $NAME php /var/www/squirrelstock/cli/backup.php --only=local >/dev/null && ok "cli/backup.php: код 0" || fail "cli/backup.php"
n=$(docker exec $NAME sh -c 'ls /var/www/squirrelstock-backup/*.zip 2>/dev/null | wc -l')
[[ $n -ge 1 ]] && ok "копия в /var/www/squirrelstock-backup ($n)" || fail "нет файла копии"
docker exec $NAME test -f /etc/cron.d/squirrelstock && ok "cron: /etc/cron.d/squirrelstock" || fail "нет cron"

docs=$(docker exec $NAME mysql -uroot -N squirrelstock -e "select count(*) from documents")
echo "--- повторный запуск"
out2=$(docker exec $NAME bash /opt/sqs/tools/install/linux.sh "$@" 2>&1) || { echo "$out2" | tail -30; fail "повторная установка"; }
echo "$out2" | grep -q "пароль admin не менялся" && ok "повтор: пароль не менялся" || fail "повтор: пароль"
[[ $(docker exec $NAME mysql -uroot -N squirrelstock -e "select count(*) from documents") == "$docs" ]] && ok "повтор: данные на месте ($docs документов)" || fail "повтор: данные"
login
echo "--- все проверки пройдены"
