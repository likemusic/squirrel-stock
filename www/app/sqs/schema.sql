-- SquirrelStock: таблицы платформы (вход, журнал правок, названия из 1С) — дополнения форка к базе Zippy (db/db.sql).
-- Программа применяет файл сама, как schema.sql модулей (App\SQS\Modules): при его изменении, после восстановления копии,
-- из cli/modules.php. Операторы должны быть повторяемыми (CREATE TABLE IF NOT EXISTS). Docker-стенд и пустая база пакета
-- (tools/release/build.sh) создают эти таблицы сразу. Таблицы и пункты меню модулей — www/app/sqs/modules/<имя>/ (docs/MODULES.md).

-- Журнал правок документов (App\SQS\DocAudit): что изменилось при сохранении — было, стало, кто, когда
CREATE TABLE IF NOT EXISTS sqs_doclog (
  id bigint NOT NULL AUTO_INCREMENT,
  document_id int NOT NULL,
  user_id int NOT NULL,
  createdon datetime NOT NULL,
  changes text NOT NULL,
  PRIMARY KEY (id),
  KEY document_id (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Позиции из 1С (App\SQS\Item1C): название, как в 1С (печатается в акте списания), и цена за единицу на последнем импорте
-- ведомости — по ней различаются одноименные позиции при повторном импорте
CREATE TABLE IF NOT EXISTS sqs_item1c (
  item_id int NOT NULL,
  name1c varchar(255) NOT NULL,
  price1c decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (item_id),
  KEY name1c (name1c)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Вход по cookie «Запомнить» (App\SQS\Remember): случайный токен; хранится хеш, срок и отпечаток пароля
CREATE TABLE IF NOT EXISTS sqs_remember (
  selector char(24) NOT NULL,
  user_id int NOT NULL,
  token_hash char(64) NOT NULL,
  pass_fp char(64) NOT NULL,
  expires datetime NOT NULL,
  PRIMARY KEY (selector),
  KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Неудачные попытки входа (App\SQS\LoginGuard): временная блокировка подбора пароля по логину и по адресу
CREATE TABLE IF NOT EXISTS sqs_loginfail (
  id bigint NOT NULL AUTO_INCREMENT,
  login varchar(100) NOT NULL,
  ip varchar(45) NOT NULL,
  createdon datetime NOT NULL,
  PRIMARY KEY (id),
  KEY login (login, createdon),
  KEY ip (ip, createdon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Двухфакторный вход (App\SQS\TwoFactor): ключ приложения-аутентификатора, последний принятый шаг; резервные коды — хешами
CREATE TABLE IF NOT EXISTS sqs_2fa (
  user_id int NOT NULL,
  secret varchar(64) NOT NULL,
  enabled tinyint(1) NOT NULL DEFAULT 0,
  last_step bigint NOT NULL DEFAULT 0,
  updated datetime NOT NULL,
  PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS sqs_2fa_backup (
  id int NOT NULL AUTO_INCREMENT,
  user_id int NOT NULL,
  code_hash varchar(255) NOT NULL,
  used tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
