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
