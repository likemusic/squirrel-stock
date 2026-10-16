# SquirrelStock

Inventory for charities and non-profits that hand out supplies and equipment free of charge. A PHP + MySQL/MariaDB web application that runs on a single offline computer, in a local network or on shared hosting, and works from a phone.

**SquirrelStock is an unofficial fork of [Zippy](https://zippy.com.ua/) ([leon-mbs/zstore](https://github.com/leon-mbs/zstore)) by Leon, MIT License.** It is not affiliated with or endorsed by the original author. First user: a children's hospice. The interface is in Russian and Ukrainian; the user documentation is in Russian ([README.md](README.md)).

## Features

- Quick issue of consumables (phone friendly), returns based on the issue, issue intervals ("not more often than once in N days") with a warning.
- Equipment lending by contract: units with inventory and factory numbers, "where each unit is now", issue journal, term reminders on the main page and term extension, small equipment by quantity.
- Recipient card: equipment on hand, consumables by month, next allowed issue.
- Reports for the accounting: monthly write-off act, recipients by month, item turnover, below minimum stock; Excel export.
- Import of the 1C trial balance and of equipment issue history from spreadsheets (dry run first, name mapping).
- Backup in one click and on schedule: local folder, network folder, one or more clouds (encrypted); warning when there is no recent backup.
- Two-factor login, login throttling, audit log of document edits, a read-only check of a deployed site (`tools/release/check-site.sh`).
- Modules: own documents, reports and checks in a folder, without changing Zippy code ([docs/MODULES.md](docs/MODULES.md)).
- A charity profile: menu without money and prices, issue-oriented names and print forms. No external requests: works offline.

![Equipment: where each unit is now](docs/img/equipment-where.png)

## Try it

```bash
git clone https://github.com/likemusic/squirrel-stock.git && cd squirrel-stock/docker
SQS_LANG=ru docker compose up -d --build      # http://127.0.0.1:8083, login admin / admin
```

Demo data (fictional): restore `db/demo/squirrelstock-demo.zip` on the backup page. Installation on Linux and on hosting: [docs/INSTALL_LINUX.ru.md](docs/INSTALL_LINUX.ru.md), [docs/INSTALL_HOSTING.ru.md](docs/INSTALL_HOSTING.ru.md) (Russian).

## Differences from Zippy

Stock accounting fixes (double posting, batch with the nearest expiry date, import date, serial units issued on a date when they are not in stock, and others), each change to Zippy code listed in [docs/CORE_CHANGES.md](docs/CORE_CHANGES.md) with a test; interface translation by English keys; offline operation; the charity features above; Playwright regression tests. Development: [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).

## License

MIT, see [LICENSE](LICENSE). Copyright (c) 2018 Leon (original Zippy code), (c) 2026 SquirrelStock contributors. The original Zippy README (Ukrainian): [README.zippy.md](README.zippy.md).
