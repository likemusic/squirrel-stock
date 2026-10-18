// Отбор по категории в отчетах форка включает подкатегории: категория «Расходники» с подкатегорией «Перевязка» —
// по родителю позиция подкатегории в отчете есть (раньше по родителю было пусто, по подкатегории — есть).
const { test, expect } = require('../fixtures');
const { runReport, bodyText } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { query, value } = require('../helpers/db');

test('отбор по родительской категории включает подкатегории', async ({ page, uniq }) => {
  query(`insert into item_cat (cat_name, parent_id) values ('Родитель ${uniq}', 0)`);
  const parent = value(`select max(cat_id) from item_cat where cat_name = 'Родитель ${uniq}'`);
  query(`insert into item_cat (cat_name, parent_id) values ('Подкатегория ${uniq}', ${parent})`);
  const child = value(`select max(cat_id) from item_cat where cat_name = 'Подкатегория ${uniq}'`);
  query(`insert into item_cat (cat_name, parent_id) values ('Внук ${uniq}', ${child})`);
  const grandchild = value(`select max(cat_id) from item_cat where cat_name = 'Внук ${uniq}'`);

  const code = 'SC-' + uniq, item = 'Позиция ' + code, inv = 'INV-SC-' + uniq, r = 'Получатель ' + uniq;
  seed({
    items: [{ name: item, code, serial: true, minqty: 5, cat_id: grandchild }],
    customers: [{ name: r, type: 1 }],
    docs: [{ type: 'IncomeItem', date: '2026-09-01', rows: [{ code, qty: 1, price: 0, sn: inv }] },
           { type: 'GoodsIssue', date: '2026-09-03', customer: r, rows: [{ code, qty: 1, price: 0, sn: inv }] }],
  });

  const period = { from: '2026-09-01', to: '2026-09-30' };
  const reports = [
    ['IssueAct', { selects: { year: 2026, month: 9, kind: 0, cat: parent } }, item],   // kind 0 — «всё вместе»: позиция с инв. номером в акте — оборудование, а по умолчанию акт по расходникам
    ['ItemTurnover', { ...period, selects: { cat: parent } }, item],
    ['RecipientMonths', { ...period, selects: { cat: parent } }, r],
    ['EquipmentWhere', { selects: { cat: parent } }, item],
    ['EquipmentJournal', { ...period, selects: { cat: parent } }, r],
    ['BelowMinimum', { selects: { cat: parent } }, item],
  ];
  const missing = [];
  for (const [name, f, expectText] of reports) {
    await runReport(page, 'Report/' + name, f);
    if (!(await bodyText(page)).includes(expectText)) missing.push(name);
  }
  expect(missing, 'отчеты, где по родительской категории нет позиции подкатегории').toEqual([]);
});
