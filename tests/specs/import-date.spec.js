// Импорт товаров с оприходованием: документ остатков получает дату из формы, а не сегодняшнюю.
// Раньше дату можно было поменять только отменой проведения и повторным проведением. ZIPPY_GAPS п. 2.
const fs = require('fs');
const path = require('path');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { value, stockQty, esc } = require('../helpers/db');

test('импорт с оприходованием на заданную дату', async ({ page, uniq }, testInfo) => {
  const codes = ['IM' + uniq + '-1', 'IM' + uniq + '-2'];
  const file = testInfo.outputPath('import.csv');
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, 'name,code,msr,qty,price\n' + codes.map((c, i) => `Item ${c},${c},pcs,${3 + i},0`).join('\n') + '\n');

  await page.goto('/index.php?p=App/Pages/Service/Import');
  const f = page.locator('#iform');
  await Promise.all([page.waitForLoadState('networkidle'), f.locator('#itype').selectOption('1')]);
  await page.waitForTimeout(800);
  await f.locator('#icompare').selectOption('0');
  await f.locator('#filename').setInputFiles(file);
  await f.locator('#passfirst').setChecked(true);
  await f.locator('#preview').setChecked(false);
  for (const [k, v] of Object.entries({ colname: 'A', colcode: 'B', colmsr: 'C', colqty: 'D', colinprice: 'E' })) await f.locator('#' + k).selectOption(v);
  await f.locator('#docdate').fill('2026-07-01');
  await clickWait(page, '#iform input[type=submit], #iform button[type=submit]');
  await expect(page.locator('.toast-message').first()).toContainText('Імпортовано 2');

  expect(stockQty(codes[0])).toBe(3);
  expect(stockQty(codes[1])).toBe(4);
  const date = value(`select distinct date(d.document_date) from documents d join entrylist e on e.document_id = d.document_id join store_stock s on s.stock_id = e.stock_id join items i on i.item_id = s.item_id where i.item_code = '${esc(codes[0])}'`);
  expect(date, 'дата документа остатков').toBe('2026-07-01');
});
