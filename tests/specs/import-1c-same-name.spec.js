// Одноименные позиции 1С (одно название, разные цены) при повторном импорте ведомости по счету 10 и название из 1С
// (ZIPPY_GAPS п. 29–30): строки сопоставляются позициям по цене с прошлого импорта, неоднозначные — в отчет и не приходуются;
// акт списания печатает название из 1С одной строкой на название; при переименовании позиции из 1С — предупреждение.
// Ведомости вымышленные: tests/fixtures/make-1c-same.php, названия — с меткой прогона.
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait, createCustomer, createDoc, messages } = require('../helpers/zippy');
const { query, value, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';

test('одноименные позиции 1С: повторный импорт по цене, неоднозначные — в отчет; акт — по названию из 1С', async ({ page, uniq }, testInfo) => {
  test.setTimeout(6 * 60_000);
  const band = `Повязка тестовая ${uniq}`;
  const dir = testInfo.outputPath('osv');
  const tmp = `/tmp/i1c-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-1c-same.php'), `${WEB}:${tmp}/make.php`]);
  execFileSync('docker', ['exec', WEB, 'php', `${tmp}/make.php`, tmp, uniq]);
  fs.mkdirSync(dir, { recursive: true });
  for (const f of ['v1', 'v2', 'v3', 'v4', 'v5']) execFileSync('docker', ['cp', `${WEB}:${tmp}/${f}.xls`, path.join(dir, f + '.xls')]);
  execFileSync('docker', ['exec', WEB, 'rm', '-rf', tmp]);

  // позиции с этим названием из 1С и остаток каждой: [[item_id, price1c, qty], ...] по возрастанию прошлой цены
  const bands = () => query(`select c.item_id, c.price1c, coalesce((select sum(qty) from store_stock s where s.item_id = c.item_id), 0)
                              from sqs_item1c c where c.name1c = '${esc(band)}' order by c.price1c`).map(r => [r[0], Number(r[1]), Number(r[2])]);
  const importFile = async f => {
    await page.goto('/index.php?p=App/Pages/Service/Import1C');
    await page.locator('#iform #date').fill('2026-09-01');
    await page.locator('#iform #filename').setInputFiles(path.join(dir, f));
    await clickWait(page, '#iform #import');
    return (await page.locator('body').innerText()).replace(/\s+/g, ' ');
  };

  // первый импорт: две позиции с одним названием
  await importFile('v1.xls');
  let b = bands();
  expect(b.map(r => [r[1], r[2]])).toEqual([[10, 10], [50, 10]]);
  const [cheap, dear] = [b[0][0], b[1][0]];

  // строки в другом порядке, цены сдвинулись — пары по возрастанию цены, новых позиций нет
  await importFile('v2.xls');
  b = bands();
  expect(b.map(r => r[0])).toEqual([cheap, dear]);
  expect(b.map(r => [r[1], r[2]])).toEqual([[12, 20], [48, 20]]);

  // одна строка, цена до копейки как у дорогой на прошлом импорте
  await importFile('v3.xls');
  expect(bands().map(r => r[2])).toEqual([20, 30]);

  // одна строка с ценой, не совпадающей ни с одной, — неоднозначно, не оприходовано
  const text = await importFile('v4.xls');
  expect(text).toMatch(/неоднозначно, не оприбутковано рядків: 1/);
  const amb = await page.locator('#i1c-ambiguous').innerText();
  expect(amb).toContain(band);
  expect(amb).toMatch(/у відомості: 10 шт за 30/);
  expect(bands().map(r => r[2])).toEqual([20, 30]);

  // переименование позиции из 1С в справочнике — предупреждение, сохранение не блокируется
  await page.goto('/index.php?p=App/Pages/Reference/ItemList');
  await page.locator('#searchkey').fill(band);
  await page.locator('#searchkey').press('Enter');
  await page.waitForLoadState('networkidle');
  await clickWait(page, `#itemlist tr:has-text("${band}") a[id^="edit"], tr:has-text("${band}") a[id^="edit"]`);
  await page.locator('#editname').fill(band + ' (переименована)');
  await clickWait(page, '#save');
  expect((await messages(page)).join(' ')).toContain(`Назва в 1С: «${band}»`);
  expect(value(`select count(*) from items where itemname = '${esc(band)} (переименована)'`)).toBe('1');

  // акт списания: выдача обеих позиций — одна строка с названием из 1С
  const code1 = 'S1-' + uniq, code2 = 'S2-' + uniq, who = 'Получатель ' + uniq;
  query(`update items set item_code = '${code1}' where item_id = ${cheap}; update items set item_code = '${code2}' where item_id = ${dear}`);
  await createCustomer(page, { name: who, type: 1 });
  await createDoc(page, 'GoodsIssue', { date: '2026-09-05', customer: who, payment: 0, rows: [{ code: code1, qty: 2, price: 0 }, { code: code2, qty: 3, price: 0 }] });
  await page.goto('/index.php?p=App/Pages/Report/IssueAct');
  await expect(page.locator('#issueact-names1c')).toBeVisible();
  await page.locator('#filter #year').selectOption('2026');
  await page.locator('#filter #month').selectOption('9');
  await clickWait(page, '#filter #onreport');
  // больше 10 выдач — рядом скрытые куски для печати (table.d-print-table); считаем по экранной таблице
  const rows = page.locator('#preview table:not(.d-print-table) tr', { hasText: band });
  expect(await rows.count()).toBe(1);
  expect(await rows.first().innerText()).not.toContain('переименована');
  const cells = await rows.first().locator('td').allInnerTexts();
  expect(cells[cells.length - 1].trim()).toBe('5');

  // кавычки в названии: программа сохраняет их как `, это не переименование; повторный импорт — те же позиции
  const quoted = [`Мазь \`Тестовая\` ${uniq}`, `Крем \`Тестовый\` ${uniq}`];
  for (let i = 0; i < 2; i++) {
    await importFile('v5.xls');
    expect(await page.locator('#i1c-notes').count() ? await page.locator('#i1c-notes').innerText() : '').not.toMatch(/перейменовано/i);
    for (const q of quoted) expect(value(`select count(*) from items where itemname = '${esc(q)}'`), q).toBe('1');
  }
  expect(Number(value(`select sum(qty) from store_stock s join items i on i.item_id = s.item_id where i.itemname = '${esc(quoted[0])}'`))).toBe(8);
});
