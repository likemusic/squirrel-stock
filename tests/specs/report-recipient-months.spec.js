// «Получатель × месяц»: кто в каком месяце получал (замена листов «РАСХОДНИКИ ПО МЕСЯЦАМ» и «ГАСТРОСТОМЫ»),
// с отбором по категории или позиции. ZIPPY_GAPS п. 15.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, clickWait, pickAuto } = require('../helpers/zippy');

test('получатель × месяц: отметки и количества, отбор по позиции', async ({ page, uniq }) => {
  const a = 'RM-' + uniq + '-A', b = 'RM-' + uniq + '-B';
  const r1 = 'Получатель ' + uniq + ' один', r2 = 'Получатель ' + uniq + ' два';
  await createItem(page, { name: 'Позиция ' + a, code: a });
  await createItem(page, { name: 'Позиция ' + b, code: b });
  await createCustomer(page, { name: r1, type: 1 });
  await createCustomer(page, { name: r2, type: 1 });
  await createDoc(page, 'IncomeItem', { date: '2026-06-01', rows: [{ code: a, qty: 50, price: 0 }, { code: b, qty: 50, price: 0 }] });
  await createDoc(page, 'GoodsIssue', { date: '2026-07-05', customer: r1, payment: 0, rows: [{ code: a, qty: 2, price: 0 }] });
  await createDoc(page, 'GoodsIssue', { date: '2026-07-20', customer: r1, payment: 0, rows: [{ code: a, qty: 3, price: 0 }] });
  await createDoc(page, 'GoodsIssue', { date: '2026-08-02', customer: r2, payment: 0, rows: [{ code: b, qty: 1, price: 0 }] });

  await page.goto('/');
  await expect(page.locator('a[href*="Report/RecipientMonths"]').first(), 'пункт меню (db/squirrelstock.sql)').toBeAttached();

  const run = async (mode, item) => {
    await page.goto('/index.php?p=App/Pages/Report/RecipientMonths');
    await page.locator('#filter #from').fill('2026-06-01');
    await page.locator('#filter #to').fill('2026-08-31');
    await page.locator('#filter #mode').selectOption(mode);
    if (item) await pickAuto(page, '#filter #item', item, item);
    await clickWait(page, '#filter #onreport');
    const table = page.locator('#preview table');
    const head = await table.locator('tr').nth(1).locator('th').allInnerTexts();
    const row = async name => (await table.locator('tr', { hasText: name }).count()) ? await table.locator('tr', { hasText: name }).locator('td').allInnerTexts() : null;
    return { head: head.map(s => s.trim()), r1: await row(r1), r2: await row(r2) };
  };

  let t = await run('mark');
  expect(t.head.slice(1, 4)).toEqual(['06.2026', '07.2026', '08.2026']);
  expect(t.r1.slice(1, 4).map(s => s.trim())).toEqual(['', 'х', '']);
  expect(t.r2.slice(1, 4).map(s => s.trim())).toEqual(['', '', 'х']);

  t = await run('qty');
  expect(t.r1.slice(1, 4).map(s => s.trim())).toEqual(['', '5', '']);

  t = await run('mark', 'Позиция ' + b);   // отбор по позиции: первого получателя нет
  expect(t.r1).toBeNull();
  expect(t.r2.slice(1, 4).map(s => s.trim())).toEqual(['', '', 'х']);
});
