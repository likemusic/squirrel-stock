// Журнал выдачи оборудования за период: строка — выданная единица, договор, получатель, срок, дата возврата.
// Второй взгляд на те же данные, что «Оборудование: где сейчас» (у хосписа это две таблицы со сверкой). ZIPPY_GAPS п. 10.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, createContract, clickWait, messages } = require('../helpers/zippy');
const { value, esc } = require('../helpers/db');

test('журнал выдачи оборудования: выдача, возврат, повторная выдача', async ({ page, uniq }) => {
  const code = 'EJ-' + uniq, name = 'Кровать ' + uniq, r1 = 'Получатель ' + uniq + ' один', r2 = 'Получатель ' + uniq + ' два';
  const inv = 'INV-' + uniq;
  await createItem(page, { name, code, serial: true });
  await createCustomer(page, { name: r1, type: 1 });
  await createCustomer(page, { name: r2, type: 1 });
  await createDoc(page, 'IncomeItem', { date: '2026-08-01', rows: [{ code, qty: 1, price: 0, sn: inv }] });
  await createContract(page, { number: 'Ж1-' + uniq, customer: r1, from: '2026-08-02', to: '2026-09-30', note: 'Отделение 1' });
  const { number } = await createDoc(page, 'GoodsIssue', { date: '2026-08-02', customer: r1, payment: 0, contract: 'Ж1-' + uniq, rows: [{ code, qty: 1, price: 0, sn: inv }] });
  const issueId = value(`select document_id from documents where document_number = '${esc(number)}' order by document_id desc limit 1`);
  await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + issueId);
  await page.locator('#document_date').fill('2026-08-20');
  await clickWait(page, '#execdoc');
  expect(await page.locator('#docform #execdoc:visible').count(), (await messages(page)).join(' ')).toBe(0);
  await createContract(page, { number: 'Ж2-' + uniq, customer: r2, from: '2026-08-25', to: '2099-12-31', note: 'Отделение 2' });
  await createDoc(page, 'GoodsIssue', { date: '2026-08-25', customer: r2, payment: 0, contract: 'Ж2-' + uniq, rows: [{ code, qty: 1, price: 0, sn: inv }] });

  await page.goto('/index.php?p=App/Pages/Report/EquipmentJournal');
  await page.locator('#filter #from').fill('2026-08-01');
  await page.locator('#filter #to').fill('2026-08-31');
  await clickWait(page, '#filter #onreport');
  const table = page.locator('#preview table');
  const head = (await table.locator('tr').nth(1).locator('th').allInnerTexts()).map(s => s.trim());
  const row = async who => {
    const td = (await table.locator('tr', { hasText: who }).locator('td').allInnerTexts()).map(s => s.trim());
    return Object.fromEntries(head.map((h, i) => [h, td[i]]));
  };
  expect(await row(r1)).toMatchObject({ 'Договір': 'Ж1-' + uniq, 'Дата видачі': '02.08.2026', 'Строк': '30.09.2026', 'Дата повернення': '20.08.2026', 'Інв. №': inv, 'Ким направлено': 'Отделение 1' });
  expect(await row(r2)).toMatchObject({ 'Договір': 'Ж2-' + uniq, 'Дата видачі': '25.08.2026', 'Строк': 'до запитання', 'Дата повернення': '' });
});
