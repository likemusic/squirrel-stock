// Подготовка данных без интерфейса (fixtures/seed.php, helpers/seed.js) дает то же, что формы: позиции, контрагенты,
// договоры и документы всех пяти видов, которые создает seed, — одинаковые поля, шапка, строки, проводки и остатки.
// Пока этот тест проходит, остальные тесты могут готовить фон через seed, а не через формы.
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createContract, createDoc } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { query, value, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
let dumpCopied = false;
function dump(ids) {
  if (!dumpCopied) {
    execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'doc-dump.php'), `${WEB}:/tmp/sqs-doc-dump.php`]);
    dumpCopied = true;
  }
  return JSON.parse(execFileSync('docker', ['exec', '-u', 'www-data', WEB, 'php', '/tmp/sqs-doc-dump.php', ...ids.map(String)], { encoding: 'utf8' }).trim());
}

// Значения, которые различаются по построению: id, номера, метки времени; имена и коды — с суффиксом набора (U… или S…)
const VOLATILE = new Set(['document_number', 'hash', 'time', 'lastupdated', 'createdon', 'stock_id', 'entry_id']);
const IDS = new Set(['item_id', 'customer_id', 'contract_id', 'document_id', 'parent_id']);
function normalize(v, ids, mark, key = '') {
  if (Array.isArray(v)) return v.map(x => normalize(x, ids, mark));
  if (v && typeof v === 'object') {
    return Object.fromEntries(Object.keys(v).sort().map(k => [k, VOLATILE.has(k) ? '*' : normalize(v[k], ids, mark, k)]));
  }
  if (IDS.has(key) && ids[String(v)]) return ids[String(v)];
  return typeof v === 'string' ? v.split(mark).join('#') : v;
}

const codes = [];
test.afterEach(() => {   // позиция с режимом «срок годности» показывает поле срока во всех формах прихода (SerialStock::modeUsed)
  if (codes.length) query(`update items set disabled = 1 where item_code in (${codes.map(c => `'${esc(c)}'`).join(',')})`);
});

// набор данных для одного способа: позиции без учета серий, с серийным номером, партиями со сроком; донор, получатель, договор
function names(mark) {
  return {
    plain: 'PP-' + mark, serial: 'PS-' + mark, batch: 'PB-' + mark,
    donor: 'Донор ' + mark, who: 'Получатель ' + mark, contract: 'Д' + mark,
  };
}

const rowsFor = (n, type) => {
  const sn = n.serial.replace('PS-', 'SN-');
  switch (type) {
    case 'IncomeItem': return [{ code: n.plain, qty: 4, price: 0 }, { code: n.serial, qty: 1, price: 0, sn }, { code: n.batch, qty: 6, price: 0, sn: 'L1', sdate: '2029-05-31' }];
    case 'GoodsReceipt': return [{ code: n.plain, qty: 4, price: 0 }, { code: n.serial, qty: 1, price: 0, sn }, { code: n.batch, qty: 6, price: 0, sn: 'L1', sdate: '2029-05-31' }];
    case 'GoodsIssue': return [{ code: n.plain, qty: 2, price: 0 }, { code: n.serial, qty: 1, price: 0, sn }, { code: n.batch, qty: 2, price: 0, sn: 'L1' }];
    default: return [{ code: n.plain, qty: 1 }, { code: n.serial, qty: 1, sn }, { code: n.batch, qty: 1, sn: 'L1' }];
  }
};

