// SQL-инъекции на страницах, которые остаются в меню после cli/charity-profile.php (ZIPPY_GAPS п. 21).
// В значение подставляется метка с кавычкой. Если значение попадает в SQL без приведения к числу или qstr(),
// MySQL отвечает ошибкой синтаксиса, а программа пишет ее в www/logs вместе с текстом запроса — метка оказывается в журнале.
const { test, expect } = require('../fixtures');
const { logCount } = require('../helpers/stand');

let seq = 0;
const mark = uniq => `SQSI${uniq}x${++seq}`;
const payload = m => `1 ${m}'"`;

// Вызов метода страницы, как это делает callPM() в браузере: ?q=<страница>::<метод>:<арг1>:<арг2>&ajax=true
async function callPM(page, method, args = [], post = null) {
  return page.evaluate(async ({ method, args, post }) => {
    const url = window._baseurl + '::' + method + (args.length ? ':' + args.map(encodeURIComponent).join(':') : '') + '&ajax=true';
    const r = await fetch(url, post === null ? {} : { method: 'POST', body: post });
    return r.text();
  }, { method, args, post });
}

test.describe('AJAX-методы общей страницы (App\\Pages\\Base), доступны на любой странице', () => {
  test('подсказка товаров: склад и категория (Item::findArrayAC)', async ({ page, uniq }) => {
    await page.goto('/index.php?p=App/Pages/Reference/ItemList');
    const m1 = mark(uniq), m2 = mark(uniq);
    const r1 = await callPM(page, 'vonTextItem', ['a', payload(m1)]);
    const r2 = await callPM(page, 'vonTextItem', ['a', '0', payload(m2)]);
    expect(r1 + r2).not.toContain('mysqli error');
    expect(logCount(m1), 'склад попал в SQL как есть').toBe(0);
    expect(logCount(m2), 'категория попала в SQL как есть').toBe(0);
  });

  test('подсказка контрагентов: тип (Customer::getList)', async ({ page, uniq }) => {
    await page.goto('/index.php?p=App/Pages/Reference/ItemList');
    const m = mark(uniq);
    const r = await callPM(page, 'vonTextCust', ['a', payload(m)]);
    expect(r).not.toContain('mysqli error');
    expect(logCount(m), 'тип контрагента попал в SQL как есть').toBe(0);
  });
});
