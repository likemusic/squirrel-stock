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
