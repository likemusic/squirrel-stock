// Профиль для благотворительной организации (cli/charity-profile.php): в меню нужное для выдачи расходников
// и оборудования, лишнее (продажи, касса, производство, кафе) выключено; --back возвращает меню. ZIPPY_GAPS п. 22.
const { test, expect } = require('../fixtures');
const { blockExternal, login, clickWait, messages, createDoc, printForm } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { webPhp } = require('../helpers/stand');
const { query, value, esc } = require('../helpers/db');

// новая сессия: меню и настройки (в том числе флаг профиля) кэшируются в сессии
async function freshPage(browser) {
  const context = await browser.newContext({ storageState: undefined });
  await blockExternal(context);
  const page = await context.newPage();
  await login(page);
  return page;
}

// после каждого теста, в том числе упавшего: иначе следующие тесты (и других файлов) идут с включенным профилем
test.afterEach(() => webPhp('cli/charity-profile.php', '--back'));

test('меню профиля для благотворительной организации', async ({ browser }) => {
  webPhp('cli/charity-profile.php');
  const context = await browser.newContext({ storageState: undefined });   // новая сессия: меню и настройки кэшируются
  await blockExternal(context);
  const page = await context.newPage();
  await login(page);
  await page.goto('/');
  const hrefs = await page.locator('a[href*="index.php?p=App/Pages/"]').evaluateAll(as => as.map(a => a.getAttribute('href')));
  for (const keep of ['Doc/GoodsIssue', 'Doc/GoodsReceipt', 'Doc/ReturnIssue', 'Doc/OutcomeItem', 'Doc/MoveItem', 'Report/IssueAct', 'Report/EquipmentWhere', 'Report/BelowMinimum', 'Service/ConsumablesIssue', 'Reference/ContractList', 'Service/Backup']) {
    expect(hrefs.some(h => h.includes(keep)), keep).toBe(true);
  }
  for (const off of ['Doc/POSCheck', 'Doc/Invoice', 'Doc/IncomeMoney', 'Doc/CalcSalary', 'Register/PayList']) {
    expect(hrefs.some(h => h.includes(off + '&') || h.endsWith(off)), off).toBe(false);
  }
  await context.close();
});

test('главная в профиле: без денежных плиток и графиков доходов', async ({ browser }) => {
  webPhp('cli/charity-profile.php');
  let page = await freshPage(browser);
  await page.goto('/index.php');
  await expect(page.locator('a[href$="Register/GIList"]').first()).toBeAttached();   // страница отрисована, а не ошибка
  await expect(page.locator('.info-box')).toHaveCount(0);
  await expect(page.locator('#paystructure, #paycompare, #sellstat')).toHaveCount(0);
  await page.context().close();

  webPhp('cli/charity-profile.php', '--back');
  page = await freshPage(browser);
  await page.goto('/index.php');
  await expect(page.locator('.info-box')).toHaveCount(6);
  await expect(page.locator('#paystructure')).toHaveCount(1);
  await page.context().close();
});

test('журнал выдач в профиле: статус «Все», название «Видачі»', async ({ browser }) => {
  webPhp('cli/charity-profile.php');
  expect(value("select concat(description, '|', menugroup) from metadata where meta_type = 3 and meta_name = 'GIList'")).toBe('Видачі|Видачі');
  let page = await freshPage(browser);
  await page.goto('/index.php?p=App/Pages/Register/GIList');
  await expect(page.locator('#filter #status')).toHaveValue('3');
  await expect(page.locator('h3').first()).toHaveText('Журнал видач');
  await expect(page.locator('a[href$="Register/GIList"]').first()).toHaveText('Видачі');
  await page.context().close();

  webPhp('cli/charity-profile.php', '--back');
  expect(value("select concat(description, '|', menugroup) from metadata where meta_type = 3 and meta_name = 'GIList'")).toBe('Продажі|Продажі');
  page = await freshPage(browser);
  await page.goto('/index.php?p=App/Pages/Register/GIList');
  await expect(page.locator('#filter #status')).toHaveValue('0');
  await expect(page.locator('h3').first()).toHaveText('Журнал продажів');
  await page.context().close();
});

