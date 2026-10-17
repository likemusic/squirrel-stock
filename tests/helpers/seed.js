// Подготовка данных теста без интерфейса (fixtures/seed.php): позиции, контрагенты, договоры, проведенные документы.
// Для фона теста; там, где проверяется сама форма, — createItem/createDoc из helpers/zippy.js.
// Формат данных — в шапке seed.php. Документ из seed совпадает с документом из формы: tests/specs/seed-parity.spec.js.
const path = require('path');
const { execFileSync } = require('child_process');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const REMOTE = '/tmp/sqs-seed.php';
let copied = false;

// Возвращает { items: {code: id}, customers: {name: id}, contracts: {number: id}, docs: [{ id, number }] };
// отказ проведения или ошибка — исключение с текстом ядра (документы до отказа остаются проведенными)
function seed(data) {
  if (!copied) {
    execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'seed.php'), `${WEB}:${REMOTE}`]);
    copied = true;
  }
  const out = execFileSync('docker', ['exec', '-i', '-u', 'www-data', WEB, 'php', REMOTE], { input: JSON.stringify(data), encoding: 'utf8' });
  const res = JSON.parse(out.trim().split('\n').pop());
  if (res.error) throw new Error('seed: ' + res.error);
  return res;
}

module.exports = { seed };
