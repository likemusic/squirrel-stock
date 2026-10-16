// Источник запроса для действий (App\SQS\RequestOrigin, index.php): событие (?q=...) и POST выполняются, только если запрос
// пришел со страниц самой программы — Sec-Fetch-Site same-origin/none, а без него (обычный HTTP в локальной сети)
// Origin или Referer того же адреса (host:port). Иначе действие не выполняется, в журнал — адрес и источник.
// Проверка — обычными запросами к своему стенду с разными заголовками; адреса действий у Zippy постоянные.
const crypto = require('crypto');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { blockExternal, clickWait, createItem, createDoc } = require('../helpers/zippy');
const { query, value, esc } = require('../helpers/db');
const { logCount } = require('../helpers/stand');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const BASE = process.env.SQS_BASE || 'http://127.0.0.1:8083';
const LOGOUT = '/index.php?q=p:App/Pages/Main::logout';   // ссылка «Выход» (ClickLink: window.location на этот адрес)
const hashOf = pass => execFileSync('docker', ['exec', '-i', WEB, 'php', '-r', 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);'], { input: pass, encoding: 'utf8' });

function createUser(login, pass) {
  query(`insert into users (userlogin, userpass, createdon, email, acl, disabled, options, role_id)
         select '${esc(login)}', '${esc(hashOf(pass))}', now(), '', acl, 0, options, role_id from users where userlogin = 'admin'`);
}

async function newContext(browser) {
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  await blockExternal(context);
  return context;
}

// вход через страницу; remember — с галочкой «Запомнить»
async function login(context, user, pass, remember = false) {
  const page = await context.newPage();
  await page.goto('/');
  await page.locator('#userlog').fill(user);
  await page.locator('input[type=password]').first().fill(pass);
  if (remember) await page.locator('#remember').setChecked(true);
  await page.locator('button[type=submit], input[type=submit]').first().click();
  await page.locator('#logout, #codeform, .alert-danger:visible').first().waitFor({ state: 'attached', timeout: 15000 });
  return page;
}

async function loggedIn(context) {
  const page = await context.newPage();
  await page.goto('/index.php?p=App/Pages/Main');
  const n = await page.locator('#logout').count();
  await page.close();
  return n > 0;
}

test('действие с чужого источника не выполняется, со своего — выполняется', async ({ browser, uniq }) => {
  test.setTimeout(240_000);
  const user = 'ro' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  createUser(user, pass);
  const context = await newContext(browser);
  await login(context, user, pass);
  expect(await loggedIn(context)).toBe(true);
  const own = new URL(BASE);

  // «Выход» — безвредное действие с видимым итогом: после отклоненного запроса пользователь остается в программе
  const rejected = {
    'Sec-Fetch-Site: cross-site': { 'sec-fetch-site': 'cross-site', referer: BASE + '/index.php' },
    'Sec-Fetch-Site: same-site': { 'sec-fetch-site': 'same-site' },
    'без заголовков источника': {},
    'Referer другого сайта': { referer: 'http://other.example/page' },
    'Referer: свой адрес — часть чужого имени': { referer: `http://${own.host}.other.example/` },
    'Referer: свой хост, другой порт': { referer: `http://${own.hostname}:1/` },
    'Origin: null': { origin: 'null' },
    'Origin другого сайта при своем Referer': { origin: 'http://other.example', referer: BASE + '/' },
    'X-Forwarded-Host не принимается за свой адрес': { 'x-forwarded-host': 'other.example', referer: 'http://other.example/' },
  };
  const wrong = [];
  for (const [name, headers] of Object.entries(rejected)) {
    const mark = 'ro' + crypto.randomBytes(4).toString('hex');
    const r = await context.request.get(LOGOUT + '&sqsmark=' + mark, { headers, maxRedirects: 0 });
    if (!(await loggedIn(context))) {
      wrong.push(name + ': выполнено');
      await login(context, user, pass);
      continue;
    }
    if (logCount(mark) == 0) wrong.push(name + ': нет записи в журнале');
    if (!/Действие не выполнено|Дію не виконано/.test(await r.text())) wrong.push(name + ': нет сообщения об отказе');
  }
  // AJAX-событие с чужого источника — 403
  const ajax = await context.request.get(LOGOUT + '&ajax=true', { headers: { 'sec-fetch-site': 'cross-site' }, maxRedirects: 0 });
  if (ajax.status() != 403) wrong.push('AJAX с чужого источника: статус ' + ajax.status());
  if (!(await loggedIn(context))) { wrong.push('AJAX с чужого источника: выполнено'); await login(context, user, pass); }

  // показ страницы по адресу события без компонента (так перенаправляет Zippy) — не действие, заголовки не нужны
  const show = await context.request.get('/index.php?q=p:App/Pages/Main', { maxRedirects: 0 });
  if (!(await show.text()).includes('id="logout"')) wrong.push('показ страницы ?q=p:<страница> без заголовков: не показана');

  const allowed = {
    'Sec-Fetch-Site: same-origin': { 'sec-fetch-site': 'same-origin' },
    'Sec-Fetch-Site: none (адрес набран или из закладки)': { 'sec-fetch-site': 'none' },
    'без Sec-Fetch-Site, Referer своего адреса (обычный HTTP)': { referer: BASE + '/index.php?p=App/Pages/Main' },
    'без Sec-Fetch-Site, Origin своего адреса': { origin: BASE },
  };
  for (const [name, headers] of Object.entries(allowed)) {
    await context.request.get(LOGOUT, { headers, maxRedirects: 0 });
    if (await loggedIn(context)) wrong.push(name + ': не выполнено');
    await login(context, user, pass);
  }
  await context.close();
  expect(wrong).toEqual([]);
});

test('форма входа (POST) с чужого источника не входит', async ({ browser, uniq }) => {
  const user = 'rp' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  createUser(user, pass);
  const form = { q: 'p:App/Pages/UserLogin::loginform', loginform_q: '', userlogin: '', userlog: user, userpasswo: pass };
  const post = async headers => {
    const context = await newContext(browser);
    const page = await context.newPage();   // как в браузере: сначала страница входа, потом отправка формы
    await page.goto('/');
    await page.close();
    await context.request.post('/index.php', { form, headers, maxRedirects: 0 });
    const ok = await loggedIn(context);
    await context.close();
    return ok;
  };
  expect(await post({ 'sec-fetch-site': 'cross-site', origin: 'http://other.example' }), 'с чужого сайта').toBe(false);
  expect(await post({}), 'без источника').toBe(false);
  expect(await post({ 'sec-fetch-site': 'same-origin', origin: BASE, referer: BASE + '/' }), 'со своей страницы').toBe(true);
  expect(await post({ origin: BASE, referer: BASE + '/' }), 'со своей страницы без Sec-Fetch-Site').toBe(true);
});

// Обычный HTTP по имени компьютера (не localhost): браузер не шлет Sec-Fetch-Site, остаются Origin и Referer.
// Имя sqs-lan.test браузер направляет на стенд (--host-resolver-rules), Host в запросе — это имя.
test('обычный HTTP по имени компьютера: вход, документ, печать и Excel работают без Sec-Fetch-Site', async ({ playwright, uniq }) => {
  test.setTimeout(180_000);
  const lan = new URL(BASE), host = 'sqs-lan.test';
  const browser = await playwright.chromium.launch({ args: [`--host-resolver-rules=MAP ${host} ${lan.hostname}:${lan.port || 80}`] });
  const context = await browser.newContext({ baseURL: 'http://' + host, locale: 'ru-RU', viewport: { width: 1440, height: 900 } });
  await context.route(u => new URL(u).hostname != host, r => r.abort());   // как blockExternal: только стенд
  const page = await context.newPage();
  page.on('dialog', d => d.accept());
  const user = 'rl' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  createUser(user, pass);
  const sent = [];
  page.on('request', async r => {
    if (r.url().includes('index.php?q=') || r.method() == 'POST') sent.push((await r.allHeaders())['sec-fetch-site'] || '');
  });
  await page.goto('/');
  await page.locator('#userlog').fill(user);
  await page.locator('input[type=password]').first().fill(pass);
  await page.locator('#remember').setChecked(true);
  await page.locator('button[type=submit], input[type=submit]').first().click();
  await expect(page.locator('#logout'), 'вход').toHaveCount(1, { timeout: 15000 });

  // документ: позиция (форма справочника), оприходование (строки, автоподсказка, проведение)
  const code = 'RL-' + uniq, notes = 'Источник запроса ' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  const { number } = await createDoc(page, 'IncomeItem', { date: '2026-10-01', notes, rows: [{ code, qty: 2, price: 0 }] });
  expect(value(`select count(*) from documents where notes = '${esc(notes)}'`), 'документ сохранен').toBe('1');

  // журнал документов: просмотр (печатная форма в окне просмотра) и выгрузка в Excel (событие с файлом в ответе)
  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await page.locator('#filter #searchnumber').fill(number);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchnumber').press('Enter')]);
  await clickWait(page, 'a[id^=show_]:visible');
  await expect(page.locator('#preview'), 'печатная форма документа').toContainText(number, { timeout: 10000 });
  const [csv] = await Promise.all([page.waitForEvent('download', { timeout: 15000 }), page.locator('#csv').click()]);
  expect(csv.suggestedFilename(), 'Excel журнала').toBeTruthy();

  // отчет и его Excel (ссылка на ShowReport, в новом окне — здесь в том же, чтобы поймать файл)
  await page.goto('/index.php?p=App/Pages/Report/ItemActivity');
  await clickWait(page, '#filter #onreport');
  const xls = page.locator('a[href*="ShowReport&arg=xls"]').first();
  await xls.evaluate(a => a.removeAttribute('target'));
  const [rep] = await Promise.all([page.waitForEvent('download', { timeout: 15000 }), xls.click()]);
  expect(rep.suggestedFilename(), 'Excel отчета').toBeTruthy();

  expect(sent.length, 'действия были').toBeGreaterThan(0);
  expect(sent.filter(s => s), 'браузер не шлет Sec-Fetch-Site по обычному HTTP').toEqual([]);
  await browser.close();
});
