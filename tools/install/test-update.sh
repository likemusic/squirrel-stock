#!/usr/bin/env bash
# Проверка обновления «старая версия → эта рабочая копия» в чистом контейнере debian:12 — так, как обновляет человек:
#   tools/install/test-update.sh hosting --zip=dist/squirrel-stock-<версия>.zip   пакет распаковывается поверх сайта
#                                                                                  (как в файловом менеджере хостинга)
#   tools/install/test-update.sh linux                                             повторный tools/install/linux.sh
#   --old=<коммит>   старая версия (по умолчанию f07b0f58a — 15.10.2026, последняя до модулей: код форка в app/pages и
#                    app/sqs, таблицы в db/squirrelstock.sql, одно облако копий)
# Старая версия ставится своим linux.sh с демо-базой; к данным добавляются 2FA у admin, облако копий (rclone, папка в
# контейнере), файл в upload/; делается копия старой версией. После обновления: данные те же (по столбцам старой
# версии), таблицы новой версии созданы, вход с кодом 2FA, отчеты и сервисные страницы открываются, версия видна,
# обновление записано, копия делается во все места, остатков старой версии нет (hosting — после кнопки на странице
# «Обновление программы»). Нужен Playwright из tests/ (npm ci в tests/). Контейнер удаляется в конце.
# Порт на этом компьютере — SQS_TEST_PORT (по умолчанию 8095). Прогон — около 10 минут, в основном скачивание пакетов.
set -euo pipefail
cd "$(dirname "$0")/../.."
MODE=${1:-}; shift || true
OLD=f07b0f58a
ZIP=
for a in "$@"; do
  case $a in
    --old=*) OLD=${a#*=} ;;
    --zip=*) ZIP=$(realpath "${a#*=}") ;;
    *) echo "неизвестный параметр: $a" >&2; exit 2 ;;
  esac
done
[[ $MODE == hosting || $MODE == linux ]] || { sed -n '2,15p' "$0"; exit 2; }
[[ $MODE == linux || -f $ZIP ]] || { echo "для hosting нужен --zip=<пакет tools/release/build.sh>" >&2; exit 2; }
[[ -d tests/node_modules/@playwright ]] || { echo "нет tests/node_modules (cd tests && npm ci)" >&2; exit 2; }
PORT=${SQS_TEST_PORT:-8095}
NAME=sqs-update-test
SITE=/var/www/squirrelstock
SECRET=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP   # ключ 2FA для admin (тестовый, base32)
ok() { echo "OK   $*"; }
fail() { echo "FAIL $*"; exit 1; }
dx() { docker exec $NAME "$@"; }
sql() { dx mysql -uroot -N squirrelstock -e "$1"; }

trap 'docker rm -f $NAME >/dev/null 2>&1 || true' EXIT
docker rm -f $NAME >/dev/null 2>&1 || true
docker run -d --name $NAME -p "127.0.0.1:$PORT:80" debian:12 sleep infinity >/dev/null
git archive "$OLD" | docker exec -i $NAME sh -c 'mkdir -p /opt/old && tar -xf - -C /opt/old'
git ls-files -co --exclude-standard | grep -v -e '^tests/node_modules' -e '^dist/' | tar -cf - -T - | docker exec -i $NAME sh -c 'mkdir -p /opt/sqs && tar -xf - -C /opt/sqs'
[[ -z $ZIP ]] || docker cp -q "$ZIP" $NAME:/opt/new.zip