test('расходная накладная в профиле: «Видав / Отримувач», адреса один раз', async ({ browser, uniq }) => {
  const code = 'CP-' + uniq, recipient = 'Получатель ' + uniq;
  const { docs } = seed({
    items: [{ name: 'Позиция ' + code, code }],
    customers: [{ name: recipient, type: 1, phone: '029' + String(Date.now()).slice(-7), address: 'г. Вымышленный, ул. Тестовая, 1' }],
    docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 5, price: 10 }] },
           { type: 'GoodsIssue', date: '2026-10-02', customer: recipient, rows: [{ code, qty: 2, price: 0 }] }],
  });
  const { id, number } = docs[1];

  webPhp('cli/charity-profile.php');
  let p = await freshPage(browser);
  let text = await printForm(p, id);
  expect(text).toContain('Отримувач ' + recipient);
  expect(text).toContain(`Видача № ${number} від `);
  expect(text).not.toContain('Видаткова накладна');
  expect(text).toContain('Видав ___');
  expect(text).toContain('Позиция ' + code);
  expect(text).not.toMatch(/Продавець|Покупець|Списано з/);
  expect(text.match(/Адреса/g) || []).toHaveLength(1);
  await p.context().close();

  webPhp('cli/charity-profile.php', '--back');   // без профиля — форма ядра
  p = await freshPage(browser);
  text = await printForm(p, id);
  expect(text).toMatch(/Покупець/);
  expect(text).toMatch(/Продавець/);
  expect(text).toContain(`Видаткова накладна № ${number}`);
  await p.context().close();
});

// выдача и возврат вымышленному получателю: id выдачи и возврата
async function issueAndReturn(page, uniq) {
  const code = 'CR-' + uniq, recipient = 'Получатель ' + uniq;
  const { docs } = seed({
    items: [{ name: 'Позиция ' + code, code }],
    customers: [{ name: recipient, type: 1, phone: '029' + String(Date.now()).slice(-7) }],
    docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 5, price: 10 }] },
           { type: 'GoodsIssue', date: '2026-10-02', customer: recipient, rows: [{ code, qty: 2, price: 0 }] }],
  });
  const { id: issueId, number } = docs[1];
  await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + issueId);
  await page.locator('#document_date').fill('2026-10-03');
  await page.locator('#docform #notes').fill('Возврат ' + uniq);   // вместо «Підстава …»: i18n-ru ищет украинский текст в журналах
  await clickWait(page, '#execdoc');
  expect(await page.locator('#docform #execdoc:visible').count(), (await messages(page)).join(' ')).toBe(0);
  const returnId = value(`select max(d.document_id) from documents d join metadata m on m.meta_id = d.meta_id where m.meta_name = 'ReturnIssue' and d.parent_id = ${issueId}`);
  return { code, recipient, number, issueId, returnId };
}

test('возврат в профиле: «Повернув / Прийняв», без цен', async ({ browser, page, uniq }) => {
  const { code, recipient, returnId } = await issueAndReturn(page, uniq);
  expect(returnId).toBeTruthy();

  webPhp('cli/charity-profile.php');
  let p = await freshPage(browser);
  let text = await printForm(p, returnId);
  expect(text).toContain('Повернув ' + recipient);
  expect(text).toContain('Повернув ___');
  expect(text).toContain('Прийняв ___');
  expect(text).toContain('Позиция ' + code);
  expect(text).not.toMatch(/Покупець|Ціна|Сума|Оплачено|До сплати/);
  await p.context().close();

  webPhp('cli/charity-profile.php', '--back');   // без профиля — форма ядра
  p = await freshPage(browser);
  text = await printForm(p, returnId);
  expect(text).toMatch(/Покупець/);
  expect(text).toMatch(/Оплачено/);
  await p.context().close();
});

