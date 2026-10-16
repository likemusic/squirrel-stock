// Загрузка файлов в импорт (Сервис → Импорт): принимаются таблицы Excel, OpenDocument и CSV; HTML и XML не читаются
// (PhpSpreadsheet по файлу без расширения перебирал все свои читатели). Файлы безобидные: таблица из двух позиций.
const fs = require('fs');
const path = require('path');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { value, esc } = require('../helpers/db');

async function importItems(page, file, cols) {
  await page.goto('/index.php?p=App/Pages/Service/Import');
  const f = page.locator('#iform');
  await f.locator('#icompare').selectOption('0');
  await f.locator('#filename').setInputFiles(file);
  await f.locator('#passfirst').setChecked(true);
  await f.locator('#preview').setChecked(false);
  for (const [k, v] of Object.entries(cols)) await f.locator('#' + k).selectOption(v);
  await clickWait(page, '#iform input[type=submit], #iform button[type=submit]');
}

const count = code => Number(value(`select count(*) from items where item_code = '${esc(code)}'`));

test('импорт: CSV читается, HTML-таблица — нет', async ({ page, uniq }, testInfo) => {
  const dir = testInfo.outputPath('files');
  fs.mkdirSync(dir, { recursive: true });
  const csv = path.join(dir, 'items.csv'), html = path.join(dir, 'items.html');
  const c1 = 'IF' + uniq + '-1', c2 = 'IF' + uniq + '-2';
  fs.writeFileSync(csv, `name,code\nПозиция ${c1},${c1}\n`);
  fs.writeFileSync(html, `<html><body><table><tr><td>name</td><td>code</td></tr><tr><td>Позиция ${c2}</td><td>${c2}</td></tr></table></body></html>\n`);

  await importItems(page, csv, { colname: 'A', colcode: 'B' });
  await expect(page.locator('.toast-message').first()).toContainText('Імпортовано 1');
  expect(count(c1)).toBe(1);

  await importItems(page, html, { colname: 'A', colcode: 'B' });
  await expect(page.locator('.toast-message, .alert-danger').first()).toContainText('потрібна таблиця Excel');
  expect(count(c2), 'позиция из HTML не создана').toBe(0);
  expect(Number(value(`select count(*) from items where itemname like '%<td>%' or itemname like '%<table>%'`)), 'строки разметки не стали позициями').toBe(0);
});
