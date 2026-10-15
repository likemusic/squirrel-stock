// Импорт истории выдачи оборудования из Google-таблиц хосписа (перепись и журнал, .xlsx), ZIPPY_GAPS п. 28.
// Таблицы вымышленные, той же раскладки (tests/fixtures/make-eqhistory.php), номера — свои на каждый прогон.
// Проверяется: «Проверить» ничего не пишет и показывает ошибки с причиной; «Записать» создает выдачи, возвраты, списания
// и приход единицы только с заводским номером; одна выдача из двух таблиц склеивается; повторный импорт не дублирует.
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));
const lastDoc = sn => value(`select m.meta_name from entrylist e join store_stock s on s.stock_id = e.stock_id join documents d on d.document_id = e.document_id
                             join metadata m on m.meta_id = d.meta_id where s.snumber = '${sn}' order by d.document_date desc, d.document_id desc limit 1`);
// [состояние, кем направлен, получатель]; «кем направлен» — краткое описание договора (в XML details)
const contract = n => query(`select c.state, extractvalue(c.details, '//shortdesc'), cu.customer_name from contracts c join customers cu on cu.customer_id = c.customer_id where c.contract_number = '${n}'`)[0];

test('импорт истории оборудования в меню сервиса', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('a[href*="Service/ImportEquipmentHistory"]').first(), 'пункт меню (db/squirrelstock.sql)').toBeAttached();
});

