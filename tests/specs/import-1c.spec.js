// Импорт номенклатуры и остатков из оборотно-сальдовой ведомости 1С (.xls) — так хоспис заведет справочник и
// начальные остатки: материалы (счет 10, две строки на позицию) и основные средства (счет 01, единица = инв. №).
// Названия — как в 1С, иначе бухгалтерия не сопоставит акт. ZIPPY_GAPS п. 17. Файлы — tests/fixtures/make-1c.php.
const path = require('path');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const qtyByName = name => Number(value(`select coalesce(sum(s.qty), 0) from store_stock s join items i on i.item_id = s.item_id where i.itemname = '${name}'`));
const units = name => query(`select s.snumber from store_stock s join items i on i.item_id = s.item_id where i.itemname = '${name}' and s.qty > 0 order by 1`).map(r => r[0]);

test('импорт ведомости 1С в меню сервиса', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('a[href*="Service/Import1C"]').first(), 'пункт меню (db/squirrelstock.sql)').toBeAttached();
});

async function importFile(page, file) {
  await page.goto('/index.php?p=App/Pages/Service/Import1C');
  await page.locator('#iform #date').fill('2026-07-01');
  await page.locator('#iform #filename').setInputFiles(path.join(__dirname, '..', 'fixtures', file));
  await clickWait(page, '#iform #import');
  return (await page.locator('body').innerText()).replace(/\s+/g, ' ');
}

test('импорт ведомости 1С: материалы', async ({ page }) => {
  const gloves = 'Перчатки тестовые нестерильные размер M', syringe = 'Шприц тестовый 10 мл';
  const before = [qtyByName(gloves), qtyByName(syringe)];
  const text = await importFile(page, 'osv-10.xls');
  expect(text).toMatch(/позицій: 3/);   // украинский интерфейс тестов
  expect(qtyByName(gloves) - before[0]).toBe(30);
  expect(qtyByName(syringe) - before[1]).toBe(8);
  // цена = сумма / количество, дата документа — из формы
  expect(value(`select round(s.partion, 2) from store_stock s join items i on i.item_id = s.item_id where i.itemname = '${syringe}' order by s.stock_id desc limit 1`)).toBe('118.75');
  expect(value(`select date(d.document_date) from documents d where d.notes like 'Імпорт відомості 1С%' order by d.document_id desc limit 1`)).toBe('2026-07-01');
  // строка с суммой без количества (остаток округления в 1С) не переносится, но не пропадает молча: сводка и таблица
  expect(text).toMatch(/оприбутковано 2 з 3, пропущено рядків без кількості: 1 на суму 12\.00/);
  const skipped = page.locator('#i1c-skipped');
  await expect(skipped.locator('tr', { hasText: 'Салфетки тестовые' })).toContainText(/10\.1\s*\/\s*14\s*12\.00/);
  await expect(skipped.locator('tfoot')).toContainText('12.00');
});

test('импорт ведомости 1С: основные средства по инвентарным номерам, повторный импорт не дублирует', async ({ page }) => {
  const conc = 'Концентратор кислорода тестовый', bed = 'Кровать функциональная тестовая';
  await importFile(page, 'osv-01.xls');
  expect(units(conc)).toEqual(['01140001', '01140002']);
  expect(units(bed)).toEqual(['77']);
  expect(value(`select detail like '%<useserial>1</useserial>%' from items where itemname = '${conc}'`)).toBe('1');
  const text = await importFile(page, 'osv-01.xls');
  expect(text).toMatch(/уже на складі: 3/);
  expect(units(conc)).toEqual(['01140001', '01140002']);
});
