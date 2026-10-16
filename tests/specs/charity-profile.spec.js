// Профиль для благотворительной организации (cli/charity-profile.php): в меню нужное для выдачи расходников
// и оборудования, лишнее (продажи, касса, производство, кафе) выключено; --back возвращает меню. ZIPPY_GAPS п. 22.
const { test, expect } = require('../fixtures');
const { blockExternal, login, createItem, createCustomer, createDoc, printForm } = require('../helpers/zippy');
const { webPhp } = require('../helpers/stand');
const { value, esc } = require('../helpers/db');

// новая сессия: меню и настройки (в том числе флаг профиля) кэшируются в сессии
async function freshPage(browser) {
  const context = await browser.newContext({ storageState: undefined });
  await blockExternal(context);
  const page = await context.newPage();
  await login(page);
  return page;
}

test.afterAll(() => webPhp('cli/charity-profile.php', '--back'));

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

test('расходная накладная в профиле: «Видав / Отримувач», адреса один раз', async ({ browser, page, uniq }) => {
  const code = 'CP-' + uniq, recipient = 'Получатель ' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  await createCustomer(page, { name: recipient, type: 1, phone: '029' + String(Date.now()).slice(-7), address: 'г. Вымышленный, ул. Тестовая, 1' });
  await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code, qty: 5, price: 10 }] });
  const { number } = await createDoc(page, 'GoodsIssue', { date: '2026-10-02', customer: recipient, payment: 0, rows: [{ code, qty: 2, price: 0 }] });
  const id = value(`select document_id from documents where document_number = '${esc(number)}' order by document_id desc limit 1`);

  webPhp('cli/charity-profile.php');
  let p = await freshPage(browser);
  let text = await printForm(p, id);
  expect(text).toContain('Отримувач ' + recipient);
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
  await p.context().close();
});
