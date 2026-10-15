-- SquirrelStock: дополнения форка к базе Zippy (db/db.sql). Выполнять после db.sql; повторный запуск безопасен.
-- Docker-стенд подключает файл автоматически при создании базы; на существующей базе:
--   mariadb -u<пользователь> -p <база> < db/squirrelstock.sql

-- Отчеты в меню «Звіти → Склад» (названия по-украински, как у остальных пунктов; на русский — cli/localize-db.php ru)
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 2, 'Обладнання: де зараз', 'EquipmentWhere', 'Склад', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 2 AND meta_name = 'EquipmentWhere');

INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 2, 'Журнал видачі обладнання', 'EquipmentJournal', 'Склад', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 2 AND meta_name = 'EquipmentJournal');

-- Сервисные страницы
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

-- Единицы оборудования (App\SQS\Units): заводской номер к инвентарному (серийному номеру Zippy, store_stock.snumber)
CREATE TABLE IF NOT EXISTS sqs_unit (
  item_id int NOT NULL,
  snumber varchar(255) NOT NULL,
  factory_no varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (item_id, snumber),
  KEY factory_no (factory_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Выдача оборудования одной формой: договор + расходная накладная
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 5, 'Видача обладнання за договором', 'EquipmentIssue', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 5 AND meta_name = 'EquipmentIssue');

-- Справочник единиц оборудования: инвентарный и заводской номер, где сейчас
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 4, 'Одиниці обладнання', 'EquipmentUnits', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 4 AND meta_name = 'EquipmentUnits');

-- Импорт истории выдачи оборудования из Google-таблиц (App\SQS\EqHistory); что уже импортировано — по ключу события
-- «вид|договор|единица|дата», чтобы повторный импорт не дублировал
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 5, 'Імпорт історії видачі обладнання', 'ImportEquipmentHistory', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 5 AND meta_name = 'ImportEquipmentHistory');

CREATE TABLE IF NOT EXISTS sqs_eqhistory (
  eventkey varchar(255) NOT NULL,
  document_id int NOT NULL,
  createdon datetime NOT NULL,
  PRIMARY KEY (eventkey)
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