test('журнал выдач в профиле: без доставки, ТТН и гарантийного талона, «Повернення» есть', async ({ browser, page, uniq }) => {
  const { number } = await issueAndReturn(page, uniq);
  const open = async p => {
    await p.goto('/index.php?p=App/Pages/Register/GIList');
    await p.locator('#filter #searchnumber').fill(number);   // поиск по номеру — без отбора по статусу
    await clickWait(p, '#filter input[type=submit]');
    await clickWait(p, `[id^=number_]:text-is("${number}")`);
    await expect(p.locator('#statusform #bret')).toBeVisible();
  };

  webPhp('cli/charity-profile.php');
  let p = await freshPage(browser);
  await open(p);
  for (const b of ['bsend', 'bdevivered', 'bttn', 'bgar', 'bnp', 'bdecl', 'ship_number']) {
    await expect(p.locator('#statusform #' + b), b).toBeHidden();
  }
  await expect(p.locator('a[href$="Doc/Invoice"]:visible, a[href$="Doc/TTN"]:visible')).toHaveCount(0);   // «Створити РФ / ТТН»
  await expect(p.locator('#filter #salesource')).toBeHidden();   // «Джерело продажів»
  await expect(p.locator('a.btn-primary[href$="Service/ConsumablesIssue"]')).toBeVisible();   // вместо «Створити ВН» — формы выдачи (profile-polish.spec.js)
  await p.context().close();

  webPhp('cli/charity-profile.php', '--back');
  p = await freshPage(browser);
  await open(p);
  for (const b of ['bsend', 'bttn', 'bgar']) {
    await expect(p.locator('#statusform #' + b), b).toBeVisible();
  }
  await expect(p.locator('#filter #salesource')).toBeVisible();
  await p.context().close();
});

test('названия в профиле: «Видача», «Надходження», «Видача (накладна)»; переименованное вручную не меняется', async ({ browser }) => {
  const names = () => value(`select group_concat(concat(meta_name, '=', description, '/', menugroup) order by meta_name separator ';') from metadata
                             where meta_type = 1 and meta_name in ('GoodsIssue', 'ReturnIssue', 'GoodsReceipt', 'RetCustIssue')`);
  const before = 'GoodsIssue=Видаткова накладна/Продажі;GoodsReceipt=Прибуткова накладна/Закупівлі;RetCustIssue=Повернення постачальнику/Закупівлі;ReturnIssue=Повернення від покупця/Продажі';
  // отчеты: группа «Закупівлі» и отчет «Закупівлі» в ней
  const reports = () => value(`select group_concat(concat(meta_name, '=', description, '/', menugroup) order by meta_name separator ';') from metadata
                               where meta_type = 2 and meta_name in ('Income', 'CustOrder')`);
  const reportsBefore = 'CustOrder=ТМЦ в дорозі/Закупівлі;Income=Закупівлі/Закупівлі';
  expect(names()).toBe(before);
  expect(reports()).toBe(reportsBefore);

  webPhp('cli/charity-profile.php');
  expect(names()).toBe('GoodsIssue=Видача (накладна)/Видача;GoodsReceipt=Прибуткова накладна/Надходження;RetCustIssue=Повернення постачальнику/Надходження;ReturnIssue=Повернення від отримувача/Видача');
  expect(reports()).toBe('CustOrder=ТМЦ в дорозі/Надходження;Income=Надходження/Надходження');
  const p = await freshPage(browser);
  await p.goto('/');
  await expect(p.locator('a[href$="Doc/GoodsIssue"]').first()).toHaveText('Видача (накладна)');
  await expect(p.locator('a[href$="Doc/ReturnIssue"]').first()).toHaveText('Повернення від отримувача');
  await expect(p.locator('a[href$="Report/Income"]').first()).toHaveText('Надходження');
  await p.context().close();
  webPhp('cli/charity-profile.php', '--back');
  expect(names()).toBe(before);
  expect(reports()).toBe(reportsBefore);

  // название, измененное вручную, профиль и --back оставляют как есть
  query(`update metadata set description = 'Повернення (своє)' where meta_type = 1 and meta_name = 'ReturnIssue'`);
  try {
    webPhp('cli/charity-profile.php');
    expect(value(`select description from metadata where meta_type = 1 and meta_name = 'ReturnIssue'`)).toBe('Повернення (своє)');
    webPhp('cli/charity-profile.php', '--back');
    expect(value(`select description from metadata where meta_type = 1 and meta_name = 'ReturnIssue'`)).toBe('Повернення (своє)');
  } finally {
    query(`update metadata set description = 'Повернення від покупця' where meta_type = 1 and meta_name = 'ReturnIssue'`);
  }
});

