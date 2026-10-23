// Поиск по словам в любом порядке (TODO 2.10): «Зонд 14» находит «Зонд гастростомический 14 Fr» — каждое слово должно
// встретиться (в названии, коде, номере…), порядок и промежуточные слова не важны. Раньше искалась подстрока целиком:
// находили только «Зонд» и «14 Fr». Подсказки позиции (формы выдачи и документы), единицы оборудования, получателя;
// фильтр отчета «Где сейчас».
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');

// варианты подсказки после ввода текста (пауза подсказки 250 мс + ответ)
async function options(page, sel, text) {
  const inp = page.locator(sel);
  await inp.fill('');
  await inp.pressSequentially(text, { delay: 20 });
  await page.waitForTimeout(1500);
  return (await page.locator('li a.dropdown-item:visible').allInnerTexts()).map(s => s.trim());
}

test('позиция: слова в любом порядке — выдача расходников, приход, расходная накладная', async ({ page, uniq }) => {
  const name = `Зонд гастростомический 14 Fr ${uniq}`, other = `Зонд назогастральный 16 Fr ${uniq}`;
  const code = 'ZW-' + uniq, code2 = 'ZW2-' + uniq;
  seed({ items: [{ name, code }, { name: other, code: code2 }],
         docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 5, price: 0 }, { code: code2, qty: 5, price: 0 }] }] });

  await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
  let got = await options(page, '#cform #item', `Зонд 14 ${uniq}`);
  expect(got, 'выдача расходников').toHaveLength(1);
  expect(got[0]).toContain(name);
  got = await options(page, '#cform #item', `${uniq} fr зонд`);
  expect(got, 'порядок и регистр не важны').toHaveLength(2);
  expect(await options(page, '#cform #item', `Зонд 15 ${uniq}`), 'каждое слово должно встретиться').toHaveLength(0);
  got = await options(page, '#cform #item', `${code} Зонд`);
  expect(got, 'слово из кода').toHaveLength(1);

  for (const [doc, field] of [['GoodsReceipt', '#edititem'], ['GoodsIssue', '#edittovar']]) {
    await page.goto('/index.php?p=App/Pages/Doc/' + doc);
    await clickWait(page, '#addrow');
    got = await options(page, field, `Зонд 14 ${uniq}`);
    expect(got, doc).toHaveLength(1);
    expect(got[0]).toContain(name);
    got = await options(page, field, code);
    expect(got[0], `${doc}: код целиком — как раньше`).toContain(name);
  }
});

test('единица оборудования и получатель: слова в любом порядке; фильтр «Где сейчас»', async ({ page, uniq }) => {
  const name = `Кровать функциональная электрическая ${uniq}`, code = 'KW-' + uniq, inv = 'INV' + uniq;
  const who = `Семья Тестовая ${uniq}`;
  seed({ items: [{ name, code, serial: true }], customers: [{ name: who, type: 1 }],
         docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 1, price: 0, sn: inv }] }] });

  await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
  let got = await options(page, '#iform #unit', `кровать ${inv}`);
  expect(got, 'название и инвентарный номер').toHaveLength(1);
  expect(got[0]).toContain(name);
  got = await options(page, '#iform #unit', `электрическая ${uniq} кровать`);
  expect(got).toHaveLength(1);
  expect(await options(page, '#iform #unit', `кровать механическая ${uniq}`)).toHaveLength(0);

  got = await options(page, '#iform #customer', `${uniq} семья`);
  expect(got, 'получатель').toHaveLength(1);
  expect(got[0]).toContain(who);
  await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
  got = await options(page, '#cform #customer', `тестовая ${uniq}`);
  expect(got, 'получатель в выдаче расходников').toHaveLength(1);
  expect(await options(page, '#cform #customer', `Семья Другая ${uniq}`)).toHaveLength(0);

  await page.goto('/index.php?p=App/Pages/Report/EquipmentWhere');
  await page.locator('#filter #searchtext').fill(`${inv} кровать`);
  await clickWait(page, '#filter #onreport');
  await expect(page.locator('#preview')).toContainText(inv);
});
