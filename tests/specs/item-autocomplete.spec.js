// Подсказка товара в документе: при точном совпадении кода этот товар первый в списке,
// а не товар, у которого код лишь начинается так же («1C-03» → 1C-030). ZIPPY_GAPS п. 3.
const { test, expect } = require('../fixtures');
const { createItem, createDoc, clickWait } = require('../helpers/zippy');

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
