# Changes to upstream Zippy code

Every place where SquirrelStock changes files inherited from Zippy ([leon-mbs/zstore](https://github.com/leon-mbs/zstore), base commit `c74df59f4`). Needed when merging upstream updates. Newest at the bottom.

| Commit | File | What and why | Test |
|---|---|---|---|
| `02221545c` | `www/app/entity/item.php` `getNearestSerie()` | `order by sdate desc` → `order by sdate is null, sdate asc`: issue form suggested the batch with the farthest expiry date instead of the nearest | `tests/specs/batch-expiry.spec.js` |
