// Поиск позиции по штрихкоду в документах (Item::findBarCode): выключенная позиция не находится, отбор по категории
// в инвентаризации работает. Поле штрихкода показывается при включенной настройке «Сканер» (Настройки → Бизнес).
const { test, expect } = require('../fixtures');
const { createItem, createDoc, clickWait, messages, bodyText } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

async function setScanner(page, on) {
  await page.goto('/index.php?p=App/Pages/Options');
  await page.locator('#business #usescanner').setChecked(on);
  await clickWait(page, '#business button[type=submit]');
}

async function scan(page, code) {
  await page.locator('#docform #barcode').fill(code);
  await clickWait(page, '#docform #addcode');
}

test('штрихкод: выключенная позиция не находится, в инвентаризации учитывается категория', async ({ page, uniq }) => {
  test.setTimeout(180_000);
  await page.goto('/index.php?p=App/Pages/Options');
  const scanner = await page.locator('#business #usescanner').isChecked();
  await setScanner(page, true);
  try {
    query(`insert into item_cat (cat_name, parent_id) values ('Категория ШК ${uniq}', 0)`);
    const cat = value(`select max(cat_id) from item_cat where cat_name = 'Категория ШК ${uniq}'`);
    const codeA = 'BA-' + uniq, codeD = 'BD-' + uniq, itemA = 'Позиция ' + codeA, itemD = 'Позиция ' + codeD;
    await createItem(page, { name: itemA, code: codeA });
    await createItem(page, { name: itemD, code: codeD });
    query(`update items set bar_code = '20${uniq}1', cat_id = ${cat} where item_code = '${codeA}'`);
    query(`update items set bar_code = '20${uniq}2', disabled = 1 where item_code = '${codeD}'`);

    // оприходование: штрихкод действующей позиции находится, выключенной — нет
    await page.goto('/index.php?p=App/Pages/Doc/IncomeItem');
    await scan(page, '20' + uniq + '1');
    const wrong = [];
    if (!(await page.locator('#docform').innerText()).includes(itemA)) wrong.push('оприходование: действующая позиция не найдена');
    await scan(page, '20' + uniq + '2');
    if ((await page.locator('#docform').innerText()).includes(itemD)) wrong.push('оприходование: найдена выключенная позиция');
    else if (!(await messages(page)).length) wrong.push('оприходование: нет сообщения «не найдено»');

    // инвентаризация по складу и категории: позиция с остатком находится по штрихкоду
    await createDoc(page, 'IncomeItem', { date: '2026-09-01', rows: [{ code: codeA, qty: 2, price: 0 }] });
    await page.goto('/index.php?p=App/Pages/Doc/Inventory');
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('#docform #store').selectOption('1')]);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('#docform #category').selectOption(String(cat))]);
    await scan(page, '20' + uniq + '1');
    const t = await bodyText(page);
    if (/SQL|syntax/i.test(t)) wrong.push('инвентаризация: ошибка SQL: ' + t.slice(0, 300));
    if (!(await page.locator('#docform').innerText({ timeout: 5000 }).catch(() => '')).includes(itemA)) wrong.push('инвентаризация: позиция категории не найдена');
    expect(wrong).toEqual([]);
  } finally {
    if (!scanner) await setScanner(page, false);
  }
});
