// Исправления в vendor/ (www/cli/patch-vendor.php, composer post-autoload-dump): правка наложена, и то, что она
// затрагивает, работает как раньше.
const { test, expect } = require('../fixtures');
const { webPhp } = require('../helpers/stand');
const { execFileSync } = require('child_process');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';

// Ссылка index.php?r=..., как ее строят RedirectLink и LinkList фреймворка с кодированием: base64(serialize([страница, параметры]))
const encoded = (page, params) => webPhp('-r', `echo base64_encode(serialize([${JSON.stringify(page)}, ${params}]));`);

test('Zippy HttpRequest: ссылка с параметром r принимает только [страница, параметры]', async ({ page }) => {
  const src = execFileSync('docker', ['exec', WEB, 'cat', 'vendor/leon-mbs/zippy/core/httprequest.php'], { encoding: 'utf8' });
  expect(src, 'правка из cli/patch-vendor.php наложена').toContain("['allowed_classes' => false]");

  // ссылки фреймворка открываются как раньше
  await page.goto('/index.php?r=' + encodeURIComponent(encoded('\\App\\Pages\\Report\\ItemActivity', '[]')));
  await expect(page.locator('#filter #onreport')).toBeAttached();
  await page.goto('/index.php?r=' + encodeURIComponent(encoded('\\App\\Pages\\Register\\DocList', '[]')));
  await expect(page.locator('#filter')).toBeAttached();

  // значение, которое не является такой ссылкой (строка вместо массива), — главная страница, без ошибки
  await page.goto('/index.php?r=' + encodeURIComponent(webPhp('-r', 'echo base64_encode(serialize("text"));')));
  await expect(page.locator('#filter #onreport')).toHaveCount(0);
  expect(await page.locator('body').innerText()).not.toMatch(/Fatal|Warning|Exception/);
});
