// Профиль для благотворительной организации (cli/charity-profile.php): в меню нужное для выдачи расходников
// и оборудования, лишнее (продажи, касса, производство, кафе) выключено; --back возвращает меню. ZIPPY_GAPS п. 22.
const { test, expect } = require('@playwright/test');
const { blockExternal, login } = require('../helpers/zippy');
const { webPhp } = require('../helpers/stand');

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
