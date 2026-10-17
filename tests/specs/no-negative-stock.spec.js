// Запрет минусовых остатков (настройка allowminus выключена в global-setup): выдать или списать больше,
// чем есть на складе, нельзя. В Zippy это работает — тест следит, чтобы доработки форка этого не сломали. ZIPPY_GAPS п. 7.
const { test, expect } = require('../fixtures');
const { createDoc } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { value, stockQty, esc } = require('../helpers/db');

for (const type of ['GoodsIssue', 'OutcomeItem']) {
  test(`${type}: больше остатка не проводится`, async ({ page, uniq }) => {
    const code = 'NM-' + uniq, who = 'Подопечный ' + uniq, notes = 'Тест минуса ' + uniq;
    seed({ items: [{ name: 'Позиция ' + code, code }], customers: [{ name: who, type: 1 }],
           docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 2, price: 0 }] }] });

    const err = await createDoc(page, type, { date: '2026-10-02', customer: type === 'GoodsIssue' ? who : undefined, notes, rows: [{ code, qty: 5, price: 0 }] })
      .then(() => null, e => e.message);
    expect(err, 'документ на 5 при остатке 2 проведён').not.toBeNull();
    expect(stockQty(code), 'остаток').toBe(2);
    expect(Number(value(`select count(*) from documents where notes = '${esc(notes)}' and state = 5`)), 'проведённых документов').toBe(0);

    // в пределах остатка — проводится
    await createDoc(page, type, { date: '2026-10-03', customer: type === 'GoodsIssue' ? who : undefined, rows: [{ code, qty: 2, price: 0 }] });
    expect(stockQty(code)).toBe(0);
  });
}
