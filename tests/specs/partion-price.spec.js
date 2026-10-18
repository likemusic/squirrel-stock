// Цена партии (store_stock.partion) — от нее считаются все суммы по товару: количество × цена.
// Настройка «Цена партии»: 2 знака (как у Zippy, по умолчанию) или точная (6 знаков, профиль благотворительной организации).
// В обоих положениях цена партии не зависит от «округления стоимости» (amdigits): у Zippy при «без копеек» приход
// округлял цену партии до рубля, позиция за 0,45 приходила с ценой 0.
const path = require('path');
const { test, expect } = require('../fixtures');
const { clickWait, runReport } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { query, value } = require('../helpers/db');
const { webPhp } = require('../helpers/stand');

async function setCommon(page, { amdigits, partion }) {
  await page.goto('/index.php?p=App/Pages/Options');
  if (amdigits !== undefined) await page.locator('#common #amdigits').selectOption(String(amdigits));
  if (partion !== undefined) await page.locator('#common #sqspartion').selectOption(String(partion));
  await clickWait(page, '#common button[type=submit]');
}

test.afterEach(async ({ page }) => setCommon(page, { amdigits: 1, partion: 0 }));

const docId = number => value(`select document_id from documents where document_number = '${number}' order by document_id desc limit 1`);
// цены партий документа и сумма его проводок (количество × цена партии) — так считают остатки и отчеты
const partions = id => query(`select distinct s.partion from entrylist e join store_stock s on s.stock_id = e.stock_id where e.document_id = ${id} order by 1`).map(r => r[0]);
const docSum = id => value(`select round(sum(e.quantity * s.partion), 2) from entrylist e join store_stock s on s.stock_id = e.stock_id where e.document_id = ${id}`);
const stockRows = code => query(`select s.partion, s.qty from store_stock s join items i on i.item_id = s.item_id where i.item_code = '${code}' order by s.stock_id`);

async function import1c(page, file) {
  await page.goto('/index.php?p=App/Pages/Service/Import1C');
  await page.locator('#iform #date').fill('2026-07-01');
  await page.locator('#iform #filename').setInputFiles(path.join(__dirname, '..', 'fixtures', file));
  await clickWait(page, '#iform #import');
  return value(`select document_id from documents where notes like 'Імпорт відомості 1С%' order by document_id desc limit 1`);
}

test('без копеек в суммах: цена партии на приходе и в импорте 1С не округляется до рубля', async ({ page, uniq }) => {
  await setCommon(page, { amdigits: 0 });
  const a = 'PA-' + uniq, b = 'PB-' + uniq;
  const { docs } = seed({ items: [{ name: 'Позиция ' + a, code: a }, { name: 'Позиция ' + b, code: b }],
                          docs: [{ type: 'GoodsReceipt', date: '2026-10-01', rows: [{ code: a, qty: 1, price: 0.45 }] },
                                 { type: 'GoodsReceipt', date: '2026-10-01', rows: [{ code: a, qty: 10, price: 0.45 }, { code: b, qty: 1, price: 1.5 }] }] });
  expect(partions(docs[0].id), 'одна позиция за 0,45: сумма документа «без копеек» — 0').toEqual(['0.450000']);
  expect(partions(docs[1].id)).toEqual(['0.450000', '1.500000']);

  const id = await import1c(page, 'osv-10-exact.xls');
  expect(partions(id), '108,37 / 900 и 100,00 / 3 — до копейки').toEqual(['0.120000', '33.330000']);
});

test('2 знака: повторный приход по той же цене не создает новую партию', async ({ uniq }) => {
  const code = 'PD-' + uniq;
  seed({ items: [{ name: 'Позиция ' + code, code }],
         docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 3, price: 33.333 }] },
                { type: 'IncomeItem', date: '2026-10-02', rows: [{ code, qty: 3, price: 33.333 }] }] });
  expect(stockRows(code)).toEqual([['33.330000', '6.000']]);
});

