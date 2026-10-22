// «Договор заключен с» (ответ хосписа 5.18): договор на оборудование заключен с представителем (обычно родителем) в пользу получателя.
// Получатель выдач — подопечный; представитель — поле договора (Contract::sqs_holder, тег <sqs_holder> в contracts.details), пусто — с самим получателем.
const { test, expect } = require('../fixtures');
const { pickAuto, clickWait, login, printForm, messages } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { value, esc } = require('../helpers/db');
const { webPhp } = require('../helpers/stand');

// value() отдает null на пустую строку — значение в скобках; нет тега — null
const holderOf = number => {
  const v = value(`select concat('[', substring_index(substring_index(details, '<sqs_holder><![CDATA[', -1), ']]></sqs_holder>', 1), ']') from contracts
                   where contract_number = '${esc(number)}' and details like '%<sqs_holder>%'`);
  return v == null ? null : v.slice(1, -1);
};

async function freshPage(browser) {   // профиль кэшируется в сессии — новая сессия после переключения
  const ctx = await browser.newContext({ storageState: undefined });
  const p = await ctx.newPage();
  await login(p);
  return p;
}

test.afterEach(() => webPhp('cli/charity-profile.php', '--back'));

test('форма выдачи: «Договор заключен с» сохраняется в договоре и печатается в форме профиля', async ({ page, browser, uniq }) => {
  const code = 'CH-' + uniq, who = 'Подопечный ' + uniq, parent = 'Представитель ' + uniq, inv = 'INV-' + uniq;
  seed({ items: [{ name: 'Кровать ' + uniq, code, serial: true }],
         docs: [{ type: 'IncomeItem', date: '2026-09-01', rows: [{ code, qty: 1, price: 0, sn: inv }, { code, qty: 1, price: 0, sn: inv + 'b' }] }] });

  const issue = async (number, holder, sn) => {
    await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
    await page.locator('#iform #contract_number').fill(number);
    await page.locator('#iform #customer').fill(who);
    await page.locator('#iform #date').fill('2026-10-05');
    if (holder) await page.locator('#iform #holder').fill(holder);
    await pickAuto(page, '#iform #unit', sn, sn);
    await clickWait(page, '#iform #addunit');
    await clickWait(page, '#iform #issue');
    await expect(page.locator('.toast-message, .alert-success').first()).toContainText(number);
    await expect(page.locator('#iform #holder'), 'после выдачи поле очищено').toHaveValue('');
  };
  await issue('A' + uniq, parent, inv);
  await issue('B' + uniq, '', inv + 'b');
  expect(holderOf('A' + uniq)).toBe(parent);
  expect(holderOf('B' + uniq), 'пусто — с самим получателем').toBe('');
  const doc = n => value(`select d.document_id from documents d where d.content like concat('%<contract_id>', (select contract_id from contracts where contract_number = '${esc(n)}'), '</contract_id>%')`);

  webPhp('cli/charity-profile.php');
  const p = await freshPage(browser);
  let text = await printForm(p, doc('A' + uniq));
  expect(text).toContain('Отримувач ' + who);
  expect(text).toContain('Договір укладено з ' + parent);
  text = await printForm(p, doc('B' + uniq));
  expect(text, 'без представителя строки нет').not.toContain('Договір укладено з');
  await p.context().close();
});

test('карточка договора: поле видно и правится у договора «В работе», правка других полей его не стирает', async ({ page, uniq }) => {
  const who = 'Подопечный ' + uniq, number = 'Д' + uniq;
  seed({ customers: [{ name: who, type: 1 }], contracts: [{ number, customer: who, from: '2026-10-01', to: '2099-12-31', note: 'Врач Тестовый', holder: 'Представитель Первый' }] });

  const open = async () => {
    await page.goto('/index.php?p=App/Pages/Reference/ContractList');
    await page.locator('#filter #searchkey').fill(number);
    await clickWait(page, '#filter input[type=submit], #filter button[type=submit]');
    await clickWait(page, `a:text-is("${number}")`);
  };
  await open();
  const input = page.locator('#statusform #sqs_holder');
  await expect(input).toHaveValue('Представитель Первый');
  await input.fill('Представитель Второй');
  await clickWait(page, '#statusform #sqs_bholder');
  expect(holderOf(number)).toBe('Представитель Второй');
  await expect(page.locator('body'), 'было → стало в истории договора').toContainText('Договір укладено з: Представитель Первый → Представитель Второй');

  // другая правка договора (смена ответственного) сохраняет договор целиком — поле не теряется
  await open();
  await page.locator('#statusform #musers').selectOption({ index: 1 });
  await clickWait(page, '#statusform #buser');
  expect(holderOf(number)).toBe('Представитель Второй');
  expect(value(`select count(*) from contracts where contract_number = '${esc(number)}' and details like '%Врач Тестовый%'`)).toBe('1');

  await open();
  await input.fill('');
  await clickWait(page, '#statusform #sqs_bholder');
  expect(holderOf(number), 'очищено — с самим получателем').toBe('');
});

