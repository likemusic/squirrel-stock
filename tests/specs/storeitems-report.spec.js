// Отчёт «Состояние складов» строится без ошибок и показывает товар с остатком.
// В русской версии падал (Missing closing tag), в украинской автор исправил сам. ZIPPY_GAPS п. 5.
const { test, expect } = require('../fixtures');
const { createItem, createDoc, runReport, bodyText } = require('../helpers/zippy');

test('«Состояние складов» показывает остаток', async ({ page, uniq }) => {
  const code = 'SI-' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code, qty: 7, price: 0 }] });
  await runReport(page, 'Report/StoreItems', {});
  const text = await bodyText(page);
  expect(text).not.toMatch(/Missing closing tag|Exception|Fatal|Warning:/i);
  expect(text).toContain(code);
});
