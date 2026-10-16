// Установка в интернет (хостинг с Apache): служебные файлы не отдаются, нужные браузеру — отдаются,
// ответы с заголовками безопасности. Запросы без входа. ZIPPY_GAPS п. 36.
const { test, expect } = require('@playwright/test');

// запросы — как от нового посетителя: без общей сессии тестов (страница входа очищает сессию и разлогинила бы остальные тесты)
test.use({ storageState: { cookies: [], origins: [] } });

const closed = [
  'composer.json', 'composer.lock', 'vendor/composer/installed.json', 'vendor/autoload.php',
  'lang/uk.json', 'lang/ru.json', 'cli/i18n/ru.json', 'cli/backup.php', 'script_examples.zip',
  'config/config.php', 'app/system.php', 'templates/pages/main.html', 'logs/', 'init.php',
];
const open = ['vendor/leon-mbs/zippy/assets/css/zippy-bundle.css', 'assets/css/app.css', 'index.php'];

test('служебные файлы закрыты, нужные браузеру открыты', async ({ request }) => {
  const bad = [];
  for (const path of closed) {
    const r = await request.get('/' + path, { maxRedirects: 0 });
    if (![403, 404].includes(r.status())) bad.push(`${path}: ${r.status()}`);
  }
  for (const path of open) {
    const r = await request.get('/' + path, { maxRedirects: 0 });
    if (![200, 302].includes(r.status())) bad.push(`${path}: ${r.status()} (должен открываться)`);
  }
  expect(bad).toEqual([]);
});

test('заголовки безопасности, версия PHP не раскрывается', async ({ request }) => {
  const r = await request.get('/index.php?p=App/Pages/UserLogin', { maxRedirects: 0 });
  const h = r.headers();
  expect(h['x-frame-options']).toBe('SAMEORIGIN');
  expect(h['x-content-type-options']).toBe('nosniff');
  expect(h['referrer-policy']).toBe('same-origin');
  expect(h['x-powered-by'], 'версия PHP').toBeUndefined();
});

test('cookie сессии: HttpOnly и SameSite', async ({ request }) => {
  const r = await request.get('/', { maxRedirects: 0 });
  const cookie = r.headersArray().filter(h => h.name.toLowerCase() === 'set-cookie').map(h => h.value).find(v => v.startsWith('PHPSESSID='));
  expect(cookie, 'сессия выдается').toBeTruthy();
  expect(cookie).toMatch(/;\s*HttpOnly/i);
  expect(cookie).toMatch(/;\s*SameSite=Lax/i);
});

// Папка upload/: браузеру отдаются только картинки, остальное (скрипты PHP, svg, текст) — 403, и PHP в ней не выполняется
// даже в имени вида x.php.png. Защита держится без upload/.htaccess (тома Docker и установка Linux создают папку пустой):
// правило в корневом .htaccess и настройка Apache. Пробные файлы — безобидные: PHP печатает сумму, картинка 1×1.
test('upload/: только картинки, PHP не выполняется', async ({ request }) => {
  const { execFileSync } = require('child_process');
  const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
  const sh = cmd => execFileSync('docker', ['exec', '-u', 'www-data', WEB, 'sh', '-c', cmd], { encoding: 'utf8' });
  const p = 'sqs-probe-' + Date.now().toString(36);
  const php = '<?php echo "probe-" . (40 + 2);';
  const png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
  const files = { [p + '.php']: php, [p + '.phtml']: php, [p + '.php.png']: php, [p + '.txt']: 'text',
    [p + '.svg']: '<svg xmlns="http://www.w3.org/2000/svg"><text>t</text></svg>', [p + '-dir/' + p + '.php']: php };
  const hadHtaccess = sh('test -f upload/.htaccess && echo 1 || echo 0').trim() == '1';
  try {
    sh(`mkdir -p upload/${p}-dir`);
    for (const [name, body] of Object.entries(files)) sh(`printf '%s' '${body.replace(/'/g, `'\\''`)}' > upload/${name}`);
    sh(`echo ${png} | base64 -d > upload/${p}.png`);
    for (const htaccess of [true, false]) {   // с upload/.htaccess и без него
      if (!htaccess) sh('mv upload/.htaccess upload/.htaccess.off 2>/dev/null || true');
      const bad = [];
      for (const name of Object.keys(files)) {
        const r = await request.get('/upload/' + name, { maxRedirects: 0 });
        const body = await r.text();
        if (body.includes('probe-42')) bad.push(`${name}: PHP выполнен`);
        else if (r.status() != 403) bad.push(`${name}: ${r.status()}`);
      }
      const img = await request.get(`/upload/${p}.png`);
      if (img.status() != 200 || !img.headers()['content-type'].startsWith('image/png')) bad.push(`${p}.png: ${img.status()} (картинка должна открываться)`);
      expect(bad, htaccess ? 'с upload/.htaccess' : 'без upload/.htaccess').toEqual([]);
    }
  } finally {
    sh(`rm -rf upload/${p}* ; [ -f upload/.htaccess.off ] && mv upload/.htaccess.off upload/.htaccess; true`);
  }
  expect(hadHtaccess, 'upload/.htaccess есть в образе').toBe(true);
});