test('2 знака: импорт 1С — цена до копейки, как у Zippy', async ({ page }) => {
  const id = await import1c(page, 'osv-10-exact.xls');
  expect(partions(id)).toEqual(['0.120000', '33.330000']);
  expect(docSum(id), 'ведомость 208,37: 900 × 0,12 + 3 × 33,33').toBe('207.99');
});

test('точная: импорт 1С — сумма остатков равна сумме ведомости до копейки', async ({ page }) => {
  await setCommon(page, { partion: 1 });
  let id = await import1c(page, 'osv-10.xls');
  expect(docSum(id), 'ведомость 1250,50 (300,50 / 30 шт. = 10,0166…)').toBe('1250.50');
  id = await import1c(page, 'osv-10-exact.xls');
  expect(partions(id)).toEqual(['0.120411', '33.333333']);
  expect(docSum(id)).toBe('208.37');
  // документ импорта печатает сумму ведомости
  expect(value(`select amount from documents where document_id = ${id}`)).toBe('208.37');
});

test('точная: приход 900 шт. на 108,37 со скидкой, списание, выдача, повторный приход, отчет «Движение ТМЦ»', async ({ page, uniq }) => {
  await setCommon(page, { partion: 1 });
  const code = 'PX-' + uniq, name = 'Позиция ' + code;
  // 900 × 0,13 = 117,00, скидка 8,63 — 108,37: цена партии 0,120411 (коэффициент прихода)
  const { docs } = seed({ items: [{ name, code }], customers: [{ name: 'Получатель ' + code }],
                          docs: [{ type: 'GoodsReceipt', date: '2026-10-01', disc: 8.63, rows: [{ code, qty: 900, price: 0.13 }] },
                                 { type: 'OutcomeItem', date: '2026-10-02', rows: [{ code, qty: 300 }] },
                                 { type: 'GoodsIssue', date: '2026-10-02', customer: 'Получатель ' + code, rows: [{ code, qty: 100, price: 0 }] },
                                 { type: 'IncomeItem', date: '2026-10-03', rows: [{ code, qty: 3, price: 33.333333 }] },
                                 { type: 'IncomeItem', date: '2026-10-04', rows: [{ code, qty: 3, price: 33.333333 }] }] });
  expect(partions(docs[0].id)).toEqual(['0.120411']);
  expect(docSum(docs[0].id), 'приход').toBe('108.37');
  expect(docSum(docs[1].id), 'списание 300 шт.').toBe('-36.12');
  expect(docSum(docs[2].id), 'выдача 100 шт.').toBe('-12.04');
  expect(stockRows(code), 'повторный приход по той же цене — та же партия').toEqual([['0.120411', '500.000'], ['33.333333', '6.000']]);
  expect(value(`select round(sum(s.qty * s.partion), 2) from store_stock s join items i on i.item_id = s.item_id where i.item_code = '${code}'`), 'остаток').toBe('260.21');

  // акт списания: сумма строки по цене партии
  await page.goto('/index.php?p=App/Pages/ShowDoc&arg=print/' + docs[1].id);
  expect((await page.locator('body').innerText()).replace(/\s+/g, ' ')).toMatch(/36[.,]12/);

  // «Движение ТМЦ»: итог прихода 108,37 + 100,00 + 100,00, расхода 36,12 + 12,04
  await runReport(page, 'Report/ItemActivity', { from: '2026-10-01', to: '2026-10-04', autos: { item: code } });
  const text = (await page.locator('body').innerText()).replace(/\s+/g, ' ');
  expect(text).toMatch(/На суму 308\.37 48\.16/);
});

test('профиль благотворительной организации ставит «точная», --back возвращает 2 знака', async ({ uniq }) => {
  const code = 'PP-' + uniq;
  seed({ items: [{ name: 'Позиция ' + code, code }] });
  try {
    webPhp('cli/charity-profile.php');
    seed({ docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 3, price: 100 / 3 }] }] });
  } finally {
    webPhp('cli/charity-profile.php', '--back');
  }
  seed({ docs: [{ type: 'IncomeItem', date: '2026-10-02', rows: [{ code, qty: 3, price: 100 / 3 }] }] });
  expect(stockRows(code)).toEqual([['33.333333', '3.000'], ['33.330000', '3.000']]);
});
