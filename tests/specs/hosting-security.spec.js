// Установка в интернет (хостинг с Apache): служебные файлы не отдаются, нужные браузеру — отдаются,
// ответы с заголовками безопасности. Запросы без входа. ZIPPY_GAPS п. 36.
const { test, expect } = require('@playwright/test');

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
