// Оборудование: где сейчас каждая единица (инвентарный номер = серийный номер, режим хосписа usesnumber = 3).
// Выдача — по договору (номер, дата, срок, «кем направлен» в кратком описании договора). ZIPPY_GAPS п. 10.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, createContract, clickWait } = require('../helpers/zippy');

test('оборудование: выдано по договору, на складе, списано', async ({ page, uniq }) => {
  const code = 'EQ-' + uniq, name = 'Концентратор ' + uniq, r1 = 'Получатель ' + uniq;
  const inv = n => `INV${n}-${uniq}`;
  await createItem(page, { name, code, serial: true });
  await createCustomer(page, { name: r1, type: 1 });
  await createDoc(page, 'IncomeItem', { date: '2026-09-01', rows: [1, 2, 3].map(n => ({ code, qty: 1, price: 0, sn: inv(n) })) });
  await createContract(page, { number: 'Д' + uniq, customer: r1, from: '2026-10-01', to: '2099-12-31', note: 'Врач Тестовый' });
  await createDoc(page, 'GoodsIssue', { date: '2026-10-02', customer: r1, payment: 0, contract: 'Д' + uniq, rows: [{ code, qty: 1, price: 0, sn: inv(1) }] });
  await createDoc(page, 'OutcomeItem', { date: '2026-10-03', notes: 'Причина: сломан', rows: [{ code, qty: 1, sn: inv(3) }] });

  const run = async (search, state) => {
    await page.goto('/index.php?p=App/Pages/Report/EquipmentWhere');
    await page.locator('#filter #searchtext').fill(search);
    if (state) await page.locator('#filter #state').selectOption(state);
    await clickWait(page, '#filter #onreport');
    const table = page.locator('#preview table');
    const head = (await table.locator('tr').nth(1).locator('th').allInnerTexts()).map(s => s.trim());
    const rows = {};
    for (const n of [1, 2, 3]) {
      const tr = table.locator('tr', { hasText: inv(n) });
      if (!(await tr.count())) continue;
      const td = (await tr.locator('td').allInnerTexts()).map(s => s.trim());
      rows[n] = Object.fromEntries(head.map((h, i) => [h, td[i]]));
    }
    return rows;
  };

  let r = await run(uniq, 'all');
  expect(r[1]).toMatchObject({ 'Де': 'видано', 'Отримувач': r1, 'Строк': 'до запитання', 'Ким направлено': 'Врач Тестовый', 'З дати': '02.10.2026' });
  expect(r[1]['Договір']).toContain('Д' + uniq);
  expect(r[2]['Де']).toContain('Основний склад');
  expect(r[3]['Де']).toBe('списано');

  r = await run(uniq);   // по умолчанию без списанного
  expect(Object.keys(r).sort()).toEqual(['1', '2']);

  r = await run(r1, 'all');   // поиск по получателю
  expect(Object.keys(r)).toEqual(['1']);
});
