// Журнал правок документов и запрет менять содержимое проведенного документа. ZIPPY_GAPS п. 6.
// Раньше «Зберегти» на проведенном документе меняло строки, а проводки оставались от старых — остатки расходились.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, clickWait, messages } = require('../helpers/zippy');
const { value, stockQty, esc } = require('../helpers/db');
const { webPhp } = require('../helpers/stand');

async function editFirstRowQty(page, id, qty) {
  await page.goto('/index.php?p=App/Pages/Doc/GoodsReceipt&arg=' + id);
  await clickWait(page, '#docform a[id^=edit_]:visible');
  await page.locator('#editquantity').fill(String(qty));
  await clickWait(page, '#saverow');
  if (await page.locator('#editdetail:visible').count()) await clickWait(page, '#cancelrow');
  await clickWait(page, '#savedoc');
}

test('правка проведенного документа: запрет, отмена проведения, журнал', async ({ page, uniq }) => {
  const code = 'AU-' + uniq, notes = 'Тест журнала правок ' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  await createCustomer(page, { name: 'Донор ' + uniq, type: 2 });
  await createDoc(page, 'GoodsReceipt', { date: '2026-10-01', customer: 'Донор ' + uniq, payment: 0, notes, rows: [{ code, qty: 5, price: 0 }] });
  const id = value(`select document_id from documents where notes = '${esc(notes)}'`);

  await editFirstRowQty(page, id, 7);
  expect((await messages(page)).join(' ')).toMatch(/скасуйте проведення/);
  expect(stockQty(code)).toBe(5);
  expect(value(`select count(*) from sqs_doclog where document_id = ${id}`)).toBe('0');

  // отменить проведение в журнале, исправить, провести
  await page.goto('/index.php?p=App/Pages/Register/DocList');
  const number = value(`select document_number from documents where document_id = ${id}`);
  await page.locator('#filter #searchnumber').fill(number);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchnumber').press('Enter')]);
  await clickWait(page, 'a[id^=cancel_]:visible');
  await editFirstRowQty(page, id, 7);
  await page.goto('/index.php?p=App/Pages/Doc/GoodsReceipt&arg=' + id);
  await clickWait(page, '#execdoc');
  expect(stockQty(code)).toBe(7);

  const log = value(`select group_concat(changes) from sqs_doclog where document_id = ${id}`);
  expect(log).toContain('"field":"rows"');
  // время правки — по часовому поясу программы, а не базы (в Docker база в UTC, программа в Europe/Minsk)
  // запись — строкой «дата время», журнал показывает её как есть, поэтому строка должна быть по времени программы
  const logged = value(`select max(createdon) from sqs_doclog where document_id = ${id}`);
  const now = webPhp('-r', `echo strtotime(date("Y-m-d H:i:s")) - strtotime("${logged}");`);
  expect(Math.abs(Number(now)), 'расхождение времени записи журнала со временем программы, с').toBeLessThan(600);
  expect(log).toMatch(/"old":"5 × [^"]*","new":"7 × /);

  // история в просмотре документа
  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await page.locator('#filter #searchnumber').fill(number);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchnumber').press('Enter')]);
  await clickWait(page, 'a[id^=show_]:visible');
  await page.locator('a[data-bs-target="#thist"]').click();
  await expect(page.locator('#thist')).toContainText('Позиция ' + code);
  await expect(page.locator('#thist')).toContainText(/5 × .* → 7 × /);
});
