// Восстановление .sql со страницы «Резервная копия» (TODO 2.20): -demo.sql пакета (tools/release/build.sh) содержал строку
// «DELETE … ;   -- комментарий», а восстановление считало оператор законченным, только если строка кончается на «;» —
// DELETE склеивался со следующим оператором, восстановление падало посреди дампа (таблицы удалены, представлений нет).
// Теперь хвостовой комментарий после разделителя (« -- …», « # …») не мешает; «--» внутри строки данных — не комментарий.
// Дамп — копия этого же стенда (база после теста та же), со строками, как их дописывает build.sh.
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait, messages } = require('../helpers/zippy');
const { value, query } = require('../helpers/db');

test('дамп .sql с хвостовыми комментариями после «;» восстанавливается страницей', async ({ page, uniq }, testInfo) => {
  test.setTimeout(6 * 60_000);
  const objects = () => value("select concat((select count(*) from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE'), '/', (select count(*) from information_schema.views where table_schema = database()), '/', (select count(*) from information_schema.triggers where trigger_schema = database()))");
  const before = objects();
  const hash = value("select userpass from users where userlogin = 'admin'");

  await page.goto('/index.php?p=App/Pages/Service/Backup');
  const [download] = await Promise.all([page.waitForEvent('download', { timeout: 120_000 }), page.locator('#bform #create').click()]);
  const zip = testInfo.outputPath('stand.zip');
  await download.saveAs(zip);
  const lines = execFileSync('unzip', ['-p', zip, 'database.sql'], { encoding: 'utf8', maxBuffer: 512 * 1024 * 1024 }).split('\n');

  // как build.sh: сразу после записи пользователей
  let i = lines.findIndex(s => /^INSERT INTO `?users`? /.test(s));
  expect(i).toBeGreaterThan(0);
  while (!lines[i].trimEnd().endsWith(';')) i++;
  lines.splice(i + 1, 0, '', '-- пакет: пароль admin и соль, созданные при сборке',
    `UPDATE users SET userpass = '${hash}' WHERE userlogin = 'admin';`,
    "DELETE FROM keyval WHERE keyd IN ('sqs_version', 'sqs_versions');   -- история версий стенда, где собиралась демо-база",
    `REPLACE INTO keyval (keyd, vald) VALUES ('sqs-rt1-${uniq}', '1');  # комментарий MySQL`,
    `REPLACE INTO keyval (keyd, vald) VALUES ('sqs-rt2-${uniq}', 'a;   -- не комментарий');`, '');
  const sql = testInfo.outputPath('stand.sql');
  fs.writeFileSync(sql, lines.join('\n'));

  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await page.locator('#rform #rfile').setInputFiles(sql);
  await page.locator('#rform #confirm').setChecked(true);
  await clickWait(page, '#rform #restore');
  await expect(page.locator('.toast-message').first(), (await messages(page)).join(' ')).toContainText(/відновлено|восстановлено/);

  expect(objects(), 'таблицы, представления, триггеры — как до восстановления').toBe(before);
  expect(value(`select vald from keyval where keyd = 'sqs-rt1-${uniq}'`)).toBe('1');
  expect(value(`select vald from keyval where keyd = 'sqs-rt2-${uniq}'`), '«--» внутри строки — данные').toBe('a;   -- не комментарий');
  query(`delete from keyval where keyd in ('sqs-rt1-${uniq}', 'sqs-rt2-${uniq}')`);
});

// сборка пакета не пишет комментарий в одну строку с оператором: так дамп читают и старые версии восстановления
test('build.sh: строки, которые дописываются в дамп, без комментария после «;»', () => {
  const src = fs.readFileSync(path.join(__dirname, '..', '..', 'tools', 'release', 'build.sh'), 'utf8');
  const block = src.slice(src.indexOf("lines[i + 1:i + 1] = ["), src.indexOf("open(f, 'w'"));
  expect(block.length).toBeGreaterThan(0);
  expect(block).not.toMatch(/;\s+(--|#)/);
});

// ошибка восстановления — в журнал программы, а не только на страницу: после сбоя посреди дампа страница может не
// отрисоваться (нет таблиц), и сообщение терялось. Здесь — архив без дампа (отказ до удаления таблиц).
test('ошибка восстановления записывается в журнал программы', async ({ page }, testInfo) => {
  const { logCount } = require('../helpers/stand');
  const zip = testInfo.outputPath('nodump.zip');
  execFileSync('python3', ['-I', '-c', 'import sys, zipfile; zipfile.ZipFile(sys.argv[1], "w").writestr("readme.txt", "x")', zip]);
  const mark = 'SquirrelStock restore: В архиве нет database.sql';
  const was = logCount(mark);
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await page.locator('#rform #rfile').setInputFiles(zip);
  await page.locator('#rform #confirm').setChecked(true);
  await clickWait(page, '#rform #restore');
  expect((await messages(page)).join(' ')).toContain('database.sql');
  expect(logCount(mark)).toBe(was + 1);
});
