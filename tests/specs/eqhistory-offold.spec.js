// Импорт истории оборудования: единица, номера которой нет ни в базе, ни в пределах номеров ведомости 1С, — списана до
// ведомости. Если по таблицам она не на руках (последняя выдача возвращена или в строке «списан»), заводится и списывается,
// как единица, списанная в 1С (5.21); на руках без «списан» — ошибка. Дата в колонке инвентарного номера — по-прежнему «нет в базе».
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const esc = s => s.replace(/\\/g, '\\\\').replace(/'/g, "''");
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));
const stocks = sn => Number(value(`select count(*) from store_stock where snumber = '${sn}'`));
const notes = (sn, meta) => value(`select d.notes from entrylist e join store_stock s on s.stock_id = e.stock_id join documents d on d.document_id = e.document_id
                                   where s.snumber = '${sn}' and d.meta_id = (select meta_id from metadata where meta_name = '${meta}') order by d.document_date desc limit 1`);
const offDate = sn => value(`select d.document_date from entrylist e join store_stock s on s.stock_id = e.stock_id join documents d on d.document_id = e.document_id
                             where s.snumber = '${sn}' and d.meta_id = (select meta_id from metadata where meta_name = 'OutcomeItem') limit 1`);

test('импорт истории: единица, списанная до ведомости 1С', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const no = Number(String(base).slice(-5));

  const dir = testInfo.outputPath('eqho');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-offold.php'), `${WEB}:${tmp}/make.php`]);
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
    await page.locator('#iform #date').fill('2024-12-01');
    await page.locator('#iform #filename').setInputFiles(path.join(dir, 'osv-01.xls'));
    await clickWait(page, '#iform #import');
    expect(qty(inv(3))).toBe(1);

    const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
    await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    await page.locator('#iform #fixes').fill('');
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
    await clickWait(page, '#iform #check');

    const errors = await page.locator('#eqh-problems tr.error').allInnerTexts();
    expect(errors.join('\n')).toContain(`Інвентарного номера 6${no} немає в базі, а одиниця за таблицями на руках`);
    expect(errors.join('\n')).toContain('Інвентарного номера 12.03.2024 немає в базі');
    expect(errors, errors.join('\n')).toHaveLength(2);
    const noteText = (await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n');
    expect(noteText.match(/немає ні в базі, ні в межах номерів відомості 1С/g), noteText).toHaveLength(3);
    expect(noteText).toContain('Інвентарного номера немає в базі (списано в 1С)');
    const missing = await page.locator('#eqh-missing').innerText();
    expect(missing).toContain(`6${no}`);
    expect(missing).not.toContain(`7${no}`);
    expect(await stat('issue')).toBe(5);
    expect(await stat('return')).toBe(5);     // 1, 2 в день следующей выдачи, 3, 4 и 7 датой импорта
    expect(await stat('writeoff')).toBe(4);   // 1, 3, 4 и 7

    await clickWait(page, '#iform #write');
    await expect(page.locator('#eqh-summary tr[data-stat=failed]')).toBeVisible({ timeout: 120_000 });
    expect(await stat('failed')).toBe(0);

    for (const sn of [inv(50), `7${no}`, `8${no}`, inv(2)]) {
      expect(stocks(sn), `единица ${sn} заведена`).toBe(1);
      expect(qty(sn), `единица ${sn} списана`).toBe(0);
    }
    expect(notes(inv(50), 'OutcomeItem')).toContain('Списано до відомості 1С');
    expect(String(offDate(inv(50))).slice(0, 10)).toBe('2025-02-20');     // датой возврата
    expect(String(offDate(`7${no}`)).slice(0, 10)).toBe('2025-04-01');
    expect(notes(inv(2), 'OutcomeItem')).toContain('Списано в 1С');
    expect(stocks(`6${no}`), 'единица на руках не заведена').toBe(0);
  } finally {
    await setActual(actual);
    query("delete from keyval where keyd = 'sqs_eqfix'" + (savedFix.length ? `; insert into keyval (keyd, vald) values ('sqs_eqfix', '${esc(savedFix[0][0])}')` : ''));
  }
});
