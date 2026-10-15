-- SquirrelStock: дополнения форка к базе Zippy (db/db.sql). Выполнять после db.sql; повторный запуск безопасен.
-- Docker-стенд подключает файл автоматически при создании базы; на существующей базе:
--   mariadb -u<пользователь> -p <база> < db/squirrelstock.sql

-- Отчеты в меню «Звіти → Склад» (названия по-украински, как у остальных пунктов; на русский — cli/localize-db.php ru)
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 2, 'Акт списання за місяць', 'IssueAct', 'Склад', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 2 AND meta_name = 'IssueAct');

INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 2, 'Отримувачі по місяцях', 'RecipientMonths', 'Склад', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 2 AND meta_name = 'RecipientMonths');

INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 2, 'Оборот по позиціях', 'ItemTurnover', 'Склад', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 2 AND meta_name = 'ItemTurnover');

INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 2, 'Обладнання: де зараз', 'EquipmentWhere', 'Склад', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 2 AND meta_name = 'EquipmentWhere');

INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 2, 'Журнал видачі обладнання', 'EquipmentJournal', 'Склад', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 2 AND meta_name = 'EquipmentJournal');

INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 2, 'Нижче мінімального залишку', 'BelowMinimum', 'Склад', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 2 AND meta_name = 'BelowMinimum');

-- Сервисные страницы
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 5, 'Імпорт відомості 1С', 'Import1C', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 5 AND meta_name = 'Import1C');

INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 5, 'Резервна копія', 'Backup', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 5 AND meta_name = 'Backup');

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

-- Выдача расходников одной короткой формой (основная ежедневная операция): проведенная расходная накладная по цене 0
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 5, 'Видача витратних матеріалів', 'ConsumablesIssue', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 5 AND meta_name = 'ConsumablesIssue');

-- Выдача оборудования одной формой: договор + расходная накладная
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 5, 'Видача обладнання за договором', 'EquipmentIssue', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 5 AND meta_name = 'EquipmentIssue');

-- Справочник единиц оборудования: инвентарный и заводской номер, где сейчас
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 4, 'Одиниці обладнання', 'EquipmentUnits', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 4 AND meta_name = 'EquipmentUnits');

-- Журнал автоматических резервных копий (App\SQS\BackupJob, cli/backup.php): попытка по каждому месту — папка, сеть, облако
CREATE TABLE IF NOT EXISTS sqs_backuplog (
  id bigint NOT NULL AUTO_INCREMENT,
  place varchar(20) NOT NULL,
  createdon datetime NOT NULL,
  ok tinyint(1) NOT NULL,
  file varchar(255) NOT NULL DEFAULT '',
  size bigint NOT NULL DEFAULT 0,
  seconds int NOT NULL DEFAULT 0,
  message text,
  PRIMARY KEY (id),
  KEY place (place, createdon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
