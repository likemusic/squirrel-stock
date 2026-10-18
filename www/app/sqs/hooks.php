<?php

namespace App\SQS;

use App\Entity\Doc\Document;

/**
 * SquirrelStock: точки расширения документа — обработчики по типу документа (meta_name) или для всех ('*').
 * События: beforeSave, afterSave, beforeExecute, afterExecute, beforeCancel, afterCancel, afterDelete (документ уже удален).
 * Обработчик: function (Document $doc, string $event): void. Общие события (не документ) — listen()/emit(). Исключение в before* отменяет сохранение или проведение,
 * текст исключения видит пользователь. Вызовы — в Document::beforeSave/afterSave/updateStatus/afterDelete.
 * Встроенные обработчики форка (журнал правок DocAudit) регистрируются здесь же, модули — в своем module.php (App\SQS\Modules).
 */
class Hooks
{
    public const EVENTS = ['beforeSave', 'afterSave', 'beforeExecute', 'afterExecute', 'beforeCancel', 'afterCancel', 'afterDelete'];
    // общие события, где ошибка обработчика не прерывает страницу: блок модуля пропускается, ошибка — в журнал
    // (нет таблицы модуля — схема не применилась — и главная после входа не открывалась целиком)
    public const SAFE = ['mainBlocks'];

    private static array $handlers = [];
    private static array $listeners = [];   // общие события (не документ): событие => [callable]
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

    /**
     * Общие события — точки расширения не для документа. Обработчик получает аргументы emit() и может вернуть значение:
     *   'mainBlocks'  — () → HTML блока главной страницы (App\Pages\Main) или '';
     *   'itemSaved'   — (Item $item, Page $page, string $oldname) → после сохранения карточки позиции (справочник);
     *   'customerCardUrl' — (int $customer_id) → адрес карточки получателя или '' (имя в журнале выдач, значок в справочнике контрагентов).
     */
    public static function listen(string $event, callable $handler): void {
        self::$listeners[$event][] = $handler;
    }

    /**
     * Вызвать обработчики события; результаты — массивом (null не включается)
     */
    public static function emit(string $event, ...$args): array {
        self::boot();
        $out = [];
        foreach (self::$listeners[$event] ?? [] as $handler) {
            if (in_array($event, self::SAFE, true)) {
                try {
                    $r = $handler(...$args);
                } catch (\Throwable $e) {
                    global $logger;
                    $logger?->error("SquirrelStock {$event}: " . $e->getMessage());
                    continue;
                }
            } else {
                $r = $handler(...$args);
            }
            if ($r !== null) {
                $out[] = $r;
            }
        }
        return $out;
    }

    // встроенные обработчики форка: работают и без модулей
    private static function boot(): void {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        self::on('beforeSave', '*', [DocAudit::class, 'beforeSave']);
        self::on('afterSave', '*', [DocAudit::class, 'afterSave']);
        self::on('afterDelete', '*', [DocAudit::class, 'afterDelete']);
        self::listen('itemSaved', [Item1C::class, 'itemSaved']);
        self::listen('mainBlocks', [Modules::class, 'mainBlock']);
    }
}
