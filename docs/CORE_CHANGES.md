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
