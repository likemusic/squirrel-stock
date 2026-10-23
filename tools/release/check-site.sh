#!/usr/bin/env bash
# Проверка уже выложенного сайта SquirrelStock (docs/INSTALL_HOSTING.ru.md, раздел 9): HTTPS, заголовки безопасности,
# закрытые служебные файлы, cookie сессии, не раскрываются ли версия PHP и пути, вход и двухфакторный вход, отключены ли
# обновления Zippy. Только чтение: запросы GET к этому сайту и, если задан пароль, один вход и выход. Нужны bash и curl.
#
#   tools/release/check-site.sh https://stock.example.org
#   SQS_CHECK_PASS='пароль' tools/release/check-site.sh https://stock.example.org     # и проверки после входа
#
# Переменные окружения:
#   SQS_CHECK_USER — логин для проверки входа (по умолчанию admin);
#   SQS_CHECK_PASS — его пароль; не задан — проверки после входа пропускаются. Неверный пароль считается программой
#                    как неудачная попытка входа;
#   SQS_CHECK_DELAY — пауза между запросами в секундах (по умолчанию 0.2): у хостинга бывает ограничение частоты запросов
#                    («защита от DDoS»), при превышении он отвечает 503 или рвёт соединение; такие ответы повторяются до 3 раз;
#   CURL_CA_BUNDLE — свой корневой сертификат (самоподписанный на тестовом стенде), стандартная переменная curl.
# Код выхода: 0 — ошибок нет (предупреждения возможны), 1 — есть ошибки, 2 — неверный запуск.
set -uo pipefail

