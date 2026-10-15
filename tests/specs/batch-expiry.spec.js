// При выдаче товара с сериями форма должна подставлять партию с БЛИЖАЙШИМ сроком годности
// (иначе первыми истекают оставшиеся на складе). ZIPPY_GAPS п. 4.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, pickAuto, clickWait } = require('../helpers/zippy');

test('выдача подставляет партию с ближайшим сроком годности', async ({ page, uniq }) => {
  const code = 'BX-' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code, serial: true });
  await createCustomer(page, { name: 'Донор ' + uniq, type: 2 });
  // дальнюю партию заводим первой, чтобы порядок прихода не совпадал с порядком сроков
  await createDoc(page, 'GoodsReceipt', { date: '2026-10-01', customer: 'Донор ' + uniq, payment: 0, rows: [
    { code, qty: 5, price: 0, sn: 'FAR-' + uniq, sdate: '2028-12-31' },
    { code, qty: 5, price: 0, sn: 'NEAR-' + uniq, sdate: '2027-01-31' },
  ] });

  await page.goto('/index.php?p=App/Pages/Doc/GoodsIssue');
  await clickWait(page, '#addrow');
  await pickAuto(page, '#edittovar', code, '', code);
  await expect(page.locator('#editserial')).toHaveValue('NEAR-' + uniq);
});
