// Импорт истории оборудования: «акт» в ячейке номера договора — выдача по акту без номера (ответ хосписа 11.11.2026). Строка
// переносится договором «акт ДД.ММ.ГГГГ», своим на получателя и дату, как договор без номера «б/н ДД.ММ.ГГГГ» (у другого
// получателя той же даты — «(2)»); в примечании выдачи — «выдано по акту». В исправлениях такой договор пишется, как в отчете.
// «акт» с номером («акт 7») — по-прежнему «номер договора не распознан».
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const esc = s => s.replace(/\\/g, '\\\\').replace(/'/g, "''");
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));
const docs = sn => query(`select concat(m.meta_name, ' ', d.document_date) from entrylist e join store_stock s on s.stock_id = e.stock_id
                          join documents d on d.document_id = e.document_id join metadata m on m.meta_id = d.meta_id
                          where s.snumber = '${sn}' group by d.document_id order by d.document_date, d.document_id`).map(r => r[0]);
const notes = (sn, meta) => value(`select d.notes from entrylist e join store_stock s on s.stock_id = e.stock_id join documents d on d.document_id = e.document_id
                                   where s.snumber = '${sn}' and d.meta_id = (select meta_id from metadata where meta_name = '${meta}') order by d.document_date desc limit 1`);
const owner = c => value(`select cu.customer_name from contracts c join customers cu on cu.customer_id = c.customer_id where c.contract_number = '${c}'`);
const contracts = who => query(`select c.contract_number from contracts c join customers cu on cu.customer_id = c.customer_id where cu.customer_name = '${who}' order by c.contract_number`).map(r => r[0]);

test('импорт истории: выдача по акту без номера', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const who = k => `Подопечный ${uniq}-${String(k).padStart(3, '0')}`;
  // даты таблиц — от того же дня, что в make-eqhistory-act.php
  const iso = n => new Date(Date.UTC(2015, 0, 1) + ((base % 2500) + n) * 86400000).toISOString().slice(0, 10);
  const dmy = n => iso(n).split('-').reverse().join('.');

  const dir = testInfo.outputPath('eqha');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-act.php'), `${WEB}:${tmp}/make.php`]);
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
  const savedFix = query("select vald from keyval where keyd = 'sqs_eqfix'");
  const actual = await setActual('2010-01-01');
  try {
    await page.goto('/index.php?p=App/Pages/Service/Import1C');
    await page.locator('#iform #date').fill('2014-12-01');
    await page.locator('#iform #filename').setInputFiles(path.join(dir, 'osv-01.xls'));
    await clickWait(page, '#iform #import');
    expect(qty(inv(5))).toBe(1);

    const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
    const check = async () => {
      await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
      await page.locator('#iform #fixes').fill(`акт ${dmy(0)} ${who(2)} = возврат без даты`);
      await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
      await clickWait(page, '#iform #check');
    };
    await check();

    const errors = await page.locator('#eqh-problems tr.error').allInnerTexts();
    expect(errors.join('\n')).toContain('Номер договору не розпізнано: акт 7');
    expect(errors, errors.join('\n')).toHaveLength(1);
    const noteText = (await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n');
    for (const c of [`акт ${dmy(0)}`, `акт ${dmy(0)} (2)`, `акт ${dmy(10)}`]) {
      expect(noteText).toContain(`Видача за актом без номера: у програмі — договір «${c}»`);
    }
    expect(noteText).toContain(`Договір без номера: у програмі — «б/н ${dmy(10)}»`);
    expect(noteText).toContain(`Дату повернення не відновлено (виправлення, рядок 1): повернення датою видачі ${dmy(0)}`);
    expect(await stat('issue')).toBe(4);
    expect(await stat('return')).toBe(2);
    expect(await stat('contracts')).toBe(4);

    await clickWait(page, '#iform #write');
    await expect(page.locator('#eqh-summary tr[data-stat=failed]')).toBeVisible({ timeout: 120_000 });
    expect(await stat('failed')).toBe(0);

    expect(owner(`акт ${dmy(0)}`)).toBe(who(1));
    expect(owner(`акт ${dmy(0)} (2)`)).toBe(who(2));
    expect(contracts(who(3))).toEqual([`б/н ${dmy(10)}`, `акт ${dmy(10)}`].sort());
    expect(notes(inv(1), 'GoodsIssue')).toContain('Видано за актом, номер договору невідомий');
    expect(notes(inv(4), 'GoodsIssue')).not.toContain('за актом');
    expect(docs(inv(1)).slice(1)).toEqual([`GoodsIssue ${iso(0)}`, `ReturnIssue ${iso(5)}`]);
    expect(docs(inv(2)).slice(1)).toEqual([`GoodsIssue ${iso(0)}`, `ReturnIssue ${iso(0)}`]);
    expect(qty(inv(3))).toBe(0);
    expect(qty(inv(5)), 'строка с ошибкой, единица на складе').toBe(1);

    // повторная проверка: договоры по акту — те же, ничего не дублируется
    await check();
    expect(await stat('issue')).toBe(0);
    expect(await stat('return')).toBe(0);
    expect(await stat('contracts')).toBe(0);
  } finally {
    await setActual(actual);
    query("delete from keyval where keyd = 'sqs_eqfix'" + (savedFix.length ? `; insert into keyval (keyd, vald) values ('sqs_eqfix', '${esc(savedFix[0][0])}')` : ''));
  }
});