test('«Где сейчас» и карточка получателя: представитель рядом с договором', async ({ page, uniq }) => {
  const code = 'CW-' + uniq, who = 'Подопечный ' + uniq, inv = n => `INV${n}-${uniq}`;
  const { customers } = seed({
    items: [{ name: 'Коляска ' + uniq, code, serial: true }],
    customers: [{ name: who, type: 1 }],
    contracts: [{ number: 'A' + uniq, customer: who, from: '2026-10-01', to: '2099-12-31', holder: 'Представитель ' + uniq },
                { number: 'B' + uniq, customer: who, from: '2026-10-02', to: '2099-12-31' }],
    docs: [{ type: 'IncomeItem', date: '2026-09-01', rows: [1, 2].map(n => ({ code, qty: 1, price: 0, sn: inv(n) })) },
           { type: 'GoodsIssue', date: '2026-10-02', customer: who, contract: 'A' + uniq, rows: [{ code, qty: 1, price: 0, sn: inv(1) }] },
           { type: 'GoodsIssue', date: '2026-10-03', customer: who, contract: 'B' + uniq, rows: [{ code, qty: 1, price: 0, sn: inv(2) }] }],
  });

  await page.goto('/index.php?p=App/Pages/Report/EquipmentWhere');
  await page.locator('#filter #searchtext').fill(uniq);
  await clickWait(page, '#filter #onreport');
  const row = n => page.locator('#preview table.sqs-units tr', { hasText: inv(n) });
  await expect(row(1)).toContainText(`A${uniq} від 01.10.2026, укладено з Представитель ${uniq}`);
  await expect(row(2)).toContainText(`B${uniq} від 02.10.2026`);
  await expect(row(2)).not.toContainText('укладено з');

  await page.goto('/index.php?p=App/Pages/Report/RecipientCard&arg=' + customers[who]);
  const card = page.locator('#preview');
  await expect(card.locator('tr', { hasText: inv(1) }).first()).toContainText(`укладено з Представитель ${uniq}`);
  await expect(card.locator('tr', { hasText: inv(2) }).first()).not.toContainText('укладено з');
});

test('«Договор заключен с» подставляется из последнего договора получателя, вписанное вручную не затирается', async ({ page, uniq }) => {
  const who = 'Подопечный ' + uniq, other = 'Подопечный другой ' + uniq;
  seed({ customers: [{ name: who, type: 1 }, { name: other, type: 1 }],
         contracts: [{ number: 'A' + uniq, customer: who, from: '2026-09-01', to: '2099-12-31', holder: 'Представитель Прежний' },
                     { number: 'B' + uniq, customer: who, from: '2026-10-01', to: '2099-12-31', holder: 'Представитель Последний' },
                     { number: 'C' + uniq, customer: who, from: '2026-10-02', to: '2099-12-31' }] });   // договор без поля не затирает прежнее значение
  await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
  const holder = page.locator('#iform #holder');
  await pickAuto(page, '#iform #customer', who, who);
  await expect(holder, 'из последнего договора с заполненным полем').toHaveValue('Представитель Последний');

  await pickAuto(page, '#iform #customer', other, other);
  await expect(holder, 'подставленное снимается при смене получателя без представителя').toHaveValue('');

  await holder.fill('Представитель Вписанный');
  await pickAuto(page, '#iform #customer', who, who);
  await expect(holder, 'вписанное вручную не перезаписывается').toHaveValue('Представитель Вписанный');
});

test('печать возврата в профиле: договор выдачи и «Договор заключен с»', async ({ page, browser, uniq }) => {
  const code = 'CR-' + uniq, who = 'Подопечный ' + uniq, parent = 'Представитель ' + uniq;
  const { docs } = seed({
    items: [{ name: 'Позиция ' + code, code }],
    customers: [{ name: who, type: 1 }],
    contracts: [{ number: 'A' + uniq, customer: who, from: '2026-10-01', to: '2099-12-31', holder: parent }],
    docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 5, price: 0 }] },
           { type: 'GoodsIssue', date: '2026-10-02', customer: who, contract: 'A' + uniq, rows: [{ code, qty: 2, price: 0 }] },
           { type: 'GoodsIssue', date: '2026-10-02', customer: who, rows: [{ code, qty: 1, price: 0 }] }],
  });
  const ret = async issueId => {
    await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + issueId);
    await page.locator('#document_date').fill('2026-10-03');
    await page.locator('#docform #notes').fill('Возврат ' + uniq);
    await clickWait(page, '#execdoc');
    expect(await page.locator('#docform #execdoc:visible').count(), (await messages(page)).join(' ')).toBe(0);
    return value(`select max(document_id) from documents where parent_id = ${issueId}`);
  };
  const withContract = await ret(docs[1].id), without = await ret(docs[2].id);

  webPhp('cli/charity-profile.php');
  const p = await freshPage(browser);
  let text = await printForm(p, withContract);
  expect(text).toContain('Повернув ' + who);
  expect(text).toContain(`Договір A${uniq} від 01.10.2026`);
  expect(text).toContain('Договір укладено з ' + parent);
  text = await printForm(p, without);
  expect(text, 'возврат выдачи без договора — без строк договора').not.toMatch(/Договір/);
  await p.context().close();
});
