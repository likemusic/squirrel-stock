// Выключенный пункт меню (metadata.disabled = 1: профиль cli/charity-profile.php, настройка меню, выключенный модуль) не
// открывается и по прямому адресу — «нет права доступа», в том числе у администратора (ZIPPY_GAPS п. 21). Раньше профиль
// убирал пункт только из меню, а страница выключенного модуля завершалась сообщением автозагрузчика «Невірний клас».
const fs = require('fs');
const path = require('path');
const { test, expect } = require('../fixtures');
const { bodyText } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const MODULES = path.join(__dirname, '../../www/app/sqs/modules');
const DENIED = /Нет права доступа к странице|Немає права доступу до сторінки/;

test('выключенный пункт меню Zippy по прямому адресу: нет доступа; включенный открывается', async ({ page }) => {
  const was = value(`select disabled from metadata where meta_type = 2 and meta_name = 'ItemActivity'`);
  try {
    query(`update metadata set disabled = 1 where meta_type = 2 and meta_name = 'ItemActivity'`);
    await page.goto('/index.php?p=App/Pages/Report/ItemActivity');
    expect(await bodyText(page)).toMatch(DENIED);
    await expect(page.locator('#filter #onreport')).toHaveCount(0);

    query(`update metadata set disabled = 0 where meta_type = 2 and meta_name = 'ItemActivity'`);
    await page.goto('/index.php?p=App/Pages/Report/ItemActivity');
    await expect(page.locator('#filter #onreport')).toBeAttached();
    expect(await bodyText(page)).not.toMatch(DENIED);
  } finally {
    query(`update metadata set disabled = ${Number(was)} where meta_type = 2 and meta_name = 'ItemActivity'`);
  }
});

test('страница выключенного модуля по прямому адресу: нет доступа вместо «Невірний клас»', async ({ page, uniq }) => {
  const u = uniq.toLowerCase(), cls = 'ZzOff' + uniq;
  const dir = path.join(MODULES, 'zz-test-off-' + u), off = path.join(MODULES, '.zz-test-off-' + u);
  fs.mkdirSync(path.join(dir, 'pages/report'), { recursive: true });
  fs.mkdirSync(path.join(dir, 'templates/pages/report'), { recursive: true });
  fs.writeFileSync(path.join(dir, 'module.php'), `<?php\nreturn ['name' => 'Выключение ${uniq}', 'menu' => [[2, '${cls}', 'Выключение ${uniq}', 'Склад']]];\n`);
  fs.writeFileSync(path.join(dir, 'pages/report', cls.toLowerCase() + '.php'), `<?php\nnamespace App\\Pages\\Report;\n\nclass ${cls} extends \\App\\Pages\\Base\n{\n}\n`);
  fs.writeFileSync(path.join(dir, 'templates/pages/report', cls.toLowerCase() + '.html'), `<html><head><title>t</title></head><body><h3 id="zz-off">Модуль ${uniq}</h3></body></html>\n`);
  try {
    await page.goto('/index.php?p=App/Pages/Report/' + cls);
    await expect(page.locator('#zz-off'), 'модуль включен — страница открывается').toBeAttached();

    fs.renameSync(dir, off);
    await page.goto('/index.php?p=App/Pages/Report/' + cls);
    expect(value(`select disabled from metadata where meta_type = 2 and meta_name = '${cls}'`)).toBe('1');
    const t = await bodyText(page);
    expect(t).not.toContain('Невірний клас');
    expect(t).toMatch(DENIED);

    fs.renameSync(off, dir);
    await page.goto('/index.php?p=App/Pages/Report/' + cls);
    await expect(page.locator('#zz-off'), 'модуль вернулся — страница открывается').toBeAttached();
  } finally {
    for (const d of [dir, off]) fs.rmSync(d, { recursive: true, force: true });
    await page.goto('/');   // синхронизация: пункт удаленного модуля выключен
    query(`delete from metadata where meta_name = '${cls}'`);
  }
});
