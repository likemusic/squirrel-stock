// Перевод интерфейса по английским ключам: язык из профиля пользователя, тексты из lang/<язык>.json,
// ключи в шаблонах ({{#t}}ключ{{/t}}, в том числе в атрибутах) и в PHP (__()).
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');

async function setLang(page, lang) {
  await page.goto('/index.php?p=App/Pages/UserProfile');
  await page.locator('#profileform #lang').selectOption(lang);
  await clickWait(page, '#profileform button[type=submit], #profileform input[type=submit]');
}

test.afterEach(async ({ page }) => setLang(page, 'uk'));   // язык тестов (global-setup)

for (const [lang, label, search, def] of [
  ['ru', 'Язык интерфейса', 'Поиск...', 'По умолчанию'],
  ['uk', 'Мова інтерфейсу', 'Пошук...', 'За замовчуванням'],
]) {
  test(`интерфейс на языке ${lang}`, async ({ page }) => {
    await setLang(page, lang);
    await page.goto('/index.php?p=App/Pages/UserProfile');
    await expect(page.locator('label[for=lang]')).toHaveText(label);                 // шаблон
    await expect(page.locator('#profileform #lang option[value=""]')).toHaveText(def); // PHP
    await page.goto('/index.php?p=App/Pages/Register/DocList');
    await expect(page.locator('#filter #searchnumber')).toHaveAttribute('placeholder', search); // атрибут
  });
}
