<?php
/**
 * Резервная копия: вручную, по расписанию в несколько мест, на флешку, восстановление
 */
use App\SQS\Hooks;
use App\SQS\BackupJob;

return [
    'name' => 'Резервная копия',
    'menu' => [
        [5, 'Backup', ['uk' => 'Резервна копія', 'ru' => 'Резервная копия'], ['uk' => '', 'ru' => '']],
    ],
    'register' => function () {
        // предупреждение на главной: нет удачной копии дольше 2 суток (администратору)
        Hooks::listen('mainBlocks', [BackupJob::class, 'mainBlock']);
    },
];
