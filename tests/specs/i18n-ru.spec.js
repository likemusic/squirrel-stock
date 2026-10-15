// Русский интерфейс полный: на страницах меню нет украинского текста — ни из шаблонов и PHP (lang/ru.json),
// ни из значений по умолчанию в базе (cli/localize-db.php ru), ни в скриптах и атрибутах страницы.
// Названия языков в списке языков — на самом языке.
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
      // NotifyList показывает сохраненные сообщения: они на языке, действовавшем при их создании (вход — до смены языка)
      .filter(h => !/Update|Logout|UserLogin|NotifyList/i.test(h));
    expect(links.length).toBeGreaterThan(30);
    const found = [];
    for (const href of links) {
      await page.goto(href);
      const text = await page.locator('body').innerText().catch(() => '');
      for (const line of text.split('\n').map(s => s.trim())) {
        if (UKRAINIAN.test(line) && !ALLOWED.includes(line)) found.push(`${href}: ${line.slice(0, 100)}`);
      }
      // невидимое: строки в скриптах (confirm, toastr), подсказки в атрибутах
      const html = (await page.content())
        .replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '')   // комментарии пользователь не видит
        .split(/\n/).map(l => l.replace(/(^|\s)\/\/.*$/, ''))
        .filter(l => UKRAINIAN.test(l) && !ALLOWED.some(a => l.includes(a)));
      for (const l of html) found.push(`${href}: [html] ${l.trim().slice(0, 100)}`);
    }
    expect(found).toEqual([]);
  } finally {
    await setLang(page, 'uk');   // язык тестов (global-setup)
    await context.close();
  }
});

// Календари (FullCalendar) и moment следуют языку интерфейса: в журнале нарядов локаль календаря задает
// компонент фреймворка (ZCL\Calendar), в табеле — шаблон.
test('календари и moment на русском', async ({ browser }) => {
  const context = await browser.newContext({ storageState: undefined });
  await blockExternal(context);
  const page = await context.newPage();
  await login(page);
  await setLang(page, 'ru');
  try {
    const calendarText = async () => {
      await page.locator('.fc-today-button').first().waitFor();
      return {
        today: (await page.locator('.fc-today-button').first().innerText()).trim(),
        month: (await page.locator('.fc-dayGridMonth-button').first().innerText()).trim(),
        title: (await page.locator('.fc-toolbar-title').first().innerText()).trim(),
      };
    };

    await page.goto('/index.php?p=App/Pages/Register/TaskList');
    await clickWait(page, '#tabc');
    let cal = await calendarText();
    expect(cal.today).toBe('Сегодня');
    expect(cal.month).toBe('Месяц');
    expect(cal.title).toMatch(/^[а-яё]+ \d{4}/i);
    expect(cal.title).not.toMatch(UKRAINIAN);

    await page.goto('/index.php?p=App/Pages/TimeSheet');
    await page.evaluate(() => calendar.render());   // сам табель рисует календарь после выбора сотрудника
    cal = await calendarText();
    expect(cal.today).toBe('Сегодня');
    expect(cal.month).toBe('Месяц');
    expect(cal.title).toMatch(/^[а-яё]+ \d{4}/i);
    expect(await page.evaluate(() => moment.locale())).toBe('ru');
  } finally {
    await setLang(page, 'uk');
    await context.close();
  }
});
