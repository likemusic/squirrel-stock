// Ключи перевода (ZIPPY_GAPS п. 24): новые ключи — только по смыслу, черновые транслит-ключи конвертера — по списку
// tools/i18n/translit-keys.txt; переименование — tools/i18n/rename.php. Стенд не нужен: PHP на машине тестов,
// rename.php — на копии кода во временной папке теста.
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { test, expect } = require('@playwright/test');

const REPO = path.join(__dirname, '..', '..');
const WWW = path.join(REPO, 'www');
const DIRS = ['app', 'templates', 'lang', 'cli'];
const MAP = path.join(__dirname, '..', 'fixtures', 'i18n-rename-map.tsv');

const php = (script, ...args) => {
  const r = spawnSync('php', [path.join(REPO, 'tools', 'i18n', script), ...args], { encoding: 'utf8' });
  if (r.error) throw new Error(`нужен PHP 8.1+ в PATH: ${r.error.message}`);
  return { code: r.status, out: r.stdout + r.stderr };
};

function copyWww(dir) {
  for (const d of DIRS) fs.cpSync(path.join(WWW, d), path.join(dir, d), { recursive: true });
  return dir;
}

// файлы копии, которые отличаются от www
function changed(dir) {
  const out = [];
  const walk = rel => {
    for (const e of fs.readdirSync(path.join(dir, rel), { withFileTypes: true })) {
      const r = path.join(rel, e.name);
      if (e.isDirectory()) walk(r);
      else if (!fs.readFileSync(path.join(dir, r)).equals(fs.readFileSync(path.join(WWW, r)))) out.push(r);
    }
  };
  DIRS.forEach(walk);
  return out.sort();
}

const writeMap = (dir, lines) => {
  const f = path.join(dir, 'map.tsv');
  fs.writeFileSync(f, lines.map(l => l.join('\t')).join('\n') + '\n');
  return f;
};
const json = (dir, f) => JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));

// копия кода — около 10 МБ на тест: после успешного теста не нужна
test.afterEach(({}, info) => {
  if (info.status === info.expectedStatus) fs.rmSync(info.outputDir, { recursive: true, force: true });
});

test('новых транслит-ключей в словарях нет', () => {
  const r = php('check-keys.php');
  expect(r.out, 'новый ключ — по смыслу (docs/DEVELOPMENT.md), не транслит фразы').toMatch(/новых: 0/);
  expect(r.code).toBe(0);
});

test('проверка ключей ловит новый транслит-ключ', () => {
  const dir = copyWww(test.info().outputPath('www'));
  const f = path.join(dir, 'app/sqs/modules/update/lang/uk.json');
  fs.writeFileSync(f, JSON.stringify({ ...json(dir, 'app/sqs/modules/update/lang/uk.json'), 'sqs.update.zberehty_kopiiu': 'Зберегти копію' }, null, 4));
  const r = php('check-keys.php', `--root=${dir}`);
  expect(r.code).toBe(1);
  expect(r.out).toContain('sqs.update.zberehty_kopiiu = Зберегти копію');
});

