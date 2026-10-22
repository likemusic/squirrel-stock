// Импорт истории оборудования: ответы хосписа на сверку таблиц (19.10.2026, вопросы 5.17–5.22).
//  - список исправлений на странице импорта: ФИО по договору, договор с представителем, номер договора, задвоенная строка,
//    верный лист для номера на двух листах; строка, которая ни к чему не подошла, — заметка;
//  - повторная выдача раньше возврата по прошлой: верна дата возврата, выдача сдвигается на нее с примечанием;
//  - инвентарного номера нет в 1С (там списан): единица переносится и списывается;
//  - пальчиковые пульсоксиметры — расходник (позиция «-» в соответствиях): не переносятся, даже с номером.
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const esc = s => s.replace(/\\/g, '\\\\').replace(/'/g, "''");
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));
// документы единицы: «тип дата» по порядку
const docs = sn => query(`select concat(m.meta_name, ' ', d.document_date) from entrylist e join store_stock s on s.stock_id = e.stock_id
                          join documents d on d.document_id = e.document_id join metadata m on m.meta_id = d.meta_id
                          where s.snumber = '${sn}' group by d.document_id order by d.document_date, d.document_id`).map(r => r[0]);
const notes = (sn, meta) => value(`select d.notes from entrylist e join store_stock s on s.stock_id = e.stock_id join documents d on d.document_id = e.document_id
                                   where s.snumber = '${sn}' and d.meta_id = (select meta_id from metadata where meta_name = '${meta}') order by d.document_date desc limit 1`);
const owner = c => value(`select cu.customer_name from contracts c join customers cu on cu.customer_id = c.customer_id where c.contract_number = '${c}'`);
const today = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

