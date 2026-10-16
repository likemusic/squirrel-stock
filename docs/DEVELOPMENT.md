# Development

How to run SquirrelStock locally, run the tests and work with translations. Overview of the project: [README.md](../README.md); list of documents: [README.md](README.md).

## Local stand

Docker, PHP 8.2 + MariaDB 10.11; the code is taken from `www/` as is. On first start the database is created from `db/db.sql` (the Zippy database) and `www/app/sqs/schema.sql` (SquirrelStock tables); module tables and menu items are added by the program itself ([MODULES.md](MODULES.md)).

```bash
cd docker && docker compose up -d --build        # http://127.0.0.1:8083, login admin / admin
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d   # mount www/app, templates, assets for live editing
docker compose -f docker-compose.yml -f docker-compose.offline.yml up -d   # no internet for web and db (offline check)
docker compose down -v                           # stop and drop the database
```

`SQS_PORT` and `SQS_SUBNET` change the port and the Docker subnet, `SQS_LANG` (`uk`, `ru`) the default interface language. Console scripts run as `www-data`: `docker exec -u www-data squirrelstock-web-1 php cli/charity-profile.php`.

## Tests

Playwright, through the UI; external requests are blocked so that offline problems show up:

```bash
cd tests && npm ci && npx playwright install chromium
npm test                                          # SQS_BASE=http://127.0.0.1:8083 by default
npx playwright test specs/backup.spec.js          # one file
```

For another stand: `SQS_BASE`, `SQS_DB_CONTAINER`, `SQS_WEB_CONTAINER`. Tests create their own data with unique names and can run against a non-empty database. Bugs are reproduced with a test first, then fixed.

`tests/tools/`: `seed-demo.js` builds the demo database (`db/demo/`, see [GUIDE.ru.md](GUIDE.ru.md), «Демо-база»), `equipment-volume.js` measures the equipment reports on ~700 fictional units, `dump-texts.js` takes a snapshot of the visible text of all menu pages to compare the interface before and after automatic changes (such as the key converter).

## Interface languages

Interface texts are English keys: `__('key')` in PHP, `{{#t}}key{{/t}}` in templates (`{|#t|}key{|/t|}` where a template switches Mustache delimiters for Vue). Texts are in `www/lang/<lang>.json`: `uk` (the original Ukrainian text of Zippy) and `ru`; modules keep their keys in `lang/<lang>.json` of the module. The language is chosen in the user profile; the default is `$_config['common']['lang']` in `config/config.php`.

Default names stored in the database (documents and menu items, main store, cash desk, price types) are translated once per installation:

```bash
php cli/localize-db.php ru          # Docker: docker exec -u www-data squirrelstock-web-1 php cli/localize-db.php ru
php cli/localize-db.php ru --back   # back to Ukrainian
```

Tools in `tools/i18n/`: `convert.php` replaces Ukrainian strings with keys (used on every upstream update), `pairs.php` collects translation pairs from the Russian edition of Zippy, `merge_tsv.py` builds a dictionary from `key<TAB>text` files and checks placeholders and quotes.

## Code layout

- `www/` — the site root (Zippy code plus SquirrelStock); `www/app/sqs/` — SquirrelStock platform code, `www/app/sqs/modules/` — modules ([MODULES.md](MODULES.md)), `www/cli/` — console scripts.
- Every change to files inherited from Zippy is a separate commit listed in [CORE_CHANGES.md](CORE_CHANGES.md), together with its test.
- `tools/install/` — Linux installer and its container test ([INSTALL_LINUX.ru.md](INSTALL_LINUX.ru.md)), Windows installation check `check-windows.ps1` (PowerShell 5.1, UTF-8 with BOM; [INSTALL_WINDOWS.ru.md](INSTALL_WINDOWS.ru.md)); `tools/release/` — release package `build.sh` ([INSTALL_HOSTING.ru.md](INSTALL_HOSTING.ru.md)) and site check `check-site.sh`. Links between documents: `python3 tools/release/check-doc-links.py`.
- Upstream: remote `upstream` = `leon-mbs/zstore`, base commit `c74df59f4`. On an update take the author's code, run `tools/i18n/convert.php` and reapply the changes from [CORE_CHANGES.md](CORE_CHANGES.md).
