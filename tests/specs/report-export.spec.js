// Выгрузка в Excel и PDF отчетов «Состояние складов» и «Движение по счету»: кнопки отдают сформированный отчет.
// У Zippy отчет писался только в printform (печать), а выгрузка брала пустой экспорт из сессии — 404.
// Исправление взято у автора (upstream c957f18be).
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { runReport } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { xlsxText } = require('../helpers/xlsx');
const { query, value } = require('../helpers/db');
const { logCount } = require('../helpers/stand');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const SCRIPT = fs.readFileSync(path.join(__dirname, '..', 'fixtures', 'pdf-report.php'), 'utf8');
const pdfReport = (...args) => execFileSync('docker', ['exec', '-i', '-u', 'www-data', WEB, 'php', '--', ...args.map(String)], { input: SCRIPT, encoding: 'utf8' }).trim();

async function pdf(page, name) {
  const resp = await page.context().request.get('/index.php?p=App/Pages/ShowReport&arg=pdf/' + name);
  expect(resp.status(), 'PDF').toBe(200);
  expect((await resp.body()).subarray(0, 4).toString(), 'PDF').toBe('%PDF');
}

// отбор по своей категории: отчет по всей базе на полном прогоне — тысячи строк
test('«Состояние складов»: Excel и PDF', async ({ page, uniq }) => {
  const code = 'EX-' + uniq, name = 'Позиция ' + code;
  query(`insert into item_cat (cat_name, parent_id) values ('Выгрузка ${uniq}', 0)`);
  const cat = value(`select max(cat_id) from item_cat where cat_name = 'Выгрузка ${uniq}'`);
  seed({ items: [{ name, code, cat_id: cat }], docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 2, price: 10 }] }] });
  await page.goto('/index.php?p=App/Pages/Report/StoreItems');
  expect((await page.context().request.get('/index.php?p=App/Pages/ShowReport&arg=xls/storeitems')).status(), 'до «ОК» выгружать нечего').toBe(404);
  await runReport(page, 'Report/StoreItems', { selects: { searchcat: cat } });
  await expect(page.locator('#preview')).toContainText(name);
  expect(await page.locator('#preview tr').count(), 'в отчете только своя позиция').toBe(3);
  expect(await xlsxText(page.context().request, '/index.php?p=App/Pages/ShowReport&arg=xls/storeitems')).toContain(name);
  await pdf(page, 'storeitems');
});

// отчет бухгалтерии, в базе по умолчанию выключен — на время теста включается
test('«Движение по счету»: Excel и PDF', async ({ page }) => {
  query(`update metadata set disabled = 0 where meta_name = 'AccountActivity'`);
  try {
    await runReport(page, 'Report/AccountActivity');
    await expect(page.locator('#preview')).toContainText('Рух по рахунку');
    expect(await xlsxText(page.context().request, '/index.php?p=App/Pages/ShowReport&arg=xls/accountactivity')).toContain('Рух по рахунку');
    await pdf(page, 'accountactivity');
  } finally {
    query(`update metadata set disabled = 1 where meta_name = 'AccountActivity'`);
  }
});

// dompdf на длинной таблице расходует память квадратично — у Zippy «PDF» отчета в тысячи строк давал 500 (нехватка памяти).
// Длинная таблица режется на куски с шапкой, слишком большой отчет — сообщение и запись в журнал (fixtures/pdf-report.php).
test('большой отчет: длинная таблица в PDF режется на куски, слишком большой — сообщение вместо 500', () => {
  expect(JSON.parse(pdfReport(400, 6, 'split')), '400 строк — как есть').toEqual({ tables: 1, heads: 1, rows: 400 });
  expect(JSON.parse(pdfReport(600, 6, 'split')), '600 строк — 12 кусков по 50, шапка в каждом').toEqual({ tables: 12, heads: 12, rows: 600 });
  expect(pdfReport(600, 6)).toMatch(/^%PDF \d+$/);

  const logged = logCount('PDF report is too big: test: 33001 cells');
  const out = pdfReport(3000, 11);
  expect(out).toContain('PDF');
  expect(out).toMatch(/Excel/);
  expect(out).not.toContain('%PDF');
  expect(logCount('PDF report is too big: test: 33001 cells'), 'запись в журнал').toBe(logged + 1);
});
