// Программа работает без интернета: страницы не обращаются к внешним адресам (CDN, шрифты, счётчики),
// графики на главной рисуются локальной библиотекой. ZIPPY_GAPS п. 25.
const { test, expect } = require('../fixtures');

const LOCAL = ['127.0.0.1', 'localhost'];
const PAGES = [
  '/',
  '/index.php?p=App/Pages/Reference/ItemList',
  '/index.php?p=App/Pages/Reference/CustomerList',
  '/index.php?p=App/Pages/Register/DocList',
  '/index.php?p=App/Pages/Doc/GoodsReceipt',
  '/index.php?p=App/Pages/Doc/GoodsIssue',
  '/index.php?p=App/Pages/Report/StoreItems',
  '/index.php?p=App/Pages/Report/Outcome',
  '/index.php?p=App/Pages/Options',
  '/index.php?p=App/Pages/Admin',
];

test('страницы не обращаются к внешним адресам', async ({ page }) => {
  const external = [];
  page.on('request', r => { if (!LOCAL.includes(new URL(r.url()).hostname)) external.push(page.url() + ' -> ' + r.url()); });
  for (const url of PAGES) {
    await page.goto(url);
    await page.waitForLoadState('networkidle');
  }
  expect(external).toEqual([]);
});

test('графики на главной рисуются без интернета', async ({ page }) => {
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.goto('/');
  await page.waitForLoadState('networkidle');
  for (const id of ['paystructure', 'paycompare', 'sellstat']) {
    await expect(page.locator(`#${id} canvas`), id).toHaveCount(1);
  }
  expect(errors).toEqual([]);
});
