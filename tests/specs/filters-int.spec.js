// Фильтры журналов, справочников, отчетов и документов после приведения номеров к числу (intval) и экранирования (qstr)
// в SQL (ZIPPY_GAPS п. 21): с обычными значениями из списков отбор работает как раньше — нужное есть, лишнего нет.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, pickAuto, clickWait, bodyText } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

// Выбрать значения в форме и нажать ее «ОК»
async function submitFilter(page, form, { selects = {}, texts = {}, autos = {}, submit } = {}) {
  const f = page.locator(form);
  for (const [id, v] of Object.entries(selects)) {
    await Promise.all([page.waitForLoadState('networkidle'), f.locator('#' + id).selectOption(String(v))]);
  }
  for (const [id, v] of Object.entries(texts)) await f.locator('#' + id).fill(v);
  for (const [id, v] of Object.entries(autos)) await pickAuto(page, `${form} #${id}`, v, v);
  await Promise.all([page.waitForLoadState('networkidle'), f.locator(submit || 'input[type=submit]:visible, button[type=submit]:visible').first().click()]);
  await page.waitForTimeout(400);
}

test('фильтры со своими обычными значениями отбирают как раньше', async ({ page, uniq }) => {
  test.setTimeout(300_000);
  query(`insert into item_cat (cat_name, parent_id) values ('Категория A ${uniq}', 0)`);
  const catA = value(`select max(cat_id) from item_cat where cat_name = 'Категория A ${uniq}'`);
  query(`insert into item_cat (cat_name, parent_id) values ('Категория B ${uniq}', 0)`);
  const catB = value(`select max(cat_id) from item_cat where cat_name = 'Категория B ${uniq}'`);
  const codeA = 'FA-' + uniq, codeB = 'FB-' + uniq, itemA = 'Позиция ' + codeA, itemB = 'Позиция ' + codeB, r = 'Получатель ' + uniq;
  await createItem(page, { name: itemA, code: codeA, serial: true });
  await createItem(page, { name: itemB, code: codeB, serial: true });
  query(`update items set cat_id = ${catA} where item_code = '${codeA}'`);
  query(`update items set cat_id = ${catB} where item_code = '${codeB}'`);
  await createCustomer(page, { name: r, type: 1 });
  const inc = await createDoc(page, 'IncomeItem', { date: '2026-09-01', rows: [{ code: codeA, qty: 1, price: 0, sn: 'SA-' + uniq }, { code: codeB, qty: 1, price: 0, sn: 'SB-' + uniq }] });
  const gi = await createDoc(page, 'GoodsIssue', { date: '2026-09-02', customer: r, payment: 0, rows: [{ code: codeA, qty: 1, price: 0, sn: 'SA-' + uniq }] });
  const metaGI = value(`select meta_id from metadata where meta_name = 'GoodsIssue'`);
  const metaInc = value(`select meta_id from metadata where meta_name = 'IncomeItem'`);
  const admin = value(`select user_id from users where userlogin = 'admin'`);
  const wrong = [];
  const check = async (name, has, hasNot = []) => {
    const t = await bodyText(page);
    for (const s of has) if (!t.includes(s)) wrong.push(`${name}: нет «${s}»`);
    for (const s of hasNot) if (t.includes(s)) wrong.push(`${name}: лишнее «${s}»`);
  };

  // Журнал документов: тип, автор, склад, затем контрагент (фильтр журнала запоминается между отправками)
  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await submitFilter(page, '#filter', { texts: { from: '2026-09-01', to: '2026-09-30' }, selects: { doctype: metaInc, author: admin, store: 1 } });
  await check('DocList, оприходование', [inc.number], [gi.number]);
  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await submitFilter(page, '#filter', { texts: { from: '2026-09-01', to: '2026-09-30' }, selects: { doctype: metaGI, author: admin, store: 1 }, autos: { searchcust: r } });
  await check('DocList, выдача', [gi.number], [inc.number]);

  // Журнал выдач: контрагент и склад
  await page.goto('/index.php?p=App/Pages/Register/GIList');
  await submitFilter(page, '#filter', { selects: { status: 3, fstore: 1 }, autos: { searchcust: r } });
  await check('GIList', [gi.number]);

  // Товары на складе (с остатком: A выдана целиком, B на складе): категория и склад
  await page.goto('/index.php?p=App/Pages/Register/ItemList');
  await submitFilter(page, '#filter', { selects: { searchcat: catB, searchstore: 1 } });
  await check('Register/ItemList', [itemB], [itemA]);

  // Справочник товаров: категория
  await page.goto('/index.php?p=App/Pages/Reference/ItemList');
  await submitFilter(page, '#filter', { selects: { searchcat: catB } });
  await check('Reference/ItemList', [itemB], [itemA]);

  // Движение товара: склад и категория
  await page.goto('/index.php?p=App/Pages/Report/ItemActivity');
  await submitFilter(page, '#filter', { texts: { from: '2026-09-01', to: '2026-09-30' }, selects: { store: 1, searchcat: catA }, submit: '#onreport' });
  await check('Report/ItemActivity', [itemA], [itemB]);

  // Экспорт: просмотр документов по типу
  await page.goto('/index.php?p=App/Pages/Service/Export');
  await submitFilter(page, '#dform', { selects: { dtype: 'GoodsIssue' }, texts: { dfrom: '2026-09-01', dto: '2026-09-30' } });
  await check('Service/Export', [gi.number]);

  // Инвентаризация: «загрузить все» (позиции с остатком) по складу и категории
  await page.goto('/index.php?p=App/Pages/Doc/Inventory');
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#docform #store').selectOption('1')]);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#docform #category').selectOption(String(catB))]);
  await clickWait(page, '#docform #loadall');
  await check('Doc/Inventory', [itemB], [itemA]);

  expect(wrong).toEqual([]);
});