test('импорт истории: список исправлений, выдача раньше возврата, номера нет в 1С, расходники', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const fac = (p, k) => p + base + k;
  const cn = k => (Number(String(base).slice(-5)) + k) + '/25';
  const who = k => `Подопечный ${uniq}-${String(k).padStart(3, '0')}`;

  const dir = testInfo.outputPath('eqhf');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-fixes.php'), `${WEB}:${tmp}/make.php`]);
  execFileSync('docker', ['exec', WEB, 'php', `${tmp}/make.php`, tmp, String(base), uniq]);
  require('fs').mkdirSync(dir, { recursive: true });
  for (const f of ['osv-01.xls', 'census.xlsx']) execFileSync('docker', ['cp', `${WEB}:${tmp}/${f}`, path.join(dir, f)]);

  const setActual = async date => {
    await page.goto('/index.php?p=App/Pages/Options');
    const was = await page.locator('#business #actualdate').inputValue();
    await page.locator('#business #actualdate').fill(date);
    await clickWait(page, '#business button[type=submit], #business input[type=submit]');
    return was;
  };
  // соответствия и исправления общие — вернуть после теста
  const savedMap = query('select written, itemname from sqs_eqitemmap order by id');
  const savedFix = query("select vald from keyval where keyd = 'sqs_eqfix'");
  const actual = await setActual('2010-01-01');
  try {
    await page.goto('/index.php?p=App/Pages/Service/Import1C');
    await page.locator('#iform #date').fill('2024-01-01');
    await page.locator('#iform #filename').setInputFiles(path.join(dir, 'osv-01.xls'));
    await clickWait(page, '#iform #import');
    expect(qty(inv(1))).toBe(1);

    const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
    await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    // строка с ошибкой — не сохраняется, файлы не разбираются
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
    await page.locator('#iform #fixes').fill('просто текст = что-то');
    await clickWait(page, '#iform #check');
    await expect(page.locator('.toast-message, .alert-danger').first()).toContainText(/рядки 1 /);
    await expect(page.locator('#eqh-summary')).toHaveCount(0);

    const map = await page.locator('#iform #itemmap').inputValue();
    await page.locator('#iform #itemmap').fill((map.trim() ? map.trim() + '\n' : '') + 'Beurer Medical = -\nпульсоксиметр пальчиковый = -');
    await page.locator('#iform #fixes').fill([
      `${cn(3)} = ${who(3)}`,
      `${cn(4)} ${who(9).toUpperCase()} = ${cn(5)}`,
      `${cn(6)} ${who(10)} = -`,
      `${cn(9)} = ${who(11)}; договор с Представитель ${uniq}-011`,
      `№ ${fac('S', 1)} = эл. отс. В-40+АКБ`,
      `${cn(99)} = Нет такого договора`,
    ].join('\n'));
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
    await clickWait(page, '#iform #check');
    expect(value("select vald from keyval where keyd = 'sqs_eqfix'")).toContain(`№ ${fac('S', 1)} = эл. отс. В-40+АКБ`);

    const errors = await page.locator('#eqh-problems tr.error').allInnerTexts();
    expect(errors).toHaveLength(1);
    expect(errors[0]).toContain(`Інвентарного номера ИС-${base} немає в базі`);
    const noteText = (await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n');
    expect(noteText).toContain('Видача 20.02.2025 раніше повернення за попередньою видачею: дата видачі — 01.03.2025');
    expect(noteText).toContain('Інвентарного номера немає в базі (списано в 1С)');
    expect(noteText).toContain('Номер правильний на аркуші «эл. отс. В-40+АКБ» (виправлення, рядок 5)');
    expect(noteText).toContain('Не переноситься за виправленнями, рядок 3');
    expect(noteText).toContain('Рядок виправлень 6 не підійшов до жодного рядка таблиць');
    expect(await stat('fixed')).toBe(3);        // ФИО, номер договора, представитель
    expect(await stat('consumable')).toBe(2);
    expect(await page.locator('#eqh-consumable tr').allInnerTexts()).toEqual(['Beurer Medical\t1', 'пульсоксиметр пальчиковый\t1']);
    expect(await stat('issue')).toBe(11);
    expect(await stat('return')).toBe(4);       // строки 3, 4, 10 и возврат датой импорта у единицы на руках, списанной в 1С
    expect(await stat('writeoff')).toBe(2);
    expect(await stat('income')).toBe(7);       // F1, F2, F4, P1, S1 и два номера, которых нет в 1С

    await clickWait(page, '#iform #write');
    await expect(page.locator('#eqh-summary tr[data-stat=failed]')).toBeVisible({ timeout: 120_000 });
    expect(await stat('failed')).toBe(0);
    expect(await stat('issue')).toBe(11);

    // 5.19: выдача — в день возврата прошлой, в примечании — дата из таблицы
    expect(docs(inv(1))).toEqual(['IncomeItem 2024-01-01', 'GoodsIssue 2025-02-01', 'ReturnIssue 2025-03-01', 'GoodsIssue 2025-03-01', 'ReturnIssue 2025-04-01']);
    expect(notes(inv(1), 'GoodsIssue')).toContain('Дату видачі виправлено під час перенесення (у таблиці 20.02.2025)');
    // 5.18: один получатель по договору; строка с опечаткой в номере — свой договор; задвоенная — не перенесена
    expect(owner(cn(3))).toBe(who(3));
    expect(value(`select count(*) from customers where customer_name like 'Подопечная ${uniq}%'`)).toBe('0');
    expect(qty(inv(3))).toBe(0);
    expect(owner(cn(4))).toBe(who(4));
    expect(owner(cn(5))).toBe(who(9));
    expect(value(`select count(*) from customers where customer_name = '${who(10)}'`)).toBe('0');
    expect(value(`select count(*) from sqs_unit where factory_no = '${fac('F', 3)}'`)).toBe('0');
    // 5.21: списан в 1С — после возврата списание той же датой; на руках — возврат и списание датой импорта
    expect(docs(inv(6))).toEqual(['IncomeItem 2025-02-28', 'GoodsIssue 2025-03-01', 'ReturnIssue 2025-03-15', 'OutcomeItem 2025-03-15']);
    expect(notes(inv(6), 'OutcomeItem')).toContain('Списано в 1С');
    expect(docs(inv(7))).toEqual(['IncomeItem 2025-03-31', 'GoodsIssue 2025-04-01', `ReturnIssue ${today()}`, `OutcomeItem ${today()}`]);
    expect(qty(inv(6)) + qty(inv(7))).toBe(0);
    // 5.17: «Пульсар» — единица, пальчиковые не перенесены ни единицей, ни количеством
    expect(value(`select count(*) from sqs_unit where factory_no = '${fac('P', 1)}'`)).toBe('1');
    expect(value(`select count(*) from sqs_unit where factory_no = '${fac('P', 2)}'`)).toBe('0');
    expect(value(`select count(*) from customers where customer_name in ('${who(13)}', '${who(14)}')`)).toBe('0');
    // 5.22: одна единица, товар — по верному листу
    expect(query(`select i.itemname from sqs_unit u join items i on i.item_id = u.item_id where u.factory_no = '${fac('S', 1)}'`)).toEqual([['эл. отс. В-40+АКБ']]);
    // договор с представителем: получатель — подопечный, «договор заключен с» — представитель
    expect(owner(cn(9))).toBe(who(11));
    expect(value(`select extractvalue(details, '//sqs_holder') from contracts where contract_number = '${cn(9)}'`)).toBe(`Представитель ${uniq}-011`);
    expect(value(`select count(*) from customers where customer_name like 'Представитель ${uniq}%'`)).toBe('0');
  } finally {
    query('delete from sqs_eqitemmap' + savedMap.map(([w, i]) => `; insert into sqs_eqitemmap (written, itemname) values ('${esc(w)}', '${esc(i)}')`).join(''));
    query("delete from keyval where keyd = 'sqs_eqfix'" + (savedFix.length ? `; insert into keyval (keyd, vald) values ('sqs_eqfix', '${esc(savedFix[0][0])}')` : ''));
    await setActual(actual);
  }
});
