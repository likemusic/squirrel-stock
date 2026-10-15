// Русский интерфейс полный: на страницах меню нет украинского текста — ни из шаблонов и PHP (lang/ru.json),
// ни из значений по умолчанию в базе (cli/localize-db.php ru). Названия языков в списке языков — на самом языке.
const { test, expect } = require('@playwright/test');
const { blockExternal, login, clickWait } = require('../helpers/zippy');
const { webPhp } = require('../helpers/stand');

const UKRAINIAN = /[іїєґІЇЄҐ]/;
const ALLOWED = ['Українська'];

async function setLang(page, lang) {
  await page.goto('/index.php?p=App/Pages/UserProfile');
  await page.locator('#profileform #lang').selectOption(lang);
  await clickWait(page, '#profileform button[type=submit], #profileform input[type=submit]');
}

test.beforeAll(() => webPhp('cli/localize-db.php', 'ru'));
test.afterAll(() => webPhp('cli/localize-db.php', 'ru', '--back'));

test('страницы меню на русском без украинского текста', async ({ browser }) => {
  test.setTimeout(15 * 60_000);
  // новая сессия: настройки (типы цен) кэшируются в сессии и после перевода базы читаются заново только при входе
  const context = await browser.newContext({ storageState: undefined });
  await blockExternal(context);
  const page = await context.newPage();
  page.on('dialog', d => d.dismiss());
  await login(page);
  await setLang(page, 'ru');
  try {
    await page.goto('/');
    const links = [...new Set(await page.locator('a[href*="index.php?p=App/Pages/"]').evaluateAll(as => as.map(a => a.getAttribute('href'))))]
      .filter(h => !/Update|Logout|UserLogin/i.test(h));
    expect(links.length).toBeGreaterThan(30);
    const found = [];
    for (const href of links) {
      await page.goto(href);
      const text = await page.locator('body').innerText().catch(() => '');
      for (const line of text.split('\n').map(s => s.trim())) {
        if (UKRAINIAN.test(line) && !ALLOWED.includes(line)) found.push(`${href}: ${line.slice(0, 100)}`);
      }
    }
    expect(found).toEqual([]);
  } finally {
    await setLang(page, '');
    await context.close();
  }
});
