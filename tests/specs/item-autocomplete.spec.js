// Подсказка товара в документе: при точном совпадении кода этот товар первый в списке,
// а не товар, у которого код лишь начинается так же («1C-03» → 1C-030). ZIPPY_GAPS п. 3.
const { test, expect } = require('../fixtures');
const { createItem, createDoc, clickWait } = require('../helpers/zippy');
const { value } = require('../helpers/db');

for (const [doc, field] of [['GoodsReceipt', '#edititem'], ['GoodsIssue', '#edittovar']]) {
  test(`точное совпадение кода первым в подсказке: ${doc}`, async ({ page, uniq }) => {
    const base = 'AC' + uniq;
    // товар с более длинным кодом заводится первым: без сортировки он первым и показывается
    await createItem(page, { name: 'Позиция ' + base + '-030', code: base + '-030' });
    await createItem(page, { name: 'Позиция ' + base + '-03', code: base + '-03' });
    // в выдаче подсказка показывает только товары, которые есть на складе
    if (doc === 'GoodsIssue') await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code: base + '-030', qty: 1, price: 0 }, { code: base + '-03', qty: 1, price: 0 }] });
    await page.goto('/index.php?p=App/Pages/Doc/' + doc);
    await clickWait(page, '#addrow');
    await page.locator(field).pressSequentially(base + '-03', { delay: 40 });
    await page.waitForTimeout(2000); // дождаться ответа на полный запрос
    const options = page.locator('li a.dropdown-item:visible');
    await expect(options).toHaveCount(2);
    await expect(options.first()).toHaveText(new RegExp(`, ${base}-03$`));
  });
}

// Гонка: Enter нажат раньше, чем пришел ответ на полный код, — подсказка не должна выбрать вариант из списка по
// недопечатанному запросу («X-03» → X-030 вместо X-039). Медленная сеть воспроизводится задержкой ответа на полный код.
test('Enter до ответа на полный код выбирает товар по полному коду', async ({ page, uniq }) => {
  const base = 'RC' + uniq;
  await createItem(page, { name: 'Позиция ' + base + '-030', code: base + '-030' });
  await createItem(page, { name: 'Позиция ' + base + '-039', code: base + '-039' });
  const id039 = value(`select item_id from items where item_code = '${base}-039'`);
  await page.route(u => /ajax=true/.test(u.href) && u.searchParams.get('text') === base + '-039', async r => {
    await new Promise(res => setTimeout(res, 1500));
    await r.continue();
  });
  await page.goto('/index.php?p=App/Pages/Doc/GoodsReceipt');
  await clickWait(page, '#addrow');
  const inp = page.locator('#edititem');
  await inp.pressSequentially(base + '-03', { delay: 40 });
  await expect(page.locator('li a.dropdown-item:visible')).toHaveCount(2);   // список по «-03»: X-030 первым
  await inp.press('9');
  await inp.press('Enter');   // свежий ответ еще не пришел
  await page.waitForTimeout(2500);
  expect(await page.locator('#edititem_id').inputValue()).toBe(id039);
  await expect(inp).toHaveValue(new RegExp(`${base}-039`));
});
