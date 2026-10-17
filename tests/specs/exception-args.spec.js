// Трассы исключений пишутся в журнал (logs/) целиком: init.php при сбое соединения с базой, index.php, API. С
// zend.exception_ignore_args = Off (образ php:*-apache, часть хостингов) в трассе — аргументы функций: пароль базы из
// connect(), пароль входа через API, значения запросов. init.php включает ignore_args сам, docker/php.ini — тоже.
const { test, expect } = require('../fixtures');
const { webPhp, logCount } = require('../helpers/stand');

test('трасса исключения в журнале без аргументов функций', async ({ uniq }) => {
  const secret = 'Секрет' + uniq, dbPass = 'pw' + uniq;
  const out = webPhp('-r', `
    require "init.php";
    function sqs_test_login($login, $password) { throw new \\Exception("отказ ${uniq}"); }
    try { sqs_test_login("admin", "${secret}"); } catch (\\Throwable $e) { $logger->error($e); }
    // соединение с базой, которая не отвечает, — как в init.php
    \\ZDB\\DB::config("127.0.0.1:1", "nodb", "nouser", "${dbPass}");
    try { \\ZDB\\DB::getConnect(); } catch (\\Throwable $e) { $logger->error($e); }
    echo ini_get("zend.exception_ignore_args");
  `);
  expect(out.trim().slice(-1), 'init.php включает zend.exception_ignore_args').toBe('1');
  expect(logCount('отказ ' + uniq), 'исключение записано').toBeGreaterThan(0);
  expect(logCount(secret), 'аргумента функции в журнале нет').toBe(0);
  expect(logCount(dbPass), 'пароля базы в журнале нет').toBe(0);
});
