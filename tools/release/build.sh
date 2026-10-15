#!/usr/bin/env bash
# Сборка SquirrelStock для установки без Docker (хостинг с Apache, свой сервер):
#   dist/squirrel-stock-<версия>.zip       — содержимое корня сайта: код из git (HEAD) + vendor + config.php для заполнения
#   dist/squirrel-stock-<версия>-empty.sql — пустая база: db/db.sql + db/squirrelstock.sql
#   dist/squirrel-stock-<версия>-demo.sql  — демо-база с вымышленными данными (db/demo/squirrelstock-demo.zip), вход admin / admin
# Нужны git, docker, python3. Зависимости composer берутся из образа docker/Dockerfile.
set -euo pipefail
cd "$(dirname "$0")/../.."
ver=$(git describe --always --dirty)
[[ $ver == *-dirty ]] && echo "внимание: в рабочей копии есть незакоммиченные изменения, в архив идёт HEAD" >&2
out=dist
stage=$(mktemp -d)
trap 'rm -rf "$stage"; docker rm -f sqs-release >/dev/null 2>&1 || true' EXIT

docker build -q -f docker/Dockerfile -t squirrelstock-web:release . >/dev/null
git archive HEAD www | tar -x -C "$stage"
rm -f "$stage/www/script_examples.zip"   # примеры скриптов автора Zippy на сайте не нужны
docker create --name sqs-release squirrelstock-web:release >/dev/null
docker cp -q sqs-release:/var/www/html/vendor "$stage/www/vendor"
cp tools/release/config.php "$stage/www/config/config.php"

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
cat db/db.sql db/squirrelstock.sql > "$out/squirrel-stock-$ver-empty.sql"
unzip -p db/demo/squirrelstock-demo.zip database.sql > "$out/squirrel-stock-$ver-demo.sql"

# пакет ставится в интернет: вместо admin / admin — случайный пароль (печатается один раз) и длинная случайная «соль»
# (в db/demo пароль остается admin — для знакомства на своем компьютере)
pass=$(python3 -I -c 'import secrets; print(secrets.token_urlsafe(12))')
hash=$(printf '%s' "$pass" | docker run --rm -i squirrelstock-web:release php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);')
salt=$(python3 -I -c 'import secrets; print(secrets.token_hex(32))')
for f in "$out/squirrel-stock-$ver-empty.sql" "$out/squirrel-stock-$ver-demo.sql"; do
  printf "\n-- пакет: пароль admin и соль, созданные при сборке\nUPDATE users SET userpass = '%s' WHERE userlogin = 'admin';\nREPLACE INTO keyval (keyd, vald) VALUES ('salt', '%s');\n" "$hash" "$salt" >> "$f"
done
(cd "$out" && sha256sum "squirrel-stock-$ver.zip" "squirrel-stock-$ver-empty.sql" "squirrel-stock-$ver-demo.sql" > "squirrel-stock-$ver.sha256")
ls -lh "$out"/squirrel-stock-"$ver"*
echo
echo "Пароль admin в пакете (обе базы): $pass"
echo "Запишите его: он нигде не сохраняется и больше не показывается."