echo "--- старая версия $(git log -1 --format='%h %cd' --date=format:%d.%m.%Y "$OLD"), демо-база"
out=$(dx bash /opt/old/tools/install/linux.sh --demo 2>&1) || { echo "$out" | tail -30; fail "установка старой версии"; }
pass=$(echo "$out" | sed -n 's/^Вход: admin \/ //p')
[[ -n $pass ]] || fail "старая версия: пароль не напечатан"
# демо-база той версии собрана без таблиц входа (2FA): дополнения форка на существующую базу, как велела ее инструкция
dx sh -c "mysql -uroot squirrelstock < /opt/old/db/squirrelstock.sql"
admin=$(sql "select user_id from users where userlogin = 'admin'")
sql "replace into sqs_2fa (user_id, secret, enabled, last_step, updated) values ($admin, '$SECRET', 1, 0, now())"
dx mkdir -p /srv/cloud
dx chown www-data:www-data /srv/cloud
sql "replace into keyval (keyd, vald) values ('sqs_backup', '{\"cloud\":\":local:/srv/cloud\",\"password\":\"Пароль-копии-001\",\"keep\":30}')"
dx sh -c "printf 'GIF89a' > $SITE/upload/test-update.gif && chown www-data:www-data $SITE/upload/test-update.gif"
out=$(dx runuser -u www-data -- php $SITE/cli/backup.php 2>&1) || { echo "$out" | tail -10; sql "select place, ok, message from sqs_backuplog order by id desc limit 3"; fail "старая версия: копия"; }
[[ $(dx sh -c 'ls /srv/cloud | wc -l') -ge 1 ]] && ok "старая версия: копия в папку и в облако" || fail "старая версия: копии в облаке нет"
# состав данных старой версии: столбцы таблиц на момент установки — после обновления те же значения. Без того, что
# меняет сам вход (время входа, настройки пользователя, последний шаг 2FA) и сама программа (keyval: версия, сверка
# модулей и схем, время запуска встроенного планировщика Zippy lastcron*)
tmp=$(mktemp -d); trap 'docker rm -f $NAME >/dev/null 2>&1 || true; rm -rf "$tmp"' EXIT
TABLES="documents contracts customers items store_stock sqs_unit users sqs_2fa keyval"
for t in $TABLES; do
  case $t in
    users) echo "user_id, userlogin, userpass, acl, disabled, role_id" ;;
    sqs_2fa) echo "user_id, secret, enabled" ;;
    keyval) echo "keyd, vald" ;;
    *) sql "select group_concat(column_name order by ordinal_position) from information_schema.columns where table_schema = 'squirrelstock' and table_name = '$t'" ;;
  esac > "$tmp/$t"
done
digest() {
  local q="set session group_concat_max_len = 1000000000;" t cols where
  for t in $TABLES; do
    cols=$(cat "$tmp/$t"); where=
    [[ $t == keyval ]] && where="where keyd not like 'sqs\\_schema%' and keyd not like 'sqs\\_modules%' and keyd not like 'sqs\\_version%' and keyd not like 'lastcron%'"
    q+="select '$t', count(*), md5(group_concat(concat_ws(char(31), $cols) order by $cols separator '#~#')) from $t $where;"
  done
  sql "$q"
}
before=$(digest)
sql "select keyd, md5(vald) from keyval order by keyd" > "$tmp/keyval-before"
echo "$before" | awk '{print "     " $1 ": " $2}' | tr '\n' ' '; echo
conf_md5=$(dx md5sum $SITE/config/config.php | cut -c1-32)
mapfile -t oldonly < <(comm -23 <(git ls-tree -r --name-only "$OLD" www | sed 's|^www/||' | sort) <(git ls-tree -r --name-only HEAD www | sed 's|^www/||' | sort))
echo "     файлов старой версии, которых нет в новой: ${#oldonly[@]}"

echo "--- обновление: $MODE"
if [[ $MODE == hosting ]]; then
  # человек: копия (сделана выше), распаковка пакета поверх сайта с заменой файлов; база — без ручных шагов
  dx sh -c "cd $SITE && unzip -o -q /opt/new.zip && chown -R www-data:www-data ."
  [[ $(dx md5sum $SITE/config/config.php | cut -c1-32) == "$conf_md5" ]] && ok "config/config.php не затерт пакетом" || fail "config/config.php изменился"
  left=0; for f in "${oldonly[@]}"; do dx test -e "$SITE/$f" && left=$((left + 1)); done
  echo "     остатки старой версии после распаковки: $left (удаляются на странице «Обновление программы»)"
  newver=$(dx cat $SITE/config/version.txt)
