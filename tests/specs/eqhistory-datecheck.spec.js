// Импорт истории оборудования: одна выдача (единица и договор) в двух строках таблиц с разными датами, а первая к дате второй
// не закрыта возвратом, — ошибка всех таких строк (строки не импортируются). Раньше это были две выдачи, и сдвиг выдачи на
// возврат прошлой (ответ 5.19) с возвратом в день новой (5.33) давали возврат и повторную выдачу тому же получателю.
// Выдача по тому же договору после возврата — по-прежнему две выдачи.
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

test('импорт истории: одна выдача с разными датами — ошибка', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const cn = k => (Number(String(base).slice(-5)) + k) + '/25';

  const dir = testInfo.outputPath('eqhd');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-datecheck.php'), `${WEB}:${tmp}/make.php`]);
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
    expect(qty(inv(4))).toBe(1);

    const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
    await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    await page.locator('#iform #fixes').fill('');
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
    await page.locator('#iform #journal').setInputFiles(path.join(dir, 'issues.xlsx'));
    await clickWait(page, '#iform #check');

    const errors = await page.locator('#eqh-problems tr.error').allInnerTexts();
    const text = errors.join('\n');
    expect(text).toContain(`Одна видача за договором ${cn(4)} записана з різними датами: 10.06.2025 (ИВЛ:4), 11.06.2025 (обычные:3)`);
    expect(text).toContain(`Одна видача за договором ${cn(7)} записана з різними датами: 01.05.2025 (ИВЛ:7), 03.05.2025 (ИВЛ:8)`);
    expect(errors, text).toHaveLength(4);
    const noteText = (await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n');
    expect(noteText).not.toMatch(/раніше повернення за попередньою видачею|Видано повторно без повернення/);
    expect(await stat('issue')).toBe(3);    // 1/(перепись + журнал), 6 дважды
    expect(await stat('return')).toBe(2);

    await clickWait(page, '#iform #write');
    await expect(page.locator('#eqh-summary tr[data-stat=failed]')).toBeVisible({ timeout: 120_000 });
    expect(await stat('failed')).toBe(0);
    expect(docs(inv(1)).slice(1)).toEqual(['GoodsIssue 2025-02-11', 'ReturnIssue 2025-06-26']);
    expect(docs(inv(2)).slice(1)).toEqual(['GoodsIssue 2025-04-01', 'ReturnIssue 2025-04-05', 'GoodsIssue 2025-04-10']);
    expect(qty(inv(1))).toBe(1);
    expect(qty(inv(3)), 'строки с ошибкой не импортированы').toBe(1);
  } finally {
    await setActual(actual);
    query("delete from keyval where keyd = 'sqs_eqfix'" + (savedFix.length ? `; insert into keyval (keyd, vald) values ('sqs_eqfix', '${esc(savedFix[0][0])}')` : ''));
  }
});
