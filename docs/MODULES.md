# Modules and document hooks

SquirrelStock changes that do not have to live in Zippy's code go into modules: a folder in `www/app/sqs/modules/`. A module can react to document events (save, execute, cancel), add menu items and bring its own pages. Without module folders the program works exactly as before.

## Module folder

```
www/app/sqs/modules/hello/
    module.php
    pages/report/hello.php               class App\Pages\Report\Hello
    templates/pages/report/hello.html    its template
```

Folder and file names are lower case (the class name may use any case). `app/` is closed to the browser by `.htaccess`, so module files are not served directly.

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

## Menu

Menu items of modules are written to `metadata` when the set of module items changes: a new item is added (or enabled again), an item of a removed module is disabled (rows are not deleted, role permissions refer to them). Items that stay are not touched, so they can be switched off in the menu settings. The menu cached in the session is refreshed.

To switch a module off, delete its folder or rename it with a leading dot (`.hello`).

## Cost

An ordinary request only scans the module folders: 0.04 ms without modules, 0.16 ms with one (measured on the Docker stand). The database is read once per session and when the set of module menu items changes.

## Test

`tests/specs/hooks.spec.js` writes a temporary module (`zz-test-*`, git-ignored) into the working copy (the dev stand mounts `www/app`), checks the hooks and the menu item and removes the module.