else
  out=$(dx bash /opt/sqs/tools/install/linux.sh 2>&1) || { echo "$out" | tail -30; fail "linux.sh"; }
  echo "$out" | grep -E "^(копия|Обновлено)" | sed 's/^/     /'
  echo "$out" | grep -q "пароль admin не менялся" && ok "пароль admin не менялся" || fail "пароль admin"
  echo "$out" | grep -q "^Обновлено: без номера -> " && ok "версия «было → стало» напечатана" || fail "версия не напечатана"
  [[ $(dx sh -c 'ls /var/www/squirrelstock-backup/before-update/*.zip 2>/dev/null | wc -l') -ge 1 ]] && ok "копия перед обновлением: before-update/*.zip" || fail "нет копии перед обновлением"
  dx grep -q " обновление: без номера -> " $SITE/logs/update.log && ok "logs/update.log" || fail "нет записи в logs/update.log"
  [[ $(dx md5sum $SITE/config/config.php | cut -c1-32) == "$conf_md5" ]] && ok "config/config.php не изменился" || fail "config/config.php изменился"
  left=0; for f in "${oldonly[@]}"; do dx test -e "$SITE/$f" && left=$((left + 1)); done
  [[ $left == 0 ]] && ok "остатков старой версии нет (${#oldonly[@]} файлов удалены)" || fail "остатки старой версии: $left"
  newver=$(dx cat $SITE/config/version.txt 2>/dev/null || echo dev)
fi