test('импорт истории оборудования: проверка, запись, повтор без дублей', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const cn = (k, yy) => (Number(String(base).slice(-5)) + k) + '/' + yy;
  const who = k => `Подопечный ${uniq}-${String(k).padStart(3, '0')}`;

  const dir = testInfo.outputPath('eqh');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory.php'), `${WEB}:${tmp}/make.php`]);
  execFileSync('docker', ['exec', WEB, 'php', `${tmp}/make.php`, tmp, String(base), uniq]);
  require('fs').mkdirSync(dir, { recursive: true });
  for (const f of ['osv-01.xls', 'perepis.xlsx', 'zhurnal.xlsx']) execFileSync('docker', ['cp', `${WEB}:${tmp}/${f}`, path.join(dir, f)]);

  // начало актуального периода (настройки): документы раньше него ядро не сохраняет, а история — с 2015 года
  const setActual = async date => {
    await page.goto('/index.php?p=App/Pages/Options');
    const was = await page.locator('#business #actualdate').inputValue();
    await page.locator('#business #actualdate').fill(date);
    await clickWait(page, '#business button[type=submit], #business input[type=submit]');
    return was;
  };
  const actual = await setActual('2010-01-01');
  try {
  // единицы с инвентарными номерами — в базе до импорта истории (ведомость 1С, счет 01)
  await page.goto('/index.php?p=App/Pages/Service/Import1C');
  await page.locator('#iform #date').fill('2024-01-01');
  await page.locator('#iform #filename').setInputFiles(path.join(dir, 'osv-01.xls'));
  await clickWait(page, '#iform #import');
  expect(qty(inv(1))).toBe(1);

  const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
  const run = async button => {
    const resp = await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    expect(resp.status()).toBe(200);
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'perepis.xlsx'));
    await page.locator('#iform #journal').setInputFiles(path.join(dir, 'zhurnal.xlsx'));
    await clickWait(page, '#iform #check');
    if (button == 'write') await clickWait(page, '#iform #write');
  };

  // актуальный период начинается позже первого документа импорта — записать нельзя, причина в отчете
  await setActual('2025-01-01');
  await run('check');
  expect((await page.locator('#eqh-problems tr.error').allInnerTexts()).join('\n')).toMatch(/до початку актуального періоду .*01\.01\.2025.* від 05\.05\.2024/);
  await expect(page.locator('#iform #write')).toHaveCount(0);
  await setActual('2010-01-01');

  // проверка: ничего не записано, план и ошибки с причиной
  await run('check');
  expect(await stat('issue')).toBe(5);       // единицы 1, 2, 3, 5 и кровать с заводским номером
  expect(await stat('merged')).toBe(1);      // единица 1 — в переписи и в журнале
  expect(await stat('income')).toBe(1);      // кровать: только заводской номер
  expect(await stat('return')).toBe(2);      // 1 — возврат; 3 — возврат перед списанием
  expect(await stat('writeoff')).toBe(2);    // 3 — «списан» с датой; 6 — лист «СПИСАНО»
  expect(await stat('errors')).toBe(5);
  const problems = (await page.locator('#eqh-problems tr.error').allInnerTexts()).join('\n');
  expect(problems).toMatch(/Дату не розпізнано: весна/);
  expect(problems).toContain(`Інвентарного номера ${inv(99)} немає в базі`);
  expect(problems).toMatch(/Повернення без дати/);
  expect(problems).toMatch(/Кілька одиниць в одній клітинці/);
  expect(problems).toContain(`Номера XX-${uniq} немає ні в базі, ні в переписі`);
  const notes = (await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n');
  expect(notes).toMatch(/Стан отримувача «умер»/);
  expect(notes).toMatch(/Дата видачі = дата договору/);
  expect(await page.locator('#eqh-missing').innerText()).toContain(inv(99));
  expect(await page.locator('#eqh-skipped').innerText()).toMatch(/кровати\s+мешок Амбу\s+1/);
  expect(await page.locator('#eqh-unitonly').innerText()).toMatch(/кровати\s+1/);
  expect(qty(inv(2))).toBe(1);
  expect(value(`select count(*) from contracts where contract_number = '${cn(2, '25')}'`)).toBe('0');

  // запись
  await clickWait(page, '#iform #write');
  expect(await stat('failed')).toBe(0);
  expect(qty(inv(1))).toBe(1);                       // выдана и возвращена
  expect(contract(cn(1, '25'))).toEqual(['8', 'Поликлиника тестовая', who(1)]);   // закрыт; «кем направлен» — из журнала
  expect(qty(inv(2))).toBe(0);                       // у получателя, договор в работе
  expect(contract(cn(2, '25'))[0]).toBe('6');
  expect(qty(inv(3))).toBe(0);
  expect(lastDoc(inv(3))).toBe('OutcomeItem');
  expect(contract(cn(3, '24'))[0]).toBe('8');
  expect(qty(inv(5))).toBe(0);
  expect(contract(cn(5, '25'))).toEqual(['6', 'Врач тестовый', who(5)]);   // номер договора журнала — «№/ГГ»
  expect(qty(inv(6))).toBe(0);
  expect(lastDoc(inv(6))).toBe('OutcomeItem');
  expect(value(`select i.itemname from store_stock s join items i on i.item_id = s.item_id where s.snumber = 'KR-${uniq}'`)).toBe('кровати');
  expect(qty(`KR-${uniq}`)).toBe(0);
  expect(value(`select phone from customers where customer_name = '${who(1)}'`)).toBe('375291110001');
  // документы — в хронологическом порядке: выдача единицы 3 (2024) раньше ее возврата (2025)
  expect(value(`select date(d.document_date) from documents d join entrylist e on e.document_id = d.document_id join store_stock s on s.stock_id = e.stock_id
                where s.snumber = '${inv(3)}' and d.meta_id = (select meta_id from metadata where meta_name = 'GoodsIssue')`)).toBe('2024-05-05');

  // повторный запуск: все уже импортировано
  await run('check');
  for (const k of ['income', 'issue', 'return', 'writeoff']) expect(await stat(k)).toBe(0);
  expect(await stat('done')).toBe(9);   // выдачи, возвраты, списания; приход кровати не нужен — она уже в базе
  await expect(page.locator('#iform #write')).toHaveCount(0);
  } finally {
    await setActual(actual);
  }
});
