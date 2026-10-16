// Режим серий у позиции (ZIPPY_GAPS п. 8): в одной базе расходник партиями со сроком годности и оборудование по серийным
// номерам. Модули форка считали любой номер в store_stock.snumber единицей оборудования: партии расходника попадали в
// отчеты оборудования и в выдачу единиц, а сам расходник с партиями пропадал из выдачи расходников и карточки получателя.
// Общий режим — как у хосписа (серийные номера); режим позиции задается в items.detail (<snmode>2</snmode>), пока у
// справочника нет поля для него.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, createContract, pickAuto, clickWait, messages } = require('../helpers/zippy');
const { query, value, esc } = require('../helpers/db');

test('партии расходника — не оборудование; выдача расходников по партиям, сначала ближайший срок', async ({ page, uniq }) => {
  const cons = 'BT-' + uniq, eq = 'EQ-' + uniq, who = 'Получатель ' + uniq;
  const batch = n => `П${n}-${uniq}`, inv = n => `INV${n}-${uniq}`;
  await createItem(page, { name: 'Раствор ' + cons, code: cons, serial: true });
  await createItem(page, { name: 'Концентратор ' + eq, code: eq, serial: true });
  query(`update items set detail = replace(detail, '<useserial>1</useserial>', '<useserial>1</useserial><snmode>2</snmode>') where item_code = '${cons}'`);
  await createCustomer(page, { name: who, type: 1 });
  await createDoc(page, 'IncomeItem', { date: '2026-09-01', rows: [
    { code: cons, qty: 10, price: 0, sn: batch(1) }, { code: cons, qty: 5, price: 0, sn: batch(2) }, { code: cons, qty: 4, price: 0, sn: batch(3) },
    { code: eq, qty: 1, price: 0, sn: inv(1) }, { code: eq, qty: 1, price: 0, sn: inv(2) }] });
  // срок годности партий (у формы прихода поля срока нет в режиме серийных номеров): 3 — просрочена, 2 — ближе, чем 1
  for (const [n, d] of [[1, '2030-06-01'], [2, '2029-01-01'], [3, '2020-01-01']]) {
    query(`update store_stock set sdate = '${d}' where snumber = '${batch(n)}'`);
  }
  await createContract(page, { number: 'Д' + uniq, customer: who, from: '2026-10-01', to: '2099-12-31', note: 'Врач Тестовый' });
  await createDoc(page, 'GoodsIssue', { date: '2026-10-02', customer: who, payment: 0, contract: 'Д' + uniq, rows: [{ code: eq, qty: 1, price: 0, sn: inv(1) }] });

  // выдача расходников: позиция с партиями есть, остаток без просроченной партии; 7 шт — 5 из партии 2, 2 из партии 1
  await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
  await pickAuto(page, '#cform #customer', who, who);
  await page.locator('#cform #date').fill('2026-10-10');
  await pickAuto(page, '#cform #item', cons, 'на складі 15');
  await page.locator('#cform #qty').fill('7');
  await clickWait(page, '#cform #additem');
  await clickWait(page, '#cform #issue');
  const doc = value(`select d.document_id from documents d join metadata m on m.meta_id = d.meta_id join customers c on c.customer_id = d.customer_id
                     where m.meta_name = 'GoodsIssue' and c.customer_name = '${esc(who)}' order by d.document_id desc`);   // последняя — выдача расходников, раньше — концентратор по договору
  expect(doc, (await messages(page)).join(' ')).not.toBeNull();
  expect(query(`select s.snumber, -cast(sum(e.quantity) as signed) from entrylist e join store_stock s on s.stock_id = e.stock_id where e.document_id = ${doc} group by s.snumber order by s.snumber`))
    .toEqual([[batch(1), '2'], [batch(2), '5']]);

  // единицы для выдачи оборудования — только номера оборудования
  await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
  await page.locator('#iform #unit').pressSequentially(uniq, { delay: 30 });
  await page.waitForTimeout(1500);
  const offered = await page.locator('li a.dropdown-item:visible').allInnerTexts();
  expect(offered.join(' | ')).toContain(inv(2));
  expect(offered.join(' | ')).not.toContain('П');

  // где оборудование, журнал выдач, справочник единиц: без партий
  await page.goto('/index.php?p=App/Pages/Report/EquipmentWhere');
  await page.locator('#filter #searchtext').fill(uniq);
  await page.locator('#filter #state').selectOption('all');
  await clickWait(page, '#filter #onreport');
  let text = await page.locator('#preview').innerText();
  expect(text).toContain(inv(1));
  expect(text).toContain(inv(2));
  expect(text).not.toContain(batch(1));

  await page.goto('/index.php?p=App/Pages/Report/EquipmentJournal');
  await page.locator('#filter #from').fill('2026-01-01');
  await page.locator('#filter #to').fill('2026-12-31');
  await page.locator('#filter #searchtext').fill(uniq);
  await clickWait(page, '#filter #onreport');
  text = await page.locator('#preview').innerText();
  expect(text).toContain(inv(1));
  expect(text).not.toContain(batch(1));
  expect(text).not.toContain(batch(2));

  await page.goto('/index.php?p=App/Pages/Reference/EquipmentUnits');
  await page.locator('#filter #kind').selectOption('all');
  await page.locator('#filter #searchtext').fill(uniq);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchtext').press('Enter')]);
  text = await page.locator('body').innerText();
  expect(text).toContain(inv(2));
  expect(text).not.toContain(batch(1));

  // акт выдачи за октябрь: раствор — в акте расходников, концентратор — в акте оборудования
  const act = async kind => {
    await page.goto('/index.php?p=App/Pages/Report/IssueAct');
    await page.locator('#filter #year').selectOption('2026');
    await page.locator('#filter #month').selectOption('10');
    await page.locator('#filter #kind').selectOption(String(kind));
    await clickWait(page, '#filter #onreport');
    return page.locator('#preview').innerText();
  };
  text = await act(1);
  expect(text).toContain('Раствор ' + cons);
  expect(text).not.toContain('Концентратор ' + eq);
  text = await act(2);
  expect(text).toContain('Концентратор ' + eq);
  expect(text).not.toContain('Раствор ' + cons);

  // карточка получателя: концентратор — на руках, раствор — в расходниках (7 шт), не в оборудовании
  const cid = value(`select customer_id from customers where customer_name = '${esc(who)}'`);
  await page.goto(`/index.php?p=App/Pages/Report/RecipientCard&arg=${cid}`);
  await page.locator('#filter #from').fill('2026-06-01');
  await page.locator('#filter #to').fill('2026-12-31');
  await clickWait(page, '#filter #onreport');
  const units = await page.locator('#preview table.sqs-card-units').innerText();
  expect(units).toContain(inv(1));
  expect(units).not.toContain('Раствор');
  expect(await page.locator('#preview table.sqs-card-items tr', { hasText: 'Раствор ' + cons }).innerText()).toMatch(/\b7\b/);
});