// остаток позиции на складах
const stockOf = code => Number(value(`select coalesce(sum(s.qty), 0) from store_stock s join items i on i.item_id = s.item_id where i.item_code = '${esc(code)}'`));
// сумма, оплата, касса и статус документа; число платежей по нему
const moneyOf = id => value(`select concat(amount, '|', payamount, '|', payed, '|', state, '|', (select count(*) from paylist p where p.document_id = d.document_id))
                             from documents d where document_id = ${id}`);

test('формы выдачи и возврата в профиле: «Отримувач», без каси, цін і оплати; ціна 0, залишки сходяться', async ({ browser, uniq }) => {
  const code = 'CF-' + uniq, recipient = 'Получатель ' + uniq;
  seed({ items: [{ name: 'Позиция ' + code, code }] });
  query(`update items set detail = replace(detail, '<price1></price1>', '<price1>15</price1>') where item_code = '${esc(code)}'`);   // у позиции есть цена продажи
  seed({ customers: [{ name: recipient, type: 1, phone: '029' + String(Date.now()).slice(-7) }],
         docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 5, price: 10 }] }] });

  webPhp('cli/charity-profile.php');
  const p = await freshPage(browser);
  await p.goto('/index.php?p=App/Pages/Doc/GoodsIssue');
  await expect(p.locator('#docform h3')).toHaveText('Видача (накладна)');
  await expect(p.locator('#docform label[for=customer]')).toHaveText('Отримувач');
  for (const id of ['payment', 'pricetype', 'order', 'salesource', 'payamount', 'payed', 'total']) {
    await expect(p.locator('#docform #' + id), id).toBeHidden();
  }
  await expect(p.locator('#docform th', { hasText: 'Ціна' })).toBeHidden();
  await expect(p.locator('#docform th', { hasText: 'Сума' })).toBeHidden();
  await clickWait(p, '#addrow');
  await expect(p.locator('#editdetail #editprice')).toBeHidden();
  const { number } = await createDoc(p, 'GoodsIssue', { date: '2026-10-02', customer: recipient, rows: [{ code, qty: 2 }] });
  const issueId = value(`select document_id from documents where document_number = '${esc(number)}' order by document_id desc limit 1`);
  expect(moneyOf(issueId), 'выдача бесплатная: сумма 0, без оплаты, проведена').toBe('0.00|0.00|0.00|5|0');
  expect(stockOf(code)).toBe(3);

  await p.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + issueId);
  await expect(p.locator('#docform h3')).toHaveText('Повернення від отримувача');
  await expect(p.locator('#docform label[for=customer]')).toHaveText('Отримувач');
  for (const id of ['payment', 'payamount', 'payed', 'total']) {
    await expect(p.locator('#docform #' + id), id).toBeHidden();
  }
  await expect(p.locator('#docform th', { hasText: 'Ціна' })).toBeHidden();
  await expect(p.locator('#docform #addrow')).toHaveText('Додати позицію');
  await p.locator('#document_date').fill('2026-10-03');
  await p.locator('#docform #notes').fill('Возврат ' + uniq);
  await clickWait(p, '#execdoc');
  expect(await p.locator('#docform #execdoc:visible').count(), (await messages(p)).join(' ')).toBe(0);
  const returnId = value(`select max(d.document_id) from documents d join metadata m on m.meta_id = d.meta_id where m.meta_name = 'ReturnIssue' and d.parent_id = ${issueId}`);
  expect(moneyOf(returnId), 'возврат: без оплаты, проведен').toBe('0.00|0.00|0.00|5|0');
  expect(stockOf(code)).toBe(5);
  await p.context().close();

  webPhp('cli/charity-profile.php', '--back');   // без профиля — форма ядра
  const q = await freshPage(browser);
  await q.goto('/index.php?p=App/Pages/Doc/GoodsIssue');
  await expect(q.locator('#docform label[for=customer]')).toHaveText('Покупець');
  await expect(q.locator('#docform #payment')).toBeVisible();
  await expect(q.locator('#docform th', { hasText: 'Ціна' })).toBeVisible();
  await q.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=' + returnId);
  await expect(q.locator('#docform label[for=customer]')).toHaveText('Покупець');
  await expect(q.locator('#docform #payment')).toBeVisible();
  await q.context().close();
});

