// Временная папка на хостинге с open_basedir без /tmp (ISPmanager; TODO 2.18). tempnam(sys_get_temp_dir()) там дает
// false, и «Создать копию» выводила вместо страницы «Path must not be empty» с путем сервера. Временная папка программы
// (App\SQS\TempFiles): системная, если доступна, иначе upload_tmp_dir, иначе tmp/ внутри программы (закрыта от браузера).
// Хостинг воспроизводится в контейнере стенда: open_basedir, upload_tmp_dir и session.save_path включаются только для
// запросов с заголовком X-SQS-Basedir (<If> в конфигурации Apache) — остальные тесты и сессии стенда не затронуты.
// «up» — как ISPmanager: корень сайта, папка копий и папка хостинга с upload_tmp_dir; «own» — без upload_tmp_dir.
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait, messages } = require('../helpers/zippy');
const { query, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const H = '/var/www/sqs-hosting', BK = '/var/www/squirrelstock-backup', CONF = '/etc/apache2/conf-enabled/zz-sqs-basedir.conf';
const sh = cmd => execFileSync('docker', ['exec', WEB, 'sh', '-c', cmd], { encoding: 'utf8' });
const ls = dir => sh(`ls -A ${dir} 2>/dev/null || true`).split('\n').filter(Boolean);
const graceful = () => {
  sh('apache2ctl graceful 2>/dev/null');
  execFileSync('sleep', ['2']);   // новые процессы Apache — с новой конфигурацией
};
const block = (name, extra) => `<If "%{HTTP:X-SQS-Basedir} == '${name}'">
    php_admin_value open_basedir "/var/www/html/:${H}/:${BK}/"
    php_admin_value session.save_path "${H}/sess"
${extra}</If>
`;

test.describe.configure({ mode: 'serial' });

let savedBackup;
test.beforeAll(() => {
  savedBackup = query("select vald from keyval where keyd = 'sqs_backup'");
  sh(`rm -rf ${H} /var/www/html/tmp && mkdir -p ${H}/tmp ${H}/sess ${BK}/tmpdir-test && chown -R www-data ${H} ${BK}/tmpdir-test`);
  execFileSync('docker', ['exec', '-i', WEB, 'sh', '-c', `cat > ${CONF}`], {
    input: block('up', `    php_admin_value upload_tmp_dir "${H}/tmp"\n`) + block('own', ''),
  });
  graceful();
});

test.afterAll(() => {
  sh(`rm -f ${CONF}; rm -rf ${H} ${BK}/tmpdir-test /var/www/html/tmp`);
  graceful();
  query("delete from keyval where keyd = 'sqs_backup'");
  if (savedBackup.length) query(`insert into keyval (keyd, vald) values ('sqs_backup', '${esc(savedBackup[0][0])}')`);
});

// контекст хостинга: сессия admin из .auth/admin.json — копией файла сессии в папку сессий хостинга
async function hosting(browser, mode) {
  const state = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '.auth', 'admin.json'), 'utf8'));
  for (const c of state.cookies.filter(c => /^[A-Za-z0-9,-]{20,}$/.test(c.value))) {
    sh(`f=/tmp/sess_${c.value}; [ -f $f ] && cp -p $f ${H}/sess/ || true`);
  }
  const context = await browser.newContext({ storageState: state, extraHTTPHeaders: { 'X-SQS-Basedir': mode } });
  const page = await context.newPage();
  page.on('dialog', d => d.accept());
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await expect(page.locator('#bform #create'), 'вход под admin в контексте хостинга').toBeVisible();
  return { context, page };
}

