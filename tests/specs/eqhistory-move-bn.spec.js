// Импорт истории оборудования: перенос в договор без номера (ответ хосписа 11.11.2026). Номер договора в строке ошибочный
// (записан у другого получателя), верный не восстановить: строка списка исправлений «договор ФИО = б/н» (и «б\н», «бн»)
// переносит строки договора с этим ФИО в договор без номера — как строку с «б/н» в ячейке договора: «б/н ДД.ММ.ГГГГ»
// датой договора строки, свой на получателя и дату, без дублей при повторном импорте.
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const esc = s => s.replace(/\\/g, '\\\\').replace(/'/g, "''");
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));
const notes = (sn, meta) => value(`select d.notes from entrylist e join store_stock s on s.stock_id = e.stock_id join documents d on d.document_id = e.document_id
                                   where s.snumber = '${sn}' and d.meta_id = (select meta_id from metadata where meta_name = '${meta}') order by d.document_date desc limit 1`);
const owner = c => value(`select cu.customer_name from contracts c join customers cu on cu.customer_id = c.customer_id where c.contract_number = '${c}'`);
const contracts = who => query(`select c.contract_number from contracts c join customers cu on cu.customer_id = c.customer_id where cu.customer_name = '${who}' order by c.contract_number`).map(r => r[0]);

test('импорт истории: перенос в договор без номера по списку исправлений', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const cn = k => (Number(String(base).slice(-5)) + k) + '/25';
  const who = k => `Подопечный ${uniq}-${String(k).padStart(3, '0')}`;
  // даты таблиц — от того же дня, что в make-eqhistory-move-bn.php
  const iso = n => new Date(Date.UTC(2015, 0, 1) + ((base % 2500) + n) * 86400000).toISOString().slice(0, 10);
  const dmy = n => iso(n).split('-').reverse().join('.');

  const dir = testInfo.outputPath('eqhm');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-move-bn.php'), `${WEB}:${tmp}/make.php`]);
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
    expect(qty(inv(3))).toBe(1);

    const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
    const check = async () => {
      await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
      await page.locator('#iform #fixes').fill([`${cn(1)} ${who(2)} = б/н`, `${cn(3)} ${who(3)} = БН`].join('\n'));
      await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
      await clickWait(page, '#iform #check');
    };
    await check();

    const errors = await page.locator('#eqh-problems tr.error').allInnerTexts();
    expect(errors, errors.join('\n')).toHaveLength(0);
    const noteText = (await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n');
    expect(noteText).toContain('Виправлено за списком виправлень, рядок 1');
    expect(noteText).toContain('Виправлено за списком виправлень, рядок 2');
    expect(noteText).toContain(`Договір без номера: у програмі — «б/н ${dmy(5)}»`);
    expect(noteText).toContain(`Договір без номера: у програмі — «б/н ${dmy(5)} (2)»`);
    expect(await stat('issue')).toBe(3);
    expect(await stat('contracts')).toBe(3);

    await clickWait(page, '#iform #write');
    await expect(page.locator('#eqh-summary tr[data-stat=failed]')).toBeVisible({ timeout: 120_000 });
    expect(await stat('failed')).toBe(0);

    expect(contracts(who(1))).toEqual([cn(1)]);
    expect(owner(`б/н ${dmy(5)}`)).toBe(who(2));
    expect(owner(`б/н ${dmy(5)} (2)`)).toBe(who(3));
    expect(contracts(who(3))).toEqual([`б/н ${dmy(5)} (2)`]);
    expect(notes(inv(2), 'GoodsIssue')).toContain('Номер договору невідомий');
    for (const k of [1, 2, 3]) expect(qty(inv(k)), `единица ${k} на руках`).toBe(0);

    // повторная проверка: договоры без номера — те же, ничего не дублируется
    await check();
    expect(await stat('issue')).toBe(0);
    expect(await stat('contracts')).toBe(0);
    expect(await page.locator('#eqh-problems tr.error').count()).toBe(0);
  } finally {
    await setActual(actual);
    query("delete from keyval where keyd = 'sqs_eqfix'" + (savedFix.length ? `; insert into keyval (keyd, vald) values ('sqs_eqfix', '${esc(savedFix[0][0])}')` : ''));
  }
});
