# Changes to upstream Zippy code

Every place where SquirrelStock changes files inherited from Zippy ([leon-mbs/zstore](https://github.com/leon-mbs/zstore), base commit `c74df59f4`). Needed when merging upstream updates. Newest at the bottom.

| Commit | File | What and why | Test |
|---|---|---|---|
| `02221545c` | `www/app/entity/item.php` `getNearestSerie()` | `order by sdate desc` → `order by sdate is null, sdate asc`: issue form suggested the batch with the farthest expiry date instead of the nearest | `tests/specs/batch-expiry.spec.js` |
| `9e9bcec26` | `www/app/pages/report/outcome.php` | total margin percent: check `($totsumself + $totnavar) != 0` instead of `$totsumself > 0`: Division by zero when goods are issued at price 0 | `tests/specs/outcome-zero-price.spec.js` |
| `3c5f5f65c` | `www/app/entity/doc/document.php` `updateStatus()`, new `isExecuted()` | refuse to execute an already executed document (last executed/canceled record in `docstatelog`); before: repeated "Execute" doubled stock and payments | `tests/specs/double-posting.spec.js` |
| `3e01ce6d1` | `www/app/entity/doc/document.php` `beforeSave()`; `www/app/pages/register/doclist.php` `onCopy()`; `www/app/pages/register/orderlist.php` (order split) | refuse to insert a new document whose `headerdata['hash']` already exists (page version after browser "Back"); copies get a new hash | `tests/specs/double-posting.spec.js` |
| `0b821c391` | `www/app/entity/item.php` `findArrayAC()` | order autocomplete results: exact code/barcode, code prefix, name prefix, rest; before: no order, "X-03" offered "X-030" first | `tests/specs/item-autocomplete.spec.js` |
| `8e9021bf9` | `www/app/pages/service/import.php`, `www/templates/pages/service/import.html` | "Document date" field for item import with stock receipt; before: always today | `tests/specs/import-date.spec.js` |
| `183eddd03` | `www/templates/pages/main.html`, `www/templates/pages/reference/customerlist.html`, `www/templates/modules/shop/pages/admin/dashboard.html`; new `www/assets/js/chart.umd.js`, `www/assets/js/gcharts-local.js` | Google Charts loader replaced by local Chart.js with a google.visualization compatible layer; before: no charts and "google is not defined" offline | `tests/specs/offline.spec.js` |
| `f524d92af` | `www/app/system.php` (`FORK_NO_UPSTREAM_UPDATES`, `checkVersion()`), `www/app/pages/base.php`, `www/app/pages/update.php` | updates from zippy.com.ua disabled: no version check, no Update menu item and page | `tests/specs/offline.spec.js` |
| `a350134be` | `www/init.php`, `www/app/pages/base.php`, `www/app/report.php`, `www/app/entity/user.php`, `www/app/pages/userprofile.php` + template; new `www/app/translator.php`, `www/app/functions.php`, `www/lang/*.json` | translation by English keys: `__()`, `{{#t}}key{{/t}}`, language in user profile and config | `tests/specs/i18n.spec.js` |
| `8582a3873` | 519 files in `www/app`, `www/templates` (automatic) | Ukrainian strings replaced with `__('key')` / `{{#t}}key{{/t}}` by `tools/i18n/convert.php`; texts in `www/lang/uk.json`. On an upstream update: take the author's code, run the converter, reapply the other patches from this list | `tests/specs/i18n-pages.spec.js`, `tests/tools/dump-texts.js` |
| `1c6707995` | `www/app/pages/userlogin.php`, `showreport.php`, `showdoc.php`, `doclink.php`, shop catalog and df module base/login pages, `www/app/helper.php` (e-mail templates) | translator in `_tvars` of pages outside `App\Pages\Base` | `tests/specs/i18n-pages.spec.js` |
| `73a2d5bf9`, `8f54c4b8b` | `www/app/translator.php` | no recursion while the user is loaded; language list only from language dictionaries | `tests/specs/i18n.spec.js` |
| `40400c70e` | 61 templates (automatic) | Ukrainian strings in template scripts (confirm/alert/toastr) and help popovers replaced with keys | `tests/specs/i18n-ru.spec.js` |
| `cdaa5674e` | `www/templates/pages/base.html`, `www/app/pages/base.php`; new `www/assets/js/select2-ru.js`, `www/assets/js/summernote/lang/summernote-ru-RU.js` | `<html lang>`, Select2 language and Summernote table follow the interface language | `tests/specs/i18n-ru.spec.js` |
| `0f98beb22` | `www/app/util.php` `money2str()`, `www/app/pages/options.php` + template | currency of the amount in words from the settings (`common.money2str`), default hryvnia | `tests/specs/money-words.spec.js` |
| `db28160cf` | `www/app/entity/doc/document.php` `beforeSave()`/`afterSave()`, `www/app/widgets/docview.php` + template; new `www/app/sqs/docaudit.php`, table `sqs_doclog` | audit log of document edits; rows/date/store/customer of an executed document cannot be changed without cancelling | `tests/specs/doc-audit.spec.js` |
| `6d6dd5883` | `www/app/pages/main.php` (one line), `www/templates/pages/main.html`; new `www/app/sqs/equipmentdue.php` | main page: reminder block for issued equipment whose term has expired or expires within 14 days (only template variables from `App\SQS\EquipmentDue`) | `tests/specs/equipment-due.spec.js` |
| `99e5bbe88` | `www/app/pages/register/tasklist.php` + template, `www/templates/pages/timesheet.html` | FullCalendar locale (task list, time sheet) and moment locale (time sheet) follow the interface language; the framework bundle `zippy-bundle-ua.js` is not changed — these pages load their own FullCalendar over it | `tests/specs/i18n-ru.spec.js` |
| `7bd910d2d` | `www/app/pages/reference/itemlist.php` `save()` | warning when a position with a 1C name (`sqs_item1c`) is renamed: the write-off act prints the 1C name; saving is not blocked | `tests/specs/import-1c-same-name.spec.js` |
| `9cf10faec` | `www/assets/css/app.css` (end of file) | below 576 px the document button row (`d-flex justify-content-end`, 34 templates in `pages/doc/`) wraps; before: "Cancel" ran off the left edge of a phone screen | `tests/specs/mobile.spec.js` |
| `107c25468` | `www/assets/css/app.css` (end of file) | below 768 px the opened sidebar pushes the page without shrinking it (`.main` min-width, `.wrapper` overflow-x hidden); before: the page shrank to ~60 px | `tests/specs/mobile.spec.js` |
| `2352be760` | `www/assets/css/app.css` (end of file) | `#sidebar.collapsed { box-shadow: none }`: no grey band on the left edge when the menu is hidden | `tests/specs/mobile.spec.js` |
| `9e0f28a35` | `www/templates/pages/register/doclist.html`, `gilist.html`, `grlist.html`, `itemlist.html` | below 768 px secondary columns hidden (`d-none d-md-table-cell`), row actions visible without horizontal scroll; desktop unchanged | `tests/specs/mobile.spec.js` |
| `0642e6635` | `www/templates/pages/main.html`, `www/templates/pages/base.html`; `www/app/sqs/equipmentdue.php` | main page: backup warning for administrators (`App\SQS\BackupJob::status()`), `table-responsive` around the critical quantity table; `app.css?v=828-sqs1` | `tests/specs/backup-main-warning.spec.js`, `tests/specs/mobile.spec.js` |
| `6a205b118` | `www/.htaccess` | service files (composer.json/lock, `lang/`, `cli/`, examples archive, `init.php`, non-static files under `vendor/`) are not served; security headers X-Frame-Options, X-Content-Type-Options, Referrer-Policy, no X-Powered-By | `tests/specs/hosting-security.spec.js` |
| `04cf0e89f` | `www/app/helper.php` `login()`, `www/app/system.php` `checkUpdate()` | password checked only with `password_verify()`; a plain-text password from an old database is accepted once and re-hashed; default admin password reminder recognises any hash of it | `tests/specs/login-security.spec.js` |

## Proposed upstream

Fixes offered to the author as pull requests (15.10.2026, branches in [likemusic/zstore](https://github.com/likemusic/zstore) from `master` `c74df59f4`, only the `www/` part of the commit). If a PR is merged, the change comes with the next upstream update and the row above needs no reapplying.

| Commit | Pull request |
|---|---|
| `02221545c` | [#1107](https://github.com/leon-mbs/zstore/pull/1107) nearest expiry batch |
| `9e9bcec26` | [#1108](https://github.com/leon-mbs/zstore/pull/1108) sales report, zero price |
| `3c5f5f65c` | [#1109](https://github.com/leon-mbs/zstore/pull/1109) repeated Execute |
| `3e01ce6d1` | [#1110](https://github.com/leon-mbs/zstore/pull/1110) browser Back duplicate |
| `0b821c391` | [#1111](https://github.com/leon-mbs/zstore/pull/1111) autocomplete order |
| `8e9021bf9` | [#1112](https://github.com/leon-mbs/zstore/pull/1112) import document date |
