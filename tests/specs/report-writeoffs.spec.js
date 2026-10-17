// Списания с причинами за период — для бухгалтерии: причина — примечание документа «Списание ТМЦ» (ответ хосписа 3.2,
// справочник причин не нужен). Строка — позиция документа с инвентарным номером, отбор по тексту причины, выгрузка в Excel.
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { xlsxText } = require('../helpers/xlsx');

test('списания с причинами: позиции, инвентарный номер, отбор по причине, Excel', async ({ page, uniq }) => {
  const a = 'WA-' + uniq, eq = 'WE-' + uniq, inv = 'INV-W' + uniq, r = 'Получатель ' + uniq + ' списания';
  const { docs } = seed({
    items: [{ name: 'Пеленки ' + a, code: a }, { name: 'Отсасыватель ' + eq, code: eq, serial: true }],
    customers: [{ name: r, type: 1 }],
    docs: [
      { type: 'IncomeItem', date: '2026-05-01', rows: [{ code: a, qty: 20, price: 0 }, { code: eq, qty: 1, price: 0, sn: inv }] },
      { type: 'OutcomeItem', date: '2026-05-10', notes: 'Причина: порча — намокла упаковка', rows: [{ code: a, qty: 3 }] },
      { type: 'OutcomeItem', date: '2026-05-20', notes: 'Причина: сломан, ремонт невозможен', rows: [{ code: eq, qty: 1, sn: inv }] },
      { type: 'OutcomeItem', date: '2026-06-02', notes: 'Причина: порча — вне периода', rows: [{ code: a, qty: 1 }] },
      { type: 'GoodsIssue', date: '2026-05-12', customer: r, rows: [{ code: a, qty: 5, price: 0 }] },   // выдача — не списание
    ],
  });
  const [, { number: w1 }, { number: w2 }] = docs;

  await page.goto('/');
  await expect(page.locator('a[href*="Report/WriteOffs"]').first(), 'пункт меню модуля reports').toBeAttached();
  const report = async reason => {
    await page.goto('/index.php?p=App/Pages/Report/WriteOffs');
    await page.locator('#filter #from').fill('2026-05-01');
    await page.locator('#filter #to').fill('2026-05-31');
    if (reason) await page.locator('#filter #reason').fill(reason);
    await clickWait(page, '#filter #onreport');
    const rows = [];
    for (const tr of await page.locator('#preview table.sqs-writeoffs tr').all()) rows.push((await tr.locator('td').allInnerTexts()).map(s => s.trim()));
    return rows.filter(c => c.length == 7 && (c[3].includes(uniq)));
  };

  let rows = await report();
  expect(rows).toHaveLength(2);
  expect(rows[0]).toEqual(['10.05.2026', w1, expect.any(String), 'Пеленки ' + a, '', expect.stringMatching(/^3\b/), 'Причина: порча — намокла упаковка']);
  expect(rows[1].slice(0, 5)).toEqual(['20.05.2026', w2, expect.any(String), 'Отсасыватель ' + eq, inv]);
  expect(rows[1][6]).toBe('Причина: сломан, ремонт невозможен');

  const xls = await xlsxText(page.context().request, '/index.php?p=App/Pages/ShowReport&arg=xls/writeoffs');
  expect(xls).toContain('Причина: порча — намокла упаковка');
  expect(xls).toContain(inv);

  rows = await report('сломан');
  expect(rows.map(c => c[1])).toEqual([w2]);

  // из отчета — в документ
  await page.goto('/index.php?p=App/Pages/Report/WriteOffs');
  await page.locator('#filter #from').fill('2026-05-01');
  await page.locator('#filter #to').fill('2026-05-31');
  await clickWait(page, '#filter #onreport');
  await clickWait(page, `#preview a.sqs-doc:text-is("${w1}")`);
  await expect(page).toHaveURL(/Register\/DocList/);
});
