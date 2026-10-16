// Напоминание на главной: выданное оборудование с истекшим или истекающим в ближайшие 14 дней сроком (ответ хосписа 1.4).
// «До востребования» в напоминание не попадает. ZIPPY_GAPS п. 10.
const { test, expect } = require('../fixtures');
const { query, esc } = require('../helpers/db');
const { createItem, createCustomer, createContract, createDoc, pickAuto, clickWait } = require('../helpers/zippy');

// дата по местному времени (стенд — Europe/Minsk): после полуночи по UTC еще вчерашний день
const day = n => new Date(Date.now() + n * 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

test('напоминание о сроке пользования на главной', async ({ page, uniq }) => {
  const code = 'ED-' + uniq, name = 'Кровать ' + uniq;
  const inv = n => `INV${n}-${uniq}`;
  await createItem(page, { name, code, serial: true });
  await createDoc(page, 'IncomeItem', { date: day(-60), rows: [1, 2, 3].map(n => ({ code, qty: 1, price: 0, sn: inv(n) })) });

  const issue = async (n, end) => {
    await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
    await page.locator('#iform #customer').fill(`Получатель ${n} ${uniq}`);
    await page.locator('#iform #date').fill(day(-30));
    if (end) {
      await page.locator('#iform #ondemand').setChecked(false);
      await page.locator('#iform #enddate').fill(end);
    }
    await pickAuto(page, '#iform #unit', inv(n), inv(n));
    await clickWait(page, '#iform #issue');
  };
  await issue(1, day(-3));   // просрочено
  await issue(2, day(5));    // истекает
  await issue(3);            // до востребования

  await page.goto('/');
  const box = page.locator('#sqs-due');
  await expect(box).toBeVisible();
  await expect(box.locator('tr', { hasText: inv(1) }).locator('td').last()).toHaveClass(/text-danger/);
  await expect(box.locator('tr', { hasText: inv(1) })).toContainText('3');
  await expect(box.locator('tr', { hasText: inv(2) })).toHaveCount(1);
  await expect(box.locator('tr', { hasText: inv(3) })).toHaveCount(0);
});

// мелкое оборудование выдается количеством по договору (ответ хосписа 1.5, 1.13): срок договора тоже напоминается
test('напоминание о сроке: мелкое оборудование по договору', async ({ page, uniq }) => {
  const code = 'EDS-' + uniq, name = 'Ходунки ' + uniq, r = 'Получатель ' + uniq + ' мелочь';
  await createItem(page, { name, code });
  await createCustomer(page, { name: r, type: 1 });
  await createDoc(page, 'IncomeItem', { date: day(-60), rows: [{ code, qty: 5, price: 0 }] });
  await createContract(page, { number: 'КМ' + uniq, customer: r, from: day(-30), to: day(4), note: 'Врач Вымышленный' });
  await createDoc(page, 'GoodsIssue', { date: day(-30), customer: r, payment: 0, contract: 'КМ' + uniq, rows: [{ code, qty: 2, price: 0 }] });

  await page.goto('/');
  const row = page.locator('#sqs-due tr', { hasText: name });
  await expect(row).toHaveCount(1);
  await expect(row).toContainText(r);
  await expect(row).toContainText('КМ' + uniq);
  await expect(row).toContainText('2');
  await expect(row.locator('a.sqs-extend')).toHaveCount(1);

  // «Продлить» — договор только с мелочью: новый срок дальше двух недель, напоминание уходит
  await clickWait(page, `#sqs-due tr:has-text("${name}") a.sqs-extend`);
  await expect(page.locator('body')).toContainText(name);
  await page.locator('#eform #enddate').fill(day(40));
  await clickWait(page, '#eform #save');
  await page.goto('/');
  await expect(page.locator('#sqs-due tr', { hasText: name })).toHaveCount(0);
  await page.goto('/index.php?p=App/Pages/Service/EquipmentExtend&arg=' + query(`select contract_id from contracts where contract_number = 'КМ${uniq}'`)[0][0]);
  await page.locator('#eform #enddate').fill(day(3));
  await clickWait(page, '#eform #save');
  await page.goto('/');
  await expect(page.locator('#sqs-due tr', { hasText: name })).toHaveCount(1);

  // вернули все — напоминания нет
  const issueId = query(`select document_id from documents where customer_id = (select customer_id from customers where customer_name = '${esc(r)}') order by document_id desc limit 1`)[0][0];
  await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + issueId);
  await page.locator('#document_date').fill(day(-1));
  await clickWait(page, '#execdoc');
  await page.goto('/');
  await expect(page.locator('#sqs-due tr', { hasText: name })).toHaveCount(0);
});