base=${1:-}
base=${base%/}
if [[ ! $base =~ ^(https?)://([^/?#]+)$ ]]; then
  echo "запуск: $0 https://адрес-сайта   (корень сайта, без пути)" >&2
  exit 2
fi
scheme=${BASH_REMATCH[1]}
host=${BASH_REMATCH[2]}
https=0; [[ $scheme == https ]] && https=1

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
jar=$tmp/cookies
ok=0 err=0 warn=0 skip=0
OK()   { ok=$((ok + 1));     echo "OK            $*"; }
ERR()  { err=$((err + 1));   echo "ОШИБКА        $*"; }
WARN() { warn=$((warn + 1)); echo "ПРЕДУПРЕЖДЕНИЕ $*"; }
SKIP() { skip=$((skip + 1)); echo "пропущено     $*"; }
section() { echo; echo "== $*"; }

delay=${SQS_CHECK_DELAY:-0.2}
# GET без перехода по перенаправлениям (запросы только к этому сайту): заголовки в $tmp/h, тело в $tmp/b, код в $code,
# код выхода curl в $cexit. Пауза перед каждым запросом; 503 и сбой соединения (нет связи, таймаут, обрыв TLS) — повтор
# через 2 и 4 с: так срабатывает ограничение частоты запросов на хостинге
get() {
  local try
  for try in 1 2 3; do
    sleep "$delay"
    : > "$tmp/b"; : > "$tmp/h"
    cexit=0
    code=$(curl -sS --max-time 30 -o "$tmp/b" -D "$tmp/h" -w '%{http_code}' -b "$jar" -c "$jar" "$@" 2>"$tmp/e") || cexit=$?
    (( cexit )) && code=000
    [[ $code == 503 ]] || [[ $cexit =~ ^(6|7|28|35|56)$ ]] || return 0
    (( try < 3 )) && sleep $((try * 2))
  done
}
# сервер оборвал соединение, не ответив (nginx «return 444»; по HTTP/2 curl видит это как ошибку протокола)
dropped() { [[ $code == 000 && $cexit =~ ^(52|92)$ ]]; }
summary() { echo; echo "Итого: OK $ok, ошибок $err, предупреждений $warn, пропущено $skip"; (( err == 0 )); }
header() { grep -i "^$1:" "$tmp/h" | head -1 | sed -E 's/^[^:]+:[[:space:]]*//; s/\r$//'; }

echo "Проверка сайта $base"

section "1. HTTPS"
if (( https )); then
  if curl -sS --max-time 30 -o /dev/null "$base/" 2>"$tmp/e"; then
    OK "сертификат действителен для $host"
    exp=$(curl -sv --max-time 30 -o /dev/null "$base/" 2>&1 | sed -nE 's/^\*[[:space:]]+expire date:[[:space:]]*(.*)$/\1/p' | head -1)
    if [[ -n $exp ]] && end=$(date -d "$exp" +%s 2>/dev/null); then
      days=$(( (end - $(date +%s)) / 86400 ))
      if (( days < 14 )); then WARN "сертификат истекает через $days дн. ($exp): проверьте автопродление Let's Encrypt в панели"
      else OK "сертификат действует ещё $days дн."; fi
    fi
  else
    ERR "сертификат: $(head -1 "$tmp/e") — остальное без действительного сертификата не проверить (самоподписанный на тестовом стенде — CURL_CA_BUNDLE)"
    summary; exit
  fi
  r=$(curl -s --max-time 30 -o /dev/null -w '%{http_code} %{redirect_url}' "http://$host/") || r="000 "
  if [[ $r =~ ^30[1278]\ https://${host//./\\.}(:443)?(/|$) ]]; then OK "HTTP перенаправляет на HTTPS (${r%% *})"
  else ERR "HTTP не перенаправляет на HTTPS (ответ: ${r:-нет}): включите «Перенаправлять HTTP-запросы в HTTPS» (INSTALL_HOSTING 2.2)"; fi
else
  WARN "сайт открыт по HTTP: в интернете — только по HTTPS (INSTALL_HOSTING 6)"
  SKIP "сертификат и перенаправление HTTP → HTTPS: не HTTPS"
fi

section "2. Заголовки безопасности (страница входа)"
get "$base/index.php?p=App/Pages/UserLogin"
if [[ $code == 000 ]]; then
  ERR "сайт не отвечает: $(head -1 "$tmp/e")"
  summary; exit
elif [[ $code != 200 ]]; then
  ERR "страница входа ответила $code"
else
  csp=$(header Content-Security-Policy)
  if [[ $csp == *"default-src 'self'"* && $csp == *"frame-ancestors"* ]]; then OK "Content-Security-Policy"
  elif [[ -n $csp ]]; then WARN "Content-Security-Policy не такая, как в .htaccess пакета: $csp"
  else ERR "нет Content-Security-Policy: .htaccess не из пакета или не работает mod_headers (INSTALL_HOSTING 6)"; fi
  xfo=$(header X-Frame-Options)
  if [[ ${xfo^^} == SAMEORIGIN || ${xfo^^} == DENY ]]; then OK "X-Frame-Options: $xfo"; else ERR "X-Frame-Options: ${xfo:-нет}"; fi
  nos=$(header X-Content-Type-Options)
  if [[ ${nos,,} == nosniff ]]; then OK "X-Content-Type-Options: nosniff"; else ERR "X-Content-Type-Options: ${nos:-нет}"; fi
  rp=$(header Referrer-Policy)
  if [[ -n $rp && ${rp,,} != unsafe-url && ${rp,,} != no-referrer-when-downgrade ]]; then OK "Referrer-Policy: $rp"; else ERR "Referrer-Policy: ${rp:-нет}"; fi
  if (( https )); then
    hsts=$(header Strict-Transport-Security)
    if [[ -n $hsts ]]; then OK "Strict-Transport-Security: $hsts"
    else WARN "нет Strict-Transport-Security (HSTS). Когда HTTPS работает, добавьте в .htaccess внутри <IfModule mod_headers.c>: Header always set Strict-Transport-Security \"max-age=31536000\" (INSTALL_HOSTING 6)"; fi
  else
    SKIP "Strict-Transport-Security: не HTTPS"
  fi
  xpb=$(header X-Powered-By); srv=$(header Server)
  if [[ -n $xpb ]]; then ERR "X-Powered-By: $xpb — версия PHP видна (expose_php = Off, INSTALL_HOSTING 6)"; else OK "нет X-Powered-By"; fi
  if [[ $srv =~ PHP/[0-9] ]]; then WARN "Server: $srv — в нём версия PHP"; fi
fi

section "3. Служебные файлы закрыты"
closed=(config/config.php config/ app/system.php app/ templates/pages/base.html templates/ logs/ cli/ cli/backup.php
  vendor/autoload.php vendor/composer/installed.json composer.json composer.lock init.php lang/ru.json lang/uk.json
  upload/ upload/sqs-check.txt upload/sqs-check.php .htaccess upload/.htaccess db/ db/db.sql script_examples.zip README.md)
bad=0 drop=()
for p in "${closed[@]}"; do
  get "$base/$p"
  if dropped; then drop+=("/$p"); continue; fi
  case $code in
    403|404) ;;
    200) bad=1
         if grep -q '\$_config' "$tmp/b"; then ERR "/$p отдаётся (200) — виден файл с паролем базы! Нужен Apache с .htaccess (INSTALL_HOSTING 0, 8)"
         else ERR "/$p отдаётся (200), должен быть 403 или 404"; fi ;;
    *) bad=1; WARN "/$p: ответ $code (ждали 403 или 404)" ;;
  esac
done
(( bad )) || OK "${#closed[@]} служебных адресов не отдаются (403 или 404${drop:+; у ${#drop[@]} — обрыв соединения без ответа})"
(( ${#drop[@]} )) && echo "              сервер обрывает соединение без ответа (так делает nginx хостинга, содержимое не отдаётся): ${drop[*]}"
bad=0
for p in assets/css/app.css vendor/leon-mbs/zippy/assets/css/zippy-bundle.css vendor/leon-mbs/zippy/assets/js/jquery.js; do
  get "$base/$p"
  [[ $code == 200 ]] || { bad=1; ERR "/$p: $code — страницам нужен этот файл"; }
done
(( bad )) || OK "файлы, нужные страницам (css, js), открываются"

section "4. Cookie сессии"
rm -f "$jar"
get "$base/"
ck=$(grep -i '^set-cookie:[[:space:]]*PHPSESSID=' "$tmp/h" | head -1 | tr -d '\r')
if [[ -z $ck ]]; then
  ERR "при открытии сайта не выдана cookie сессии PHPSESSID (ответ $code)"
else
  [[ ${ck,,} == *"; httponly"* ]] && OK "HttpOnly" || ERR "у cookie сессии нет HttpOnly (session.cookie_httponly = On)"
  if [[ ${ck,,} =~ \;[[:space:]]*samesite=(lax|strict) ]]; then OK "SameSite"; else ERR "у cookie сессии нет SameSite=Lax (session.cookie_samesite = Lax)"; fi
  if (( https )); then
    [[ ${ck,,} == *"; secure"* ]] && OK "Secure" || ERR "у cookie сессии нет Secure (session.cookie_secure = On)"
  else
    SKIP "Secure: не HTTPS"
  fi
fi

section "5. Ошибки PHP не показываются"
leak='/var/www|/home/[a-z0-9_]+/|[A-Za-z]:\\\\|on line <b>|on line [0-9]+|Stack trace|Fatal error|Uncaught |Warning</b>:|Notice</b>:|Deprecated</b>:'
bad=0
for p in sqs-check-no-such-page sqs-check-no-such.php "index.php?p=App/Pages/SqsCheckNoSuch" "index.php?q=p:App/Pages/SqsCheckNoSuch::x"; do
  get "$base/$p"
  if m=$(grep -oE "$leak" "$tmp/b" | head -1) && [[ -n $m ]]; then bad=1; ERR "/$p ($code): в ответе «$m» — выключите display_errors (INSTALL_HOSTING 6)"; fi
done
(( bad )) || OK "несуществующие страницы не показывают путей и текста ошибок PHP"

section "6. Вход"
user=${SQS_CHECK_USER:-admin}
logged=0
if [[ -z ${SQS_CHECK_PASS:-} ]]; then
  SKIP "вход, двухфакторный вход, обновления Zippy: не задан SQS_CHECK_PASS"
else
  rm -f "$jar"
  get "$base/index.php?p=App/Pages/UserLogin"
  q=$(grep -o 'name="q" id="loginform_q" value="[^"]*"' "$tmp/b" | sed 's/.*value="//; s/"$//')
  if grep -q 'name="capchacode"' "$tmp/b"; then
    SKIP "вход: на странице входа капча, скрипт её не вводит"
  elif [[ -z $q ]]; then
    ERR "на странице входа ($code) не найдена форма входа"
  else
    get -X POST -H "Origin: $base" -H "Referer: $base/index.php?p=App/Pages/UserLogin" \
      --data-urlencode "q=$q" --data-urlencode "loginform_q=" --data-urlencode "userlogin=" \
      --data-urlencode "userlog=$user" --data-urlencode "userpasswo=$SQS_CHECK_PASS" "$base/index.php"
    loc=$(header Location)
    if [[ $loc == *TwoFactorLogin* ]]; then
      OK "пароль принят, у $user включён двухфакторный вход (дальше нужен код — проверки после входа пропущены)"
    else
      get -H "Referer: $base/" "$base/"
      if grep -q 'id="logout"' "$tmp/b"; then
        OK "вход под $user"
        logged=1
        WARN "у $user не включён двухфакторный вход: «Профиль → Двухфакторный вход» (INSTALL_HOSTING 6)"
      else
        ERR "вход под $user не удался (неверный пароль, пользователь заблокирован после неудачных попыток или программа не работает)"
      fi
    fi
  fi
fi

section "7. Обновления Zippy отключены"
if (( logged )); then
  if grep -q 'App/Pages/Update' "$tmp/b"; then ERR "на главной есть ссылка на обновление Zippy"; else OK "на главной нет ссылки на обновление Zippy"; fi
  get -H "Referer: $base/" "$base/index.php?p=App/Pages/Update"
  loc=$(header Location)
  if [[ -n $loc && $loc != http* ]]; then loc="$base/${loc#/}"; fi
  if [[ -n $loc && $loc == "$base/"* ]]; then get -H "Referer: $base/" "$loc"; fi
  if grep -qE 'zippy\.com\.ua (в|in) SquirrelStock' "$tmp/b"; then OK "страница обновления Zippy: «отключено»"
  elif grep -qE 'доступ (тільки|только) адміністратор|доступ только администратор' "$tmp/b"; then SKIP "страница обновления открывается только admin, проверка под $user невозможна"
  else ERR "страница обновления Zippy не сообщает, что обновления отключены (ответ $code)"; fi
  # выход: ссылка «Выйти» — событие logout на текущей странице
  get -H "Referer: $base/" "$base/"
  out=$(grep -o 'id="logout"[^>]*' "$tmp/b" | grep -o "q=[^'\"&]*" | head -1)
  [[ -n $out ]] && get -H "Referer: $base/" "$base/index.php?$out"
  get "$base/"
  grep -q 'id="logout"' "$tmp/b" && WARN "выход после проверки не выполнен: сессия истечёт сама"
else
  SKIP "обновления Zippy: нужен успешный вход без двухфакторного кода (SQS_CHECK_PASS)"
fi

summary
