<?php
/**
 * Выдача расходников и интервалы выдачи
 */
use App\SQS\Hooks;
use App\SQS\IssueInterval;

return [
    'name' => 'Выдача расходников и интервалы выдачи',
    'menu' => [
        [5, 'ConsumablesIssue', ['uk' => 'Видача витратних матеріалів', 'ru' => 'Выдача расходников'], ['uk' => '', 'ru' => '']],
        [4, 'IssueIntervals', ['uk' => 'Інтервали видачі', 'ru' => 'Интервалы выдачи'], ['uk' => 'Товари', 'ru' => 'Товары']],
    ],
    'register' => function () {
        // интервал выдачи: предупреждение после проведения штатной расходной накладной
        Hooks::on('afterExecute', 'GoodsIssue', [IssueInterval::class, 'afterExecute']);
    },
];
