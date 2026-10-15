// Акт списания за месяц в формате бухгалтерии хосписа: строки — позиции, колонки — выдачи «Получатель дд.мм.гггг»,
// в ячейках количество за вычетом возвратов, итог по позиции и по выдаче; выгрузка в Excel. ZIPPY_GAPS п. 14.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, clickWait, messages } = require('../helpers/zippy');
const { value, esc } = require('../helpers/db');

test('акт списания: матрица позиции × выдачи с итогами', async ({ page, uniq }) => {
  const a = 'AC-' + uniq + '-A', b = 'AC-' + uniq + '-B';
  const r1 = 'Получатель ' + uniq + ' один', r2 = 'Получатель ' + uniq + ' два';
  await createItem(page, { name: 'Позиция ' + a, code: a });
  await createItem(page, { name: 'Позиция ' + b, code: b });
  await createCustomer(page, { name: r1, type: 1 });
  await createCustomer(page, { name: r2, type: 1 });
  await createDoc(page, 'IncomeItem', { date: '2026-09-01', rows: [{ code: a, qty: 50, price: 0 }, { code: b, qty: 50, price: 0 }] });
  await createDoc(page, 'GoodsIssue', { date: '2026-09-03', customer: r1, payment: 0, rows: [{ code: a, qty: 4, price: 0 }, { code: b, qty: 2, price: 0 }] });
  await createDoc(page, 'GoodsIssue', { date: '2026-09-10', customer: r1, payment: 0, rows: [{ code: a, qty: 1, price: 0 }] });
  const { number } = await createDoc(page, 'GoodsIssue', { date: '2026-09-12', customer: r2, payment: 0, rows: [{ code: b, qty: 3, price: 0 }] });
  // выдачу второму получателю вернули целиком: в акте ее быть не должно
  const issueId = value(`select document_id from documents where document_number = '${esc(number)}' order by document_id desc limit 1`);
  await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + issueId);
  await page.locator('#document_date').fill('2026-09-15');
  await clickWait(page, '#execdoc');
  expect(await page.locator('#docform #execdoc:visible').count(), (await messages(page)).join(' ')).toBe(0);

  await page.goto('/');
  await expect(page.locator('a[href*="Report/IssueAct"]').first(), 'пункт меню (db/squirrelstock.sql)').toBeAttached();
  await page.goto('/index.php?p=App/Pages/Report/IssueAct');
  await page.locator('#filter #year').selectOption('2026');
  await page.locator('#filter #month').selectOption('9');
  await clickWait(page, '#filter #onreport');

  const table = page.locator('#preview table');
  const head = (await table.locator('tr').nth(1).innerText()).replace(/\s+/g, ' ');
  expect(head).toContain(`${r1} 03.09.2026`);
  expect(head).toContain(`${r1} 10.09.2026`);
  expect(head).not.toContain(`${r2} 12.09.2026`);

  // строка позиции: ячейки по колонкам выдач и итог в последней колонке
  const cols = await table.locator('tr').nth(1).locator('th, td').allInnerTexts();
  const rowA = await table.locator('tr', { hasText: 'Позиция ' + a }).locator('td').allInnerTexts();
  const colOf = text => cols.findIndex(c => c.replace(/\s+/g, ' ').includes(text));
  expect(rowA[colOf(`${r1} 03.09.2026`)].trim()).toBe('4');
  expect(rowA[colOf(`${r1} 10.09.2026`)].trim()).toBe('1');
  expect(rowA[rowA.length - 1].trim()).toBe('5');
  const rowB = await table.locator('tr', { hasText: 'Позиция ' + b }).locator('td').allInnerTexts();
  expect(rowB[rowB.length - 1].trim()).toBe('2');   // 2 выдано первому, 3 выдано и возвращено вторым

  const resp = await page.context().request.get('/index.php?p=App/Pages/ShowReport&arg=xls/issueact');
  expect(resp.status()).toBe(200);
  expect(resp.headers()['content-type']).toContain('spreadsheetml');
  expect((await resp.body()).length).toBeGreaterThan(1000);
});