test('rename.php: 10 ключей туда и обратно — код и словари побайтно как были', () => {
  const dir = copyWww(test.info().outputPath('www'));
  const map = fs.readFileSync(MAP, 'utf8').split('\n').filter(l => l && !l.startsWith('#')).map(l => l.split('\t'));
  expect(map).toHaveLength(10);

  const r = php('rename.php', `--map=${MAP}`, `--root=${dir}`);
  expect(r.code, r.out).toBe(0);
  expect(r.out).toMatch(/ключей: 10, файлов кода: \d+, замен: \d+, склеено: 0/);
  expect(r.out).not.toContain('без замен в коде');
  const uk = json(dir, 'lang/uk.json'), eq = json(dir, 'app/sqs/modules/equipment/lang/uk.json');
  expect(uk['sqs.test_rename.bad_code']).toBe(json(WWW, 'lang/uk.json')['sqs.2fa.bad_code']);
  expect(uk['sqs.2fa.bad_code']).toBeUndefined();
  expect(eq['sqs.test_rename.units_factory']).toBe(json(WWW, 'app/sqs/modules/equipment/lang/uk.json')['sqs.units.factory']);
  // обмен двух имен
  expect(eq['sqs.extend.title'] ?? uk['sqs.extend.title']).toBe(json(WWW, 'lang/uk.json')['sqs.interval.title'] ?? json(WWW, 'app/sqs/modules/equipment/lang/uk.json')['sqs.interval.title']);
  const files = changed(dir);
  expect(files).toEqual(expect.arrayContaining(['lang/uk.json', 'lang/ru.json', 'app/sqs/modules/equipment/lang/uk.json']));
  expect(files.some(f => f.endsWith('.php'))).toBe(true);
  expect(files.some(f => f.endsWith('.html'))).toBe(true);
  for (const f of files.filter(f => !f.endsWith('.json'))) {
    expect(fs.readFileSync(path.join(dir, f), 'utf8'), f).not.toMatch(/[{'"]sqs\.(extend\.link|units\.factory|2fa\.bad_code)\b/);
  }

  const back = writeMap(test.info().outputPath(), map.map(([a, b]) => [b, a]));
  const r2 = php('rename.php', `--map=${back}`, `--root=${dir}`);
  expect(r2.code, r2.out).toBe(0);
  expect(changed(dir)).toEqual([]);
});

test('rename.php: склейка двух ключей с одинаковыми текстами', () => {
  const dir = copyWww(test.info().outputPath('www'));
  const r = php('rename.php', `--map=${writeMap(test.info().outputPath(), [['sqs.2fa.profile_link', 'sqs.2fa.name'], ['sqs.2fa.title', 'sqs.2fa.name']])}`, `--root=${dir}`);
  expect(r.code, r.out).toBe(0);
  expect(r.out).toContain('склеено: 2');   // по ключу в uk.json и ru.json
  const before = json(WWW, 'lang/uk.json'), uk = json(dir, 'lang/uk.json');
  expect(Object.keys(uk)).toHaveLength(Object.keys(before).length - 1);
  expect(uk['sqs.2fa.name']).toBe(before['sqs.2fa.title']);
});

for (const [name, lines, error] of [
  ['дубль в карте', [['sqs.extend.link', 'sqs.a.b'], ['sqs.extend.link', 'sqs.a.c']], 'в карте дважды: sqs.extend.link'],
  ['ключа нет в словарях', [['sqs.no.such', 'sqs.a.b']], 'нет в словарях: sqs.no.such'],
  ['имя занято', [['sqs.extend.link', 'sqs.extend.title']], 'имя занято'],
  ['ключ — начало нового имени', [['sqs.extend.link', 'sqs.extend.title.more']], 'ключ sqs.extend.title — начало нового имени'],
  ['новое имя — начало ключа', [['sqs.extend.link', 'sqs.units']], 'новое имя sqs.units — начало ключа'],
  ['старый ключ собирается в коде из куска', [['sqs.doclog.date', 'sqs.doclog.day']], "ключ собирается из 'sqs.doclog.'"],
  ['новое имя попадает под кусок из кода', [['sqs.extend.link', 'sqs.eqhist.kind_link']], "ключ собирается из 'sqs.eqhist.kind_'"],
  ['склейка разных текстов', [['sqs.units.title', 'sqs.a.title'], ['sqs.extend.title', 'sqs.a.title']], 'склейка в sqs.a.title: разные значения'],
]) {
  test(`rename.php отказывается: ${name}`, () => {
    const dir = copyWww(test.info().outputPath('www'));
    const r = php('rename.php', `--map=${writeMap(test.info().outputPath(), lines)}`, `--root=${dir}`);
    expect(r.code, r.out).toBe(1);
    expect(r.out).toContain(error);
    expect(r.out).toContain('ничего не записано');
    expect(changed(dir)).toEqual([]);
  });
}

test('rename.php отказывается: старый ключ остался вне литерала и {{#t}}', () => {
  const dir = copyWww(test.info().outputPath('www'));
  const f = path.join(dir, 'templates/pages/twofactor.html');
  fs.appendFileSync(f, '\n<span data-key="sqs.2fa.bad_code"></span>\n');
  const r = php('rename.php', `--map=${writeMap(test.info().outputPath(), [['sqs.2fa.bad_code', 'sqs.test_rename.bad_code']])}`, `--root=${dir}`);
  expect(r.code, r.out).toBe(1);
  expect(r.out).toContain('templates/pages/twofactor.html: старый ключ остался: sqs.2fa.bad_code');
  expect(changed(dir)).toEqual(['templates/pages/twofactor.html']);
});
