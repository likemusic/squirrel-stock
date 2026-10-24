# Development

How to run SquirrelStock locally, run the tests and work with translations. Overview of the project: [README.md](../README.md); list of documents: [README.md](README.md).

## Local stand

Docker, PHP 8.4 + MariaDB 11.4 (the versions are set in one line each: `ARG PHP_VERSION` in `docker/Dockerfile`, `SQS_MARIADB` in `docker/docker-compose.yml`; PHP 8.2 is still supported and tested). A stand whose database was created by MariaDB 10.11 is upgraded in place on the first start with 11.4 (`MARIADB_AUTO_UPGRADE`; checked on a stand with test data); going back to 10.11 needs a new database or a restore from a backup; the code is taken from `www/` as is. On first start the database is created from `db/db.sql` (the Zippy database) and `www/app/sqs/schema.sql` (SquirrelStock tables); module tables and menu items are added by the program itself ([MODULES.md](MODULES.md)).

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

## Release

1. `tools/release/build.sh` — the package `dist/squirrel-stock-<version>.zip` with `-empty.sql` and `-demo.sql` ([INSTALL_HOSTING.ru.md](INSTALL_HOSTING.ru.md)).
2. `tools/release/accept.sh dist/squirrel-stock-<version>.zip` — acceptance of this very package before it goes to a server. A clean copy of a hosting: Apache + PHP 8.4 without stand settings, `open_basedir` limited to the site root, `upload_tmp_dir` and the backup folder (no `/tmp`), a separate MariaDB 11.4 with the binary log and an ordinary database user; before the checks a temporary file in the site root confirms through Apache that these settings are in effect and `/tmp` is not reachable, otherwise the acceptance fails; `--limits` adds the hosting PHP defaults fixed with `php_admin_value` (`max_execution_time` 30, `memory_limit` 128M); the package is unpacked into the site root, `-demo.sql` is loaded with the console client, the warehouse role and user `sklad` are created (`cli/role-warehouse.php`). Then `check-site.sh` and `tests/accept/demo.spec.js` (not part of `npm test`) walk the demo scenario as `admin` and as `sklad`: the main page, every menu item, consumables and equipment issue, return, extension, the act in Excel, PDF, backup and restore of that backup; closed pages for `sklad`. Screenshots of the main page and the menu of both roles are in `tests/test-results/accept/shots/`. `--keep` leaves the containers for a manual look (port `SQS_TEST_PORT`, 8096 by default; the passwords are printed at the end).
3. Only an accepted package is handed over for deployment. Updating an existing installation is checked by `tools/install/test-update.sh hosting --zip=…`.

## Interface languages

Interface texts are English keys: `__('key')` in PHP, `{{#t}}key{{/t}}` in templates (`{|#t|}key{|/t|}` where a template switches Mustache delimiters for Vue). Texts are in `www/lang/<lang>.json`: `uk` (the original Ukrainian text of Zippy) and `ru`; modules keep their keys in `lang/<lang>.json` of the module. The language is chosen in the user profile; the default is `$_config['common']['lang']` in `config/config.php`.

Default names stored in the database (documents and menu items, main store, cash desk, price types) are translated once per installation:

```bash
php cli/localize-db.php ru          # Docker: docker exec -u www-data squirrelstock-web-1 php cli/localize-db.php ru
php cli/localize-db.php ru --back   # back to Ukrainian
```

Tools in `tools/i18n/` (PHP 8.1+ on the host): `convert.php` replaces Ukrainian strings with keys (used on every upstream update), `pairs.php` collects translation pairs from the Russian edition of Zippy, `merge_tsv.py` builds a dictionary from `key<TAB>text` files and checks placeholders and quotes, `check-keys.php` and `rename.php` — see below.

**New code uses meaningful keys only.** A key names the place and the meaning: `area.page.name`, for example `sqs.report.pdf_too_big`, `service.backup.place_local`, `sqs.eqhist.col_recipient`. Do not write a transliteration of the Ukrainian text (`app.base.drukuvaty`): such keys are drafts that `convert.php` makes for the author's code, the ~3 900 of them inherited from Zippy are listed in `tools/i18n/translit-keys.txt` and will be renamed after the merge with Zippy 8.3.0. `php tools/i18n/check-keys.php` fails on any transliterated key in `lang/*.json` (main and modules) that is not in the list; the test is `tests/specs/i18n-keys.spec.js`. After an upstream update `convert.php` adds new draft keys: rename them with `rename.php`, or, consciously, add them to the list with `php tools/i18n/check-keys.php --update` (also for an English word that happens to equal the transliteration, like `model` or `period`).

`php tools/i18n/rename.php --map=map.tsv [--dry-run]` renames keys by a map of `old<TAB>new` lines: string literals equal to the key in PHP, keys inside `{{#t}}…{{/t}}` in templates, keys in all dictionaries (in place) and in `translit-keys.txt`. It writes nothing and lists the reasons if a new name is taken or is the beginning of another key (or the other way round), if the map has a duplicate or a key missing from the dictionaries, if the code builds a key from a piece the old or new name starts with (`__('sqs.eqhist.kind_' . $kind)`), or if the result does not pass the check: every changed file rendered through `uk.json` and `ru.json` (key → text) is byte-for-byte the same as before, dictionaries keep their texts and lose exactly the merged keys (two old keys may become one only with equal `uk` and `ru` texts), no old key is left in the code. The test runs it on a copy of the code with `tests/fixtures/i18n-rename-map.tsv` (10 keys) there and back.

## Code layout

- `www/` — the site root (Zippy code plus SquirrelStock); `www/app/sqs/` — SquirrelStock platform code, `www/app/sqs/modules/` — modules ([MODULES.md](MODULES.md)), `www/cli/` — console scripts.
- Every change to files inherited from Zippy is a separate commit listed in [CORE_CHANGES.md](CORE_CHANGES.md), together with its test.
- `tools/install/` — Linux installer and its container test ([INSTALL_LINUX.ru.md](INSTALL_LINUX.ru.md)), Windows installation check `check-windows.ps1` (PowerShell 5.1, UTF-8 with BOM; [INSTALL_WINDOWS.ru.md](INSTALL_WINDOWS.ru.md)); `tools/release/` — release package `build.sh` ([INSTALL_HOSTING.ru.md](INSTALL_HOSTING.ru.md)) and site check `check-site.sh`. Links between documents: `python3 tools/release/check-doc-links.py`.
- Upstream: remote `upstream` = `leon-mbs/zstore`, base commit `c74df59f4`. On an update take the author's code, run `tools/i18n/convert.php` and reapply the changes from [CORE_CHANGES.md](CORE_CHANGES.md).
