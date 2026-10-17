// «Отбор»: оборот по позициям за период — на начало, приход, выдано, возвращено, списано, прочее, на конец,
// сколько раз выдавали; с отбором по получателю. Заголовки колонок — украинского интерфейса тестов. ZIPPY_GAPS п. 16 (как лист «Отбор» в их книге Excel).
const { test, expect } = require('../fixtures');
const { clickWait, pickAuto, messages } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');

test('оборот по позиции за период и по получателю', async ({ page, uniq }) => {
  const a = 'TO-' + uniq;
  const r1 = 'Получатель ' + uniq + ' один', r2 = 'Получатель ' + uniq + ' два';
  const { docs } = seed({
    items: [{ name: 'Позиция ' + a, code: a }],
    customers: [{ name: r1, type: 1 }, { name: r2, type: 1 }],
    docs: [
      { type: 'IncomeItem', date: '2026-05-10', rows: [{ code: a, qty: 20, price: 0 }] },   // до периода
      { type: 'IncomeItem', date: '2026-06-02', rows: [{ code: a, qty: 10, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-06-05', customer: r1, rows: [{ code: a, qty: 4, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-06-07', customer: r2, rows: [{ code: a, qty: 3, price: 0 }] },
      { type: 'OutcomeItem', date: '2026-06-09', notes: 'Причина: порча', rows: [{ code: a, qty: 1 }] },
    ],
  });
  const issueId = docs[3].id;   // возврат — формой
  await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + issueId);
  await page.locator('#document_date').fill('2026-06-20');
  await clickWait(page, '#execdoc');
  expect(await page.locator('#docform #execdoc:visible').count(), (await messages(page)).join(' ')).toBe(0);

  await page.goto('/');
  await expect(page.locator('a[href*="Report/ItemTurnover"]').first(), 'пункт меню (db/squirrelstock.sql)').toBeAttached();

  const run = async customer => {
    await page.goto('/index.php?p=App/Pages/Report/ItemTurnover');
    await page.locator('#filter #from').fill('2026-06-01');
    await page.locator('#filter #to').fill('2026-06-30');
    if (customer) await pickAuto(page, '#filter #cust', customer, customer);
    await clickWait(page, '#filter #onreport');
    const table = page.locator('#preview table');
    const head = (await table.locator('tr').nth(1).locator('th').allInnerTexts()).map(s => s.trim());
    const row = (await table.locator('tr', { hasText: 'Позиция ' + a }).locator('td').allInnerTexts()).map(s => s.trim());
    return Object.fromEntries(head.map((h, i) => [h, row[i]]));
  };

  const all = await run();
  expect(all).toMatchObject({ 'На початок': '20', 'Прихід': '10', 'Видано': '7', 'Повернуто': '3', 'Списано': '1', 'На кінець': '25', 'Видач': '2' });

  const one = await run(r1);
  expect(one).toMatchObject({ 'Видано': '4', 'Повернуто': '', 'Видач': '1' });
  expect(one['На початок']).toBeUndefined();   // остатки склада при отборе по получателю не показываются
});
