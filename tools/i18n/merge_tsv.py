#!/usr/bin/env python3
"""Собрать lang/<язык>.json из файлов перевода «ключ<TAB>текст» (по одному на строку).

Пробелы по краям берутся из исходного uk.json (в ключах по фразам из кода они значимы: " (Копія)").
Проверка: плейсхолдеры {x}, {{x}}, {{{x}}} и HTML-теги в переводе те же, что в исходном тексте;
расхождения печатаются, такие строки не записываются.

python3 tools/i18n/merge_tsv.py www/lang/ru.json перевод1.tsv [перевод2.tsv ...]
"""
import json, re, sys

target, files = sys.argv[1], sys.argv[2:]
uk = json.load(open(target.rsplit('/', 1)[0] + '/uk.json', encoding='utf-8'))
try:
    out = json.load(open(target, encoding='utf-8'))
except FileNotFoundError:
    out = {}

TOK = re.compile(r'\{\{\{?[^}]*\}?\}\}|\{[a-z_0-9]+\}|</?[a-zA-Z][^>]*>')
def tokens(s):
    return sorted(TOK.findall(s))

bad = 0
added = 0
for f in files:
    for n, line in enumerate(open(f, encoding='utf-8'), 1):
        line = line.rstrip('\n')
        if not line.strip():
            continue
        key, _, text = line.partition('\t')
        if key not in uk:
            print(f'{f}:{n}: нет ключа {key} в uk.json'); bad += 1; continue
        src = uk[key]
        if tokens(src) != tokens(text.strip()):
            print(f'{f}:{n}: {key}: плейсхолдеры/теги не совпадают: {tokens(src)} != {tokens(text)}'); bad += 1; continue
        lead = src[:len(src) - len(src.lstrip())]
        trail = src[len(src.rstrip()):]
        out[key] = lead + text.strip() + trail
        added += 1

out = dict(sorted(out.items()))
json.dump(out, open(target, 'w', encoding='utf-8'), ensure_ascii=False, indent=4)
open(target, 'a', encoding='utf-8').write('\n')
print(f'записано {added}, ошибок {bad}, всего ключей в {target}: {len(out)}')
