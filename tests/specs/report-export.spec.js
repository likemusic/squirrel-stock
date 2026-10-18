// Выгрузка в Excel и PDF отчетов «Состояние складов» и «Движение по счету»: кнопки отдают сформированный отчет.
// У Zippy отчет писался только в printform (печать), а выгрузка брала пустой экспорт из сессии — 404.
// Исправление взято у автора (upstream c957f18be).
const { test, expect } = require('../fixtures');
const { runReport } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { xlsxText } = require('../helpers/xlsx');
const { query } = require('../helpers/db');

async function pdf(page, name) {
  const resp = await page.context().request.get('/index.php?p=App/Pages/ShowReport&arg=pdf/' + name);
  expect(resp.status(), 'PDF').toBe(200);
  expect((await resp.body()).subarray(0, 4).toString(), 'PDF').toBe('%PDF');
}

test('«Состояние складов»: Excel и PDF', async ({ page, uniq }) => {
  const code = 'EX-' + uniq, name = 'Позиция ' + code;
  seed({ items: [{ name, code }], docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 2, price: 10 }] }] });
  await page.goto('/index.php?p=App/Pages/Report/StoreItems');
  expect((await page.context().request.get('/index.php?p=App/Pages/ShowReport&arg=xls/storeitems')).status(), 'до «ОК» выгружать нечего').toBe(404);
  await runReport(page, 'Report/StoreItems');
  await expect(page.locator('#preview')).toContainText(name);
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
