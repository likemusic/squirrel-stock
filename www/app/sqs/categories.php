<?php

namespace App\SQS;

/**
 * SquirrelStock: отбор по категории в отчетах форка — категория вместе со всеми подкатегориями
 * (категория «Расходники» включает «Перевязку»). В Zippy отбор по категории в большинстве отчетов — только сама категория.
 */
class Categories
{
    /**
     * id категории и всех ее подкатегорий; 0 — пустой список (отбора нет)
     */
    public static function withChildren(int $cat_id): array {
        if ($cat_id <= 0) {
            return [];
        }
        $cat = \App\Entity\Category::load($cat_id);
        if ($cat == null) {
            return [$cat_id];
        }
        return array_map('intval', array_merge([$cat_id], $cat->getChildren()));
    }

    /**
     * Условие SQL «{$column} IN (категория и подкатегории)» для отбора; '' — если категория не выбрана
     */
    public static function sqlIn(int $cat_id, string $column = 'i.cat_id'): string {
        $ids = self::withChildren($cat_id);
        return $ids ? "{$column} IN (" . implode(',', $ids) . ')' : '';
    }
}
