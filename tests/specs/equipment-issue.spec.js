// Выдача оборудования одной формой «договор + выдача» (ответ хосписа 1.3) и заводской номер единицы (1.1).
// Форма создает получателя, договор «В работе» и проведенную расходную накладную по нему. ZIPPY_GAPS п. 10.
const { test, expect } = require('../fixtures');
const { pickAuto, clickWait, messages } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { value, esc } = require('../helpers/db');

test('договор и выдача одной формой, поиск единицы по заводскому номеру', async ({ page, uniq }) => {
  const code = 'EI-' + uniq, name = 'Концентратор ' + uniq, who = 'Получатель ' + uniq;
  const inv1 = 'INV1-' + uniq, inv2 = 'INV2-' + uniq, factory = 'ZAV-' + uniq;
  const item = seed({ items: [{ name, code, serial: true }],
                      docs: [{ type: 'IncomeItem', date: '2026-09-01', rows: [{ code, qty: 1, price: 0, sn: inv1 }, { code, qty: 1, price: 0, sn: inv2 }] }] }).items[code];
  value(`insert into sqs_unit (item_id, snumber, factory_no) values (${item}, '${esc(inv2)}', '${esc(factory)}')`);

  await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
  const number = await page.locator('#iform #contract_number').inputValue();
  expect(number, 'номер договора предлагается').toMatch(/^\d+\/\d\d$/);
  await page.locator('#iform #customer').fill(who);   // новый получатель — просто вписать
  await page.locator('#iform #phone').fill('0291234567');
  await page.locator('#iform #address').fill('ул. Тестовая, 1');
  await page.locator('#iform #date').fill('2026-10-05');
  await page.locator('#iform #referrer').fill('Врач Тестовый');
  await pickAuto(page, '#iform #unit', factory, inv2);   // поиск по заводскому номеру
  await clickWait(page, '#iform #addunit');
  await expect(page.locator('#iform [id^=unitlabel]')).toHaveCount(1);
  await expect(page.locator('#iform [id^=unitlabel]').first()).toContainText(factory);
  await clickWait(page, '#iform #issue');
  await expect(page.locator('.toast-message, .alert-success').first(), 'сообщение о выдаче').toContainText(number);   // ждать сообщение, а не читать сразу

  const c = value(`select concat(c.state, '|', c.details like '%<shortdesc><![CDATA[Врач Тестовый]]>%', '|', substring_index(substring_index(c.details, '<enddate>', -1), '</enddate>', 1), '|', cu.phone)
                   from contracts c join customers cu on cu.customer_id = c.customer_id where c.contract_number = '${esc(number)}' and cu.customer_name = '${esc(who)}'`);
  // срок — метка времени (from_unixtime в MariaDB 10.11 не понимает даты после 2038 года); стенд в Europe/Minsk, UTC+3
  const [state, ref, end, phone] = c.split('|');
  expect([state, ref, new Date((Number(end) + 3 * 3600) * 1000).toISOString().slice(0, 10), phone], 'договор: «В работе», кем направлен, до востребования, телефон').toEqual(['6', '1', '2099-12-31', '0291234567']);
  const d = value(`select concat(d.state, '|', date(d.document_date)) from documents d join metadata m on m.meta_id = d.meta_id join customers cu on cu.customer_id = d.customer_id
                   where m.meta_name = 'GoodsIssue' and cu.customer_name = '${esc(who)}'`);
  expect(d, 'накладная проведена датой договора').toBe('5|2026-10-05');
  expect(Number(value(`select coalesce(sum(qty), 0) from store_stock where item_id = ${item} and snumber = '${esc(inv2)}'`)), 'выданная единица').toBe(0);
  expect(Number(value(`select coalesce(sum(qty), 0) from store_stock where item_id = ${item} and snumber = '${esc(inv1)}'`)), 'остальная — на складе').toBe(1);
});

