// Подготовка данных теста без интерфейса (fixtures/seed.php): позиции, контрагенты, договоры, проведенные документы.
// Для фона теста; там, где проверяется сама форма, — createItem/createDoc из helpers/zippy.js.
// Формат данных — в шапке seed.php. Документ из seed совпадает с документом из формы: tests/specs/seed-parity.spec.js.
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const SCRIPT = fs.readFileSync(path.join(__dirname, '..', 'fixtures', 'seed.php'), 'utf8');

// Возвращает { items: {code: id}, customers: {name: id}, contracts: {number: id}, docs: [{ id, number }] };
// отказ проведения или ошибка — исключение с текстом ядра (документы до отказа остаются проведенными).
// Скрипт — на stdin php, данные — аргументом: без docker cp (он ломается, когда у контейнера устарел смонтированный файл)
function seed(data) {
  const out = execFileSync('docker', ['exec', '-i', '-u', 'www-data', WEB, 'php', '--', JSON.stringify(data)], { input: SCRIPT, encoding: 'utf8' });
  const res = JSON.parse(out.trim().split('\n').pop());
  if (res.error) throw new Error('seed: ' + res.error);
  return res;
}

module.exports = { seed };
