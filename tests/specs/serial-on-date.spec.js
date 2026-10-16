// Единица с серийным (инвентарным) номером выдается, только если она на складе на дату документа и не выдана позже
// (иначе остаток номера уходит в минус). Ядро проверяло только общий остаток позиции: у модели с несколькими единицами
// можно было выдать уже выданный номер второй раз (нашлось на репетиции переноса истории оборудования, 16.10.2026).
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const day = n => new Date(Date.now() + n * 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));

test('расходная накладная: номера нет на складе на дату — не проводится', async ({ page, uniq }) => {
  test.setTimeout(5 * 60_000);
  const code = 'SD-' + uniq, a = 'SDA-' + uniq, b = 'SDB-' + uniq;
  const one = 'Получатель ' + uniq + '-1', two = 'Получатель ' + uniq + '-2';
  await createItem(page, { name: 'Аппарат ' + uniq, code, serial: true });
  await createCustomer(page, { name: one });
  await createCustomer(page, { name: two });
  await createDoc(page, 'IncomeItem', { date: day(-60), rows: [{ code, qty: 1, price: 0, sn: a }, { code, qty: 1, price: 0, sn: b }] });
  const issue = (date, sn, customer) => createDoc(page, 'GoodsIssue', { date, customer, payment: 0, rows: [{ code, qty: 1, price: 0, sn }] });
  const refused = async (date, sn, customer) => {
    const err = await issue(date, sn, customer).then(() => null, e => e.message);
    expect(err, `выдача ${sn} на ${date} не проведена`).toMatch(/не проведён/);
    expect(err).toMatch(new RegExp(`${sn}.*немає на складі на ${date.split('-').reverse().join('\\.')}`));
  };

  // форма накладной не дает выбрать номер, которого нет на складе сейчас; проверяются номера, которые на складе сейчас,
  // но не на дату документа. Вернувшаяся единица оформлена повторным приходом того же номера
  await issue(day(-30), a, one);
  await createDoc(page, 'IncomeItem', { date: day(-5), rows: [{ code, qty: 1, price: 0, sn: a }] });
  expect(qty(a)).toBe(1);
  await refused(day(-20), a, two);   // на ту дату выдан первому получателю
  await refused(day(-90), b, one);   // раньше прихода

  await issue(day(-10), b, one);
  await createDoc(page, 'IncomeItem', { date: day(-3), rows: [{ code, qty: 1, price: 0, sn: b }] });
  await refused(day(-40), b, two);   // на ту дату был, но выдан позже: задним числом остаток ушел бы в минус
  expect(qty(a)).toBe(1);
  expect(qty(b)).toBe(1);
  await issue(day(-1), b, two);      // сейчас на складе — выдается
  expect(value(`select count(*) from documents d join customers c on c.customer_id = d.customer_id where c.customer_name = '${two}'`)).toBe('1');
});

