# Changes to upstream Zippy code

Every place where SquirrelStock changes files inherited from Zippy ([leon-mbs/zstore](https://github.com/leon-mbs/zstore), base commit `c74df59f4`). Needed when merging upstream updates. Newest at the bottom.

| Commit | File | What and why | Test |
|---|---|---|---|
| `02221545c` | `www/app/entity/item.php` `getNearestSerie()` | `order by sdate desc` → `order by sdate is null, sdate asc`: issue form suggested the batch with the farthest expiry date instead of the nearest | `tests/specs/batch-expiry.spec.js` |
| `9e9bcec26` | `www/app/pages/report/outcome.php` | total margin percent: check `($totsumself + $totnavar) != 0` instead of `$totsumself > 0`: Division by zero when goods are issued at price 0 | `tests/specs/outcome-zero-price.spec.js` |
| `3c5f5f65c` | `www/app/entity/doc/document.php` `updateStatus()`, new `isExecuted()` | refuse to execute an already executed document (last executed/canceled record in `docstatelog`); before: repeated "Execute" doubled stock and payments | `tests/specs/double-posting.spec.js` |
| `3e01ce6d1` | `www/app/entity/doc/document.php` `beforeSave()`; `www/app/pages/register/doclist.php` `onCopy()`; `www/app/pages/register/orderlist.php` (order split) | refuse to insert a new document whose `headerdata['hash']` already exists (page version after browser "Back"); copies get a new hash | `tests/specs/double-posting.spec.js` |
