// Интервал выдачи (ZIPPY_GAPS п. 37): гастростомическую трубку одному получателю выдают не чаще раза в 90 дней, если не
// повредилась. Правило — на категорию (действует и на подкатегорию), раньше срока — предупреждение, выдать можно.
// Данные вымышленные.
const { test, expect } = require('../fixtures');
const { createDoc, pickAuto, clickWait, messages } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { query, value, esc } = require('../helpers/db');

test('повторная выдача раньше интервала — предупреждение, позже — нет', async ({ page, uniq }) => {
  query(`insert into item_cat (cat_name, parent_id) values ('Гастростомы ${uniq}', 0)`);
  const parent = value(`select max(cat_id) from item_cat where cat_name = 'Гастростомы ${uniq}'`);
  query(`insert into item_cat (cat_name, parent_id) values ('Трубки ${uniq}', ${parent})`);
  const child = value(`select max(cat_id) from item_cat where cat_name = 'Трубки ${uniq}'`);
  const code = 'GT-' + uniq, who = 'Получатель ' + uniq, other = 'Получатель ' + uniq + ' другой';
  seed({
    items: [{ name: 'Трубка ' + code, code, cat_id: child }],
    customers: [{ name: who, type: 1 }, { name: other, type: 1 }],
    docs: [{ type: 'IncomeItem', date: '2026-01-01', rows: [{ code, qty: 10, price: 0 }] }],
  });

  // правило в справочнике: категория «Гастростомы» — не чаще раза в 90 дней
  await page.goto('/');
  await expect(page.locator('a[href*="Reference/IssueIntervals"]').first(), 'пункт меню (db/squirrelstock.sql)').toBeAttached();
  await page.goto('/index.php?p=App/Pages/Reference/IssueIntervals');
  await page.locator('#catform #cat').selectOption(String(parent));
  await page.locator('#catform #catdays').fill('90');
  await clickWait(page, '#catform #catsave');
  await expect(page.locator('#interval-rules tr', { hasText: 'Гастростомы ' + uniq })).toContainText('90');

  // «Выдача расходников»: выдача на дату; возвращает текст предупреждения у строки (или '')
  const issue = async (date, recipient) => {
    await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
    await page.locator('#cform #date').fill(date);
    await pickAuto(page, '#cform #customer', recipient, recipient);
    await pickAuto(page, '#cform #item', code, code);
    await page.locator('#cform #qty').fill('1');
    await clickWait(page, '#cform #additem');
    const warn = (await page.locator('#cissue-rows [id^=rowwarn]:visible').allInnerTexts()).join(' ').trim();
    await clickWait(page, '#cform #issue');
    expect((await messages(page)).join(' '), 'выдано').toMatch(/Видано/);
    return warn;
  };

  expect(await issue('2026-01-10', who), 'первая выдача').toBe('');
  const warn = await issue('2026-02-10', who);   // через месяц
  expect(warn).toContain('10.01.2026');
  expect(warn).toContain('31 дн.');
  expect(Number(value(`select count(*) from documents d join customers c on c.customer_id = d.customer_id where c.customer_name = '${esc(who)}' and d.state = 5`)), 'выдано все равно').toBe(2);
  expect(await issue('2026-02-15', other), 'другой получатель').toBe('');
  expect(await issue('2026-06-10', who), 'через 4 месяца после последней').toBe('');

  // штатная «Расходная накладная»: предупреждение после проведения
  await createDoc(page, 'GoodsIssue', { date: '2026-06-20', customer: who, payment: 0, rows: [{ code, qty: 1, price: 0 }] });
  // текст — в вызове toastr.warning на странице после проведения: всплывающее окно живет 4 с, на медленной машине его можно не застать
  expect(await page.content()).toContain('10.06.2026 (10 дн.');   // не просто дата: в журнале есть документ от 10.06.2026
});
