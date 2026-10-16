#!/usr/bin/env python3
"""Ссылки между документами: битые ссылки [текст](путь) в *.md репозитория и пути в обратных кавычках (`docs/...`, `tools/...`,
`tests/...`, `docker/...`, `db/...`, `www/...`), которых нет в рабочей копии. Внешние адреса не проверяются;
пути в docs/CORE_CHANGES.md — тоже: это история, там пути на момент правки.
Запуск из корня репозитория: python3 tools/release/check-doc-links.py; код выхода 1, если что-то не найдено."""
import os
import re
import subprocess
import sys

root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
files = subprocess.run(['git', '-C', root, 'ls-files', '--cached', '--others', '--exclude-standard', '*.md'],
                       capture_output=True, text=True, check=True).stdout.split()
bad = 0
for f in sorted(set(files)):
    if f.startswith('www/vendor/') or '/node_modules/' in f or f.startswith('tests/node_modules/'):
        continue
    p = os.path.join(root, f)
    if not os.path.exists(p):
        continue
    text = re.sub(r'```.*?```', '', open(p, encoding='utf8').read(), flags=re.S)
    for m in re.finditer(r'!?\[[^\]]*\]\(([^)\s]+)\)', text):
        url = m.group(1)
        if re.match(r'[a-z]+:', url) or url.startswith('#'):
            continue
        if not os.path.exists(os.path.normpath(os.path.join(os.path.dirname(p), url.split('#')[0]))):
            print(f'{f}: битая ссылка {url}')
            bad += 1
    if f == 'docs/CORE_CHANGES.md':
        continue
    for m in re.finditer(r'`((?:docs|tools|tests|docker|db|www)/[^`\s*<>]+)`', text):
        if not os.path.exists(os.path.join(root, m.group(1).rstrip('/'))):
            print(f'{f}: нет пути {m.group(1)}')
            bad += 1
print('не найдено:', bad)
sys.exit(1 if bad else 0)
