#!/usr/bin/env bash
# Сборка SquirrelStock для установки без Docker (хостинг с Apache, свой сервер):
#   dist/squirrel-stock-<версия>.zip       — содержимое корня сайта: код из git (HEAD) + vendor + config/config.example.php
#                                            для заполнения (не config.php: распаковка нового пакета поверх не затирает настройки),
#                                            config/version.txt (версия) и config/manifest.txt (файлы пакета с md5 — по нему
#                                            «Сервис → Обновление программы» находит остатки старых версий и недораспакованное)
#   dist/squirrel-stock-<версия>-empty.sql — пустая база: db/db.sql + www/app/sqs/schema.sql
#   dist/squirrel-stock-<версия>-demo.sql  — демо-база с вымышленными данными (db/demo/squirrelstock-demo.zip), вход admin / admin
# Нужны git, docker, python3. Зависимости composer берутся из образа docker/Dockerfile.
set -euo pipefail
cd "$(dirname "$0")/../.."
# версия: дата коммита и его номер — по ней видно, что новее (2026.10.16-d2489fb22)
ver=$(git log -1 --format=%cd --date=format:%Y.%m.%d HEAD)-$(git rev-parse --short=9 HEAD)
[[ -z $(git status --porcelain --untracked-files=no) ]] || ver=$ver-dirty
[[ $ver == *-dirty ]] && echo "внимание: в рабочей копии есть незакоммиченные изменения, в архив идёт HEAD" >&2
out=dist
stage=$(mktemp -d)
trap 'rm -rf "$stage"; docker rm -f sqs-release >/dev/null 2>&1 || true' EXIT

docker build -q -f docker/Dockerfile -t squirrelstock-web:release . >/dev/null
git archive HEAD www | tar -x -C "$stage"
rm -f "$stage/www/script_examples.zip"   # примеры скриптов автора Zippy на сайте не нужны
docker create --name sqs-release squirrelstock-web:release >/dev/null
docker cp -q sqs-release:/var/www/html/vendor "$stage/www/vendor"
rm -f "$stage/www/config/config.php"   # настройки стенда Docker
cp tools/release/config.php "$stage/www/config/config.example.php"
echo "${ver%-dirty}" > "$stage/www/config/version.txt"   # в архиве HEAD — без «-dirty»
(cd "$stage/www" && find . -type f ! -path ./config/manifest.txt -printf '%P\n' | LC_ALL=C sort | xargs -d '\n' md5sum > "$stage/manifest.txt")
mv "$stage/manifest.txt" "$stage/www/config/manifest.txt"

mkdir -p "$out"
rm -f "$out/squirrel-stock-$ver.zip"
python3 -I - "$stage/www" "$out/squirrel-stock-$ver.zip" <<'PY'
import os, sys, zipfile
root, dst = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(dst, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for d, dirs, files in os.walk(root):
        dirs.sort()
        rel = os.path.relpath(d, root)
        if rel != '.':
            z.write(d, rel + '/')
        for f in sorted(files):
            z.write(os.path.join(d, f), os.path.normpath(os.path.join(rel, f)))
PY
cat db/db.sql www/app/sqs/schema.sql > "$out/squirrel-stock-$ver-empty.sql"
unzip -p db/demo/squirrelstock-demo.zip database.sql > "$out/squirrel-stock-$ver-demo.sql"

# пакет ставится в интернет: вместо admin / admin — случайный пароль (печатается один раз) и длинная случайная «соль»
# (в db/demo пароль остается admin — для знакомства на своем компьютере)
pass=$(python3 -I -c 'import secrets; print(secrets.token_urlsafe(12))')
hash=$(printf '%s' "$pass" | docker run --rm -i squirrelstock-web:release php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);')
salt=$(python3 -I -c 'import secrets; print(secrets.token_hex(32))')
# строки — сразу после записи пользователей, а не в конец файла: импорт, оборванный на триггерах (#1419, INSTALL_HOSTING 3.4),
# не должен оставить в базе пароль admin из db/demo
for f in "$out/squirrel-stock-$ver-empty.sql" "$out/squirrel-stock-$ver-demo.sql"; do
  python3 -I - "$f" "$hash" "$salt" <<'PY'
import re, sys
f, hash, salt = sys.argv[1:]
lines = open(f, encoding='utf-8').read().split('\n')
i = next(k for k, s in enumerate(lines) if re.match(r'INSERT INTO `?users`? ', s))   # нет записи пользователей — сборка останавливается
while not lines[i].rstrip().endswith(';'):
    i += 1
lines[i + 1:i + 1] = ['', '-- пакет: пароль admin и соль, созданные при сборке', f"UPDATE users SET userpass = '{hash}' WHERE userlogin = 'admin';",
                      f"REPLACE INTO keyval (keyd, vald) VALUES ('salt', '{salt}');", '']
open(f, 'w', encoding='utf-8').write('\n'.join(lines))
PY
done
(cd "$out" && sha256sum "squirrel-stock-$ver.zip" "squirrel-stock-$ver-empty.sql" "squirrel-stock-$ver-demo.sql" > "squirrel-stock-$ver.sha256")
ls -lh "$out"/squirrel-stock-"$ver"*
echo
echo "Пароль admin в пакете (обе базы): $pass"
echo "Запишите его: он нигде не сохраняется и больше не показывается."
