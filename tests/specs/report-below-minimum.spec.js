// «Ниже минимального остатка»: позиции, у которых остаток меньше «Мин. количества» из карточки, — список для закупки
// с последним поступлением; позиция, которой на складе не было ни разу, тоже в списке (виджет главной страницы Zippy
// ее не показывает). Заголовки колонок — украинского интерфейса тестов. ZIPPY_GAPS п. 31.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, runReport } = require('../helpers/zippy');

test('позиции ниже минимального остатка и выгрузка в Excel', async ({ page, uniq }) => {
  const low = 'BM-' + uniq + '-L', ok = 'BM-' + uniq + '-OK', none = 'BM-' + uniq + '-N', nomin = 'BM-' + uniq + '-X';
  const donor = 'Донор ' + uniq;
  await createCustomer(page, { name: donor, type: 2 });
  await createItem(page, { name: 'Позиция ' + low, code: low, minqty: 5 });
  await createItem(page, { name: 'Позиция ' + ok, code: ok, minqty: 5 });
  await createItem(page, { name: 'Позиция ' + none, code: none, minqty: 3 });
  await createItem(page, { name: 'Позиция ' + nomin, code: nomin });
  await createDoc(page, 'IncomeItem', { date: '2026-09-01', rows: [{ code: low, qty: 4, price: 0 }, { code: ok, qty: 5, price: 0 }, { code: nomin, qty: 1, price: 0 }] });
  await createDoc(page, 'IncomeItem', { date: '2026-09-10', customer: donor, rows: [{ code: low, qty: 2, price: 0 }] });
  await createDoc(page, 'OutcomeItem', { date: '2026-09-12', rows: [{ code: low, qty: 3 }] });

  await page.goto('/');
  await expect(page.locator('a[href*="Report/BelowMinimum"]').first(), 'пункт меню (db/squirrelstock.sql)').toBeAttached();

  await runReport(page, 'Report/BelowMinimum', {});
  const table = page.locator('#preview table');
  const head = (await table.locator('tr').nth(1).locator('th').allInnerTexts()).map(s => s.trim());
  const row = async code => {
    const cells = (await table.locator('tr', { hasText: 'Позиция ' + code }).locator('td').allInnerTexts()).map(s => s.trim());
    return cells.length ? Object.fromEntries(head.map((h, i) => [h, cells[i]])) : null;
  };

  expect(await row(low)).toMatchObject({ 'Код': low, 'Залишок': '3', 'Мінімум': '5', 'Не вистачає': '2', 'Останнє надходження': '10.09.2026', 'Від кого': donor });
  expect(await row(none)).toMatchObject({ 'Залишок': '0', 'Мінімум': '3', 'Не вистачає': '3', 'Останнє надходження': '' });
  expect(await row(ok), 'остаток равен минимуму — не ниже').toBeNull();
  expect(await row(nomin), 'минимум не задан').toBeNull();

  const resp = await page.context().request.get('/index.php?p=App/Pages/ShowReport&arg=xls/belowminimum');
  expect(resp.status()).toBe(200);
  expect(resp.headers()['content-type']).toContain('spreadsheetml');
  expect((await resp.body()).length).toBeGreaterThan(1000);
});