# вход (пароль + код 2FA), страницы, версия; hosting — удаление остатков на странице «Обновление программы»
# первый запрос к сайту (на хостинге — первое открытие после распаковки): таблицы и пункты меню новой версии
curl -s -o /dev/null "http://127.0.0.1:$PORT/" || true
reports=$(sql "select group_concat(meta_name) from metadata where meta_type = 2 and disabled = 0")
services=$(sql "select group_concat(meta_name) from metadata where meta_type = 5 and disabled = 0 and meta_name in ('Backup', 'AppUpdate', 'Import1C', 'EquipmentIssue', 'ConsumablesIssue', 'ImportEquipmentHistory')")
[[ ,$services, == *,AppUpdate,* ]] && ok "пункт меню «Обновление программы» добавлен" || fail "нет пункта меню AppUpdate ($services)"
(cd tests && SQS_URL="http://127.0.0.1:$PORT" SQS_PASS="$pass" SQS_SECRET="$SECRET" SQS_VER="$newver" SQS_REPORTS="$reports" SQS_SERVICES="$services" SQS_CLEAN=$([[ $MODE == hosting ]] && echo 1 || echo 0) node -e '
  const { chromium } = require("@playwright/test");
  const crypto = require("crypto");
  function totp(b32) {
    const alpha = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567"; let bits = "";
    for (const c of b32) bits += alpha.indexOf(c).toString(2).padStart(5, "0");
    const key = Buffer.from(bits.match(/.{8}/g).map(b => parseInt(b, 2)));
    const msg = Buffer.alloc(8); msg.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const h = crypto.createHmac("sha1", key).update(msg).digest(); const o = h[19] & 15;
    return String((h.readUInt32BE(o) & 0x7fffffff) % 1000000).padStart(6, "0");
  }
  const say = (ok, m) => { console.log((ok ? "OK   " : "FAIL ") + m); if (!ok) process.exitCode = 1; };
  (async () => {
    const b = await chromium.launch(); const p = await (await b.newContext({ baseURL: process.env.SQS_URL })).newPage();
    const errors = []; p.on("pageerror", e => errors.push(e.message));
    await p.goto("/"); await p.locator("#userlog").fill("admin"); await p.locator("input[type=password]").fill(process.env.SQS_PASS);
    await Promise.all([p.waitForLoadState("networkidle"), p.locator("button[type=submit], input[type=submit]").first().click()]);
    say(/TwoFactorLogin/.test(p.url()), "после пароля — страница кода 2FA");
    await p.locator("input[autocomplete=one-time-code]").fill(totp(process.env.SQS_SECRET));
    await Promise.all([p.waitForLoadState("networkidle"), p.locator("button[type=submit], input[type=submit]").first().click()]);
    say(await p.locator("#logout").first().waitFor({ state: "attached", timeout: 15000 }).then(() => true, () => false), "вход с кодом 2FA");
    say((await p.locator("#sqs-version").innerText()).includes(process.env.SQS_VER), "версия внизу меню: " + process.env.SQS_VER);
    const bad = async () => { const t = await p.locator("body").innerText(); return /Fatal error|Uncaught|Невірний клас|Exception/.test(t) || /Pages\/Error/.test(p.url()) ? t.slice(0, 300) : ""; };
    const open = async (kind, list) => {
      for (const n of list.split(",").filter(Boolean)) {
        await p.goto("/index.php?p=App/Pages/" + kind + "/" + n); await p.waitForLoadState("networkidle");
        const e = await bad(); say(!e, kind + "/" + n + (e ? ": " + e : ""));
      }
    };
    await open("Report", process.env.SQS_REPORTS);
    await open("Service", process.env.SQS_SERVICES);
    await p.goto("/index.php?p=App/Pages/Service/AppUpdate"); await p.waitForLoadState("networkidle");
    say((await p.locator("#appupdate-history").innerText()).includes(process.env.SQS_VER), "история обновлений: запись о новой версии");
    if (process.env.SQS_CLEAN == "1") {
      const n = Number(await p.locator("#appupdate-stale-n").innerText());
      say(n > 0, "страница «Обновление программы»: файлы старых версий — " + n);
      say(await p.locator("#appupdate-broken").count() == 0, "пакет распакован полностью (нет недостающих и измененных)");
      let asked = false; p.once("dialog", d => { asked = true; d.accept(); });
      await Promise.all([p.waitForLoadState("networkidle"), p.locator("input[name=clean], #clean").first().click()]);
      const okNow = await p.locator("#appupdate-ok").waitFor({ state: "attached", timeout: 30000 }).then(() => true, () => false);
      say(okNow, "после удаления: файлы совпадают с пакетом" + (okNow ? "" : ": " + (await p.locator(".main").innerText()).slice(0, 1500)));
      say(asked, "перед удалением — подтверждение");
      await open("Report", process.env.SQS_REPORTS);
      await open("Service", process.env.SQS_SERVICES);
    }
    say(errors.length == 0, "ошибок JavaScript нет" + (errors.length ? ": " + errors.join("; ") : ""));
    await b.close();
  })();') || fail "проверки в браузере"

if [[ $MODE == hosting ]]; then
  left=0; for f in "${oldonly[@]}"; do dx test -e "$SITE/$f" && left=$((left + 1)); done
  [[ $left == 0 ]] && ok "остатков старой версии нет (${#oldonly[@]} файлов удалены кнопкой)" || fail "остатки старой версии: $left"
  dx sh -c "grep -h 'SquirrelStock version' $SITE/logs/*.txt" | grep -q -- "-> $newver" && ok "журнал программы: смена версии" || fail "в журнале нет смены версии"
  # без config.php (удалили по ошибке, первая установка) — понятное сообщение, а не пустая страница
  dx mv $SITE/config/config.php /root/config.php
  msg=$(curl -s -w ' %{http_code}' "http://127.0.0.1:$PORT/" || true)
  dx mv /root/config.php $SITE/config/config.php
  [[ $msg == *"config.example.php"*" 503" ]] && ok "без config/config.php: сообщение и код 503" || fail "без config.php: $msg"
fi
after=$(digest)
[[ $before == "$after" ]] && ok "данные старой версии те же (документы, договоры, единицы, остатки, пользователи, 2FA, настройки)" || { diff <(echo "$before") <(echo "$after") || true; echo "keyval (ключи с другим значением):"; diff <(cat "$tmp/keyval-before") <(sql "select keyd, md5(vald) from keyval order by keyd") | grep '^[<>]' || true; fail "данные изменились"; }
missing=
for f in $(dx sh -c "ls $SITE/app/sqs/schema.sql $SITE/app/sqs/modules/*/schema.sql"); do
  for t in $(dx sed -n 's/^CREATE TABLE IF NOT EXISTS \([a-z0-9_]*\).*/\1/p' "$f"); do
    [[ $(sql "select count(*) from information_schema.tables where table_schema = 'squirrelstock' and table_name = '$t'") == 1 ]] || missing+=" $t"
  done
done
[[ -z $missing ]] && ok "таблицы новой версии созданы" || fail "нет таблиц:$missing"
[[ $(sql "select vald from keyval where keyd = 'sqs_version'") == "$newver" ]] && ok "версия в базе: $newver" || fail "версия в базе"
dx test -f $SITE/upload/test-update.gif && ok "upload/ на месте" || fail "upload/"
n0=$(dx sh -c 'ls /srv/cloud | wc -l')
out=$(dx runuser -u www-data -- php $SITE/cli/backup.php 2>&1) && ok "cli/backup.php новой версии: код 0" || { echo "$out" | tail -10; sql "select place, ok, message from sqs_backuplog order by id desc limit 3"; fail "cli/backup.php"; }
[[ $(dx sh -c 'ls /srv/cloud | wc -l') -gt $n0 ]] && ok "копия в облаке (настройка старой версии)" || fail "копии в облаке нет"
echo "--- все проверки пройдены ($MODE, $OLD -> $newver)"
