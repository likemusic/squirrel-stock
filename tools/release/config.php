<?php
// SquirrelStock: config.php для установки на хостинг или свой сервер. Заполните параметры базы данных.

$_config = array('common'=>[],'db'=>[],'smtp'=>[]) ;
$_config['common']['loglevel'] = 300  ;//DEBUG = 100,INFO = 200,WARNING = 300,ERROR = 400;
$_config['common']['timezone'] = 'Europe/Minsk';
$_config['common']['lang'] = 'ru';   // язык интерфейса по умолчанию: ru, uk (файлы lang/*.json); у пользователя — в профиле

$_config['db']['host'] = 'localhost';
$_config['db']['name'] = 'DB_NAME';
$_config['db']['user'] = 'DB_USER';
$_config['db']['pass'] = 'DB_PASS';

$_config['smtp']['usesmtp'] = false ;
$_config['smtp']['host'] = '' ;
$_config['smtp']['port'] = 587 ;
$_config['smtp']['user'] = '' ;
$_config['smtp']['emailfrom'] = '' ;
$_config['smtp']['pass'] = '' ;
$_config['smtp']['tls'] = true ;
