// Настройки (таблица options) читаются без восстановления произвольных классов PHP: значение могло прийти из файла
// импорта настроек или из резервной копии. Разрешены массивы, строки, числа и App\DataItem (списки валют, доп. полей,
// источников) — с ними работают страницы настроек, главная и профиль. Проверочный объект — безобидный ArrayObject.
const { test, expect } = require('../fixtures');
const { webPhp } = require('../helpers/stand');
const { query, esc } = require('../helpers/db');

// PHP-код с загрузкой программы (как cli/*.php): печатает JSON
const php = code => JSON.parse(webPhp('-r', `define('_ROOT', '/var/www/html/'); require '/var/www/html/init.php'; ${code}`).trim().split('\n').pop());

const OBJECTS = `function sqs_objects($x, $path, &$out) {
    if (is_object($x)) { $out[] = $path . ': ' . get_class($x); return; }
    if (is_array($x)) foreach ($x as $k => $y) sqs_objects($y, $path . '.' . $k, $out);
  }`;

test('настройки стенда читаются, объекты в них — только App\\DataItem', async () => {
  const found = php(`${OBJECTS}
    $out = [];
    foreach (\\ZDB\\DB::getConnect()->GetCol("select optname from options where optname <> 'version'") as $n) {
      sqs_objects(\\App\\System::getOptions($n, true), $n, $out);
    }
    echo json_encode($out);`);
  expect(found.filter(s => !s.endsWith(': App\\DataItem')), 'другие классы').toEqual([]);
});

test('объект другого класса в настройке не восстанавливается', async ({ uniq }) => {
  const name = 'sqs_probe_' + uniq.toLowerCase();
  const value = webPhp('-r', `define('_ROOT', '/var/www/html/'); require '/var/www/html/init.php';
    $d = new \\App\\DataItem(); $d->code = 'X';
    echo base64_encode(serialize(['obj' => new ArrayObject([1, 2]), 'item' => $d, 'n' => 5]));`).trim().split('\n').pop();
  query(`insert into options (optname, optvalue) values ('${name}', '${esc(value)}')`);
  try {
    const r = php(`$o = \\App\\System::getOptions('${name}', true);
      echo json_encode(['obj' => get_class($o['obj']), 'item' => get_class($o['item']), 'code' => $o['item']->code, 'n' => $o['n']]);`);
    expect(r).toEqual({ obj: '__PHP_Incomplete_Class', item: 'App\\DataItem', code: 'X', n: 5 });
  } finally {
    query(`delete from options where optname = '${name}'`);
  }
});

test('страницы с настройками открываются: главная, настройки, справочник позиций, профиль', async ({ page }) => {
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  for (const [url, sel] of [['/', '#sidebar, nav'], ['/index.php?p=App/Pages/Options', 'form'], ['/index.php?p=App/Pages/Reference/ItemList', '#filter'], ['/index.php?p=App/Pages/UserProfile', 'form']]) {
    await page.goto(url);
    await expect(page.locator(sel).first(), url).toBeAttached();
    expect(await page.locator('body').innerText(), url).not.toMatch(/Fatal|Warning|Exception|Incomplete|помилка|ошибка/i);
  }
  expect(errors).toEqual([]);
});
