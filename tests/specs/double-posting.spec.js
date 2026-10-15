// Документ нельзя провести второй раз: повторное «Провести» на уже проведённом документе
// (открыли из журнала, «назад» в браузере, вторая вкладка) удваивало остатки. ZIPPY_GAPS п. 1.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, clickWait, messages } = require('../helpers/zippy');
const { value, stockQty, esc } = require('../helpers/db');

test('повторное «Провести» открытого проведённого прихода не удваивает остаток', async ({ page, uniq }) => {
  const code = 'DP-' + uniq, donor = 'Донор ' + uniq, notes = 'Тест двойного проведения ' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  await createCustomer(page, { name: donor, type: 2 });
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
  await createItem(page, { name: 'Позиция ' + code, code });
  await createCustomer(page, { name: donor, type: 2 });
  await createDoc(page, 'GoodsReceipt', { date: '2026-10-01', customer: donor, payment: 0, notes, dblclick: true, rows: [{ code, qty: 5, price: 0 }] });
  await page.waitForTimeout(2000); // второй запрос мог ещё обрабатываться
  expect(Number(value(`select count(*) from documents where notes = '${esc(notes)}'`)), 'документов').toBe(1);
  expect(stockQty(code), 'остаток').toBe(5);
});

test('после отмены проведения документ проводится снова', async ({ page, uniq }) => {
  const code = 'DR-' + uniq, donor = 'Донор ' + uniq, notes = 'Тест перепроведения ' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  await createCustomer(page, { name: donor, type: 2 });
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

