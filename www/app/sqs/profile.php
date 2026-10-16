<?php

namespace App\SQS;

/**
 * SquirrelStock: профиль благотворительной организации (cli/charity-profile.php) — флаг в общих настройках.
 * По нему главная скрывает денежные плитки и графики, журнал выдач открывается со статусом «Все»,
 * печатные формы берутся из templates/printforms/charity/ модулей (App\Report). Настройки кэшируются в сессии.
 */
class Profile
{
    public const OPTION = 'sqs_charity';

    public static function charity(): bool {
        return intval(\App\System::getOption('common', self::OPTION)) === 1;
    }
}