test('отчет «Надходження» в профиле: заголовок страницы и разделов без «закупок»', async ({ browser }) => {
  const check = async (title, section) => {
    const p = await freshPage(browser);
    await p.goto('/index.php?p=App/Pages/Report/Income');
    await expect(p.locator('h3').first()).toHaveText(title);
    await expect(p).toHaveTitle(new RegExp(title));
    await clickWait(p, '#onreport');
    await expect(p.locator('#preview')).toContainText(section);
    await p.context().close();
  };
  webPhp('cli/charity-profile.php');
  await check('Звіт по надходженню', 'Надходження по товарам');
  webPhp('cli/charity-profile.php', '--back');   // без профиля — названия ядра
  await check('Звіт по закупках', 'Закупівлі по товарам');
});

// ответ хосписа 2.1: признаков получателя три — подопечный, по запросу, снят с опеки (тег контрагента, выбирается списком);
// ответ 2.2: данных хватает (ФИО, адрес, телефон) — торговые поля формы контрагента в профиле скрыты
test('справочник контрагентов в профиле: признак получателя списком, без торговых полей', async ({ browser, uniq }) => {
  const name = 'Подопечный ' + uniq + ' признак';
  const tags = () => query(`select tag_name from taglist t join customers c on c.customer_id = t.item_id where t.tag_type = 1 and c.customer_name = '${esc(name)}' order by tag_name`).map(r => r[0]);
  // теги — на языке тестов (украинском): после теста убираются, иначе i18n-ru найдет их в списках тегов
  try {
    webPhp('cli/charity-profile.php');
    let p = await freshPage(browser);
    await p.goto('/index.php?p=App/Pages/Reference/CustomerList');
    await expect(p.locator('#chleads')).toBeHidden();
    await clickWait(p, '#addnew');
    for (const hidden of ['#editviber', '#editedrpou', '#editpricetype', '#editholding', '#editisholding', '#editnosubs', '#editaddressdel', '#editjurid']) {
      await expect(p.locator(hidden), hidden).toBeHidden();
    }
    await expect(p.locator('#edittype')).toHaveValue('1');
    expect(await p.locator('#edittype option[value="1"]').innerText()).toBe('Отримувач');
    expect(await p.locator('#editsqsmark option').allInnerTexts()).toEqual(['Не вказано', 'підопічний', 'за запитом', 'знятий з опіки']);
    await p.locator('#editcustomername').fill(name);
    await p.locator('#editaddress').fill('г. Вымышленный, ул. Тестовая, 2');
    await p.locator('#editsqsmark').selectOption('підопічний');
    await clickWait(p, '#customerdetail #save');
    expect(tags()).toEqual(['підопічний']);
    // свой тег (не признак) — как если бы его ввели в поле тегов
    query(`insert into taglist (tag_type, item_id, tag_name) select 1, customer_id, 'алергія' from customers where customer_name = '${esc(name)}'`);

    // в списке признак рядом с именем; смена признака заменяет прежний, остальные теги остаются
    await p.locator('#filter #searchkey').fill(name);
    await clickWait(p, '#filter input[type=submit]');
    const row = p.locator('tr', { hasText: name });
    await expect(row.locator('.badge', { hasText: 'підопічний' })).toBeVisible();
    await clickWait(p, `tr:has-text("${name}") >> a >> nth=0`);
    await expect(p.locator('#editsqsmark')).toHaveValue('підопічний');
    expect(await p.locator('#edittags_tags').inputValue()).toBe('алергія');
    await p.locator('#editsqsmark').selectOption('знятий з опіки');
    await clickWait(p, '#customerdetail #save');
    expect(tags()).toEqual(['алергія', 'знятий з опіки']);

    // до списка признаки вписывали тегами, бывает два сразу: «знятий з опіки» важнее «підопічний», сохранение оставляет один
    query(`insert into taglist (tag_type, item_id, tag_name) select 1, customer_id, 'підопічний' from customers where customer_name = '${esc(name)}'`);
    await p.goto('/index.php?p=App/Pages/Reference/CustomerList');
    await p.locator('#filter #searchkey').fill(name);
    await clickWait(p, '#filter input[type=submit]');
    await expect(p.locator('tr', { hasText: name }).locator('.badge')).toHaveText('знятий з опіки');
    await clickWait(p, `tr:has-text("${name}") >> a >> nth=0`);
    await expect(p.locator('#editsqsmark')).toHaveValue('знятий з опіки');
    await clickWait(p, '#customerdetail #save');
    expect(tags()).toEqual(['алергія', 'знятий з опіки']);
    await p.context().close();

    // без профиля — форма ядра: торговые поля на месте, списка признака нет, теги как есть
    webPhp('cli/charity-profile.php', '--back');
    p = await freshPage(browser);
    await p.goto('/index.php?p=App/Pages/Reference/CustomerList');
    await clickWait(p, '#addnew');
    await expect(p.locator('#editedrpou')).toBeVisible();
    await expect(p.locator('#editsqsmark')).toBeHidden();
    await p.context().close();
  } finally {
    query(`delete t from taglist t join customers c on c.customer_id = t.item_id where t.tag_type = 1 and c.customer_name = '${esc(name)}'`);
  }
});

