# Modules and document hooks

SquirrelStock changes that do not have to live in Zippy's code go into modules: a folder in `www/app/sqs/modules/`. A module can react to document events (save, execute, cancel), add menu items and bring its own pages. Without module folders the program works exactly as before.

## Module folder

```
www/app/sqs/modules/hello/
    module.php
    pages/report/hello.php               class App\Pages\Report\Hello
    templates/pages/report/hello.html    its template
    templates/printforms/report/hello.tpl  print form / report table (new \App\Report('report/hello.tpl'))
    templates/printforms/charity/doc/goodsissue.tpl  print form used instead of doc/goodsissue.tpl in the charity profile
    greeter.php                          class App\SQS\Greeter (module classes keep the App\SQS namespace)
    schema.sql                           module tables: CREATE TABLE IF NOT EXISTS ...
    lang/uk.json, lang/ru.json           translation keys of the module
    cli/hello.php                        console scripts
```

Folder and file names are lower case (the class name may use any case).

**Rule: Zippy code never calls a module class directly**, only through extension points (`App\SQS\Hooks`). The standard autoloader stops the program when a class file is missing, so a disabled module would break every page that names its class. Everything the core calls directly (login security, the document audit log, `Hooks`, `Modules`, `Categories`) is platform code in `www/app/sqs/` outside modules. `app/` is closed to the browser by `.htaccess`, so module files are not served directly.

`module.php` returns an array:

```php
<?php
use App\SQS\Hooks;

return [
    'name' => 'Hello',
    // [type, meta_name, menu title, menu group]; type as in metadata: 1 document, 2 report, 3 journal, 4 reference, 5 service
    'menu' => [[2, 'Hello', 'Привіт', 'Склад']],
    'register' => function () {
        // refuse to execute a write-off without a note
        Hooks::on('beforeExecute', 'OutcomeItem', function ($doc) {
            if (trim((string)$doc->notes) === '') {
                throw new \Exception('Укажите причину списания в примечании');
            }
        });
    },
];
```

The page is an ordinary Zippy page:

```php
<?php
namespace App\Pages\Report;

class Hello extends \App\Pages\Base
{
}
```

## SquirrelStock modules

| Module | What | Tests |
|---|---|---|
| `reports` | issue act (consumables / equipment), recipients by month, turnover by item, below minimum, write-offs with reasons, recipient card | `report-issue-act`, `report-recipient-months`, `report-item-turnover`, `report-below-minimum`, `report-subcategories`, `report-writeoffs`, `report-recipient-card` |
| `consumables` | consumables issue, issue intervals (table `sqs_issue_interval`) | `consumables-issue`, `issue-interval` |
| `import1c` | 1C trial balance import page | `import-1c`, `import-1c-same-name`, `import-1c-date` |
| `backup` | backup page, scheduled backup to several places (`cli/backup.php` is a wrapper), main page warning (table `sqs_backuplog`) | `backup`, `backup-auto`, `backup-main-warning` |
| `equipment` | equipment issue by contract, units, where now, issue journal, history import, main page reminder incl. small equipment under a contract (tables `sqs_unit`, `sqs_eqhistory`) | `equipment-issue`, `equipment-due`, `report-equipment-where`, `report-equipment-journal`, `eqhistory-import`, `eqhistory-write` |
| `update` | "Service pages → Program update": version, update history, package file check, removal of files left by old versions (`App\SQS\Release`, docs/UPDATE.ru.md) | `app-update`, `tools/install/test-update.sh` |

Platform code outside modules (`www/app/sqs/`): `Hooks`, `Modules`, `Categories`, login security (`Remember`, `LoginGuard`, `Totp`, `TwoFactor` and its pages), the document audit log (`DocAudit`), 1C names (`Item1C`, used by the issue act and the import), serial number stock on the document date (`SerialStock`, called from goods issue, write-off, move, return to supplier and stocktaking), program version and package file list (`Release`: `config/version.txt`, `config/manifest.txt` from the release build; version change noted by `Modules`). Its tables are in `www/app/sqs/schema.sql`; like module schemas, the file is applied automatically when it changes (and after restoring a backup).

