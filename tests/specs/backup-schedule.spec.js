// Команда для системного планировщика на странице «Резервная копия» (BackupJob::scheduleCommand): путь к php.exe на Windows
// под Apache с mod_php берется не жестко «C:\php\php.exe», а рядом с загруженным php.ini (PHPIniDir) или в PHP_BINDIR;
// на Linux — тот же PHP, что у сайта (php8.2 и т. п.), в Docker — команда для cron хоста. Окружение Windows подставляется
// параметрами: на стенде Linux проверяется только выбор пути и текст команды.
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';

// scheduleCommand с подставленным окружением; files — какие файлы «существуют»
function schedule(env, files = []) {
  const code = `$_SERVER['HTTP_HOST'] = 'localhost'; require 'init.php';
    $e = json_decode($argv[1], true); $f = json_decode($argv[2], true);
    $e['exists'] = fn($p) => in_array($p, $f, true);
    echo json_encode(\\App\\SQS\\BackupJob::scheduleCommand($e), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);`;
  const out = execFileSync('docker', ['exec', '-u', 'www-data', '-w', '/var/www/html', WEB, 'php', '-r', code, JSON.stringify(env), JSON.stringify(files)], { encoding: 'utf8' });
  return JSON.parse(out);
}

const WIN = { family: 'Windows', docker: false, root: 'C:\\SquirrelStock\\www\\' };
const TASK = php => `schtasks /Create /TN "SquirrelStock backup" /SC HOURLY /TR "\\"${php}\\" \\"C:\\SquirrelStock\\www\\cli\\backup.php\\" --if-older=20"`;

test('Windows, Apache с mod_php: php.exe рядом с php.ini, а не C:\\php', () => {
  const r = schedule({ ...WIN, binary: 'C:\\SquirrelStock\\Apache24\\bin\\httpd.exe', ini: 'D:\\php84\\php.ini', bindir: 'C:\\php' }, ['D:\\php84\\php.exe']);
  expect(r.os).toBe('Windows');
  expect(r.cmd).toBe(TASK('D:\\php84\\php.exe'));
});

test('Windows, консоль: PHP_BINARY и есть php.exe', () => {
  const r = schedule({ ...WIN, binary: 'E:\\tools\\php\\php.exe', ini: '', bindir: 'C:\\php' });
  expect(r.cmd).toBe(TASK('E:\\tools\\php\\php.exe'));
});

test('Windows, php.ini не загружен: PHP_BINDIR, иначе php.exe из PATH', () => {
  expect(schedule({ ...WIN, binary: 'C:\\Apache24\\bin\\httpd.exe', ini: '', bindir: 'C:\\php' }, ['C:\\php\\php.exe']).cmd).toBe(TASK('C:\\php\\php.exe'));
  expect(schedule({ ...WIN, binary: 'C:\\Apache24\\bin\\httpd.exe', ini: '', bindir: 'C:\\php' }).cmd).toBe(TASK('php.exe'));
});

test('Linux: PHP сайта (php8.2), под mod_php — из PHP_BINDIR', () => {
  const LNX = { family: 'Linux', docker: false, root: '/var/www/squirrelstock/' };
  expect(schedule({ ...LNX, binary: '/usr/bin/php8.2', ini: '', bindir: '/usr/bin' }).cmd).toBe('17 * * * * /usr/bin/php8.2 /var/www/squirrelstock/cli/backup.php --if-older=20');
  const r = schedule({ ...LNX, binary: '', ini: '/etc/php/8.2/apache2/php.ini', bindir: '/usr/bin' }, ['/usr/bin/php']);
  expect(r.os).toBe('Linux (cron)');
  expect(r.cmd).toBe('17 * * * * /usr/bin/php /var/www/squirrelstock/cli/backup.php --if-older=20');
});

test('Docker: команда для cron хоста; страница показывает ее', async ({ page }) => {
  expect(schedule({ family: 'Linux', docker: true }).cmd).toBe('17 * * * * docker exec -u www-data <web-container> php cli/backup.php --if-older=20');
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await expect(page.locator('#schedule-cmd')).toHaveText('17 * * * * docker exec -u www-data <web-container> php cli/backup.php --if-older=20');
});
