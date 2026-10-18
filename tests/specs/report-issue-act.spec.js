// Акт списания за месяц в формате бухгалтерии хосписа: строки — позиции, колонки — выдачи «Получатель дд.мм.гггг»,
// в ячейках количество за вычетом возвратов, итог по позиции и по выдаче; выгрузка в Excel. ZIPPY_GAPS п. 14.
const { test, expect } = require('../fixtures');
const { clickWait, messages } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { xlsxText } = require('../helpers/xlsx');

test('акт списания: матрица позиции × выдачи с итогами', async ({ page, uniq }) => {
  const a = 'AC-' + uniq + '-A', b = 'AC-' + uniq + '-B';
  const r1 = 'Получатель ' + uniq + ' один', r2 = 'Получатель ' + uniq + ' два';
  const { docs } = seed({
    items: [{ name: 'Позиция ' + a, code: a }, { name: 'Позиция ' + b, code: b }],
    customers: [{ name: r1, type: 1 }, { name: r2, type: 1 }],
    docs: [
      { type: 'IncomeItem', date: '2026-09-01', rows: [{ code: a, qty: 50, price: 0 }, { code: b, qty: 50, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-09-03', customer: r1, rows: [{ code: a, qty: 4, price: 0 }, { code: b, qty: 2, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-09-10', customer: r1, rows: [{ code: a, qty: 1, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-09-12', customer: r2, rows: [{ code: b, qty: 3, price: 0 }] },
    ],
  });
  // выдачу второму получателю вернули целиком (формой возврата): в акте ее быть не должно
  const issueId = docs[3].id;
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

// ответ хосписа 3.1: отдельные акты на расходники и на оборудование (единица с инвентарным номером, мелочь по договору)
test('акт списания: отдельно расходники и оборудование', async ({ page, uniq }) => {
  const r = 'Получатель ' + uniq + ' акт';
  const eq = 'AE-' + uniq, small = 'AS-' + uniq, a = 'AC-' + uniq, inv = 'INV-A' + uniq;
  seed({
    items: [{ name: 'Концентратор ' + eq, code: eq, serial: true }, { name: 'Ходунки ' + small, code: small }, { name: 'Шприц ' + a, code: a }],
    customers: [{ name: r, type: 1 }],
    contracts: [{ number: 'КА' + uniq, customer: r, from: '2026-08-03', to: '2026-12-31', note: 'Врач Вымышленный' }],
    docs: [
      { type: 'IncomeItem', date: '2026-08-01', rows: [{ code: eq, qty: 1, price: 0, sn: inv }, { code: small, qty: 5, price: 0 }, { code: a, qty: 50, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-08-03', customer: r, contract: 'КА' + uniq, rows: [{ code: eq, qty: 1, price: 0, sn: inv }, { code: small, qty: 1, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-08-05', customer: r, rows: [{ code: a, qty: 4, price: 0 }] },
    ],
  });

  const act = async kind => {
    await page.goto('/index.php?p=App/Pages/Report/IssueAct');
    await page.locator('#filter #year').selectOption('2026');
    await page.locator('#filter #month').selectOption('8');
    if (kind !== undefined) await page.locator('#filter #kind').selectOption(kind);
    await clickWait(page, '#filter #onreport');
    return page.locator('#preview').innerText();
  };
  // по умолчанию — расходники
  let text = await act();
  expect(text).toContain('Шприц ' + a);
  expect(text).not.toContain('Концентратор ' + eq);
  expect(text).not.toContain('Ходунки ' + small);
  expect(text).not.toContain(`${r} 03.08.2026`);
  expect(text).toContain('Списання витратних матеріалів');

  text = await act('2');
  expect(text).toContain('Концентратор ' + eq);
  expect(text).toContain('Ходунки ' + small);
  expect(text).not.toContain('Шприц ' + a);
  expect(text).not.toContain(`${r} 05.08.2026`);
  expect(text).toContain('Списання обладнання');

  text = await act('0');
  expect(text).toContain('Шприц ' + a);
  expect(text).toContain('Концентратор ' + eq);
});

// печать широкого акта: больше 10 выдач — на печать куски по 10 колонок с наименованием в каждом и «Разом» в последнем;
// на экране и в Excel — одна таблица
test('акт списания: печать по 10 выдач', async ({ page, uniq }) => {
  const a = 'AP-' + uniq;
  const r = n => `Получатель ${uniq} п${String(n).padStart(2, '0')}`;
  const n = 12;
  seed({
    items: [{ name: 'Позиция ' + a, code: a }],
    customers: Array.from({ length: n }, (_, i) => ({ name: r(i + 1), type: 1 })),
    docs: [
      { type: 'IncomeItem', date: '2026-04-01', rows: [{ code: a, qty: 100, price: 0 }] },
      ...Array.from({ length: n }, (_, i) => ({ type: 'GoodsIssue', date: '2026-04-' + String(i + 2).padStart(2, '0'), customer: r(i + 1), rows: [{ code: a, qty: i + 1, price: 0 }] })),
    ],
  });
  await page.goto('/index.php?p=App/Pages/Report/IssueAct');
  await page.locator('#filter #year').selectOption('2026');
  await page.locator('#filter #month').selectOption('4');
  await clickWait(page, '#filter #onreport');

  const screen = page.locator('#preview table:not(.d-print-table)');
  const parts = page.locator('#preview table.d-print-table');
  const heads = async t => (await t.locator('tr').nth(1).locator('th').allInnerTexts()).map(s => s.replace(/\s+/g, ' ').trim());
  const row = async t => (await t.locator('tr', { hasText: 'Позиция ' + a }).locator('td').allInnerTexts()).map(s => s.trim());
  const all = await heads(screen);
  const issues = all.length - 2;   // без «Найменування» и «Разом»
  expect(issues).toBeGreaterThanOrEqual(n);
  await expect(parts).toHaveCount(Math.ceil(issues / 10));
  await expect(screen).toBeVisible();
  await expect(parts.first()).toBeHidden();

  await page.emulateMedia({ media: 'print' });
  await expect(screen).toBeHidden();
  await expect(parts.first()).toBeVisible();
  const cols = [], cells = [];
  for (let i = 0; i < await parts.count(); i++) {
    const h = await heads(parts.nth(i)), c = await row(parts.nth(i));
    expect(h[0], 'наименование в каждом куске').toBe(all[0]);
    expect(h.length, 'не больше 10 выдач в куске').toBeLessThanOrEqual(i == await parts.count() - 1 ? 12 : 11);
    cols.push(...h.slice(1));
    cells.push(...c.slice(1));
  }
  expect(cols, 'все колонки и «Разом» в последнем куске').toEqual(all.slice(1));
  expect(cells).toEqual((await row(screen)).slice(1));
  expect(cells[cells.length - 1]).toBe(String(n * (n + 1) / 2));
  await page.emulateMedia({ media: 'screen' });

  const xls = await xlsxText(page.context().request, '/index.php?p=App/Pages/ShowReport&arg=xls/issueact');
  expect(xls.split('Позиция ' + a).length - 1, 'в Excel позиция один раз').toBe(1);
});
