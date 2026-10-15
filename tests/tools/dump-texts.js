// Снимок видимого текста, placeholder и title всех страниц меню — для сравнения интерфейса до и после
// автоматических правок (например, конвертации текстов в ключи). Стенд: SQS_BASE, вход: .auth/admin.json (после npm test).
// node tools/dump-texts.js before.json; …правка…; node tools/dump-texts.js after.json; сравнить файлы
const { chromium } = require('@playwright/test');
const fs = require('fs');
(async () => {
  const b = await chromium.launch();
  const c = await b.newContext({ baseURL: process.env.SQS_BASE || 'http://127.0.0.1:8083', storageState: __dirname + '/../.auth/admin.json' });
  await c.route(u => !['127.0.0.1','localhost'].includes(new URL(u).hostname), r => r.abort());
  const p = await c.newPage();
  p.on('dialog', d => d.dismiss());
  await p.goto('/');
  const links = [...new Set(await p.locator('a[href*="index.php?p=App/Pages/"]').evaluateAll(as => as.map(a => a.getAttribute('href'))))].filter(h => !/Update|Logout|UserLogin/i.test(h)).sort();
  const out = {};
  for (const h of links) {
    await p.goto(h);
    out[h] = {
      text: (await p.locator('body').innerText().catch(() => '')).replace(/\d{2}[.:]\d{2}([.:]\d{2,4})?/g, '#').replace(/\s+/g, ' '),
      attrs: await p.locator('[placeholder],[title]').evaluateAll(es => es.map(e => (e.getAttribute('placeholder') || '') + '|' + (e.getAttribute('title') || ''))),
    };
  }
  fs.writeFileSync(process.argv[2], JSON.stringify(out, null, 1));
  console.log('pages', links.length);
  await b.close();
})();