test('open_basedir без /tmp, upload_tmp_dir задан: копия, копия в папку, восстановление, импорт истории', async ({ browser }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const { context, page } = await hosting(browser, 'up');
  try {
    // скачать копию
    const [download] = await Promise.all([page.waitForEvent('download', { timeout: 60_000 }), page.locator('#bform #create').click()]);
    const file = testInfo.outputPath('backup.zip');
    await download.saveAs(file);
    expect(fs.readFileSync(file).subarray(0, 2).toString(), 'zip, а не текст ошибки').toBe('PK');

    // «Сохранить и сделать копию сейчас» — в папку внутри open_basedir
    await page.locator('#sform #local').fill(`${BK}/tmpdir-test`);
    await page.locator('#sform #network').fill('');
    await page.locator('#sform #cloud').fill('');
    await clickWait(page, '#sform #runnow');
    expect((await messages(page)).join(' ')).not.toMatch(/Path|empty|помилк|ошибк/i);
    expect(ls(`${BK}/tmpdir-test`).filter(f => /^squirrelstock-.*\.zip$/.test(f))).toHaveLength(1);

    // восстановление той же копии
    await page.locator('#rform #rfile').setInputFiles(file);
    await page.locator('#rform #confirm').setChecked(true);
    await clickWait(page, '#rform #restore');
    await expect(page.locator('.toast-message').first()).toContainText(/відновлено|восстановлено/);

    // импорт истории: «Проверить» сохраняет копии таблиц и состояние во временной папке (sqs-eqh-*) — в upload_tmp_dir
    const book = testInfo.outputPath('eqh.xlsx');
    execFileSync('docker', ['exec', WEB, 'php', '-r', `require "vendor/autoload.php"; $s = new PhpOffice\\PhpSpreadsheet\\Spreadsheet(); $s->getActiveSheet()->fromArray([["Инв. номер", "Наименование"], ["0000001", "Позиция"]]); (new PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($s))->save("${H}/eqh.xlsx");`]);
    execFileSync('docker', ['cp', `${WEB}:${H}/eqh.xlsx`, book]);
    const resp = await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    expect(resp.status()).toBe(200);
    await page.locator('#iform #census').setInputFiles(book);
    await page.locator('#iform #journal').setInputFiles(book);
    await clickWait(page, '#iform #check');
    expect(await page.content()).not.toMatch(/Path must not be empty|open_basedir/);
    expect(ls(`${H}/tmp`).filter(f => f.startsWith('sqs-eqh-')).length, 'копии таблиц — в upload_tmp_dir').toBeGreaterThan(0);
    expect(ls('/var/www/html/tmp'), 'tmp/ программы не нужна').toEqual([]);
  } finally {
    await context.close();
  }
});

test('open_basedir без /tmp и без upload_tmp_dir: копия через tmp/ программы, закрытую от браузера', async ({ browser, request }) => {
  const { context, page } = await hosting(browser, 'own');
  try {
    const [download] = await Promise.all([page.waitForEvent('download', { timeout: 60_000 }), page.locator('#bform #create').click()]);
    expect(await download.failure()).toBeNull();
    expect(ls('/var/www/html/tmp')).toEqual(['.htaccess']);   // временные файлы копии удалены
    for (const u of ['/tmp/', '/tmp/.htaccess', '/tmp/sqsABC123']) {
      expect((await request.get(u)).status(), u).toBe(403);
    }
  } finally {
    await context.close();
  }
});

// ошибка копии — сообщением на странице без путей сервера, путь — в журнал программы
test('нет временной папки: понятная ошибка на странице, без пути сервера', async ({ browser }) => {
  sh('mkdir -p /var/www/html/tmp && chmod 0500 /var/www/html/tmp && chown root /var/www/html/tmp');
  const { context, page } = await hosting(browser, 'own');
  try {
    await clickWait(page, '#bform #create');
    const text = (await messages(page)).join(' ');
    expect(text).toMatch(/upload_tmp_dir/);
    expect(text).not.toMatch(/\/var\/www|Path must not be empty/);
    await expect(page.locator('#bform #create'), 'страница на месте').toBeVisible();
  } finally {
    await context.close();
    sh('rm -rf /var/www/html/tmp');
  }
});
