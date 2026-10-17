// Режим серий у позиции (ZIPPY_GAPS п. 8), формы ядра: режим задается в справочнике (у позиции или отмеченным позициям),
// приход и расход проверяют строку по режиму ее позиции. Общий режим — как у хосписа (серийные номера); расходник —
// партиями со сроком годности. Позиции с режимом после теста отключаются: включенная позиция с режимом «срок годности»
// показывает поле срока во всех формах прихода (App\SQS\SerialStock::modeUsed), а остальные тесты ждут формы хосписа.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, pickAuto, clickWait, messages } = require('../helpers/zippy');
const { query, value, esc } = require('../helpers/db');

const codes = [];
test.afterEach(() => {
  if (codes.length) query(`update items set disabled = 1 where item_code in (${codes.map(c => `'${esc(c)}'`).join(',')})`);
});

const detail = code => value(`select detail from items where item_code = '${esc(code)}'`);

async function openItem(page, code) {
  await page.goto('/index.php?p=App/Pages/Reference/ItemList');
  await page.locator('#filter #searchkey').fill(code);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchkey').press('Enter')]);
}

test('режим серий: у позиции в справочнике и отмеченным позициям', async ({ page, uniq }) => {
  const a = 'MA-' + uniq, b = 'MB-' + uniq, c = 'MC-' + uniq;
  codes.push(a, b, c);

  // новая позиция: флажок серий и режим «партии со сроком»
  await page.goto('/index.php?p=App/Pages/Reference/ItemList');
  await clickWait(page, '#addnew');
  await page.locator('#editname').fill('Раствор ' + a);
  await page.locator('#editcode').fill(a);
  await page.locator('#editmsr').fill('шт');
  await page.locator('#edituseserial').setChecked(true);
  const opts = await page.locator('#editsnmode option').allInnerTexts();
  expect(opts[0]).toMatch(/\(.+\)/);   // «как в настройках (по серийным номерам изделий)»
  await page.locator('#editsnmode').selectOption('2');
  await clickWait(page, '#save');
  expect(detail(a)).toContain('<snmode>2</snmode>');

  // позиция без режима — detail без snmode, как у Zippy
  await createItem(page, { name: 'Позиция ' + b, code: b });
  await createItem(page, { name: 'Позиция ' + c, code: c });
  expect(detail(b)).not.toContain('snmode');

  // отмеченным: «партии» включает учет серий; «как в настройках» снимает режим, флажок серий оставляет
  await openItem(page, uniq);
  for (const code of [b, c]) await page.locator('tr', { hasText: code }).locator('input.seldel').check();
  await Promise.all([page.waitForNavigation(), page.locator('#allsnmode').selectOption('1')]);   // выбор отправляет форму
  expect((await messages(page)).join(' ')).toContain('2');
  for (const code of [b, c]) {
    expect(detail(code)).toContain('<useserial>1</useserial>');
    expect(detail(code)).toContain('<snmode>1</snmode>');
  }
  expect(detail(a), 'не отмечена — не меняется').toContain('<snmode>2</snmode>');
  await openItem(page, uniq);
  await page.locator('tr', { hasText: b }).locator('input.seldel').check();
  await Promise.all([page.waitForNavigation(), page.locator('#allsnmode').selectOption('9')]);   // выбор отправляет форму
  expect(detail(b)).not.toContain('snmode');
  expect(detail(b)).toContain('<useserial>1</useserial>');
});

