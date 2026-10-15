<?php

namespace App\SQS;

use App\Entity\Doc\Document;

/**
 * SquirrelStock: точки расширения документа — обработчики по типу документа (meta_name) или для всех ('*').
 * События: beforeSave, afterSave, beforeExecute, afterExecute, beforeCancel, afterCancel.
 * Обработчик: function (Document $doc, string $event): void. Исключение в before* отменяет сохранение или проведение,
 * текст исключения видит пользователь. Вызовы — в Document::beforeSave/afterSave/updateStatus.
 * Встроенные обработчики форка (журнал правок DocAudit, интервал выдачи IssueInterval) регистрируются здесь же, модули — в своем module.php (App\SQS\Modules).
 */
class Hooks
{
    public const EVENTS = ['beforeSave', 'afterSave', 'beforeExecute', 'afterExecute', 'beforeCancel', 'afterCancel'];

    private static array $handlers = [];
    private static bool $booted = false;

    public static function on(string $event, string $docType, callable $handler): void {
        if (!in_array($event, self::EVENTS, true)) {
            throw new \InvalidArgumentException('Unknown document event: ' . $event);
        }
        self::$handlers[$event][$docType][] = $handler;
    }

    public static function run(string $event, Document $doc): void {
        self::boot();
        $type = (string)($doc->meta_name ?? '');
        foreach (array_merge(self::$handlers[$event]['*'] ?? [], $type !== '' ? (self::$handlers[$event][$type] ?? []) : []) as $handler) {
            $handler($doc, $event);
        }
    }

    // встроенные обработчики форка: работают и без модулей
    private static function boot(): void {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        self::on('beforeSave', '*', [DocAudit::class, 'beforeSave']);
        self::on('afterSave', '*', [DocAudit::class, 'afterSave']);
        self::on('afterExecute', 'GoodsIssue', [IssueInterval::class, 'afterExecute']);   // интервал выдачи: предупреждение
    }
}
