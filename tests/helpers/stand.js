// Команды внутри web-контейнера стенда (например, cli/localize-db.php).
// Имя контейнера: SQS_WEB_CONTAINER (по умолчанию squirrelstock-web-1).
const { execFileSync } = require('child_process');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';

function webPhp(...args) {
  return execFileSync('docker', ['exec', WEB, 'php', ...args], { encoding: 'utf8' });
}

module.exports = { webPhp };