test('приход и расход в базе с двумя режимами: партия со сроком и серийный номер', async ({ page, uniq }) => {
  const cons = 'MS-' + uniq, eq = 'ME-' + uniq, donor = 'Донор ' + uniq, who = 'Получатель ' + uniq;
  codes.push(cons, eq);
  await createItem(page, { name: 'Раствор ' + cons, code: cons, serial: true });
  await createItem(page, { name: 'Концентратор ' + eq, code: eq, serial: true });
  query(`update items set detail = replace(detail, '<useserial>1</useserial>', '<useserial>1</useserial><snmode>2</snmode>') where item_code = '${cons}'`);
  await createCustomer(page, { name: donor, type: 2 });
  await createCustomer(page, { name: who, type: 1 });

  await page.goto('/index.php?p=App/Pages/Doc/GoodsReceipt');
  await page.locator('#docform #store').selectOption('1');
  await expect(page.locator('#opensn'), 'ввод списком номеров есть: общий режим — серийные номера').toBeAttached();
  await clickWait(page, '#addrow');
  await expect(page.locator('#editsdate'), 'поле срока есть: срок годности — у позиции').toBeAttached();

  const row = async (code, f) => {
    if (!(await page.locator('#editdetail:visible').count())) await clickWait(page, '#addrow');
    await pickAuto(page, '#edititem', code, '', code);
    await page.locator('#editquantity').fill(String(f.qty));
    await page.locator('#editprice').fill('0');   // с суммой больше нуля нужна касса
    await page.locator('#editsnumber').fill(f.sn || '');
    await page.locator('#editsdate').fill(f.sdate || '');
    await clickWait(page, '#saverow');
    return (await messages(page)).join(' ');
  };
  // расходник: партия без срока — отказ (режим позиции 2), со сроком и количеством 10 — можно
  expect(await row(cons, { qty: 10, sn: 'П1-' + uniq })).toMatch(/дат/i);
  await row(cons, { qty: 10, sn: 'П1-' + uniq, sdate: '2029-03-31' });
  // концентратор: режим общий (серийные номера) — без номера отказ, количество 1 с номером — можно
  expect(await row(eq, { qty: 2 })).not.toBe('');
  await row(eq, { qty: 1, sn: 'INV1-' + uniq });
  if (await page.locator('#editdetail:visible').count()) await clickWait(page, '#cancelrow');
  await expect(page.locator('#docform a[id^=delete_]:visible')).toHaveCount(2);

  // ввод списком номеров — только для позиций с серийными номерами
  await clickWait(page, '#opensn');
  await pickAuto(page, '#editsnitemname', cons, '', cons);
  await page.locator('#editsn').fill('П2-' + uniq + '\nП3-' + uniq);
  await page.locator('#editsnprice').fill('1');
  await clickWait(page, '#savesnitem');
  expect((await messages(page)).join(' ')).toContain('серійними номерами');   // тесты — на украинском (global-setup)
  await clickWait(page, '#cancelsnitem');

  await pickAuto(page, '#docform #customer', donor, donor);
  if (await page.locator('#docform #payment:visible').count()) await page.locator('#docform #payment').selectOption('0');
  await page.locator('#document_date').fill('2026-10-01');
  await clickWait(page, '#execdoc');
  expect(value(`select date(sdate) from store_stock where snumber = 'П1-${uniq}'`)).toBe('2029-03-31');
  expect(value(`select cast(sum(qty) as signed) from store_stock where snumber = 'П1-${uniq}'`)).toBe('10');

  // расход: у партии подставляется срок из остатка (режим позиции 2), номер концентратора — по общему режиму
  const r = await createDoc(page, 'GoodsIssue', { date: '2026-10-05', customer: who, payment: 0, rows: [
    { code: cons, qty: 3, price: 0, sn: 'П1-' + uniq }, { code: eq, qty: 1, price: 0, sn: 'INV1-' + uniq }] });
  const content = value(`select content from documents where document_number = '${esc(r.number)}' order by document_id desc`);
  expect(content).toMatch(new RegExp(`П1-${uniq}`));
  expect(value(`select cast(-sum(e.quantity) as signed) from entrylist e join store_stock s on s.stock_id = e.stock_id
                join documents d on d.document_id = e.document_id where d.document_number = '${esc(r.number)}' and s.snumber = 'П1-${uniq}'`)).toBe('3');
});
