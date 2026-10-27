// Пределы PHP хостинга (TODO 2.25): на хостинге по умолчанию max_execution_time = 30 с и memory_limit = 128 МБ, и
// «Проверить» в импорте истории оборудования на реальных таблицах падала с «Fatal error: Maximum execution time of 30
// seconds exceeded», а после подъема времени — с «Allowed memory size … exhausted». Стенд дает 120 с и 256 МБ
// (docker/php.ini), поэтому раньше не было видно. Теперь долгая операция поднимает пределы сама (App\SQS\Limits), таблица
// читается по листу, номера единиц, договоры и получатели берутся из базы одним запросом, а не на каждую строку; если
// хостинг поднять пределы не дает — страница с сообщением, что у него попросить, и строка в журнале программы.
// Хостинг воспроизводится заголовком запроса (<If> в конфигурации Apache, как в tmpdir.spec.js): «low» — пределы
// заниженные, но программа может их поднять (php_value); «locked» — заниженные и закрепленные (php_admin_value: так же
// не работает set_time_limit, как при disable_functions). Предел — 2 с процессорного времени (на Linux max_execution_time
// считает его, а не ожидание базы) и 20 МБ, таблицы — вымышленные (tests/fixtures/make-eqhistory-big.php).
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait, messages } = require('../helpers/zippy');
const { logCount } = require('../helpers/stand');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const CONF = '/etc/apache2/conf-enabled/zz-sqs-limit.conf';
const ROWS = 400;   // строк на лист: старый код читал такие таблицы дольше 2 с
// «хостинг не дает»: предел должен быть исчерпан наверняка — таблицы по 400 строк на незагруженной машине проверяются
// быстрее 2 с и в 20 МБ, обрыва не было; по 4000 строк не хватает памяти при любой нагрузке. Так же проверяется, что
// страница с сообщением выводится и после нехватки памяти (раньше второй раз падал переводчик)
const ROWS_LOCKED = 4000;
const sh = cmd => execFileSync('docker', ['exec', WEB, 'sh', '-c', cmd], { encoding: 'utf8' });
const graceful = () => {
  sh('apache2ctl graceful 2>/dev/null');
  execFileSync('sleep', ['2']);   // новые процессы Apache — с новой конфигурацией
};
const LIMITS = { max_execution_time: '2', memory_limit: '20M' };
const block = (name, directive) => `<If "%{HTTP:X-SQS-Limit} == '${name}'">
${Object.entries(LIMITS).map(([k, v]) => `    ${directive} ${k} ${v}\n`).join('')}</If>
`;

test.describe.configure({ mode: 'serial' });

let dir;
test.beforeAll(async ({}, testInfo) => {
  test.setTimeout(3 * 60_000);
  const tmp = '/tmp/sqs-timelimit';
  sh(`rm -rf ${tmp} && mkdir -p ${tmp}`);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-big.php'), `${WEB}:${tmp}/make.php`]);
  dir = testInfo.outputPath('eqh');
  for (const [sub, rows] of [['low', ROWS], ['locked', ROWS_LOCKED]]) {
    sh(`mkdir -p ${tmp}/${sub} && php ${tmp}/make.php ${tmp}/${sub} ${rows} TL`);
    fs.mkdirSync(path.join(dir, sub), { recursive: true });
    for (const f of ['census-big.xlsx', 'issues-big.xlsx']) execFileSync('docker', ['cp', `${WEB}:${tmp}/${sub}/${f}`, path.join(dir, sub, f)]);
  }
  sh(`rm -rf ${tmp}`);
  execFileSync('docker', ['exec', '-i', WEB, 'sh', '-c', `cat > ${CONF}`], { input: block('low', 'php_value') + block('locked', 'php_admin_value') });
  graceful();
});

test.afterAll(() => {
  sh(`rm -f ${CONF}`);
  graceful();
});

async function hosting(browser, mode) {
  // прогрев без заголовка: первый запрос после перезапуска Apache разбирает шаблоны и код (opcache) дольше 2 с
  const warm = await browser.newContext({ storageState: path.join(__dirname, '..', '.auth', 'admin.json') });
  await (await warm.newPage()).goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
  await warm.close();
  const context = await browser.newContext({ storageState: path.join(__dirname, '..', '.auth', 'admin.json'), extraHTTPHeaders: { 'X-SQS-Limit': mode } });
  const page = await context.newPage();
  const resp = await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
  expect(resp.status()).toBe(200);
  await expect(page.locator('#iform #check'), 'страница открывается в пределах хостинга').toBeVisible();
  return { context, page };
}

async function check(page, sub) {
  await page.locator('#iform #census').setInputFiles(path.join(dir, sub, 'census-big.xlsx'));
  await page.locator('#iform #journal').setInputFiles(path.join(dir, sub, 'issues-big.xlsx'));
  await clickWait(page, '#iform #check');
}

test('проверка истории оборудования при заниженных пределах: пределы поднимаются, отчет проверки', async ({ browser }) => {
  test.setTimeout(3 * 60_000);
  const { context, page } = await hosting(browser, 'low');
  try {
    await check(page, 'low');
    const body = await page.locator('body').innerText();
    expect(body, 'не Fatal').not.toMatch(/Maximum execution time|Allowed memory size/);
    await expect(page.locator('#eqh-summary'), (await messages(page)).join(' ')).toBeVisible();
    // все строки таблиц прочитаны и разобраны: 3 листа переписи и журнал, номеров единиц в базе нет — у каждой ошибка
    expect(Number((await page.locator('#eqh-summary tr[data-stat=errors] td').last().innerText({ timeout: 5000 })).trim())).toBe(4 * ROWS);
  } finally {
    await context.close();
  }
});

test('хостинг не дает поднять пределы: сообщение, что попросить у хостинга, и строка в журнале', async ({ browser }) => {
  test.setTimeout(3 * 60_000);
  const mark = 'SquirrelStock limits: equipment history check';
  const was = logCount(mark);
  const { context, page } = await hosting(browser, 'locked');
  try {
    await check(page, 'locked');
    await expect(page.locator('#sqs-limits')).toContainText('max_execution_time = 300');
    await expect(page.locator('#sqs-limits')).toContainText('memory_limit = 512M');
    expect(await page.locator('body').innerText(), 'без текста Fatal и путей сервера').not.toMatch(/Fatal|\/var\/www/);
    expect(logCount(mark)).toBe(was + 1);
    await page.locator('a', { hasText: /Вернуться|Повернутися/ }).click();
    await expect(page.locator('#iform #check'), 'ссылка — на страницу импорта').toBeVisible();
  } finally {
    await context.close();
  }
});
