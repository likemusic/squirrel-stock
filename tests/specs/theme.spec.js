// Тема интерфейса (TODO 6.3): «Светлая», «Тёмная», «Как в системе» (prefers-color-scheme, на лету).
// Настройка у пользователя (options.theme), прежняя галочка «Ночная тема» (darkmode 1/0) читается как тёмная/светлая,
// без настройки — как в системе. Фактическую тему ставит assets/js/sqs-theme.js на <html data-bs-theme> до отрисовки <body>.
// Переключатель — в верхней панели (#sqs-theme) и в профиле; страница входа берет последний выбор из cookie sqs_theme.
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { createUser, dropUser, loginAs } = require('../helpers/users');
const { blockExternal, clickWait } = require('../helpers/zippy');
const { query, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const php = (code, input) => execFileSync('docker', ['exec', '-i', WEB, 'php', '-r', code], { input, encoding: 'utf8' });

// опции пользователя (serialize): value() из db.js экранирует «\\» (-B), здесь — сырое значение (-r) и запись через unhex
const DB = process.env.SQS_DB_CONTAINER || 'squirrelstock-db-1';
const rawOptions = login => execFileSync('docker', ['exec', '-i', DB, 'mariadb', '-uzstore', '-pzstore', 'zstore', '-N', '-r', '-e',
  `select options from users where userlogin = '${esc(login)}'`], { encoding: 'utf8' }).replace(/\n$/, '');
const writeOptions = (login, s) => query(`update users set options = unhex('${Buffer.from(s, 'utf8').toString('hex')}') where userlogin = '${esc(login)}'`);
// patch — поля для записи, null — убрать поле
function setOptions(login, patch) {
  writeOptions(login, php(`$o = unserialize(stream_get_contents(STDIN)) ?: []; foreach (json_decode('${JSON.stringify(patch)}', true) as $k => $v) { if ($v === null) unset($o[$k]); else $o[$k] = $v; } echo serialize($o);`, rawOptions(login)));
}
const getOption = (login, key) => php(`$o = unserialize(stream_get_contents(STDIN)); echo json_encode($o['${key}'] ?? null);`, rawOptions(login));

const theme = page => page.evaluate(() => [document.documentElement.getAttribute('data-sqs-theme'), document.documentElement.getAttribute('data-bs-theme')].join('/'));

// пользователь без прав (профиль и пустая страница доступны); options копируются у admin — тема задается явно
async function withUser(browser, uniq, options, fn) {
  const login = 'th' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  const role = createUser(login, pass, 'th-' + uniq);
  let context;
  try {
    setOptions(login, options);
    let page;
    ({ context, page } = await loginAs(browser, login, pass));
    await fn(page, login, context);
  } finally {
    await context?.close();
    dropUser(login, role);
  }
}

test('прежняя галочка darkmode: 1 — тёмная, 0 — светлая; без настройки — как в системе, с переключением на лету', async ({ browser, uniq }) => {
  for (const [i, [opts, scheme, expected]] of [
    [{ darkmode: 1, theme: null }, 'light', 'dark/dark'],
    [{ darkmode: 0, theme: null }, 'dark', 'light/light'],
    [{ darkmode: null, theme: null }, 'dark', 'auto/dark'],
  ].entries()) {
    await withUser(browser, uniq + i, opts, async page => {
      await page.emulateMedia({ colorScheme: scheme });
      await page.goto('/index.php?p=App/Pages/UserProfile');
      expect(await theme(page), JSON.stringify(opts)).toBe(expected);
      await expect(page.locator('#profileform #theme')).toHaveValue(expected.split('/')[0]);
      if (expected.startsWith('auto')) {
        await page.emulateMedia({ colorScheme: 'light' });
        await expect.poll(() => theme(page), 'системная тема сменилась — страница следом, без перезагрузки').toBe('auto/light');
      }
    });
  }
});

test('тема стоит на <html> до появления <body> (без вспышки светлой)', async ({ browser, uniq }) => {
  await withUser(browser, uniq, { theme: 'auto' }, async page => {
    await page.addInitScript(() => {
      new MutationObserver((list, obs) => {
        if (document.body) {
          window.__sqsAtBody = document.documentElement.getAttribute('data-bs-theme');
          obs.disconnect();
        }
      }).observe(document, { childList: true, subtree: true });
    });
    for (const scheme of ['dark', 'light']) {
      await page.emulateMedia({ colorScheme: scheme });
      await page.goto('/index.php?p=App/Pages/UserProfile');
      expect(await page.evaluate(() => window.__sqsAtBody)).toBe(scheme);
      expect(await page.locator('body').getAttribute('data-bs-theme'), 'у <body> своего атрибута нет — не перебивает <html>').toBeNull();
    }
  });
});

test('переключатель в верхней панели: сразу на странице, сохраняется у пользователя и для страницы входа', async ({ browser, uniq }) => {
  await withUser(browser, uniq, { theme: 'light' }, async (page, login, context) => {
    await page.emulateMedia({ colorScheme: 'light' });
    await page.goto('/index.php?p=App/Pages/UserProfile');
    const pick = async mode => {
      await page.locator('#sqs-theme > a').click();
      await page.locator(`#sqs-theme [data-sqs-theme=${mode}]`).click();
    };
    await pick('dark');
    expect(await theme(page), 'без перезагрузки').toBe('dark/dark');
    await expect(page.locator('#sqs-theme [data-sqs-theme=dark]')).toHaveClass(/active/);
    await expect.poll(() => getOption(login, 'theme')).toBe('"dark"');
    expect(getOption(login, 'darkmode'), 'галочка для прежних версий — следом').toBe('1');

    await page.reload();
    expect(await theme(page)).toBe('dark/dark');
    await pick('auto');
    await expect.poll(() => getOption(login, 'theme')).toBe('"auto"');
    expect(await theme(page)).toBe('auto/light');
    await pick('dark');
    await expect.poll(() => getOption(login, 'theme')).toBe('"dark"');

    const cookie = (await context.cookies()).find(c => c.name == 'sqs_theme');
    expect(cookie?.value).toBe('dark');
    expect(cookie?.sameSite).toBe('Lax');

    // страница входа в новой сессии с той же cookie: тёмная; чужое значение cookie не применяется — как в системе
    const guest = await browser.newContext({ storageState: { cookies: [cookie], origins: [] }, colorScheme: 'light' });
    await blockExternal(guest);
    const lp = await guest.newPage();
    await lp.goto('/');
    await expect(lp.locator('#userlog')).toBeVisible();
    expect(await theme(lp)).toBe('dark/dark');
    await guest.addCookies([{ ...cookie, value: 'x"><b>' }]);
    await lp.reload();
    expect(await theme(lp)).toBe('auto/light');
    await guest.close();
  });
});

test('профиль: выбор темы вместо галочки «Ночная тема»', async ({ browser, uniq }) => {
  await withUser(browser, uniq, { theme: 'dark' }, async (page, login) => {
    await page.goto('/index.php?p=App/Pages/UserProfile');
    await expect(page.locator('#profileform [zippy=darkmode], #profileform #darkmode')).toHaveCount(0);
    const sel = page.locator('#profileform #theme');
    expect(await sel.locator('option').evaluateAll(o => o.map(x => x.value))).toEqual(['auto', 'light', 'dark']);
    await sel.selectOption('light');
    await clickWait(page, '#profileform [type=submit]');
    expect(getOption(login, 'theme')).toBe('"light"');
    expect(getOption(login, 'darkmode')).toBe('0');
    expect(await theme(page)).toBe('light/light');
  });
});

test('select2 в тёмной теме — тёмный, в светлой — светлый (по фактической теме, на лету)', async ({ browser, uniq }) => {
  await withUser(browser, uniq, { theme: 'auto' }, async page => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/index.php?p=App/Pages/UserProfile');
    // список с классом select2 так же, как его оформляет base.html при загрузке страницы
    await page.evaluate(() => $('<select class="form-select select2" id="sqs-s2"><option>1</option></select>').appendTo('#profileform').select2({ theme: 'bootstrap-5' }));
    const sel = page.locator('#sqs-s2 + .select2-container--bootstrap-5 .select2-selection');
    const bg = () => sel.evaluate(e => getComputedStyle(e).backgroundColor);
    // фон страницы темы (--bs-body-bg, в app.css у тёмной свой)
    const pageBg = () => page.evaluate(() => {
      const d = document.body.appendChild(document.createElement('div'));
      d.style.background = 'var(--bs-body-bg)';
      const c = getComputedStyle(d).backgroundColor;
      d.remove();
      return c;
    });
    await expect(sel).toBeVisible();
    expect(await pageBg()).not.toBe('rgb(255, 255, 255)');
    expect(await bg()).toBe(await pageBg());
    await page.emulateMedia({ colorScheme: 'light' });
    await expect.poll(bg).toBe('rgb(255, 255, 255)');
  });
});

// main.html (и customerlist.html) ветвились по {{#darkmode}}, а в шаблон шла строка "dark"/"light" — непустая, то есть «истина»
// и в светлой теме: подписи графиков белые на светлом фоне. Теперь цвета берутся из фактической темы (sqsTheme.current())
// и графики перерисовываются при ее смене.
test('графики на главной: цвета подписей по фактической теме', async ({ browser, uniq }) => {
  for (const [i, [mode, scheme, font, bg]] of [['light', 'dark', '#000', '#fff'], ['dark', 'light', '#fff', '#333'], ['auto', 'dark', '#fff', '#333']].entries()) {
    await withUser(browser, uniq + i, { theme: mode }, async page => {
      await page.emulateMedia({ colorScheme: scheme });
      const colors = () => page.evaluate(() => [window.fontcolor, window.bgcolor]);
      await page.goto('/index.php?p=App/Pages/Main');
      expect(await colors(), mode).toEqual([font, bg]);
      if (mode == 'auto') {
        await page.emulateMedia({ colorScheme: 'light' });
        await expect.poll(colors, 'системная тема сменилась — графики следом').toEqual(['#000', '#fff']);
      }
    });
  }
});