test('позиции, контрагенты и договор из seed — как из справочников', async ({ page, uniq }) => {
  const u = names('U' + uniq), s = names('S' + uniq);
  codes.push(u.batch, s.batch);
  await createItem(page, { name: 'Позиция ' + u.plain, code: u.plain, minqty: 2 });
  await createItem(page, { name: 'Позиция ' + u.serial, code: u.serial, serial: true });
  await createCustomer(page, { name: u.donor, type: 2 });
  await createCustomer(page, { name: u.who, type: 1, address: 'Адрес 1' });
  await createContract(page, { number: u.contract, customer: u.who, from: '2026-10-01', to: '2099-12-31', note: 'Врач Тестовый' });
  seed({
    items: [{ code: s.plain, name: 'Позиция ' + s.plain, minqty: 2 }, { code: s.serial, name: 'Позиция ' + s.serial, serial: true }],
    customers: [{ name: s.donor, type: 2 }, { name: s.who, type: 1, address: 'Адрес 1' }],
    contracts: [{ number: s.contract, customer: s.who, from: '2026-10-01', to: '2099-12-31', note: 'Врач Тестовый' }],
  });

  const item = code => query(`select itemname, item_code, cat_id, msr, disabled, detail from items where item_code = '${esc(code)}'`)[0];
  const cust = name => query(`select customer_name, detail, phone, email, status, city, leadstatus, leadsource, country from customers where customer_name = '${esc(name)}'`)[0];
  const contr = num => query(`select k.contract_number, k.state, date(k.createdon), k.details, c.customer_name from contracts k
                              join customers c on c.customer_id = k.customer_id where contract_number = '${esc(num)}'`)[0];
  const same = (a, b) => expect(b.map(x => String(x).split('S' + uniq).join('#')).join('\n'))
    .toBe(a.map(x => String(x).split('U' + uniq).join('#')).join('\n'));
  same(item(u.plain), item(s.plain));
  same(item(u.serial), item(s.serial));
  same(cust(u.donor), cust(s.donor));
  same(cust(u.who), cust(s.who));
  // <creator_id> и <user_id> — администратор в обоих случаях
  same(contr(u.contract), contr(s.contract));
  const history = num => query(`select m.message, m.user_id from messages m join contracts k on k.contract_id = m.item_id
                                where m.item_type = 7 and k.contract_number = '${esc(num)}' order by m.message_id`).map(r => r.join('|'));
  expect(history(s.contract)).toEqual(history(u.contract));
  expect(history(u.contract)).toHaveLength(1);
});

for (const type of ['IncomeItem', 'GoodsReceipt', 'GoodsIssue', 'OutcomeItem', 'MoveItem']) {
  test(`${type} из seed — как из формы`, async ({ page, uniq }) => {
    const u = names('U' + uniq), s = names('S' + uniq);
    codes.push(u.batch, s.batch);
    const rs = type === 'MoveItem' ? (() => {
      query(`insert into stores (storename, branch_id, disabled) values ('Склад ${uniq}', 0, 0)`);
      return Number(value(`select max(store_id) from stores where storename = 'Склад ${uniq}'`));
    })() : undefined;

    // фон для обоих способов одинаковый (seed), сравнивается только сам документ
    const base = n => ({
      items: [{ code: n.plain, name: 'Позиция ' + n.plain }, { code: n.serial, name: 'Позиция ' + n.serial, serial: true },
              { code: n.batch, name: 'Позиция ' + n.batch, serial: true, snmode: 2 }],
      customers: [{ name: n.donor, type: 2 }, { name: n.who, type: 1 }],
      contracts: [{ number: n.contract, customer: n.who, from: '2026-10-01', to: '2099-12-31', note: 'Врач Тестовый' }],
      docs: ['IncomeItem', 'GoodsReceipt'].includes(type) ? [] : [{ type: 'IncomeItem', date: '2026-10-01', rows: rowsFor(n, 'IncomeItem') }],
    });
    const ub = seed(base(u)), sb = seed(base(s));

    const d = n => ({
      date: '2026-10-05', notes: 'Сверка ' + n.plain,
      customer: type === 'GoodsReceipt' ? n.donor : type === 'GoodsIssue' ? n.who : undefined,
      contract: type === 'GoodsIssue' ? n.contract : undefined,
      payment: ['GoodsReceipt', 'GoodsIssue'].includes(type) ? 0 : undefined,
      tostore: rs, rows: rowsFor(n, type),
    });
    await createDoc(page, type, d(u));
    const uid = Number(value(`select document_id from documents where notes = '${esc('Сверка ' + u.plain)}'`));
    const sid = seed({ docs: [{ type, ...d(s) }] }).docs[0].id;

    const ids = res => Object.fromEntries([...Object.entries(res.items).map(([c, id]) => [String(id), c.replace(/^P(.)-.*/, 'item-$1')]),
      ...Object.entries(res.customers).map(([c, id]) => [String(id), c.split(' ')[0]]),
      ...Object.entries(res.contracts).map(([, id]) => [String(id), 'contract'])]);
    const [ud, sd] = dump([uid, sid]);
    expect(normalize(sd, ids(sb), 'S' + uniq)).toEqual(normalize(ud, ids(ub), 'U' + uniq));
  });
}
