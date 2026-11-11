// Импорт истории оборудования: инвентарный номер «б/н» (и «б\н», «бн») в ячейке номера — номера нет (ответ хосписа 11.11.2026:
// номер не восстановить или не присваивается). Строка идет путем строки без номера: мелочь — по соответствиям наименований,
// позиция со «*» — в список «без инвентарного номера»; ошибки «номера б/н нет ни в базе, ни в переписи» нет.
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const esc = s => s.replace(/\\/g, '\\\\').replace(/'/g, "''");

test('импорт истории: инвентарный номер «б/н»', async ({ page, uniq }, testInfo) => {
  test.setTimeout(3 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const who = k => `Подопечный ${uniq}-${String(k).padStart(3, '0')}`;

  const dir = testInfo.outputPath('eqhb');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-bn.php'), `${WEB}:${tmp}/make.php`]);
  execFileSync('docker', ['exec', WEB, 'php', `${tmp}/make.php`, tmp, String(base), uniq]);
  require('fs').mkdirSync(dir, { recursive: true });
  execFileSync('docker', ['cp', `${WEB}:${tmp}/issues.xlsx`, path.join(dir, 'issues.xlsx')]);

  // соответствия и исправления общие — вернуть после теста
  const savedMap = query('select written, itemname from sqs_eqitemmap order by id');
  const savedFix = query("select vald from keyval where keyd = 'sqs_eqfix'");
  try {
    await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    await page.locator('#iform #fixes').fill('');
    const map = await page.locator('#iform #itemmap').inputValue();
    await page.locator('#iform #itemmap').fill((map.trim() ? map.trim() + '\n' : '') + `стул тест ${uniq} = Позиция ${uniq} *`);
    await page.locator('#iform #journal').setInputFiles(path.join(dir, 'issues.xlsx'));
    await clickWait(page, '#iform #check');

    const errors = await page.locator('#eqh-problems tr.error').allInnerTexts();
    expect(errors.join('\n')).not.toContain('б/н');
    // ошибки пути строки без номера, а не поиска единицы «б/н»
    expect(errors.join('\n')).toContain(`Немає у відповідностях найменувань: «рециркулятор тест ${uniq}»`);
    expect(errors.join('\n')).toContain(`Немає інвентарного номера, а позиція «Позиция ${uniq}» обліковується за номерами`);
    expect(errors, errors.join('\n')).toHaveLength(2);
    expect(await page.locator('#eqh-unmapped').innerText()).toMatch(new RegExp(`^рециркулятор тест ${uniq}\\s+1$`));
    const noinv = page.locator('#eqh-noinv tr').filter({ hasText: who(2) });
    await expect(noinv).toHaveCount(1);
    expect((await noinv.locator('td').allInnerTexts()).map(t => t.trim()).slice(-2)).toEqual([`стул тест ${uniq}`, `Позиция ${uniq}`]);
    await expect(page.locator('#eqh-noinv tr').filter({ hasText: who(3) })).toHaveCount(0);
  } finally {
    query('delete from sqs_eqitemmap' + savedMap.map(([w, i]) => `; insert into sqs_eqitemmap (written, itemname) values ('${esc(w)}', '${esc(i)}')`).join(''));
    query("delete from keyval where keyd = 'sqs_eqfix'" + (savedFix.length ? `; insert into keyval (keyd, vald) values ('sqs_eqfix', '${esc(savedFix[0][0])}')` : ''));
  }
});
