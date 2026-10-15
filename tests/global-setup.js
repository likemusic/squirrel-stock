// Перед тестами: вход под admin и настройки, как у хосписа (серийные номера изделий без сроков годности,
// без минусовых остатков, цена выдачи 0, копейки). Сессия сохраняется в .auth/admin.json и переиспользуется тестами.
const { chromium } = require('@playwright/test');
const { login, clickWait, blockExternal, USESNUMBER_HOSPICE } = require('./helpers/zippy');

module.exports = async config => {
  const { baseURL } = config.projects[0].use;
  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL });
  await blockExternal(context);
  const page = await context.newPage();
  await login(page);

  await page.goto('/index.php?p=App/Pages/Options');
  await page.locator('#business #usesnumber').selectOption(USESNUMBER_HOSPICE);
  await page.locator('#business #allowminus').setChecked(false);  // запрет минусовых остатков
  await page.locator('#business #allowminusmf').setChecked(true); // закупки без учёта денег в кассе
  await page.locator('#business #defprice').fill('0');            // цена выдачи по умолчанию 0
  await clickWait(page, '#business button[type=submit]');
  await page.goto('/index.php?p=App/Pages/Options');
  await page.locator('#common #amdigits').selectOption('1');      // до копейки
  await clickWait(page, '#common button[type=submit]');

  // тесты проверяют украинские тексты (язык автора): язык администратора задается явно, независимо от SQS_LANG стенда
  await page.goto('/index.php?p=App/Pages/UserProfile');
  await page.locator('#profileform #lang').selectOption('uk');
  await clickWait(page, '#profileform button[type=submit], #profileform input[type=submit]');

  await context.storageState({ path: __dirname + '/.auth/admin.json' });
  await browser.close();
};