## Document hooks

`App\SQS\Hooks::on($event, $docType, $handler)`, `$docType` is the document `meta_name` (`GoodsIssue`, `OutcomeItem`, ...) or `'*'` for all. The handler gets `(Document $doc, string $event)`.

| Event | When |
|---|---|
| `beforeSave` / `afterSave` | every save of a document (`Document::beforeSave`/`afterSave`) |
| `beforeExecute` | before the document is executed, before any write |
| `afterExecute` | once, after the executed state is saved |
| `beforeCancel` | before execution is cancelled, before any write |
| `afterCancel` | once, after the cancelled state is saved |

An exception thrown in a `before*` handler stops the action; its message is shown to the user. Document pages save and execute inside one transaction, so nothing is half done: for a new document nothing is saved, stock does not change.

The audit log of document edits (`App\SQS\DocAudit`) is a built-in `beforeSave`/`afterSave` handler and works without modules.

## Other extension points

`App\SQS\Hooks::listen($event, $handler)` / `Hooks::emit($event, ...$args)` — events that are not about a document:

| Event | Arguments | Return | Where |
|---|---|---|---|
| `mainBlocks` | — | HTML of a block for the main page, or `''` | `App\Pages\Main` (placeholder `{{{sqs_blocks}}}` in `main.html`) |
| `itemSaved` | `Item $item`, the page, `string $oldname` | — | item card saved (`Reference\ItemList`) |
| `customerCardUrl` | `int $customer_id` | address of the recipient card or `''` (first result is used) | recipient name in the issue journal (`Register\GIList`), card icon in the customer list (`Reference\CustomerList`) |

A block is usually rendered from the module's print form folder: `(new \App\Report('blocks/hello.tpl'))->generate($data, false)`.

## Tables, translations, console scripts

- `schema.sql` is executed when the file changes (hash in `keyval`), from a web request or a console script (`init.php`). Statements must be repeatable (`CREATE TABLE IF NOT EXISTS`). The database user needs the CREATE privilege; an error is written to `logs/` and retried on the next session.
- `lang/<language>.json` keys are merged into the main dictionaries (the main dictionary wins on the same key).
- Menu titles and groups may be given per language: `[2, 'Hello', ['uk' => 'Привіт', 'ru' => 'Привет'], ['uk' => 'Склад', 'ru' => 'Склад']]`; the installation language (`$_config['common']['lang']`) is written to `metadata`, `cli/localize-db.php` switches them like the other menu titles.
- The charity profile (`cli/charity-profile.php`) keeps menu items of enabled modules: an enabled module is a wanted one.
- The charity profile sets the flag `App\SQS\Profile::charity()`. With it `App\Report` looks for `printforms/charity/<template>` first — in modules, then in `www/templates/printforms/charity/` (the platform's own: the goods issue form "issued by / recipient" and the return form "returned by / accepted by", both without prices).

## Menu

Menu items of modules are written to `metadata` when the set of module items changes: a new item is added, an item of a removed module is disabled (rows are not deleted, role permissions refer to them) and enabled again when the module comes back. An item that already existed (a page moved into a module) is not switched on: it may have been switched off by hand or by the profile. Items that stay are not touched, so they can be switched off in the menu settings. The menu cached in the session is refreshed.

To switch a module off, delete its folder or rename it with a leading dot (`.hello`).

## Cost

An ordinary request only scans the module folders: 0.04 ms without modules, 0.16 ms with one (measured on the Docker stand). The database is read once per session and when the set of module menu items changes.

## Test

`tests/specs/hooks.spec.js` writes a temporary module (`zz-test-*`, git-ignored) into the working copy (the dev stand mounts `www/app`), checks the hooks and the menu item and removes the module.
