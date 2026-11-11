// Импорт истории оборудования: номер договора с частью через дефис и с годом четырьмя цифрами.
// Задублированный договор хоспис разбивает на части «12-1/25», «12-2/25» — это отдельные договоры со своими получателями;
// в таблице выдачи часть пишется и без года («12-1»), год — из даты регистрации, как у номера без части.
// Год четырьмя цифрами («01/2026») — тот же договор «1/26». Часть без года в переписи и «акт 7» — по-прежнему ошибки (просто «акт» — выдача по акту, eqhistory-act.spec.js).
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const esc = s => s.replace(/\\/g, '\\\\').replace(/'/g, "''");
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));
const owner = c => value(`select cu.customer_name from contracts c join customers cu on cu.customer_id = c.customer_id where c.contract_number = '${c}'`);

test('импорт истории: договор, разбитый на части, и год четырьмя цифрами', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const no = Number(String(base).slice(-5));
  const who = k => `Подопечный ${uniq}-${String(k).padStart(3, '0')}`;

  const dir = testInfo.outputPath('eqhs');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-split.php'), `${WEB}:${tmp}/make.php`]);
  execFileSync('docker', ['exec', WEB, 'php', `${tmp}/make.php`, tmp, String(base), uniq]);
  require('fs').mkdirSync(dir, { recursive: true });
  for (const f of ['osv-01.xls', 'census.xlsx', 'issues.xlsx']) execFileSync('docker', ['cp', `${WEB}:${tmp}/${f}`, path.join(dir, f)]);

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
    await page.locator('#iform #date').fill('2024-12-01');
    await page.locator('#iform #filename').setInputFiles(path.join(dir, 'osv-01.xls'));
    await clickWait(page, '#iform #import');
    expect(qty(inv(8))).toBe(1);

    const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
    await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    await page.locator('#iform #fixes').fill(`${no + 5}-1/25 = ${who(7)}`);
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
    await page.locator('#iform #journal').setInputFiles(path.join(dir, 'issues.xlsx'));
    await clickWait(page, '#iform #check');

    const errors = await page.locator('#eqh-problems tr.error').allInnerTexts();
    expect(errors.join('\n')).toContain(`Номер договору не розпізнано: ${no + 4}-1`);
    expect(errors.join('\n')).toContain('Номер договору не розпізнано: акт 7');
    expect(errors, errors.join('\n')).toHaveLength(2);
    expect(await stat('issue')).toBe(7);       // шесть строк переписи и строка только в журнале
    expect(await stat('return')).toBe(1);
    expect(await stat('contracts')).toBe(7);

    await clickWait(page, '#iform #write');
    await expect(page.locator('#eqh-summary tr[data-stat=failed]')).toBeVisible({ timeout: 120_000 });
    expect(await stat('failed')).toBe(0);

    expect(owner(`${no}-1/25`)).toBe(who(1));
    expect(owner(`${no}-2/25`)).toBe(who(2));
    expect(owner(`${no + 1}-1/25`)).toBe(who(3));
    expect(owner(`${no + 2}/25`)).toBe(who(4));
    expect(owner(`${no + 3}/25`)).toBe(who(5));
    expect(owner(`${no + 5}-1/25`)).toBe(who(7));    // строка исправлений с номером части
    expect(owner(`${no + 6}-2/25`)).toBe(who(8));
    expect(qty(inv(1))).toBe(1);                      // выдача из переписи и журнала — одна, возвращена
    for (const k of [2, 3, 4, 5, 7, 8]) expect(qty(inv(k)), `единица ${k} выдана`).toBe(0);
    expect(qty(inv(6)), 'строки с ошибкой не перенесены').toBe(1);
  } finally {
    await setActual(actual);
    query("delete from keyval where keyd = 'sqs_eqfix'" + (savedFix.length ? `; insert into keyval (keyd, vald) values ('sqs_eqfix', '${esc(savedFix[0][0])}')` : ''));
  }
});
