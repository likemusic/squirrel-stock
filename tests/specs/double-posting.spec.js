// Документ нельзя провести второй раз: повторное «Провести» на уже проведённом документе
// (открыли из журнала, «назад» в браузере, вторая вкладка) удваивало остатки. ZIPPY_GAPS п. 1.
const { test, expect } = require('../fixtures');
const { createDoc, clickWait, messages } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { value, stockQty, esc } = require('../helpers/db');

test('повторное «Провести» открытого проведённого прихода не удваивает остаток', async ({ page, uniq }) => {
  const code = 'DP-' + uniq, donor = 'Донор ' + uniq, notes = 'Тест двойного проведения ' + uniq;
  seed({ items: [{ name: 'Позиция ' + code, code }], customers: [{ name: donor, type: 2 }] });
  await createDoc(page, 'GoodsReceipt', { date: '2026-10-01', customer: donor, payment: 0, notes, rows: [{ code, qty: 5, price: 0 }] });
  expect(stockQty(code)).toBe(5);

  const id = value(`select document_id from documents where notes = '${esc(notes)}'`);
  await page.goto('/index.php?p=App/Pages/Doc/GoodsReceipt&arg=' + id);
  await clickWait(page, '#execdoc');
  expect(stockQty(code), 'остаток после повторного «Провести»').toBe(5);
  expect((await messages(page)).join(' ')).toMatch(/вже проведен/i);
});

test('двойной щелчок по «Провести» проводит один документ один раз', async ({ page, uniq }) => {
  const code = 'DC-' + uniq, donor = 'Донор ' + uniq, notes = 'Тест двойного щелчка ' + uniq;
  seed({ items: [{ name: 'Позиция ' + code, code }], customers: [{ name: donor, type: 2 }] });
  await createDoc(page, 'GoodsReceipt', { date: '2026-10-01', customer: donor, payment: 0, notes, dblclick: true, rows: [{ code, qty: 5, price: 0 }] });
  await page.waitForTimeout(2000); // второй запрос мог ещё обрабатываться
  expect(Number(value(`select count(*) from documents where notes = '${esc(notes)}'`)), 'документов').toBe(1);
  expect(stockQty(code), 'остаток').toBe(5);
});

test('после отмены проведения документ проводится снова', async ({ page, uniq }) => {
  const code = 'DR-' + uniq, donor = 'Донор ' + uniq, notes = 'Тест перепроведения ' + uniq;
  seed({ items: [{ name: 'Позиция ' + code, code }], customers: [{ name: donor, type: 2 }] });
  const { number } = await createDoc(page, 'GoodsReceipt', { date: '2026-10-01', customer: donor, payment: 0, notes, rows: [{ code, qty: 5, price: 0 }] });
  const id = value(`select document_id from documents where notes = '${esc(notes)}'`);

  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await page.locator('#filter #searchnumber').fill(number);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchnumber').press('Enter')]);
  await expect(page.locator('a[id^=cancel_]:visible')).toHaveCount(1);
  await clickWait(page, 'a[id^=cancel_]:visible');
  expect(stockQty(code), 'остаток после отмены').toBe(0);

  await page.goto('/index.php?p=App/Pages/Doc/GoodsReceipt&arg=' + id);
  await clickWait(page, '#execdoc');
  expect(stockQty(code), 'остаток после повторного проведения').toBe(5);
});

test('«назад» в браузере и повторное «Провести» не создают второго проведения', async ({ page, uniq }) => {
  const code = 'DB-' + uniq, donor = 'Донор ' + uniq, notes = 'Тест кнопки назад ' + uniq;
  seed({ items: [{ name: 'Позиция ' + code, code }], customers: [{ name: donor, type: 2 }] });
  await createDoc(page, 'GoodsReceipt', { date: '2026-10-01', customer: donor, payment: 0, notes, rows: [{ code, qty: 5, price: 0 }] });
  await page.goBack();
  await page.waitForLoadState('networkidle');
  if (await page.locator('#execdoc:visible').count()) await clickWait(page, '#execdoc');
  expect(stockQty(code), 'остаток').toBe(5);
  expect(Number(value(`select count(*) from documents where notes = '${esc(notes)}'`)), 'документов').toBe(1);
});

test('копия документа из журнала сохраняется и проводится', async ({ page, uniq }) => {
  const code = 'DK-' + uniq, donor = 'Донор ' + uniq, notes = 'Тест копии ' + uniq;
  seed({ items: [{ name: 'Позиция ' + code, code }], customers: [{ name: donor, type: 2 }] });
  const { number } = await createDoc(page, 'GoodsReceipt', { date: '2026-10-01', customer: donor, payment: 0, notes, rows: [{ code, qty: 5, price: 0 }] });

  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await page.locator('#filter #searchnumber').fill(number);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchnumber').press('Enter')]);
  await clickWait(page, 'a[id^=show_]:visible');
  await clickWait(page, '#bcopy');
  expect((await messages(page)).join(' ')).not.toMatch(/вже створено/i);
  const ids = value(`select group_concat(document_id order by document_id) from documents where notes = '${esc(notes)}'`).split(',');
  expect(ids, 'исходный документ и копия').toHaveLength(2);

  await page.goto('/index.php?p=App/Pages/Doc/GoodsReceipt&arg=' + ids[1]);
  await clickWait(page, '#execdoc');
  expect(stockQty(code), 'остаток после проведения копии').toBe(10);
});
