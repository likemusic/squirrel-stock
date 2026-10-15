// Отчёт «Продажи» (Outcome) не должен падать, если товар с закупочной ценой выдан по цене 0
// (обычная выдача подопечному хосписа). Было: Division by zero. ZIPPY_GAPS п. 4.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createDoc, runReport, bodyText } = require('../helpers/zippy');

test('«Продажи» по покупателю при выдаче по цене 0', async ({ page, uniq }) => {
  const code = 'OZ-' + uniq, supplier = 'Поставщик ' + uniq, recipient = 'Подопечный ' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  await createCustomer(page, { name: supplier, type: 2 });
  await createCustomer(page, { name: recipient, type: 1 });
  await createDoc(page, 'GoodsReceipt', { date: '2026-10-01', customer: supplier, payment: 1, rows: [{ code, qty: 10, price: 5 }] });
  await createDoc(page, 'GoodsIssue', { date: '2026-10-02', customer: recipient, payment: 0, rows: [{ code, qty: 2, price: 0 }] });

  // 6 — товары по покупателю, 1 — по товарам, 2 — по покупателям
  for (const [type, expected] of [[6, code], [1, code], [2, recipient]]) {
    await runReport(page, 'Report/Outcome', { from: '2026-10-01', to: '2026-10-31', selects: { type }, autos: type === 6 ? { cust: recipient } : {} });
    const text = await bodyText(page);
    expect(text, `тип отчёта ${type}`).not.toMatch(/Division by zero|DivisionByZero|Fatal|Exception/i);
    expect(text, `тип отчёта ${type}`).toContain(expected);
  }
});