// Списание и перемещение — те же правила. Проведение не смотрит, что вернул Execute(): отказ — исключением, иначе документ
// помечался проведенным без движений (так было со списанием).
test('списание и перемещение: номера нет на складе на дату — не проводятся', async ({ page, uniq }) => {
  test.setTimeout(5 * 60_000);
  const code = 'SM-' + uniq, a = 'SMA-' + uniq, b = 'SMB-' + uniq, one = 'Получатель ' + uniq, repair = 'Ремонт ' + uniq;
  query(`insert into stores (storename, branch_id, disabled) values ('${repair}', 0, 0)`);
  const rs = value(`select max(store_id) from stores where storename = '${repair}'`);
  await createItem(page, { name: 'Аппарат ' + uniq, code, serial: true });
  await createCustomer(page, { name: one });
  await createDoc(page, 'IncomeItem', { date: day(-60), rows: [{ code, qty: 1, price: 0, sn: a }, { code, qty: 1, price: 0, sn: b }] });
  await createDoc(page, 'GoodsIssue', { date: day(-30), customer: one, payment: 0, rows: [{ code, qty: 1, price: 0, sn: a }] });
  await createDoc(page, 'IncomeItem', { date: day(-5), rows: [{ code, qty: 1, price: 0, sn: a }] });   // вернулась
  await createDoc(page, 'GoodsIssue', { date: day(-10), customer: one, payment: 0, rows: [{ code, qty: 1, price: 0, sn: b }] });
  const refused = async (type, date, sn, extra = {}) => {
    const docs = () => value(`select count(*) from documents where meta_id = (select meta_id from metadata where meta_name = '${type}')`);
    const was = docs();
    const err = await createDoc(page, type, { date, ...extra, rows: [{ code, qty: 1, sn }] }).then(() => null, e => e.message);
    expect(err, `${type} ${sn} на ${date} не проведен`).toMatch(/не проведён/);
    expect(err).toMatch(new RegExp(`${sn}.*немає на складі на ${date.split('-').reverse().join('\\.')}`));
    expect(docs(), 'документ не сохранен').toBe(was);
  };

  await refused('OutcomeItem', day(-20), a);                  // на ту дату на руках
  await refused('MoveItem', day(-20), a, { tostore: rs });
  await refused('MoveItem', day(0), b, { tostore: rs });       // на руках сейчас: форма перемещения номер не проверяет
  expect(qty(a)).toBe(1);
  expect(qty(b)).toBe(0);
  expect(Number(value(`select count(*) from store_stock where snumber in ('${a}', '${b}') and qty < 0`))).toBe(0);

  await createDoc(page, 'MoveItem', { date: day(0), tostore: rs, rows: [{ code, qty: 1, sn: a }] });
  expect(Number(value(`select qty from store_stock where snumber = '${a}' and store_id = ${rs}`))).toBe(1);
});

// Возврат поставщику и недостача по инвентаризации — без формы (fixtures/serial-doc.php): строки возврата берутся
// из прихода от поставщика, а проверяется проведение документа в ядре
test('возврат поставщику и инвентаризация: номера нет на складе на дату — не проводятся', async ({ page, uniq }) => {
  test.setTimeout(5 * 60_000);
  const code = 'SR-' + uniq, a = 'SRA-' + uniq, b = 'SRB-' + uniq, one = 'Получатель ' + uniq;
  await createItem(page, { name: 'Аппарат ' + uniq, code, serial: true });
  await createCustomer(page, { name: one });
  await createDoc(page, 'IncomeItem', { date: day(-60), rows: [{ code, qty: 1, price: 0, sn: a }, { code, qty: 1, price: 0, sn: b }] });
  await createDoc(page, 'GoodsIssue', { date: day(-30), customer: one, payment: 0, rows: [{ code, qty: 1, price: 0, sn: a }] });
  await createDoc(page, 'IncomeItem', { date: day(-5), rows: [{ code, qty: 1, price: 0, sn: a }] });
  await createDoc(page, 'GoodsIssue', { date: day(-10), customer: one, payment: 0, rows: [{ code, qty: 1, price: 0, sn: b }] });
  const item = value(`select item_id from items where item_code = '${code}'`);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'serial-doc.php'), `${WEB}:/tmp/serial-doc-${uniq}.php`]);
  const run = (type, date, sn) => execFileSync('docker', ['exec', '-u', 'www-data', WEB, 'php', `/tmp/serial-doc-${uniq}.php`, type, date, item, sn], { encoding: 'utf8' }).trim();
  const refused = (type, date, sn) => expect(run(type, date, sn), `${type} ${sn} на ${date}`).toMatch(new RegExp(`^error .*${sn}.*немає на складі на ${date.split('-').reverse().join('\\.')}`));
  try {
    refused('RetCustIssue', day(-20), a);   // на ту дату на руках
    refused('Inventory', day(-20), a);
    refused('RetCustIssue', day(0), b);     // на руках сейчас
    refused('Inventory', day(0), b);
    expect(qty(a)).toBe(1);
    expect(qty(b)).toBe(0);
    expect(run('Inventory', day(0), a)).toMatch(/^ok /);   // на складе — недостача списывается
    expect(qty(a)).toBe(0);
  } finally {
    execFileSync('docker', ['exec', WEB, 'rm', '-f', `/tmp/serial-doc-${uniq}.php`]);
  }
});
