// Оборудование: где сейчас каждая единица (инвентарный номер = серийный номер, режим хосписа usesnumber = 3).
// Выдача — по договору (номер, дата, срок, «кем направлен» в кратком описании договора). ZIPPY_GAPS п. 10.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, createContract, clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');
const { xlsxText } = require('../helpers/xlsx');

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
    const table = page.locator('#preview table.sqs-units');
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

  // заводской номер (ответ хосписа 1.1): колонка, поиск по нему, выгрузка в Excel
  const item = value(`select item_id from items where item_code = '${code}'`);
  const fno = n => `ZW${n}-${uniq}`;
  query(`insert into sqs_unit (item_id, snumber, factory_no) values (${item}, '${inv(1)}', '${fno(1)}'), (${item}, '${inv(2)}', '${fno(2)}')`);
  r = await run(fno(2), 'all');
  expect(Object.keys(r), 'поиск по заводскому номеру').toEqual(['2']);
  expect(r[2]['Заводський номер']).toBe(fno(2));
  r = await run(uniq, 'all');
  expect(r[1]['Заводський номер']).toBe(fno(1));
  expect(r[3]['Заводський номер']).toBe('');
  expect(await xlsxText(page.context().request, '/index.php?p=App/Pages/ShowReport&arg=xls/equipmentwhere'), 'в Excel').toContain(fno(1));

  // итог по моделям (над списком): выдано, по складам (склад «Ремонт» — состояние «в ремонте»), списано, всего
  const name2 = 'Кровать ' + uniq, code2 = 'EQB-' + uniq;
  await createItem(page, { name: name2, code: code2, serial: true });
  await createDoc(page, 'IncomeItem', { date: '2026-09-01', rows: [4, 5].map(n => ({ code: code2, qty: 1, price: 0, sn: inv(n) })) });
  const repair = 'Ремонт ' + uniq;
  query(`insert into stores (storename, branch_id, disabled) values ('${repair}', 0, 0)`);
  query(`update store_stock set store_id = ${value(`select max(store_id) from stores where storename = '${repair}'`)} where snumber = '${inv(5)}'`);

  const summary = async state => {
    await page.goto('/index.php?p=App/Pages/Report/EquipmentWhere');
    await page.locator('#filter #searchtext').fill(uniq);
    if (state) await page.locator('#filter #state').selectOption(state);
    await clickWait(page, '#filter #onreport');
    const table = page.locator('#preview table.sqs-summary');
    const head = (await table.locator('tr').nth(1).locator('th').allInnerTexts()).map(s => s.trim());
    const out = {};
    for (const n of [name, name2]) {
      const td = (await table.locator('tr', { hasText: n }).locator('td').allInnerTexts()).map(s => s.trim());
      out[n] = Object.fromEntries(head.map((h, i) => [h, td[i]]));
    }
    return out;
  };
  let s = await summary('all');
  expect(s[name]).toMatchObject({ 'Видано': '1', 'Основний склад': '1', [repair]: '', 'Списано': '1', 'Всього': '3' });
  expect(s[name2]).toMatchObject({ 'Видано': '', 'Основний склад': '1', [repair]: '1', 'Списано': '', 'Всього': '2' });
  s = await summary();   // по умолчанию без списанного
  expect(s[name]).toMatchObject({ 'Видано': '1', 'Основний склад': '1', 'Всього': '2' });
  expect(s[name]['Списано']).toBeUndefined();

  const resp = await page.context().request.get('/index.php?p=App/Pages/ShowReport&arg=xls/equipmentwhere');   // две таблицы — один лист Excel
  expect(resp.status()).toBe(200);
  expect(resp.headers()['content-type']).toContain('spreadsheetml');
});
