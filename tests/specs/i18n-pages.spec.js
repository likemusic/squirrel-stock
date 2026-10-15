// После перевода текстов в ключи все страницы меню открываются без ошибок, без сырых {{#t}}…{{/t}}
// и без ключей словаря вместо текста (так выглядел бы пропущенный перевод).
const fs = require('fs');
const { test, expect } = require('../fixtures');

const UK = JSON.parse(fs.readFileSync(__dirname + '/../../www/lang/uk.json', 'utf8'));
const KEYS = Object.keys(UK).filter(k => k.includes('.'));

test('страницы меню без сырых ключей перевода', async ({ page }) => {
  test.setTimeout(15 * 60_000);
  await page.goto('/');
  const links = [...new Set(await page.locator('a[href*="index.php?p=App/Pages/"]').evaluateAll(as => as.map(a => a.getAttribute('href'))))]
    .filter(h => !/Update|Logout|UserLogin/i.test(h));
  expect(links.length).toBeGreaterThan(30);
  const problems = [];
  for (const href of links) {
    const resp = await page.goto(href);
    const html = await page.content();
    const text = await page.locator('body').innerText().catch(() => '');
    if (resp && resp.status() >= 500) problems.push(`${href}: HTTP ${resp.status()}`);
    if (/Fatal error|Uncaught/.test(text)) problems.push(`${href}: ошибка PHP`);
    if (/\{\{[#\/]t\}\}|\{\|[#\/]t\|\}/.test(html)) problems.push(`${href}: сырая метка перевода`);
    const key = KEYS.find(k => text.includes(k));
    if (key) problems.push(`${href}: ключ ${key} в тексте`);
  }
  expect(problems).toEqual([]);
});

test('печатная форма документа без сырых ключей перевода', async ({ page }) => {
  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await page.locator('a[id^=show_]').first().click();
  await page.waitForLoadState('networkidle');
  const html = await page.content();
  const text = await page.locator('body').innerText();
  expect(html).not.toMatch(/\{\{[#\/]t\}\}|\{\|[#\/]t\|\}/);
  expect(KEYS.filter(k => text.includes(k))).toEqual([]);
  expect(text).toMatch(/Кількість|Найменування|Сума/);   // шапка таблицы печатной формы на украинском
});
