// Точки расширения (ZIPPY_GAPS п. 18): модуль из папки app/sqs/modules регистрирует хуки документа и пункт меню со своей
// страницей. Тест кладет временный модуль в рабочую копию (в dev-стенде app/ смонтирован в контейнер) и удаляет его.
const fs = require('fs');
const path = require('path');
const { test, expect } = require('../fixtures');
const { createDoc } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { query, value, stockQty } = require('../helpers/db');

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
    seed({ items: [{ name: 'Позиция ' + code, code }], docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 5, price: 0 }] }] });

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
  query(`delete from metadata where meta_name = '${cls}'`);   // после себя — без строки меню
});

// Механизм модулей целиком: таблицы из schema.sql, словарь модуля, название пункта меню по языку, печатная форма и блок
// главной из папки модуля; выключенный модуль (папка с точкой) не роняет страницы, его пункт выключается и при возврате
// модуля включается снова; профиль благотворительной организации пункты модулей не выключает.
test('модуль: таблицы, словарь, печатная форма, блок главной, выключение', async ({ page, uniq }) => {
  const { webPhp } = require('../helpers/stand');
  const u = uniq.toLowerCase(), cls = 'ZzMech' + uniq, table = 'zz_mech_' + u;
  const dir = path.join(MODULES, 'zz-test-mech-' + u), off = path.join(MODULES, '.zz-test-mech-' + u);
  fs.mkdirSync(path.join(dir, 'pages/report'), { recursive: true });
  fs.mkdirSync(path.join(dir, 'templates/pages/report'), { recursive: true });
  fs.mkdirSync(path.join(dir, 'templates/printforms/zz'), { recursive: true });
  fs.mkdirSync(path.join(dir, 'lang'), { recursive: true });
  fs.writeFileSync(path.join(dir, 'module.php'), `<?php
use App\\SQS\\Hooks;

return [
    'name' => 'Механизм ${uniq}',
    'menu' => [[2, '${cls}', ['uk' => 'Механізм ${uniq}', 'ru' => 'Механизм ${uniq}'], 'Склад']],
    'register' => function () {
        Hooks::listen('mainBlocks', fn() => '<div id="zz-block-${u}">' . __('zz.mech.${u}') . '</div>');
    },
];
`);
  fs.writeFileSync(path.join(dir, 'schema.sql'), `-- таблица модуля\nCREATE TABLE IF NOT EXISTS ${table} (id int NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB;\n`);
  fs.writeFileSync(path.join(dir, 'lang/uk.json'), JSON.stringify({ ['zz.mech.' + u]: 'Текст модуля ' + uniq }));
  fs.writeFileSync(path.join(dir, 'templates/printforms/zz', u + '.tpl'), '<b id="zz-print">{{#t}}zz.mech.' + u + '{{/t}} {{n}}</b>');
  fs.writeFileSync(path.join(dir, 'pages/report', cls.toLowerCase() + '.php'), `<?php
namespace App\\Pages\\Report;

class ${cls} extends \\App\\Pages\\Base
{
    public function __construct() {
        parent::__construct();
        $this->_tvars['print'] = (new \\App\\Report('zz/${u}.tpl'))->generate(['n' => 42]);
    }
}
`);
  fs.writeFileSync(path.join(dir, 'templates/pages/report', cls.toLowerCase() + '.html'), '<html><head><title>t</title></head><body>{{{print}}}</body></html>\n');
  try {
    await page.goto('/');
    await expect(page.locator('#zz-block-' + u), 'блок главной из модуля, текст из словаря модуля').toHaveText('Текст модуля ' + uniq);
    expect(value(`select count(*) from information_schema.tables where table_schema = database() and table_name = '${table}'`), 'таблица из schema.sql').toBe('1');
    expect(value(`select description from metadata where meta_type = 2 and meta_name = '${cls}'`), 'название на языке установки').toBe('Механізм ' + uniq);
    await page.goto('/index.php?p=App/Pages/Report/' + cls);
    await expect(page.locator('#zz-print'), 'печатная форма из модуля').toHaveText('Текст модуля ' + uniq + ' 42');

    // перевод значений в базе: название пункта модуля — из его module.php
    webPhp('cli/localize-db.php', 'ru');
    try {
      expect(value(`select description from metadata where meta_type = 2 and meta_name = '${cls}'`), 'localize-db ru').toBe('Механизм ' + uniq);
    } finally {
      webPhp('cli/localize-db.php', 'ru', '--back');
    }
    expect(value(`select description from metadata where meta_type = 2 and meta_name = '${cls}'`)).toBe('Механізм ' + uniq);

    // профиль благотворительной организации не выключает пункт модуля
    webPhp('cli/charity-profile.php');
    try {
      expect(value(`select disabled from metadata where meta_type = 2 and meta_name = '${cls}'`)).toBe('0');
    } finally {
      webPhp('cli/charity-profile.php', '--back');   // при падении профиль не должен остаться включенным для следующих тестов
    }

    // выключение: папка с точкой — страницы открываются, пункт выключен; возвращение — включен снова
    fs.renameSync(dir, off);
    await page.goto('/');
    await expect(page.locator('#zz-block-' + u)).toHaveCount(0);
    await expect(page.locator('a[href*="Report/Main"], #sidebar').first()).toBeAttached();
    expect(value(`select disabled from metadata where meta_type = 2 and meta_name = '${cls}'`)).toBe('1');
    await page.goto('/index.php?p=App/Pages/Doc/GoodsIssue');
    await expect(page.locator('#docform')).toBeAttached();
    fs.renameSync(off, dir);
    await page.goto('/');
    expect(value(`select disabled from metadata where meta_type = 2 and meta_name = '${cls}'`), 'модуль вернулся').toBe('0');
  } finally {
    for (const d of [dir, off]) fs.rmSync(d, { recursive: true, force: true });
    query(`drop table if exists ${table}`);
    await page.goto('/');   // синхронизация: пункт удаленного модуля выключен
    // после себя — без строк меню и отпечатка схемы (иначе страница настройки меню показывает украинское название в ru)
    query(`delete from metadata where meta_name = '${cls}'; delete from keyval where keyd = 'sqs_schema_zz-test-mech-${u}'`);
  }
});
