// Импорт истории выдачи оборудования из Google-таблиц хосписа (перепись и журнал, .xlsx), ZIPPY_GAPS п. 28.
// Таблицы вымышленные, той же раскладки (tests/fixtures/make-eqhistory.php), номера — свои на каждый прогон.
// Проверяется: «Проверить» ничего не пишет и показывает ошибки с причиной; «Записать» создает выдачи, возвраты, списания
// и приход единицы только с заводским номером; одна выдача из двух таблиц склеивается; повторный импорт не дублирует;
// мелочь без номеров — на позицию по соответствиям «написание → позиция», неизвестное написание — ошибка без записи;
// оборудование с инвентарными номерами без номера (ответ хосписа 5.12) — не мелочь, а список для ручного ввода номера.
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait, pickAuto } = require('../helpers/zippy');
const { query, value, esc } = require('../helpers/db');
const { webPhp } = require('../helpers/stand');

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
  const savedMap = query('select written, itemname from sqs_eqitemmap order by id');   // справочник общий — вернуть после теста
  try {
  // единицы с инвентарными номерами — в базе до импорта истории (ведомость 1С, счет 01)
  await page.goto('/index.php?p=App/Pages/Service/Import1C');
  await page.locator('#iform #date').fill('2024-01-01');
  await page.locator('#iform #filename').setInputFiles(path.join(dir, 'osv-01.xls'));
  await clickWait(page, '#iform #import');
  expect(qty(inv(1))).toBe(1);

  const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
  const run = async (button, map) => {
    const resp = await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    expect(resp.status()).toBe(200);
    if (map !== undefined) await page.locator('#iform #itemmap').fill(map);
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'perepis.xlsx'));
    await page.locator('#iform #journal').setInputFiles(path.join(dir, 'zhurnal.xlsx'));
    await clickWait(page, '#iform #check');
    if (button == 'write') await clickWait(page, '#iform #write');
  };

  // актуальный период начинается позже первого документа импорта (как будет у хосписа: история старше)
  await setActual('2025-01-01');

  // соответствия наименований мелочи: строка не в виде «написание = позиция» — не сохраняются, файлы не разбираются
  await run('check', 'Амбу 680 мл. = Мешок Амбу 680 мл\nпросто текст');
  await expect(page.locator('.toast-message, .alert-danger').first()).toContainText(/рядки 2 /);
  await expect(page.locator('#eqh-summary')).toHaveCount(0);

  // проверка: ничего не записано, план и ошибки с причиной. Соответствия: «Амбу 680 мл.» (наименование в строке листа
  // «мешки Амбу») — «Мешок Амбу 680 мл»; «Ингалят.детский» — «Ингалятор детский», а «ингалятор детский» совпадает
  // с позицией и своей строки не требует; «кровати» (строка листа без наименования) — позиция со «*», по номерам
  await run('check', 'Амбу 680 мл. = Мешок Амбу 680 мл\n# комментарий\nингалят . детский = Ингалятор детский\nматрас к кровати = Матрас медицинский'
    + '\nкровати = Кровать функциональная *\nконцентратор = Концентратор кислорода ЭИ тестовый');
  expect(query('select written, itemname from sqs_eqitemmap order by id')).toEqual([['Амбу 680 мл.', 'Мешок Амбу 680 мл'], ['ингалят . детский', 'Ингалятор детский'],
    ['матрас к кровати', 'Матрас медицинский'], ['кровати', 'Кровать функциональная *'], ['концентратор', 'Концентратор кислорода ЭИ тестовый']]);
  await expect(page.locator('#iform #itemmap')).toHaveValue(/\nкровати = Кровать функциональная \*\n/);
  // выдачи: единицы 1, 2, 3, 5 (дважды), 8, 9, кровать с заводским номером; без номеров — мешок Амбу, два ингалятора, матрас
  expect(await stat('issue')).toBe(12);
  expect(await stat('merged')).toBe(2);      // единица 1 и мешок Амбу — в переписи и в журнале
  expect(await stat('income')).toBe(5);      // кровать (только заводской номер), мешок Амбу, ингаляторы и матрас (количеством)
  expect(await stat('return')).toBe(5);      // 1; 3 перед списанием; 5 накануне новой выдачи; 8 и 9 — датой импорта
  expect(await stat('writeoff')).toBe(4);    // 3 «списан» с датой; 9 «списан» без даты; 6 и 10 — лист «СПИСАНО»
  expect(await stat('noinv')).toBe(3);       // кровать в переписи и в журнале, концентратор в журнале
  expect(await stat('errors')).toBe(9);      // 4 по строкам + аспиратор (нет в соответствиях) + 3 без инв. номера + закрытый период
  const problems = (await page.locator('#eqh-problems tr.error').allInnerTexts()).join('\n');
  expect(problems).toMatch(/до початку актуального періоду .*01\.01\.2025.* від 05\.05\.2024/);
  expect(problems).toMatch(/Дату не розпізнано: весна/);
  expect(problems).toContain(`Інвентарного номера ${inv(99)} немає в базі`);
  expect(problems).toMatch(/Кілька одиниць в одній клітинці/);
  expect(problems).toContain(`Номера XX-${uniq} немає ні в базі, ні в переписі`);
  expect(problems).toContain('Немає у відповідностях найменувань: «аспиратор тестовый»');
  expect(await page.locator('#eqh-unmapped').innerText()).toMatch(/^аспиратор тестовый\s+1$/);
  // без инвентарного номера: кому, что, когда, договор — для ручного ввода номера
  const noinv = page.locator('#eqh-noinv tr');
  await expect(noinv).toHaveCount(4);
  const cells = async k => (await noinv.filter({ hasText: who(k) }).locator('td').allInnerTexts()).map(t => t.trim());
  expect(await cells(22)).toEqual(['кровати', expect.any(String), cn(22, '25'), '10.06.2025', who(22), 'кровати', 'Кровать функциональная']);
  expect((await cells(24)).slice(3)).toEqual(['12.06.2025', who(24), 'кровать функциональная', 'Кровать функциональная']);
  expect((await cells(25)).slice(5)).toEqual(['концентратор', 'Концентратор кислорода ЭИ тестовый']);
  expect(problems).toContain('Немає інвентарного номера, а позиція «Кровать функциональная» обліковується за номерами');
  // кнопка дописывает неизвестное написание в поле соответствий (сохранится при следующей проверке)
  await page.locator('#eqh-unmapped-add').click();
  expect(await page.locator('#iform #itemmap').inputValue()).toMatch(/концентратор = Концентратор кислорода ЭИ тестовый\nаспиратор тестовый = аспиратор тестовый$/);
  const notes = (await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n');
  expect(notes).toMatch(/Стан отримувача «умер»/);
  expect(notes).toMatch(/Дата видачі = дата договору/);
  // дата импорта — как ее считает программа (часовой пояс из config.php), а не по часам этого компьютера или базы
  const todayIso = webPhp('-r', "include 'config/config.php'; date_default_timezone_set($_config['common']['timezone']); echo date('Y-m-d');").trim();
  const today = todayIso.split('-').reverse().join('.');
  expect(notes).toContain('Повернення без дати: напередодні наступної видачі (02.04.2025)');
  expect(notes).toContain(`Повернення без дати: датою імпорту (${today})`);
  expect(notes).toContain(`Списання без дати: датою імпорту (${today})`);
  expect(notes).not.toMatch(/подарили/);    // подаренное — обычная выдача, без заметки
  expect(await page.locator('#eqh-missing').innerText()).toContain(inv(99));
  expect(await page.locator('#eqh-skipped').innerText()).toMatch(/мешки Амбу\s+мешок Амбу\s+1/);   // возвращенный — пропуск
  const onhand = await page.locator('#eqh-onhand').innerText();
  expect(onhand).toMatch(/Мешок Амбу 680 мл\s+1/);
  expect(onhand).toMatch(/Матрас медицинский\s+1/);
  expect(onhand).not.toMatch(/Кровать|Концентратор/);
  expect(onhand).toMatch(/Ингалятор детский\s+2/);
  expect(onhand).not.toMatch(/Ингалят\.|аспиратор/i);
  expect(await page.locator('#eqh-unitonly').innerText()).toMatch(/кровати\s+1/);
  expect(qty(inv(2))).toBe(1);
  expect(value(`select count(*) from contracts where contract_number = '${cn(2, '25')}'`)).toBe('0');

  // запись без галочки: отказ из-за закрытого периода, ничего не записано. Сначала заново, без строки от кнопки:
  // запись сохраняет поле соответствий, и аспиратор завелся бы позицией «как написано»
  await run('check');
  await clickWait(page, '#iform #write');
  await expect(page.locator('.toast-message, .alert-danger').first()).toContainText(/до початку актуального періоду/);
  expect(value(`select count(*) from contracts where contract_number = '${cn(2, '25')}'`)).toBe('0');
  expect(qty(inv(2))).toBe(1);

  // запись с галочкой: период снят на время записи и возвращен
  await run('check');
  await page.locator('#iform #liftperiod').check();
  await clickWait(page, '#iform #write');
  expect(await stat('failed')).toBe(0);
  expect(await page.locator('#eqh-period').innerText()).toMatch(/був 01\.01\.2025, на час запису 05\.05\.2024, повернуто 01\.01\.2025/);
  await page.goto('/index.php?p=App/Pages/Options');
  expect(await page.locator('#business #actualdate').inputValue()).toBe('2025-01-01');
  expect(qty(inv(1))).toBe(1);                       // выдана и возвращена
  expect(contract(cn(1, '25'))).toEqual(['8', 'Поликлиника тестовая', who(1)]);   // закрыт; «кем направлен» — из журнала
  expect(qty(inv(2))).toBe(0);                       // у получателя, договор в работе
  expect(contract(cn(2, '25'))[0]).toBe('6');
  expect(qty(inv(3))).toBe(0);
  expect(lastDoc(inv(3))).toBe('OutcomeItem');
  expect(contract(cn(3, '24'))[0]).toBe('8');
  expect(qty(inv(5))).toBe(0);
  expect(contract(cn(5, '25'))).toEqual(['6', 'Врач тестовый', who(5)]);   // номер договора журнала — «№/ГГ»
  expect(contract(cn(8, '25'))[0]).toBe('8');        // «вернули» без даты: возврат накануне следующей выдачи, договор закрыт
  expect(qty(inv(8))).toBe(1);                       // возврат датой импорта с примечанием
  expect(value(`select concat(date(d.document_date), '|', d.notes like '%Дата повернення невідома%') from documents d join metadata m on m.meta_id = d.meta_id
                join entrylist e on e.document_id = d.document_id join store_stock s on s.stock_id = e.stock_id
                where m.meta_name = 'ReturnIssue' and s.snumber = '${inv(8)}'`)).toBe(`${todayIso}|1`);
  expect(qty(inv(9))).toBe(0);                       // «списан» без даты: возврат и списание датой импорта
  expect(lastDoc(inv(9))).toBe('OutcomeItem');
  expect(qty(inv(10))).toBe(0);                      // лист «СПИСАНО» без даты
  expect(lastDoc(inv(10))).toBe('OutcomeItem');
  // подаренное — обычная выдача, слово в примечании документа
  expect(value(`select d.notes like '%подарили%' from documents d join customers c on c.customer_id = d.customer_id where c.customer_name = '${who(1)}' and d.meta_id = (select meta_id from metadata where meta_name = 'GoodsIssue')`)).toBe('1');
  // мелочь без номеров — у получателя количеством, позиция без серийного учета
  expect(value(`select concat(detail like '%<useserial>1</useserial>%', '|', coalesce((select sum(qty) from store_stock s where s.item_id = items.item_id), 0)) from items where itemname = 'Мешок Амбу 680 мл'`)).toMatch(/^0\|/);
  // без инвентарного номера ничего не заведено: ни позиции «количеством», ни получателей
  // (позиции с учетом по номерам с такими названиями на стенде бывают от других тестов — считаются только «количеством»)
  expect(value("select count(*) from items where (itemname like 'Кровать функциональная%' or itemname like 'Концентратор кислорода ЭИ тестовый (%') and detail not like '%<useserial>1</useserial>%'")).toBe('0');
  expect(value(`select count(*) from customers where customer_name in ('${who(22)}', '${who(24)}', '${who(25)}')`)).toBe('0');
  expect(qty(inv(6))).toBe(0);
  expect(lastDoc(inv(6))).toBe('OutcomeItem');
  expect(value(`select i.itemname from store_stock s join items i on i.item_id = s.item_id where s.snumber = 'KR1-${uniq}'`)).toBe('кровати');
  expect(qty(`KR1-${uniq}`)).toBe(0);
  expect(value(`select phone from customers where customer_name = '${who(1)}'`)).toBe('375291110001');
  // документы — в хронологическом порядке: выдача единицы 3 (2024) раньше ее возврата (2025)
  expect(value(`select date(d.document_date) from documents d join entrylist e on e.document_id = d.document_id join store_stock s on s.stock_id = e.stock_id
                where s.snumber = '${inv(3)}' and d.meta_id = (select meta_id from metadata where meta_name = 'GoodsIssue')`)).toBe('2024-05-05');

  // повторный запуск: все уже импортировано
  await run('check');
  for (const k of ['income', 'issue', 'return', 'writeoff']) expect(await stat(k)).toBe(0);
  expect(await stat('done')).toBe(25);   // все события, кроме прихода кровати — она уже в базе; события «датой импорта» — тоже
  await expect(page.locator('#iform #write')).toHaveCount(0);

  // мелочь на руках видна в «Обороте по позициям» по получателю
  await page.goto('/index.php?p=App/Pages/Report/ItemTurnover');
  await page.locator('#filter #from').fill('2025-01-01');
  await pickAuto(page, '#filter #cust', who(16), who(16));
  await clickWait(page, '#filter #onreport');
  expect((await page.locator('body').innerText()).replace(/\s+/g, ' ')).toMatch(/Мешок Амбу 680 мл/);
  // оба написания ингалятора — на одной позиции; аспиратор (нет в соответствиях) не записан: ни позиции, ни получателя
  expect(value(`select group_concat(distinct i.itemname) from documents d join customers c on c.customer_id = d.customer_id join entrylist e on e.document_id = d.document_id
                join store_stock s on s.stock_id = e.stock_id join items i on i.item_id = s.item_id where c.customer_name in ('${who(19)}', '${who(20)}')`)).toBe('Ингалятор детский');
  expect(value("select count(*) from items where itemname like 'Ингалят.%'")).toBe('0');
  expect(value(`select count(*) from customers where customer_name = '${who(21)}'`)).toBe('0');
  } finally {
    await setActual(actual);
    query('delete from sqs_eqitemmap' + savedMap.map(([w, i]) => `; insert into sqs_eqitemmap (written, itemname) values ('${esc(w)}', '${esc(i)}')`).join(''));
  }
});
