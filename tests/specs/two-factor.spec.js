// Двухфакторный вход (TOTP, RFC 6238): включение в профиле с подтверждением кодом, резервные коды (показываются один раз),
// вход «пароль + код», код и резервный код не принимаются дважды, «Запомнить» выдается только после второго шага,
// сброс администратором. Код в тесте считается тем же алгоритмом. ZIPPY_GAPS п. 36 (В2).
const crypto = require('crypto');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { blockExternal, clickWait, messages } = require('../helpers/zippy');
const { query, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function totp(secret, at = Date.now() / 1000) {
  let bits = '';
  for (const c of secret.replace(/\s/g, '')) bits += B32.indexOf(c).toString(2).padStart(5, '0');
  const key = Buffer.from(bits.match(/.{8}/g).map(b => parseInt(b, 2)));
  const msg = Buffer.alloc(8);
  msg.writeBigUInt64BE(BigInt(Math.floor(at / 30)));
  const h = crypto.createHmac('sha1', key).update(msg).digest();
  const o = h[19] & 0x0f;
  return String(((h.readUInt32BE(o) & 0x7fffffff) % 1000000)).padStart(6, '0');
}

const hashOf = pass => execFileSync('docker', ['exec', '-i', WEB, 'php', '-r', 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);'], { input: pass, encoding: 'utf8' });

// новая сессия: пароль (и при необходимости код); итог — 'in', 'code' (ждет код) или 'out'
async function login(browser, user, pass, { code, remember } = {}) {
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  await blockExternal(context);
  const page = await context.newPage();
  await page.goto('/');
  await page.locator('#userlog').fill(user);
  await page.locator('input[type=password]').first().fill(pass);
  if (remember) await page.locator('#remember').setChecked(true);
  await page.locator('button[type=submit], input[type=submit]').first().click();
  await page.locator('#logout, #codeform, .alert-danger:visible').first().waitFor({ state: 'attached', timeout: 15000 });
  let state = (await page.locator('#logout').count()) ? 'in' : (await page.locator('#codeform').count()) ? 'code' : 'out';
  let error = '';
  if (state == 'code' && code !== undefined) {
    await page.locator('#codeform #code').fill(code);
    await page.locator('#codeform button[type=submit]').click();
    await page.locator('#logout, .alert-danger:visible').first().waitFor({ state: 'attached', timeout: 15000 });
    state = (await page.locator('#logout').count()) ? 'in' : 'code';
    error = (await page.locator('.alert-danger').allInnerTexts()).join(' ');
  }
  const cookies = await context.cookies();
  return { context, page, state, error, remember: cookies.find(c => c.name === 'remember') };
}

test('двухфакторный вход: включение, вход с кодом, резервные коды, сброс администратором', async ({ page, browser, uniq }) => {
  const user = 'tf' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  query(`insert into users (userlogin, userpass, createdon, email, acl, disabled, options, role_id)
         select '${esc(user)}', '${esc(hashOf(pass))}', now(), '', acl, 0, options, role_id from users where userlogin = 'admin'`);

  // включение: ключ со страницы, подтверждение кодом; резервные коды — один раз
  let s = await login(browser, user, pass);
  expect(s.state).toBe('in');
  const p = s.page;
  await p.goto('/index.php?p=App/Pages/UserProfile');
  await Promise.all([p.waitForURL(/TwoFactor/), p.locator('#twofa-link').click()]);   // ссылка из профиля
  const secret = (await p.locator('#twofa-secret').innerText()).replace(/\s/g, '');
  expect(secret).toMatch(/^[A-Z2-7]{32}$/);
  await expect(p.locator('#enableform img[alt=QR]')).toHaveAttribute('src', /^data:image\/png;base64,/);
  await p.locator('#enableform #enablecode').fill('000000' === totp(secret) ? '111111' : '000000');
  await clickWait(p, '#enableform #enable');
  expect((await messages(p)).join(' '), 'неверный код не включает').toMatch(/Невірний код/);
  await p.locator('#enableform #enablecode').fill(totp(secret));
  await clickWait(p, '#enableform #enable');
  const codes = (await p.locator('#backup-codes li').allInnerTexts()).map(c => c.trim());
  expect(codes).toHaveLength(10);
  expect(codes[0]).toMatch(/^[A-Z2-7]{5}-[A-Z2-7]{5}$/);
  await p.reload();
  await expect(p.locator('#twofa-status')).toBeVisible();
  await expect(p.locator('#backup-codes'), 'коды больше не показываются').toHaveCount(0);
  await s.context.close();

  // вход: после пароля — код; неверный код не пускает
  s = await login(browser, user, pass, { code: '12345' });
  expect(s.state).toBe('code');
  expect(s.error).toMatch(/Невірний код/);
  await s.context.close();
  const next = totp(secret, Date.now() / 1000 + 30);   // код следующего шага: код включения уже принят
  s = await login(browser, user, pass, { code: next });
  expect(s.state, 'пароль + код').toBe('in');
  await s.context.close();
  s = await login(browser, user, pass, { code: next });
  expect(s.state, 'тот же код второй раз').toBe('code');
  await s.context.close();

  // резервный код — один раз
  s = await login(browser, user, pass, { code: codes[0].toLowerCase() });
  expect(s.state, 'резервный код').toBe('in');
  await s.context.close();
  s = await login(browser, user, pass, { code: codes[0] });
  expect(s.state, 'резервный код второй раз').toBe('code');
  await s.context.close();

  // «Запомнить»: до второго шага cookie не выдается
  s = await login(browser, user, pass, { remember: true });
  expect(s.state).toBe('code');
  expect(s.remember, 'до кода — без cookie').toBeUndefined();
  await s.page.locator('#codeform #code').fill(codes[1]);
  await s.page.locator('#codeform button[type=submit]').click();
  await s.page.locator('#logout').first().waitFor({ state: 'attached' });
  expect((await s.context.cookies()).find(c => c.name === 'remember'), 'после кода').toBeTruthy();
  await s.context.close();

  // сброс администратором — вход снова только по паролю
  await page.goto('/index.php?p=App/Pages/TwoFactor');
  await page.locator('#adminform #resetuser').selectOption({ label: user });
  await clickWait(page, '#adminform #reset');
  s = await login(browser, user, pass);
  expect(s.state, 'после сброса').toBe('in');
  await s.context.close();
});

// вход только по паролю мимо страницы входа (токен API): учитывает блокировку подбора и недоступен при двухфакторном входе
test('токен API по паролю: блокировка подбора и отказ при двухфакторном входе', async ({ playwright, uniq }, testInfo) => {
  const user = 'api' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  query(`insert into users (userlogin, userpass, createdon, email, acl, disabled, options, role_id)
         select '${esc(user)}', '${esc(hashOf(pass))}', now(), '', acl, 0, options, role_id from users where userlogin = 'admin'`);
  const api = await playwright.request.newContext({ baseURL: testInfo.project.use.baseURL, storageState: { cookies: [], origins: [] } });
  const token = async (login, password) => {
    const r = await api.post('/api/common', { data: { jsonrpc: '2.0', method: 'token', params: { login, password }, id: 1 } });
    const j = await r.json();
    return j.result ? 'token' : 'error';
  };
  try {
    expect(await token(user, pass), 'обычный пользователь').toBe('token');
    for (let i = 0; i < 5; i++) expect(await token(user, 'wrong-' + i)).toBe('error');
    expect(await token(user, pass), 'после 5 неудач — блокировка').toBe('error');
    query(`delete from sqs_loginfail where login = '${esc(user)}'`);
    query(`insert into sqs_2fa (user_id, secret, enabled, last_step, updated) select user_id, 'JBSWY3DPEHPK3PXP', 1, 0, now() from users where userlogin = '${esc(user)}'`);
    expect(await token(user, pass), 'двухфакторный вход: только по паролю нельзя').toBe('error');
  } finally {
    await api.dispose();
  }
});
