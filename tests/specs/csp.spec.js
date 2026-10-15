// Content-Security-Policy (ZIPPY_GAPS п. 36): принудительная политика из корневого .htaccess — загружать и отправлять
// можно только на свой адрес, встраивать — только в свои фреймы. Встроенные скрипты и eval разрешены: их требует
// фреймворк (onclick в ссылках списков, ответы AJAX выполняются через eval). Страницы меню профиля благотворительной
// организации (KEEP в cli/charity-profile.php) и страницы SquirrelStock открываются без нарушений политики.
const fs = require('fs');
const path = require('path');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');

const KEEP = [...fs.readFileSync(path.join(__dirname, '../../www/cli/charity-profile.php'), 'utf8')
  .match(/const KEEP = \[([\s\S]*?)\];/)[1].matchAll(/'([A-Za-z0-9]+)'/g)].map(m => m[1]);

// нарушения политики: событие securitypolicyviolation в странице и сообщения консоли про Content Security Policy
async function watch(page) {
  const found = [];
  await page.addInitScript(() => {
    document.addEventListener('securitypolicyviolation', e => console.error(`CSP-VIOLATION ${e.violatedDirective} ${e.blockedURI}`));
  });
  page.on('console', m => {
    if (/CSP-VIOLATION|Content Security Policy/i.test(m.text())) found.push(`${page.url()}: ${m.text().slice(0, 200)}`);
  });
  return found;
}

test('политика безопасности содержимого: заголовок и страницы профиля без нарушений', async ({ page }) => {
  test.setTimeout(10 * 60_000);
  const found = await watch(page);
  const resp = await page.goto('/');
  const csp = resp.headers()['content-security-policy'] || '';
  expect(csp, 'заголовок Content-Security-Policy (www/.htaccess)').toContain("default-src 'self'");
  for (const d of ["connect-src 'self'", "object-src 'none'", "frame-ancestors 'self'", "form-action 'self'", "img-src 'self' data:", "font-src 'self' data:"]) expect(csp).toContain(d);

  const links = [...new Set(await page.locator('a[href*="index.php?p=App/Pages/"]').evaluateAll(as => as.map(a => a.getAttribute('href'))))]
    .filter(h => KEEP.includes(h.split('/').pop().split('&')[0]));
  expect(links.length).toBeGreaterThan(25);
  for (const href of links) await page.goto(href);

  // действия с AJAX и выполнением ответа через eval: строка документа и подсказка, отчет, форма выдачи
  await page.goto('/index.php?p=App/Pages/Doc/GoodsIssue');
  await clickWait(page, '#addrow');
  await page.locator('#edittovar').pressSequentially('шприц', { delay: 30 });
  await page.waitForTimeout(1000);
  await page.goto('/index.php?p=App/Pages/Report/ItemTurnover');
  await clickWait(page, '#filter #onreport');
  await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
  await page.locator('#cform #customer').pressSequentially('По', { delay: 30 });
  await page.waitForTimeout(1000);

  expect(found).toEqual([]);
});

test('магазин (модуль вне профиля) — без политики: платежные виджеты подключаются с чужих адресов', async ({ request }) => {
  const resp = await request.get('/shop', { maxRedirects: 0 });
  expect(resp.headers()['content-security-policy']).toBeUndefined();
});
