// Ошибка посреди восстановления (TODO 5.8): восстановление удаляло все таблицы и представления и выполняло дамп по
// операторам; любая ошибка на середине (ошибка SQL, как с -demo.sql 23.10 в 2.20, обрезанный или недокачанный файл)
// оставляла базу без части таблиц, сайт показывал «Ошибка базы данных». Теперь обрезанный дамп отклоняется до удаления
// таблиц (дамп разбирается целиком вхолостую), а перед заменой делается страховочный дамп текущей базы: после ошибки
// база возвращается из него. Дампы — копия этого же стенда; метка в keyval записана после копии: она на месте —
// значит, база та, что была до восстановления, а не частично восстановленная копия.
const fs = require('fs');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait, messages } = require('../helpers/zippy');
const { value, query } = require('../helpers/db');
const { logCount } = require('../helpers/stand');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const DB = process.env.SQS_DB_CONTAINER || 'squirrelstock-db-1';
const objects = () => value("select concat((select count(*) from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE'), '/', (select count(*) from information_schema.views where table_schema = database()), '/', (select count(*) from information_schema.triggers where trigger_schema = database()))");
// страховочные дампы (sqs*) во временной папке программы: после неудачного восстановления не остаются
const tempDumps = () => execFileSync('docker', ['exec', WEB, 'php', '-r', 'require "init.php"; echo count(glob(\\App\\SQS\\TempFiles::dir() . "/sqs*"));'], { encoding: 'utf8' }).trim();

test.describe.configure({ mode: 'serial' });

let lines;
test.beforeAll(async ({ browser }, testInfo) => {
  test.setTimeout(4 * 60_000);
  const context = await browser.newContext({ storageState: require('path').join(__dirname, '..', '.auth', 'admin.json') });
  const page = await context.newPage();
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  const [download] = await Promise.all([page.waitForEvent('download', { timeout: 120_000 }), page.locator('#bform #create').click()]);
  const zip = testInfo.outputPath('stand.zip');
  await download.saveAs(zip);
  await context.close();
  lines = execFileSync('unzip', ['-p', zip, 'database.sql'], { encoding: 'utf8', maxBuffer: 512 * 1024 * 1024 }).split('\n');
});

async function restore(page, file) {
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await page.locator('#rform #rfile').setInputFiles(file);
  await page.locator('#rform #confirm').setChecked(true);
  await clickWait(page, '#rform #restore');
  return (await messages(page)).join(' ');
}

async function siteOpens(page) {
  const resp = await page.goto('/');
  expect(resp.status()).toBe(200);
  await expect(page.locator('body')).not.toContainText(/Ошибка базы данных|Помилка бази даних|Fatal/);
  await expect(page.locator('#sqs-schema-error')).toHaveCount(0);
}

test('ошибка SQL посреди дампа: база возвращена в состояние до восстановления, сайт открывается', async ({ page, uniq }, testInfo) => {
  test.setTimeout(6 * 60_000);
  const before = objects();
  const tmp = tempDumps();
  query(`replace into keyval (keyd, vald) values ('sqs-rs-${uniq}', 'после копии')`);
  try {
    // после записи пользователей — оператор с ошибкой: к этому месту часть таблиц удалена и создана заново, представлений еще нет
    const i = lines.findIndex(s => /^INSERT INTO `?users`? /.test(s));
    expect(i).toBeGreaterThan(0);
    let j = i;
    while (!lines[j].trimEnd().endsWith(';')) j++;
    const sql = testInfo.outputPath('bad.sql');
    fs.writeFileSync(sql, [...lines.slice(0, j + 1), `INSERT INTO sqs_no_such_table_${uniq} VALUES (1);`, ...lines.slice(j + 1)].join('\n'));
    const mark = 'SquirrelStock restore: ';
    const was = logCount(mark);

    const msg = await restore(page, sql);
    expect(msg).toContain(`sqs_no_such_table_${uniq}`);
    expect(msg).toMatch(/возвращена в состояние до восстановления|повернуто до стану перед відновленням/);
    expect(objects(), 'таблицы, представления, триггеры — как до восстановления').toBe(before);
    expect(value(`select vald from keyval where keyd = 'sqs-rs-${uniq}'`), 'данные — те, что были до восстановления').toBe('после копии');
    expect(logCount(mark), 'ошибка — в журнале программы').toBe(was + 1);
    expect(tempDumps(), 'страховочный дамп удален').toBe(tmp);
    await siteOpens(page);
  } finally {
    query(`delete from keyval where keyd = 'sqs-rs-${uniq}'`);
  }
});