test('журнал документов в профиле: в отборе «Тип» только документы из меню и те, по которым есть документы', async ({ browser, uniq }) => {
  const options = async (p, path) => {
    await p.goto(`/index.php?p=App/Pages/${path}`);
    return p.locator('select[name="doctype"] option').evaluateAll(os => os.map(o => o.textContent.trim()));
  };
  const meta = (name) => value(`select description from metadata where meta_type = 1 and meta_name = '${name}'`);
  // документ выключенного типа, оставшийся в базе (например, из импорта): его тип в отборе остается
  const number = `ТЕСТ-${uniq}`;
  const issue = `ТЕСТ-В-${uniq}`;
  const based = async (p) => {
    await p.goto('/index.php?p=App/Pages/Register/DocList');
    await p.locator('input[name="searchnumber"]').fill(issue);
    await clickWait(p, '#filter input[type="submit"]');
    return p.locator(`tr:has-text("${issue}") .dropdown-menu a`).evaluateAll(as => as.map(a => a.getAttribute('href').replace(/.*Pages\/Doc\/|&.*/g, '')));
  };
  query(`insert into documents (document_number, document_date, meta_id, state, content) select '${number}', curdate(), meta_id, 1, '<doc></doc>' from metadata where meta_type = 1 and meta_name = 'OutcomeMoney'`);
  query(`insert into documents (document_number, document_date, meta_id, state, content) select '${issue}', curdate(), meta_id, 5, '<doc></doc>' from metadata where meta_type = 1 and meta_name = 'GoodsIssue'`);
  try {
    webPhp('cli/charity-profile.php');
    let p = await freshPage(browser);
    for (const path of ['Register/DocList', 'SystemLog']) {
      const types = await options(p, path);
      for (const keep of ['GoodsIssue', 'ReturnIssue', 'OutcomeItem', 'OutcomeMoney']) expect(types, `${path}: ${keep}`).toContain(meta(keep));
      for (const off of ['IncomeMoney', 'CalcSalary', 'OutSalary', 'POSCheck', 'OrderFood']) expect(types, `${path}: ${off}`).not.toContain(meta(off));
    }
    // «Создать на основании»: только типы из меню
    const docs = await based(p);
    expect(docs).toContain('ReturnIssue');
    for (const off of ['TTN', 'Warranty', 'Invoice']) expect(docs, off).not.toContain(off);
    await p.context().close();

    webPhp('cli/charity-profile.php', '--back');
    p = await freshPage(browser);
    const types = await options(p, 'Register/DocList');
    for (const all of ['IncomeMoney', 'CalcSalary', 'POSCheck']) expect(types, all).toContain(meta(all));
    expect(await based(p)).toEqual(expect.arrayContaining(['ReturnIssue', 'TTN', 'Warranty', 'Invoice']));
    await p.context().close();
  } finally {
    query(`delete from documents where document_number in ('${esc(number)}', '${esc(issue)}')`);
  }
});
