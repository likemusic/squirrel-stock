// Единица с серийным (инвентарным) номером выдается, только если она на складе на дату документа и не выдана позже
// (иначе остаток номера уходит в минус). Ядро проверяло только общий остаток позиции: у модели с несколькими единицами
// можно было выдать уже выданный номер второй раз (нашлось на репетиции переноса истории оборудования, 16.10.2026).
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc } = require('../helpers/zippy');
const { value } = require('../helpers/db');

const day = n => new Date(Date.now() + n * 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));

test('расходная накладная: номера нет на складе на дату — не проводится', async ({ page, uniq }) => {
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
