// «Выдача расходников» — короткая форма основной ежедневной операции (Service/ConsumablesIssue): получатель, дата,
// позиции с количеством из того, что есть на складе, «Выдать» → проведенная расходная накладная по цене 0.
// На десктопе и на телефоне (375 px, касания). ZIPPY_GAPS п. 35.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, pickAuto, clickWait, messages } = require('../helpers/zippy');
const { value, query, esc, stockQty } = require('../helpers/db');

async function prepare(page, uniq) {
  const a = 'CA-' + uniq, b = 'CB-' + uniq, eq = 'CE-' + uniq;
  await createItem(page, { name: 'Перевязка ' + a, code: a });
  await createItem(page, { name: 'Шприц ' + b, code: b });
  await createItem(page, { name: 'Концентратор ' + eq, code: eq, serial: true });
  await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code: a, qty: 10, price: 0 }, { code: b, qty: 5, price: 0 }, { code: eq, qty: 1, price: 0, sn: 'INV-' + eq }] });
  return { a, b, eq };
}

const lastIssue = who => query(`select d.document_number, d.state, d.amount, date(d.document_date) from documents d join metadata m on m.meta_id = d.meta_id
                               join customers c on c.customer_id = d.customer_id where m.meta_name = 'GoodsIssue' and c.customer_name = '${esc(who)}' order by d.document_id desc`);

test('выдача расходников: новый получатель, несколько позиций, нехватка остатка', async ({ page, uniq }) => {
  const { a, b, eq } = await prepare(page, uniq);
  const who = 'Получатель ' + uniq;

  await page.goto('/');
  await expect(page.locator('a[href*="Service/ConsumablesIssue"]').first(), 'пункт меню (db/squirrelstock.sql)').toBeAttached();
  await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');

  // в подсказке только то, что есть на складе, и без оборудования с инвентарными номерами
  await page.locator('#cform #item').pressSequentially(eq, { delay: 30 });
  await page.waitForTimeout(1500);
  expect(await page.locator('li a.dropdown-item:visible', { hasText: eq }).count(), 'оборудование — другой формой').toBe(0);
  await page.locator('#cform #item').fill('');

  await page.locator('#cform #customer').fill(who);
  await page.locator('#cform #date').fill('2026-10-10');
  const add = async (code, qty) => {
    await pickAuto(page, '#cform #item', code, code);
    await page.locator('#cform #qty').fill(String(qty));
    await clickWait(page, '#cform #additem');
  };
  await add(a, 3);
  await add(a, 2);   // та же позиция — количество складывается
  await add(b, 9);   // на складе 5
  expect((await messages(page)).join(' ')).toMatch(/Не вистачає.*є 5/);
  await add(b, 2);
  const rows = (await page.locator('#cissue-rows tr').allInnerTexts()).map(s => s.replace(/\s+/g, ' ').trim());
  expect(rows).toEqual([`Перевязка ${a} 5 шт`, `Шприц ${b} 2 шт`]);

  await clickWait(page, '#cform #issue');
  const msg = (await messages(page)).join(' ');
  const issued = lastIssue(who);
  expect(issued, msg).toHaveLength(1);
  expect(msg).toContain(issued[0][0]);
  expect(issued[0].slice(1)).toEqual(['5', '0.00', '2026-10-10']);   // проведена, сумма 0, дата из формы
  expect(value(`select detail like '%<type>1</type>%' from customers where customer_name = '${esc(who)}'`), 'новый получатель — покупатель').toBe('1');
  expect(stockQty(a)).toBe(5);
  expect(stockQty(b)).toBe(3);
  await expect(page.locator('#cissue-rows tr')).toHaveCount(0);   // форма готова к следующей выдаче
  await expect(page.locator('#cform #date')).toHaveValue('2026-10-10');

  // существующий получатель; позиция выбрана, но кнопка «Добавить» не нажата — тоже выдается
  const who2 = 'Получатель ' + uniq + ' два';
  await createCustomer(page, { name: who2, type: 1 });
  await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
  await pickAuto(page, '#cform #customer', who2, who2);
  await pickAuto(page, '#cform #item', a, a);
  await page.locator('#cform #qty').fill('1');
  await clickWait(page, '#cform #issue');
  expect(lastIssue(who2), (await messages(page)).join(' ')).toHaveLength(1);
  expect(stockQty(a)).toBe(4);
  expect(Number(value(`select count(*) from customers where customer_name = '${esc(who2)}'`)), 'получатель не задвоен').toBe(1);
});

test.describe('телефон 375 px', () => {
  test.use({ viewport: { width: 375, height: 667 }, isMobile: true, hasTouch: true });

  test('выдача расходников с телефона', async ({ page, uniq }, testInfo) => {
    const { a } = await prepare(page, uniq);
    const who = 'Получатель ' + uniq;
    await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');

    // все поля и кнопки помещаются в ширину экрана
    const off = [];
    for (const el of await page.locator('#cform input:visible, #cform select:visible, #cform textarea:visible').all()) {
      const box = await el.boundingBox();
      if (box.x < 0 || box.x + box.width > 375.5) off.push(await el.getAttribute('id'));
    }
    expect(off, 'за краем экрана').toEqual([]);

    await page.locator('#cform #customer').fill(who);
    await pickAuto(page, '#cform #item', a, a);
    await page.locator('#cform #qty').fill('2');
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('#cform #additem').tap()]);
    await expect(page.locator('#cissue-rows tr')).toHaveCount(1);
    await page.screenshot({ path: testInfo.outputPath('consumables-375.png'), fullPage: true });
    await page.locator('#cform #issue').scrollIntoViewIfNeeded();
    await page.locator('#cform #issue').tap();
    // результат — по базе, без чтения страницы: после «Выдать» она перезагружается (POST → GET), и сообщения на ней
    // можно запросить посреди перехода («Execution context was destroyed» на медленной машине)
    await expect.poll(() => lastIssue(who).length, { timeout: 20000, message: 'накладная проведена' }).toBe(1);
    await page.waitForLoadState('networkidle');
    expect(stockQty(a)).toBe(8);
  });
});
