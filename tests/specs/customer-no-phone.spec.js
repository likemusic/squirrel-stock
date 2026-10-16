// Получатель без телефона в подсказке контрагента (Customer::getList, concat(customer_name, ' ', phone)): формы выдачи
// расходников и оборудования создают получателя без телефона (phone = NULL), и concat с NULL давал пустую строку.
const { test, expect } = require('../fixtures');
const { createItem, createDoc, pickAuto, clickWait, messages } = require('../helpers/zippy');
const { value, esc } = require('../helpers/db');

test('получатель из «Выдачи расходников» находится в подсказке расходной накладной', async ({ page, uniq }) => {
  const code = 'NP-' + uniq, who = 'Получатель ' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code, qty: 5, price: 0 }] });

  await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
  await page.locator('#cform #customer').fill(who);
  await page.locator('#cform #date').fill('2026-10-10');
  await pickAuto(page, '#cform #item', code, code);
  await page.locator('#cform #qty').fill('1');
  await clickWait(page, '#cform #additem');
  await clickWait(page, '#cform #issue');
  expect(value(`select count(*) from customers where customer_name = '${esc(who)}' and phone is null`), (await messages(page)).join(' ')).toBe('1');

  await page.goto('/index.php?p=App/Pages/Doc/GoodsIssue');
  await page.locator('#docform #customer').pressSequentially(uniq, { delay: 30 });
  const option = page.locator('li a.dropdown-item:visible', { hasText: who });
  await expect(option, 'получатель в подсказке').toHaveCount(1, { timeout: 8000 });
});
