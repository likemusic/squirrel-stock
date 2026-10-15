// Основные страницы открываются без ошибок PHP и без запросов в интернет.
const { test, expect } = require('../fixtures');

const PAGES = [
  ['Главная', '/'],
  ['Товары', '/index.php?p=App/Pages/Reference/ItemList'],
  ['Контрагенты', '/index.php?p=App/Pages/Reference/CustomerList'],
  ['Журнал документов', '/index.php?p=App/Pages/Register/DocList'],
  ['Приходная накладная', '/index.php?p=App/Pages/Doc/GoodsReceipt'],
  ['Расходная накладная', '/index.php?p=App/Pages/Doc/GoodsIssue'],
  ['Настройки', '/index.php?p=App/Pages/Options'],
];

for (const [name, url] of PAGES) {
  test(`страница открывается: ${name}`, async ({ page }) => {
    // ZIPPY_GAPS п. 25: графики главной берут Google Charts из интернета, без сети — «google is not defined»
    test.fail(name === 'Главная', 'известная ошибка: работа без интернета (п. 25)');
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    const resp = await page.goto(url);
    expect(resp.status()).toBeLessThan(400);
    await expect(page.locator('input[type=password]')).toHaveCount(0);
    const text = await page.locator('body').innerText();
    expect(text).not.toMatch(/Fatal error|Uncaught|Exception|Warning:|Notice:/);
    expect(errors, 'ошибки JavaScript').toEqual([]);
  });
}
