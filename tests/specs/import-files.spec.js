// Загрузка файлов в импорт (Сервис → Импорт): принимаются таблицы Excel, OpenDocument и CSV; HTML и XML не читаются
// (PhpSpreadsheet по файлу без расширения перебирал все свои читатели). Файлы безобидные: таблица из двух позиций.
const fs = require('fs');
const path = require('path');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { value, query, esc } = require('../helpers/db');

async function importItems(page, file, cols) {
  await page.goto('/index.php?p=App/Pages/Service/Import');
  const f = page.locator('#iform');
  await f.locator('#icompare').selectOption('0');
  await f.locator('#filename').setInputFiles(file);
  await f.locator('#passfirst').setChecked(true);
  await f.locator('#preview').setChecked(false);
  for (const [k, v] of Object.entries(cols)) await f.locator('#' + k).selectOption(v);
  await clickWait(page, '#iform input[type=submit], #iform button[type=submit]');
}

// колонки импорта сохраняются для следующего раза (keyval importcols) — после теста вернуть прежние: другие тесты
// импорта не задают все колонки
let savedCols;
test.beforeEach(() => { savedCols = query(`select vald from keyval where keyd = 'importcols'`); });
test.afterEach(() => {
  query(`delete from keyval where keyd = 'importcols'`);
  if (savedCols.length) query(`insert into keyval (keyd, vald) values ('importcols', '${esc(savedCols[0][0])}')`);
});

const count = code => Number(value(`select count(*) from items where item_code = '${esc(code)}'`));

test('импорт: CSV читается, HTML-таблица — нет', async ({ page, uniq }, testInfo) => {
  const dir = testInfo.outputPath('files');
  fs.mkdirSync(dir, { recursive: true });
  const csv = path.join(dir, 'items.csv'), html = path.join(dir, 'items.html');
  const c1 = 'IF' + uniq + '-1', c2 = 'IF' + uniq + '-2';
  fs.writeFileSync(csv, `name,code\nПозиция ${c1},${c1}\n`);
  fs.writeFileSync(html, `<html><body><table><tr><td>name</td><td>code</td></tr><tr><td>Позиция ${c2}</td><td>${c2}</td></tr></table></body></html>\n`);

  await importItems(page, csv, { colname: 'A', colcode: 'B' });
  await expect(page.locator('.toast-message').first()).toContainText('Імпортовано 1');
  expect(count(c1)).toBe(1);

  await importItems(page, html, { colname: 'A', colcode: 'B' });
  await expect(page.locator('.toast-message, .alert-danger').first()).toContainText('потрібна таблиця Excel');
  expect(count(c2), 'позиция из HTML не создана').toBe(0);
  expect(Number(value(`select count(*) from items where itemname like '%<td>%' or itemname like '%<table>%'`)), 'строки разметки не стали позициями').toBe(0);
});

// Колонка «картинка»: адрес скачивается только по http(s) с публичного адреса. Локальный путь к файлу сервера,
// file:// и адреса самого сервера и внутренней сети не читаются; позиции создаются, в сообщении — какие картинки
// не загружены. Картинка — служебная заглушка программы (assets/images/noimage.jpg), она есть на любом стенде.
test('импорт: картинка из локального файла и с внутреннего адреса не загружается', async ({ page, uniq }, testInfo) => {
  const dir = testInfo.outputPath('files');
  fs.mkdirSync(dir, { recursive: true });
  const csv = path.join(dir, 'images.csv');
  const img = ['/var/www/html/assets/images/noimage.jpg', 'file:///var/www/html/assets/images/noimage.jpg',
    'http://127.0.0.1/assets/images/noimage.jpg', 'http://localhost/assets/images/noimage.jpg'];
  const codes = img.map((_, i) => `IG${uniq}-${i}`);
  fs.writeFileSync(csv, 'name,code,image\n' + codes.map((c, i) => `Позиция ${c},${c},${img[i]}`).join('\n') + '\n');
  const images = () => Number(value('select count(*) from images'));
  const before = images();

  await importItems(page, csv, { colname: 'A', colcode: 'B', colimage: 'C', colmsr: '0' });
  await expect(page.locator('.toast-message', { hasText: 'Імпортовано' }).first()).toContainText('Імпортовано 4');
  const warn = page.locator('.toast-message', { hasText: 'Не завантажено зображень' });   // сообщение — 4 с, проверить сразу
  await expect(warn).toContainText('Не завантажено зображень: 4');
  await expect(warn).toContainText('адреса не в інтернеті: 127.0.0.1');
  await expect(warn).toContainText('потрібна адреса http');
  for (const c of codes) expect(count(c), c).toBe(1);
  expect(images(), 'картинки не сохранены').toBe(before);
});

test('адреса, с которых картинка не скачивается', async () => {
  const { webPhp } = require('../helpers/stand');
  const ips = ['8.8.8.8', '1.1.1.1', '127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '198.18.0.1', '224.0.0.1', '255.255.255.255', '::1'];
  const out = JSON.parse(webPhp('-r', `define('_ROOT', '/var/www/html/'); require '/var/www/html/init.php';
    echo json_encode(array_map(fn($ip) => \\App\\SQS\\RemoteFile::publicIp($ip), ${JSON.stringify(ips).replace(/"/g, "'")}));`).trim().split('\n').pop());
  expect(Object.fromEntries(ips.map((ip, i) => [ip, out[i]]))).toEqual(Object.fromEntries(ips.map((ip, i) => [ip, i < 2])));
});
