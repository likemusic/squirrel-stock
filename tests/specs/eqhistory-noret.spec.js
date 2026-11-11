// Импорт истории оборудования: возврат, дата которого не восстанавливается (решение 11.11.2026). Дата возврата раньше
// выдачи (в том числе после сдвига выдачи на возврат прошлой) — ошибка; строка списка исправлений «договор ФИО = возврат
// без даты» проводит возврат датой выдачи, с заметкой в отчете и «дата возврата не восстановлена» в примечании возврата.
// Договор без номера пишется в исправлениях номером из отчета: «б/н ДД.ММ.ГГГГ ФИО = возврат без даты».
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
const notes = (sn, meta) => query(`select d.notes from entrylist e join store_stock s on s.stock_id = e.stock_id join documents d on d.document_id = e.document_id
                                   where s.snumber = '${sn}' and d.meta_id = (select meta_id from metadata where meta_name = '${meta}') order by d.document_date, d.document_id`).map(r => r[0]);
const owner = c => value(`select cu.customer_name from contracts c join customers cu on cu.customer_id = c.customer_id where c.contract_number = '${c}'`);

test('импорт истории: возврат без даты по списку исправлений', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const cn = k => (Number(String(base).slice(-5)) + k) + '/25';
  const who = k => `Подопечный ${uniq}-${String(k).padStart(3, '0')}`;
  // даты таблиц — от того же дня, что в make-eqhistory-noret.php
  const iso = n => new Date(Date.UTC(2015, 0, 1) + ((base % 2500) + n) * 86400000).toISOString().slice(0, 10);
  const dmy = n => iso(n).split('-').reverse().join('.');

  const dir = testInfo.outputPath('eqhr');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-noret.php'), `${WEB}:${tmp}/make.php`]);
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
    await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    await page.locator('#iform #fixes').fill([
      `${cn(1)} ${who(1)} = возврат без даты`,
      `${cn(3)} ${who(3).toUpperCase()} = повернення без дати`,
      `б/н ${dmy(40)} ${who(4)} = Возврат без даты`,
      `${cn(6)} ${who(6)} = возврат без даты`,
    ].join('\n'));
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
    await clickWait(page, '#iform #check');

    const errors = await page.locator('#eqh-problems tr.error').allInnerTexts();
    expect(errors.join('\n')).toContain('Дата повернення раніше дати видачі');
    expect(errors, errors.join('\n')).toHaveLength(1);
    const noteText = (await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n');
    expect(noteText).toContain(`Дату повернення не відновлено (виправлення, рядок 1): повернення датою видачі ${dmy(0)}`);
    expect(noteText).toContain(`Дату повернення не відновлено (виправлення, рядок 2): повернення датою видачі ${dmy(30)}`);
    expect(noteText).toContain(`Дату повернення не відновлено (виправлення, рядок 3): повернення датою видачі ${dmy(40)}`);
    expect(noteText).toContain('Рядок виправлень 4: дата повернення не раніше видачі, виправлення не потрібне');
    expect(noteText).not.toContain('ні до жодного рядка');
    expect(await stat('issue')).toBe(5);
    expect(await stat('return')).toBe(5);

    await clickWait(page, '#iform #write');
    await expect(page.locator('#eqh-summary tr[data-stat=failed]')).toBeVisible({ timeout: 120_000 });
    expect(await stat('failed')).toBe(0);

    expect(docs(inv(1)).slice(1)).toEqual([`GoodsIssue ${iso(0)}`, `ReturnIssue ${iso(0)}`]);
    expect(notes(inv(1), 'ReturnIssue')[0]).toContain('Дату повернення не відновлено');
    // вторая выдача — в день возврата первой, ее возврат — тем же днем
    expect(docs(inv(2)).slice(1)).toEqual([`GoodsIssue ${iso(10)}`, `ReturnIssue ${iso(30)}`, `GoodsIssue ${iso(30)}`, `ReturnIssue ${iso(30)}`]);
    expect(notes(inv(2), 'ReturnIssue')[0]).not.toContain('Дату повернення не відновлено');
    expect(notes(inv(2), 'ReturnIssue')[1]).toContain('Дату повернення не відновлено');
    expect(owner(`б/н ${dmy(40)}`)).toBe(who(4));
    expect(docs(inv(3)).slice(1)).toEqual([`GoodsIssue ${iso(40)}`, `ReturnIssue ${iso(40)}`]);
    expect(notes(inv(5), 'ReturnIssue')[0]).not.toContain('Дату повернення не відновлено');
    for (const k of [1, 2, 3, 4, 5]) expect(qty(inv(k)), `единица ${k} на складе`).toBe(1);
  } finally {
    await setActual(actual);
    query("delete from keyval where keyd = 'sqs_eqfix'" + (savedFix.length ? `; insert into keyval (keyd, vald) values ('sqs_eqfix', '${esc(savedFix[0][0])}')` : ''));
  }
});
