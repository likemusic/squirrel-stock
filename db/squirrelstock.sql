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

-- Сервисные страницы
INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled)
SELECT 5, 'Імпорт відомості 1С', 'Import1C', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM metadata WHERE meta_type = 5 AND meta_name = 'Import1C');
