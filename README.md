# SquirrelStock

Lightweight web-based inventory and warehouse management in PHP. Keeps stock the way a squirrel keeps its supplies: knows what is stored where, and stays quick.

**SquirrelStock is an unofficial fork of [Zippy](https://zippy.com.ua/) ([leon-mbs/zstore](https://github.com/leon-mbs/zstore)) by Leon, MIT License.** It is not affiliated with or endorsed by the original author. The original README is kept in [README.zippy.md](README.zippy.md) (Ukrainian).

## Why a fork

The fork is developed for charities and non-profits that hand out supplies and equipment free of charge (first user: a children's hospice). Planned changes:

- fixes for stock accounting reliability (double posting, batch order by expiry date, import date and others);
- interface translation with English keys (`uk`, `ru`, later `be` and `en`);
- full offline operation (no external scripts or CDNs);
- equipment lending: who has each unit now, contracts, returns, repairs;
- reports for free issue of supplies: write-off act, recipient × month, item and recipient movements for a period;
- regression tests.

Status (October 2026), based on upstream commit `c74df59f4` (13.10.2026):

- fixed: double posting (repeated "Execute", browser "Back"), batch with the nearest expiry date, division by zero in the sales report, item autocomplete order, document date in import, amount in words currency;
- interface by English keys with Ukrainian and Russian dictionaries, works offline;
- for charities: monthly issue act for the accounting, recipients by month, item turnover, equipment by contracts ("where each unit is now", issue journal), import of the 1C trial balance, backup and restore in one click, a menu profile;
- regression tests (Playwright) for all of the above; changes to upstream files are listed in [docs/CORE_CHANGES.md](docs/CORE_CHANGES.md).

Not yet: Windows installer, audit log of document edits, extension hooks.

## Development

Local stand (Docker, PHP 8.2 + MariaDB 10.11), code is taken from `www/` as is:

```bash
cd docker && docker compose up -d --build        # http://127.0.0.1:8083, login admin / admin
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d   # mount www/app, templates, assets for live editing
docker compose -f docker-compose.yml -f docker-compose.offline.yml up -d   # no internet for web and db (offline check)
docker compose down -v                           # stop and drop the database
```

Port and Docker subnet can be changed with `SQS_PORT` and `SQS_SUBNET`.

Regression tests (Playwright, through the UI; external requests are blocked so that offline problems show up):

```bash
cd tests && npm ci && npx playwright install chromium
npm test                                          # SQS_BASE=http://127.0.0.1:8083 by default
```

Tests create their own data with unique names and can run against a non-empty database. Bugs are reproduced with a test first, then fixed.

## Interface languages

Interface texts are English keys: `__('key')` in PHP, `{{#t}}key{{/t}}` in templates (`{|#t|}key{|/t|}` where a template switches Mustache delimiters for Vue). Texts are in `www/lang/<lang>.json`: `uk` (the original Ukrainian text of Zippy) and `ru`. The language is chosen in the user profile; the default is `$_config['common']['lang']` in `config/config.php` (Docker stand: `SQS_LANG`).

Default names stored in the database (documents and menu items, main store, cash desk, price types) are translated once per installation:

```bash
php cli/localize-db.php ru          # in Docker: docker exec squirrelstock-web-1 php cli/localize-db.php ru
php cli/localize-db.php ru --back   # back to Ukrainian
```

Tools in `tools/i18n/`: `convert.php` replaces Ukrainian strings with keys (used on every upstream update), `pairs.php` collects translation pairs from the Russian edition of Zippy, `merge_tsv.py` builds a dictionary from `key<TAB>text` files and checks placeholders and quotes.

## Русский

Руководство по настройке и работе: [docs/GUIDE.ru.md](docs/GUIDE.ru.md).

SquirrelStock — неофициальный форк складской программы Zippy (leon-mbs/zstore, лицензия MIT) для благотворительных организаций, которые бесплатно выдают расходные материалы и оборудование. Первый пользователь — детский хоспис. В планах: исправления надёжности учёта, перевод интерфейса на русский (ключи на английском), работа без интернета, учёт выдачи оборудования по договорам, отчёты для бухгалтерии, автотесты.

## License

MIT, see [LICENSE](LICENSE). Copyright (c) 2018 Leon (original Zippy code), (c) 2026 SquirrelStock contributors.
