// Точки расширения (ZIPPY_GAPS п. 18): модуль из папки app/sqs/modules регистрирует хуки документа и пункт меню со своей
// страницей. Тест кладет временный модуль в рабочую копию (в dev-стенде app/ смонтирован в контейнер) и удаляет его.
const fs = require('fs');
const path = require('path');
const { test, expect } = require('../fixtures');
const { createItem, createDoc } = require('../helpers/zippy');
const { value, stockQty } = require('../helpers/db');

const MODULES = path.join(__dirname, '../../www/app/sqs/modules');

function writeModule(dir, uniq) {
  const cls = 'ZzHello' + uniq;
  fs.mkdirSync(path.join(dir, 'pages/report'), { recursive: true });
  fs.mkdirSync(path.join(dir, 'templates/pages/report'), { recursive: true });
  fs.writeFileSync(path.join(dir, 'module.php'), `<?php
use App\\SQS\\Hooks;

return [
    'name' => 'Тестовый модуль ${uniq}',
    'menu' => [[2, '${cls}', 'Тест модуля ${uniq}', 'Склад']],
    'register' => function () {
        Hooks::on('beforeExecute', 'OutcomeItem', function ($doc) {
            if (str_contains((string)$doc->notes, 'STOP-HOOK')) {
                throw new \\Exception('Хук модуля: проведение отменено');
            }
        });
        Hooks::on('afterExecute', 'OutcomeItem', function ($doc) {
            $key = 'zz_hook_' . $doc->document_id;
            \\App\\Helper::setKeyVal($key, intval(\\App\\Helper::getKeyVal($key)) + 1);
        });
    },
];
`);
  fs.writeFileSync(path.join(dir, 'pages/report', cls.toLowerCase() + '.php'), `<?php
namespace App\\Pages\\Report;

class ${cls} extends \\App\\Pages\\Base
{
}
`);
  fs.writeFileSync(path.join(dir, 'templates/pages/report', cls.toLowerCase() + '.html'),
    `<html><head><title>Тест модуля</title></head><body><h3 id="zz-hello">Привет из модуля ${uniq}</h3></body></html>\n`);
  return cls;
}

test('модуль: хуки проведения и пункт меню', async ({ page, uniq }) => {
  const dir = path.join(MODULES, 'zz-test-' + uniq.toLowerCase());
  const cls = writeModule(dir, uniq);
  try {
    const code = 'HK-' + uniq;
    await createItem(page, { name: 'Позиция ' + code, code });
    await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code, qty: 5, price: 0 }] });

    // хук до проведения отменяет его: сообщение пользователю, документ не создан, остаток прежний
    const stop = 'STOP-HOOK-' + uniq;
    const err = await createDoc(page, 'OutcomeItem', { date: '2026-10-02', notes: stop, rows: [{ code, qty: 2 }] }).then(() => null, e => e.message);
    expect(err, 'проведение отменено').toContain('Хук модуля: проведение отменено');
    expect(stockQty(code)).toBe(5);
    expect(Number(value(`select count(*) from documents where notes = '${stop}' and state = 5`)), 'проведенного документа нет').toBe(0);

    // хук после проведения — ровно один раз
    const note = 'HOOK-OK-' + uniq;
    await createDoc(page, 'OutcomeItem', { date: '2026-10-02', notes: note, rows: [{ code, qty: 1 }] });
    expect(stockQty(code)).toBe(4);
    const id = value(`select document_id from documents where notes = '${note}' order by document_id desc limit 1`);
    expect(value(`select vald from keyval where keyd = 'zz_hook_${id}'`), 'afterExecute вызван один раз').toBe('1');

    // пункт меню и страница из папки модуля
    await page.goto('/');
    await expect(page.locator(`a[href*="Report/${cls}"]`).first(), 'пункт меню модуля').toBeAttached();
    await page.goto('/index.php?p=App/Pages/Report/' + cls);
    await expect(page.locator('#zz-hello')).toHaveText('Привет из модуля ' + uniq);
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
  // модуль удален — его пункт меню выключен
  await page.goto('/');
  expect(value(`select disabled from metadata where meta_type = 2 and meta_name = '${cls}'`)).toBe('1');
  await expect(page.locator(`a[href*="Report/${cls}"]`)).toHaveCount(0);
});