test('обрезанный дамп и дамп без таблиц: отказ до удаления таблиц', async ({ page, uniq }, testInfo) => {
  test.setTimeout(6 * 60_000);
  const before = objects();
  query(`replace into keyval (keyd, vald) values ('sqs-rs-${uniq}', 'после копии')`);
  try {
    // оборван посреди оператора INSERT: как недокачанный файл. Обрыв посреди строки и сразу после «;» внутри данных
    // (настройки пользователя — serialize, «i:0;»): такую строку разбор по строкам принимает за законченный оператор
    const i = lines.findIndex(s => /^INSERT INTO `?users`? /.test(s));
    const half = Math.floor(lines[i].length / 2);
    const semi = lines[i].indexOf(';', lines[i].indexOf("'a:"));
    expect(semi, 'в записи пользователя есть «;» внутри данных').toBeGreaterThan(0);
    for (const [name, end] of [['cut-mid', lines[i][half - 1] == ';' ? half - 1 : half], ['cut-semi', semi + 1]]) {
      const cut = testInfo.outputPath(name + '.sql');
      fs.writeFileSync(cut, [...lines.slice(0, i), lines[i].slice(0, end)].join('\n'));
      expect(await restore(page, cut), name).toMatch(/обрывается посреди оператора|обривається посеред оператора/);
      expect(objects()).toBe(before);
      expect(value(`select vald from keyval where keyd = 'sqs-rs-${uniq}'`)).toBe('после копии');
    }

    // только данные: начало файла похоже на копию, но таблиц в нем нет — после удаления текущих база осталась бы пустой
    const data = testInfo.outputPath('data.sql');
    fs.writeFileSync(data, `SET NAMES utf8;\nINSERT INTO keyval (keyd, vald) VALUES ('sqs-rs2-${uniq}', '1');\n`);
    expect(await restore(page, data)).toMatch(/не является резервной копией|не є резервною копією/);
    expect(objects()).toBe(before);
    expect(value(`select count(*) from keyval where keyd = 'sqs-rs2-${uniq}'`)).toBe('0');
    await siteOpens(page);
  } finally {
    query(`delete from keyval where keyd in ('sqs-rs-${uniq}', 'sqs-rs2-${uniq}')`);
  }
});

// сбой и самого возврата: представление текущей базы ссылается на функцию, а копия до ошибки ее удаляет — страховочный
// дамп (функции он не переносит) представление не создает. Страховочный дамп остается на сервере, сообщение называет
// его путь; база возвращается из него консольным mysql, как написано в docs/BACKUP.ru.md
test('возврат не удался: страховочный дамп остается, путь в сообщении, база возвращается из него консольным mysql', async ({ page, uniq }, testInfo) => {
  test.setTimeout(6 * 60_000);
  const fn = `sqs_rf_${uniq}`, view = `sqs_rv_${uniq}`;
  query(`create function ${fn}() returns int deterministic return 1`);
  query(`create view ${view} as select ${fn}() as x`);
  const before = objects();
  const tmp = Number(tempDumps());
  let kept = null;
  try {
    const sql = testInfo.outputPath('bad-rollback.sql');
    // перед последним оператором копии (SET FOREIGN_KEY_CHECKS = 1): иначе копия отклоняется как обрезанная
    const end = lines.findLastIndex(s => /^SET FOREIGN_KEY_CHECKS = 1;/.test(s));
    expect(end).toBeGreaterThan(0);
    fs.writeFileSync(sql, [...lines.slice(0, end), `DROP FUNCTION IF EXISTS ${fn};`, `INSERT INTO sqs_no_such_table_${uniq} VALUES (1);`, ...lines.slice(end)].join('\n'));
    const mark = 'SquirrelStock restore: rollback failed';
    const was = logCount(mark);

    await page.goto('/index.php?p=App/Pages/Service/Backup');
    await page.locator('#rform #rfile').setInputFiles(sql);
    await page.locator('#rform #confirm').setChecked(true);
    await clickWait(page, '#rform #restore');
    // база неполная — сообщение на отдельной странице, без шаблонов программы
    const msg = await page.locator('#sqs-restore-failed').innerText();
    expect(msg).toContain(`sqs_no_such_table_${uniq}`);
    expect(msg).toMatch(/Вернуть базу в состояние до восстановления тоже не удалось|Повернути базу до стану перед відновленням теж не вдалося/);
    kept = msg.match(/(\/\S*\/sqs[A-Za-z0-9]+)/)?.[1];
    expect(kept, 'путь к страховочному дампу в сообщении').toBeTruthy();
    expect(Number(tempDumps()), 'страховочный дамп оставлен').toBe(tmp + 1);
    expect(logCount(mark)).toBe(was + 1);

    query(`create function ${fn}() returns int deterministic return 1`);
    execFileSync('sh', ['-c', `docker exec ${WEB} cat "$1" | docker exec -i ${DB} sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'`, 'sh', kept]);
    expect(objects(), 'база из страховочного дампа — как до восстановления').toBe(before);
    await siteOpens(page);
  } finally {
    kept && execFileSync('docker', ['exec', WEB, 'rm', '-f', kept]);
    query(`drop view if exists ${view}; drop function if exists ${fn}`);
  }
});
