// Работа с телефона (ширина 375 и 414 px, касания): меню, выдача расходной накладной и выдача оборудования по договору,
// отчеты форка с широкими таблицами. ZIPPY_GAPS п. 35. Скриншоты — в test-results (не в git).
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, pickAuto, messages, runReport } = require('../helpers/zippy');
const { value, esc, stockQty } = require('../helpers/db');

// Журналы из меню профиля: строка документа (или позиции) и все ссылки и кнопки в ней. GIList и GRList по умолчанию
// показывают только открытые документы — фильтр «Все».
const JOURNALS = { DocList: null, GIList: '3', GRList: '2', ItemList: null };
async function openJournal(page, name) {
  await page.goto('/index.php?p=App/Pages/Register/' + name);
  if (JOURNALS[name]) {
    await page.locator('#filter #status').selectOption(JOURNALS[name]);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter').evaluate(f => f.requestSubmit())]);
  }
  const row = page.locator('tr:has([id^=number_]), tr:has([id^=itemname_])').first();
  await row.waitFor();
  return row;
}

for (const [width, height] of [[375, 667], [414, 896]]) {
  test.describe(`телефон ${width} px`, () => {
    test.use({ viewport: { width, height }, isMobile: true, hasTouch: true });

    // касание по элементу, как пальцем; после него — ответ сервера
    const tap = async (page, sel) => {
      await page.locator(sel).first().scrollIntoViewIfNeeded();
      await Promise.all([page.waitForLoadState('networkidle'), page.locator(sel).first().tap()]);
      await page.waitForTimeout(400);
    };
    const shot = (page, testInfo, name) => page.screenshot({ path: testInfo.outputPath(name + '.png'), fullPage: true });

    test('меню открывается и закрывается', async ({ page }, testInfo) => {
      await page.goto('/');
      await expect(page.locator('#sidebar')).toHaveClass(/collapsed/);
      expect(await page.locator('#sidebar').evaluate(e => getComputedStyle(e).boxShadow), 'у свернутого меню нет тени на краю экрана').toBe('none');
      await page.locator('#barbtn').tap();
      await expect(page.locator('#sidebar')).not.toHaveClass(/collapsed/);
      await expect.poll(async () => (await page.locator('#sidebar').boundingBox()).x, 'меню выехало на экран').toBeGreaterThanOrEqual(0);
      expect((await page.locator('.main').boundingBox()).width, 'меню накрывает страницу, а не сжимает ее').toBeGreaterThan(width - 30);
      await page.locator('a[data-bs-target="#mnrefs"]').tap();
      const link = page.locator('#mnrefs a[href*="Reference/EquipmentUnits"]').first();
      await expect(link).toBeVisible();
      await shot(page, testInfo, 'menu');
      await page.locator('#barbtn').tap();
      await expect(page.locator('#sidebar')).toHaveClass(/collapsed/);
      await page.locator('#barbtn').tap();
      await Promise.all([page.waitForURL(/EquipmentUnits/), link.tap()]);   // переход по пункту меню касанием
    });

    test('кнопки документов целиком на экране', async ({ page }, testInfo) => {
      const off = [];
      for (const doc of ['GoodsIssue', 'IncomeItem', 'OutcomeItem', 'GoodsReceipt', 'MoveItem']) {
        await page.goto('/index.php?p=App/Pages/Doc/' + doc);
        for (const b of await page.locator('#docform input[type=submit]:visible, #docform input[type=button]:visible').all()) {
          const box = await b.boundingBox();
          if (box.x < 0 || box.x + box.width > width + 0.5) off.push(`${doc} ${await b.getAttribute('id')}: ${Math.round(box.x)}..${Math.round(box.x + box.width)}`);
        }
      }
      expect(off, 'кнопки за краем экрана').toEqual([]);
    });

    test('журналы: действия со строкой видны без прокрутки', async ({ page }, testInfo) => {
      const off = [];
      for (const name of Object.keys(JOURNALS)) {
        const row = await openJournal(page, name);
        await page.screenshot({ path: testInfo.outputPath('journal-' + name + '.png'), fullPage: true });
        for (const el of await row.locator('a:visible, button:visible').all()) {
          const box = await el.boundingBox();
          if (box && box.width > 0 && box.x + box.width > width + 0.5) off.push(`${name} ${await el.getAttribute('id') || 'button'}: ${Math.round(box.x + box.width)}`);
        }
      }
      expect(off, 'за правым краем экрана').toEqual([]);
    });

    test('выдача расходной накладной с телефона', async ({ page, uniq }, testInfo) => {
      const code = 'MB-' + uniq, who = 'Получатель ' + uniq;
      await createItem(page, { name: 'Позиция ' + code, code });
      await createCustomer(page, { name: who, type: 1 });
      await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code, qty: 5, price: 0 }] });

      await page.goto('/index.php?p=App/Pages/Doc/GoodsIssue');
      const number = await page.locator('#document_number').inputValue();
      await pickAuto(page, '#docform #customer', who, who);
      await tap(page, '#addrow');
      await page.locator('#edittovar:visible').waitFor({ timeout: 15000 });
      await pickAuto(page, '#edittovar', code, '', code);
      await page.locator('#editquantity').fill('2');
      await page.locator('#editprice').fill('0');
      await tap(page, '#submitrow');
      // после сохранения строки форма перезагружается и может сама открыть следующую строку
      await page.locator('#cancelrow:visible, #addrow:visible').first().waitFor({ timeout: 15000 });
      if (await page.locator('#editdetail:visible').count()) await tap(page, '#cancelrow');
      await expect(page.locator('#docform a[id^=delete_]:visible')).toHaveCount(1);
      await shot(page, testInfo, 'goodsissue');
      await tap(page, '#execdoc');
      expect(await page.locator('#docform #execdoc:visible').count(), (await messages(page)).join(' ')).toBe(0);
      expect(value(`select state from documents where document_number = '${esc(number)}' order by document_id desc limit 1`)).toBe('5');
      expect(stockQty(code)).toBe(3);
    });

    test('выдача оборудования по договору с телефона', async ({ page, uniq }, testInfo) => {
      const code = 'MBE-' + uniq, inv = 'INV-M-' + uniq, who = 'Получатель ' + uniq;
      await createItem(page, { name: 'Концентратор ' + uniq, code, serial: true });
      await createDoc(page, 'IncomeItem', { date: '2026-09-01', rows: [{ code, qty: 1, price: 0, sn: inv }] });

      await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
      const number = await page.locator('#iform #contract_number').inputValue();
      await page.locator('#iform #customer').fill(who);
      await page.locator('#iform #phone').fill('0291234567');
      await pickAuto(page, '#iform #unit', inv, inv);
      await tap(page, '#iform #addunit');
      await expect(page.locator('#iform [id^=unitlabel]')).toHaveCount(1);
      await shot(page, testInfo, 'equipmentissue');
      await tap(page, '#iform #issue');
      expect((await messages(page)).join(' ')).toContain(number);
      expect(Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${esc(inv)}'`)), 'единица выдана').toBe(0);
    });

    test('широкий отчет: наименование остается на месте при прокрутке', async ({ page }, testInfo) => {
      await runReport(page, 'Report/ItemTurnover', { from: '2026-01-01', to: '2026-12-31' });
      const preview = page.locator('#preview');
      const cell = preview.locator('table tr').nth(2).locator('td').first();
      const before = (await cell.boundingBox()).x;
      await preview.evaluate(e => { e.scrollLeft = 300; });
      await page.waitForTimeout(200);
      expect(Math.abs((await cell.boundingBox()).x - before), 'первая колонка закреплена').toBeLessThan(2);
      await shot(page, testInfo, 'itemturnover');
    });
  });
}

test('десктоп: меню по-прежнему сдвигает страницу, а не накрывает', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('#sidebar')).toHaveClass(/collapsed/);
  await page.locator('#barbtn').click();
  await expect.poll(async () => (await page.locator('#sidebar').boundingBox()).x).toBeGreaterThanOrEqual(0);
  await expect.poll(async () => Math.round((await page.locator('.main').boundingBox()).x)).toBe(300);
  await page.locator('#barbtn').click();
  await expect.poll(async () => Math.round((await page.locator('.main').boundingBox()).x)).toBe(0);
});

test('десктоп: в журналах все колонки на месте', async ({ page }) => {
  // число колонок до правки для телефона (1440 px): скрываются только на узком экране
  const expected = { DocList: 15, GIList: null, GRList: null, ItemList: 14 };
  for (const name of Object.keys(JOURNALS)) {
    const row = await openJournal(page, name);
    const visible = await row.locator('xpath=ancestor::table[1]').locator('th:visible').count();
    if (expected[name] !== null) expect(visible, name).toBe(expected[name]);
    const all = await row.locator('xpath=ancestor::table[1]').locator('th').count();
    expect(visible, name + ': на десктопе скрытых колонок нет').toBe(all);
  }
});
