// Импорт истории оборудования: договор без номера и повторная выдача без возврата (вопрос хосписа 5.33, TODO 3.7).
// Номера и даты возврата многих старых договоров восстановить нельзя, и такие строки не переносились вовсе:
//  - номер «б/н» (и «б\н», «бн», без учета регистра и пробелов) или пустая ячейка — договор без номера «б/н ДД.ММ.ГГГГ»,
//    свой на получателя и дату: второй получатель той же даты — «б/н ДД.ММ.ГГГГ (2)»; в примечании — «номер договора неизвестен»;
//  - единица выдана повторно без возврата — прошлая выдача закрывается возвратом в день новой, дата возврата неизвестна;
//  - номер с цифрами не по форме «№/ГГ», строка без даты выдачи, возврат раньше выдачи — по-прежнему ошибки.
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));
const docs = sn => query(`select concat(m.meta_name, ' ', d.document_date) from entrylist e join store_stock s on s.stock_id = e.stock_id
                          join documents d on d.document_id = e.document_id join metadata m on m.meta_id = d.meta_id
                          where s.snumber = '${sn}' group by d.document_id order by d.document_date, d.document_id`).map(r => r[0]);
const notes = (sn, meta) => value(`select d.notes from entrylist e join store_stock s on s.stock_id = e.stock_id join documents d on d.document_id = e.document_id
                                   where s.snumber = '${sn}' and d.meta_id = (select meta_id from metadata where meta_name = '${meta}') order by d.document_date desc limit 1`);
const owner = c => value(`select cu.customer_name from contracts c join customers cu on cu.customer_id = c.customer_id where c.contract_number = '${c}'`);
const contracts = who => query(`select c.contract_number from contracts c join customers cu on cu.customer_id = c.customer_id where cu.customer_name = '${who}' order by c.contract_number`).map(r => r[0]);

test('импорт истории: договор без номера, повторная выдача без возврата', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const cn = k => (Number(String(base).slice(-5)) + k) + '/25';
  const who = k => `Подопечный ${uniq}-${String(k).padStart(3, '0')}`;
  // даты таблиц — от того же дня, что в make-eqhistory-nocontract.php
  const iso = n => new Date(Date.UTC(2015, 0, 1) + ((base % 2500) + n) * 86400000).toISOString().slice(0, 10);
  const dmy = n => iso(n).split('-').reverse().join('.');

  const dir = testInfo.outputPath('eqhn');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-nocontract.php'), `${WEB}:${tmp}/make.php`]);
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
  const actual = await setActual('2010-01-01');
  try {
    await page.goto('/index.php?p=App/Pages/Service/Import1C');
    await page.locator('#iform #date').fill('2014-12-01');
    await page.locator('#iform #filename').setInputFiles(path.join(dir, 'osv-01.xls'));
    await clickWait(page, '#iform #import');
    expect(qty(inv(9))).toBe(1);

    const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
    const check = async () => {
      await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
      await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
      await clickWait(page, '#iform #check');
    };
    await check();

    const errors = await page.locator('#eqh-problems tr.error').allInnerTexts();
    expect(errors.join('\n')).toContain('Номер договору не розпізнано: 12-3');
    expect(errors.join('\n')).toContain('Немає дати');
    expect(errors.join('\n')).toContain('Дата повернення раніше дати видачі');
    expect(errors).toHaveLength(3);
    const noteText = (await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n');
    for (const c of [`б/н ${dmy(0)}`, `б/н ${dmy(0)} (2)`, `б/н ${dmy(10)}`, `б/н ${dmy(20)}`]) {
      expect(noteText).toContain(`Договір без номера: у програмі — «${c}»`);
    }
    expect(noteText).toContain(`Видано повторно без повернення: повернення в день нової видачі (${dmy(40)})`);
    expect(await stat('issue')).toBe(7);       // строки 3–7 и обе выдачи единицы 7
    expect(await stat('return')).toBe(2);      // строка 3 и возврат прошлой выдачи единицы 7
    expect(await stat('contracts')).toBe(6);   // четыре без номера и два с номером

    await clickWait(page, '#iform #write');
    await expect(page.locator('#eqh-summary tr[data-stat=failed]')).toBeVisible({ timeout: 120_000 });
    expect(await stat('failed')).toBe(0);

    // свой договор на получателя и дату: два получателя одного дня — разные договоры, две единицы одного получателя
    // в один день — один договор; номер — в списке договоров и в карточке получателя, как у договоров с номером
    expect(owner(`б/н ${dmy(0)}`)).toBe(who(1));
    expect(owner(`б/н ${dmy(0)} (2)`)).toBe(who(2));
    expect(contracts(who(1)).sort()).toEqual([`б/н ${dmy(0)}`, `б/н ${dmy(20)}`].sort());
    expect(contracts(who(3))).toEqual([`б/н ${dmy(10)}`]);
    expect(qty(inv(3)) + qty(inv(4))).toBe(0);
    expect(contracts(who(5))).toEqual([cn(1)]);   // договоры с номером — со своим номером
    expect(contracts(who(6))).toEqual([cn(2)]);
    expect(notes(inv(1), 'GoodsIssue')).toContain('Номер договору невідомий');
    expect(docs(inv(1)).slice(1)).toEqual([`GoodsIssue ${iso(0)}`, `ReturnIssue ${iso(5)}`]);
    // повторная выдача: возврат прошлой в день новой, затем новая выдача
    expect(docs(inv(7)).slice(1)).toEqual([`GoodsIssue ${iso(30)}`, `ReturnIssue ${iso(40)}`, `GoodsIssue ${iso(40)}`]);
    expect(notes(inv(7), 'ReturnIssue')).toContain('Дата повернення невідома');
    expect(qty(inv(7))).toBe(0);
    for (const k of [6, 8, 9]) expect(qty(inv(k)), `строка с ошибкой, единица ${k} на складе`).toBe(1);

    // ФИО поправили в программе; повторная проверка: договор без номера — тот же, ничего не дублируется
    query(`update customers set customer_name = '${who(1)} испр.' where customer_name = '${who(1)}'`);
    await check();
    expect(await stat('issue')).toBe(0);
    expect(await stat('return')).toBe(0);
    expect(await stat('contracts')).toBe(0);
    expect(await page.locator('#eqh-problems tr.error').count()).toBe(3);
  } finally {
    await setActual(actual);
  }
});
