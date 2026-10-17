// Сумма прописью в печатных формах — в валюте из настроек: хоспису (Беларусь) нужны рубли, у автора всегда гривны.
const { test, expect } = require('../fixtures');
const { createDoc, clickWait, printForm } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { value, esc } = require('../helpers/db');

async function setMoneyWords(page, value) {
  await page.goto('/index.php?p=App/Pages/Options');
  await page.locator('#common #money2str').selectOption(value);
  await clickWait(page, '#common button[type=submit]');
}

test.afterEach(async ({ page }) => setMoneyWords(page, 'ua'));

test('сумма прописью в рублях и в гривнах', async ({ page, uniq }) => {
  const code = 'MW-' + uniq, recipient = 'Получатель ' + uniq;
  seed({ items: [{ name: 'Позиция ' + code, code }], customers: [{ name: recipient, type: 1 }],
         docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 5, price: 10 }] }] });
  const { number } = await createDoc(page, 'GoodsIssue', { date: '2026-10-02', customer: recipient, payment: 1, rows: [{ code, qty: 1, price: 21.5 }] });

  const id = value(`select document_id from documents where document_number = '${esc(number)}' order by document_id desc limit 1`);

  await setMoneyWords(page, 'ru');
  expect(await printForm(page, id)).toMatch(/Двадцать один рубль 50 коп/i);

  await setMoneyWords(page, 'ua');
  expect(await printForm(page, id)).toMatch(/Двадцять одна гривня 50 коп/i);
});
