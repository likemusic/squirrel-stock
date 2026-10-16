// Ошибка базы данных (App\SQS\DbException вместо ADODB_Exception, init.php): пользователь видит общее сообщение на языке
// интерфейса, текст ошибки СУБД и запрос — только в журнале logs/. Запрос к несуществующей таблице — в контейнере стенда.
const { test, expect } = require('../fixtures');
const { webPhp, logCount } = require('../helpers/stand');

test('ошибка базы: сообщение без текста запроса, подробности в журнале', async ({ uniq }) => {
  const table = 'sqs_no_table_' + uniq.toLowerCase();
  const out = JSON.parse(webPhp('-r', `
    require "init.php";
    try { \\ZDB\\DB::getConnect()->Execute("select * from ${table}"); echo "{}"; }
    catch (\\Throwable $e) { echo json_encode(["class" => get_class($e), "message" => $e->getMessage(), "sql" => $e->sql, "ado" => $e instanceof \\ADODB_Exception]); }
  `).replace(/^[^{]*/, ''));
  expect(out.ado, 'исключение по-прежнему ADODB_Exception').toBe(true);
  expect(out.class).toBe('App\\SQS\\DbException');
  expect(out.message).not.toContain(table);
  expect(out.message).toMatch(/logs/);
  expect(out.sql, 'запрос остается в исключении для кода, который его разбирает').toContain(table);
  expect(logCount(table), 'полный текст в журнале').toBeGreaterThan(0);
});
