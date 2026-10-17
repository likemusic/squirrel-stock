// «Получатель × месяц»: кто в каком месяце получал (замена листов «РАСХОДНИКИ ПО МЕСЯЦАМ» и «ГАСТРОСТОМЫ»),
// с отбором по категории или позиции. ZIPPY_GAPS п. 15.
const { test, expect } = require('../fixtures');
const { clickWait, pickAuto } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { query, value, esc } = require('../helpers/db');

test('получатель × месяц: отметки и количества, отбор по позиции', async ({ page, uniq }) => {
  const a = 'RM-' + uniq + '-A', b = 'RM-' + uniq + '-B';
  const r1 = 'Получатель ' + uniq + ' один', r2 = 'Получатель ' + uniq + ' два';
  seed({
    items: [{ name: 'Позиция ' + a, code: a }, { name: 'Позиция ' + b, code: b }],
    customers: [{ name: r1, type: 1 }, { name: r2, type: 1 }],
    docs: [
      { type: 'IncomeItem', date: '2026-06-01', rows: [{ code: a, qty: 50, price: 0 }, { code: b, qty: 50, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-07-05', customer: r1, rows: [{ code: a, qty: 2, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-07-20', customer: r1, rows: [{ code: a, qty: 3, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-08-02', customer: r2, rows: [{ code: b, qty: 1, price: 0 }] },
    ],
  });

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

  // признак получателя — тег контрагента; с флажком «все контрагенты» видны и помеченные без выдач
  const r3 = 'Получатель ' + uniq + ' три', tag = 'гастростома-' + uniq;
  seed({ customers: [{ name: r3, type: 1 }] });
  for (const who of [r2, r3]) {
    const id = value(`select customer_id from customers where customer_name = '${esc(who)}'`);
    query(`insert into taglist (tag_name, tag_type, item_id) values ('${esc(tag)}', 1, ${id})`);
  }
  await page.goto('/index.php?p=App/Pages/Report/RecipientMonths');
  await page.locator('#filter #from').fill('2026-06-01');
  await page.locator('#filter #to').fill('2026-08-31');
  await page.locator('#filter #ctag').selectOption(tag);
  await page.locator('#filter #allcust').setChecked(true);
  await clickWait(page, '#filter #onreport');
  const text = await page.locator('#preview table').innerText();
  expect(text).toContain(r2);
  expect(text).toContain(r3);
  expect(text).not.toContain(r1);
});
