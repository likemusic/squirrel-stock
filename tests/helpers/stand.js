// Команды внутри web-контейнера стенда (например, cli/localize-db.php).
// Имя контейнера: SQS_WEB_CONTAINER (по умолчанию squirrelstock-web-1).
const { execFileSync } = require('child_process');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';

function webPhp(...args) {
  return execFileSync('docker', ['exec', WEB, 'php', ...args], { encoding: 'utf8' });
}

// Число строк журнала программы (www/logs), где встречается метка: ошибки SQL пишутся туда вместе с текстом запроса
function logCount(mark) {
  return Number(execFileSync('docker', ['exec', WEB, 'sh', '-c', 'cat logs/*.txt 2>/dev/null | grep -cF -- "$1" || true', 'sh', mark], { encoding: 'utf8' }).trim());
}

module.exports = { webPhp, logCount };