test('справочник единиц: заводской номер задается и ищется', async ({ page, uniq }) => {
  const code = 'EU-' + uniq, name = 'Отсасыватель ' + uniq, inv = 'INV-' + uniq, factory = 'F-' + uniq;
  seed({ items: [{ name, code, serial: true }], docs: [{ type: 'IncomeItem', date: '2026-09-01', rows: [{ code, qty: 1, price: 0, sn: inv }] }] });

  const search = async text => {
    await page.goto('/index.php?p=App/Pages/Reference/EquipmentUnits');
    await page.locator('#filter #searchtext').fill(text);
    await clickWait(page, '#filter input[type=submit]');
    return page.locator('tr', { hasText: inv });
  };
  let row = await search(inv);
  await expect(row).toHaveCount(1);
  await expect(row.locator('td').nth(3)).toContainText(/склад/i);
  await clickWait(page, `tr:has-text("${inv}") a[id^=edit]`);
  await page.locator('#editform #editfactory').fill(factory);
  await clickWait(page, '#editform #save');

  row = await search(factory);
  await expect(row).toHaveCount(1);
  await expect(row.locator('td').nth(2)).toHaveText(factory);
  const item = value(`select item_id from items where item_code = '${esc(code)}'`);
  expect(value(`select factory_no from sqs_unit where item_id = ${item} and snumber = '${esc(inv)}'`)).toBe(factory);
});

// Раньше выдача задним числом до прихода проходила (ОСВ 01 загружали после истории, датой ведомости), и единица в отчетах
// на прошлые даты числилась выданной раньше, чем появилась. С 16.10.2026 ядро не выдает номер, которого нет на складе на дату
// документа (App\SQS\SerialStock); при переносе ОСВ 01 приходуется датой раньше первой выдачи (поле «Дата оприходования»).
test('выдача оборудования раньше даты прихода — отказ с понятной причиной', async ({ page, uniq }) => {
  const code = 'EL-' + uniq, inv = 'INV-' + uniq, who = 'Получатель ' + uniq;
  seed({ items: [{ name: 'Ингалятор ' + uniq, code, serial: true }], docs: [{ type: 'IncomeItem', date: '2026-10-10', rows: [{ code, qty: 1, price: 0, sn: inv }] }] });
  await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
  await page.locator('#iform #customer').fill(who);
  await page.locator('#iform #date').fill('2026-09-01');
  await pickAuto(page, '#iform #unit', inv, inv);
  await clickWait(page, '#iform #issue');
  expect((await messages(page)).join(' ')).toContain(`${inv}`);
  expect((await messages(page)).join(' ')).toContain('немає на складі на 01.09.2026');

  await page.goto('/index.php?p=App/Pages/Reference/EquipmentUnits');
  await page.locator('#filter #searchtext').fill(inv);
  await clickWait(page, '#filter input[type=submit]');
  await expect(page.locator('tr', { hasText: inv }).locator('td').nth(3)).toContainText(/склад/i);
  expect(value(`select count(*) from customers where customer_name = '${esc(who)}'`)).toBe('0');
});

test('«кем направлен» подставляется из последнего договора получателя, поле можно поправить', async ({ page, uniq }) => {
  const who = 'Получатель ' + uniq, other = 'Новый получатель ' + uniq;
  const cust = value(`insert into customers (customer_name, detail, phone, status) values ('${esc(who)}', '<detail><type>0</type></detail>', '029${uniq.replace(/\D/g, '').slice(-7)}', 0); select last_insert_id()`);
  const contract = (num, ref, day) => value(`insert into contracts (customer_id, createdon, contract_number, state, details)
    values (${cust}, '2026-${day}', '${esc(num)}', 6, '<details><shortdesc><![CDATA[${ref}]]></shortdesc><desc><![CDATA[]]></desc></details>')`);
  contract('A-' + uniq, 'Врач Прежний', '09-01');
  contract('B-' + uniq, 'Врач Последний', '10-01');
  contract('C-' + uniq, '', '10-02');   // договор без «кем направлен» не затирает прежнее значение
  try {
    await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
    const ref = page.locator('#iform #referrer');
    await pickAuto(page, '#iform #customer', who, who);
    await expect(ref, 'из последнего договора с заполненным полем').toHaveValue('Врач Последний');

    await ref.fill('Врач Поправленный');
    await pickAuto(page, '#iform #customer', who, who);
    await expect(ref, 'поправленное вручную не перезаписывается').toHaveValue('Врач Поправленный');

    await ref.fill('');
    await pickAuto(page, '#iform #customer', who, who);
    await expect(ref).toHaveValue('Врач Последний');
    await page.locator('#iform #customer').fill(other);   // новый получатель — подсказки нет, поле не трогается
    await page.locator('#iform #customer').blur();
    await expect(ref).toHaveValue('Врач Последний');
  } finally {
    value(`delete from contracts where customer_id = ${cust}`);
    value(`delete from customers where customer_id = ${cust}`);
  }
});
